<?php
// aluno/dashboard.php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

// Verificar se é aluno
if (!isLoggedIn() || !isAluno()) {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Buscar ID do aluno logado com verificação
$query = "SELECT id FROM alunos WHERE usuario_id = ?";
$stmt = $db->prepare($query);
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

if (!$result || $result->num_rows === 0) {
    die("Erro: Aluno não encontrado. Por favor, contacte o administrador.");
}

$aluno = $result->fetch_assoc();
$aluno_id = $aluno['id'];
$stmt->close();

// =============================================
// ESTATÍSTICAS DO ALUNO (adaptado para nota_trimestre)
// =============================================

// Média geral (apenas notas lançadas)
$query = "SELECT AVG(n.nota_trimestre) as media_geral 
          FROM notas n
          WHERE n.aluno_id = ? AND n.nota_trimestre IS NOT NULL";
$stmt = $db->prepare($query);
$stmt->bind_param('i', $aluno_id);
$stmt->execute();
$result = $stmt->get_result();
$stats['media_geral'] = round($result->fetch_assoc()['media_geral'] ?? 0, 1);
$stmt->close();

// Total de disciplinas com notas
$query = "SELECT COUNT(DISTINCT n.disciplina_id) as total_disciplinas 
          FROM notas n
          WHERE n.aluno_id = ?";
$stmt = $db->prepare($query);
$stmt->bind_param('i', $aluno_id);
$stmt->execute();
$result = $stmt->get_result();
$stats['disciplinas'] = $result->fetch_assoc()['total_disciplinas'];
$stmt->close();

// Total de trimestres com notas
$query = "SELECT COUNT(DISTINCT CONCAT(n.ano_letivo, n.trimestre)) as total_periodos 
          FROM notas n
          WHERE n.aluno_id = ?";
$stmt = $db->prepare($query);
$stmt->bind_param('i', $aluno_id);
$stmt->execute();
$result = $stmt->get_result();
$stats['periodos'] = $result->fetch_assoc()['total_periodos'];
$stmt->close();

// Aproveitamento (aprovados vs total)
$query = "SELECT 
          COUNT(CASE WHEN n.estado = 'Aprovado' THEN 1 END) as aprovados,
          COUNT(*) as total
          FROM notas n
          WHERE n.aluno_id = ? AND n.estado IS NOT NULL";
$stmt = $db->prepare($query);
$stmt->bind_param('i', $aluno_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();
$stats['aprovados'] = $row['aprovados'] ?? 0;
$stats['total_notas'] = $row['total'] ?? 0;
$stats['aproveitamento'] = $stats['total_notas'] > 0 ? round(($stats['aprovados'] / $stats['total_notas']) * 100, 1) : 0;

// =============================================
// ÚLTIMAS NOTAS (apenas nota_trimestre)
// =============================================
$query = "SELECT n.nota_trimestre, n.trimestre, n.ano_letivo, n.estado, 
          d.nome as disciplina, d.codigo as disciplina_codigo
          FROM notas n
          INNER JOIN disciplinas d ON n.disciplina_id = d.id
          WHERE n.aluno_id = ?
          ORDER BY n.ano_letivo DESC, n.trimestre DESC, n.id DESC
          LIMIT 10";
$stmt = $db->prepare($query);
$stmt->bind_param('i', $aluno_id);
$stmt->execute();
$ultimas_notas = $stmt->get_result();
$stmt->close();

// =============================================
// INFORMAÇÕES DA TURMA ATUAL (simplificado)
// =============================================
$query = "SELECT t.id, t.nome as turma_nome, t.ano_letivo, t.curso
          FROM enturmacoes e
          INNER JOIN turmas t ON e.turma_id = t.id
          WHERE e.aluno_id = ? AND t.ano_letivo = YEAR(CURDATE())
          LIMIT 1";
$stmt = $db->prepare($query);
$stmt->bind_param('i', $aluno_id);
$stmt->execute();
$turma_atual = $stmt->get_result()->fetch_assoc();
$stmt->close();

// =============================================
// NOTAS POR TRIMESTRE (EVOLUÇÃO)
// =============================================
$evolucao = [];
for ($tri = 1; $tri <= 3; $tri++) {
    $query = "SELECT AVG(n.nota_trimestre) as media 
              FROM notas n
              WHERE n.aluno_id = ? AND n.trimestre = ? AND n.nota_trimestre IS NOT NULL";
    $stmt = $db->prepare($query);
    $stmt->bind_param('ii', $aluno_id, $tri);
    $stmt->execute();
    $result = $stmt->get_result();
    $evolucao[$tri] = round($result->fetch_assoc()['media'] ?? 0, 1);
    $stmt->close();
}

// =============================================
// MELHOR E PIOR DISCIPLINA
// =============================================
$query = "SELECT d.nome as disciplina, n.nota_trimestre as media_final 
          FROM notas n
          INNER JOIN disciplinas d ON n.disciplina_id = d.id
          WHERE n.aluno_id = ? AND n.nota_trimestre IS NOT NULL
          ORDER BY n.nota_trimestre DESC LIMIT 1";
$stmt = $db->prepare($query);
$stmt->bind_param('i', $aluno_id);
$stmt->execute();
$melhor = $stmt->get_result()->fetch_assoc();
$stmt->close();

$query = "SELECT d.nome as disciplina, n.nota_trimestre as media_final 
          FROM notas n
          INNER JOIN disciplinas d ON n.disciplina_id = d.id
          WHERE n.aluno_id = ? AND n.nota_trimestre IS NOT NULL
          ORDER BY n.nota_trimestre ASC LIMIT 1";
$stmt = $db->prepare($query);
$stmt->bind_param('i', $aluno_id);
$stmt->execute();
$pior = $stmt->get_result()->fetch_assoc();
$stmt->close();

$page_title = "Meu Dashboard";
?>
<!DOCTYPE html>
<html lang="pt">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IPOK - Aluno Dashboard</title>
    <link rel="shortcut icon" href="../assets/img/logo.png" type="image/x-icon">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        /* (TODO O SEU CSS ORIGINAL PERMANECE EXATAMENTE IGUAL) */
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
            box-shadow: 2px 0 10px rgba(0, 0, 0, .1);
        }

        .sidebar.hidden {
            transform: translateX(-100%);
        }

        .sidebar-header {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, .1);
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

        .sidebar-menu {
            padding: 20px 0;
        }

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
            background: rgba(255, 255, 255, .2);
            transform: translateX(5px);
        }

        .sidebar-menu .menu-item i {
            width: 30px;
            font-size: 1.2rem;
        }

        .sidebar-menu .menu-item span {
            flex: 1;
        }

        .main-content {
            margin-left: var(--sidebar-width);
            padding: 20px;
            transition: all .3s ease;
            min-height: 100vh;
        }

        .main-content.sidebar-hidden {
            margin-left: 0;
        }

        .top-nav {
            background: white;
            padding: 15px 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, .05);
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .top-nav .page-title {
            color: var(--primary-blue);
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0;
        }

        .top-nav .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .top-nav .user-info .user-avatar {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }

        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, .05);
            transition: all 0.3s;
            margin-bottom: 20px;
            border-left: 4px solid var(--primary-blue);
        }

        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(30, 60, 114, .1);
        }

        .stats-icon {
            width: 50px;
            height: 50px;
            background: rgba(30, 60, 114, .1);
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

        .info-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, .05);
            margin-bottom: 20px;
        }

        .info-card h4 {
            color: var(--primary-blue);
            font-size: 1.2rem;
            margin-bottom: 15px;
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 10px;
        }

        .table-container {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, .05);
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

        .info-box {
            background: #e6f0fa;
            border-left: 4px solid var(--primary-blue);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .progress {
            height: 10px;
            border-radius: 5px;
        }

        .progress-bar {
            background: linear-gradient(90deg, var(--primary-blue), var(--secondary-blue));
        }

        .destaque-card {
            background: linear-gradient(135deg, #f8fafc, #ffffff);
            border-radius: 12px;
            padding: 15px;
            text-align: center;
            transition: all 0.3s;
        }

        .destaque-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, .1);
        }

        .destaque-numero {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-blue);
        }

        .destaque-label {
            font-size: .8rem;
            color: #6c757d;
        }

        .badge-terminal {
            background: #ffc107;
            color: #856404;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: .7rem;
            font-weight: 600;
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
            <h3>IPOK Aluno</h3>
            <p><?php echo htmlspecialchars($_SESSION['user_nome']); ?></p>
        </div>

        <div class="sidebar-menu">
            <div class="menu-title">PRINCIPAL</div>
            <a href="dashboard.php" class="menu-item active">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>

            <div class="menu-title">NOTAS</div>
            <a href="minhas-notas.php" class="menu-item">
                <i class="fas fa-chart-line"></i>
                <span>Minhas Notas</span>
            </a>
            <a href="boletim.php" class="menu-item">
                <i class="fas fa-file-alt"></i>
                <span>Boletim</span>
            </a>
            <a href="historico.php" class="menu-item">
                <i class="fas fa-history"></i>
                <span>Histórico Escolar</span>
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
                    <div class="user-role small text-muted">Aluno</div>
                </div>
                <div class="user-avatar">
                    <?php echo strtoupper(substr($_SESSION['user_nome'], 0, 1)); ?>
                </div>
            </div>
        </div>

        <!-- Info Box - RN07 -->
        <div class="info-box">
            <i class="fas fa-info-circle me-2 text-primary"></i>
            <strong>Regra de Negócio (RN07):</strong> Você está visualizando apenas notas de períodos já fechados pela secretaria.
        </div>

        <!-- Stats Cards -->
        <div class="row g-4">
            <div class="col-xl-3 col-md-6">
                <div class="stats-card">
                    <div class="stats-icon">
                        <i class="fas fa-star"></i>
                    </div>
                    <div class="stats-number"><?php echo $stats['media_geral']; ?></div>
                    <div class="stats-label">Média Geral</div>
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
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="stats-number"><?php echo $stats['periodos']; ?></div>
                    <div class="stats-label">Períodos Avaliados</div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="stats-card">
                    <div class="stats-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stats-number"><?php echo $stats['aproveitamento']; ?>%</div>
                    <div class="stats-label">Aproveitamento</div>
                </div>
            </div>
        </div>

        <!-- Turma Atual e Progresso -->
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="info-card">
                    <h4><i class="fas fa-chalkboard me-2"></i>Minha Turma Atual</h4>
                    <?php if ($turma_atual): ?>
                        <p><strong>Turma:</strong> <?php echo htmlspecialchars($turma_atual['turma_nome']); ?></p>
                        <p><strong>Ano Letivo:</strong> <?php echo $turma_atual['ano_letivo']; ?></p>
                        <?php if ($turma_atual['curso']): ?>
                            <p><strong>Curso:</strong> <?php echo htmlspecialchars($turma_atual['curso']); ?></p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="text-muted">Não enturmado para o ano letivo corrente.</p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-6">
                <div class="info-card">
                    <h4><i class="fas fa-chart-line me-2"></i>Evolução por Trimestre</h4>
                    <canvas id="evolucaoChart" style="height: 200px;"></canvas>
                </div>
            </div>
        </div>

        <!-- Melhor e Pior Disciplina -->
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="info-card">
                    <h4><i class="fas fa-trophy me-2 text-warning"></i>Melhor Desempenho</h4>
                    <?php if ($melhor): ?>
                        <div class="destaque-card">
                            <div class="destaque-numero"><?php echo $melhor['media_final']; ?></div>
                            <div class="destaque-label">Média</div>
                            <div class="mt-2 fw-bold"><?php echo htmlspecialchars($melhor['disciplina']); ?></div>
                        </div>
                    <?php else: ?>
                        <p class="text-muted text-center py-4">Ainda sem notas registradas.</p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-6">
                <div class="info-card">
                    <h4><i class="fas fa-chart-line me-2 text-danger"></i>Área de Melhoria</h4>
                    <?php if ($pior): ?>
                        <div class="destaque-card">
                            <div class="destaque-numero"><?php echo $pior['media_final']; ?></div>
                            <div class="destaque-label">Média</div>
                            <div class="mt-2 fw-bold"><?php echo htmlspecialchars($pior['disciplina']); ?></div>
                        </div>
                    <?php else: ?>
                        <p class="text-muted text-center py-4">Ainda sem notas registradas.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Últimas Notas (simplificado) -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="table-container">
                    <div class="table-title">
                        <span><i class="fas fa-history me-2"></i>Últimas Notas</span>
                        <a href="minhas-notas.php" class="btn btn-sm btn-outline-primary">Ver Todas</a>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Disciplina</th>
                                    <th>Trimestre</th>
                                    <th>Nota</th>
                                    <th>Estado</th>
                                </thead>
                                <tbody>
                                    <?php if ($ultimas_notas && $ultimas_notas->num_rows > 0): ?>
                                        <?php while ($nota = $ultimas_notas->fetch_assoc()): 
                                            $media = $nota['nota_trimestre'] ?? 0;
                                            $classe_nota = 'nota-media';
                                            if ($media >= 14) $classe_nota = 'nota-alta';
                                            elseif ($media < 10 && $media > 0) $classe_nota = 'nota-baixa';
                                        ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($nota['disciplina']); ?></td>
                                                <td><?php echo $nota['trimestre']; ?>º Trimestre</td>
                                                <td>
                                                    <span class="nota-badge <?php echo $classe_nota; ?>">
                                                        <?php echo $media ? number_format($media, 1) : '-'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($nota['estado'] == 'Aprovado'): ?>
                                                        <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Aprovado</span>
                                                    <?php elseif ($nota['estado'] == 'Reprovado'): ?>
                                                        <span class="badge bg-danger"><i class="fas fa-times-circle me-1"></i>Reprovado</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary"><i class="fas fa-hourglass-half me-1"></i>Pendente</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-4">
                                                <i class="fas fa-info-circle me-2"></i>
                                                Nenhuma nota disponível. As notas aparecerão após o fechamento dos períodos.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Gráfico de Evolução por Trimestre
        const evolCtx = document.getElementById('evolucaoChart').getContext('2d');
        new Chart(evolCtx, {
            type: 'line',
            data: {
                labels: ['1º Trimestre', '2º Trimestre', '3º Trimestre'],
                datasets: [{
                    label: 'Média por Trimestre',
                    data: [
                        <?php echo $evolucao[1]; ?>,
                        <?php echo $evolucao[2]; ?>,
                        <?php echo $evolucao[3]; ?>
                    ],
                    borderColor: '#1e3c72',
                    backgroundColor: 'rgba(30, 60, 114, 0.1)',
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#1e3c72',
                    pointBorderColor: '#fff',
                    pointRadius: 6,
                    pointHoverRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 20,
                        title: {
                            display: true,
                            text: 'Média'
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Média: ' + context.raw.toFixed(1);
                            }
                        }
                    }
                }
            }
        });

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