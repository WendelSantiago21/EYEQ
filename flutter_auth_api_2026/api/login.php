<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

include __DIR__ . "/config.php";

// Read JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Validate input
if (!$data || empty($data['email']) || empty($data['password'])) {
    echo json_encode([
        "status" => "error",
        "message" => "Email and password are required"
    ]);
    exit;
}

$email = trim($data['email']);
$password = $data['password'];

// Fetch user (username REMOVED, dob ADDED)
$stmt = $conn->prepare("
    SELECT id, full_name, dob, email, password 
    FROM users 
    WHERE email = ?
");
$stmt->bindParam(1, $email, PDO::PARAM_STR);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Check email existence
if (!$user) {
    echo json_encode([
        "status" => "error",
        "message" => "Email not found"
    ]);
    exit;
}

// Verify password
if (password_verify($password, $user['password'])) {
    echo json_encode([
        "status"    => "success",
        "message"   => "Login successful",
        "full_name" => $user['full_name'],
        "dob"       => $user['dob'],
        "email"     => $user['email']
    ]);
} else {
    echo json_encode([
        "status" => "error",
        "message" => "Incorrect password"
    ]);
}
?>