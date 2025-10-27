<?php
function requireAuth() {
    // ✅ Ensure Authorization header is captured even on shared hosting
    if (!isset($_SERVER['PHP_AUTH_USER'])) {
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
            if (stripos($authHeader, 'Basic ') === 0) {
                $decoded = base64_decode(substr($authHeader, 6));
                if ($decoded) {
                    list($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']) = explode(':', $decoded);
                }
            }
        }
    }

    // ✅ Define your valid credentials (can come from DB or .env)
    $valid_user = 'admin';
    $valid_pass = '12345';

    // ✅ Validate
    if (
        !isset($_SERVER['PHP_AUTH_USER']) ||
        $_SERVER['PHP_AUTH_USER'] !== $valid_user ||
        $_SERVER['PHP_AUTH_PW'] !== $valid_pass
    ) {
        http_response_code(401);
        echo json_encode(["message" => "Unauthorized"]);
        exit;
    }
}
?>
