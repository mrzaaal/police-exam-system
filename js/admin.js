/**
 * js/admin.js
 * Mengelola semua logika untuk halaman dashboard admin, termasuk navigasi tab,
 * paginasi, pencarian, analitik, dan semua interaksi modal.
 */

// --- DOM Elements ---
const resultsTableBody = $('#results-table-body');
const searchResultsInput = $('#search-results-input');
const resultsPaginationControls = $('#results-pagination-controls');
const totalParticipantsDisplay = $('#total-participants');
const passedCountDisplay = $('#passed-count');
const failedCountDisplay = $('#failed-count');
const averageScoreDisplay = $('#average-score');
const resultDetailsModal = $('#result-details-modal');
const resultDetailsContent = $('#result-details-content');
const resultDetailsCloseBtn = $('#result-details-close-btn');
const viewForensicsBtn = $('#view-forensics-btn');
const forensicsModal = $('#forensics-modal');
const forensicsTimelineContainer = $('#forensics-timeline-container');
const forensicsCloseBtn = $('#forensics-close-btn');

// --- Tab Elements ---
const tabButtons = $$('.tab-btn');
const tabContents = $$('.tab-content');

// --- State ---
let resultsCurrentPage = 1;
let resultsCurrentSearch = '';
let resultsSearchDebounceTimer;
let scoreChart = null;
let currentResultIdForForensics = null;

/**
 * Mengelola perpindahan antar tab di halaman admin.
 */
const handleTabSwitch = (event) => {
    event.preventDefault();
    const targetTab = event.currentTarget.getAttribute('href');

    // Hentikan polling monitoring jika meninggalkan tab pengawasan
    if (window.location.hash === '#pengawasan' && targetTab !== '#pengawasan') {
        stopMonitoring();
    }

    window.location.hash = targetTab; // Update URL hash

    // Atur style tombol tab
    tabButtons.forEach(btn => {
        btn.classList.remove('border-blue-500', 'text-blue-600');
        btn.classList.add('border-transparent', 'text-gray-500');
    });
    event.currentTarget.classList.add('border-blue-500', 'text-blue-600');
    event.currentTarget.classList.remove('border-transparent', 'text-gray-500');

    // Tampilkan konten tab yang sesuai
    tabContents.forEach(content => {
        content.classList.add('hidden');
    });
    $(targetTab.replace('#', '#content-')).classList.remove('hidden');

    // Inisialisasi konten tab yang aktif
    if (targetTab === '#manajemen-peserta') {
        initializeUserManagementPage();
    } else if (targetTab === '#manajemen-jadwal') {
        initializeSchedulePage();
    } else if (targetTab === '#paket-soal') {
        initializeQuestionSetPage();
    } else if (targetTab === '#bank-soal') {
        initializeBankSoalPage();
    } else if (targetTab === '#penilaian-esai') {
        initializeGradingPage();
    } else if (targetTab === '#pengawasan') {
        initializeMonitoringPage();
    } else if (targetTab === '#audit-trail') {
        initializeAuditTrailPage();
    }
};

/**
 * Mengambil data hasil ujian dari API dengan parameter paginasi dan pencarian.
 */
const fetchResultsData = async () => {
    try {
        const url = `api/results.php?page=${resultsCurrentPage}&limit=10&search=${encodeURIComponent(resultsCurrentSearch)}`;
        const response = await fetch(url);
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        const result = await response.json();
        
        if (result.success) {
            renderResultsTable(result.data);
            renderResultsPagination(result.pagination);
            // Catatan: Statistik dihitung ulang setiap kali data diambil.
            // Untuk akurasi penuh di semua data, ini memerlukan endpoint statistik terpisah.
            updateDashboardStats(result.pagination.total_records, result.data); 
        } else {
             resultsTableBody.innerHTML = `<tr><td colspan="6" class="text-center py-4 text-red-500">${result.message}</td></tr>`;
        }
    } catch (error) {
        console.error("Gagal mengambil data hasil ujian:", error);
        resultsTableBody.innerHTML = `<tr><td colspan="6" class="text-center py-4 text-red-500">Gagal memuat data.</td></tr>`;
    }
};

/**
 * Merender tabel hasil ujian.
 */
const renderResultsTable = (resultsData) => {
    resultsTableBody.innerHTML = '';
    if (!resultsData || resultsData.length === 0) {
        resultsTableBody.innerHTML = `<tr><td colspan="6" class="text-center py-4">Tidak ada hasil ujian yang ditemukan.</td></tr>`;
        return;
    }
    resultsData.forEach(result => {
        const statusClass = result.status === 'Lulus' ? 'status-passed' : 'status-failed';
        let gradingIndicator = '';
        if (result.grading_status === 'pending-review') {
            gradingIndicator = `<span class="ml-2 text-xs text-yellow-600 bg-yellow-100 px-2 py-1 rounded-full">Review Esai</span>`;
        } else if (result.grading_status === 'fully-graded') {
             gradingIndicator = `<span class="ml-2 text-xs text-green-600 bg-green-100 px-2 py-1 rounded-full">Final</span>`;
        }

        const row = `<tr>
                        <td class="px-6 py-4 font-medium text-gray-900">${result.name} (${result.username})</td>
                        <td class="px-6 py-4 font-semibold">${parseFloat(result.score).toFixed(2)}</td>
                        <td class="px-6 py-4 text-center">${result.attempt_number}</td>
                        <td class="px-6 py-4"><span class="status-badge ${statusClass}">${result.status}</span>${gradingIndicator}</td>
                        <td class="px-6 py-4">${result.completed_at}</td>
                        <td class="px-6 py-4">
                            <button class="action-btn view view-details-btn" data-id="${result.id}">Detail</button>
                            <button class="action-btn delete reset-attempt-btn" data-id="${result.id}" data-name="${result.name}">Reset Percobaan</button>
                        </td>
                    </tr>`;
        resultsTableBody.innerHTML += row;
    });
};

/**
 * Merender kontrol paginasi untuk hasil ujian.
 */
const renderResultsPagination = (pagination) => {
    const { current_page, total_pages, total_records } = pagination;
    resultsPaginationControls.innerHTML = '';
    if (total_pages <= 1) {
        resultsPaginationControls.innerHTML = `<div class="text-sm text-gray-500">Total ${total_records} hasil</div>`;
        return;
    }

    let html = `<div class="text-sm text-gray-500">Total ${total_records} hasil</div>`;
    html += `<div class="flex items-center"><button class="pagination-btn ${current_page === 1 ? 'opacity-50' : ''}" data-page="${current_page - 1}" ${current_page === 1 ? 'disabled' : ''}>Sebelumnya</button><span class="px-4">Halaman ${current_page} dari ${total_pages}</span><button class="pagination-btn ${current_page === total_pages ? 'opacity-50' : ''}" data-page="${current_page + 1}" ${current_page === total_pages ? 'disabled' : ''}>Berikutnya</button></div>`;
    resultsPaginationControls.innerHTML = html;
};

/**
 * Menangani input pencarian hasil ujian.
 */
const handleResultsSearchInput = (event) => {
    clearTimeout(resultsSearchDebounceTimer);
    resultsSearchDebounceTimer = setTimeout(() => {
        resultsCurrentSearch = event.target.value;
        resultsCurrentPage = 1;
        fetchResultsData();
    }, 500);
};

/**
 * Menangani klik pada tombol paginasi hasil ujian.
 */
const handleResultsPaginationClick = (event) => {
    const target = event.target.closest('.pagination-btn');
    if (target && !target.disabled) {
        resultsCurrentPage = parseInt(target.dataset.page);
        fetchResultsData();
    }
};

/**
 * Menangani permintaan reset percobaan ujian.
 */
const handleResetAttempt = async (resultId, participantName) => {
    const confirmed = await showConfirmModal('Konfirmasi Reset', `Anda yakin ingin mereset percobaan untuk ${participantName}? Ini akan menghapus hasil ini secara permanen.`);
    if (!confirmed) return;

    try {
        const response = await fetch('api/reset_attempt.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ result_id: resultId })
        });
        const result = await response.json();
        if (result.success) {
            showToast(result.message, 'success');
            fetchResultsData();
        } else {
            showToast(result.message, 'error');
        }
    } catch (error) {
        showToast('Terjadi kesalahan koneksi.', 'error');
    }
};

/**
 * Menghitung dan menampilkan statistik ringkasan.
 */
const updateDashboardStats = (total_records, page_data) => {
    // Note: This is an approximation. For full accuracy, a separate API endpoint for stats is better.
    totalParticipantsDisplay.textContent = total_records;
    if (!page_data) return;
    const passedCount = page_data.filter(r => r.status === 'Lulus').length;
    const failedCount = page_data.filter(r => r.status === 'Gagal').length;
    const totalScore = page_data.reduce((sum, r) => sum + parseFloat(r.score), 0);
    const averageScore = page_data.length > 0 ? (totalScore / page_data.length).toFixed(1) : 0;
    passedCountDisplay.textContent = passedCount; // Approximation
    failedCountDisplay.textContent = failedCount; // Approximation
    averageScoreDisplay.textContent = averageScore; // Approximation
};

/**
 * Mengambil data analitik dan merender grafik.
 */
const renderAnalytics = async () => {
    try {
        const response = await fetch('api/get_analytics_data.php');
        const result = await response.json();
        if (result.success) {
            renderScoreDistributionChart(result.scoreDistribution);
        }
    } catch (error) {
        console.error('Gagal memuat data analitik:', error);
    }
};

/**
 * Merender grafik distribusi skor.
 */
const renderScoreDistributionChart = (chartData) => {
    const ctx = document.getElementById('scoreDistributionChart').getContext('2d');
    if (scoreChart) scoreChart.destroy();
    scoreChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: chartData.labels,
            datasets: [{
                label: 'Jumlah Peserta',
                data: chartData.data,
                backgroundColor: 'rgba(59, 130, 246, 0.5)',
                borderColor: 'rgba(59, 130, 246, 1)',
                borderWidth: 1
            }]
        },
        options: { scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }, plugins: { legend: { display: false } } }
    });
};

/**
 * Membuka modal dan memuat detail hasil ujian.
 */
const showResultDetails = async (resultId) => {
    currentResultIdForForensics = resultId;
    resultDetailsContent.innerHTML = `<p>Memuat detail...</p>`;
    toggleElementVisibility(resultDetailsModal, true);
    try {
        const response = await fetch(`api/get_result_details.php?id=${resultId}`);
        const result = await response.json();
        if (result.success) {
            renderResultDetails(result.data);
        } else {
            resultDetailsContent.innerHTML = `<p class="text-red-500">${result.message}</p>`;
        }
    } catch (error) {
        resultDetailsContent.innerHTML = `<p class="text-red-500">Gagal memuat detail.</p>`;
    }
};

/**
 * Merender konten detail hasil ujian ke dalam modal.
 */
const renderResultDetails = (data) => {
    const { main, essays } = data;
    let essaysHTML = '<h3>Tidak ada jawaban esai.</h3>';
    if (essays.length > 0) {
        essaysHTML = essays.map(essay => `<div class="mt-4 border-t pt-4"><p class="font-semibold">Pertanyaan:</p><p class="text-gray-800 bg-gray-50 p-3 rounded-md mt-1">${essay.question_text}</p><p class="font-semibold mt-3">Jawaban Peserta:</p><div class="text-gray-800 bg-gray-50 p-3 rounded-md mt-1 whitespace-pre-wrap">${essay.answer_text || '<i>Tidak dijawab</i>'}</div><p class="text-sm mt-3">Status: <span class="font-semibold">${essay.status === 'graded' ? 'Sudah Dinilai' : 'Menunggu Penilaian'}</span> | Skor Esai: <span class="font-semibold">${essay.score || '-'}</span></p></div>`).join('');
    }
    resultDetailsContent.innerHTML = `<div class="grid grid-cols-2 gap-4 mb-6"><div><p class="text-sm text-gray-500">Peserta</p><p class="font-semibold">${main.name} (${main.username})</p></div><div><p class="text-sm text-gray-500">Waktu Selesai</p><p class="font-semibold">${main.completed_at}</p></div><div><p class="text-sm text-gray-500">Skor Akhir</p><p class="font-bold text-2xl text-blue-600">${parseFloat(main.score).toFixed(2)}</p></div><div><p class="text-sm text-gray-500">Status</p><p class="font-semibold">${main.status} (${main.grading_status})</p></div></div><h3 class="text-xl font-bold border-b pb-2">Rincian Jawaban Esai</h3>${essaysHTML}`;
};

const closeResultDetailsModal = () => toggleElementVisibility(resultDetailsModal, false);

/**
 * Membuka modal forensik dan memuat data timeline.
 */
const showForensicsTimeline = async () => {
    if (!currentResultIdForForensics) return;
    
    forensicsTimelineContainer.innerHTML = `<p>Memuat timeline...</p>`;
    toggleElementVisibility(forensicsModal, true);

    try {
        const response = await fetch(`api/get_exam_forensics.php?result_id=${currentResultIdForForensics}`);
        const result = await response.json();

        if (result.success) {
            renderForensicsTimeline(result.data);
        } else {
            forensicsTimelineContainer.innerHTML = `<p class="text-red-500">${result.message}</p>`;
        }
    } catch (error) {
        console.error('Error fetching forensics:', error);
        forensicsTimelineContainer.innerHTML = `<p class="text-red-500">Gagal memuat data timeline.</p>`;
    }
};

/**
 * Merender data timeline ke dalam kontainer.
 */
const renderForensicsTimeline = (events) => {
    if (events.length === 0) {
        forensicsTimelineContainer.innerHTML = `<p>Tidak ada aktivitas yang tercatat untuk sesi ini.</p>`;
        return;
    }

    let timelineHTML = events.map(event => {
        let detailsText = '';
        let icon = 'ℹ️';
        let color = 'text-gray-600';

        try {
            const details = JSON.parse(event.details || '{}');
            const qIndex = details.questionIndex !== undefined ? `Soal #${details.questionIndex + 1}` : '';
            
            switch(event.event_type) {
                case 'QUESTION_VIEWED':
                    icon = '👁️'; detailsText = `Melihat ${qIndex}`; break;
                case 'ANSWER_CHANGED':
                    icon = '✏️'; detailsText = `Mengubah jawaban ${qIndex}`; break;
                case 'QUESTION_FLAGGED':
                    icon = '🚩'; detailsText = `Menandai ${qIndex} (ragu-ragu)`; break;
                case 'QUESTION_UNFLAGGED':
                    icon = '🏳️'; detailsText = `Membatalkan tanda ${qIndex}`; break;
                default: // Pelanggaran
                    icon = '⚠️'; color = 'text-red-600 font-semibold'; detailsText = event.event_type; break;
            }
        } catch (e) {
            detailsText = event.details;
        }

        return `<div class="flex items-start space-x-3 py-2 border-b">
                    <span class="text-lg">${icon}</span>
                    <div>
                        <p class="text-sm ${color}">${detailsText}</p>
                        <p class="text-xs text-gray-400">${event.event_timestamp}</p>
                    </div>
                </div>`;
    }).join('');

    forensicsTimelineContainer.innerHTML = timelineHTML;
};

const closeForensicsModal = () => {
    toggleElementVisibility(forensicsModal, false);
};


/**
 * Inisialisasi halaman admin.
 */
const initializeAdminPage = async () => {
    resultsCurrentPage = 1;
    resultsCurrentSearch = '';
    searchResultsInput.value = '';
    
    await fetchResultsData();
    await renderAnalytics();

    tabButtons.forEach(btn => btn.addEventListener('click', handleTabSwitch));
    const currentHash = window.location.hash || '#hasil-ujian';
    const activeTabButton = $(`a[href="${currentHash}"]`);
    if (activeTabButton) activeTabButton.click();
};

// --- Event Listeners ---
resultDetailsCloseBtn.addEventListener('click', closeResultDetailsModal);
searchResultsInput.addEventListener('input', handleResultsSearchInput);
resultsPaginationControls.addEventListener('click', handleResultsPaginationClick);
resultsTableBody.addEventListener('click', (event) => {
    if (event.target.classList.contains('view-details-btn')) {
        event.preventDefault();
        showResultDetails(event.target.dataset.id);
    }
    if (event.target.classList.contains('reset-attempt-btn')) {
        handleResetAttempt(event.target.dataset.id, event.target.dataset.name);
    }
});
viewForensicsBtn.addEventListener('click', showForensicsTimeline);
forensicsCloseBtn.addEventListener('click', closeForensicsModal);

console.log("admin.js loaded (final complete version).");
