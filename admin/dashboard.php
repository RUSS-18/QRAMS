<?php include 'header.php'; ?>

<h2>Dashboard</h2>

<?php
// ------------------------------------------------------------
// STATS
// ------------------------------------------------------------
$today = date('Y-m-d');

// Total users
$totalUsers = (int)$conn->query("SELECT COUNT(*) FROM users")->fetchColumn();

// Active events today
$stmt = $conn->prepare("SELECT COUNT(*) FROM events WHERE ? BETWEEN start_date AND end_date");
$stmt->execute([$today]);
$activeEvents = (int)$stmt->fetchColumn();

// Total events
$totalEvents = (int)$conn->query("SELECT COUNT(*) FROM events")->fetchColumn();

// Today's check-ins
$stmt = $conn->prepare("SELECT COUNT(*) FROM attendance WHERE attendance_date = ?");
$stmt->execute([$today]);
$todayCheckins = (int)$stmt->fetchColumn();

// Today's check-outs
$stmt = $conn->prepare("SELECT COUNT(*) FROM attendance WHERE attendance_date = ? AND time_out IS NOT NULL");
$stmt->execute([$today]);
$todayCheckouts = (int)$stmt->fetchColumn();
?>

<!-- STAT CARDS -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-center shadow-sm">
            <div class="card-body">
                <h3 class="mb-0"><?= $totalUsers ?></h3>
                <small class="text-muted">Participants</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center shadow-sm">
            <div class="card-body">
                <h3 class="mb-0 text-primary"><?= $totalEvents ?></h3>
                <small class="text-muted">Total Events</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center shadow-sm">
            <div class="card-body">
                <h3 class="mb-0 text-success"><?= $todayCheckins ?></h3>
                <small class="text-muted">Today's Check-ins</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center shadow-sm">
            <div class="card-body">
                <h3 class="mb-0 text-info"><?= $todayCheckouts ?></h3>
                <small class="text-muted">Today's Check-outs</small>
            </div>
        </div>
    </div>
</div>

<!-- ACTIVE EVENTS -->
<div class="row">
    <div class="col-md-6">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-dark text-white">
                Active Events Today
            </div>
            <div class="card-body">
                <?php
                $stmt = $conn->prepare("
                    SELECT id, event_name, start_date, end_date
                    FROM events
                    WHERE ? BETWEEN start_date AND end_date
                    ORDER BY start_date DESC
                ");
                $stmt->execute([$today]);
                $activeList = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (empty($activeList)) {
                    echo '<p class="text-muted mb-0">No events active today.</p>';
                } else {
                    echo '<ul class="list-group list-group-flush">';
                    foreach ($activeList as $e) {
                        $range = $e['start_date'] === $e['end_date']
                            ? date('M j, Y', strtotime($e['start_date']))
                            : date('M j', strtotime($e['start_date'])) . ' – ' . date('M j, Y', strtotime($e['end_date']));
                        echo '<li class="list-group-item d-flex justify-content-between align-items-center">'
                           . htmlspecialchars($e['event_name'])
                           . '<span class="badge bg-primary">' . $range . '</span>'
                           . '</li>';
                    }
                    echo '</ul>';
                }
                ?>
            </div>
        </div>
    </div>

    <!-- UPCOMING EVENTS -->
    <div class="col-md-6">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-secondary text-white">
                Upcoming Events
            </div>
            <div class="card-body">
                <?php
                $stmt = $conn->prepare("
                    SELECT id, event_name, start_date, end_date
                    FROM events
                    WHERE start_date > ?
                    ORDER BY start_date ASC
                    LIMIT 5
                ");
                $stmt->execute([$today]);
                $upcoming = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (empty($upcoming)) {
                    echo '<p class="text-muted mb-0">No upcoming events.</p>';
                } else {
                    echo '<ul class="list-group list-group-flush">';
                    foreach ($upcoming as $e) {
                        echo '<li class="list-group-item d-flex justify-content-between align-items-center">'
                           . htmlspecialchars($e['event_name'])
                           . '<span class="badge bg-secondary">' . date('M j, Y', strtotime($e['start_date'])) . '</span>'
                           . '</li>';
                    }
                    echo '</ul>';
                }
                ?>
            </div>
        </div>
    </div>
</div>

<!-- RECENT ATTENDANCE -->
<div class="card shadow-sm">
    <div class="card-header bg-dark text-white">
        Recent Attendance Activity
    </div>
    <div class="card-body">
        <?php
        $stmt = $conn->query("
            SELECT a.attendance_date, a.time_in, a.time_out,
                   u.name, u.department, u.year_level,
                   e.event_name
            FROM attendance a
            JOIN users  u ON u.id = a.user_id
            JOIN events e ON e.id = a.event_id
            ORDER BY a.time_in DESC
            LIMIT 10
        ");
        $recent = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($recent)) {
            echo '<p class="text-muted mb-0">No attendance recorded yet.</p>';
        } else {
        ?>
            <table class="table table-sm table-hover mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Event</th>
                        <th>Date</th>
                        <th>Time In</th>
                        <th>Time Out</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent as $r): ?>
                        <tr>
                            <td>
                                <?= htmlspecialchars($r['name']) ?>
                                <br><small class="text-muted">
                                    <?= htmlspecialchars($r['department'] ?? '—') ?> ·
                                    <?= htmlspecialchars($r['year_level'] ?? '—') ?>
                                </small>
                            </td>
                            <td><?= htmlspecialchars($r['event_name']) ?></td>
                            <td><?= date('M j', strtotime($r['attendance_date'])) ?></td>
                            <td><?= date('g:i A', strtotime($r['time_in'])) ?></td>
                            <td>
                                <?php if ($r['time_out']): ?>
                                    <?= date('g:i A', strtotime($r['time_out'])) ?>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">In</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php } ?>
    </div>
</div>

<?php include 'footer.php'; ?>