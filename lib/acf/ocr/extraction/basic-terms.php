<?php
if (!defined('ABSPATH')) exit;

function koto_ocr_append_basic_terms(array &$fields, $source, $text, array $image = [])
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
    $attribute_slug = koto_ocr_find_basic_term_slug($text, $attributes);
    if ($attribute_slug === '') {
        $attribute_slug = koto_ocr_find_icon_term_slug($image, 'main_attribute_icon', $attributes);
    }
    if ($attribute_slug !== '') {
        $fields['attribute'][] = ['source_image' => $source, 'text' => $attributes[$attribute_slug][0], 'slug' => $attribute_slug];
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
    $species_slug = koto_ocr_find_basic_term_slug($text, $species);
    if ($species_slug === '') {
        $species_slug = koto_ocr_find_icon_term_slug($image, 'main_species_icon', $species);
    }
    if ($species_slug !== '') {
        $fields['species'][] = ['source_image' => $source, 'text' => $species[$species_slug][0], 'slug' => $species_slug];
    }

    $rarity_slug = koto_ocr_find_rarity_slug($text, $image);
    if ($rarity_slug !== '') {
        $fields['rarity'][] = ['source_image' => $source, 'text' => $rarity_slug, 'slug' => $rarity_slug];
    }
}

function koto_ocr_find_rarity_slug($text, array $image = [])
{
    $candidate = (string) $text;
    foreach ($image['blocks'] ?? [] as $block) {
        if (($block['region'] ?? '') === 'main_rarity_text') {
            $candidate .= "\n" . (string) ($block['text'] ?? '');
        }
    }

    if (preg_match('/グランド/u', $candidate)) return 'grand';
    if (preg_match('/レジェンド/u', $candidate)) return 'legend';
    if (preg_match('/スペシャル/u', $candidate)) return 'special';
    if (preg_match('/ドリーム/u', $candidate)) return 'dream';
    if (preg_match('/ミラクル/u', $candidate)) return 'miracle';
    if (preg_match('/(?:星|★|☆)?\s*([1-6])\s*(?:星|★|☆)?/u', $candidate, $m)) return (string) $m[1];

    return '';
}

function koto_ocr_find_basic_term_slug($text, array $terms)
{
    foreach ($terms as $slug => $patterns) {
        if (koto_ocr_text_matches_any($text, $patterns)) {
            return $slug;
        }
    }
    return '';
}

function koto_ocr_find_icon_term_slug(array $image, $region, array $terms)
{
    foreach ($image['blocks'] ?? [] as $block) {
        if (($block['region'] ?? '') !== $region) {
            continue;
        }
        $text = preg_replace('/[\s　]+/u', '', (string) ($block['text'] ?? ''));
        if ($text === '') {
            continue;
        }
        foreach ($terms as $slug => $patterns) {
            $label = (string) ($patterns[0] ?? '');
            $icon = mb_substr($label, 0, 1);
            if ($icon !== '' && mb_strpos($text, $icon) !== false) {
                return $slug;
            }
        }
    }
    return '';
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
