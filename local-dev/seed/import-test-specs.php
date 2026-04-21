<?php

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

$theme_dir = get_stylesheet_directory();
$spec_dir = $theme_dir . '/testdata/spec-json';

if (!is_dir($spec_dir)) {
    throw new RuntimeException('Spec fixture directory not found: ' . $spec_dir);
}

$attr_labels = [
    'fire' => '火',
    'water' => '水',
    'wood' => '木',
    'light' => '光',
    'dark' => '闇',
    'void' => '冥',
    'heaven' => '天',
    'rainbow' => '虹',
];

$species_labels = [
    'god' => '神',
    'demon' => '魔',
    'hero' => '英',
    'dragon' => '龍',
    'beast' => '獣',
    'spirit' => '霊',
    'artifact' => '物',
    'yokai' => '妖',
];

$rarity_labels = [
    '6' => '星6',
    'legend' => 'レジェンド',
    'grand' => 'グランド',
];

$gimmick_labels = [
    'shield' => 'シールドブレイカー',
    'needle' => 'トゲガード',
    'change' => 'チェンジガード',
    'weakening' => '弱体ガード',
    'wall' => 'ウォールブレイカー',
    'shock' => 'ビリビリガード',
    'healing' => 'ヒールブレイカー',
    'copy' => 'コピーガード',
    'freezing' => 'フリーズブレイカー',
    'landmine' => '地雷ガード',
    'smash' => 'スマッシュブレイカー',
    'balloon' => 'バルーンガード',
    'healing_core' => 'ヒールコア',
    'attack_core' => 'アタックコア',
    'attack_buff_core' => 'アタックバフコア',
    'super_attack_core' => 'スーパーアタックコア',
];

$ensure_term = static function ($taxonomy, $slug, $name, $parent_slug = null) {
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
};

$gimmick_label = static function ($slug) use ($gimmick_labels) {
    if (isset($gimmick_labels[$slug])) {
        return $gimmick_labels[$slug];
    }

    if (strpos($slug, 'super_') === 0) {
        $base_slug = substr($slug, 6);
        $base_label = $gimmick_labels[$base_slug] ?? $base_slug;
        return 'スーパー' . $base_label;
    }

    return $slug;
};

$find_existing_post_id = static function (array $spec) {
    $spec_id = (int) ($spec['id'] ?? 0);
    if ($spec_id <= 0) {
        return 0;
    }

    $posts = get_posts([
        'post_type' => 'character',
        'post_status' => ['publish', 'draft', 'pending', 'future', 'private'],
        'posts_per_page' => 1,
        'fields' => 'ids',
        'meta_key' => '_local_test_spec_id',
        'meta_value' => (string) $spec_id,
    ]);
    if (!empty($posts)) {
        return (int) $posts[0];
    }

    $decoded_name = html_entity_decode((string) ($spec['name'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if ($decoded_name === '') {
        return 0;
    }

    $posts = get_posts([
        'post_type' => 'character',
        'post_status' => ['publish', 'draft', 'pending', 'future', 'private'],
        'title' => $decoded_name,
        'posts_per_page' => 1,
        'fields' => 'ids',
    ]);

    return !empty($posts) ? (int) $posts[0] : 0;
};

foreach ($attr_labels as $slug => $label) {
    $ensure_term('attribute', $slug, $label);
}

foreach ($species_labels as $slug => $label) {
    $ensure_term('species', $slug, $label);
}

$ensure_term('rarity', '6', $rarity_labels['6']);
$ensure_term('rarity', 'legend', $rarity_labels['legend'], '6');
$ensure_term('rarity', 'grand', $rarity_labels['grand'], '6');

$spec_files = glob($spec_dir . '/*.json');
sort($spec_files);

$imported = [];

foreach ($spec_files as $spec_file) {
    $raw = file_get_contents($spec_file);
    $spec = json_decode($raw, true);

    if (!is_array($spec) || empty($spec['id'])) {
        throw new RuntimeException('Invalid spec fixture: ' . basename($spec_file));
    }

    $spec_id = (int) $spec['id'];
    $post_title = html_entity_decode((string) ($spec['name'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $post_name = (string) $spec_id;
    $existing_post_id = $find_existing_post_id($spec);

    $post_args = [
        'post_type' => 'character',
        'post_status' => 'publish',
        'post_title' => $post_title,
        'post_name' => $post_name,
        'post_content' => '',
    ];

    if ($existing_post_id > 0) {
        $post_args['ID'] = $existing_post_id;
        $post_id = wp_update_post($post_args, true);
    } else {
        $post_id = wp_insert_post($post_args, true);
    }

    if (is_wp_error($post_id)) {
        throw new RuntimeException($post_id->get_error_message());
    }

    $wpdb->update(
        $wpdb->posts,
        ['post_name' => $post_name],
        ['ID' => $post_id],
        ['%s'],
        ['%d']
    );
    clean_post_cache($post_id);

    update_post_meta($post_id, '_local_test_spec_id', (string) $spec_id);
    update_post_meta($post_id, '_spec_json', wp_slash(wp_json_encode($spec, JSON_UNESCAPED_UNICODE)));
    update_post_meta($post_id, 'name_ruby', $spec['name_ruby'] ?? '');
    update_post_meta($post_id, 'voice_actor', html_entity_decode((string) ($spec['cv'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    update_post_meta($post_id, 'get_place', $spec['acquisition'] ?? '');
    update_post_meta($post_id, 'impl_date', $spec['release_date'] ?? '');
    update_post_meta($post_id, '99_hp', (int) ($spec['_val_99_hp'] ?? 0));
    update_post_meta($post_id, '99_atk', (int) ($spec['_val_99_atk'] ?? 0));
    update_post_meta($post_id, '120_hp', (int) ($spec['_val_120_hp'] ?? 0));
    update_post_meta($post_id, '120_atk', (int) ($spec['_val_120_atk'] ?? 0));
    update_post_meta($post_id, 'talent_hp', (int) ($spec['talent_hp'] ?? 0));
    update_post_meta($post_id, 'talent_atk', (int) ($spec['talent_atk'] ?? 0));
    update_post_meta($post_id, 'firepower_index', (int) ($spec['firepower_index'] ?? 0));

    $attr_terms = [];
    if (!empty($spec['attribute'])) {
        $attr_terms[] = (string) $spec['attribute'];
    }
    if (!empty($spec['sub_attributes']) && is_array($spec['sub_attributes'])) {
        foreach ($spec['sub_attributes'] as $sub_attr_slug) {
            $attr_terms[] = (string) $sub_attr_slug;
        }
    }
    $attr_terms = array_values(array_unique(array_filter($attr_terms)));

    $species_terms = !empty($spec['species']) ? [(string) $spec['species']] : [];

    $affiliation_terms = [];
    foreach (($spec['groups'] ?? []) as $group) {
        $group_slug = trim((string) ($group['slug'] ?? ''));
        $group_name = trim((string) ($group['name'] ?? $group_slug));
        if ($group_slug === '') {
            continue;
        }
        $ensure_term('affiliation', $group_slug, $group_name);
        $affiliation_terms[] = $group_slug;
    }

    $rarity_terms = [];
    if (!empty($spec['rarity'])) {
        $rarity_terms[] = (string) $spec['rarity'];
    }
    if (!empty($spec['rarity_detail']) && $spec['rarity_detail'] !== 'none') {
        $rarity_terms[] = (string) $spec['rarity_detail'];
    }

    $available_moji_terms = [];
    foreach (($spec['chars'] ?? []) as $char) {
        $char_slug = trim((string) ($char['slug'] ?? ''));
        $char_val = trim((string) ($char['val'] ?? $char_slug));
        if ($char_slug === '') {
            continue;
        }
        $ensure_term('available_moji', $char_slug, $char_val);
        $available_moji_terms[] = $char_slug;
    }
    $available_moji_terms = array_values(array_unique($available_moji_terms));

    $gimmick_terms = [];
    foreach (['trait1', 'trait2', 'blessing'] as $trait_key) {
        foreach (($spec[$trait_key]['contents'] ?? []) as $trait) {
            if (($trait['type'] ?? '') !== 'gimmick') {
                continue;
            }
            $gimmick_slug = trim((string) ($trait['sub_type'] ?? ''));
            if ($gimmick_slug === '') {
                continue;
            }
            $ensure_term('gimmick', $gimmick_slug, $gimmick_label($gimmick_slug));
            $gimmick_terms[] = $gimmick_slug;
        }
    }
    $gimmick_terms = array_values(array_unique($gimmick_terms));

    wp_set_object_terms($post_id, $attr_terms, 'attribute');
    wp_set_object_terms($post_id, $species_terms, 'species');
    wp_set_object_terms($post_id, $affiliation_terms, 'affiliation');
    wp_set_object_terms($post_id, $rarity_terms, 'rarity');
    wp_set_object_terms($post_id, $available_moji_terms, 'available_moji');
    wp_set_object_terms($post_id, $gimmick_terms, 'gimmick');

    $imported[] = [
        'spec_id' => $spec_id,
        'post_id' => (int) $post_id,
        'title' => $post_title,
    ];
}

wp_cache_flush();
flush_rewrite_rules(false);

echo wp_json_encode($imported, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
