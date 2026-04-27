<?php
// admin/atribuicoes.php
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
                $professor_id = (int)$_POST['professor_id'];
                $turma_id = (int)$_POST['turma_id'];
                $disciplina_id = (int)$_POST['disciplina_id'];
                $ano_letivo = (int)$_POST['ano_letivo'];
                
                // Buscar ou criar o registro em turma_disciplina
                $check_td = $db->query("SELECT id FROM turma_disciplina 
                                       WHERE turma_id = $turma_id AND disciplina_id = $disciplina_id");
                
                if ($check_td && $check_td->num_rows > 0) {
                    $td = $check_td->fetch_assoc();
                    $turma_disciplina_id = $td['id'];
                } else {
                    $db->query("INSERT INTO turma_disciplina (turma_id, disciplina_id) 
                               VALUES ($turma_id, $disciplina_id)");
                    $turma_disciplina_id = $db->insert_id;
                }
                
                // Verificar se já existe atribuição
                $check = $db->query("SELECT id FROM atribuicoes 
                                    WHERE professor_id = $professor_id 
                                    AND turma_disciplina_id = $turma_disciplina_id 
                                    AND ano_letivo = $ano_letivo");
                
                if ($check && $check->num_rows > 0) {
                    $error = "Este professor já está atribuído a esta disciplina nesta turma para o ano letivo $ano_letivo!";
                } else {
                    $query = "INSERT INTO atribuicoes (professor_id, turma_disciplina_id, ano_letivo) 
                              VALUES ($professor_id, $turma_disciplina_id, $ano_letivo)";
                    
                    if ($db->query($query)) {
                        $atribuicao_id = $db->insert_id;
                        $db->query("INSERT INTO logs_auditoria (usuario_id, acao, tabela, registro_id, ip) 
                                   VALUES ($usuario_id, 'CRIAR_ATRIBUICAO', 'atribuicoes', $atribuicao_id, '$ip')");
                        $message = "Professor atribuído à disciplina com sucesso!";
                    } else {
                        $error = "Erro ao criar atribuição: " . $db->error;
                    }
                }
                break;
                
            case 'delete':
                $id = (int)$_POST['id'];
                
                $atrib = $db->query("SELECT a.*, td.disciplina_id 
                                    FROM atribuicoes a
                                    INNER JOIN turma_disciplina td ON a.turma_disciplina_id = td.id
                                    WHERE a.id = $id")->fetch_assoc();
                
                if ($atrib) {
                    $check = $db->query("SELECT COUNT(*) as total FROM notas 
                                        WHERE disciplina_id = {$atrib['disciplina_id']} 
                                        AND ano_letivo = {$atrib['ano_letivo']}");
                    $notas = $check->fetch_assoc()['total'];
                    
                    if ($notas > 0) {
                        $error = "Não é possível remover esta atribuição pois já existem notas lançadas para esta disciplina no ano letivo!";
                        break;
                    }
                }
                
                $db->query("INSERT INTO logs_auditoria (usuario_id, acao, tabela, registro_id, ip) 
                           VALUES ($usuario_id, 'DELETAR_ATRIBUICAO', 'atribuicoes', $id, '$ip')");
                $query = "DELETE FROM atribuicoes WHERE id = $id";
                if ($db->query($query)) {
                    $message = "Atribuição removida com sucesso!";
                } else {
                    $error = "Erro ao remover atribuição: " . $db->error;
                }
                break;
        }
    }
}

// Buscar professores
$query = "SELECT p.id, u.nome, p.codigo_funcionario 
          FROM professores p
          INNER JOIN usuarios u ON p.usuario_id = u.id
          WHERE u.ativo = 1
          ORDER BY u.nome ASC";
$professores = $db->query($query);

// Buscar turmas
$query = "SELECT id, nome, ano_letivo, curso 
          FROM turmas 
          ORDER BY ano_letivo DESC, nome ASC";
$turmas = $db->query($query);

// Buscar disciplinas
$query = "SELECT id, nome, codigo 
          FROM disciplinas 
          ORDER BY nome ASC";
$disciplinas = $db->query($query);

// Buscar atribuições existentes
$query = "SELECT a.*, 
          u.nome as professor_nome,
          p.codigo_funcionario,
          t.nome as turma_nome,
          t.ano_letivo as turma_ano,
          t.curso as turma_curso,
          d.nome as disciplina_nome,
          d.codigo as disciplina_codigo
          FROM atribuicoes a
          INNER JOIN professores p ON a.professor_id = p.id
          INNER JOIN usuarios u ON p.usuario_id = u.id
          INNER JOIN turma_disciplina td ON a.turma_disciplina_id = td.id
          INNER JOIN turmas t ON td.turma_id = t.id
          INNER JOIN disciplinas d ON td.disciplina_id = d.id
          ORDER BY a.ano_letivo DESC, t.nome ASC, d.nome ASC";
$atribuicoes = $db->query($query);

// Anos letivos disponíveis
$ano_atual = date('Y');
$anos_letivos = range($ano_atual - 2, $ano_atual + 2);

// Estatísticas
$stats_query = "SELECT 
                COUNT(DISTINCT a.professor_id) as total_professores,
                COUNT(DISTINCT td.turma_id) as total_turmas,
                COUNT(DISTINCT td.disciplina_id) as total_disciplinas,
                COUNT(*) as total_atribuicoes,
                COUNT(DISTINCT a.ano_letivo) as anos_distintos
                FROM atribuicoes a
                INNER JOIN turma_disciplina td ON a.turma_disciplina_id = td.id";
$stats_result = $db->query($stats_query);
$stats = $stats_result->fetch_assoc();

if (!$stats['total_atribuicoes']) {
    $stats = [
        'total_professores' => 0,
        'total_turmas' => 0,
        'total_disciplinas' => 0,
        'total_atribuicoes' => 0,
        'anos_distintos' => 0
    ];
}

$page_title = "Gestão de Atribuições";
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IPOK - Gestão de Atribuições</title>
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
        
        .badge-professor {
            background: #cfe2ff;
            color: #084298;
        }
        
        .badge-codigo {
            background: #e2e3e5;
            color: #495057;
        }
        
        .badge-turma {
            background: #d1fae5;
            color: #065f46;
        }
        
        .badge-disciplina {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-ano {
            background: #f8d7da;
            color: #721c24;
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
        
        .info-box {
            background: #e6f0fa;
            border-left: 4px solid var(--primary-blue);
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 25px;
        }
        
        .select2-container--bootstrap-5 .select2-selection {
            border: 2px solid #e1e5e9;
            min-height: 45px;
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
            <a href="atribuicoes.php" class="menu-item active">
                <i class="fas fa-user-tie"></i>
                <span>Atribuições</span>
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
                    <i class="fas fa-user-tie me-2"></i>Gestão de Atribuições
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
                    <i class="fas fa-list"></i> Todas Atribuições
                </button>
                <button class="filter-pill" data-filter="professor" onclick="filterByProfessor()">
                    <i class="fas fa-chalkboard-teacher"></i> Por Professor
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
                               placeholder="Professor, turma, disciplina...">
                    </div>
                </div>
                
                <div class="col-md-3" id="filterProfessorContainer" style="display: none;">
                    <label class="form-label text-muted small mb-2">
                        <i class="fas fa-chalkboard-teacher me-1"></i>Professor
                    </label>
                    <select class="form-select" id="filterProfessor">
                        <option value="">Todos os professores</option>
                        <?php 
                        mysqli_data_seek($professores, 0);
                        while ($prof = $professores->fetch_assoc()): 
                        ?>
                            <option value="<?php echo htmlspecialchars($prof['nome']); ?>">
                                <?php echo htmlspecialchars($prof['nome']); ?>
                            </option>
                        <?php endwhile; 
                        mysqli_data_seek($professores, 0);
                        ?>
                    </select>
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
                
                <div class="col-md-2">
                    <button class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#atribuicaoModal" style="border-radius: 12px; background: linear-gradient(135deg, #1e3c72, #2a5298);">
                        <i class="fas fa-plus me-2"></i>Nova Atribuição
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
                <li>Apenas professores atribuídos podem lançar notas (RN05)</li>
                <li>Professor vê apenas suas turmas/disciplinas atribuídas (RN08)</li>
                <li>Um professor pode ter múltiplas turmas e disciplinas por ano letivo</li>
                <li>Não é possível remover atribuição se já houver notas lançadas</li>
            </ul>
        </div>
        
        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-user-tie"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['total_atribuicoes']; ?></h3>
                    <p>Total de Atribuições</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-chalkboard-teacher"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['total_professores']; ?></h3>
                    <p>Professores</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-chalkboard"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['total_turmas']; ?></h3>
                    <p>Turmas</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-book"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['total_disciplinas']; ?></h3>
                    <p>Disciplinas</p>
                </div>
            </div>
        </div>
        
        <!-- Atribuições Table -->
        <div class="table-container">
            <div class="table-header">
                <h5>
                    <i class="fas fa-list me-2"></i>Atribuições Cadastradas
                    <span class="badge bg-primary ms-2" id="totalCount"><?php echo $atribuicoes->num_rows; ?></span>
                </h5>
            </div>
            
            <div class="table-responsive">
                <table class="modern-table" id="atribuicoesTable">
                    <thead>
                        <tr>
                            <th style="width: 60px;">#ID</th>
                            <th>Professor</th>
                            <th>Código</th>
                            <th>Turma</th>
                            <th>Disciplina</th>
                            <th>Ano Letivo</th>
                            <th style="width: 100px;">Ações</th>
                        </thead>
                    <tbody>
                        <?php if ($atribuicoes && $atribuicoes->num_rows > 0): ?>
                            <?php while ($atrib = $atribuicoes->fetch_assoc()): 
                                $iniciais = substr($atrib['professor_nome'], 0, 2);
                            ?>
                            <tr data-id="<?php echo $atrib['id']; ?>"
                                data-professor="<?php echo strtolower($atrib['professor_nome']); ?>"
                                data-ano="<?php echo $atrib['ano_letivo']; ?>">
                                <td>
                                    <span class="badge bg-light text-dark">#<?php echo $atrib['id']; ?></span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="user-avatar" style="width: 40px; height: 40px; font-size: 1rem;">
                                            <?php echo strtoupper($iniciais); ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold"><?php echo htmlspecialchars($atrib['professor_nome']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge-status badge-codigo">
                                        <i class="fas fa-barcode me-1"></i>
                                        <?php echo htmlspecialchars($atrib['codigo_funcionario'] ?? '---'); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge-status badge-turma">
                                        <i class="fas fa-chalkboard me-1"></i>
                                        <?php echo htmlspecialchars($atrib['turma_nome']); ?>
                                    </span>
                                    <br>
                                    <small class="text-muted"><?php echo $atrib['turma_ano']; ?></small>
                                </td>
                                <td>
                                    <span class="badge-status badge-disciplina">
                                        <i class="fas fa-book me-1"></i>
                                        <?php echo htmlspecialchars($atrib['disciplina_nome']); ?>
                                    </span>
                                    <?php if ($atrib['disciplina_codigo']): ?>
                                        <br>
                                        <small class="text-muted"><?php echo $atrib['disciplina_codigo']; ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge-status badge-ano">
                                        <i class="fas fa-calendar me-1"></i>
                                        <?php echo $atrib['ano_letivo']; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn btn-sm btn-info" onclick="viewDetails(<?php echo $atrib['id']; ?>, '<?php echo addslashes($atrib['professor_nome']); ?>', '<?php echo addslashes($atrib['codigo_funcionario'] ?? ''); ?>', '<?php echo addslashes($atrib['turma_nome']); ?>', '<?php echo $atrib['turma_ano']; ?>', '<?php echo addslashes($atrib['disciplina_nome']); ?>', '<?php echo addslashes($atrib['disciplina_codigo'] ?? ''); ?>', '<?php echo $atrib['ano_letivo']; ?>')" data-bs-toggle="tooltip" title="Ver Detalhes">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        
                                        <form method="POST" style="display: contents;" onsubmit="return confirm('⚠️ Tem certeza que deseja remover esta atribuição?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $atrib['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" data-bs-toggle="tooltip" title="Remover Atribuição">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-4">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Nenhuma atribuição encontrada. Clique em "Nova Atribuição" para começar.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Modal de Detalhes da Atribuição -->
    <div class="modal fade" id="detailsModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 20px; overflow: hidden;">
                <div class="modal-header" style="background: linear-gradient(135deg, #1e3c72, #2a5298); color: white; border: none;">
                    <h5 class="modal-title">
                        <i class="fas fa-user-tie me-2"></i>Detalhes da Atribuição
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="text-center mb-4">
                        <div class="user-avatar" style="width: 80px; height: 80px; font-size: 2rem; margin: 0 auto;">
                            <span id="detailIniciais">AT</span>
                        </div>
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-md-12">
                            <div class="detail-card">
                                <div class="detail-label">
                                    <i class="fas fa-chalkboard-teacher me-1"></i> Professor
                                </div>
                                <div class="detail-value" id="detailProfessor">---</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="detail-card">
                                <div class="detail-label">
                                    <i class="fas fa-barcode me-1"></i> Código do Funcionário
                                </div>
                                <div class="detail-value" id="detailCodigo">---</div>
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
                                    <i class="fas fa-chalkboard me-1"></i> Turma
                                </div>
                                <div class="detail-value" id="detailTurma">---</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="detail-card">
                                <div class="detail-label">
                                    <i class="fas fa-book me-1"></i> Disciplina
                                </div>
                                <div class="detail-value" id="detailDisciplina">---</div>
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
    
    <!-- Atribuicao Modal (Create) -->
    <div class="modal fade" id="atribuicaoModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" style="border-radius: 20px; overflow: hidden;">
                <div class="modal-header" style="background: linear-gradient(135deg, #1e3c72, #2a5298); color: white; border: none;">
                    <h5 class="modal-title">
                        <i class="fas fa-plus-circle me-2"></i>Nova Atribuição
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="" id="atribuicaoForm">
                    <div class="modal-body p-4">
                        <input type="hidden" name="action" value="create">
                        
                        <div class="mb-3">
                            <label class="form-label">Professor *</label>
                            <select class="form-select select2" name="professor_id" id="professor_id" required>
                                <option value="">-- Selecione um professor --</option>
                                <?php 
                                mysqli_data_seek($professores, 0);
                                while ($prof = $professores->fetch_assoc()): 
                                ?>
                                <option value="<?php echo $prof['id']; ?>">
                                    <?php echo htmlspecialchars($prof['nome']); ?> 
                                    <?php if ($prof['codigo_funcionario']): ?>
                                    (<?php echo htmlspecialchars($prof['codigo_funcionario']); ?>)
                                    <?php endif; ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Turma *</label>
                                <select class="form-select select2" name="turma_id" id="turma_id" required>
                                    <option value="">-- Selecione uma turma --</option>
                                    <?php 
                                    mysqli_data_seek($turmas, 0);
                                    while ($turma = $turmas->fetch_assoc()): 
                                    ?>
                                    <option value="<?php echo $turma['id']; ?>">
                                        <?php echo htmlspecialchars($turma['nome']); ?> - 
                                        <?php echo $turma['ano_letivo']; ?>
                                        <?php if ($turma['curso']): ?>
                                        (<?php echo htmlspecialchars($turma['curso']); ?>)
                                        <?php endif; ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Disciplina *</label>
                                <select class="form-select select2" name="disciplina_id" id="disciplina_id" required>
                                    <option value="">-- Selecione uma disciplina --</option>
                                    <?php 
                                    mysqli_data_seek($disciplinas, 0);
                                    while ($disc = $disciplinas->fetch_assoc()): 
                                    ?>
                                    <option value="<?php echo $disc['id']; ?>">
                                        <?php echo htmlspecialchars($disc['nome']); ?>
                                        <?php if ($disc['codigo']): ?>
                                        (<?php echo htmlspecialchars($disc['codigo']); ?>)
                                        <?php endif; ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Ano Letivo *</label>
                            <select class="form-select" name="ano_letivo" required>
                                <option value="">-- Selecione --</option>
                                <?php foreach ($anos_letivos as $ano): ?>
                                <option value="<?php echo $ano; ?>" <?php echo ($ano == $ano_atual) ? 'selected' : ''; ?>>
                                    <?php echo $ano; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="alert alert-info mt-3" id="previewInfo" style="display: none;">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Pré-visualização:</strong><br>
                            <span id="previewText"></span>
                        </div>
                        
                        <div class="alert alert-warning mt-3">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Nota:</strong> Esta atribuição permitirá que o professor lance notas para esta disciplina nesta turma no ano letivo selecionado.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Guardar Atribuição
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
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
            
            document.getElementById('filterProfessorContainer').style.display = type === 'professor' ? 'block' : 'none';
            document.getElementById('filterAnoContainer').style.display = type === 'ano' ? 'block' : 'none';
            
            if (type !== 'professor') document.getElementById('filterProfessor').value = '';
            if (type !== 'ano') document.getElementById('filterAno').value = '';
            
            aplicarFiltros();
        }
        
        function filterByProfessor() { filterByType('professor'); }
        function filterByAno() { filterByType('ano'); }
        
        function aplicarFiltros() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase().trim();
            const profFilter = document.getElementById('filterProfessor').value;
            const anoFilter = document.getElementById('filterAno').value;
            const rows = document.querySelectorAll('#atribuicoesTable tbody tr');
            let visibleCount = 0;
            
            rows.forEach(row => {
                const professor = row.getAttribute('data-professor') || '';
                const ano = row.getAttribute('data-ano') || '';
                
                let match = true;
                
                if (searchTerm && !professor.includes(searchTerm)) {
                    match = false;
                }
                
                if (currentFilter === 'professor' && profFilter && professor !== profFilter.toLowerCase()) {
                    match = false;
                }
                
                if (currentFilter === 'ano' && anoFilter && ano !== anoFilter) {
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
        
        document.getElementById('searchInput').addEventListener('keyup', aplicarFiltros);
        document.getElementById('filterProfessor').addEventListener('change', aplicarFiltros);
        document.getElementById('filterAno').addEventListener('change', aplicarFiltros);
        
        // Função para visualizar detalhes
        let currentAtribuicaoId = null;
        
        function viewDetails(id, professor, codigo, turma, turmaAno, disciplina, disciplinaCodigo, ano) {
            currentAtribuicaoId = id;
            
            const iniciais = professor.substring(0, 2).toUpperCase();
            document.getElementById('detailIniciais').textContent = iniciais;
            
            document.getElementById('detailProfessor').textContent = professor;
            document.getElementById('detailCodigo').textContent = codigo || 'Não definido';
            document.getElementById('detailAno').textContent = ano;
            document.getElementById('detailTurma').textContent = turma + ' (' + turmaAno + ')';
            document.getElementById('detailDisciplina').textContent = disciplina + (disciplinaCodigo ? ' (' + disciplinaCodigo + ')' : '');
            
            new bootstrap.Modal(document.getElementById('detailsModal')).show();
        }
        
        // Select2 initialization
        $(document).ready(function() {
            $('.select2').select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: '-- Selecione --',
                allowClear: true,
                dropdownParent: $('#atribuicaoModal')
            });
            
            // Preview da atribuição
            function updatePreview() {
                var professor = $('#professor_id option:selected').text();
                var turma = $('#turma_id option:selected').text();
                var disciplina = $('#disciplina_id option:selected').text();
                var ano = $('select[name="ano_letivo"] option:selected').text();
                
                if (professor && professor !== '-- Selecione um professor --' && 
                    turma && turma !== '-- Selecione uma turma --' && 
                    disciplina && disciplina !== '-- Selecione uma disciplina --' && 
                    ano && ano !== '-- Selecione --') {
                    
                    $('#previewText').html(
                        '<strong>Professor:</strong> ' + professor + '<br>' +
                        '<strong>Turma:</strong> ' + turma + '<br>' +
                        '<strong>Disciplina:</strong> ' + disciplina + '<br>' +
                        '<strong>Ano Letivo:</strong> ' + ano
                    );
                    $('#previewInfo').show();
                } else {
                    $('#previewInfo').hide();
                }
            }
            
            $('#professor_id, #turma_id, #disciplina_id, select[name="ano_letivo"]').on('change', updatePreview);
            
            // Limpar preview ao fechar modal
            $('#atribuicaoModal').on('hidden.bs.modal', function() {
                $('#previewInfo').hide();
                $('#atribuicaoForm')[0].reset();
                $('.select2').val(null).trigger('change');
            });
        });
        
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
        
        document.addEventListener('DOMContentLoaded', function() {
            const rows = document.querySelectorAll('#atribuicoesTable tbody tr');
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