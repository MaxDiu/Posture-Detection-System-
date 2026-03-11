<?php
// alerts.php
session_start();
include 'db_connect.php';

// Security Check
if (!isset($_SESSION['caregiverID'])) {
    header("Location: login.php");
    exit();
}

$caregiverID = $_SESSION['caregiverID'];
$caregiverName = $_SESSION['caregiverName'] ?? 'Caregiver';

// Handle Actions: Acknowledge or Dismiss Alerts
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $eventID = $conn->real_escape_string($_GET['id']);
    
    if ($action === 'ack') {
        $update_sql = "UPDATE eventlog SET STATUS = 'Acknowledged' WHERE eventID = '$eventID'";
        $conn->query($update_sql);
    } elseif ($action === 'dismiss') {
        $update_sql = "UPDATE eventlog SET STATUS = 'Dismissed' WHERE eventID = '$eventID'";
        $conn->query($update_sql);
    }
    
    header("Location: alerts.php");
    exit();
}

// 1. Fetch all ALERTS (eventlog)
$alerts = [];
$fetch_alerts_sql = "SELECT el.eventID, el.eventType, el.TIMESTAMP, el.STATUS, el.videoPath, ep.name AS elderName 
              FROM eventlog el 
              JOIN ElderProfile ep ON el.elderID = ep.elderID 
              WHERE ep.caregiverID = '$caregiverID' 
              ORDER BY el.TIMESTAMP DESC";
$result_alerts = $conn->query($fetch_alerts_sql);
if ($result_alerts && $result_alerts->num_rows > 0) {
    while($row = $result_alerts->fetch_assoc()) {
        $alerts[] = $row;
    }
}

// 2. Fetch all SESSIONS (monitoringsession)
$sessions = [];
$fetch_sessions_sql = "SELECT ms.sessionID, ms.startTime, ms.endTime, ms.STATUS, ms.videoPath, ep.name AS elderName 
              FROM monitoringsession ms 
              JOIN ElderProfile ep ON ms.elderID = ep.elderID 
              WHERE ep.caregiverID = '$caregiverID' 
              ORDER BY ms.startTime DESC";
$result_sessions = $conn->query($fetch_sessions_sql);
if ($result_sessions && $result_sessions->num_rows > 0) {
    while($row = $result_sessions->fetch_assoc()) {
        $sessions[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alert History - GoodLife Vision</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Shared Dashboard Styling */
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; background-color: #f8f9fc; display: flex; height: 100vh; color: #2c3e50; }
        .sidebar { width: 260px; background-color: #ffffff; border-right: 1px solid #eaeaea; display: flex; flex-direction: column; padding-top: 20px; }
        .logo-container { padding: 0 20px 30px 20px; text-align: center; }
        .logo-container img { width: 140px; height: auto; }
        .nav-links { list-style: none; padding: 0; margin: 0; flex-grow: 1; }
        .nav-links li { margin-bottom: 5px; }
        .nav-links a { display: flex; align-items: center; padding: 14px 24px; color: #7f8c8d; text-decoration: none; font-size: 15px; font-weight: 500; transition: all 0.3s; }
        .nav-links a i { margin-right: 15px; font-size: 18px; width: 20px; text-align: center; }
        .nav-links a:hover { background-color: #f4f7f6; color: #2c3e50; }
        .nav-links a.active { color: #4a90e2; border-left: 4px solid #4a90e2; background-color: #f0f7ff; }
        .main-content { flex-grow: 1; display: flex; flex-direction: column; overflow-y: auto; }
        .top-bar { display: flex; justify-content: flex-end; align-items: center; padding: 20px 40px; background-color: #f8f9fc; }
        .profile-info { text-align: right; margin-right: 20px; }
        .profile-info h4 { margin: 0; font-size: 14px; color: #2c3e50; }
        .profile-info p { margin: 0; font-size: 12px; color: #7f8c8d; }
        .logout-btn { color: #7f8c8d; text-decoration: none; font-size: 14px; display: flex; align-items: center; transition: color 0.3s; }
        .logout-btn i { margin-left: 8px; }
        .logout-btn:hover { color: #e74c3c; }

        /* Alerts Page Specific Styling */
        .page-header { padding: 20px 60px 10px 60px; }
        .page-header h1 { margin: 0; font-size: 24px; }
        .page-header p { color: #7f8c8d; margin-top: 5px; font-size: 14px; }
        
        .content-body { padding: 20px 60px; }

        /* Tabs Styling */
        .tabs-container { margin-bottom: 15px; display: flex; gap: 10px; }
        .tab-btn { padding: 10px 24px; border: none; background: #e5e7eb; color: #4b5563; border-radius: 8px; font-size: 14px; font-weight: bold; cursor: pointer; transition: all 0.3s; }
        .tab-btn:hover { background: #d1d5db; }
        .tab-btn.active { background: #4a90e2; color: white; box-shadow: 0 4px 10px rgba(74, 144, 226, 0.3); }
        .tab-content { display: none; }
        .tab-content.active { display: block; animation: fadeIn 0.4s; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }

        /* Table Styling */
        .table-container { background-color: white; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); overflow: hidden; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        thead { background-color: #f4f7f6; }
        th { padding: 16px 20px; font-size: 13px; color: #7f8c8d; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #eaeaea; }
        td { padding: 16px 20px; font-size: 14px; color: #2c3e50; border-bottom: 1px solid #eaeaea; vertical-align: middle; }
        tbody tr:hover { background-color: #fdfdfe; }
        tbody tr:last-child td { border-bottom: none; }

        /* Status Badges */
        .badge { padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; display: inline-block; }
        .badge.pending { background-color: #fee2e2; color: #e74c3c; }
        .badge.acknowledged { background-color: #d1fae5; color: #059669; }
        .badge.dismissed { background-color: #f3f4f6; color: #6b7280; }
        .badge.completed { background-color: #e0f2fe; color: #0284c7; }

        /* Action Buttons */
        .action-btns { display: flex; gap: 8px; }
        .btn-action { padding: 6px 12px; border: none; border-radius: 6px; font-size: 12px; font-weight: bold; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; transition: opacity 0.3s; }
        .btn-action i { margin-right: 5px; }
        .btn-action:hover { opacity: 0.8; }
        .btn-ack { background-color: #4a90e2; color: white; }
        .btn-dismiss { background-color: #e5e7eb; color: #4b5563; }
        .btn-watch { background-color: #27ae60; color: white; }

        .empty-state { text-align: center; padding: 60px 20px; }
        .empty-state i { font-size: 48px; color: #bdc3c7; margin-bottom: 15px; }
        .empty-state h3 { color: #2c3e50; margin-bottom: 10px; }
        .empty-state p { color: #7f8c8d; }
        .type-icon { margin-right: 8px; color: #e67e22; }

        /* Video Modal Styling */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.7); backdrop-filter: blur(5px); }
        .modal-content { background-color: white; margin: 5% auto; padding: 25px; border-radius: 12px; width: 80%; max-width: 800px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); position: relative; }
        .close-modal { position: absolute; top: 15px; right: 20px; font-size: 28px; font-weight: bold; color: #95a5a6; cursor: pointer; transition: 0.3s; }
        .close-modal:hover { color: #e74c3c; }
        .modal-content h3 { margin-top: 0; margin-bottom: 15px; color: #2c3e50; }
        .video-wrapper { width: 100%; border-radius: 8px; overflow: hidden; background: #000; display: flex; justify-content: center; }
        video { width: 100%; max-height: 500px; outline: none; }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="logo-container"><img src="images/logo.jpg" alt="GoodLife Vision Logo"></div>
        <ul class="nav-links">
            <li><a href="dashboard.php"><i class="fa-solid fa-table-cells-large"></i> Dashboard</a></li>
            <li><a href="profiles.php"><i class="fa-solid fa-user-group"></i> Elder/OKU Profiles</a></li>
            <li><a href="monitoring.php"><i class="fa-solid fa-video"></i> Monitoring</a></li>
            <li><a href="alerts.php" class="active"><i class="fa-regular fa-bell"></i> Alerts</a></li>
            <li><a href="settings.php"><i class="fa-solid fa-gear"></i> Settings</a></li>
        </ul>
    </div>

    <div class="main-content">
        <div class="top-bar">
            <div class="profile-info">
                <h4><?php echo htmlspecialchars($caregiverName); ?></h4>
                <p>Caregiver</p>
            </div>
            <a href="logout.php" class="logout-btn"><i class="fa-solid fa-arrow-right-from-bracket"></i> Logout</a>
        </div>

        <div class="page-header">
            <h1>Alert & Session History</h1>
            <p>Review specific 10-second alerts and full monitoring session recordings.</p>
        </div>

        <div class="content-body">
            
            <div class="tabs-container">
                <button class="tab-btn active" onclick="switchTab('alertsTab', this)">
                    <i class="fa-solid fa-triangle-exclamation"></i> Alert Events
                </button>
                <button class="tab-btn" onclick="switchTab('sessionsTab', this)">
                    <i class="fa-solid fa-video"></i> Full Sessions
                </button>
            </div>

            <div id="alertsTab" class="tab-content active">
                <div class="table-container">
                    <?php if (count($alerts) > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Date & Time</th>
                                    <th>Elder Target</th>
                                    <th>Event Type</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($alerts as $alert): 
                                    $dateObj = new DateTime($alert['TIMESTAMP']);
                                    $formattedDate = $dateObj->format('M d, Y \a\t h:i A');
                                    
                                    $status = $alert['STATUS'] ?? 'Pending';
                                    $badgeClass = 'pending';
                                    if ($status == 'Acknowledged') $badgeClass = 'acknowledged';
                                    if ($status == 'Dismissed') $badgeClass = 'dismissed';
                                ?>
                                <tr>
                                    <td><strong><?php echo $formattedDate; ?></strong></td>
                                    <td><?php echo htmlspecialchars($alert['elderName']); ?></td>
                                    <td>
                                        <i class="fa-solid fa-triangle-exclamation type-icon"></i> 
                                        <?php echo htmlspecialchars($alert['eventType']); ?>
                                    </td>
                                    <td><span class="badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($status); ?></span></td>
                                    <td>
                                        <div class="action-btns">
                                            <?php if (!empty($alert['videoPath'])): 
                                                $vPath = str_replace(' ', '%20', $alert['videoPath']);
                                            ?>
                                                <button class="btn-action btn-watch" onclick="openVideo('<?php echo $vPath; ?>', 'Alert: <?php echo htmlspecialchars($alert['eventType']); ?>')">
                                                    <i class="fa-solid fa-play"></i> Watch
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fa-regular fa-folder-open"></i>
                            <h3>No Alerts Found</h3>
                            <p>There are no emergency alerts logged in the system.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div id="sessionsTab" class="tab-content">
                <div class="table-container">
                    <?php if (count($sessions) > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Session Started</th>
                                    <th>Session Ended</th>
                                    <th>Elder Target</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sessions as $session): 
                                    $startObj = new DateTime($session['startTime']);
                                    $formattedStart = $startObj->format('M d, Y \a\t h:i A');
                                    
                                    $endObj = new DateTime($session['endTime']);
                                    $formattedEnd = $endObj->format('h:i A'); // Just show time for end
                                    
                                    $badgeClass = ($session['STATUS'] == 'Completed') ? 'completed' : 'pending';
                                ?>
                                <tr>
                                    <td><strong><?php echo $formattedStart; ?></strong></td>
                                    <td><?php echo $formattedEnd; ?></td>
                                    <td><?php echo htmlspecialchars($session['elderName']); ?></td>
                                    <td><span class="badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($session['STATUS']); ?></span></td>
                                    <td>
                                        <div class="action-btns">
                                            <?php if (!empty($session['videoPath'])): 
                                                $sVPath = str_replace(' ', '%20', $session['videoPath']);
                                            ?>
                                                <button class="btn-action btn-watch" onclick="openVideo('<?php echo $sVPath; ?>', 'Full Session: <?php echo htmlspecialchars($session['elderName']); ?>')">
                                                    <i class="fa-solid fa-play"></i> Watch Full
                                                </button>
                                            <?php else: ?>
                                                <span style="color: #95a5a6; font-size: 12px; font-style: italic;">No video file</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fa-regular fa-folder-open"></i>
                            <h3>No Sessions Found</h3>
                            <p>No complete monitoring sessions have been recorded yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>

    <div id="videoModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeVideo()">&times;</span>
            <h3 id="modalVideoTitle">Video Playback</h3>
            <div class="video-wrapper">
                <video id="webPlayer" controls>
                    <source id="videoSource" src="" type="video/webm">
                    Your browser does not support the video tag.
                </video>
            </div>
        </div>
    </div>

    <script>
        // Tab Switcher Logic
        function switchTab(tabId, btnElement) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            // Remove active class from all buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab and highlight button
            document.getElementById(tabId).classList.add('active');
            btnElement.classList.add('active');
        }

        // Video Player Modal Logic
        function openVideo(videoPath, title) {
            const modal = document.getElementById('videoModal');
            const player = document.getElementById('webPlayer');
            const source = document.getElementById('videoSource');
            const titleElement = document.getElementById('modalVideoTitle');
            
            // Set the video file path and title
            titleElement.innerText = title;
            source.src = videoPath;
            
            // Load and show the video
            player.load();
            modal.style.display = 'block';
            player.play(); // Auto-play when opened
        }

        function closeVideo() {
            const modal = document.getElementById('videoModal');
            const player = document.getElementById('webPlayer');
            
            // Pause and hide the video
            player.pause();
            modal.style.display = 'none';
            document.getElementById('videoSource').src = ""; // Clear source
        }

        // Close modal if user clicks outside of the video box
        window.onclick = function(event) {
            const modal = document.getElementById('videoModal');
            if (event.target == modal) {
                closeVideo();
            }
        }
    </script>
    <script src="monitoring_global.js"></script>
</body>
</html>