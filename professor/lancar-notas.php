<?php
// professor/lancar-notas.php
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

// Processar salvamento de notas
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'lancar_notas') {
    $turma_id = (int)$_POST['turma_id'];
    $disciplina_id = (int)$_POST['disciplina_id'];
    $trimestre = isset($_POST['trimestre']) ? (int)$_POST['trimestre'] : null;
    $ip = $_SERVER['REMOTE_ADDR'];

    // Verificar permissões: o período deve estar aberto
    $periodo_check = $db->query("SELECT id FROM periodos WHERE ano_letivo = $ano_atual AND trimestre = $trimestre AND status = 'aberto'");
    if ($periodo_check->num_rows == 0) {
        $error = "Não é possível lançar notas para este trimestre porque o período está fechado.";
    }

    if (empty($error)) {
        $alunos = isset($_POST['alunos']) ? $_POST['alunos'] : [];
        $success_count = 0;
        
        foreach ($alunos as $aluno_id) {
            $aluno_id = (int)$aluno_id;
            
            $av1 = isset($_POST['avaliacao1'][$aluno_id]) ? (float)$_POST['avaliacao1'][$aluno_id] : null;
            $av2 = isset($_POST['avaliacao2'][$aluno_id]) ? (float)$_POST['avaliacao2'][$aluno_id] : null;
            $ex = isset($_POST['exame'][$aluno_id]) ? (float)$_POST['exame'][$aluno_id] : null;
            
            if (($av1 !== null && ($av1 < 0 || $av1 > 20)) ||
                ($av2 !== null && ($av2 < 0 || $av2 > 20)) ||
                ($ex !== null && ($ex < 0 || $ex > 20))) {
                $error = "Nota inválida para o aluno $aluno_id (deve estar entre 0 e 20).";
                break;
            }
            
            // Verificar se já existe registro
            $check = $db->query("SELECT id FROM notas WHERE aluno_id = $aluno_id AND disciplina_id = $disciplina_id AND ano_letivo = $ano_atual AND trimestre = $trimestre");
            if ($check->num_rows > 0) {
                // Atualizar
                $av1_val = $av1 !== null ? $av1 : 'NULL';
                $av2_val = $av2 !== null ? $av2 : 'NULL';
                $ex_val = $ex !== null ? $ex : 'NULL';
                $update = $db->query("UPDATE notas SET avaliacao1 = $av1_val, avaliacao2 = $av2_val, exame = $ex_val, ultima_edicao_por = {$_SESSION['user_id']} WHERE aluno_id = $aluno_id AND disciplina_id = $disciplina_id AND ano_letivo = $ano_atual AND trimestre = $trimestre");
                if ($update) {
                    $db->query("INSERT INTO logs_auditoria (usuario_id, acao, tabela, registro_id, ip) VALUES ({$_SESSION['user_id']}, 'EDITAR_NOTA', 'notas', $aluno_id, '$ip')");
                    $success_count++;
                }
            } else {
                // Inserir
                $av1_val = $av1 !== null ? $av1 : 'NULL';
                $av2_val = $av2 !== null ? $av2 : 'NULL';
                $ex_val = $ex !== null ? $ex : 'NULL';
                $insert = $db->query("INSERT INTO notas (aluno_id, disciplina_id, ano_letivo, trimestre, avaliacao1, avaliacao2, exame, ultima_edicao_por) VALUES ($aluno_id, $disciplina_id, $ano_atual, $trimestre, $av1_val, $av2_val, $ex_val, {$_SESSION['user_id']})");
                if ($insert) {
                    $db->query("INSERT INTO logs_auditoria (usuario_id, acao, tabela, registro_id, ip) VALUES ({$_SESSION['user_id']}, 'CRIAR_NOTA', 'notas', $db->insert_id, '$ip')");
                    $success_count++;
                }
            }
        }
        
        if (empty($error)) {
            $message = "$success_count nota(s) salva(s) com sucesso!";
        }
    }
    
    // Redirecionar para evitar reenvio
    header("Location: lancar-notas.php?turma_id=$turma_id&disciplina_id=$disciplina_id&trimestre=$trimestre&msg=" . urlencode($message) . "&err=" . urlencode($error));
    exit();
}

// Se houver mensagens via GET, capturá-las
if (isset($_GET['msg']) && $_GET['msg']) $message = $_GET['msg'];
if (isset($_GET['err']) && $_GET['err']) $error = $_GET['err'];

// Buscar períodos abertos
$periodos_abertos = [];
$query = "SELECT * FROM periodos WHERE status = 'aberto' AND ano_letivo = $ano_atual ORDER BY trimestre";
$result = $db->query($query);
while ($periodo = $result->fetch_assoc()) {
    $periodos_abertos[$periodo['trimestre']] = $periodo;
}

// Buscar turmas do professor
$query = "SELECT DISTINCT t.id, t.nome, t.ano_letivo, t.curso
          FROM atribuicoes a
          INNER JOIN turma_disciplina td ON a.turma_disciplina_id = td.id
          INNER JOIN turmas t ON td.turma_id = t.id
          WHERE a.professor_id = $professor_id AND a.ano_letivo = $ano_atual
          ORDER BY t.nome ASC";
$turmas = $db->query($query);

// Se uma turma foi selecionada, buscar disciplinas
$turma_selecionada = isset($_GET['turma_id']) ? (int)$_GET['turma_id'] : 0;
$disciplina_selecionada = isset($_GET['disciplina_id']) ? (int)$_GET['disciplina_id'] : 0;
$trimestre_selecionado = isset($_GET['trimestre']) ? (int)$_GET['trimestre'] : 1;

$disciplinas = [];
$alunos_notas = [];

if ($turma_selecionada) {
    $query = "SELECT d.id, d.nome, d.codigo
              FROM atribuicoes a
              INNER JOIN turma_disciplina td ON a.turma_disciplina_id = td.id
              INNER JOIN disciplinas d ON td.disciplina_id = d.id
              WHERE a.professor_id = $professor_id 
              AND td.turma_id = $turma_selecionada
              AND a.ano_letivo = $ano_atual
              ORDER BY d.nome ASC";
    $disciplinas = $db->query($query);
}

if ($turma_selecionada && $disciplina_selecionada) {
    // Buscar alunos da turma
    $query = "SELECT a.id, u.nome, a.numero_matricula
              FROM enturmacoes e
              INNER JOIN alunos a ON e.aluno_id = a.id
              INNER JOIN usuarios u ON a.usuario_id = u.id
              WHERE e.turma_id = $turma_selecionada
              ORDER BY u.nome ASC";
    $alunos = $db->query($query);
    
    $alunos_notas = [];
    while ($aluno = $alunos->fetch_assoc()) {
        $query = "SELECT * FROM notas 
                  WHERE aluno_id = {$aluno['id']} 
                  AND disciplina_id = $disciplina_selecionada
                  AND ano_letivo = $ano_atual
                  AND trimestre = $trimestre_selecionado";
        $nota_result = $db->query($query);
        $nota = $nota_result->fetch_assoc();
        
        $alunos_notas[] = [
            'id' => $aluno['id'],
            'nome' => $aluno['nome'],
            'matricula' => $aluno['numero_matricula'],
            'avaliacao1' => $nota['avaliacao1'] ?? '',
            'avaliacao2' => $nota['avaliacao2'] ?? '',
            'exame' => $nota['exame'] ?? ''
        ];
    }
}

$page_title = "Lançar Notas";
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IPOK - Lançar Notas</title>
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
        
        .btn-salvar {
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-salvar:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(30,60,114,.3);
        }
        
        .btn-salvar:disabled {
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
            <h3>Professor</h3>
            <h3><?php echo htmlspecialchars($_SESSION['user_nome']); ?></h3>
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
            <a href="lancar-notas.php" class="menu-item active">
                <i class="fas fa-plus-circle"></i>
                <span>Lançar Notas</span>
            </a>
            <a href="editar-notas.php" class="menu-item">
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
                    <i class="fas fa-plus-circle me-2"></i>Lançar Notas
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
                <i class="fas fa-filter me-2"></i>Selecionar Turma e Disciplina
            </div>
            
            <form method="GET" action="" class="row g-3">
                <div class="col-md-5">
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
                
                <div class="col-md-5">
                    <label class="form-label">Disciplina</label>
                    <select class="form-select" name="disciplina_id" onchange="this.form.submit()" required <?php echo !$turma_selecionada ? 'disabled' : ''; ?>>
                        <option value="">-- Selecione uma disciplina --</option>
                        <?php if ($disciplinas): while ($disc = $disciplinas->fetch_assoc()): ?>
                        <option value="<?php echo $disc['id']; ?>" <?php echo $disciplina_selecionada == $disc['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($disc['nome']); ?>
                        </option>
                        <?php endwhile; endif; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Trimestre</label>
                    <select class="form-select" name="trimestre" onchange="this.form.submit()" required>
                        <option value="1" <?php echo $trimestre_selecionado == 1 ? 'selected' : ''; ?>>1º Trimestre</option>
                        <option value="2" <?php echo $trimestre_selecionado == 2 ? 'selected' : ''; ?>>2º Trimestre</option>
                        <option value="3" <?php echo $trimestre_selecionado == 3 ? 'selected' : ''; ?>>3º Trimestre</option>
                    </select>
                </div>
            </form>
        </div>
        
        <!-- Tabela de Notas -->
        <?php if ($turma_selecionada && $disciplina_selecionada && !empty($alunos_notas)): ?>
        <div class="table-card">
            <div class="card-header-custom">
                <h4>
                    <i class="fas fa-edit"></i>
                    Lançamento de Notas - <?php echo $trimestre_selecionado; ?>º Trimestre
                </h4>
                <span class="badge-total">
                    <i class="fas fa-users me-1"></i> <?php echo count($alunos_notas); ?> alunos
                </span>
            </div>
            
            <form method="POST" action="" id="formNotas">
                <input type="hidden" name="action" value="lancar_notas">
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
                                <th style="width: 120px;">Avaliação 1</th>
                                <th style="width: 120px;">Avaliação 2</th>
                                <th style="width: 120px;">Exame</th>
                                <th style="width: 80px;">MAC</th>
                                <th style="width: 80px;">Média</th>
                                <th style="width: 100px;">Estado</th>
                             </thead>
                            <tbody>
                                <?php foreach ($alunos_notas as $index => $aluno): 
                                    $iniciais = substr($aluno['nome'], 0, 2);
                                    $av1 = $aluno['avaliacao1'] ? (float)$aluno['avaliacao1'] : 0;
                                    $av2 = $aluno['avaliacao2'] ? (float)$aluno['avaliacao2'] : 0;
                                    $ex = $aluno['exame'] ? (float)$aluno['exame'] : 0;
                                    
                                    $mac = ($av1 && $av2) ? ($av1 + $av2) / 2 : 0;
                                    $media = ($mac && $ex) ? ($mac + $ex) / 2 : 0;
                                    
                                    $estado = $media >= 10 ? 'Aprovado' : ($media > 0 ? 'Reprovado' : 'Incompleto');
                                    $estadoClass = $estado == 'Aprovado' ? 'estado-aprovado' : ($estado == 'Reprovado' ? 'estado-reprovado' : 'estado-incompleto');
                                    $estadoIcone = $estado == 'Aprovado' ? 'fa-check-circle' : ($estado == 'Reprovado' ? 'fa-times-circle' : 'fa-hourglass-half');
                                ?>
                                <tr>
                                    <td class="text-center"><?php echo $index + 1; ?></td>
                                    <td>
                                        <div class="aluno-cell">
                                            <div class="aluno-avatar-small">
                                                <?php echo strtoupper($iniciais); ?>
                                            </div>
                                            <div>
                                                <div class="fw-bold"><?php echo htmlspecialchars($aluno['nome']); ?></div>
                                                <input type="hidden" name="alunos[]" value="<?php echo $aluno['id']; ?>">
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark">
                                            <i class="fas fa-id-card me-1"></i>
                                            <?php echo htmlspecialchars($aluno['matricula']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <input type="number" 
                                               class="nota-input" 
                                               name="avaliacao1[<?php echo $aluno['id']; ?>]" 
                                               value="<?php echo $aluno['avaliacao1'] ?: ''; ?>"
                                               min="0" max="20" step="0.1"
                                               placeholder="0-20"
                                               onchange="calcularMedia(this, <?php echo $aluno['id']; ?>)">
                                    </td>
                                    <td>
                                        <input type="number" 
                                               class="nota-input" 
                                               name="avaliacao2[<?php echo $aluno['id']; ?>]" 
                                               value="<?php echo $aluno['avaliacao2'] ?: ''; ?>"
                                               min="0" max="20" step="0.1"
                                               placeholder="0-20"
                                               onchange="calcularMedia(this, <?php echo $aluno['id']; ?>)">
                                    </td>
                                    <td>
                                        <input type="number" 
                                               class="nota-input" 
                                               name="exame[<?php echo $aluno['id']; ?>]" 
                                               value="<?php echo $aluno['exame'] ?: ''; ?>"
                                               min="0" max="20" step="0.1"
                                               placeholder="0-20"
                                               onchange="calcularMedia(this, <?php echo $aluno['id']; ?>)">
                                    </td>
                                    <td class="text-center">
                                        <span class="media-box" id="mac_<?php echo $aluno['id']; ?>">
                                            <?php echo $mac ? number_format($mac, 1) : '-'; ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="media-box" id="media_<?php echo $aluno['id']; ?>">
                                            <?php echo $media ? number_format($media, 1) : '-'; ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="estado-box <?php echo $estadoClass; ?>" id="estado_<?php echo $aluno['id']; ?>">
                                            <i class="fas <?php echo $estadoIcone; ?>"></i>
                                            <?php echo $estado; ?>
                                        </span>
                                    </td>
                                 </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Legenda -->
                    <div class="legenda-box">
                        <i class="fas fa-info-circle text-primary me-2"></i>
                        <strong>Sistema de Avaliação:</strong>
                        <span class="ms-3"><i class="fas fa-calculator me-1"></i> MAC = (Avaliação 1 + Avaliação 2) / 2</span>
                        <span class="ms-3"><i class="fas fa-calculator me-1"></i> Média Final = (MAC + Exame) / 2</span>
                        <span class="ms-3"><i class="fas fa-chart-line text-success me-1"></i> Aprovado: ≥ 10 | Reprovado: < 10</span>
                    </div>
                    
                    <!-- Botões -->
                    <div class="action-bar">
                        <div>
                            <i class="fas fa-info-circle text-muted me-2"></i>
                            <small>As notas ficarão visíveis para os alunos após o fechamento do período.</small>
                        </div>
                        <?php if (isset($periodos_abertos[$trimestre_selecionado])): ?>
                        <button type="submit" class="btn-salvar">
                            <i class="fas fa-save me-2"></i>Salvar Notas
                        </button>
                        <?php else: ?>
                        <button type="button" class="btn-salvar" disabled style="opacity: 0.6;">
                            <i class="fas fa-lock me-2"></i>Período Fechado
                        </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            <?php elseif ($turma_selecionada && $disciplina_selecionada): ?>
            <div class="alert alert-info text-center py-5">
                <i class="fas fa-users fa-3x mb-3 text-primary"></i>
                <h5>Nenhum aluno encontrado</h5>
                <p class="mb-0">Não há alunos enturmados para esta turma.</p>
            </div>
            <?php elseif ($turma_selecionada): ?>
            <div class="alert alert-info text-center py-5">
                <i class="fas fa-book-open fa-3x mb-3 text-primary"></i>
                <h5>Selecione uma disciplina</h5>
                <p class="mb-0">Escolha uma disciplina para começar a lançar as notas.</p>
            </div>
            <?php else: ?>
            <div class="alert alert-info text-center py-5">
                <i class="fas fa-arrow-left fa-3x mb-3 text-primary"></i>
                <h5>Selecione uma turma</h5>
                <p class="mb-0">Comece selecionando uma turma para visualizar as disciplinas disponíveis.</p>
            </div>
            <?php endif; ?>
        </div>
        
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        
        <script>
            function calcularMedia(input, alunoId) {
                var linha = input.closest('tr');
                
                var av1 = parseFloat(linha.querySelector('input[name="avaliacao1[' + alunoId + ']"]')?.value) || 0;
                var av2 = parseFloat(linha.querySelector('input[name="avaliacao2[' + alunoId + ']"]')?.value) || 0;
                var ex = parseFloat(linha.querySelector('input[name="exame[' + alunoId + ']"]')?.value) || 0;
                
                var mac = (av1 > 0 && av2 > 0) ? ((av1 + av2) / 2).toFixed(1) : null;
                var media = (mac !== null && ex > 0) ? ((parseFloat(mac) + ex) / 2).toFixed(1) : null;
                var estado = 'Incompleto';
                
                if (media !== null) {
                    estado = media >= 10 ? 'Aprovado' : 'Reprovado';
                }
                
                var macSpan = document.getElementById('mac_' + alunoId);
                var mediaSpan = document.getElementById('media_' + alunoId);
                var estadoSpan = document.getElementById('estado_' + alunoId);
                
                if (macSpan) {
                    macSpan.textContent = mac || '-';
                    if (mac >= 10) {
                        macSpan.style.background = '#d4edda';
                        macSpan.style.color = '#155724';
                    } else if (mac > 0) {
                        macSpan.style.background = '#f8d7da';
                        macSpan.style.color = '#721c24';
                    } else {
                        macSpan.style.background = '#f8f9fa';
                        macSpan.style.color = '#6c757d';
                    }
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
            
            document.getElementById('formNotas')?.addEventListener('submit', function(e) {
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