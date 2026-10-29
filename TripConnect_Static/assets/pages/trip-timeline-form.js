(function () {
    'use strict';

    const form = document.getElementById('tripForm');
    if (!form) return;

    let currentDay = 1;
    let maxDays = parseInt(document.getElementById('tripDays').value || '1', 10);
    // PHP mã hóa dữ liệu rỗng thành []; cần object để JSON.stringify giữ khóa day_N.
    let activities = Object.assign({}, window.tripTimelineInitialActivities || {});
    let dayDescriptions = Object.assign({}, window.tripTimelineInitialDescriptions || {});
    let deletedActivityIds = window.tripTimelineDeletedActivityIds || [];
    let editingActivity = null;
    let locationRequest = 0;
    let selectedLocation = null;

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
    const tt=value=>window.tcT?window.tcT(value):value;
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
    function money(value) { return Number(value || 0).toLocaleString(localStorage.getItem('tripconnect_lang')==='en'?'en-US':'vi-VN', {maximumFractionDigits: 2}) + (localStorage.getItem('tripconnect_lang')==='en'?' VND':' đ'); }
    function activityTotal(item) {
        const participants = parseInt($('participantCount').value || '0', 10);
        if (!['string', 'number'].includes(typeof item.cost) || String(item.cost).trim() === ''
            || !Number.isFinite(Number(item.cost)) || Number(item.cost) < 0 || Number(item.cost) > 99999999.99
            || !['fixed', 'per_person'].includes(item.cost_scope)
            || (item.cost_scope === 'per_person' && (participants < 1 || participants > 65535))) return null;
        return Math.round(Number(item.cost) * 100) * (item.cost_scope === 'per_person' ? participants : 1);
    }
    function updateBudgetSummary() {
        let total = 0, incomplete = 0, count = 0;
        const categories = {};
        const days = {};
        Object.entries(activities).forEach(([day, list]) => {
            days[day] = {cents: 0, incomplete: 0};
            list.forEach(item => {
                count++;
                const key = item.category || 'other';
                categories[key] ||= {cents: 0, incomplete: 0};
                const cost = activityTotal(item);
                if (cost === null) { incomplete++; days[day].incomplete++; categories[key].incomplete++; }
                else { total += cost; days[day].cents += cost; categories[key].cents += cost; }
            });
        });
        const participants = parseInt($('participantCount').value || '0', 10);
        if ($('tripCalculatedTotal')) $('tripCalculatedTotal').textContent = (incomplete ? '≥ ' : '') + money(total / 100);
        if ($('tripAveragePerPerson')) $('tripAveragePerPerson').textContent = count > 0 && !incomplete && participants > 0 && participants <= 65535 ? money(total / 100 / participants) : tt('Chưa đủ dữ liệu');
        if ($('targetBudgetValue')) $('targetBudgetValue').textContent = money($('tripBudget').value);
        const comparison = $('budgetComparison');
        const budget = Math.round(Number($('tripBudget').value || 0) * 100);
        if (comparison) {
            comparison.className = 'small mt-2 ' + (!count ? 'text-muted' : (total > budget ? 'text-danger' : (incomplete ? 'text-warning' : 'text-success')));
            comparison.textContent = !count ? tt('Chưa có hoạt động để so sánh với ngân sách.') : incomplete
                ? `${incomplete} ${tt('khoản chưa xác định đủ chi phí/phạm vi hoặc thiếu số người.')}` + (total > budget ? ` ${tt('Phần đã xác định vượt ngân sách ít nhất:')} ${money((total - budget) / 100)}` : ` ${tt('Chưa thể so sánh đầy đủ với ngân sách.')}`)
                : `${tt(total > budget ? 'Vượt ngân sách dự kiến:' : 'Ngân sách dự kiến còn lại:')} ${money(Math.abs(budget - total) / 100)}`;
        }
        const labels = {food: 'Ăn uống', sightseeing: 'Tham quan', transport: 'Di chuyển', lodging: 'Lưu trú', rest: 'Nghỉ ngơi', free_time: 'Thời gian tự do', other: 'Khác'};
        const breakdown = (groups, label) => Object.entries(groups).map(([key, value]) => `<div class="d-flex justify-content-between gap-2 small"><span data-i18n-ui>${esc(label(key))}</span><span>${value.incomplete ? '≥ ' : ''}${money(value.cents / 100)}${value.incomplete ? ' · ' + value.incomplete + ' khoản chưa xác định' : ''}</span></div>`).join('');
        if ($('budgetByCategory')) $('budgetByCategory').innerHTML = breakdown(categories, key => labels[key] || 'Khác') || `<span class="small text-muted"><span data-i18n-ui>${tt('Chưa có chi phí hoạt động.')}</span></span>`;
        if ($('budgetByDay')) $('budgetByDay').innerHTML = breakdown(days, key => 'Ngày ' + key.replace('day_', ''));
    }

    function render() {
        const list = currentList();
        const target = $('activityList');
        if (!list.length) {
            target.innerHTML = `<p class="text-muted fst-italic"><span data-i18n-ui>${tt('Chưa có hoạt động nào cho ngày này.')}</span></p>`;
            $('currentDayTotal').textContent = '0 đ';
            updateBudgetSummary();
            return;
        }

        target.innerHTML = list.map((item, index) => {
            const scope = tt(item.cost_scope === 'per_person' ? 'theo người' : (item.cost_scope === 'fixed' ? 'cả nhóm/cố định' : 'chưa xác định phạm vi'));
            const time = item.start_time || item.end_time ? `${item.start_time || '--'} – ${item.end_time || '--'}${item.overnight ? ' (ngày hôm sau)' : ''}` : 'Chưa xác định thời gian';
            return `<div class="timeline-item border-start border-primary ps-3 mb-3">
                <div class="small text-muted">${esc(time)}</div>
                <strong>${esc(item.title)}</strong> <span data-i18n-ui class="badge bg-secondary">${esc(item.category)}</span>
                <div class="small text-muted"><i class="bi bi-geo-alt"></i> ${esc(item.location)} · ${item.cost === null || item.cost === undefined || item.cost === '' ? 'Chưa xác định chi phí' : money(item.cost)} (${scope})</div>
                ${item.description ? `<div class="small mt-1">${esc(item.description)}</div>` : ''}
                ${item.notes ? `<div class="small text-muted">Ghi chú: ${esc(item.notes)}</div>` : ''}
                <div class="mt-2"><button type="button" class="btn btn-sm btn-outline-secondary me-1" data-edit="${index}"><span data-i18n-ui>Sửa</span></button><button type="button" class="btn btn-sm btn-outline-secondary me-1" data-up="${index}" ${index === 0 ? 'disabled' : ''}><span data-i18n-ui>Lên</span></button><button type="button" class="btn btn-sm btn-outline-secondary me-1" data-down="${index}" ${index === currentList().length - 1 ? 'disabled' : ''}><span data-i18n-ui>Xuống</span></button><button type="button" class="btn btn-sm btn-outline-danger" data-remove="${index}"><span data-i18n-ui>Xóa</span></button></div>
            </div>`;
        }).join('');

        const costs = list.map(activityTotal);
        $('currentDayTotal').textContent = (costs.includes(null) ? '≥ ' : '') + money(costs.reduce((sum, value) => sum + (value || 0), 0) / 100) + (costs.includes(null) ? ' (chưa đầy đủ)' : '');
        updateBudgetSummary();
    }

    document.addEventListener('tripconnect:languagechange',()=>{render();if($('dayBadge'))$('dayBadge').textContent=tt('Ngày')+' '+currentDay;if($('dayDescLabel'))$('dayDescLabel').textContent=tt('Mô tả tổng quan Ngày '+currentDay);});
    function updateDay() {
        const dayDate = dateForDay(currentDay);
        $('dayBadge').textContent = tt('Ngày') + ' ' + currentDay + (dayDate ? ' - ' + dayDate.split('-').reverse().join('/') : '');
        $('dayDescLabel').textContent = 'Mô tả tổng quan Ngày ' + currentDay;
        $('currentDayDescInput').value = dayDescriptions[dayKey()] || '';
        render();
    }

    function resetActivityForm() {
        locationRequest++;
        selectedLocation = null;
        editingActivity = null;
        Object.values(fields).forEach((id) => { if ($(id)) $(id).value = ''; });
        if ($('locationSearchStatus')) $('locationSearchStatus').textContent = '';
        if ($('locationSearchResults')) $('locationSearchResults').innerHTML = '';
        showLocationDetails(null);
        $('activityOvernight').checked = false;
        $('overnightHint').textContent = 'Sang ngày kế tiếp';
        $('activityCategory').value = 'sightseeing';
        $('activityCostScope').value = 'fixed';
        $('btnAddActivity').dataset.editing = '';
        $('btnAddActivity').innerHTML = '<i class="bi bi-plus-lg"></i> Thêm hoạt động';
    }

    function readActivity() {
        const item = Object.fromEntries(Object.entries(fields).map(([key, id]) => [key, $(id).value.trim()]));
        if (selectedLocation && String(selectedLocation.id) === item.location_catalog_id) {
            ['latitude', 'longitude', 'location_source', 'location_source_ref', 'location_verified_at'].forEach(key => { item[key] = selectedLocation[key] ?? null; });
        }
        return item;
    }

    function fillActivity(item, index) {
        locationRequest++;
        if ($('locationSearchResults')) $('locationSearchResults').innerHTML = '';
        selectedLocation = item.location_catalog_id ? Object.assign({}, item, {id: item.location_catalog_id}) : null;
        editingActivity = item;
        Object.entries(fields).forEach(([key, id]) => { $(id).value = item[key] ?? ''; });
        showLocationDetails(item);
        $('activityOvernight').checked = item.overnight === true;
        $('overnightHint').textContent = item.overnight === true ? 'Sang Ngày ' + (currentDay + 1) : 'Sang ngày kế tiếp';
        $('btnAddActivity').dataset.editing = String(index);
        $('btnAddActivity').innerHTML = '<i class="bi bi-check-lg"></i> Cập nhật hoạt động';
    }

    let acceptedDates = {departure: $('departureAt').value, return: $('returnAt').value};
    function dateStatus(message, invalid = false) {
        const status = $('tripDateStatus');
        if (status) { status.textContent = message; status.className = 'form-text' + (invalid ? ' text-danger' : ''); }
    }
    function localTimestamp(value) {
        const match = String(value).match(/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})$/);
        if (!match) return NaN;
        const [, year, month, day, hour, minute] = match.map(Number);
        const date = new Date(Date.UTC(year, month - 1, day, hour, minute));
        return date.getUTCFullYear() === year && date.getUTCMonth() === month - 1 && date.getUTCDate() === day
            && hour < 24 && minute < 60 ? date.getTime() : NaN;
    }
    function removedDaysHaveData(nextDays) {
        return Object.entries(activities).some(([key, list]) => Number(key.replace('day_', '')) > nextDays && list.length)
            || Object.entries(dayDescriptions).some(([key, note]) => Number(key.replace('day_', '')) > nextDays && String(note).trim());
    }
    function setDayCount(count) {
        maxDays = count;
        $('tripDays').value = count;
        if (currentDay > maxDays) currentDay = maxDays;
        updateDay();
    }
    function syncTripDates(fromChange = false) {
        const departure = $('departureAt');
        const returning = $('returnAt');
        departure.setCustomValidity(''); returning.setCustomValidity('');
        if (fromChange && (editingActivity || $('activityName').value.trim() || $('activityPlace').value.trim())) {
            departure.value = acceptedDates.departure; returning.value = acceptedDates.return;
            dateStatus('Hãy thêm hoặc cập nhật hoạt động đang nhập trước khi đổi ngày chuyến đi.', true);
            updateDay(); return false;
        }
        $('tripDays').readOnly = false;
        if (!departure.value && !returning.value) {
            acceptedDates = {departure: '', return: ''};
            dateStatus('Chưa có ngày cụ thể: bạn có thể nhập số ngày để lưu nháp.');
            updateDay(); return true;
        }
        const start = localTimestamp(departure.value);
        const end = localTimestamp(returning.value);
        const invalid = !Number.isFinite(start) || !Number.isFinite(end) || end <= start;
        const count = invalid ? 0 : Math.floor(end / 86400000) - Math.floor(start / 86400000) + 1;
        if (invalid || count > 15) {
            const message = invalid ? 'Nhập đủ ngày giờ hợp lệ; trở về phải sau khởi hành.' : 'Chuyến đi chỉ hỗ trợ từ 1 đến 15 ngày.';
            returning.setCustomValidity(message);
            if (!departure.value || !returning.value) acceptedDates = {departure: departure.value, return: returning.value};
            dateStatus(message, true); updateDay(); return false;
        }
        if (removedDaysHaveData(count)) {
            departure.value = acceptedDates.departure; returning.value = acceptedDates.return;
            $('tripDays').value = maxDays;
            $('tripDays').readOnly = Number.isFinite(localTimestamp(departure.value)) && Number.isFinite(localTimestamp(returning.value));
            dateStatus('Không thể rút ngắn chuyến đi: ngày bị bỏ vẫn còn hoạt động hoặc ghi chú. Dữ liệu được giữ nguyên.', true);
            updateDay(); return false;
        }
        acceptedDates = {departure: departure.value, return: returning.value};
        $('tripDays').readOnly = true;
        setDayCount(count);
        dateStatus(`Tự tính ${count} ngày theo ngày khởi hành và trở về. Đổi hai ngày này để thay đổi số ngày.`);
        return true;
    }

    $('tripDays').addEventListener('input', function () {
        if (editingActivity || $('activityName').value.trim() || $('activityPlace').value.trim()) {
            this.value = maxDays;
            alert('Hãy thêm hoặc cập nhật hoạt động đang nhập trước khi đổi số ngày.');
            return;
        }
        if ($('departureAt').value && $('returnAt').value) { syncTripDates(); return; }
        const nextDays = Math.max(1, Math.min(15, parseInt(this.value || '1', 10)));
        if (removedDaysHaveData(nextDays)) {
            this.value = maxDays;
            alert('Các ngày bị bỏ vẫn còn hoạt động hoặc ghi chú. Hãy xử lý dữ liệu của những ngày đó trước khi giảm số ngày.');
            return;
        }
        setDayCount(nextDays);
    });
    $('participantCount').addEventListener('input', render);
    $('tripBudget').addEventListener('input', updateBudgetSummary);
    $('departureAt').addEventListener('change', () => syncTripDates(true));
    $('returnAt').addEventListener('change', () => syncTripDates(true));
    $('activityOvernight').addEventListener('change', () => {
        $('overnightHint').textContent = $('activityOvernight').checked ? 'Sang Ngày ' + (currentDay + 1) : 'Sang ngày kế tiếp';
    });
    function changeDay(offset) {
        const nextDay = currentDay + offset;
        if (nextDay < 1 || nextDay > maxDays) return;
        if (editingActivity || $('activityName').value.trim() || $('activityPlace').value.trim()) {
            alert('Hãy thêm hoặc cập nhật hoạt động đang nhập trước khi chuyển ngày.');
            return;
        }
        currentDay = nextDay;
        updateDay();
    }
    $('dayPlus').addEventListener('click', () => changeDay(1));
    $('dayMinus').addEventListener('click', () => changeDay(-1));
    $('currentDayDescInput').addEventListener('input', () => { dayDescriptions[dayKey()] = $('currentDayDescInput').value; });

    let lastLocationResults = [];
    let lastLocationSource = 'mysql';
    function showLocationDetails(item) {
        const details = $('activityLocationDetails');
        if (!details) return;
        if (!item) { details.textContent = ''; return; }
        const lat = item.latitude, lon = item.longitude;
        const hasPair = lat !== null && lat !== undefined && lat !== '' && lon !== null && lon !== undefined && lon !== ''
            && Number.isFinite(Number(lat)) && Number.isFinite(Number(lon)) && Math.abs(Number(lat)) <= 90 && Math.abs(Number(lon)) <= 180;
        details.textContent = (hasPair ? `Tọa độ: ${lat}, ${lon}. ` : 'Chưa có tọa độ hợp lệ. ')
            + (item.location_source === 'verified' ? 'Địa điểm đã xác minh.' : 'Nguồn: ' + (item.location_source || 'nhập thủ công') + '; chưa đánh dấu xác minh.')
            + (item.location_source_ref ? ' Mã nguồn: ' + item.location_source_ref : '');
    }
    function renderLocationResults(results, source) {
        lastLocationResults = results;
        lastLocationSource = source;
        const target = $('locationSearchResults');
        if (!target) return;
        if (!results.length) {
            target.innerHTML = '<div class="list-group-item text-muted"><span data-i18n-ui>Không tìm thấy địa điểm phù hợp.</span></div>';
            return;
        }
        target.innerHTML = results.map((item, index) => `<button type="button" class="list-group-item list-group-item-action" data-location-result="${index}"><strong>${esc(item.name || item.display_name || '')}</strong><br><span class="text-muted">${esc(item.address || '')}</span>${source === 'nominatim' ? '<br><small><span data-i18n-ui>Nguồn OpenStreetMap; chưa được xác minh hoàn toàn</span></small>' : ''}</button>`).join('');
    }
    async function searchLocations(external) {
        const request = ++locationRequest;
        lastLocationResults = [];
        if ($('locationSearchResults')) $('locationSearchResults').innerHTML = '';
        const query = $('activityPlace').value.trim();
        if (query.length < 2) {
            if ($('locationSearchStatus')) $('locationSearchStatus').textContent = 'Nhập ít nhất 2 ký tự.';
            return;
        }
        if ($('locationSearchStatus')) $('locationSearchStatus').textContent = external ? 'Đang tìm trên OpenStreetMap...' : 'Đang tìm trong dữ liệu TripConnect...';
        try {
            const response = await fetch(`location-search.php?q=${encodeURIComponent(query)}&external=${external ? '1' : '0'}`, { headers: { 'Accept': 'application/json' } });
            const responseText = await response.text();
            if (request !== locationRequest || query !== $('activityPlace').value.trim()) return;
            let data;
            try {
                data = JSON.parse(responseText);
            } catch (parseError) {
                throw new Error('Không thể tìm địa điểm. Vui lòng thử lại.');
            }
            if (!response.ok || !data.ok) throw new Error(data.message || 'Không thể tìm địa điểm.');
            if ($('locationSearchStatus')) $('locationSearchStatus').textContent = data.message || (external ? 'Kết quả OpenStreetMap.' : 'Kết quả tìm địa điểm.');
            renderLocationResults(data.results || [], data.source || 'mysql');
        } catch (error) {
            if (request !== locationRequest) return;
            const message = error && error.message ? error.message : 'Không thể kết nối dịch vụ tìm địa điểm.';
            if ($('locationSearchStatus')) $('locationSearchStatus').textContent = message;
            if ($('locationSearchResults')) $('locationSearchResults').innerHTML = `<div class="list-group-item list-group-item-warning">${esc(message)}</div>`;
        }
    }
    function applyCatalogLocation(item) {
        selectedLocation = item;
        $('activityPlace').value = item.name || item.display_name || '';
        $('activityLocationCatalogId').value = item.id || '';
        locationRequest++;
        showLocationDetails(item);
        if ($('locationSearchStatus')) $('locationSearchStatus').textContent = lastLocationSource === 'nominatim' ? 'Đã chọn kết quả bên ngoài; sẽ lưu nguồn OSM, chưa đánh dấu xác minh.' : 'Đã chọn địa điểm trong catalog.';
        if ($('locationSearchResults')) $('locationSearchResults').innerHTML = '';
    }
    $('btnSearchCatalog')?.addEventListener('click', () => searchLocations(false));
    $('btnSearchExternal')?.addEventListener('click', () => searchLocations(true));
    $('activityPlace')?.addEventListener('input', () => {
        selectedLocation = null;
        locationRequest++;
        $('activityLocationCatalogId').value = '';
        lastLocationResults = [];
        if ($('locationSearchResults')) $('locationSearchResults').innerHTML = '';
        showLocationDetails(null);
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
        const selectedRequest = ++locationRequest;
        const selectedPlace = $('activityPlace').value;
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
            if (selectedRequest !== locationRequest || selectedPlace !== $('activityPlace').value) return;
            if (!response.ok || !data.ok || !data.location) throw new Error(data.message || 'Không thể lưu địa điểm đã chọn.');
            lastLocationSource = 'nominatim';
            applyCatalogLocation(data.location);
        } catch (error) {
            if (selectedRequest !== locationRequest) return;
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
    function markSelectedCover(url) {
        coverResults?.querySelectorAll('[data-cover-result]').forEach((button) => {
            const selected = !!url && coverCandidates[Number(button.dataset.coverResult)]?.image_url === url;
            button.setAttribute('aria-pressed', String(selected));
            const action = button.querySelector('.cover-choice-action');
            if (action) action.textContent = selected ? '✓ Đã chọn ảnh này' : 'Chọn ảnh này';
        });
    }
    function previewCover(url, license = '', attribution = '') {
        if ($('previewPlaceholder')) $('previewPlaceholder').innerHTML = `<img src="${esc(url)}" class="w-100 rounded" style="max-height:160px;object-fit:cover" alt="Ảnh bìa đã chọn">${license || attribution ? `<p class="small text-muted mt-2 mb-0">${esc(license)} · ${esc(attribution)}</p>` : ''}`;
    }
    function clearSelectedCover() {
        Object.values(coverFields).forEach((id) => { if ($(id)) $(id).value = ''; });
        markSelectedCover('');
    }
    function renderCoverCandidates(results) {
        coverCandidates = results;
        if (!coverResults) return;
        if (!results.length) {
            coverResults.innerHTML = '<div class="col-12 small text-muted"><span data-i18n-ui>Chưa có ứng viên ảnh đủ tin cậy.</span></div>';
            return;
        }
        coverResults.innerHTML = results.map((item, index) => `<div class="col-12 col-sm-6"><button type="button" class="cover-choice h-100" data-cover-result="${index}" aria-pressed="false"><img src="${esc(item.image_url)}" alt="${esc(item.title)}" loading="lazy"><span class="cover-choice-info"><span class="d-block small fw-semibold">${esc(item.title)}</span><span class="d-block small text-muted">${esc(item.license)} · ${esc(item.attribution)}</span><span class="d-block small text-muted">${esc(item.match_note)}</span><span class="d-block small mt-2 cover-choice-action"><span data-i18n-ui>Chọn ảnh này</span></span></span></button></div>`).join('');
        markSelectedCover($(coverFields.url)?.value || '');
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
            if (coverResults) coverResults.innerHTML = '<div class="col-12 small text-warning"><span data-i18n-ui>Không thể tải danh sách ảnh. Hãy thử lại sau.</span></div>';
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
        markSelectedCover(item.image_url);
        previewCover(item.image_url, item.license, item.attribution);
        if (coverStatus) coverStatus.textContent = 'Đã chọn ảnh Wikimedia. Ảnh upload mới sẽ được ưu tiên nếu bạn chọn file.';
    });
    $('coverInput')?.addEventListener('change', (event) => {
        const input = event.target;
        const file = input.files?.[0];
        if (file) {
            clearSelectedCover();
            if (coverStatus) coverStatus.textContent = 'Đã chọn ảnh upload; ảnh này được ưu tiên hơn ảnh Wikimedia.';
            const reader = new FileReader();
            reader.onload = () => {
                // Không ghi đè preview nếu người dùng đã chuyển sang ảnh khác.
                if (input.files?.[0] === file) previewCover(reader.result);
            };
            reader.onerror = () => {
                if (input.files?.[0] === file && coverStatus) coverStatus.textContent = 'Không thể xem trước file ảnh. Vui lòng chọn lại ảnh.';
            };
            reader.readAsDataURL(file);
        }
    });
    if ($(coverFields.url)?.value) {
        previewCover($(coverFields.url).value, $(coverFields.license)?.value, $(coverFields.attribution)?.value);
        if (coverStatus) coverStatus.textContent = 'Ảnh Wikimedia đã chọn được giữ lại. Bạn có thể lưu lại hoặc chọn ảnh khác.';
    }

    $('btnAddActivity').addEventListener('click', function () {
        const item = readActivity();
        if (item.cost === '') { alert('Hãy nhập chi phí dự kiến. Nếu hoạt động miễn phí, nhập 0.'); return; }
        if (!item.title || !item.location) { alert('Vui lòng nhập tên và địa điểm hoạt động.'); return; }
        if (!['fixed', 'per_person'].includes(item.cost_scope)) { alert('Hãy chọn cách tính chi phí cho hoạt động: cả nhóm hoặc theo người.'); return; }
        if (item.cost !== '' && (!Number.isFinite(Number(item.cost)) || Number(item.cost) < 0 || Number(item.cost) > 99999999.99)) {
            alert('Chi phí hoạt động phải từ 0 đến 99.999.999,99 đ.'); return;
        }
        if ((item.start_time && !item.end_time) || (!item.start_time && item.end_time)) { alert('Hãy nhập đủ thời gian bắt đầu và kết thúc.'); return; }
        if (item.start_time && item.end_time) {
            if (!dateForDay(currentDay)) { alert('Hãy nhập ngày giờ khởi hành trước khi thêm giờ hoạt động.'); return; }
            if (!$('activityOvernight').checked && item.end_time <= item.start_time) { alert('Giờ kết thúc phải sau giờ bắt đầu. Nếu hoạt động qua đêm, hãy chọn “Kết thúc vào ngày hôm sau”.'); return; }
        }
        item.start_at = dateTimeFor(currentDay, item.start_time, false);
        item.end_at = dateTimeFor(currentDay, item.end_time, $('activityOvernight').checked);
        item.overnight = $('activityOvernight').checked;
        const list = currentList();
        const editingIndex = editingActivity ? list.indexOf(editingActivity) : -1;
        if (editingIndex >= 0) {
            if (!item.location_catalog_id && (String(editingActivity.location_catalog_id || '') !== String(item.location_catalog_id || '')
                || editingActivity.location !== item.location)) {
                Object.assign(item, { latitude: null, longitude: null, image: '', location_source: 'user', location_source_ref: '', location_verified_at: null });
            }
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
            if (removed === editingActivity) resetActivityForm();
            if (removed && removed.id) deletedActivityIds.push(Number(removed.id));
            currentList().splice(index, 1);
            render();
        }
        if (up) { const index = Number(up.dataset.up); if (index > 0) { const list = currentList(); [list[index - 1], list[index]] = [list[index], list[index - 1]]; render(); } }
        if (down) { const index = Number(down.dataset.down); const list = currentList(); if (index < list.length - 1) { [list[index + 1], list[index]] = [list[index], list[index + 1]]; render(); } }
    });

    form.addEventListener('submit', (event) => {
        if (editingActivity || $('activityName').value.trim() || $('activityPlace').value.trim()) {
            event.preventDefault();
            alert('Hãy thêm hoặc cập nhật hoạt động đang nhập trước khi lưu chuyến đi.');
            return;
        }
        dayDescriptions[dayKey()] = $('currentDayDescInput').value;
        if (!syncTripDates()) { event.preventDefault(); $('returnAt').reportValidity(); return; }
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

    syncTripDates();
}());
