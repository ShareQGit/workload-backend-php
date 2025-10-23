<?php
require_once __DIR__ . '/../config/database.php';

$database = new Database();
$db = $database->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $stmt = $db->query("
            SELECT id, name, type, hours_per_day, days_per_month,
                   (hours_per_day * days_per_month) AS total_hours
            FROM employees ORDER BY name ASC
        ");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    case 'POST':
        $input = json_decode(file_get_contents("php://input"), true);

        if (!$input) {
            echo json_encode(["error" => "No input received"]);
            exit;
        }

        $query = "INSERT INTO employees (name, type, hours_per_day, days_per_month, manager_id)
                  VALUES (:name, :type, :hours_per_day, :days_per_month, :manager_id)";
        $stmt = $db->prepare($query);

        try {
            $stmt->execute([
                ":name" => $input['name'],
                ":type" => $input['type'],
                ":hours_per_day" => $input['hours_per_day'],
                ":days_per_month" => $input['days_per_month'],
                ":manager_id" => $input['manager_id']
            ]);
            echo json_encode(["message" => "Employee created"]);
        } catch (PDOException $e) {
            echo json_encode(["error" => $e->getMessage()]);
        }
        break;

    case 'PUT':
        $input = json_decode(file_get_contents("php://input"), true);

        if (!$input || !isset($input['id'])) {
            http_response_code(400);
            echo json_encode(["error" => "Employee ID is required for update"]);
            exit;
        }

        $query = "UPDATE employees 
                  SET name = :name, 
                      type = :type, 
                      hours_per_day = :hours_per_day, 
                      days_per_month = :days_per_month
                  WHERE id = :id";

        $stmt = $db->prepare($query);

        try {
            $stmt->execute([
                ":id" => $input['id'],
                ":name" => $input['name'],
                ":type" => $input['type'],
                ":hours_per_day" => $input['hours_per_day'],
                ":days_per_month" => $input['days_per_month']
            ]);
            echo json_encode(["message" => "Employee updated"]);
        } catch (PDOException $e) {
            echo json_encode(["error" => $e->getMessage()]);
        }
        break;

    case 'DELETE':
        $id = $_GET['id'] ?? null;
        if ($id) {
            $stmt = $db->prepare("DELETE FROM employees WHERE id = :id");
            $stmt->execute([":id" => $id]);
            echo json_encode(["message" => "Employee deleted"]);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(["message" => "Method not allowed"]);
        break;
}
?>
