/**
 * js/schedules.js
 * Mengelola logika untuk tab Manajemen Jadwal di dasbor admin.
 * Fitur: CRUD Jadwal, Integrasi Paket Soal, Rilis Hasil, Aksi Analisis.
 */

// --- DOM Elements ---
const schedulesTableBody = $('#schedules-table-body');
const addScheduleBtn = $('#add-schedule-btn');
const scheduleModal = $('#schedule-modal');
const scheduleModalTitle = $('#schedule-modal-title');
const scheduleForm = $('#schedule-form');
const scheduleModalCancelBtn = $('#schedule-modal-cancel');
const scheduleIdInput = $('#schedule-id');
const scheduleQuestionSetSelect = $('#schedule_question_set');
const analysisModal = $('#analysis-modal');
const analysisModalCloseBtn = $('#analysis-modal-close-btn');
const analysisResultsContainer = $('#analysis-results-container');

// --- State ---
let allSchedules = [];
let allQuestionSetsForSelection = [];

/**
 * Mengambil semua jadwal dari API.
 */
const fetchSchedules = async () => {
    try {
        const response = await fetch('api/schedules_crud.php');
        const result = await response.json();
        if (result.success) {
            allSchedules = result.data;
            renderSchedulesTable();
        }
    } catch (error) { console.error('Error fetching schedules:', error); }
};

/**
 * Merender tabel jadwal dengan semua tombol aksi.
 */
const renderSchedulesTable = () => {
    schedulesTableBody.innerHTML = '';
    if (allSchedules.length === 0) {
        schedulesTableBody.innerHTML = `<tr><td colspan="9" class="text-center py-4">Belum ada jadwal yang dibuat.</td></tr>`;
        return;
    }
    allSchedules.forEach(s => {
        const statusClass = s.is_active == 1 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
        const statusText = s.is_active == 1 ? 'Aktif' : 'Nonaktif';
        const isReleased = s.results_released == 1;

        let analysisButtonHTML = '';
        if (s.analysis_status === 'completed') {
            analysisButtonHTML = `<button class="action-btn view view-analysis-btn" data-id="${s.id}">Lihat Hasil</button>`;
        } else {
            const isExamOver = new Date(s.end_time) < new Date();
            if (isExamOver) {
                analysisButtonHTML = `<button class="action-btn delete run-analysis-btn" data-id="${s.id}">Jalankan Analisis</button>`;
            } else {
                analysisButtonHTML = `<span class="text-xs text-gray-400">Menunggu Selesai</span>`;
            }
        }

        const row = `
            <tr>
                <td class="px-6 py-4 font-medium">${s.title}</td>
                <td class="px-6 py-4">${s.start_time}</td>
                <td class="px-6 py-4">${s.end_time}</td>
                <td class="px-6 py-4">${s.duration_minutes}</td>
                <td class="px-6 py-4">${s.question_count}</td>
                <td class="px-6 py-4"><span class="px-2 py-1 text-xs font-semibold rounded-full ${statusClass}">${statusText}</span></td>
                <td class="px-6 py-4">
                    <button role="switch" aria-checked="${isReleased}" data-id="${s.id}" class="release-toggle relative inline-flex h-6 w-11 items-center rounded-full ${isReleased ? 'bg-blue-600' : 'bg-gray-200'}">
                        <span class="inline-block h-4 w-4 transform rounded-full bg-white transition" style="transform: ${isReleased ? 'translateX(20px)' : 'translateX(0)'}"></span>
                    </button>
                </td>
                <td class="px-6 py-4">${analysisButtonHTML}</td>
                <td class="px-6 py-4"><button class="action-btn view edit-schedule-btn" data-id="${s.id}">Edit</button></td>
            </tr>
        `;
        schedulesTableBody.innerHTML += row;
    });
};

/**
 * Membuka modal untuk membuat atau mengedit jadwal.
 */
const openScheduleModal = async (schedule = null) => {
    scheduleForm.reset();
    const isEditing = schedule !== null;
    scheduleModalTitle.textContent = isEditing ? 'Edit Jadwal Ujian' : 'Buat Jadwal Baru';
    
    scheduleQuestionSetSelect.innerHTML = '<option value="">Memuat paket soal...</option>';
    try {
        const response = await fetch('api/question_sets_crud.php');
        const result = await response.json();
        if (result.success && result.data.length > 0) {
            allQuestionSetsForSelection = result.data;
            let optionsHTML = '<option value="" disabled selected>-- Pilih Paket Soal --</option>';
            optionsHTML += allQuestionSetsForSelection.map(set => `<option value="${set.id}">${set.name} (${set.question_count} soal)</option>`).join('');
            scheduleQuestionSetSelect.innerHTML = optionsHTML;
        } else {
            scheduleQuestionSetSelect.innerHTML = '<option value="">Tidak ada paket soal tersedia</option>';
        }
    } catch (error) {
        scheduleQuestionSetSelect.innerHTML = '<option value="">Gagal memuat</option>';
    }

    if (isEditing) {
        scheduleIdInput.value = schedule.id;
        $('#schedule_title').value = schedule.title;
        $('#schedule_duration').value = schedule.duration_minutes;
        $('#schedule_max_attempts').value = schedule.max_attempts;
        $('#schedule_start_time').value = schedule.start_time.replace(' ', 'T');
        $('#schedule_end_time').value = schedule.end_time.replace(' ', 'T');
        scheduleQuestionSetSelect.disabled = true; // Tidak bisa mengubah paket soal saat edit
    } else {
        scheduleIdInput.value = '';
        scheduleQuestionSetSelect.disabled = false;
    }

    toggleElementVisibility(scheduleModal, true);
};

const closeScheduleModal = () => toggleElementVisibility(scheduleModal, false);

/**
 * Menangani submit form jadwal (Create & Update).
 */
const handleScheduleFormSubmit = async (event) => {
    event.preventDefault();
    const formData = new FormData(scheduleForm);
    const id = formData.get('id');
    const isUpdating = !!id;

    const scheduleData = {
        title: formData.get('title'),
        question_set_id: formData.get('question_set_id'),
        start_time: formData.get('start_time'),
        end_time: formData.get('end_time'),
        duration_minutes: parseInt(formData.get('duration_minutes')),
        max_attempts: parseInt(formData.get('max_attempts')),
        is_active: true
    };

    const url = isUpdating ? `api/schedules_crud.php?id=${id}` : 'api/schedules_crud.php';
    const method = isUpdating ? 'PUT' : 'POST';

    try {
        const response = await fetch(url, {
            method: method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(scheduleData)
        });
        const result = await response.json();
        if (result.success) {
            closeScheduleModal();
            fetchSchedules();
            showToast(result.message, 'success');
        } else {
            showToast(`Gagal: ${result.message}`, 'error');
        }
    } catch (error) {
        showToast('Terjadi kesalahan koneksi.', 'error');
    }
};

/**
 * Menangani klik pada toggle rilis hasil.
 */
const handleReleaseToggle = async (scheduleId, newStatus) => {
    const actionVerb = newStatus ? 'MERILIS' : 'MENARIK';
    const confirmed = await showConfirmModal('Konfirmasi Rilis Hasil', `Anda yakin ingin ${actionVerb} hasil untuk jadwal ini?`);
    if (!confirmed) return;

    try {
        const response = await fetch('api/release_results.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ schedule_id: scheduleId, release_status: newStatus })
        });
        const result = await response.json();
        if (result.success) {
            fetchSchedules();
            showToast(result.message, 'success');
        } else {
            showToast(`Gagal: ${result.message}`, 'error');
        }
    } catch (error) {
        showToast('Terjadi kesalahan koneksi.', 'error');
    }
};

const handleRunAnalysis = async (scheduleId) => {
    const confirmed = await showConfirmModal('Konfirmasi Analisis', `Anda yakin ingin menjalankan analisis butir soal untuk jadwal ini?`);
    if (!confirmed) return;
    try {
        const response = await fetch('api/run_item_analysis.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ schedule_id: scheduleId }) });
        const result = await response.json();
        showToast(result.message, result.success ? 'success' : 'error');
        if (result.success) fetchSchedules();
    } catch (error) { showToast('Terjadi kesalahan koneksi.', 'error'); }
};

const handleViewAnalysis = async (scheduleId) => {
    analysisResultsContainer.innerHTML = `<p>Memuat hasil analisis...</p>`;
    toggleElementVisibility(analysisModal, true);
    try {
        const response = await fetch(`api/get_item_analysis.php?schedule_id=${scheduleId}`);
        const result = await response.json();
        if (result.success) {
            renderAnalysisResults(result.data);
        } else {
            analysisResultsContainer.innerHTML = `<p class="text-red-500">${result.message}</p>`;
        }
    } catch (error) { analysisResultsContainer.innerHTML = `<p class="text-red-500">Gagal memuat data.</p>`; }
};

const renderAnalysisResults = (analysisData) => {
    if (analysisData.length === 0) {
        analysisResultsContainer.innerHTML = `<p>Belum ada data analisis.</p>`;
        return;
    }
    let tableHTML = `<table class="w-full text-sm text-left"><thead class="text-xs text-gray-700 uppercase bg-gray-100"><tr><th class="px-4 py-2">Soal</th><th class="px-4 py-2">Indeks Kesulitan</th><th class="px-4 py-2">Indeks Pembeda</th><th class="px-4 py-2">Interpretasi</th></tr></thead><tbody>`;
    analysisData.forEach(item => {
        const diff = parseFloat(item.difficulty_index).toFixed(2);
        const disc = parseFloat(item.discrimination_index).toFixed(2);
        let interpretation = 'Baik';
        if (diff < 0.3 || diff > 0.9) interpretation = 'Perlu Revisi (Kesulitan)';
        if (disc < 0.2) interpretation = 'Perlu Revisi (Pembeda)';
        tableHTML += `<tr class="border-b"><td class="px-4 py-2">${item.question_text.substring(0, 80)}...</td><td class="px-4 py-2">${diff}</td><td class="px-4 py-2">${disc}</td><td class="px-4 py-2 font-semibold">${interpretation}</td></tr>`;
    });
    tableHTML += `</tbody></table>`;
    analysisResultsContainer.innerHTML = tableHTML;
};

const closeAnalysisModal = () => toggleElementVisibility(analysisModal, false);

/**
 * Inisialisasi halaman manajemen jadwal.
 */
const initializeSchedulePage = () => {
    fetchSchedules();
};

// --- Event Listeners ---
addScheduleBtn.addEventListener('click', () => openScheduleModal());
scheduleModalCancelBtn.addEventListener('click', closeScheduleModal);
scheduleForm.addEventListener('submit', handleScheduleFormSubmit);
analysisModalCloseBtn.addEventListener('click', closeAnalysisModal);

schedulesTableBody.addEventListener('click', (event) => {
    const target = event.target;
    if (target.closest('.edit-schedule-btn')) {
        const id = target.closest('.edit-schedule-btn').dataset.id;
        const scheduleToEdit = allSchedules.find(s => s.id == id);
        if (scheduleToEdit) openScheduleModal(scheduleToEdit);
    }
    if (target.closest('.release-toggle')) {
        const toggle = target.closest('.release-toggle');
        const scheduleId = toggle.dataset.id;
        const newStatus = !(toggle.getAttribute('aria-checked') === 'true');
        handleReleaseToggle(scheduleId, newStatus);
    }
    if (target.classList.contains('run-analysis-btn')) {
        handleRunAnalysis(target.dataset.id);
    }
    if (target.classList.contains('view-analysis-btn')) {
        handleViewAnalysis(target.dataset.id);
    }
});
