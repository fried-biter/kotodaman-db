<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('kotodaman_local_upsert_term')) {
    function kotodaman_local_upsert_term($taxonomy, $slug, $name, $parent_slug = null)
    {
        $parent = 0;
        if ($parent_slug) {
            $parent_term = get_term_by('slug', $parent_slug, $taxonomy);
            if ($parent_term && !is_wp_error($parent_term)) {
                $parent = (int) $parent_term->term_id;
            }
        }

        $existing = get_term_by('slug', $slug, $taxonomy);
        if ($existing && !is_wp_error($existing)) {
            wp_update_term($existing->term_id, $taxonomy, [
                'name' => $name,
                'slug' => $slug,
                'parent' => $parent,
            ]);

            return (int) $existing->term_id;
        }

        $created = wp_insert_term($name, $taxonomy, [
            'slug' => $slug,
            'parent' => $parent,
        ]);

        if (is_wp_error($created)) {
            throw new RuntimeException($created->get_error_message());
        }

        return (int) $created['term_id'];
    }
}

if (!function_exists('kotodaman_local_upsert_page')) {
    function kotodaman_local_upsert_page($slug, $title, $template = '', $meta = [])
    {
        $page = get_page_by_path($slug, OBJECT, 'page');
        $page_args = [
            'post_type' => 'page',
            'post_status' => 'publish',
            'post_title' => $title,
            'post_name' => $slug,
            'post_content' => '',
        ];

        if ($page) {
            $page_args['ID'] = $page->ID;
            $page_id = wp_update_post($page_args, true);
        } else {
            $page_id = wp_insert_post($page_args, true);
        }

        if (is_wp_error($page_id)) {
            throw new RuntimeException($page_id->get_error_message());
        }

        if ($template !== '') {
            update_post_meta($page_id, '_wp_page_template', $template);
        }

        foreach ($meta as $key => $value) {
            update_post_meta($page_id, $key, $value);
        }

        return (int) $page_id;
    }
}

if (!function_exists('kotodaman_local_upsert_character')) {
    function kotodaman_local_upsert_character(array $config)
    {
        $post = get_page_by_path($config['slug'], OBJECT, 'character');
        $post_args = [
            'post_type' => 'character',
            'post_status' => 'publish',
            'post_title' => $config['title'],
            'post_name' => $config['slug'],
            'post_content' => $config['content'] ?? 'Local dummy character for theme development.',
        ];

        if ($post) {
            $post_args['ID'] = $post->ID;
            $post_id = wp_update_post($post_args, true);
        } else {
            $post_id = wp_insert_post($post_args, true);
        }

        if (is_wp_error($post_id)) {
            throw new RuntimeException($post_id->get_error_message());
        }

        foreach ($config['tax_input'] as $taxonomy => $terms) {
            wp_set_object_terms($post_id, $terms, $taxonomy);
        }

        $spec = $config['spec'];
        update_post_meta($post_id, '_spec_json', wp_slash(wp_json_encode($spec, JSON_UNESCAPED_UNICODE)));

        foreach ($config['meta'] as $meta_key => $meta_value) {
            update_post_meta($post_id, $meta_key, $meta_value);
        }

        return (int) $post_id;
    }
}

$seed_version = 1;
$existing_seed_version = (int) get_option('kotodaman_local_seed_version', 0);

if ($existing_seed_version >= $seed_version) {
    echo "Seed already applied\n";
    return;
}

$terms = [
    ['attribute', 'fire', '火', null],
    ['attribute', 'water', '水', null],
    ['attribute', 'wood', '木', null],
    ['attribute', 'light', '光', null],
    ['attribute', 'dark', '闇', null],
    ['species', 'god', '神', null],
    ['species', 'hero', '英', null],
    ['species', 'dragon', '龍', null],
    ['affiliation', 'starter-party', 'スターターパーティ', null],
    ['affiliation', 'summer-fes', 'サマーフェス', null],
    ['event', 'launch-event', 'リリース記念', null],
    ['event', 'summer-2026', '夏イベント2026', null],
    ['gimmick', 'shield', 'シールド', null],
    ['gimmick', 'wall', 'ウォール', null],
    ['rarity', '6', '星6', null],
    ['rarity', 'legend', 'レジェンド', '6'],
    ['available_moji', 'あ', 'あ', null],
    ['available_moji', 'い', 'い', null],
    ['available_moji', 'う', 'う', null],
    ['available_moji', 'か', 'か', null],
    ['available_moji', 'さ', 'さ', null],
    ['available_moji', 'ん', 'ん', null],
];

foreach ($terms as [$taxonomy, $slug, $name, $parent_slug]) {
    kotodaman_local_upsert_term($taxonomy, $slug, $name, $parent_slug);
}

$characters = [
    [
        'slug' => 'sample-kaen',
        'title' => '火宴・カエン',
        'spec' => [
            'id' => 0,
            'name' => '火宴・カエン',
            'name_ruby' => 'かえん',
            'pre_evo_name' => '',
            'another_image_name' => '',
            'cv' => '山田太郎',
            'acquisition' => 'ガチャ',
            'release_date' => '2026-04-01',
            'attribute' => 'fire',
            'sub_attributes' => ['light'],
            'species' => 'god',
            'groups' => [
                ['slug' => 'starter-party', 'name' => 'スターターパーティ'],
            ],
            'rarity' => 6,
            'rarity_detail' => 'legend',
            'chars' => [
                ['val' => 'か', 'attr' => 'fire', 'unlock' => 'default'],
                ['val' => 'ん', 'attr' => 'light', 'unlock' => 'blessing'],
            ],
            '_val_99_hp' => 1821,
            '_val_99_atk' => 742,
            '_val_120_hp' => 2210,
            '_val_120_atk' => 901,
            'talent_hp' => 120,
            'talent_atk' => 60,
            'priority' => 5,
            'buff_counts_hand' => [0, 1, 0, 0, 0, 0],
            'buff_counts_board' => [0, 0, 1, 0, 0, 0],
            'debuff_counts' => [0, 0, 0, 0, 0, 0],
            'max_ls_hp' => 120,
            'max_ls_atk' => 180,
            'waza' => ['name' => 'フレイムエッジ', 'variations' => []],
            'sugowaza' => ['name' => 'インフェルノレイド', 'variations' => []],
            'kotowaza' => [],
            'trait1' => [
                'name' => '炎の守り',
                'contents' => [
                    ['type' => 'gimmick', 'sub_type' => 'shield'],
                ],
            ],
            'trait2' => ['name' => '火力補助', 'contents' => []],
            'blessing' => ['name' => '祝福', 'contents' => []],
            'leader' => [],
            'miracle_leader' => [],
        ],
        'meta' => [
            'name_ruby' => 'かえん',
            'voice_actor' => '山田太郎',
            'get_place' => 'ガチャ',
            'impl_date' => '2026-04-01',
            '99_hp' => 1821,
            '99_atk' => 742,
            '120_hp' => 2210,
            '120_atk' => 901,
            'talent_hp' => 120,
            'talent_atk' => 60,
            'max_hp' => 2330,
            'max_atk' => 961,
            'firepower_index' => 0,
            '_search_tags_str' => ' axis_i char_connector ',
            '_waza_tags_str' => ' type_attack type_attack_single ',
            '_sugo_tags_str' => ' type_attack type_attack_all type_omni_advantage ',
            '_kotowaza_tags_str' => ' ',
            '_trait_tags_str_1' => ' trait_other trait_other_penetration ',
            '_trait_tags_str_2' => ' trait_damage_correction trait_damage_correction_oneself ',
            '_trait_tags_str_blessing' => ' ',
        ],
        'tax_input' => [
            'attribute' => ['fire', 'light'],
            'species' => ['god'],
            'affiliation' => ['starter-party'],
            'event' => ['launch-event'],
            'gimmick' => ['shield'],
            'rarity' => ['6', 'legend'],
            'available_moji' => ['か', 'ん'],
        ],
    ],
    [
        'slug' => 'sample-suisei',
        'title' => '水星・スイセイ',
        'spec' => [
            'id' => 0,
            'name' => '水星・スイセイ',
            'name_ruby' => 'すいせい',
            'pre_evo_name' => '',
            'another_image_name' => '',
            'cv' => '佐藤花子',
            'acquisition' => 'その他',
            'release_date' => '2026-03-15',
            'attribute' => 'water',
            'sub_attributes' => [],
            'species' => 'hero',
            'groups' => [
                ['slug' => 'summer-fes', 'name' => 'サマーフェス'],
            ],
            'rarity' => 6,
            'rarity_detail' => 'legend',
            'chars' => [
                ['val' => 'い', 'attr' => 'water', 'unlock' => 'default'],
                ['val' => 'う', 'attr' => 'water', 'unlock' => 'default'],
            ],
            '_val_99_hp' => 1710,
            '_val_99_atk' => 810,
            '_val_120_hp' => 2080,
            '_val_120_atk' => 980,
            'talent_hp' => 80,
            'talent_atk' => 90,
            'priority' => 4,
            'buff_counts_hand' => [0, 0, 0, 1, 0, 0],
            'buff_counts_board' => [0, 0, 0, 0, 0, 0],
            'debuff_counts' => [0, 1, 0, 0, 0, 0],
            'max_ls_hp' => 100,
            'max_ls_atk' => 160,
            'waza' => ['name' => 'アクアスラッシュ', 'variations' => []],
            'sugowaza' => ['name' => 'タイダルバースト', 'variations' => []],
            'kotowaza' => [],
            'trait1' => [
                'name' => '耐壁',
                'contents' => [
                    ['type' => 'gimmick', 'sub_type' => 'wall'],
                ],
            ],
            'trait2' => ['name' => '支援', 'contents' => []],
            'blessing' => ['name' => '祝福', 'contents' => []],
            'leader' => [],
            'miracle_leader' => [],
        ],
        'meta' => [
            'name_ruby' => 'すいせい',
            'voice_actor' => '佐藤花子',
            'get_place' => 'その他',
            'impl_date' => '2026-03-15',
            '99_hp' => 1710,
            '99_atk' => 810,
            '120_hp' => 2080,
            '120_atk' => 980,
            'talent_hp' => 80,
            'talent_atk' => 90,
            'max_hp' => 2160,
            'max_atk' => 1070,
            'firepower_index' => 0,
            '_search_tags_str' => ' axis_u ',
            '_waza_tags_str' => ' type_attack type_attack_all_multi ',
            '_sugo_tags_str' => ' type_heal type_debuff type_def_debuff ',
            '_kotowaza_tags_str' => ' ',
            '_trait_tags_str_1' => ' trait_status_up trait_status_up_resistance ',
            '_trait_tags_str_2' => ' trait_draw_eff trait_draw_eff_healing ',
            '_trait_tags_str_blessing' => ' ',
        ],
        'tax_input' => [
            'attribute' => ['water'],
            'species' => ['hero'],
            'affiliation' => ['summer-fes'],
            'event' => ['summer-2026'],
            'gimmick' => ['wall'],
            'rarity' => ['6', 'legend'],
            'available_moji' => ['い', 'う'],
        ],
    ],
    [
        'slug' => 'sample-ryuoh',
        'title' => '龍王・リュウオウ',
        'spec' => [
            'id' => 0,
            'name' => '龍王・リュウオウ',
            'name_ruby' => 'りゅうおう',
            'pre_evo_name' => '',
            'another_image_name' => '',
            'cv' => '高橋一郎',
            'acquisition' => 'ガチャ',
            'release_date' => '2026-02-10',
            'attribute' => 'wood',
            'sub_attributes' => ['dark'],
            'species' => 'dragon',
            'groups' => [
                ['slug' => 'starter-party', 'name' => 'スターターパーティ'],
                ['slug' => 'summer-fes', 'name' => 'サマーフェス'],
            ],
            'rarity' => 6,
            'rarity_detail' => 'legend',
            'chars' => [
                ['val' => 'あ', 'attr' => 'wood', 'unlock' => 'default'],
                ['val' => 'さ', 'attr' => 'dark', 'unlock' => 'super_change'],
            ],
            '_val_99_hp' => 1960,
            '_val_99_atk' => 700,
            '_val_120_hp' => 2390,
            '_val_120_atk' => 860,
            'talent_hp' => 150,
            'talent_atk' => 50,
            'priority' => 2,
            'buff_counts_hand' => [0, 0, 0, 0, 1, 0],
            'buff_counts_board' => [0, 0, 0, 0, 0, 1],
            'debuff_counts' => [0, 0, 1, 0, 0, 0],
            'max_ls_hp' => 140,
            'max_ls_atk' => 140,
            'waza' => ['name' => 'ドラゴンブレス', 'variations' => []],
            'sugowaza' => ['name' => 'フォレストディザスター', 'variations' => []],
            'kotowaza' => [],
            'trait1' => ['name' => '特性1', 'contents' => []],
            'trait2' => ['name' => '特性2', 'contents' => []],
            'blessing' => ['name' => '祝福', 'contents' => []],
            'leader' => [],
            'miracle_leader' => [],
        ],
        'meta' => [
            'name_ruby' => 'りゅうおう',
            'voice_actor' => '高橋一郎',
            'get_place' => 'ガチャ',
            'impl_date' => '2026-02-10',
            '99_hp' => 1960,
            '99_atk' => 700,
            '120_hp' => 2390,
            '120_atk' => 860,
            'talent_hp' => 150,
            'talent_atk' => 50,
            'max_hp' => 2540,
            'max_atk' => 910,
            'firepower_index' => 0,
            '_search_tags_str' => ' axis_youon ',
            '_waza_tags_str' => ' type_buff type_atk_buff ',
            '_sugo_tags_str' => ' type_attack type_attack_random ',
            '_kotowaza_tags_str' => ' ',
            '_trait_tags_str_1' => ' trait_new_traits trait_new_traits_support ',
            '_trait_tags_str_2' => ' trait_other trait_other_over_healing ',
            '_trait_tags_str_blessing' => ' ',
        ],
        'tax_input' => [
            'attribute' => ['wood', 'dark'],
            'species' => ['dragon'],
            'affiliation' => ['starter-party', 'summer-fes'],
            'event' => ['launch-event', 'summer-2026'],
            'gimmick' => ['shield', 'wall'],
            'rarity' => ['6', 'legend'],
            'available_moji' => ['あ', 'さ'],
        ],
    ],
];

$character_ids = [];
foreach ($characters as $character) {
    $post_id = kotodaman_local_upsert_character($character);
    $raw_spec = json_decode(get_post_meta($post_id, '_spec_json', true), true);
    $raw_spec['id'] = $post_id;
    update_post_meta($post_id, '_spec_json', wp_slash(wp_json_encode($raw_spec, JSON_UNESCAPED_UNICODE)));
    $character_ids[] = $post_id;
}

$front_page_id = kotodaman_local_upsert_page('db-top', 'コトダマンDBトップ', 'page-db-top.php', [
    'pickup_chara' => array_slice($character_ids, 0, 3),
]);

kotodaman_local_upsert_page('affiliation-list', 'グループ一覧', 'page-term-list.php', [
    'target_taxonomy' => 'affiliation',
]);

kotodaman_local_upsert_page('event-list', 'イベント一覧', 'page-term-list.php', [
    'target_taxonomy' => 'event',
]);

kotodaman_local_upsert_page('mgn-blank-charas', '未入力記事リスト', 'page-missing-info.php');
kotodaman_local_upsert_page('magnification-calc', '簡易ダメージ・倍率計算機', 'page-simple-calc.php');
kotodaman_local_upsert_page('character-list', 'キャラクター一覧プレビュー', 'page-character-list.php');
kotodaman_local_upsert_page('event-date', 'イベント・キャラ一覧ページ', 'page-event-date-list.php', [
    'event_loop' => [
        [
            'event_name' => 'ダミーイベント一覧',
            'event_detail_name' => 'リリース記念クエスト',
            'event_date' => '01/04/2026',
            'character_name_loop' => [
                ['character_name' => '火宴・カエン', 'done_tf' => true],
                ['character_name' => '水星・スイセイ', 'done_tf' => false],
            ],
        ],
        [
            'event_name' => '',
            'event_detail_name' => '夏イベント2026 前半',
            'event_date' => '15/07/2026',
            'character_name_loop' => [
                ['character_name' => '龍王・リュウオウ', 'done_tf' => false],
            ],
        ],
    ],
]);

update_option('show_on_front', 'page');
update_option('page_on_front', $front_page_id);
update_option('kotodaman_local_seed_version', $seed_version);

echo "Seeded local dummy data\n";
