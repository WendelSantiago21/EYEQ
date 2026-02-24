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

  $sql = "SELECT full_name, dob, email FROM users WHERE email = ? LIMIT 1";
  $stmt = $conn->prepare($sql);

  if (!$stmt) {
    throw new Exception("Prepare failed: " . $conn->error);
  }

  $stmt->bind_param("s", $email);
  $stmt->execute();
  $res = $stmt->get_result();

  if ($row = $res->fetch_assoc()) {
    echo json_encode(["status" => "success", "user" => $row]);
  } else {
    http_response_code(404);
    echo json_encode(["status" => "error", "message" => "User not found"]);
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
