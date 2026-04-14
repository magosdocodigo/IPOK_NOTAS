<?php
// logout.php
session_start();

// Registrar logout no log de auditoria
if (isset($_SESSION['user_id'])) {
    require_once 'config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    $user_id = $_SESSION['user_id'];
    $ip = $_SERVER['REMOTE_ADDR'];
    
    $db->query("INSERT INTO logs_auditoria (usuario_id, acao, tabela, ip) 
                VALUES ($user_id, 'LOGOUT', 'usuarios', '$ip')");
}

// Destruir todas as variáveis de sessão
$_SESSION = array();

// Destruir o cookie de sessão
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finalmente, destruir a sessão
session_destroy();

// Redirecionar para a página de login
header('Location: login.php?msg=loggedout');
exit();
?>