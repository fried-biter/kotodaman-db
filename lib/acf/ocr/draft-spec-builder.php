<?php
if (!defined('ABSPATH')) exit;

function koto_ocr_build_draft_summary(array $normalized, array $extracted)
{
    $fields = $extracted['fields'] ?? [];
    $title = trim((string) ($fields['character_name'][0]['text'] ?? ''));
    $warnings = $normalized['warnings'] ?? [];
    if ($title === '') {
        $title = 'OCR入力 ' . current_time('Y-m-d H:i');
        $warnings[] = koto_ocr_warning('character_name', 'missing_name', 'キャラ名を安全に確定できなかったため仮タイトルで下書きを作成しました。');
    }

    foreach (['waza', 'sugowaza'] as $field) {
        if (!empty($fields[$field][0]['text'])) {
            $warnings[] = koto_ocr_warning($field, 'manual_numeric_required', '倍率/数値は画像本文から安全に確定できないため手入力してください。');
        }
    }
    foreach (['trait1', 'trait2', 'blessing', 'leader', 'kotowaza', 'EX_skill', 'charge_skill'] as $field) {
        if (!empty($fields[$field][0]['text'])) {
            $warnings[] = koto_ocr_warning($field, 'raw_text_only', 'OCR本文を保存しました。公開前に内容を確認してください。');
        }
    }
    $warnings[] = koto_ocr_warning('draft', 'review_required', 'OCR下書きです。公開前に必須項目と数値を確認してください。');

    return ['title' => $title, 'warnings' => koto_ocr_unique_warnings($warnings)];
}

function koto_ocr_unique_warnings(array $warnings)
{
    $seen = [];
    $unique = [];
    foreach ($warnings as $warning) {
        if (!is_array($warning)) continue;
        $key = ($warning['field'] ?? '') . '|' . ($warning['code'] ?? '') . '|' . ($warning['message'] ?? '');
        if (isset($seen[$key])) continue;
        $seen[$key] = true;
        $unique[] = $warning;
    }
    return $unique;
}
