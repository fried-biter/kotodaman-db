<?php
if (!defined('ABSPATH')) exit;

function koto_ocr_extract_fields(array $normalized)
{
    $fields = [];
    $classifications = [];

    foreach ($normalized['images'] ?? [] as $image) {
        $classification = koto_ocr_classify_image($image);
        $type = $classification['screen_type'];
        $source = $image['source_image'] ?? '';
        $text = trim((string) ($image['fullText'] ?? ''));
        $classifications[] = ['source_image' => $source] + $classification;

        if ($text === '') {
            continue;
        }

        if ($type === 'main') {
            $name = koto_ocr_extract_name($image);
            if ($name !== '') {
                $fields['character_name'][] = ['source_image' => $source, 'text' => $name];
            }
            koto_ocr_append_basic_terms($fields, $source, $text);
            $chars = koto_ocr_extract_chars($text);
            if (!empty($chars)) {
                $fields['chars'][] = ['source_image' => $source, 'text' => implode('・', $chars), 'items' => $chars];
            }
        } elseif ($type === 'waza') {
            $fields['waza'][] = ['source_image' => $source, 'text' => $text];
            $name = koto_ocr_extract_skill_name($image, ['わざ名', '技名', '名称']);
            if ($name !== '') $fields['waza_name'][] = ['source_image' => $source, 'text' => $name];
            koto_ocr_append_basic_terms($fields, $source, $text);
        } elseif ($type === 'sugowaza') {
            $fields['sugowaza'][] = ['source_image' => $source, 'text' => $text];
            $name = koto_ocr_extract_skill_name($image, ['すごわざ名', '名称']);
            if ($name !== '') $fields['sugowaza_name'][] = ['source_image' => $source, 'text' => $name];
            $condition = koto_ocr_extract_trigger_text($image);
            if ($condition !== '') $fields['sugowaza_condition'][] = ['source_image' => $source, 'text' => $condition];
            $chars = koto_ocr_extract_quoted_chars($condition);
            if (!empty($chars)) $fields['chars'][] = ['source_image' => $source, 'text' => implode('・', $chars), 'items' => $chars];
            koto_ocr_append_basic_terms($fields, $source, $text);
        } elseif ($type === 'trait') {
            koto_ocr_append_split_traits($fields, $source, $text);
            koto_ocr_append_basic_terms($fields, $source, $text);
        } elseif ($type === 'blessing') {
            $fields['blessing'][] = ['source_image' => $source, 'text' => $text];
            $chars = koto_ocr_extract_quoted_chars($text);
            if (!empty($chars)) $fields['chars'][] = ['source_image' => $source, 'text' => implode('・', $chars), 'items' => $chars];
        } elseif (in_array($type, ['leader', 'kotowaza', 'EX_skill', 'charge_skill'], true)) {
            $fields[$type][] = ['source_image' => $source, 'text' => $text];
            koto_ocr_append_basic_terms($fields, $source, $text);
        }
    }

    return ['fields' => $fields, 'classifications' => $classifications];
}

function koto_ocr_extract_name(array $image)
{
    foreach ($image['blocks'] ?? [] as $block) {
        if (($block['region'] ?? '') === 'main_name_text' && trim((string) ($block['text'] ?? '')) !== '') {
            return koto_ocr_clean_name($block['text']);
        }
    }
    $lines = preg_split('/\R/u', (string) ($image['fullText'] ?? ''));
    foreach (array_slice($lines ?: [], 0, 6) as $line) {
        $line = koto_ocr_clean_name($line);
        if ($line !== '' && !preg_match('/(Lv|HP|ATK|属性|種族|文字|レア|満福|CV|わざ|とくせい)/u', $line)) {
            return $line;
        }
    }
    return '';
}

function koto_ocr_clean_name($name)
{
    $name = trim(preg_replace('/\s+/u', ' ', (string) $name));
    $name = preg_replace('/^(名前|キャラ名)[:：\s]*/u', '', $name);
    return trim($name);
}

function koto_ocr_extract_chars($text)
{
    $chars = [];
    $lines = preg_split('/\R/u', (string) $text) ?: [];

    foreach ($lines as $line) {
        if (!preg_match('/(使用可能文字|使用文字|文字変換|文字追加|もじ)/u', $line)) {
            continue;
        }

        $line = preg_replace('/.*?(?:使用可能文字|使用文字|文字変換|文字追加|もじ)[:：\s　]*/u', '', $line);
        $line = preg_split('/(?:リーダー|とくせい|わざ|すごわざ|祝福|詳細|発動例|HP|ATK|CV)/u', $line)[0] ?? $line;
        $chars = array_merge($chars, koto_ocr_parse_moji_candidates($line));
    }

    if (empty($chars) && preg_match_all('/[「\"]([^」\"]+)[」\"]/u', (string) $text, $matches)) {
        foreach ($matches[1] ?? [] as $match) {
            $chars = array_merge($chars, koto_ocr_parse_moji_candidates($match));
        }
    }

    return array_values(array_unique($chars));
}

function koto_ocr_extract_labeled_line($text, array $labels)
{
    foreach ($labels as $label) {
        if (preg_match('/' . preg_quote($label, '/') . '[:：\s]*([^\r\n]+)/u', $text, $m)) {
            return trim($m[1]);
        }
    }
    return '';
}

function koto_ocr_extract_skill_name(array $image, array $labels)
{
    $text = (string) ($image['fullText'] ?? '');
    $labeled = koto_ocr_extract_labeled_line($text, $labels);
    if ($labeled !== '') return koto_ocr_clean_skill_name($labeled);

    $body_text = '';
    foreach ($image['blocks'] ?? [] as $block) {
        if (($block['region'] ?? '') === 'modal_body') {
            $body_text = trim((string) ($block['text'] ?? ''));
            break;
        }
    }
    if ($body_text !== '' && mb_strpos($text, $body_text) !== false) {
        $prefix = trim(mb_substr($text, 0, mb_strpos($text, $body_text)));
        $prefix = preg_replace('/[\s　]+$/u', '', $prefix);
        $prefix = koto_ocr_clean_skill_name($prefix);
        if ($prefix !== '') return $prefix;
    }

    if (preg_match('/^([^\s　]+(?:・[^\s　]+)?)/u', trim($text), $m)) {
        return koto_ocr_clean_skill_name($m[1]);
    }
    return '';
}

function koto_ocr_clean_skill_name($name)
{
    $name = trim(preg_replace('/\s+/u', ' ', (string) $name));
    $name = preg_replace('/(?:詳細|発動例|とじる).*$/u', '', $name);
    return trim($name);
}

function koto_ocr_extract_trigger_text(array $image)
{
    foreach ($image['blocks'] ?? [] as $block) {
        if (($block['region'] ?? '') === 'modal_trigger' && trim((string) ($block['text'] ?? '')) !== '') {
            return trim((string) $block['text']);
        }
    }
    return koto_ocr_extract_labeled_line((string) ($image['fullText'] ?? ''), ['発動条件', '条件']);
}

function koto_ocr_extract_quoted_chars($text)
{
    preg_match_all('/[「\"]([^」\"]+)[」\"]/u', (string) $text, $matches);
    $chars = [];
    foreach ($matches[1] ?? [] as $match) {
        $chars = array_merge($chars, koto_ocr_parse_moji_candidates($match));
    }
    return array_values(array_unique($chars));
}

function koto_ocr_parse_moji_candidates($text)
{
    $items = preg_split('/[・,、\/\s　]+/u', (string) $text) ?: [];
    $chars = [];

    foreach ($items as $item) {
        $item = trim($item);
        $item = preg_replace('/^[「」\[\]\(\)（）『』【】\"\'\s　]+|[「」\[\]\(\)（）『』【】\"\'\s　]+$/u', '', $item);
        if ($item === '') {
            continue;
        }
        if (function_exists('mb_convert_kana')) {
            $item = mb_convert_kana($item, 'c', 'UTF-8');
        }
        if (preg_match('/^[ぁ-ゖ]$/u', $item)) {
            $chars[] = $item;
        }
    }

    return $chars;
}

function koto_ocr_append_basic_terms(array &$fields, $source, $text)
{
    $attributes = [
        'fire' => ['火属性', '/属性[:：\s　]*火/u'],
        'water' => ['水属性', '/属性[:：\s　]*水/u'],
        'wood' => ['木属性', '/属性[:：\s　]*木/u'],
        'light' => ['光属性', '/属性[:：\s　]*光/u'],
        'dark' => ['闇属性', '/属性[:：\s　]*闇/u'],
        'void' => ['冥属性', '/属性[:：\s　]*冥/u'],
        'heaven' => ['天属性', '/属性[:：\s　]*天/u'],
        'rainbow' => ['虹属性', '/属性[:：\s　]*虹/u'],
    ];
    foreach ($attributes as $slug => $patterns) {
        if (koto_ocr_text_matches_any($text, $patterns)) {
            $fields['attribute'][] = ['source_image' => $source, 'text' => $patterns[0], 'slug' => $slug];
            break;
        }
    }

    $species = [
        'dragon' => ['龍種族', '/種族[:：\s　]*龍/u'],
        'god' => ['神種族', '/種族[:：\s　]*神/u'],
        'demon' => ['魔種族', '/種族[:：\s　]*魔/u'],
        'beast' => ['獣種族', '/種族[:：\s　]*獣/u'],
        'artifact' => ['物種族', '/種族[:：\s　]*物/u'],
        'hero' => ['英種族', '/種族[:：\s　]*英/u'],
        'spirit' => ['霊種族', '/種族[:：\s　]*霊/u'],
        'yokai' => ['妖種族', '/種族[:：\s　]*妖/u'],
    ];
    foreach ($species as $slug => $patterns) {
        if (koto_ocr_text_matches_any($text, $patterns)) {
            $fields['species'][] = ['source_image' => $source, 'text' => $patterns[0], 'slug' => $slug];
            break;
        }
    }
}

function koto_ocr_text_matches_any($text, array $patterns)
{
    $text = (string) $text;
    foreach ($patterns as $pattern) {
        if (preg_match('/^\/.+\/[a-zA-Z]*$/', $pattern)) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        } elseif (mb_strpos($text, $pattern) !== false) {
            return true;
        }
    }
    return false;
}

function koto_ocr_append_split_traits(array &$fields, $source, $text)
{
    // とくせい本文中の①②は同一とくせい内の効果番号なので、分割せず画面単位で割り当てる。
    if (!isset($fields['trait1'])) {
        $fields['trait1'][] = ['source_image' => $source, 'text' => $text];
    } else {
        $fields['trait2'][] = ['source_image' => $source, 'text' => $text];
    }
}
