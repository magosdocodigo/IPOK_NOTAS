<?php
// aluno/boletim.php
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

// Buscar informações do aluno
$query = "SELECT u.nome, u.email, a.numero_matricula, a.data_matricula
          FROM alunos a
          INNER JOIN usuarios u ON a.usuario_id = u.id
          WHERE a.id = $aluno_id";
$aluno_info = $db->query($query)->fetch_assoc();

// Buscar turma atual do aluno
$query = "SELECT t.id, t.nome as turma_nome, t.ano_letivo, t.curso
          FROM enturmacoes e
          INNER JOIN turmas t ON e.turma_id = t.id
          WHERE e.aluno_id = $aluno_id AND t.ano_letivo = YEAR(CURDATE())
          LIMIT 1";
$turma_atual = $db->query($query)->fetch_assoc();

// Buscar notas do aluno por trimestre (campos reais do banco)
$query = "SELECT n.*, d.nome as disciplina_nome, d.codigo as disciplina_codigo
          FROM notas n
          INNER JOIN disciplinas d ON n.disciplina_id = d.id
          WHERE n.aluno_id = $aluno_id
          AND n.ano_letivo = YEAR(CURDATE())
          ORDER BY n.trimestre ASC, d.nome ASC";
$notas = $db->query($query);

// Organizar notas por trimestre
$notas_por_trimestre = [
    1 => [],
    2 => [],
    3 => []
];
$medias_trimestre = [];

while ($nota = $notas->fetch_assoc()) {
    $tri = $nota['trimestre'];
    $notas_por_trimestre[$tri][] = $nota;
    
    if (!isset($medias_trimestre[$tri])) {
        $medias_trimestre[$tri] = ['soma' => 0, 'count' => 0];
    }
    if ($nota['media_final'] > 0) {
        $medias_trimestre[$tri]['soma'] += $nota['media_final'];
        $medias_trimestre[$tri]['count']++;
    }
}

// Calcular média final
$media_final = 0;
$total_disciplinas = 0;
foreach ($medias_trimestre as $tri => $data) {
    $medias_trimestre[$tri]['media'] = $data['count'] > 0 ? round($data['soma'] / $data['count'], 1) : 0;
    $media_final += $medias_trimestre[$tri]['media'];
    $total_disciplinas++;
}
$media_final = $total_disciplinas > 0 ? round($media_final / $total_disciplinas, 1) : 0;

// Determinar situação final
$situacao = $media_final >= 10 ? 'Aprovado' : ($media_final > 0 ? 'Reprovado' : 'Em Andamento');
$situacao_classe = $situacao == 'Aprovado' ? 'aprovado' : ($situacao == 'Reprovado' ? 'reprovado' : 'andamento');

// Processar exportação CSV
$export_action = isset($_GET['export']) ? $_GET['export'] : '';

if ($export_action == 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="boletim_' . $aluno_info['numero_matricula'] . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Boletim Escolar - IPOK']);
    fputcsv($output, []);
    fputcsv($output, ['Aluno:', $aluno_info['nome']]);
    fputcsv($output, ['Matrícula:', $aluno_info['numero_matricula']]);
    fputcsv($output, ['Turma:', $turma_atual['turma_nome']]);
    fputcsv($output, ['Curso:', $turma_atual['curso'] ?? 'Não definido']);
    fputcsv($output, ['Data Emissão:', date('d/m/Y H:i')]);
    fputcsv($output, []);
    
    for ($tri = 1; $tri <= 3; $tri++) {
        if (empty($notas_por_trimestre[$tri])) continue;
        
        fputcsv($output, [$tri . 'º Trimestre']);
        fputcsv($output, ['Disciplina', 'Avaliação 1', 'Avaliação 2', 'MAC', 'Exame', 'Média Final', 'Estado']);
        
        foreach ($notas_por_trimestre[$tri] as $nota) {
            $media = $nota['media_final'] ?? 0;
            fputcsv($output, [
                $nota['disciplina_nome'],
                $nota['avaliacao1'] ? number_format($nota['avaliacao1'], 1) : '-',
                $nota['avaliacao2'] ? number_format($nota['avaliacao2'], 1) : '-',
                $nota['mac'] ? number_format($nota['mac'], 1) : '-',
                $nota['exame'] ? number_format($nota['exame'], 1) : '-',
                $media ? number_format($media, 1) : '-',
                $nota['estado'] ?? 'Pendente'
            ]);
        }
        
        if (isset($medias_trimestre[$tri]['media']) && $medias_trimestre[$tri]['media'] > 0) {
            fputcsv($output, ['Média do Trimestre:', '', '', '', '', number_format($medias_trimestre[$tri]['media'], 1)]);
        }
        fputcsv($output, []);
    }
    
    fputcsv($output, ['Situação Final:', $situacao . ' (Média: ' . number_format($media_final, 1) . ')']);
    fclose($output);
    exit;
}

$page_title = "Meu Boletim";
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IPOK - Meu Boletim</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary-blue: #1e3c72;
            --secondary-blue: #2a5298;
            --sidebar-width: 280px;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f4f7fc 0%, #e9ecef 100%);
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
            width: 80px;
            height: 80px;
            margin: 0 auto 15px;
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
        
        /* Boletim Card */
        .boletim-card {
            background: white;
            border-radius: 24px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.08);
            overflow: hidden;
            margin-bottom: 30px;
            animation: slideUp 0.6s ease;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .boletim-header {
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            padding: 30px;
            color: white;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .boletim-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: rotate 20s linear infinite;
        }
        
        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .boletim-logo {
            width: 100px;
            height: 100px;
            background: white;
            border-radius: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            padding: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .boletim-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        
        .boletim-header h2 {
            font-size: 2rem;
            font-weight: 700;
            margin: 15px 0 5px;
        }
        
        .boletim-header p {
            opacity: 0.9;
            margin: 0;
        }
        
        .aluno-info {
            background: #f8fafc;
            padding: 25px 30px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            border-bottom: 2px solid #e9ecef;
        }
        
        .info-item {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .info-icon {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
        }
        
        .info-content {
            flex: 1;
        }
        
        .info-label {
            font-size: 0.75rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .info-value {
            font-size: 1rem;
            font-weight: 600;
            color: var(--primary-blue);
        }
        
        /* Trimestre Cards */
        .trimestre-container {
            padding: 25px 30px;
        }
        
        .trimestre-card {
            background: white;
            border-radius: 20px;
            margin-bottom: 25px;
            border: 1px solid #e9ecef;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .trimestre-card:hover {
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            transform: translateY(-3px);
        }
        
        .trimestre-header {
            background: linear-gradient(135deg, #f8fafc, #ffffff);
            padding: 15px 25px;
            border-bottom: 2px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .trimestre-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--primary-blue);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .trimestre-title i {
            font-size: 1.3rem;
        }
        
        .trimestre-media {
            background: var(--primary-blue);
            color: white;
            padding: 5px 15px;
            border-radius: 30px;
            font-size: 0.9rem;
            font-weight: 600;
        }
        
        .boletim-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .boletim-table thead th {
            background: #f8fafc;
            color: var(--primary-blue);
            font-weight: 600;
            font-size: 0.85rem;
            padding: 12px 15px;
            border-bottom: 2px solid #e9ecef;
            text-align: center;
        }
        
        .boletim-table tbody td {
            padding: 12px 15px;
            border-bottom: 1px solid #e9ecef;
            vertical-align: middle;
            text-align: center;
        }
        
        .boletim-table tbody td:first-child {
            text-align: left;
            font-weight: 500;
        }
        
        .boletim-table tbody tr:hover {
            background: #f8fafc;
        }
        
        .nota-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 30px;
            font-weight: 600;
            font-size: 0.85rem;
        }
        
        .nota-alta {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
        }
        
        .nota-media {
            background: linear-gradient(135deg, #fff3cd, #ffeeba);
            color: #856404;
        }
        
        .nota-baixa {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            color: #721c24;
        }
        
        .resumo-card {
            background: linear-gradient(135deg, #f8fafc, #ffffff);
            padding: 25px 30px;
            border-top: 2px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .media-final {
            text-align: center;
        }
        
        .media-final .label {
            font-size: 0.8rem;
            color: #6c757d;
            text-transform: uppercase;
        }
        
        .media-final .value {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-blue);
            line-height: 1;
        }
        
        .situacao {
            padding: 10px 25px;
            border-radius: 40px;
            font-weight: 700;
            font-size: 1.2rem;
        }
        
        .situacao-aprovado {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
        }
        
        .situacao-reprovado {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            color: #721c24;
        }
        
        .situacao-andamento {
            background: linear-gradient(135deg, #fff3cd, #ffeeba);
            color: #856404;
        }
        
        .action-bar {
            background: white;
            padding: 20px 30px;
            border-top: 1px solid #e9ecef;
            display: flex;
            justify-content: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .btn-export {
            padding: 12px 30px;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn-pdf {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            border: none;
        }
        
        .btn-pdf:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(220, 53, 69, 0.3);
            color: white;
        }
        
        .btn-csv {
            background: linear-gradient(135deg, #28a745, #218838);
            color: white;
            border: none;
        }
        
        .btn-csv:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(40, 167, 69, 0.3);
            color: white;
        }
        
        .btn-print {
            background: linear-gradient(135deg, #17a2b8, #138496);
            color: white;
            border: none;
        }
        
        .btn-print:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(23, 162, 184, 0.3);
            color: white;
        }
        
        /* Estilos para impressão/PDF */
        @media print {
            .sidebar, .top-nav, .action-bar, .btn-export, .btn-print, .no-print {
                display: none !important;
            }
            .main-content {
                margin-left: 0 !important;
                padding: 0 !important;
            }
            .boletim-card {
                box-shadow: none;
                border-radius: 0;
                margin: 0;
            }
            .boletim-header {
                background: var(--primary-blue);
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .boletim-logo {
                background: white;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .trimestre-header {
                background: #f0f0f0;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .situacao {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .nota-badge {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
        
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); }
            .main-content { margin-left: 0; }
            .boletim-table {
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
                <img src="../assets/img/logo.png" alt="IPOK Logo">
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
            <a href="consultar_notas.php" class="menu-item">
                <i class="fas fa-chart-line"></i>
                <span>Minhas Notas</span>
            </a>
            <a href="boletim.php" class="menu-item active">
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
        <div class="top-nav no-print">
            <div style="display: flex; align-items: center; gap: 15px;">
                <button class="btn btn-sm btn-outline-primary" onclick="toggleSidebar()" style="border-radius: 10px;">
                    <i class="fas fa-bars"></i>
                </button>
                <h1 class="page-title">
                    <i class="fas fa-file-alt me-2"></i>Meu Boletim
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
        
        <!-- Boletim Principal -->
        <div class="boletim-card">
            <div class="boletim-header">
                <div class="boletim-logo">
                    <img src="../assets/img/logo.png" alt="IPOK">
                </div>
                <h2>Boletim Escolar</h2>
                <p>Instituto Politécnico do Kituma - IPOK</p>
                <p class="mt-2">Ano Letivo <?php echo date('Y'); ?></p>
            </div>
            
            <div class="aluno-info">
                <div class="info-item">
                    <div class="info-icon">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <div class="info-content">
                        <div class="info-label">Aluno</div>
                        <div class="info-value"><?php echo htmlspecialchars($aluno_info['nome']); ?></div>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-icon">
                        <i class="fas fa-id-card"></i>
                    </div>
                    <div class="info-content">
                        <div class="info-label">Matrícula</div>
                        <div class="info-value"><?php echo htmlspecialchars($aluno_info['numero_matricula']); ?></div>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-icon">
                        <i class="fas fa-chalkboard"></i>
                    </div>
                    <div class="info-content">
                        <div class="info-label">Turma</div>
                        <div class="info-value"><?php echo htmlspecialchars($turma_atual['turma_nome']); ?></div>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-icon">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <div class="info-content">
                        <div class="info-label">Curso</div>
                        <div class="info-value"><?php echo htmlspecialchars($turma_atual['curso'] ?? 'Não definido'); ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Trimestres -->
            <div class="trimestre-container">
                <?php for ($tri = 1; $tri <= 3; $tri++): 
                    if (empty($notas_por_trimestre[$tri])) continue;
                ?>
                <div class="trimestre-card">
                    <div class="trimestre-header">
                        <div class="trimestre-title">
                            <i class="fas fa-calendar-alt"></i>
                            <?php echo $tri; ?>º Trimestre
                        </div>
                        <?php if (isset($medias_trimestre[$tri]['media']) && $medias_trimestre[$tri]['media'] > 0): ?>
                            <div class="trimestre-media">
                                <i class="fas fa-star me-1"></i>
                                Média: <?php echo number_format($medias_trimestre[$tri]['media'], 1); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="boletim-table">
                            <thead>
                                <tr>
                                    <th>Disciplina</th>
                                    <th>Avaliação 1</th>
                                    <th>Avaliação 2</th>
                                    <th>MAC</th>
                                    <th>Exame</th>
                                    <th>Média Final</th>
                                    <th>Estado</th>
                                 </thead>
                                <tbody>
                                    <?php foreach ($notas_por_trimestre[$tri] as $nota): 
                                        $media = $nota['media_final'] ?? 0;
                                        $classe_nota = 'nota-media';
                                        if ($media >= 14) $classe_nota = 'nota-alta';
                                        elseif ($media < 10 && $media > 0) $classe_nota = 'nota-baixa';
                                    ?>
                                    <tr>
                                        <td class="fw-500">
                                            <?php echo htmlspecialchars($nota['disciplina_nome']); ?>
                                            <?php if ($nota['disciplina_codigo']): ?>
                                                <br><small class="text-muted">(<?php echo $nota['disciplina_codigo']; ?>)</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="nota-badge <?php echo ($nota['avaliacao1'] ?? 0) >= 10 ? 'nota-alta' : (($nota['avaliacao1'] ?? 0) > 0 ? 'nota-baixa' : ''); ?>">
                                                <?php echo ($nota['avaliacao1'] ?? 0) ? number_format($nota['avaliacao1'], 1) : '-'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="nota-badge <?php echo ($nota['avaliacao2'] ?? 0) >= 10 ? 'nota-alta' : (($nota['avaliacao2'] ?? 0) > 0 ? 'nota-baixa' : ''); ?>">
                                                <?php echo ($nota['avaliacao2'] ?? 0) ? number_format($nota['avaliacao2'], 1) : '-'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="nota-badge <?php echo ($nota['mac'] ?? 0) >= 10 ? 'nota-alta' : (($nota['mac'] ?? 0) > 0 ? 'nota-baixa' : ''); ?>">
                                                <?php echo ($nota['mac'] ?? 0) ? number_format($nota['mac'], 1) : '-'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="nota-badge <?php echo ($nota['exame'] ?? 0) >= 10 ? 'nota-alta' : (($nota['exame'] ?? 0) > 0 ? 'nota-baixa' : ''); ?>">
                                                <?php echo ($nota['exame'] ?? 0) ? number_format($nota['exame'], 1) : '-'; ?>
                                            </span>
                                        </td>
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
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endfor; ?>
            </div>
            
            <!-- Resumo Final -->
            <div class="resumo-card">
                <div class="media-final">
                    <div class="label">Média Final</div>
                    <div class="value"><?php echo number_format($media_final, 1); ?></div>
                </div>
                <div class="situacao situacao-<?php echo $situacao_classe; ?>">
                    <i class="fas fa-<?php echo $situacao == 'Aprovado' ? 'check-circle' : ($situacao == 'Reprovado' ? 'times-circle' : 'clock'); ?> me-2"></i>
                    <?php echo $situacao; ?>
                </div>
                <div class="text-muted small">
                    <i class="fas fa-info-circle me-1"></i>
                    Mínimo para aprovação: 10 valores
                </div>
            </div>
            
            <!-- Ações de Exportação -->
            <div class="action-bar no-print">
                <button onclick="window.print()" class="btn-export btn-pdf">
                    <i class="fas fa-file-pdf"></i> Exportar PDF
                </button>
                <a href="?export=csv" class="btn-export btn-csv">
                    <i class="fas fa-file-excel"></i> Exportar CSV
                </a>
                <button onclick="window.print()" class="btn-export btn-print">
                    <i class="fas fa-print"></i> Imprimir
                </button>
            </div>
            
            <!-- Rodapé -->
            <div class="text-center text-muted small py-3 border-top">
                <i class="fas fa-calendar-alt me-1"></i>
                Documento emitido em <?php echo date('d/m/Y H:i'); ?>
                <span class="mx-2">|</span>
                <i class="fas fa-lock me-1"></i>
                Documento válido eletronicamente
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.querySelector('.main-content');
            sidebar.classList.toggle('hidden');
            mainContent.classList.toggle('sidebar-hidden');
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.trimestre-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                setTimeout(() => {
                    card.style.transition = 'opacity 0.5s ease';
                    card.style.opacity = '1';
                }, 100 + (index * 150));
            });
        });
    </script>
</body>
</html>