<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

include __DIR__ . "/config.php";

// Get JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// ✅ Validate required fields
if (
    empty($data['full_name']) ||
    empty($data['dob']) ||
    empty($data['email']) ||
    empty($data['password'])
) {
    echo json_encode([
        "status" => "error",
        "message" => "All fields are required for signup"
    ]);
    exit;
}

$full_name = trim($data['full_name']);
$dob = trim($data['dob']); // ✅ Date of Birth
$email = trim($data['email']);
$password = $data['password'];

// ✅ Check if email already exists
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$email]);

if ($stmt->rowCount() > 0) {
    echo json_encode([
        "status" => "error",
        "message" => "Email already registered"
    ]);
    exit;
}

// ✅ Hash password
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// ✅ Insert user with DOB
$stmt = $conn->prepare(
    "INSERT INTO users (full_name, dob, email, password)
     VALUES (?, ?, ?, ?)"
);

if ($stmt->execute([$full_name, $dob, $email, $hashed_password])) {
    echo json_encode([
        "status" => "success",
        "message" => "Signup successful"
    ]);
} else {
    echo json_encode([
        "status" => "error",
        "message" => "Signup failed"
    ]);
}
?>