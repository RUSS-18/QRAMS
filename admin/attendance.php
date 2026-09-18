<?php include 'header.php'; ?>

<h2>Attendance Records</h2>

<?php
// ------------------------------------------------------------
// FILTERS
// ------------------------------------------------------------
$filterEvent  = (int)($_GET['event_id'] ?? 0);
$filterDate   = $_GET['date'] ?? '';
$filterDept   = $_GET['department'] ?? '';
$filterYear   = $_GET['year_level'] ?? '';

// Build query
$sql = "
    SELECT a.id, a.attendance_date, a.time_in, a.time_out,
           a.latitude, a.longitude, a.accuracy,
           a.out_latitude, a.out_longitude, a.out_accuracy,
           u.name, u.department, u.year_level, u.email,
           e.event_name, e.start_date, e.end_date
    FROM attendance a
    JOIN users  u ON u.id = a.user_id
    JOIN events e ON e.id = a.event_id
    WHERE 1=1
";
$params = [];

if ($filterEvent) {
    $sql .= " AND a.event_id = ?";
    $params[] = $filterEvent;
}

if ($filterDate) {
    $sql .= " AND a.attendance_date = ?";
    $params[] = $filterDate;
}

if ($filterDept) {
    $sql .= " AND u.department = ?";
    $params[] = $filterDept;
}

if ($filterYear) {
    $sql .= " AND u.year_level = ?";
    $params[] = $filterYear;
}

$sql .= " ORDER BY a.attendance_date DESC, a.time_in DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Stats
$totalRecords    = count($records);
$totalCompleted  = 0;
$totalStillIn    = 0;
$totalDurationSecs = 0;

foreach ($records as $r) {
    if (!empty($r['time_out'])) {
        $totalCompleted++;
        $totalDurationSecs += (strtotime($r['time_out']) - strtotime($r['time_in']));
    } else {
        $totalStillIn++;
    }
}

$avgDuration = $totalCompleted > 0
    ? round($totalDurationSecs / $totalCompleted / 60)   // minutes
    : 0;

// Fetch events for the filter dropdown
$eventList = $conn->query("SELECT id, event_name, start_date FROM events ORDER BY start_date DESC")
                  ->fetchAll(PDO::FETCH_ASSOC);

$departments = ['CICS','CTED','CIT','Other'];
$yearLevels  = ['1st Year','2nd Year','3rd Year','4th Year','5th Year','Graduate','Faculty'];
?>

<!-- ============================================================
     STATS CARDS
     ============================================================ -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-center shadow-sm">
            <div class="card-body">
                <h3 class="mb-0"><?= $totalRecords ?></h3>
                <small class="text-muted">Total records</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center shadow-sm">
            <div class="card-body">
                <h3 class="mb-0 text-success"><?= $totalCompleted ?></h3>
                <small class="text-muted">Completed (in & out)</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center shadow-sm">
            <div class="card-body">
                <h3 class="mb-0 text-warning"><?= $totalStillIn ?></h3>
                <small class="text-muted">Still checked in</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center shadow-sm">
            <div class="card-body">
                <h3 class="mb-0"><?= $avgDuration ?>m</h3>
                <small class="text-muted">Avg. duration</small>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================
     FILTERS
     ============================================================ -->
<div class="card mb-4">
  <div class="card-body">
    <form method="GET" class="row g-2">
      <div class="col-md-3">
        <label class="form-label small">Event</label>
        <select name="event_id" class="form-select">
          <option value="">All events</option>
          <?php foreach ($eventList as $ev): ?>
            <option value="<?= $ev['id'] ?>" <?= $filterEvent == $ev['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($ev['event_name']) ?>
              (<?= date('M j', strtotime($ev['start_date'])) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small">Date</label>
        <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($filterDate) ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label small">Department</label>
        <select name="department" class="form-select">
          <option value="">All</option>
          <?php foreach ($departments as $d): ?>
            <option <?= $filterDept === $d ? 'selected' : '' ?>><?= $d ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small">Year Level</label>
        <select name="year_level" class="form-select">
          <option value="">All</option>
          <?php foreach ($yearLevels as $y): ?>
            <option <?= $filterYear === $y ? 'selected' : '' ?>><?= $y ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3 d-flex align-items-end gap-2">
        <button class="btn btn-primary flex-fill">Apply Filters</button>
        <a href="attendance.php" class="btn btn-outline-secondary">Reset</a>
      </div>
    </form>
  </div>
</div>

<!-- ============================================================
     RECORDS TABLE
     ============================================================ -->
<div class="table-responsive">
<table class="table table-bordered table-striped table-hover">
    <thead class="table-dark">
        <tr>
            <th>#</th>
            <th>Name</th>
            <th>Dept / Year</th>
            <th>Event</th>
            <th>Date</th>
            <th>Time In</th>
            <th>Time Out</th>
            <th>Duration</th>
            <th>Location</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($records)): ?>
            <tr><td colspan="9" class="text-center text-muted">No attendance records match your filters.</td></tr>
        <?php else: ?>
            <?php foreach ($records as $i => $r): 
                $duration = '';
                if (!empty($r['time_out'])) {
                    $mins = round((strtotime($r['time_out']) - strtotime($r['time_in'])) / 60);
                    $h = floor($mins / 60);
                    $m = $mins % 60;
                    $duration = $h > 0 ? "{$h}h {$m}m" : "{$m}m";
                }
            ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= htmlspecialchars($r['name']) ?></td>
                    <td>
                        <?= htmlspecialchars($r['department'] ?? '—') ?>
                        <br><small class="text-muted"><?= htmlspecialchars($r['year_level'] ?? '—') ?></small>
                    </td>
                    <td><?= htmlspecialchars($r['event_name']) ?></td>
                    <td><?= date('M j, Y', strtotime($r['attendance_date'])) ?></td>
                    <td><?= date('g:i A', strtotime($r['time_in'])) ?></td>
                    <td>
                        <?php if ($r['time_out']): ?>
                            <?= date('g:i A', strtotime($r['time_out'])) ?>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark">Still in</span>
                        <?php endif; ?>
                    </td>
                    <td><?= $duration ?: '—' ?></td>
                    <td>
                        <?php if (!empty($r['latitude']) && !empty($r['longitude'])): ?>
                            <a href="https://www.google.com/maps?q=<?= $r['latitude'] ?>,<?= $r['longitude'] ?>"
                               target="_blank" class="small" title="Check-in location">
                                📍 In
                            </a>
                        <?php endif; ?>
                        <?php if (!empty($r['out_latitude']) && !empty($r['out_longitude'])): ?>
                            <br>
                            <a href="https://www.google.com/maps?q=<?= $r['out_latitude'] ?>,<?= $r['out_longitude'] ?>"
                               target="_blank" class="small" title="Check-out location">
                                📍 Out
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>
</div>

<?php if (!empty($records)): ?>
    <p class="text-muted small">
        Showing <strong><?= count($records) ?></strong> record(s)
        <?= $filterDate ? 'for ' . date('F j, Y', strtotime($filterDate)) : '' ?>.
    </p>
<?php endif; ?>

<?php include 'footer.php'; ?>