<?php
if (!defined('ABSPATH')) exit;

add_action('admin_enqueue_scripts', 'koto_ocr_admin_enqueue_assets');
add_action('wp_ajax_koto_ocr_create_draft', 'koto_ocr_ajax_create_draft');

function koto_ocr_admin_enqueue_assets($hook)
{
    if ($hook !== 'toplevel_page_koto-acf-editor') {
        return;
    }
    $base_dir = __DIR__;
    $base_uri = get_stylesheet_directory_uri() . '/lib/acf/ocr';
    wp_enqueue_style('koto-ocr-draft', $base_uri . '/acf-ocr-draft.css', [], filemtime($base_dir . '/acf-ocr-draft.css'));
    wp_enqueue_script('koto-ocr-draft', $base_uri . '/acf-ocr-draft.js', [], filemtime($base_dir . '/acf-ocr-draft.js'), true);
    wp_add_inline_script('koto-ocr-draft', 'window.KOTO_OCR_DRAFT_CONFIG = ' . wp_json_encode(koto_ocr_public_config(), JSON_UNESCAPED_UNICODE) . ';', 'before');
}

function koto_ocr_public_config()
{
    return [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('koto_ocr_create_draft'),
        'action' => 'koto_ocr_create_draft',
        'hasApiKey' => koto_ocr_openrouter_api_key() !== '',
        'model' => koto_ocr_openrouter_model(),
        'maxImages' => koto_ocr_max_images(),
        'maxImageBytes' => koto_ocr_max_image_bytes(),
        'uploadTargetBytes' => koto_ocr_upload_target_bytes(),
        'allowedMimeTypes' => koto_ocr_allowed_mime_types(),
        'timeoutSeconds' => koto_ocr_openrouter_timeout(),
        'debug' => koto_ocr_debug_enabled(),
    ];
}

function koto_ocr_render_draft_panel()
{
    if (!current_user_can('edit_posts')) {
        return;
    }
    $has_key = koto_ocr_openrouter_api_key() !== '';
    ?>
    <div class="koto-ocr-panel" data-koto-ocr-panel>
        <button type="button" class="koto-ocr-panel__toggle" data-koto-ocr-toggle aria-expanded="false">
            OCRから新規下書きを作成
        </button>
        <div class="koto-ocr-panel__body" data-koto-ocr-body hidden>
            <?php if (!$has_key) : ?>
                <div class="notice notice-warning inline"><p>OpenRouter APIキーが未設定です。<code>KOTO_OCR_OPENROUTER_API_KEY</code> 定数または <code>OPENROUTER_API_KEY</code> 環境変数を設定してください。</p></div>
            <?php endif; ?>
            <p class="description">スクリーンショット画像を選択すると、OCR結果から <code>character</code> 下書きを新規作成します。画像自体は保存しません。</p>
            <label class="koto-ocr-dropzone" data-koto-ocr-dropzone>
                <span>画像を選択 / ドラッグ&ドロップ</span>
                <input type="file" data-koto-ocr-input accept="image/png,image/jpeg,image/webp" multiple>
            </label>
            <div class="koto-ocr-preview" data-koto-ocr-preview></div>
            <div class="koto-ocr-actions">
                <button type="button" class="button button-primary" data-koto-ocr-submit <?php disabled(!$has_key); ?>>OCR実行して下書きを作成</button>
                <span class="spinner" data-koto-ocr-spinner></span>
                <span class="koto-ocr-status" data-koto-ocr-status></span>
            </div>
            <div class="koto-ocr-result" data-koto-ocr-result></div>
        </div>
    </div>
    <?php
}

function koto_ocr_render_existing_draft_review($post_id, $current_group = '')
{
    $post_id = (int) $post_id;
    if (!$post_id || get_post_meta($post_id, '_koto_ocr_draft', true) !== '1') {
        return;
    }

    $warnings = json_decode((string) get_post_meta($post_id, '_koto_ocr_warnings', true), true);
    $source = json_decode((string) get_post_meta($post_id, '_koto_ocr_source', true), true);
    $fields_meta = json_decode((string) get_post_meta($post_id, '_koto_ocr_fields', true), true);
    if (!is_array($warnings)) $warnings = [];
    if (!is_array($source)) $source = [];
    if (!is_array($fields_meta)) $fields_meta = [];
    $review_items = koto_ocr_review_items_for_group((string) $current_group, $fields_meta['fields'] ?? []);
    $saved_summary = koto_ocr_saved_acf_summary($post_id);
    ?>
    <div class="koto-ocr-review-panel" data-koto-ocr-review-panel>
        <div class="koto-ocr-review-panel__header">
            <h2>OCR下書き確認</h2>
            <p class="description">この投稿はOCRから作成された下書きです。このタブで修正する項目をOCR断片と照合してください。</p>
        </div>
        <?php if (!empty($saved_summary)) : ?>
            <div class="notice notice-info inline">
                <p><strong>OCRから保存済みの主なACF:</strong></p>
                <ul>
                    <?php foreach ($saved_summary as $item) : ?>
                        <li>
                            <?php echo esc_html($item['label']); ?>: <?php echo esc_html($item['count']); ?>件
                            <?php if (!empty($item['url'])) : ?>
                                <a href="<?php echo esc_url($item['url']); ?>">この欄を開く</a>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <?php if (!empty($warnings)) : ?>
            <div class="notice notice-warning inline">
                <ul>
                    <?php foreach ($warnings as $warning) : ?>
                        <li><strong><?php echo esc_html($warning['field'] ?? ''); ?></strong>: <?php echo esc_html($warning['message'] ?? ''); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <div class="koto-ocr-review-items">
            <?php if (!empty($review_items)) : ?>
                <?php foreach ($review_items as $item) { $item['post_id'] = $post_id; koto_ocr_render_review_item($item); } ?>
            <?php else : ?>
                <p class="koto-ocr-review-empty">このタブ向けOCR断片はありません。</p>
            <?php endif; ?>
        </div>
        <?php koto_ocr_render_source_summary($source); ?>
        <?php if (koto_ocr_debug_enabled() && !empty($source)) : ?>
            <details>
                <summary>OCR source JSON</summary>
                <pre><?php echo esc_html(wp_json_encode($source, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)); ?></pre>
            </details>
        <?php endif; ?>
    </div>
    <?php
}

function koto_ocr_review_items_for_group($current_group, array $fields)
{
    $fields_by_group = [
        'group_69204fa4dd82e' => [
            'character_name' => 'キャラ名',
            'chars' => '文字',
            'attribute' => '属性',
            'species' => '種族',
            'rarity' => 'レアリティ',
            'cv' => 'CV',
        ],
        'group_6937900895bf1' => [
            'waza_name' => 'わざ名',
            'waza' => 'わざraw',
            'sugowaza_name' => 'すごわざ名',
            'sugowaza_condition' => 'すごわざ条件',
            'sugowaza' => 'すごわざraw',
            'chars' => '抽出文字',
        ],
        'group_693790ee221c3' => [
            'trait1' => 'とくせい1 raw',
            'trait2' => 'とくせい2 raw',
        ],
        'group_693971a11a6b2' => [
            'blessing' => '祝福 raw',
        ],
        'group_693790bd6b499' => [
            'kotowaza' => 'ことわざ raw',
        ],
        'group_693969515ca4d' => [
            'leader' => 'リーダーとくせい raw',
        ],
        'group_693c070768756' => [
            'EX_skill' => 'EXスキル raw',
        ],
        'group_69d4b6b256263' => [
            'charge_skill' => 'ミラクルリーダー raw',
        ],
    ];

    $items = [];
    foreach ($fields_by_group[$current_group] ?? [] as $field_name => $label) {
        foreach ($fields[$field_name] ?? [] as $field_item) {
            if (!is_array($field_item)) {
                continue;
            }
            $text = koto_ocr_review_field_text($field_item);
            if ($text === '') {
                continue;
            }
            $items[] = koto_ocr_review_item($label, $text, $field_item['source_image'] ?? '');
        }
    }
    return $items;
}

function koto_ocr_review_item($label, $text, $source_image = '', $extra = [])
{
    return array_merge($extra, [
        'label' => (string) $label,
        'text' => (string) $text,
        'source_image' => (string) $source_image,
    ]);
}

function koto_ocr_review_field_text(array $field_item)
{
    if (!empty($field_item['items']) && is_array($field_item['items'])) {
        return trim(implode('・', array_filter(array_map('strval', $field_item['items']))));
    }
    return trim((string) ($field_item['text'] ?? ''));
}

function koto_ocr_render_review_item(array $item)
{
    $source_image = (string) ($item['source_image'] ?? '');
    ?>
    <div class="koto-ocr-review-item" data-koto-ocr-source-image="<?php echo esc_attr($source_image); ?>" data-koto-ocr-post-id="<?php echo esc_attr((int) ($item['post_id'] ?? 0)); ?>">
        <div class="koto-ocr-review-item__head">
            <strong class="koto-ocr-review-item__label"><?php echo esc_html($item['label'] ?? 'OCR'); ?></strong>
            <?php if ($source_image !== '') : ?>
                <span class="koto-ocr-review-item__source">元画像: <?php echo esc_html($source_image); ?></span>
            <?php endif; ?>
        </div>
        <?php if ($source_image !== '') : ?>
            <div class="koto-ocr-review-item__image" data-koto-ocr-source-image-container>このブラウザに元画像なし</div>
        <?php endif; ?>
        <pre class="koto-ocr-review-item__text"><?php echo esc_html($item['text'] ?? ''); ?></pre>
    </div>
    <?php
}

function koto_ocr_render_source_summary(array $source)
{
    $images = $source['images'] ?? [];
    if (empty($images) || !is_array($images)) return;
    ?>
    <details class="koto-ocr-review-raw-summary">
        <summary>全文OCR raw text <?php echo esc_html(count($images)); ?>件</summary>
        <?php foreach ($images as $item) : ?>
            <details>
                <summary><?php echo esc_html($item['source_image'] ?? 'image'); ?> OCR raw text</summary>
                <pre><?php echo esc_html($item['full_text'] ?? ''); ?></pre>
            </details>
        <?php endforeach; ?>
    </details>
    <?php
}

function koto_ocr_saved_acf_summary($post_id)
{
    $groups = [
        'group_69204fa4dd82e' => [
            '基本データ' => ['available_moji_loop'],
        ],
        'group_6937900895bf1' => [
            'わざ' => ['waza_name', 'waza_group_loop'],
            'すごわざ' => ['sugowaza_name', 'sugowaza_group_loop', 'sugowaza_condition'],
        ],
        'group_693790ee221c3' => [
            'とくせい' => ['first_trait_loop', 'second_trait_loop'],
        ],
        'group_693971a11a6b2' => [
            '祝福' => ['blessing_trait_loop'],
        ],
    ];
    $summary = [];
    foreach ($groups as $group_key => $labels) {
        foreach ($labels as $label => $field_names) {
            $count = 0;
            foreach ($field_names as $field_name) {
                $value = get_field($field_name, $post_id);
                if (is_array($value)) {
                    $count += count($value);
                } elseif ($value !== null && $value !== false && $value !== '') {
                    $count++;
                }
            }
            if ($count > 0) {
                $summary[] = [
                    'label' => $label,
                    'count' => $count,
                    'url' => admin_url('admin.php?page=koto-acf-editor&edit_post_id=' . (int) $post_id . '&acf_group=' . $group_key),
                ];
            }
        }
    }
    return $summary;
}

function koto_ocr_ajax_create_draft()
{
    check_ajax_referer('koto_ocr_create_draft', 'nonce');
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(['message' => 'OCR実行権限がありません。'], 403);
    }
    if (koto_ocr_openrouter_api_key() === '') {
        wp_send_json_error(['message' => 'OpenRouter APIキーが設定されていません。'], 400);
    }

    $images = koto_ocr_validate_uploaded_images($_FILES['images'] ?? null);
    if (is_wp_error($images)) {
        wp_send_json_error(['message' => $images->get_error_message()], 400);
    }

    $backend = new Koto_Ocr_Openrouter_Vlm(koto_ocr_openrouter_api_key(), koto_ocr_openrouter_model(), koto_ocr_openrouter_timeout());
    $result = koto_ocr_run_pipeline($images, $backend);
    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()], 400);
    }

    $post_id = (int) $result['post_id'];
    $links = [];
    if (current_user_can('edit_post', $post_id)) {
        $links['editPost'] = get_edit_post_link($post_id, 'raw');
        $links['dbEditor'] = admin_url('admin.php?page=koto-acf-editor&edit_post_id=' . $post_id . '&acf_group=group_69204fa4dd82e');
        $links['dbEditorSkills'] = admin_url('admin.php?page=koto-acf-editor&edit_post_id=' . $post_id . '&acf_group=group_6937900895bf1');
        $links['dbEditorTraits'] = admin_url('admin.php?page=koto-acf-editor&edit_post_id=' . $post_id . '&acf_group=group_693790ee221c3');
        $links['dbEditorBlessing'] = admin_url('admin.php?page=koto-acf-editor&edit_post_id=' . $post_id . '&acf_group=group_693971a11a6b2');
    }

    wp_send_json_success([
        'postId' => $post_id,
        'title' => get_the_title($post_id),
        'links' => $links,
        'warnings' => $result['draft']['warnings'] ?? [],
        'debug' => koto_ocr_debug_enabled() ? $result : null,
    ]);
}

function koto_ocr_validate_uploaded_images($files)
{
    if (!is_array($files) || empty($files['tmp_name'])) {
        return new WP_Error('koto_ocr_no_files', '画像ファイルが送信されていません。');
    }

    $tmp_names = is_array($files['tmp_name']) ? $files['tmp_name'] : [$files['tmp_name']];
    $errors = is_array($files['error']) ? $files['error'] : [$files['error']];
    $sizes = is_array($files['size']) ? $files['size'] : [$files['size']];
    $names = is_array($files['name']) ? $files['name'] : [$files['name']];

    if (count($tmp_names) > koto_ocr_max_images()) {
        return new WP_Error('koto_ocr_too_many_files', '画像は一度に' . koto_ocr_max_images() . '枚までです。');
    }

    $allowed = koto_ocr_allowed_mime_types();
    $validated = [];
    foreach ($tmp_names as $index => $tmp_name) {
        if (($errors[$index] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return new WP_Error('koto_ocr_upload_error', '画像アップロードに失敗しました。');
        }
        if (!is_uploaded_file($tmp_name) || !is_readable($tmp_name)) {
            return new WP_Error('koto_ocr_unreadable_file', 'アップロード画像を読み取れません。');
        }
        if ((int) ($sizes[$index] ?? 0) > koto_ocr_max_image_bytes()) {
            return new WP_Error('koto_ocr_file_too_large', '画像が1ファイル上限を超えています。ブラウザ側の自動縮小に失敗した可能性があります。');
        }

        $check = wp_check_filetype_and_ext($tmp_name, $names[$index] ?? 'image');
        $mime = $check['type'] ?? '';
        if (!$mime && function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = $finfo ? finfo_file($finfo, $tmp_name) : '';
            if ($finfo) finfo_close($finfo);
        }
        if (!in_array($mime, $allowed, true) || @getimagesize($tmp_name) === false) {
            return new WP_Error('koto_ocr_invalid_mime', '対応していない画像形式です。PNG/JPEG/WebPのみ利用できます。');
        }

        $validated[] = [
            'source_image' => 'image_' . (count($validated) + 1),
            'mime_type' => $mime,
            'path' => $tmp_name,
        ];
    }
    return $validated;
}
