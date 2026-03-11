<?php
// clear_active_session.php
session_start();
unset($_SESSION['activeElderID']);
unset($_SESSION['activeStartTime']);
echo json_encode(["status" => "success"]);
?>