<?php
// aluno/historico.php
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

// =============================================
// HISTÓRICO SIMPLIFICADO (apenas nota_trimestre)
// =============================================
$query = "SELECT n.nota_trimestre, n.trimestre, n.ano_letivo, n.estado, 
          d.nome as disciplina, d.codigo as disciplina_codigo, t.nome as turma_nome
          FROM notas n
          INNER JOIN disciplinas d ON n.disciplina_id = d.id
          LEFT JOIN enturmacoes e ON n.aluno_id = e.aluno_id AND n.ano_letivo = e.data_enturmacao
          LEFT JOIN turmas t ON e.turma_id = t.id
          WHERE n.aluno_id = $aluno_id
          ORDER BY n.ano_letivo DESC, n.trimestre ASC, d.nome ASC";
$historico = $db->query($query);

// Agrupar por ano letivo
$historico_por_ano = [];
while ($item = $historico->fetch_assoc()) {
    $ano = $item['ano_letivo'];
    if (!isset($historico_por_ano[$ano])) {
        $historico_por_ano[$ano] = [];
    }
    $historico_por_ano[$ano][] = $item;
}

// Calcular estatísticas por ano
$estatisticas_ano = [];
foreach ($historico_por_ano as $ano => $notas) {
    $total = count($notas);
    $aprovados = 0;
    $soma_medias = 0;
    
    foreach ($notas as $n) {
        if ($n['estado'] == 'Aprovado') $aprovados++;
        if ($n['nota_trimestre'] > 0) {
            $soma_medias += $n['nota_trimestre'];
        }
    }
    
    $estatisticas_ano[$ano] = [
        'total' => $total,
        'aprovados' => $aprovados,
        'reprovados' => $total - $aprovados,
        'media_geral' => $total > 0 ? round($soma_medias / $total, 1) : 0,
    ];
}

$page_title = "Histórico Escolar";
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IPOK - Histórico Escolar</title>
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
        
        .ano-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,.05);
            margin-bottom: 30px;
            overflow: hidden;
            border: 1px solid rgba(0,0,0,.05);
            transition: all 0.3s ease;
        }
        
        .ano-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(30,60,114,.15);
        }
        
        .ano-header {
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            color: white;
            padding: 18px 25px;
            font-size: 1.3rem;
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .ano-header h3 {
            margin: 0;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .ano-header .badge-ano {
            background: rgba(255,255,255,0.2);
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
        }
        
        .stats-mini {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 15px;
            padding: 20px 25px;
            background: #f8fafc;
            border-bottom: 1px solid #e9ecef;
        }
        
        .stat-mini-item {
            text-align: center;
            padding: 10px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,.05);
        }
        
        .stat-mini-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-blue);
        }
        
        .stat-mini-label {
            font-size: 0.75rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .trimestre-section {
            padding: 0 25px 20px 25px;
        }
        
        .trimestre-badge {
            background: var(--secondary-blue);
            color: white;
            padding: 6px 15px;
            border-radius: 25px;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin: 15px 0 10px 0;
        }
        
        .historico-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        .historico-table thead th {
            background: #f8fafc;
            color: var(--primary-blue);
            font-weight: 600;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 12px 10px;
            border-bottom: 2px solid #e9ecef;
            text-align: center;
        }
        
        .historico-table tbody td {
            padding: 12px 10px;
            border-bottom: 1px solid #e9ecef;
            vertical-align: middle;
            text-align: center;
        }
        
        .historico-table tbody td:first-child {
            text-align: left;
            font-weight: 500;
        }
        
        .historico-table tbody tr:hover {
            background: #f8fafc;
        }
        
        .nota-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.8rem;
            min-width: 55px;
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
        
        .badge-terminal-info {
            background: #ffc107;
            color: #856404;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .info-box {
            background: #e6f0fa;
            border-left: 4px solid var(--primary-blue);
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .legenda-box {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 12px 20px;
            margin-top: 15px;
            font-size: 0.8rem;
        }
        
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); }
            .main-content { margin-left: 0; }
            .historico-table {
                display: block;
                overflow-x: auto;
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
            <a href="minhas-notas.php" class="menu-item">
                <i class="fas fa-chart-line"></i>
                <span>Minhas Notas</span>
            </a>
            <a href="boletim.php" class="menu-item">
                <i class="fas fa-file-alt"></i>
                <span>Boletim</span>
            </a>
            <a href="historico.php" class="menu-item active">
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
                    <i class="fas fa-history me-2"></i>Histórico Escolar
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
        
        <!-- Info Box -->
        <div class="info-box">
            <i class="fas fa-info-circle text-primary fa-2x"></i>
            <div>
                <strong>Histórico Completo</strong><br>
                Visualize todo o seu percurso académico, com notas por ano letivo e trimestre.
            </div>
            <div class="ms-auto">
                <span class="badge bg-primary">RN07</span>
            </div>
        </div>
        
        <!-- Legenda do Sistema de Avaliação -->
        <div class="legenda-box">
            <i class="fas fa-info-circle text-primary me-2"></i>
            <strong>Sistema de Avaliação:</strong>
            <span class="ms-3"><i class="fas fa-chart-line text-success me-1"></i> <span class="fw-bold">Nota Alta:</span> ≥ 14</span>
            <span class="ms-3"><i class="fas fa-chart-line text-warning me-1"></i> <span class="fw-bold">Nota Média:</span> 10 - 13.9</span>
            <span class="ms-3"><i class="fas fa-chart-line text-danger me-1"></i> <span class="fw-bold">Nota Baixa:</span> < 10</span>
        </div>
        
        <!-- Histórico por Ano -->
        <?php if (empty($historico_por_ano)): ?>
        <div class="alert alert-info text-center py-5">
            <i class="fas fa-search fa-3x mb-3 text-primary"></i>
            <h5>Nenhum registro encontrado</h5>
            <p class="mb-0">Seu histórico escolar ainda não possui notas registradas.</p>
        </div>
        <?php else: ?>
            <?php foreach ($historico_por_ano as $ano => $notas): ?>
            <div class="ano-card">
                <div class="ano-header">
                    <h3>
                        <i class="fas fa-calendar-alt"></i>
                        Ano Letivo <?php echo $ano; ?>
                    </h3>
                    <span class="badge-ano">
                        <i class="fas fa-book me-1"></i><?php echo count($notas); ?> disciplinas
                    </span>
                </div>
                
                <!-- Estatísticas do Ano -->
                <div class="stats-mini">
                    <div class="stat-mini-item">
                        <div class="stat-mini-number"><?php echo number_format($estatisticas_ano[$ano]['media_geral'], 1); ?></div>
                        <div class="stat-mini-label">Média Geral</div>
                    </div>
                    <div class="stat-mini-item">
                        <div class="stat-mini-number"><?php echo $estatisticas_ano[$ano]['aprovados']; ?></div>
                        <div class="stat-mini-label">Aprovados</div>
                    </div>
                    <div class="stat-mini-item">
                        <div class="stat-mini-number"><?php echo $estatisticas_ano[$ano]['reprovados']; ?></div>
                        <div class="stat-mini-label">Reprovados</div>
                    </div>
                </div>
                
                <!-- Notas por Trimestre -->
                <?php
                $notas_por_trimestre = [];
                foreach ($notas as $n) {
                    $notas_por_trimestre[$n['trimestre']][] = $n;
                }
                ksort($notas_por_trimestre);
                ?>
                
                <div class="trimestre-section">
                    <?php for ($t = 1; $t <= 3; $t++): 
                        if (!isset($notas_por_trimestre[$t])) continue;
                    ?>
                    <div class="trimestre-badge">
                        <i class="fas fa-calendar-week"></i>
                        <?php echo $t; ?>º Trimestre
                    </div>
                    
                    <div class="table-responsive">
                        <table class="historico-table">
                            <thead>
                                <tr>
                                    <th>Disciplina</th>
                                    <th>Nota do Trimestre</th>
                                    <th>Estado</th>
                                 </thead>
                            <tbody>
                                <?php foreach ($notas_por_trimestre[$t] as $nota): 
                                    $nota_valor = $nota['nota_trimestre'] ?? 0;
                                    $classe_nota = 'nota-media';
                                    if ($nota_valor >= 14) $classe_nota = 'nota-alta';
                                    elseif ($nota_valor < 10 && $nota_valor > 0) $classe_nota = 'nota-baixa';
                                ?>
                                <tr>
                                    <td class="fw-500">
                                        <?php echo htmlspecialchars($nota['disciplina']); ?>
                                        <?php if (!empty($nota['disciplina_codigo'])): ?>
                                            <br>
                                            <small class="text-muted">(<?php echo htmlspecialchars($nota['disciplina_codigo']); ?>)</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="nota-badge <?php echo $classe_nota; ?>">
                                            <?php echo $nota_valor ? number_format($nota_valor, 1) : '-'; ?>
                                        </span>
                                    </td>
                                    <td>
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
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <!-- Rodapé com data de emissão -->
        <div class="text-center text-muted small mt-4">
            <i class="fas fa-calendar-alt me-1"></i>
            Histórico gerado em <?php echo date('d/m/Y H:i'); ?>
        </div>
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
        
        // Animar entrada dos cards
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.ano-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.4s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, 100 + (index * 100));
            });
        });
    </script>
</body>
</html>