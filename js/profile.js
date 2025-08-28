/**
 * js/profile.js
 * Mengelola logika untuk halaman profil peserta.
 */

// --- DOM Elements ---
const changePasswordForm = $('#change-password-form');
const changePasswordStatus = $('#change-password-status');
const newPasswordInput = $('#new_password');
const confirmPasswordInput = $('#confirm_password');

/**
 * Menangani pengiriman form ubah password.
 * @param {Event} event
 */
const handleChangePassword = async (event) => {
    event.preventDefault();
    changePasswordStatus.textContent = 'Memproses...';
    changePasswordStatus.className = 'mt-4 text-center text-sm text-blue-600';

    const currentPassword = $('#current_password').value;
    const newPassword = newPasswordInput.value;
    const confirmPassword = confirmPasswordInput.value;

    if (newPassword !== confirmPassword) {
        changePasswordStatus.textContent = 'Konfirmasi password baru tidak cocok.';
        changePasswordStatus.className = 'mt-4 text-center text-sm text-red-600';
        return;
    }

    try {
        const response = await fetch('api/change_password.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                current_password: currentPassword,
                new_password: newPassword
            })
        });
        const result = await response.json();

        if (result.success) {
            changePasswordStatus.textContent = result.message;
            changePasswordStatus.className = 'mt-4 text-center text-sm text-green-600';
            changePasswordForm.reset();
        } else {
            changePasswordStatus.textContent = `Gagal: ${result.message}`;
            changePasswordStatus.className = 'mt-4 text-center text-sm text-red-600';
        }
    } catch (error) {
        console.error('Error changing password:', error);
        changePasswordStatus.textContent = 'Terjadi kesalahan koneksi.';
        changePasswordStatus.className = 'mt-4 text-center text-sm text-red-600';
    }
};

/**
 * Inisialisasi halaman profil.
 */
const initializeProfilePage = () => {
    changePasswordForm.reset();
    changePasswordStatus.textContent = '';
};

// --- Event Listeners ---
changePasswordForm.addEventListener('submit', handleChangePassword);
