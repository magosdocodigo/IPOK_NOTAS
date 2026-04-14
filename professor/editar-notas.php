<?php
// professor/editar-notas.php
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

// Verificar períodos abertos
$periodos_abertos = [];
$query = "SELECT * FROM periodos WHERE status = 'aberto' AND ano_letivo = $ano_atual ORDER BY trimestre";
$result = $db->query($query);
while ($periodo = $result->fetch_assoc()) {
    $periodos_abertos[$periodo['trimestre']] = $periodo;
}

// Processar edição de notas
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'editar_notas') {
        $notas = $_POST['notas'] ?? [];
        $ip = $_SERVER['REMOTE_ADDR'];
        
        $sucessos = 0;
        $erros = 0;
        
        foreach ($notas as $nota_id => $dados) {
            $nota_id = (int)$nota_id;
            
            // Buscar dados antigos para log
            $old_query = "SELECT n.*, d.nome as disciplina_nome, u.nome as aluno_nome
                         FROM notas n
                         INNER JOIN alunos a ON n.aluno_id = a.id
                         INNER JOIN usuarios u ON a.usuario_id = u.id
                         INNER JOIN disciplinas d ON n.disciplina_id = d.id
                         WHERE n.id = $nota_id";
            $old_result = $db->query($old_query);
            $old_data = $old_result->fetch_assoc();
            
            // Processar notas: avaliacao1, avaliacao2, exame
            $avaliacao1 = isset($dados['avaliacao1']) && $dados['avaliacao1'] !== '' ? (float)$dados['avaliacao1'] : null;
            $avaliacao2 = isset($dados['avaliacao2']) && $dados['avaliacao2'] !== '' ? (float)$dados['avaliacao2'] : null;
            $exame = isset($dados['exame']) && $dados['exame'] !== '' ? (float)$dados['exame'] : null;
            
            // Validar notas (0-20)
            if (($avaliacao1 !== null && ($avaliacao1 < 0 || $avaliacao1 > 20)) ||
                ($avaliacao2 !== null && ($avaliacao2 < 0 || $avaliacao2 > 20)) ||
                ($exame !== null && ($exame < 0 || $exame > 20))) {
                $erros++;
                continue;
            }
            
            $update_query = "UPDATE notas SET 
                            avaliacao1 = " . ($avaliacao1 !== null ? $avaliacao1 : "NULL") . ",
                            avaliacao2 = " . ($avaliacao2 !== null ? $avaliacao2 : "NULL") . ",
                            exame = " . ($exame !== null ? $exame : "NULL") . ",
                            ultima_edicao_por = {$_SESSION['user_id']},
                            ultima_edicao_em = NOW()
                            WHERE id = $nota_id";
            
            if ($db->query($update_query)) {
                // Buscar dados novos para log
                $new_query = "SELECT n.*, d.nome as disciplina_nome, u.nome as aluno_nome
                             FROM notas n
                             INNER JOIN alunos a ON n.aluno_id = a.id
                             INNER JOIN usuarios u ON a.usuario_id = u.id
                             INNER JOIN disciplinas d ON n.disciplina_id = d.id
                             WHERE n.id = $nota_id";
                $new_result = $db->query($new_query);
                $new_data = $new_result->fetch_assoc();
                
                // Log de auditoria
                $db->query("INSERT INTO logs_auditoria (usuario_id, acao, tabela, registro_id, dados_antigos, dados_novos, ip) 
                           VALUES ({$_SESSION['user_id']}, 'EDITAR_NOTA', 'notas', $nota_id, '" . json_encode($old_data) . "', '" . json_encode($new_data) . "', '$ip')");
                
                $sucessos++;
            } else {
                $erros++;
            }
        }
        
        if ($sucessos > 0) {
            $message = "Notas atualizadas com sucesso! ($sucessos registros alterados)";
            if ($erros > 0) {
                $message .= " ($erros erros)";
            }
        } else {
            $error = "Erro ao atualizar notas. Nenhum registro foi alterado.";
        }
    }
}

// Buscar turmas do professor
$query = "SELECT DISTINCT t.id, t.nome, t.ano_letivo, t.curso
          FROM atribuicoes a
          INNER JOIN turma_disciplina td ON a.turma_disciplina_id = td.id
          INNER JOIN turmas t ON td.turma_id = t.id
          WHERE a.professor_id = $professor_id
          ORDER BY t.ano_letivo DESC, t.nome ASC";
$turmas = $db->query($query);

// Se uma turma foi selecionada, buscar disciplinas
$turma_selecionada = isset($_GET['turma_id']) ? (int)$_GET['turma_id'] : 0;
$disciplina_selecionada = isset($_GET['disciplina_id']) ? (int)$_GET['disciplina_id'] : 0;
$trimestre_selecionado = isset($_GET['trimestre']) ? (int)$_GET['trimestre'] : 0;
$ano_selecionado = isset($_GET['ano']) ? (int)$_GET['ano'] : $ano_atual;

$disciplinas = [];
$notas = [];

if ($turma_selecionada) {
    // Buscar disciplinas da turma que o professor leciona
    $query = "SELECT d.id, d.nome, d.codigo
              FROM atribuicoes a
              INNER JOIN turma_disciplina td ON a.turma_disciplina_id = td.id
              INNER JOIN disciplinas d ON td.disciplina_id = d.id
              WHERE a.professor_id = $professor_id 
              AND td.turma_id = $turma_selecionada
              GROUP BY d.id
              ORDER BY d.nome ASC";
    $disciplinas = $db->query($query);
}

if ($turma_selecionada && $disciplina_selecionada && $trimestre_selecionado) {
    // Buscar notas dos alunos - estrutura única para todos os trimestres
    $query = "SELECT n.*, 
              u.nome as aluno_nome, 
              a.numero_matricula,
              t.nome as turma_nome,
              d.nome as disciplina_nome
              FROM notas n
              INNER JOIN alunos a ON n.aluno_id = a.id
              INNER JOIN usuarios u ON a.usuario_id = u.id
              INNER JOIN enturmacoes e ON a.id = e.aluno_id
              INNER JOIN turmas t ON e.turma_id = t.id
              INNER JOIN disciplinas d ON n.disciplina_id = d.id
              WHERE n.disciplina_id = $disciplina_selecionada 
              AND n.ano_letivo = $ano_selecionado
              AND n.trimestre = $trimestre_selecionado
              AND e.turma_id = $turma_selecionada
              ORDER BY u.nome ASC";
    $notas = $db->query($query);
}

// Estatísticas para cards
$stats = ['total_notas' => 0, 'aprovados' => 0, 'reprovados' => 0, 'media_geral' => 0];

if ($turma_selecionada && $disciplina_selecionada && $trimestre_selecionado && $notas && $notas->num_rows > 0) {
    $stats_query = "SELECT 
                    COUNT(DISTINCT n.id) as total_notas,
                    SUM(CASE WHEN n.media_final >= 10 THEN 1 ELSE 0 END) as aprovados,
                    SUM(CASE WHEN n.media_final < 10 AND n.media_final > 0 THEN 1 ELSE 0 END) as reprovados,
                    AVG(n.media_final) as media_geral
                    FROM notas n
                    INNER JOIN alunos a ON n.aluno_id = a.id
                    INNER JOIN enturmacoes e ON a.id = e.aluno_id
                    WHERE n.disciplina_id = $disciplina_selecionada 
                    AND n.ano_letivo = $ano_selecionado
                    AND n.trimestre = $trimestre_selecionado
                    AND e.turma_id = $turma_selecionada";
    
    $stats_result = $db->query($stats_query);
    $stats = $stats_result->fetch_assoc();
}

$page_title = "Editar Notas";
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IPOK - Editar Notas</title>
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
        
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 20px;
            transition: all .3s ease;
            min-height: 100vh;
        }
        
        .main-content.sidebar-hidden { margin-left: 0; }
        
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
        
        .periodo-info {
            background: #e6f0fa;
            border-left: 4px solid var(--primary-blue);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }
        
        .periodo-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: .8rem;
            font-weight: 500;
        }
        
        .periodo-aberto {
            background: #d4edda;
            color: #155724;
        }
        
        .periodo-fechado {
            background: #f8d7da;
            color: #721c24;
        }
        
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
        
        .table-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0,0,0,.05);
        }
        
        .card-header-custom {
            background: linear-gradient(135deg, #f8fafc, #ffffff);
            padding: 15px 20px;
            border-bottom: 2px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-header-custom h4 {
            margin: 0;
            color: var(--primary-blue);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .badge-total {
            background: var(--primary-blue);
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
        }
        
        .nota-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .nota-table th {
            background: #f8fafc;
            color: var(--primary-blue);
            font-weight: 600;
            font-size: 0.85rem;
            padding: 12px 15px;
            border-bottom: 2px solid #e9ecef;
            text-align: left;
        }
        
        .nota-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #e9ecef;
            vertical-align: middle;
        }
        
        .nota-table tr:hover {
            background: #f8fafc;
        }
        
        .aluno-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .aluno-avatar-small {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 0.9rem;
        }
        
        .nota-input {
            width: 90px;
            text-align: center;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 8px;
            transition: all 0.3s;
        }
        
        .nota-input:focus {
            border-color: var(--primary-blue);
            outline: none;
            box-shadow: 0 0 0 3px rgba(30,60,114,.1);
        }
        
        .nota-input.valida {
            border-color: #28a745;
            background: #f0fff0;
        }
        
        .nota-input.invalida {
            border-color: #dc3545;
            background: #fff0f0;
        }
        
        .media-box {
            background: #f8f9fa;
            padding: 6px 12px;
            border-radius: 8px;
            text-align: center;
            font-weight: 600;
            display: inline-block;
            min-width: 60px;
        }
        
        .estado-box {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .estado-aprovado {
            background: #d4edda;
            color: #155724;
        }
        
        .estado-reprovado {
            background: #f8d7da;
            color: #721c24;
        }
        
        .estado-incompleto {
            background: #fff3cd;
            color: #856404;
        }
        
        .legenda-box {
            background: #e6f0fa;
            border-radius: 8px;
            padding: 12px 20px;
            margin: 15px 20px;
            font-size: 0.85rem;
        }
        
        .action-bar {
            background: #f8fafc;
            padding: 15px 20px;
            border-top: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .btn-editar {
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-editar:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(30,60,114,.3);
        }
        
        .btn-editar:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .text-center {
            text-align: center;
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
            <a href="editar-notas.php" class="menu-item active">
                <i class="fas fa-edit"></i>
                <span>Editar Notas</span>
            </a>
            
            <div class="menu-title">RELATÓRIOS</div>
            <a href="boletins.php" class="menu-item">
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
        <div class="top-nav">
            <div style="display: flex; align-items: center; gap: 15px;">
                <button class="btn btn-sm btn-outline-primary" onclick="toggleSidebar()" style="border-radius: 8px;">
                    <i class="fas fa-bars"></i>
                </button>
                <h1 class="page-title">
                    <i class="fas fa-edit me-2"></i>Editar Notas
                </h1>
            </div>
            <div class="user-info d-flex align-items-center gap-3">
                <div class="text-end">
                    <div class="user-name"><?php echo htmlspecialchars($_SESSION['user_nome']); ?></div>
                    <div class="small text-muted">Professor</div>
                </div>
                <div class="user-avatar">
                    <?php echo strtoupper(substr($_SESSION['user_nome'], 0, 1)); ?>
                </div>
            </div>
        </div>
        
        <!-- Mensagens -->
        <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <!-- Períodos Info -->
        <div class="periodo-info">
            <span><i class="fas fa-calendar-alt me-2 text-primary"></i><strong>Períodos <?php echo $ano_atual; ?>:</strong></span>
            <?php for ($t = 1; $t <= 3; $t++): ?>
                <span class="periodo-badge <?php echo isset($periodos_abertos[$t]) ? 'periodo-aberto' : 'periodo-fechado'; ?>">
                    <i class="fas <?php echo isset($periodos_abertos[$t]) ? 'fa-unlock' : 'fa-lock'; ?> me-1"></i>
                    <?php echo $t; ?>º Trimestre: <?php echo isset($periodos_abertos[$t]) ? 'Aberto' : 'Fechado'; ?>
                </span>
            <?php endfor; ?>
        </div>
        
        <!-- Filtros -->
        <div class="filter-section">
            <div class="filter-title">
                <i class="fas fa-filter me-2"></i>Filtrar Notas
            </div>
            
            <form method="GET" action="" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Turma</label>
                    <select class="form-select" name="turma_id" onchange="this.form.submit()" required>
                        <option value="">-- Selecione --</option>
                        <?php while ($turma = $turmas->fetch_assoc()): ?>
                        <option value="<?php echo $turma['id']; ?>" <?php echo $turma_selecionada == $turma['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($turma['nome']); ?> (<?php echo $turma['ano_letivo']; ?>)
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Disciplina</label>
                    <select class="form-select" name="disciplina_id" onchange="this.form.submit()" required <?php echo !$turma_selecionada ? 'disabled' : ''; ?>>
                        <option value="">-- Selecione --</option>
                        <?php if ($disciplinas): while ($disc = $disciplinas->fetch_assoc()): ?>
                        <option value="<?php echo $disc['id']; ?>" <?php echo $disciplina_selecionada == $disc['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($disc['nome']); ?>
                        </option>
                        <?php endwhile; endif; ?>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Trimestre</label>
                    <select class="form-select" name="trimestre" onchange="this.form.submit()" required>
                        <option value="">-- Selecione --</option>
                        <option value="1" <?php echo $trimestre_selecionado == 1 ? 'selected' : ''; ?>>1º Trimestre</option>
                        <option value="2" <?php echo $trimestre_selecionado == 2 ? 'selected' : ''; ?>>2º Trimestre</option>
                        <option value="3" <?php echo $trimestre_selecionado == 3 ? 'selected' : ''; ?>>3º Trimestre</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Ano Letivo</label>
                    <select class="form-select" name="ano" onchange="this.form.submit()">
                        <?php for ($a = $ano_atual; $a >= $ano_atual - 2; $a--): ?>
                        <option value="<?php echo $a; ?>" <?php echo $ano_selecionado == $a ? 'selected' : ''; ?>><?php echo $a; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </form>
        </div>
        
        <!-- Stats Cards -->
        <?php if ($turma_selecionada && $disciplina_selecionada && $trimestre_selecionado && $notas && $notas->num_rows > 0): ?>
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number"><?php echo $stats['total_notas']; ?></div>
                    <div class="stats-label">Total de Notas</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card" style="border-left-color: #28a745;">
                    <div class="stats-number text-success"><?php echo $stats['aprovados']; ?></div>
                    <div class="stats-label">Aprovados</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card" style="border-left-color: #dc3545;">
                    <div class="stats-number text-danger"><?php echo $stats['reprovados']; ?></div>
                    <div class="stats-label">Reprovados</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number"><?php echo number_format($stats['media_geral'] ?? 0, 1); ?></div>
                    <div class="stats-label">Média Geral</div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Tabela de Notas -->
        <?php if ($turma_selecionada && $disciplina_selecionada && $trimestre_selecionado): ?>
            <?php if ($notas && $notas->num_rows > 0): ?>
            <div class="table-card">
                <div class="card-header-custom">
                    <h4>
                        <i class="fas fa-edit"></i>
                        Editar Notas - <?php echo $trimestre_selecionado; ?>º Trimestre
                    </h4>
                    <span class="badge-total">
                        <i class="fas fa-users me-1"></i> <?php echo $notas->num_rows; ?> alunos
                    </span>
                </div>
                
                <form method="POST" action="" id="formEditarNotas">
                    <input type="hidden" name="action" value="editar_notas">
                    <input type="hidden" name="turma_id" value="<?php echo $turma_selecionada; ?>">
                    <input type="hidden" name="disciplina_id" value="<?php echo $disciplina_selecionada; ?>">
                    <input type="hidden" name="trimestre" value="<?php echo $trimestre_selecionado; ?>">
                    
                    <div class="table-responsive">
                        <table class="nota-table">
                            <thead>
                                <tr>
                                    <th style="width: 50px;">#</th>
                                    <th>Aluno</th>
                                    <th style="width: 120px;">Matrícula</th>
                                    <th style="width: 130px;">Avaliação 1</th>
                                    <th style="width: 130px;">Avaliação 2</th>
                                    <th style="width: 130px;">Exame</th>
                                    <th style="width: 80px;">MAC</th>
                                    <th style="width: 80px;">Média</th>
                                    <th style="width: 100px;">Estado</th>
                                    <th style="width: 140px;">Última Edição</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $index = 1;
                                while ($nota = $notas->fetch_assoc()): 
                                    $iniciais = substr($nota['aluno_nome'], 0, 2);
                                    
                                    // Calcular médias baseado nas colunas reais
                                    $av1 = $nota['avaliacao1'] ? (float)$nota['avaliacao1'] : 0;
                                    $av2 = $nota['avaliacao2'] ? (float)$nota['avaliacao2'] : 0;
                                    $exame = $nota['exame'] ? (float)$nota['exame'] : 0;
                                    $mac = ($av1 + $av2) / 2;
                                    $media_final = ($mac + $exame) / 2;
                                    
                                    $estado = $media_final >= 10 ? 'Aprovado' : ($media_final > 0 ? 'Reprovado' : 'Incompleto');
                                    $estadoClass = $estado == 'Aprovado' ? 'estado-aprovado' : ($estado == 'Reprovado' ? 'estado-reprovado' : 'estado-incompleto');
                                    $estadoIcone = $estado == 'Aprovado' ? 'fa-check-circle' : ($estado == 'Reprovado' ? 'fa-times-circle' : 'fa-hourglass-half');
                                    $periodo_aberto = isset($periodos_abertos[$trimestre_selecionado]);
                                ?>
                                    <tr>
                                        <td class="text-center"><?php echo $index++; ?></td>
                                        <td>
                                            <div class="aluno-cell">
                                                <div class="aluno-avatar-small">
                                                    <?php echo strtoupper($iniciais); ?>
                                                </div>
                                                <div>
                                                    <div class="fw-bold"><?php echo htmlspecialchars($nota['aluno_nome']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark">
                                                <i class="fas fa-id-card me-1"></i>
                                                <?php echo htmlspecialchars($nota['numero_matricula']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <input type="number" 
                                                   class="nota-input <?php echo !$periodo_aberto ? 'bg-light' : ''; ?>" 
                                                   name="notas[<?php echo $nota['id']; ?>][avaliacao1]" 
                                                   value="<?php echo $nota['avaliacao1']; ?>"
                                                   min="0" max="20" step="0.1"
                                                   placeholder="0-20"
                                                   onchange="calcularMedia(this, <?php echo $nota['id']; ?>)"
                                                   <?php echo !$periodo_aberto ? 'disabled' : ''; ?>>
                                        </td>
                                        <td>
                                            <input type="number" 
                                                   class="nota-input <?php echo !$periodo_aberto ? 'bg-light' : ''; ?>" 
                                                   name="notas[<?php echo $nota['id']; ?>][avaliacao2]" 
                                                   value="<?php echo $nota['avaliacao2']; ?>"
                                                   min="0" max="20" step="0.1"
                                                   placeholder="0-20"
                                                   onchange="calcularMedia(this, <?php echo $nota['id']; ?>)"
                                                   <?php echo !$periodo_aberto ? 'disabled' : ''; ?>>
                                        </td>
                                        <td>
                                            <input type="number" 
                                                   class="nota-input <?php echo !$periodo_aberto ? 'bg-light' : ''; ?>" 
                                                   name="notas[<?php echo $nota['id']; ?>][exame]" 
                                                   value="<?php echo $nota['exame']; ?>"
                                                   min="0" max="20" step="0.1"
                                                   placeholder="0-20"
                                                   onchange="calcularMedia(this, <?php echo $nota['id']; ?>)"
                                                   <?php echo !$periodo_aberto ? 'disabled' : ''; ?>>
                                        </td>
                                        <td class="text-center">
                                            <span class="media-box" id="mac_<?php echo $nota['id']; ?>">
                                                <?php echo $mac > 0 ? number_format($mac, 1) : '-'; ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <span class="media-box" id="media_<?php echo $nota['id']; ?>">
                                                <?php echo $media_final > 0 ? number_format($media_final, 1) : '-'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="estado-box <?php echo $estadoClass; ?>" id="estado_<?php echo $nota['id']; ?>">
                                                <i class="fas <?php echo $estadoIcone; ?>"></i>
                                                <?php echo $estado; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small>
                                                <i class="fas fa-clock me-1"></i>
                                                <?php echo date('d/m/Y H:i', strtotime($nota['ultima_edicao_em'])); ?>
                                            </small>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Legenda -->
                        <div class="legenda-box">
                            <i class="fas fa-info-circle text-primary me-2"></i>
                            <strong>Sistema de Avaliação:</strong>
                            <span class="ms-3"><i class="fas fa-calculator me-1"></i> MAC = (Av1 + Av2) / 2 | Média = (MAC + Exame) / 2</span>
                            <span class="ms-3"><i class="fas fa-chart-line text-success me-1"></i> Aprovado: ≥ 10 | Reprovado: < 10</span>
                        </div>
                        
                        <!-- Botões -->
                        <div class="action-bar">
                            <div>
                                <i class="fas fa-info-circle text-muted me-2"></i>
                                <small>As alterações serão registradas no histórico de auditoria.</small>
                            </div>
                            <?php if (isset($periodos_abertos[$trimestre_selecionado])): ?>
                            <button type="submit" class="btn-editar" onclick="return confirm('⚠️ Tem certeza que deseja salvar as alterações?')">
                                <i class="fas fa-save me-2"></i>Salvar Alterações
                            </button>
                            <?php else: ?>
                            <button type="button" class="btn-editar" disabled style="opacity: 0.6;">
                                <i class="fas fa-lock me-2"></i>Período Fechado
                            </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
                <?php else: ?>
                <div class="alert alert-info text-center py-5">
                    <i class="fas fa-search fa-3x mb-3"></i>
                    <h5>Nenhuma nota encontrada</h5>
                    <p>Não há notas lançadas para esta turma, disciplina e trimestre.</p>
                    <a href="lancar-notas.php?turma_id=<?php echo $turma_selecionada; ?>&disciplina_id=<?php echo $disciplina_selecionada; ?>&trimestre=<?php echo $trimestre_selecionado; ?>" class="btn btn-primary mt-3">
                        <i class="fas fa-plus-circle me-2"></i>Lançar Notas Agora
                    </a>
                </div>
                <?php endif; ?>
            <?php elseif ($turma_selecionada && $disciplina_selecionada): ?>
            <div class="alert alert-warning text-center py-5">
                <i class="fas fa-hand-pointer fa-3x mb-3"></i>
                <h5>Selecione um trimestre</h5>
                <p>Escolha o trimestre para visualizar as notas.</p>
            </div>
            <?php elseif ($turma_selecionada): ?>
            <div class="alert alert-warning text-center py-5">
                <i class="fas fa-book-open fa-3x mb-3"></i>
                <h5>Selecione uma disciplina</h5>
                <p>Escolha a disciplina que deseja editar.</p>
            </div>
            <?php else: ?>
            <div class="alert alert-info text-center py-5">
                <i class="fas fa-arrow-left fa-3x mb-3"></i>
                <h5>Selecione uma turma</h5>
                <p>Use os filtros acima para encontrar as notas que deseja editar.</p>
            </div>
            <?php endif; ?>
        </div>
        
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        
        <script>
            function calcularMedia(input, notaId) {
                var linha = input.closest('tr');
                var av1 = parseFloat(linha.querySelector('input[name="notas[' + notaId + '][avaliacao1]"]')?.value) || 0;
                var av2 = parseFloat(linha.querySelector('input[name="notas[' + notaId + '][avaliacao2]"]')?.value) || 0;
                var exame = parseFloat(linha.querySelector('input[name="notas[' + notaId + '][exame]"]')?.value) || 0;
                
                var mac = null;
                var media = null;
                var estado = 'Incompleto';
                
                if (av1 > 0 || av2 > 0 || exame > 0) {
                    mac = ((av1 + av2) / 2).toFixed(1);
                    media = ((parseFloat(mac) + exame) / 2).toFixed(1);
                    estado = media >= 10 ? 'Aprovado' : 'Reprovado';
                }
                
                var macSpan = document.getElementById('mac_' + notaId);
                var mediaSpan = document.getElementById('media_' + notaId);
                var estadoSpan = document.getElementById('estado_' + notaId);
                
                if (macSpan) {
                    macSpan.textContent = mac || '-';
                }
                
                if (mediaSpan) {
                    mediaSpan.textContent = media || '-';
                    if (media >= 10) {
                        mediaSpan.style.background = '#d4edda';
                        mediaSpan.style.color = '#155724';
                    } else if (media > 0) {
                        mediaSpan.style.background = '#f8d7da';
                        mediaSpan.style.color = '#721c24';
                    } else {
                        mediaSpan.style.background = '#f8f9fa';
                        mediaSpan.style.color = '#6c757d';
                    }
                }
                
                if (estadoSpan) {
                    estadoSpan.innerHTML = '<i class="fas ' + (estado == 'Aprovado' ? 'fa-check-circle' : (estado == 'Reprovado' ? 'fa-times-circle' : 'fa-hourglass-half')) + '"></i> ' + estado;
                    estadoSpan.className = 'estado-box ' + 
                        (estado == 'Aprovado' ? 'estado-aprovado' : 
                         (estado == 'Reprovado' ? 'estado-reprovado' : 'estado-incompleto'));
                }
                
                validarCampo(input);
            }
            
            function validarCampo(campo) {
                if (!campo) return;
                var valor = parseFloat(campo.value);
                if (campo.value === '') {
                    campo.classList.remove('valida', 'invalida');
                } else if (valor >= 0 && valor <= 20) {
                    campo.classList.add('valida');
                    campo.classList.remove('invalida');
                } else {
                    campo.classList.add('invalida');
                    campo.classList.remove('valida');
                }
            }
            
            document.getElementById('formEditarNotas')?.addEventListener('submit', function(e) {
                var invalidos = document.querySelectorAll('.invalida');
                if (invalidos.length > 0) {
                    e.preventDefault();
                    alert('⚠️ Existem notas inválidas (fora do intervalo 0-20). Por favor, corrija antes de salvar.');
                }
            });
            
            function toggleSidebar() {
                const sidebar = document.querySelector('.sidebar');
                const mainContent = document.querySelector('.main-content');
                sidebar.classList.toggle('hidden');
                mainContent.classList.toggle('sidebar-hidden');
            }
        </script>
    </body>
    </html>