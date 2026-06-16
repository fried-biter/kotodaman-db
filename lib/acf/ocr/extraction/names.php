<?php
if (!defined('ABSPATH')) exit;

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
