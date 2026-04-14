<?php
// admin/turma_disciplinas.php
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

// Obter ID da turma
$turma_id = isset($_GET['turma_id']) ? (int)$_GET['turma_id'] : 0;

if (!$turma_id) {
    header('Location: turmas.php');
    exit();
}

// Validar se turma existe
$turma_check = $db->query("SELECT id, nome FROM turmas WHERE id = $turma_id");
if (!$turma_check || $turma_check->num_rows === 0) {
    header('Location: turmas.php');
    exit();
}
$turma = $turma_check->fetch_assoc();

$message = '';
$error = '';

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ip = $_SERVER['REMOTE_ADDR'];
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'vincular':
                $disciplina_id = (int)$_POST['disciplina_id'];
                
                // Verificar se já existe para evitar duplicatas
                $check = $db->query("SELECT id FROM turma_disciplina WHERE turma_id = $turma_id AND disciplina_id = $disciplina_id");
                if ($check && $check->num_rows > 0) {
                    $error = "Esta disciplina já está vinculada à turma.";
                } else {
                    $query = "INSERT INTO turma_disciplina (turma_id, disciplina_id) VALUES ($turma_id, $disciplina_id)";
                    
                    if ($db->query($query)) {
                        // Log de auditoria
                        $db->query("INSERT INTO logs_auditoria (usuario_id, acao, tabela, registro_id, ip) 
                                   VALUES ({$_SESSION['user_id']}, 'VINCULAR_DISCIPLINA_TURMA', 'turma_disciplina', {$db->insert_id}, '$ip')");
                        
                        $message = "Disciplina vinculada com sucesso!";
                    } else {
                        $error = "Erro ao vincular disciplina: " . $db->error;
                    }
                }
                break;
                
            case 'desvincular':
                $turma_disciplina_id = (int)$_POST['turma_disciplina_id'];
                
                // Log antes de deletar
                $db->query("INSERT INTO logs_auditoria (usuario_id, acao, tabela, registro_id, ip) 
                           VALUES ({$_SESSION['user_id']}, 'DESVINCULAR_DISCIPLINA_TURMA', 'turma_disciplina', $turma_disciplina_id, '$ip')");
                
                $query = "DELETE FROM turma_disciplina WHERE id = $turma_disciplina_id AND turma_id = $turma_id";
                if ($db->query($query)) {
                    $message = "Disciplina desvinculada com sucesso!";
                } else {
                    $error = "Erro ao desvincular disciplina: " . $db->error;
                }
                break;
        }
    }
}

// Obter disciplinas vinculadas à turma
$disciplinas_vinculadas = $db->query("
    SELECT td.id, d.id as disciplina_id, d.nome, d.codigo 
    FROM turma_disciplina td
    JOIN disciplinas d ON td.disciplina_id = d.id
    WHERE td.turma_id = $turma_id
    ORDER BY d.nome
");

// Obter disciplinas disponíveis (não vinculadas)
$disciplinas_disponiveis = $db->query("
    SELECT id, nome, codigo 
    FROM disciplinas 
    WHERE id NOT IN (SELECT disciplina_id FROM turma_disciplina WHERE turma_id = $turma_id)
    ORDER BY nome
");

$page_title = "Vincular Disciplinas - Turma " . htmlspecialchars($turma['nome']);
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - IPOK</title>
    <link rel="shortcut icon" href="../assets/img/logo.png" type="image/x-icon">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Admin Sidebar CSS -->
    <link rel="stylesheet" href="../assets/css/admin_sidebar.css">

    <style>
        .container-disciplinas {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-top: 25px;
        }

        .panel-disciplinas {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,.05);
        }

        .panel-title {
            color: var(--primary-blue);
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 20px;
            border-bottom: 2px solid var(--light-blue);
            padding-bottom: 15px;
        }

        /* Search input inside panel */
        .search-box {
            margin-bottom: 20px;
            position: relative;
        }
        .search-box i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }
        .search-box input {
            padding-left: 35px;
            border-radius: 10px;
            border: 1px solid #dee2e6;
            height: 40px;
            width: 100%;
            transition: all 0.3s;
        }
        .search-box input:focus {
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 0.2rem rgba(30,60,114,0.1);
            outline: none;
        }

        .disciplina-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .disciplina-item {
            padding: 12px;
            margin-bottom: 10px;
            background: #f8f9fa;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all .3s;
        }

        .disciplina-item:hover {
            background: var(--light-blue);
            transform: translateX(5px);
        }

        .disciplina-info {
            flex: 1;
        }

        .disciplina-nome {
            font-weight: 600;
            color: var(--primary-blue);
            margin-bottom: 3px;
        }

        .disciplina-codigo {
            font-size: .85rem;
            color: #6c757d;
        }

        .btn-vincular, .btn-desvincular {
            padding: 5px 12px;
            font-size: .85rem;
            border-radius: 6px;
            cursor: pointer;
            border: none;
            transition: all .3s;
        }

        .btn-vincular {
            background: #28a745;
            color: white;
        }

        .btn-vincular:hover {
            background: #218838;
            transform: scale(1.05);
        }

        .btn-desvincular {
            background: #dc3545;
            color: white;
        }

        .btn-desvincular:hover {
            background: #c82333;
            transform: scale(1.05);
        }

        .empty-message {
            text-align: center;
            color: #6c757d;
            padding: 40px 20px;
            font-style: italic;
        }

        .alert-custom {
            border-radius: 10px;
            border: none;
            margin-bottom: 20px;
        }

        @media (max-width: 768px) {
            .container-disciplinas {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/admin_sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navigation -->
        <div class="top-nav">
            <div style="display: flex; align-items: center; gap: 15px;">
                <button class="btn btn-sm btn-outline-primary" onclick="toggleSidebar()" style="border-radius: 8px;">
                    <i class="fas fa-bars"></i>
                </button>
                <h1 class="page-title" style="margin: 0;">
                    <i class="fas fa-book-open me-2"></i><?php echo htmlspecialchars($turma['nome']); ?>
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

        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb" style="background: white; padding: 15px; border-radius: 10px;">
                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="turmas.php">Turmas</a></li>
                <li class="breadcrumb-item active">Vincular Disciplinas</li>
            </ol>
        </nav>

        <!-- Messages -->
        <?php if ($message): ?>
            <div class="alert alert-success alert-custom" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-custom" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Container de Disciplinas -->
        <div class="container-disciplinas">
            <!-- Painel Esquerdo: Disciplinas Vinculadas -->
            <div class="panel-disciplinas">
                <div class="panel-title">
                    <i class="fas fa-check-circle me-2"></i>Disciplinas Vinculadas
                </div>

                <!-- Search filter for vinculadas -->
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchVinculadas" placeholder="Pesquisar por nome ou código..." autocomplete="off">
                </div>

                <div class="disciplina-list" id="vinculadasList">
                    <?php if ($disciplinas_vinculadas && $disciplinas_vinculadas->num_rows > 0): ?>
                        <?php while ($disciplina = $disciplinas_vinculadas->fetch_assoc()): ?>
                            <div class="disciplina-item" data-nome="<?php echo strtolower(htmlspecialchars($disciplina['nome'])); ?>" data-codigo="<?php echo strtolower(htmlspecialchars($disciplina['codigo'])); ?>">
                                <div class="disciplina-info">
                                    <div class="disciplina-nome"><?php echo htmlspecialchars($disciplina['nome']); ?></div>
                                    <div class="disciplina-codigo">Código: <?php echo htmlspecialchars($disciplina['codigo']); ?></div>
                                </div>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="desvincular">
                                    <input type="hidden" name="turma_disciplina_id" value="<?php echo $disciplina['id']; ?>">
                                    <button type="submit" class="btn-desvincular" 
                                            onclick="return confirm('Deseja desvincular esta disciplina?');">
                                        <i class="fas fa-unlink"></i>
                                    </button>
                                </form>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-message" id="emptyVinculadas">
                            <i class="fas fa-inbox" style="font-size: 3rem; color: #ccc; margin-bottom: 10px;"></i>
                            <p>Nenhuma disciplina vinculada ainda.<br>Selecione disciplinas à direita.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Painel Direito: Disciplinas Disponíveis -->
            <div class="panel-disciplinas">
                <div class="panel-title">
                    <i class="fas fa-plus-circle me-2"></i>Disciplinas Disponíveis
                </div>

                <!-- Search filter for disponíveis -->
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchDisponiveis" placeholder="Pesquisar por nome ou código..." autocomplete="off">
                </div>

                <div class="disciplina-list" id="disponiveisList">
                    <?php if ($disciplinas_disponiveis && $disciplinas_disponiveis->num_rows > 0): ?>
                        <?php while ($disciplina = $disciplinas_disponiveis->fetch_assoc()): ?>
                            <div class="disciplina-item" data-nome="<?php echo strtolower(htmlspecialchars($disciplina['nome'])); ?>" data-codigo="<?php echo strtolower(htmlspecialchars($disciplina['codigo'])); ?>">
                                <div class="disciplina-info">
                                    <div class="disciplina-nome"><?php echo htmlspecialchars($disciplina['nome']); ?></div>
                                    <div class="disciplina-codigo">Código: <?php echo htmlspecialchars($disciplina['codigo']); ?></div>
                                </div>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="vincular">
                                    <input type="hidden" name="disciplina_id" value="<?php echo $disciplina['id']; ?>">
                                    <button type="submit" class="btn-vincular" title="Vincular disciplina">
                                        <i class="fas fa-link"></i>
                                    </button>
                                </form>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-message" id="emptyDisponiveis">
                            <i class="fas fa-check" style="font-size: 3rem; color: #28a745; margin-bottom: 10px;"></i>
                            <p>Todas as disciplinas estão<br>vinculadas a esta turma!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Botão de Voltar -->
        <div style="margin-top: 30px; text-align: center;">
            <a href="turmas.php" class="btn btn-secondary" style="border-radius: 8px;">
                <i class="fas fa-arrow-left me-2"></i>Voltar para Turmas
            </a>
        </div>
    </div><!-- /main-content -->

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <?php include '../includes/sidebar_toggle.php'; ?>

    <script>
        // Auto-hide alerts
        document.querySelectorAll('.alert-custom').forEach(alert => {
            setTimeout(() => {
                alert.style.transition = 'opacity 0.5s ease';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            }, 4000);
        });

        // Filter function for a given list and search input
        function setupFilter(listId, searchId) {
            const searchInput = document.getElementById(searchId);
            const container = document.getElementById(listId);
            if (!searchInput || !container) return;

            const items = container.querySelectorAll('.disciplina-item');
            const emptyMessage = container.querySelector('.empty-message');

            function filter() {
                const term = searchInput.value.trim().toLowerCase();
                let visibleCount = 0;

                items.forEach(item => {
                    const nome = item.getAttribute('data-nome') || '';
                    const codigo = item.getAttribute('data-codigo') || '';
                    const matches = nome.includes(term) || codigo.includes(term);
                    item.style.display = matches ? 'flex' : 'none';
                    if (matches) visibleCount++;
                });

                // Show/hide empty message if no visible items
                if (emptyMessage) {
                    if (visibleCount === 0 && items.length > 0) {
                        emptyMessage.style.display = 'block';
                    } else {
                        emptyMessage.style.display = 'none';
                    }
                } else if (items.length === 0 && !emptyMessage) {
                    // If there were no items originally, we may have an empty message placeholder
                    // We'll handle by checking if container has any child that is empty-message class
                    const existingEmpty = container.querySelector('.empty-message');
                    if (existingEmpty && visibleCount === 0) {
                        existingEmpty.style.display = 'block';
                    }
                }
            }

            searchInput.addEventListener('keyup', filter);
            filter(); // initial run
        }

        // Apply filters to both panels
        setupFilter('vinculadasList', 'searchVinculadas');
        setupFilter('disponiveisList', 'searchDisponiveis');
    </script>
</body>
</html>