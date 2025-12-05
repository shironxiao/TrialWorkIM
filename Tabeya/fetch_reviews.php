<?php
// Database connection details
 require_once(__DIR__ . '/api/config/db_config.php');

// Fetch only approved reviews
$sql = "SELECT review_id, user_name, user_review, user_rating FROM review_table WHERE status = 'Approved' ORDER BY datetime DESC";
$result = $conn->query($sql);

$reviews = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $reviews[] = $row;
    }
}

// Send reviews as JSON
header('Content-Type: application/json');
echo json_encode($reviews);

$conn->close();
?>