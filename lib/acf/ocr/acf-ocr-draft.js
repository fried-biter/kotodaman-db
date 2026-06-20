(function () {
    const config = window.KOTO_OCR_DRAFT_CONFIG;
    if (!config) return;

    const previewDbName = 'koto-ocr-draft-preview';
    const previewStoreName = 'sourceImages';
    const previewTtlMs = 24 * 60 * 60 * 1000;
    const panel = document.querySelector('[data-koto-ocr-panel]');
    hydrateReviewSourceImages();
    cleanupOcrSourceImages();
    if (!panel) return;

    const toggle = panel.querySelector('[data-koto-ocr-toggle]');
    const body = panel.querySelector('[data-koto-ocr-body]');
    const input = panel.querySelector('[data-koto-ocr-input]');
    const dropzone = panel.querySelector('[data-koto-ocr-dropzone]');
    const preview = panel.querySelector('[data-koto-ocr-preview]');
    const submit = panel.querySelector('[data-koto-ocr-submit]');
    const spinner = panel.querySelector('[data-koto-ocr-spinner]');
    const status = panel.querySelector('[data-koto-ocr-status]');
    const result = panel.querySelector('[data-koto-ocr-result]');
    let selectedFiles = [];
    let objectUrls = [];
    let hasCreatedDraft = false;
    let timer = null;
    let startedAt = 0;

    toggle.addEventListener('click', () => setOpen(body.hidden));
    input.addEventListener('change', () => setFiles(Array.from(input.files || [])));

    ['dragenter', 'dragover'].forEach((eventName) => {
        dropzone.addEventListener(eventName, (event) => {
            event.preventDefault();
            dropzone.classList.add('is-dragover');
        });
    });
    ['dragleave', 'drop'].forEach((eventName) => {
        dropzone.addEventListener(eventName, (event) => {
            event.preventDefault();
            dropzone.classList.remove('is-dragover');
        });
    });
    dropzone.addEventListener('drop', (event) => setFiles(Array.from(event.dataTransfer.files || [])));
    submit.addEventListener('click', runOcr);

    function setOpen(open) {
        body.hidden = !open;
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    function setFiles(files) {
        revokeObjectUrls();
        selectedFiles = files.filter((file) => config.allowedMimeTypes.includes(file.type));
        if (files.length !== selectedFiles.length) {
            renderError('PNG/JPEG/WebP以外のファイルは除外しました。');
        } else {
            result.innerHTML = '';
        }
        renderPreview();
    }

    function renderPreview() {
        preview.innerHTML = '';
        selectedFiles.forEach((file) => {
            const url = URL.createObjectURL(file);
            objectUrls.push(url);
            const item = document.createElement('a');
            item.className = 'koto-ocr-thumb';
            item.href = url;
            item.target = '_blank';
            item.rel = 'noopener';
            item.innerHTML = `<img alt="" src="${url}"><span>${escapeHtml(file.name)} (${formatBytes(file.size)})</span>`;
            preview.appendChild(item);
        });
    }

    async function runOcr() {
        if (!config.hasApiKey) return renderError('OpenRouter APIキーが未設定です。');
        if (!selectedFiles.length) return renderError('画像を選択してください。');
        if (selectedFiles.length > config.maxImages) return renderError(`画像は一度に${config.maxImages}枚までです。`);
        if (hasCreatedDraft && !window.confirm('新しい下書きが追加で作成されます。続行しますか？')) return;

        try {
            setBusy(true);
            const prepared = [];
            for (let index = 0; index < selectedFiles.length; index++) {
                const file = selectedFiles[index];
                setProgress(`画像を準備中 ${index + 1}/${selectedFiles.length}: ${file.name}`);
                prepared.push(await resizeIfNeeded(file));
            }
            setProgress(`OCRへ送信中: ${prepared.length}枚`);
            const formData = new FormData();
            formData.append('action', config.action);
            formData.append('nonce', config.nonce);
            prepared.forEach((file) => formData.append('images[]', file, file.name));
            let response;
            try {
                response = await fetch(config.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' });
            } catch (fetchError) {
                throw new Error(`OCRリクエスト送信に失敗しました: ${fetchError.message || String(fetchError)}`);
            }
            const text = await response.text();
            let json;
            try {
                json = JSON.parse(text);
            } catch (parseError) {
                throw new Error(`サーバーがJSON以外の応答を返しました。HTTP ${response.status}: ${text.slice(0, 120)}`);
            }
            if (!json.success) throw new Error(json.data && json.data.message ? json.data.message : 'OCR実行に失敗しました。');
            hasCreatedDraft = true;
            if (json.data && json.data.postId) {
                await savePreparedImagesForPost(json.data.postId, prepared);
            }
            setOpen(true);
            renderSuccess(json.data);
        } catch (error) {
            renderError(error.message || String(error));
        } finally {
            setBusy(false);
        }
    }

    async function resizeIfNeeded(file) {
        if (file.size <= config.maxImageBytes) return file;
        let bitmap;
        try {
            bitmap = await createImageBitmap(file);
        } catch (imageError) {
            throw new Error(`${file.name} を画像として読み込めませんでした: ${imageError.message || String(imageError)}`);
        }
        const steps = [1800, 1440, 1200, 960, 720];
        const qualities = [0.86, 0.76, 0.66, 0.56, 0.46];
        for (const maxSide of steps) {
            const scale = Math.min(1, maxSide / Math.max(bitmap.width, bitmap.height));
            const canvas = document.createElement('canvas');
            canvas.width = Math.max(1, Math.round(bitmap.width * scale));
            canvas.height = Math.max(1, Math.round(bitmap.height * scale));
            canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height);
            for (const quality of qualities) {
                const webp = await canvasToBlob(canvas, 'image/webp', quality);
                if (webp && webp.size <= config.maxImageBytes) return new File([webp], replaceExt(file.name, 'webp'), { type: 'image/webp' });
                // iOS Safariはcanvas.toBlob('image/webp')がnullになるためJPEGへfallbackする。
                const jpeg = await canvasToBlob(canvas, 'image/jpeg', quality);
                if (jpeg && jpeg.size <= config.maxImageBytes) return new File([jpeg], replaceExt(file.name, 'jpg'), { type: 'image/jpeg' });
            }
        }
        throw new Error(`${file.name} を上限以下に縮小できませんでした。`);
    }

    function canvasToBlob(canvas, type, quality) {
        return new Promise((resolve) => canvas.toBlob(resolve, type, quality));
    }

    function renderSuccess(data) {
        const warnings = (data.warnings || []).map((warning) => `<li><strong>${escapeHtml(warning.field || '')}</strong>: ${escapeHtml(warning.message || '')}</li>`).join('');
        const rawText = (data.rawText || []).map((item) => `<details><summary>${escapeHtml(item.source_image || '')} OCR raw text</summary><pre>${escapeHtml(item.text || '')}</pre></details>`).join('');
        const links = data.links || {};
        const buttons = [
            ['基本データで開く', links.dbEditor],
            ['わざ、すごわざで開く', links.dbEditorSkills],
            ['とくせいで開く', links.dbEditorTraits],
            ['祝福で開く', links.dbEditorBlessing],
            ['投稿編集で開く', links.editPost],
        ].filter(([, url]) => url).map(([label, url]) => `<a class="button" target="_blank" rel="noopener" href="${escapeAttr(url)}">${escapeHtml(label)}</a>`).join(' ');
        result.innerHTML = `<div class="notice notice-success inline"><p><strong>下書きを作成しました:</strong> ${escapeHtml(data.title || '')} (#${data.postId})</p><p>${buttons}</p></div>${warnings ? `<div class="notice notice-warning inline"><ul>${warnings}</ul></div>` : ''}${rawText}${data.debug ? '<p><button type="button" class="button" data-koto-ocr-copy-debug>debug JSONをコピー</button></p>' : ''}`;
        const copy = result.querySelector('[data-koto-ocr-copy-debug]');
        if (copy) copy.addEventListener('click', () => navigator.clipboard.writeText(JSON.stringify(data.debug, null, 2)));
    }

    function renderError(message) {
        result.innerHTML = `<div class="notice notice-error inline"><p>${escapeHtml(message)}</p></div>`;
    }

    function setBusy(busy) {
        submit.disabled = busy || !config.hasApiKey;
        spinner.classList.toggle('is-active', busy);
        if (busy) {
            startedAt = Date.now();
            setProgress(`0/${config.timeoutSeconds}秒`);
            timer = window.setInterval(() => {
                const elapsed = Math.floor((Date.now() - startedAt) / 1000);
                setProgress(`${elapsed}/${config.timeoutSeconds}秒`);
            }, 1000);
        } else {
            window.clearInterval(timer);
            timer = null;
            setProgress('');
        }
    }

    function setProgress(message) {
        status.textContent = message;
    }

    function revokeObjectUrls() {
        objectUrls.forEach((url) => URL.revokeObjectURL(url));
        objectUrls = [];
    }

    function replaceExt(name, ext) {
        return name.replace(/\.[^.]+$/, '') + '.' + ext;
    }

    function formatBytes(bytes) {
        return `${Math.round(bytes / 1024)}KB`;
    }

    function escapeHtml(value) {
        return String(value).replace(/[&<>"]/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[char]));
    }

    function escapeAttr(value) {
        return escapeHtml(value || '#');
    }

    function openOcrPreviewDb() {
        if (!window.indexedDB) return Promise.resolve(null);
        return new Promise((resolve) => {
            const request = indexedDB.open(previewDbName, 1);
            request.onupgradeneeded = () => {
                const db = request.result;
                if (!db.objectStoreNames.contains(previewStoreName)) {
                    db.createObjectStore(previewStoreName, { keyPath: 'key' });
                }
            };
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => resolve(null);
            request.onblocked = () => resolve(null);
        });
    }

    async function putOcrSourceImage(postId, sourceImage, file) {
        const db = await openOcrPreviewDb();
        if (!db) return;
        try {
            const tx = db.transaction(previewStoreName, 'readwrite');
            const done = transactionDone(tx);
            tx.objectStore(previewStoreName).put({
                key: `${postId}:${sourceImage}`,
                postId: Number(postId),
                sourceImage,
                fileName: file.name || sourceImage,
                mimeType: file.type || 'application/octet-stream',
                createdAt: Date.now(),
                blob: file,
            });
            await done;
        } catch (error) {
            // プレビュー画像の保存失敗はOCR下書き作成自体を失敗させない。
        } finally {
            db.close();
        }
    }

    async function getOcrSourceImage(postId, sourceImage) {
        const db = await openOcrPreviewDb();
        if (!db) return null;
        try {
            const tx = db.transaction(previewStoreName, 'readonly');
            const done = transactionDone(tx);
            const record = await requestResult(tx.objectStore(previewStoreName).get(`${postId}:${sourceImage}`));
            await done;
            if (!record || record.createdAt < Date.now() - previewTtlMs) return null;
            return record;
        } catch (error) {
            return null;
        } finally {
            db.close();
        }
    }

    async function cleanupOcrSourceImages() {
        const db = await openOcrPreviewDb();
        if (!db) return;
        try {
            const tx = db.transaction(previewStoreName, 'readwrite');
            const done = transactionDone(tx);
            const store = tx.objectStore(previewStoreName);
            const cutoff = Date.now() - previewTtlMs;
            await new Promise((resolve) => {
                const request = store.openCursor();
                request.onsuccess = () => {
                    const cursor = request.result;
                    if (!cursor) return resolve();
                    if ((cursor.value.createdAt || 0) < cutoff) cursor.delete();
                    cursor.continue();
                };
                request.onerror = () => resolve();
            });
            await done;
        } catch (error) {
        } finally {
            db.close();
        }
    }

    async function savePreparedImagesForPost(postId, preparedFiles) {
        await cleanupOcrSourceImages();
        await Promise.all(preparedFiles.map((file, index) => putOcrSourceImage(postId, `image_${index + 1}`, file)));
    }

    async function hydrateReviewSourceImages() {
        const items = Array.from(document.querySelectorAll('[data-koto-ocr-review-panel] [data-koto-ocr-source-image]'));
        await Promise.all(items.map(async (item) => {
            const postId = item.getAttribute('data-koto-ocr-post-id');
            const sourceImage = item.getAttribute('data-koto-ocr-source-image');
            const container = item.querySelector('[data-koto-ocr-source-image-container]');
            if (!postId || !sourceImage || !container) return;
            const record = await getOcrSourceImage(postId, sourceImage);
            if (record) renderReviewSourceImage(container, record);
        }));
    }

    function renderReviewSourceImage(container, record) {
        const url = URL.createObjectURL(record.blob);
        const link = document.createElement('a');
        link.href = url;
        link.target = '_blank';
        link.rel = 'noopener';
        link.title = record.fileName || record.sourceImage;

        const image = document.createElement('img');
        image.alt = record.fileName || record.sourceImage;
        image.src = url;
        link.appendChild(image);

        const label = document.createElement('span');
        label.textContent = record.fileName || record.sourceImage;

        container.innerHTML = '';
        container.append(link, label);
    }

    function requestResult(request) {
        return new Promise((resolve, reject) => {
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    function transactionDone(tx) {
        return new Promise((resolve, reject) => {
            tx.oncomplete = () => resolve();
            tx.onerror = () => reject(tx.error);
            tx.onabort = () => reject(tx.error);
        });
    }
})();
