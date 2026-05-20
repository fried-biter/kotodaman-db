<?php
/**
 * ターム一括付与 Ajax処理
 */

// 直接アクセス禁止
if (!defined('ABSPATH')) exit;

/**
 * Ajax: 新規ターム作成
 */
add_action('wp_ajax_term_setter_create_term', 'term_setter_handle_create_term');
function term_setter_handle_create_term() {
    // 権限チェック
    if (!current_user_can('publish_posts')) {
        wp_send_json_error(['message' => '権限がありません。']);
    }

    // nonceチェック
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'term_setter_nonce')) {
        wp_send_json_error(['message' => 'セキュリティチェックに失敗しました。']);
    }

    $taxonomy = sanitize_text_field($_POST['taxonomy'] ?? '');
    $name = sanitize_text_field($_POST['name'] ?? '');
    $slug = sanitize_title($_POST['slug'] ?? '');

    if (empty($taxonomy) || empty($name)) {
        wp_send_json_error(['message' => 'タクソノミー名とターム名は必須です。']);
    }

    // タクソノミーの存在確認
    if (!taxonomy_exists($taxonomy)) {
        wp_send_json_error(['message' => '指定されたタクソノミーが存在しません。']);
    }

    // 親ターム指定
    $parent = intval($_POST['parent'] ?? 0);
    
    // ターム作成
    $args = [];
    if (!empty($slug)) {
        $args['slug'] = $slug;
    }
    if ($parent > 0) {
        $args['parent'] = $parent;
    }

    $result = wp_insert_term($name, $taxonomy, $args);

    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()]);
    }

    // ACFのsave_fieldアクションをトリガー（必要に応じて）
    do_action('acf/save_term', $result['term_id'], $taxonomy);

    wp_send_json_success([
        'term_id' => $result['term_id'],
        'name' => $name,
        'message' => "ターム「{$name}」を作成しました。",
    ]);
}

/**
 * Ajax: ターム一括付与
 */
add_action('wp_ajax_term_setter_apply_terms', 'term_setter_handle_apply_terms');
function term_setter_handle_apply_terms() {
    // 権限チェック
    if (!current_user_can('publish_posts')) {
        wp_send_json_error(['message' => '権限がありません。']);
    }

    // nonceチェック
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'term_setter_nonce')) {
        wp_send_json_error(['message' => 'セキュリティチェックに失敗しました。']);
    }

    $taxonomy = sanitize_text_field($_POST['taxonomy'] ?? '');
    $term_ids = array_map('intval', (array) ($_POST['term_ids'] ?? []));
    $character_ids = array_map('intval', (array) ($_POST['character_ids'] ?? []));

    if (empty($taxonomy)) {
        wp_send_json_error(['message' => 'タクソノミーが指定されていません。']);
    }

    if (empty($term_ids)) {
        wp_send_json_error(['message' => 'タームが選択されていません。']);
    }

    if (empty($character_ids)) {
        wp_send_json_error(['message' => 'キャラクターが選択されていません。']);
    }

    // タクソノミーの存在確認
    if (!taxonomy_exists($taxonomy)) {
        wp_send_json_error(['message' => '指定されたタクソノミーが存在しません。']);
    }

    $success_count = 0;
    $failed_count = 0;
    $errors = [];

    foreach ($character_ids as $post_id) {
        // 投稿の存在確認
        $post = get_post($post_id);
        if (!$post) {
            $failed_count++;
            $errors[] = "ID {$post_id}: 投稿が見つかりません";
            continue;
        }

        // タームを付与（追加モード）
        $result = wp_set_object_terms($post_id, $term_ids, $taxonomy, true);

        if (is_wp_error($result)) {
            $failed_count++;
            $errors[] = "ID {$post_id}: " . $result->get_error_message();
        } else {
            $success_count++;
            
            // ACFのsave_fieldアクションをトリガー
            // 各タームについてacf/save_fieldを発火
            foreach ($term_ids as $term_id) {
                $field_key = 'field_' . $taxonomy . '_' . $term_id;
                do_action('acf/save_field', $field_key, $term_id, $post_id);
            }
            
            // 投稿全体の保存アクションも発火
            do_action('acf/save_post', $post_id);
        }
    }

    // 全体の処理完了アクション
    do_action('term_setter_after_bulk_apply', $character_ids, $term_ids, $taxonomy);

    wp_send_json_success([
        'message' => "処理が完了しました。（成功: {$success_count}件 / 失敗: {$failed_count}件）",
        'success_count' => $success_count,
        'failed_count' => $failed_count,
        'errors' => $errors,
    ]);
}

/**
 * Ajax: ターム階層データ取得
 */
add_action('wp_ajax_term_setter_get_term_hierarchy', 'term_setter_handle_get_term_hierarchy');
function term_setter_handle_get_term_hierarchy() {
    // 権限チェック
    if (!current_user_can('publish_posts')) {
        wp_send_json_error(['message' => '権限がありません。']);
    }

    // nonceチェック
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'term_setter_nonce')) {
        wp_send_json_error(['message' => 'セキュリティチェックに失敗しました。']);
    }

    $taxonomy = sanitize_text_field($_POST['taxonomy'] ?? '');

    if (empty($taxonomy) || !taxonomy_exists($taxonomy)) {
        wp_send_json_error(['message' => 'タクソノミーが存在しません。']);
    }

    // 階層付きでタームを取得
    $terms = get_terms([
        'taxonomy' => $taxonomy,
        'hide_empty' => false,
        'orderby' => 'name',
        'order' => 'ASC',
    ]);

    if (is_wp_error($terms)) {
        wp_send_json_error(['message' => $terms->get_error_message()]);
    }

    // 階層構造を構築
    $hierarchy = build_term_hierarchy($terms);

    wp_send_json_success([
        'terms' => $hierarchy,
        'flat_terms' => array_map(function($term) {
            return [
                'id' => $term->term_id,
                'name' => $term->name,
                'slug' => $term->slug,
                'parent' => $term->parent,
                'level' => isset($term->level) ? $term->level : 0,
            ];
        }, $terms),
    ]);
}

/**
 * ターム階層を構築
 */
function build_term_hierarchy($terms, $parent_id = 0, $level = 0) {
    $hierarchy = [];

    foreach ($terms as $term) {
        if ($term->parent == $parent_id) {
            $term->level = $level;
            $term->children = build_term_hierarchy($terms, $term->term_id, $level + 1);
            $hierarchy[] = [
                'id' => $term->term_id,
                'name' => $term->name,
                'slug' => $term->slug,
                'parent' => $term->parent,
                'level' => $level,
                'children' => $term->children,
            ];
        }
    }

    return $hierarchy;
}

/**
 * ACFフィールド保存時の追加処理（オプション）
 */
add_action('acf/save_field', 'term_setter_on_acf_save_field', 10, 3);
function term_setter_on_acf_save_field($field_key, $value, $post_id) {
    // 必要に応じてカスタム処理を追加
    // 例: 特定のフィールドキーに対する処理
}

/**
 * ターム一括付与後の処理（オプション）
 */
add_action('term_setter_after_bulk_apply', 'term_setter_after_bulk_apply', 10, 3);
function term_setter_after_bulk_apply($character_ids, $term_ids, $taxonomy) {
    // 必要に応じて追加処理（ログ記録など）
    
    // デバッグログ（開発時のみ）
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log(sprintf(
            '[Term Setter] Bulk apply completed: %d characters, %d terms, taxonomy: %s',
            count($character_ids),
            count($term_ids),
            $taxonomy
        ));
    }
}
