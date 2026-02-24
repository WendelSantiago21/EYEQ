<?php
header("Content-Type: application/json; charset=UTF-8");

$DB_HOST = "localhost";
$DB_USER = "root";
$DB_PASS = "";
$DB_NAME = "flutter_auth";

$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

if ($conn->connect_error) {
  http_response_code(500);
  echo json_encode([
    "status" => "error",
    "message" => "Database connection failed"
  ]);
  exit;
}
date_default_timezone_set('Asia/Manila');
$conn->set_charset("utf8mb4");
?>
