<?php
// admin/usuarios.php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

$message = '';
$error = '';
$usuario_criado = false;

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $ip = $_SERVER['REMOTE_ADDR'];

        switch ($_POST['action']) {
            case 'create':
            case 'edit':
                $nome = mysqli_real_escape_string($db, $_POST['nome']);
                $email = mysqli_real_escape_string($db, $_POST['email']);
                $username = mysqli_real_escape_string($db, $_POST['username']);
                $nivel = $_POST['nivel'];
                $ativo = isset($_POST['ativo']) ? 1 : 0;

                if ($_POST['action'] === 'create') {
                    // Verificar se o username ou email já existem para evitar erro fatal de duplicidade
                    $check_duplicate = $db->query("SELECT id FROM usuarios WHERE username = '$username' OR email = '$email' LIMIT 1");
                    if ($check_duplicate && $check_duplicate->num_rows > 0) {
                        $error = "Erro: O nome de utilizador ou e-mail informado já está a ser utilizado.";
                        break;
                    }

                    // Verificar se a matrícula já existe (apenas para alunos)
                    if ($nivel === 'aluno') {
                        $matricula = mysqli_real_escape_string($db, $_POST['matricula']);
                        $check_mat = $db->query("SELECT id FROM alunos WHERE numero_matricula = '$matricula' LIMIT 1");
                        if ($check_mat && $check_mat->num_rows > 0) {
                            $error = "Erro: O número de matrícula '$matricula' já está registado para outro aluno.";
                            break;
                        }
                    }

                    $senha = password_hash($_POST['senha'], PASSWORD_DEFAULT);

                    // Inserir usuário
                    $query = "INSERT INTO usuarios (nome, email, username, senha, nivel, ativo) 
                              VALUES ('$nome', '$email', '$username', '$senha', '$nivel', $ativo)";

                    if ($db->query($query)) {
                        $usuario_id = $db->insert_id;

                        // Se for aluno, criar registro na tabela alunos e enturmação
                        if ($nivel === 'aluno') {
                            $matricula = mysqli_real_escape_string($db, $_POST['matricula']);
                            $data_matricula = $_POST['data_matricula'] ?: date('Y-m-d');
                            $turma_id = !empty($_POST['turma_id']) ? (int)$_POST['turma_id'] : null;

                            $db->query("INSERT INTO alunos (usuario_id, numero_matricula, data_matricula) 
                                       VALUES ($usuario_id, '$matricula', '$data_matricula')");

                            // Obter o ID do aluno recém-criado
                            $aluno_id = $db->insert_id;

                            // Se uma turma foi selecionada, enturmar o aluno
                            if ($turma_id) {
                                $db->query("INSERT INTO enturmacoes (aluno_id, turma_id) 
                                           VALUES ($aluno_id, $turma_id)");
                            }
                        }

                        // Se for professor, criar registro na tabela professores
                        if ($nivel === 'professor') {
                            $codigo = mysqli_real_escape_string($db, $_POST['codigo_funcionario']);
                            $db->query("INSERT INTO professores (usuario_id, codigo_funcionario) 
                                       VALUES ($usuario_id, '$codigo')");

                            $professor_id = $db->insert_id;

                            // Atribuição imediata de disciplina
                            $turma_id = !empty($_POST['prof_turma_id']) ? (int)$_POST['prof_turma_id'] : null;
                            $disciplina_id = !empty($_POST['prof_disciplina_id']) ? (int)$_POST['prof_disciplina_id'] : null;
                            $ano_letivo = !empty($_POST['prof_ano_letivo']) ? (int)$_POST['prof_ano_letivo'] : null;

                            if ($turma_id && $disciplina_id && $ano_letivo) {
                                // Verificar ou criar vínculo em turma_disciplina
                                $check_td = $db->query("SELECT id FROM turma_disciplina WHERE turma_id = $turma_id AND disciplina_id = $disciplina_id");
                                if ($check_td && $check_td->num_rows > 0) {
                                    $turma_disciplina_id = $check_td->fetch_assoc()['id'];
                                } else {
                                    $db->query("INSERT INTO turma_disciplina (turma_id, disciplina_id) VALUES ($turma_id, $disciplina_id)");
                                    $turma_disciplina_id = $db->insert_id;
                                }

                                // Criar atribuição
                                $db->query("INSERT INTO atribuicoes (professor_id, turma_disciplina_id, ano_letivo) 
                                           VALUES ($professor_id, $turma_disciplina_id, $ano_letivo)");

                                $db->query("INSERT INTO logs_auditoria (usuario_id, acao, tabela, registro_id, ip) 
                                           VALUES ({$_SESSION['user_id']}, 'CRIAR_ATRIBUICAO', 'atribuicoes', {$db->insert_id}, '$ip')");
                            }
                        }

                        // Log de auditoria
                        $db->query("INSERT INTO logs_auditoria (usuario_id, acao, tabela, registro_id, ip) 
                                   VALUES ({$_SESSION['user_id']}, 'CRIAR_USUARIO', 'usuarios', $usuario_id, '$ip')");

                        $message = "Usuário criado com sucesso!";
                        $usuario_criado = true;
                    } else {
                        $error = "Erro ao criar usuário: " . $db->error;
                    }
                } else {
                    // Editar usuário
                    $id = (int)$_POST['id'];

                    // Verificar se o novo username ou email já pertencem a outro utilizador
                    $check_duplicate = $db->query("SELECT id FROM usuarios WHERE (username = '$username' OR email = '$email') AND id != $id LIMIT 1");
                    if ($check_duplicate && $check_duplicate->num_rows > 0) {
                        $error = "Erro: Este username ou e-mail já está em uso por outro utilizador.";
                        break;
                    }

                    // Verificar se a nova matrícula já pertence a outro aluno (na edição)
                    if ($nivel === 'aluno') {
                        $matricula = mysqli_real_escape_string($db, $_POST['matricula']);
                        $check_mat = $db->query("SELECT id FROM alunos WHERE numero_matricula = '$matricula' AND usuario_id != $id LIMIT 1");
                        if ($check_mat && $check_mat->num_rows > 0) {
                            $error = "Erro: Este número de matrícula já está em uso por outro aluno.";
                            break;
                        }
                    }

                    $query = "UPDATE usuarios SET 
                              nome = '$nome',
                              email = '$email',
                              username = '$username',
                              nivel = '$nivel',
                              ativo = $ativo
                              WHERE id = $id";

                    if ($db->query($query)) {
                        // Se for aluno, atualizar matrícula e possivelmente turma
                        if ($nivel === 'aluno') {
                            $matricula = mysqli_real_escape_string($db, $_POST['matricula']);
                            $db->query("UPDATE alunos SET numero_matricula = '$matricula' WHERE usuario_id = $id");

                            // Se foi fornecida uma nova turma, criar nova enturmação (histórico)
                            $turma_id = !empty($_POST['turma_id']) ? (int)$_POST['turma_id'] : null;
                            if ($turma_id) {
                                // Obter aluno_id a partir do usuario_id
                                $aluno_query = "SELECT id FROM alunos WHERE usuario_id = $id";
                                $aluno_result = $db->query($aluno_query);
                                if ($aluno_result && $aluno_result->num_rows > 0) {
                                    $aluno = $aluno_result->fetch_assoc();
                                    $aluno_id = $aluno['id'];

                                    // Verificar se a turma já é a atual (última enturmação)
                                    $check_query = "SELECT turma_id FROM enturmacoes 
                                                   WHERE aluno_id = $aluno_id 
                                                   ORDER BY data_enturmacao DESC LIMIT 1";
                                    $check_result = $db->query($check_query);
                                    $ultima_turma = $check_result->fetch_assoc();

                                    if (!$ultima_turma || $ultima_turma['turma_id'] != $turma_id) {
                                        $db->query("INSERT INTO enturmacoes (aluno_id, turma_id) 
                                                   VALUES ($aluno_id, $turma_id)");
                                    }
                                }
                            }
                        }

                        // Se for professor, atualizar código
                        if ($nivel === 'professor') {
                            $codigo = mysqli_real_escape_string($db, $_POST['codigo_funcionario']);
                            $db->query("UPDATE professores SET codigo_funcionario = '$codigo' WHERE usuario_id = $id");

                            // Atribuição de nova disciplina na edição (opcional)
                            $turma_id = !empty($_POST['prof_turma_id']) ? (int)$_POST['prof_turma_id'] : null;
                            $disciplina_id = !empty($_POST['prof_disciplina_id']) ? (int)$_POST['prof_disciplina_id'] : null;
                            $ano_letivo = !empty($_POST['prof_ano_letivo']) ? (int)$_POST['prof_ano_letivo'] : null;

                            if ($turma_id && $disciplina_id && $ano_letivo) {
                                $prof_res = $db->query("SELECT id FROM professores WHERE usuario_id = $id");
                                if ($prof_res && $prof_res->num_rows > 0) {
                                    $professor_id = $prof_res->fetch_assoc()['id'];

                                    // Mesma lógica de vínculo
                                    $check_td = $db->query("SELECT id FROM turma_disciplina WHERE turma_id = $turma_id AND disciplina_id = $disciplina_id");
                                    if ($check_td && $check_td->num_rows > 0) {
                                        $turma_disciplina_id = $check_td->fetch_assoc()['id'];
                                    } else {
                                        $db->query("INSERT INTO turma_disciplina (turma_id, disciplina_id) VALUES ($turma_id, $disciplina_id)");
                                        $turma_disciplina_id = $db->insert_id;
                                    }

                                    // Verificar se já existe essa atribuição específica para não duplicar
                                    $check_at = $db->query("SELECT id FROM atribuicoes WHERE professor_id = $professor_id AND turma_disciplina_id = $turma_disciplina_id AND ano_letivo = $ano_letivo");
                                    if ($check_at->num_rows == 0) {
                                        $db->query("INSERT INTO atribuicoes (professor_id, turma_disciplina_id, ano_letivo) VALUES ($professor_id, $turma_disciplina_id, $ano_letivo)");
                                    }
                                }
                            }
                        }

                        // Log de auditoria
                        $db->query("INSERT INTO logs_auditoria (usuario_id, acao, tabela, registro_id, ip) 
                                   VALUES ({$_SESSION['user_id']}, 'EDITAR_USUARIO', 'usuarios', $id, '$ip')");

                        $message = "Usuário atualizado com sucesso!";
                    } else {
                        $error = "Erro ao atualizar usuário: " . $db->error;
                    }
                }
                break;

            case 'delete':
                $id = (int)$_POST['id'];

                // Verificar se o usuário é professor
                $check = $db->query("SELECT nivel FROM usuarios WHERE id = $id");
                $user = $check->fetch_assoc();

                if ($user && $user['nivel'] == 'professor') {
                    // Soft delete: apenas desativar (não deletar fisicamente)
                    $query = "UPDATE usuarios SET ativo = 0 WHERE id = $id";
                    $acao_log = 'DESATIVAR_USUARIO';
                    $success_msg = "Professor desativado com sucesso! (as notas e disciplinas permanecem)";
                } else {
                    // Para outros níveis, manter exclusão física
                    $query = "DELETE FROM usuarios WHERE id = $id";
                    $acao_log = 'DELETAR_USUARIO';
                    $success_msg = "Usuário deletado com sucesso!";
                }

                // Log antes de executar a ação
                $db->query("INSERT INTO logs_auditoria (usuario_id, acao, tabela, registro_id, ip) 
                           VALUES ({$_SESSION['user_id']}, '$acao_log', 'usuarios', $id, '$ip')");

                if ($db->query($query)) {
                    $message = $success_msg;
                } else {
                    $error = "Erro ao processar: " . $db->error;
                }
                break;

            case 'toggle_status':
                $id = (int)$_POST['id'];
                $status = (int)$_POST['status'];

                $query = "UPDATE usuarios SET ativo = $status WHERE id = $id";
                if ($db->query($query)) {
                    $acao = $status ? 'ATIVAR_USUARIO' : 'DESATIVAR_USUARIO';
                    $db->query("INSERT INTO logs_auditoria (usuario_id, acao, tabela, registro_id, ip) 
                               VALUES ({$_SESSION['user_id']}, '$acao', 'usuarios', $id, '$ip')");

                    $message = "Status do usuário atualizado!";
                }
                break;

            case 'reset_password':
                $id = (int)$_POST['id'];
                $nova_senha = password_hash('ipok2026', PASSWORD_DEFAULT);

                $query = "UPDATE usuarios SET senha = '$nova_senha' WHERE id = $id";
                if ($db->query($query)) {
                    $db->query("INSERT INTO logs_auditoria (usuario_id, acao, tabela, registro_id, ip) 
                               VALUES ({$_SESSION['user_id']}, 'RESETAR_SENHA', 'usuarios', $id, '$ip')");

                    $message = "Senha resetada para: ipok2026";
                }
                break;
        }
    }
}

// Buscar usuários com dados adicionais (incluindo turma atual)
$query = "SELECT u.*, 
          a.numero_matricula,
          a.data_matricula,
          p.codigo_funcionario,
          (SELECT t.nome FROM enturmacoes e 
           LEFT JOIN turmas t ON e.turma_id = t.id 
           WHERE e.aluno_id = a.id 
           ORDER BY e.data_enturmacao DESC LIMIT 1) as turma_nome
          FROM usuarios u
          LEFT JOIN alunos a ON u.id = a.usuario_id
          LEFT JOIN professores p ON u.id = p.usuario_id
          ORDER BY u.criado_em DESC";
$usuarios = $db->query($query);

// Buscar dados para edição
$edit_user = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $query = "SELECT u.*, 
              a.numero_matricula,
              a.data_matricula,
              p.codigo_funcionario,
              (SELECT turma_id FROM enturmacoes 
               WHERE aluno_id = a.id 
               ORDER BY data_enturmacao DESC LIMIT 1) as turma_id
              FROM usuarios u
              LEFT JOIN alunos a ON u.id = a.usuario_id
              LEFT JOIN professores p ON u.id = p.usuario_id
              WHERE u.id = $id";
    $result = $db->query($query);
    $edit_user = $result->fetch_assoc();
}

// Buscar turmas para filtro e select
$turmas_list = [];
$turmas_result = $db->query("SELECT DISTINCT id, nome, curso, ano_letivo FROM turmas ORDER BY ano_letivo DESC, nome");
while ($turma = $turmas_result->fetch_assoc()) {
    $turmas_list[] = $turma;
}

// Buscar disciplinas para o modal
$disciplinas_list = [];
$disciplinas_result = $db->query("SELECT id, nome, codigo FROM disciplinas ORDER BY nome");
while ($disc = $disciplinas_result->fetch_assoc()) {
    $disciplinas_list[] = $disc;
}

$anos_letivos = range(date('Y') - 1, date('Y') + 1);

$page_title = "Gestão de Utilizadores";
?>
<?php
if ($usuario_criado) {
    $_SESSION['toast'] = ['type' => 'success', 'title' => 'Usuário criado!', 'message' => 'Email: admin@ipok.com'];
}
?>
<!DOCTYPE html>
<html lang="pt">

<head>
    <?php require_once '../includes/toast_notification.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IPOK - Gestão de Utilizadores</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">

    <style>
        /* (mantido igual) */
        :root {
            --primary-blue: #1e3c72;
            --secondary-blue: #2a5298;
            --sidebar-width: 280px;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f4f7fc;
            overflow-x: hidden;
        }

        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            color: white;
            transition: all 0.3s ease;
            z-index: 1000;
            overflow-y: auto;
        }

        .sidebar.hidden {
            transform: translateX(-100%);
        }

        .sidebar-header {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-header .logo {
            width: 100px;
            height: 100px;
            background: white;
            border-radius: 50%;
            padding: 8px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
            margin: 0 auto 15px;
            transition: transform 0.3s ease;
        }

        .sidebar-header .logo:hover {
            transform: scale(1.05);
        }

        .sidebar-header .logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .sidebar-menu {
            padding: 20px 0;
        }

        .sidebar-menu .menu-title {
            padding: 10px 20px;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0.6;
        }

        .sidebar-menu .menu-item {
            padding: 12px 20px;
            display: flex;
            align-items: center;
            color: white;
            text-decoration: none;
            transition: all 0.3s;
            margin: 5px 10px;
            border-radius: 10px;
        }

        .sidebar-menu .menu-item:hover,
        .sidebar-menu .menu-item.active {
            background: rgba(255, 255, 255, 0.2);
            transform: translateX(5px);
        }

        .sidebar-menu .menu-item i {
            width: 30px;
            font-size: 1.2rem;
        }

        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 20px;
            transition: all 0.3s ease;
            min-height: 100vh;
        }

        .main-content.sidebar-hidden {
            margin-left: 0;
        }

        /* Top Navigation */
        .top-nav {
            background: white;
            padding: 15px 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .top-nav .page-title {
            color: var(--primary-blue);
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0;
        }

        /* Filter Section */
        .filter-section {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
        }

        /* Badges de filtro */
        .filter-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
        }

        .filter-pill {
            padding: 8px 20px;
            border-radius: 30px;
            background: #f0f2f5;
            color: #495057;
            border: none;
            font-weight: 500;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .filter-pill:hover {
            background: var(--primary-blue);
            color: white;
            transform: translateY(-2px);
        }

        .filter-pill.active {
            background: var(--primary-blue);
            color: white;
            box-shadow: 0 4px 12px rgba(30, 60, 114, 0.3);
        }

        .filter-pill i {
            margin-right: 8px;
        }

        /* Table Container */
        .table-container {
            background: white;
            border-radius: 15px;
            padding: 0;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        .table-header {
            padding: 20px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .table-header h5 {
            color: var(--primary-blue);
            font-weight: 600;
            margin: 0;
        }

        /* Modern Table */
        .modern-table {
            width: 100%;
            border-collapse: collapse;
        }

        .modern-table thead th {
            background: #f8fafc;
            color: var(--primary-blue);
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 15px;
            border-bottom: 2px solid #e9ecef;
        }

        .modern-table tbody tr {
            transition: all 0.3s ease;
        }

        .modern-table tbody tr:hover {
            background: #f8fafc;
            transform: scale(1.01);
        }

        .modern-table tbody td {
            padding: 15px;
            vertical-align: middle;
            border-bottom: 1px solid #e9ecef;
        }

        /* Badges */
        .badge-status {
            padding: 6px 12px;
            border-radius: 30px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .badge-ativo {
            background: #d4edda;
            color: #155724;
        }

        .badge-inativo {
            background: #f8d7da;
            color: #721c24;
        }

        .badge-admin {
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            color: white;
        }

        .badge-professor {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
        }

        .badge-aluno {
            background: linear-gradient(135deg, #17a2b8, #0d6efd);
            color: white;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            justify-content: space-between;
            min-width: 250px;
        }

        .action-buttons .btn {
            flex-basis: calc(50% - 3px);
        }

        .action-buttons .btn:nth-child(n+3) {
            flex-basis: calc(33.333% - 4px);
        }

        .btn-sm {
            padding: 6px 8px;
            font-size: 0.75rem;
            border-radius: 8px;
            transition: all 0.3s ease;
            white-space: nowrap;
        }

        .btn-sm:hover {
            transform: translateY(-2px);
        }

        /* Avatar */
        .user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1rem;
        }

        .user-info-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-info-details {
            display: flex;
            flex-direction: column;
        }

        .user-name {
            font-weight: 600;
            color: #1e3c72;
        }

        .user-email {
            font-size: 0.75rem;
            color: #6c757d;
        }

        /* Modal de Detalhes */
        .detail-card {
            padding: 15px;
            background: #f8fafc;
            border-radius: 12px;
            margin-bottom: 15px;
        }

        .detail-label {
            font-size: 0.75rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }

        .detail-value {
            font-weight: 600;
            color: #1e3c72;
            font-size: 1rem;
        }

        .avatar-large {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            font-weight: bold;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .main-content {
                margin-left: 0;
            }
        }

        /* Search Input */
        .search-input-group {
            position: relative;
        }

        .search-input-group i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }

        .search-input-group input {
            padding-left: 40px;
            height: 45px;
            border-radius: 12px;
            border: 1px solid #e9ecef;
        }
        /* ==================== DIÁLOGO DE CONFIRMAÇÃO ==================== */
.confirm-dialog {
    position: relative;
    z-index: 10000;
    background: #ffffff;
    border-radius: 24px;
    padding: 40px 45px;
    box-shadow: 0 30px 60px rgba(0, 0, 0, 0.3), 0 0 0 1px rgba(255, 255, 255, 0.2) inset;
    max-width: 440px;
    width: 90%;
    text-align: center;
    pointer-events: auto;
    animation: confirmSlideIn 0.4s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 20px;
}

.confirm-dialog.closing {
    animation: confirmSlideOut 0.3s ease forwards;
}

.confirm-icon-wrapper {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.8rem;
    margin-bottom: 5px;
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
    box-shadow: 0 0 0 10px rgba(239, 68, 68, 0.08);
    animation: pulseWarning 2s infinite;
}

.confirm-icon-wrapper.warning-icon {
    background: rgba(245, 158, 11, 0.12);
    color: #f59e0b;
    box-shadow: 0 0 0 10px rgba(245, 158, 11, 0.07);
}

.confirm-icon-wrapper.info-icon {
    background: rgba(59, 130, 246, 0.1);
    color: #3b82f6;
    box-shadow: 0 0 0 10px rgba(59, 130, 246, 0.07);
}

.confirm-title {
    font-size: 1.4rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0;
    line-height: 1.3;
}

.confirm-message {
    font-size: 0.95rem;
    color: #475569;
    line-height: 1.5;
    margin: 0;
    opacity: 0.9;
}

.confirm-actions {
    display: flex;
    gap: 15px;
    width: 100%;
    margin-top: 10px;
}

.confirm-btn {
    flex: 1;
    padding: 14px 10px;
    border-radius: 14px;
    font-weight: 600;
    font-size: 0.95rem;
    cursor: pointer;
    border: none;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.confirm-btn-cancel {
    background: #f1f5f9;
    color: #475569;
    border: 1px solid #e2e8f0;
}

.confirm-btn-cancel:hover {
    background: #e2e8f0;
    color: #1e293b;
}

.confirm-btn-danger {
    background: #ef4444;
    color: white;
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
}

.confirm-btn-danger:hover {
    background: #dc2626;
    box-shadow: 0 6px 16px rgba(239, 68, 68, 0.4);
    transform: translateY(-1px);
}

.confirm-btn-warning {
    background: #f59e0b;
    color: white;
    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
}

.confirm-btn-warning:hover {
    background: #d97706;
    box-shadow: 0 6px 16px rgba(245, 158, 11, 0.4);
    transform: translateY(-1px);
}

/* Animações */
@keyframes confirmSlideIn {
    from { opacity: 0; transform: scale(0.8) translateY(-40px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
}
@keyframes confirmSlideOut {
    from { opacity: 1; transform: scale(1) translateY(0); }
    to { opacity: 0; transform: scale(0.8) translateY(30px); }
}
@keyframes pulseWarning {
    0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.3); }
    70% { box-shadow: 0 0 0 18px rgba(239, 68, 68, 0); }
    100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
}
    </style>
</head>

<body>
    <!-- Sidebar (mesmo código) -->
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <img src="../assets/img/logo.png" alt="IPOK Logo" onerror="this.src='https://via.placeholder.com/80?text=IPOK'">
            </div>
            <h3>IPOK Admin</h3>
            <p>Instituto Politécnico do Kituma</p>
        </div>

        <div class="sidebar-menu">
            <div class="menu-title">PRINCIPAL</div>
            <a href="dashboard.php" class="menu-item">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>

            <div class="menu-title">GESTÃO</div>
            <a href="usuarios.php" class="menu-item active">
                <i class="fas fa-users"></i>
                <span>Utilizadores</span>
            </a>
            <a href="turmas.php" class="menu-item">
                <i class="fas fa-chalkboard"></i>
                <span>Turmas</span>
            </a>
            <a href="disciplinas.php" class="menu-item">
                <i class="fas fa-book"></i>
                <span>Disciplinas</span>
            </a>
            <a href="periodos.php" class="menu-item">
                <i class="fas fa-calendar-alt"></i>
                <span>Períodos</span>
            </a>

            <div class="menu-title">ATRIBUIÇÕES</div>
            <a href="atribuicoes.php" class="menu-item">
                <i class="fas fa-user-tie"></i>
                <span>Professor x Turma</span>
            </a>
            <a href="enturmacoes.php" class="menu-item">
                <i class="fas fa-user-plus"></i>
                <span>Enturmações</span>
            </a>

            <div class="menu-title">RELATÓRIOS</div>
            <a href="relatorios.php" class="menu-item">
                <i class="fas fa-chart-bar"></i>
                <span>Relatórios</span>
            </a>
            <a href="logs.php" class="menu-item">
                <i class="fas fa-history"></i>
                <span>Logs</span>
            </a>

            <div class="menu-title">CONTA</div>
            <a href="../logout.php" class="menu-item">
                <i class="fas fa-sign-out-alt"></i>
                <span>Sair</span>
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navigation -->
        <div class="top-nav">
            <div style="display: flex; align-items: center; gap: 15px;">
                <button class="btn btn-sm btn-outline-primary" onclick="toggleSidebar()" style="border-radius: 10px;">
                    <i class="fas fa-bars"></i>
                </button>
                <h1 class="page-title">
                    <i class="fas fa-users me-2"></i>Gestão de Utilizadores
                </h1>
            </div>
            <div class="user-info">
                <div class="user-details text-end">
                    <div class="fw-bold"><?php echo htmlspecialchars($_SESSION['user_nome']); ?></div>
                    <div class="small text-muted">Administrador</div>
                </div>
                <div class="user-avatar">
                    <?php echo strtoupper(substr($_SESSION['user_nome'], 0, 1)); ?>
                </div>
            </div>
        </div>

        <!-- Filter Section (mesmo código) -->
        <div class="filter-section">
            <!-- Filtros em Pílulas -->
            <div class="filter-pills">
                <button class="filter-pill active" data-filter="all" onclick="filterByType('all')">
                    <i class="fas fa-users"></i> Todos Utilizadores
                </button>
                <button class="filter-pill" data-filter="admin" onclick="filterByType('admin')">
                    <i class="fas fa-user-shield"></i> Administradores
                </button>
                <button class="filter-pill" data-filter="professor" onclick="filterByType('professor')">
                    <i class="fas fa-chalkboard-user"></i> Professores
                </button>
                <button class="filter-pill" data-filter="aluno" onclick="filterByType('aluno')">
                    <i class="fas fa-graduation-cap"></i> Alunos
                </button>
            </div>

            <div class="row align-items-end g-3">
                <div class="col-md-5">
                    <label class="form-label text-muted small mb-2">
                        <i class="fas fa-search me-1"></i>Pesquisar
                    </label>
                    <div class="search-input-group">
                        <i class="fas fa-search"></i>
                        <input type="text" class="form-control" id="searchInput"
                            placeholder="Nome, email, username, matrícula ou código...">
                    </div>
                </div>

                <div class="col-md-3" id="filterTurmaContainer" style="display: none;">
                    <label class="form-label text-muted small mb-2">
                        <i class="fas fa-chalkboard me-1"></i>Filtrar por Turma
                    </label>
                    <select class="form-select" id="filterTurma">
                        <option value="">Todas as turmas</option>
                        <?php foreach ($turmas_list as $turma): ?>
                            <option value="<?php echo htmlspecialchars($turma['nome']); ?>">
                                <?php echo htmlspecialchars($turma['nome']); ?> (<?php echo $turma['ano_letivo']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label text-muted small mb-2">
                        <i class="fas fa-check-circle me-1"></i>Status
                    </label>
                    <select class="form-select" id="filterStatus">
                        <option value="">Todos</option>
                        <option value="1">Ativos</option>
                        <option value="0">Inativos</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <button class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#userModal" style="border-radius: 12px; background: linear-gradient(135deg, #1e3c72, #2a5298);">
                        <i class="fas fa-plus me-2"></i>Novo Utilizador
                    </button>
                </div>
            </div>
        </div>

        <!-- Messages -->
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Users Table (mesmo código, mas agora a turma vem da enturmação) -->
        <div class="table-container">
            <div class="table-header">
                <h5>
                    <i class="fas fa-list me-2"></i>Utilizadores Cadastrados
                    <span class="badge bg-primary ms-2" id="totalCount"><?php echo $usuarios->num_rows; ?></span>
                </h5>
            </div>

            <div class="table-responsive">
                <table class="modern-table" id="usersTable">
                    <thead>
                        <tr>
                            <th style="width: 60px;">#ID</th>
                            <th>Utilizador</th>
                            <th>Username</th>
                            <th>Nível</th>
                            <th>Identificador</th>
                            <th>Turma</th>
                            <th>Status</th>
                            <th style="width: 180px;">Ações</th>
                    </thead>
                    <tbody>
                        <?php while ($user = $usuarios->fetch_assoc()):
                            $iniciais = substr($user['nome'], 0, 2);
                            $identificador = '';
                            if ($user['nivel'] == 'aluno') {
                                $identificador = $user['numero_matricula'] ?? '---';
                            } elseif ($user['nivel'] == 'professor') {
                                $identificador = $user['codigo_funcionario'] ?? '---';
                            } else {
                                $identificador = '---';
                            }
                        ?>
                            <tr data-id="<?php echo $user['id']; ?>"
                                data-nivel="<?php echo $user['nivel']; ?>"
                                data-status="<?php echo $user['ativo']; ?>"
                                data-turma="<?php echo htmlspecialchars($user['turma_nome'] ?? ''); ?>">
                                <td>
                                    <span class="badge bg-light text-dark">#<?php echo $user['id']; ?></span>
                                </td>
                                <td>
                                    <div class="user-info-cell">
                                        <div class="user-avatar">
                                            <?php echo strtoupper($iniciais); ?>
                                        </div>
                                        <div class="user-info-details">
                                            <div class="user-name"><?php echo htmlspecialchars($user['nome']); ?></div>
                                            <div class="user-email"><?php echo htmlspecialchars($user['email']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td>
                                    <span class="badge-status 
                                    <?php
                                    if ($user['nivel'] == 'admin') echo 'badge-admin';
                                    elseif ($user['nivel'] == 'professor') echo 'badge-professor';
                                    else echo 'badge-aluno';
                                    ?>">
                                        <i class="fas <?php
                                                        if ($user['nivel'] == 'admin') echo 'fa-shield-alt';
                                                        elseif ($user['nivel'] == 'professor') echo 'fa-chalkboard-teacher';
                                                        else echo 'fa-graduation-cap';
                                                        ?>"></i>
                                        <?php
                                        if ($user['nivel'] == 'admin') echo 'Administrador';
                                        elseif ($user['nivel'] == 'professor') echo 'Professor';
                                        else echo 'Aluno';
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark">
                                        <i class="fas <?php echo $user['nivel'] == 'aluno' ? 'fa-id-card' : 'fa-barcode'; ?> me-1"></i>
                                        <?php echo $identificador; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($user['turma_nome']): ?>
                                        <span class="badge bg-info text-white">
                                            <i class="fas fa-door-open me-1"></i>
                                            <?php echo htmlspecialchars($user['turma_nome']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">---</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge-status <?php echo $user['ativo'] ? 'badge-ativo' : 'badge-inativo'; ?>">
                                        <i class="fas <?php echo $user['ativo'] ? 'fa-circle' : 'fa-circle-dot'; ?> me-1"></i>
                                        <?php echo $user['ativo'] ? 'Ativo' : 'Inativo'; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn btn-sm btn-info" onclick="viewDetails(<?php echo $user['id']; ?>, '<?php echo addslashes($user['nome']); ?>', '<?php echo addslashes($user['email']); ?>', '<?php echo addslashes($user['username']); ?>', '<?php echo $user['nivel']; ?>', '<?php echo addslashes($identificador); ?>', '<?php echo addslashes($user['turma_nome'] ?? ''); ?>', '<?php echo date('d/m/Y', strtotime($user['criado_em'])); ?>', '<?php echo $user['ativo']; ?>')" data-bs-toggle="tooltip" title="Ver Detalhes">
                                            <i class="fas fa-eye"></i> Detalhes
                                        </button>

                                        <a href="?edit=<?php echo $user['id']; ?>" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" title="Editar">
                                            <i class="fas fa-edit"></i> Editar
                                        </a>

                                        <form method="POST" style="display: contents;" onsubmit="return confirm('Tem certeza que deseja resetar a senha para: ipok2026?');">
                                            <input type="hidden" name="action" value="reset_password">
                                            <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-warning" data-bs-toggle="tooltip" title="Resetar Senha">
                                                <i class="fas fa-key"></i> Senha
                                            </button>
                                        </form>

                                        <form method="POST" style="display: contents;">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                            <input type="hidden" name="status" value="<?php echo $user['ativo'] ? 0 : 1; ?>">
                                            <button type="submit" class="btn btn-sm <?php echo $user['ativo'] ? 'btn-secondary' : 'btn-success'; ?>" data-bs-toggle="tooltip" title="<?php echo $user['ativo'] ? 'Desativar' : 'Ativar'; ?>">
                                                <i class="fas <?php echo $user['ativo'] ? 'fa-ban' : 'fa-check'; ?>"></i> <?php echo $user['ativo'] ? 'Desat.' : 'Ativar'; ?>
                                            </button>
                                        </form>

                                        <form method="POST" style="display: contents;" onsubmit="return confirm(<?php echo ($user['nivel'] == 'professor') ? "'⚠️ Ao desativar este professor, ele não poderá mais acessar o sistema, mas todas as notas e disciplinas permanecerão. Deseja continuar?'" : "'⚠️ Tem certeza que deseja deletar este usuário? Esta ação não pode ser desfeita.'"; ?>);">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" data-bs-toggle="tooltip" title="<?php echo ($user['nivel'] == 'professor') ? 'Desativar Professor' : 'Deletar'; ?>">
                                                <i class="fas fa-trash"></i> <?php echo ($user['nivel'] == 'professor') ? 'Desativar' : 'Deletar'; ?>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal de Detalhes do Utilizador (mesmo código) -->
    <div class="modal fade" id="detailsModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content" style="border-radius: 20px; overflow: hidden;">
                <div class="modal-header" style="background: linear-gradient(135deg, #1e3c72, #2a5298); color: white; border: none;">
                    <h5 class="modal-title">
                        <i class="fas fa-user-circle me-2"></i>Detalhes do Utilizador
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="text-center mb-4">
                        <div class="avatar-large mx-auto" id="detailAvatar">
                            <span id="detailIniciais">JD</span>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="detail-card">
                                <div class="detail-label">
                                    <i class="fas fa-user me-1"></i> Nome Completo
                                </div>
                                <div class="detail-value" id="detailNome">---</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="detail-card">
                                <div class="detail-label">
                                    <i class="fas fa-envelope me-1"></i> E-mail
                                </div>
                                <div class="detail-value" id="detailEmail">---</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="detail-card">
                                <div class="detail-label">
                                    <i class="fas fa-user-tag me-1"></i> Username
                                </div>
                                <div class="detail-value" id="detailUsername">---</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="detail-card">
                                <div class="detail-label">
                                    <i class="fas fa-shield-alt me-1"></i> Nível
                                </div>
                                <div class="detail-value" id="detailNivel">---</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="detail-card">
                                <div class="detail-label">
                                    <i class="fas fa-id-card me-1"></i> Identificador
                                </div>
                                <div class="detail-value" id="detailIdentificador">---</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="detail-card">
                                <div class="detail-label">
                                    <i class="fas fa-chalkboard me-1"></i> Turma
                                </div>
                                <div class="detail-value" id="detailTurma">---</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="detail-card">
                                <div class="detail-label">
                                    <i class="fas fa-calendar me-1"></i> Data de Registo
                                </div>
                                <div class="detail-value" id="detailData">---</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="detail-card">
                                <div class="detail-label">
                                    <i class="fas fa-circle me-1"></i> Status
                                </div>
                                <div class="detail-value" id="detailStatus">---</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                    <button type="button" class="btn btn-primary" id="btnEditFromDetails">
                        <i class="fas fa-edit me-2"></i>Editar Utilizador
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- User Modal (Create/Edit) com campo de turma -->
    <div class="modal fade" id="userModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" style="border-radius: 20px; overflow: hidden;">
                <div class="modal-header" style="background: linear-gradient(135deg, #1e3c72, #2a5298); color: white; border: none;">
                    <h5 class="modal-title">
                        <i class="fas <?php echo $edit_user ? 'fa-edit' : 'fa-user-plus'; ?> me-2"></i>
                        <?php echo $edit_user ? 'Editar Utilizador' : 'Criar Novo Utilizador'; ?>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body p-4">
                        <input type="hidden" name="action" value="<?php echo $edit_user ? 'edit' : 'create'; ?>">
                        <?php if ($edit_user): ?>
                            <input type="hidden" name="id" value="<?php echo $edit_user['id']; ?>">
                        <?php endif; ?>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nome Completo *</label>
                                <input type="text" class="form-control" name="nome"
                                    value="<?php echo $edit_user ? htmlspecialchars($edit_user['nome']) : ''; ?>" required>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email *</label>
                                <input type="email" class="form-control" name="email"
                                    value="<?php echo $edit_user ? htmlspecialchars($edit_user['email']) : ''; ?>" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Username *</label>
                                <input type="text" class="form-control" name="username"
                                    value="<?php echo $edit_user ? htmlspecialchars($edit_user['username']) : ''; ?>" required>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nível de Acesso *</label>
                                <select class="form-select" name="nivel" id="nivelSelect" required>
                                    <option value="">-- Selecione --</option>
                                    <option value="admin" <?php echo ($edit_user && $edit_user['nivel'] == 'admin') ? 'selected' : ''; ?>>Administrador</option>
                                    <option value="professor" <?php echo ($edit_user && $edit_user['nivel'] == 'professor') ? 'selected' : ''; ?>>Professor</option>
                                    <option value="aluno" <?php echo ($edit_user && $edit_user['nivel'] == 'aluno') ? 'selected' : ''; ?>>Aluno</option>
                                </select>
                            </div>
                        </div>

                        <?php if (!$edit_user): ?>
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">Senha (mín. 6 caracteres) *</label>
                                    <input type="password" class="form-control" name="senha" minlength="6" required>
                                    <small class="text-muted">A senha será encriptada antes de ser guardada</small>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Campos específicos para Aluno -->
                        <div id="alunoFields" style="display: none;">
                            <hr>
                            <h6 class="text-primary"><i class="fas fa-graduation-cap me-2"></i>Dados do Aluno</h6>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Número de Matrícula *</label>
                                    <input type="text" class="form-control" name="matricula"
                                        value="<?php echo ($edit_user && $edit_user['nivel'] == 'aluno') ? htmlspecialchars($edit_user['numero_matricula']) : ''; ?>">
                                    <small class="text-muted">Ex: 2025001</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Data de Matrícula</label>
                                    <input type="date" class="form-control" name="data_matricula"
                                        value="<?php echo ($edit_user && $edit_user['nivel'] == 'aluno') ? $edit_user['data_matricula'] : date('Y-m-d'); ?>">
                                </div>
                            </div>

                            <!-- Campo de seleção de Turma -->
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">Turma</label>
                                    <select class="form-select" name="turma_id" id="turmaSelect">
                                        <option value="">-- Selecione uma turma (opcional) --</option>
                                        <?php foreach ($turmas_list as $turma): ?>
                                            <option value="<?php echo $turma['id']; ?>"
                                                <?php echo ($edit_user && $edit_user['nivel'] == 'aluno' && $edit_user['turma_id'] == $turma['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($turma['nome']); ?> (<?php echo $turma['ano_letivo']; ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="text-muted">Selecione a turma onde o aluno será enturmado</small>
                                </div>
                            </div>
                        </div>

                        <!-- Campos específicos para Professor -->
                        <div id="professorFields" style="display: none;">
                            <hr>
                            <h6 class="text-primary"><i class="fas fa-chalkboard-teacher me-2"></i>Dados do Professor</h6>
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">Código de Funcionário</label>
                                    <input type="text" class="form-control" name="codigo_funcionario"
                                        value="<?php echo ($edit_user && $edit_user['nivel'] == 'professor') ? htmlspecialchars($edit_user['codigo_funcionario']) : ''; ?>">
                                    <small class="text-muted">Ex: PROF2025001</small>
                                </div>
                            </div>
                            <div class="vinculacao-section p-3 border rounded bg-light">
                                <h6 class="small fw-bold mb-3"><i class="fas fa-link me-2"></i>Atribuição de Disciplina (Opcional)</h6>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label small">Turma</label>
                                        <select class="form-select form-select-sm" name="prof_turma_id">
                                            <option value="">-- Selecione --</option>
                                            <?php foreach ($turmas_list as $turma): ?>
                                                <option value="<?php echo $turma['id']; ?>"><?php echo htmlspecialchars($turma['nome']); ?> (<?php echo $turma['ano_letivo']; ?>)</option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label small">Disciplina</label>
                                        <select class="form-select form-select-sm" name="prof_disciplina_id">
                                            <option value="">-- Selecione --</option>
                                            <?php foreach ($disciplinas_list as $disc): ?>
                                                <option value="<?php echo $disc['id']; ?>"><?php echo htmlspecialchars($disc['nome']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-12">
                                        <label class="form-label small">Ano Letivo</label>
                                        <select class="form-select form-select-sm" name="prof_ano_letivo">
                                            <?php foreach ($anos_letivos as $ano): ?>
                                                <option value="<?php echo $ano; ?>" <?php echo ($ano == date('Y')) ? 'selected' : ''; ?>><?php echo $ano; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php if ($edit_user): ?>
                            <hr>
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="ativo" id="ativo"
                                            <?php echo $edit_user['ativo'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="ativo">
                                            Utilizador Ativo
                                        </label>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Toggle sidebar
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.querySelector('.main-content');
            sidebar.classList.toggle('hidden');
            mainContent.classList.toggle('sidebar-hidden');
        }

        // Função para filtrar por tipo
        let currentType = 'all';

        function filterByType(type) {
            currentType = type;

            // Atualizar classes dos botões
            document.querySelectorAll('.filter-pill').forEach(pill => {
                pill.classList.remove('active');
            });
            document.querySelector(`.filter-pill[data-filter="${type}"]`).classList.add('active');

            // Mostrar/esconder filtro de turma (apenas para alunos)
            if (type === 'aluno') {
                document.getElementById('filterTurmaContainer').style.display = 'block';
            } else {
                document.getElementById('filterTurmaContainer').style.display = 'none';
                document.getElementById('filterTurma').value = '';
            }

            aplicarFiltros();
        }

        function aplicarFiltros() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase().trim();
            const tipoFilter = currentType;
            const statusFilter = document.getElementById('filterStatus').value;
            const turmaFilter = document.getElementById('filterTurma').value.toLowerCase();
            const rows = document.querySelectorAll('#usersTable tbody tr');
            let visibleCount = 0;

            rows.forEach(row => {
                const nome = row.querySelector('.user-name')?.textContent.toLowerCase() || '';
                const email = row.querySelector('.user-email')?.textContent.toLowerCase() || '';
                const username = row.cells[2]?.textContent.toLowerCase() || '';
                const identificador = row.cells[4]?.textContent.toLowerCase() || '';
                const nivel = row.getAttribute('data-nivel');
                const status = row.getAttribute('data-status');
                const turma = row.getAttribute('data-turma')?.toLowerCase() || '';

                // Verificar tipo
                const matchTipo = tipoFilter === 'all' || nivel === tipoFilter;

                // Verificar status
                const matchStatus = statusFilter === '' || status === statusFilter;

                // Verificar turma
                const matchTurma = turmaFilter === '' || turma.includes(turmaFilter);

                // Verificar pesquisa
                const matchSearch = searchTerm === '' ||
                    nome.includes(searchTerm) ||
                    email.includes(searchTerm) ||
                    username.includes(searchTerm) ||
                    identificador.includes(searchTerm);

                if (matchTipo && matchStatus && matchTurma && matchSearch) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            document.getElementById('totalCount').innerHTML = visibleCount;
        }

        // Event listeners
        document.getElementById('searchInput').addEventListener('keyup', aplicarFiltros);
        document.getElementById('filterStatus').addEventListener('change', aplicarFiltros);
        document.getElementById('filterTurma').addEventListener('change', aplicarFiltros);

        // Função para visualizar detalhes
        let currentUserId = null;

        function viewDetails(id, nome, email, username, nivel, identificador, turma, dataCriacao, ativo) {
            currentUserId = id;

            // Atualizar avatar
            const iniciais = nome.substring(0, 2).toUpperCase();
            document.getElementById('detailIniciais').textContent = iniciais;

            // Atualizar dados
            document.getElementById('detailNome').textContent = nome;
            document.getElementById('detailEmail').textContent = email;
            document.getElementById('detailUsername').textContent = username;

            // Nível com badge
            let nivelHtml = '';
            if (nivel === 'admin') {
                nivelHtml = '<span class="badge-status badge-admin"><i class="fas fa-shield-alt"></i> Administrador</span>';
            } else if (nivel === 'professor') {
                nivelHtml = '<span class="badge-status badge-professor"><i class="fas fa-chalkboard-teacher"></i> Professor</span>';
            } else {
                nivelHtml = '<span class="badge-status badge-aluno"><i class="fas fa-graduation-cap"></i> Aluno</span>';
            }
            document.getElementById('detailNivel').innerHTML = nivelHtml;

            document.getElementById('detailIdentificador').textContent = identificador !== '---' ? identificador : 'Não definido';
            document.getElementById('detailTurma').textContent = turma ? turma : 'Não enturmado';
            document.getElementById('detailData').textContent = dataCriacao;

            // Status
            let statusHtml = ativo == 1 ?
                '<span class="badge-status badge-ativo"><i class="fas fa-circle"></i> Ativo</span>' :
                '<span class="badge-status badge-inativo"><i class="fas fa-circle-dot"></i> Inativo</span>';
            document.getElementById('detailStatus').innerHTML = statusHtml;

            // Botão de editar
            document.getElementById('btnEditFromDetails').onclick = function() {
                window.location.href = '?edit=' + currentUserId;
            };

            // Mostrar modal
            new bootstrap.Modal(document.getElementById('detailsModal')).show();
        }

        // Tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Mostrar/esconder campos específicos por nível
        function toggleNivelFields() {
            var nivel = document.getElementById('nivelSelect').value;
            var alunoFields = document.getElementById('alunoFields');
            var professorFields = document.getElementById('professorFields');

            alunoFields.style.display = 'none';
            professorFields.style.display = 'none';

            if (nivel === 'aluno') {
                alunoFields.style.display = 'block';
                alunoFields.querySelectorAll('input').forEach(input => input.required = true);
                // O campo turma não é obrigatório, então não alteramos required
                if (professorFields) professorFields.querySelectorAll('input').forEach(input => input.required = false);
            } else if (nivel === 'professor') {
                professorFields.style.display = 'block';
                professorFields.querySelectorAll('input').forEach(input => input.required = false);
                if (alunoFields) alunoFields.querySelectorAll('input').forEach(input => input.required = false);
            } else {
                if (alunoFields) alunoFields.querySelectorAll('input').forEach(input => input.required = false);
                if (professorFields) professorFields.querySelectorAll('input').forEach(input => input.required = false);
            }
        }

        var nivelSelect = document.getElementById('nivelSelect');
        if (nivelSelect) {
            nivelSelect.addEventListener('change', toggleNivelFields);
            toggleNivelFields();
        }

        // Mostrar modal de edição se existir
        <?php if ($edit_user): ?>
            var userModal = new bootstrap.Modal(document.getElementById('userModal'));
            userModal.show();
        <?php endif; ?>

        // Animações de entrada
        document.addEventListener('DOMContentLoaded', function() {
            const rows = document.querySelectorAll('#usersTable tbody tr');
            rows.forEach((row, index) => {
                row.style.opacity = '0';
                row.style.transform = 'translateY(10px)';
                setTimeout(() => {
                    row.style.transition = 'all 0.3s ease';
                    row.style.opacity = '1';
                    row.style.transform = 'translateY(0)';
                }, index * 50);
            });
        });
        // Sucesso
        // Exibir toasts de mensagens PHP
        document.addEventListener('DOMContentLoaded', function() {
            const messageDiv = document.querySelector('.alert.alert-success');
            const errorDiv = document.querySelector('.alert.alert-danger');

            if (messageDiv) {
                const message = messageDiv.textContent.trim();
                toast.success('✅ Sucesso!', message);
                // Ocultar o alerta Bootstrap
                messageDiv.style.display = 'none';
            }

            if (errorDiv) {
                const error = errorDiv.textContent.trim();
                toast.error('❌ Erro!', error);
                // Ocultar o alerta Bootstrap
                errorDiv.style.display = 'none';
            }
        });
    </script>
</body>

</html>