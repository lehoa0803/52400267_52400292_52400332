(function () {
    'use strict';

    const form = document.getElementById('tripForm');
    if (!form) return;

    let currentDay = 1;
    let maxDays = parseInt(document.getElementById('tripDays').value || '1', 10);
    let activities = window.tripTimelineInitialActivities || {};
    let dayDescriptions = window.tripTimelineInitialDescriptions || {};
    let deletedActivityIds = [];

    const $ = (id) => document.getElementById(id);
    const esc = (value) => String(value ?? '').replace(/[&<>'"]/g, (char) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'
    }[char]));

    const fields = {
        start_time: 'activityStartTime', end_time: 'activityEndTime', title: 'activityName',
        location: 'activityPlace', location_catalog_id: 'activityLocationCatalogId', category: 'activityCategory', cost: 'activityCost',
        cost_scope: 'activityCostScope', description: 'activityDescription', notes: 'activityNotes',
        menu_suggestions: 'activityMenu', nearby_amenities: 'activityAmenities'
    };

    function dayKey() { return 'day_' + currentDay; }
    function pad(value) { return String(value).padStart(2, '0'); }
    function dateForDay(dayNumber) {
        const departure = $('departureAt').value || '';
        const datePart = departure.substring(0, 10);
        if (!/^\d{4}-\d{2}-\d{2}$/.test(datePart)) return '';
        const [year, month, day] = datePart.split('-').map(Number);
        // datetime-local không có timezone. Dùng UTC chỉ để cộng ngày theo lịch,
        // tránh timezone của trình duyệt làm lùi ngày 22/10 thành 21/10.
        const date = new Date(Date.UTC(year, month - 1, day + dayNumber - 1));
        return `${date.getUTCFullYear()}-${pad(date.getUTCMonth() + 1)}-${pad(date.getUTCDate())}`;
    }
    function dateTimeFor(dayNumber, time, overnight) {
        const date = dateForDay(dayNumber);
        if (!date || !/^\d{2}:\d{2}$/.test(time || '')) return '';
        if (!overnight) return `${date}T${time}`;
        const [year, month, day] = date.split('-').map(Number);
        const next = new Date(Date.UTC(year, month - 1, day + 1));
        return `${next.getUTCFullYear()}-${pad(next.getUTCMonth() + 1)}-${pad(next.getUTCDate())}T${time}`;
    }
    function timePart(value) {
        const match = String(value || '').match(/(?:T|\s)(\d{2}:\d{2})/);
        return match ? match[1] : '';
    }
    function datePart(value) {
        const match = String(value || '').match(/^(\d{4}-\d{2}-\d{2})/);
        return match ? match[1] : '';
    }
    function activityForUi(item) {
        const startDate = datePart(item.start_at);
        const endDate = datePart(item.end_at);
        return Object.assign({}, item, {
            start_time: item.start_time || timePart(item.start_at),
            end_time: item.end_time || timePart(item.end_at),
            overnight: item.overnight === true || (!!startDate && !!endDate && endDate > startDate)
        });
    }
    Object.keys(activities).forEach((key) => { activities[key] = activities[key].map(activityForUi); });
    function currentList() { return activities[dayKey()] || (activities[dayKey()] = []); }
    function money(value) { return Number(value || 0).toLocaleString('vi-VN') + ' đ'; }
    function activityTotal(item) {
        const participants = parseInt($('participantCount').value || '0', 10);
        return item.cost_scope === 'per_person' ? Number(item.cost || 0) * participants : Number(item.cost || 0);
    }
    function updateBudgetSummary() {
        const total = Object.values(activities).flat().reduce((sum, item) => sum + activityTotal(item), 0);
        const participants = parseInt($('participantCount').value || '0', 10);
        if ($('tripCalculatedTotal')) $('tripCalculatedTotal').textContent = money(total);
        if ($('tripAveragePerPerson')) $('tripAveragePerPerson').textContent = money(participants > 0 ? total / participants : 0);
        if ($('targetBudgetValue')) $('targetBudgetValue').textContent = money($('tripBudget').value);
    }

    function render() {
        const list = currentList();
        const target = $('activityList');
        if (!list.length) {
            target.innerHTML = '<p class="text-muted fst-italic">Chưa có hoạt động nào cho ngày này.</p>';
            $('currentDayTotal').textContent = '0 đ';
            updateBudgetSummary();
            return;
        }

        target.innerHTML = list.map((item, index) => {
            const scope = item.cost_scope === 'per_person' ? 'theo người' : 'cả nhóm/cố định';
            const time = item.start_time || item.end_time ? `${item.start_time || '--'} – ${item.end_time || '--'}${item.overnight ? ' (ngày hôm sau)' : ''}` : 'Chưa xác định thời gian';
            return `<div class="timeline-item border-start border-primary ps-3 mb-3">
                <div class="small text-muted">${esc(time)}</div>
                <strong>${esc(item.title)}</strong> <span class="badge bg-secondary">${esc(item.category)}</span>
                <div class="small text-muted"><i class="bi bi-geo-alt"></i> ${esc(item.location)} · ${money(item.cost)} (${scope})</div>
                ${item.description ? `<div class="small mt-1">${esc(item.description)}</div>` : ''}
                ${item.notes ? `<div class="small text-muted">Ghi chú: ${esc(item.notes)}</div>` : ''}
                <div class="mt-2"><button type="button" class="btn btn-sm btn-outline-secondary me-1" data-edit="${index}">Sửa</button><button type="button" class="btn btn-sm btn-outline-secondary me-1" data-up="${index}" ${index === 0 ? 'disabled' : ''}>Lên</button><button type="button" class="btn btn-sm btn-outline-secondary me-1" data-down="${index}" ${index === currentList().length - 1 ? 'disabled' : ''}>Xuống</button><button type="button" class="btn btn-sm btn-outline-danger" data-remove="${index}">Xóa</button></div>
            </div>`;
        }).join('');

        $('currentDayTotal').textContent = money(list.reduce((total, item) => total + activityTotal(item), 0));
        updateBudgetSummary();
    }

    function updateDay() {
        const dayDate = dateForDay(currentDay);
        $('dayBadge').textContent = 'Ngày ' + currentDay + (dayDate ? ' - ' + dayDate.split('-').reverse().join('/') : '');
        $('dayDescLabel').textContent = 'Mô tả tổng quan Ngày ' + currentDay;
        $('currentDayDescInput').value = dayDescriptions[dayKey()] || '';
        render();
    }

    function resetActivityForm() {
        Object.values(fields).forEach((id) => { if ($(id)) $(id).value = ''; });
        if ($('locationSearchStatus')) $('locationSearchStatus').textContent = '';
        if ($('locationSearchResults')) $('locationSearchResults').innerHTML = '';
        $('activityOvernight').checked = false;
        $('overnightHint').textContent = 'Sang ngày kế tiếp';
        $('activityCategory').value = 'sightseeing';
        $('activityCostScope').value = 'fixed';
        $('btnAddActivity').dataset.editing = '';
        $('btnAddActivity').innerHTML = '<i class="bi bi-plus-lg"></i> Thêm hoạt động';
    }

    function readActivity() {
        return Object.fromEntries(Object.entries(fields).map(([key, id]) => [key, $(id).value.trim()]));
    }

    function fillActivity(item, index) {
        Object.entries(fields).forEach(([key, id]) => { $(id).value = item[key] || ''; });
        $('activityOvernight').checked = item.overnight === true;
        $('overnightHint').textContent = item.overnight === true ? 'Sang Ngày ' + (currentDay + 1) : 'Sang ngày kế tiếp';
        $('btnAddActivity').dataset.editing = String(index);
        $('btnAddActivity').innerHTML = '<i class="bi bi-check-lg"></i> Cập nhật hoạt động';
    }

    $('tripDays').addEventListener('input', function () {
        maxDays = Math.max(1, Math.min(15, parseInt(this.value || '1', 10)));
        if (currentDay > maxDays) { currentDay = maxDays; updateDay(); }
    });
    $('participantCount').addEventListener('input', render);
    $('tripBudget').addEventListener('input', updateBudgetSummary);
    $('departureAt').addEventListener('input', updateDay);
    $('activityOvernight').addEventListener('change', () => {
        $('overnightHint').textContent = $('activityOvernight').checked ? 'Sang Ngày ' + (currentDay + 1) : 'Sang ngày kế tiếp';
    });
    $('dayPlus').addEventListener('click', () => { if (currentDay < maxDays) { currentDay++; updateDay(); } });
    $('dayMinus').addEventListener('click', () => { if (currentDay > 1) { currentDay--; updateDay(); } });
    $('currentDayDescInput').addEventListener('input', () => { dayDescriptions[dayKey()] = $('currentDayDescInput').value; });

    let lastLocationResults = [];
    let lastLocationSource = 'mysql';
    function renderLocationResults(results, source) {
        lastLocationResults = results;
        lastLocationSource = source;
        const target = $('locationSearchResults');
        if (!target) return;
        if (!results.length) {
            target.innerHTML = '<div class="list-group-item text-muted">Không tìm thấy địa điểm phù hợp.</div>';
            return;
        }
        target.innerHTML = results.map((item, index) => `<button type="button" class="list-group-item list-group-item-action" data-location-result="${index}"><strong>${esc(item.name || item.display_name || '')}</strong><br><span class="text-muted">${esc(item.address || '')}</span>${source === 'nominatim' ? '<br><small>Nguồn OpenStreetMap; chưa được xác minh hoàn toàn</small>' : ''}</button>`).join('');
    }
    async function searchLocations(external) {
        const query = $('activityPlace').value.trim();
        if (query.length < 2) {
            if ($('locationSearchStatus')) $('locationSearchStatus').textContent = 'Nhập ít nhất 2 ký tự.';
            return;
        }
        if ($('locationSearchStatus')) $('locationSearchStatus').textContent = external ? 'Đang tìm trên OpenStreetMap...' : 'Đang tìm trong dữ liệu TripConnect...';
        try {
            const response = await fetch(`location-search.php?q=${encodeURIComponent(query)}&external=${external ? '1' : '0'}`, { headers: { 'Accept': 'application/json' } });
            const responseText = await response.text();
            let data;
            try {
                data = JSON.parse(responseText);
            } catch (parseError) {
                throw new Error(`Endpoint tìm địa điểm trả về dữ liệu không hợp lệ (HTTP ${response.status}).`);
            }
            if (!response.ok || !data.ok) throw new Error(data.message || 'Không thể tìm địa điểm.');
            if ($('locationSearchStatus')) $('locationSearchStatus').textContent = data.message || (external ? 'Kết quả OpenStreetMap.' : 'Kết quả từ MySQL.');
            renderLocationResults(data.results || [], data.source || 'mysql');
        } catch (error) {
            const message = error && error.message ? error.message : 'Không thể kết nối dịch vụ tìm địa điểm.';
            if ($('locationSearchStatus')) $('locationSearchStatus').textContent = message;
            if ($('locationSearchResults')) $('locationSearchResults').innerHTML = `<div class="list-group-item list-group-item-warning">${esc(message)}</div>`;
        }
    }
    function applyCatalogLocation(item) {
        $('activityPlace').value = item.name || item.display_name || '';
        $('activityLocationCatalogId').value = item.id || '';
        if ($('locationSearchStatus')) $('locationSearchStatus').textContent = lastLocationSource === 'nominatim' ? 'Đã chọn kết quả bên ngoài; sẽ lưu nguồn OSM, chưa đánh dấu xác minh.' : 'Đã chọn địa điểm trong catalog.';
        if ($('locationSearchResults')) $('locationSearchResults').innerHTML = '';
    }
    $('btnSearchCatalog')?.addEventListener('click', () => searchLocations(false));
    $('btnSearchExternal')?.addEventListener('click', () => searchLocations(true));
    $('activityPlace')?.addEventListener('input', () => {
        $('activityLocationCatalogId').value = '';
        if ($('locationSearchStatus')) $('locationSearchStatus').textContent = 'Địa điểm nhập thủ công; chưa liên kết catalog.';
    });
    $('locationSearchResults')?.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-location-result]');
        if (!button) return;
        const item = lastLocationResults[Number(button.dataset.locationResult)];
        if (!item) return;
        if (lastLocationSource !== 'nominatim') {
            applyCatalogLocation(item);
            return;
        }
        const raw = item.raw || {};
        const formData = new FormData();
        formData.append('action', 'save_external');
        formData.append('csrf_token', document.querySelector('input[name="csrf_token"]')?.value || '');
        formData.append('osm_type', raw.osm_type || '');
        formData.append('osm_id', raw.osm_id || '');
        formData.append('result', JSON.stringify(raw));
        try {
            if ($('locationSearchStatus')) $('locationSearchStatus').textContent = 'Đang lưu lựa chọn địa điểm...';
            const response = await fetch('location-search.php', { method: 'POST', body: formData, headers: { 'Accept': 'application/json' } });
            const data = await response.json();
            if (!response.ok || !data.ok || !data.location) throw new Error(data.message || 'Không thể lưu địa điểm đã chọn.');
            lastLocationSource = 'nominatim';
            applyCatalogLocation(data.location);
        } catch (error) {
            if ($('locationSearchStatus')) $('locationSearchStatus').textContent = error.message;
        }
    });

    const coverQuery = $('coverImageQuery');
    const coverStatus = $('coverImageStatus');
    const coverResults = $('coverImageResults');
    const coverFields = {
        url: 'coverSelectedUrl', source: 'coverSelectedSource', source_url: 'coverSelectedSourceUrl',
        attribution: 'coverSelectedAttribution', license: 'coverSelectedLicense', license_url: 'coverSelectedLicenseUrl'
    };
    let coverCandidates = [];
    function clearSelectedCover() {
        Object.values(coverFields).forEach((id) => { if ($(id)) $(id).value = ''; });
    }
    function renderCoverCandidates(results) {
        coverCandidates = results;
        if (!coverResults) return;
        if (!results.length) {
            coverResults.innerHTML = '<div class="col-12 small text-muted">Chưa có ứng viên ảnh đủ tin cậy.</div>';
            return;
        }
        coverResults.innerHTML = results.map((item, index) => `<div class="col-md-6"><div class="card h-100"><img src="${esc(item.image_url)}" class="card-img-top" style="height:110px;object-fit:cover" alt="${esc(item.title)}" loading="lazy"><div class="card-body p-2"><div class="small fw-semibold">${esc(item.title)}</div><div class="small text-muted">${esc(item.license)} · ${esc(item.attribution)}</div><div class="small text-muted">${esc(item.match_note)}</div><button type="button" class="btn btn-sm btn-outline-primary mt-2" data-cover-result="${index}">Chọn ảnh này</button></div></div></div>`).join('');
    }
    $('btnSearchCoverImage')?.addEventListener('click', async () => {
        const query = (coverQuery?.value || $('tripLocation')?.selectedOptions?.[0]?.textContent || '').trim();
        const area = $('tripLocation')?.selectedOptions?.[0]?.textContent?.trim() || '';
        if (query.length < 2) {
            if (coverStatus) coverStatus.textContent = 'Nhập ít nhất 2 ký tự để tìm ảnh.';
            return;
        }
        if (coverStatus) coverStatus.textContent = 'Đang tìm ảnh có metadata trên Wikimedia Commons...';
        if (coverResults) coverResults.innerHTML = '';
        try {
            const response = await fetch(`location-search.php?action=search_wikimedia&q=${encodeURIComponent(query)}&area=${encodeURIComponent(area)}`, { headers: { Accept: 'application/json' } });
            const data = await response.json();
            if (!response.ok || !data.ok) throw new Error(data.message || 'Không thể tìm ảnh Wikimedia.');
            renderCoverCandidates(data.results || []);
            if (coverStatus) coverStatus.textContent = data.message || `Tìm thấy ${(data.results || []).length} ứng viên cần kiểm tra.`;
        } catch (error) {
            if (coverStatus) coverStatus.textContent = error.message || 'Không thể tìm ảnh Wikimedia lúc này.';
            if (coverResults) coverResults.innerHTML = '<div class="col-12 small text-warning">Không thể tải danh sách ảnh. Hãy thử lại sau.</div>';
        }
    });
    coverResults?.addEventListener('click', (event) => {
        const button = event.target.closest('[data-cover-result]');
        if (!button) return;
        const item = coverCandidates[Number(button.dataset.coverResult)];
        if (!item) return;
        // A selected Wikimedia candidate is the user's explicit choice. Clear
        // a previously selected upload so PHP cannot receive both values and
        // silently prioritize a different image than the preview.
        const coverInput = $('coverInput');
        if (coverInput) coverInput.value = '';
        $(coverFields.url).value = item.image_url || '';
        $(coverFields.source).value = 'wikimedia';
        $(coverFields.source_url).value = item.source_url || '';
        $(coverFields.attribution).value = item.attribution || '';
        $(coverFields.license).value = item.license || '';
        $(coverFields.license_url).value = item.license_url || '';
        if ($('previewPlaceholder')) $('previewPlaceholder').innerHTML = `<img src="${esc(item.image_url)}" class="w-100 rounded" style="max-height:160px;object-fit:cover" alt="Ảnh Wikimedia được chọn"><p class="small text-muted mt-2 mb-0">${esc(item.license)} · ${esc(item.attribution)}</p>`;
        if (coverStatus) coverStatus.textContent = 'Đã chọn ảnh Wikimedia. Ảnh upload mới sẽ được ưu tiên nếu bạn chọn file.';
    });
    $('coverInput')?.addEventListener('change', (event) => {
        if (event.target.files?.length) {
            clearSelectedCover();
            if (coverStatus) coverStatus.textContent = 'Đã chọn ảnh upload; ảnh này được ưu tiên hơn ảnh Wikimedia.';
        }
    });

    $('btnAddActivity').addEventListener('click', function () {
        const item = readActivity();
        if (!item.title || !item.location) { alert('Vui lòng nhập tên và địa điểm hoạt động.'); return; }
        if ((item.start_time && !item.end_time) || (!item.start_time && item.end_time)) { alert('Hãy nhập đủ thời gian bắt đầu và kết thúc.'); return; }
        if (item.start_time && item.end_time) {
            if (!dateForDay(currentDay)) { alert('Hãy nhập ngày giờ khởi hành trước khi thêm giờ hoạt động.'); return; }
            if (!$('activityOvernight').checked && item.end_time <= item.start_time) { alert('Giờ kết thúc phải sau giờ bắt đầu. Nếu hoạt động qua đêm, hãy chọn “Kết thúc vào ngày hôm sau”.'); return; }
        }
        item.start_at = dateTimeFor(currentDay, item.start_time, false);
        item.end_at = dateTimeFor(currentDay, item.end_time, $('activityOvernight').checked);
        item.overnight = $('activityOvernight').checked;
        const list = currentList();
        const editing = this.dataset.editing;
        const editingIndex = Number(editing);
        if (editing !== undefined && editing !== '' && Number.isInteger(editingIndex) && editingIndex >= 0 && editingIndex < list.length) {
            list[editingIndex] = Object.assign({}, list[editingIndex], item);
        } else {
            list.push(item);
        }
        render();
        resetActivityForm();
    });

    $('activityList').addEventListener('click', (event) => {
        const edit = event.target.closest('[data-edit]');
        const remove = event.target.closest('[data-remove]');
        const up = event.target.closest('[data-up]');
        const down = event.target.closest('[data-down]');
        if (edit) fillActivity(currentList()[Number(edit.dataset.edit)], Number(edit.dataset.edit));
        if (remove) {
            const index = Number(remove.dataset.remove);
            const removed = currentList()[index];
            if (removed && removed.id) deletedActivityIds.push(Number(removed.id));
            currentList().splice(index, 1);
            render();
        }
        if (up) { const index = Number(up.dataset.up); if (index > 0) { const list = currentList(); [list[index - 1], list[index]] = [list[index], list[index - 1]]; render(); } }
        if (down) { const index = Number(down.dataset.down); const list = currentList(); if (index < list.length - 1) { [list[index + 1], list[index]] = [list[index], list[index + 1]]; render(); } }
    });

    form.addEventListener('submit', () => {
        dayDescriptions[dayKey()] = $('currentDayDescInput').value;
        Object.keys(activities).forEach((key) => {
            const dayNumber = Number(key.replace('day_', ''));
            activities[key].forEach((item) => {
                const startTime = item.start_time || timePart(item.start_at);
                const endTime = item.end_time || timePart(item.end_at);
                if (startTime && endTime && dateForDay(dayNumber)) {
                    item.start_time = startTime;
                    item.end_time = endTime;
                    item.start_at = dateTimeFor(dayNumber, startTime, false);
                    item.end_at = dateTimeFor(dayNumber, endTime, item.overnight === true);
                }
            });
        });
        const activitiesPayload = JSON.stringify(activities);
        $('activitiesJson').value = activitiesPayload;
        // Có thể kiểm tra chính xác payload trong DevTools trước khi submit.
        console.debug('[TripConnect timeline submit]', {
            departure_at: $('departureAt').value,
            activities_json: JSON.parse(activitiesPayload)
        });
        const deletedInput = $('deletedActivityIds');
        if (deletedInput) deletedInput.value = JSON.stringify([...new Set(deletedActivityIds)]);
        const container = $('dayDescInputsContainer');
        container.innerHTML = '';
        for (let day = 1; day <= maxDays; day++) {
            const input = document.createElement('input');
            input.type = 'hidden'; input.name = 'day_desc_' + day; input.value = dayDescriptions['day_' + day] || '';
            container.appendChild(input);
        }
    });

    updateDay();
}());
