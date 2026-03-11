<?php
// api_log_session.php
header("Content-Type: application/json");
include 'db_connect.php';

$data = json_decode(file_get_contents("php://input"), true);

if (isset($data['elderID'], $data['startTime'], $data['endTime'], $data['videoPath'])) {
    $elderID = $conn->real_escape_string($data['elderID']);
    $startTime = $conn->real_escape_string($data['startTime']);
    $endTime = $conn->real_escape_string($data['endTime']);
    $status = $conn->real_escape_string($data['status'] ?? 'Completed');
    $videoPath = $conn->real_escape_string($data['videoPath']);

    // Added backticks (`) around column names
    $sql = "INSERT INTO `monitoringsession` (`elderID`, `startTime`, `endTime`, `STATUS`, `videoPath`) 
            VALUES ('$elderID', '$startTime', '$endTime', '$status', '$videoPath')";
    
    if ($conn->query($sql) === TRUE) {
        echo json_encode(["status" => "success"]);
    } else {
        echo json_encode(["status" => "error", "message" => $conn->error]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Missing data"]);
}
?>