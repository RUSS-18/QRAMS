<?php
// ============================================================
// Fetch events — filtered by role
// ============================================================
$today = date('Y-m-d');
$isSuperAdmin = ($_SESSION['admin_role'] ?? 'super_admin') === 'super_admin';
$adminId = (int)$_SESSION['admin'];

try {
    if ($isSuperAdmin) {
        // Super admin sees all events
        $stmt = $conn->prepare("
            SELECT id, event_name, start_date, end_date,
                   CASE 
                       WHEN ? BETWEEN start_date AND end_date THEN 'active'
                       WHEN start_date > ? THEN 'upcoming'
                       ELSE 'past'
                   END AS status
            FROM events
            ORDER BY 
                CASE 
                    WHEN ? BETWEEN start_date AND end_date THEN 1
                    WHEN start_date > ? THEN 2
                    ELSE 3
                END,
                start_date ASC
        ");
        $stmt->execute([$today, $today, $today, $today]);
    } else {
        // Facilitator sees only assigned events
        $stmt = $conn->prepare("
            SELECT e.id, e.event_name, e.start_date, e.end_date,
                   CASE 
                       WHEN ? BETWEEN e.start_date AND e.end_date THEN 'active'
                       WHEN e.start_date > ? THEN 'upcoming'
                       ELSE 'past'
                   END AS status
            FROM events e
            JOIN event_facilitators ef ON ef.event_id = e.id
            WHERE ef.admin_id = ?
            ORDER BY 
                CASE 
                    WHEN ? BETWEEN e.start_date AND e.end_date THEN 1
                    WHEN e.start_date > ? THEN 2
                    ELSE 3
                END,
                e.start_date ASC
        ");
        $stmt->execute([$today, $today, $adminId, $today, $today]);
    }

    $events_for_modal = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('[scan_qr modal] ' . $e->getMessage());
    $events_for_modal = [];
    $modal_error = $e->getMessage();
}
?>

<!-- Scan QR Modal -->
<div class="modal fade" id="scanQrModal" tabindex="-1" aria-labelledby="scanQrModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="scanQrModalLabel">📷 Scan QR Attendance</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">

        <?php if (!empty($modal_error)): ?>
          <div class="alert alert-danger"><strong>Database error:</strong> <?= htmlspecialchars($modal_error) ?></div>
        <?php endif; ?>

        <?php if (empty($events_for_modal)): ?>
          <div class="alert alert-warning">
            <strong>No events available.</strong><br>
            <?php if ($isSuperAdmin): ?>
              Go to <a href="events.php">Events</a> and create one first.
            <?php else: ?>
              You have no assigned events. Contact the Super Admin.
            <?php endif; ?>
          </div>
        <?php else: ?>
          <div class="mb-3">
            <label class="form-label fw-bold">Select Event</label>
            <select id="scanEventSelect" class="form-select">
              <option value="">-- Choose an event to begin --</option>
              <?php foreach ($events_for_modal as $ev): 
                  if ($ev['start_date'] === $ev['end_date']) {
                      $dateLabel = date('M j, Y', strtotime($ev['start_date']));
                  } else {
                      $dateLabel = date('M j', strtotime($ev['start_date'])) . ' – ' . date('M j, Y', strtotime($ev['end_date']));
                  }
                  $prefix = $ev['status'] === 'active'   ? '🟢 ACTIVE · ' :
                           ($ev['status'] === 'upcoming' ? '📅 Upcoming · ' : '⚪ Past · ');
              ?>
                <option value="<?= $ev['id'] ?>">
                  <?= $prefix ?><?= htmlspecialchars($ev['event_name']) ?> — <?= $dateLabel ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div id="scanPlaceholder" class="text-center text-muted py-5">
            <div style="font-size:48px;">📷</div>
            <p class="mt-2 mb-0">Select an event above to activate the scanner.</p>
          </div>

          <div id="scanFrameContainer" style="display:none;">
            <iframe id="qrFrame"
                    style="width:100%; height:70vh; border:1px solid #dee2e6; border-radius:8px;"
                    allow="camera; geolocation"
                    referrerpolicy="no-referrer-when-downgrade"
                    title="QR Scanner"></iframe>
          </div>
        <?php endif; ?>

      </div>
      <div class="modal-footer">
        <small class="text-muted me-auto">💡 Tip: Allow camera and location access when prompted.</small>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal          = document.getElementById('scanQrModal');
    const select         = document.getElementById('scanEventSelect');
    const frame          = document.getElementById('qrFrame');
    const frameContainer = document.getElementById('scanFrameContainer');
    const placeholder    = document.getElementById('scanPlaceholder');

    if (!modal || !select) return;

    select.addEventListener('change', function () {
        const eventId = this.value;
        if (!eventId) {
            frameContainer.style.display = 'none';
            placeholder.style.display = 'block';
            frame.src = '';
            return;
        }
        frame.src = '../qr/scan.php?event_id=' + encodeURIComponent(eventId);
        frameContainer.style.display = 'block';
        placeholder.style.display = 'none';
    });

    modal.addEventListener('hidden.bs.modal', function () {
        frame.src = '';
        frameContainer.style.display = 'none';
        placeholder.style.display = 'block';
        select.value = '';
    });
});
</script>