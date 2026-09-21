<?php include 'header.php'; ?>

<h2>Participants</h2>

<?php if (isset($_SESSION['import_result'])): ?>
    <div class="alert alert-<?= $_SESSION['import_result']['type'] ?> alert-dismissible fade show">
        <?= $_SESSION['import_result']['message'] ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['import_result']); ?>
<?php endif; ?>

<!-- ACTION BUTTONS -->
<div class="mb-3">
    <a href="#" class="btn btn-primary" data-bs-toggle="collapse" data-bs-target="#addUserForm">
        Add Participant
    </a>
    <a href="#" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#importModal">
        📥 Import
    </a>
    <button class="btn btn-outline-info" id="sendAllQrBtn" disabled>
        📧 Email QR to Filtered
    </button>
    <button class="btn btn-outline-primary" id="downloadQrZipBtn" disabled>
        ⬇️ Download QR ZIP
    </button>
</div>

<!-- ADD PARTICIPANT FORM -->
<div class="collapse mb-4" id="addUserForm">
    <div class="card card-body">
        <form action="../process/save_user.php" method="POST"
              data-confirm-form
              data-confirm="Save this participant?"
              data-confirm-message="The participant will be added to the database."
              data-confirm-button="Save"
              data-confirm-color="success">

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label>Name</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control">
                </div>
            </div>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label>Role</label>
                    <select name="role" class="form-control">
                        <option>Student</option>
                        <option>Faculty</option>
                        <option>Guest</option>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label>Department</label>
                    <select name="department" class="form-control">
                        <option value="">-- None --</option>
                        <option>CICS</option>
                        <option>CIT</option>
                        <option>CTED</option>
                        <option>ADMIN</option>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label>Year Level</label>
                    <select name="year_level" class="form-control">
                        <option value="">-- None --</option>
                        <option>1st Year</option><option>2nd Year</option>
                        <option>3rd Year</option><option>4th Year</option>
                        <option>5th Year</option><option>Graduate</option>
                        <option>Faculty</option>
                    </select>
                </div>
            </div>
            <button type="submit" name="save" class="btn btn-success">Save</button>
        </form>
    </div>
</div>

<!-- IMPORT MODAL -->
<div class="modal fade" id="importModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form action="../process/import_users.php" method="POST" enctype="multipart/form-data">
        <div class="modal-header">
          <h5 class="modal-title">📥 Import Participants</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <a href="../process/download_template.php" class="btn btn-outline-primary btn-sm">
              ⬇️ Download Template (CSV)
            </a>
          </div>
          <hr>
          <label class="fw-bold">Upload filled CSV</label>
          <input type="file" name="csv_file" class="form-control" accept=".csv,text/csv" required>
          <div class="alert alert-light border mt-3 mb-0 small">
            <strong>Columns:</strong> <code>name, role, department, year_level, email</code><br>
            <strong>Departments:</strong> <code>CICS, CIT, CTED, ADMIN</code>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success">Import</button>
        </div>
      </form>
    </div>
  </div>
</div>


<?php
$q     = trim($_GET['q'] ?? '');
$fRole = trim($_GET['role'] ?? '');
$fDept = trim($_GET['department'] ?? '');
$fYear = trim($_GET['year_level'] ?? '');

$sql = "SELECT u.*,
        (SELECT COUNT(*) FROM qr_sent q
         WHERE q.user_id = u.id AND q.status = 'sent') AS qr_sent
        FROM users u WHERE 1=1";
$params = [];

if ($q !== '') {
    $sql .= " AND (u.name LIKE ? OR u.email LIKE ?)";
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}
if ($fRole !== '') { $sql .= " AND u.role = ?";       $params[] = $fRole; }
if ($fDept !== '') { $sql .= " AND u.department = ?"; $params[] = $fDept; }
if ($fYear !== '') { $sql .= " AND u.year_level = ?"; $params[] = $fYear; }

$sql .= " ORDER BY u.name ASC";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalResults = count($users);
$hasFilters   = ($q !== '' || $fRole !== '' || $fDept !== '' || $fYear !== '');

$roles       = ['Student', 'Faculty', 'Guest'];
$departments = ['CICS', 'CIT', 'CTED', 'ADMIN'];
$yearLevels  = ['1st Year','2nd Year','3rd Year','4th Year','5th Year','Graduate','Faculty'];
?>

<!-- SEARCH + FILTER -->
<div class="card mb-3 shadow-sm">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small mb-1">Search</label>
                <div class="input-group">
                    <span class="input-group-text">🔍</span>
                    <input type="text" name="q" class="form-control"
                           placeholder="Search by name or email..."
                           value="<?= htmlspecialchars($q) ?>">
                </div>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Role</label>
                <select name="role" class="form-select">
                    <option value="">All</option>
                    <?php foreach ($roles as $r): ?>
                        <option <?= $fRole === $r ? 'selected' : '' ?>><?= $r ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Department</label>
                <select name="department" class="form-select">
                    <option value="">All</option>
                    <?php foreach ($departments as $d): ?>
                        <option <?= $fDept === $d ? 'selected' : '' ?>><?= $d ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Year Level</label>
                <select name="year_level" class="form-select">
                    <option value="">All</option>
                    <?php foreach ($yearLevels as $y): ?>
                        <option <?= $fYear === $y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-fill">Apply</button>
                <?php if ($hasFilters): ?>
                    <a href="users.php" class="btn btn-outline-secondary">✖</a>
                <?php endif; ?>
            </div>
        </form>
        <div class="mt-2 small text-muted">
            Showing <strong><?= $totalResults ?></strong> participant<?= $totalResults === 1 ? '' : 's' ?>
            <?= $hasFilters ? '(filtered)' : '' ?>
        </div>
    </div>
</div>

<!-- PROGRESS -->
<div id="qrProgressArea" class="mb-3" style="display:none;">
  <div class="progress">
    <div id="qrProgressBar" class="progress-bar progress-bar-striped progress-bar-animated"
         style="width:0%;">0%</div>
  </div>
  <div id="qrProgressLog" class="mt-2 small text-muted font-monospace"
       style="max-height:200px; overflow-y:auto; background:#f8f9fa; padding:8px; border-radius:4px;"></div>
</div>

<!-- TABLE -->
<table class="table table-bordered table-striped align-middle">
    <thead class="table-dark">
        <tr>
            <th style="width:40px;"><input type="checkbox" id="selectAllRows" class="form-check-input"></th>
            <th>ID</th>
            <th>Name</th>
            <th>Role</th>
            <th>Department</th>
            <th>Year Level</th>
            <th>Email</th>
            <th>QR Sent</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($users)): ?>
            <tr><td colspan="9" class="text-center text-muted py-4">
                <?= $hasFilters ? 'No participants match your filters.' : 'No participants yet.' ?>
            </td></tr>
        <?php else: ?>
            <?php foreach($users as $u): ?>
                <tr>
                    <td><input type="checkbox" class="form-check-input row-check"
                               data-id="<?= $u['id'] ?>"
                               <?= empty($u['email']) ? 'disabled' : '' ?>></td>
                    <td><?= $u['id'] ?></td>
                    <td><?= htmlspecialchars($u['name']) ?></td>
                    <td><?= htmlspecialchars($u['role']) ?></td>
                    <td><?= htmlspecialchars($u['department'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($u['year_level'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($u['email'] ?? '') ?></td>
                    <td>
                        <?php if ($u['qr_sent']): ?>
                            <span class="badge bg-success">Sent</span>
                        <?php elseif (empty($u['email'])): ?>
                            <span class="badge bg-secondary">No email</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Not sent</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-nowrap">
                        <button class="btn btn-sm btn-warning" data-bs-toggle="modal"
                                data-bs-target="#editUserModal<?= $u['id'] ?>">Edit</button>

                        <a href="../process/delete_user.php?id=<?= $u['id'] ?>"
                           class="btn btn-sm btn-danger"
                           data-confirm="Delete this participant?"
                           data-confirm-message="Their attendance records will also be removed."
                           data-confirm-button="Delete"
                           data-confirm-color="danger">Delete</a>

                        <button type="button" class="btn btn-sm btn-info qr-btn"
                                title="View QR"
                                data-user-id="<?= $u['id'] ?>"
                                data-user-name="<?= htmlspecialchars($u['name']) ?>">
                            <svg style="pointer-events:none;" xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M2 2h2v2H2z"/><path d="M6 0v6H0V0zM5 1H1v4h4zM4 12H2v2h2z"/><path d="M6 10v6H0v-6zm-5 1v4h4v-4zm11-9h2v2h-2z"/><path d="M10 0v6h6V0zm5 1v4h-4V1zM8 1V0h1v2H8v2H7V1zm0 5V4h1v2zM6 8V7h1V6h1v2h1V7h5v1h-4v1H7V8zm0 0v1H2V8H1v1H0V7h3v1zm10 1h-1V7h1zm-1 0h-1v2h2v-1h-1zm-4 0h2v1h-1v1h-1zm2 3v-1h-1v1h-1v1H8v1h4v-2zm0 0v1h2v-1z"/>
                            </svg>
                        </button>

                        <button type="button" class="btn btn-sm btn-primary send-qr-btn"
                                data-id="<?= $u['id'] ?>"
                                <?= empty($u['email']) ? 'disabled' : '' ?>>
                            📧
                        </button>
                    </td>
                </tr>

                <!-- Edit Modal -->
                <div class="modal fade" id="editUserModal<?= $u['id'] ?>" tabindex="-1" aria-hidden="true">
                  <div class="modal-dialog">
                    <div class="modal-content">
                      <form action="../process/save_user.php" method="POST"
                            data-confirm-form
                            data-confirm="Save changes?"
                            data-confirm-message="Your edits will be saved."
                            data-confirm-button="Save Changes"
                            data-confirm-color="success">
                        <div class="modal-header">
                          <h5 class="modal-title">Edit Participant</h5>
                          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="id" value="<?= $u['id'] ?>">
                            <div class="mb-3">
                                <label>Name</label>
                                <input type="text" name="name" class="form-control"
                                       value="<?= htmlspecialchars($u['name']) ?>" required>
                            </div>
                            <div class="mb-3">
                                <label>Email</label>
                                <input type="email" name="email" class="form-control"
                                       value="<?= htmlspecialchars($u['email'] ?? '') ?>">
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label>Role</label>
                                    <select name="role" class="form-control">
                                        <?php foreach($roles as $r): ?>
                                            <option <?= $u['role']==$r?'selected':'' ?>><?= $r ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label>Department</label>
                                    <select name="department" class="form-control">
                                        <option value="">-- None --</option>
                                        <?php foreach($departments as $d): ?>
                                            <option <?= ($u['department'] ?? '')==$d?'selected':'' ?>><?= $d ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label>Year Level</label>
                                    <select name="year_level" class="form-control">
                                        <option value="">-- None --</option>
                                        <?php foreach($yearLevels as $y): ?>
                                            <option <?= ($u['year_level'] ?? '')==$y?'selected':'' ?>><?= $y ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                          <button type="submit" name="update" class="btn btn-success">Save Changes</button>
                        </div>
                      </form>
                    </div>
                  </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<!-- QR PREVIEW MODAL -->
<div class="modal fade" id="qrModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">QR Code — <span id="qrModalName" class="fw-normal"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center">
        <div id="qrContainer" class="d-inline-block p-3 bg-white border rounded">
          <div class="text-muted">Generating…</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary btn-sm" id="qrDownloadBtn" disabled>⬇️ Download PNG</button>
      </div>
    </div>
  </div>
</div>

<script>
const selectAllRows    = document.getElementById('selectAllRows');
const sendAllQrBtn     = document.getElementById('sendAllQrBtn');
const downloadQrZipBtn = document.getElementById('downloadQrZipBtn');
const qrProgressArea   = document.getElementById('qrProgressArea');
const qrProgressBar    = document.getElementById('qrProgressBar');
const qrProgressLog    = document.getElementById('qrProgressLog');

const currentFilters = {
    q:          <?= json_encode($q) ?>,
    role:       <?= json_encode($fRole) ?>,
    department: <?= json_encode($fDept) ?>,
    year_level: <?= json_encode($fYear) ?>
};

function updateBulkButtons() {
    const anyRows = document.querySelectorAll('.row-check:not(:disabled)').length > 0;
    sendAllQrBtn.disabled     = !anyRows;
    downloadQrZipBtn.disabled = !anyRows;
}
updateBulkButtons();

selectAllRows.addEventListener('change', function () {
    document.querySelectorAll('.row-check:not(:disabled)').forEach(cb => cb.checked = this.checked);
});

document.querySelectorAll('.send-qr-btn').forEach(btn => {
    btn.addEventListener('click', async function () {
        const userId = this.dataset.id;
        this.disabled = true;
        this.innerHTML = '⏳';

        const fd = new FormData();
        fd.append('user_id', userId);

        try {
            const res  = await fetch('../process/send_qr.php', { method: 'POST', body: fd });
            const data = await res.json();

            if (data.status === 'success') {
                this.innerHTML = '✅';
                this.className = 'btn btn-sm btn-success send-qr-btn';
                const badge = this.closest('tr').querySelector('td:nth-child(8) .badge');
                if (badge) { badge.className = 'badge bg-success'; badge.textContent = 'Sent'; }
            } else {
                this.innerHTML = '❌';
                this.className = 'btn btn-sm btn-danger send-qr-btn';
                alert('Failed: ' + data.message);
            }
        } catch (err) {
            this.innerHTML = '❌';
            this.className = 'btn btn-sm btn-danger send-qr-btn';
            alert('Network error.');
        } finally {
            setTimeout(() => {
                this.disabled = false;
                this.innerHTML = '📧';
                this.className = 'btn btn-sm btn-primary send-qr-btn';
            }, 2500);
        }
    });
});

sendAllQrBtn.addEventListener('click', async function () {
    const checked = Array.from(document.querySelectorAll('.row-check:checked')).map(cb => cb.dataset.id);
    const useChecked = checked.length > 0;

    const fd = new FormData();
    if (useChecked) {
        checked.forEach(id => fd.append('user_ids[]', id));
    } else {
        Object.entries(currentFilters).forEach(([k, v]) => { if (v) fd.append(k, v); });
    }

    const label = useChecked ? `${checked.length} selected participant(s)` : 'all filtered participants with emails';
    if (!confirm(`Send QR codes to ${label}?`)) return;

    qrProgressArea.style.display = 'block';
    qrProgressLog.innerHTML = '';
    sendAllQrBtn.disabled = true;
    downloadQrZipBtn.disabled = true;

    try {
        const res  = await fetch('../process/bulk_send_qr.php', { method: 'POST', body: fd });
        const data = await res.json();

        if (data.status !== 'success') {
            qrProgressLog.innerHTML = `<span class="text-danger">Error: ${data.message}</span>`;
            return;
        }

        const results = data.results || [];
        let sent = 0;

        results.forEach((r, i) => {
            const line = `[${i + 1}/${results.length}] ${r.name}: `;
            if (r.status === 'success') {
                sent++;
                qrProgressLog.innerHTML += line + `<span class="text-success">OK (${r.email})</span><br>`;
            } else {
                qrProgressLog.innerHTML += line + `<span class="text-danger">${r.message}</span><br>`;
            }

            const pct = Math.round(((i + 1) / results.length) * 100);
            qrProgressBar.style.width = pct + '%';
            qrProgressBar.textContent = pct + '%';
        });

        qrProgressBar.classList.remove('progress-bar-animated');
        qrProgressBar.classList.add('bg-success');
        qrProgressLog.innerHTML += `<hr><strong>Done: ${sent}/${results.length} sent.</strong><br>`;
        qrProgressLog.scrollTop = qrProgressLog.scrollHeight;

    } catch (err) {
        qrProgressLog.innerHTML = `<span class="text-danger">Network error: ${err.message}</span>`;
    } finally {
        sendAllQrBtn.disabled = false;
        downloadQrZipBtn.disabled = false;
        setTimeout(() => location.reload(), 1500);
    }
});

downloadQrZipBtn.addEventListener('click', function () {
    const checked = Array.from(document.querySelectorAll('.row-check:checked')).map(cb => cb.dataset.id);
    const params = new URLSearchParams();

    if (checked.length > 0) {
        checked.forEach(id => params.append('ids[]', id));
    } else {
        Object.entries(currentFilters).forEach(([k, v]) => { if (v) params.append(k, v); });
    }

    window.location.href = '../process/download_qr_zip.php?' + params.toString();
});

document.addEventListener('DOMContentLoaded', function () {
    const qrModal     = document.getElementById('qrModal');
    const container   = document.getElementById('qrContainer');
    const nameSpan    = document.getElementById('qrModalName');
    const downloadBtn = document.getElementById('qrDownloadBtn');
    if (!qrModal || !container || !nameSpan || !downloadBtn) return;

    document.querySelectorAll('.qr-btn').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            const userId   = btn.getAttribute('data-user-id');
            const userName = btn.getAttribute('data-user-name') || '';
            if (!userId) return;

            nameSpan.textContent = userName;
            container.innerHTML  = '<div class="text-muted">Requesting QR…</div>';
            downloadBtn.disabled = true;

            try { bootstrap.Modal.getOrCreateInstance(qrModal).show(); } catch (err) { return; }

            const apiUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' + encodeURIComponent(userId);
            const img = new Image();
            img.crossOrigin = 'anonymous';
            img.style.width  = '260px';
            img.style.height = '260px';

            img.onload = function () {
                container.innerHTML = '';
                container.appendChild(img);
                downloadBtn.disabled = false;
            };
            img.onerror = function () {
                container.innerHTML = '<div class="text-danger p-3">Could not load QR.<br><a href="' + apiUrl + '" target="_blank">Open in new tab</a></div>';
            };
            img.src = apiUrl;
        });
    });

    downloadBtn.addEventListener('click', async function () {
        const img = container.querySelector('img');
        if (!img) return;
        const safeName = (nameSpan.textContent || 'user').replace(/[^a-z0-9_-]/gi, '_');
        downloadBtn.disabled = true;
        downloadBtn.textContent = '⏳ Downloading...';
        try {
            const response = await fetch(img.src, { mode: 'cors' });
            const blob     = await response.blob();
            const blobUrl  = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = blobUrl;
            a.download = 'QR_' + safeName + '.png';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            setTimeout(() => URL.revokeObjectURL(blobUrl), 1000);
        } catch (err) {
            window.open(img.src, '_blank');
        } finally {
            downloadBtn.disabled = false;
            downloadBtn.textContent = '⬇️ Download PNG';
        }
    });
});
</script>

<?php include 'footer.php'; ?>