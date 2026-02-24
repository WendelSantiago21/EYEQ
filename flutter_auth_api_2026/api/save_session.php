<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

include __DIR__ . "/config.php";

$data = json_decode(file_get_contents("php://input"), true);

if (!$data || empty($data['email'])) {
    echo json_encode(["status" => "error", "message" => "Invalid input"]);
    exit;
}

$email = trim($data['email']);
$completed = isset($data['completed']) ? (int)$data['completed'] : 0;
$duration = isset($data['duration']) ? (int)$data['duration'] : 20;

$today = date('Y-m-d' , strtotime('now'));
$now = date('H:i:sa', strtotime('now'));

/**
 * Insert session
 */
$stmt = $conn->prepare("
    INSERT INTO eye_sessions 
    (email, session_date, session_time, duration_minutes, completed)
    VALUES (?, ?, ?, ?, ?)
");
$stmt->execute([$email, $today, $now, $duration, $completed]);

/**
 * Update daily stats
 */
$minutes = $completed ? $duration : 0;

$stmt = $conn->prepare("
    INSERT INTO daily_eye_stats (email, stat_date, total_sessions, total_minutes)
    VALUES (?, ?, 1, ?)
    ON DUPLICATE KEY UPDATE
      total_sessions = total_sessions + 1,
      total_minutes = total_minutes + ?
");
$stmt->execute([$email, $today, $minutes, $minutes]);

echo json_encode([
    "status" => "success",
    "session" => [
        "date" => $today,
        "time" => $now,
        "duration" => $duration,
        "completed" => $completed
    ]
]);
