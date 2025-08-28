/**
 * js/anti-cheat.js
 * Mengimplementasikan fitur-fitur untuk mencegah kecurangan dan melaporkan
 * pelanggaran ke API monitoring. Menggunakan notifikasi modern.
 */

let violationCount = 0;
const MAX_VIOLATIONS = 5; // Batas maksimal pelanggaran sebelum tindakan

/**
 * Mengirim log pelanggaran ke server.
 * @param {string} eventType - Jenis pelanggaran.
 * @param {string|null} details - Detail tambahan tentang pelanggaran.
 */
const reportViolation = async (eventType, details = null) => {
    console.warn(`Pelanggaran terdeteksi: ${eventType}. Total: ${violationCount + 1}`);
    try {
        await fetch('api/monitoring.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ eventType, details })
        });
    } catch (error) {
        console.error('Gagal melaporkan pelanggaran:', error);
    }
};

/**
 * Menangani peringatan dan tindakan jika batas terlampaui.
 * @param {string} type - Jenis pelanggaran.
 */
const handleViolation = async (type) => {
    violationCount++;
    const message = `Peringatan ${violationCount}/${MAX_VIOLATIONS}: Terdeteksi tindakan ${type}. Harap tetap fokus.`;
    
    reportViolation(type);

    // Ganti alert dengan toast peringatan
    showToast(message, 'warning');

    if (violationCount >= MAX_VIOLATIONS) {
        // Gunakan modal konfirmasi sebelum mengakhiri ujian
        const confirmed = await showConfirmModal(
            'Batas Pelanggaran Tercapai',
            'Anda telah melebihi batas maksimal peringatan. Ujian akan dihentikan.',
            { okText: 'Mengerti', okClass: 'bg-red-600 text-white' }
        );
        // Akhiri ujian baik dikonfirmasi atau tidak (setelah modal ditutup)
        finishExam(); // Panggil fungsi dari exam.js
    }
};

/**
 * Mencegah pengguna untuk meninggalkan tab/jendela ujian.
 */
const handleVisibilityChange = () => {
    if (document.hidden) {
        handleViolation("Meninggalkan Tab Ujian");
    }
};

/**
 * Mencegah klik kanan untuk menghindari menu konteks.
 */
const preventContextMenu = (event) => {
    event.preventDefault();
    handleViolation("Klik Kanan");
};

/**
 * Mencegah aksi copy, cut, dan paste.
 */
const preventClipboardActions = (event) => {
    event.preventDefault();
    handleViolation(`Percobaan ${event.type}`);
};

/**
 * Mendeteksi perubahan status fullscreen.
 */
const handleFullscreenChange = () => {
    // Jika tidak ada elemen dalam mode fullscreen, berarti pengguna telah keluar.
    if (!document.fullscreenElement && !document.webkitFullscreenElement && !document.msFullscreenElement) {
        // Beri jeda singkat untuk menghindari false positive saat ujian selesai normal
        setTimeout(() => {
            // Cek apakah ujian masih berjalan (halaman ujian masih terlihat)
            if (!$('#exam-page').classList.contains('hidden')) {
                handleViolation("Keluar dari Mode Layar Penuh");
            }
        }, 500);
    }
};

/**
 * Mengaktifkan semua listener untuk fitur anti-cheat.
 */
const startAntiCheat = () => {
    violationCount = 0; // Reset saat ujian dimulai
    document.addEventListener('visibilitychange', handleVisibilityChange);
    document.addEventListener('contextmenu', preventContextMenu);
    ['copy', 'cut', 'paste'].forEach(eventType => {
        document.addEventListener(eventType, preventClipboardActions);
    });
    // Tambahkan listener untuk fullscreen
    document.addEventListener('fullscreenchange', handleFullscreenChange);
    document.addEventListener('webkitfullscreenchange', handleFullscreenChange);
    document.addEventListener('msfullscreenchange', handleFullscreenChange);
    console.log("Fitur Anti-Cheat diaktifkan.");
};

/**
 * Menonaktifkan semua listener anti-cheat.
 */
const stopAntiCheat = () => {
    document.removeEventListener('visibilitychange', handleVisibilityChange);
    document.removeEventListener('contextmenu', preventContextMenu);
    ['copy', 'cut', 'paste'].forEach(eventType => {
        document.removeEventListener(eventType, preventClipboardActions);
    });
    // Hapus listener fullscreen
    document.removeEventListener('fullscreenchange', handleFullscreenChange);
    document.removeEventListener('webkitfullscreenchange', handleFullscreenChange);
    document.removeEventListener('msfullscreenchange', handleFullscreenChange);
    console.log("Fitur Anti-Cheat dinonaktifkan.");
};

console.log("anti-cheat.js loaded.");
