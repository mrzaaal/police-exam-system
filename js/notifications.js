/**
 * js/notifications.js
 * Helper untuk menampilkan notifikasi modern (toast) di seluruh aplikasi.
 */

/**
 * Menampilkan notifikasi toast.
 * @param {string} text - Pesan yang akan ditampilkan.
 * @param {string} type - Tipe notifikasi ('success', 'error', 'warning', 'info').
 */
function showToast(text, type = 'info') {
    let backgroundColor;
    switch (type) {
        case 'success':
            backgroundColor = "linear-gradient(to right, #00b09b, #96c93d)";
            break;
        case 'error':
            backgroundColor = "linear-gradient(to right, #ff5f6d, #ffc371)";
            break;
        case 'warning':
            backgroundColor = "linear-gradient(to right, #f7b733, #fc4a1a)";
            break;
        default:
            backgroundColor = "#4e54c8";
            break;
    }

    Toastify({
        text: text,
        duration: 3000,
        close: true,
        gravity: "top", // `top` or `bottom`
        position: "right", // `left`, `center` or `right`
        stopOnFocus: true, // Prevents dismissing of toast on hover
        style: {
            background: backgroundColor,
            borderRadius: "8px"
        },
    }).showToast();
}
