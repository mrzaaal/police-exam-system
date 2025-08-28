/**
 * js/bank-soal.js
 * Mengelola semua logika untuk tab Bank Soal di dasbor admin.
 * Fitur: CRUD, Paginasi, Pencarian, Impor/Ekspor, Alur Persetujuan,
 * Modal Dinamis dengan Dukungan Gambar dan Pratinjau MathJax.
 */

// --- DOM Elements ---
const questionsTableBody = $('#questions-table-body');
const addQuestionBtn = $('#add-question-btn');
const importBtn = $('#import-btn');
const importFileInput = $('#import-file-input');
const importStatusDiv = $('#import-status');
const searchQuestionInput = $('#search-question-input');
const questionsPaginationControls = $('#questions-pagination-controls');

// Modal Elements
const questionModal = $('#question-modal');
const questionModalTitle = $('#question-modal-title');
const questionForm = $('#question-form');
const questionModalCancelBtn = $('#question-modal-cancel');
const questionIdInput = $('#question-id');
const questionTypeSelect = $('#question_type');
const mcFields = $('#mc-fields');
const questionTextArea = $('#question_text');
const questionPreviewText = $('#preview-text');
const previewImage = $('#preview-image');
const previewImageWrapper = $('#preview-image-wrapper');

// Image Upload Elements
const uploadImageBtn = $('#upload-image-btn');
const removeImageBtn = $('#remove-image-btn');
const questionImageInput = $('#question-image-input');
const imageUrlInput = $('#image_url');
const uploadStatus = $('#upload-status');

// --- State ---
let allQuestions = [];
let currentPage = 1;
let totalPages = 1;
let currentSearch = '';
let searchDebounceTimer;

/**
 * Mengambil soal dari API dengan parameter paginasi dan pencarian.
 */
const fetchQuestions = async () => {
    try {
        const url = `api/questions_crud.php?page=${currentPage}&limit=10&search=${encodeURIComponent(currentSearch)}`;
        const response = await fetch(url);
        const result = await response.json();
        
        if (result.success) {
            allQuestions = result.data;
            currentPage = result.pagination.current_page;
            totalPages = result.pagination.total_pages;
            renderQuestionsTable();
            renderPaginationControls(result.pagination);
        } else {
            showToast(result.message, 'error');
        }
    } catch (error) {
        console.error('Error fetching questions:', error);
        showToast('Gagal memuat data soal.', 'error');
    }
};

/**
 * Merender tabel bank soal.
 */
const renderQuestionsTable = () => {
    questionsTableBody.innerHTML = '';
    if (allQuestions.length === 0) {
        questionsTableBody.innerHTML = `<tr><td colspan="7" class="text-center py-4">Tidak ada soal yang ditemukan.</td></tr>`;
        return;
    }

    allQuestions.forEach(q => {
        const truncatedText = q.question_text.length > 50 ? q.question_text.substring(0, 50) + '...' : q.question_text;
        const typeLabel = q.question_type === 'multiple-choice' ? 'PG' : 'Esai';
        
        let difficultyLabel = 'Sedang';
        if (q.difficulty === 'low') difficultyLabel = 'Mudah';
        if (q.difficulty === 'high') difficultyLabel = 'Sulit';

        const isApproved = q.status === 'approved';
        const statusClass = isApproved ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800';
        const statusText = isApproved ? 'Disetujui' : 'Draf';

        const actionButton = isApproved 
            ? `<button class="action-btn delete status-change-btn" data-id="${q.id}" data-status="draft">Jadikan Draf</button>`
            : `<button class="action-btn view status-change-btn" data-id="${q.id}" data-status="approved">Setujui</button>`;

        const row = `
            <tr data-id="${q.id}">
                <td class="px-6 py-4 font-semibold">${q.id}</td>
                <td class="px-6 py-4">${typeLabel}</td>
                <td class="px-6 py-4">${truncatedText}</td>
                <td class="px-6 py-4">${q.topic}</td>
                <td class="px-6 py-4">${difficultyLabel}</td>
                <td class="px-6 py-4"><span class="px-2 py-1 text-xs font-semibold rounded-full ${statusClass}">${statusText}</span></td>
                <td class="px-6 py-4">
                    ${actionButton}
                    <button class="action-btn view edit-btn" data-id="${q.id}">Edit</button>
                    <button class="action-btn delete delete-btn" data-id="${q.id}">Hapus</button>
                </td>
            </tr>
        `;
        questionsTableBody.innerHTML += row;
    });
};

/**
 * Merender kontrol paginasi.
 */
const renderPaginationControls = (pagination) => {
    const { current_page, total_pages, total_records } = pagination;
    questionsPaginationControls.innerHTML = '';
    if (total_pages <= 1) {
        questionsPaginationControls.innerHTML = `<div class="text-sm text-gray-500">Total ${total_records} soal</div>`;
        return;
    }
    let html = `<div class="text-sm text-gray-500">Total ${total_records} soal</div>`;
    html += `<div class="flex items-center"><button class="pagination-btn ${current_page === 1 ? 'opacity-50' : ''}" data-page="${current_page - 1}" ${current_page === 1 ? 'disabled' : ''}>Sebelumnya</button><span class="px-4">Halaman ${current_page} dari ${total_pages}</span><button class="pagination-btn ${current_page === total_pages ? 'opacity-50' : ''}" data-page="${current_page + 1}" ${current_page === total_pages ? 'disabled' : ''}>Berikutnya</button></div>`;
    questionsPaginationControls.innerHTML = html;
};

/**
 * Menangani input pencarian.
 */
const handleSearchInput = (event) => {
    clearTimeout(searchDebounceTimer);
    searchDebounceTimer = setTimeout(() => {
        currentSearch = event.target.value;
        currentPage = 1;
        fetchQuestions();
    }, 500);
};

/**
 * Menangani klik paginasi.
 */
const handlePaginationClick = (event) => {
    const target = event.target.closest('.pagination-btn');
    if (target && !target.disabled) {
        currentPage = parseInt(target.dataset.page);
        fetchQuestions();
    }
};

/**
 * Menyesuaikan tampilan form berdasarkan tipe soal.
 */
const toggleQuestionTypeFields = () => {
    const selectedType = questionTypeSelect.value;
    const isMc = selectedType === 'multiple-choice';
    toggleElementVisibility(mcFields, isMc);
    $$('.mc-option, .mc-correct-answer').forEach(el => { el.required = isMc; });
};

/**
 * Memperbarui pratinjau soal di modal.
 */
const updateQuestionPreview = () => {
    questionPreviewText.textContent = questionTextArea.value;
    const imageUrl = imageUrlInput.value;
    if (imageUrl) {
        previewImage.src = imageUrl;
        toggleElementVisibility(previewImageWrapper, true);
    } else {
        toggleElementVisibility(previewImageWrapper, false);
    }
    if (window.MathJax) {
        MathJax.typesetPromise([questionPreviewText]);
    }
};

/**
 * Membuka modal untuk menambah/mengedit soal.
 */
const openQuestionModal = (question = null) => {
    questionForm.reset();
    removeImage();
    if (question) {
        questionModalTitle.textContent = 'Edit Soal';
        questionIdInput.value = question.id;
        questionTypeSelect.value = question.question_type;
        $('#question_text').value = question.question_text;
        $('#topic').value = question.topic;
        $('#difficulty').value = question.difficulty;
        $('#competency').value = question.competency;
        if (question.image_url) {
            imageUrlInput.value = question.image_url;
            imagePreview.src = question.image_url;
            toggleElementVisibility(imagePreview, true);
            toggleElementVisibility(imagePlaceholder, false);
            toggleElementVisibility(removeImageBtn, true);
        }
        if (question.question_type === 'multiple-choice') {
            const options = JSON.parse(question.options);
            options.forEach((opt, index) => { $(`input[name="option_${index}"]`).value = opt; });
            $(`input[name="correct_answer_index"][value="${question.correct_answer_index}"]`).checked = true;
        }
    } else {
        questionModalTitle.textContent = 'Tambah Soal Baru';
        questionIdInput.value = '';
        questionTypeSelect.value = 'multiple-choice';
    }
    toggleQuestionTypeFields();
    updateQuestionPreview();
    toggleElementVisibility(questionModal, true);
};

const closeQuestionModal = () => toggleElementVisibility(questionModal, false);

/**
 * Menangani submit form soal.
 */
const handleFormSubmit = async (event) => {
    event.preventDefault();
    const formData = new FormData(questionForm);
    const id = formData.get('id');
    const questionType = formData.get('question_type');
    let questionData = {
        question_type: questionType,
        question_text: formData.get('question_text'),
        image_url: formData.get('image_url'),
        topic: formData.get('topic'),
        difficulty: formData.get('difficulty'),
        competency: formData.get('competency')
    };
    if (questionType === 'multiple-choice') {
        questionData.options = [formData.get('option_0'), formData.get('option_1'), formData.get('option_2'), formData.get('option_3')];
        questionData.correct_answer_index = parseInt(formData.get('correct_answer_index'));
    }
    const isUpdating = !!id;
    const url = isUpdating ? `api/questions_crud.php?id=${id}` : 'api/questions_crud.php';
    const method = isUpdating ? 'PUT' : 'POST';
    try {
        const response = await fetch(url, { method: method, headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(questionData) });
        const result = await response.json();
        if (result.success) {
            closeQuestionModal();
            fetchQuestions();
            showToast(result.message, 'success');
        } else {
            showToast(`Gagal: ${result.message}`, 'error');
        }
    } catch (error) { showToast('Terjadi kesalahan koneksi.', 'error'); }
};

/**
 * Menangani perubahan status soal.
 */
const handleStatusChange = async (questionId, newStatus) => {
    const actionVerb = newStatus === 'approved' ? 'menyetujui' : 'mengembalikan ke draf';
    const confirmed = await showConfirmModal('Konfirmasi Status', `Anda yakin ingin ${actionVerb} soal ID ${questionId}?`);
    if (!confirmed) return;
    try {
        const response = await fetch('api/question_status_action.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ question_id: questionId, status: newStatus }) });
        const result = await response.json();
        if (result.success) {
            fetchQuestions();
            showToast(result.message, 'success');
        } else {
            showToast(`Gagal: ${result.message}`, 'error');
        }
    } catch (error) { showToast('Terjadi kesalahan koneksi.', 'error'); }
};

/**
 * Menangani penghapusan soal.
 */
const handleDeleteQuestion = async (id) => {
    const confirmed = await showConfirmModal('Konfirmasi Hapus', `Anda yakin ingin menghapus soal ID ${id}? Tindakan ini tidak dapat dibatalkan.`);
    if (!confirmed) return;
    try {
        const response = await fetch(`api/questions_crud.php?id=${id}`, { method: 'DELETE' });
        const result = await response.json();
        if (result.success) {
            fetchQuestions();
            showToast(result.message, 'success');
        } else {
            showToast(`Gagal: ${result.message}`, 'error');
        }
    } catch (error) { showToast('Terjadi kesalahan koneksi.', 'error'); }
};

/**
 * Menangani pemilihan file gambar.
 */
const handleImageSelection = () => {
    const file = questionImageInput.files[0];
    if (!file) return;
    uploadStatus.textContent = 'Mengunggah...';
    const formData = new FormData();
    formData.append('question_image', file);
    fetch('api/upload_image.php', { method: 'POST', body: formData })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            uploadStatus.textContent = 'Unggah berhasil.';
            uploadStatus.className = 'text-sm mt-2 text-green-600';
            imageUrlInput.value = result.filePath;
            updateQuestionPreview();
            toggleElementVisibility(removeImageBtn, true);
        } else {
            uploadStatus.textContent = `Gagal: ${result.message}`;
            uploadStatus.className = 'text-sm mt-2 text-red-600';
        }
    })
    .catch(error => {
        uploadStatus.textContent = 'Error: Gagal terhubung ke server.';
        uploadStatus.className = 'text-sm mt-2 text-red-600';
    });
};

const removeImage = () => {
    imageUrlInput.value = '';
    questionImageInput.value = '';
    uploadStatus.textContent = '';
    updateQuestionPreview();
    toggleElementVisibility(removeImageBtn, false);
};

/**
 * Menangani impor file CSV soal.
 */
const handleFileImport = async () => {
    const file = importFileInput.files[0];
    if (!file) return;
    const formData = new FormData();
    formData.append('questions_csv', file);
    importStatusDiv.innerHTML = `<p class="text-blue-600">Mengunggah...</p>`;
    try {
        const response = await fetch('api/import_questions.php', { method: 'POST', body: formData });
        const result = await response.json();
        if (response.ok && result.success) {
            importStatusDiv.innerHTML = `<div class="p-4 bg-green-100 text-green-800 rounded-lg">${result.message}</div>`;
            fetchQuestions();
        } else {
            importStatusDiv.innerHTML = `<div class="p-4 bg-red-100 text-red-800 rounded-lg">Gagal: ${result.message}</div>`;
        }
    } catch (error) {
        importStatusDiv.innerHTML = `<div class="p-4 bg-red-100 text-red-800 rounded-lg">Error koneksi.</div>`;
    } finally {
        importFileInput.value = '';
    }
};

/**
 * Inisialisasi halaman bank soal.
 */
const initializeBankSoalPage = () => {
    currentPage = 1;
    currentSearch = '';
    searchQuestionInput.value = '';
    fetchQuestions();
    importStatusDiv.innerHTML = '';
};

// --- Event Listeners ---
addQuestionBtn.addEventListener('click', () => openQuestionModal());
questionModalCancelBtn.addEventListener('click', closeQuestionModal);
questionForm.addEventListener('submit', handleFormSubmit);
questionTypeSelect.addEventListener('change', toggleQuestionTypeFields);
questionTextArea.addEventListener('input', updateQuestionPreview);
uploadImageBtn.addEventListener('click', () => questionImageInput.click());
questionImageInput.addEventListener('change', handleImageSelection);
removeImageBtn.addEventListener('click', removeImage);
importBtn.addEventListener('click', () => importFileInput.click());
importFileInput.addEventListener('change', handleFileImport);
searchQuestionInput.addEventListener('input', handleSearchInput);
questionsPaginationControls.addEventListener('click', handlePaginationClick);

questionsTableBody.addEventListener('click', (event) => {
    const target = event.target;
    if (target.classList.contains('edit-btn')) {
        const id = parseInt(target.dataset.id);
        const questionToEdit = allQuestions.find(q => q.id === id);
        if (questionToEdit) openQuestionModal(questionToEdit);
    }
    if (target.classList.contains('delete-btn')) {
        const id = parseInt(target.dataset.id);
        handleDeleteQuestion(id);
    }
    if (target.classList.contains('status-change-btn')) {
        const id = parseInt(target.dataset.id);
        const status = target.dataset.status;
        handleStatusChange(id, status);
    }
});
