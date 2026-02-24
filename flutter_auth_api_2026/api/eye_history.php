<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

include __DIR__ . "/config.php";

/**
 * Read raw JSON input
 */
$input = file_get_contents("php://input");
$data = json_decode($input, true);
$email = $data['email'];
if (!$data) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid JSON payload"
    ]);
    exit;
}

/**
 * Basic validation (required fields)
 */
if (
    !isset($data['wear_glasses']) ||
    !isset($data['wear_contacts']) ||
    empty($data['vision_issue']) ||
    !isset($data['step'])
) {
    echo json_encode([
        "status" => "error",
        "message" => "Required fields missing"
    ]);
    exit;
}

/**
 * Sanitize / normalize inputs
 */
$wear_glasses   = (int) $data['wear_glasses'];
$glasses_od     = !empty($data['glasses_od']) ? trim($data['glasses_od']) : null;
$glasses_os     = !empty($data['glasses_os']) ? trim($data['glasses_os']) : null;

$wear_contacts  = (int) $data['wear_contacts'];
$contacts_od    = !empty($data['contacts_od']) ? trim($data['contacts_od']) : null;
$contacts_os    = !empty($data['contacts_os']) ? trim($data['contacts_os']) : null;

$vision_issue   = trim($data['vision_issue']);
$astigmatism = isset($data['astigmatism']) ? (int)$data['astigmatism'] : 0;
$eye_surgery = isset($data['eye_surgery']) ? (int)$data['eye_surgery'] : 0;
$surgery_details = !empty($data['surgery_details']) ? trim($data['surgery_details']) : null;
$current_conditions = !empty($data['current_conditions']) ? trim($data['current_conditions']) : null;
$eye_doctor = !empty($data['eye_doctor']) ? trim($data['eye_doctor']) : null;
$last_eye_exam = !empty($data['last_eye_exam']) ? trim($data['last_eye_exam']) : null;
$additional_notes = !empty($data['additional_notes']) ? trim($data['additional_notes']) : null;
$step           = (int) $data['step'];

/**
 * Insert into database
 */
$sql = "INSERT INTO eye_history (
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
            step
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);

$success = $stmt->execute([
	$email,
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
    $step
]);

if ($success) {
    echo json_encode([
        "status" => "success",
        "message" => "Eye history saved successfully",
        "eye_history_id" => $conn->lastInsertId()
    ]);
} else {
    echo json_encode([
        "status" => "error",
        "message" => "Failed to save eye history"
    ]);
}
?>