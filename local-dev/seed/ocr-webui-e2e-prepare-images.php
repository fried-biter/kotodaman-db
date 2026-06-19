<?php
if (!defined('ABSPATH')) exit;

function koto_ocr_webui_e2e_args(array $argv)
{
    $args = [
        'input' => get_stylesheet_directory() . '/local-dev/seed/ocr-fixtures/live-openrouter/input',
        'out' => sys_get_temp_dir() . '/kotodaman-ocr-e2e',
        'cases' => [],
        'max_side' => 1440,
        'quality' => 82,
    ];
    foreach ($argv as $arg) {
        if (strpos($arg, 'input=') === 0) {
            $args['input'] = substr($arg, strlen('input='));
        } elseif (strpos($arg, 'out=') === 0) {
            $args['out'] = substr($arg, strlen('out='));
        } elseif (strpos($arg, 'case=') === 0) {
            $args['cases'][] = substr($arg, strlen('case='));
        } elseif (strpos($arg, 'cases=') === 0) {
            $args['cases'] = array_merge($args['cases'], explode(',', substr($arg, strlen('cases='))));
        } elseif (strpos($arg, 'max_side=') === 0) {
            $args['max_side'] = max(320, (int) substr($arg, strlen('max_side=')));
        } elseif (strpos($arg, 'quality=') === 0) {
            $args['quality'] = max(1, min(100, (int) substr($arg, strlen('quality='))));
        }
    }
    $args['cases'] = array_values(array_unique(array_filter(array_map('trim', $args['cases']))));
    return $args;
}

function koto_ocr_webui_e2e_case_images($case_dir)
{
    $paths = glob(rtrim($case_dir, '/') . '/*.{png,PNG,jpg,JPG,jpeg,JPEG,webp,WEBP}', GLOB_BRACE) ?: [];
    sort($paths, SORT_NATURAL);
    return $paths;
}

function koto_ocr_webui_e2e_resize_image($source, $target, $max_side, $quality)
{
    $editor = wp_get_image_editor($source);
    if (is_wp_error($editor)) {
        return $editor;
    }

    $size = $editor->get_size();
    $width = (int) ($size['width'] ?? 0);
    $height = (int) ($size['height'] ?? 0);
    if ($width <= 0 || $height <= 0) {
        return new WP_Error('koto_ocr_webui_e2e_invalid_image', '画像サイズを取得できません: ' . $source);
    }

    $scale = min(1, $max_side / max($width, $height));
    if ($scale < 1) {
        $resized = $editor->resize(max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)), false);
        if (is_wp_error($resized)) {
            return $resized;
        }
    }

    $editor->set_quality($quality);
    $saved = $editor->save($target, 'image/jpeg');
    if (is_wp_error($saved)) {
        return $saved;
    }
    return true;
}

function koto_ocr_webui_e2e_prepare_images(array $args)
{
    $input = rtrim($args['input'], '/');
    $out = rtrim($args['out'], '/');
    $cases = $args['cases'];
    if (empty($cases)) {
        $case_dirs = glob($input . '/*', GLOB_ONLYDIR) ?: [];
        foreach ($case_dirs as $case_dir) {
            if (is_readable($case_dir . '/spec.json') && koto_ocr_webui_e2e_case_images($case_dir)) {
                $cases[] = basename($case_dir);
            }
        }
        sort($cases, SORT_NATURAL);
    }

    $prepared = [];
    foreach ($cases as $case) {
        $case = sanitize_key($case);
        $case_dir = $input . '/' . $case;
        if (!is_dir($case_dir)) {
            return new WP_Error('koto_ocr_webui_e2e_missing_case', 'caseディレクトリがありません: ' . $case_dir);
        }

        $target_dir = $out . '/' . $case;
        if (!wp_mkdir_p($target_dir)) {
            return new WP_Error('koto_ocr_webui_e2e_mkdir', '出力ディレクトリを作成できません: ' . $target_dir);
        }

        $files = [];
        foreach (koto_ocr_webui_e2e_case_images($case_dir) as $index => $source) {
            $target_name = sprintf('%02d-%s.jpg', $index + 1, sanitize_file_name(pathinfo($source, PATHINFO_FILENAME)));
            $target = trailingslashit($target_dir) . $target_name;
            $result = koto_ocr_webui_e2e_resize_image($source, $target, (int) $args['max_side'], (int) $args['quality']);
            if (is_wp_error($result)) {
                return $result;
            }
            $files[] = [
                'name' => $target_name,
                'path' => $target,
                'bytes' => filesize($target),
            ];
        }

        $prepared[$case] = [
            'case' => $case,
            'count' => count($files),
            'files' => $files,
        ];
    }

    return $prepared;
}

$cli_args = isset($args) && is_array($args) ? $args : (isset($argv) && is_array($argv) ? array_slice($argv, 1) : []);
$result = koto_ocr_webui_e2e_prepare_images(koto_ocr_webui_e2e_args($cli_args));
if (is_wp_error($result)) {
    WP_CLI::error($result->get_error_message());
}

WP_CLI::line(wp_json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
