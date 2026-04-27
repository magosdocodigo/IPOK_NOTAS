<?php
// admin/dashboard.php
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

// ── Valores padrão para evitar erros se alguma query falhar ──────────────────
$stats = [
    'usuarios' => [
        'total_admin'       => 0,
        'total_professores' => 0,
        'total_alunos'      => 0,
        'total_usuarios'    => 0,
    ],
    'turmas'           => 0,
    'disciplinas'      => 0,
    'periodos_abertos' => 0,
];
$logs            = null;
$ultimos_usuarios = null;

// ── Estatísticas para o dashboard ────────────────────────────────────────────

// Total de usuários por tipo
$query = "SELECT 
            COUNT(CASE WHEN nivel = 'admin'     THEN 1 END) AS total_admin,
            COUNT(CASE WHEN nivel = 'professor' THEN 1 END) AS total_professores,
            COUNT(CASE WHEN nivel = 'aluno'     THEN 1 END) AS total_alunos,
            COUNT(*)                                        AS total_usuarios
          FROM usuarios
          WHERE ativo = 1";
$result = $db->query($query);
if ($result && $result instanceof mysqli_result) {
    $row = $result->fetch_assoc();
    if ($row) {
        $stats['usuarios'] = $row;
    }
    $result->free();
}

// Total de turmas
$query  = "SELECT COUNT(*) AS total FROM turmas";
$result = $db->query($query);
if ($result && $result instanceof mysqli_result) {
    $row = $result->fetch_assoc();
    $stats['turmas'] = $row ? (int)$row['total'] : 0;
    $result->free();
}

// Total de disciplinas
$query  = "SELECT COUNT(*) AS total FROM disciplinas";
$result = $db->query($query);
if ($result && $result instanceof mysqli_result) {
    $row = $result->fetch_assoc();
    $stats['disciplinas'] = $row ? (int)$row['total'] : 0;
    $result->free();
}

// Períodos abertos
$query  = "SELECT COUNT(*) AS total FROM periodos WHERE status = 'aberto'";
$result = $db->query($query);
if ($result && $result instanceof mysqli_result) {
    $row = $result->fetch_assoc();
    $stats['periodos_abertos'] = $row ? (int)$row['total'] : 0;
    $result->free();
}

// Últimos logs de auditoria
$query = "SELECT l.*, u.nome AS usuario_nome 
          FROM logs_auditoria l 
          LEFT JOIN usuarios u ON l.usuario_id = u.id 
          ORDER BY l.data_hora DESC 
          LIMIT 10";
$logs  = $db->query($query);
if (!($logs instanceof mysqli_result)) {
    $logs = null;
}

// Últimos utilizadores cadastrados
$query = "SELECT id, nome, email, nivel, ativo, criado_em 
          FROM usuarios 
          ORDER BY criado_em DESC 
          LIMIT 5";
$ultimos_usuarios = $db->query($query);
if (!($ultimos_usuarios instanceof mysqli_result)) {
    $ultimos_usuarios = null;
}

$page_title = "Dashboard Admin";
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IPOK - Admin Dashboard</title>
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
            --primary-blue:   #1e3c72;
            --secondary-blue: #2a5298;
            --accent-blue:    #3a6ab5;
            --light-blue:     #e6f0fa;
            --sidebar-width:  280px;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f4f7fc;
            overflow-x: hidden;
            height: auto;
        }

        /* ── Sidebar ───────────────────────────────────────────────── */
        .sidebar {
            position: fixed;
            top: 0; left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            color: white;
            transition: all .3s ease;
            z-index: 1000;
            overflow-y: auto;
            box-shadow: 2px 0 10px rgba(0,0,0,.1);
        }

        .sidebar.hidden {
            transform: translateX(-100%);
            box-shadow: none;
        }

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

        .sidebar-header h3 { font-size: 1.2rem; margin-bottom: 5px; }
        .sidebar-header p  { font-size: .85rem; opacity: .8; margin-bottom: 0; }

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

        .sidebar-menu .menu-item i    { width: 30px; font-size: 1.2rem; }
        .sidebar-menu .menu-item span { flex: 1; }
        .sidebar-menu .menu-item .badge { background: rgba(255,255,255,.3); color: white; }

        /* ── Main Content ──────────────────────────────────────────── */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 20px;
            transition: all .3s ease;
            min-height: 100vh;
            overflow-y: auto;
        }

        .main-content.sidebar-hidden {
            margin-left: 0;
        }

        /* ── Top Nav ───────────────────────────────────────────────── */
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

        .top-nav .user-info .user-details  { text-align: right; }
        .top-nav .user-info .user-name     { font-weight: 600; color: var(--primary-blue); }
        .top-nav .user-info .user-role     { font-size: .85rem; color: #6c757d; }

        /* ── Cards ─────────────────────────────────────────────────── */
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,.05);
            transition: transform .3s;
            margin-bottom: 20px;
            border: 1px solid rgba(0,0,0,.05);
        }

        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(30,60,114,.1);
        }

        .stats-icon {
            width: 60px; height: 60px;
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.8rem;
            margin-bottom: 15px;
        }

        .stats-number { font-size: 2rem; font-weight: 700; color: var(--primary-blue); margin-bottom: 5px; }
        .stats-label  { color: #6c757d; font-size: .9rem; text-transform: uppercase; letter-spacing: .5px; }

        /* ── Tables ────────────────────────────────────────────────── */
        .table-container {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,.05);
            margin-bottom: 25px;
            overflow: hidden;
        }

        .table-container canvas {
            max-height: 350px;
            width: 100% !important;
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

        .table-title .btn-add {
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 8px;
            font-size: .9rem;
            transition: all .3s;
        }

        .table-title .btn-add:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(30,60,114,.3);
        }

        .table thead th { color: var(--primary-blue); font-weight: 600; border-bottom: 2px solid var(--light-blue); }

        .badge-status    { padding: 5px 10px; border-radius: 30px; font-size: .8rem; font-weight: 500; }
        .badge-ativo     { background: #d4edda; color: #155724; }
        .badge-inativo   { background: #f8d7da; color: #721c24; }
        .badge-admin     { background: var(--primary-blue); color: white; }
        .badge-professor { background: var(--secondary-blue); color: white; }
        .badge-aluno     { background: #28a745; color: white; }

        .action-btns .btn { padding: 5px 10px; font-size: .8rem; margin: 0 2px; }

        /* ── Responsive ────────────────────────────────────────────── */
        @media (max-width: 768px) {
            .sidebar { margin-left: -280px; }
            .sidebar.active { margin-left: 0; }
            .main-content { margin-left: 0; }
            .main-content.active { margin-left: 280px; }
        }
    </style>
</head>
<body>

    <!-- ── Sidebar ──────────────────────────────────────────────────────── -->
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <img src="../assets/img/logo.png" alt="IPOK Logo">
            </div>
            <h3>IPOK Admin</h3>
            <p>Instituto Politécnico do Kituma</p>
        </div>

        <div class="sidebar-menu">
            <div class="menu-title">PRINCIPAL</div>
            <a href="dashboard.php" class="menu-item active">
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
                <?php if ($stats['periodos_abertos'] > 0): ?>
                    <span class="badge"><?php echo $stats['periodos_abertos']; ?> aberto(s)</span>
                <?php endif; ?>
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
                <span>Logs de Auditoria</span>
            </a>

            <div class="menu-title">CONTA</div>
            <a href="perfil.php" class="menu-item">
                <i class="fas fa-user-cog"></i>
                <span>Meu Perfil</span>
            </a>
            <a href="../logout.php" class="menu-item">
                <i class="fas fa-sign-out-alt"></i>
                <span>Sair</span>
            </a>
        </div>
    </div><!-- /sidebar -->

    <!-- ── Main Content ──────────────────────────────────────────────────── -->
    <div class="main-content">

        <!-- Top Navigation -->
        <div class="top-nav">
            <div style="display: flex; align-items: center; gap: 15px;">
                <button class="btn btn-sm btn-outline-primary" onclick="toggleSidebar()" style="border-radius: 8px;">
                    <i class="fas fa-bars"></i>
                </button>
                <h1 class="page-title" style="margin: 0;">
                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                </h1>
            </div>
            <div class="user-info">
                <div class="user-details">
                    <div class="user-name"><?php echo htmlspecialchars($_SESSION['user_nome'] ?? ''); ?></div>
                    <div class="user-role">Administrador</div>
                </div>
                <div class="user-avatar">
                    <?php echo strtoupper(substr($_SESSION['user_nome'] ?? 'A', 0, 1)); ?>
                </div>
            </div>
        </div>

        <!-- ── Stats Cards ────────────────────────────────────────────── -->
        <div class="row g-4">
            <div class="col-xl-3 col-md-6">
                <div class="stats-card">
                    <div class="stats-icon"><i class="fas fa-users"></i></div>
                    <div class="stats-number"><?php echo (int)$stats['usuarios']['total_usuarios']; ?></div>
                    <div class="stats-label">Total Utilizadores</div>
                    <div class="mt-2 small">
                        <span class="text-primary"><?php echo (int)$stats['usuarios']['total_admin']; ?> Admin</span> |
                        <span class="text-info"><?php echo (int)$stats['usuarios']['total_professores']; ?> Professores</span> |
                        <span class="text-success"><?php echo (int)$stats['usuarios']['total_alunos']; ?> Alunos</span>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="stats-card">
                    <div class="stats-icon"><i class="fas fa-chalkboard"></i></div>
                    <div class="stats-number"><?php echo (int)$stats['turmas']; ?></div>
                    <div class="stats-label">Turmas</div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="stats-card">
                    <div class="stats-icon"><i class="fas fa-book"></i></div>
                    <div class="stats-number"><?php echo (int)$stats['disciplinas']; ?></div>
                    <div class="stats-label">Disciplinas</div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="stats-card">
                    <div class="stats-icon"><i class="fas fa-calendar-check"></i></div>
                    <div class="stats-number"><?php echo (int)$stats['periodos_abertos']; ?></div>
                    <div class="stats-label">Períodos Abertos</div>
                </div>
            </div>
        </div>

        <!-- ── Gráfico + Últimos Utilizadores ────────────────────────── -->
        <div class="row mt-4">
            <div class="col-lg-6">
                <div class="table-container">
                    <div class="table-title">
                        <span><i class="fas fa-chart-pie me-2"></i>Distribuição de Utilizadores</span>
                    </div>
                    <div style="max-height: 350px;">
                        <canvas id="userChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="table-container">
                    <div class="table-title">
                        <span><i class="fas fa-history me-2"></i>Últimos Utilizadores</span>
                        <a href="usuarios.php" class="btn-add btn-sm">Ver Todos</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Nome</th>
                                    <th>Email</th>
                                    <th>Nível</th>
                                    <th>Status</th>
                                </td>
                            </thead>
                            <tbody>
                                <?php if ($ultimos_usuarios && $ultimos_usuarios->num_rows > 0): ?>
                                    <?php while ($user = $ultimos_usuarios->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user['nome']); ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td>
                                            <?php
                                            $nivel       = $user['nivel'] ?? '';
                                            $badgeClass  = match($nivel) {
                                                'admin'     => 'badge-admin',
                                                'professor' => 'badge-professor',
                                                default     => 'badge-aluno',
                                            };
                                            ?>
                                            <span class="badge-status <?php echo $badgeClass; ?>">
                                                <?php echo ucfirst($nivel); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge-status <?php echo $user['ativo'] ? 'badge-ativo' : 'badge-inativo'; ?>">
                                                <?php echo $user['ativo'] ? 'Ativo' : 'Inativo'; ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-3">
                                            Nenhum utilizador encontrado.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Logs de Auditoria ──────────────────────────────────────── -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="table-container">
                    <div class="table-title">
                        <span><i class="fas fa-history me-2"></i>Últimas Atividades</span>
                        <a href="logs.php" class="btn-add btn-sm">Ver Todos</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Data/Hora</th>
                                    <th>Usuário</th>
                                    <th>Ação</th>
                                    <th>Tabela</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($logs && $logs->num_rows > 0): ?>
                                    <?php while ($log = $logs->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y H:i', strtotime($log['data_hora'])); ?></td>
                                        <td><?php echo htmlspecialchars($log['usuario_nome'] ?? 'Sistema'); ?></td>
                                        <td>
                                            <span class="badge bg-secondary">
                                                <?php echo htmlspecialchars($log['acao']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($log['tabela']); ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-3">
                                            Nenhuma atividade registada.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div><!-- /main-content -->

    <!-- ── Scripts ──────────────────────────────────────────────────────── -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

    <script>
        $(document).ready(function () {
            // Gráfico de distribuição de utilizadores
            const ctx = document.getElementById('userChart').getContext('2d');
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Administradores', 'Professores', 'Alunos'],
                    datasets: [{
                        data: [
                            <?php echo (int)$stats['usuarios']['total_admin']; ?>,
                            <?php echo (int)$stats['usuarios']['total_professores']; ?>,
                            <?php echo (int)$stats['usuarios']['total_alunos']; ?>
                        ],
                        backgroundColor: ['#1e3c72', '#2a5298', '#28a745'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: { position: 'bottom' }
                    }
                }
            });

            // DataTables inicializado apenas se tiver tabelas com classe 'datatable'
            if ($('.datatable').length > 0) {
                $('.datatable').DataTable({
                    paging: true,
                    ordering: true,
                    searching: true
                });
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