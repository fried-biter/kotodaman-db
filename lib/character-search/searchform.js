document.addEventListener('DOMContentLoaded', function () {

    // 各種ボタンとモーダルの要素をクラス名で一括取得
    const overlays = document.querySelectorAll('.js-search-modal-overlay');
    const openBtns = document.querySelectorAll('.js-toggle-advanced-search');
    const closeBtns = document.querySelectorAll('.js-close-modal-btn');
    const applyBtns = document.querySelectorAll('.js-apply-modal-btn');
    const resetButtons = document.querySelectorAll('.js-reset-search-btn, .js-modal-reset-search-btn');

    // モーダルを開き、背景のスクロールを無効化する処理
    const openModal = () => {
        overlays.forEach(overlay => overlay.style.display = 'flex');
        document.body.style.overflow = 'hidden';
    };

    // モーダルを閉じ、背景のスクロールを有効化する処理
    const closeModal = () => {
        overlays.forEach(overlay => overlay.style.display = 'none');
        document.body.style.overflow = '';
        if (typeof updateSearchUrlAndAnalytics === 'function') {
            updateSearchUrlAndAnalytics();
        }
    };

    // 取得した全てのボタンに開閉イベントを付与
    openBtns.forEach(btn => btn.addEventListener('click', openModal));
    [...closeBtns, ...applyBtns].forEach(btn => btn.addEventListener('click', closeModal));

    // 背景のオーバーレイ部分のクリックでモーダルを閉じる処理
    overlays.forEach(overlay => {
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) closeModal();
        });
    });

    // フォーム内の入力を初期状態に戻す処理
    const performReset = function () {
        const forms = document.querySelectorAll('.js-search-form');

        forms.forEach(form => {
            const textInputs = form.querySelectorAll('input[type="text"]');
            textInputs.forEach(input => input.value = '');

            const checkboxes = form.querySelectorAll('input[type="checkbox"]');
            checkboxes.forEach(box => {
                if (box.name === 'scope_skill[]' || box.name === 'scope_trait[]') return;
                box.checked = false;
                box.indeterminate = false;
            });

            const selects = form.querySelectorAll('select');
            selects.forEach(sel => sel.selectedIndex = 0);
        });

        const treeItems = document.querySelectorAll('.term-tree-item');
        treeItems.forEach(el => el.style.display = '');

        if (typeof window.filterCharacters === 'function') {
            window.filterCharacters();
        }
    };

    // 取得した全てのリセットボタンにイベントを付与
    resetButtons.forEach(btn => btn.addEventListener('click', performReset));

    // ツリー検索フィルター
    const treeSearches = document.querySelectorAll('.term-tree-search');
    treeSearches.forEach(function (input) {
        input.addEventListener('input', function () {
            const keyword = this.value.toLowerCase().trim();
            const container = this.closest('.custom-term-selector-ui');
            if (!container) return;

            const items = container.querySelectorAll('.term-tree-item');

            if (keyword === '') {
                items.forEach(el => el.style.display = '');
                return;
            }

            items.forEach(el => el.style.display = 'none');

            container.querySelectorAll('.term-name').forEach(function (span) {
                if (span.textContent.toLowerCase().includes(keyword)) {
                    let item = span.closest('.term-tree-item');
                    item.style.display = '';
                    let parent = item.parentElement.closest('.term-tree-item');
                    while (parent) {
                        parent.style.display = '';
                        const details = parent.querySelector('details');
                        if (details) details.open = true;
                        parent = parent.parentElement.closest('.term-tree-item');
                    }
                }
            });
        });
    });

    // 親子チェックボックスの連動ロジック
    const parentCheckboxes = document.querySelectorAll('.js-parent-checkbox');
    parentCheckboxes.forEach(parentCheckbox => {
        const details = parentCheckbox.closest('details');
        if (!details) return;

        const childContainer = details.querySelector('.tag-children, .term-children-container');
        if (!childContainer) return;

        const childCheckboxes = childContainer.querySelectorAll('input[type="checkbox"]');

        if (childCheckboxes.length > 0) {
            const updateParentState = () => {
                const total = childCheckboxes.length;
                const checkedCount = Array.from(childCheckboxes).filter(cb => cb.checked).length;

                if (checkedCount === 0) {
                    parentCheckbox.checked = false;
                    parentCheckbox.indeterminate = false;
                } else if (checkedCount === total) {
                    parentCheckbox.checked = true;
                    parentCheckbox.indeterminate = false;
                } else {
                    parentCheckbox.checked = false;
                    parentCheckbox.indeterminate = true;
                }
            };

            updateParentState();

            parentCheckbox.addEventListener('change', function () {
                const isChecked = this.checked;
                childCheckboxes.forEach(child => {
                    child.checked = isChecked;
                });

                if (typeof window.filterCharacters === 'function') {
                    window.filterCharacters();
                }
            });

            childCheckboxes.forEach(child => {
                child.addEventListener('change', function () {
                    updateParentState();
                });
            });
        }
    });

    // 親チェックボックスクリック時のアコーディオン開閉を防ぐ処理
    const parentLabels = document.querySelectorAll('summary .term-label, summary .parent-label');
    parentLabels.forEach(label => {
        label.addEventListener('click', function (e) {
            e.stopPropagation();
        });
    });
    // リーダー検索項目の追加・削除機能
    const addLeaderCondBtns = document.querySelectorAll('.js-add-leader-btn');
    let leaderIndex = 0;

    addLeaderCondBtns.forEach(btn => {
        btn.addEventListener('click', function () {
            // 現在の検索フォーム全体を取得
            const form = this.closest('.js-search-form');
            // 追加先のコンテナと、複製元のテンプレートをクラス名で取得
            const container = form.querySelector('.leader-container');
            const template = form.querySelector('.js-leader-template');
            if (template && container) {
                // テンプレートのHTMLを文字列として取得し、プレースホルダーを置換
                const currentIndex = leaderIndex++;
                const templateHtml = template.innerHTML.replace(/__IDX__/g, `leader_${currentIndex}_`);

                // 一時的なdivを作成してHTMLをパース
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = templateHtml;
                const newItem = tempDiv.firstElementChild;

                // コンテナの末尾に追加
                container.appendChild(newItem);
            }
        });
    });

    // 動的に追加された要素に対するクリックイベントの捕捉
    document.addEventListener('click', function (e) {
        // クリックされた要素が削除ボタンであるか判定
        if (e.target && e.target.classList.contains('js-remove-cond-btn')) {
            // 削除ボタンの親にあたる項目全体を削除
            e.target.closest('.leader-item').remove();
        }
    });

    document.addEventListener('change', function (e) {
        if (e.target.classList.contains('js-leader-type-select')) {
            const leaderItem = e.target.closest('.leader-item');
            if (leaderItem) {
                const statusResistanceSelect = leaderItem.querySelector('.js-leader-status-resistance');
                if (statusResistanceSelect) {
                    if (e.target.value === 'status_resistance') {
                        statusResistanceSelect.style.display = '';
                    } else {
                        statusResistanceSelect.style.display = 'none';
                    }
                }
            }
        }
    });

    document.addEventListener('change', function (e) {
        if (e.target.classList.contains('js-leader-target-select')) {
            const leaderItem = e.target.closest('.leader-item');
            if (leaderItem) {
                const attrContainer = leaderItem.querySelector('.leader-target-attr');
                const speciesContainer = leaderItem.querySelector('.leader-target-species');
                const groupContainer = leaderItem.querySelector('.leader-target-group');
                const selectedValue = e.target.value;

                if (attrContainer) {
                    attrContainer.style.display = selectedValue === 'attribute' ? 'flex' : 'none';
                    if (selectedValue === 'attribute') {
                        attrContainer.style.justifyContent = 'center';
                    }
                }
                if (speciesContainer) {
                    speciesContainer.style.display = selectedValue === 'species' ? 'flex' : 'none';
                    if (selectedValue === 'species') {
                        speciesContainer.style.justifyContent = 'center';
                    }
                }
                if (groupContainer) {
                    groupContainer.style.display = selectedValue === 'affiliation' ? 'block' : 'none';
                }
            }
        }
    });

    document.addEventListener('change', function (e) {
        if (e.target.classList.contains('js-leader-cond-type-select')) {
            const leaderItem = e.target.closest('.leader-item');
            if (leaderItem) {
                const condTypeSelect = leaderItem.querySelector('.js-leader-cond-type-select');
                const selectedValue = condTypeSelect.value;
                const condTargetSelect = leaderItem.querySelector('.js-leader-cond-target-select');
                const selectedTarget = condTargetSelect ? condTargetSelect.value : '';
                const condValueInput = leaderItem.querySelector('.js-leader-cond-val');
                const condAttrContainer = leaderItem.querySelector('.leader-cond-attr');
                const condSpeciesContainer = leaderItem.querySelector('.leader-cond-species');
                if (selectedValue === '') {
                    condTargetSelect.style.display = 'none';
                    condValueInput.style.display = 'none';
                } else if (selectedValue === 'chara_num') {
                    condTargetSelect.style.display = 'block';
                    condValueInput.style.display = 'block';
                    condValueInput.placeholder = '必要編成数を入力';
                } else {
                    condValueInput.placeholder = '条件の値を入力';
                    condValueInput.style.display = 'block';
                    condTargetSelect.style.display = 'none';
                }
                if (condAttrContainer) {
                    condAttrContainer.style.display = selectedTarget === 'attribute' ? 'flex' : 'none';
                }
                if (condSpeciesContainer) {
                    condSpeciesContainer.style.display = selectedTarget === 'species' ? 'flex' : 'none';
                }
            }
        }
    });

    document.addEventListener('change', function (e) {
        if (e.target.classList.contains('js-leader-cond-target-select')) {
            const leaderItem = e.target.closest('.leader-item');
            if (leaderItem) {
                const selectedTarget = e.target.value;
                const condAttrContainer = leaderItem.querySelector('.leader-cond-attr');
                const condSpeciesContainer = leaderItem.querySelector('.leader-cond-species');
                if (condAttrContainer) {
                    condAttrContainer.style.display = selectedTarget === 'attribute' ? 'flex' : 'none';
                }
                if (condSpeciesContainer) {
                    condSpeciesContainer.style.display = selectedTarget === 'species' ? 'flex' : 'none';
                }
            }
        }
    });
});

// タブ切り替え処理
const searchForms = document.querySelectorAll('.js-search-form');
searchForms.forEach(form => {
    const tabButtons = form.querySelectorAll('.area-select-button');
    const tabContents = form.querySelectorAll('.tab-content');

    tabButtons.forEach(button => {
        button.addEventListener('click', function () {
            // タブとコンテンツの初期化
            tabButtons.forEach(btn => btn.classList.remove('is-active'));
            tabContents.forEach(content => content.classList.remove('is-active'));

            // クリックされたタブをアクティブ化
            this.classList.add('is-active');

            // 対象コンテンツのアクティブ化
            const targetId = this.getAttribute('data-target');
            const targetContent = form.querySelector(`.tab-content[data-content="${targetId}"]`);

            if (targetContent) {
                targetContent.classList.add('is-active');
            }
        });
    });
});