<?php
// admin/relatorios.php
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

// Processar geração de relatórios
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['gerar_relatorio'])) {
        $tipo_relatorio = $_POST['tipo_relatorio'];
        $ano_letivo = $_POST['ano_letivo'] ?? date('Y');
        $trimestre = $_POST['trimestre'] ?? '';
        $turma_id = $_POST['turma_id'] ?? '';
        $disciplina_id = $_POST['disciplina_id'] ?? '';
        $formato = $_POST['formato'] ?? 'html';
        
        // Redirecionar para o relatório apropriado
        $params = "?tipo=$tipo_relatorio&ano=$ano_letivo&formato=$formato";
        if ($trimestre) $params .= "&trimestre=$trimestre";
        if ($turma_id) $params .= "&turma_id=$turma_id";
        if ($disciplina_id) $params .= "&disciplina_id=$disciplina_id";
        
        header("Location: visualizar_relatorio.php$params");
        exit();
    }
}

// Buscar anos letivos disponíveis
$query = "SELECT DISTINCT ano_letivo FROM periodos ORDER BY ano_letivo DESC";
$anos = $db->query($query);

// Buscar turmas
$query = "SELECT id, nome, ano_letivo, curso FROM turmas ORDER BY ano_letivo DESC, nome ASC";
$turmas = $db->query($query);

// Buscar disciplinas
$query = "SELECT id, nome, codigo FROM disciplinas ORDER BY nome ASC";
$disciplinas = $db->query($query);

// Estatísticas gerais para cards
$stats = [];

// Total de alunos
$query = "SELECT COUNT(*) as total FROM alunos a INNER JOIN usuarios u ON a.usuario_id = u.id WHERE u.ativo = 1";
$result = $db->query($query);
$stats['total_alunos'] = $result->fetch_assoc()['total'];

// Total de professores
$query = "SELECT COUNT(*) as total FROM professores p INNER JOIN usuarios u ON p.usuario_id = u.id WHERE u.ativo = 1";
$result = $db->query($query);
$stats['total_professores'] = $result->fetch_assoc()['total'];

// Total de turmas
$query = "SELECT COUNT(*) as total FROM turmas";
$result = $db->query($query);
$stats['total_turmas'] = $result->fetch_assoc()['total'];

// Total de disciplinas
$query = "SELECT COUNT(*) as total FROM disciplinas";
$result = $db->query($query);
$stats['total_disciplinas'] = $result->fetch_assoc()['total'];

// Total de notas lançadas
$query = "SELECT COUNT(*) as total FROM notas";
$result = $db->query($query);
$stats['total_notas'] = $result->fetch_assoc()['total'];

// Média geral de notas
$query = "SELECT AVG(media_final) as media FROM notas WHERE media_final IS NOT NULL";
$result = $db->query($query);
$stats['media_geral'] = round($result->fetch_assoc()['media'] ?? 0, 1);

// Aprovados vs Reprovados
$query = "SELECT 
          SUM(CASE WHEN estado = 'Aprovado' THEN 1 ELSE 0 END) as aprovados,
          SUM(CASE WHEN estado = 'Reprovado' THEN 1 ELSE 0 END) as reprovados
          FROM notas WHERE estado IS NOT NULL";
$result = $db->query($query);
$stats_estado = $result->fetch_assoc();
$stats['aprovados'] = $stats_estado['aprovados'] ?? 0;
$stats['reprovados'] = $stats_estado['reprovados'] ?? 0;

$page_title = "Relatórios";
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IPOK - Relatórios</title>
    <link rel="shortcut icon" href="../assets/img/logo.png" type="image/x-icon">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    
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
            box-shadow: 2px 0 10px rgba(0,0,0,.1);
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
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            padding: 8px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
            transition: transform 0.3s ease;
        }

        .sidebar-header .logo:hover {
            transform: scale(1.05);
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
        .sidebar-menu .menu-item span { flex: 1; }
        
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
        
        .top-nav .user-info { display: flex; align-items: center; gap: 15px; }
        
        .top-nav .user-info .user-avatar {
            width: 45px; height: 45px;
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        
        /* Cards */
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,.05);
            margin-bottom: 20px;
            border-left: 4px solid var(--primary-blue);
        }
        
        .stats-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-blue);
        }
        
        .stats-label {
            color: #6c757d;
            font-size: .9rem;
            text-transform: uppercase;
            letter-spacing: .5px;
        }
        
        /* Relatório Cards */
        .relatorio-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,.05);
            transition: all 0.3s;
            height: 100%;
            border: 1px solid rgba(0,0,0,.05);
            cursor: pointer;
        }
        
        .relatorio-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(30,60,114,.15);
        }
        
        .relatorio-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            color: white;
            font-size: 2rem;
        }
        
        .relatorio-card h3 {
            color: var(--primary-blue);
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .relatorio-card p {
            color: #6c757d;
            font-size: .9rem;
            margin-bottom: 20px;
        }
        
        .relatorio-card .badge {
            background: var(--light-blue);
            color: var(--primary-blue);
            padding: 5px 10px;
            border-radius: 20px;
            font-size: .8rem;
        }
        
        /* Formulário */
        .form-container {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,.05);
            margin-bottom: 25px;
        }
        
        .form-container h4 {
            color: var(--primary-blue);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--light-blue);
        }
        
        .form-label {
            color: var(--primary-blue);
            font-weight: 500;
        }
        
        .form-control, .form-select {
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            padding: 10px;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--secondary-blue);
            box-shadow: 0 0 0 0.2rem rgba(42,82,152,.25);
        }
        
        .btn-relatorio {
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-relatorio:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(30,60,114,.3);
            color: white;
        }
        
        .btn-pdf {
            background: #dc3545;
            color: white;
        }
        
        .btn-excel {
            background: #28a745;
            color: white;
        }
        
        .info-box {
            background: #e6f0fa;
            border-left: 4px solid var(--primary-blue);
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
        }
        
        /* Estatísticas */
        .chart-container {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,.05);
            margin-bottom: 25px;
        }
        
        .chart-container canvas {
            max-height: 300px;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <i class="fas fa-graduation-cap"></i>
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
                <span>Professor x Turma</span>
            </a>
            <a href="enturmacoes.php" class="menu-item">
                <i class="fas fa-user-plus"></i>
                <span>Enturmações</span>
            </a>
            
            <div class="menu-title">RELATÓRIOS</div>
            <a href="relatorios.php" class="menu-item active">
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
                <button class="btn btn-sm btn-outline-primary" onclick="toggleSidebar()" style="border-radius: 8px;">
                    <i class="fas fa-bars"></i>
                </button>
                <h1 class="page-title">
                    <i class="fas fa-chart-bar me-2"></i>Relatórios
                </h1>
            </div>
            <div class="user-info">
                <div class="user-details text-end">
                    <div class="user-name"><?php echo htmlspecialchars($_SESSION['user_nome'] ?? ''); ?></div>
                    <div class="user-role small text-muted">Administrador</div>
                </div>
                <div class="user-avatar">
                    <?php echo strtoupper(substr($_SESSION['user_nome'] ?? 'A', 0, 1)); ?>
                </div>
            </div>
        </div>
        
        <!-- Stats Row -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number"><?php echo $stats['total_alunos']; ?></div>
                    <div class="stats-label">Alunos Ativos</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number"><?php echo $stats['total_professores']; ?></div>
                    <div class="stats-label">Professores</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number"><?php echo $stats['total_turmas']; ?></div>
                    <div class="stats-label">Turmas</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number"><?php echo $stats['total_disciplinas']; ?></div>
                    <div class="stats-label">Disciplinas</div>
                </div>
            </div>
        </div>
        
        <!-- Gráficos Rápidos -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="chart-container">
                    <h5 class="text-primary mb-3">
                        <i class="fas fa-chart-pie me-2"></i>Distribuição de Notas
                    </h5>
                    <canvas id="notasChart"></canvas>
                    <div class="row mt-3 text-center">
                        <div class="col-6">
                            <div class="text-success fw-bold"><?php echo $stats['aprovados']; ?></div>
                            <small>Aprovados</small>
                        </div>
                        <div class="col-6">
                            <div class="text-danger fw-bold"><?php echo $stats['reprovados']; ?></div>
                            <small>Reprovados</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="chart-container">
                    <h5 class="text-primary mb-3">
                        <i class="fas fa-chart-line me-2"></i>Resumo Acadêmico
                    </h5>
                    <div class="row text-center">
                        <div class="col-4">
                            <div class="display-6 text-primary"><?php echo $stats['total_notas']; ?></div>
                            <small>Total de Notas</small>
                        </div>
                        <div class="col-4">
                            <div class="display-6 text-info"><?php echo $stats['media_geral']; ?></div>
                            <small>Média Geral</small>
                        </div>
                        <div class="col-4">
                            <div class="display-6 text-success">
                                <?php 
                                $taxa_aprovacao = $stats['aprovados'] + $stats['reprovados'] > 0 
                                    ? round(($stats['aprovados'] / ($stats['aprovados'] + $stats['reprovados'])) * 100, 1) 
                                    : 0;
                                echo $taxa_aprovacao; ?>%
                            </div>
                            <small>Taxa Aprovação</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Gerar Relatórios -->
        <div class="form-container">
            <h4><i class="fas fa-file-alt me-2"></i>Gerar Relatório</h4>
            
            <form method="POST" action="" class="row g-4">
                <div class="col-md-4">
                    <label class="form-label">Tipo de Relatório *</label>
                    <select class="form-select" name="tipo_relatorio" id="tipoRelatorio" required>
                        <option value="">Selecione </option>
                        <option value="boletim">📋 Boletim Individual do Aluno</option>
                        <option value="pauta">📊 Pauta de Turma</option>
                        <option value="disciplina">📚 Notas por Disciplina</option>
                        <option value="professor">👨‍🏫 Relatório do Professor</option>
                        <option value="aproveitamento">📈 Aproveitamento Geral</option>
                        <option value="historico">📜 Histórico Escolar</option>
                        <option value="estatisticas">📉 Estatísticas Acadêmicas</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Ano Letivo *</label>
                    <select class="form-select" name="ano_letivo" required>
                        <option value="">Selecione </option>
                        <?php while ($ano = $anos->fetch_assoc()): ?>
                        <option value="<?php echo $ano['ano_letivo']; ?>"><?php echo $ano['ano_letivo']; ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="col-md-2" id="trimestreField" style="display: none;">
                    <label class="form-label">Trimestre</label>
                    <select class="form-select" name="trimestre">
                        <option value="">Todos</option>
                        <option value="1">1º Trimestre</option>
                        <option value="2">2º Trimestre</option>
                        <option value="3">3º Trimestre</option>
                    </select>
                </div>
                
                <div class="col-md-3" id="alunoField" style="display: none;">
                    <label class="form-label">Aluno</label>
                    <select class="form-select" name="aluno_id" id="alunoSelect">
                        <option value="">Selecione </option>
                        <?php
                        $alunos_query = "SELECT a.id, u.nome, a.numero_matricula 
                                        FROM alunos a 
                                        INNER JOIN usuarios u ON a.usuario_id = u.id 
                                        WHERE u.ativo = 1 
                                        ORDER BY u.nome ASC";
                        $alunos = $db->query($alunos_query);
                        while ($aluno = $alunos->fetch_assoc()):
                        ?>
                        <option value="<?php echo $aluno['id']; ?>">
                            <?php echo htmlspecialchars($aluno['nome']); ?> (<?php echo $aluno['numero_matricula']; ?>)
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="col-md-3" id="turmaField" style="display: none;">
                    <label class="form-label">Turma</label>
                    <select class="form-select" name="turma_id" id="turmaSelect">
                        <option value="">Selecione </option>
                        <?php 
                        mysqli_data_seek($turmas, 0);
                        while ($turma = $turmas->fetch_assoc()): 
                        ?>
                        <option value="<?php echo $turma['id']; ?>">
                            <?php echo htmlspecialchars($turma['nome']); ?> - <?php echo $turma['ano_letivo']; ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="col-md-3" id="disciplinaField" style="display: none;">
                    <label class="form-label">Disciplina</label>
                    <select class="form-select" name="disciplina_id" id="disciplinaSelect">
                        <option value="">Selecione </option>
                        <?php 
                        mysqli_data_seek($disciplinas, 0);
                        while ($disc = $disciplinas->fetch_assoc()): 
                        ?>
                        <option value="<?php echo $disc['id']; ?>">
                            <?php echo htmlspecialchars($disc['nome']); ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Formato</label>
                    <select class="form-select" name="formato">
                        <option value="html">📱 Visualizar</option>
                        <option value="pdf">📄 PDF</option>
                        <option value="excel">📊 Excel</option>
                    </select>
                </div>
                
                <div class="col-12">
                    <button type="submit" name="gerar_relatorio" class="btn-relatorio">
                        <i class="fas fa-file-export me-2"></i>Gerar Relatório
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Tipos de Relatórios -->
        <div class="row mt-4">
            <div class="col-12">
                <h5 class="text-primary mb-3">
                    <i class="fas fa-th-large me-2"></i>Relatórios Disponíveis
                </h5>
            </div>
            
            <div class="col-md-4 mb-4">
                <div class="relatorio-card" onclick="selecionarRelatorio('boletim')">
                    <div class="relatorio-icon">
                        <i class="fas fa-id-card"></i>
                    </div>
                    <h3>Boletim Individual</h3>
                    <p>Boletim detalhado do aluno com todas as notas por trimestre</p>
                    <span class="badge">Individual</span>
                </div>
            </div>
            
            <div class="col-md-4 mb-4">
                <div class="relatorio-card" onclick="selecionarRelatorio('pauta')">
                    <div class="relatorio-icon">
                        <i class="fas fa-table"></i>
                    </div>
                    <h3>Pauta de Turma</h3>
                    <p>Notas de todos os alunos de uma turma por disciplina</p>
                    <span class="badge">Turma</span>
                </div>
            </div>
            
            <div class="col-md-4 mb-4">
                <div class="relatorio-card" onclick="selecionarRelatorio('disciplina')">
                    <div class="relatorio-icon">
                        <i class="fas fa-book-open"></i>
                    </div>
                    <h3>Notas por Disciplina</h3>
                    <p>Desempenho dos alunos em uma disciplina específica</p>
                    <span class="badge">Disciplina</span>
                </div>
            </div>
            
            <div class="col-md-4 mb-4">
                <div class="relatorio-card" onclick="selecionarRelatorio('professor')">
                    <div class="relatorio-icon">
                        <i class="fas fa-chalkboard-teacher"></i>
                    </div>
                    <h3>Relatório do Professor</h3>
                    <p>Turmas e disciplinas atribuídas a um professor</p>
                    <span class="badge">Professor</span>
                </div>
            </div>
            
            <div class="col-md-4 mb-4">
                <div class="relatorio-card" onclick="selecionarRelatorio('aproveitamento')">
                    <div class="relatorio-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h3>Aproveitamento Geral</h3>
                    <p>Estatísticas de aprovação por turma/disciplina</p>
                    <span class="badge">Estatístico</span>
                </div>
            </div>
            
            <div class="col-md-4 mb-4">
                <div class="relatorio-card" onclick="selecionarRelatorio('historico')">
                    <div class="relatorio-icon">
                        <i class="fas fa-history"></i>
                    </div>
                    <h3>Histórico Escolar</h3>
                    <p>Histórico completo do aluno por ano letivo</p>
                    <span class="badge">Individual</span>
                </div>
            </div>
        </div>
        
        <!-- Relatórios Recentes -->
        <div class="table-container mt-4">
            <div class="table-title">
                <span><i class="fas fa-clock me-2"></i>Relatórios Gerados Recentemente</span>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Data/Hora</th>
                            <th>Tipo</th>
                            <th>Parâmetros</th>
                            <th>Utilizador</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Buscar últimos relatórios do log
                        $query = "SELECT * FROM logs_auditoria 
                                 WHERE acao LIKE '%RELATORIO%' 
                                 ORDER BY data_hora DESC 
                                 LIMIT 10";
                        $reports = $db->query($query);
                        
                        if ($reports && $reports->num_rows > 0):
                            while ($report = $reports->fetch_assoc()):
                        ?>
                        <tr>
                            <td><?php echo date('d/m/Y H:i', strtotime($report['data_hora'])); ?></td>
                            <td>
                                <span class="badge bg-info">
                                    <?php echo str_replace('_', ' ', $report['acao']); ?>
                                </span>
                            </td>
                            <td>
                                <small class="text-muted">
                                    <?php echo htmlspecialchars($report['dados_novos'] ?? '---'); ?>
                                </small>
                            </td>
                            <td>
                                <?php 
                                $user = $db->query("SELECT nome FROM usuarios WHERE id = {$report['usuario_id']}")->fetch_assoc();
                                echo htmlspecialchars($user['nome'] ?? 'Sistema');
                                ?>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" onclick="alert('Funcionalidade em desenvolvimento')">
                                    <i class="fas fa-redo"></i>
                                </button>
                            </td>
                        </tr>
                        <?php 
                            endwhile;
                        else:
                        ?>
                        <tr>
                            <td colspan="5" class="text-center py-4">
                                <i class="fas fa-info-circle me-2"></i>
                                Nenhum relatório gerado recentemente.
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Gráfico de distribuição de notas
            const ctx = document.getElementById('notasChart').getContext('2d');
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Aprovados', 'Reprovados'],
                    datasets: [{
                        data: [
                            <?php echo $stats['aprovados']; ?>,
                            <?php echo $stats['reprovados']; ?>
                        ],
                        backgroundColor: ['#28a745', '#dc3545'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
            
            // Mostrar campos baseado no tipo de relatório
            $('#tipoRelatorio').on('change', function() {
                var tipo = $(this).val();
                
                // Esconder todos os campos primeiro
                $('#alunoField, #turmaField, #disciplinaField, #trimestreField').hide();
                
                // Mostrar campos necessários
                if (tipo === 'boletim' || tipo === 'historico') {
                    $('#alunoField').show();
                    $('#trimestreField').show();
                } else if (tipo === 'pauta') {
                    $('#turmaField').show();
                    $('#trimestreField').show();
                } else if (tipo === 'disciplina') {
                    $('#disciplinaField').show();
                    $('#turmaField').show();
                    $('#trimestreField').show();
                } else if (tipo === 'professor') {
                    $('#professorField').show();
                } else if (tipo === 'aproveitamento') {
                    $('#turmaField').show();
                    $('#disciplinaField').show();
                }
            });
            
            // Inicializar tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
        
        // Função para selecionar relatório pelos cards
        function selecionarRelatorio(tipo) {
            $('#tipoRelatorio').val(tipo).trigger('change');
            $('html, body').animate({
                scrollTop: $('#tipoRelatorio').offset().top - 100
            }, 500);
        }
        
        // Toggle sidebar no mobile
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.querySelector('.main-content');
            sidebar.classList.toggle('hidden');
            mainContent.classList.toggle('sidebar-hidden');
        }
    </script>
</body>
</html>