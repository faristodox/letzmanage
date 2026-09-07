import Chart from 'chart.js/auto';
import QRCode from 'qrcode';

// Registered for the <x-charts.card> Blade component — one Chart.js instance
// per card, initialized fresh whenever Livewire swaps in new chart data
// (the wrapping element is wire:ignore + keyed on a hash of the data).
document.addEventListener('alpine:init', () => {
    Alpine.data('chartCard', (type, labels, data) => ({
        chart: null,

        init() {
            this.chart = new Chart(this.$refs.canvas, {
                type,
                data: {
                    labels,
                    datasets: [{
                        data,
                        backgroundColor: [
                            '#6366f1', '#8b5cf6', '#06b6d4', '#10b981',
                            '#f59e0b', '#ef4444', '#ec4899', '#84cc16',
                        ],
                    }],
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { position: type === 'pie' ? 'bottom' : 'none' },
                    },
                    scales: type === 'bar' ? { y: { beginAtZero: true, ticks: { precision: 0 } } } : {},
                },
            });
        },
    }));

    // Registered for the event-day check-in QR block in the form Builder.
    // Encodes only the check-in URL (with a ?src=qr marker so a scan can be
    // told apart from the plain copied link) — no participant data involved.
    Alpine.data('qrCanvas', (url) => ({
        init() {
            QRCode.toCanvas(this.$refs.canvas, url, { width: 200, margin: 1 });
        },

        download() {
            const link = document.createElement('a');
            link.download = 'checkin-qr.png';
            link.href = this.$refs.canvas.toDataURL('image/png');
            link.click();
        },
    }));
});
