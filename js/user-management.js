/**
 * js/user-management.js
 * Mengelola logika untuk tab Manajemen Peserta di dasbor admin.
 * Fitur: CRUD Lengkap, Paginasi, Pencarian, Impor Massal, Reset Password.
 */

// --- Elemen DOM dideklarasikan di app.js ---

// --- State untuk Paginasi & Pencarian ---
let usersCurrentPage = 1;
let usersCurrentSearch = '';
let usersSearchDebounceTimer;

/**
 * Mengambil daftar peserta dari API dengan parameter paginasi dan pencarian.
 */
const fetchUsers = async () => {
    try {
        const url = `api/users_crud.php?page=${usersCurrentPage}&limit=15&search=${encodeURIComponent(usersCurrentSearch)}`;
        const response = await fetch(url);
        const result = await response.json();
        
        if (result.success) {
            renderUsersTable(result.data);
            renderUsersPagination(result.pagination);
        } else {
            usersTableBody.innerHTML = `<tr><td colspan="5" class="text-center py-4 text-red-500">${result.message}</td></tr>`;
        }
    } catch (error) {
        console.error('Error fetching users:', error);
        usersTableBody.innerHTML = `<tr><td colspan="5" class="text-center py-4 text-red-500">Gagal memuat data peserta.</td></tr>`;
    }
};

/**
 * Merender tabel peserta dengan tombol aksi.
 */
const renderUsersTable = (users) => {
    usersTableBody.innerHTML = '';
    if (users.length === 0) {
        usersTableBody.innerHTML = `<tr><td colspan="5" class="text-center py-4">Tidak ada peserta yang ditemukan.</td></tr>`;
        return;
    }
    users.forEach(user => {
        const row = `<tr>
                        <td class="px-6 py-4 font-semibold">${user.id}</td>
                        <td class="px-6 py-4">${user.username}</td>
                        <td class="px-6 py-4">${user.name}</td>
                        <td class="px-6 py-4">${user.created_at}</td>
                        <td class="px-6 py-4">
                            <button class="action-btn view reset-password-btn" data-id="${user.id}" data-name="${user.name}">Reset Password</button>
                            <button class="action-btn delete delete-user-btn" data-id="${user.id}" data-name="${user.name}">Hapus</button>
                        </td>
                    </tr>`;
        usersTableBody.innerHTML += row;
    });
};

/**
 * Merender kontrol paginasi untuk peserta.
 */
const renderUsersPagination = (pagination) => {
    const { current_page, total_pages, total_records } = pagination;
    usersPaginationControls.innerHTML = '';
    if (total_pages <= 1) {
        usersPaginationControls.innerHTML = `<div class="text-sm text-gray-500">Total ${total_records} peserta</div>`;
        return;
    }
    let html = `<div class="text-sm text-gray-500">Total ${total_records} peserta</div>`;
    html += `<div class="flex items-center"><button class="pagination-btn ${current_page === 1 ? 'opacity-50' : ''}" data-page="${current_page - 1}" ${current_page === 1 ? 'disabled' : ''}>Sebelumnya</button><span class="px-4">Halaman ${current_page} dari ${total_pages}</span><button class="pagination-btn ${current_page === total_pages ? 'opacity-50' : ''}" data-page="${current_page + 1}" ${current_page === total_pages ? 'disabled' : ''}>Berikutnya</button></div>`;
    usersPaginationControls.innerHTML = html;
};

/**
 * Menangani input pencarian peserta.
 */
const handleUserSearchInput = (event) => {
    clearTimeout(usersSearchDebounceTimer);
    usersSearchDebounceTimer = setTimeout(() => {
        usersCurrentSearch = event.target.value;
        usersCurrentPage = 1;
        fetchUsers();
    }, 500);
};

/**
 * Menangani klik pada tombol paginasi peserta.
 */
const handleUserPaginationClick = (event) => {
    const target = event.target.closest('.pagination-btn');
    if (target && !target.disabled) {
        usersCurrentPage = parseInt(target.dataset.page);
        fetchUsers();
    }
};

/**
 * Membuka modal untuk menambah peserta.
 */
const openUserModal = () => {
    userForm.reset();
    $('#user-modal-title').textContent = 'Tambah Peserta Baru';
    toggleElementVisibility(userModal, true);
};

const closeUserModal = () => toggleElementVisibility(userModal, false);

/**
 * Menangani submit form tambah peserta.
 */
const handleUserFormSubmit = async (event) => {
    event.preventDefault();
    const formData = new FormData(userForm);
    const userData = {
        username: formData.get('username'),
        name: formData.get('name'),
        email: formData.get('email')
    };
    try {
        const response = await fetch('api/users_crud.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(userData)
        });
        const result = await response.json();
        if (result.success) {
            closeUserModal();
            fetchUsers();
            showToast(result.message, 'success');
        } else {
            showToast(`Gagal: ${result.message}`, 'error');
        }
    } catch (error) { showToast('Terjadi kesalahan koneksi.', 'error'); }
};

/**
 * Menangani penghapusan peserta.
 */
const handleDeleteUser = async (userId, userName) => {
    const confirmed = await showConfirmModal('Konfirmasi Hapus', `Anda yakin ingin menghapus peserta "${userName}"? Semua data terkait (hasil ujian, dll) akan ikut terhapus.`);
    if (!confirmed) return;
    try {
        const response = await fetch(`api/users_crud.php?id=${userId}`, { method: 'DELETE' });
        const result = await response.json();
        if (result.success) {
            fetchUsers();
            showToast(result.message, 'success');
        } else { showToast(`Gagal: ${result.message}`, 'error'); }
    } catch (error) { showToast('Terjadi kesalahan koneksi.', 'error'); }
};

/**
 * Menangani permintaan reset password.
 */
const handleResetPassword = async (userId, userName) => {
    const confirmed = await showConfirmModal('Konfirmasi Reset Password', `Apakah Anda yakin ingin mereset password untuk ${userName}?`);
    if (!confirmed) return;
    try {
        const response = await fetch('api/reset_password.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ user_id: userId }) });
        const result = await response.json();
        showToast(result.message, result.success ? 'success' : 'error');
    } catch (error) {
        showToast('Terjadi kesalahan koneksi.', 'error');
    }
};

/**
 * Menangani proses impor file CSV peserta.
 */
const handleUserImport = async () => {
    const file = importUsersInput.files[0];
    if (!file) return;
    const formData = new FormData();
    formData.append('users_csv', file);
    importUsersStatus.innerHTML = `<p class="text-blue-600">Mengunggah...</p>`;
    try {
        const response = await fetch('api/import_users.php', { method: 'POST', body: formData });
        const result = await response.json();
        if (response.ok && result.success) {
            importUsersStatus.innerHTML = `<div class="p-4 bg-green-100 text-green-800 rounded-lg">${result.message}</div>`;
            fetchUsers();
        } else {
            importUsersStatus.innerHTML = `<div class="p-4 bg-red-100 text-red-800 rounded-lg">Gagal: ${result.message}</div>`;
        }
    } catch (error) {
        importUsersStatus.innerHTML = `<div class="p-4 bg-red-100 text-red-800 rounded-lg">Error koneksi.</div>`;
    } finally {
        importUsersInput.value = '';
    }
};

/**
 * Inisialisasi halaman manajemen peserta.
 */
const initializeUserManagementPage = () => {
    usersCurrentPage = 1;
    usersCurrentSearch = '';
    searchUsersInput.value = '';
    fetchUsers();
    importUsersStatus.innerHTML = '';
};

// --- Event Listeners ---
// Pastikan elemen ini ada sebelum menambahkan listener
if ($('#add-user-btn')) {
    $('#add-user-btn').addEventListener('click', openUserModal);
}
if ($('#user-modal-cancel')) {
    $('#user-modal-cancel').addEventListener('click', closeUserModal);
}
if ($('#user-form')) {
    $('#user-form').addEventListener('submit', handleUserFormSubmit);
}
if ($('#import-users-btn')) {
    $('#import-users-btn').addEventListener('click', () => importUsersInput.click());
}
if ($('#import-users-input')) {
    $('#import-users-input').addEventListener('change', handleUserImport);
}
if ($('#search-users-input')) {
    $('#search-users-input').addEventListener('input', handleUserSearchInput);
}
if ($('#users-pagination-controls')) {
    $('#users-pagination-controls').addEventListener('click', handleUserPaginationClick);
}

if ($('#users-table-body')) {
    $('#users-table-body').addEventListener('click', (event) => {
        if (event.target.classList.contains('reset-password-btn')) {
            const userId = event.target.dataset.id;
            const userName = event.target.dataset.name;
            handleResetPassword(userId, userName);
        }
        if (event.target.classList.contains('delete-user-btn')) {
            const userId = event.target.dataset.id;
            const userName = event.target.dataset.name;
            handleDeleteUser(userId, userName);
        }
    });
}
