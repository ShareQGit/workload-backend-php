<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/auth.php';
requireAuth();
 
$database = new Database();
$db = $database->getConnection();
 
$method = $_SERVER['REQUEST_METHOD'];
 
switch ($method) {
    case 'GET':
        // Get all managers
        $stmt = $db->query("SELECT * FROM managers ORDER BY name ASC");
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($data);
        break;
 
    case 'POST':
        // Add new manager
        $input = json_decode(file_get_contents("php://input"), true);
        $query = "INSERT INTO managers (name) VALUES (:name)";
        $stmt = $db->prepare($query);
        $stmt->execute([":name" => $input['name']]);
        echo json_encode(["message" => "Manager created"]);
        break;
 
        case 'PUT':
        // decode JSON body
        $input = json_decode(file_get_contents("php://input"), true);
 
        if (!$input || !isset($input['id'])) {
            http_response_code(400);
            echo json_encode(["error" => "Manager ID is required for update"]);
            exit;
        }
 
        $query = "UPDATE managers SET name = :name WHERE id = :id";
        $stmt  = $db->prepare($query);
        $stmt->execute([
            ":id"   => $input['id'],
            ":name" => $input['name']
        ]);
 
        echo json_encode(["message" => "Manager updated"]);
        break;
 
 
    case 'DELETE':
        // Delete manager
        $id = $_GET['id'] ?? null;
        if ($id) {
            $stmt = $db->prepare("DELETE FROM managers WHERE id = :id");
            $stmt->execute([":id" => $id]);
            echo json_encode(["message" => "Manager deleted"]);
        } else {
            echo json_encode(["error" => "Manager ID not provided"]);
        }
        break;
 
    default:
        http_response_code(405);
        echo json_encode(["message" => "Method not allowed"]);
        break;
}
?>