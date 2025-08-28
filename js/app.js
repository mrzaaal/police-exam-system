/**
 * js/app.js
 * File utama aplikasi. Menginisialisasi dan mengelola alur aplikasi,
 * termasuk perutean cerdas untuk peserta dan inisialisasi dasbor admin.
 */

// --- DEKLARASI SEMUA ELEMEN HALAMAN UTAMA ---
const loginPage = $('#login-page');
const preExamPage = $('#pre-exam-page');
const examPage = $('#exam-page');
const examFinishedPage = $('#exam-finished-page');
const viewResultsPage = $('#view-results-page');
const detailedReportPage = $('#detailed-report-page');
const profilePage = $('#profile-page');
const adminPage = $('#admin-page');

// Elemen-elemen yang dideklarasikan di sini untuk digunakan di file lain
const loginForm = $('#login-form');
const usernameInput = $('#username');
const passwordInput = $('#password');
const logoutBtn = $('#logout-btn');
const usersTableBody = $('#users-table-body');
const importUsersBtn = $('#import-users-btn');
const importUsersInput = $('#import-users-input');
const importUsersStatus = $('#import-users-status');
const searchUsersInput = $('#search-users-input');
const usersPaginationControls = $('#users-pagination-controls');
const addUserBtn = $('#add-user-btn');
const userModal = $('#user-modal');
const userForm = $('#user-form');
const userModalCancelBtn = $('#user-modal-cancel');


const pages = {
    login: loginPage,
    preExam: preExamPage,
    exam: examPage,
    examFinished: examFinishedPage,
    viewResults: viewResultsPage,
    detailedReport: detailedReportPage,
    profile: profilePage,
    admin: adminPage
};

/**
 * Menampilkan halaman yang ditentukan dan menyembunyikan yang lain.
 * @param {string} pageName - Nama halaman yang akan ditampilkan.
 */
const showPage = (pageName) => {
    for (const key in pages) {
        if (pages[key]) {
            pages[key].classList.add('hidden');
        }
    }
    const pageToShow = pages[pageName];
    if (pageToShow) {
        pageToShow.classList.remove('hidden');
    } else {
        console.error(`Halaman "${pageName}" tidak ditemukan.`);
        pages.login.classList.remove('hidden'); // Default ke halaman login jika error
    }
};

/**
 * Merender daftar hasil ujian yang tersedia untuk peserta.
 * @param {Array} results - Array data hasil ujian dari API.
 */
const renderAvailableResultsList = (results) => {
    const container = $('#results-list-container');
    if (!results || results.length === 0) {
        container.innerHTML = '<p class="text-center text-gray-500">Tidak ada hasil ujian yang dirilis untuk Anda saat ini.</p>';
        return;
    }
    let html = '<div class="space-y-4">';
    results.forEach(result => {
        const statusClass = result.status === 'Lulus' ? 'text-green-600' : 'text-red-600';
        html += `
            <div class="border rounded-lg p-4 flex justify-between items-center">
                <div>
                    <p class="font-bold text-lg">${result.schedule_title || 'Ujian'}</p>
                    <p class="text-sm text-gray-500">Selesai pada: ${result.completed_at}</p>
                </div>
                <div class="text-right">
                    <p class="text-2xl font-bold ${statusClass}">${parseFloat(result.score).toFixed(2)}</p>
                    <button class="action-btn view view-report-btn mt-2" data-id="${result.id}">Lihat Rincian</button>
                </div>
            </div>
        `;
    });
    html += '</div>';
    container.innerHTML = html;
};

/**
 * Menampilkan halaman laporan rinci untuk hasil ujian yang dipilih.
 * @param {number} resultId - ID dari hasil ujian.
 */
const showDetailedReport = async (resultId) => {
    showPage('detailedReport');
    const container = $('#detailed-report-container');
    container.innerHTML = '<p>Memuat rincian...</p>';

    try {
        const response = await fetch(`api/get_my_result_details.php?result_id=${resultId}`);
        const result = await response.json();

        if (result.success) {
            renderDetailedReport(result.data);
        } else {
            container.innerHTML = `<p class="text-red-500">${result.message}</p>`;
        }
    } catch (error) {
        console.error('Error fetching report details:', error);
        container.innerHTML = `<p class="text-red-500">Gagal memuat rincian.</p>`;
    }
};

/**
 * Merender konten laporan rinci.
 * @param {object} data - Data lengkap dari API.
 */
const renderDetailedReport = (data) => {
    const container = $('#detailed-report-container');
    let reportHTML = '';

    data.report.forEach((item, index) => {
        let answerSection = '';
        if (item.question_type === 'multiple-choice') {
            answerSection = '<div class="space-y-2 mt-2">';
            item.options.forEach((option, optIndex) => {
                let itemClass = 'bg-gray-100'; // Default
                if (optIndex === item.correct_answer) {
                    itemClass = 'bg-green-100 border-green-500'; // Jawaban benar
                }
                if (optIndex === item.user_answer && item.user_answer !== item.correct_answer) {
                    itemClass = 'bg-red-100 border-red-500'; // Jawaban salah
                }
                answerSection += `<div class="p-2 border rounded ${itemClass}">${String.fromCharCode(65 + optIndex)}. ${option}</div>`;
            });
            answerSection += '</div>';
        } else { // Essay
            answerSection = `<div class="mt-2 p-3 bg-gray-50 border rounded whitespace-pre-wrap">${item.user_answer || '<i>Tidak dijawab</i>'}</div>`;
        }

        reportHTML += `
            <div class="py-4 border-b">
                <p class="font-semibold">Soal #${index + 1}</p>
                ${item.image_url ? `<img src="${item.image_url}" class="my-2 max-w-xs rounded">` : ''}
                <div class="prose">${item.question_text}</div>
                ${answerSection}
            </div>
        `;
    });

    container.innerHTML = reportHTML;
    if (window.MathJax) {
        MathJax.typesetPromise([container]);
    }
};

/**
 * Memeriksa status peserta dan mengarahkan ke halaman yang sesuai.
 */
const routeParticipant = async () => {
    try {
        const response = await fetch('api/get_user_status.php');
        const result = await response.json();
        if (result.success) {
            switch (result.status) {
                case 'active_session':
                    showPage('exam');
                    initializeExamPage();
                    break;
                case 'results_available':
                    renderAvailableResultsList(result.data);
                    showPage('viewResults');
                    break;
                case 'schedule_available':
                    showPage('preExam');
                    initializePreExamPage();
                    break;
                case 'idle':
                default:
                    showPage('examFinished');
                    break;
            }
        } else {
            showToast(result.message, 'error');
            handleLogout();
        }
    } catch (error) {
        console.error("Gagal memeriksa status:", error);
        showToast("Gagal terhubung ke server. Silakan coba lagi.", 'error');
        handleLogout();
    }
};

/**
 * Fungsi inisialisasi utama aplikasi.
 */
const initializeApp = () => {
    const currentUser = getUserSession();
    if (currentUser) {
        if (currentUser.role === 'admin' || currentUser.role === 'grader' || currentUser.role === 'proctor') {
            showPage('admin');
            initializeAdminPage();
        } else if (currentUser.role === 'participant') {
            routeParticipant();
        } else {
            handleLogout();
        }
    } else {
        showPage('login');
    }
};

// --- Titik Awal Aplikasi ---
document.addEventListener('DOMContentLoaded', () => {
    console.log("Aplikasi siap.");
    initializeApp();
    
    // Event listener untuk tombol-tombol navigasi utama
    $('#logout-after-exam-btn').addEventListener('click', handleLogout);
    $('#logout-from-results-btn').addEventListener('click', handleLogout);
    
    const goToProfile = () => {
        showPage('profile');
        initializeProfilePage();
    };
    $('#go-to-profile-btn-1').addEventListener('click', goToProfile);
    $('#go-to-profile-btn-2').addEventListener('click', goToProfile);
    
    $('#back-to-dashboard-btn').addEventListener('click', routeParticipant);
    
    $('#view-results-page').addEventListener('click', (event) => {
        if (event.target.classList.contains('view-report-btn')) {
            const resultId = event.target.dataset.id;
            showDetailedReport(resultId);
        }
    });

    $('#back-to-results-list-btn').addEventListener('click', () => showPage('viewResults'));
});
