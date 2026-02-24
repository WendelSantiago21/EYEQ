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
        sl.id,
        sl.symptom_date,
        sl.severity,
        GROUP_CONCAT(si.symptom_name) AS symptoms
    FROM symptom_logs sl
    JOIN symptom_items si ON sl.id = si.symptom_log_id
    WHERE sl.email = ?
    GROUP BY sl.id
    ORDER BY sl.symptom_date DESC
");

$stmt->execute([$email]);

$logs = [];

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $logs[] = [
        "created_at" => $row['symptom_date'],
        "severity" => $row['severity'],
        "symptoms" => explode(',', $row['symptoms']),
    ];
}

echo json_encode($logs);
