<?php
if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/extraction/available-moji.php';
require_once __DIR__ . '/extraction/skills.php';
require_once __DIR__ . '/extraction/basic-terms.php';
require_once __DIR__ . '/extraction/traits.php';
require_once __DIR__ . '/extraction/names.php';

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
