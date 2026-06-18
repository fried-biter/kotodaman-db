<?php
if (!defined('ABSPATH')) exit;

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

    if (empty($chars) && preg_match('/(使用可能文字|使用文字|文字変換|文字追加|もじ)/u', (string) $text) && preg_match_all('/[「\"]([^」\"]+)[」\"]/u', (string) $text, $matches)) {
        foreach ($matches[1] ?? [] as $match) {
            $chars = array_merge($chars, koto_ocr_parse_moji_candidates($match));
        }
    }

    return array_values(array_unique($chars));
}

function koto_ocr_extract_chars_from_image(array $image)
{
    $chars = koto_ocr_extract_chars($image['fullText'] ?? '');
    foreach ($image['blocks'] ?? [] as $block) {
        $region = $block['region'] ?? '';
        $text = (string) ($block['text'] ?? '');
        if ($text === '') {
            continue;
        }
        if (in_array($region, ['main_char_ball', 'trait_available_moji'], true)) {
            $chars = array_merge($chars, koto_ocr_parse_moji_candidates($text));
        } elseif ($region === 'trait_body') {
            $chars = array_merge($chars, koto_ocr_extract_chars($text));
        }
    }

    return array_values(array_unique($chars));
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
