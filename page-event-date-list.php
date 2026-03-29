<?php
/*
 * Template Name: イベント・キャラ一覧ページ
 */

get_header();

$events = get_field('event_loop');
if ($events) {
    echo '<div class="event-history-container">';

    // 完了グループ非表示用ボタン
    echo '<div class="controls-container">';
    echo '<button id="toggle-done-groups-btn" class="toggle-done-btn">完了済みのグループを非表示</button>';
    echo '</div>';

    $last_date_text = '';
    $last_event_name = '';
    $is_section_open = false;

    foreach ($events as $event) {
        $main_name     = $event['event_name'];
        $detail_name   = $event['event_detail_name'];
        $date_raw      = $event['event_date'];
        $characters    = $event['character_name_loop'];

        if (! empty($date_input)) {
            $last_date_text = $date_input;
        } else {
            $date_input = $last_date_text;
        }

        $date_display = '';
        if ($date_raw) {
            $date_obj = DateTime::createFromFormat('d/m/Y', $date_raw);
            if ($date_obj) {
                $date_display = $date_obj->format('Y.m.d');
            } else {
                $date_display = $date_raw;
            }
        }

        // 大見出し（セクション）の切り替えとアコーディオン用ラッパーの設置
        if (! empty($main_name)) {
            if ($is_section_open) {
                echo '</div>'; // .event-group-content close
                echo '</div>'; // .event-group close
            }

            echo '<div class="event-group">';
            echo '<h2 class="event-main-title js-accordion-trigger">';
            echo '<span class="title-text">' . esc_html($main_name) . '</span>';
            echo '<span class="accordion-icon">▼</span>';
            echo '</h2>';
            echo '<div class="event-group-content" style="display: none;">';

            $is_section_open = true;
        } elseif (! $is_section_open) {
            echo '<div class="event-group">';
            echo '<div class="event-group-content">';
            $is_section_open = true;
        }

        echo '<div class="event-row">';

        echo '<div class="event-row-header">';
        echo '<span class="sub-name">' . esc_html($detail_name) . '</span>';
        if ($date_display) {
            echo '<span class="event-date">' . esc_html($date_display) . '</span>';
        }
        echo '</div>';

        if ($characters) {
            echo '<ul class="character-list">';
            foreach ($characters as $char_item) {
                $char_name = $char_item['character_name'];
                $done = $char_item['done_tf'] ?? false;
                $status_class = $done ? 'tag-done' : 'tag-not-done';
                if ($char_name) {
                    echo '<li class="character-tag ' . esc_attr($status_class) . '">' . esc_html($char_name) . '</li>';
                }
            }
            echo '</ul>';
        }

        echo '</div>'; // .event-row close
    }

    if ($is_section_open) {
        echo '</div>'; // .event-group-content close
        echo '</div>'; // .event-group close
    }

    echo '</div>'; // .event-history-container close
}
?>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        // 全キャラ完了の判定と✅の付与
        const groups = document.querySelectorAll('.event-group');
        groups.forEach(group => {
            const tags = group.querySelectorAll('.character-tag');
            if (tags.length === 0) return;

            const isAllDone = Array.from(tags).every(tag => tag.classList.contains('tag-done'));

            if (isAllDone) {
                group.classList.add('is-all-done');
                const titleText = group.querySelector('.title-text');
                if (titleText) {
                    titleText.textContent = '✅ ' + titleText.textContent;
                }
            }
        });

        // 完了グループの表示・非表示切り替え
        const toggleBtn = document.getElementById('toggle-done-groups-btn');
        let isHidden = false;

        if (toggleBtn) {
            toggleBtn.addEventListener('click', () => {
                isHidden = !isHidden;
                const doneGroups = document.querySelectorAll('.is-all-done');

                doneGroups.forEach(group => {
                    group.style.display = isHidden ? 'none' : 'block';
                });

                toggleBtn.textContent = isHidden ? '完了済みのグループを表示' : '完了済みのグループを非表示';
            });
        }

        // アコーディオンの排他開閉制御とスクロール
        const triggers = document.querySelectorAll('.js-accordion-trigger');
        triggers.forEach(trigger => {
            trigger.addEventListener('click', function() {
                const currentGroup = this.closest('.event-group');
                const currentContent = currentGroup.querySelector('.event-group-content');
                const isOpen = currentContent.classList.contains('is-open');

                document.querySelectorAll('.event-group-content').forEach(content => {
                    content.classList.remove('is-open');
                    content.style.display = 'none';
                });

                document.querySelectorAll('.accordion-icon').forEach(icon => {
                    icon.textContent = '▼';
                });

                if (!isOpen) {
                    currentContent.classList.add('is-open');
                    currentContent.style.display = 'block';
                    const icon = this.querySelector('.accordion-icon');
                    if (icon) icon.textContent = '▲';

                    // 開いたタブの先頭に瞬時にスクロール
                    currentGroup.scrollIntoView({
                        behavior: 'instant',
                        block: 'start'
                    });
                }
            });
        });
    });
</script>

<style>
    .event-history-container {
        max-width: 800px;
        margin: 30px auto;
        padding: 0 15px;
        font-family: "Helvetica Neue", Arial, sans-serif;
    }

    /* 上部のトグルボタン用レイアウト */
    .controls-container {
        margin-bottom: 20px;
        text-align: right;
    }

    .event-group {
        margin-bottom: 40px;
        background: #fff;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
    }

    .event-main-title {
        background-color: #333;
        color: #fff;
        font-size: 1.2rem;
        padding: 12px 20px;
        margin: 0;
    }

    .event-main-title.js-accordion-trigger {
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: center;
        user-select: none;
    }

    .event-main-title.js-accordion-trigger:hover {
        background-color: #444;
    }

    .accordion-icon {
        font-size: 0.8em;
    }

    .event-row {
        padding: 20px;
        border-bottom: 1px solid #eee;
    }

    .event-row:last-child {
        border-bottom: none;
    }

    .event-row-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
        flex-wrap: wrap;
        gap: 10px;
    }

    .event-row-header .sub-name {
        font-size: 1.1rem;
        font-weight: bold;
        color: #444;
        border-left: 4px solid #d32f2f;
        padding-left: 10px;
    }

    .event-row-header .event-date {
        font-size: 0.9rem;
        color: #888;
        background: #f5f5f5;
        padding: 4px 10px;
        border-radius: 4px;
    }

    .character-list {
        list-style: none;
        margin: 0;
        padding: 0;
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }

    .character-tag {
        font-size: 0.9rem;
        padding: 6px 12px;
        border-radius: 20px;
    }

    .character-tag.tag-done {
        background-color: #e8f5e9;
        color: #2e7d32;
        border: 1px solid #c8e6c9;
    }

    .character-tag.tag-not-done {
        background-color: #ffebee;
        color: #c62828;
        border: 1px solid #ffcdd2;
    }

    .toggle-done-btn {
        padding: 8px 16px;
        background-color: #fff;
        border: 1px solid #ccc;
        border-radius: 4px;
        cursor: pointer;
        font-weight: bold;
        color: #333;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        transition: 0.2s;
    }

    .toggle-done-btn:hover {
        background-color: #f5f5f5;
    }
</style>


<?php
get_footer();
