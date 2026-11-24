// ── SHARED UTILITIES ─────────────────────────

// XSS escape
function escHtml(s) {
    return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

// Date formatter for Asia/Jakarta — use Date(year, month-1, day) to avoid UTC shift
function formatDate(str) {
    if (!str) return '–';
    const parts = str.split('-');
    if (parts.length !== 3) return '–';
    const d = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
    return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}

// Alias for laporan.js (same logic)
function formatDateLaporan(str) {
    return formatDate(str);
}

// Get CSRF token from meta tag
function csrfToken() {
    const m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.content : '';
}

function getToday() {
    return document.body.dataset.today || '';
}

// Toast notification
function showToast(msg, type = 'success') {
    const container = document.getElementById('toastContainer');
    if (!container) return;
    const t = document.createElement('div');
    t.className = 'toast toast-' + type;
    t.innerHTML = '<span class="toast-ico">' + (type === 'success' ? '✅' : '❌') + '</span><span>' + msg + '</span>';
    container.appendChild(t);
    setTimeout(() => {
        t.style.opacity = '0';
        t.style.transform = 'translateX(60px)';
        t.style.transition = 'all .3s';
        setTimeout(() => t.remove(), 320);
    }, 3200);
}
