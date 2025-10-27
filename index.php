<?php
header("Access-Control-Allow-Origin: http://127.0.0.1:5500");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache"); // For older browsers/proxies
header("Expires: 0");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

 
$request = $_SERVER['REQUEST_URI'];
 
switch (true) {
    case strpos($request, '/auth') !== false:
        require __DIR__ . '/routes/auth.php';
        break;
 
    case strpos($request, '/managers') !== false:
        require __DIR__ . '/routes/managers.php';
        break;
 
    case strpos($request, '/employees') !== false:
        require __DIR__ . '/routes/employees.php';
        break;
 
    case strpos($request, '/projects') !== false:
        require __DIR__ . '/routes/projects.php';
        break;
 
    case strpos($request, '/tasks') !== false:
        require __DIR__ . '/routes/tasks.php';
        break;
 
    default:
        http_response_code(404);
        echo json_encode(["message" => "Endpoint not found"]);
        break;
}
 
?>

