<?php
// logout.php
session_start();

// Registrar logout no log de auditoria
if (isset($_SESSION['user_id'])) {
    require_once 'config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    $user_id = $_SESSION['user_id'];
        
        $db->query("INSERT INTO logs_auditoria (usuario_id, acao, tabela) 
                    VALUES ($user_id, 'LOGOUT', 'usuarios')");
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