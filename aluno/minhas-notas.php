<?php
// aluno/consultar_notas.php
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

// Buscar ID do aluno logado
$query = "SELECT id FROM alunos WHERE usuario_id = {$_SESSION['user_id']}";
$result = $db->query($query);
$aluno = $result->fetch_assoc();
$aluno_id = $aluno['id'];

// Buscar informações da turma atual do aluno (apenas para exibição)
$query = "SELECT t.id, t.nome as turma_nome, t.ano_letivo, t.curso
          FROM enturmacoes e
          INNER JOIN turmas t ON e.turma_id = t.id
          WHERE e.aluno_id = $aluno_id AND t.ano_letivo = YEAR(CURDATE())
          LIMIT 1";
$turma_atual = $db->query($query)->fetch_assoc();

// Filtros
$ano_selecionado = isset($_GET['ano']) ? (int)$_GET['ano'] : date('Y');
$trimestre_selecionado = isset($_GET['trimestre']) ? (int)$_GET['trimestre'] : 0;

// Buscar anos disponíveis (das notas do aluno)
$query = "SELECT DISTINCT n.ano_letivo FROM notas n WHERE n.aluno_id = $aluno_id ORDER BY n.ano_letivo DESC";
$anos = $db->query($query);

// Buscar notas com filtros (estrutura simplificada: apenas nota_trimestre)
$query = "SELECT n.nota_trimestre, n.trimestre, n.ano_letivo, n.estado,
          d.nome as disciplina, d.codigo as disciplina_codigo 
          FROM notas n 
          INNER JOIN disciplinas d ON n.disciplina_id = d.id
          WHERE n.aluno_id = $aluno_id";
if ($ano_selecionado) {
    $query .= " AND n.ano_letivo = $ano_selecionado";
}
if ($trimestre_selecionado) {
    $query .= " AND n.trimestre = $trimestre_selecionado";
}
$query .= " ORDER BY n.ano_letivo DESC, n.trimestre ASC, d.nome ASC";
$notas = $db->query($query);

// Agrupar por ano/trimestre para melhor visualização
$notas_agrupadas = [];
while ($nota = $notas->fetch_assoc()) {
    $chave = $nota['ano_letivo'] . '_' . $nota['trimestre'];
    if (!isset($notas_agrupadas[$chave])) {
        $notas_agrupadas[$chave] = [
            'ano' => $nota['ano_letivo'],
            'trimestre' => $nota['trimestre'],
            'disciplinas' => []
        ];
    }
    // Garantir que disciplina_codigo existe
    if (!isset($nota['disciplina_codigo'])) {
        $nota['disciplina_codigo'] = '';
    }
    $notas_agrupadas[$chave]['disciplinas'][] = $nota;
}

// Calcular médias por trimestre (usando nota_trimestre)
$medias_trimestre = [];
foreach ($notas_agrupadas as $grupo) {
    $soma = 0;
    $count = 0;
    foreach ($grupo['disciplinas'] as $nota) {
        if ($nota['nota_trimestre'] !== null && $nota['nota_trimestre'] > 0) {
            $soma += $nota['nota_trimestre'];
            $count++;
        }
    }
    $medias_trimestre[$grupo['ano']][$grupo['trimestre']] = $count > 0 ? round($soma / $count, 1) : 0;
}

// Estatísticas gerais
$total_notas = 0;
$aprovadas = 0;
$reprovadas = 0;
foreach ($notas_agrupadas as $grupo) {
    foreach ($grupo['disciplinas'] as $nota) {
        $total_notas++;
        if ($nota['estado'] === 'Aprovado') $aprovadas++;
        elseif ($nota['estado'] === 'Reprovado') $reprovadas++;
    }
}
$aproveitamento = $total_notas > 0 ? round(($aprovadas / $total_notas) * 100, 1) : 0;

$page_title = "Minhas Notas";
?>
<!DOCTYPE html>
<html lang="pt">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IPOK - Minhas Notas</title>
    <link rel="shortcut icon" href="../assets/img/logo.png" type="image/x-icon">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

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
            width: 60px;
            height: 60px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            color: var(--primary-blue);
            font-size: 2rem;
        }

        .sidebar-menu {
            padding: 20px 0;
        }

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
            background: rgba(255, 255, 255, .2);
            transform: translateX(5px);
        }

        .sidebar-menu .menu-item i {
            width: 30px;
            font-size: 1.2rem;
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

        .user-avatar {
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
            box-shadow: 0 5px 20px rgba(0, 0, 0, .05);
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

        .filter-section {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, .05);
        }

        .trimestre-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0, 0, 0, .05);
            margin-bottom: 25px;
            border: 1px solid rgba(0, 0, 0, .05);
        }

        .trimestre-header {
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            color: white;
            padding: 15px 20px;
            font-weight: 600;
            font-size: 1.1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .trimestre-media {
            background: rgba(255, 255, 255, 0.2);
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
        }

        .disciplina-row {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            border-bottom: 1px solid #e9ecef;
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

        .disciplina-detalhe {
            flex: 0.8;
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
            padding: 12px 15px;
            margin-bottom: 20px;
            font-size: 0.85rem;
        }

        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .main-content {
                margin-left: 0;
            }
            .disciplina-row {
                flex-wrap: wrap;
                gap: 10px;
            }
            .disciplina-nome {
                flex: 100%;
            }
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
            <h3>IPOK Aluno</h3>
            <p><?php echo htmlspecialchars($_SESSION['user_nome']); ?></p>
        </div>

        <div class="sidebar-menu">
            <div class="menu-title">PRINCIPAL</div>
            <a href="dashboard.php" class="menu-item">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>

            <div class="menu-title">NOTAS</div>
            <a href="minhas-notas.php" class="menu-item active">
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
                <button class="btn btn-sm btn-outline-primary" onclick="toggleSidebar()" style="border-radius: 10px;">
                    <i class="fas fa-bars"></i>
                </button>
                <h1 class="page-title">
                    <i class="fas fa-chart-line me-2"></i>Minhas Notas
                </h1>
            </div>
            <div class="user-info d-flex align-items-center gap-3">
                <div class="text-end">
                    <div class="fw-bold"><?php echo htmlspecialchars($_SESSION['user_nome']); ?></div>
                    <div class="small text-muted">Aluno</div>
                </div>
                <div class="user-avatar">
                    <?php echo strtoupper(substr($_SESSION['user_nome'], 0, 1)); ?>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-star"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $total_notas; ?></h3>
                    <p>Total de Notas</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $aprovadas; ?></h3>
                    <p>Aprovações</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $reprovadas; ?></h3>
                    <p>Reprovações</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $aproveitamento; ?>%</h3>
                    <p>Aproveitamento</p>
                </div>
            </div>
        </div>

        <!-- Info Box - RN07 -->
        <div class="info-box">
            <i class="fas fa-info-circle me-2 text-primary"></i>
            <strong>Regra de Negócio (RN07):</strong> Você está visualizando apenas notas de períodos já fechados pela secretaria.
        </div>

        <!-- Legenda do Sistema de Avaliação (simplificada) -->
        <div class="legenda-box">
            <i class="fas fa-info-circle text-primary me-2"></i>
            <strong>Sistema de Avaliação:</strong>
            <span class="ms-3"><i class="fas fa-calculator me-1"></i> Nota única por trimestre</span>
            <span class="ms-3"><i class="fas fa-chart-line text-success me-1"></i> Aprovado: ≥ 10 | Reprovado: < 10</span>
        </div>

        <!-- Filtros -->
        <div class="filter-section">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Ano Letivo</label>
                    <select class="form-select" name="ano" onchange="this.form.submit()">
                        <option value="">Todos</option>
                        <?php while ($ano = $anos->fetch_assoc()): ?>
                            <option value="<?php echo $ano['ano_letivo']; ?>" <?php echo $ano_selecionado == $ano['ano_letivo'] ? 'selected' : ''; ?>>
                                <?php echo $ano['ano_letivo']; ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Trimestre</label>
                    <select class="form-select" name="trimestre" onchange="this.form.submit()">
                        <option value="0">Todos</option>
                        <option value="1" <?php echo $trimestre_selecionado == 1 ? 'selected' : ''; ?>>1º Trimestre</option>
                        <option value="2" <?php echo $trimestre_selecionado == 2 ? 'selected' : ''; ?>>2º Trimestre</option>
                        <option value="3" <?php echo $trimestre_selecionado == 3 ? 'selected' : ''; ?>>3º Trimestre</option>
                    </select>
                </div>

                <div class="col-md-4 d-flex align-items-end">
                    <a href="minhas-notas.php" class="btn btn-secondary w-100">Limpar Filtros</a>
                </div>
            </form>
        </div>

        <!-- Notas Agrupadas -->
        <?php if (empty($notas_agrupadas)): ?>
            <div class="alert alert-info text-center py-5">
                <i class="fas fa-search fa-3x mb-3 text-primary"></i>
                <h5>Nenhuma nota encontrada</h5>
                <p class="mb-0">Não há notas disponíveis para os filtros selecionados.</p>
            </div>
        <?php else: ?>
            <?php foreach ($notas_agrupadas as $grupo):
                $media_trimestre = $medias_trimestre[$grupo['ano']][$grupo['trimestre']] ?? 0;
            ?>
                <div class="trimestre-card">
                    <div class="trimestre-header">
                        <span>
                            <i class="fas fa-calendar-alt me-2"></i>
                            <?php echo $grupo['ano']; ?> - <?php echo $grupo['trimestre']; ?>º Trimestre
                        </span>
                        <?php if ($media_trimestre > 0): ?>
                            <span class="trimestre-media">
                                <i class="fas fa-star me-1"></i>Média: <?php echo number_format($media_trimestre, 1); ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="disciplina-row fw-bold text-muted" style="background: #f8fafc; border-bottom: 2px solid #e9ecef;">
                        <div class="disciplina-nome">Disciplina</div>
                        <div class="disciplina-nota">Nota do Trimestre</div>
                        <div class="disciplina-detalhe">Estado</div>
                    </div>

                    <?php foreach ($grupo['disciplinas'] as $nota):
                        $nota_valor = $nota['nota_trimestre'] ?? 0;
                        $classe_nota = 'nota-media';
                        if ($nota_valor >= 14) $classe_nota = 'nota-alta';
                        elseif ($nota_valor < 10 && $nota_valor > 0) $classe_nota = 'nota-baixa';
                    ?>
                        <div class="disciplina-row">
                            <div class="disciplina-nome">
                                <?php echo htmlspecialchars($nota['disciplina']); ?>
                                <?php if (!empty($nota['disciplina_codigo'])): ?>
                                    <br>
                                    <small class="text-muted">(<?php echo htmlspecialchars($nota['disciplina_codigo']); ?>)</small>
                                <?php endif; ?>
                            </div>

                            <div class="disciplina-nota">
                                <span class="nota-badge <?php echo $classe_nota; ?>">
                                    <?php echo $nota_valor ? number_format($nota_valor, 1) : '-'; ?>
                                </span>
                            </div>

                            <div class="disciplina-detalhe">
                                <?php if ($nota['estado'] == 'Aprovado'): ?>
                                    <span class="badge bg-success">
                                        <i class="fas fa-check-circle me-1"></i>Aprovado
                                    </span>
                                <?php elseif ($nota['estado'] == 'Reprovado'): ?>
                                    <span class="badge bg-danger">
                                        <i class="fas fa-times-circle me-1"></i>Reprovado
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">
                                        <i class="fas fa-hourglass-half me-1"></i>Pendente
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
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
    </script>
</body>

</html>