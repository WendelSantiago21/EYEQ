<?php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . "/db.php";

$input = json_decode(file_get_contents("php://input"), true);
$email = trim($input["email"] ?? "");
$full_name = trim($input["full_name"] ?? "");
$dob = trim($input["dob"] ?? "");

if ($email === "" || $full_name === "" || $dob === "") {
  echo json_encode(["status" => "error", "message" => "Missing required fields"]);
  exit;
}

$stmt = $conn->prepare("UPDATE users SET full_name=?, dob=? WHERE email=?");
$stmt->bind_param("sss", $full_name, $dob, $email);

if ($stmt->execute()) {
  echo json_encode(["status" => "success", "message" => "User updated"]);
} else {
  echo json_encode(["status" => "error", "message" => $stmt->error]);
}

$stmt->close();
$conn->close();
?>
