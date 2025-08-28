/**
 * js/audit-trail.js
 * Mengelola logika untuk tab Audit Trail di dasbor admin.
 * Fitur: Pengambilan Data Log, Tampilan Tabel.
 */

// --- DOM Elements ---
const auditTrailTableBody = $('#audit-trail-table-body');

/**
 * Mengambil data log dari API dan merendernya ke tabel.
 */
const fetchAuditTrail = async () => {
    try {
        const response = await fetch('api/get_audit_trail.php');
        const result = await response.json();
        
        if (result.success) {
            renderAuditTrailTable(result.data);
        } else {
            auditTrailTableBody.innerHTML = `<tr><td colspan="5" class="text-center py-4 text-red-500">${result.message}</td></tr>`;
        }
    } catch (error) {
        console.error('Error fetching audit trail:', error);
        auditTrailTableBody.innerHTML = `<tr><td colspan="5" class="text-center py-4 text-red-500">Gagal memuat data log.</td></tr>`;
    }
};

/**
 * Merender data log ke dalam tabel.
 * @param {Array} logs - Array objek log.
 */
const renderAuditTrailTable = (logs) => {
    auditTrailTableBody.innerHTML = '';
    if (logs.length === 0) {
        auditTrailTableBody.innerHTML = `<tr><td colspan="5" class="text-center py-4">Belum ada aktivitas yang tercatat.</td></tr>`;
        return;
    }

    logs.forEach(log => {
        const row = `
            <tr class="border-b">
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${log.timestamp}</td>
                <td class="px-6 py-4 font-medium text-gray-900">${log.username}</td>
                <td class="px-6 py-4 text-sm text-gray-600 font-semibold">${log.action}</td>
                <td class="px-6 py-4 text-sm text-gray-500">${log.details || '-'}</td>
                <td class="px-6 py-4 text-sm text-gray-500">${log.ip_address}</td>
            </tr>
        `;
        auditTrailTableBody.innerHTML += row;
    });
};

/**
 * Inisialisasi halaman audit trail (dipanggil dari admin.js).
 */
const initializeAuditTrailPage = () => {
    fetchAuditTrail();
};
