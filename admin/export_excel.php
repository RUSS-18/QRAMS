<?php
// export_excel.php
include '../config/db.php';

// Set headers to force download as Excel
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=attendance.xls");
header("Pragma: no-cache");
header("Expires: 0");

// Build filter conditions
$where = [];
$params = [];

if(!empty($_GET['event_id'])){
    $where[] = "attendance.event_id = :event_id";
    $params['event_id'] = $_GET['event_id'];
}
if(!empty($_GET['date_from'])){
    $where[] = "DATE(attendance.scan_time) >= :date_from";
    $params['date_from'] = $_GET['date_from'];
}
if(!empty($_GET['date_to'])){
    $where[] = "DATE(attendance.scan_time) <= :date_to";
    $params['date_to'] = $_GET['date_to'];
}

// SQL Query
$sql = "SELECT users.name, events.event_name, attendance.scan_time
        FROM attendance
        JOIN users ON users.id = attendance.user_id
        JOIN events ON events.id = attendance.event_id";

if($where){
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " ORDER BY attendance.scan_time DESC";

// Execute
$stmt = $conn->prepare($sql);
$stmt->execute($params);

// Start table
echo "<table border='1'>";
echo "<tr>
        <th>Name</th>
        <th>Event</th>
        <th>Scan Time</th>
      </tr>";

// Fetch and output rows
while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
    echo "<tr>
            <td>{$row['name']}</td>
            <td>{$row['event_name']}</td>
            <td>{$row['scan_time']}</td>
          </tr>";
}

echo "</table>";
exit;
?>