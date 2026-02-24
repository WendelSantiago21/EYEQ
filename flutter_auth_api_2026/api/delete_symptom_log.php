<?php
header("Content-Type: application/json; charset=utf-8");
require_once "db.php";

$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

if (!is_array($data)) {
  http_response_code(400);
  echo json_encode(["status"=>"error","message"=>"Invalid JSON"]);
  exit;
}

$email = trim($data["email"] ?? "");
$id    = (int)($data["id"] ?? 0);

if ($email === "" || $id <= 0) {
  http_response_code(400);
  echo json_encode(["status"=>"error","message"=>"email and valid id are required"]);
  exit;
}

$stmt = $conn->prepare("DELETE FROM symptom_logs WHERE id=? AND email=?");
if (!$stmt) {
  http_response_code(500);
  echo json_encode(["status"=>"error","message"=>"Server error","debug"=>$conn->error]);
  exit;
}
$stmt->bind_param("is", $id, $email);
$ok = $stmt->execute();
$affected = $stmt->affected_rows;
$err = $stmt->error;
$stmt->close();

if (!$ok) {
  http_response_code(500);
  echo json_encode(["status"=>"error","message"=>"Delete failed","debug"=>$err]);
  exit;
}

if ($affected === 0) {
  echo json_encode(["status"=>"error","message"=>"No record deleted (not found or not yours)"]);
  exit;
}

echo json_encode(["status"=>"success","message"=>"Deleted successfully"]);
