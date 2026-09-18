<?php
include '../config/db.php';

$event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;
if ($event_id <= 0) {
    die("Please select a valid event.");
}

$stmt = $conn->prepare("SELECT * FROM events WHERE id = ?");
$stmt->execute([$event_id]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    die("Event not found.");
}

if ($event['start_date'] === $event['end_date']) {
    $dateLabel = date('M j, Y', strtotime($event['start_date']));
} else {
    $dateLabel = date('M j', strtotime($event['start_date']))
               . ' – '
               . date('M j, Y', strtotime($event['end_date']));
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Scan Attendance — <?= htmlspecialchars($event['event_name']) ?></title>

<link rel="stylesheet" href="../assets/css/bootstrap.min.css">
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

<style>
    #reader { width: 100%; max-width: 480px; margin: auto; border-radius: 10px; overflow: hidden; }
    #reader video { border-radius: 10px; }
    #result { display: none; text-align: center; font-size: 16px; }
    .new-scan { background-color: #d4edda !important; }
    #gps-status { font-size: 14px; color: #555; }

    .mode-tabs .nav-link { cursor: pointer; }

    #externalInput {
        font-size: 22px; padding: 14px; text-align: center;
        font-family: monospace; letter-spacing: 2px;
        border: 3px solid #0d6efd; border-radius: 8px;
    }
    .scanner-ready {
        display: inline-block; padding: 4px 12px;
        background: #d1e7dd; color: #0f5132;
        border-radius: 12px; font-size: 12px;
    }
    .format-badge {
        display: inline-block; padding: 2px 8px;
        background: #e9ecef; border-radius: 12px;
        font-size: 11px; margin: 2px;
    }
    .upload-zone {
        border: 2px dashed #0d6efd; border-radius: 10px;
        padding: 30px; text-align: center; background: #f8f9ff;
        transition: all 0.2s; cursor: pointer;
    }
    .upload-zone:hover { background: #eef3ff; }
    .upload-zone.drag { background: #dbe7ff; border-color: #0a58ca; }
</style>
</head>

<body class="bg-light">

<div class="container py-4">

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-0">Scan Attendance</h4>
        <small class="text-muted">
            <strong><?= htmlspecialchars($event['event_name']) ?></strong> · <?= $dateLabel ?>
        </small>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-body">

        <!-- GPS status -->
        <div id="gps-status" class="mb-3 text-center">📍 Checking GPS status...</div>

        <!-- MODE TABS -->
        <ul class="nav nav-pills nav-fill mb-3 mode-tabs" role="tablist">
            <li class="nav-item"><a class="nav-link active" data-mode="camera">📷 Camera</a></li>
            <li class="nav-item"><a class="nav-link" data-mode="upload">📁 Upload Image</a></li>
            <li class="nav-item"><a class="nav-link" data-mode="external">🔌 External Scanner</a></li>
            <li class="nav-item"><a class="nav-link" data-mode="manual">⌨️ Manual</a></li>
        </ul>

        <!-- CAMERA MODE -->
        <div id="mode-camera" class="mode-panel">
            <div id="reader"></div>
            <div class="d-flex justify-content-center gap-2 mt-3 flex-wrap">
                <button type="button" class="btn btn-outline-primary btn-sm" id="btnSwitchCamera">🔄 Switch Camera</button>
                <button type="button" class="btn btn-outline-primary btn-sm" id="btnTorch">🔦 Torch</button>
            </div>
        </div>

        <!-- UPLOAD IMAGE MODE -->
        <div id="mode-upload" class="mode-panel" style="display:none;">
            <div class="alert alert-light border small">
                <strong>How to use:</strong> Take a photo of the QR code with your phone, or upload an existing image.
                The system will decode the QR from the image.
            </div>

            <div class="upload-zone" id="uploadZone">
                <div style="font-size:40px;">📁</div>
                <p class="mb-2">Click to select an image, or drag a file here</p>
                <small class="text-muted">PNG, JPG, or GIF — max 5 MB</small>
                <input type="file" id="uploadInput" accept="image/*" style="display:none;">
            </div>

            <div id="uploadPreview" class="mt-3 text-center" style="display:none;">
                <img id="previewImg" src="" alt="Preview" style="max-width:300px; max-height:300px; border:1px solid #dee2e6; border-radius:8px;">
                <div class="mt-2">
                    <button class="btn btn-primary btn-sm" id="decodeBtn">🔍 Decode QR</button>
                    <button class="btn btn-secondary btn-sm" id="clearUploadBtn">✖ Clear</button>
                </div>
            </div>
        </div>

        <!-- EXTERNAL SCANNER MODE -->
        <div id="mode-external" class="mode-panel" style="display:none;">
            <div class="text-center mb-3">
                <span class="scanner-ready" id="scannerReadyBadge">● Ready — Waiting for scan</span>
            </div>
            <div class="alert alert-light border small">
                Plug in your USB/Bluetooth scanner. It acts as a keyboard — scan and press Enter.
            </div>
            <input type="text" id="externalInput" class="form-control"
                   placeholder="Scan here…" autocomplete="off" autofocus>
        </div>

        <!-- MANUAL MODE -->
        <div id="mode-manual" class="mode-panel" style="display:none;">
            <div class="alert alert-light border small">Type the participant ID and click Submit.</div>
            <div class="input-group">
                <input type="text" id="manualInput" class="form-control" placeholder="Enter participant ID" autocomplete="off">
                <button class="btn btn-primary" id="manualSubmitBtn">Submit</button>
            </div>
        </div>

        <!-- RESULT -->
        <div id="result" class="mt-3 alert alert-info"></div>

    </div>
</div>

<!-- Recent attendance -->
<div class="card shadow">
    <div class="card-body">
        <h6 class="mb-3">Recent Attendance</h6>
        <table class="table table-sm mb-0">
            <thead><tr><th>Name</th><th>Time</th></tr></thead>
            <tbody id="attendanceBody"></tbody>
        </table>
    </div>
</div>

</div>

<audio id="beep" src="../assets/sounds/beep.mp3" preload="auto"></audio>

<script>
const EVENT_ID = <?= $event_id ?>;

// Only QR_CODE — keeps the decoder fast and reliable
const QR_FORMATS = ['QR_CODE'];

let scanner        = null;
let fileScanner    = null;
let currentCameraId = null;
let availableCameras = [];
let torchOn        = false;
let currentMode    = 'camera';

// ============================================================
// MODE SWITCHING
// ============================================================
document.querySelectorAll('.mode-tabs .nav-link').forEach(tab => {
    tab.addEventListener('click', function (e) {
        e.preventDefault();
        switchMode(this.dataset.mode);
    });
});

async function switchMode(mode) {
    currentMode = mode;

    document.querySelectorAll('.mode-tabs .nav-link').forEach(t => t.classList.remove('active'));
    document.querySelector(`.mode-tabs .nav-link[data-mode="${mode}"]`).classList.add('active');

    document.querySelectorAll('.mode-panel').forEach(p => p.style.display = 'none');
    document.getElementById('mode-' + mode).style.display = 'block';

    // Stop camera when leaving camera mode
    if (mode !== 'camera' && scanner) {
        try { await scanner.stop(); } catch (e) {}
        scanner = null;
    }

    // Start camera when entering camera mode
    if (mode === 'camera') {
        await startCameraScanner();
    }

    if (mode === 'external') {
        setTimeout(() => document.getElementById('externalInput').focus(), 100);
    }

    if (mode === 'manual') {
        setTimeout(() => document.getElementById('manualInput').focus(), 100);
    }
}

// ============================================================
// CAMERA SCANNER
// ============================================================
async function startCameraScanner() {
    const readerDiv = document.getElementById('reader');
    readerDiv.innerHTML = '';

    if (scanner) {
        try { await scanner.clear(); } catch (e) {}
        scanner = null;
    }

    scanner = new Html5Qrcode("reader", { verbose: false });

    const config = {
        fps: 10,
        qrbox: { width: 250, height: 250 },
        aspectRatio: 1.0,
        formatsToSupport: undefined, // let ZXing auto-detect; specifying breaks some devices
        experimentalFeatures: {
            useBarCodeDetectorIfSupported: true
        }
    };

    const cameraConfig = currentCameraId
        ? { deviceId: { exact: currentCameraId } }
        : { facingMode: "environment" };

    try {
        await scanner.start(cameraConfig, config, onCameraScanSuccess, () => {});
    } catch (err) {
        console.error('[camera] start failed:', err);
        // Try fallback: any camera
        try {
            await scanner.start({ facingMode: "user" }, config, onCameraScanSuccess, () => {});
        } catch (err2) {
            readerDiv.innerHTML = `
                <div class="alert alert-danger m-3">
                    <strong>Camera access failed</strong><br>
                    ${err.message || 'Permission denied or no camera available.'}<br><br>
                    <small>Try <strong>Upload Image</strong>, <strong>External Scanner</strong>, or <strong>Manual</strong> mode.</small>
                </div>`;
        }
    }
}

async function onCameraScanSuccess(decodedText) {
    try { scanner.pause(true); } catch (e) {}
    await handleScannedCode(decodedText, 'QR_CODE');
    setTimeout(() => { try { scanner.resume(); } catch (e) {} }, 2500);
}

// ============================================================
// UPLOAD IMAGE MODE
// ============================================================
const uploadZone   = document.getElementById('uploadZone');
const uploadInput  = document.getElementById('uploadInput');
const uploadPreview = document.getElementById('uploadPreview');
const previewImg   = document.getElementById('previewImg');

uploadZone.addEventListener('click', () => uploadInput.click());

uploadZone.addEventListener('dragover', (e) => {
    e.preventDefault();
    uploadZone.classList.add('drag');
});
uploadZone.addEventListener('dragleave', () => uploadZone.classList.remove('drag'));
uploadZone.addEventListener('drop', (e) => {
    e.preventDefault();
    uploadZone.classList.remove('drag');
    if (e.dataTransfer.files.length > 0) {
        handleUploadedFile(e.dataTransfer.files[0]);
    }
});

uploadInput.addEventListener('change', function () {
    if (this.files.length > 0) handleUploadedFile(this.files[0]);
});

function handleUploadedFile(file) {
    if (!file.type.startsWith('image/')) {
        alert('Please select an image file.');
        return;
    }
    if (file.size > 5 * 1024 * 1024) {
        alert('File too large. Max 5 MB.');
        return;
    }

    const reader = new FileReader();
    reader.onload = function (e) {
        previewImg.src = e.target.result;
        uploadPreview.style.display = 'block';
        uploadZone.style.display = 'none';
    };
    reader.readAsDataURL(file);
}

document.getElementById('clearUploadBtn').addEventListener('click', function () {
    uploadInput.value = '';
    previewImg.src = '';
    uploadPreview.style.display = 'none';
    uploadZone.style.display = 'block';
});

document.getElementById('decodeBtn').addEventListener('click', async function () {
    if (!previewImg.src) return;

    this.disabled = true;
    this.textContent = '⏳ Decoding...';

    try {
        if (!fileScanner) {
            fileScanner = new Html5Qrcode("reader", { verbose: false });
        }

        const decodedText = await fileScanner.scanFileV2(
            document.querySelector('#uploadInput').files[0] || previewImg.src,
            true
        );

        await handleScannedCode(decodedText.decodedText || decodedText, 'QR_IMAGE');

    } catch (err) {
        const resultDiv = document.getElementById('result');
        resultDiv.style.display = 'block';
        resultDiv.className = 'mt-3 alert alert-warning';
        resultDiv.innerHTML = `⚠️ Could not decode QR from image. Make sure the image is clear and contains a QR code.`;
    } finally {
        this.disabled = false;
        this.textContent = '🔍 Decode QR';
    }
});

// ============================================================
// EXTERNAL SCANNER MODE
// ============================================================
const externalInput = document.getElementById('externalInput');

externalInput.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' || e.keyCode === 13) {
        e.preventDefault();
        const value = this.value.trim();
        if (!value) return;
        this.value = '';
        handleScannedCode(value, 'EXTERNAL');
    }
});

externalInput.addEventListener('input', function () {
    const badge = document.getElementById('scannerReadyBadge');
    if (this.value.length > 0) {
        badge.style.background = '#fff3cd';
        badge.style.color = '#664d03';
        badge.textContent = '● Reading: ' + this.value;
    } else {
        badge.style.background = '#d1e7dd';
        badge.style.color = '#0f5132';
        badge.textContent = '● Ready — Waiting for scan';
    }
});

setInterval(() => {
    if (currentMode === 'external' && document.activeElement !== externalInput) {
        externalInput.focus();
    }
}, 500);

// ============================================================
// MANUAL MODE
// ============================================================
document.getElementById('manualSubmitBtn').addEventListener('click', function () {
    const input = document.getElementById('manualInput');
    const value = input.value.trim();
    if (!value) return;
    input.value = '';
    handleScannedCode(value, 'MANUAL');
});

document.getElementById('manualInput').addEventListener('keydown', function (e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        document.getElementById('manualSubmitBtn').click();
    }
});

// ============================================================
// CORE HANDLER
// ============================================================
async function handleScannedCode(rawCode, format) {
    const resultDiv = document.getElementById('result');
    resultDiv.style.display = 'block';
    resultDiv.className = 'mt-3 alert alert-info';
    resultDiv.innerHTML = `📷 <strong>${format}</strong> — getting location...`;

    const userId = extractUserId(rawCode);
    if (!userId) {
        resultDiv.className = 'mt-3 alert alert-danger';
        resultDiv.innerHTML = `❌ Invalid code: <code>${escapeHtml(rawCode)}</code>`;
        return;
    }

    try {
        const loc = await getLocation();

        const fd = new FormData();
        fd.append('user_id',   userId);
        fd.append('event_id',  EVENT_ID);
        fd.append('latitude',  loc.latitude);
        fd.append('longitude', loc.longitude);
        fd.append('accuracy',  loc.accuracy);
        fd.append('barcode_format', format);

        const res  = await fetch('../process/save_attendance.php', { method: 'POST', body: fd });
        const data = await res.json();

        displayResult(data, format, rawCode);

    } catch (err) {
        resultDiv.className = 'mt-3 alert alert-warning';
        resultDiv.innerHTML = `⚠️ ${err}`;
    }
}

function extractUserId(raw) {
    if (!raw) return null;
    if (/^\d+$/.test(raw)) return raw;
    try {
        const url = new URL(raw);
        const id = url.searchParams.get('user_id');
        if (id) return id;
    } catch (e) {}
    const m1 = raw.match(/USER[_:]?(\d+)/i);
    if (m1) return m1[1];
    const m2 = raw.match(/user_id=(\d+)/i);
    if (m2) return m2[1];
    const m3 = raw.match(/^(\d+)/);
    if (m3) return m3[1];
    return raw;
}

function displayResult(data, format, rawCode) {
    const resultDiv = document.getElementById('result');
    const badge = `<span class="format-badge">${format}</span>`;
    const raw = `<div class="text-muted small mt-1">Scanned: <code>${escapeHtml(rawCode)}</code></div>`;

    if (data.status === 'check_in') {
        document.getElementById('beep').play();
        resultDiv.className = 'mt-3 alert alert-success';
        resultDiv.innerHTML = `<b>✅ CHECK-IN</b> ${badge}<br>
            <strong>${escapeHtml(data.name)}</strong><br>
            ${escapeHtml(data.event)}<br>
            Time: ${data.time}${raw}`;
        loadAttendance(data.user_id);
    } else if (data.status === 'check_out') {
        document.getElementById('beep').play();
        resultDiv.className = 'mt-3 alert alert-primary';
        resultDiv.innerHTML = `<b>👋 CHECK-OUT</b> ${badge}<br>
            <strong>${escapeHtml(data.name)}</strong><br>
            ${escapeHtml(data.event)}<br>
            In: ${data.time_in}<br>
            Out: ${data.time_out}<br>
            Duration: ${data.duration}${raw}`;
        loadAttendance(data.user_id);
    } else if (data.status === 'not_eligible') {
        resultDiv.className = 'mt-3 alert alert-warning';
        resultDiv.innerHTML = `<b>🚫 Not Eligible</b> ${badge}<br>${escapeHtml(data.message)}${raw}`;
    } else if (data.status === 'out_of_range') {
        resultDiv.className = 'mt-3 alert alert-warning';
        resultDiv.innerHTML = `<b>🚫 Out of Range</b> ${badge}<br>${escapeHtml(data.message)}${raw}`;
    } else if (data.status === 'duplicate') {
        resultDiv.className = 'mt-3 alert alert-danger';
        resultDiv.innerHTML = `<b>⚠️ Duplicate</b> ${badge}<br>${escapeHtml(data.message)}${raw}`;
    } else {
        resultDiv.className = 'mt-3 alert alert-warning';
        resultDiv.innerHTML = `<b>${escapeHtml(data.status || 'Error')}</b><br>${escapeHtml(data.message || 'Unknown error')}${raw}`;
    }
}

function getLocation() {
    return new Promise((resolve, reject) => {
        if (!navigator.geolocation) { reject("Geolocation not supported."); return; }
        navigator.geolocation.getCurrentPosition(
            pos => resolve({
                latitude:  pos.coords.latitude,
                longitude: pos.coords.longitude,
                accuracy:  pos.coords.accuracy
            }),
            err => reject("Please enable GPS. Location is required."),
            { enableHighAccuracy: true, timeout: 8000, maximumAge: 0 }
        );
    });
}

function loadAttendance(newUserId = null) {
    fetch("../process/get_recent_attendance.php?event_id=" + EVENT_ID)
        .then(res => res.json())
        .then(data => {
            const tbody = document.getElementById("attendanceBody");
            tbody.innerHTML = "";
            data.forEach(row => {
                const tr = document.createElement("tr");
                tr.innerHTML = `<td>${escapeHtml(row.name)}</td><td>${row.scan_time}</td>`;
                if (newUserId && row.user_id == newUserId) {
                    tr.classList.add("new-scan");
                    setTimeout(() => tr.classList.remove("new-scan"), 3000);
                }
                tbody.appendChild(tr);
            });
        });
}

function escapeHtml(str) {
    return String(str ?? '').replace(/[&<>"']/g, m => ({
        '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
    }[m]));
}

// ============================================================
// CAMERA CONTROLS
// ============================================================
async function listCameras() {
    try {
        availableCameras = await Html5Qrcode.getCameras();
        if (availableCameras.length > 0) {
            const back = availableCameras.find(c => /back|rear|environment/i.test(c.label));
            currentCameraId = back ? back.id : availableCameras[0].id;
        }
    } catch (e) { console.warn('Camera list failed:', e); }
}

document.getElementById('btnSwitchCamera').addEventListener('click', async function () {
    if (availableCameras.length < 2) { alert('Only one camera detected.'); return; }
    const idx = availableCameras.findIndex(c => c.id === currentCameraId);
    currentCameraId = availableCameras[(idx + 1) % availableCameras.length].id;
    await startCameraScanner();
});

document.getElementById('btnTorch').addEventListener('click', async function () {
    try {
        torchOn = !torchOn;
        await scanner.applyVideoConstraints({ advanced: [{ torch: torchOn }] });
    } catch (e) { alert('Torch not supported.'); }
});

// ============================================================
// INIT
// ============================================================
async function init() {
    try {
        const loc = await getLocation();
        document.getElementById('gps-status').innerText =
            `📍 GPS Ready — (±${Math.round(loc.accuracy)}m)`;
        document.getElementById('gps-status').style.color = 'green';
    } catch (err) {
        document.getElementById('gps-status').innerText = '⚠️ ' + err;
        document.getElementById('gps-status').style.color = 'red';
    }

    await listCameras();
    await startCameraScanner();
    loadAttendance();
}

init();
</script>

</body>
</html>5
