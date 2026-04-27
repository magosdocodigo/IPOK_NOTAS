<?php
// admin/logs.php
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

// Processar limpeza de logs (apenas admin master)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'clear_old' && $_SESSION['user_id'] == 1) {
        $dias = (int)$_POST['dias'];
        $data_limite = date('Y-m-d H:i:s', strtotime("-$dias days"));
        
        $query = "DELETE FROM logs_auditoria WHERE data_hora < '$data_limite'";
        if ($db->query($query)) {
            $message = "Logs mais antigos que $dias dias foram removidos com sucesso!";
        } else {
            $error = "Erro ao limpar logs: " . $db->error;
        }
    }
}

// Paginação
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 50;
$offset = ($page - 1) * $limit;

// Filtros
$where = [];
$params = [];

if (!empty($_GET['usuario'])) {
    $usuario = mysqli_real_escape_string($db, $_GET['usuario']);
    $where[] = "u.nome LIKE '%$usuario%'";
}
if (!empty($_GET['acao'])) {
    $acao = mysqli_real_escape_string($db, $_GET['acao']);
    $where[] = "l.acao LIKE '%$acao%'";
}
if (!empty($_GET['tabela'])) {
    $tabela = mysqli_real_escape_string($db, $_GET['tabela']);
    $where[] = "l.tabela = '$tabela'";
}
if (!empty($_GET['data_inicio'])) {
    $data_inicio = mysqli_real_escape_string($db, $_GET['data_inicio']);
    $where[] = "DATE(l.data_hora) >= '$data_inicio'";
}
if (!empty($_GET['data_fim'])) {
    $data_fim = mysqli_real_escape_string($db, $_GET['data_fim']);
    $where[] = "DATE(l.data_hora) <= '$data_fim'";
}

$where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// Total de registros para paginação
$count_query = "SELECT COUNT(*) as total 
                FROM logs_auditoria l 
                LEFT JOIN usuarios u ON l.usuario_id = u.id 
                $where_clause";
$count_result = $db->query($count_query);
$total_registros = $count_result->fetch_assoc()['total'];
$total_paginas = ceil($total_registros / $limit);

// Buscar logs
$query = "SELECT l.*, u.nome as usuario_nome, u.email as usuario_email, u.nivel as usuario_nivel
          FROM logs_auditoria l 
          LEFT JOIN usuarios u ON l.usuario_id = u.id 
          $where_clause
          ORDER BY l.data_hora DESC 
          LIMIT $offset, $limit";
$logs = $db->query($query);

// Estatísticas dos logs
$stats_query = "SELECT 
                COUNT(*) as total_logs,
                COUNT(DISTINCT DATE(data_hora)) as dias_com_logs,
                COUNT(DISTINCT acao) as tipos_acao,
                COUNT(DISTINCT tabela) as tabelas_afetadas,
                MAX(data_hora) as ultimo_log
                FROM logs_auditoria";
$stats_result = $db->query($stats_query);
$stats = $stats_result->fetch_assoc();

// Ações mais comuns
$acoes_query = "SELECT acao, COUNT(*) as total 
                FROM logs_auditoria 
                GROUP BY acao 
                ORDER BY total DESC 
                LIMIT 10";
$acoes_comuns = $db->query($acoes_query);

// Função para gerar mensagem descritiva do log
function gerarMensagemDescritiva($log) {
    $usuario = $log['usuario_nome'] ?? 'Sistema';
    $acao = $log['acao'];
    $tabela = $log['tabela'];
    $registro_id = $log['registro_id'];
    $dados_novos = json_decode($log['dados_novos'], true);
    $dados_antigos = json_decode($log['dados_antigos'], true);
    
    $mapa_tabelas = [
        'usuarios' => 'utilizador',
        'alunos' => 'aluno',
        'professores' => 'professor',
        'turmas' => 'turma',
        'disciplinas' => 'disciplina',
        'periodos' => 'período',
        'notas' => 'nota',
        'atribuicoes' => 'atribuição',
        'enturmacoes' => 'enturmação'
    ];
    
    $nome_tabela = $mapa_tabelas[$tabela] ?? $tabela;
    
    // Processar diferentes tipos de ação
    if ($acao === 'LOGIN') {
        return "$usuario iniciou sessão no sistema";
    }
    
    if (strpos($acao, 'CRIAR') !== false) {
        if ($tabela === 'usuarios' && isset($dados_novos['nome'])) {
            return "$usuario criou o utilizador: " . $dados_novos['nome'];
        }
        if ($tabela === 'turmas' && isset($dados_novos['nome'])) {
            return "$usuario criou a turma: " . $dados_novos['nome'];
        }
        if ($tabela === 'disciplinas' && isset($dados_novos['nome'])) {
            return "$usuario criou a disciplina: " . $dados_novos['nome'];
        }
        return "$usuario criou um(a) novo(a) $nome_tabela";
    }
    
    if (strpos($acao, 'EDITAR') !== false) {
        if ($tabela === 'usuarios' && isset($dados_novos['nome'])) {
            if (isset($dados_antigos['nome']) && $dados_novos['nome'] !== $dados_antigos['nome']) {
                return "$usuario alterou o nome do utilizador de '" . $dados_antigos['nome'] . "' para '" . $dados_novos['nome'] . "'";
            }
            return "$usuario editou o utilizador " . $dados_novos['nome'];
        }
        if ($tabela === 'notas') {
            $aluno = $dados_novos['aluno_nome'] ?? $dados_antigos['aluno_nome'] ?? 'aluno';
            $disciplina = $dados_novos['disciplina_nome'] ?? $dados_antigos['disciplina_nome'] ?? 'disciplina';
            return "$usuario alterou as notas do $aluno na disciplina $disciplina";
        }
        if ($tabela === 'turmas' && isset($dados_novos['nome'])) {
            return "$usuario editou a turma: " . $dados_novos['nome'];
        }
        if ($tabela === 'disciplinas' && isset($dados_novos['nome'])) {
            return "$usuario editou a disciplina: " . $dados_novos['nome'];
        }
        return "$usuario editou um(a) $nome_tabela (ID: $registro_id)";
    }
    
    if (strpos($acao, 'DELETAR') !== false) {
        if ($tabela === 'usuarios' && isset($dados_antigos['nome'])) {
            return "$usuario eliminou o utilizador: " . $dados_antigos['nome'];
        }
        if ($tabela === 'notas') {
            $aluno = $dados_antigos['aluno_nome'] ?? 'aluno';
            $disciplina = $dados_antigos['disciplina_nome'] ?? 'disciplina';
            return "$usuario eliminou as notas do $aluno na disciplina $disciplina";
        }
        if ($tabela === 'turmas' && isset($dados_antigos['nome'])) {
            return "$usuario eliminou a turma: " . $dados_antigos['nome'];
        }
        if ($tabela === 'disciplinas' && isset($dados_antigos['nome'])) {
            return "$usuario eliminou a disciplina: " . $dados_antigos['nome'];
        }
        return "$usuario eliminou um(a) $nome_tabela (ID: $registro_id)";
    }
    
    if ($acao === 'ABRIR_PERIODO') {
        $ano = $dados_novos['ano_letivo'] ?? $dados_antigos['ano_letivo'] ?? '';
        $trimestre = $dados_novos['trimestre'] ?? $dados_antigos['trimestre'] ?? '';
        return "$usuario abriu o {$trimestre}º trimestre do ano $ano para lançamento de notas";
    }
    
    if ($acao === 'FECHAR_PERIODO') {
        $ano = $dados_novos['ano_letivo'] ?? $dados_antigos['ano_letivo'] ?? '';
        $trimestre = $dados_novos['trimestre'] ?? $dados_antigos['trimestre'] ?? '';
        return "$usuario fechou o {$trimestre}º trimestre do ano $ano";
    }
    
    if ($acao === 'ATIVAR_USUARIO') {
        return "$usuario ativou um utilizador";
    }
    
    if ($acao === 'DESATIVAR_USUARIO') {
        return "$usuario desativou um utilizador";
    }
    
    if ($acao === 'RESETAR_SENHA') {
        return "$usuario redefiniu a senha de um utilizador";
    }
    
    if ($acao === 'BATCH_ENTURMAR') {
        $alunos = isset($dados_novos['alunos']) ? count($dados_novos['alunos']) : 0;
        return "$usuario enturmou $alunos alunos em massa na turma";
    }
    
    // Mensagem genérica
    return "$usuario realizou a ação: " . str_replace('_', ' ', $acao);
}

$page_title = "Logs de Auditoria";
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IPOK - Logs de Auditoria</title>
    <link rel="shortcut icon" href="../assets/img/logo.png" type="image/x-icon">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
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
        .badge-log {
            padding: 6px 12px;
            border-radius: 30px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .badge-create { background: #d4edda; color: #155724; }
        .badge-edit { background: #fff3cd; color: #856404; }
        .badge-delete { background: #f8d7da; color: #721c24; }
        .badge-login { background: #cce5ff; color: #004085; }
        .badge-view { background: #e2e3e5; color: #383d41; }
        
        .descricao-log {
            font-size: 0.95rem;
            line-height: 1.4;
        }
        
        .json-preview {
            background: #1e1e1e;
            color: #d4d4d4;
            border-left: 3px solid var(--primary-blue);
            padding: 15px;
            font-size: 0.85rem;
            font-family: monospace;
            max-height: 400px;
            overflow-y: auto;
            white-space: pre-wrap;
            word-break: break-all;
            border-radius: 8px;
        }
        
        .acao-filtro {
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .acao-filtro:hover {
            transform: translateX(5px);
            background: #f0f0f0;
        }
        
        /* Paginação */
        .pagination .page-link {
            color: var(--primary-blue);
            border: 2px solid #dee2e6;
            margin: 0 2px;
            border-radius: 8px;
        }
        
        .pagination .page-item.active .page-link {
            background: var(--primary-blue);
            border-color: var(--primary-blue);
            color: white;
        }
        
        .info-box {
            background: #e6f0fa;
            border-left: 4px solid var(--primary-blue);
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 25px;
        }
        
        .data-badge {
            background: #f8fafc;
            padding: 5px 8px;
            border-radius: 8px;
            display: inline-block;
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
            <a href="enturmacoes.php" class="menu-item">
                <i class="fas fa-user-plus"></i>
                <span>Enturmações</span>
            </a>
            
            <div class="menu-title">RELATÓRIOS</div>
            <a href="relatorios.php" class="menu-item">
                <i class="fas fa-chart-bar"></i>
                <span>Relatórios</span>
            </a>
            <a href="logs.php" class="menu-item active">
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
                    <i class="fas fa-history me-2"></i>Logs de Auditoria
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
        
        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-history"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($stats['total_logs'] ?? 0); ?></h3>
                    <p>Total de Logs</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['dias_com_logs'] ?? 0; ?></h3>
                    <p>Dias com Atividade</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-tags"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['tipos_acao'] ?? 0; ?></h3>
                    <p>Tipos de Ação</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-database"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['tabelas_afetadas'] ?? 0; ?></h3>
                    <p>Tabelas Afetadas</p>
                </div>
            </div>
        </div>
        
        <!-- Info Box - RN09 -->
        <div class="info-box">
            <ul class="mb-0 mt-2">
                <li>Registro de IP e usuário em todas ações</li>
                <li>Dados antigos e novos são preservados em JSON</li>
                <li>Logs são permanentes (apenas admin master pode limpar)</li>
            </ul>
        </div>
        
        <!-- Filter Section -->
        <div class="filter-section">
            <!-- Filtros em Pílulas -->
            <div class="filter-pills">
                <button class="filter-pill active" data-filter="all" onclick="filterByType('all')">
                    <i class="fas fa-list"></i> Todos
                </button>
                <button class="filter-pill" data-filter="create" onclick="filterByAcao('CRIAR')">
                    <i class="fas fa-plus-circle"></i> Criações
                </button>
                <button class="filter-pill" data-filter="edit" onclick="filterByAcao('EDITAR')">
                    <i class="fas fa-edit"></i> Edições
                </button>
                <button class="filter-pill" data-filter="delete" onclick="filterByAcao('DELETAR')">
                    <i class="fas fa-trash"></i> Deleções
                </button>
                <button class="filter-pill" data-filter="login" onclick="filterByAcao('LOGIN')">
                    <i class="fas fa-sign-in-alt"></i> Logins
                </button>
                <button class="filter-pill" data-filter="periodo" onclick="filterByAcao('PERIODO')">
                    <i class="fas fa-calendar-alt"></i> Períodos
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
                               placeholder="Nome do utilizador...">
                    </div>
                </div>
                
                <div class="col-md-3" id="filterAcaoContainer" style="display: none;">
                    <label class="form-label text-muted small mb-2">
                        <i class="fas fa-tag me-1"></i>Ação
                    </label>
                    <select class="form-select" id="filterAcao">
                        <option value="">Todas</option>
                        <option value="CRIAR">Criações</option>
                        <option value="EDITAR">Edições</option>
                        <option value="DELETAR">Deleções</option>
                        <option value="LOGIN">Logins</option>
                        <option value="ABRIR_PERIODO">Abrir Período</option>
                        <option value="FECHAR_PERIODO">Fechar Período</option>
                        <option value="ATIVAR_USUARIO">Ativar Utilizador</option>
                        <option value="DESATIVAR_USUARIO">Desativar Utilizador</option>
                        <option value="RESETAR_SENHA">Resetar Senha</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label text-muted small mb-2">
                        <i class="fas fa-calendar me-1"></i>Data Início
                    </label>
                    <input type="date" class="form-control" id="dataInicio">
                </div>
                
                <div class="col-md-2">
                    <label class="form-label text-muted small mb-2">
                        <i class="fas fa-calendar me-1"></i>Data Fim
                    </label>
                    <input type="date" class="form-control" id="dataFim">
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
        
        <!-- Logs Table -->
        <div class="table-container">
            <div class="table-header">
                <h5>
                    <i class="fas fa-list me-2"></i>Registros de Auditoria
                    <span class="badge bg-primary ms-2" id="totalCount"><?php echo number_format($total_registros); ?></span>
                </h5>
                
                <?php if ($_SESSION['user_id'] == 1): ?>
                <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#clearLogsModal">
                    <i class="fas fa-trash-alt me-2"></i>Limpar Logs Antigos
                </button>
                <?php endif; ?>
            </div>
            
            <div class="table-responsive">
                <table class="modern-table" id="logsTable">
                    <thead>
                        <tr>
                            <th style="width: 140px;">Data/Hora</th>
                            <th style="width: 180px;">Utilizador</th>
                            <th style="width: 100px;">Ação</th>
                            <th style="min-width: 350px;">Descrição</th>
                            <th style="width: 120px;">IP</th>
                            <th style="width: 80px;">Detalhes</th>
                        </thead>
                    <tbody id="tableBody">
                        <?php if ($logs && $logs->num_rows > 0): ?>
                            <?php while ($log = $logs->fetch_assoc()): 
                                $badge_class = 'badge-view';
                                if (strpos($log['acao'], 'CRIAR') !== false) $badge_class = 'badge-create';
                                elseif (strpos($log['acao'], 'EDITAR') !== false) $badge_class = 'badge-edit';
                                elseif (strpos($log['acao'], 'DELETAR') !== false) $badge_class = 'badge-delete';
                                elseif (strpos($log['acao'], 'LOGIN') !== false) $badge_class = 'badge-login';
                                
                                $icone = match(true) {
                                    strpos($log['acao'], 'CRIAR') !== false => 'fa-plus-circle',
                                    strpos($log['acao'], 'EDITAR') !== false => 'fa-edit',
                                    strpos($log['acao'], 'DELETAR') !== false => 'fa-trash',
                                    strpos($log['acao'], 'LOGIN') !== false => 'fa-sign-in-alt',
                                    strpos($log['acao'], 'ABRIR') !== false => 'fa-unlock',
                                    strpos($log['acao'], 'FECHAR') !== false => 'fa-lock',
                                    default => 'fa-info-circle'
                                };
                                
                                $descricao = gerarMensagemDescritiva($log);
                                $iniciais = $log['usuario_nome'] ? substr($log['usuario_nome'], 0, 2) : 'SIS';
                            ?>
                            <tr data-id="<?php echo $log['id']; ?>"
                                data-usuario="<?php echo strtolower($log['usuario_nome'] ?? ''); ?>"
                                data-acao="<?php echo $log['acao']; ?>"
                                data-data="<?php echo date('Y-m-d', strtotime($log['data_hora'])); ?>">
                                <td class="align-middle">
                                    <div class="data-badge">
                                        <i class="fas fa-clock text-muted me-1"></i>
                                        <span class="fw-bold"><?php echo date('d/m/Y', strtotime($log['data_hora'])); ?></span>
                                        <br>
                                        <small class="text-muted"><?php echo date('H:i:s', strtotime($log['data_hora'])); ?></small>
                                    </div>
                                
                                <td class="align-middle">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="user-avatar" style="width: 40px; height: 40px; font-size: 1rem;">
                                            <?php echo strtoupper($iniciais); ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold"><?php echo htmlspecialchars($log['usuario_nome'] ?? 'Sistema'); ?></div>
                                            <small class="text-muted"><?php echo $log['usuario_nivel'] ?? 'Sistema'; ?></small>
                                        </div>
                                    </div>
                                
                                <td class="align-middle">
                                    <span class="badge-log <?php echo $badge_class; ?>">
                                        <i class="fas <?php echo $icone; ?> me-1"></i>
                                        <?php echo str_replace('_', ' ', $log['acao']); ?>
                                    </span>
                                
                                <td class="align-middle">
                                    <div class="descricao-log">
                                        <i class="fas fa-comment-dots text-primary me-2"></i>
                                        <?php echo htmlspecialchars($descricao); ?>
                                        <br>
                                        <small class="text-muted">
                                            <i class="fas fa-table me-1"></i>Tabela: <?php echo $log['tabela']; ?>
                                            <?php if ($log['registro_id'] > 0): ?>
                                                | ID: #<?php echo $log['registro_id']; ?>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                
                                <td class="align-middle">
                                    <code class="small"><?php echo htmlspecialchars($log['ip'] ?? '---'); ?></code>
                                
                                <td class="align-middle text-center">
                                    <?php if ($log['dados_antigos'] || $log['dados_novos']): ?>
                                        <button class="btn btn-sm btn-info" 
                                                onclick="verDados(<?php echo htmlspecialchars(json_encode($log)); ?>)"
                                                data-bs-toggle="tooltip" title="Ver detalhes JSON">
                                            <i class="fas fa-code"></i>
                                        </button>
                                    <?php else: ?>
                                        <span class="text-muted">---</span>
                                    <?php endif; ?>
                                
                             
                            <?php endwhile; ?>
                        <?php else: ?>
                             
                                <td colspan="6" class="text-center py-4">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Nenhum registro encontrado com os filtros selecionados.
                                
                             
                        <?php endif; ?>
                    </tbody>
                 
            </div>
            
            <!-- Paginação -->
            <?php if ($total_paginas > 1): ?>
            <div class="card-footer bg-white py-3 border-top">
                <div class="d-flex justify-content-between align-items-center">
                    <small class="text-muted">
                        <i class="fas fa-info-circle me-1"></i>
                        Mostrando <?php echo min($offset + 1, $total_registros); ?> - <?php echo min($offset + $limit, $total_registros); ?> de <?php echo number_format($total_registros); ?> registos
                    </small>
                    <nav aria-label="Page navigation">
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="#" onclick="mudarPagina(<?php echo $page - 1; ?>)">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($total_paginas, $page + 2); $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="#" onclick="mudarPagina(<?php echo $i; ?>)">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?php echo $page >= $total_paginas ? 'disabled' : ''; ?>">
                                <a class="page-link" href="#" onclick="mudarPagina(<?php echo $page + 1; ?>)">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Ações Mais Comuns -->
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="table-container">
                    <div class="table-header">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-bar me-2"></i>Ações Mais Comuns
                        </h5>
                    </div>
                    <div class="table-responsive p-3">
                        <table class="table table-sm mb-0">
                            <thead>
                                 
                                    <th>Ação</th>
                                    <th>Quantidade</th>
                                    <th>Porcentagem</th>
                                 
                            </thead>
                            <tbody>
                                <?php 
                                $total_acoes = $stats['total_logs'] ?? 1;
                                $acoes_comuns->data_seek(0);
                                while ($acao = $acoes_comuns->fetch_assoc()): 
                                    $percent = round(($acao['total'] / $total_acoes) * 100, 1);
                                    $badge_class = 'badge-view';
                                    if (strpos($acao['acao'], 'CRIAR') !== false) $badge_class = 'badge-create';
                                    elseif (strpos($acao['acao'], 'EDITAR') !== false) $badge_class = 'badge-edit';
                                    elseif (strpos($acao['acao'], 'DELETAR') !== false) $badge_class = 'badge-delete';
                                    elseif (strpos($acao['acao'], 'LOGIN') !== false) $badge_class = 'badge-login';
                                ?>
                                <tr class="acao-filtro" onclick="filtrarPorAcao('<?php echo $acao['acao']; ?>')">
                                     
                                        <span class="badge-log <?php echo $badge_class; ?>">
                                            <?php echo $acao['acao']; ?>
                                        </span>
                                     
                                    <td class="fw-bold"><?php echo number_format($acao['total']); ?> 
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="progress flex-grow-1" style="height: 8px;">
                                                <div class="progress-bar bg-primary" style="width: <?php echo $percent; ?>%"></div>
                                            </div>
                                            <small><?php echo $percent; ?>%</small>
                                        </div>
                                     
                                 
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="table-container">
                    <div class="table-header">
                        <h5 class="mb-0">
                            <i class="fas fa-calendar-alt me-2"></i>Atividade dos Últimos 7 Dias
                        </h5>
                    </div>
                    <div class="p-3">
                        <canvas id="activityChart" style="height: 250px;"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal Ver Dados -->
    <div class="modal fade" id="viewDataModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" style="border-radius: 20px; overflow: hidden;">
                <div class="modal-header" style="background: linear-gradient(135deg, #1e3c72, #2a5298); color: white; border: none;">
                    <h5 class="modal-title">
                        <i class="fas fa-code me-2"></i>Detalhes do Registro
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <ul class="nav nav-tabs" id="dataTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="novos-tab" data-bs-toggle="tab" data-bs-target="#novos" type="button">
                                <i class="fas fa-file-alt me-1"></i> Dados Novos
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="antigos-tab" data-bs-toggle="tab" data-bs-target="#antigos" type="button">
                                <i class="fas fa-history me-1"></i> Dados Antigos
                            </button>
                        </li>
                    </ul>
                    <div class="tab-content mt-3">
                        <div class="tab-pane fade show active" id="novos">
                            <pre id="dadosNovos" class="json-preview" style="border-left-color: #28a745;"></pre>
                        </div>
                        <div class="tab-pane fade" id="antigos">
                            <pre id="dadosAntigos" class="json-preview" style="border-left-color: #dc3545;"></pre>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal Limpar Logs -->
    <?php if ($_SESSION['user_id'] == 1): ?>
    <div class="modal fade" id="clearLogsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content" style="border-radius: 20px; overflow: hidden;">
                <div class="modal-header" style="background: #dc3545; color: white; border: none;">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle me-2"></i>Limpar Logs Antigos
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body p-4">
                        <input type="hidden" name="action" value="clear_old">
                        
                        <div class="alert alert-warning mb-3">
                            <i class="fas fa-info-circle me-2"></i>
                            Esta ação removerá permanentemente os logs de auditoria mais antigos que o período selecionado.
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Remover logs mais antigos que:</label>
                            <select class="form-select" name="dias" required>
                                <option value="30">30 dias</option>
                                <option value="60">60 dias</option>
                                <option value="90">90 dias</option>
                                <option value="180">6 meses</option>
                                <option value="365">1 ano</option>
                            </select>
                        </div>
                        
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <strong>Atenção:</strong> Esta ação não pode ser desfeita!
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash me-2"></i>Limpar Logs
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
    
    <script>
        let currentPage = <?php echo $page; ?>;
        let currentFilter = 'all';
        let currentAcao = '';
        
        // Toggle sidebar
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.querySelector('.main-content');
            sidebar.classList.toggle('hidden');
            mainContent.classList.toggle('sidebar-hidden');
        }
        
        // Funções de filtro
        function filterByType(type) {
            currentFilter = type;
            
            document.querySelectorAll('.filter-pill').forEach(pill => {
                pill.classList.remove('active');
            });
            document.querySelector(`.filter-pill[data-filter="${type}"]`).classList.add('active');
            
            document.getElementById('filterAcaoContainer').style.display = type === 'all' ? 'none' : 'block';
            
            if (type === 'all') {
                currentAcao = '';
                document.getElementById('filterAcao').value = '';
            }
            
            aplicarFiltros();
        }
        
        function filterByAcao(acao) {
            currentAcao = acao;
            document.getElementById('filterAcao').value = acao;
            aplicarFiltros();
        }
        
        function filtrarPorAcao(acao) {
            window.location.href = 'logs.php?acao=' + encodeURIComponent(acao);
        }
        
        function mudarPagina(pagina) {
            const params = new URLSearchParams(window.location.search);
            params.set('page', pagina);
            window.location.href = 'logs.php?' + params.toString();
        }
        
        function aplicarFiltros() {
            const params = new URLSearchParams();
            
            if (currentFilter !== 'all' && currentAcao) {
                params.set('acao', currentAcao);
            }
            
            const dataInicio = document.getElementById('dataInicio').value;
            const dataFim = document.getElementById('dataFim').value;
            const searchTerm = document.getElementById('searchInput').value;
            
            if (dataInicio) params.set('data_inicio', dataInicio);
            if (dataFim) params.set('data_fim', dataFim);
            if (searchTerm) params.set('usuario', searchTerm);
            
            window.location.href = 'logs.php?' + params.toString();
        }
        
        // Event listeners
        document.getElementById('searchInput').addEventListener('keyup', function(e) {
            if (e.key === 'Enter') aplicarFiltros();
        });
        document.getElementById('dataInicio').addEventListener('change', aplicarFiltros);
        document.getElementById('dataFim').addEventListener('change', aplicarFiltros);
        document.getElementById('filterAcao').addEventListener('change', function() {
            currentAcao = this.value;
            aplicarFiltros();
        });
        
        // Função para ver dados detalhados
        function verDados(log) {
            try {
                const dadosNovos = log.dados_novos ? JSON.stringify(JSON.parse(log.dados_novos), null, 2) : 'Nenhum dado novo';
                const dadosAntigos = log.dados_antigos ? JSON.stringify(JSON.parse(log.dados_antigos), null, 2) : 'Nenhum dado antigo';
                document.getElementById('dadosNovos').textContent = dadosNovos;
                document.getElementById('dadosAntigos').textContent = dadosAntigos;
            } catch(e) {
                document.getElementById('dadosNovos').textContent = log.dados_novos || 'Nenhum dado novo';
                document.getElementById('dadosAntigos').textContent = log.dados_antigos || 'Nenhum dado antigo';
            }
            new bootstrap.Modal(document.getElementById('viewDataModal')).show();
        }
        
        // Gráfico de atividade
        document.addEventListener('DOMContentLoaded', function() {
            <?php
            $activity_query = "SELECT DATE(data_hora) as data, COUNT(*) as total 
                              FROM logs_auditoria 
                              WHERE data_hora >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                              GROUP BY DATE(data_hora)
                              ORDER BY data ASC";
            $activity = $db->query($activity_query);
            
            $datas = [];
            $totais = [];
            while ($dia = $activity->fetch_assoc()) {
                $datas[] = date('d/m', strtotime($dia['data']));
                $totais[] = $dia['total'];
            }
            ?>
            
            const ctx = document.getElementById('activityChart').getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($datas); ?>,
                    datasets: [{
                        label: 'Atividade',
                        data: <?php echo json_encode($totais); ?>,
                        borderColor: '#1e3c72',
                        backgroundColor: 'rgba(30, 60, 114, 0.1)',
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: '#1e3c72',
                        pointBorderColor: '#fff',
                        pointRadius: 4,
                        pointHoverRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.raw + ' registros';
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1,
                                precision: 0
                            },
                            title: {
                                display: true,
                                text: 'Número de registos'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Data'
                            }
                        }
                    }
                }
            });
        });
        
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
        
        // Animações de entrada
        document.addEventListener('DOMContentLoaded', function() {
            const rows = document.querySelectorAll('#tableBody tr');
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