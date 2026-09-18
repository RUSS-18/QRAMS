<!-- <?php 
include '../config/db.php';
session_start();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Select Event</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
</head>

<body class="bg-light">

<div class="container vh-100 d-flex justify-content-center align-items-center">

    <div class="card shadow" style="width:500px;">
        <div class="card-body">

            <h3 class="text-center mb-4">Select Event to Scan QR</h3>

            <form action="scan.php" method="GET">

                <div class="mb-3">
                    <label class="form-label">Event</label>
                    <select name="event_id" id="event_id" class="form-control" required>

                        <?php
                        $stmt = $conn->query("SELECT * FROM events ORDER BY event_date DESC");

                        while($event = $stmt->fetch(PDO::FETCH_ASSOC)){
                        ?>

                        <option value="<?= $event['id'] ?>">
                            <?= $event['event_name'] ?> (<?= $event['event_date'] ?>)
                        </option>

                        <?php } ?>

                    </select>
                </div>
            </form>

        </div>
    </div>

</div>

</body>
</html> -->