(function () {
    'use strict';
    const form = document.getElementById('coverMaintenanceForm');
    if (!form) return;
    const preview = document.getElementById('previewCovers');
    const apply = document.getElementById('applyCovers');
    const status = document.getElementById('coverMaintenanceStatus');
    const rows = [...form.querySelectorAll('[data-trip-id]')];
    let busy = false;
    function selected() { return rows.filter(row => row.querySelector('input').checked); }
    function refresh() {
        apply.disabled = busy || !selected().some(row => row.dataset.token);
        preview.disabled = busy;
        document.getElementById('selectAllCovers').disabled = busy;
        rows.forEach(row => { row.querySelector('input').disabled = busy; });
    }
    async function request(data) {
        data.append('csrf_token', form.dataset.csrf);
        const response = await fetch('admin-trip-covers.php', {method: 'POST', body: data, headers: {Accept: 'application/json'}});
        let result;
        try { result = await response.json(); } catch (_) { throw new Error('Phiên đăng nhập hết hạn hoặc máy chủ chưa trả kết quả. Hãy tải lại trang.'); }
        if (!response.ok || !result.ok) throw new Error(result.message || 'Không thể thực hiện thao tác.');
        return result;
    }
    form.addEventListener('change', refresh);
    document.getElementById('selectAllCovers').addEventListener('click', () => {
        const check = rows.some(row => !row.querySelector('input').checked);
        rows.forEach(row => { row.querySelector('input').checked = check; });
        refresh();
    });
    preview.addEventListener('click', async () => {
        const targets = selected();
        if (!targets.length) { status.textContent = 'Chọn ít nhất một lịch trình.'; return; }
        busy = true; refresh();
        let errors = 0;
        let retries = 0;
        for (let index = 0; index < targets.length; index++) {
            if (index > 0) await new Promise(resolve => setTimeout(resolve, 1100));
            const row = targets[index];
            delete row.dataset.token;
            delete row.dataset.imageReady;
            const box = row.querySelector('.cover-audit-proposal');
            box.replaceChildren();
            status.textContent = `Đang kiểm tra và tìm ảnh ${index + 1}/${targets.length}. Chưa cập nhật dữ liệu.`;
            const data = new FormData(); data.append('action', 'preview'); data.append('id', row.dataset.tripId);
            try {
                const result = await request(data);
                row.querySelector('.cover-audit-note').textContent = result.message;
                if (result.status === 'protected') {
                    box.textContent = 'Giữ nguyên ảnh hiện tại.';
                    row.querySelector('input').checked = false;
                    continue;
                }
                if (result.status !== 'ready' || !result.candidate || !result.token) {
                    retries++;
                    box.textContent = result.message || 'Cần tìm lại ảnh. Chưa cập nhật dữ liệu.';
                    continue;
                }
                row.dataset.token = result.token;
                if (result.candidate) {
                    const image = document.createElement('img');
                    image.className = 'cover-audit-image d-block mb-2'; image.alt = '';
                    image.onload = () => { if (row.dataset.token === result.token) { row.dataset.imageReady = 'true'; refresh(); } };
                    image.onerror = () => { if (row.dataset.token !== result.token) return; delete row.dataset.token; row.querySelector('.cover-audit-note').textContent = 'Ảnh đề xuất không tải được. Hãy tìm lại; chưa cho phép cập nhật.'; refresh(); };
                    image.src = result.candidate.image_url;
                    const title = document.createElement('div'); title.textContent = result.candidate.title;
                    const source = document.createElement('a'); source.href = result.candidate.source_url;
                    source.target = '_blank'; source.rel = 'noopener noreferrer'; source.textContent = 'Nguồn Wikimedia';
                    const license = document.createElement('a'); license.href = result.candidate.license_url;
                    license.target = '_blank'; license.rel = 'noopener noreferrer';
                    license.textContent = result.candidate.license;
                    const artist = document.createElement('div'); artist.textContent = result.candidate.attribution;
                    box.append(image, title, artist, source, document.createTextNode(' · '), license);
                }
            } catch (error) {
                delete row.dataset.token;
                errors++; box.textContent = error.message;
            }
        }
        busy = false; refresh();
        status.textContent = `Đã xử lý ${targets.length} lịch trình, ${errors} lỗi, ${retries} mục cần tìm lại. Chỉ ảnh đã tìm được và xem trước mới có thể xác nhận cập nhật.`;
    });
    apply.addEventListener('click', async () => {
        const targets = selected().filter(row => row.dataset.token);
        if (targets.some(row => row.dataset.imageReady !== 'true')) {
            status.textContent = 'Chờ ảnh đề xuất tải xong trước khi xác nhận.'; return;
        }
        if (!targets.length || !window.confirm(`Xác nhận cập nhật ảnh bìa cho ${targets.length} lịch trình đã xem trước? Nội dung và tác giả sẽ được giữ nguyên.`)) return;
        busy = true; refresh();
        const data = new FormData(); data.append('action', 'apply');
        targets.forEach(row => data.append(`confirmations[${row.dataset.tripId}]`, row.dataset.token));
        try {
            const result = await request(data);
            targets.forEach(row => {
                const current = row.querySelector('.cover-audit-image');
                const proposed = row.querySelector('.cover-audit-proposal img');
                if (current && proposed) {
                    delete current.dataset.coverFallback;
                    current.style.visibility = '';
                    current.src = proposed.src;
                }
                delete row.dataset.token; row.querySelector('input').checked = false;
                row.querySelector('.cover-audit-note').textContent = 'Đã cập nhật ảnh bìa; giữ nguyên tác giả, nội dung và trạng thái.';
            });
            status.textContent = result.message;
        } catch (error) { status.textContent = error.message; }
        busy = false; refresh();
    });
}());
