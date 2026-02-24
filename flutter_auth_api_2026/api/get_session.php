<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

include __DIR__ . "/config.php";

$data = json_decode(file_get_contents("php://input"), true);

if (!$data || empty($data['email'])) {
    echo json_encode([]);
    exit;
}

$email = trim($data['email']);

$stmt = $conn->prepare("
    SELECT 
        session_date,
        session_time,
        duration_minutes,
        completed
    FROM eye_sessions
    WHERE email = ?
    ORDER BY session_date DESC, session_time DESC
    LIMIT 50
");

$stmt->execute([$email]);

$sessions = [];

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    [$hour, $minute] = explode(':', $row['session_time']);

    $sessions[] = [
        "hour" => (int)$hour,
        "minute" => (int)$minute,
        "duration_minutes" => (int)$row['duration_minutes'],
        "completed" => (int)$row['completed'],
    ];
}

echo json_encode($sessions);
