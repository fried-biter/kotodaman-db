<?php

declare(strict_types=1);

add_filter('acf/settings/save_json', function (string $path): string {
    return get_stylesheet_directory() . '/acf-json';
}, 20);

add_filter('acf/settings/load_json', function (array $paths): array {
    return [get_stylesheet_directory() . '/acf-json'];
}, 20);
