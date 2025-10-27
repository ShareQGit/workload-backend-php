<?php
require_once __DIR__ . '/../config/database.php';

$database = new Database();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: POST, OPTIONS");

if ($method === 'OPTIONS') {
  http_response_code(200);
  exit;
}

switch ($method) {
  case 'POST':
    $input = json_decode(file_get_contents("php://input"), true);

    $username = $input['username'] ?? '';
    $password = $input['password'] ?? '';

    // Replace with your static credentials or DB check
    $validUser = "admin";
    $validPass = "12345";

    if ($username === $validUser && $password === $validPass) {
      // Generate Basic Auth token
      $token = base64_encode("$username:$password");

      echo json_encode([
        "message" => "Login successful",
        "token" => $token,
        "username" => $username
      ]);
    } else {
      http_response_code(401);
      echo json_encode(["message" => "Invalid username or password"]);
    }
    break;

  default:
    http_response_code(405);
    echo json_encode(["message" => "Method not allowed"]);
    break;
}
?>
