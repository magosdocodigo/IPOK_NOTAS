<?php
// professor/boletins.php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

// Verificar se é professor
if (!isLoggedIn() || !isProfessor()) {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

$message = '';
$error = '';
$professor_id = null;
$ano_atual = date('Y');

// Buscar ID do professor logado
$query = "SELECT id FROM professores WHERE usuario_id = {$_SESSION['user_id']}";
$result = $db->query($query);
if ($result && $result->num_rows > 0) {
    $professor = $result->fetch_assoc();
    $professor_id = $professor['id'];
}

// Buscar turmas do professor
$query = "SELECT DISTINCT t.id, t.nome, t.ano_letivo, t.curso
          FROM atribuicoes a
          INNER JOIN turma_disciplina td ON a.turma_disciplina_id = td.id
          INNER JOIN turmas t ON td.turma_id = t.id
          WHERE a.professor_id = $professor_id
          ORDER BY t.ano_letivo DESC, t.nome ASC";
$turmas = $db->query($query);

// Se uma turma foi selecionada, buscar alunos
$turma_selecionada = isset($_GET['turma_id']) ? (int)$_GET['turma_id'] : 0;
$aluno_selecionado = isset($_GET['aluno_id']) ? (int)$_GET['aluno_id'] : 0;
$ano_selecionado = isset($_GET['ano']) ? (int)$_GET['ano'] : $ano_atual;

$alunos = [];
$boletim = [];
$dados_aluno = null;

if ($turma_selecionada) {
    // Buscar alunos da turma
    $query = "SELECT a.id, u.nome, a.numero_matricula
              FROM enturmacoes e
              INNER JOIN alunos a ON e.aluno_id = a.id
              INNER JOIN usuarios u ON a.usuario_id = u.id
              WHERE e.turma_id = $turma_selecionada
              ORDER BY u.nome ASC";
    $alunos = $db->query($query);
}

if ($aluno_selecionado) {
    // Buscar dados do aluno
    $query = "SELECT a.id, u.nome, a.numero_matricula, t.nome as turma_nome, t.curso
              FROM alunos a
              INNER JOIN usuarios u ON a.usuario_id = u.id
              INNER JOIN enturmacoes e ON a.id = e.aluno_id
              INNER JOIN turmas t ON e.turma_id = t.id
              WHERE a.id = $aluno_selecionado AND e.turma_id = $turma_selecionada";
    $result = $db->query($query);
    $dados_aluno = $result->fetch_assoc();
    
    // Notas trimestrais (apenas nota_trimestre)
    $query = "SELECT 
              n.trimestre,
              d.id as disciplina_id,
              d.nome as disciplina_nome,
              d.codigo as disciplina_codigo,
              n.nota_trimestre,
              n.media_final,
              n.estado
              FROM notas n
              INNER JOIN disciplinas d ON n.disciplina_id = d.id
              WHERE n.aluno_id = $aluno_selecionado 
              AND n.ano_letivo = $ano_selecionado
              ORDER BY n.trimestre ASC, d.nome ASC";
    $notas = $db->query($query);
    
    $boletim = [
        1 => [],
        2 => [],
        3 => []
    ];
    
    while ($nota = $notas->fetch_assoc()) {
        $boletim[$nota['trimestre']][] = $nota;
    }
}

// Estatísticas gerais da turma (apenas contagem de alunos e média geral)
$stats_turma = [];
if ($turma_selecionada && $ano_selecionado) {
    $query = "SELECT 
              COUNT(DISTINCT a.id) as total_alunos,
              COUNT(DISTINCT CASE WHEN n.estado = 'Aprovado' THEN a.id END) as aprovados,
              COUNT(DISTINCT CASE WHEN n.estado = 'Reprovado' THEN a.id END) as reprovados,
              AVG(n.media_final) as media_geral
              FROM enturmacoes e
              INNER JOIN alunos a ON e.aluno_id = a.id
              LEFT JOIN notas n ON a.id = n.aluno_id AND n.ano_letivo = $ano_selecionado
              WHERE e.turma_id = $turma_selecionada";
    $result = $db->query($query);
    $stats_turma = $result->fetch_assoc();
}

$page_title = "Boletins";
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IPOK - Boletins</title>
    <link rel="shortcut icon" href="../assets/img/logo.png" type="image/x-icon">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
            width: 60px; height: 60px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            color: var(--primary-blue);
            font-size: 2rem;
        }
        
        .sidebar-menu { padding: 20px 0; }
        
        .sidebar-menu .menu-title {
            padding: 10px 20px;
            font-size: .75rem;
            text-transform: uppercase;
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
        
        /* Filtros */
        .filter-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,.05);
            margin-bottom: 25px;
        }
        
        .filter-title {
            color: var(--primary-blue);
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 20px;
        }
        
        /* Cards de Estatísticas */
        .stats-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 3px 10px rgba(0,0,0,.05);
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
            font-size: .8rem;
            text-transform: uppercase;
        }
        
        /* Boletim */
        .boletim-container {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,.05);
        }
        
        .boletim-header {
            border-bottom: 3px solid var(--primary-blue);
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        
        .boletim-header h2 {
            color: var(--primary-blue);
            font-size: 1.8rem;
        }
        
        .aluno-info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .aluno-info-item {
            display: inline-block;
            margin-right: 30px;
        }
        
        .aluno-info-label {
            font-size: .8rem;
            color: #6c757d;
            text-transform: uppercase;
        }
        
        .aluno-info-value {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--primary-blue);
        }
        
        .trimestre-card {
            border: 1px solid #dee2e6;
            border-radius: 10px;
            margin-bottom: 25px;
            overflow: hidden;
        }
        
        .trimestre-header {
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            color: white;
            padding: 15px 20px;
            font-weight: 600;
            font-size: 1.1rem;
        }
        
        .trimestre-body {
            padding: 20px;
        }
        
        .disciplina-row {
            display: flex;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px dashed #e9ecef;
        }
        
        .disciplina-row:last-child {
            border-bottom: none;
        }
        
        .disciplina-nome {
            flex: 2;
            font-weight: 500;
        }
        
        .disciplina-nota {
            flex: 1;
            text-align: center;
        }
        
        .nota-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-weight: 600;
            min-width: 60px;
        }
        
        .nota-alta {
            background: #d4edda;
            color: #155724;
        }
        
        .nota-media {
            background: #fff3cd;
            color: #856404;
        }
        
        .nota-baixa {
            background: #f8d7da;
            color: #721c24;
        }
        
        .estado-global {
            margin-top: 20px;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            font-weight: 600;
            font-size: 1.2rem;
        }
        
        .estado-aprovado {
            background: #d4edda;
            color: #155724;
        }
        
        .estado-reprovado {
            background: #f8d7da;
            color: #721c24;
        }
        
        .estado-em-andamento {
            background: #fff3cd;
            color: #856404;
        }
        
        .btn-boletim {
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 8px;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-boletim:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(30,60,114,.3);
            color: white;
        }
        
        .btn-pdf {
            background: #dc3545;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 8px;
            transition: all 0.3s;
            text-decoration: none;
        }
        
        .btn-pdf:hover {
            background: #c82333;
            color: white;
        }
        
        .info-box {
            background: #e6f0fa;
            border-left: 4px solid var(--primary-blue);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .legenda-box {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 12px;
            margin: 15px 0;
            font-size: 0.85rem;
        }
        
        .form-select, .form-control {
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            padding: 10px;
        }
        
        .form-select:focus, .form-control:focus {
            border-color: var(--secondary-blue);
            box-shadow: 0 0 0 0.2rem rgba(42,82,152,.25);
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
            body { background: white; }
            .main-content { margin: 0; padding: 0; }
            .boletim-container { box-shadow: none; padding: 0; }
            .aluno-info { background: none; border: 1px solid #dee2e6; }
            .trimestre-header { background: #1e3c72; }
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
            <h3>IPOK Professor</h3>
            <p><?php echo htmlspecialchars($_SESSION['user_nome']); ?></p>
        </div>
        
        <div class="sidebar-menu">
            <div class="menu-title">PRINCIPAL</div>
            <a href="dashboard.php" class="menu-item">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            
            <div class="menu-title">TURMAS</div>
            <a href="minhas-turmas.php" class="menu-item">
                <i class="fas fa-chalkboard"></i>
                <span>Minhas Turmas</span>
            </a>
            
            <div class="menu-title">NOTAS</div>
            <a href="lancar-notas.php" class="menu-item">
                <i class="fas fa-plus-circle"></i>
                <span>Lançar Notas</span>
            </a>
            <a href="editar-notas.php" class="menu-item">
                <i class="fas fa-edit"></i>
                <span>Editar Notas</span>
            </a>
            
            <div class="menu-title">RELATÓRIOS</div>
            <a href="boletins.php" class="menu-item active">
                <i class="fas fa-file-alt"></i>
                <span>Boletins</span>
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
        <div class="top-nav no-print">
            <div style="display: flex; align-items: center; gap: 15px;">
                <button class="btn btn-sm btn-outline-primary" onclick="toggleSidebar()" style="border-radius: 10px;">
                    <i class="fas fa-bars"></i>
                </button>
                <h1 class="page-title">
                    <i class="fas fa-file-alt me-2"></i>Boletins
                </h1>
            </div>
            <div class="user-info d-flex align-items-center gap-3">
                <div class="text-end">
                    <div class="fw-bold"><?php echo htmlspecialchars($_SESSION['user_nome']); ?></div>
                    <div class="small text-muted">Professor</div>
                </div>
                <div class="user-avatar">
                    <?php echo strtoupper(substr($_SESSION['user_nome'], 0, 1)); ?>
                </div>
            </div>
        </div>
        
        <!-- Filtros -->
        <div class="filter-section no-print">
            <div class="filter-title">
                <i class="fas fa-filter me-2"></i>Selecionar Boletim
            </div>
            
            <form method="GET" action="" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Turma</label>
                    <select class="form-select" name="turma_id" onchange="this.form.submit()" required>
                        <option value="">-- Selecione uma turma --</option>
                        <?php while ($turma = $turmas->fetch_assoc()): ?>
                        <option value="<?php echo $turma['id']; ?>" <?php echo $turma_selecionada == $turma['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($turma['nome']); ?> (<?php echo $turma['ano_letivo']; ?>)
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="col-md-4">
                    <label class="form-label">Aluno</label>
                    <select class="form-select" name="aluno_id" onchange="this.form.submit()" <?php echo !$turma_selecionada ? 'disabled' : ''; ?>>
                        <option value="">-- Selecione um aluno --</option>
                        <?php if ($alunos): while ($aluno = $alunos->fetch_assoc()): ?>
                        <option value="<?php echo $aluno['id']; ?>" <?php echo $aluno_selecionado == $aluno['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($aluno['nome']); ?> (<?php echo $aluno['numero_matricula']; ?>)
                        </option>
                        <?php endwhile; endif; ?>
                    </select>
                </div>
                
                <div class="col-md-4">
                    <label class="form-label">Ano Letivo</label>
                    <select class="form-select" name="ano" onchange="this.form.submit()">
                        <?php for ($a = $ano_atual; $a >= $ano_atual - 2; $a--): ?>
                        <option value="<?php echo $a; ?>" <?php echo $ano_selecionado == $a ? 'selected' : ''; ?>><?php echo $a; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </form>
        </div>
        
        <!-- Estatísticas da Turma -->
        <?php if ($turma_selecionada && $stats_turma): ?>
        <div class="row mb-4 no-print">
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number"><?php echo $stats_turma['total_alunos']; ?></div>
                    <div class="stats-label">Alunos na Turma</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card" style="border-left-color: #28a745;">
                    <div class="stats-number text-success"><?php echo $stats_turma['aprovados']; ?></div>
                    <div class="stats-label">Aprovados</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card" style="border-left-color: #dc3545;">
                    <div class="stats-number text-danger"><?php echo $stats_turma['reprovados']; ?></div>
                    <div class="stats-label">Reprovados</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number"><?php echo number_format($stats_turma['media_geral'] ?? 0, 1); ?></div>
                    <div class="stats-label">Média Geral</div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Boletim -->
        <?php if ($dados_aluno): ?>
        <div class="boletim-container">
            <div class="boletim-header d-flex justify-content-between align-items-center">
                <div>
                    <h2>
                        <i class="fas fa-graduation-cap me-2"></i>
                        Boletim Escolar
                    </h2>
                    <p class="text-muted">Ano Letivo <?php echo $ano_selecionado; ?></p>
                </div>
                <div class="no-print">
                    <button onclick="window.print()" class="btn-pdf me-2">
                        <i class="fas fa-print me-2"></i>Imprimir
                    </button>
                    <a href="gerar_pdf_boletim.php?aluno_id=<?php echo $aluno_selecionado; ?>&ano=<?php echo $ano_selecionado; ?>" class="btn-pdf">
                        <i class="fas fa-file-pdf me-2"></i>PDF
                    </a>
                </div>
            </div>
            
            <!-- Informações do Aluno -->
            <div class="aluno-info">
                <div class="row">
                    <div class="col-md-4">
                        <div class="aluno-info-item">
                            <div class="aluno-info-label">Aluno</div>
                            <div class="aluno-info-value"><?php echo htmlspecialchars($dados_aluno['nome']); ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="aluno-info-item">
                            <div class="aluno-info-label">Matrícula</div>
                            <div class="aluno-info-value"><?php echo htmlspecialchars($dados_aluno['numero_matricula']); ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="aluno-info-item">
                            <div class="aluno-info-label">Turma</div>
                            <div class="aluno-info-value"><?php echo htmlspecialchars($dados_aluno['turma_nome']); ?></div>
                        </div>
                    </div>
                </div>
                <?php if ($dados_aluno['curso']): ?>
                <div class="row mt-2">
                    <div class="col-12">
                        <div class="aluno-info-item">
                            <div class="aluno-info-label">Curso</div>
                            <div class="aluno-info-value"><?php echo htmlspecialchars($dados_aluno['curso']); ?></div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Legenda do Sistema de Avaliação -->
            <div class="legenda-box">
                <i class="fas fa-info-circle text-primary me-2"></i>
                <strong>Sistema de Avaliação:</strong>
                <span class="ms-3"><i class="fas fa-calculator me-1"></i> Nota do Trimestre = Avaliação Única</span>
                <span class="ms-3"><i class="fas fa-chart-line text-success me-1"></i> Aprovado: ≥ 10 | Reprovado: < 10</span>
            </div>
            
            <!-- Notas Trimestrais -->
            <?php 
            $total_disciplinas = 0;
            $soma_medias = 0;
            $trimestres_completos = 0;
            ?>
            
            <?php for ($t = 1; $t <= 3; $t++): 
                $notas_trimestre = $boletim[$t] ?? [];
                if (empty($notas_trimestre)) continue;
                
                $trimestres_completos++;
            ?>
            <div class="trimestre-card">
                <div class="trimestre-header">
                    <i class="fas fa-calendar-alt me-2"></i><?php echo $t; ?>º Trimestre
                </div>
                <div class="trimestre-body">
                    <div class="disciplina-row fw-bold text-muted mb-2">
                        <div class="disciplina-nome">Disciplina</div>
                        <div class="disciplina-nota">Nota do Trimestre</div>
                        <div class="disciplina-nota">Estado</div>
                    </div>
                    
                    <?php foreach ($notas_trimestre as $nota): 
                        $total_disciplinas++;
                        $media = $nota['nota_trimestre'] ?? 0;
                        $soma_medias += $media;
                        
                        $nota_classe = 'nota-media';
                        if ($media >= 14) $nota_classe = 'nota-alta';
                        elseif ($media < 10 && $media > 0) $nota_classe = 'nota-baixa';
                    ?>
                    <div class="disciplina-row">
                        <div class="disciplina-nome">
                            <?php echo htmlspecialchars($nota['disciplina_nome']); ?>
                            <?php if ($nota['disciplina_codigo']): ?>
                            <small class="text-muted">(<?php echo $nota['disciplina_codigo']; ?>)</small>
                            <?php endif; ?>
                        </div>
                        <div class="disciplina-nota">
                            <span class="nota-badge <?php echo $nota_classe; ?>">
                                <?php echo $media ? number_format($media, 1) : '-'; ?>
                            </span>
                        </div>
                        <div class="disciplina-nota">
                            <?php if ($nota['estado']): ?>
                                <span class="badge <?php echo $nota['estado'] == 'Aprovado' ? 'bg-success' : 'bg-danger'; ?>">
                                    <i class="fas fa-<?php echo $nota['estado'] == 'Aprovado' ? 'check-circle' : 'times-circle'; ?> me-1"></i>
                                    <?php echo $nota['estado']; ?>
                                </span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Pendente</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endfor; ?>
            
            <!-- Resumo Final -->
            <?php if ($trimestres_completos > 0): 
                $media_geral = $total_disciplinas > 0 ? round($soma_medias / $total_disciplinas, 1) : 0;
                
                // Determinar estado geral do aluno
                $tem_reprovacao = false;
                $todas_completas = true;
                
                for ($t = 1; $t <= 3; $t++) {
                    foreach ($boletim[$t] as $nota) {
                        if ($nota['estado'] === 'Reprovado') $tem_reprovacao = true;
                        if ($nota['estado'] === null) $todas_completas = false;
                    }
                }
                
                if ($tem_reprovacao) {
                    $estado_geral = 'Reprovado';
                    $estado_classe = 'estado-reprovado';
                } elseif (!$todas_completas) {
                    $estado_geral = 'Em Andamento';
                    $estado_classe = 'estado-em-andamento';
                } else {
                    $estado_geral = 'Aprovado';
                    $estado_classe = 'estado-aprovado';
                }
            ?>
            <div class="row mt-4">
                <div class="col-md-6">
                    <div class="info-box">
                        <strong>Resumo:</strong><br>
                        Total de Disciplinas: <?php echo $total_disciplinas; ?><br>
                        Média Geral: <?php echo number_format($media_geral, 1); ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="estado-global <?php echo $estado_classe; ?>">
                        <i class="fas fa-<?php echo $estado_geral == 'Aprovado' ? 'check-circle' : ($estado_geral == 'Reprovado' ? 'times-circle' : 'clock'); ?> me-2"></i>
                        Situação: <?php echo $estado_geral; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Rodapé do Boletim -->
            <div class="row mt-4 text-muted small">
                <div class="col-6">
                    <i class="fas fa-calendar me-1"></i>
                    Data de Emissão: <?php echo date('d/m/Y H:i'); ?>
                </div>
                <div class="col-6 text-end">
                    <i class="fas fa-user me-1"></i>
                    Professor: <?php echo htmlspecialchars($_SESSION['user_nome']); ?>
                </div>
            </div>
        </div>
        <?php elseif ($turma_selecionada): ?>
        <div class="alert alert-info text-center py-5 no-print">
            <i class="fas fa-user-graduate fa-3x mb-3 text-primary"></i>
            <h5>Selecione um aluno</h5>
            <p class="mb-0">Escolha um aluno da turma para visualizar o boletim.</p>
        </div>
        <?php else: ?>
        <div class="alert alert-info text-center py-5 no-print">
            <i class="fas fa-arrow-left fa-3x mb-3 text-primary"></i>
            <h5>Selecione uma turma</h5>
            <p class="mb-0">Escolha uma turma para visualizar os boletins dos alunos.</p>
        </div>
        <?php endif; ?>
    </div>
    
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
        
        // Atalho de teclado para imprimir (Ctrl+P)
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'p') {
                e.preventDefault();
                window.print();
            }
        });
    </script>
</body>
</html>