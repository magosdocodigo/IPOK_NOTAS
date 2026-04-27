<?php
// admin/enturmacoes.php
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
        $usuario_id = $_SESSION['user_id'];
        
        switch ($_POST['action']) {
            case 'create':
                $aluno_id = (int)$_POST['aluno_id'];
                $turma_id = (int)$_POST['turma_id'];
                
                $check = $db->query("SELECT id FROM enturmacoes 
                                    WHERE aluno_id = $aluno_id AND turma_id = $turma_id");
                
                if ($check && $check->num_rows > 0) {
                    $error = "Este aluno já está enturmado nesta turma!";
                } else {
                    $query = "INSERT INTO enturmacoes (aluno_id, turma_id) 
                              VALUES ($aluno_id, $turma_id)";
                    
                    if ($db->query($query)) {
                        $enturmacao_id = $db->insert_id;
                        $db->query("INSERT INTO logs_auditoria (usuario_id, acao, tabela, registro_id, ip) 
                                   VALUES ($usuario_id, 'CRIAR_ENTURMACAO', 'enturmacoes', $enturmacao_id, '$ip')");
                        $message = "Aluno enturmado com sucesso!";
                    } else {
                        $error = "Erro ao enturmar aluno: " . $db->error;
                    }
                }
                break;
                
            case 'delete':
                $id = (int)$_POST['id'];
                
                $ent = $db->query("SELECT aluno_id, turma_id FROM enturmacoes WHERE id = $id")->fetch_assoc();
                if ($ent) {
                    $disciplinas = $db->query("SELECT disciplina_id FROM turma_disciplina WHERE turma_id = {$ent['turma_id']}");
                    $tem_notas = false;
                    while ($disc = $disciplinas->fetch_assoc()) {
                        $check = $db->query("SELECT id FROM notas 
                                            WHERE aluno_id = {$ent['aluno_id']} 
                                            AND disciplina_id = {$disc['disciplina_id']}");
                        if ($check && $check->num_rows > 0) {
                            $tem_notas = true;
                            break;
                        }
                    }
                    
                    if ($tem_notas) {
                        $error = "Não é possível remover esta enturmação pois já existem notas lançadas para este aluno!";
                        break;
                    }
                }
                
                $db->query("INSERT INTO logs_auditoria (usuario_id, acao, tabela, registro_id, ip) 
                           VALUES ($usuario_id, 'DELETAR_ENTURMACAO', 'enturmacoes', $id, '$ip')");
                $query = "DELETE FROM enturmacoes WHERE id = $id";
                if ($db->query($query)) {
                    $message = "Enturmação removida com sucesso!";
                } else {
                    $error = "Erro ao remover enturmação: " . $db->error;
                }
                break;
                
            case 'batch_enturmar':
                $turma_id = (int)$_POST['turma_id'];
                $alunos = $_POST['alunos'] ?? [];
                
                if (empty($alunos)) {
                    $error = "Selecione pelo menos um aluno para enturmar.";
                    break;
                }
                
                $sucessos = 0;
                $erros = 0;
                
                foreach ($alunos as $aluno_id) {
                    $aluno_id = (int)$aluno_id;
                    $check = $db->query("SELECT id FROM enturmacoes WHERE aluno_id = $aluno_id AND turma_id = $turma_id");
                    
                    if ($check && $check->num_rows == 0) {
                        $query = "INSERT INTO enturmacoes (aluno_id, turma_id) VALUES ($aluno_id, $turma_id)";
                        if ($db->query($query)) {
                            $sucessos++;
                        } else {
                            $erros++;
                        }
                    } else {
                        $erros++;
                    }
                }
                
                if ($sucessos > 0) {
                    $db->query("INSERT INTO logs_auditoria (usuario_id, acao, tabela, registro_id, ip, dados_novos) 
                               VALUES ($usuario_id, 'BATCH_ENTURMAR', 'enturmacoes', 0, '$ip', '{\"turma\":$turma_id,\"alunos\":" . json_encode($alunos) . "}')");
                    $message = "Operação concluída: $sucessos aluno(s) enturmado(s) com sucesso" . ($erros > 0 ? ", $erros falha(s)." : ".");
                } else {
                    $error = "Nenhum aluno foi enturmado. Verifique se os alunos já não estão na turma.";
                }
                break;
        }
    }
}

// Buscar todas as turmas
$query = "SELECT t.*, 
          COUNT(DISTINCT e.aluno_id) as total_alunos
          FROM turmas t
          LEFT JOIN enturmacoes e ON t.id = e.turma_id
          GROUP BY t.id
          ORDER BY t.ano_letivo DESC, t.nome ASC";
$turmas = $db->query($query);

// Buscar alunos não enturmados
$query = "SELECT a.id, u.nome, a.numero_matricula
          FROM alunos a
          INNER JOIN usuarios u ON a.usuario_id = u.id
          WHERE u.ativo = 1
          ORDER BY u.nome ASC";
$alunos_disponiveis = $db->query($query);

// Buscar enturmações existentes
$query = "SELECT e.*, 
          u.nome as aluno_nome,
          a.numero_matricula,
          t.nome as turma_nome,
          t.ano_letivo,
          t.curso
          FROM enturmacoes e
          INNER JOIN alunos a ON e.aluno_id = a.id
          INNER JOIN usuarios u ON a.usuario_id = u.id
          INNER JOIN turmas t ON e.turma_id = t.id
          ORDER BY e.data_enturmacao DESC";
$enturmacoes = $db->query($query);

// Estatísticas
$stats_query = "SELECT 
                COUNT(DISTINCT aluno_id) as total_alunos_enturmados,
                COUNT(DISTINCT turma_id) as total_turmas_ocupadas,
                COUNT(*) as total_enturmacoes,
                (SELECT AVG(cnt) FROM (SELECT COUNT(*) as cnt FROM enturmacoes GROUP BY turma_id) as t) as media_alunos_por_turma
                FROM enturmacoes";
$stats_result = $db->query($stats_query);
$stats = $stats_result->fetch_assoc();

if (!$stats['total_enturmacoes']) {
    $stats = [
        'total_alunos_enturmados' => 0,
        'total_turmas_ocupadas' => 0,
        'total_enturmacoes' => 0,
        'media_alunos_por_turma' => 0
    ];
}

$page_title = "Gestão de Enturmações";

// Criar array de turmas para o select
$turmas_list = [];
mysqli_data_seek($turmas, 0);
while ($t = $turmas->fetch_assoc()) {
    $turmas_list[] = $t;
}
mysqli_data_seek($turmas, 0);
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IPOK - Gestão de Enturmações</title>
    <link rel="shortcut icon" href="../assets/img/logo.png" type="image/x-icon">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Select2 -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    
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
            white-space: nowrap;
        }
        
        .modern-table tbody tr {
            transition: all 0.3s ease;
            border-bottom: 1px solid #e9ecef;
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
        
        .badge-matricula {
            background: #e2e3e5;
            color: #495057;
        }
        
        .badge-turma {
            background: #d1fae5;
            color: #065f46;
        }
        
        .badge-ano {
            background: #cfe2ff;
            color: #084298;
        }
        
        .badge-curso {
            background: #fff3cd;
            color: #856404;
        }
        
        /* Turma Cards */
        .turma-card {
            background: white;
            border-radius: 12px;
            margin-bottom: 20px;
            border: 1px solid #e9ecef;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .turma-card:hover {
            box-shadow: 0 8px 24px rgba(0,0,0,.1);
            transform: translateY(-2px);
        }
        
        .turma-header {
            background: linear-gradient(135deg, #f8fafc, #ffffff);
            padding: 15px 20px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
        }
        
        .turma-header h5 {
            margin: 0;
            color: var(--primary-blue);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .turma-stats {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .turma-stat {
            background: #f8fafc;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
        }
        
        .turma-stat .number {
            font-weight: 700;
            color: var(--primary-blue);
        }
        
        .aluno-list {
            padding: 0 20px;
            max-height: 300px;
            overflow-y: auto;
        }
        
        .aluno-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .aluno-item:last-child {
            border-bottom: none;
        }
        
        .aluno-info {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            min-width: 100px;
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
            border: none;
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
        
        .btn-add, .btn-batch {
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 8px;
            font-size: .9rem;
            transition: all .3s;
        }
        
        .btn-add:hover, .btn-batch:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(30,60,114,.3);
            color: white;
        }
        
        .info-box {
            background: #e6f0fa;
            border-left: 4px solid var(--primary-blue);
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 25px;
        }
        
        .select-all {
            background: #f8fafc;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        
        /* Data badge */
        .data-badge {
            font-family: monospace;
            font-size: 0.8rem;
            background: #f8fafc;
            padding: 4px 8px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); }
            .main-content { margin-left: 0; }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
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
                <span>Atribuições</span>
            </a>
            <a href="enturmacoes.php" class="menu-item active">
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
                    <i class="fas fa-user-plus me-2"></i>Gestão de Enturmações
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
                    <i class="fas fa-list"></i> Todas Enturmações
                </button>
                <button class="filter-pill" data-filter="turma" onclick="filterByTurma()">
                    <i class="fas fa-chalkboard"></i> Por Turma
                </button>
                <button class="filter-pill" data-filter="ano" onclick="filterByAno()">
                    <i class="fas fa-calendar-alt"></i> Por Ano Letivo
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
                               placeholder="Aluno, matrícula, turma...">
                    </div>
                </div>
                
                <div class="col-md-3" id="filterTurmaContainer" style="display: none;">
                    <label class="form-label text-muted small mb-2">
                        <i class="fas fa-chalkboard me-1"></i>Turma
                    </label>
                    <select class="form-select" id="filterTurma">
                        <option value="">Todas as turmas</option>
                        <?php foreach ($turmas_list as $t): ?>
                            <option value="<?php echo htmlspecialchars($t['nome']); ?>">
                                <?php echo htmlspecialchars($t['nome']); ?> (<?php echo $t['ano_letivo']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2" id="filterAnoContainer" style="display: none;">
                    <label class="form-label text-muted small mb-2">
                        <i class="fas fa-calendar me-1"></i>Ano Letivo
                    </label>
                    <select class="form-select" id="filterAno">
                        <option value="">Todos os anos</option>
                        <?php 
                        $anos = [];
                        foreach ($turmas_list as $t) {
                            $anos[$t['ano_letivo']] = true;
                        }
                        krsort($anos);
                        foreach (array_keys($anos) as $ano): 
                        ?>
                            <option value="<?php echo $ano; ?>"><?php echo $ano; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <button class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#enturmacaoModal" style="border-radius: 12px; background: linear-gradient(135deg, #1e3c72, #2a5298);">
                        <i class="fas fa-plus me-2"></i>Nova Enturmação
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
        
        <!-- Info Box -->
        <div class="info-box">
            <ul class="mb-0 mt-2">
                <li>Apenas alunos enturmados podem receber notas</li>
                <li>Um aluno pode estar em apenas uma turma por ano letivo</li>
                <li>Não é possível remover enturmação se já houver notas lançadas</li>
            </ul>
        </div>
        
        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['total_enturmacoes']; ?></h3>
                    <p>Total de Enturmações</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['total_alunos_enturmados']; ?></h3>
                    <p>Alunos Enturmados</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-chalkboard"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['total_turmas_ocupadas']; ?></h3>
                    <p>Turmas com Alunos</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo round($stats['media_alunos_por_turma']); ?></h3>
                    <p>Média Alunos/Turma</p>
                </div>
            </div>
        </div>
        
        <!-- Turmas com Alunos (Visualização por Turma) -->
        <div class="table-container mb-4">
            <div class="table-header">
                <h5>
                    <i class="fas fa-chalkboard me-2"></i>Alunos por Turma
                    <span class="badge bg-primary ms-2"><?php echo count($turmas_list); ?> turmas</span>
                </h5>
                <button class="btn-batch" onclick="window.location.href='?batch=1'">
                    <i class="fas fa-layer-group me-2"></i>Enturmação em Massa
                </button>
            </div>
            
            <div class="row g-3 p-3">
                <?php foreach ($turmas_list as $turma): 
                    $alunos_query = "SELECT e.id as enturmacao_id, u.nome, a.numero_matricula, e.data_enturmacao
                                    FROM enturmacoes e
                                    INNER JOIN alunos a ON e.aluno_id = a.id
                                    INNER JOIN usuarios u ON a.usuario_id = u.id
                                    WHERE e.turma_id = {$turma['id']}
                                    ORDER BY u.nome ASC";
                    $alunos_turma = $db->query($alunos_query);
                ?>
                <div class="col-md-6">
                    <div class="turma-card">
                        <div class="turma-header" onclick="toggleAlunos(<?php echo $turma['id']; ?>)">
                            <h5>
                                <i class="fas fa-chalkboard"></i>
                                <?php echo htmlspecialchars($turma['nome']); ?>
                                <small class="text-muted ms-2"><?php echo $turma['ano_letivo']; ?></small>
                            </h5>
                            <div class="turma-stats">
                                <span class="turma-stat">
                                    <i class="fas fa-user-graduate me-1"></i>
                                    <span class="number"><?php echo $turma['total_alunos']; ?></span> alunos
                                </span>
                                <button class="btn btn-sm btn-outline-primary" onclick="event.stopPropagation(); window.location.href='?batch_turma=<?php echo $turma['id']; ?>'" title="Adicionar alunos">
                                    <i class="fas fa-plus"></i>
                                </button>
                                <i class="fas fa-chevron-down text-muted"></i>
                            </div>
                        </div>
                        
                        <div class="aluno-list" id="alunos-<?php echo $turma['id']; ?>" style="display: none;">
                            <?php if ($alunos_turma->num_rows > 0): ?>
                                <?php while ($aluno = $alunos_turma->fetch_assoc()): ?>
                                <div class="aluno-item">
                                    <div class="aluno-info">
                                        <span class="badge-status badge-matricula">
                                            <i class="fas fa-id-card me-1"></i>
                                            <?php echo htmlspecialchars($aluno['numero_matricula']); ?>
                                        </span>
                                        <span><?php echo htmlspecialchars($aluno['nome']); ?></span>
                                        <small class="text-muted">
                                            <i class="fas fa-calendar-alt"></i>
                                            <?php echo date('d/m/Y', strtotime($aluno['data_enturmacao'])); ?>
                                        </small>
                                    </div>
                                    <form method="POST" style="display: contents;" onsubmit="return confirm('⚠️ Tem certeza que deseja remover este aluno da turma?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $aluno['enturmacao_id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Remover">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="text-center py-4 text-muted">
                                    <i class="fas fa-user-slash me-2"></i>
                                    Nenhum aluno nesta turma
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Tabela de Enturmações - Organizada -->
        <div class="table-container">
            <div class="table-header">
                <h5>
                    <i class="fas fa-list me-2"></i>Todas as Enturmações
                    <span class="badge bg-primary ms-2" id="totalCount"><?php echo $enturmacoes->num_rows; ?></span>
                </h5>
            </div>
            
            <div class="table-responsive">
                <table class="modern-table" id="enturmacoesTable">
                    <thead>
                        <tr>
                            <th style="width: 70px;">#ID</th>
                            <th style="min-width: 180px;">Aluno</th>
                            <th style="width: 120px;">Matrícula</th>
                            <th style="min-width: 200px;">Turma</th>
                            <th style="width: 100px;">Ano Letivo</th>
                            <th style="min-width: 180px;">Curso</th>
                            <th style="width: 150px;">Data Enturmação</th>
                            <th style="width: 100px;">Ações</th>
                        </thead>
                    <tbody>
                        <?php 
                        mysqli_data_seek($enturmacoes, 0);
                        while ($ent = $enturmacoes->fetch_assoc()): 
                            $iniciais = substr($ent['aluno_nome'], 0, 2);
                            $nome_completo = $ent['aluno_nome'];
                            $partes_nome = explode(' ', $nome_completo);
                            $primeiro_nome = $partes_nome[0];
                            $ultimo_nome = end($partes_nome);
                        ?>
                        <tr data-id="<?php echo $ent['id']; ?>"
                            data-aluno="<?php echo strtolower($ent['aluno_nome']); ?>"
                            data-matricula="<?php echo strtolower($ent['numero_matricula']); ?>"
                            data-turma="<?php echo strtolower($ent['turma_nome']); ?>"
                            data-ano="<?php echo $ent['ano_letivo']; ?>">
                            <td class="align-middle text-center">
                                <span class="badge bg-light text-dark">#<?php echo $ent['id']; ?></span>
                            </td>
                            <td class="align-middle">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="user-avatar" style="width: 42px; height: 42px; font-size: 1rem; background: linear-gradient(135deg, #1e3c72, #2a5298);">
                                        <?php echo strtoupper($iniciais); ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold"><?php echo htmlspecialchars($ent['aluno_nome']); ?></div>
                                        <small class="text-muted">
                                            <i class="fas fa-user-graduate"></i> 
                                            <?php echo htmlspecialchars($primeiro_nome); ?>
                                        </small>
                                    </div>
                                </div>
                            </td>
                            <td class="align-middle">
                                <span class="badge-status badge-matricula">
                                    <i class="fas fa-id-card me-1"></i>
                                    <?php echo htmlspecialchars($ent['numero_matricula']); ?>
                                </span>
                            </td>
                            <td class="align-middle">
                                <div class="d-flex flex-column">
                                    <span class="badge-status badge-turma mb-1">
                                        <i class="fas fa-chalkboard me-1"></i>
                                        <?php echo htmlspecialchars($ent['turma_nome']); ?>
                                    </span>
                                </div>
                            </td>
                            <td class="align-middle text-center">
                                <span class="badge-status badge-ano">
                                    <i class="fas fa-calendar me-1"></i>
                                    <?php echo $ent['ano_letivo']; ?>
                                </span>
                            </td>
                            <td class="align-middle">
                                <?php if ($ent['curso']): ?>
                                    <span class="badge-status badge-curso">
                                        <i class="fas fa-mortarboard me-1"></i>
                                        <?php echo htmlspecialchars($ent['curso']); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">---</span>
                                <?php endif; ?>
                            </td>
                            <td class="align-middle">
                                <span class="data-badge">
                                    <i class="fas fa-calendar-check text-success"></i>
                                    <?php echo date('d/m/Y', strtotime($ent['data_enturmacao'])); ?>
                                    <small class="text-muted"><?php echo date('H:i', strtotime($ent['data_enturmacao'])); ?></small>
                                </span>
                            </td>
                            <td class="align-middle">
                                <div class="action-buttons">
                                    <button class="btn btn-sm btn-info" onclick="viewDetails(<?php echo $ent['id']; ?>, '<?php echo addslashes($ent['aluno_nome']); ?>', '<?php echo addslashes($ent['numero_matricula']); ?>', '<?php echo addslashes($ent['turma_nome']); ?>', '<?php echo $ent['ano_letivo']; ?>', '<?php echo addslashes($ent['curso'] ?? ''); ?>', '<?php echo date('d/m/Y H:i', strtotime($ent['data_enturmacao'])); ?>')" data-bs-toggle="tooltip" title="Ver Detalhes">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    
                                    <form method="POST" style="display: contents;" onsubmit="return confirm('⚠️ Tem certeza que deseja remover esta enturmação?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $ent['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" data-bs-toggle="tooltip" title="Remover Enturmação">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Rodapé da tabela -->
            <div class="card-footer bg-white py-3 border-top">
                <div class="d-flex justify-content-between align-items-center">
                    <small class="text-muted">
                        <i class="fas fa-info-circle me-1"></i>
                        Total de <strong><?php echo $enturmacoes->num_rows; ?></strong> enturmações registadas
                    </small>
                    <small class="text-muted">
                        <i class="fas fa-calendar-alt me-1"></i>
                        Última atualização: <?php echo date('d/m/Y H:i'); ?>
                    </small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal de Detalhes da Enturmação (mantido igual) -->
    <div class="modal fade" id="detailsModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 20px; overflow: hidden;">
                <div class="modal-header" style="background: linear-gradient(135deg, #1e3c72, #2a5298); color: white; border: none;">
                    <h5 class="modal-title">
                        <i class="fas fa-user-plus me-2"></i>Detalhes da Enturmação
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="text-center mb-4">
                        <div class="user-avatar" style="width: 80px; height: 80px; font-size: 2rem; margin: 0 auto;">
                            <span id="detailIniciais">AL</span>
                        </div>
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-md-12">
                            <div class="detail-card">
                                <div class="detail-label">
                                    <i class="fas fa-user-graduate me-1"></i> Aluno
                                </div>
                                <div class="detail-value" id="detailAluno">---</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="detail-card">
                                <div class="detail-label">
                                    <i class="fas fa-id-card me-1"></i> Matrícula
                                </div>
                                <div class="detail-value" id="detailMatricula">---</div>
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
                                    <i class="fas fa-calendar me-1"></i> Ano Letivo
                                </div>
                                <div class="detail-value" id="detailAno">---</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="detail-card">
                                <div class="detail-label">
                                    <i class="fas fa-mortarboard me-1"></i> Curso
                                </div>
                                <div class="detail-value" id="detailCurso">---</div>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="detail-card">
                                <div class="detail-label">
                                    <i class="fas fa-calendar-plus me-1"></i> Data de Enturmação
                                </div>
                                <div class="detail-value" id="detailData">---</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Enturmacao Modal (Individual) - mantido igual -->
    <div class="modal fade" id="enturmacaoModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content" style="border-radius: 20px; overflow: hidden;">
                <div class="modal-header" style="background: linear-gradient(135deg, #1e3c72, #2a5298); color: white; border: none;">
                    <h5 class="modal-title">
                        <i class="fas fa-user-plus me-2"></i>Enturmar Aluno
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body p-4">
                        <input type="hidden" name="action" value="create">
                        
                        <div class="mb-3">
                            <label class="form-label">Aluno *</label>
                            <select class="form-select select2" name="aluno_id" required>
                                <option value="">-- Selecione um aluno --</option>
                                <?php 
                                mysqli_data_seek($alunos_disponiveis, 0);
                                while ($aluno = $alunos_disponiveis->fetch_assoc()): 
                                ?>
                                <option value="<?php echo $aluno['id']; ?>">
                                    <?php echo htmlspecialchars($aluno['nome']); ?> 
                                    (<?php echo htmlspecialchars($aluno['numero_matricula']); ?>)
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Turma *</label>
                            <select class="form-select select2" name="turma_id" required>
                                <option value="">-- Selecione uma turma --</option>
                                <?php foreach ($turmas_list as $turma): ?>
                                <option value="<?php echo $turma['id']; ?>">
                                    <?php echo htmlspecialchars($turma['nome']); ?> - <?php echo $turma['ano_letivo']; ?>
                                    (<?php echo $turma['total_alunos']; ?> alunos)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Nota:</strong> O aluno será enturmado imediatamente e poderá receber notas.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Enturmar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Batch Enturmar Modal - mantido igual -->
    <?php if (isset($_GET['batch_turma'])): 
        $turma_id = (int)$_GET['batch_turma'];
        $turma_batch = null;
        foreach ($turmas_list as $t) {
            if ($t['id'] == $turma_id) {
                $turma_batch = $t;
                break;
            }
        }
        $alunos_na_turma = [];
        $result = $db->query("SELECT aluno_id FROM enturmacoes WHERE turma_id = $turma_id");
        while ($row = $result->fetch_assoc()) {
            $alunos_na_turma[] = $row['aluno_id'];
        }
    ?>
    <div class="modal fade" id="batchTurmaModal" tabindex="-1" data-show="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" style="border-radius: 20px; overflow: hidden;">
                <div class="modal-header" style="background: linear-gradient(135deg, #1e3c72, #2a5298); color: white; border: none;">
                    <h5 class="modal-title">
                        <i class="fas fa-layer-group me-2"></i>
                        Enturmar Alunos em Massa - <?php echo htmlspecialchars($turma_batch['nome']); ?>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body p-4">
                        <input type="hidden" name="action" value="batch_enturmar">
                        <input type="hidden" name="turma_id" value="<?php echo $turma_id; ?>">
                        
                        <div class="alert alert-info mb-3">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Turma:</strong> <?php echo htmlspecialchars($turma_batch['nome']); ?> - 
                            Ano: <?php echo $turma_batch['ano_letivo']; ?><br>
                            <strong>Curso:</strong> <?php echo htmlspecialchars($turma_batch['curso'] ?? 'Não definido'); ?>
                        </div>
                        
                        <div class="select-all mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="selectAll">
                                <label class="form-check-label" for="selectAll">
                                    <strong>Selecionar todos os alunos</strong>
                                </label>
                            </div>
                        </div>
                        
                        <div class="row">
                            <?php 
                            mysqli_data_seek($alunos_disponiveis, 0);
                            while ($aluno = $alunos_disponiveis->fetch_assoc()): 
                                $ja_enturmado = in_array($aluno['id'], $alunos_na_turma);
                            ?>
                            <div class="col-md-6 mb-2">
                                <div class="form-check">
                                    <input class="form-check-input aluno-checkbox" 
                                           type="checkbox" 
                                           name="alunos[]" 
                                           value="<?php echo $aluno['id']; ?>"
                                           id="aluno_<?php echo $aluno['id']; ?>"
                                           <?php echo $ja_enturmado ? 'disabled' : ''; ?>>
                                    <label class="form-check-label <?php echo $ja_enturmado ? 'text-muted' : ''; ?>" 
                                           for="aluno_<?php echo $aluno['id']; ?>">
                                        <?php echo htmlspecialchars($aluno['nome']); ?>
                                        <small class="text-muted">
                                            (<?php echo htmlspecialchars($aluno['numero_matricula']); ?>)
                                        </small>
                                        <?php if ($ja_enturmado): ?>
                                        <span class="badge bg-secondary ms-2">Já enturmado</span>
                                        <?php endif; ?>
                                    </label>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Enturmar Selecionados
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
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
            
            document.querySelectorAll('.filter-pill').forEach(pill => {
                pill.classList.remove('active');
            });
            document.querySelector(`.filter-pill[data-filter="${type}"]`).classList.add('active');
            
            document.getElementById('filterTurmaContainer').style.display = type === 'turma' ? 'block' : 'none';
            document.getElementById('filterAnoContainer').style.display = type === 'ano' ? 'block' : 'none';
            
            if (type !== 'turma') document.getElementById('filterTurma').value = '';
            if (type !== 'ano') document.getElementById('filterAno').value = '';
            
            aplicarFiltros();
        }
        
        function filterByTurma() { filterByType('turma'); }
        function filterByAno() { filterByType('ano'); }
        
        function aplicarFiltros() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase().trim();
            const turmaFilter = document.getElementById('filterTurma').value;
            const anoFilter = document.getElementById('filterAno').value;
            const rows = document.querySelectorAll('#enturmacoesTable tbody tr');
            let visibleCount = 0;
            
            rows.forEach(row => {
                const aluno = row.getAttribute('data-aluno') || '';
                const matricula = row.getAttribute('data-matricula') || '';
                const turma = row.getAttribute('data-turma') || '';
                const ano = row.getAttribute('data-ano') || '';
                
                let match = true;
                
                // Filtro de pesquisa
                if (searchTerm && !aluno.includes(searchTerm) && !matricula.includes(searchTerm) && !turma.includes(searchTerm)) {
                    match = false;
                }
                
                // Filtro por turma
                if (match && currentFilter === 'turma' && turmaFilter && turma !== turmaFilter.toLowerCase()) {
                    match = false;
                }
                
                // Filtro por ano
                if (match && currentFilter === 'ano' && anoFilter && ano !== anoFilter) {
                    match = false;
                }
                
                if (match) {
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
        document.getElementById('filterTurma').addEventListener('change', aplicarFiltros);
        document.getElementById('filterAno').addEventListener('change', aplicarFiltros);
        
        // Toggle alunos na turma
        function toggleAlunos(turmaId) {
            const element = document.getElementById('alunos-' + turmaId);
            if (element.style.display === 'none') {
                element.style.display = 'block';
            } else {
                element.style.display = 'none';
            }
        }
        
        // Função para visualizar detalhes
        function viewDetails(id, aluno, matricula, turma, ano, curso, data) {
            const iniciais = aluno.substring(0, 2).toUpperCase();
            document.getElementById('detailIniciais').textContent = iniciais;
            document.getElementById('detailAluno').textContent = aluno;
            document.getElementById('detailMatricula').textContent = matricula;
            document.getElementById('detailTurma').textContent = turma;
            document.getElementById('detailAno').textContent = ano;
            document.getElementById('detailCurso').textContent = curso || 'Não definido';
            document.getElementById('detailData').textContent = data;
            
            new bootstrap.Modal(document.getElementById('detailsModal')).show();
        }
        
        // Select2 initialization
        $(document).ready(function() {
            $('.select2').select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: '-- Selecione --',
                allowClear: true,
                dropdownParent: $('#enturmacaoModal')
            });
            
            // Select All checkbox
            $('#selectAll').on('change', function() {
                $('.aluno-checkbox:not(:disabled)').prop('checked', $(this).prop('checked'));
            });
            
            // Mostrar modais
            <?php if (isset($_GET['batch_turma'])): ?>
            var batchModal = new bootstrap.Modal(document.getElementById('batchTurmaModal'));
            batchModal.show();
            <?php endif; ?>
        });
        
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
        
        document.addEventListener('DOMContentLoaded', function() {
            const rows = document.querySelectorAll('#enturmacoesTable tbody tr');
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
    </script>
</body>
</html>