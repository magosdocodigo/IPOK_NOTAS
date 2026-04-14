<?php
/**
 * Admin Sidebar Include
 * Use em todas as páginas do admin com: include '../includes/admin_sidebar.php';
 */

// Determine a página atual para marcar como ativa
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!-- Sidebar -->
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
        <a href="dashboard.php" class="menu-item <?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>">
            <i class="fas fa-home"></i>
            <span>Dashboard</span>
        </a>

        <div class="menu-title">GESTÃO</div>
        <a href="usuarios.php" class="menu-item <?php echo $current_page === 'usuarios.php' ? 'active' : ''; ?>">
            <i class="fas fa-users"></i>
            <span>Utilizadores</span>
        </a>
        <a href="turmas.php" class="menu-item <?php echo $current_page === 'turmas.php' ? 'active' : ''; ?>">
            <i class="fas fa-chalkboard"></i>
            <span>Turmas</span>
        </a>
        <a href="disciplinas.php" class="menu-item <?php echo $current_page === 'disciplinas.php' ? 'active' : ''; ?>">
            <i class="fas fa-book"></i>
            <span>Disciplinas</span>
        </a>
        <a href="periodos.php" class="menu-item <?php echo $current_page === 'periodos.php' ? 'active' : ''; ?>">
            <i class="fas fa-calendar-alt"></i>
            <span>Períodos</span>
        </a>

        <div class="menu-title">ATRIBUIÇÕES</div>
        <a href="atribuicoes.php" class="menu-item <?php echo $current_page === 'atribuicoes.php' ? 'active' : ''; ?>">
            <i class="fas fa-user-tie"></i>
            <span>Professor x Turma</span>
        </a>
        <a href="enturmacoes.php" class="menu-item <?php echo $current_page === 'enturmacoes.php' ? 'active' : ''; ?>">
            <i class="fas fa-user-plus"></i>
            <span>Enturmações</span>
        </a>

        <div class="menu-title">RELATÓRIOS</div>
        <a href="relatorios.php" class="menu-item <?php echo $current_page === 'relatorios.php' ? 'active' : ''; ?>">
            <i class="fas fa-chart-bar"></i>
            <span>Relatórios</span>
        </a>
        <a href="logs.php" class="menu-item <?php echo $current_page === 'logs.php' ? 'active' : ''; ?>">
            <i class="fas fa-history"></i>
            <span>Logs de Auditoria</span>
        </a>

        <div class="menu-title">CONTA</div>
        <a href="perfil.php" class="menu-item <?php echo $current_page === 'perfil.php' ? 'active' : ''; ?>">
            <i class="fas fa-user-cog"></i>
            <span>Meu Perfil</span>
        </a>
        <a href="../logout.php" class="menu-item">
            <i class="fas fa-sign-out-alt"></i>
            <span>Sair</span>
        </a>
    </div>
</div>
