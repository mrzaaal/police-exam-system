/**
 * js/auth.js
 * Mengelola otentikasi pengguna: login, logout, dan manajemen sesi.
 * Versi ini terintegrasi dengan API backend dan menggunakan notifikasi modern.
 */

// --- Elemen DOM ---
const loginForm = $('#login-form');
const usernameInput = $('#username');
const passwordInput = $('#password');
const logoutBtn = $('#logout-btn');

/**
 * Menyimpan informasi sesi pengguna di sessionStorage.
 * @param {object} userData - Data pengguna yang akan disimpan.
 */
const saveUserSession = (userData) => {
    sessionStorage.setItem('currentUser', JSON.stringify(userData));
};

/**
 * Mengambil informasi sesi pengguna dari sessionStorage.
 * @returns {object|null} Data pengguna atau null jika tidak ada.
 */
const getUserSession = () => {
    const user = sessionStorage.getItem('currentUser');
    return user ? JSON.parse(user) : null;
};

/**
 * Menghapus sesi pengguna dari sessionStorage.
 */
const clearUserSession = () => {
    sessionStorage.removeItem('currentUser');
};

/**
 * Menangani proses login pengguna dengan mengirim data ke API.
 * @param {Event} event - Event submit dari form.
 */
const handleLogin = async (event) => {
    event.preventDefault();
    const username = usernameInput.value.trim();
    const password = passwordInput.value;

    try {
        const response = await fetch('api/auth.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ username, password }),
        });

        const result = await response.json();

        if (response.ok && result.success) {
            // Login berhasil
            saveUserSession(result.user);
            initializeApp(); // Panggil fungsi dari app.js untuk mengarahkan ke halaman yang sesuai
        } else {
            // Login gagal, tampilkan pesan error dari server
            showToast(result.message || 'Terjadi kesalahan.', 'error');
            loginForm.reset();
            usernameInput.focus();
        }
    } catch (error) {
        console.error('Error saat login:', error);
        // Tangani kasus di mana server mengirim error PHP (bukan JSON) atau tidak terjangkau
        showToast('Tidak dapat terhubung ke server atau terjadi error internal.', 'error');
    }
};

/**
 * Menangani proses logout.
 */
const handleLogout = () => {
    clearUserSession();
    // Muat ulang halaman untuk kembali ke halaman login
    window.location.reload(); 
};

// --- Event Listeners ---
loginForm.addEventListener('submit', handleLogin);
logoutBtn.addEventListener('click', handleLogout);

console.log("auth.js dimuat (versi API).");
