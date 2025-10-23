<?php
require_once __DIR__ . '/../config/database.php';

$database = new Database();
$db = $database->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $manager_id = $_GET['manager_id'] ?? null;
        if ($manager_id) {
            $stmt = $db->prepare("SELECT * FROM tasks WHERE manager_id=:mid ORDER BY id ASC");
            $stmt->execute([":mid" => $manager_id]);
        } else {
            $stmt = $db->query("SELECT * FROM tasks ORDER BY id ASC");
        }
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    case 'POST':
        $input = json_decode(file_get_contents("php://input"), true);
        $query = "INSERT INTO tasks (name, hours_required, manager_id) VALUES (:name, :hours, :mid)";
        $stmt = $db->prepare($query);
        $stmt->execute([
            ":name" => $input['name'],
            ":hours" => $input['hours_required'],
            ":mid" => $input['manager_id']
        ]);

        if (!empty($input['employees'])) {
            $task_id = $db->lastInsertId();
            $linkQuery = $db->prepare("INSERT INTO task_assignments (task_id, employee_id) VALUES (:tid, :eid)");
            foreach ($input['employees'] as $eid) {
                $linkQuery->execute([":tid" => $task_id, ":eid" => $eid]);
            }
        }

        echo json_encode(["message" => "Task created"]);
        break;

    case 'PUT':
        $input = json_decode(file_get_contents("php://input"), true);
        if (!$input || !isset($input['id'])) {
            http_response_code(400);
            echo json_encode(["error" => "Task ID is required for update"]);
            exit;
        }

        $query = "UPDATE tasks 
                  SET name = :name, hours_required = :hours_required 
                  WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->execute([
            ":id" => $input['id'],
            ":name" => $input['name'],
            ":hours_required" => $input['hours_required']
        ]);

        // Optional: update assigned employees
        if (isset($input['employees'])) {
            $task_id = $input['id'];

            // Delete old links
            $db->prepare("DELETE FROM task_assignments WHERE task_id = :tid")
               ->execute([":tid" => $task_id]);

            // Add new ones
            $linkQuery = $db->prepare("INSERT INTO task_assignments (task_id, employee_id) VALUES (:tid, :eid)");
            foreach ($input['employees'] as $eid) {
                $linkQuery->execute([":tid" => $task_id, ":eid" => $eid]);
            }
        }

        echo json_encode(["message" => "Task updated"]);
        break;

    case 'DELETE':
        $id = $_GET['id'] ?? null;
        if ($id) {
            $stmt = $db->prepare("DELETE FROM tasks WHERE id=:id");
            $stmt->execute([":id" => $id]);
            echo json_encode(["message" => "Task deleted"]);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(["message" => "Method not allowed"]);
        break;
}
?>

