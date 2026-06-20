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
    $skill_previews = koto_ocr_collect_main_skill_previews($normalized);

    foreach ($normalized['images'] ?? [] as $image) {
        $classification = koto_ocr_classify_image($image);
        $type = $classification['screen_type'];
        if (in_array($type, ['waza', 'sugowaza', 'unknown'], true) && koto_ocr_image_has_skill_modal_body($image)) {
            $preview_type = koto_ocr_match_skill_preview_type($image, $skill_previews);
            if ($preview_type !== '') {
                $type = $preview_type;
                $classification = ['screen_type' => $type, 'confidence' => 0.9, 'reason' => 'main_skill_preview_match'];
            } elseif (($type === 'sugowaza' || $type === 'unknown') && !$seen_waza_modal) {
                $type = 'waza';
                $classification = ['screen_type' => 'waza', 'confidence' => 0.65, 'reason' => 'skill_modal_order:first'];
            } elseif ($type === 'unknown' && !$seen_sugowaza_modal) {
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
            $reliable_main = koto_ocr_is_reliable_main_image($image);
            $name = $reliable_main ? koto_ocr_extract_name($image) : '';
            if ($name !== '') {
                $fields['character_name'][] = ['source_image' => $source, 'text' => $name];
            }
            if ($reliable_main) {
                koto_ocr_append_basic_terms($fields, $source, $text, $image);
            }
            $chars = koto_ocr_extract_chars_from_image($image);
            if (!empty($chars)) {
                $fields['chars'][] = ['source_image' => $source, 'text' => implode('・', $chars), 'items' => $chars];
            }
        } elseif ($type === 'waza') {
            $seen_waza_modal = true;
            $fields['waza'][] = ['source_image' => $source, 'text' => $text];
            $name = koto_ocr_extract_skill_name($image, ['わざ名', '技名', '名称']);
            if ($name !== '') $fields['waza_name'][] = ['source_image' => $source, 'text' => $name];
        } elseif ($type === 'sugowaza') {
            $seen_sugowaza_modal = true;
            $fields['sugowaza'][] = ['source_image' => $source, 'text' => $text];
            $name = koto_ocr_extract_skill_name($image, ['すごわざ名', '名称']);
            if ($name !== '') $fields['sugowaza_name'][] = ['source_image' => $source, 'text' => $name];
            $condition = koto_ocr_extract_trigger_text($image);
            if ($condition !== '') $fields['sugowaza_condition'][] = ['source_image' => $source, 'text' => $condition];
            $chars = koto_ocr_extract_quoted_chars($condition);
            if (!empty($chars)) $fields['chars'][] = ['source_image' => $source, 'text' => implode('・', $chars), 'items' => $chars];
        } elseif ($type === 'trait') {
            $trait_text = koto_ocr_extract_block_text($image, ['trait_body'], $text);
            koto_ocr_append_split_traits($fields, $source, $trait_text);
            $chars = koto_ocr_extract_chars_from_image($image);
            if (!empty($chars)) {
                $fields['chars'][] = ['source_image' => $source, 'text' => implode('・', $chars), 'items' => $chars];
            }
        } elseif ($type === 'blessing') {
            $blessing_text = koto_ocr_extract_block_text($image, ['blessing_body'], $text);
            $fields['blessing'][] = ['source_image' => $source, 'text' => $blessing_text];
            $chars = koto_ocr_extract_quoted_chars($blessing_text);
            if (!empty($chars)) $fields['chars'][] = ['source_image' => $source, 'text' => implode('・', $chars), 'items' => $chars];
        } elseif ($type === 'profile') {
            $fields['profile'][] = ['source_image' => $source, 'text' => $text];
            $cv_text = koto_ocr_extract_block_text($image, ['cv_text'], '');
            if ($cv_text !== '') {
                $fields['cv'][] = ['source_image' => $source, 'text' => koto_ocr_clean_cv_text($cv_text)];
            }
        } elseif ($type === 'EX_skill') {
            $fields[$type][] = ['source_image' => $source, 'text' => koto_ocr_extract_block_text($image, ['modal_body'], $text)];
        } elseif (in_array($type, ['leader', 'kotowaza', 'charge_skill'], true)) {
            $fields[$type][] = ['source_image' => $source, 'text' => $text];
        }

        $cv = koto_ocr_extract_cv($text);
        if ($cv !== '') {
            $fields['cv'][] = ['source_image' => $source, 'text' => $cv];
        }
    }

    return ['fields' => $fields, 'classifications' => $classifications];
}

function koto_ocr_collect_main_skill_previews(array $normalized)
{
    $previews = ['waza' => '', 'sugowaza' => ''];
    foreach ($normalized['images'] ?? [] as $image) {
        foreach ($image['blocks'] ?? [] as $block) {
            $region = $block['region'] ?? '';
            $text = trim((string) ($block['text'] ?? ''));
            if ($text === '') {
                continue;
            }
            if ($region === 'main_waza_preview' && $previews['waza'] === '') {
                $previews['waza'] = koto_ocr_clean_skill_name($text);
            } elseif ($region === 'main_sugowaza_preview' && $previews['sugowaza'] === '') {
                $previews['sugowaza'] = koto_ocr_clean_skill_name($text);
            }
        }
    }
    return $previews;
}

function koto_ocr_match_skill_preview_type(array $image, array $previews)
{
    $name = koto_ocr_extract_skill_name($image, ['わざ名', 'すごわざ名', '技名', '名称']);
    if ($name === '') {
        return '';
    }
    foreach (['waza', 'sugowaza'] as $type) {
        if (($previews[$type] ?? '') !== '' && $name === $previews[$type]) {
            return $type;
        }
    }
    return '';
}

function koto_ocr_is_reliable_main_image(array $image)
{
    foreach ($image['blocks'] ?? [] as $block) {
        if (in_array($block['region'] ?? '', ['main_name_text', 'main_attribute_icon', 'main_species_icon', 'main_char_ball', 'main_waza_preview', 'main_sugowaza_preview'], true)) {
            return true;
        }
    }
    return false;
}

function koto_ocr_extract_cv($text)
{
    if (preg_match('/CV[:：]\s*([\p{Han}\p{Hiragana}\p{Katakana}ー・]+?)(?=(?:暖かな|言霊|$|\s|[。,.、]))/u', (string) $text, $m)) {
        return trim($m[1]);
    }
    if (preg_match('/CV[:：]\s*([^\s　\r\n。,.、]+)/u', (string) $text, $m)) {
        return trim($m[1]);
    }
    return '';
}

function koto_ocr_clean_cv_text($text)
{
    $text = preg_replace('/^CV[:：]\s*/u', '', (string) $text);
    return trim($text);
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
