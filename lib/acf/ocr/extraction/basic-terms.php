<?php
if (!defined('ABSPATH')) exit;

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
