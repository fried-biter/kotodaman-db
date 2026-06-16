<?php
if (!defined('ABSPATH')) exit;

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
