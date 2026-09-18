<?php include 'header.php'; ?>

<h2>Certificates</h2>

<!-- ============================================================
     EVENT SELECTOR
     ============================================================ -->
<div class="card mb-4">
  <div class="card-body">
    <label class="fw-bold mb-2">Select an Event</label>
    <div class="row g-2">
      <div class="col-md-8">
        <select id="eventSelect" class="form-select">
          <option value="">-- Choose an event --</option>
          <?php
            $stmt = $conn->query("SELECT id, event_name, start_date, end_date FROM events ORDER BY start_date DESC");
                while ($e = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $label = $e['start_date'] === $e['end_date']
                        ? date('M j, Y', strtotime($e['start_date']))
                        : date('M j', strtotime($e['start_date'])) . ' – ' . date('M j, Y', strtotime($e['end_date']));
                    echo '<option value="' . $e['id'] . '">'
                    . htmlspecialchars($e['event_name']) . ' (' . $label . ')</option>';
                }
          ?>
        </select>
      </div>
      <div class="col-md-4 d-flex gap-2">
        <button class="btn btn-success flex-fill" id="sendAllBtn" disabled>
          Email All
        </button>
        <button class="btn btn-outline-primary flex-fill" id="downloadAllBtn" disabled>
          Download ZIP
        </button>
      </div>
    </div>
  </div>
</div>


<!-- ============================================================
     CERTIFICATE SETTINGS (Signatory)
     ============================================================ -->
<div class="card mb-4" id="settingsPanel" style="display:none;">
  <div class="card-header d-flex justify-content-between align-items-center"
       data-bs-toggle="collapse" data-bs-target="#settingsBody"
       style="cursor:pointer;">
    <span>⚙️ Certificate Settings (Signatory)</span>
    <small class="text-muted">Click to expand</small>
  </div>
  <div id="settingsBody" class="collapse show">
    <div class="card-body">

      <form id="signatoryForm">
        <input type="hidden" name="event_id" id="sigEventId">

        <div class="row">
          <div class="col-md-5 mb-3">
            <label class="form-label fw-bold">Signatory Name</label>
            <input type="text" name="signatory_name" id="sigName"
                   class="form-control" placeholder="e.g., Dr. Maria Santos" required>
          </div>

          <div class="col-md-5 mb-3">
            <label class="form-label fw-bold">Signature Image (optional)</label>
            <input type="file" name="signature_image" id="sigImage"
                   class="form-control" accept="image/png,image/jpeg,image/gif">
            <small class="text-muted">PNG with transparent background works best.</small>
          </div>

          <div class="col-md-2 mb-3 d-flex align-items-end">
            <button type="submit" class="btn btn-primary w-100">Save</button>
          </div>
        </div>
      </form>

      <!-- Preview -->
      <div id="sigPreviewArea" class="mt-2" style="display:none;">
        <p class="mb-1 small text-muted">Current signature on file:</p>
        <img id="sigPreviewImg" src="" alt="Signature"
             style="max-height:80px; border:1px solid #dee2e6; border-radius:4px; padding:4px; background:#fff;">
      </div>

      <div id="sigStatus" class="mt-2 small"></div>

    </div>
  </div>
</div>


<!-- ============================================================
     PROGRESS BAR (bulk send)
     ============================================================ -->
<div id="progressArea" class="mb-3" style="display:none;">
  <div class="progress">
    <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated"
         style="width:0%;">0%</div>
  </div>
  <div id="progressLog" class="mt-2 small text-muted font-monospace"
       style="max-height:200px; overflow-y:auto; background:#f8f9fa; padding:8px; border-radius:4px;"></div>
</div>


<!-- ============================================================
     ATTENDEES LIST
     ============================================================ -->
<div id="attendeeArea" style="display:none;">
  <h5 class="mt-4">Attendees</h5>
  <table class="table table-bordered table-striped">
    <thead class="table-dark">
      <tr>
        <th>ID</th>
        <th>Name</th>
        <th>Email</th>
        <th>Certificate Status</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody id="attendeeTable"></tbody>
  </table>
</div>


<script>
// ============================================================
// ELEMENT REFERENCES
// ============================================================
const eventSelect    = document.getElementById('eventSelect');
const sendAllBtn     = document.getElementById('sendAllBtn');
const downloadAllBtn = document.getElementById('downloadAllBtn');
const attendeeArea   = document.getElementById('attendeeArea');
const attendeeTable  = document.getElementById('attendeeTable');
const progressArea   = document.getElementById('progressArea');
const progressBar    = document.getElementById('progressBar');
const progressLog    = document.getElementById('progressLog');

// Signatory panel
const settingsPanel  = document.getElementById('settingsPanel');
const sigForm        = document.getElementById('signatoryForm');
const sigEventId     = document.getElementById('sigEventId');
const sigName        = document.getElementById('sigName');
const sigImage       = document.getElementById('sigImage');
const sigPreviewArea = document.getElementById('sigPreviewArea');
const sigPreviewImg  = document.getElementById('sigPreviewImg');
const sigStatus      = document.getElementById('sigStatus');

let attendees = [];


// ============================================================
// EVENT CHANGE — LOAD ATTENDEES + SIGNATORY INFO
// ============================================================
eventSelect.addEventListener('change', async function () {
    const eventId = this.value;

    // Reset everything
    attendeeTable.innerHTML = '';
    attendeeArea.style.display = 'none';
    sendAllBtn.disabled = true;
    downloadAllBtn.disabled = true;
    sigStatus.innerHTML = '';
    sigPreviewArea.style.display = 'none';

    if (!eventId) {
        settingsPanel.style.display = 'none';
        return;
    }

    // ------------------------------------------------------------
    // 1. LOAD SIGNATORY INFO
    // ------------------------------------------------------------
    settingsPanel.style.display = 'block';
    sigEventId.value = eventId;

    try {
        const res  = await fetch('../process/get_signatory.php?event_id=' + encodeURIComponent(eventId));
        const data = await res.json();

        if (data.status === 'success') {
            sigName.value = data.signatory_name || '';

            if (data.signatory_signature) {
                sigPreviewImg.src = '../' + data.signatory_signature + '?t=' + Date.now();
                sigPreviewArea.style.display = 'block';
            }
        }
    } catch (err) {
        console.error('Failed to load signatory:', err);
    }

    // ------------------------------------------------------------
    // 2. LOAD ATTENDEES
    // ------------------------------------------------------------
    try {
        const res  = await fetch('../process/get_attendees.php?event_id=' + encodeURIComponent(eventId));
        const data = await res.json();

        if (data.status !== 'success') {
            alert(data.message || 'Failed to load attendees.');
            return;
        }

        attendees = data.attendees;

        if (attendees.length === 0) {
            attendeeTable.innerHTML =
                '<tr><td colspan="5" class="text-center text-muted">No attendees for this event.</td></tr>';
        } else {
            attendees.forEach(function (a) {
                const tr = document.createElement('tr');
                const statusBadge = a.sent
                    ? '<span class="badge bg-success">Sent</span>'
                    : '<span class="badge bg-secondary">Not sent</span>';
                const emailDisplay = a.email
                    ? a.email
                    : '<span class="text-danger">No email</span>';

                tr.innerHTML = `
                    <td>${a.id}</td>
                    <td>${a.name}</td>
                    <td>${emailDisplay}</td>
                    <td>${statusBadge}</td>
                    <td>
                        <button class="btn btn-sm btn-primary"
                                onclick="sendOne(${a.id}, this)"
                                ${!a.email ? 'disabled' : ''}>
                            📧 Send
                        </button>
                    </td>`;
                attendeeTable.appendChild(tr);
            });
        }

        attendeeArea.style.display = 'block';
        sendAllBtn.disabled     = attendees.length === 0;
        downloadAllBtn.disabled = attendees.length === 0;

    } catch (err) {
        console.error('Failed to load attendees:', err);
        alert('Network error while loading attendees.');
    }
});


// ============================================================
// SAVE SIGNATORY
// ============================================================
sigForm.addEventListener('submit', async function (e) {
    e.preventDefault();

    const fd = new FormData(this);
    sigStatus.innerHTML = '<span class="text-muted">Saving…</span>';

    try {
        const res = await fetch('../process/save_signatory.php', {
            method: 'POST',
            body: fd
        });
        const data = await res.json();

        if (data.status === 'success') {
            sigStatus.innerHTML = '<span class="text-success">✅ ' + data.message + '</span>';

            // Refresh preview if a new image was uploaded
            if (data.signature_path) {
                sigPreviewImg.src = '../' + data.signature_path + '?t=' + Date.now();
                sigPreviewArea.style.display = 'block';
                sigImage.value = '';
            }
        } else {
            sigStatus.innerHTML = '<span class="text-danger">❌ ' + data.message + '</span>';
        }
    } catch (err) {
        sigStatus.innerHTML = '<span class="text-danger">❌ Network error.</span>';
    }
});


// ============================================================
// SEND ONE CERTIFICATE
// ============================================================
async function sendOne(userId, btn) {
    if (btn) { btn.disabled = true; btn.textContent = '⏳ Sending...'; }

    const fd = new FormData();
    fd.append('user_id', userId);
    fd.append('event_id', eventSelect.value);

    try {
        const res  = await fetch('../process/send_certificate.php', { method: 'POST', body: fd });
        const data = await res.json();

        if (data.status === 'success') {
            if (btn) { btn.textContent = '✅ Sent'; btn.className = 'btn btn-sm btn-success'; }
            return true;
        } else {
            if (btn) { btn.textContent = '❌ Failed'; btn.className = 'btn btn-sm btn-danger'; }
            console.error(data.message);
            return false;
        }
    } catch (err) {
        if (btn) { btn.textContent = '❌ Error'; btn.className = 'btn btn-sm btn-danger'; }
        return false;
    }
}


// ============================================================
// SEND ALL (with progress bar)
// ============================================================
sendAllBtn.addEventListener('click', async function () {
    if (!confirm('Send certificates to all attendees of this event?')) return;

    const list = attendees.filter(a => a.email);
    if (list.length === 0) { alert('No attendees with emails.'); return; }

    progressArea.style.display = 'block';
    progressLog.innerHTML = '';
    sendAllBtn.disabled = true;
    downloadAllBtn.disabled = true;

    let sent = 0;

    for (let i = 0; i < list.length; i++) {
        const a = list[i];
        progressLog.innerHTML += `[${i + 1}/${list.length}] Sending to ${a.name}... `;

        const fd = new FormData();
        fd.append('user_id', a.id);
        fd.append('event_id', eventSelect.value);

        try {
            const res  = await fetch('../process/send_certificate.php', { method: 'POST', body: fd });
            const data = await res.json();

            if (data.status === 'success') {
                sent++;
                progressLog.innerHTML += `<span class="text-success">OK</span><br>`;
            } else {
                progressLog.innerHTML += `<span class="text-danger">${data.message}</span><br>`;
            }
        } catch (err) {
            progressLog.innerHTML += `<span class="text-danger">Network error</span><br>`;
        }

        progressLog.scrollTop = progressLog.scrollHeight;

        const pct = Math.round(((i + 1) / list.length) * 100);
        progressBar.style.width = pct + '%';
        progressBar.textContent = pct + '%';
    }

    progressBar.classList.remove('progress-bar-animated');
    progressBar.classList.add('bg-success');
    progressLog.innerHTML += `<hr><strong>Done: ${sent}/${list.length} sent.</strong><br>`;

    sendAllBtn.disabled = false;
    downloadAllBtn.disabled = false;

    // Refresh the attendee list so badges update
    eventSelect.dispatchEvent(new Event('change'));
});


// ============================================================
// DOWNLOAD ALL CERTIFICATES AS ZIP
// ============================================================
downloadAllBtn.addEventListener('click', function () {
    window.location.href =
        '../process/download_certificates_zip.php?event_id=' + eventSelect.value;
});
</script>

<?php include 'footer.php'; ?>