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

function koto_ocr_test_assert_has_keys(array $keys, array $actual, $message)
{
    foreach ($keys as $key) {
        if (!array_key_exists($key, $actual)) {
            throw new Koto_Ocr_Test_Failure($message . ' missing key=' . $key);
        }
    }
}

function koto_ocr_test_first_skill_detail(array $acf, $group_key)
{
    return $acf[$group_key][0]['sugo_detail_loop'][0] ?? [];
}

function koto_ocr_test_condition_loop($condition_text)
{
    $rows = koto_ocr_build_sugowaza_condition_rows($condition_text);
    return $rows[0]['sugo_cond_loop'] ?? [];
}

function koto_ocr_test_assert_character_reading(array $case)
{
    $id = $case['id'] ?? 'unknown';
    $draft = koto_ocr_test_build_draft_from_normalized($case['normalized']);
    $spec = $draft['spec'] ?? [];
    $expected = $case['expected'];

    koto_ocr_test_assert_same($expected['name'], $spec['name'] ?? null, $id . ' name mismatch');
    koto_ocr_test_assert_same($expected['attribute'], $spec['attribute'] ?? null, $id . ' attribute mismatch');
    koto_ocr_test_assert_same($expected['species'], $spec['species'] ?? null, $id . ' species mismatch');
    koto_ocr_test_assert_same($expected['chars'], $spec['chars'] ?? null, $id . ' chars mismatch');
    koto_ocr_test_assert_same($expected['waza_name'], $spec['waza']['name'] ?? null, $id . ' waza name mismatch');
    koto_ocr_test_assert_array_contains_assoc(
        $expected['waza_attack'],
        koto_ocr_build_skill_detail_row($spec['waza']['raw_text'] ?? '', $expected['attribute']),
        $id . ' waza attack mismatch'
    );
    koto_ocr_test_assert_same($expected['sugowaza_name'], $spec['sugowaza']['name'] ?? null, $id . ' sugowaza name mismatch');
    koto_ocr_test_assert_same(
        $expected['sugowaza_condition'],
        koto_ocr_test_condition_loop($spec['sugowaza']['condition'] ?? ''),
        $id . ' sugowaza condition mismatch'
    );
    koto_ocr_test_assert_array_contains_assoc(
        $expected['sugowaza_attack'],
        koto_ocr_build_skill_detail_row($spec['sugowaza']['raw_text'] ?? '', $expected['attribute']),
        $id . ' sugowaza attack mismatch'
    );
    koto_ocr_test_assert_same($expected['trait1_raw'], $spec['trait1']['raw_text'] ?? null, $id . ' trait1 mismatch');
    koto_ocr_test_assert_same($expected['trait2_raw'], $spec['trait2']['raw_text'] ?? null, $id . ' trait2 mismatch');
    koto_ocr_test_assert_same($expected['blessing_raw'], $spec['blessing']['raw_text'] ?? null, $id . ' blessing mismatch');
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
            'suite' => 'pipeline',
            'name' => 'character normalized OCR builds draft spec and ACF data',
            'run' => function () {
                $fixture = koto_ocr_test_fixture('characters/synthetic-fire-god.json');
                $normalized = $fixture['normalized'];
                $extracted = koto_ocr_extract_fields($normalized);
                $fragment = koto_ocr_build_spec_fragments($extracted);
                $draft = koto_ocr_build_draft_spec($normalized, $extracted, $fragment);
                $spec = $draft['spec'] ?? [];
                $expected_spec = $fixture['expected']['spec'];

                koto_ocr_test_assert_same($expected_spec['name'], $spec['name'] ?? null, 'draft name mismatch');
                koto_ocr_test_assert_same($expected_spec['attribute'], $spec['attribute'] ?? null, 'draft attribute mismatch');
                koto_ocr_test_assert_same($expected_spec['species'], $spec['species'] ?? null, 'draft species mismatch');
                koto_ocr_test_assert_same($expected_spec['chars'], $spec['chars'] ?? null, 'draft chars mismatch');
                koto_ocr_test_assert_same($expected_spec['waza_name'], $spec['waza']['name'] ?? null, 'draft waza name mismatch');
                koto_ocr_test_assert_same($expected_spec['waza_raw'], $spec['waza']['raw_text'] ?? null, 'draft waza raw mismatch');
                koto_ocr_test_assert_same($expected_spec['sugowaza_name'], $spec['sugowaza']['name'] ?? null, 'draft sugowaza name mismatch');
                koto_ocr_test_assert_same($expected_spec['sugowaza_condition'], $spec['sugowaza']['condition'] ?? null, 'draft sugowaza condition mismatch');
                koto_ocr_test_assert_same($expected_spec['sugowaza_raw'], $spec['sugowaza']['raw_text'] ?? null, 'draft sugowaza raw mismatch');
                koto_ocr_test_assert_same($expected_spec['trait1_raw'], $spec['trait1']['raw_text'] ?? null, 'draft trait1 raw mismatch');
                koto_ocr_test_assert_same($expected_spec['trait2_raw'], $spec['trait2']['raw_text'] ?? null, 'draft trait2 raw mismatch');
                koto_ocr_test_assert_same($expected_spec['blessing_raw'], $spec['blessing']['raw_text'] ?? null, 'draft blessing raw mismatch');

                $acf = koto_ocr_spec_to_acf_data($spec);
                koto_ocr_test_assert_has_keys(
                    ['available_moji_loop', 'attribute', 'species', 'waza_name', 'waza_group_loop', 'sugowaza_name', 'sugowaza_condition', 'sugowaza_group_loop'],
                    $acf,
                    'ACF data mismatch'
                );
                koto_ocr_test_assert_same($fixture['expected']['acf']['waza_name'], $acf['waza_name'], 'ACF waza name mismatch');
                koto_ocr_test_assert_same($fixture['expected']['acf']['sugowaza_name'], $acf['sugowaza_name'], 'ACF sugowaza name mismatch');
                koto_ocr_test_assert_same(count($expected_spec['chars']), count($acf['available_moji_loop']), 'ACF available moji row count mismatch');
                foreach ($acf['available_moji_loop'] as $row) {
                    koto_ocr_test_assert_has_keys(['available_moji', 'moji_attr', 'unlock_place'], $row, 'ACF available moji row mismatch');
                    koto_ocr_test_assert_true(is_array($row['available_moji']) && count($row['available_moji']) === 1, 'ACF available moji term shape mismatch');
                    koto_ocr_test_assert_same('default', $row['unlock_place'], 'ACF available moji unlock place mismatch');
                }

                $condition_types = array_map(function ($row) {
                    return $row['sugo_cond_type'] ?? null;
                }, $acf['sugowaza_condition'][0]['sugo_cond_loop'] ?? []);
                koto_ocr_test_assert_same($fixture['expected']['acf']['sugowaza_condition_types'], $condition_types, 'ACF sugowaza condition type mismatch');
                koto_ocr_test_assert_array_contains_assoc(
                    $fixture['expected']['acf']['waza_attack'],
                    koto_ocr_test_first_skill_detail($acf, 'waza_group_loop'),
                    'ACF waza attack detail mismatch'
                );
                koto_ocr_test_assert_array_contains_assoc(
                    $fixture['expected']['acf']['sugowaza_attack'],
                    koto_ocr_test_first_skill_detail($acf, 'sugowaza_group_loop'),
                    'ACF sugowaza attack detail mismatch'
                );
            },
        ],
        [
            'suite' => 'pipeline',
            'name' => 'live OCR trait rows are saved through existing auto input rules',
            'run' => function () {
                $inserted_terms = [];
                $post_id = 0;
                try {
                    foreach (['フリーズブレイカー', 'スマッシュブレイカー'] as $term_name) {
                        if (get_term_by('name', $term_name, 'gimmick')) {
                            continue;
                        }
                        $term = wp_insert_term($term_name, 'gimmick');
                        if (!is_wp_error($term)) {
                            $inserted_terms[] = (int) $term['term_id'];
                        }
                    }

                    $fixture = koto_ocr_test_fixture('live-openrouter/recordings/mashiro7.json');
                    $normalized = koto_ocr_normalize_payload($fixture['payload'], count($fixture['payload']['images']));
                    $extracted = koto_ocr_extract_fields($normalized);
                    $rule_data = koto_ocr_apply_existing_auto_input_rules($extracted['fields'] ?? []);

                    koto_ocr_test_assert_true(!empty($rule_data['auto_input_trait2']), 'OCR trait2 auto input rules were not generated');
                    koto_ocr_test_assert_same(3, count($rule_data['auto_input_trait2']), 'OCR trait2 should include both gimmicks and available moji');

                    $post_id = wp_insert_post([
                        'post_title' => 'OCR trait save test',
                        'post_type' => 'character',
                        'post_status' => 'draft',
                    ], true);
                    if (is_wp_error($post_id)) {
                        throw new Koto_Ocr_Test_Failure('temporary post create failed: ' . $post_id->get_error_message());
                    }
                    koto_update_character_post_with_acf($post_id, $rule_data);

                    $trait_rows = get_field('second_trait_loop', $post_id);
                    koto_ocr_test_assert_true(is_array($trait_rows) && count($trait_rows) === 2, 'saved second trait row count mismatch');
                    koto_ocr_test_assert_true(!empty($trait_rows[0]['gimmick']), 'saved first gimmick is empty');
                    koto_ocr_test_assert_true(!empty($trait_rows[1]['gimmick']), 'saved second gimmick is empty');

                    $moji_rows = get_field('available_moji_loop', $post_id);
                    koto_ocr_test_assert_true(is_array($moji_rows) && count($moji_rows) === 1, 'saved available moji row count mismatch');
                    koto_ocr_test_assert_same('second_trait', $moji_rows[0]['unlock_place'] ?? null, 'saved available moji unlock place mismatch');
                } finally {
                    if ($post_id && !is_wp_error($post_id)) {
                        wp_delete_post($post_id, true);
                    }
                    foreach ($inserted_terms as $term_id) {
                        wp_delete_term($term_id, 'gimmick');
                    }
                }
            },
        ],
        [
            'suite' => 'matrix',
            'name' => 'seven character reading matrix covers all OCR modules',
            'run' => function () {
                $fixture = koto_ocr_test_fixture('characters/reading-matrix.json');
                foreach ($fixture['cases'] as $case) {
                    koto_ocr_test_assert_character_reading($case);
                    WP_CLI::line('matrix ' . $case['id'] . ' - name/chars/terms/waza/sugowaza/traits/blessing ok');
                }
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
            'name' => 'icon regions and multiple char sources build one spec',
            'run' => function () {
                $draft = koto_ocr_test_build_draft_from_normalized([
                    'images' => [
                        [
                            'source_image' => 'image_1',
                            'screen_type' => 'main',
                            'fullText' => "テストキャラ",
                            'blocks' => [
                                ['region' => 'main_name_text', 'text' => 'テストキャラ'],
                                ['region' => 'main_attribute_icon', 'text' => '水'],
                                ['region' => 'main_species_icon', 'text' => '獣'],
                                ['region' => 'main_char_ball', 'text' => 'こ・ご'],
                            ],
                        ],
                        [
                            'source_image' => 'image_2',
                            'screen_type' => 'trait',
                            'fullText' => '文字変換に「が」を追加する',
                            'blocks' => [
                                ['region' => 'trait_available_moji', 'text' => 'け・げ'],
                            ],
                        ],
                    ],
                ]);
                $spec = $draft['spec'] ?? [];
                koto_ocr_test_assert_same('water', $spec['attribute'] ?? null, 'icon attribute mismatch');
                koto_ocr_test_assert_same('beast', $spec['species'] ?? null, 'icon species mismatch');
                koto_ocr_test_assert_same(['こ', 'ご', 'が', 'け', 'げ'], $spec['chars'] ?? null, 'union chars mismatch');
            },
        ],
        [
            'suite' => 'module',
            'name' => 'skill modal order corrects mislabeled waza screens',
            'run' => function () {
                $draft = koto_ocr_test_build_draft_from_normalized([
                    'images' => [
                        [
                            'source_image' => 'image_1',
                            'screen_type' => 'main',
                            'fullText' => 'テストキャラ\n属性: 火\n種族: 神\n使用可能文字: あ',
                            'blocks' => [['region' => 'main_name_text', 'text' => 'テストキャラ']],
                        ],
                        [
                            'source_image' => 'image_2',
                            'screen_type' => 'sugowaza',
                            'fullText' => '通常わざ 詳細 敵単体に強力な火属性攻撃 発動条件 3文字以上',
                            'blocks' => [
                                ['region' => 'modal_header_title', 'text' => '通常わざ'],
                                ['region' => 'modal_body', 'text' => '敵単体に強力な火属性攻撃'],
                                ['region' => 'modal_trigger', 'text' => '3文字以上'],
                            ],
                        ],
                        [
                            'source_image' => 'image_3',
                            'screen_type' => 'sugowaza',
                            'fullText' => '超わざ 詳細 敵全体に超絶強力な火属性攻撃 発動条件 4文字以上',
                            'blocks' => [
                                ['region' => 'modal_header_title', 'text' => '超わざ'],
                                ['region' => 'modal_body', 'text' => '敵全体に超絶強力な火属性攻撃'],
                                ['region' => 'modal_trigger', 'text' => '4文字以上'],
                            ],
                        ],
                    ],
                ]);
                $spec = $draft['spec'] ?? [];
                koto_ocr_test_assert_same('通常わざ', $spec['waza']['name'] ?? null, 'ordered waza name mismatch');
                koto_ocr_test_assert_same('超わざ', $spec['sugowaza']['name'] ?? null, 'ordered sugowaza name mismatch');
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
