<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/auth.php';
requireAuth();
 
$database = new Database();
$db = $database->getConnection();
$method = $_SERVER['REQUEST_METHOD'];
 
// Parse URI for nested routes like /projects/{manager_id}/{project_id}/tasks
$uri = explode('/', trim($_SERVER['REQUEST_URI'], '/'));
 
switch ($method) {
 
    // ========================= [ GET PROJECTS or TASKS ] =========================
    case 'GET':
        $manager_id = $_GET['manager_id'] ?? null;
 
        // === If asking for all projects (with their assigned tasks) ===
        if ($manager_id) {
            // 1️⃣ Fetch all projects for this manager
            $stmt = $db->prepare("SELECT * FROM projects WHERE manager_id = :mid ORDER BY start_month ASC");
            $stmt->execute([":mid" => $manager_id]);
            $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
 
            // 2️⃣ For each project, fetch its assigned tasks
            foreach ($projects as &$proj) {
                $project_id = $proj['id'];
 
                $taskQuery = $db->prepare("
                    SELECT
                        ta.id, ta.project_id, ta.employee_id, ta.task_id, ta.coefficient,
                        ta.total_hours, ta.workload, ta.month,
                        e.name AS employee_name,
                        t.name AS task_name,
                        t.hours_required
                    FROM task_assignments ta
                    JOIN employees e ON ta.employee_id = e.id
                    JOIN tasks t ON ta.task_id = t.id
                    WHERE ta.project_id = :pid
                    ORDER BY ta.month ASC
                ");
                $taskQuery->execute([":pid" => $project_id]);
                $proj['tasks'] = $taskQuery->fetchAll(PDO::FETCH_ASSOC);
            }
 
            echo json_encode($projects);
            break;
        }
 
        // === If asking for a specific project's tasks ( /projects/.../tasks ) ===
        if (count($uri) >= 4 && $uri[count($uri) - 1] === 'tasks') {
            $project_id = $uri[count($uri) - 2];
 
            $stmt = $db->prepare("
                SELECT
                    ta.id, ta.project_id, ta.employee_id, ta.task_id, ta.coefficient,
                    ta.total_hours, ta.workload, ta.month,
                    e.name AS employee_name,
                    t.name AS task_name,
                    t.hours_required
                FROM task_assignments ta
                JOIN employees e ON ta.employee_id = e.id
                JOIN tasks t ON ta.task_id = t.id
                WHERE ta.project_id = :pid
                ORDER BY ta.month ASC
            ");
            $stmt->execute([":pid" => $project_id]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;
        }
 
        // === Otherwise, return all projects in DB (no manager filter) ===
        $stmt = $db->query("SELECT * FROM projects ORDER BY start_month ASC");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;
 
 
 
    // ========================= [ CREATE PROJECT or ASSIGN TASK ] =========================
    case 'POST':
        // === Add Task Assignment to Project ===
        if (count($uri) >= 4 && $uri[count($uri) - 1] === 'tasks') {
            $project_id = $uri[count($uri) - 2];
            $input = json_decode(file_get_contents("php://input"), true);
 
            if (!$project_id || !$input) {
                echo json_encode(["error" => "Invalid data"]);
                exit;
            }
 
            // 🔹 Fetch task hours_required from tasks table
            $stmt = $db->prepare("SELECT hours_required FROM tasks WHERE id = :tid");
            $stmt->execute([":tid" => $input['task']]);
            $taskData = $stmt->fetch(PDO::FETCH_ASSOC);
 
            if (!$taskData) {
                echo json_encode(["error" => "Task not found"]);
                exit;
            }
 
            $hoursRequired = (float)$taskData['hours_required'];
            $coef = (float)$input['coefficient'];
            $totalHours = $hoursRequired * $coef;
 
            // 🔹 Compute workload based on employee capacity
            $stmt = $db->prepare("SELECT (hours_per_day * days_per_month) AS available FROM employees WHERE id = :eid");
            $stmt->execute([":eid" => $input['employee']]);
            $empData = $stmt->fetch(PDO::FETCH_ASSOC);
            $available = $empData ? (float)$empData['available'] : 0;
            $workload = $available > 0 ? round(($totalHours / $available) * 100, 2) : 0;
 
            // 🔹 Insert into task_assignments
            $query = "INSERT INTO task_assignments
                      (project_id, task_id, employee_id, coefficient, total_hours, workload, month)
                      VALUES (:project_id, :task, :employee, :coef, :total, :workload, :month)";
            $stmt = $db->prepare($query);
            $stmt->execute([
                ":project_id" => $project_id,
                ":task" => $input['task'],
                ":employee" => $input['employee'],
                ":coef" => $coef,
                ":total" => $totalHours,
                ":workload" => $workload,
                ":month" => $input['month']
            ]);
 
            echo json_encode([
                "message" => "Task assigned successfully",
                "total_hours" => $totalHours,
                "workload" => $workload
            ]);
            break;
        }
 
        // === Normal Project Creation ===
        $input = json_decode(file_get_contents("php://input"), true);
 
        if (!$input || empty($input['start_month']) || empty($input['end_month'])) {
            echo json_encode(["error" => "Invalid input"]);
            exit;
        }
 
        // Calculate duration (in months)
        $start = DateTime::createFromFormat('Y-m', $input['start_month']);
        $end = DateTime::createFromFormat('Y-m', $input['end_month']);
        $interval = $start->diff($end);
        $duration = ($interval->y * 12) + $interval->m + 1;
 
        $query = "INSERT INTO projects (name, start_month, end_month, duration, manager_id)
                  VALUES (:name, :start, :end, :duration, :mid)";
        $stmt = $db->prepare($query);
        $stmt->execute([
            ":name" => $input['name'],
            ":start" => $input['start_month'],
            ":end" => $input['end_month'],
            ":duration" => $duration,
            ":mid" => $input['manager_id']
        ]);
 
        echo json_encode(["message" => "Project created"]);
        break;
 
 
 
    // ========================= [ UPDATE PROJECT (PUT) ] =========================
    case 'PUT':
        // ✅ ADD THIS ENTIRE "if" BLOCK
        // === Check if updating a TASK ASSIGNMENT ===
        // URI: /projects/{manager_id}/{project_id}/tasks/{assignment_id}
        if (count($uri) >= 5 && $uri[count($uri) - 2] === 'tasks') {
            $assignment_id = $uri[count($uri) - 1];
            $input = json_decode(file_get_contents("php://input"), true);

            if (!$assignment_id || !$input) {
                http_response_code(400);
                echo json_encode(["error" => "Invalid data for task update"]);
                exit;
            }

            // 🔹 Recalculate total_hours and workload (same as POST)
            $stmt = $db->prepare("SELECT hours_required FROM tasks WHERE id = :tid");
            $stmt->execute([":tid" => $input['task']]);
            $taskData = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$taskData) {
                http_response_code(404);
                echo json_encode(["error" => "Task definition not found"]);
                exit;
            }

            $hoursRequired = (float)$taskData['hours_required'];
            $coef = (float)$input['coefficient'];
            $totalHours = $hoursRequired * $coef;

            // 🔹 Compute workload
            $stmt = $db->prepare("SELECT (hours_per_day * days_per_month) AS available FROM employees WHERE id = :eid");
            $stmt->execute([":eid" => $input['employee']]);
            $empData = $stmt->fetch(PDO::FETCH_ASSOC);
            $available = $empData ? (float)$empData['available'] : 0;
            $workload = $available > 0 ? round(($totalHours / $available) * 100, 2) : 0;

            // 🔹 UPDATE the task_assignments table
            $query = "UPDATE task_assignments
                        SET task_id = :task,
                            employee_id = :employee,
                            coefficient = :coef,
                            total_hours = :total,
                            workload = :workload,
                            month = :month
                        WHERE id = :assignment_id";
            $stmt = $db->prepare($query);
            $stmt->execute([
                ":task" => $input['task'],
                ":employee" => $input['employee'],
                ":coef" => $coef,
                ":total" => $totalHours,
                ":workload" => $workload,
                ":month" => $input['month'],
                ":assignment_id" => $assignment_id
            ]);

            echo json_encode(["message" => "Task assignment updated successfully"]);
            break; // <-- IMPORTANT: Stop execution here
        }
        // ======================================================

        // === Existing code for updating a PROJECT ===
        // Expect /projects/{manager_id}/{project_id}
        $project_id = $uri[count($uri) - 1];
        $input = json_decode(file_get_contents("php://input"), true);

        // ... (rest of your existing PUT code for updating a project) ...
        
        break;
 
 
 
    // ========================= [ DELETE PROJECT or TASK ASSIGNMENT ] =========================
    case 'DELETE':
    // DELETE /projects/{manager_id}/{project_id}/tasks/{assignment_id}
    if (count($uri) >= 5 && $uri[count($uri) - 2] === 'tasks') {
        $assignment_id = $uri[count($uri) - 1];
        $stmt = $db->prepare("DELETE FROM task_assignments WHERE id = :id");
        $stmt->execute([":id" => $assignment_id]);
        echo json_encode(["message" => "Task assignment deleted"]);
        break;
    }
 
    // Normal project delete
    $id = $_GET['id'] ?? null;
    if ($id) {
        $stmt = $db->prepare("DELETE FROM projects WHERE id=:id");
        $stmt->execute([":id" => $id]);
        echo json_encode(["message" => "Project deleted"]);
    }
    break;
 
 
 
    // ========================= [ METHOD NOT ALLOWED ] =========================
    default:
        http_response_code(405);
        echo json_encode(["message" => "Method not allowed"]);
        break;
}
?>