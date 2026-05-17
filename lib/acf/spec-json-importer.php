<?php
if (!defined('ABSPATH')) exit;

if (!function_exists('koto_import_spec_json_to_draft')) {
    function koto_import_spec_json_to_draft(array $spec)
    {
        $warnings = [];
        $title = trim(html_entity_decode((string)($spec['name'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        if ($title === '') {
            return new WP_Error('missing_name', 'spec_json の name が空です。');
        }

        $post_id = wp_insert_post([
            'post_type' => 'character',
            'post_status' => 'draft',
            'post_title' => $title,
            'post_content' => '',
        ], true);

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        koto_import_spec_json_apply_to_post($post_id, $spec, $warnings);

        if (function_exists('on_save_character_specs')) {
            on_save_character_specs($post_id);
        }

        return [
            'post_id' => $post_id,
            'warnings' => array_values(array_unique($warnings)),
        ];
    }
}

if (!function_exists('koto_import_spec_json_apply_to_post')) {
    function koto_import_spec_json_apply_to_post($post_id, array $spec, array &$warnings)
    {
        koto_import_spec_json_update_field('name_ruby', $spec['name_ruby'] ?? '', $post_id);
        koto_import_spec_json_update_field('voice_actor', html_entity_decode((string)($spec['cv'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'), $post_id);
        koto_import_spec_json_update_field('実装月（わかれば実装日）', $spec['release_date'] ?? '', $post_id);
        koto_import_spec_json_update_field('pre_evo_name', $spec['pre_evo_name'] ?? '', $post_id);
        koto_import_spec_json_update_field('another_image_name', $spec['another_image_name'] ?? '', $post_id);
        koto_import_spec_json_update_field('max_ls_hp', (int)($spec['max_ls_hp'] ?? 0), $post_id);
        koto_import_spec_json_update_field('max_ls_atk', (int)($spec['max_ls_atk'] ?? 0), $post_id);
        koto_import_spec_json_update_field('no_lv120_flag', !empty($spec['is_no_lv120']), $post_id);
        koto_import_spec_json_update_field('magnification_estimate_tf', !empty($spec['is_estimate']), $post_id);
        koto_import_spec_json_update_field('koto_magnification_estimate_tf', !empty($spec['is_koto_estimate']), $post_id);

        if (!empty($spec['acquisition'])) {
            $get_place = ((string)$spec['acquisition'] === 'ガチャ' || (string)$spec['acquisition'] === 'gacha') ? 'gacha' : 'other';
            koto_import_spec_json_update_field('get_place', $get_place, $post_id);
        }

        koto_import_spec_json_update_field('lv_99_hp', (int)($spec['_val_99_hp'] ?? 0), $post_id);
        koto_import_spec_json_update_field('lv_99_atk', (int)($spec['_val_99_atk'] ?? 0), $post_id);
        koto_import_spec_json_update_field('lv_120_hp', (int)($spec['_val_120_hp'] ?? 0), $post_id);
        koto_import_spec_json_update_field('lv_120_atk', (int)($spec['_val_120_atk'] ?? 0), $post_id);
        koto_import_spec_json_update_field('hp_chouka', 0, $post_id);
        koto_import_spec_json_update_field('atk_chouka', 0, $post_id);
        koto_import_spec_json_update_field('status_auto_tf', true, $post_id);

        koto_import_spec_json_apply_taxonomies($post_id, $spec, $warnings);
        koto_import_spec_json_apply_moji_loop($post_id, $spec, $warnings);
        koto_import_spec_json_apply_skills($post_id, $spec, $warnings);

        update_post_meta($post_id, '_import_source_spec_json', wp_slash(wp_json_encode($spec, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR)));

        $warnings[] = '画像は spec_json に含まれないため未設定です。';
        $warnings[] = 'Lv99値は超化値に分解できないため、Lv99合計値として入れています。';
    }
}

if (!function_exists('koto_import_spec_json_update_field')) {
    function koto_import_spec_json_update_field($selector, $value, $post_id)
    {
        if (function_exists('update_field')) {
            return update_field(koto_import_spec_json_resolve_field_selector($selector), $value, $post_id);
        }

        return update_post_meta($post_id, $selector, $value);
    }
}

if (!function_exists('koto_import_spec_json_resolve_field_selector')) {
    function koto_import_spec_json_resolve_field_selector($selector)
    {
        if (strpos((string)$selector, 'field_') === 0 || !function_exists('acf_get_field')) {
            return $selector;
        }

        $field = acf_get_field($selector);
        if ($field && !empty($field['key'])) {
            return $field['key'];
        }

        static $field_key_by_name = null;
        if ($field_key_by_name === null) {
            $field_key_by_name = [];
            $group_keys = [
                'group_69204fa4dd82e',
                'group_6937900895bf1',
                'group_693790bd6b499',
                'group_693969515ca4d',
                'group_693790ee221c3',
                'group_693971a11a6b2',
                'group_693c070768756',
                'group_69d4b6b256263',
            ];

            foreach ($group_keys as $group_key) {
                $fields = function_exists('acf_get_fields') ? acf_get_fields($group_key) : [];
                koto_import_spec_json_index_acf_fields($fields ?: [], $field_key_by_name);
            }
        }

        return $field_key_by_name[$selector] ?? $selector;
    }
}

if (!function_exists('koto_import_spec_json_index_acf_fields')) {
    function koto_import_spec_json_index_acf_fields($fields, array &$field_key_by_name)
    {
        if (empty($fields) || !is_array($fields)) {
            return;
        }

        foreach ($fields as $field) {
            if (!is_array($field)) continue;
            if (!empty($field['name']) && !empty($field['key'])) {
                $field_key_by_name[$field['name']] = $field['key'];
            }
            if (!empty($field['sub_fields'])) {
                koto_import_spec_json_index_acf_fields($field['sub_fields'], $field_key_by_name);
            }
        }
    }
}

if (!function_exists('koto_import_spec_json_apply_taxonomies')) {
    function koto_import_spec_json_apply_taxonomies($post_id, array $spec, array &$warnings)
    {
        $attr_slugs = [];
        if (!empty($spec['attribute'])) {
            $attr_slugs[] = (string)$spec['attribute'];
        }
        foreach ((array)($spec['sub_attributes'] ?? []) as $slug) {
            $attr_slugs[] = (string)$slug;
        }
        $attr_ids = koto_import_spec_json_term_ids('attribute', $attr_slugs, $warnings);
        if ($attr_ids) {
            wp_set_object_terms($post_id, $attr_ids, 'attribute');
        }

        $species_ids = koto_import_spec_json_term_ids('species', !empty($spec['species']) ? [(string)$spec['species']] : [], $warnings);
        if ($species_ids) {
            wp_set_object_terms($post_id, $species_ids, 'species');
        }

        $group_slugs = [];
        $group_names = [];
        foreach ((array)($spec['groups'] ?? []) as $group) {
            $slug = trim((string)($group['slug'] ?? ''));
            if ($slug === '') continue;
            $group_slugs[] = $slug;
            $group_names[$slug] = trim((string)($group['name'] ?? $slug));
        }
        $group_ids = koto_import_spec_json_term_ids('affiliation', $group_slugs, $warnings, $group_names);
        if ($group_ids) {
            wp_set_object_terms($post_id, $group_ids, 'affiliation');
        }

        $rarity_slug = '';
        if (!empty($spec['rarity_detail']) && $spec['rarity_detail'] !== 'none') {
            $rarity_slug = (string)$spec['rarity_detail'];
        } elseif (!empty($spec['rarity'])) {
            $rarity_slug = (string)$spec['rarity'];
        }
        if ($rarity_slug !== '') {
            $rarity_ids = koto_import_spec_json_term_ids('rarity', [$rarity_slug], $warnings);
            if ($rarity_ids) {
                wp_set_object_terms($post_id, $rarity_ids, 'rarity');
                koto_import_spec_json_update_field('rarity', $rarity_ids[0], $post_id);
            }
        }

        $char_slugs = [];
        $char_names = [];
        foreach ((array)($spec['chars'] ?? []) as $char) {
            $slug = trim((string)($char['slug'] ?? ''));
            $name = trim((string)($char['val'] ?? $slug));
            if ($slug === '' && $name !== '') {
                $slug = $name;
            }
            if ($slug === '') continue;
            $char_slugs[] = $slug;
            $char_names[$slug] = $name !== '' ? $name : $slug;
        }
        $char_ids = koto_import_spec_json_term_ids('available_moji', $char_slugs, $warnings, $char_names);
        if ($char_ids) {
            wp_set_object_terms($post_id, $char_ids, 'available_moji');
        }

        $gimmick_slugs = koto_import_spec_json_collect_gimmick_slugs($spec);
        $gimmick_ids = koto_import_spec_json_term_ids('gimmick', $gimmick_slugs, $warnings);
        if ($gimmick_ids) {
            wp_set_object_terms($post_id, $gimmick_ids, 'gimmick');
            koto_import_spec_json_update_field('gimmick', $gimmick_ids, $post_id);
        }
    }
}

if (!function_exists('koto_import_spec_json_apply_moji_loop')) {
    function koto_import_spec_json_apply_moji_loop($post_id, array $spec, array &$warnings)
    {
        $rows_by_key = [];
        $main_attr = (string)($spec['attribute'] ?? '');

        foreach ((array)($spec['chars'] ?? []) as $char) {
            $char_slug = trim((string)($char['slug'] ?? ''));
            $char_name = trim((string)($char['val'] ?? $char_slug));
            if ($char_slug === '' && $char_name !== '') {
                $char_slug = $char_name;
            }
            if ($char_slug === '') continue;

            $char_id = koto_import_spec_json_ensure_term('available_moji', $char_slug, $char_name !== '' ? $char_name : $char_slug, $warnings);
            if (!$char_id) continue;

            $attr_slug = trim((string)($char['attr'] ?? ''));
            if ($attr_slug === '') {
                $attr_slug = $main_attr;
            }
            $attr_id = $attr_slug !== '' ? koto_import_spec_json_ensure_term('attribute', $attr_slug, koto_import_spec_json_default_term_name('attribute', $attr_slug), $warnings) : 0;

            $unlock = trim((string)($char['unlock'] ?? 'normal'));
            $group_cond = false;
            if (strpos($unlock, 'group_cond_') === 0) {
                $group_cond = true;
                $unlock = substr($unlock, strlen('group_cond_'));
            }
            if ($unlock === '') $unlock = 'normal';

            $key = $attr_slug . '|' . $unlock . '|' . ($group_cond ? '1' : '0');
            if (!isset($rows_by_key[$key])) {
                $rows_by_key[$key] = [
                    'available_moji' => [],
                    'unlock_place' => $unlock,
                    'moji_group_cond' => $group_cond,
                    'moji_attr' => $attr_id ?: '',
                ];
            }
            $rows_by_key[$key]['available_moji'][] = $char_id;
        }

        if ($rows_by_key) {
            foreach ($rows_by_key as &$row) {
                $row['available_moji'] = array_values(array_unique(array_map('intval', $row['available_moji'])));
            }
            unset($row);
            koto_import_spec_json_update_field('available_moji_loop', array_values($rows_by_key), $post_id);
        }
    }
}

if (!function_exists('koto_import_spec_json_apply_skills')) {
    function koto_import_spec_json_apply_skills($post_id, array $spec, array &$warnings)
    {
        if (!empty($spec['waza']) && is_array($spec['waza'])) {
            koto_import_spec_json_update_field('waza_name', (string)($spec['waza']['name'] ?? ''), $post_id);
            $waza_groups = koto_import_spec_json_build_skill_groups($spec['waza']['variations'] ?? [], 'waza', 'none', $warnings);
            if ($waza_groups) {
                koto_import_spec_json_update_field('waza_group_loop', $waza_groups, $post_id);
            }
        }

        if (!empty($spec['sugowaza']) && is_array($spec['sugowaza'])) {
            $shift_type = (string)($spec['sugowaza']['shift_type'] ?? 'none');
            if ($shift_type === '') $shift_type = 'none';

            koto_import_spec_json_update_field('sugowaza_name', (string)($spec['sugowaza']['name'] ?? ''), $post_id);
            koto_import_spec_json_update_field('sugo_shift_type', $shift_type, $post_id);

            $sugo_conditions = koto_import_spec_json_build_sugowaza_conditions($spec['sugowaza']['condition'] ?? []);
            if ($sugo_conditions) {
                koto_import_spec_json_update_field('sugowaza_condition', $sugo_conditions, $post_id);
            }

            $sugo_groups = koto_import_spec_json_build_skill_groups($spec['sugowaza']['variations'] ?? [], 'sugo', $shift_type, $warnings);
            if ($sugo_groups) {
                koto_import_spec_json_update_field('sugowaza_group_loop', $sugo_groups, $post_id);
            }
        }
    }
}

if (!function_exists('koto_import_spec_json_build_skill_groups')) {
    function koto_import_spec_json_build_skill_groups($variations, $skill_type, $shift_type, array &$warnings)
    {
        if (empty($variations) || !is_array($variations)) {
            return [];
        }

        $groups = [];
        foreach ($variations as $variation) {
            if (!is_array($variation)) continue;

            $group = ['waza_add_cond_loop' => []];
            $detail_key = $skill_type === 'waza' ? 'waza_detail_loop' : 'sugo_detail_loop';
            $group[$detail_key] = [];

            $shift_values = (array)($variation['shift_value'] ?? []);
            if ($skill_type !== 'waza') {
                if ($shift_type === 'attr') {
                    $group['sugo_shift_attr'] = koto_import_spec_json_term_ids('attribute', $shift_values, $warnings);
                } elseif ($shift_type === 'moji') {
                    $group['sugo_shift_moji'] = koto_import_spec_json_term_ids('available_moji', $shift_values, $warnings);
                } elseif ($shift_type === 'attacked') {
                    $group['sugo_shift_attacked'] = (string)($shift_values[0] ?? '');
                } elseif ($shift_type === 'random') {
                    $group['random_count'] = (string)($shift_values[0] ?? '');
                }
            }

            foreach ((array)($variation['timelines'] ?? []) as $timeline) {
                if (!is_array($timeline)) continue;
                $group[$detail_key][] = koto_import_spec_json_build_skill_detail($timeline, $warnings);
            }

            $groups[] = $group;
        }

        return $groups;
    }
}

if (!function_exists('koto_import_spec_json_build_skill_detail')) {
    function koto_import_spec_json_build_skill_detail(array $timeline, array &$warnings)
    {
        $target = is_array($timeline['target'] ?? null) ? $timeline['target'] : [];
        $target_type = (string)($target['type'] ?? '');
        $target_objects = (array)($target['obj'] ?? []);
        $element_slug = trim((string)($timeline['element'] ?? ''));

        $detail = [
            'waza_type' => (string)($timeline['type'] ?? ''),
            'waza_target' => (string)($target['main'] ?? ''),
            'waza_target_detail' => $target_type !== '' ? $target_type : 'none',
            'target_detail_attr' => $target_type === 'attr' ? koto_import_spec_json_target_term_ids('attribute', $target_objects, $warnings) : [],
            'target_detail_species' => $target_type === 'species' ? koto_import_spec_json_target_term_ids('species', $target_objects, $warnings) : [],
            'target_detail_group' => $target_type === 'group' ? koto_import_spec_json_target_term_ids('affiliation', $target_objects, $warnings) : [],
            'target_detail_other' => $target_type === 'other' ? trim((string)($target_objects[0]['name'] ?? '')) : '',
            'waza_value' => (float)($timeline['value'] ?? 0),
            'waza_value_last' => (float)($timeline['value_last'] ?? 0),
            'hit_count' => (int)($timeline['hit_count'] ?? 1),
            'attack_attr' => $element_slug !== '' ? koto_import_spec_json_ensure_term('attribute', $element_slug, koto_import_spec_json_default_term_name('attribute', $element_slug), $warnings) : '',
            'attack_type' => is_array($timeline['attack_type'] ?? null) ? array_values($timeline['attack_type']) : [],
            'target_status' => (string)($timeline['resist_status'] ?? ''),
            'pressure_debuff_count' => !empty($timeline['pressure_debuff']) ? implode(',', (array)$timeline['pressure_debuff']) : '',
            'omni_advantage' => !empty($timeline['omni_advantage']),
            'is_moji_healing' => !empty($timeline['is_moji_healing']),
            'moji_exhaust' => !empty($timeline['moji_exhaust']),
            'turn_count' => (int)($timeline['turn_count'] ?? 1),
            'battle_field_loop' => koto_import_spec_json_build_battle_field_loop($timeline['bt_field_eff'] ?? [], $warnings),
        ];

        if (!empty($timeline['color_order']) && is_array($timeline['color_order'])) {
            $detail['colorfull_attack_attr'] = koto_import_spec_json_term_ids('attribute', $timeline['color_order'], $warnings);
        }

        if (!empty($timeline['at_type_target']) && is_array($timeline['at_type_target'])) {
            $detail['advantage_target'] = koto_import_spec_json_build_target_group($timeline['at_type_target'], $warnings);
        }

        if (isset($timeline['killer_rate']) && $timeline['killer_rate'] !== '') {
            $detail['advantage_rate'] = (float)$timeline['killer_rate'];
        }

        return $detail;
    }
}

if (!function_exists('koto_import_spec_json_build_battle_field_loop')) {
    function koto_import_spec_json_build_battle_field_loop($effects, array &$warnings)
    {
        if (empty($effects) || !is_array($effects)) {
            return [];
        }

        $rows = [];
        foreach ($effects as $effect) {
            if (!is_array($effect)) continue;
            $target_group = is_array($effect['target'] ?? null) ? $effect['target'] : [];
            $target_type = (string)($target_group['type'] ?? '');
            $target_objects = (array)($target_group['obj'] ?? []);

            $rows[] = [
                'battle_field_target' => $target_type,
                'battle_field_attr' => $target_type === 'attr' ? koto_import_spec_json_target_term_ids('attribute', $target_objects, $warnings) : [],
                'battle_field_species' => $target_type === 'species' ? koto_import_spec_json_target_term_ids('species', $target_objects, $warnings) : [],
                'battle_field_affiliation' => $target_type === 'group' ? koto_import_spec_json_target_term_ids('affiliation', $target_objects, $warnings) : [],
                'battle_field_moji' => $target_type === 'moji' ? koto_import_spec_json_target_term_ids('available_moji', $target_objects, $warnings) : [],
                'battle_field_value_type' => (string)($effect['value_type'] ?? 'normal'),
                'battle_field_value' => (int)($effect['value'] ?? 0),
            ];
        }

        return $rows;
    }
}

if (!function_exists('koto_import_spec_json_build_sugowaza_conditions')) {
    function koto_import_spec_json_build_sugowaza_conditions($condition_patterns)
    {
        if (empty($condition_patterns) || !is_array($condition_patterns)) {
            return [];
        }

        $patterns = [];
        foreach ($condition_patterns as $pattern) {
            if (!is_array($pattern)) continue;
            $row = [
                'get_place' => $pattern['get_place'] ?? 'default',
                'get_palce' => $pattern['get_place'] ?? 'default',
                'need_blessing_point' => $pattern['need_point'] ?? '',
                'sugo_cond_loop' => [],
            ];

            foreach ((array)($pattern['conditions'] ?? []) as $condition) {
                if (!is_array($condition)) continue;
                $values = isset($condition['values']) && is_array($condition['values'])
                    ? implode(',', $condition['values'])
                    : '';

                $row['sugo_cond_loop'][] = [
                    'sugo_cond_type' => $condition['type'] ?? '',
                    'sugo_cond_val' => $values,
                ];
            }

            $patterns[] = $row;
        }

        return $patterns;
    }
}

if (!function_exists('koto_import_spec_json_build_target_group')) {
    function koto_import_spec_json_build_target_group(array $target_group, array &$warnings)
    {
        $type = (string)($target_group['type'] ?? '');
        $objects = (array)($target_group['obj'] ?? []);

        return [
            'target_type' => $type,
            'target_attr' => $type === 'attr' ? koto_import_spec_json_target_term_ids('attribute', $objects, $warnings) : [],
            'target_species' => $type === 'species' ? koto_import_spec_json_target_term_ids('species', $objects, $warnings) : [],
            'target_group' => $type === 'group' ? koto_import_spec_json_target_term_ids('affiliation', $objects, $warnings) : [],
            'target_other' => $type === 'other' ? trim((string)($objects[0]['name'] ?? '')) : '',
        ];
    }
}

if (!function_exists('koto_import_spec_json_target_term_ids')) {
    function koto_import_spec_json_target_term_ids($taxonomy, array $objects, array &$warnings)
    {
        $slugs = [];
        $names = [];
        foreach ($objects as $object) {
            if (!is_array($object)) continue;
            $slug = trim((string)($object['slug'] ?? ''));
            $name = trim((string)($object['name'] ?? $slug));
            if ($slug === '' && $name !== '') {
                $slug = $name;
            }
            if ($slug === '') continue;
            $slugs[] = $slug;
            $names[$slug] = $name !== '' ? $name : $slug;
        }

        return koto_import_spec_json_term_ids($taxonomy, $slugs, $warnings, $names);
    }
}

if (!function_exists('koto_import_spec_json_term_ids')) {
    function koto_import_spec_json_term_ids($taxonomy, $slugs, array &$warnings, array $names = [])
    {
        $ids = [];
        foreach ((array)$slugs as $slug) {
            $slug = trim((string)$slug);
            if ($slug === '') continue;
            $name = $names[$slug] ?? koto_import_spec_json_default_term_name($taxonomy, $slug);
            $term_id = koto_import_spec_json_ensure_term($taxonomy, $slug, $name, $warnings);
            if ($term_id) {
                $ids[] = (int)$term_id;
            }
        }

        return array_values(array_unique($ids));
    }
}

if (!function_exists('koto_import_spec_json_ensure_term')) {
    function koto_import_spec_json_ensure_term($taxonomy, $slug, $name, array &$warnings)
    {
        $slug = trim((string)$slug);
        if ($slug === '' || !taxonomy_exists($taxonomy)) {
            return 0;
        }

        $term = get_term_by('slug', $slug, $taxonomy);
        if ($term && !is_wp_error($term)) {
            return (int)$term->term_id;
        }

        $created = wp_insert_term($name !== '' ? $name : $slug, $taxonomy, ['slug' => $slug]);
        if (is_wp_error($created)) {
            $warnings[] = sprintf('%s:%s のターム作成に失敗しました: %s', $taxonomy, $slug, $created->get_error_message());
            return 0;
        }

        $warnings[] = sprintf('%s:%s のタームを新規作成しました。', $taxonomy, $slug);
        return (int)$created['term_id'];
    }
}

if (!function_exists('koto_import_spec_json_default_term_name')) {
    function koto_import_spec_json_default_term_name($taxonomy, $slug)
    {
        $maps = [
            'attribute' => [
                'fire' => '火',
                'water' => '水',
                'wood' => '木',
                'light' => '光',
                'dark' => '闇',
                'void' => '冥',
                'heaven' => '天',
                'rainbow' => '虹',
            ],
            'species' => [
                'god' => '神',
                'demon' => '魔',
                'hero' => '英',
                'dragon' => '龍',
                'beast' => '獣',
                'spirit' => '霊',
                'artifact' => '物',
                'yokai' => '妖',
            ],
            'rarity' => [
                '1' => '星1',
                '2' => '星2',
                '3' => '星3',
                '4' => '星4',
                '5' => '星5',
                '6' => '星6',
                'legend' => 'レジェンド',
                'grand' => 'グランド',
                'dream' => 'スペシャル',
                'special' => 'スペシャル',
                'miracle' => 'ミラクル',
            ],
        ];

        return $maps[$taxonomy][$slug] ?? $slug;
    }
}

if (!function_exists('koto_import_spec_json_collect_gimmick_slugs')) {
    function koto_import_spec_json_collect_gimmick_slugs(array $spec)
    {
        $slugs = [];
        foreach (['trait1', 'trait2', 'blessing'] as $trait_key) {
            foreach ((array)($spec[$trait_key]['contents'] ?? []) as $trait) {
                if (!is_array($trait) || ($trait['type'] ?? '') !== 'gimmick') continue;
                $slug = trim((string)($trait['sub_type'] ?? ''));
                if ($slug !== '') {
                    $slugs[] = $slug;
                }
            }
        }

        foreach ((array)($spec['traits'] ?? []) as $trait) {
            if (!is_array($trait) || ($trait['type'] ?? '') !== 'gimmick') continue;
            $slug = trim((string)($trait['sub_type'] ?? ''));
            if ($slug !== '') {
                $slugs[] = $slug;
            }
        }

        return array_values(array_unique($slugs));
    }
}
