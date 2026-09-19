import Chart from 'chart.js/auto';
import QRCode from 'qrcode';

// PWA installability — see public/sw.js for what it does (and deliberately
// doesn't do: no caching of app content, only a friendly offline page).
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js');
    });
}

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
        hybridMode: false,
        elapsedSeconds: 0,
        error: null,
        warning: null,
        mediaRecorder: null,
        chunks: [],
        mimeType: null,
        timer: null,
        micStream: null,
        displayTracks: [],
        audioContext: null,

        // Tab-audio capture (getDisplayMedia's "Share tab audio" checkbox)
        // is only reliable on desktop Chromium — Firefox's screen-share
        // dialog doesn't offer a browser-tab option with audio, Safari isn't
        // supported for live recording at all, and no mobile browser exposes
        // getDisplayMedia to web pages (Android Chrome's user-agent still
        // contains "Chrome", so it's excluded explicitly rather than by
        // feature-detecting the function, which mobile Chrome also defines
        // but can't actually fulfil for tab capture). Gating on this keeps
        // the plain mic-only path (already proven, and the only option on
        // phones) as the fallback for anyone the mixing path can't serve.
        get hybridSupported() {
            return typeof navigator.mediaDevices?.getDisplayMedia === 'function'
                && /Chrome|Edg\//.test(navigator.userAgent)
                && !/Firefox/.test(navigator.userAgent)
                && !/Mobi|Android/i.test(navigator.userAgent);
        },

        get formattedElapsed() {
            const m = Math.floor(this.elapsedSeconds / 60).toString().padStart(2, '0');
            const s = (this.elapsedSeconds % 60).toString().padStart(2, '0');
            return `${m}:${s}`;
        },

        async start() {
            this.error = null;
            this.warning = null;
            this.uploaded = false;
            this.chunks = [];
            this.elapsedSeconds = 0;
            this.displayTracks = [];
            this.audioContext = null;

            const candidates = ['audio/webm;codecs=opus', 'audio/webm', 'audio/ogg;codecs=opus'];
            this.mimeType = candidates.find((type) => window.MediaRecorder && MediaRecorder.isTypeSupported(type)) || null;

            if (!this.mimeType) {
                this.error = 'Live recording isn\'t supported in this browser. Please use Chrome, Firefox, or Edge — or use the Upload tab instead.';
                return;
            }

            try {
                this.micStream = await navigator.mediaDevices.getUserMedia({ audio: true });
            } catch (e) {
                this.error = 'Could not access your microphone. Please allow microphone access and try again.';
                return;
            }

            let recordStream = this.micStream;

            if (this.hybridMode && this.hybridSupported) {
                recordStream = await this.mixInOnlineMeetingAudio(this.micStream);
            }

            this.mediaRecorder = new MediaRecorder(recordStream, { mimeType: this.mimeType });
            this.mediaRecorder.ondataavailable = (e) => {
                if (e.data.size > 0) this.chunks.push(e.data);
            };
            this.mediaRecorder.start();
            this.recording = true;
            this.timer = setInterval(() => this.elapsedSeconds++, 1000);
        },

        // Best-effort: captures the online meeting's audio from a shared
        // browser tab and mixes it with the room mic via the Web Audio API,
        // so both physical and online attendees end up in one recording. If
        // the user cancels the share dialog, or shares a tab/window without
        // ticking "Share tab audio", we fall back to mic-only instead of
        // failing the whole recording.
        async mixInOnlineMeetingAudio(micStream) {
            try {
                const displayStream = await navigator.mediaDevices.getDisplayMedia({ video: true, audio: true });
                const audioTracks = displayStream.getAudioTracks();

                displayStream.getVideoTracks().forEach((track) => track.stop());

                if (audioTracks.length === 0) {
                    this.warning = 'No audio was shared from the selected tab — recording microphone only. Next time, tick "Share tab audio" when choosing the meeting tab.';

                    return micStream;
                }

                this.displayTracks = audioTracks;
                this.audioContext = new AudioContext();
                const destination = this.audioContext.createMediaStreamDestination();

                // Force the mix down to mono: Google Speech-to-Text assumes
                // 1 channel for WEBM/OPUS unless told otherwise, and a plain
                // mic-only recording is already mono — a stereo destination
                // (the default here) would produce a 2-channel file the
                // backend never asks for and Google then rejects outright.
                destination.channelCount = 1;
                destination.channelCountMode = 'explicit';

                this.audioContext.createMediaStreamSource(micStream).connect(destination);
                this.audioContext.createMediaStreamSource(new MediaStream(audioTracks)).connect(destination);

                return destination.stream;
            } catch (e) {
                this.warning = 'Could not capture the online meeting audio — recording microphone only.';

                return micStream;
            }
        },

        stop() {
            if (!this.mediaRecorder) return;

            clearInterval(this.timer);
            this.recording = false;
            this.uploading = true;

            this.mediaRecorder.addEventListener('stop', () => {
                this.mediaRecorder.stream.getTracks().forEach((track) => track.stop());
                this.micStream?.getTracks().forEach((track) => track.stop());
                this.displayTracks.forEach((track) => track.stop());
                this.audioContext?.close();

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
