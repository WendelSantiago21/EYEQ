<?php
header("Content-Type: application/json; charset=utf-8");
require_once "db.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
  // Read params safely
  $email = isset($_GET["email"]) ? trim($_GET["email"]) : "";
  $month = isset($_GET["month"]) ? trim($_GET["month"]) : "";

  // Default to current month if missing (yyyy-MM)
  if ($month === "") {
    $month = date("Y-m");
  }

  if ($email === "") {
    http_response_code(400);
    echo json_encode([
      "status" => "error",
      "message" => "email is required"
    ], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // Validate month format yyyy-MM
  if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    http_response_code(400);
    echo json_encode([
      "status" => "error",
      "message" => "month must be in yyyy-MM format"
    ], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // Month boundaries
  $start = $month . "-01";
  $end = date("Y-m-d", strtotime("$start +1 month"));

  // ✅ IMPORTANT:
  // - includes total_score + overall_case_severity
  // - safe ordering even if log_datetime is "0000-00-00 00:00:00"
  $stmt = $conn->prepare("
    SELECT
      id,
      email,
      log_date,
      log_time,
      log_datetime,
      symptoms,
      symptom_severity,
      total_score,
      overall_case_severity,
      created_at
    FROM symptom_logs
    WHERE email = ?
      AND log_date >= ?
      AND log_date < ?
    ORDER BY
      COALESCE(NULLIF(log_datetime, '0000-00-00 00:00:00'), created_at) DESC,
      created_at DESC
  ");

  $stmt->bind_param("sss", $email, $start, $end);
  $stmt->execute();
  $res = $stmt->get_result();

  $data = [];
  while ($row = $res->fetch_assoc()) {
    // Normalize empty string -> null for JSON fields
    foreach (["symptoms", "symptom_severity"] as $k) {
      if (isset($row[$k]) && trim((string)$row[$k]) === "") $row[$k] = null;
    }

    // Ensure numeric fields are numeric (optional)
    if (isset($row["total_score"]) && $row["total_score"] !== null) {
      $row["total_score"] = floatval($row["total_score"]);
    }

    $data[] = $row;
  }

  $stmt->close();

  echo json_encode([
    "status" => "success",
    "month" => $month,
    "range" => ["start" => $start, "end" => $end],
    "data" => $data
  ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([
    "status" => "error",
    "message" => "Server error",
    "debug" => $e->getMessage()
  ], JSON_UNESCAPED_UNICODE);
}