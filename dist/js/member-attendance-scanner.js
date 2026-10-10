(() => {
    const start = document.getElementById('member-start-scan');
    if (!start) return;
    const stop = document.getElementById('member-stop-scan');
    const video = document.getElementById('member-scanner-video');
    const message = document.getElementById('member-scan-message');
    const confirmation = document.getElementById('member-scan-confirmation');
    const confirmationTitle = document.getElementById('member-scan-title');
    const config = document.getElementById('member-scan-config');
    const canvas = document.createElement('canvas');
    const context = canvas.getContext('2d', { willReadFrequently: true });
    let stream = null;
    let frame = null;
    let lastScan = 0;
    let submitting = false;

    function stopCamera() {
        if (frame) cancelAnimationFrame(frame);
        frame = null;
        if (stream) stream.getTracks().forEach(track => track.stop());
        stream = null;
        video.pause();
        video.srcObject = null;
        video.hidden = true;
        start.hidden = false;
        stop.hidden = true;
    }

    async function record(code) {
        submitting = true;
        message.textContent = 'Recording scan…';
        try {
            const response = await fetch(config.dataset.url, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': config.dataset.token },
                body: JSON.stringify({ gym_code: code }),
            });
            const result = await response.json();
            if (!response.ok) {
                message.textContent = result.errors?.gym_code?.[0] || result.message || 'Scan could not be recorded.';
                stopCamera();
                return;
            }
            stopCamera();
            confirmation.className = `scan-confirmation ${result.action}`;
            confirmationTitle.textContent = result.action === 'check-in' ? 'CHECK-IN RECORDED' : 'CHECK-OUT RECORDED';
            confirmation.hidden = false;
            message.textContent = result.message;
            setTimeout(() => window.location.reload(), 1800);
        } catch (error) {
            stopCamera();
            message.textContent = 'Could not reach the server. Check your connection and try again.';
        } finally {
            submitting = false;
        }
    }

    function scanFrame(time) {
        if (!stream) return;
        frame = requestAnimationFrame(scanFrame);
        if (submitting || time - lastScan < 160 || video.readyState < 2 || !context) return;
        lastScan = time;
        const width = video.videoWidth;
        const height = video.videoHeight;
        if (!width || !height) return;
        canvas.width = width;
        canvas.height = height;
        context.drawImage(video, 0, 0, width, height);
        const found = window.jsQR(context.getImageData(0, 0, width, height).data, width, height, { inversionAttempts: 'attemptBoth' });
        if (found?.data?.startsWith('DG-GYM:')) record(found.data);
    }

    start.addEventListener('click', async () => {
        confirmation.hidden = true;
        if (typeof window.jsQR !== 'function' || !navigator.mediaDevices?.getUserMedia) {
            message.textContent = 'Camera scanning is unavailable in this browser. Try Chrome or Edge.';
            return;
        }
        start.hidden = true;
        message.textContent = 'Starting camera…';
        try {
            stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false });
            video.srcObject = stream;
            video.hidden = false;
            await video.play();
            stop.hidden = false;
            message.textContent = 'Point your camera at the gym QR code.';
            frame = requestAnimationFrame(scanFrame);
        } catch (error) {
            stopCamera();
            message.textContent = 'Camera did not start. Allow camera access and try again.';
        }
    });
    stop.addEventListener('click', () => { stopCamera(); message.textContent = 'Camera is off.'; });
    window.addEventListener('pagehide', stopCamera);
})();
