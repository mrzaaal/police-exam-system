/**
 * js/monitoring.js
 * Mengelola logika untuk tab Pengawasan Live di dasbor admin.
 * Fitur: Polling Real-time, Visualisasi Risiko, Aksi Pengawas.
 */

// --- DOM Elements ---
const monitoringTableBody = $('#monitoring-table-body');

// --- State ---
let monitoringInterval;

/**
 * Mengambil data sesi aktif dari API.
 */
const fetchMonitoringData = async () => {
    try {
        const response = await fetch('api/monitoring.php');
        const result = await response.json();
        if (result.success) {
            renderMonitoringTable(result.data);
        } else {
            console.error('Gagal mengambil data pengawasan:', result.message);
            stopMonitoring(); // Hentikan jika ada error, misal sesi admin habis
        }
    } catch (error) {
        console.error('Error fetching monitoring data:', error);
        stopMonitoring();
    }
};

/**
 * Merender data sesi aktif ke dalam tabel dengan kode warna risiko.
 * @param {Array} sessions - Array objek sesi aktif.
 */
const renderMonitoringTable = (sessions) => {
    monitoringTableBody.innerHTML = '';
    if (sessions.length === 0) {
        monitoringTableBody.innerHTML = `<tr><td colspan="6" class="text-center py-4">Tidak ada peserta yang sedang ujian.</td></tr>`;
        return;
    }

    sessions.forEach(session => {
        let riskClass = 'text-gray-700';
        let riskBg = 'bg-gray-100';

        switch (session.risk_level) {
            case 'Tinggi':
                riskClass = 'text-red-800 font-bold';
                riskBg = 'bg-red-100';
                break;
            case 'Sedang':
                riskClass = 'text-yellow-800 font-semibold';
                riskBg = 'bg-yellow-100';
                break;
            case 'Rendah':
                riskClass = 'text-blue-800';
                riskBg = 'bg-blue-100';
                break;
            case 'Aman':
                riskClass = 'text-green-800';
                riskBg = 'bg-green-100';
                break;
        }

        const row = `
            <tr>
                <td class="px-6 py-4 font-medium">${session.username}</td>
                <td class="px-6 py-4">${session.start_time}</td>
                <td class="px-6 py-4">${session.last_update}</td>
                <td class="px-6 py-4">${session.progress}</td>
                <td class="px-6 py-4">
                    <span class="px-2 py-1 text-xs rounded-full ${riskBg} ${riskClass}">
                        ${session.risk_level} (Skor: ${session.risk_score || 0})
                    </span>
                </td>
                <td class="px-6 py-4">
                    <button class="action-btn delete force-finish-btn" data-user-id="${session.user_id}" data-username="${session.username}">
                        Paksa Selesai
                    </button>
                </td>
            </tr>
        `;
        monitoringTableBody.innerHTML += row;
    });
};

/**
 * Menangani aksi paksa selesaikan ujian.
 * @param {number} userId - ID pengguna yang ujiannya akan dihentikan.
 * @param {string} username - Username pengguna untuk pesan konfirmasi.
 */
const handleForceFinish = async (userId, username) => {
    const confirmed = await showConfirmModal(
        'Konfirmasi Paksa Selesai',
        `Apakah Anda yakin ingin memaksa ujian untuk peserta "${username}" selesai? Progres terakhir akan dinilai.`,
        { okClass: 'bg-red-600 text-white hover:bg-red-700' }
    );
    if (!confirmed) return;

    try {
        const response = await fetch('api/proctor_action.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ user_id: userId, action: 'force_finish' })
        });
        const result = await response.json();
        if (result.success) {
            showToast(result.message, 'success');
            fetchMonitoringData(); // Muat ulang data untuk menghapus peserta dari daftar aktif
        } else {
            showToast(result.message, 'error');
        }
    } catch (error) {
        console.error('Error saat memaksa ujian selesai:', error);
        showToast('Terjadi kesalahan koneksi.', 'error');
    }
};

/**
 * Memulai polling data pengawasan secara periodik.
 */
const startMonitoring = () => {
    if (monitoringInterval) clearInterval(monitoringInterval);
    fetchMonitoringData();
    monitoringInterval = setInterval(fetchMonitoringData, 10000); 
};

/**
 * Menghentikan polling data.
 */
const stopMonitoring = () => {
    if (monitoringInterval) {
        clearInterval(monitoringInterval);
        monitoringInterval = null;
    }
};

/**
 * Inisialisasi halaman pengawasan (dipanggil dari admin.js).
 */
const initializeMonitoringPage = () => {
    startMonitoring();
};

// Event delegation untuk tombol "Paksa Selesai"
monitoringTableBody.addEventListener('click', (event) => {
    if (event.target.classList.contains('force-finish-btn')) {
        const userId = event.target.dataset.userId;
        const username = event.target.dataset.username;
        handleForceFinish(userId, username);
    }
});
