<?php
// monitoring.php
session_start();
include 'db_connect.php';

// Security Check
if (!isset($_SESSION['caregiverID'])) {
    header("Location: login.php");
    exit();
}

$caregiverID = $_SESSION['caregiverID'];
$caregiverName = $_SESSION['caregiverName'] ?? 'Caregiver';

// --- NEW: REDIRECT IF ALREADY MONITORING ---
// If an active session is stored, skip the setup and go straight to live view
if (isset($_SESSION['activeElderID'])) {
    header("Location: active_monitoring.php");
    exit();
}

// Fetch available elders for the dropdown
$elders = [];
$fetch_sql = "SELECT elderID, name FROM ElderProfile WHERE caregiverID = '$caregiverID' ORDER BY name ASC";
$result = $conn->query($fetch_sql);

if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $elders[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Start Monitoring - GoodLife Vision</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Shared Dashboard Styling (Same as dashboard and profiles) */
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

        /* Monitoring Specific Styling */
        .monitoring-container {
            display: flex;
            justify-content: center;
            align-items: center;
            flex-grow: 1;
            padding: 20px;
        }

        .setup-card {
            background-color: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.05);
            width: 100%;
            max-width: 500px;
            text-align: center;
        }

        .icon-header {
            width: 80px;
            height: 80px;
            background-color: #f0f7ff;
            color: #4a90e2;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 32px;
            margin: 0 auto 20px auto;
        }

        .setup-card h2 {
            margin: 0 0 10px 0;
            font-size: 24px;
            color: #2c3e50;
        }

        .setup-card p {
            color: #7f8c8d;
            font-size: 15px;
            margin-bottom: 30px;
            line-height: 1.5;
        }

        .status-indicator {
            display: inline-flex;
            align-items: center;
            background-color: #fdf2e9;
            color: #e67e22;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: bold;
            margin-bottom: 30px;
        }
        
        .status-dot {
            width: 8px;
            height: 8px;
            background-color: #e67e22;
            border-radius: 50%;
            margin-right: 8px;
        }

        .form-group {
            text-align: left;
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: bold;
            color: #34495e;
        }

        .form-select {
            width: 100%;
            padding: 14px;
            border: 2px solid #ecf0f1;
            border-radius: 8px;
            font-size: 15px;
            color: #2c3e50;
            background-color: #fcfcfc;
            outline: none;
            transition: border-color 0.3s;
            cursor: pointer;
            appearance: none; /* Removes default OS dropdown styling */
            background-image: url('data:image/svg+xml;charset=US-ASCII,<svg xmlns="http://www.w3.org/2000/svg" width="292.4" height="292.4"><path fill="%237f8c8d" d="M287 69.4a17.6 17.6 0 0 0-13-5.4H18.4c-5 0-9.3 1.8-12.9 5.4A17.6 17.6 0 0 0 0 82.2c0 5 1.8 9.3 5.4 12.9l128 127.9c3.6 3.6 7.8 5.4 12.8 5.4s9.2-1.8 12.8-5.4L287 95c3.5-3.5 5.4-7.8 5.4-12.8 0-5-1.9-9.2-5.5-12.8z"/></svg>');
            background-repeat: no-repeat;
            background-position: right 15px top 50%;
            background-size: 12px auto;
        }

        .form-select:focus {
            border-color: #4a90e2;
        }

        .btn-start {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #4a90e2, #5c6bc0);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .btn-start i {
            margin-right: 10px;
            font-size: 18px;
        }

        .btn-start:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(74, 144, 226, 0.3);
        }

        .btn-start:disabled {
            background: #bdc3c7;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .no-profile-warning {
            color: #e74c3c;
            font-size: 13px;
            margin-top: 10px;
            display: block;
        }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="logo-container"><img src="images/logo.jpg" alt="GoodLife Vision Logo"></div>
        <ul class="nav-links">
            <li><a href="dashboard.php"><i class="fa-solid fa-table-cells-large"></i> Dashboard</a></li>
            <li><a href="profiles.php"><i class="fa-solid fa-user-group"></i> Elder/OKU Profiles</a></li>
            <li><a href="monitoring.php" class="active"><i class="fa-solid fa-video"></i> Monitoring</a></li>
            <li><a href="alerts.php"><i class="fa-regular fa-bell"></i> Alerts</a></li>
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

        <div class="monitoring-container">
            <div class="setup-card">
                <div class="icon-header">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>
                
                <h2>Initiate Monitoring</h2>
                <p>Select the specific elder or OKU profile you wish to monitor. Ensure your camera is properly positioned.</p>

                <div class="status-indicator">
                    <span class="status-dot"></span>
                    Current Status: Standby
                </div>

                <form action="active_monitoring.php" method="POST">
                    <div class="form-group">
                        <label for="elderSelect">Select Elder/OKU to Monitor</label>
                        <select name="elderID" id="elderSelect" class="form-select" required>
                            <?php if (count($elders) > 0): ?>
                                <option value="" disabled selected>-- Choose a profile --</option>
                                <?php foreach ($elders as $elder): ?>
                                    <option value="<?php echo htmlspecialchars($elder['elderID']); ?>">
                                        <?php echo htmlspecialchars($elder['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="" disabled selected>No profiles available</option>
                            <?php endif; ?>
                        </select>
                        
                        <?php if (count($elders) == 0): ?>
                            <span class="no-profile-warning">
                                <i class="fa-solid fa-circle-exclamation"></i> You must register a profile first in the <a href="profiles.php">Profiles tab</a>.
                            </span>
                        <?php endif; ?>
                    </div>

                    <button type="submit" class="btn-start" <?php echo (count($elders) == 0) ? 'disabled' : ''; ?>>
                        <i class="fa-solid fa-video"></i> Start Monitoring
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script src="monitoring_global.js"></script>
</body>
</html>