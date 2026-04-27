<?php
// professor/dashboard.php
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

$professor_id = null;

// Buscar ID do professor logado
$query = "SELECT id FROM professores WHERE usuario_id = {$_SESSION['user_id']}";
$result = $db->query($query);
if ($result && $result->num_rows > 0) {
    $professor = $result->fetch_assoc();
    $professor_id = $professor['id'];
}

// Estatísticas do professor
$stats = [];

// Total de turmas atribuídas
$query = "SELECT COUNT(DISTINCT t.id) as total 
          FROM atribuicoes a
          INNER JOIN turma_disciplina td ON a.turma_disciplina_id = td.id
          INNER JOIN turmas t ON td.turma_id = t.id
          WHERE a.professor_id = $professor_id AND a.ano_letivo = YEAR(CURDATE())";
$result = $db->query($query);
$stats['turmas'] = $result->fetch_assoc()['total'];

// Total de disciplinas que leciona
$query = "SELECT COUNT(DISTINCT d.id) as total 
          FROM atribuicoes a
          INNER JOIN turma_disciplina td ON a.turma_disciplina_id = td.id
          INNER JOIN disciplinas d ON td.disciplina_id = d.id
          WHERE a.professor_id = $professor_id AND a.ano_letivo = YEAR(CURDATE())";
$result = $db->query($query);
$stats['disciplinas'] = $result->fetch_assoc()['total'];

// Total de alunos que avalia
$query = "SELECT COUNT(DISTINCT e.aluno_id) as total 
          FROM atribuicoes a
          INNER JOIN turma_disciplina td ON a.turma_disciplina_id = td.id
          INNER JOIN enturmacoes e ON td.turma_id = e.turma_id
          WHERE a.professor_id = $professor_id AND a.ano_letivo = YEAR(CURDATE())";
$result = $db->query($query);
$stats['alunos'] = $result->fetch_assoc()['total'];

// Total de notas lançadas no ano
$query = "SELECT COUNT(*) as total 
          FROM notas n
          INNER JOIN turma_disciplina td ON n.disciplina_id = td.disciplina_id
          INNER JOIN atribuicoes a ON td.id = a.turma_disciplina_id
          WHERE a.professor_id = $professor_id AND n.ano_letivo = YEAR(CURDATE())";
$result = $db->query($query);
$stats['notas'] = $result->fetch_assoc()['total'];

// Períodos abertos para lançamento
$query = "SELECT COUNT(*) as total FROM periodos WHERE status = 'aberto' AND ano_letivo = YEAR(CURDATE())";
$result = $db->query($query);
$stats['periodos_abertos'] = $result->fetch_assoc()['total'];

// Buscar turmas do professor para exibição rápida
$query = "SELECT DISTINCT t.id, t.nome, t.ano_letivo, t.curso,
          COUNT(DISTINCT e.aluno_id) as total_alunos
          FROM atribuicoes a
          INNER JOIN turma_disciplina td ON a.turma_disciplina_id = td.id
          INNER JOIN turmas t ON td.turma_id = t.id
          LEFT JOIN enturmacoes e ON t.id = e.turma_id
          WHERE a.professor_id = $professor_id AND a.ano_letivo = YEAR(CURDATE())
          GROUP BY t.id
          ORDER BY t.nome ASC
          LIMIT 5";
$turmas_rapidas = $db->query($query);

// Últimas notas lançadas (adaptado para nova estrutura)
$query = "SELECT n.*, u.nome as aluno_nome, d.nome as disciplina_nome, t.nome as turma_nome
          FROM notas n
          INNER JOIN alunos a ON n.aluno_id = a.id
          INNER JOIN usuarios u ON a.usuario_id = u.id
          INNER JOIN disciplinas d ON n.disciplina_id = d.id
          INNER JOIN enturmacoes e ON a.id = e.aluno_id
          INNER JOIN turmas t ON e.turma_id = t.id
          INNER JOIN atribuicoes atr ON atr.professor_id = $professor_id 
              AND atr.ano_letivo = n.ano_letivo
          INNER JOIN turma_disciplina td ON atr.turma_disciplina_id = td.id 
              AND td.disciplina_id = n.disciplina_id
          WHERE n.ultima_edicao_por = {$_SESSION['user_id']}
          ORDER BY n.ultima_edicao_em DESC
          LIMIT 10";
$ultimas_notas = $db->query($query);

$page_title = "Dashboard Professor";
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IPOK - Professor Dashboard</title>
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
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,.05);
            transition: all 0.3s;
            margin-bottom: 20px;
            border-left: 4px solid var(--primary-blue);
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(30,60,114,.1);
        }
        
        .stats-icon {
            width: 50px;
            height: 50px;
            background: rgba(30,60,114,.1);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-blue);
            font-size: 1.5rem;
            margin-bottom: 15px;
        }
        
        .stats-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-blue);
            line-height: 1.2;
        }
        
        .stats-label {
            color: #6c757d;
            font-size: .9rem;
            text-transform: uppercase;
            letter-spacing: .5px;
        }
        
        /* Turma Card */
        .turma-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,.05);
            transition: all 0.3s;
            margin-bottom: 20px;
            border: 1px solid rgba(0,0,0,.05);
        }
        
        .turma-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(30,60,114,.15);
        }
        
        .turma-card h4 {
            color: var(--primary-blue);
            font-size: 1.2rem;
            margin-bottom: 10px;
        }
        
        .turma-info {
            display: flex;
            gap: 15px;
            margin-top: 15px;
        }
        
        .turma-info-item {
            text-align: center;
            background: #f8f9fa;
            padding: 8px 15px;
            border-radius: 10px;
            flex: 1;
        }
        
        .turma-info-number {
            font-weight: 700;
            color: var(--primary-blue);
            font-size: 1.2rem;
        }
        
        .turma-info-label {
            font-size: .7rem;
            color: #6c757d;
            text-transform: uppercase;
        }
        
        .btn-turma {
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 8px;
            font-size: .9rem;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }
        
        .btn-turma:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(30,60,114,.3);
            color: white;
        }
        
        /* Tabelas */
        .table-container {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,.05);
            margin-bottom: 25px;
        }
        
        .table-title {
            color: var(--primary-blue);
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .nota-badge {
            padding: 3px 8px;
            border-radius: 20px;
            font-size: .75rem;
            font-weight: 600;
        }
        
        .nota-alta { background: #d4edda; color: #155724; }
        .nota-media { background: #fff3cd; color: #856404; }
        .nota-baixa { background: #f8d7da; color: #721c24; }
        
        .info-box {
            background: #e6f0fa;
            border-left: 4px solid var(--primary-blue);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .periodo-badge {
            display: inline-block;
            padding: 5px 10px;
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
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <img src="../assets/img/logo.png" alt="IPOK Logo">
            </div>
            <h3>IPOK Professor</h3>
            <p><?php echo htmlspecialchars($_SESSION['user_nome']); ?></p>
        </div>
        
        <div class="sidebar-menu">
            <div class="menu-title">PRINCIPAL</div>
            <a href="dashboard.php" class="menu-item active">
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
                <?php if ($stats['periodos_abertos'] > 0): ?>
                <span class="badge bg-success"><?php echo $stats['periodos_abertos']; ?></span>
                <?php endif; ?>
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
                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                </h1>
            </div>
            <div class="user-info">
                <div class="user-details text-end">
                    <div class="user-name"><?php echo htmlspecialchars($_SESSION['user_nome']); ?></div>
                    <div class="user-role small text-muted">Professor</div>
                </div>
                <div class="user-avatar">
                    <?php echo strtoupper(substr($_SESSION['user_nome'], 0, 1)); ?>
                </div>
            </div>
        </div>
        
        <!-- Info Box - RN05 e RN08 -->
        <div class="info-box">
            <i class="fas fa-info-circle me-2 text-primary"></i>
            <strong>Regras de Negócio (RN05 e RN08):</strong>
            <ul class="mb-0 mt-2">
                <li>Você só pode lançar notas para as turmas e disciplinas que estão atribuídas a si (RN08)</li>
                <li>Períodos <span class="periodo-badge periodo-aberto">abertos</span> permitem lançamento/edição de notas</li>
                <li>Períodos <span class="periodo-badge periodo-fechado">fechados</span> as notas ficam visíveis apenas para alunos (RN07)</li>
            </ul>
        </div>
        
        <!-- Stats Cards -->
        <div class="row g-4">
            <div class="col-xl-3 col-md-6">
                <div class="stats-card">
                    <div class="stats-icon">
                        <i class="fas fa-chalkboard"></i>
                    </div>
                    <div class="stats-number"><?php echo $stats['turmas']; ?></div>
                    <div class="stats-label">Minhas Turmas</div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6">
                <div class="stats-card">
                    <div class="stats-icon">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="stats-number"><?php echo $stats['disciplinas']; ?></div>
                    <div class="stats-label">Disciplinas</div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6">
                <div class="stats-card">
                    <div class="stats-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stats-number"><?php echo $stats['alunos']; ?></div>
                    <div class="stats-label">Meus Alunos</div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6">
                <div class="stats-card">
                    <div class="stats-icon">
                        <i class="fas fa-star"></i>
                    </div>
                    <div class="stats-number"><?php echo $stats['notas']; ?></div>
                    <div class="stats-label">Notas Lançadas</div>
                </div>
            </div>
        </div>
        
        <!-- Minhas Turmas (Acesso Rápido) -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="table-container">
                    <div class="table-title">
                        <span><i class="fas fa-clock me-2"></i>Minhas Turmas (Acesso Rápido)</span>
                        <a href="minhas-turmas.php" class="btn btn-sm btn-outline-primary">Ver Todas</a>
                    </div>
                    
                    <div class="row">
                        <?php if ($turmas_rapidas && $turmas_rapidas->num_rows > 0): ?>
                            <?php while ($turma = $turmas_rapidas->fetch_assoc()): ?>
                            <div class="col-md-4">
                                <div class="turma-card">
                                    <h4>
                                        <i class="fas fa-chalkboard me-2"></i>
                                        <?php echo htmlspecialchars($turma['nome']); ?>
                                    </h4>
                                    <p class="text-muted small mb-3">
                                        <i class="fas fa-calendar me-1"></i> Ano: <?php echo $turma['ano_letivo']; ?>
                                        <?php if ($turma['curso']): ?>
                                        <br><i class="fas fa-graduation-cap me-1"></i> <?php echo htmlspecialchars($turma['curso']); ?>
                                        <?php endif; ?>
                                    </p>
                                    
                                    <div class="turma-info">
                                        <div class="turma-info-item">
                                            <div class="turma-info-number"><?php echo $turma['total_alunos']; ?></div>
                                            <div class="turma-info-label">Alunos</div>
                                        </div>
                                        <div class="turma-info-item">
                                            <div class="turma-info-number">
                                                <?php
                                                // Contar disciplinas desta turma que o professor leciona
                                                $disc_query = "SELECT COUNT(DISTINCT d.id) as total
                                                             FROM atribuicoes a
                                                             INNER JOIN turma_disciplina td ON a.turma_disciplina_id = td.id
                                                             INNER JOIN disciplinas d ON td.disciplina_id = d.id
                                                             WHERE a.professor_id = $professor_id 
                                                             AND td.turma_id = {$turma['id']}
                                                             AND a.ano_letivo = YEAR(CURDATE())";
                                                $disc_result = $db->query($disc_query);
                                                echo $disc_result->fetch_assoc()['total'];
                                                ?>
                                            </div>
                                            <div class="turma-info-label">Disciplinas</div>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-3 text-center">
                                        <a href="lancar-notas.php?turma_id=<?php echo $turma['id']; ?>" class="btn-turma btn-sm">
                                            <i class="fas fa-plus-circle me-1"></i>Lançar Notas
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="col-12 text-center py-4">
                                <i class="fas fa-info-circle me-2 text-muted"></i>
                                Nenhuma turma atribuída para o ano letivo corrente.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Últimas Notas Lançadas (adaptado para nova estrutura) -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="table-container">
                    <div class="table-title">
                        <span><i class="fas fa-history me-2"></i>Últimas Notas Lançadas</span>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Data/Hora</th>
                                    <th>Aluno</th>
                                    <th>Turma</th>
                                    <th>Disciplina</th>
                                    <th>Trimestre</th>
                                    <th>Nota</th>
                                    <th>Estado</th>
                                 </thead>
                            <tbody>
                                <?php if ($ultimas_notas && $ultimas_notas->num_rows > 0): ?>
                                    <?php while ($nota = $ultimas_notas->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <small><?php echo date('d/m/Y H:i', strtotime($nota['ultima_edicao_em'])); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($nota['aluno_nome']); ?></td>
                                        <td><?php echo htmlspecialchars($nota['turma_nome']); ?></td>
                                        <td><?php echo htmlspecialchars($nota['disciplina_nome']); ?></td>
                                        <td><?php echo $nota['trimestre']; ?>º Trim</td>
                                        <td>
                                            <?php if ($nota['nota_trimestre'] !== null): ?>
                                                <span class="nota-badge 
                                                    <?php 
                                                    if ($nota['nota_trimestre'] >= 14) echo 'nota-alta';
                                                    elseif ($nota['nota_trimestre'] >= 10) echo 'nota-media';
                                                    else echo 'nota-baixa';
                                                    ?>">
                                                    <?php echo number_format($nota['nota_trimestre'], 1); ?>
                                                </span>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                         </td>
                                        <td>
                                            <?php if ($nota['estado']): ?>
                                                <span class="badge <?php echo $nota['estado'] == 'Aprovado' ? 'bg-success' : 'bg-danger'; ?>">
                                                    <?php echo $nota['estado']; ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Incompleto</span>
                                            <?php endif; ?>
                                         </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4">
                                            <i class="fas fa-info-circle me-2 text-muted"></i>
                                            Nenhuma nota lançada recentemente.
                                         </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Calendário de Períodos -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="table-container">
                    <div class="table-title">
                        <span><i class="fas fa-calendar-alt me-2"></i>Períodos Letivos <?php echo date('Y'); ?></span>
                    </div>
                    
                    <div class="row">
                        <?php
                        $periodos_query = "SELECT * FROM periodos WHERE ano_letivo = YEAR(CURDATE()) ORDER BY trimestre ASC";
                        $periodos = $db->query($periodos_query);
                        
                        while ($periodo = $periodos->fetch_assoc()):
                        ?>
                        <div class="col-md-4">
                            <div class="card mb-3">
                                <div class="card-header <?php echo $periodo['status'] == 'aberto' ? 'bg-success' : 'bg-secondary'; ?> text-white">
                                    <strong><?php echo $periodo['trimestre']; ?>º Trimestre <?php echo $periodo['ano_letivo']; ?></strong>
                                </div>
                                <div class="card-body">
                                    <p class="mb-1">
                                        <i class="fas fa-calendar-plus me-2"></i>
                                        Início: <?php echo $periodo['data_inicio'] ? date('d/m/Y', strtotime($periodo['data_inicio'])) : 'Não definido'; ?>
                                    </p>
                                    <p class="mb-1">
                                        <i class="fas fa-calendar-times me-2"></i>
                                        Fim: <?php echo $periodo['data_fim'] ? date('d/m/Y', strtotime($periodo['data_fim'])) : 'Não definido'; ?>
                                    </p>
                                    <p class="mb-0">
                                        <span class="badge <?php echo $periodo['status'] == 'aberto' ? 'bg-success' : 'bg-danger'; ?>">
                                            <?php echo $periodo['status'] == 'aberto' ? 'Aberto para lançamentos' : 'Fechado'; ?>
                                        </span>
                                    </p>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
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