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
    $seen_waza_modal = false;
    $seen_sugowaza_modal = false;

    foreach ($normalized['images'] ?? [] as $image) {
        $classification = koto_ocr_classify_image($image);
        $type = $classification['screen_type'];
        if (($type === 'sugowaza' || $type === 'unknown') && koto_ocr_image_has_skill_modal_body($image)) {
            if (!$seen_waza_modal) {
                $type = 'waza';
                $classification = ['screen_type' => 'waza', 'confidence' => 0.65, 'reason' => 'skill_modal_order:first'];
            } elseif (!$seen_sugowaza_modal) {
                $type = 'sugowaza';
                $classification = ['screen_type' => 'sugowaza', 'confidence' => 0.65, 'reason' => 'skill_modal_order:second'];
            }
        }
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
            koto_ocr_append_basic_terms($fields, $source, $text, $image);
            $chars = koto_ocr_extract_chars_from_image($image);
            if (!empty($chars)) {
                $fields['chars'][] = ['source_image' => $source, 'text' => implode('・', $chars), 'items' => $chars];
            }
        } elseif ($type === 'waza') {
            $seen_waza_modal = true;
            $fields['waza'][] = ['source_image' => $source, 'text' => $text];
            $name = koto_ocr_extract_skill_name($image, ['わざ名', '技名', '名称']);
            if ($name !== '') $fields['waza_name'][] = ['source_image' => $source, 'text' => $name];
            koto_ocr_append_basic_terms($fields, $source, $text, $image);
        } elseif ($type === 'sugowaza') {
            $seen_sugowaza_modal = true;
            $fields['sugowaza'][] = ['source_image' => $source, 'text' => $text];
            $name = koto_ocr_extract_skill_name($image, ['すごわざ名', '名称']);
            if ($name !== '') $fields['sugowaza_name'][] = ['source_image' => $source, 'text' => $name];
            $condition = koto_ocr_extract_trigger_text($image);
            if ($condition !== '') $fields['sugowaza_condition'][] = ['source_image' => $source, 'text' => $condition];
            $chars = koto_ocr_extract_quoted_chars($condition);
            if (!empty($chars)) $fields['chars'][] = ['source_image' => $source, 'text' => implode('・', $chars), 'items' => $chars];
            koto_ocr_append_basic_terms($fields, $source, $text, $image);
        } elseif ($type === 'trait') {
            $trait_text = koto_ocr_extract_block_text($image, ['trait_body'], $text);
            koto_ocr_append_split_traits($fields, $source, $trait_text);
            $chars = koto_ocr_extract_chars_from_image($image);
            if (!empty($chars)) {
                $fields['chars'][] = ['source_image' => $source, 'text' => implode('・', $chars), 'items' => $chars];
            }
            koto_ocr_append_basic_terms($fields, $source, $trait_text, $image);
        } elseif ($type === 'blessing') {
            $blessing_text = koto_ocr_extract_block_text($image, ['blessing_body'], $text);
            $fields['blessing'][] = ['source_image' => $source, 'text' => $blessing_text];
            $chars = koto_ocr_extract_quoted_chars($blessing_text);
            if (!empty($chars)) $fields['chars'][] = ['source_image' => $source, 'text' => implode('・', $chars), 'items' => $chars];
        } elseif (in_array($type, ['leader', 'kotowaza', 'EX_skill', 'charge_skill'], true)) {
            $fields[$type][] = ['source_image' => $source, 'text' => $text];
            koto_ocr_append_basic_terms($fields, $source, $text, $image);
        }
    }

    return ['fields' => $fields, 'classifications' => $classifications];
}

function koto_ocr_extract_block_text(array $image, array $regions, $fallback = '')
{
    $parts = [];
    foreach ($image['blocks'] ?? [] as $block) {
        if (in_array($block['region'] ?? '', $regions, true)) {
            $text = trim((string) ($block['text'] ?? ''));
            if ($text !== '') {
                $parts[] = $text;
            }
        }
    }
    return !empty($parts) ? implode("\n", $parts) : trim((string) $fallback);
}

function koto_ocr_image_has_skill_modal_body(array $image)
{
    $modal_text = '';
    foreach ($image['blocks'] ?? [] as $block) {
        if (in_array($block['region'] ?? '', ['modal_body', 'modal_trigger'], true)) {
            $modal_text .= "\n" . trim((string) ($block['text'] ?? ''));
        }
    }
    return $modal_text !== '' && preg_match('/(敵|攻撃|回復|ATK|発動条件|文字以上|コンボ以上|ランダムな敵)/u', $modal_text);
}
