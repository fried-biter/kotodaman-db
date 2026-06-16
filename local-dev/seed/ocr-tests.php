<?php
if (!defined('ABSPATH')) exit;

class Koto_Ocr_Test_Failure extends Exception {}

function koto_ocr_test_parse_args(array $argv)
{
    $args = ['suite' => 'all'];
    foreach ($argv as $arg) {
        if (strpos($arg, '--suite=') === 0) {
            $args['suite'] = substr($arg, strlen('--suite='));
        }
    }
    return $args;
}

function koto_ocr_test_fixture($relative_path)
{
    $path = __DIR__ . '/ocr-fixtures/' . ltrim($relative_path, '/');
    if (!is_readable($path)) {
        throw new Koto_Ocr_Test_Failure('fixture not readable: ' . $relative_path);
    }
    $decoded = json_decode((string) file_get_contents($path), true);
    if (!is_array($decoded)) {
        throw new Koto_Ocr_Test_Failure('fixture JSON parse failed: ' . $relative_path);
    }
    return $decoded;
}

function koto_ocr_test_assert_same($expected, $actual, $message)
{
    if ($expected === $actual) {
        return;
    }
    throw new Koto_Ocr_Test_Failure($message . ' expected=' . wp_json_encode($expected, JSON_UNESCAPED_UNICODE) . ' actual=' . wp_json_encode($actual, JSON_UNESCAPED_UNICODE));
}

function koto_ocr_test_assert_array_contains_assoc(array $expected, array $actual, $message)
{
    foreach ($expected as $key => $value) {
        if (!array_key_exists($key, $actual)) {
            throw new Koto_Ocr_Test_Failure($message . ' missing key=' . $key);
        }
        koto_ocr_test_assert_same($value, $actual[$key], $message . ' key=' . $key);
    }
}

function koto_ocr_test_build_draft_from_normalized(array $normalized)
{
    $extracted = koto_ocr_extract_fields($normalized);
    $fragment = koto_ocr_build_spec_fragments($extracted);
    return koto_ocr_build_draft_spec($normalized, $extracted, $fragment);
}

function koto_ocr_test_cases()
{
    return [
        [
            'suite' => 'pipeline',
            'name' => 'basic main normalized OCR builds draft spec',
            'run' => function () {
                $fixture = koto_ocr_test_fixture('pipeline/basic-main.json');
                $draft = koto_ocr_test_build_draft_from_normalized($fixture['normalized']);
                koto_ocr_test_assert_array_contains_assoc(
                    $fixture['expected']['spec'],
                    $draft['spec'] ?? [],
                    'draft spec mismatch'
                );
            },
        ],
    ];
}

function koto_ocr_test_run(array $args)
{
    $selected_suite = $args['suite'] ?? 'all';
    $ran = 0;
    $failed = 0;
    foreach (koto_ocr_test_cases() as $case) {
        if ($selected_suite !== 'all' && $case['suite'] !== $selected_suite) {
            continue;
        }
        $ran++;
        try {
            call_user_func($case['run']);
            WP_CLI::line('ok ' . $case['suite'] . ' - ' . $case['name']);
        } catch (Exception $exception) {
            $failed++;
            WP_CLI::warning('not ok ' . $case['suite'] . ' - ' . $case['name'] . ': ' . $exception->getMessage());
        }
    }

    if ($ran === 0) {
        WP_CLI::error('No OCR tests matched suite: ' . $selected_suite);
    }
    if ($failed > 0) {
        WP_CLI::error($failed . ' OCR test(s) failed.');
    }
    WP_CLI::success($ran . ' OCR test(s) passed.');
}

koto_ocr_test_run(koto_ocr_test_parse_args($argv ?? []));
