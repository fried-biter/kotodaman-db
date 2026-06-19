<?php
if (!defined('ABSPATH')) exit;

class Koto_Ocr_Openrouter_Vlm implements Koto_Ocr_Backend_Interface
{
    private $api_key;
    private $model;
    private $timeout;

    public function __construct($api_key, $model, $timeout)
    {
        $this->api_key = (string) $api_key;
        $this->model = (string) $model;
        $this->timeout = (int) $timeout;
    }

    public function get_name()
    {
        return 'openrouter-vlm-structured';
    }

    public function get_model()
    {
        return $this->model;
    }

    public function recognize(array $images)
    {
        if ($this->api_key === '') {
            return new WP_Error('koto_ocr_no_api_key', 'OpenRouter APIキーが設定されていません。');
        }

        if (count($images) > 1) {
            return $this->recognize_in_chunks($images, 1);
        }

        return $this->recognize_batch($images, 1);
    }

    private function recognize_in_chunks(array $images, $chunk_size)
    {
        $merged_images = [];
        $warnings = [];
        $usage = [
            'prompt_tokens' => 0,
            'completion_tokens' => 0,
            'total_tokens' => 0,
            'cost' => 0.0,
            'currency' => 'USD',
            'requests' => 0,
        ];
        $first_image_index = 1;
        foreach (array_chunk($images, $chunk_size) as $chunk) {
            $payload = $this->recognize_batch($chunk, $first_image_index);
            if (is_wp_error($payload)) {
                foreach (array_values($chunk) as $local_index => $_image) {
                    $source_image = 'image_' . ($first_image_index + $local_index);
                    $merged_images[] = [
                        'source_image' => $source_image,
                        'screen_type' => 'unknown',
                        'fullText' => '',
                        'blocks' => [],
                    ];
                    $warnings[] = koto_ocr_warning('ocr', 'image_recognition_failed', $source_image . ': ' . $payload->get_error_message());
                }
                $first_image_index += count($chunk);
                continue;
            }
            if (empty($payload['images']) || !is_array($payload['images'])) {
                foreach (array_values($chunk) as $local_index => $_image) {
                    $source_image = 'image_' . ($first_image_index + $local_index);
                    $merged_images[] = [
                        'source_image' => $source_image,
                        'screen_type' => 'unknown',
                        'fullText' => '',
                        'blocks' => [],
                    ];
                    $warnings[] = koto_ocr_warning('ocr', 'image_payload_missing', $source_image . ': OpenRouter応答にimages配列がありません。');
                }
                $first_image_index += count($chunk);
                continue;
            }
            if (!empty($payload['_openrouter_usage']) && is_array($payload['_openrouter_usage'])) {
                $usage = $this->merge_usage($usage, $payload['_openrouter_usage']);
            }
            foreach (array_values($payload['images']) as $local_index => $image_payload) {
                if (!is_array($image_payload)) {
                    $image_payload = [];
                }
                $image_payload['source_image'] = 'image_' . ($first_image_index + $local_index);
                $merged_images[] = $image_payload;
            }
            $first_image_index += count($chunk);
        }

        $payload = ['images' => $merged_images];
        if (!empty($warnings)) {
            $payload['warnings'] = $warnings;
        }
        if ($usage['requests'] > 0) {
            $payload['_openrouter_usage'] = $usage;
        }
        return $payload;
    }

    private function merge_usage(array $total, array $usage)
    {
        foreach (['prompt_tokens', 'completion_tokens', 'total_tokens'] as $key) {
            $total[$key] = (int) ($total[$key] ?? 0) + (int) ($usage[$key] ?? 0);
        }
        $total['cost'] = (float) ($total['cost'] ?? 0) + (float) ($usage['cost'] ?? 0);
        $total['requests'] = (int) ($total['requests'] ?? 0) + 1;
        if (!empty($usage['currency'])) {
            $total['currency'] = (string) $usage['currency'];
        }
        return $total;
    }

    private function recognize_batch(array $images, $first_image_index)
    {

        $content = [
            ['type' => 'text', 'text' => $this->build_prompt(count($images), $first_image_index)],
        ];

        foreach ($images as $image) {
            $bytes = file_get_contents($image['path']);
            if ($bytes === false) {
                return new WP_Error('koto_ocr_file_read_failed', '画像ファイルを読み取れませんでした。');
            }
            $content[] = [
                'type' => 'image_url',
                'image_url' => [
                    'url' => 'data:' . $image['mime_type'] . ';base64,' . base64_encode($bytes),
                ],
            ];
        }

        $request = [
            'timeout' => $this->timeout,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
                'HTTP-Referer' => home_url('/'),
                'X-Title' => 'Kotodaman DB OCR Draft',
            ],
            'body' => wp_json_encode([
                'model' => $this->model,
                'temperature' => 0,
                'response_format' => ['type' => 'json_object'],
                'messages' => [[
                    'role' => 'user',
                    'content' => $content,
                ]],
            ], JSON_UNESCAPED_UNICODE),
        ];

        $response = null;
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $response = wp_remote_post('https://openrouter.ai/api/v1/chat/completions', $request);
            if (!is_wp_error($response) || $response->get_error_code() !== 'http_request_failed') {
                break;
            }
        }

        if (is_wp_error($response)) {
            return $response;
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $body = (string) wp_remote_retrieve_body($response);
        if ($status < 200 || $status >= 300) {
            $decoded_error = json_decode($body, true);
            $message = $decoded_error['error']['message'] ?? $decoded_error['message'] ?? mb_substr($body, 0, 300);
            return new WP_Error('koto_ocr_openrouter_error', 'OpenRouter APIエラー: HTTP ' . $status . ' ' . $message, ['status' => $status]);
        }

        $decoded = json_decode($body, true);
        $text = $decoded['choices'][0]['message']['content'] ?? '';
        if (!is_string($text) || trim($text) === '') {
            return new WP_Error('koto_ocr_empty_response', 'OpenRouter応答からOCR JSONを取得できませんでした。');
        }

        $payload = $this->decode_json_content($text);
        if (!is_array($payload)) {
            return new WP_Error('koto_ocr_json_parse_failed', 'OCR JSONの解析に失敗しました: ' . mb_substr($text, 0, 300), ['json_error' => json_last_error_msg(), 'raw_content_preview' => mb_substr($text, 0, 2000)]);
        }
        $payload = $this->normalize_decoded_payload($payload);

        if (!empty($decoded['usage']) && is_array($decoded['usage'])) {
            $payload['_openrouter_usage'] = $this->normalize_usage($decoded['usage']);
        }

        if (koto_ocr_debug_enabled()) {
            $payload['_debug_openrouter_response'] = $body;
        }

        return $payload;
    }

    private function normalize_usage(array $usage)
    {
        return [
            'prompt_tokens' => (int) ($usage['prompt_tokens'] ?? 0),
            'completion_tokens' => (int) ($usage['completion_tokens'] ?? 0),
            'total_tokens' => (int) ($usage['total_tokens'] ?? 0),
            'cost' => (float) ($usage['cost'] ?? $usage['total_cost'] ?? 0),
            'currency' => (string) ($usage['currency'] ?? 'USD'),
        ];
    }

    private function decode_json_content($text)
    {
        $text = trim((string) $text);
        $text = preg_replace('/^\xEF\xBB\xBF/u', '', $text);

        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            return $decoded;
        }
        $escaped_text = $this->escape_control_chars_in_json_strings($text);
        if ($escaped_text !== $text) {
            $decoded = json_decode($escaped_text, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/su', $text, $m)) {
            $fenced = trim($m[1]);
            $decoded = json_decode($fenced, true);
            if (!is_array($decoded)) {
                $decoded = json_decode($this->escape_control_chars_in_json_strings($fenced), true);
            }
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $slice = substr($text, $start, $end - $start + 1);
            $decoded = json_decode($slice, true);
            if (!is_array($decoded)) {
                $decoded = json_decode($this->escape_control_chars_in_json_strings($slice), true);
            }
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $balanced = $this->extract_first_balanced_json($text);
        if ($balanced !== '') {
            $decoded = json_decode($balanced, true);
            if (!is_array($decoded)) {
                $decoded = json_decode($this->escape_control_chars_in_json_strings($balanced), true);
            }
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    private function escape_control_chars_in_json_strings($text)
    {
        $result = '';
        $in_string = false;
        $escaped = false;
        $length = strlen($text);
        for ($i = 0; $i < $length; $i++) {
            $char = $text[$i];
            if ($in_string) {
                if ($escaped) {
                    $result .= $char;
                    $escaped = false;
                    continue;
                }
                if ($char === '\\') {
                    $result .= $char;
                    $escaped = true;
                    continue;
                }
                if ($char === '"') {
                    $result .= $char;
                    $in_string = false;
                    continue;
                }
                if ($char === "\n") {
                    $result .= '\\n';
                    continue;
                }
                if ($char === "\r") {
                    $result .= '\\n';
                    continue;
                }
                if ($char === "\t") {
                    $result .= '\\t';
                    continue;
                }
                if (ord($char) < 32) {
                    continue;
                }
            } elseif ($char === '"') {
                $in_string = true;
            }
            $result .= $char;
        }
        return $result;
    }

    private function extract_first_balanced_json($text)
    {
        $start_object = strpos($text, '{');
        $start_array = strpos($text, '[');
        if ($start_object === false && $start_array === false) {
            return '';
        }
        if ($start_object === false) {
            $start = $start_array;
        } elseif ($start_array === false) {
            $start = $start_object;
        } else {
            $start = min($start_object, $start_array);
        }

        $stack = [];
        $in_string = false;
        $escaped = false;
        $length = strlen($text);
        for ($i = $start; $i < $length; $i++) {
            $char = $text[$i];
            if ($in_string) {
                if ($escaped) {
                    $escaped = false;
                } elseif ($char === '\\') {
                    $escaped = true;
                } elseif ($char === '"') {
                    $in_string = false;
                }
                continue;
            }

            if ($char === '"') {
                $in_string = true;
            } elseif ($char === '{') {
                $stack[] = '}';
            } elseif ($char === '[') {
                $stack[] = ']';
            } elseif ($char === '}' || $char === ']') {
                $expected = array_pop($stack);
                if ($expected !== $char) {
                    return '';
                }
                if (empty($stack)) {
                    return substr($text, $start, $i - $start + 1);
                }
            }
        }

        return '';
    }

    private function normalize_decoded_payload(array $payload)
    {
        if (isset($payload['images']) && is_array($payload['images'])) {
            return $payload;
        }
        if (isset($payload[0]) && is_array($payload[0]) && isset($payload[0]['images']) && is_array($payload[0]['images'])) {
            return $payload[0];
        }
        if (isset($payload[0]) && is_array($payload[0]) && (isset($payload[0]['fullText']) || isset($payload[0]['full_text']))) {
            return ['images' => $payload];
        }
        return $payload;
    }

    private function build_prompt($image_count, $first_image_index)
    {
        $labels = [];
        for ($i = 0; $i < $image_count; $i++) {
            $labels[] = 'image_' . ($first_image_index + $i);
        }
        $source_rule = 'source_image は添付順に ' . implode(', ', $labels) . ' だけを使ってください。';

        return implode("\n", [
            'あなたは日本語ゲーム「コトダマン」のスクリーンショット専用OCRです。翻訳、要約、推測、ゲーム知識による補完は禁止です。',
            '返答はJSON objectのみ。Markdown、コードフェンス、前後説明、コメント、省略記号、末尾の補足文は禁止です。JSONは一度だけ閉じ、同じ } や ] を繰り返さないでください。読めない値は空文字または空配列にしてください。',
            $source_rule,
            'DB field候補やspec_jsonは作らず、次のschemaだけで返してください: {"images":[{"source_image":"' . $labels[0] . '","screen_type":"main","fullText":"","blocks":[{"region":"main_name_text","text":""}]}]}',
            '許可するscreen_type: main, waza, sugowaza, trait, blessing, leader, kotowaza, EX_skill, charge_skill, unknown。',
            '各画像は必ず fullText と blocks を持ちます。blocks は region と text のみを含め、bbox/boxや余分なキーは出力しないでください。',
            'JSON文字列内の改行は必ず \\n としてエスケープしてください。fullText は全文転記ではなく重要block.textの短い連結にし、最大300文字まで。各 block.text も最大300文字まで。長い説明文は文の区切りで短く切ってください。',
            'main画面では main_name_text, main_attribute_icon, main_species_icon, main_char_ball, main_waza_preview, main_sugowaza_preview を可能な限り分けてください。属性/種族アイコンは 火/水/木/光/闇/冥/天/虹 と 龍/神/魔/獣/物/英/霊/妖 の1文字でもよいので省略しないでください。',
            'modal画面では modal_header_title, modal_body, modal_trigger を分けてください。trait画面では trait_body と、文字変換/文字追加が読める場合は trait_available_moji を分けてください。blessing画面では blessing_body を使ってください。blocksは最大6個までです。',
            '濁点、半濁点、小書きかな、長音、祓/祝など似た文字を特に注意して、読めた文字だけを書いてください。',
        ]);
    }
}
