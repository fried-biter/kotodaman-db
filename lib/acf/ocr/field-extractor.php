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
            $chars = koto_ocr_extract_chars($text);
            if (!empty($chars)) {
                $fields['chars'][] = ['source_image' => $source, 'text' => implode('・', $chars), 'items' => $chars];
            }
        } elseif ($type === 'waza') {
            $fields['waza'][] = ['source_image' => $source, 'text' => $text];
            $name = koto_ocr_extract_labeled_line($text, ['わざ名', '技名', '名称']);
            if ($name !== '') $fields['waza_name'][] = ['source_image' => $source, 'text' => $name];
        } elseif ($type === 'sugowaza') {
            $fields['sugowaza'][] = ['source_image' => $source, 'text' => $text];
            $name = koto_ocr_extract_labeled_line($text, ['すごわざ名', '名称']);
            if ($name !== '') $fields['sugowaza_name'][] = ['source_image' => $source, 'text' => $name];
            $condition = koto_ocr_extract_labeled_line($text, ['発動条件', '条件']);
            if ($condition !== '') $fields['sugowaza_condition'][] = ['source_image' => $source, 'text' => $condition];
        } elseif ($type === 'trait') {
            koto_ocr_append_split_traits($fields, $source, $text);
        } elseif ($type === 'blessing') {
            $fields['blessing'][] = ['source_image' => $source, 'text' => $text];
        } elseif (in_array($type, ['leader', 'kotowaza', 'EX_skill', 'charge_skill'], true)) {
            $fields[$type][] = ['source_image' => $source, 'text' => $text];
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
    if (preg_match('/(?:文字|もじ|使用文字)[:：\s]*([^\r\n]+)/u', $text, $m)) {
        $raw = preg_split('/[・,、\s]+/u', trim($m[1]));
        return array_values(array_filter(array_map(function ($item) {
            return trim($item, '「」[]()（） ');
        }, $raw)));
    }
    return [];
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

function koto_ocr_append_split_traits(array &$fields, $source, $text)
{
    $parts = preg_split('/(?=①|②|\(1\)|\(2\)|１\.|２\.)/u', $text, -1, PREG_SPLIT_NO_EMPTY);
    $parts = array_values(array_filter(array_map('trim', $parts ?: [])));
    if (count($parts) >= 2) {
        $fields['trait1'][] = ['source_image' => $source, 'text' => preg_replace('/^(①|\(1\)|１\.)\s*/u', '', $parts[0])];
        $fields['trait2'][] = ['source_image' => $source, 'text' => preg_replace('/^(②|\(2\)|２\.)\s*/u', '', $parts[1])];
        return;
    }
    if (!isset($fields['trait1'])) {
        $fields['trait1'][] = ['source_image' => $source, 'text' => $text];
    } else {
        $fields['trait2'][] = ['source_image' => $source, 'text' => $text];
    }
}
