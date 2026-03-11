<?php
// api_log_event.php
header("Content-Type: application/json");
include 'db_connect.php';

$data = json_decode(file_get_contents("php://input"), true);

if (isset($data['elderID'], $data['eventType'], $data['videoPath'])) {
    $elderID = $conn->real_escape_string($data['elderID']);
    $eventType = $conn->real_escape_string($data['eventType']);
    $videoPath = $conn->real_escape_string($data['videoPath']);
    $status = "Pending"; 
    
    // FIX: Added backticks (`) around column names to prevent reserved word conflicts
    // FIX: Explicitly inserting empty strings for notes and caregiverMessage
    $sql = "INSERT INTO `eventlog` (`elderID`, `eventType`, `TIMESTAMP`, `STATUS`, `videoPath`, `notes`, `caregiverMessage`) 
            VALUES ('$elderID', '$eventType', NOW(), '$status', '$videoPath', '', '')";
    
    if ($conn->query($sql) === TRUE) {
        echo json_encode([
            "status" => "success",
            "eventID" => $conn->insert_id
        ]);
    } else {
        // This will send the exact SQL error back to Python
        echo json_encode(["status" => "error", "message" => $conn->error]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Missing data"]);
}
?>