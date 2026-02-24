<?php
header("Content-Type: application/json; charset=utf-8");
require_once "db.php";

// Always return JSON even on warnings/notices
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
  $raw = file_get_contents("php://input");
  $data = json_decode($raw, true);

  if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(["status"=>"error","message"=>"Invalid JSON"]);
    exit;
  }

  $email        = trim($data["email"] ?? "");
  $log_date     = trim($data["log_date"] ?? "");
  $log_time     = trim($data["log_time"] ?? date("H:i:s"));
  $log_datetime = trim($data["log_datetime"] ?? "");

  // Preferred: symptom_severity map (symptom => Mild|Moderate|Severe|None)
  $symptom_severity = $data["symptom_severity"] ?? null;

  // Also allow Flutter sending severities in "symptoms" as MAP (legacy)
  $symptoms = $data["symptoms"] ?? null;

  if ($email === "" || $log_date === "") {
    http_response_code(400);
    echo json_encode(["status"=>"error","message"=>"email and log_date are required"]);
    exit;
  }

  if ($log_datetime === "") {
    $log_datetime = $log_date . " " . $log_time;
  }

  // helper: check if array is associative
  function is_assoc_array($arr) {
    if (!is_array($arr)) return false;
    $keys = array_keys($arr);
    return array_keys($keys) !== $keys;
  }

  $severityValue = [
    "None" => 0,
    "Mild" => 1,
    "Moderate" => 2,
    "Severe" => 3
  ];
  $allowedSeverity = array_keys($severityValue);

  // ----------------------------
  // 1) Normalize symptom -> severity map
  // ----------------------------
  $symptomMap = [];

  if (is_array($symptom_severity) && count($symptom_severity) > 0) {
    foreach ($symptom_severity as $symptom => $sev) {
      $symptom = trim((string)$symptom);
      $sev = trim((string)$sev);

      if ($symptom === "") continue;
      if (!in_array($sev, $allowedSeverity, true)) {
        http_response_code(400);
        echo json_encode(["status"=>"error","message"=>"Invalid severity for symptom: $symptom"]);
        exit;
      }

      $symptomMap[$symptom] = $sev;
    }
  } else if (is_assoc_array($symptoms) && count($symptoms) > 0) {
    foreach ($symptoms as $symptom => $sev) {
      $symptom = trim((string)$symptom);
      $sev = trim((string)$sev);

      if ($symptom === "") continue;
      if (!in_array($sev, $allowedSeverity, true)) {
        http_response_code(400);
        echo json_encode(["status"=>"error","message"=>"Invalid severity for symptom: $symptom"]);
        exit;
      }

      $symptomMap[$symptom] = $sev;
    }
  } else {
    http_response_code(400);
    echo json_encode(["status"=>"error","message"=>"symptom_severity (map) is required"]);
    exit;
  }

  if (count($symptomMap) === 0) {
    http_response_code(400);
    echo json_encode(["status"=>"error","message"=>"No valid symptoms provided"]);
    exit;
  }

  $symptomsJson = json_encode(array_values(array_keys($symptomMap)), JSON_UNESCAPED_UNICODE);
  $symptomSeverityJson = json_encode($symptomMap, JSON_UNESCAPED_UNICODE);

  // ----------------------------
  // 2) Compute weight × severity_value
  // ----------------------------
  $totalScore = 0.0;
  $items = []; // ✅ ONLY per-symptom info (no total/overall inside JSON)

  $wStmt = $conn->prepare("SELECT weight FROM symptom_weights WHERE symptom_name = ? LIMIT 1");

  foreach ($symptomMap as $symptom => $sevText) {
    $sevVal = $severityValue[$sevText] ?? 1;

    $weight = 1.0; // default if not found
    $wStmt->bind_param("s", $symptom);
    $wStmt->execute();
    $res = $wStmt->get_result();

    if ($res && ($row = $res->fetch_assoc())) {
      $weight = floatval($row["weight"]);
    }

    $weightedScore = $weight * $sevVal;
    $totalScore += $weightedScore;

    $items[] = [
      "symptom_name"   => $symptom,
      "severity_label" => $sevText,
      "severity_value" => $sevVal,
      "weight"         => $weight,
      "weighted_score" => $weightedScore
    ];
  }

  $wStmt->close();

  // Overall Case Severity (your rule)
$overall = "None"; // Default to "None"
if ($totalScore > 6.5) {
    $overall = "Severe"; // Above 6.5
} else if ($totalScore > 3.0) {
    $overall = "Moderate"; // Between 3.1 and 6.5
} else if ($totalScore > 0) {
    $overall = "Mild"; // Between 0.1 and 3.0
}

  // Use computed overall as main severity column too
  $severity_summary = $overall;

  // ✅ items_json contains ONLY the per-symptom breakdown
  $itemsJson = json_encode($items, JSON_UNESCAPED_UNICODE);

  // ----------------------------
  // 3) Insert into symptom_logs + symptom_log_items (transaction)
  // ----------------------------
  $conn->begin_transaction();

  // symptom_logs
  $stmt = $conn->prepare("
    INSERT INTO symptom_logs
      (email, log_date, log_time, log_datetime, symptoms, symptom_severity, severity, total_score, overall_case_severity)
    VALUES
      (?,?,?,?,?,?,?,?,?)
  ");

  // 7 strings + double + string => "sssssssds"
  $stmt->bind_param(
    "sssssssds",
    $email,
    $log_date,
    $log_time,
    $log_datetime,
    $symptomsJson,
    $symptomSeverityJson,
    $severity_summary,
    $totalScore,
    $overall
  );

  $stmt->execute();
  $logId = $conn->insert_id;
  $stmt->close();

  // symptom_log_items (ONE row per log_id)
  $itemStmt = $conn->prepare("
    INSERT INTO symptom_log_items
      (log_id, items_json, total_score, overall_case_severity)
    VALUES
      (?,?,?,?)
  ");

  $itemStmt->bind_param("isds", $logId, $itemsJson, $totalScore, $overall);
  $itemStmt->execute();
  $itemStmt->close();

  $conn->commit();

  echo json_encode([
    "status" => "success",
    "message" => "Symptom log saved",
    "log_id" => $logId,
    "total_score" => $totalScore,
    "overall_case_severity" => $overall
  ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  if (isset($conn)) {
    try { $conn->rollback(); } catch (Throwable $ignore) {}
  }
  http_response_code(500);
  echo json_encode([
    "status" => "error",
    "message" => "Server error",
    "debug" => $e->getMessage()
  ], JSON_UNESCAPED_UNICODE);
}