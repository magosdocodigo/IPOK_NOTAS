<?php
// admin/periodos.php
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
            case 'edit':
                $ano_letivo = (int)$_POST['ano_letivo'];
                $trimestre = (int)$_POST['trimestre'];
                $data_inicio = !empty($_POST['data_inicio']) ? "'" . mysqli_real_escape_string($db, $_POST['data_inicio']) . "'" : "NULL";
                $data_fim = !empty($_POST['data_fim']) ? "'" . mysqli_real_escape_string($db, $_POST['data_fim']) . "'" : "NULL";
                $status = mysqli_real_escape_string($db, $_POST['status']);
                
                if ($_POST['action'] === 'create') {
                    $check = $db->query("SELECT id FROM periodos WHERE ano_letivo = $ano_letivo AND trimestre = $trimestre");
                    if ($check && $check->num_rows > 0) {
                        $error = "Já existe um período para o $trimestreº trimestre de $ano_letivo!";
                    } else {
                        $query = "INSERT INTO periodos (ano_letivo, trimestre, data_inicio, data_fim, status) 
                                  VALUES ($ano_letivo, $trimestre, $data_inicio, $data_fim, '$status')";
                        
                        if ($db->query($query)) {
                            $periodo_id = $db->insert_id;
                            $db->query("INSERT INTO logs_auditoria (usuario_id, acao, tabela, registro_id, ip) 
                                       VALUES ($usuario_id, 'CRIAR_PERIODO', 'periodos', $periodo_id, '$ip')");
                            $message = "Período criado com sucesso!";
                        } else {
                            $error = "Erro ao criar período: " . $db->error;
                        }
                    }
                } else {
                    $id = (int)$_POST['id'];
                    $check = $db->query("SELECT id FROM periodos WHERE ano_letivo = $ano_letivo AND trimestre = $trimestre AND id != $id");
                    if ($check && $check->num_rows > 0) {
                        $error = "Já existe outro período para o $trimestreº trimestre de $ano_letivo!";
                    } else {
                        $query = "UPDATE periodos SET 
                                  ano_letivo = $ano_letivo,
                                  trimestre = $trimestre,
                                  data_inicio = $data_inicio,
                                  data_fim = $data_fim,
                                  status = '$status'
                                  WHERE id = $id";
                        
                        if ($db->query($query)) {
                            $db->query("INSERT INTO logs_auditoria (usuario_id, acao, tabela, registro_id, ip) 
                                       VALUES ($usuario_id, 'EDITAR_PERIODO', 'periodos', $id, '$ip')");
                            $message = "Período atualizado com sucesso!";
                        } else {
                            $error = "Erro ao atualizar período: " . $db->error;
                        }
                    }
                }
                break;
                
            case 'abrir':
            case 'fechar':
                $id = (int)$_POST['id'];
                $novo_status = ($_POST['action'] === 'abrir') ? 'aberto' : 'fechado';
                $acao_log = ($_POST['action'] === 'abrir') ? 'ABRIR_PERIODO' : 'FECHAR_PERIODO';
                $campo_data = ($_POST['action'] === 'abrir') ? 'aberto_em' : 'fechado_em';
                $campo_usuario = ($_POST['action'] === 'abrir') ? 'aberto_por' : 'fechado_por';
                
                $query = "UPDATE periodos SET 
                          status = '$novo_status',
                          $campo_data = NOW(),
                          $campo_usuario = $usuario_id
                          WHERE id = $id";
                
                if ($db->query($query)) {
                    $db->query("INSERT INTO logs_auditoria (usuario_id, acao, tabela, registro_id, ip) 
                               VALUES ($usuario_id, '$acao_log', 'periodos', $id, '$ip')");
                    $message = "Período " . ($_POST['action'] === 'abrir' ? 'aberto' : 'fechado') . " com sucesso!";
                } else {
                    $error = "Erro ao atualizar status do período: " . $db->error;
                }
                break;
                
            case 'delete':
                $id = (int)$_POST['id'];
                $periodo = $db->query("SELECT ano_letivo, trimestre FROM periodos WHERE id = $id")->fetch_assoc();
                if ($periodo) {
                    $check = $db->query("SELECT COUNT(*) as total FROM notas WHERE ano_letivo = {$periodo['ano_letivo']} AND trimestre = {$periodo['trimestre']}");
                    $notas = $check->fetch_assoc()['total'];
                    
                    if ($notas > 0) {
                        $error = "Não é possível excluir este período pois existem $notas notas lançadas neste trimestre!";
                        break;
                    }
                }
                
                $db->query("INSERT INTO logs_auditoria (usuario_id, acao, tabela, registro_id, ip) 
                           VALUES ($usuario_id, 'DELETAR_PERIODO', 'periodos', $id, '$ip')");
                $query = "DELETE FROM periodos WHERE id = $id";
                if ($db->query($query)) {
                    $message = "Período deletado com sucesso!";
                } else {
                    $error = "Erro ao deletar período: " . $db->error;
                }
                break;
        }
    }
}

// Buscar períodos
$query = "SELECT p.*,
          u_aberto.nome as nome_aberto_por,
          u_fechado.nome as nome_fechado_por,
          p.aberto_em,
          p.fechado_em
          FROM periodos p
          LEFT JOIN usuarios u_aberto ON p.aberto_por = u_aberto.id
          LEFT JOIN usuarios u_fechado ON p.fechado_por = u_fechado.id
          ORDER BY p.ano_letivo DESC, p.trimestre ASC";
$periodos = $db->query($query);

// Buscar dados para edição
$edit_periodo = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $query = "SELECT * FROM periodos WHERE id = $id";
    $result = $db->query($query);
    $edit_periodo = $result->fetch_assoc();
    
    if ($edit_periodo) {
        $stats = $db->query("SELECT COUNT(*) as notas FROM notas 
                            WHERE ano_letivo = {$edit_periodo['ano_letivo']} AND trimestre = {$edit_periodo['trimestre']}");
        $edit_periodo['total_notas'] = $stats->fetch_assoc()['notas'];
    }
}

// Anos letivos disponíveis
$ano_atual = date('Y');
$anos_letivos = range($ano_atual - 2, $ano_atual + 2);

// Estatísticas
$stats_query = "SELECT 
                COUNT(*) as total_periodos,
                SUM(CASE WHEN status = 'aberto' THEN 1 ELSE 0 END) as abertos,
                SUM(CASE WHEN status = 'fechado' THEN 1 ELSE 0 END) as fechados,
                COUNT(DISTINCT ano_letivo) as anos_distintos
                FROM periodos";
$stats_result = $db->query($stats_query);
$stats = $stats_result->fetch_assoc();

$page_title = "Gestão de Períodos";
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IPOK - Gestão de Períodos</title>
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
        
        .badge-aberto {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-fechado {
            background: #f8d7da;
            color: #721c24;
        }
        
        .badge-trimestre {
            background: #cfe2ff;
            color: #084298;
        }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            min-width: 200px;
        }
        
        .action-buttons .btn {
            flex-basis: calc(50% - 4px);
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
            <a href="periodos.php" class="menu-item active">
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
                    <i class="fas fa-calendar-alt me-2"></i>Gestão de Períodos
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
                    <i class="fas fa-calendar-alt"></i> Todos Períodos
                </button>
                <button class="filter-pill" data-filter="aberto" onclick="filterByStatus('aberto')">
                    <i class="fas fa-unlock"></i> Abertos
                </button>
                <button class="filter-pill" data-filter="fechado" onclick="filterByStatus('fechado')">
                    <i class="fas fa-lock"></i> Fechados
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
                               placeholder="Ano letivo...">
                    </div>
                </div>
                
                <div class="col-md-3" id="filterAnoContainer" style="display: block;">
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
                    <button class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#periodoModal" style="border-radius: 12px; background: linear-gradient(135deg, #1e3c72, #2a5298);">
                        <i class="fas fa-plus me-2"></i>Novo Período
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
                <li>Apenas o administrador pode abrir/fechar períodos</li>
                <li>Períodos abertos: Professores podem lançar/editar notas</li>
                <li>Períodos fechados: Notas ficam visíveis para os alunos</li>
                <li>Não é possível excluir períodos que já tenham notas lançadas</li>
            </ul>
        </div>
        
        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['total_periodos']; ?></h3>
                    <p>Total de Períodos</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-unlock"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['abertos']; ?></h3>
                    <p>Períodos Abertos</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-lock"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['fechados']; ?></h3>
                    <p>Períodos Fechados</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-calendar-week"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['anos_distintos']; ?></h3>
                    <p>Anos Letivos</p>
                </div>
            </div>
        </div>
        
        <!-- Periodos Table -->
        <div class="table-container">
            <div class="table-header">
                <h5>
                    <i class="fas fa-list me-2"></i>Períodos Letivos
                    <span class="badge bg-primary ms-2" id="totalCount"><?php echo $periodos->num_rows; ?></span>
                </h5>
            </div>
            
            <div class="table-responsive">
                <table class="modern-table" id="periodosTable">
                    <thead>
                        <tr>
                            <th style="width: 60px;">#ID</th>
                            <th>Ano Letivo</th>
                            <th>Trimestre</th>
                            <th>Período</th>
                            <th>Datas</th>
                            <th>Status</th>
                            <th>Aberto/Fechado por</th>
                            <th style="width: 180px;">Ações</th>
                        </thead>
                    <tbody>
                        <?php while ($periodo = $periodos->fetch_assoc()): 
                            $trimestres = ['1º Trimestre', '2º Trimestre', '3º Trimestre'];
                        ?>
                        <tr data-id="<?php echo $periodo['id']; ?>"
                            data-ano="<?php echo $periodo['ano_letivo']; ?>"
                            data-status="<?php echo $periodo['status']; ?>">
                            <td>
                                <span class="badge bg-light text-dark">#<?php echo $periodo['id']; ?></span>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <div class="user-avatar" style="width: 40px; height: 40px; font-size: 1rem;">
                                        <?php echo $periodo['ano_letivo']; ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold"><?php echo $periodo['ano_letivo']; ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge-status badge-trimestre">
                                    <i class="fas fa-<?php echo $periodo['trimestre']; ?>-circle me-1"></i>
                                    <?php echo $trimestres[$periodo['trimestre']-1]; ?>
                                </span>
                            </td>
                            <td>
                                <?php echo $trimestres[$periodo['trimestre']-1]; ?>
                            </td>
                            <td>
                                <?php if ($periodo['data_inicio']): ?>
                                    <i class="fas fa-calendar-plus text-success me-1"></i>
                                    <?php echo date('d/m/Y', strtotime($periodo['data_inicio'])); ?>
                                    <br>
                                    <i class="fas fa-calendar-times text-danger me-1"></i>
                                    <?php echo $periodo['data_fim'] ? date('d/m/Y', strtotime($periodo['data_fim'])) : '---'; ?>
                                <?php else: ?>
                                    <span class="text-muted">Datas não definidas</span>
                                <?php endif; ?>
                             </td>
                            <td>
                                <span class="badge-status <?php echo $periodo['status'] === 'aberto' ? 'badge-aberto' : 'badge-fechado'; ?>">
                                    <i class="fas fa-<?php echo $periodo['status'] === 'aberto' ? 'unlock' : 'lock'; ?> me-1"></i>
                                    <?php echo $periodo['status'] === 'aberto' ? 'Aberto' : 'Fechado'; ?>
                                </span>
                             </td>
                            <td>
                                <?php if ($periodo['status'] === 'aberto' && $periodo['nome_aberto_por']): ?>
                                    <i class="fas fa-user-check text-success me-1"></i>
                                    <?php echo htmlspecialchars($periodo['nome_aberto_por']); ?>
                                    <br>
                                    <small class="text-muted">
                                        <?php echo date('d/m/Y H:i', strtotime($periodo['aberto_em'])); ?>
                                    </small>
                                <?php elseif ($periodo['status'] === 'fechado' && $periodo['nome_fechado_por']): ?>
                                    <i class="fas fa-user-lock text-danger me-1"></i>
                                    <?php echo htmlspecialchars($periodo['nome_fechado_por']); ?>
                                    <br>
                                    <small class="text-muted">
                                        <?php echo date('d/m/Y H:i', strtotime($periodo['fechado_em'])); ?>
                                    </small>
                                <?php else: ?>
                                    <span class="text-muted">---</span>
                                <?php endif; ?>
                             </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn btn-sm btn-info" onclick="viewDetails(<?php echo $periodo['id']; ?>, '<?php echo $periodo['ano_letivo']; ?>', '<?php echo $periodo['trimestre']; ?>', '<?php echo addslashes($periodo['data_inicio'] ?? ''); ?>', '<?php echo addslashes($periodo['data_fim'] ?? ''); ?>', '<?php echo $periodo['status']; ?>', '<?php echo addslashes($periodo['nome_aberto_por'] ?? ''); ?>', '<?php echo addslashes($periodo['nome_fechado_por'] ?? ''); ?>', '<?php echo $periodo['aberto_em'] ?? ''; ?>', '<?php echo $periodo['fechado_em'] ?? ''; ?>')" data-bs-toggle="tooltip" title="Ver Detalhes">
                                        <i class="fas fa-eye"></i> Detalhes
                                    </button>
                                    
                                    <?php if ($periodo['status'] === 'fechado'): ?>
                                        <form method="POST" style="display: contents;" onsubmit="return confirm('⚠️ Tem certeza que deseja ABRIR este período? Os professores poderão lançar notas.');">
                                            <input type="hidden" name="action" value="abrir">
                                            <input type="hidden" name="id" value="<?php echo $periodo['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-success" data-bs-toggle="tooltip" title="Abrir Período">
                                                <i class="fas fa-lock-open"></i> Abrir
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" style="display: contents;" onsubmit="return confirm('⚠️ Tem certeza que deseja FECHAR este período? As notas ficarão visíveis para os alunos.');">
                                            <input type="hidden" name="action" value="fechar">
                                            <input type="hidden" name="id" value="<?php echo $periodo['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-warning" data-bs-toggle="tooltip" title="Fechar Período">
                                                <i class="fas fa-lock"></i> Fechar
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <a href="?edit=<?php echo $periodo['id']; ?>" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" title="Editar">
                                        <i class="fas fa-edit"></i> Editar
                                    </a>
                                    
                                    <form method="POST" style="display: contents;" onsubmit="return confirm('⚠️ Tem certeza que deseja deletar este período? Esta ação não pode ser desfeita.');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $periodo['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" data-bs-toggle="tooltip" title="Deletar">
                                            <i class="fas fa-trash"></i> Deletar
                                        </button>
                                    </form>
                                </div>
                             </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Modal de Detalhes do Período -->
    <div class="modal fade" id="detailsModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 20px; overflow: hidden;">
                <div class="modal-header" style="background: linear-gradient(135deg, #1e3c72, #2a5298); color: white; border: none;">
                    <h5 class="modal-title">
                        <i class="fas fa-calendar-alt me-2"></i>Detalhes do Período
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="text-center mb-4">
                        <div class="user-avatar" style="width: 80px; height: 80px; font-size: 2rem; margin: 0 auto;">
                            <span id="detailIniciais">PE</span>
                        </div>
                    </div>
                    
                    <div class="row g-3">
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
                                    <i class="fas fa-123 me-1"></i> Trimestre
                                </div>
                                <div class="detail-value" id="detailTrimestre">---</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="detail-card">
                                <div class="detail-label">
                                    <i class="fas fa-calendar-plus me-1"></i> Data de Início
                                </div>
                                <div class="detail-value" id="detailDataInicio">---</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="detail-card">
                                <div class="detail-label">
                                    <i class="fas fa-calendar-times me-1"></i> Data de Fim
                                </div>
                                <div class="detail-value" id="detailDataFim">---</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="detail-card">
                                <div class="detail-label">
                                    <i class="fas fa-info-circle me-1"></i> Status
                                </div>
                                <div class="detail-value" id="detailStatus">---</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="detail-card">
                                <div class="detail-label">
                                    <i class="fas fa-user me-1"></i> Aberto/Fechado por
                                </div>
                                <div class="detail-value" id="detailUsuario">---</div>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="detail-card">
                                <div class="detail-label">
                                    <i class="fas fa-clock me-1"></i> Data da Ação
                                </div>
                                <div class="detail-value" id="detailDataAcao">---</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                    <button type="button" class="btn btn-primary" id="btnEditFromDetails">
                        <i class="fas fa-edit me-2"></i>Editar Período
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Periodo Modal (Create/Edit) -->
    <div class="modal fade" id="periodoModal" tabindex="-1" <?php if($edit_periodo) echo 'data-show="true"'; ?>>
        <div class="modal-dialog">
            <div class="modal-content" style="border-radius: 20px; overflow: hidden;">
                <div class="modal-header" style="background: linear-gradient(135deg, #1e3c72, #2a5298); color: white; border: none;">
                    <h5 class="modal-title">
                        <i class="fas <?php echo $edit_periodo ? 'fa-edit' : 'fa-plus-circle'; ?> me-2"></i>
                        <?php echo $edit_periodo ? 'Editar Período' : 'Novo Período'; ?>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body p-4">
                        <input type="hidden" name="action" value="<?php echo $edit_periodo ? 'edit' : 'create'; ?>">
                        <?php if($edit_periodo): ?>
                        <input type="hidden" name="id" value="<?php echo $edit_periodo['id']; ?>">
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label class="form-label">Ano Letivo *</label>
                            <select class="form-select" name="ano_letivo" required>
                                <option value="">-- Selecione --</option>
                                <?php foreach ($anos_letivos as $ano): ?>
                                <option value="<?php echo $ano; ?>" 
                                    <?php echo ($edit_periodo && $edit_periodo['ano_letivo'] == $ano) ? 'selected' : ''; ?>>
                                    <?php echo $ano; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Trimestre *</label>
                            <select class="form-select" name="trimestre" required>
                                <option value="">-- Selecione --</option>
                                <option value="1" <?php echo ($edit_periodo && $edit_periodo['trimestre'] == 1) ? 'selected' : ''; ?>>1º Trimestre</option>
                                <option value="2" <?php echo ($edit_periodo && $edit_periodo['trimestre'] == 2) ? 'selected' : ''; ?>>2º Trimestre</option>
                                <option value="3" <?php echo ($edit_periodo && $edit_periodo['trimestre'] == 3) ? 'selected' : ''; ?>>3º Trimestre</option>
                            </select>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Data de Início</label>
                                <input type="date" class="form-control" name="data_inicio" 
                                       value="<?php echo $edit_periodo ? htmlspecialchars($edit_periodo['data_inicio'] ?? '') : ''; ?>">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Data de Fim</label>
                                <input type="date" class="form-control" name="data_fim" 
                                       value="<?php echo $edit_periodo ? htmlspecialchars($edit_periodo['data_fim'] ?? '') : ''; ?>">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Status *</label>
                            <select class="form-select" name="status" required>
                                <option value="fechado" <?php echo ($edit_periodo && $edit_periodo['status'] == 'fechado') ? 'selected' : ''; ?>>Fechado</option>
                                <option value="aberto" <?php echo ($edit_periodo && $edit_periodo['status'] == 'aberto') ? 'selected' : ''; ?>>Aberto</option>
                            </select>
                            <small class="text-muted">Períodos abertos permitem lançamento de notas</small>
                        </div>
                        
                        <?php if($edit_periodo && ($edit_periodo['aberto_em'] || $edit_periodo['fechado_em'])): ?>
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-history me-2"></i>
                            <strong>Histórico:</strong><br>
                            <?php if($edit_periodo['aberto_em']): ?>
                            Aberto em: <?php echo date('d/m/Y H:i', strtotime($edit_periodo['aberto_em'])); ?><br>
                            <?php endif; ?>
                            <?php if($edit_periodo['fechado_em']): ?>
                            Fechado em: <?php echo date('d/m/Y H:i', strtotime($edit_periodo['fechado_em'])); ?>
                            <?php endif; ?>
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
            
            document.querySelectorAll('.filter-pill').forEach(pill => {
                pill.classList.remove('active');
            });
            document.querySelector(`.filter-pill[data-filter="${type}"]`).classList.add('active');
            
            aplicarFiltros();
        }
        
        function filterByStatus(status) {
            currentFilter = status;
            
            document.querySelectorAll('.filter-pill').forEach(pill => {
                pill.classList.remove('active');
            });
            if (status === 'aberto') {
                document.querySelector('.filter-pill[data-filter="aberto"]').classList.add('active');
            } else if (status === 'fechado') {
                document.querySelector('.filter-pill[data-filter="fechado"]').classList.add('active');
            }
            
            aplicarFiltros();
        }
        
        function aplicarFiltros() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase().trim();
            const anoFilter = document.getElementById('filterAno').value;
            const rows = document.querySelectorAll('#periodosTable tbody tr');
            let visibleCount = 0;
            
            rows.forEach(row => {
                const ano = row.getAttribute('data-ano') || '';
                const status = row.getAttribute('data-status') || '';
                
                let match = true;
                
                if (searchTerm && !ano.includes(searchTerm)) {
                    match = false;
                }
                
                if (anoFilter && ano !== anoFilter) {
                    match = false;
                }
                
                if (currentFilter !== 'all' && status !== currentFilter) {
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
        document.getElementById('filterAno').addEventListener('change', aplicarFiltros);
        
        let currentPeriodoId = null;
        
        function viewDetails(id, ano, trimestre, dataInicio, dataFim, status, abertoPor, fechadoPor, abertoEm, fechadoEm) {
            currentPeriodoId = id;
            
            const trimestres = ['1º Trimestre', '2º Trimestre', '3º Trimestre'];
            
            document.getElementById('detailAno').textContent = ano;
            document.getElementById('detailTrimestre').textContent = trimestres[trimestre-1];
            document.getElementById('detailDataInicio').textContent = dataInicio ? dataInicio : 'Não definida';
            document.getElementById('detailDataFim').textContent = dataFim ? dataFim : 'Não definida';
            
            const statusHtml = status === 'aberto' 
                ? '<span class="badge-status badge-aberto"><i class="fas fa-unlock"></i> Aberto</span>'
                : '<span class="badge-status badge-fechado"><i class="fas fa-lock"></i> Fechado</span>';
            document.getElementById('detailStatus').innerHTML = statusHtml;
            
            if (status === 'aberto' && abertoPor) {
                document.getElementById('detailUsuario').textContent = abertoPor;
                document.getElementById('detailDataAcao').textContent = abertoEm ? new Date(abertoEm).toLocaleString('pt-PT') : '---';
            } else if (status === 'fechado' && fechadoPor) {
                document.getElementById('detailUsuario').textContent = fechadoPor;
                document.getElementById('detailDataAcao').textContent = fechadoEm ? new Date(fechadoEm).toLocaleString('pt-PT') : '---';
            } else {
                document.getElementById('detailUsuario').textContent = '---';
                document.getElementById('detailDataAcao').textContent = '---';
            }
            
            document.getElementById('btnEditFromDetails').onclick = function() {
                window.location.href = '?edit=' + currentPeriodoId;
            };
            
            new bootstrap.Modal(document.getElementById('detailsModal')).show();
        }
        
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
        
        <?php if($edit_periodo): ?>
        var periodoModal = new bootstrap.Modal(document.getElementById('periodoModal'));
        periodoModal.show();
        <?php endif; ?>
        
        document.addEventListener('DOMContentLoaded', function() {
            const rows = document.querySelectorAll('#periodosTable tbody tr');
            rows.forEach((row, index) => {
                row.style.opacity = '0';
                row.style.transform = 'translateY(10px)';
                setTimeout(() => {
                    row.style.transition = 'all 0.3s ease';
                    row.style.opacity = '1';
                    row.style.transform = 'translateY(0)';
                }, index * 50);
            });
            
            // Validação de datas
            $('form').on('submit', function(e) {
                var dataInicio = $('input[name="data_inicio"]').val();
                var dataFim = $('input[name="data_fim"]').val();
                
                if (dataInicio && dataFim && dataInicio > dataFim) {
                    e.preventDefault();
                    alert('A data de fim deve ser posterior à data de início!');
                }
            });
        });
    </script>
</body>
</html>