<?php
if (!defined('ABSPATH')) exit;

function koto_ocr_extracted_fields_to_acf_data(array $fields)
{
    $acf = [];
    $chars = koto_ocr_collect_extracted_chars($fields);
    $attribute_slug = (string) ($fields['attribute'][0]['slug'] ?? '');
    if (!empty($chars)) {
        $rows = [];
        foreach ($chars as $char) {
            $term = koto_ocr_resolve_available_moji_term((string) $char);
            if ($term && !is_wp_error($term)) {
                $rows[] = [
                    'available_moji' => [$term->term_id],
                    'moji_attr' => koto_ocr_resolve_term_id($attribute_slug, 'attribute'),
                    'unlock_place' => 'default',
                ];
            }
        }
        if (!empty($rows)) {
            $acf['available_moji_loop'] = $rows;
        }
    }
    foreach (['attribute', 'species', 'rarity'] as $taxonomy) {
        if (!empty($fields[$taxonomy][0]['slug'])) {
            $term = get_term_by('slug', (string) $fields[$taxonomy][0]['slug'], $taxonomy);
            if ($term && !is_wp_error($term)) {
                $acf[$taxonomy] = $term->term_id;
            }
        }
    }
    if (!empty($fields['character_name'][0]['text'])) {
        $acf['name_ruby'] = koto_ocr_name_to_ruby($fields['character_name'][0]['text']);
    }
    if (!empty($fields['cv'][0]['text'])) {
        $acf['voice_actor'] = $fields['cv'][0]['text'];
    }
    if (!empty($fields['waza'][0]['text'])) {
        if (!empty($fields['waza_name'][0]['text'])) $acf['waza_name'] = $fields['waza_name'][0]['text'];
        if (!empty($fields['waza'][0]['text'])) {
            $rows = koto_ocr_build_skill_group_rows($fields['waza'][0]['text'], $attribute_slug);
            if (!empty($rows)) $acf['waza_group_loop'] = $rows;
        }
    }
    if (!empty($fields['sugowaza'][0]['text'])) {
        if (!empty($fields['sugowaza_name'][0]['text'])) $acf['sugowaza_name'] = $fields['sugowaza_name'][0]['text'];
        if (!empty($fields['sugowaza_condition'][0]['text'])) {
            $condition_rows = koto_ocr_build_sugowaza_condition_rows($fields['sugowaza_condition'][0]['text']);
            if (!empty($condition_rows)) $acf['sugowaza_condition'] = $condition_rows;
        }
        if (!empty($fields['sugowaza'][0]['text'])) {
            $rows = koto_ocr_build_skill_group_rows($fields['sugowaza'][0]['text'], $attribute_slug);
            if (!empty($rows)) $acf['sugowaza_group_loop'] = $rows;
        }
    }
    if (!empty($fields['EX_skill'][0]['text'])) {
        $acf = array_merge($acf, koto_ocr_build_ex_skill_fields($fields['EX_skill'][0]['text']));
    }
    return $acf;
}

function koto_ocr_collect_extracted_chars(array $fields)
{
    $chars = [];
    foreach ($fields['chars'] ?? [] as $item) {
        foreach ($item['items'] ?? [] as $char) {
            $char = (string) $char;
            if ($char !== '' && !in_array($char, $chars, true)) {
                $chars[] = $char;
            }
        }
    }
    return $chars;
}

function koto_ocr_name_to_ruby($name)
{
    $display = (string) $name;
    if (strpos($display, '・') !== false) {
        $parts = explode('・', $display);
        $display = end($parts);
    }
    $display = preg_replace('/[\(（].*$/u', '', $display);
    return mb_convert_kana(trim($display), 'c', 'UTF-8');
}

function koto_ocr_build_ex_skill_fields($raw_text)
{
    $text = trim(preg_replace('/n(?=【|[^\s])/u', "\n", (string) $raw_text));
    if ($text === '') {
        return [];
    }

    $label = '';
    $description = $text;
    if (preg_match('/^(?:EXスキル詳細\s*)?([^\n【]+)\s*(.*)$/su', $text, $m)) {
        $label = trim($m[1]);
        $description = trim($m[2] !== '' ? $m[2] : $text);
    }

    $acf = [];
    if ($label !== '' && $label !== 'EXスキル詳細') {
        $acf['ex_skill_label'] = $label;
    }
    if (preg_match('/^【([^】]+)】/u', $description, $m)) {
        $acf['ex_skill_name'] = trim($m[1]);
    }
    if ($description !== '') {
        $acf['ex_skill_discription'] = $description;
    }
    return $acf;
}

function koto_ocr_resolve_term_id($slug, $taxonomy)
{
    if ($slug === '') {
        return null;
    }
    $term = get_term_by('slug', (string) $slug, $taxonomy);
    return ($term && !is_wp_error($term)) ? (int) $term->term_id : null;
}

function koto_ocr_resolve_available_moji_term($char)
{
    $term = get_term_by('slug', $char, 'available_moji');
    if ($term && !is_wp_error($term) && $term->name === $char) {
        return $term;
    }

    $terms = get_terms([
        'taxonomy' => 'available_moji',
        'hide_empty' => false,
        'name' => $char,
    ]);
    if (!is_wp_error($terms)) {
        foreach ($terms as $candidate) {
            if ($candidate->name === $char) {
                return $candidate;
            }
        }
    }

    return null;
}

function koto_ocr_build_sugowaza_condition_rows($condition_text)
{
    $condition_text = (string) $condition_text;
    $conditions = [];

    if (preg_match('/[「\"]([^」\"]+)[」\"].*(?:からはじまる|から始まる|ではじまる|で始まる)/u', $condition_text)) {
        $chars = koto_ocr_extract_quoted_chars($condition_text);
        if (!empty($chars)) {
            $conditions[] = [
                'sugo_cond_type' => 'start_char',
                'sugo_cond_val' => implode(',', $chars),
            ];
        }
    }

    if (preg_match('/(\d+)文字以上/u', $condition_text, $m)) {
        $conditions[] = [
            'sugo_cond_type' => 'char_count',
            'sugo_cond_val' => (string) $m[1],
        ];
    }
    if (preg_match('/(\d+)コンボ以上/u', $condition_text, $m)) {
        $conditions[] = [
            'sugo_cond_type' => 'combo',
            'sugo_cond_val' => (string) $m[1],
        ];
    }

    if (empty($conditions)) {
        return [];
    }

    return [[
        'get_palce' => 'default',
        'need_blessing_point' => '',
        'sugo_cond_loop' => $conditions,
    ]];
}

function koto_ocr_build_skill_group_rows($raw_text, $attribute_slug)
{
    $details = koto_ocr_build_skill_detail_rows((string) $raw_text, (string) $attribute_slug);
    if (empty($details)) {
        return [];
    }
    return [[
        'sugo_detail_loop' => $details,
    ]];
}

function koto_ocr_build_skill_detail_rows($raw_text, $attribute_slug)
{
    $rows = [];
    if (preg_match('/ATKを(?:少し|大きく)?強化/u', $raw_text)) {
        $rows[] = koto_ocr_build_buff_detail_row('atk_buff');
    }
    if (preg_match('/ダメージを(?:少し|大きく)?軽減/u', $raw_text)) {
        $rows[] = koto_ocr_build_buff_detail_row('def_buff');
    }
    $attack = koto_ocr_build_skill_detail_row($raw_text, $attribute_slug);
    if (!empty($attack)) {
        $rows[] = $attack;
    }
    return $rows;
}

function koto_ocr_build_buff_detail_row($type)
{
    return [
        'waza_type' => $type,
        'advantage_target' => ['target_type' => 'all'],
        'advantage_rate' => '',
        'turn_count' => 2,
    ];
}

function koto_ocr_build_skill_detail_row($raw_text, $attribute_slug)
{
    if (mb_strpos($raw_text, '攻撃') === false) {
        return [];
    }

    $target = 'single_oppo';
    if (mb_strpos($raw_text, '敵全体') !== false) {
        $target = 'all_oppo';
    } elseif (mb_strpos($raw_text, 'ランダム') !== false) {
        $target = 'random_oppo';
    }

    $prefix = 'none';
    if (mb_strpos($raw_text, '爆絶強力') !== false) {
        $prefix = 'most_strong';
    } elseif (mb_strpos($raw_text, '超絶強力') !== false) {
        $prefix = 'super_strong';
    } elseif (mb_strpos($raw_text, '超強力') !== false) {
        $prefix = 'very_strong';
    } elseif (mb_strpos($raw_text, '強力') !== false) {
        $prefix = 'strong';
    }

    return [
        'waza_type' => 'attack',
        'attack_type' => 'normal',
        'waza_target' => $target,
        'waza_target_detail' => 'none',
        'attack_attr' => koto_ocr_resolve_term_id($attribute_slug, 'attribute'),
        'attack_prefix' => $prefix,
        'hit_count' => 1,
        'waza_value' => '',
        'waza_value_last' => '',
    ];
}

function koto_ocr_apply_existing_auto_input_rules(array $fields)
{
    if (!function_exists('koto_build_acf_data_from_inputs')) {
        return [];
    }
    $csv_path = get_stylesheet_directory() . '/lib/ゲーム内文言ーACF-対応表.csv';
    $grouped_csv = koto_group_csv_by_type(koto_load_csv_dictionary($csv_path));
    $rule_data = [];
    $map = [
        'trait1' => 'auto_input_trait1',
        'trait2' => 'auto_input_trait2',
        'blessing' => 'auto_input_blessing',
    ];

    foreach ($map as $field => $input_key) {
        foreach ($fields[$field] ?? [] as $item) {
            $text = trim((string) ($item['text'] ?? ''));
            if ($text === '') {
                continue;
            }
            $parsed = koto_build_acf_data_from_inputs([$input_key => $text], $grouped_csv);
            if (empty($parsed[$input_key]) || !is_array($parsed[$input_key])) {
                continue;
            }
            if (empty($rule_data[$input_key])) {
                $rule_data[$input_key] = [];
            }
            $rows = isset($parsed[$input_key][0]) ? $parsed[$input_key] : [$parsed[$input_key]];
            foreach ($rows as $row) {
                if (!empty($row) && is_array($row)) {
                    $rule_data[$input_key][] = $row;
                }
            }
        }
    }

    return $rule_data;
}
