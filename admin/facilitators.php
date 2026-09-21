<?php include 'header.php'; ?>

<?php if (($_SESSION['admin_role'] ?? '') !== 'super_admin'): ?>
    <div class="alert alert-danger">Access denied. Only Super Admins can manage facilitators.</div>
    <?php include 'footer.php'; exit; ?>
<?php endif; ?>

<h2>Facilitators</h2>
<p class="text-muted small">
    Create accounts for staff who will assist with scanning attendance.
    <strong>Super Admins automatically have access to all events</strong> — no assignment needed.
</p>

<?php if (isset($_SESSION['facilitator_msg'])): ?>
    <div class="alert alert-<?= $_SESSION['facilitator_msg']['type'] ?> alert-dismissible fade show">
        <?= $_SESSION['facilitator_msg']['message'] ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['facilitator_msg']); ?>
<?php endif; ?>

<!-- Action button -->
<div class="mb-3">
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addFacilitatorModal">
        Add Facilitator
    </button>
</div>

<?php
// Fetch all admins
$stmt = $conn->query("
    SELECT a.*,
        (SELECT COUNT(*) FROM event_facilitators ef WHERE ef.admin_id = a.id) AS assigned_events
    FROM admins a
    ORDER BY a.role DESC, a.username ASC
");
$facilitators = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all events for assignment dropdown
$events = $conn->query("SELECT id, event_name, start_date, end_date FROM events ORDER BY start_date DESC")
                ->fetchAll(PDO::FETCH_ASSOC);
?>

<table class="table table-bordered table-striped align-middle">
    <thead class="table-dark">
        <tr>
            <th>ID</th>
            <th>Username</th>
            <th>Full Name</th>
            <th>Role</th>
            <th>Status</th>
            <th>Event Access</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($facilitators as $f): 
            $isSuper = ($f['role'] === 'super_admin');
        ?>
            <tr>
                <td><?= $f['id'] ?></td>
                <td><?= htmlspecialchars($f['username']) ?></td>
                <td><?= htmlspecialchars($f['full_name'] ?? '—') ?></td>
                <td>
                    <?php if ($isSuper): ?>
                        <span class="badge bg-primary">Super Admin</span>
                    <?php else: ?>
                        <span class="badge bg-info text-dark">Facilitator</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ((int)$f['is_active']): ?>
                        <span class="badge bg-success">Active</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">Disabled</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($isSuper): ?>
                        <span class="badge bg-success">All events ✓</span>
                    <?php else: ?>
                        <span class="badge bg-secondary"><?= (int)$f['assigned_events'] ?> event(s)</span>
                    <?php endif; ?>
                </td>
                <td class="text-nowrap">
                    <button class="btn btn-sm btn-warning" data-bs-toggle="modal"
                            data-bs-target="#editFacilitator<?= $f['id'] ?>">Edit</button>

                    <?php if (!$isSuper): ?>
                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal"
                                data-bs-target="#assignEvents<?= $f['id'] ?>">Assign Events</button>
                    <?php else: ?>
                        <button class="btn btn-sm btn-outline-secondary" disabled
                                title="Super Admins have access to all events">All Events</button>
                    <?php endif; ?>

                    <?php if (!$isSuper): ?>
                        <a href="../process/save_facilitator.php?delete_id=<?= $f['id'] ?>"
                           class="btn btn-sm btn-danger"
                           data-confirm="Delete this facilitator?"
                           data-confirm-message="Their event assignments will also be removed."
                           data-confirm-button="Delete"
                           data-confirm-color="danger">Delete</a>
                    <?php endif; ?>
                </td>
            </tr>

            <!-- Edit Modal -->
            <div class="modal fade" id="editFacilitator<?= $f['id'] ?>" tabindex="-1" aria-hidden="true">
              <div class="modal-dialog">
                <div class="modal-content">
                  <form action="../process/save_facilitator.php" method="POST">
                    <div class="modal-header">
                      <h5 class="modal-title">Edit <?= $isSuper ? 'Super Admin' : 'Facilitator' ?></h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="id" value="<?= $f['id'] ?>">
                        <div class="mb-3">
                            <label>Username</label>
                            <input type="text" name="username" class="form-control"
                                   value="<?= htmlspecialchars($f['username']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label>Full Name</label>
                            <input type="text" name="full_name" class="form-control"
                                   value="<?= htmlspecialchars($f['full_name'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label>New Password <small class="text-muted">(leave blank to keep current)</small></label>
                            <input type="password" name="password" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label>Role</label>
                            <select name="role" class="form-control">
                                <option value="facilitator" <?= !$isSuper ? 'selected' : '' ?>>Facilitator</option>
                                <option value="super_admin" <?= $isSuper ? 'selected' : '' ?>>Super Admin</option>
                            </select>
                            <small class="text-muted d-block mt-1">
                                Super Admins have full access. Facilitators only see assigned events.
                            </small>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1"
                                   id="active_<?= $f['id'] ?>" <?= (int)$f['is_active'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="active_<?= $f['id'] ?>">Account Active</label>
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

            <!-- Assign Events Modal (only for facilitators) -->
            <?php if (!$isSuper): ?>
            <div class="modal fade" id="assignEvents<?= $f['id'] ?>" tabindex="-1" aria-hidden="true">
              <div class="modal-dialog modal-lg">
                <div class="modal-content">
                  <form action="../process/save_facilitator.php" method="POST">
                    <div class="modal-header">
                      <h5 class="modal-title">
                        Assign Events to <strong><?= htmlspecialchars($f['full_name'] ?: $f['username']) ?></strong>
                      </h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="assign_admin_id" value="<?= $f['id'] ?>">

                        <?php
                        $stmtA = $conn->prepare("SELECT event_id FROM event_facilitators WHERE admin_id = ?");
                        $stmtA->execute([$f['id']]);
                        $assigned = array_column($stmtA->fetchAll(PDO::FETCH_ASSOC), 'event_id');
                        ?>

                        <?php if (empty($events)): ?>
                            <div class="alert alert-warning">No events exist yet. Create events first.</div>
                        <?php else: ?>
                            <div class="alert alert-info small mb-3">
                                Check the events this facilitator can scan. They'll only see checked events on their dashboard.
                            </div>

                            <div class="border rounded p-3" style="max-height:400px; overflow-y:auto;">
                                <?php foreach ($events as $ev): 
                                    $range = $ev['start_date'] === $ev['end_date']
                                        ? date('M j, Y', strtotime($ev['start_date']))
                                        : date('M j', strtotime($ev['start_date'])) . ' – ' . date('M j, Y', strtotime($ev['end_date']));
                                ?>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox"
                                               name="event_ids[]" value="<?= $ev['id'] ?>"
                                               id="ev_<?= $f['id'] ?>_<?= $ev['id'] ?>"
                                               <?= in_array($ev['id'], $assigned) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="ev_<?= $f['id'] ?>_<?= $ev['id'] ?>">
                                            <strong><?= htmlspecialchars($ev['event_name']) ?></strong>
                                            <span class="text-muted small">— <?= $range ?></span>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                      <button type="submit" name="assign_events" class="btn btn-primary">Save Assignments</button>
                    </div>
                  </form>
                </div>
              </div>
            </div>
            <?php endif; ?>

        <?php endforeach; ?>
    </tbody>
</table>

<!-- Add Facilitator Modal -->
<div class="modal fade" id="addFacilitatorModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form action="../process/save_facilitator.php" method="POST">
        <div class="modal-header">
          <h5 class="modal-title">Add Facilitator</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <div class="mb-3">
                <label>Username</label>
                <input type="text" name="username" class="form-control" required>
            </div>
            <div class="mb-3">
                <label>Full Name</label>
                <input type="text" name="full_name" class="form-control" required>
            </div>
            <div class="mb-3">
                <label>Password</label>
                <input type="password" name="password" class="form-control" required minlength="6">
                <small class="text-muted">Minimum 6 characters.</small>
            </div>
            <div class="mb-3">
                <label>Role</label>
                <select name="role" class="form-control">
                    <option value="facilitator" selected>Facilitator</option>
                    <option value="super_admin">Super Admin</option>
                </select>
                <small class="text-muted d-block mt-1">
                    Facilitators only see events you assign. Super Admins see everything.
                </small>
            </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="save" class="btn btn-success">Create Facilitator</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include 'footer.php'; ?>