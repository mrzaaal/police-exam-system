/**
 * js/question-sets.js
 * Mengelola logika untuk tab Manajemen Paket Soal di dasbor admin.
 * Fitur: CRUD Paket Soal, Integrasi dengan Soal yang Disetujui.
 */

// --- DOM Elements ---
const questionSetsTableBody = $('#question-sets-table-body');
const addQuestionSetBtn = $('#add-question-set-btn');
const questionSetModal = $('#question-set-modal');
const questionSetModalTitle = $('#question-set-modal-title');
const questionSetForm = $('#question-set-form');
const questionSetModalCancelBtn = $('#question-set-modal-cancel');
const setQuestionsList = $('#set-questions-list');

// --- State ---
let allQuestionSets = [];

/**
 * Mengambil semua paket soal dari API.
 */
const fetchQuestionSets = async () => {
    try {
        const response = await fetch('api/question_sets_crud.php');
        const result = await response.json();
        if (result.success) {
            allQuestionSets = result.data;
            renderQuestionSetsTable();
        } else {
            showToast(result.message, 'error');
        }
    } catch (error) { 
        console.error('Error fetching question sets:', error);
        showToast('Gagal memuat paket soal.', 'error');
    }
};

/**
 * Merender tabel paket soal.
 */
const renderQuestionSetsTable = () => {
    questionSetsTableBody.innerHTML = '';
    if (allQuestionSets.length === 0) {
        questionSetsTableBody.innerHTML = `<tr><td colspan="5" class="text-center py-4">Belum ada paket soal yang dibuat.</td></tr>`;
        return;
    }
    allQuestionSets.forEach(set => {
        const row = `
            <tr>
                <td class="px-6 py-4 font-medium">${set.name}</td>
                <td class="px-6 py-4">${set.description || '-'}</td>
                <td class="px-6 py-4">${set.question_count}</td>
                <td class="px-6 py-4">${set.created_at}</td>
                <td class="px-6 py-4">
                    <button class="action-btn delete delete-set-btn" data-id="${set.id}" data-name="${set.name}">Hapus</button>
                </td>
            </tr>
        `;
        questionSetsTableBody.innerHTML += row;
    });
};

/**
 * Membuka modal untuk membuat paket soal baru.
 */
const openQuestionSetModal = async () => {
    questionSetForm.reset();
    questionSetModalTitle.textContent = 'Buat Paket Soal Baru';
    
    setQuestionsList.innerHTML = '<p>Memuat soal...</p>';
    try {
        // PERBAIKAN: Tambahkan parameter unik untuk mencegah cache
        const cacheBuster = `&_=${new Date().getTime()}`;
        const response = await fetch(`api/questions_crud.php?status=approved${cacheBuster}`);
        const result = await response.json();
        
        if (result.success && result.data.length > 0) {
            let questionsHTML = result.data.map(q => {
                const qText = q.question_text.length > 80 ? q.question_text.substring(0, 80) + '...' : q.question_text;
                return `<div class="flex items-center"><input type="checkbox" name="question_ids[]" value="${q.id}" id="set_q_${q.id}" class="h-4 w-4"><label for="set_q_${q.id}" class="ml-3 text-sm text-gray-600">${qText} (ID: ${q.id})</label></div>`;
            }).join('');
            setQuestionsList.innerHTML = `<div class="space-y-2">${questionsHTML}</div>`;
        } else {
            setQuestionsList.innerHTML = '<p class="text-gray-500">Tidak ada soal yang disetujui untuk dipilih.</p>';
        }
    } catch (error) {
        setQuestionsList.innerHTML = '<p class="text-red-500">Gagal memuat daftar soal.</p>';
    }

    toggleElementVisibility(questionSetModal, true);
};

const closeQuestionSetModal = () => toggleElementVisibility(questionSetModal, false);

/**
 * Menangani submit form paket soal.
 */
const handleQuestionSetFormSubmit = async (event) => {
    event.preventDefault();
    const formData = new FormData(questionSetForm);
    const selectedQuestionIds = Array.from(formData.getAll('question_ids[]'));

    if (selectedQuestionIds.length === 0) {
        showToast('Pilih setidaknya satu soal untuk paket ini.', 'warning');
        return;
    }

    const setData = {
        name: formData.get('name'),
        description: formData.get('description'),
        question_ids: selectedQuestionIds
    };

    try {
        const response = await fetch('api/question_sets_crud.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(setData)
        });
        const result = await response.json();
        if (result.success) {
            closeQuestionSetModal();
            fetchQuestionSets();
            showToast(result.message, 'success');
        } else {
            showToast(`Gagal: ${result.message}`, 'error');
        }
    } catch (error) {
        console.error('Error submitting question set:', error);
        showToast('Terjadi kesalahan koneksi.', 'error');
    }
};

/**
 * Menangani penghapusan paket soal.
 */
const handleDeleteQuestionSet = async (setId, setName) => {
    const confirmed = await showConfirmModal('Konfirmasi Hapus', `Anda yakin ingin menghapus paket soal "${setName}"?`);
    if (!confirmed) return;

    try {
        const response = await fetch(`api/question_sets_crud.php?id=${setId}`, { method: 'DELETE' });
        const result = await response.json();
        if (result.success) {
            fetchQuestionSets();
            showToast(result.message, 'success');
        } else {
            showToast(`Gagal menghapus: ${result.message}`, 'error');
        }
    } catch (error) {
        console.error('Error deleting question set:', error);
        showToast('Terjadi kesalahan koneksi.', 'error');
    }
};

/**
 * Inisialisasi halaman manajemen paket soal.
 */
const initializeQuestionSetPage = () => {
    fetchQuestionSets();
};

// --- Event Listeners ---
addQuestionSetBtn.addEventListener('click', openQuestionSetModal);
questionSetModalCancelBtn.addEventListener('click', closeQuestionSetModal);
questionSetForm.addEventListener('submit', handleQuestionSetFormSubmit);

questionSetsTableBody.addEventListener('click', (event) => {
    if (event.target.classList.contains('delete-set-btn')) {
        const setId = event.target.dataset.id;
        const setName = event.target.dataset.name;
        handleDeleteQuestionSet(setId, setName);
    }
});
