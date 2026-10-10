(() => {
    const start = document.getElementById('start-scan');
    if (!start) return;
    const stop = document.getElementById('stop-scan');
    const video = document.getElementById('scanner-video');
    const message = document.getElementById('scanner-message');
    const confirmation = document.getElementById('scan-confirmation');
    const confirmationTitle = document.getElementById('scan-confirmation-title');
    const confirmationName = document.getElementById('scan-confirmation-name');
    const confirmationSound = document.getElementById('scan-confirmation-sound');
    const errorSound = document.getElementById('scan-error-sound');
    const config = document.getElementById('scanner-config');
    const count = document.getElementById('today-count');
    const rows = document.getElementById('attendance-rows');
    const canvas = document.createElement('canvas');
    const context = canvas.getContext('2d', { willReadFrequently: true });
    let stream = null;
    let frame = null;
    let lastScan = 0;
    let inFlight = false;
    let starting = false;
    let heldCode = null;
    let absentSince = null;
    let confirmationTimer = null;
    let flipView = false;

    function updateCameraView() {
        video.style.transform = flipView ? 'scaleX(-1)' : 'none';
    }

    function clearConfirmation() {
        if (confirmationTimer) clearTimeout(confirmationTimer);
        confirmation.hidden = true;
        confirmation.classList.remove('check-in', 'check-out', 'scan-error');
    }

    function playConfirmationSound() {
        if (!confirmationSound) return;
        errorSound?.pause();
        confirmationSound.pause();
        confirmationSound.currentTime = 0;
        confirmationSound.play().catch(() => {});
    }

    function playMembershipErrorSound(errorText) {
        if (!errorSound || !/no membership subscription yet|membership expired on/i.test(errorText)) return;
        confirmationSound?.pause();
        errorSound.pause();
        errorSound.currentTime = 0;
        errorSound.play().catch(() => {});
    }

    function confirmScan(result) {
        clearConfirmation();
        confirmation.classList.add(result.action);
        confirmationTitle.textContent = result.action === 'check-in' ? 'CHECK-IN RECORDED' : 'CHECK-OUT RECORDED';
        confirmationName.textContent = result.record.name;
        confirmation.hidden = false;
        playConfirmationSound();
        confirmationTimer = setTimeout(clearConfirmation, 2800);
    }

    function showScanError(errorText) {
        clearConfirmation();
        confirmation.classList.add('scan-error');
        confirmationTitle.textContent = 'SCAN DENIED';
        confirmationName.textContent = errorText;
        confirmation.hidden = false;
        confirmationTimer = setTimeout(clearConfirmation, 4200);
    }

    function stopCamera() {
        clearConfirmation();
        if (frame) cancelAnimationFrame(frame);
        frame = null;
        if (stream) stream.getTracks().forEach(track => track.stop());
        stream = null;
        video.pause();
        video.srcObject = null;
        video.hidden = true;
        start.hidden = false;
        stop.hidden = true;
        heldCode = null;
        absentSince = null;
    }

    function showResult(result) {
        const record = result.record;
        if (count) count.textContent = result.today_count;
        if (!rows) return;
        document.getElementById('empty-attendance')?.remove();
        let row = document.getElementById(`attendance-${record.id}`);
        if (!row) {
            row = document.createElement('tr');
            row.id = `attendance-${record.id}`;
            rows.prepend(row);
        }
        row.replaceChildren();
        const visitor = document.createElement('td');
        const name = document.createElement('strong');
        name.textContent = record.name;
        const username = document.createElement('small');
        username.textContent = `@${record.username}`;
        visitor.append(name, username);
        const type = document.createElement('td');
        type.textContent = 'Member QR';
        const checkIn = document.createElement('td');
        checkIn.textContent = record.checked_in_at;
        const checkOut = document.createElement('td');
        checkOut.className = 'checkout-time';
        checkOut.textContent = record.checked_out_at || '—';
        row.append(visitor, type, checkIn, checkOut);
    }

    async function submitCode(code) {
        inFlight = true;
        message.textContent = 'Recording scan…';
        try {
            const response = await fetch(config.dataset.url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': config.dataset.token,
                },
                body: JSON.stringify({ qr_token: code }),
            });
            const result = await response.json();
            if (!response.ok) {
                message.textContent = result.errors?.qr_token?.[0] || result.message || 'Scan could not be recorded.';
                showScanError(message.textContent);
                playMembershipErrorSound(message.textContent);
                return;
            }
            showResult(result);
            confirmScan(result);
            message.textContent = `${result.message} Move the code out of view before scanning it again.`;
        } catch (error) {
            message.textContent = 'Could not reach the server. Check your connection and try again.';
            showScanError(message.textContent);
        } finally {
            inFlight = false;
        }
    }

    function scanFrame(time) {
        if (!stream) return;
        frame = requestAnimationFrame(scanFrame);
        if (time - lastScan < 150 || video.readyState < 2) return;
        lastScan = time;
        const width = video.videoWidth;
        const height = video.videoHeight;
        if (!width || !height || !context) return;
        canvas.width = width;
        canvas.height = height;
        context.drawImage(video, 0, 0, width, height);
        const pixels = context.getImageData(0, 0, width, height);
        let found = window.jsQR(pixels.data, width, height, { inversionAttempts: 'attemptBoth' });
        if (!found) {
            context.save();
            context.translate(width, 0);
            context.scale(-1, 1);
            context.drawImage(video, 0, 0, width, height);
            context.restore();
            const flippedPixels = context.getImageData(0, 0, width, height);
            found = window.jsQR(flippedPixels.data, width, height, { inversionAttempts: 'attemptBoth' });
        }
        const code = found?.data || null;

        if (!code) {
            if (heldCode && absentSince === null) absentSince = time;
            if (heldCode && time - absentSince >= 3000) {
                heldCode = null;
                absentSince = null;
                if (!inFlight) message.textContent = 'Ready for the next scan.';
            }
            return;
        }
        if (code === heldCode || inFlight) {
            absentSince = null;
            return;
        }
        heldCode = code;
        absentSince = null;
        submitCode(code);
    }

    async function startCamera() {
        if (starting || stream) return;
        starting = true;
        start.hidden = true;
        message.textContent = 'Starting camera…';
        if (typeof window.jsQR !== 'function') {
            message.textContent = 'QR scanner did not load. Refresh the page and try again.';
            start.hidden = false;
            starting = false;
            return;
        }
        if (!navigator.mediaDevices?.getUserMedia) {
            message.textContent = 'Camera access is unavailable in this browser. Open this page in Chrome or Edge.';
            start.hidden = false;
            starting = false;
            return;
        }
        let cameraTimer = null;
        try {
            let timedOut = false;
            const cameraRequest = navigator.mediaDevices.getUserMedia({ video: { facingMode: { exact: 'environment' } }, audio: false })
                .catch(error => {
                    if (error.name !== 'OverconstrainedError' && error.name !== 'NotFoundError') throw error;
                    return navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false });
                });
            cameraRequest.then(lateStream => {
                if (timedOut) lateStream.getTracks().forEach(track => track.stop());
            }).catch(() => {});
            stream = await Promise.race([
                cameraRequest,
                new Promise((_, reject) => { cameraTimer = setTimeout(() => {
                    timedOut = true;
                    reject(new Error('Camera request timed out'));
                }, 10000); }),
            ]);
            video.srcObject = stream;
            flipView = stream.getVideoTracks()[0]?.getSettings().facingMode === 'user';
            updateCameraView();
            video.hidden = false;
            await video.play();
            start.hidden = true;
            stop.hidden = false;
            message.textContent = '';
            frame = requestAnimationFrame(scanFrame);
        } catch (error) {
            stopCamera();
            message.textContent = error?.name === 'NotAllowedError'
                ? 'Camera permission was denied. Allow camera access in your browser, then select Retry camera.'
                : 'Camera did not start. Allow camera access or open this page in Chrome or Edge, then select Retry camera.';
        } finally {
            if (cameraTimer) clearTimeout(cameraTimer);
            starting = false;
        }
    }

    start.addEventListener('click', startCamera);

    stop.addEventListener('click', () => {
        stopCamera();
        message.textContent = 'Camera is off.';
        start.textContent = 'Start camera';
    });
    window.addEventListener('pagehide', stopCamera);
    startCamera();
})();
