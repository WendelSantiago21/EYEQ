<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

include __DIR__ . "/config.php";

$data = json_decode(file_get_contents("php://input"), true);

if (
    !$data ||
    empty($data['email']) ||
    empty($data['severity']) ||
    empty($data['symptoms']) ||
    !is_array($data['symptoms'])
) {
    echo json_encode(["status" => "error", "message" => "Invalid input"]);
    exit;
}

$email = trim($data['email']);
$severity = $data['severity'];
$symptoms = $data['symptoms'];

/**
 * Insert symptom log
 */
$stmt = $conn->prepare("
    INSERT INTO symptom_logs (email, symptom_date, severity)
    VALUES (?, NOW(), ?)
");
$stmt->execute([$email, $severity]);

$logId = $conn->lastInsertId();

/**
 * Insert symptom items
 */
$itemStmt = $conn->prepare("
    INSERT INTO symptom_items (symptom_log_id, symptom_name)
    VALUES (?, ?)
");

foreach ($symptoms as $symptom) {
    $itemStmt->execute([$logId, trim($symptom)]);
}

echo json_encode([
    "status" => "success",
    "log" => [
        "id" => $logId,
        "severity" => $severity,
        "symptoms" => $symptoms,
        "created_at" => date("Y-m-d H:i:s")
    ]
]);
