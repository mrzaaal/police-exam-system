/**
 * js/utils.js
 * Berisi fungsi-fungsi bantuan (helper functions) yang digunakan di seluruh aplikasi,
 * termasuk untuk modal konfirmasi modern.
 */

// --- DOM Selectors ---
const $ = (selector, scope = document) => scope.querySelector(selector);
const $$ = (selector, scope = document) => scope.querySelectorAll(selector);

// --- DOM Elements for Generic Modal ---
const genericConfirmModal = $('#generic-confirm-modal');
const genericConfirmTitle = $('#generic-confirm-title');
const genericConfirmBody = $('#generic-confirm-body');
const genericConfirmOkBtn = $('#generic-confirm-ok-btn');
const genericConfirmCancelBtn = $('#generic-confirm-cancel-btn');

/**
 * Fungsi untuk memformat waktu dari detik menjadi format HH:MM:SS.
 * @param {number} totalSeconds - Jumlah total detik.
 * @returns {string} Waktu yang sudah diformat.
 */
const formatTime = (totalSeconds) => {
    if (totalSeconds < 0) totalSeconds = 0;
    const hours = Math.floor(totalSeconds / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = totalSeconds % 60;
    return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
};

/**
 * Menampilkan atau menyembunyikan elemen.
 * @param {Element} element - Elemen yang akan diubah visibilitasnya.
 * @param {boolean} show - True untuk menampilkan, false untuk menyembunyikan.
 */
const toggleElementVisibility = (element, show) => {
    if (element) {
        element.classList.toggle('hidden', !show);
    }
};

/**
 * Menampilkan modal konfirmasi modern dan mengembalikan Promise.
 * @param {string} title - Judul modal.
 * @param {string} body - Teks isi modal.
 * @param {object} [options] - Opsi tambahan (misal: okText, cancelText, okClass).
 * @returns {Promise<boolean>} - Resolve menjadi true jika dikonfirmasi, false jika dibatalkan.
 */
function showConfirmModal(title, body, options = {}) {
    return new Promise((resolve) => {
        if (!genericConfirmModal) {
            console.error('Modal konfirmasi umum tidak ditemukan di DOM.');
            resolve(false); // Otomatis batalkan jika modal tidak ada
            return;
        }

        genericConfirmTitle.textContent = title;
        genericConfirmBody.textContent = body;

        genericConfirmOkBtn.textContent = options.okText || 'Yakin';
        genericConfirmCancelBtn.textContent = options.cancelText || 'Batal';

        // Reset classes and apply new one if provided
        genericConfirmOkBtn.className = 'font-semibold px-6 py-2 rounded-lg transition';
        genericConfirmOkBtn.classList.add(...(options.okClass || 'bg-red-600 text-white hover:bg-red-700').split(' '));
        
        toggleElementVisibility(genericConfirmModal, true);

        const handleConfirm = () => {
            cleanup();
            resolve(true);
        };

        const handleCancel = () => {
            cleanup();
            resolve(false);
        };

        const cleanup = () => {
            toggleElementVisibility(genericConfirmModal, false);
            genericConfirmOkBtn.removeEventListener('click', handleConfirm);
            genericConfirmCancelBtn.removeEventListener('click', handleCancel);
        };

        // Hapus listener lama sebelum menambahkan yang baru untuk mencegah duplikasi
        genericConfirmOkBtn.replaceWith(genericConfirmOkBtn.cloneNode(true));
        genericConfirmCancelBtn.replaceWith(genericConfirmCancelBtn.cloneNode(true));
        
        // Tambahkan listener baru
        $('#generic-confirm-ok-btn').addEventListener('click', handleConfirm);
        $('#generic-confirm-cancel-btn').addEventListener('click', handleCancel);
    });
}

console.log("utils.js loaded.");
