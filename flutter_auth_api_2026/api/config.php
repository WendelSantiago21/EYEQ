<?php
// config.php
$host = "localhost"; // your database host
$db_name = "flutter_auth"; // your database name
$db_user = "root"; // your database username
$db_pass = ""; // your database password

try {
    $conn = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // Return JSON error if connection fails
    echo json_encode([
        "status" => "error",
        "message" => "Database connection failed: " . $e->getMessage()
    ]);
    exit;
}
date_default_timezone_set('Asia/Manila');
?>