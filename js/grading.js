/**
 * js/grading.js
 * Mengelola logika untuk tab Penilaian Esai di halaman admin.
 */

// --- DOM Elements ---
const gradingContainer = $('#grading-container');

// --- State ---
let pendingEssays = [];

/**
 * Mengambil data esai yang menunggu penilaian dari API.
 */
const fetchPendingEssays = async () => {
    try {
        const response = await fetch('api/grading.php');
        const result = await response.json();
        if (result.success) {
            pendingEssays = result.data;
            renderGradingCards();
        } else {
            gradingContainer.innerHTML = `<p class="text-red-500">${result.message}</p>`;
        }
    } catch (error) {
        console.error('Error fetching pending essays:', error);
        gradingContainer.innerHTML = `<p class="text-red-500">Gagal memuat data penilaian.</p>`;
    }
};

/**
 * Merender kartu-kartu penilaian untuk setiap esai.
 */
const renderGradingCards = () => {
    gradingContainer.innerHTML = '';
    if (pendingEssays.length === 0) {
        gradingContainer.innerHTML = `<p class="text-gray-500">Tidak ada jawaban esai yang perlu dinilai saat ini.</p>`;
        return;
    }

    pendingEssays.forEach(essay => {
        const card = document.createElement('div');
        card.className = 'border border-gray-200 rounded-lg p-6 grading-card';
        card.dataset.submissionId = essay.id;

        card.innerHTML = `
            <div class="mb-4">
                <p class="text-sm text-gray-500">Peserta: <span class="font-semibold text-gray-700">${essay.username}</span></p>
                <p class="font-semibold mt-2">Pertanyaan:</p>
                <p class="text-gray-800 bg-gray-50 p-3 rounded-md mt-1">${essay.question_text}</p>
            </div>
            <div class="mb-4">
                <p class="font-semibold">Jawaban Peserta:</p>
                <div class="text-gray-800 bg-gray-50 p-3 rounded-md mt-1 whitespace-pre-wrap">${essay.answer_text}</div>
            </div>
            <form class="grading-form flex items-center space-x-4">
                <input type="hidden" name="submission_id" value="${essay.id}">
                <div>
                    <label for="score_${essay.id}" class="block text-sm font-medium text-gray-700">Skor (0-100)</label>
                    <input type="number" id="score_${essay.id}" name="score" min="0" max="100" required class="w-32 mt-1 px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <button type="submit" class="self-end bg-blue-600 text-white font-semibold px-6 py-2 rounded-lg hover:bg-blue-700 transition">Simpan Skor</button>
            </form>
        `;
        gradingContainer.appendChild(card);
    });
};

/**
 * Menangani pengiriman skor dari form penilaian.
 * @param {Event} event 
 */
const handleGradeSubmit = async (event) => {
    event.preventDefault();
    const form = event.target;
    const submissionId = form.querySelector('input[name="submission_id"]').value;
    const score = form.querySelector('input[name="score"]').value;

    try {
        const response = await fetch('api/grading.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ submission_id: submissionId, score: score })
        });

        const result = await response.json();
        if (result.success) {
            // Hapus kartu dari UI setelah berhasil dinilai
            const cardToRemove = gradingContainer.querySelector(`.grading-card[data-submission-id="${submissionId}"]`);
            if (cardToRemove) {
                cardToRemove.remove();
            }
            // Perbarui daftar esai yang belum dinilai
            pendingEssays = pendingEssays.filter(e => e.id != submissionId);
            if (pendingEssays.length === 0) {
                renderGradingCards(); // Tampilkan pesan "Tidak ada esai"
            }
        } else {
            alert(`Gagal menilai: ${result.message}`);
        }
    } catch (error) {
        console.error('Error submitting grade:', error);
        alert('Terjadi kesalahan saat menyimpan skor.');
    }
};

/**
 * Inisialisasi halaman penilaian esai.
 */
const initializeGradingPage = () => {
    fetchPendingEssays();
};

// Event delegation untuk form penilaian
gradingContainer.addEventListener('submit', (event) => {
    if (event.target.classList.contains('grading-form')) {
        handleGradeSubmit(event);
    }
});
