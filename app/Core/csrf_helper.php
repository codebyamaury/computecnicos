<?php
/**
 * Helper de protección CSRF (Cross-Site Request Forgery).
 * Genera y valida tokens criptográficos para evitar ataques de falsificación de petición.
 */

// Generar token si no existe
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        try {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        } catch (Exception $e) {
            $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
        }
    }
    return $_SESSION['csrf_token'];
}

// Generar campo oculto HTML para formularios
function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

// Inicializar de inmediato para que esté disponible en toda la sesión
if (session_status() === PHP_SESSION_ACTIVE) {
    csrf_token();
}

// Verificar que el token proporcionado coincida de forma segura
function verify_csrf_token($token = null) {
    if ($token === null) {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_GET['csrf_token'] ?? '';
    }
    
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    
    // Evita timing attacks (comparación en tiempo constante)
    return hash_equals($_SESSION['csrf_token'], $token);
}

// Interceptar peticiones POST masivas en el área de Admin para proteger automáticamente
function auto_protect_admin_csrf() {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $is_admin = strpos($uri, '/admin/') !== false;
    $is_post_delete = $_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'DELETE';
    $is_get_deletion = isset($_GET['eliminar']) || strpos($_SERVER['SCRIPT_NAME'] ?? '', '_eliminar.php') !== false;

    // Si estamos en una ruta '/admin/' y es POST o una acción de borrado GET
    if ($is_admin && ($is_post_delete || $is_get_deletion)) {
        
        
        // Excepciones donde no queramos CSRF (ej: hooks externos), por defecto todo el admin debe estar protegido
        if (!verify_csrf_token()) {
            
            $is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') 
                       || strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;

            if ($is_ajax) {
                header('Content-Type: application/json');
                http_response_code(403);
                echo json_encode(['ok' => false, 'msg' => 'Error de seguridad (CSRF inválido). Recarga la página e intenta de nuevo.']);
                exit;
            }
            
            // Pantalla de error para navegación estándar
            die('
                <div style="font-family:sans-serif;text-align:center;padding:50px;color:#fff;background:#111;height:100vh;">
                    <h2 style="color:#ef4444">Error de Seguridad (Vencimiento de Token CSRF)</h2>
                    <p>Por seguridad, tu sesión para esta acción ha expirado o es inválida.</p>
                    <a href="javascript:history.back()" style="display:inline-block;padding:10px 20px;background:#ef4444;color:#fff;text-decoration:none;border-radius:5px;margin-top:20px;">Volver Atrás</a>
                </div>
            ');
        }
    }
}
