<?php
header("Content-Type: application/json; charset=utf-8");
require_once __DIR__ . "/db.php";

$email = trim($_GET["email"] ?? "");
$date  = trim($_GET["date"] ?? "");

// ------------------------------
// VALIDATION
// ------------------------------
if ($email === "" || $date === "") {
  http_response_code(400);
  echo json_encode([
    "status" => "error",
    "message" => "email and date are required"
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
  http_response_code(400);
  echo json_encode([
    "status" => "error",
    "message" => "date must be in YYYY-MM-DD format"
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

// ------------------------------
// QUERY (NO severity COLUMN)
// ------------------------------
$sql = "
  SELECT
    id,
    log_time,
    log_datetime,
    symptoms,
    symptom_severity,
    total_score,
    overall_case_severity,
    created_at
  FROM symptom_logs
  WHERE email = ?
    AND log_date = ?
  ORDER BY log_datetime DESC
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
  http_response_code(500);
  echo json_encode([
    "status" => "error",
    "message" => "Server error preparing query",
    "debug" => $conn->error
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

$stmt->bind_param("ss", $email, $date);

if (!$stmt->execute()) {
  http_response_code(500);
  echo json_encode([
    "status" => "error",
    "message" => "Server error executing query",
    "debug" => $stmt->error
  ], JSON_UNESCAPED_UNICODE);
  $stmt->close();
  exit;
}

$res = $stmt->get_result();
if (!$res) {
  http_response_code(500);
  echo json_encode([
    "status" => "error",
    "message" => "Server error reading result"
  ], JSON_UNESCAPED_UNICODE);
  $stmt->close();
  exit;
}

// ------------------------------
// FORMAT RESPONSE
// ------------------------------
$data = [];
while ($row = $res->fetch_assoc()) {
  foreach (["symptoms", "symptom_severity"] as $k) {
    if (isset($row[$k]) && trim((string)$row[$k]) === "") {
      $row[$k] = null;
    }
  }
  $data[] = $row;
}

$stmt->close();

echo json_encode([
  "status" => "success",
  "date" => $date,
  "count" => count($data),
  "data" => $data
], JSON_UNESCAPED_UNICODE);
exit;
?>
