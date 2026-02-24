<?php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . "/db.php";

$input = json_decode(file_get_contents("php://input"), true);
$email = trim($input["email"] ?? "");

if ($email === "") {
  echo json_encode(["status" => "error", "message" => "Missing email"]);
  exit;
}

$stmt = $conn->prepare("
  UPDATE eye_history
  SET
    wear_glasses=?,
    glasses_od=?,
    glasses_os=?,
    wear_contacts=?,
    contacts_od=?,
    contacts_os=?,
    vision_issue=?,
    astigmatism=?,
    eye_surgery=?,
    surgery_details=?,
    current_conditions=?,
    eye_doctor=?,
    last_eye_exam=?,
    additional_notes=?
  WHERE email=?
  ORDER BY created_at DESC
  LIMIT 1
");

$wear_glasses = intval($input["wear_glasses"] ?? 0);
$glasses_od = trim($input["glasses_od"] ?? "");
$glasses_os = trim($input["glasses_os"] ?? "");
$wear_contacts = intval($input["wear_contacts"] ?? 0);
$contacts_od = trim($input["contacts_od"] ?? "");
$contacts_os = trim($input["contacts_os"] ?? "");
$vision_issue = trim($input["vision_issue"] ?? "none");
$astigmatism = intval($input["astigmatism"] ?? 0);
$eye_surgery = intval($input["eye_surgery"] ?? 0);
$surgery_details = trim($input["surgery_details"] ?? "");
$current_conditions = trim($input["current_conditions"] ?? "");
$eye_doctor = trim($input["eye_doctor"] ?? "");
$last_eye_exam = trim($input["last_eye_exam"] ?? "");
$additional_notes = trim($input["additional_notes"] ?? "");

$stmt->bind_param(
  "issisissiisssss",
  $wear_glasses,
  $glasses_od,
  $glasses_os,
  $wear_contacts,
  $contacts_od,
  $contacts_os,
  $vision_issue,
  $astigmatism,
  $eye_surgery,
  $surgery_details,
  $current_conditions,
  $eye_doctor,
  $last_eye_exam,
  $additional_notes,
  $email
);

if ($stmt->execute()) {
  echo json_encode(["status" => "success", "message" => "Eye history updated"]);
} else {
  echo json_encode(["status" => "error", "message" => $stmt->error]);
}

$stmt->close();
$conn->close();
?>
