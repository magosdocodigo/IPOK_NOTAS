<?php
// admin/turmas.php
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
                $ano_letivo = (int)$_POST['ano_letivo'];
                $curso = mysqli_real_escape_string($db, $_POST['curso']);
                
                if ($_POST['action'] === 'create') {
                    // Verificar se já existe turma com mesmo nome e ano letivo
                    $check = $db->query("SELECT id FROM turmas WHERE nome = '$nome' AND ano_letivo = $ano_letivo");
                    if ($check && $check->num_rows > 0) {
                        $error = "Já existe uma turma com este nome no ano letivo $ano_letivo!";
                    } else {
                        $query = "INSERT INTO turmas (nome, ano_letivo, curso) VALUES ('$nome', $ano_letivo, '$curso')";
                        
                        if ($db->query($query)) {
                            $turma_id = $db->insert_id;
                            
                            // Log de auditoria
                            $db->query("INSERT INTO logs_auditoria (usuario_id, acao, tabela, registro_id, ip) 
                                       VALUES ({$_SESSION['user_id']}, 'CRIAR_TURMA', 'turmas', $turma_id, '$ip')");
                            
                            $message = "Turma criada com sucesso!";
                        } else {
                            $error = "Erro ao criar turma: " . $db->error;
                        }
                    }
                } else {
                    // Editar turma
                    $id = (int)$_POST['id'];
                    
                    // Verificar se já existe outra turma com mesmo nome e ano letivo
                    $check = $db->query("SELECT id FROM turmas WHERE nome = '$nome' AND ano_letivo = $ano_letivo AND id != $id");
                    if ($check && $check->num_rows > 0) {
                        $error = "Já existe outra turma com este nome no ano letivo $ano_letivo!";
                    } else {
                        $query = "UPDATE turmas SET 
                                  nome = '$nome',
                                  ano_letivo = $ano_letivo,
                                  curso = '$curso'
                                  WHERE id = $id";
                        
                        if ($db->query($query)) {
                            // Log de auditoria
                            $db->query("INSERT INTO logs_auditoria (usuario_id, acao, tabela, registro_id, ip) 
                                       VALUES ({$_SESSION['user_id']}, 'EDITAR_TURMA', 'turmas', $id, '$ip')");
                            
                            $message = "Turma atualizada com sucesso!";
                        } else {
                            $error = "Erro ao atualizar turma: " . $db->error;
                        }
                    }
                }
                break;
                
            case 'delete':
                $id = (int)$_POST['id'];
                
                // Verificar se existem enturmações ou atribuições vinculadas
                $check = $db->query("SELECT COUNT(*) as total FROM enturmacoes WHERE turma_id = $id");
                $enturmacoes = $check->fetch_assoc()['total'];
                
                $check = $db->query("SELECT td.id FROM turma_disciplina td WHERE td.turma_id = $id");
                $disciplinas_vinculadas = $check->num_rows;
                
                if ($enturmacoes > 0 || $disciplinas_vinculadas > 0) {
                    $error = "Não é possível excluir esta turma pois existem registos vinculados (alunos ou disciplinas).";
                } else {
                    // Log antes de deletar
                    $db->query("INSERT INTO logs_auditoria (usuario_id, acao, tabela, registro_id, ip) 
                               VALUES ({$_SESSION['user_id']}, 'DELETAR_TURMA', 'turmas', $id, '$ip')");
                    
                    $query = "DELETE FROM turmas WHERE id = $id";
                    if ($db->query($query)) {
                        $message = "Turma deletada com sucesso!";
                    } else {
                        $error = "Erro ao deletar turma: " . $db->error;
                    }
                }
                break;
        }
    }
}

// Buscar turmas com estatísticas
$query = "SELECT t.*,
          COUNT(DISTINCT e.aluno_id) as total_alunos,
          COUNT(DISTINCT td.disciplina_id) as total_disciplinas
          FROM turmas t
          LEFT JOIN enturmacoes e ON t.id = e.turma_id
          LEFT JOIN turma_disciplina td ON t.id = td.turma_id
          GROUP BY t.id
          ORDER BY t.ano_letivo DESC, t.nome ASC";
$turmas = $db->query($query);

// Buscar dados para edição
$edit_turma = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $query = "SELECT * FROM turmas WHERE id = $id";
    $result = $db->query($query);
    $edit_turma = $result->fetch_assoc();
    
    // Adicionar estatísticas para edição
    if ($edit_turma) {
        $stats = $db->query("SELECT 
                            (SELECT COUNT(*) FROM enturmacoes WHERE turma_id = $id) as alunos,
                            (SELECT COUNT(*) FROM turma_disciplina WHERE turma_id = $id) as disciplinas");
        $stats_result = $stats->fetch_assoc();
        $edit_turma['total_alunos'] = $stats_result['alunos'];
        $edit_turma['total_disciplinas'] = $stats_result['disciplinas'];
    }
}

// Anos letivos disponíveis
$ano_atual = date('Y');
$anos_letivos = range($ano_atual - 2, $ano_atual + 2);

// Buscar cursos únicos para filtro
$cursos = [];
$cursos_query = $db->query("SELECT DISTINCT curso FROM turmas WHERE curso IS NOT NULL AND curso != '' ORDER BY curso");
while ($curso = $cursos_query->fetch_assoc()) {
    $cursos[] = $curso['curso'];
}

$page_title = "Gestão de Turmas";
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IPOK - Gestão de Turmas</title>
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
        
        /* Stats Cards */
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
        
        .badge-ano {
            background: #cfe2ff;
            color: #084298;
        }
        
        .badge-alunos {
            background: #d1fae5;
            color: #065f46;
        }
        
        .badge-disciplinas {
            background: #fff3cd;
            color: #856404;
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
            <a href="turmas.php" class="menu-item active">
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
                    <i class="fas fa-chalkboard me-2"></i>Gestão de Turmas
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
                    <i class="fas fa-chalkboard"></i> Todas Turmas
                </button>
                <button class="filter-pill" data-filter="ano" onclick="filterByAno()">
                    <i class="fas fa-calendar-alt"></i> Por Ano Letivo
                </button>
                <button class="filter-pill" data-filter="curso" onclick="filterByCurso()">
                    <i class="fas fa-graduation-cap"></i> Por Curso
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
                               placeholder="Nome da turma, curso...">
                    </div>
                </div>
                
                <div class="col-md-3" id="filterAnoContainer" style="display: none;">
                    <label class="form-label text-muted small mb-2">
                        <i class="fas fa-calendar me-1"></i>Ano Letivo
                    </label>
                    <select class="form-select" id="filterAno">
                        <option value="">Todos os anos</option>
                        <?php foreach ($anos_letivos as $ano): ?>
                            <option value="<?php echo $ano; ?>"><?php echo $ano; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3" id="filterCursoContainer" style="display: none;">
                    <label class="form-label text-muted small mb-2">
                        <i class="fas fa-graduation-cap me-1"></i>Curso
                    </label>
                    <select class="form-select" id="filterCurso">
                        <option value="">Todos os cursos</option>
                        <?php foreach ($cursos as $curso): ?>
                            <option value="<?php echo htmlspecialchars($curso); ?>">
                                <?php echo htmlspecialchars($curso); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <button class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#turmaModal" style="border-radius: 12px; background: linear-gradient(135deg, #1e3c72, #2a5298);">
                        <i class="fas fa-plus me-2"></i>Nova Turma
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
            <?php 
            // Recalcular totais
            $total_turmas = 0;
            $total_alunos = 0;
            $total_disciplinas = 0;
            mysqli_data_seek($turmas, 0);
            while ($t = $turmas->fetch_assoc()) {
                $total_turmas++;
                $total_alunos += $t['total_alunos'];
                $total_disciplinas += $t['total_disciplinas'];
            }
            mysqli_data_seek($turmas, 0);
            ?>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-chalkboard"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $total_turmas; ?></h3>
                    <p>Total de Turmas</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $total_alunos; ?></h3>
                    <p>Alunos Enturmados</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-book"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $total_disciplinas; ?></h3>
                    <p>Disciplinas Vinculadas</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo date('Y'); ?></h3>
                    <p>Ano Letivo Corrente</p>
                </div>
            </div>
        </div>
        
        <!-- Turmas Table -->
        <div class="table-container">
            <div class="table-header">
                <h5>
                    <i class="fas fa-list me-2"></i>Turmas Cadastradas
                    <span class="badge bg-primary ms-2" id="totalCount"><?php echo $total_turmas; ?></span>
                </h5>
            </div>
            
            <div class="table-responsive">
                <table class="modern-table" id="turmasTable">
                    <thead>
                        <tr>
                            <th style="width: 60px;">#ID</th>
                            <th>Turma</th>
                            <th>Ano Letivo</th>
                            <th>Curso</th>
                            <th>Alunos</th>
                            <th>Disciplinas</th>
                            <th style="width: 280px;">Ações</th>
                        </thead>
                    <tbody>
                        <?php while ($turma = $turmas->fetch_assoc()): 
                            $iniciais = substr($turma['nome'], 0, 2);
                        ?>
                        <tr data-id="<?php echo $turma['id']; ?>"
                            data-ano="<?php echo $turma['ano_letivo']; ?>"
                            data-curso="<?php echo htmlspecialchars($turma['curso'] ?? ''); ?>"
                            data-nome="<?php echo strtolower($turma['nome']); ?>">
                            <td>
                                <span class="badge bg-light text-dark">#<?php echo $turma['id']; ?></span>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <div class="user-avatar" style="width: 40px; height: 40px; font-size: 1rem;">
                                        <?php echo strtoupper($iniciais); ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold"><?php echo htmlspecialchars($turma['nome']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge-status badge-ano">
                                    <i class="fas fa-calendar me-1"></i>
                                    <?php echo $turma['ano_letivo']; ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($turma['curso']): ?>
                                    <span class="badge bg-light text-dark">
                                        <i class="fas fa-graduation-cap me-1"></i>
                                        <?php echo htmlspecialchars($turma['curso']); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">---</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge-status badge-alunos">
                                    <i class="fas fa-users me-1"></i>
                                    <?php echo (int)$turma['total_alunos']; ?> alunos
                                </span>
                            </td>
                            <td>
                                <span class="badge-status badge-disciplinas">
                                    <i class="fas fa-book me-1"></i>
                                    <?php echo (int)$turma['total_disciplinas']; ?> disciplinas
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn btn-sm btn-info" onclick="viewDetails(<?php echo $turma['id']; ?>, '<?php echo addslashes($turma['nome']); ?>', '<?php echo $turma['ano_letivo']; ?>', '<?php echo addslashes($turma['curso'] ?? ''); ?>', '<?php echo (int)$turma['total_alunos']; ?>', '<?php echo (int)$turma['total_disciplinas']; ?>')" data-bs-toggle="tooltip" title="Ver Detalhes">
                                        <i class="fas fa-eye"></i> Detalhes
                                    </button>
                                    
                                    <a href="?edit=<?php echo $turma['id']; ?>" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" title="Editar">
                                        <i class="fas fa-edit"></i> Editar
                                    </a>
                                    
                                    <a href="turma_disciplinas.php?turma_id=<?php echo $turma['id']; ?>" class="btn btn-sm btn-success" data-bs-toggle="tooltip" title="Vincular Disciplinas">
                                        <i class="fas fa-book-open"></i> Vincular
                                    </a>
                                    
                                    <a href="enturmacoes.php?turma_id=<?php echo $turma['id']; ?>" class="btn btn-sm btn-warning" data-bs-toggle="tooltip" title="Ver Alunos">
                                        <i class="fas fa-users"></i> Alunos
                                    </a>
                                    
                                    <form method="POST" style="display: contents;" onsubmit="return confirm('⚠️ Tem certeza que deseja deletar esta turma? Esta ação não pode ser desfeita.');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $turma['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" data-bs-toggle="tooltip" title="Deletar" <?php echo ($turma['total_alunos'] > 0 || $turma['total_disciplinas'] > 0) ? 'disabled' : ''; ?>>
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
    
    <!-- Modal de Detalhes da Turma -->
    <div class="modal fade" id="detailsModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 20px; overflow: hidden;">
                <div class="modal-header" style="background: linear-gradient(135deg, #1e3c72, #2a5298); color: white; border: none;">
                    <h5 class="modal-title">
                        <i class="fas fa-chalkboard me-2"></i>Detalhes da Turma
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="text-center mb-4">
                        <div class="user-avatar" style="width: 80px; height: 80px; font-size: 2rem; margin: 0 auto;">
                            <span id="detailIniciais">TD</span>
                        </div>
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="detail-card">
                                <div class="detail-label">
                                    <i class="fas fa-tag me-1"></i> Nome da Turma
                                </div>
                                <div class="detail-value" id="detailNome">---</div>
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
                        <div class="col-md-12">
                            <div class="detail-card">
                                <div class="detail-label">
                                    <i class="fas fa-graduation-cap me-1"></i> Curso
                                </div>
                                <div class="detail-value" id="detailCurso">---</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="detail-card">
                                <div class="detail-label">
                                    <i class="fas fa-users me-1"></i> Total de Alunos
                                </div>
                                <div class="detail-value" id="detailAlunos">---</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="detail-card">
                                <div class="detail-label">
                                    <i class="fas fa-book me-1"></i> Disciplinas Vinculadas
                                </div>
                                <div class="detail-value" id="detailDisciplinas">---</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                    <button type="button" class="btn btn-primary" id="btnEditFromDetails">
                        <i class="fas fa-edit me-2"></i>Editar Turma
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Turma Modal (Create/Edit) -->
    <div class="modal fade" id="turmaModal" tabindex="-1" <?php if($edit_turma) echo 'data-show="true"'; ?>>
        <div class="modal-dialog">
            <div class="modal-content" style="border-radius: 20px; overflow: hidden;">
                <div class="modal-header" style="background: linear-gradient(135deg, #1e3c72, #2a5298); color: white; border: none;">
                    <h5 class="modal-title">
                        <i class="fas <?php echo $edit_turma ? 'fa-edit' : 'fa-plus-circle'; ?> me-2"></i>
                        <?php echo $edit_turma ? 'Editar Turma' : 'Nova Turma'; ?>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body p-4">
                        <input type="hidden" name="action" value="<?php echo $edit_turma ? 'edit' : 'create'; ?>">
                        <?php if($edit_turma): ?>
                        <input type="hidden" name="id" value="<?php echo $edit_turma['id']; ?>">
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label class="form-label">Nome da Turma *</label>
                            <input type="text" class="form-control" name="nome" 
                                   value="<?php echo $edit_turma ? htmlspecialchars($edit_turma['nome']) : ''; ?>" 
                                   placeholder="Ex: 12ª Classe A" required>
                            <small class="text-muted">Ex: 10ª Classe A, 11ª Classe B, etc.</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Ano Letivo *</label>
                            <select class="form-select" name="ano_letivo" required>
                                <option value="">-- Selecione --</option>
                                <?php foreach ($anos_letivos as $ano): ?>
                                <option value="<?php echo $ano; ?>" 
                                    <?php echo ($edit_turma && $edit_turma['ano_letivo'] == $ano) ? 'selected' : ''; ?>>
                                    <?php echo $ano; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Curso</label>
                            <input type="text" class="form-control" name="curso" 
                                   value="<?php echo $edit_turma ? htmlspecialchars($edit_turma['curso'] ?? '') : ''; ?>" 
                                   placeholder="Ex: Ciências, Letras, etc.">
                            <small class="text-muted">Opcional</small>
                        </div>
                        
                        <?php if($edit_turma): ?>
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Estatísticas:</strong><br>
                            Alunos: <?php echo $edit_turma['total_alunos'] ?? 0; ?> |
                            Disciplinas: <?php echo $edit_turma['total_disciplinas'] ?? 0; ?>
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
            document.getElementById('filterAnoContainer').style.display = type === 'ano' ? 'block' : 'none';
            document.getElementById('filterCursoContainer').style.display = type === 'curso' ? 'block' : 'none';
            
            // Limpar valores dos filtros
            if (type !== 'ano') document.getElementById('filterAno').value = '';
            if (type !== 'curso') document.getElementById('filterCurso').value = '';
            
            aplicarFiltros();
        }
        
        function filterByAno() { filterByType('ano'); }
        function filterByCurso() { filterByType('curso'); }
        
        function aplicarFiltros() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase().trim();
            const anoFilter = document.getElementById('filterAno').value;
            const cursoFilter = document.getElementById('filterCurso').value;
            const rows = document.querySelectorAll('#turmasTable tbody tr');
            let visibleCount = 0;
            
            rows.forEach(row => {
                const nome = row.getAttribute('data-nome') || '';
                const ano = row.getAttribute('data-ano') || '';
                const curso = row.getAttribute('data-curso') || '';
                
                let match = true;
                
                // Filtro de pesquisa
                if (searchTerm && !nome.includes(searchTerm) && !curso.toLowerCase().includes(searchTerm)) {
                    match = false;
                }
                
                // Filtro de ano
                if (match && currentFilter === 'ano' && anoFilter && ano !== anoFilter) {
                    match = false;
                }
                
                // Filtro de curso
                if (match && currentFilter === 'curso' && cursoFilter && !curso.toLowerCase().includes(cursoFilter.toLowerCase())) {
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
        document.getElementById('filterAno').addEventListener('change', aplicarFiltros);
        document.getElementById('filterCurso').addEventListener('change', aplicarFiltros);
        
        // Função para visualizar detalhes
        let currentTurmaId = null;
        
        function viewDetails(id, nome, ano, curso, alunos, disciplinas) {
            currentTurmaId = id;
            
            // Atualizar avatar
            const iniciais = nome.substring(0, 2).toUpperCase();
            document.getElementById('detailIniciais').textContent = iniciais;
            
            // Atualizar dados
            document.getElementById('detailNome').textContent = nome;
            document.getElementById('detailAno').textContent = ano;
            document.getElementById('detailCurso').textContent = curso || 'Não definido';
            document.getElementById('detailAlunos').textContent = alunos;
            document.getElementById('detailDisciplinas').textContent = disciplinas;
            
            // Botão de editar
            document.getElementById('btnEditFromDetails').onclick = function() {
                window.location.href = '?edit=' + currentTurmaId;
            };
            
            // Mostrar modal
            new bootstrap.Modal(document.getElementById('detailsModal')).show();
        }
        
        // Tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
        
        // Mostrar modal de edição se existir
        <?php if($edit_turma): ?>
        var turmaModal = new bootstrap.Modal(document.getElementById('turmaModal'));
        turmaModal.show();
        <?php endif; ?>
        
        // Animações de entrada
        document.addEventListener('DOMContentLoaded', function() {
            const rows = document.querySelectorAll('#turmasTable tbody tr');
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