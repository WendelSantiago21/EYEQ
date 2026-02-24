<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

include __DIR__ . "/config.php";

$data = json_decode(file_get_contents("php://input"), true);

if (!$data || empty($data['email'])) {
    echo json_encode(["status" => "error", "message" => "Email required"]);
    exit;
}

$email = trim($data['email']);
$today = date("Y-m-d");

/**
 * -------------------------
 * DAILY STATS
 * -------------------------
 */
$stmt = $conn->prepare("
    SELECT total_sessions, total_minutes
    FROM daily_eye_stats
    WHERE email = ? AND stat_date = ?
");
$stmt->execute([$email, $today]);
$daily = $stmt->fetch(PDO::FETCH_ASSOC);

$sessionsToday = $daily['total_sessions'] ?? 0;
$totalMinutesToday = $daily['total_minutes'] ?? 0;

/**
 * -------------------------
 * RECENT SESSIONS (last 10)
 * -------------------------
 */
$stmt = $conn->prepare("
    SELECT 
        session_date,
        session_time,
        duration_minutes,
        completed,
        TIMESTAMP(session_date, session_time) AS session_datetime
    FROM eye_sessions
    WHERE email = ?
    ORDER BY session_date DESC, session_time DESC
    LIMIT 10
");
$stmt->execute([$email]);

$recentSessions = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
	
	$datetime = $row['session_datetime'];
	
    $recentSessions[] = [
        "datetime" => $row['session_datetime'],
        "duration" => (int)$row['duration_minutes'],
        "completed" => (bool)$row['completed']
    ];
}

/**
 * -------------------------
 * WEEKLY STREAK (CONSECUTIVE DAYS)
 * -------------------------
 */
$weeklyStreak = 0;
$currentDate = new DateTime();

for ($i = 0; $i < 30; $i++) {
    $dateStr = $currentDate->format("Y-m-d");

    $checkStmt = $conn->prepare("
        SELECT COUNT(*) 
        FROM daily_eye_stats
        WHERE email = ?
          AND stat_date = ?
          AND total_sessions > 0
    ");
    $checkStmt->execute([$email, $dateStr]);
    $count = (int)$checkStmt->fetchColumn();

    if ($count > 0) {
        $weeklyStreak++;
        $currentDate->modify("-1 day");
    } else {
        break;
    }
}

/**
 * -------------------------
 * IMPROVEMENT % (Today vs Yesterday)
 * -------------------------
 */

// Fetch all severity records for today
$stmt = $conn->prepare("
    SELECT overall_case_severity
    FROM symptom_logs
    WHERE email = ? AND log_date = ?
");
$stmt->execute([$email, $today]);
$todaySeverities = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Fetch all severity records for yesterday
$yesterday = date('Y-m-d', strtotime('-1 day'));
$stmt = $conn->prepare("
    SELECT overall_case_severity
    FROM symptom_logs
    WHERE email = ? AND log_date = ?
");
$stmt->execute([$email, $yesterday]);
$yesterdaySeverities = $stmt->fetchAll(PDO::FETCH_COLUMN);

$improvement = 0;
$todayAvgSeverity = 0.0;
$yesterdayAvgSeverity = 0.0;

// Fetch severity values from the severity_values table
$severityQuery = "SELECT severity_label, severity_value FROM severity_values";
$severityStmt = $conn->prepare($severityQuery);
$severityStmt->execute();

$severityValues = [];
while ($row = $severityStmt->fetch(PDO::FETCH_ASSOC)) {
    $severityValues[$row['severity_label']] = $row['severity_value'];
}

// Compute the average severity for today
if (count($todaySeverities) > 0) {
    $todayTotal = 0;
    foreach ($todaySeverities as $severity) {
        $todayTotal += $severityValues[$severity] ?? 0.0;
    }
    $todayAvgSeverity = $todayTotal / count($todaySeverities);
}

// Compute the average severity for yesterday
if (count($yesterdaySeverities) > 0) {
    $yesterdayTotal = 0;
    foreach ($yesterdaySeverities as $severity) {
        $yesterdayTotal += $severityValues[$severity] ?? 0.0;
    }
    $yesterdayAvgSeverity = $yesterdayTotal / count($yesterdaySeverities);
}

// Calculate improvement if yesterday's severity is greater than 0
if ($yesterdayAvgSeverity > 0) {
    // Apply the formula: ((past severity - current severity) / past severity) * 100
    $improvement = round((($yesterdayAvgSeverity - $todayAvgSeverity) / $yesterdayAvgSeverity) * 100);
}

/**
 * -------------------------
 * RESPONSE
 * -------------------------
 */
echo json_encode([
    "status" => "success",
    "data" => [
        "sessions_today" => (int)$sessionsToday,
        "total_minutes_today" => (int)$totalMinutesToday,
        "weekly_streak" => (int)$weeklyStreak,
        "target_streak" => 14,
        "improvement_percent" => (int)$improvement,
    ],
    "recent_sessions" => $recentSessions
]);
?>