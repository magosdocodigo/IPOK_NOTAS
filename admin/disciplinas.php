<?php
// admin/disciplinas.php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

// Verificar se é admin
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

$message = '';
$error = '';

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $ip = $_SERVER['REMOTE_ADDR'];
        
        switch ($_POST['action']) {
            case 'create':
            case 'edit':
                $nome = mysqli_real_escape_string($db, $_POST['nome']);
                $codigo = mysqli_real_escape_string($db, $_POST['codigo']);
                $carga_horaria = !empty($_POST['carga_horaria']) ? (int)$_POST['carga_horaria'] : 'NULL';
                
                // Dados opcionais de vinculação
                $turma_id = !empty($_POST['turma_id']) ? (int)$_POST['turma_id'] : null;
                $professor_id = !empty($_POST['professor_id']) ? (int)$_POST['professor_id'] : null;
                $ano_letivo = !empty($_POST['ano_letivo']) ? (int)$_POST['ano_letivo'] : null;
                
                if ($_POST['action'] === 'create') {
                    // Verificar se já existe disciplina com mesmo código
                    if (!empty($codigo)) {
                        $check = $db->query("SELECT id FROM disciplinas WHERE codigo = '$codigo'");
                        if ($check && $check->num_rows > 0) {
                            $error = "Já existe uma disciplina com este código!";
                            break;
                        }
                    }
                    
                    $query = "INSERT INTO disciplinas (nome, codigo, carga_horaria) 
                              VALUES ('$nome', " . ($codigo ? "'$codigo'" : "NULL") . ", $carga_horaria)";
                    
                    if ($db->query($query)) {
                        $disciplina_id = $db->insert_id;
                        
                        // Log de auditoria
                        $db->query("INSERT INTO logs_auditoria (usuario_id, acao, tabela, registro_id, ip) 
                                   VALUES ({$_SESSION['user_id']}, 'CRIAR_DISCIPLINA', 'disciplinas', $disciplina_id, '$ip')");
                        
                        // Vinculação opcional: turma e professor
                        if ($turma_id) {
                            // Verificar se já existe vínculo turma-disciplina
                            $check_td = $db->query("SELECT id FROM turma_disciplina WHERE turma_id = $turma_id AND disciplina_id = $disciplina_id");
                            if ($check_td->num_rows == 0) {
                                $db->query("INSERT INTO turma_disciplina (turma_id, disciplina_id) VALUES ($turma_id, $disciplina_id)");
                                $turma_disciplina_id = $db->insert_id;
                                
                                // Se também foi selecionado professor e ano letivo, atribuir
                                if ($professor_id && $ano_letivo) {
                                    // Verificar se já existe atribuição para essa combinação
                                    $check_atr = $db->query("SELECT id FROM atribuicoes WHERE turma_disciplina_id = $turma_disciplina_id AND professor_id = $professor_id AND ano_letivo = $ano_letivo");
                                    if ($check_atr->num_rows == 0) {
                                        $db->query("INSERT INTO atribuicoes (turma_disciplina_id, professor_id, ano_letivo) VALUES ($turma_disciplina_id, $professor_id, $ano_letivo)");
                                        $db->query("INSERT INTO logs_auditoria (usuario_id, acao, tabela, registro_id, ip) 
                                                   VALUES ({$_SESSION['user_id']}, 'ATRIBUIR_PROFESSOR_DISCIPLINA', 'atribuicoes', {$db->insert_id}, '$ip')");
                                    }
                                }
                            } elseif ($professor_id && $ano_letivo) {
                                // Se já existia o vínculo turma-disciplina, tenta atribuir professor
                                $turma_disciplina_id = $check_td->fetch_assoc()['id'];
                                $check_atr = $db->query("SELECT id FROM atribuicoes WHERE turma_disciplina_id = $turma_disciplina_id AND professor_id = $professor_id AND ano_letivo = $ano_letivo");
                                if ($check_atr->num_rows == 0) {
                                    $db->query("INSERT INTO atribuicoes (turma_disciplina_id, professor_id, ano_letivo) VALUES ($turma_disciplina_id, $professor_id, $ano_letivo)");
                                    $db->query("INSERT INTO logs_auditoria (usuario_id, acao, tabela, registro_id, ip) 
                                               VALUES ({$_SESSION['user_id']}, 'ATRIBUIR_PROFESSOR_DISCIPLINA', 'atribuicoes', {$db->insert_id}, '$ip')");
                                }
                            }
                        }
                        
                        $message = "Disciplina criada com sucesso!";
                        if ($turma_id) $message .= " Vinculada à turma selecionada.";
                        if ($professor_id && $ano_letivo) $message .= " Professor atribuído para o ano letivo $ano_letivo.";
                    } else {
                        $error = "Erro ao criar disciplina: " . $db->error;
                    }
                } else {
                    // Editar disciplina
                    $id = (int)$_POST['id'];
                    
                    // Verificar se já existe outra disciplina com mesmo código
                    if (!empty($codigo)) {
                        $check = $db->query("SELECT id FROM disciplinas WHERE codigo = '$codigo' AND id != $id");
                        if ($check && $check->num_rows > 0) {
                            $error = "Já existe outra disciplina com este código!";
                            break;
                        }
                    }
                    
                    $query = "UPDATE disciplinas SET 
                              nome = '$nome',
                              codigo = " . ($codigo ? "'$codigo'" : "NULL") . ",
                              carga_horaria = $carga_horaria
                              WHERE id = $id";
                    
                    if ($db->query($query)) {
                        // Log de auditoria
                        $db->query("INSERT INTO logs_auditoria (usuario_id, acao, tabela, registro_id, ip) 
                                   VALUES ({$_SESSION['user_id']}, 'EDITAR_DISCIPLINA', 'disciplinas', $id, '$ip')");
                        
                        // Vinculação opcional: turma e professor (adicionar novos vínculos sem remover os existentes)
                        if ($turma_id) {
                            // Verificar se já existe vínculo turma-disciplina
                            $check_td = $db->query("SELECT id FROM turma_disciplina WHERE turma_id = $turma_id AND disciplina_id = $id");
                            if ($check_td->num_rows == 0) {
                                $db->query("INSERT INTO turma_disciplina (turma_id, disciplina_id) VALUES ($turma_id, $id)");
                                $turma_disciplina_id = $db->insert_id;
                                
                                // Se também foi selecionado professor e ano letivo, atribuir
                                if ($professor_id && $ano_letivo) {
                                    $check_atr = $db->query("SELECT id FROM atribuicoes WHERE turma_disciplina_id = $turma_disciplina_id AND professor_id = $professor_id AND ano_letivo = $ano_letivo");
                                    if ($check_atr->num_rows == 0) {
                                        $db->query("INSERT INTO atribuicoes (turma_disciplina_id, professor_id, ano_letivo) VALUES ($turma_disciplina_id, $professor_id, $ano_letivo)");
                                        $db->query("INSERT INTO logs_auditoria (usuario_id, acao, tabela, registro_id, ip) 
                                                   VALUES ({$_SESSION['user_id']}, 'ATRIBUIR_PROFESSOR_DISCIPLINA', 'atribuicoes', {$db->insert_id}, '$ip')");
                                    }
                                }
                            } elseif ($professor_id && $ano_letivo) {
                                $turma_disciplina_id = $check_td->fetch_assoc()['id'];
                                $check_atr = $db->query("SELECT id FROM atribuicoes WHERE turma_disciplina_id = $turma_disciplina_id AND professor_id = $professor_id AND ano_letivo = $ano_letivo");
                                if ($check_atr->num_rows == 0) {
                                    $db->query("INSERT INTO atribuicoes (turma_disciplina_id, professor_id, ano_letivo) VALUES ($turma_disciplina_id, $professor_id, $ano_letivo)");
                                    $db->query("INSERT INTO logs_auditoria (usuario_id, acao, tabela, registro_id, ip) 
                                               VALUES ({$_SESSION['user_id']}, 'ATRIBUIR_PROFESSOR_DISCIPLINA', 'atribuicoes', {$db->insert_id}, '$ip')");
                                }
                            }
                        }
                        
                        $message = "Disciplina atualizada com sucesso!";
                        if ($turma_id) $message .= " Novo vínculo com turma adicionado.";
                        if ($professor_id && $ano_letivo) $message .= " Professor atribuído para o ano letivo $ano_letivo.";
                    } else {
                        $error = "Erro ao atualizar disciplina: " . $db->error;
                    }
                }
                break;
                
            case 'delete':
                $id = (int)$_POST['id'];
                
                // Verificar se existem notas ou atribuições vinculadas
                $check = $db->query("SELECT COUNT(*) as total FROM notas WHERE disciplina_id = $id");
                $notas = $check->fetch_assoc()['total'];
                
                $check = $db->query("SELECT COUNT(*) as total FROM turma_disciplina WHERE disciplina_id = $id");
                $vinculos = $check->fetch_assoc()['total'];
                
                if ($notas > 0 || $vinculos > 0) {
                    $error = "Não é possível excluir esta disciplina pois existem registos vinculados (notas ou turmas).";
                } else {
                    // Log antes de deletar
                    $db->query("INSERT INTO logs_auditoria (usuario_id, acao, tabela, registro_id, ip) 
                               VALUES ({$_SESSION['user_id']}, 'DELETAR_DISCIPLINA', 'disciplinas', $id, '$ip')");
                    
                    $query = "DELETE FROM disciplinas WHERE id = $id";
                    if ($db->query($query)) {
                        $message = "Disciplina deletada com sucesso!";
                    } else {
                        $error = "Erro ao deletar disciplina: " . $db->error;
                    }
                }
                break;
        }
    }
}

// Buscar disciplinas com estatísticas
$query = "SELECT d.*,
          COUNT(DISTINCT td.turma_id) as total_turmas,
          COUNT(DISTINCT n.id) as total_notas_lancadas,
          COUNT(DISTINCT a.professor_id) as total_professores
          FROM disciplinas d
          LEFT JOIN turma_disciplina td ON d.id = td.disciplina_id
          LEFT JOIN notas n ON d.id = n.disciplina_id
          LEFT JOIN atribuicoes a ON td.id = a.turma_disciplina_id
          GROUP BY d.id
          ORDER BY d.nome ASC";
$disciplinas = $db->query($query);

// Buscar dados para edição
$edit_disciplina = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $query = "SELECT * FROM disciplinas WHERE id = $id";
    $result = $db->query($query);
    $edit_disciplina = $result->fetch_assoc();
    
    // Adicionar estatísticas para edição
    if ($edit_disciplina) {
        $stats = $db->query("SELECT 
                            (SELECT COUNT(*) FROM turma_disciplina WHERE disciplina_id = $id) as turmas,
                            (SELECT COUNT(*) FROM notas WHERE disciplina_id = $id) as notas,
                            (SELECT COUNT(DISTINCT professor_id) FROM atribuicoes a 
                             INNER JOIN turma_disciplina td ON a.turma_disciplina_id = td.id 
                             WHERE td.disciplina_id = $id) as professores");
        $stats_result = $stats->fetch_assoc();
        $edit_disciplina['total_turmas'] = $stats_result['turmas'];
        $edit_disciplina['total_notas_lancadas'] = $stats_result['notas'];
        $edit_disciplina['total_professores'] = $stats_result['professores'];
    }
}

// Buscar listas para os selects do modal
$turmas_list = [];
$turmas_result = $db->query("SELECT id, nome, ano_letivo FROM turmas ORDER BY ano_letivo DESC, nome");
while ($turma = $turmas_result->fetch_assoc()) {
    $turmas_list[] = $turma;
}

$professores_list = [];
$professores_result = $db->query("SELECT u.id, u.nome FROM usuarios u 
                                  INNER JOIN professores p ON u.id = p.usuario_id 
                                  WHERE u.nivel = 'professor' AND u.ativo = 1 
                                  ORDER BY u.nome");
while ($prof = $professores_result->fetch_assoc()) {
    $professores_list[] = $prof;
}

// Buscar anos letivos disponíveis (em vez de períodos com nome)
$anos_letivos = [];
$anos_result = $db->query("SELECT DISTINCT ano_letivo FROM periodos ORDER BY ano_letivo DESC");
while ($ano = $anos_result->fetch_assoc()) {
    $anos_letivos[] = $ano['ano_letivo'];
}

// Estatísticas gerais
$stats_query = "SELECT 
                COUNT(*) as total_disciplinas,
                SUM(CASE WHEN carga_horaria IS NOT NULL THEN 1 ELSE 0 END) as com_carga,
                AVG(carga_horaria) as media_carga
                FROM disciplinas";
$stats_result = $db->query($stats_query);
$stats = $stats_result->fetch_assoc();

// Buscar cargas horárias únicas para filtro
$cargas = [];
$cargas_query = $db->query("SELECT DISTINCT carga_horaria FROM disciplinas WHERE carga_horaria IS NOT NULL ORDER BY carga_horaria");
while ($carga = $cargas_query->fetch_assoc()) {
    $cargas[] = $carga['carga_horaria'];
}

$page_title = "Gestão de Disciplinas";
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IPOK - Gestão de Disciplinas</title>
    <link rel="shortcut icon" href="../assets/img/logo.png" type="image/x-icon">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
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
            transition: all .3s ease;
            z-index: 1000;
            overflow-y: auto;
        }
        
        .sidebar.hidden { transform: translateX(-100%); }
        
        .sidebar-header {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,.1);
        }
        
        .sidebar-header .logo {
            width: 80px;
            height: 80px;
            margin: 0 auto 15px;
        }
        
        .sidebar-header .logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        
        .sidebar-menu { padding: 20px 0; }
        
        .sidebar-menu .menu-title {
            padding: 10px 20px;
            font-size: .75rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: .6;
        }
        
        .sidebar-menu .menu-item {
            padding: 12px 20px;
            display: flex;
            align-items: center;
            color: white;
            text-decoration: none;
            transition: all .3s;
            margin: 5px 10px;
            border-radius: 10px;
        }
        
        .sidebar-menu .menu-item:hover,
        .sidebar-menu .menu-item.active {
            background: rgba(255,255,255,.2);
            transform: translateX(5px);
        }
        
        .sidebar-menu .menu-item i { width: 30px; font-size: 1.2rem; }
        
        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 20px;
            transition: all .3s ease;
            min-height: 100vh;
        }
        
        .main-content.sidebar-hidden { margin-left: 0; }
        
        /* Top Navigation */
        .top-nav {
            background: white;
            padding: 15px 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,.05);
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .top-nav .page-title { color: var(--primary-blue); font-size: 1.5rem; font-weight: 600; margin: 0; }
        
        .user-avatar {
            width: 45px; height: 45px;
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        
        /* Filter Section */
        .filter-section {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,.05);
        }
        
        /* Filter Pills */
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
        
        .filter-pill i { margin-right: 8px; }
        
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
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 25px;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,.05);
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }
        
        .stat-content h3 {
            margin: 0;
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary-blue);
        }
        
        .stat-content p {
            margin: 0;
            color: #6c757d;
            font-size: 0.85rem;
        }
        
        /* Table Container */
        .table-container {
            background: white;
            border-radius: 15px;
            padding: 0;
            box-shadow: 0 5px 20px rgba(0,0,0,.05);
            overflow: hidden;
        }
        
        .table-header {
            padding: 20px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
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
        
        .badge-codigo {
            background: #cfe2ff;
            color: #084298;
        }
        
        .badge-carga {
            background: #e2e3e5;
            color: #495057;
        }
        
        .badge-turmas {
            background: #d1fae5;
            color: #065f46;
        }
        
        .badge-professores {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-notas {
            background: #f8d7da;
            color: #721c24;
        }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            min-width: 280px;
        }
        
        .action-buttons .btn {
            flex-basis: calc(33.333% - 6px);
        }
        
        .btn-sm {
            padding: 6px 8px;
            font-size: 0.75rem;
            border-radius: 8px;
            transition: all 0.3s ease;
            white-space: nowrap;
        }
        
        .btn-sm:hover { transform: translateY(-2px); }
        
        /* Modal */
        .modal-header {
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            color: white;
        }
        
        .modal-header .btn-close { filter: brightness(0) invert(1); }
        
        .form-label { color: var(--primary-blue); font-weight: 500; }
        
        .form-control, .form-select {
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            padding: 10px;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--secondary-blue);
            box-shadow: 0 0 0 0.2rem rgba(42,82,152,.25);
        }
        
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
        
        .btn-add {
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 8px;
            font-size: .9rem;
            transition: all .3s;
        }
        
        .btn-add:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(30,60,114,.3);
            color: white;
        }
        
        /* Seção de vinculação */
        .vinculacao-section {
            background: #f8fafc;
            border-radius: 12px;
            padding: 15px;
            margin-top: 15px;
            border-left: 4px solid var(--primary-blue);
        }
        .vinculacao-section h6 {
            color: var(--primary-blue);
            margin-bottom: 15px;
        }
        
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); }
            .main-content { margin-left: 0; }
        }
    </style>
</head>
<body>
    <!-- Sidebar (mesmo conteúdo) -->
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
            <a href="usuarios.php" class="menu-item">
                <i class="fas fa-users"></i>
                <span>Utilizadores</span>
            </a>
            <a href="turmas.php" class="menu-item">
                <i class="fas fa-chalkboard"></i>
                <span>Turmas</span>
            </a>
            <a href="disciplinas.php" class="menu-item active">
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
                    <i class="fas fa-book me-2"></i>Gestão de Disciplinas
                </h1>
            </div>
            <div class="user-info d-flex align-items-center gap-3">
                <div class="text-end">
                    <div class="fw-bold"><?php echo htmlspecialchars($_SESSION['user_nome'] ?? ''); ?></div>
                    <div class="small text-muted">Administrador</div>
                </div>
                <div class="user-avatar">
                    <?php echo strtoupper(substr($_SESSION['user_nome'] ?? 'A', 0, 1)); ?>
                </div>
            </div>
        </div>
        
        <!-- Filter Section -->
        <div class="filter-section">
            <!-- Filtros em Pílulas -->
            <div class="filter-pills">
                <button class="filter-pill active" data-filter="all" onclick="filterByType('all')">
                    <i class="fas fa-book"></i> Todas Disciplinas
                </button>
                <button class="filter-pill" data-filter="carga" onclick="filterByCarga()">
                    <i class="fas fa-clock"></i> Por Carga Horária
                </button>
                <button class="filter-pill" data-filter="turmas" onclick="filterByTurmas()">
                    <i class="fas fa-chalkboard"></i> Com Turmas
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
                               placeholder="Nome da disciplina, código...">
                    </div>
                </div>
                
                <div class="col-md-3" id="filterCargaContainer" style="display: none;">
                    <label class="form-label text-muted small mb-2">
                        <i class="fas fa-clock me-1"></i>Carga Horária
                    </label>
                    <select class="form-select" id="filterCarga">
                        <option value="">Todas</option>
                        <?php foreach ($cargas as $carga): ?>
                            <option value="<?php echo $carga; ?>"><?php echo $carga; ?> horas</option>
                        <?php endforeach; ?>
                        <option value="sem">Sem carga horária</option>
                    </select>
                </div>
                
                <div class="col-md-2" id="filterTurmasContainer" style="display: none;">
                    <label class="form-label text-muted small mb-2">
                        <i class="fas fa-chalkboard me-1"></i>Turmas
                    </label>
                    <select class="form-select" id="filterTurmas">
                        <option value="">Todas</option>
                        <option value="com">Com turmas vinculadas</option>
                        <option value="sem">Sem turmas</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <button class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#disciplinaModal" style="border-radius: 12px; background: linear-gradient(135deg, #1e3c72, #2a5298);">
                        <i class="fas fa-plus me-2"></i>Nova Disciplina
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
        
        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-book"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['total_disciplinas']; ?></h3>
                    <p>Total de Disciplinas</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['com_carga']; ?></h3>
                    <p>Com Carga Horária</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo round($stats['media_carga'] ?? 0); ?>h</h3>
                    <p>Média Carga Horária</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-chalkboard"></i>
                </div>
                <div class="stat-content">
                    <h3 id="totalComTurmas">0</h3>
                    <p>Disciplinas em Turmas</p>
                </div>
            </div>
        </div>
        
        <!-- Disciplinas Table -->
        <div class="table-container">
            <div class="table-header">
                <h5>
                    <i class="fas fa-list me-2"></i>Disciplinas Cadastradas
                    <span class="badge bg-primary ms-2" id="totalCount"><?php echo $disciplinas->num_rows; ?></span>
                </h5>
            </div>
            
            <div class="table-responsive">
                <table class="modern-table" id="disciplinasTable">
                    <thead>
                        <tr>
                            <th style="width: 60px;">#ID</th>
                            <th>Disciplina</th>
                            <th>Código</th>
                            <th>Carga Horária</th>
                            <th>Turmas</th>
                            <th>Professores</th>
                            <th>Notas</th>
                            <th style="width: 280px;">Ações</th>
                        </thead>
                    <tbody>
                        <?php 
                        $disciplinas_array = [];
                        while ($disciplina = $disciplinas->fetch_assoc()): 
                            $disciplinas_array[] = $disciplina;
                            $iniciais = substr($disciplina['nome'], 0, 2);
                        ?>
                        <tr data-id="<?php echo $disciplina['id']; ?>"
                            data-nome="<?php echo strtolower($disciplina['nome']); ?>"
                            data-codigo="<?php echo strtolower($disciplina['codigo'] ?? ''); ?>"
                            data-carga="<?php echo $disciplina['carga_horaria']; ?>"
                            data-turmas="<?php echo $disciplina['total_turmas']; ?>">
                            <td>
                                <span class="badge bg-light text-dark">#<?php echo $disciplina['id']; ?></span>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <div class="user-avatar" style="width: 40px; height: 40px; font-size: 1rem;">
                                        <?php echo strtoupper($iniciais); ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold"><?php echo htmlspecialchars($disciplina['nome']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if ($disciplina['codigo']): ?>
                                <span class="badge-status badge-codigo">
                                    <i class="fas fa-barcode me-1"></i>
                                    <?php echo htmlspecialchars($disciplina['codigo']); ?>
                                </span>
                                <?php else: ?>
                                <span class="text-muted">---</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($disciplina['carga_horaria']): ?>
                                <span class="badge-status badge-carga">
                                    <i class="fas fa-clock me-1"></i>
                                    <?php echo $disciplina['carga_horaria']; ?>h
                                </span>
                                <?php else: ?>
                                <span class="text-muted">---</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge-status badge-turmas">
                                    <i class="fas fa-chalkboard me-1"></i>
                                    <?php echo (int)$disciplina['total_turmas']; ?> turmas
                                </span>
                            </td>
                            <td>
                                <span class="badge-status badge-professores">
                                    <i class="fas fa-user-tie me-1"></i>
                                    <?php echo (int)$disciplina['total_professores']; ?> prof.
                                </span>
                            </td>
                            <td>
                                <span class="badge-status badge-notas">
                                    <i class="fas fa-file-alt me-1"></i>
                                    <?php echo (int)$disciplina['total_notas_lancadas']; ?> notas
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn btn-sm btn-info" onclick="viewDetails(<?php echo $disciplina['id']; ?>, '<?php echo addslashes($disciplina['nome']); ?>', '<?php echo addslashes($disciplina['codigo'] ?? ''); ?>', '<?php echo $disciplina['carga_horaria'] ?? 'Não definida'; ?>', '<?php echo (int)$disciplina['total_turmas']; ?>', '<?php echo (int)$disciplina['total_professores']; ?>', '<?php echo (int)$disciplina['total_notas_lancadas']; ?>')" data-bs-toggle="tooltip" title="Ver Detalhes">
                                        <i class="fas fa-eye"></i> Detalhes
                                    </button>
                                    
                                    <a href="?edit=<?php echo $disciplina['id']; ?>" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" title="Editar">
                                        <i class="fas fa-edit"></i> Editar
                                    </a>
                                    
                                    <a href="turma_disciplinas.php?disciplina_id=<?php echo $disciplina['id']; ?>" class="btn btn-sm btn-success" data-bs-toggle="tooltip" title="Vincular a Turmas">
                                        <i class="fas fa-link"></i> Vincular
                                    </a>
                                    
                                    <a href="atribuicoes.php?disciplina_id=<?php echo $disciplina['id']; ?>" class="btn btn-sm btn-warning" data-bs-toggle="tooltip" title="Ver Professores">
                                        <i class="fas fa-chalkboard-teacher"></i> Professores
                                    </a>
                                    
                                    <form method="POST" style="display: contents;" onsubmit="return confirm('⚠️ Tem certeza que deseja deletar esta disciplina? Esta ação não pode ser desfeita.');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $disciplina['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" data-bs-toggle="tooltip" title="Deletar" <?php echo ($disciplina['total_turmas'] > 0 || $disciplina['total_notas_lancadas'] > 0) ? 'disabled' : ''; ?>>
                                            <i class="fas fa-trash"></i> Deletar
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
    
    <!-- Modal de Detalhes da Disciplina -->
    <div class="modal fade" id="detailsModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 20px; overflow: hidden;">
                <div class="modal-header" style="background: linear-gradient(135deg, #1e3c72, #2a5298); color: white; border: none;">
                    <h5 class="modal-title">
                        <i class="fas fa-book me-2"></i>Detalhes da Disciplina
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="text-center mb-4">
                        <div class="user-avatar" style="width: 80px; height: 80px; font-size: 2rem; margin: 0 auto;">
                            <span id="detailIniciais">DI</span>
                        </div>
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-md-12">
                            <div class="detail-card">
                                <div class="detail-label">
                                    <i class="fas fa-tag me-1"></i> Nome da Disciplina
                                </div>
                                <div class="detail-value" id="detailNome">---</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="detail-card">
                                <div class="detail-label">
                                    <i class="fas fa-barcode me-1"></i> Código
                                </div>
                                <div class="detail-value" id="detailCodigo">---</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="detail-card">
                                <div class="detail-label">
                                    <i class="fas fa-clock me-1"></i> Carga Horária
                                </div>
                                <div class="detail-value" id="detailCarga">---</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="detail-card">
                                <div class="detail-label">
                                    <i class="fas fa-chalkboard me-1"></i> Turmas Vinculadas
                                </div>
                                <div class="detail-value" id="detailTurmas">---</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="detail-card">
                                <div class="detail-label">
                                    <i class="fas fa-user-tie me-1"></i> Professores
                                </div>
                                <div class="detail-value" id="detailProfessores">---</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="detail-card">
                                <div class="detail-label">
                                    <i class="fas fa-file-alt me-1"></i> Notas Lançadas
                                </div>
                                <div class="detail-value" id="detailNotas">---</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                    <button type="button" class="btn btn-primary" id="btnEditFromDetails">
                        <i class="fas fa-edit me-2"></i>Editar Disciplina
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Disciplina Modal (Create/Edit) com vinculação opcional de turma e professor -->
    <div class="modal fade" id="disciplinaModal" tabindex="-1" <?php if($edit_disciplina) echo 'data-show="true"'; ?>>
        <div class="modal-dialog">
            <div class="modal-content" style="border-radius: 20px; overflow: hidden;">
                <div class="modal-header" style="background: linear-gradient(135deg, #1e3c72, #2a5298); color: white; border: none;">
                    <h5 class="modal-title">
                        <i class="fas <?php echo $edit_disciplina ? 'fa-edit' : 'fa-plus-circle'; ?> me-2"></i>
                        <?php echo $edit_disciplina ? 'Editar Disciplina' : 'Nova Disciplina'; ?>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body p-4">
                        <input type="hidden" name="action" value="<?php echo $edit_disciplina ? 'edit' : 'create'; ?>">
                        <?php if($edit_disciplina): ?>
                        <input type="hidden" name="id" value="<?php echo $edit_disciplina['id']; ?>">
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label class="form-label">Nome da Disciplina *</label>
                            <input type="text" class="form-control" name="nome" 
                                   value="<?php echo $edit_disciplina ? htmlspecialchars($edit_disciplina['nome']) : ''; ?>" 
                                   placeholder="Ex: Matemática, Português, Física..." required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Código</label>
                            <input type="text" class="form-control" name="codigo" 
                                   value="<?php echo $edit_disciplina ? htmlspecialchars($edit_disciplina['codigo'] ?? '') : ''; ?>" 
                                   placeholder="Ex: MAT101, PORT202, FIS303...">
                            <small class="text-muted">Código único da disciplina (opcional)</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Carga Horária (horas)</label>
                            <input type="number" class="form-control" name="carga_horaria" 
                                   value="<?php echo $edit_disciplina ? htmlspecialchars($edit_disciplina['carga_horaria'] ?? '') : ''; ?>" 
                                   placeholder="Ex: 60, 80, 120..." min="0" step="1">
                            <small class="text-muted">Carga horária total da disciplina (opcional)</small>
                        </div>
                        
                        <!-- Seção de vinculação opcional -->
                        <div class="vinculacao-section">
                            <h6><i class="fas fa-link me-2"></i>Vincular a Turma e Professor (Opcional)</h6>
                            <div class="mb-3">
                                <label class="form-label">Turma</label>
                                <select class="form-select" name="turma_id" id="turmaSelect">
                                    <option value="">-- Selecione uma turma (opcional) --</option>
                                    <?php foreach ($turmas_list as $turma): ?>
                                        <option value="<?php echo $turma['id']; ?>">
                                            <?php echo htmlspecialchars($turma['nome']); ?> (<?php echo $turma['ano_letivo']; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3" id="professorAnoGroup" style="display: none;">
                                <div class="row">
                                    <div class="col-md-6">
                                        <label class="form-label">Professor</label>
                                        <select class="form-select" name="professor_id" id="professorSelect">
                                            <option value="">-- Selecione um professor --</option>
                                            <?php foreach ($professores_list as $prof): ?>
                                                <option value="<?php echo $prof['id']; ?>">
                                                    <?php echo htmlspecialchars($prof['nome']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Ano Letivo</label>
                                        <select class="form-select" name="ano_letivo" id="anoLetivoSelect">
                                            <option value="">-- Selecione o ano letivo --</option>
                                            <?php foreach ($anos_letivos as $ano): ?>
                                                <option value="<?php echo $ano; ?>"><?php echo $ano; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <small class="text-muted">Para vincular um professor, selecione também o ano letivo.</small>
                            </div>
                        </div>
                        
                        <?php if($edit_disciplina): ?>
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Estatísticas:</strong><br>
                            Turmas vinculadas: <?php echo $edit_disciplina['total_turmas'] ?? 0; ?><br>
                            Notas lançadas: <?php echo $edit_disciplina['total_notas_lancadas'] ?? 0; ?><br>
                            Professores: <?php echo $edit_disciplina['total_professores'] ?? 0; ?>
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
        
        // Variáveis de filtro
        let currentFilter = 'all';
        
        function filterByType(type) {
            currentFilter = type;
            
            // Atualizar classes dos botões
            document.querySelectorAll('.filter-pill').forEach(pill => {
                pill.classList.remove('active');
            });
            document.querySelector(`.filter-pill[data-filter="${type}"]`).classList.add('active');
            
            // Mostrar/esconder containers de filtro
            document.getElementById('filterCargaContainer').style.display = type === 'carga' ? 'block' : 'none';
            document.getElementById('filterTurmasContainer').style.display = type === 'turmas' ? 'block' : 'none';
            
            // Limpar valores dos filtros
            if (type !== 'carga') document.getElementById('filterCarga').value = '';
            if (type !== 'turmas') document.getElementById('filterTurmas').value = '';
            
            aplicarFiltros();
        }
        
        function filterByCarga() { filterByType('carga'); }
        function filterByTurmas() { filterByType('turmas'); }
        
        function aplicarFiltros() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase().trim();
            const cargaFilter = document.getElementById('filterCarga').value;
            const turmasFilter = document.getElementById('filterTurmas').value;
            const rows = document.querySelectorAll('#disciplinasTable tbody tr');
            let visibleCount = 0;
            let comTurmasCount = 0;
            
            rows.forEach(row => {
                const nome = row.getAttribute('data-nome') || '';
                const codigo = row.getAttribute('data-codigo') || '';
                const carga = row.getAttribute('data-carga');
                const turmas = parseInt(row.getAttribute('data-turmas')) || 0;
                
                let match = true;
                
                // Filtro de pesquisa
                if (searchTerm && !nome.includes(searchTerm) && !codigo.includes(searchTerm)) {
                    match = false;
                }
                
                // Filtro de carga horária
                if (match && currentFilter === 'carga') {
                    if (cargaFilter === 'sem' && carga) {
                        match = false;
                    } else if (cargaFilter !== 'sem' && cargaFilter !== '' && carga != cargaFilter) {
                        match = false;
                    }
                }
                
                // Filtro de turmas
                if (match && currentFilter === 'turmas') {
                    if (turmasFilter === 'com' && turmas === 0) {
                        match = false;
                    } else if (turmasFilter === 'sem' && turmas > 0) {
                        match = false;
                    }
                }
                
                if (match) {
                    row.style.display = '';
                    visibleCount++;
                    if (turmas > 0) comTurmasCount++;
                } else {
                    row.style.display = 'none';
                }
            });
            
            document.getElementById('totalCount').innerHTML = visibleCount;
            document.getElementById('totalComTurmas').innerHTML = comTurmasCount;
        }
        
        // Event listeners
        document.getElementById('searchInput').addEventListener('keyup', aplicarFiltros);
        document.getElementById('filterCarga').addEventListener('change', aplicarFiltros);
        document.getElementById('filterTurmas').addEventListener('change', aplicarFiltros);
        
        // Função para visualizar detalhes
        let currentDisciplinaId = null;
        
        function viewDetails(id, nome, codigo, carga, turmas, professores, notas) {
            currentDisciplinaId = id;
            
            // Atualizar avatar
            const iniciais = nome.substring(0, 2).toUpperCase();
            document.getElementById('detailIniciais').textContent = iniciais;
            
            // Atualizar dados
            document.getElementById('detailNome').textContent = nome;
            document.getElementById('detailCodigo').textContent = codigo || 'Não definido';
            document.getElementById('detailCarga').textContent = carga;
            document.getElementById('detailTurmas').textContent = turmas;
            document.getElementById('detailProfessores').textContent = professores;
            document.getElementById('detailNotas').textContent = notas;
            
            // Botão de editar
            document.getElementById('btnEditFromDetails').onclick = function() {
                window.location.href = '?edit=' + currentDisciplinaId;
            };
            
            // Mostrar modal
            new bootstrap.Modal(document.getElementById('detailsModal')).show();
        }
        
        // Tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
        
        // Mostrar/ocultar campos de professor/ano letivo conforme seleção de turma
        const turmaSelect = document.getElementById('turmaSelect');
        const professorAnoGroup = document.getElementById('professorAnoGroup');
        
        function toggleProfessorAno() {
            if (turmaSelect.value) {
                professorAnoGroup.style.display = 'block';
            } else {
                professorAnoGroup.style.display = 'none';
                // Limpar selects de professor e ano letivo
                document.getElementById('professorSelect').value = '';
                document.getElementById('anoLetivoSelect').value = '';
            }
        }
        
        turmaSelect.addEventListener('change', toggleProfessorAno);
        toggleProfessorAno(); // chamada inicial
        
        // Mostrar modal de edição se existir
        <?php if($edit_disciplina): ?>
        var disciplinaModal = new bootstrap.Modal(document.getElementById('disciplinaModal'));
        disciplinaModal.show();
        <?php endif; ?>
        a
        // Animações de entrada
        document.addEventListener('DOMContentLoaded', function() {
            const rows = document.querySelectorAll('#disciplinasTable tbody tr');
            rows.forEach((row, index) => {
                row.style.opacity = '0';
                row.style.transform = 'translateY(10px)';
                setTimeout(() => {
                    row.style.transition = 'all 0.3s ease';
                    row.style.opacity = '1';
                    row.style.transform = 'translateY(0)';
                }, index * 50);
            });
            
            // Calcular estatística de disciplinas com turmas
            let comTurmas = 0;
            rows.forEach(row => {
                const turmas = parseInt(row.getAttribute('data-turmas')) || 0;
                if (turmas > 0) comTurmas++;
            });
            document.getElementById('totalComTurmas').innerHTML = comTurmas;
        });
    </script>
</body>
</html>