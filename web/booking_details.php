<?php
require_once 'defaultincludes.inc';

header('Content-Type: text/html');
header('Expires: ' . gmdate('D, d M Y H:i:s T', time() + 60 * 30));  // 30-minute expiry

$searchTerm = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

// Database connection
$conn = mysqli_connect("localhost", "root", '', "mrbs");
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// SQL for current meetings with search
$sql_current = "SELECT 
    a.area_name AS Area,
    r.room_name AS Room,
    e.name AS Event_Name,
    u.display_name AS Booker_Name,
    FROM_UNIXTIME(e.start_time, '%Y-%m-%d %H:%i:%s') AS Start_Time,
    FROM_UNIXTIME(e.end_time, '%Y-%m-%d %H:%i:%s') AS End_Time,
    e.description AS Description
FROM 
    mrbs_entry e
JOIN 
    mrbs_room r ON e.room_id = r.id
JOIN 
    mrbs_area a ON r.area_id = a.id
LEFT JOIN 
    mrbs_users u ON e.create_by = u.name
WHERE 
    DATE(FROM_UNIXTIME(e.start_time)) = CURDATE()";

if (!empty($searchTerm)) {
    $sql_current .= " AND (e.name LIKE '%$searchTerm%' OR r.room_name LIKE '%$searchTerm%' OR u.display_name LIKE '%$searchTerm%' OR e.description LIKE '%$searchTerm%')";
}

// SQL for upcoming meetings with search
$sql_upcoming = "SELECT 
    a.area_name AS Area,
    r.room_name AS Room,
    e.name AS Event_Name,
    u.display_name AS Booker_Name,
    FROM_UNIXTIME(e.start_time, '%Y-%m-%d %H:%i:%s') AS Start_Time,
    FROM_UNIXTIME(e.end_time, '%Y-%m-%d %H:%i:%s') AS End_Time,
    e.description AS Description
FROM 
    mrbs_entry e
JOIN 
    mrbs_room r ON e.room_id = r.id
JOIN 
    mrbs_area a ON r.area_id = a.id
LEFT JOIN 
    mrbs_users u ON e.create_by = u.name
WHERE 
    FROM_UNIXTIME(e.start_time) >= CURDATE() + INTERVAL 1 DAY
ORDER BY 
    e.start_time";

if (!empty($searchTerm)) {
    $sql_upcoming .= " AND (e.name LIKE '%$searchTerm%' OR r.room_name LIKE '%$searchTerm%' OR u.display_name LIKE '%$searchTerm%' OR e.description LIKE '%$searchTerm%')";
}

// Execute queries
$result_current = mysqli_query($conn, $sql_current);
$result_upcoming = mysqli_query($conn, $sql_upcoming);

if (!$result_current || !$result_upcoming) {
    die("Error in SQL query: " . mysqli_error($conn));
}
?>

<style>
    .table-container {
        overflow-x: auto;
        border: 1px solid #ccc;
        border-radius: 10px;
        padding: 10px;
        margin: 0 auto;
        width: 100%;
    }
    .table {
        width: 100%;
        text-align: center;
        border-collapse: collapse;
        margin-top: 20px;
    }
    .table th, .table td {
        border: 1px solid #ddd;
        padding: 8px;
    }
    .table th {
        background-color: #1976D2;
        font-size: 16px;
        color: #fff;
    }
    .table tbody tr:nth-child(even) {
        background-color: #f9f9f9;
    }
    .table tbody tr:hover {
        background-color: #f1f1f1;
    }
    .table tbody td {
        font-size: 14px;
        color: #555;
    }
</style>

<div style="padding: 0px;">
    <!-- Current Meetings Table -->
    <div style="text-align: center;">
        <h2>Current Meetings for Today</h2>
    </div>
    <div class="table-container">
        <table class="table table-striped table-bordered table-hover">
            <thead class="thead-dark">
                <tr>
                    <th>Area</th>
                    <th>Room</th>
                    <th>Meeting Name</th>
                    <th>Requestor</th>
                    <th>Start Time</th>
                    <th>End Time</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
                <?php if (mysqli_num_rows($result_current) > 0) { ?>
                    <?php while ($row = mysqli_fetch_assoc($result_current)) { ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['Area']); ?></td>
                            <td><?php echo htmlspecialchars($row['Room']); ?></td>
                            <td><?php echo htmlspecialchars($row['Event_Name']); ?></td>
                            <td><?php echo htmlspecialchars($row['Booker_Name']); ?></td>
                            <td><?php echo htmlspecialchars($row['Start_Time']); ?></td>
                            <td><?php echo htmlspecialchars($row['End_Time']); ?></td>
                            <td><?php echo htmlspecialchars($row['Description']); ?></td>
                        </tr>
                    <?php } ?>
                <?php } else { ?>
                    <tr>
                        <td colspan="7">No booking is found.</td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</div>

<div style="padding: 0px; margin-top: 50px;">
    <!-- Upcoming Meetings Table -->
    <div style="text-align: center;">
        <h2>Upcoming Meetings</h2>
    </div>
    <div class="table-container">
        <table class="table table-striped table-bordered table-hover">
            <thead class="thead-dark">
                <tr>
                    <th>Area</th>
                    <th>Room</th>
                    <th>Meeting Name</th>
                    <th>Requestor</th>
                    <th>Start Time</th>
                    <th>End Time</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
                <?php if (mysqli_num_rows($result_upcoming) > 0) { ?>
                    <?php while ($row = mysqli_fetch_assoc($result_upcoming)) { ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['Area']); ?></td>
                            <td><?php echo htmlspecialchars($row['Room']); ?></td>
                            <td><?php echo htmlspecialchars($row['Event_Name']); ?></td>
                            <td><?php echo htmlspecialchars($row['Booker_Name']); ?></td>
                            <td><?php echo htmlspecialchars($row['Start_Time']); ?></td>
                            <td><?php echo htmlspecialchars($row['End_Time']); ?></td>
                            <td><?php echo htmlspecialchars($row['Description']); ?></td>
                        </tr>
                    <?php } ?>
                <?php } else { ?>
                    <tr>
                        <td colspan="7">No booking is found.</td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</div>

<?php
// Close connection
mysqli_close($conn);
?>
