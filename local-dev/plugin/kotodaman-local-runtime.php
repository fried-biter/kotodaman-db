<?php
/**
 * Plugin Name: Kotodaman Local Runtime
 * Description: Local-only content model and helper behavior for the kotodaman-db child theme.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', function () {
    register_post_type('character', [
        'labels' => [
            'name' => 'Characters',
            'singular_name' => 'Character',
        ],
        'public' => true,
        'show_in_rest' => true,
        'has_archive' => true,
        'rewrite' => ['slug' => 'character'],
        'supports' => ['title', 'editor', 'thumbnail', 'excerpt'],
        'menu_icon' => 'dashicons-universal-access-alt',
    ]);

    $taxonomies = [
        'attribute' => ['slug' => 'attribute', 'hierarchical' => false, 'label' => '属性'],
        'species' => ['slug' => 'species', 'hierarchical' => false, 'label' => '種族'],
        'affiliation' => ['slug' => 'affiliation', 'hierarchical' => true, 'label' => 'グループ'],
        'event' => ['slug' => 'event', 'hierarchical' => true, 'label' => 'イベント'],
        'gimmick' => ['slug' => 'gimmick', 'hierarchical' => true, 'label' => 'ギミック'],
        'rarity' => ['slug' => 'rarity', 'hierarchical' => true, 'label' => 'レアリティ'],
        'available_moji' => ['slug' => 'available-moji', 'hierarchical' => false, 'label' => '使用可能文字'],
    ];

    foreach ($taxonomies as $taxonomy => $config) {
        register_taxonomy($taxonomy, ['character'], [
            'labels' => [
                'name' => $config['label'],
                'singular_name' => $config['label'],
            ],
            'public' => true,
            'show_in_rest' => true,
            'hierarchical' => $config['hierarchical'],
            'rewrite' => ['slug' => $config['slug']],
        ]);
    }
}, 0);

add_filter('option_blogdescription', function ($description) {
    if ($description !== '') {
        return $description;
    }

    return 'Local WordPress environment for the Kotodaman database theme.';
});
