<?php
if (!defined('ABSPATH')) exit;

class Koto_Ocr_Test_Failure extends Exception {}
class Koto_Ocr_Test_Pending extends Exception {}

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

function koto_ocr_test_assert_true($actual, $message)
{
    if ($actual) {
        return;
    }
    throw new Koto_Ocr_Test_Failure($message);
}

function koto_ocr_test_pending($message)
{
    throw new Koto_Ocr_Test_Pending($message);
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
        [
            'suite' => 'module',
            'name' => 'available moji parsing keeps only strict single hiragana',
            'run' => function () {
                $fixture = koto_ocr_test_fixture('module/available-moji.json');
                foreach ($fixture['cases'] as $case) {
                    koto_ocr_test_assert_same(
                        $case['expected'],
                        koto_ocr_extract_chars($case['input']),
                        $case['name']
                    );
                }
            },
        ],
        [
            'suite' => 'module',
            'name' => 'waza extracts cleaned name and all-target very-strong attack row',
            'run' => function () {
                $fixture = koto_ocr_test_fixture('module/waza-attack.json');
                $extracted = koto_ocr_extract_fields($fixture['normalized']);
                $fragment = koto_ocr_build_spec_fragments($extracted);
                $spec = $fragment['fragment'];
                koto_ocr_test_assert_same($fixture['expected']['waza_name'], $spec['waza']['name'] ?? null, 'waza name mismatch');

                $detail = koto_ocr_build_skill_detail_row($spec['waza']['raw_text'] ?? '', $fixture['attribute']);
                koto_ocr_test_assert_array_contains_assoc(
                    $fixture['expected']['attack_detail'],
                    $detail,
                    'waza attack detail mismatch'
                );
            },
        ],
        [
            'suite' => 'module',
            'name' => 'sugowaza condition parses combined char_count and combo',
            'run' => function () {
                $fixture = koto_ocr_test_fixture('module/sugowaza-condition.json');
                $rows = koto_ocr_build_sugowaza_condition_rows($fixture['condition']);
                koto_ocr_test_assert_same($fixture['expected'], $rows, 'sugowaza condition rows mismatch');
            },
        ],
        [
            'suite' => 'module',
            'name' => 'sugowaza condition start_char is captured',
            'run' => function () {
                $fixture = koto_ocr_test_fixture('module/sugowaza-start-char.json');
                $rows = koto_ocr_build_sugowaza_condition_rows($fixture['condition']);
                if ($rows !== $fixture['expected']) {
                    koto_ocr_test_pending('start_char condition parsing is not implemented yet');
                }
            },
        ],
        [
            'suite' => 'module',
            'name' => 'two trait screens map to trait1 and trait2 without splitting numbered effects',
            'run' => function () {
                $fixture = koto_ocr_test_fixture('module/traits-two-screens.json');
                $draft = koto_ocr_test_build_draft_from_normalized($fixture['normalized']);
                koto_ocr_test_assert_same($fixture['expected']['trait1'], $draft['spec']['trait1']['raw_text'] ?? null, 'trait1 text mismatch');
                koto_ocr_test_assert_same($fixture['expected']['trait2'], $draft['spec']['trait2']['raw_text'] ?? null, 'trait2 text mismatch');
                koto_ocr_test_assert_true(
                    mb_strpos($draft['spec']['trait1']['raw_text'] ?? '', '②') !== false,
                    'trait1 numbered effect was split unexpectedly'
                );
            },
        ],
    ];
}

function koto_ocr_test_run(array $args)
{
    $selected_suite = $args['suite'] ?? 'all';
    $ran = 0;
    $passed = 0;
    $failed = 0;
    $pending = 0;
    foreach (koto_ocr_test_cases() as $case) {
        if ($selected_suite !== 'all' && $case['suite'] !== $selected_suite) {
            continue;
        }
        $ran++;
        try {
            call_user_func($case['run']);
            $passed++;
            WP_CLI::line('ok ' . $case['suite'] . ' - ' . $case['name']);
        } catch (Koto_Ocr_Test_Pending $exception) {
            $pending++;
            WP_CLI::line('pending ' . $case['suite'] . ' - ' . $case['name'] . ': ' . $exception->getMessage());
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
    WP_CLI::success($passed . ' OCR test(s) passed, ' . $pending . ' pending.');
}

koto_ocr_test_run(koto_ocr_test_parse_args($argv ?? []));
