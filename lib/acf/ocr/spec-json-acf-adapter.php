<?php
if (!defined('ABSPATH')) exit;

function koto_ocr_spec_to_acf_data(array $spec)
{
    $acf = [];
    if (!empty($spec['chars']) && is_array($spec['chars'])) {
        $rows = [];
        foreach ($spec['chars'] as $char) {
            $term = koto_ocr_resolve_available_moji_term((string) $char);
            if ($term && !is_wp_error($term)) {
                $rows[] = [
                    'available_moji' => [$term->term_id],
                    'moji_attr' => koto_ocr_resolve_term_id($spec['attribute'] ?? '', 'attribute'),
                    'unlock_place' => 'default',
                ];
            }
        }
        if (!empty($rows)) {
            $acf['available_moji_loop'] = $rows;
        }
    }
    foreach (['attribute', 'species'] as $taxonomy) {
        if (!empty($spec[$taxonomy])) {
            $term = get_term_by('slug', (string) $spec[$taxonomy], $taxonomy);
            if ($term && !is_wp_error($term)) {
                $acf[$taxonomy] = $term->term_id;
            }
        }
    }
    if (!empty($spec['waza'])) {
        if (!empty($spec['waza']['name'])) $acf['waza_name'] = $spec['waza']['name'];
        if (!empty($spec['waza']['raw_text'])) {
            $rows = koto_ocr_build_skill_group_rows($spec['waza']['raw_text'], $spec['attribute'] ?? '');
            if (!empty($rows)) $acf['waza_group_loop'] = $rows;
        }
    }
    if (!empty($spec['sugowaza'])) {
        if (!empty($spec['sugowaza']['name'])) $acf['sugowaza_name'] = $spec['sugowaza']['name'];
        if (!empty($spec['sugowaza']['condition'])) {
            $condition_rows = koto_ocr_build_sugowaza_condition_rows($spec['sugowaza']['condition']);
            if (!empty($condition_rows)) $acf['sugowaza_condition'] = $condition_rows;
        }
        if (!empty($spec['sugowaza']['raw_text'])) {
            $rows = koto_ocr_build_skill_group_rows($spec['sugowaza']['raw_text'], $spec['attribute'] ?? '');
            if (!empty($rows)) $acf['sugowaza_group_loop'] = $rows;
        }
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
    $detail = koto_ocr_build_skill_detail_row((string) $raw_text, (string) $attribute_slug);
    if (empty($detail)) {
        return [];
    }
    return [[
        'sugo_detail_loop' => [$detail],
    ]];
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
