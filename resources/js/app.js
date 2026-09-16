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

    // Live meeting recording (Meetings > New Meeting > Record tab). Produces
    // an audio Blob client-side, then feeds it into the exact same Livewire
    // $file property / save() method as the Upload tab's plain <input
    // type=file> — one processing pipeline, two ways to get the audio in.
    // Chrome/Firefox/Edge record audio/webm;codecs=opus, which Google
    // Speech-to-Text supports directly; Safari only records audio/mp4 (AAC),
    // which it doesn't — those users are pointed at the Upload tab instead.
    Alpine.data('meetingRecorder', () => ({
        recording: false,
        uploading: false,
        uploaded: false,
        elapsedSeconds: 0,
        error: null,
        mediaRecorder: null,
        chunks: [],
        mimeType: null,
        timer: null,

        get formattedElapsed() {
            const m = Math.floor(this.elapsedSeconds / 60).toString().padStart(2, '0');
            const s = (this.elapsedSeconds % 60).toString().padStart(2, '0');
            return `${m}:${s}`;
        },

        async start() {
            this.error = null;
            this.uploaded = false;
            this.chunks = [];
            this.elapsedSeconds = 0;

            const candidates = ['audio/webm;codecs=opus', 'audio/webm', 'audio/ogg;codecs=opus'];
            this.mimeType = candidates.find((type) => window.MediaRecorder && MediaRecorder.isTypeSupported(type)) || null;

            if (!this.mimeType) {
                this.error = 'Live recording isn\'t supported in this browser. Please use Chrome, Firefox, or Edge — or use the Upload tab instead.';
                return;
            }

            try {
                const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                this.mediaRecorder = new MediaRecorder(stream, { mimeType: this.mimeType });
                this.mediaRecorder.ondataavailable = (e) => {
                    if (e.data.size > 0) this.chunks.push(e.data);
                };
                this.mediaRecorder.start();
                this.recording = true;
                this.timer = setInterval(() => this.elapsedSeconds++, 1000);
            } catch (e) {
                this.error = 'Could not access your microphone. Please allow microphone access and try again.';
            }
        },

        stop() {
            if (!this.mediaRecorder) return;

            clearInterval(this.timer);
            this.recording = false;
            this.uploading = true;

            this.mediaRecorder.addEventListener('stop', () => {
                this.mediaRecorder.stream.getTracks().forEach((track) => track.stop());

                const extension = this.mimeType.includes('ogg') ? 'ogg' : 'webm';
                const file = new File(this.chunks, `recording.${extension}`, { type: this.mimeType });
                const duration = this.elapsedSeconds;

                this.$wire.set('recordedDurationSeconds', duration).then(() => {
                    this.$wire.upload(
                        'file',
                        file,
                        () => { this.uploading = false; this.uploaded = true; },
                        () => { this.uploading = false; this.error = 'Upload failed. Please try again.'; },
                        () => {},
                    );
                });
            }, { once: true });

            this.mediaRecorder.stop();
        },
    }));
});
