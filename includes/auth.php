<?php
// includes/auth.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['user_nivel']) && $_SESSION['user_nivel'] === 'admin';
}

function isProfessor() {
    return isset($_SESSION['user_nivel']) && $_SESSION['user_nivel'] === 'professor';
}

function isAluno() {
    return isset($_SESSION['user_nivel']) && $_SESSION['user_nivel'] === 'aluno';
}

function redirectIfNotLoggedIn() {
    if (!isLoggedIn()) {
        header('Location: ../login.php');
        exit();
    }
}

function redirectBasedOnRole() {
    if (isAdmin()) {
        header('Location: admin/dashboard.php');
    } elseif (isProfessor()) {
        header('Location: professor/dashboard.php');
    } elseif (isAluno()) {
        header('Location: aluno/dashboard.php');
    } else {
        header('Location: login.php');
    }
    exit();
}
?>
