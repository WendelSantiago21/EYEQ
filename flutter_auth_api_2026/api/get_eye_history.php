<?php
ini_set('display_errors', 0);
ini_set('html_errors', 0);
error_reporting(E_ALL);

header("Content-Type: application/json; charset=UTF-8");

try {
  require_once __DIR__ . "/db.php";

  $email = isset($_GET["email"]) ? trim($_GET["email"]) : "";
  if ($email === "") {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Missing email"]);
    exit;
  }

  // ✅ NO trailing comma before FROM
  $sql = "SELECT 
            id,
            email,
            wear_glasses,
            glasses_od,
            glasses_os,
            wear_contacts,
            contacts_od,
            contacts_os,
            vision_issue,
            astigmatism,
            eye_surgery,
            surgery_details,
            current_conditions,
            eye_doctor,
            last_eye_exam,
            additional_notes,
            step,
            created_at
          FROM eye_history
          WHERE email = ?
          ORDER BY created_at DESC
          LIMIT 1";

  $stmt = $conn->prepare($sql);
  if (!$stmt) {
    throw new Exception("Prepare failed: " . $conn->error);
  }

  $stmt->bind_param("s", $email);

  if (!$stmt->execute()) {
    throw new Exception("Execute failed: " . $stmt->error);
  }

  $res = $stmt->get_result();
  if ($row = $res->fetch_assoc()) {
    echo json_encode(["status" => "success", "eye_history" => $row]);
  } else {
    http_response_code(404);
    echo json_encode(["status" => "error", "message" => "Eye history not found"]);
  }

  $stmt->close();
  $conn->close();
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([
    "status" => "error",
    "message" => "Server error",
    "debug" => $e->getMessage()
  ]);
}
