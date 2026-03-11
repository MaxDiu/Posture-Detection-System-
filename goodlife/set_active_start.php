<?php
// set_active_start.php
session_start();
if (!isset($_SESSION['activeStartTime'])) {
    // Store as UNIX timestamp in milliseconds
    $_SESSION['activeStartTime'] = time() * 1000;
}
echo json_encode(["status" => "success", "startTime" => $_SESSION['activeStartTime']]);
?>