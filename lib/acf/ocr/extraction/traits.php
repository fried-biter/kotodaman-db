<?php
if (!defined('ABSPATH')) exit;

function koto_ocr_append_split_traits(array &$fields, $source, $text)
{
    // とくせい本文中の①②は同一とくせい内の効果番号なので、分割せず画面単位で割り当てる。
    if (!isset($fields['trait1'])) {
        $fields['trait1'][] = ['source_image' => $source, 'text' => $text];
    } else {
        $fields['trait2'][] = ['source_image' => $source, 'text' => $text];
    }
}
