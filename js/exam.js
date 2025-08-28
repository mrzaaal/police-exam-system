/**
 * js/exam.js
 * Mengelola semua logika untuk halaman ujian peserta.
 * Versi ini mencakup:
 * - Dukungan soal Pilihan Ganda & Esai (dengan gambar & MathJax).
 * - Fitur Aksesibilitas (Kontras Tinggi & Ukuran Font).
 * - Mode Ujian Terkunci (Fullscreen).
 * - Autosave & Tandai Ragu-ragu ke Database via Redis.
 * - Pencatatan Event untuk Forensik (QUESTION_VIEWED).
 * - Alur Ujian Selesai yang Terkontrol.
 */

// --- DOM Elements ---
const timerDisplay = $('#timer');
const questionContainer = $('#question-container');
const questionNavigation = $('#question-navigation');
const prevBtn = $('#prev-btn');
const nextBtn = $('#next-btn');
const finishExamBtn = $('#finish-exam-btn');
const currentQuestionNumberDisplay = $('#current-question-number');
const totalQuestionsDisplay = $('#total-questions');
const flagQuestionCheckbox = $('#flag-question-checkbox');
const startFullscreenBtn = $('#start-fullscreen-exam-btn');

// Accessibility DOM Elements
const highContrastToggle = $('#high-contrast-toggle');
const fontIncreaseBtn = $('#font-increase-btn');
const fontDecreaseBtn = $('#font-decrease-btn');

// --- Exam State ---
let questions = [];
let examConfig = {};
let currentQuestionIndex = 0;
let userAnswers = [];
let flaggedQuestions = [];
let timerInterval;

// Accessibility State
let currentFontSize = 0; // 0: Normal, 1: Large, 2: XLarge
const fontClasses = ['', 'font-size-large', 'font-size-xlarge'];


// =============================================
// ===== FUNGSI AKSESIBILITAS =====
// =============================================

/**
 * Menerapkan preferensi aksesibilitas yang tersimpan.
 */
const applyAccessibilityPreferences = () => {
    const isHighContrast = localStorage.getItem('highContrast') === 'true';
    document.body.classList.toggle('high-contrast', isHighContrast);
    highContrastToggle.setAttribute('aria-checked', isHighContrast);
    const toggleSpan = highContrastToggle.querySelector('span');
    if (toggleSpan) {
        toggleSpan.style.transform = isHighContrast ? 'translateX(20px)' : 'translateX(0)';
    }

    currentFontSize = parseInt(localStorage.getItem('fontSize') || '0');
    updateFontSize();
};

/**
 * Mengaktifkan atau menonaktifkan mode kontras tinggi.
 */
const toggleHighContrast = () => {
    const currentState = document.body.classList.toggle('high-contrast');
    localStorage.setItem('highContrast', currentState);
    highContrastToggle.setAttribute('aria-checked', currentState);
    const toggleSpan = highContrastToggle.querySelector('span');
    if (toggleSpan) {
        toggleSpan.style.transform = currentState ? 'translateX(20px)' : 'translateX(0)';
    }
};

/**
 * Memperbarui kelas CSS untuk ukuran font pada kontainer soal.
 */
const updateFontSize = () => {
    questionContainer.classList.remove(...fontClasses);
    if (fontClasses[currentFontSize]) {
        questionContainer.classList.add(fontClasses[currentFontSize]);
    }
    localStorage.setItem('fontSize', currentFontSize);
};

const increaseFontSize = () => {
    if (currentFontSize < fontClasses.length - 1) {
        currentFontSize++;
        updateFontSize();
    }
};

const decreaseFontSize = () => {
    if (currentFontSize > 0) {
        currentFontSize--;
        updateFontSize();
    }
};


// =============================================
// ===== FUNGSI INTI UJIAN =====
// =============================================

/**
 * Mengambil data soal, jawaban, dan status ragu-ragu dari API.
 */
const fetchExamData = async () => {
    try {
        const response = await fetch('api/questions.php');
        if (!response.ok) {
            const errorResult = await response.json();
            throw new Error(errorResult.message || `HTTP error! status: ${response.status}`);
        }
        const data = await response.json();
        questions = data.questions;
        userAnswers = data.saved_answers;
        flaggedQuestions = data.saved_flags;
        
        // Ambil durasi dari data yang dikirim server
        examConfig.durationInSeconds = (data.duration_minutes || 90) * 60;

    } catch (error) {
        console.error("Gagal memuat data ujian:", error);
        // Alihkan ke halaman "menunggu" dengan pesan error
        showPage('examFinished');
        $('#exam-finished-page h1').textContent = 'Gagal Memuat Ujian';
        $('#exam-finished-page p').textContent = error.message;
    }
};

/**
 * Menyimpan progres (jawaban atau status ragu-ragu) ke server.
 */
const autosaveProgress = async (payload) => {
    try {
        await fetch('api/autosave.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        console.log(`Progres untuk soal ${payload.questionIndex + 1} berhasil di-autosave.`);
    } catch (error) {
        console.error('Autosave gagal:', error);
    }
};

/**
 * Merender soal, termasuk gambar dan rumus, dan mencatat event 'QUESTION_VIEWED'.
 */
const renderQuestion = () => {
    if (questions.length === 0) return;
    const question = questions[currentQuestionIndex];
    let answerAreaHTML = '';
    let imageHTML = '';

    // Catat bahwa peserta melihat soal ini
    autosaveProgress({
        questionIndex: currentQuestionIndex,
        eventType: 'QUESTION_VIEWED'
    });

    if (question.image_url) {
        imageHTML = `<div class="mb-4 max-w-lg mx-auto"><img src="${question.image_url}" alt="Gambar Pendukung Soal" class="w-full h-auto rounded-lg border"></div>`;
    }

    if (question.question_type === 'multiple-choice') {
        let optionsHTML = '';
        question.options.forEach((option, index) => {
            const isChecked = userAnswers[currentQuestionIndex] === index;
            optionsHTML += `<label class="option-label block"><input type="radio" name="option" class="option-input" value="${index}" ${isChecked ? 'checked' : ''}><span class="checkmark"></span>${option}</label>`;
        });
        answerAreaHTML = `<div class="options-container">${optionsHTML}</div>`;
    } else { // 'essay'
        const savedAnswerText = userAnswers[currentQuestionIndex] || '';
        answerAreaHTML = `<div><label for="essay_answer" class="block text-sm font-medium text-gray-700 mb-2">Jawaban Anda:</label><textarea id="essay_answer" rows="8" class="w-full px-4 py-2 border border-gray-300 rounded-lg" placeholder="Ketik jawaban esai Anda di sini...">${savedAnswerText}</textarea></div>`;
    }

    questionContainer.innerHTML = `${imageHTML}<div class="question-text prose">${question.question_text}</div>${answerAreaHTML}`;
    
    if (window.MathJax) {
        MathJax.typesetPromise([questionContainer]);
    }
    
    currentQuestionNumberDisplay.textContent = currentQuestionIndex + 1;
    
    if (question.question_type === 'multiple-choice') {
        $$('input[name="option"]').forEach(input => input.addEventListener('change', handleAnswerSelection));
    } else {
        $('#essay_answer').addEventListener('input', handleAnswerSelection);
    }
    
    flagQuestionCheckbox.checked = flaggedQuestions[currentQuestionIndex];
    updateNavigationButtons();
};

const updateNavigationStatus = () => {
    $$('.nav-btn').forEach((btn, index) => {
        btn.classList.remove('active', 'answered', 'flagged');
        const answer = userAnswers[index];
        if (answer !== null && answer !== undefined && answer !== '') {
            btn.classList.add('answered');
        }
        if (flaggedQuestions[index]) {
            btn.classList.add('flagged');
        }
        if (index === currentQuestionIndex) {
            btn.classList.add('active');
        }
    });
};

const handleAnswerSelection = (event) => {
    const question = questions[currentQuestionIndex];
    let answerValue;
    if (question.question_type === 'multiple-choice') {
        answerValue = parseInt(event.target.value);
    } else {
        answerValue = event.target.value;
    }
    userAnswers[currentQuestionIndex] = answerValue;
    updateNavigationStatus();
    autosaveProgress({
        questionIndex: currentQuestionIndex,
        answerValue: answerValue
    });
};

const handleFlagToggle = () => {
    const isFlagged = flagQuestionCheckbox.checked;
    flaggedQuestions[currentQuestionIndex] = isFlagged;
    updateNavigationStatus();
    autosaveProgress({
        questionIndex: currentQuestionIndex,
        isFlagged: isFlagged
    });
};

const startTimer = () => {
    let timeLeft = examConfig.durationInSeconds;
    timerInterval = setInterval(() => {
        timeLeft--;
        timerDisplay.textContent = formatTime(timeLeft);
        if (timeLeft <= 0) {
            clearInterval(timerInterval);
            showToast("Waktu habis! Ujian akan diselesaikan secara otomatis.", "warning");
            finishExam();
        }
    }, 1000);
};

const finishExam = async () => {
    clearInterval(timerInterval);
    stopAntiCheat();
    toggleElementVisibility(genericConfirmModal, false); // Tutup modal jika terbuka
    try {
        const response = await fetch('api/results.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ answers: userAnswers })
        });
        const result = await response.json();
        if (result.success) {
            showPage('examFinished');
        } else {
            showToast(`Gagal menyimpan hasil: ${result.message}`, 'error');
        }
    } catch (error) {
        showToast("Gagal terhubung ke server untuk menyimpan hasil.", 'error');
    }
};

const confirmFinishExam = async () => {
    const confirmed = await showConfirmModal('Konfirmasi Selesai Ujian', 'Apakah Anda yakin ingin menyelesaikan ujian ini? Anda tidak dapat kembali ke soal setelahnya.');
    if (confirmed) {
        finishExam();
    }
};

const enterFullscreen = () => {
    const elem = document.documentElement;
    if (elem.requestFullscreen) elem.requestFullscreen();
    else if (elem.webkitRequestFullscreen) elem.webkitRequestFullscreen();
    else if (elem.msRequestFullscreen) elem.msRequestFullscreen();
};

const startExam = async () => {
    enterFullscreen();
    showPage('exam');
    await fetchExamData();
    if (questions.length > 0) {
        totalQuestionsDisplay.textContent = questions.length;
        renderQuestion();
        renderQuestionNavigation();
        startTimer();
        startAntiCheat();
    }
};

const initializePreExamPage = () => {
    startFullscreenBtn.addEventListener('click', startExam);
};

const initializeExamPage = async () => {
    applyAccessibilityPreferences();
    await fetchExamData();
    if (questions.length > 0) {
        totalQuestionsDisplay.textContent = questions.length;
        renderQuestion();
        renderQuestionNavigation();
        startTimer();
        startAntiCheat();
    }
};

// --- Helper Functions ---
const renderQuestionNavigation = () => {
    questionNavigation.innerHTML = questions.map((_, index) => `<button class="nav-btn" data-index="${index}">${index + 1}</button>`).join('');
    $$('.nav-btn').forEach(btn => btn.addEventListener('click', (e) => navigateToQuestion(parseInt(e.target.dataset.index))));
    updateNavigationStatus();
};
const updateNavigationButtons = () => {
    prevBtn.disabled = currentQuestionIndex === 0;
    nextBtn.disabled = currentQuestionIndex === questions.length - 1;
    prevBtn.classList.toggle('opacity-50', prevBtn.disabled);
    nextBtn.classList.toggle('opacity-50', nextBtn.disabled);
};
const goToNextQuestion = () => { if (currentQuestionIndex < questions.length - 1) { currentQuestionIndex++; renderQuestion(); updateNavigationStatus(); } };
const goToPrevQuestion = () => { if (currentQuestionIndex > 0) { currentQuestionIndex--; renderQuestion(); updateNavigationStatus(); } };
const navigateToQuestion = (index) => { currentQuestionIndex = index; renderQuestion(); updateNavigationStatus(); };

// --- Event Listeners ---
prevBtn.addEventListener('click', goToPrevQuestion);
nextBtn.addEventListener('click', goToNextQuestion);
finishExamBtn.addEventListener('click', confirmFinishExam);
flagQuestionCheckbox.addEventListener('change', handleFlagToggle);
highContrastToggle.addEventListener('click', toggleHighContrast);
fontIncreaseBtn.addEventListener('click', increaseFontSize);
fontDecreaseBtn.addEventListener('click', decreaseFontSize);

console.log("exam.js loaded (final complete version).");
