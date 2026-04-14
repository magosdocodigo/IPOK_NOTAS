<?php
// professor/minhas-turmas.php
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
$ano_atual = date('Y');

// Buscar ID do professor logado
$query = "SELECT id FROM professores WHERE usuario_id = {$_SESSION['user_id']}";
$result = $db->query($query);
if ($result && $result->num_rows > 0) {
    $professor = $result->fetch_assoc();
    $professor_id = $professor['id'];
}

// Buscar todas as turmas do professor com detalhes
$query = "SELECT DISTINCT 
          t.id, 
          t.nome as turma_nome, 
          t.ano_letivo, 
          t.curso,
          COUNT(DISTINCT e.aluno_id) as total_alunos,
          GROUP_CONCAT(DISTINCT d.nome ORDER BY d.nome SEPARATOR '|') as disciplinas,
          COUNT(DISTINCT d.id) as total_disciplinas
          FROM atribuicoes a
          INNER JOIN turma_disciplina td ON a.turma_disciplina_id = td.id
          INNER JOIN turmas t ON td.turma_id = t.id
          INNER JOIN disciplinas d ON td.disciplina_id = d.id
          LEFT JOIN enturmacoes e ON t.id = e.turma_id
          WHERE a.professor_id = $professor_id
          GROUP BY t.id
          ORDER BY t.ano_letivo DESC, t.nome ASC";
$turmas = $db->query($query);

// Anos letivos para filtro
$anos_query = "SELECT DISTINCT ano_letivo FROM atribuicoes WHERE professor_id = $professor_id ORDER BY ano_letivo DESC";
$anos = $db->query($anos_query);

// Calcular total de turmas
$total_turmas = $turmas ? $turmas->num_rows : 0;

// Calcular total de alunos
$total_alunos = 0;
$turmas_array = [];
if ($turmas && $turmas->num_rows > 0) {
    while ($t = $turmas->fetch_assoc()) {
        $total_alunos += $t['total_alunos'];
        $turmas_array[] = $t;
    }
    mysqli_data_seek($turmas, 0);
}

// Calcular disciplinas distintas
$total_disciplinas = 0;
$disciplinas_list = [];
foreach ($turmas_array as $t) {
    $discs = explode('|', $t['disciplinas']);
    foreach ($discs as $disc) {
        if (!in_array($disc, $disciplinas_list)) {
            $disciplinas_list[] = $disc;
        }
    }
}
$total_disciplinas = count($disciplinas_list);

$page_title = "Minhas Turmas";
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IPOK - Minhas Turmas</title>
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
        
        /* Stats Cards */
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
            box-shadow: 0 5px 20px rgba(0,0,0,.05);
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
        
        /* Filter Section */
        .filter-section {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,.05);
        }
        
        /* Filter Pills */
        .filter-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .filter-pill {
            padding: 8px 20px;
            border-radius: 30px;
            background: #f0f2f5;
            color: #495057;
            border: none;
            font-weight: 500;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .filter-pill:hover {
            background: var(--primary-blue);
            color: white;
            transform: translateY(-2px);
        }
        
        .filter-pill.active {
            background: var(--primary-blue);
            color: white;
            box-shadow: 0 4px 12px rgba(30, 60, 114, 0.3);
        }
        
        .filter-pill i { margin-right: 8px; }
        
        /* Search Input */
        .search-input-group {
            position: relative;
        }
        
        .search-input-group i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }
        
        .search-input-group input {
            padding-left: 40px;
            height: 45px;
            border-radius: 12px;
            border: 1px solid #e9ecef;
        }
        
        /* Turma Cards */
        .turma-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 1.5rem;
        }
        
        .turma-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0,0,0,.05);
            transition: all 0.3s ease;
            border: 1px solid rgba(0,0,0,.05);
        }
        
        .turma-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(30,60,114,.15);
        }
        
        .turma-header {
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            color: white;
            padding: 20px;
            position: relative;
        }
        
        .turma-header h3 {
            margin: 0;
            font-size: 1.2rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .turma-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: rgba(255,255,255,0.2);
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
        }
        
        .turma-body {
            padding: 20px;
        }
        
        .info-row {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .info-icon {
            width: 40px;
            height: 40px;
            background: #f8fafc;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-blue);
            font-size: 1.2rem;
        }
        
        .info-content {
            flex: 1;
        }
        
        .info-label {
            font-size: 0.7rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .info-value {
            font-weight: 600;
            color: #2c3e50;
            font-size: 1rem;
        }
        
        .disciplinas-section {
            margin: 15px 0;
            padding: 15px 0;
            border-top: 1px solid #e9ecef;
            border-bottom: 1px solid #e9ecef;
        }
        
        .disciplinas-title {
            font-size: 0.8rem;
            color: var(--primary-blue);
            font-weight: 600;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .disciplinas-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .disciplina-tag {
            background: #f0f2f5;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            color: #495057;
        }
        
        .disciplina-tag i {
            margin-right: 4px;
            color: var(--primary-blue);
        }
        
        .stats-row {
            display: flex;
            gap: 15px;
            margin: 15px 0;
        }
        
        .stat-mini {
            flex: 1;
            text-align: center;
            padding: 10px;
            background: #f8fafc;
            border-radius: 10px;
        }
        
        .stat-mini .number {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--primary-blue);
        }
        
        .stat-mini .label {
            font-size: 0.65rem;
            color: #6c757d;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .btn-primary-custom {
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            color: white;
            border: none;
            padding: 10px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            flex: 1;
        }
        
        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(30,60,114,.3);
            color: white;
        }
        
        .btn-outline-custom {
            background: transparent;
            color: var(--primary-blue);
            border: 2px solid var(--primary-blue);
            padding: 8px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .btn-outline-custom:hover {
            background: var(--primary-blue);
            color: white;
            transform: translateY(-2px);
        }
        
        .empty-state {
            text-align: center;
            padding: 4rem;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,.05);
        }
        
        .empty-state i {
            font-size: 4rem;
            color: var(--primary-blue);
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        .empty-state h4 {
            color: var(--primary-blue);
            margin-bottom: 0.5rem;
        }
        
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); }
            .main-content { margin-left: 0; }
            .turma-grid {
                grid-template-columns: 1fr;
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
            <a href="minhas-turmas.php" class="menu-item active">
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
                <button class="btn btn-sm btn-outline-primary" onclick="toggleSidebar()" style="border-radius: 10px;">
                    <i class="fas fa-bars"></i>
                </button>
                <h1 class="page-title">
                    <i class="fas fa-chalkboard me-2"></i>Minhas Turmas
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
        
        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-chalkboard"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $total_turmas; ?></h3>
                    <p>Turmas</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $total_alunos; ?></h3>
                    <p>Alunos</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-book"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $total_disciplinas; ?></h3>
                    <p>Disciplinas</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $ano_atual; ?></h3>
                    <p>Ano Letivo</p>
                </div>
            </div>
        </div>
        
        <!-- Filter Section -->
        <div class="filter-section">
            <!-- Filtros em Pílulas -->
            <div class="filter-pills">
                <button class="filter-pill active" data-filter="all" onclick="filterByType('all')">
                    <i class="fas fa-chalkboard"></i> Todas Turmas
                </button>
                <button class="filter-pill" data-filter="ano" onclick="filterByAno()">
                    <i class="fas fa-calendar-alt"></i> Por Ano Letivo
                </button>
            </div>
            
            <div class="row align-items-end g-3">
                <div class="col-md-6">
                    <label class="form-label text-muted small mb-2">
                        <i class="fas fa-search me-1"></i>Pesquisar
                    </label>
                    <div class="search-input-group">
                        <i class="fas fa-search"></i>
                        <input type="text" class="form-control" id="searchInput" 
                               placeholder="Nome da turma, curso...">
                    </div>
                </div>
                
                <div class="col-md-4" id="filterAnoContainer" style="display: none;">
                    <label class="form-label text-muted small mb-2">
                        <i class="fas fa-calendar me-1"></i>Ano Letivo
                    </label>
                    <select class="form-select" id="filterAno">
                        <option value="">Todos os anos</option>
                        <?php 
                        mysqli_data_seek($anos, 0);
                        while ($ano = $anos->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $ano['ano_letivo']; ?>"><?php echo $ano['ano_letivo']; ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>
        </div>
        
        <!-- Turmas Grid -->
        <?php if ($turmas && $turmas->num_rows > 0): ?>
        <div class="turma-grid" id="turmaGrid">
            <?php while ($turma = $turmas->fetch_assoc()): 
                $disciplinas = explode('|', $turma['disciplinas']);
                $is_terminal = strpos($turma['curso'] ?? '', '13ª') !== false || strpos($turma['turma_nome'] ?? '', '13ª') !== false;
            ?>
            <div class="turma-card" 
                 data-nome="<?php echo strtolower($turma['turma_nome']); ?>"
                 data-ano="<?php echo $turma['ano_letivo']; ?>"
                 data-curso="<?php echo strtolower($turma['curso'] ?? ''); ?>">
                <div class="turma-header">
                    <h3>
                        <i class="fas fa-door-open"></i>
                        <?php echo htmlspecialchars($turma['turma_nome']); ?>
                    </h3>
                    <span class="turma-badge">
                        <i class="fas fa-calendar me-1"></i>
                        <?php echo $turma['ano_letivo']; ?>
                    </span>
                </div>
                <div class="turma-body">
                    <?php if ($turma['curso']): ?>
                    <div class="info-row">
                        <div class="info-icon">
                            <i class="fas fa-graduation-cap"></i>
                        </div>
                        <div class="info-content">
                            <div class="info-label">Curso</div>
                            <div class="info-value"><?php echo htmlspecialchars($turma['curso']); ?></div>
                        </div>
                        <?php if ($is_terminal): ?>
                        <span class="badge bg-warning text-dark">13ª Classe - Terminal</span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="disciplinas-section">
                        <div class="disciplinas-title">
                            <i class="fas fa-book"></i>
                            Disciplinas que leciona
                        </div>
                        <div class="disciplinas-list">
                            <?php foreach ($disciplinas as $disciplina): ?>
                            <span class="disciplina-tag">
                                <i class="fas fa-check-circle"></i>
                                <?php echo htmlspecialchars($disciplina); ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="stats-row">
                        <div class="stat-mini">
                            <div class="number"><?php echo $turma['total_alunos']; ?></div>
                            <div class="label">Alunos</div>
                        </div>
                        <div class="stat-mini">
                            <div class="number"><?php echo $turma['total_disciplinas']; ?></div>
                            <div class="label">Disciplinas</div>
                        </div>
                    </div>
                    
                    <div class="action-buttons">
                        <a href="lancar-notas.php?turma_id=<?php echo $turma['id']; ?>" class="btn-primary-custom">
                            <i class="fas fa-plus-circle"></i> Lançar Notas
                        </a>
                        <a href="editar-notas.php?turma_id=<?php echo $turma['id']; ?>" class="btn-outline-custom">
                            <i class="fas fa-edit"></i>
                        </a>
                        <a href="boletins.php?turma_id=<?php echo $turma['id']; ?>" class="btn-outline-custom">
                            <i class="fas fa-file-alt"></i>
                        </a>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
        
        <!-- Resultado da pesquisa -->
        <div id="noResults" class="empty-state" style="display: none;">
            <i class="fas fa-search"></i>
            <h4>Nenhuma turma encontrada</h4>
            <p class="text-muted">Tente outros termos de pesquisa ou limpe os filtros.</p>
        </div>
        
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-chalkboard"></i>
            <h4>Nenhuma turma atribuída</h4>
            <p class="text-muted">Você ainda não possui turmas atribuídas. Aguarde o administrador fazer as atribuições.</p>
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
        
        // Variáveis de filtro
        let currentFilter = 'all';
        
        function filterByType(type) {
            currentFilter = type;
            
            // Atualizar classes dos botões
            document.querySelectorAll('.filter-pill').forEach(pill => {
                pill.classList.remove('active');
            });
            document.querySelector(`.filter-pill[data-filter="${type}"]`).classList.add('active');
            
            // Mostrar/esconder container de filtro de ano
            document.getElementById('filterAnoContainer').style.display = type === 'ano' ? 'block' : 'none';
            
            // Limpar valor do filtro de ano
            if (type !== 'ano') document.getElementById('filterAno').value = '';
            
            aplicarFiltros();
        }
        
        function filterByAno() { filterByType('ano'); }
        
        function aplicarFiltros() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase().trim();
            const anoFilter = document.getElementById('filterAno').value;
            const cards = document.querySelectorAll('.turma-card');
            let resultadosVisiveis = 0;
            
            cards.forEach(card => {
                const nome = card.getAttribute('data-nome') || '';
                const curso = card.getAttribute('data-curso') || '';
                const ano = card.getAttribute('data-ano') || '';
                
                let match = true;
                
                // Filtro de pesquisa
                if (searchTerm && !nome.includes(searchTerm) && !curso.includes(searchTerm)) {
                    match = false;
                }
                
                // Filtro de ano
                if (match && currentFilter === 'ano' && anoFilter && ano !== anoFilter) {
                    match = false;
                }
                
                if (match) {
                    card.style.display = 'block';
                    resultadosVisiveis++;
                } else {
                    card.style.display = 'none';
                }
            });
            
            const noResults = document.getElementById('noResults');
            if (resultadosVisiveis === 0 && cards.length > 0) {
                noResults.style.display = 'block';
            } else {
                noResults.style.display = 'none';
            }
        }
        
        // Event listeners
        document.getElementById('searchInput').addEventListener('keyup', aplicarFiltros);
        document.getElementById('filterAno').addEventListener('change', aplicarFiltros);
        
        // Animar entrada dos cards
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.turma-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.4s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, 100 + (index * 50));
            });
        });
    </script>
</body>
</html>