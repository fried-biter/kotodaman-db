<?php
if (!defined('ABSPATH')) exit;

function koto_ocr_live_parse_args(array $argv)
{
    $args = [
        'input' => get_stylesheet_directory() . '/local-dev/seed/ocr-fixtures/live-openrouter/input',
        'out' => get_stylesheet_directory() . '/local-dev/seed/ocr-fixtures/live-openrouter',
        'refresh' => false,
    ];
    foreach ($argv as $arg) {
        if (strpos($arg, 'input=') === 0) {
            $args['input'] = substr($arg, strlen('input='));
        } elseif (strpos($arg, 'out=') === 0) {
            $args['out'] = substr($arg, strlen('out='));
        } elseif ($arg === 'refresh=1') {
            $args['refresh'] = true;
        }
    }
    return $args;
}

function koto_ocr_live_cases($input_dir)
{
    if (!is_dir($input_dir)) {
        return new WP_Error('koto_ocr_live_missing_input_dir', '入力ディレクトリがありません: ' . $input_dir);
    }

    $case_dirs = glob(rtrim($input_dir, '/') . '/*', GLOB_ONLYDIR) ?: [];
    $cases = [];
    foreach ($case_dirs as $case_dir) {
        if (!is_readable($case_dir . '/spec.json')) {
            continue;
        }
        if (empty(koto_ocr_live_case_images($case_dir))) {
            continue;
        }
        $cases[] = basename($case_dir);
    }
    sort($cases, SORT_NATURAL);

    if (empty($cases)) {
        return new WP_Error('koto_ocr_live_no_cases', 'spec.jsonと画像を持つOCR caseがありません: ' . $input_dir);
    }

    return $cases;
}

function koto_ocr_live_backend_hash()
{
    return hash_file('sha256', __DIR__ . '/../../lib/acf/ocr/backends/openrouter-vlm.php');
}

function koto_ocr_live_json_read($path)
{
    if (!is_readable($path)) {
        return null;
    }
    $decoded = json_decode((string) file_get_contents($path), true);
    return is_array($decoded) ? $decoded : null;
}

function koto_ocr_live_json_write($path, array $value)
{
    $dir = dirname($path);
    if (!is_dir($dir) && !wp_mkdir_p($dir)) {
        return new WP_Error('koto_ocr_live_mkdir_failed', '出力ディレクトリを作成できません: ' . $dir);
    }
    $json = wp_json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if (file_put_contents($path, $json . "\n") === false) {
        return new WP_Error('koto_ocr_live_write_failed', 'JSONを書き込めません: ' . $path);
    }
    return true;
}

function koto_ocr_live_case_images($case_dir)
{
    $paths = glob(rtrim($case_dir, '/') . '/*.{png,PNG,jpg,JPG,jpeg,JPEG,webp,WEBP}', GLOB_BRACE) ?: [];
    sort($paths, SORT_NATURAL);
    $images = [];
    foreach ($paths as $path) {
        $mime = wp_check_filetype($path)['type'] ?? '';
        if ($mime === '' && function_exists('mime_content_type')) {
            $mime = (string) mime_content_type($path);
        }
        if (!in_array($mime, koto_ocr_allowed_mime_types(), true)) {
            continue;
        }
        $images[] = [
            'source_image' => basename($path),
            'mime_type' => $mime,
            'path' => $path,
        ];
    }
    return $images;
}

function koto_ocr_live_recording_is_current(array $recording, $model, $backend_hash)
{
    $meta = $recording['metadata'] ?? [];
    return ($meta['model'] ?? '') === $model && ($meta['backend_source_hash'] ?? '') === $backend_hash;
}

function koto_ocr_live_record_case($case, array $args, Koto_Ocr_Openrouter_Vlm $backend, $model, $backend_hash)
{
    $case_dir = rtrim($args['input'], '/') . '/' . $case;
    $recording_path = rtrim($args['out'], '/') . '/recordings/' . $case . '.json';
    $cached = koto_ocr_live_json_read($recording_path);
    if (!$args['refresh'] && $cached && koto_ocr_live_recording_is_current($cached, $model, $backend_hash)) {
        return ['recording' => $cached, 'from_cache' => true];
    }

    if (!is_dir($case_dir)) {
        return new WP_Error('koto_ocr_live_missing_case_dir', '入力ディレクトリがありません: ' . $case_dir);
    }
    $images = koto_ocr_live_case_images($case_dir);
    if (empty($images)) {
        return new WP_Error('koto_ocr_live_no_images', 'OCR対象画像がありません: ' . $case_dir);
    }

    $payload = $backend->recognize($images);
    $recording = [
        'metadata' => [
            'case' => $case,
            'backend' => $backend->get_name(),
            'model' => $model,
            'backend_source_hash' => $backend_hash,
            'recorded_at' => current_time('mysql'),
            'image_count' => count($images),
            'images' => array_map(function ($image) {
                return ['source_image' => $image['source_image'], 'mime_type' => $image['mime_type'], 'bytes' => filesize($image['path'])];
            }, $images),
        ],
    ];
    if (is_wp_error($payload)) {
        $recording['error'] = [
            'code' => $payload->get_error_code(),
            'message' => $payload->get_error_message(),
            'data' => $payload->get_error_data(),
        ];
    } else {
        $recording['payload'] = $payload;
    }

    $written = koto_ocr_live_json_write($recording_path, $recording);
    if (is_wp_error($written)) {
        return $written;
    }
    return ['recording' => $recording, 'from_cache' => false];
}

function koto_ocr_live_expected_attack_summary(array $skill)
{
    foreach (($skill['variations'] ?? []) as $variation) {
        foreach (($variation['timelines'] ?? []) as $timeline) {
            if (($timeline['type'] ?? '') !== 'attack') {
                continue;
            }
            return [
                'waza_target' => $timeline['target']['main'] ?? '',
                'attack_prefix' => koto_ocr_live_attack_prefix_from_value((float) ($timeline['value'] ?? 0)),
            ];
        }
    }
    return [];
}

function koto_ocr_live_attack_prefix_from_value($value)
{
    if ($value >= 10) {
        return 'most_strong';
    }
    if ($value >= 7) {
        return 'very_strong';
    }
    if ($value > 0) {
        return 'strong';
    }
    return 'none';
}

function koto_ocr_live_expected_condition_summary(array $spec)
{
    $start_chars = [];
    $char_count = null;
    $combo = null;

    foreach (($spec['sugowaza']['condition'] ?? []) as $group) {
        foreach (($group['conditions'] ?? []) as $condition) {
            $values = $condition['values'] ?? [];
            if (($condition['type'] ?? '') === 'start_char' && empty($start_chars)) {
                $start_chars = array_map('strval', $values);
            } elseif (($condition['type'] ?? '') === 'char_count') {
                foreach ($values as $value) {
                    $char_count = max((int) $value, (int) $char_count);
                }
            } elseif (($condition['type'] ?? '') === 'combo') {
                foreach ($values as $value) {
                    $combo = max((int) $value, (int) $combo);
                }
            }
        }
    }

    $rows = [];
    if (!empty($start_chars)) {
        $rows[] = ['sugo_cond_type' => 'start_char', 'sugo_cond_val' => implode(',', $start_chars)];
    }
    if ($char_count !== null) {
        $rows[] = ['sugo_cond_type' => 'char_count', 'sugo_cond_val' => (string) $char_count];
    }
    if ($combo !== null) {
        $rows[] = ['sugo_cond_type' => 'combo', 'sugo_cond_val' => (string) $combo];
    }
    return $rows;
}

function koto_ocr_live_expected_from_spec(array $spec)
{
    return [
        'name' => html_entity_decode((string) ($spec['name'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        'attribute' => $spec['attribute'] ?? '',
        'species' => $spec['species'] ?? '',
        'chars' => array_values(array_map(function ($char) {
            return (string) ($char['val'] ?? '');
        }, $spec['chars'] ?? [])),
        'waza_name' => $spec['waza']['name'] ?? '',
        'waza_attack' => koto_ocr_live_expected_attack_summary($spec['waza'] ?? []),
        'sugowaza_name' => $spec['sugowaza']['name'] ?? '',
        'sugowaza_condition' => koto_ocr_live_expected_condition_summary($spec),
        'sugowaza_attack' => koto_ocr_live_expected_attack_summary($spec['sugowaza'] ?? []),
    ];
}

function koto_ocr_live_fixture_expected_by_case($input_dir, array $cases)
{
    $by_case = [];
    foreach ($cases as $case) {
        $spec_path = rtrim($input_dir, '/') . '/' . $case . '/spec.json';
        $spec = koto_ocr_live_json_read($spec_path);
        if (!$spec) {
            continue;
        }
        $by_case[$case] = [
            'expected' => koto_ocr_live_expected_from_spec($spec),
            'golden_spec_path' => $spec_path,
        ];
    }
    return $by_case;
}

function koto_ocr_live_skill_summary(array $spec, $key)
{
    $raw_text = (string) ($spec[$key]['raw_text'] ?? '');
    return [
        'name' => $spec[$key]['name'] ?? '',
        'raw_present' => $raw_text !== '',
        'attack' => koto_ocr_build_skill_detail_row($raw_text, (string) ($spec['attribute'] ?? '')),
    ];
}

function koto_ocr_live_condition_summary(array $spec)
{
    $rows = koto_ocr_build_sugowaza_condition_rows((string) ($spec['sugowaza']['condition'] ?? ''));
    return $rows[0]['sugo_cond_loop'] ?? [];
}

function koto_ocr_live_compare($expected, $actual)
{
    return ['expected' => $expected, 'actual' => $actual, 'status' => $expected === $actual ? 'matched' : 'mismatched'];
}

function koto_ocr_live_case_report($case, array $record_result, array $expected_cases)
{
    $recording = $record_result['recording'];
    $expected_case = $expected_cases[$case] ?? [];
    $expected = $expected_case['expected'] ?? [];
    $report = [
        'case' => $case,
        'from_cache' => $record_result['from_cache'],
        'golden_spec_path' => $expected_case['golden_spec_path'] ?? null,
        'error' => $recording['error'] ?? null,
    ];
    if (!empty($recording['error'])) {
        return $report;
    }

    $expected_count = (int) ($recording['metadata']['image_count'] ?? count($recording['payload']['images'] ?? []));
    $normalized = koto_ocr_normalize_payload($recording['payload'] ?? [], $expected_count);
    if (is_wp_error($normalized)) {
        $report['error'] = ['code' => $normalized->get_error_code(), 'message' => $normalized->get_error_message()];
        return $report;
    }

    $extracted = koto_ocr_extract_fields($normalized);
    $fragment = koto_ocr_build_spec_fragments($extracted);
    $draft = koto_ocr_build_draft_spec($normalized, $extracted, $fragment);
    $spec = $draft['spec'] ?? [];

    $normalized_summary = array_map(function ($image) {
        return [
            'source_image' => $image['source_image'] ?? '',
            'screen_type' => $image['screen_type'] ?? 'unknown',
            'block_regions' => array_values(array_unique(array_filter(array_map(function ($block) {
                return $block['region'] ?? '';
            }, $image['blocks'] ?? [])))),
            'text_preview' => mb_substr((string) ($image['fullText'] ?? ''), 0, 120),
        ];
    }, $normalized['images'] ?? []);

    $actual = [
        'name' => $spec['name'] ?? '',
        'attribute' => $spec['attribute'] ?? '',
        'species' => $spec['species'] ?? '',
        'chars' => $spec['chars'] ?? [],
        'waza' => koto_ocr_live_skill_summary($spec, 'waza'),
        'sugowaza' => koto_ocr_live_skill_summary($spec, 'sugowaza'),
        'sugowaza_condition' => koto_ocr_live_condition_summary($spec),
        'trait1_present' => !empty($spec['trait1']['raw_text']),
        'trait2_present' => !empty($spec['trait2']['raw_text']),
        'blessing_present' => !empty($spec['blessing']['raw_text']),
        'classifications' => $extracted['classifications'] ?? [],
    ];

    $comparisons = [
        'name' => koto_ocr_live_compare($expected['name'] ?? null, $actual['name']),
        'attribute' => koto_ocr_live_compare($expected['attribute'] ?? null, $actual['attribute']),
        'species' => koto_ocr_live_compare($expected['species'] ?? null, $actual['species']),
        'chars' => koto_ocr_live_compare($expected['chars'] ?? null, $actual['chars']),
        'waza_name' => koto_ocr_live_compare($expected['waza_name'] ?? null, $actual['waza']['name']),
        'waza_attack' => koto_ocr_live_compare($expected['waza_attack'] ?? null, array_intersect_key($actual['waza']['attack'], $expected['waza_attack'] ?? [])),
        'sugowaza_name' => koto_ocr_live_compare($expected['sugowaza_name'] ?? null, $actual['sugowaza']['name']),
        'sugowaza_condition' => koto_ocr_live_compare($expected['sugowaza_condition'] ?? null, $actual['sugowaza_condition']),
        'sugowaza_attack' => koto_ocr_live_compare($expected['sugowaza_attack'] ?? null, array_intersect_key($actual['sugowaza']['attack'], $expected['sugowaza_attack'] ?? [])),
        'trait1_present' => koto_ocr_live_compare(true, $actual['trait1_present']),
        'trait2_present' => koto_ocr_live_compare(true, $actual['trait2_present']),
        'blessing_present' => koto_ocr_live_compare(true, $actual['blessing_present']),
    ];

    return $report + [
        'normalized_diff' => [
            'actual_live_images' => count($normalized['images'] ?? []),
            'actual_images' => $normalized_summary,
        ],
        'actual' => $actual,
        'comparisons' => $comparisons,
        'warnings' => $draft['warnings'] ?? [],
    ];
}

function koto_ocr_live_run(array $args)
{
    if (koto_ocr_openrouter_api_key() === '') {
        WP_CLI::error('OpenRouter APIキーが設定されていません。');
    }
    $model = koto_ocr_openrouter_model();
    $backend_hash = koto_ocr_live_backend_hash();
    $backend = new Koto_Ocr_Openrouter_Vlm(koto_ocr_openrouter_api_key(), $model, koto_ocr_openrouter_timeout());
    $cases = koto_ocr_live_cases($args['input']);
    if (is_wp_error($cases)) {
        WP_CLI::error($cases->get_error_message());
    }
    $expected_cases = koto_ocr_live_fixture_expected_by_case($args['input'], $cases);
    $reports = [];

    foreach ($cases as $case) {
        WP_CLI::line('OCR live survey: ' . $case);
        $record_result = koto_ocr_live_record_case($case, $args, $backend, $model, $backend_hash);
        if (is_wp_error($record_result)) {
            WP_CLI::warning($case . ': ' . $record_result->get_error_message());
            $reports[] = ['case' => $case, 'error' => ['code' => $record_result->get_error_code(), 'message' => $record_result->get_error_message()]];
            continue;
        }
        $report = koto_ocr_live_case_report($case, $record_result, $expected_cases);
        $reports[] = $report;
        if (!empty($report['error'])) {
            WP_CLI::warning($case . ': ' . ($report['error']['message'] ?? 'OCR failed'));
            continue;
        }
        $matched = 0;
        $total = 0;
        foreach ($report['comparisons'] ?? [] as $comparison) {
            $total++;
            if (($comparison['status'] ?? '') === 'matched') $matched++;
        }
        WP_CLI::line(sprintf('%s: %d/%d fields matched%s', $case, $matched, $total, $report['from_cache'] ? ' (cache)' : ''));
    }

    $output = [
        'metadata' => [
            'backend' => $backend->get_name(),
            'model' => $model,
            'backend_source_hash' => $backend_hash,
            'generated_at' => current_time('mysql'),
            'input' => $args['input'],
            'expected_source' => 'input_case_spec_json',
            'case_discovery' => 'directories_with_spec_json_and_images',
            'cases' => $cases,
        ],
        'reports' => $reports,
    ];
    $report_path = rtrim($args['out'], '/') . '/report.json';
    $written = koto_ocr_live_json_write($report_path, $output);
    if (is_wp_error($written)) {
        WP_CLI::error($written->get_error_message());
    }
    WP_CLI::success('live OCR report written: ' . $report_path);
}

koto_ocr_live_run(koto_ocr_live_parse_args($argv ?? []));
