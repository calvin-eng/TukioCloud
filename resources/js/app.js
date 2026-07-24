import QrScanner from 'qr-scanner';
window.QrScanner = QrScanner;

document.addEventListener('alpine:init', () => {
    Alpine.store('ui', { sidebarOpen: false });
});
