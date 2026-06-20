<?php
if (!defined('ABSPATH')) exit;

function koto_ocr_webui_report_args(array $argv)
{
    $args = [
        'input' => get_stylesheet_directory() . '/local-dev/seed/ocr-fixtures/live-openrouter/input',
        'out' => sys_get_temp_dir() . '/kotodaman-ocr-e2e-report',
        'cases' => [],
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
        }
    }
    $args['cases'] = array_values(array_filter(array_map('trim', $args['cases'])));
    return $args;
}

function koto_ocr_webui_report_case_map(array $case_args)
{
    $map = [];
    foreach ($case_args as $case_arg) {
        if (strpos($case_arg, ':') === false) {
            continue;
        }
        [$case, $post_id] = explode(':', $case_arg, 2);
        $case = sanitize_key($case);
        $post_id = (int) $post_id;
        if ($case !== '' && $post_id > 0) {
            $map[$case] = $post_id;
        }
    }
    return $map;
}

function koto_ocr_webui_report_read_spec($input_dir, $case)
{
    $path = rtrim($input_dir, '/') . '/' . $case . '/spec.json';
    if (!is_readable($path)) {
        return [];
    }
    $spec = json_decode((string) file_get_contents($path), true);
    return is_array($spec) ? $spec : [];
}

function koto_ocr_webui_report_term_slug($post_id, $taxonomy)
{
    $terms = get_the_terms($post_id, $taxonomy);
    if (!$terms || is_wp_error($terms) || empty($terms[0])) {
        return '';
    }
    return (string) $terms[0]->slug;
}

function koto_ocr_webui_report_chars($post_id)
{
    $chars = [];
    $count = (int) get_post_meta($post_id, 'available_moji_loop', true);
    for ($i = 0; $i < $count; $i++) {
        $ids = (array) get_post_meta($post_id, 'available_moji_loop_' . $i . '_available_moji', true);
        foreach ($ids as $id) {
            $term = get_term((int) $id);
            if ($term && !is_wp_error($term) && $term->name !== '') {
                $chars[] = $term->name;
            }
        }
    }
    return array_values(array_unique($chars));
}

function koto_ocr_webui_report_expected_chars(array $spec)
{
    $chars = [];
    foreach ($spec['chars'] ?? [] as $char) {
        if (!empty($char['val'])) {
            $chars[] = (string) $char['val'];
        }
    }
    return array_values(array_unique($chars));
}

function koto_ocr_webui_report_text_samples($rows, $limit = 5)
{
    $samples = [];
    foreach (($rows ?: []) as $row) {
        if (!is_array($row)) {
            continue;
        }
        $text = $row['other_text'] ?? $row['other_traits'] ?? $row['trait_type'] ?? '';
        if ($text === '' && !empty($row['gimmick'])) {
            $text = is_array($row['gimmick']) ? 'gimmick:' . implode(',', $row['gimmick']) : 'gimmick:' . $row['gimmick'];
        }
        if ($text !== '') {
            $samples[] = (string) $text;
        }
        if (count($samples) >= $limit) {
            break;
        }
    }
    return $samples;
}

function koto_ocr_webui_report_usage($post_id)
{
    $source = json_decode((string) get_post_meta($post_id, '_koto_ocr_source', true), true);
    $usage = is_array($source) ? ($source['_openrouter_usage'] ?? []) : [];
    return is_array($usage) ? [
        'requests' => (int) ($usage['requests'] ?? 0),
        'prompt_tokens' => (int) ($usage['prompt_tokens'] ?? 0),
        'completion_tokens' => (int) ($usage['completion_tokens'] ?? 0),
        'total_tokens' => (int) ($usage['total_tokens'] ?? 0),
        'cost' => (float) ($usage['cost'] ?? 0),
        'currency' => (string) ($usage['currency'] ?? 'USD'),
    ] : [];
}

function koto_ocr_webui_report_term_value($post_id, $field_name)
{
    $value = get_field($field_name, $post_id);
    if (is_object($value) && isset($value->slug)) {
        return (string) $value->slug;
    }
    if (is_array($value) && isset($value['slug'])) {
        return (string) $value['slug'];
    }
    return $value ? (string) $value : '';
}

function koto_ocr_webui_report_skill_detail_status($field_name, $post_id)
{
    $rows = get_field($field_name, $post_id) ?: [];
    $detail_count = 0;
    $blank_value_count = 0;
    foreach ($rows as $row) {
        foreach (($row['sugo_detail_loop'] ?? []) as $detail) {
            if (!is_array($detail)) {
                continue;
            }
            $detail_count++;
            if (($detail['waza_value'] ?? '') === '' && ($detail['waza_value_last'] ?? '') === '') {
                $blank_value_count++;
            }
        }
    }
    return ['detail_count' => $detail_count, 'blank_value_count' => $blank_value_count];
}

function koto_ocr_webui_report_row($case, $post_id, array $spec)
{
    $expected_chars = koto_ocr_webui_report_expected_chars($spec);
    $chars = koto_ocr_webui_report_chars($post_id);
    $waza_name = get_field('waza_name', $post_id) ?: '';
    $sugowaza_name = get_field('sugowaza_name', $post_id) ?: '';
    $blessing_rows = get_field('blessing_trait_loop', $post_id) ?: [];
    $waza_detail = koto_ocr_webui_report_skill_detail_status('waza_group_loop', $post_id);
    $sugowaza_detail = koto_ocr_webui_report_skill_detail_status('sugowaza_group_loop', $post_id);
    $expected_rarity = !empty($spec['rarity_detail']) && $spec['rarity_detail'] !== 'none' ? (string) $spec['rarity_detail'] : (string) ($spec['rarity'] ?? '');

    return [
        'case' => $case,
        'postId' => $post_id,
        'title' => html_entity_decode(get_the_title($post_id), ENT_QUOTES, 'UTF-8'),
        'expectedTitle' => html_entity_decode((string) ($spec['name'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'titleOk' => html_entity_decode(get_the_title($post_id), ENT_QUOTES, 'UTF-8') === html_entity_decode((string) ($spec['name'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'attribute' => koto_ocr_webui_report_term_slug($post_id, 'attribute'),
        'expectedAttribute' => (string) ($spec['attribute'] ?? ''),
        'species' => koto_ocr_webui_report_term_slug($post_id, 'species'),
        'expectedSpecies' => (string) ($spec['species'] ?? ''),
        'rarity' => koto_ocr_webui_report_term_value($post_id, 'rarity'),
        'expectedRarity' => $expected_rarity,
        'rarityOk' => $expected_rarity === '' || koto_ocr_webui_report_term_value($post_id, 'rarity') === $expected_rarity,
        'nameRuby' => (string) (get_field('name_ruby', $post_id) ?: ''),
        'expectedNameRuby' => (string) ($spec['name_ruby'] ?? ''),
        'voiceActor' => (string) (get_field('voice_actor', $post_id) ?: ''),
        'expectedVoiceActor' => (string) ($spec['cv'] ?? ''),
        'voiceActorOk' => (string) ($spec['cv'] ?? '') === '' || (string) (get_field('voice_actor', $post_id) ?: '') === (string) ($spec['cv'] ?? ''),
        'chars' => $chars,
        'expectedChars' => $expected_chars,
        'missingChars' => array_values(array_diff($expected_chars, $chars)),
        'wazaName' => $waza_name,
        'expectedWazaName' => (string) ($spec['waza']['name'] ?? ''),
        'wazaNameOk' => $waza_name === (string) ($spec['waza']['name'] ?? ''),
        'wazaRows' => count(get_field('waza_group_loop', $post_id) ?: []),
        'wazaDetailCount' => $waza_detail['detail_count'],
        'wazaBlankValueCount' => $waza_detail['blank_value_count'],
        'sugowazaName' => $sugowaza_name,
        'expectedSugowazaName' => (string) ($spec['sugowaza']['name'] ?? ''),
        'sugowazaNameOk' => $sugowaza_name === (string) ($spec['sugowaza']['name'] ?? ''),
        'sugowazaRows' => count(get_field('sugowaza_group_loop', $post_id) ?: []),
        'sugowazaDetailCount' => $sugowaza_detail['detail_count'],
        'sugowazaBlankValueCount' => $sugowaza_detail['blank_value_count'],
        'sugowazaConditionRows' => count(get_field('sugowaza_condition', $post_id) ?: []),
        'trait1Rows' => count(get_field('first_trait_loop', $post_id) ?: []),
        'trait2Rows' => count(get_field('second_trait_loop', $post_id) ?: []),
        'blessingRows' => count($blessing_rows),
        'blessingSamples' => koto_ocr_webui_report_text_samples($blessing_rows, 4),
        'exSkillLabel' => (string) (get_field('ex_skill_label', $post_id) ?: ''),
        'exSkillDescription' => (string) (get_field('ex_skill_discription', $post_id) ?: ''),
        'exSkillSaved' => (get_field('ex_skill_label', $post_id) ?: '') !== '' || (get_field('ex_skill_discription', $post_id) ?: '') !== '',
        'usage' => koto_ocr_webui_report_usage($post_id),
    ];
}

function koto_ocr_webui_report_summary(array $rows)
{
    $summary = [
        'case_count' => count($rows),
        'created_count' => count($rows),
        'title_ok_count' => 0,
        'attribute_ok_count' => 0,
        'species_ok_count' => 0,
        'rarity_ok_count' => 0,
        'voice_actor_ok_count' => 0,
        'chars_complete_count' => 0,
        'waza_name_ok_count' => 0,
        'sugowaza_name_ok_count' => 0,
        'blessing_nonzero_count' => 0,
        'ex_skill_saved_count' => 0,
        'openrouter_cost' => 0.0,
        'openrouter_currency' => 'USD',
        'openrouter_requests' => 0,
    ];
    foreach ($rows as $row) {
        if ($row['titleOk']) $summary['title_ok_count']++;
        if ($row['attribute'] === $row['expectedAttribute']) $summary['attribute_ok_count']++;
        if ($row['species'] === $row['expectedSpecies']) $summary['species_ok_count']++;
        if ($row['rarityOk']) $summary['rarity_ok_count']++;
        if ($row['voiceActorOk']) $summary['voice_actor_ok_count']++;
        if (empty($row['missingChars'])) $summary['chars_complete_count']++;
        if ($row['wazaNameOk']) $summary['waza_name_ok_count']++;
        if ($row['sugowazaNameOk']) $summary['sugowaza_name_ok_count']++;
        if ($row['blessingRows'] > 0) $summary['blessing_nonzero_count']++;
        if ($row['exSkillSaved']) $summary['ex_skill_saved_count']++;
        $usage = $row['usage'] ?? [];
        $summary['openrouter_cost'] += (float) ($usage['cost'] ?? 0);
        $summary['openrouter_requests'] += (int) ($usage['requests'] ?? 0);
        if (!empty($usage['currency'])) $summary['openrouter_currency'] = $usage['currency'];
    }
    return $summary;
}

function koto_ocr_webui_report_markdown(array $report)
{
    $summary = $report['summary'];
    $lines = [];
    $lines[] = '# OCR Web UI E2E Report';
    $lines[] = '';
    $lines[] = '- generated_at: `' . $report['generated_at'] . '`';
    $lines[] = '- cases: `' . $summary['case_count'] . '`';
    $lines[] = '- OpenRouter cost: `' . sprintf('%.6f', $summary['openrouter_cost']) . ' ' . $summary['openrouter_currency'] . '`';
    $lines[] = '- OpenRouter requests: `' . $summary['openrouter_requests'] . '`';
    $lines[] = '';
    $lines[] = '| case | postId | title | attr | species | rarity | cv | chars | waza | sugowaza | values | traits | blessing | EX | cost |';
    $lines[] = '|---|---:|---|---|---|---|---|---|---|---|---|---:|---:|---|---:|';
    foreach ($report['cases'] as $row) {
        $chars = empty($row['missingChars']) ? 'OK' : 'missing ' . implode(',', $row['missingChars']);
        $usage = $row['usage'] ?? [];
        $lines[] = sprintf(
            '| `%s` | %d | %s | %s | %s | %s | %s | %s | %s | %s | %s | %d/%d | %d | %s | %.6f |',
            $row['case'],
            $row['postId'],
            $row['titleOk'] ? 'OK' : 'NG',
            $row['attribute'] === $row['expectedAttribute'] ? 'OK' : 'NG',
            $row['species'] === $row['expectedSpecies'] ? 'OK' : 'NG',
            $row['rarityOk'] ? 'OK' : 'NG',
            $row['voiceActorOk'] ? 'OK' : 'NG',
            $chars,
            $row['wazaNameOk'] ? 'OK' : 'NG',
            $row['sugowazaNameOk'] ? 'OK' : 'NG',
            ($row['wazaBlankValueCount'] + $row['sugowazaBlankValueCount']) . ' blank',
            $row['trait1Rows'],
            $row['trait2Rows'],
            $row['blessingRows'],
            $row['exSkillSaved'] ? 'OK' : 'NG',
            (float) ($usage['cost'] ?? 0)
        );
    }
    $lines[] = '';
    $lines[] = '## Blessing Samples';
    foreach ($report['cases'] as $row) {
        $samples = empty($row['blessingSamples']) ? '(none)' : implode(' / ', $row['blessingSamples']);
        $lines[] = '- `' . $row['case'] . '`: ' . $samples;
    }
    return implode("\n", $lines) . "\n";
}

function koto_ocr_webui_report_write($out_dir, array $report)
{
    if (!wp_mkdir_p($out_dir)) {
        return new WP_Error('koto_ocr_webui_report_mkdir', '出力ディレクトリを作成できません: ' . $out_dir);
    }
    $json_path = rtrim($out_dir, '/') . '/report.json';
    $md_path = rtrim($out_dir, '/') . '/report.md';
    file_put_contents($json_path, wp_json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n");
    file_put_contents($md_path, koto_ocr_webui_report_markdown($report));
    return ['json' => $json_path, 'markdown' => $md_path];
}

$cli_args = isset($args) && is_array($args) ? $args : (isset($argv) && is_array($argv) ? array_slice($argv, 1) : []);
$args = koto_ocr_webui_report_args($cli_args);
$case_map = koto_ocr_webui_report_case_map($args['cases']);
if (empty($case_map)) {
    WP_CLI::error('cases=case:postId,... を指定してください。');
}

$rows = [];
foreach ($case_map as $case => $post_id) {
    $rows[] = koto_ocr_webui_report_row($case, $post_id, koto_ocr_webui_report_read_spec($args['input'], $case));
}
$report = [
    'generated_at' => current_time('mysql'),
    'summary' => koto_ocr_webui_report_summary($rows),
    'cases' => $rows,
];
$written = koto_ocr_webui_report_write($args['out'], $report);
if (is_wp_error($written)) {
    WP_CLI::error($written->get_error_message());
}
WP_CLI::line(wp_json_encode(['summary' => $report['summary'], 'files' => $written], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
