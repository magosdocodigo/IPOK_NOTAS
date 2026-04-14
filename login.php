<?php
// login.php
session_start();
require_once 'config/database.php';

// Geração de token CSRF
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

$csrf_token = generateCSRFToken();

// Se já estiver logado, redireciona
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['user_nivel'] === 'admin') {
        header('Location: admin/dashboard.php');
    } elseif ($_SESSION['user_nivel'] === 'professor') {
        header('Location: professor/dashboard.php');
    } elseif ($_SESSION['user_nivel'] === 'aluno') {
        header('Location: aluno/dashboard.php');
    }
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validação do token CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Token de segurança inválido. Tente novamente.";
    } else {
        $database = new Database();
        $db = $database->getConnection();
        
        $identificador = mysqli_real_escape_string($db, $_POST['identificador']);
        $senha = $_POST['senha'];
        $ip = $_SERVER['REMOTE_ADDR'];
        
        // Verificar tentativas de login (anti brute force)
        $check_attempts = $db->query("SELECT COUNT(*) as attempts FROM login_attempts WHERE ip = '$ip' AND tentativa_em > DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
        $attempts = $check_attempts->fetch_assoc();
        
        if ($attempts['attempts'] >= 5) {
            $error = "Muitas tentativas de login. Aguarde 5 minutos.";
        } else {
            // Registrar tentativa
            $db->query("INSERT INTO login_attempts (ip, identificador) VALUES ('$ip', '$identificador')");
            
            // Buscar usuário
            $query = "SELECT u.*, a.numero_matricula FROM usuarios u 
                      LEFT JOIN alunos a ON u.id = a.usuario_id 
                      WHERE (u.email = '$identificador' OR a.numero_matricula = '$identificador') AND u.ativo = 1";
            $result = $db->query($query);
            
            if ($result && $result->num_rows > 0) {
                $user = $result->fetch_assoc();
                
                // Verificar senha (usando password_hash)
                if (password_verify($senha, $user['senha'])) {
                    // Login bem-sucedido
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_nome'] = $user['nome'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_nivel'] = $user['nivel'];
                    
                    if ($user['nivel'] === 'aluno') {
                        $_SESSION['user_matricula'] = $user['numero_matricula'];
                    }
                    
                    // Atualizar último acesso
                    $db->query("UPDATE usuarios SET ultimo_acesso = NOW() WHERE id = " . $user['id']);
                    
                    // Registrar login no log de auditoria
                    $db->query("INSERT INTO logs_auditoria (usuario_id, acao, tabela, ip) 
                               VALUES ({$user['id']}, 'LOGIN', 'usuarios', '$ip')");
                    
                    // Redirecionar para splash.php
                    header('Location: splash.php');
                    exit();
                } else {
                    $error = "Credenciais inválidas!";
                }
            } else {
                $error = "Credenciais inválidas!";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SISG NOTA - Login</title>
    <link rel="shortcut icon" href="assets/img/logo.png" type="image/x-icon">
    
    <!-- Favicon -->
    <link rel="icon" type="image/jpeg" href="assets/img/ipok_logo.jpeg">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- GSAP -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js"></script>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f0f2f5 0%, #e6e9ef 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow-x: hidden;
        }

        /* Background particles effect */
        .bg-particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            pointer-events: none;
            overflow: hidden;
        }

        .particle {
            position: absolute;
            background: rgba(30, 77, 140, 0.1);
            border-radius: 50%;
            pointer-events: none;
        }

        .login-wrapper {
            width: 100%;
            max-width: 1300px;
            display: flex;
            border-radius: 32px;
            overflow: hidden;
            box-shadow: 0 40px 60px rgba(0, 0, 0, 0.15);
            position: relative;
            z-index: 10;
            backdrop-filter: blur(10px);
        }

        /* Lado Esquerdo - Branding */
        .brand-side {
            flex: 1.2;
            background: linear-gradient(135deg, #1e4d8c 0%, #2c5f9e 50%, #3a6db0 100%);
            color: white;
            padding: 60px 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .brand-side::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, transparent 70%);
            animation: slowRotate 25s linear infinite;
        }

        .brand-side::after {
            content: '';
            position: absolute;
            bottom: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%);
            animation: slowRotate 20s linear infinite reverse;
        }

        @keyframes slowRotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .brand-content {
            position: relative;
            z-index: 1;
        }

        .logo-wrapper {
            margin-bottom: 40px;
            text-align: center;
            transform: scale(0.9);
            opacity: 0;
        }

        .logo {
            width: 130px;
            height: 130px;
            border-radius: 50%;
            background: white;
            padding: 12px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            margin-bottom: 25px;
            object-fit: contain;
            transition: transform 0.3s ease;
        }

        .logo:hover {
            transform: scale(1.05);
        }

        .brand-title {
            font-size: 2.8rem;
            font-weight: 800;
            margin-bottom: 12px;
            letter-spacing: -1px;
            background: linear-gradient(135deg, #fff, #e0e8ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .brand-subtitle {
            font-size: 1.1rem;
            opacity: 0.95;
            margin-bottom: 40px;
            line-height: 1.6;
        }

        .brand-features {
            margin-top: 40px;
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: 18px;
            margin-bottom: 28px;
            opacity: 0;
            transform: translateX(-30px);
        }

        .feature-icon {
            width: 55px;
            height: 55px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            transition: all 0.3s ease;
        }

        .feature-item:hover .feature-icon {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.1);
        }

        .feature-text h4 {
            font-size: 1.15rem;
            font-weight: 700;
            margin-bottom: 6px;
        }

        .feature-text p {
            font-size: 0.9rem;
            opacity: 0.85;
            margin: 0;
        }

        /* Lado Direito - Formulário */
        .form-side {
            flex: 0.8;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            padding: 60px 50px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .form-container {
            width: 100%;
            max-width: 420px;
        }

        .mobile-logo {
            display: none;
            text-align: center;
            margin-bottom: 30px;
        }

        .mobile-logo img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            padding: 5px;
            border: 3px solid #1e4d8c;
        }

        .form-header {
            text-align: center;
            margin-bottom: 40px;
            opacity: 0;
            transform: translateY(20px);
        }

        .form-header h2 {
            font-size: 2.2rem;
            font-weight: 800;
            color: #1e4d8c;
            margin-bottom: 10px;
        }

        .form-header p {
            color: #666;
            font-size: 0.95rem;
        }

        /* Alertas */
        .alert-custom {
            padding: 15px 20px;
            border-radius: 14px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.95rem;
            animation: slideInDown 0.5s ease;
            backdrop-filter: blur(10px);
        }

        .alert-error {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            border: none;
            color: #991b1b;
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.1);
        }

        .alert-success {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            border: none;
            color: #065f46;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.1);
        }

        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Inputs */
        .input-group {
            position: relative;
            margin-bottom: 24px;
            opacity: 0;
            transform: translateX(30px);
        }

        .input-icon {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #1e4d8c;
            font-size: 1.1rem;
            z-index: 10;
            transition: all 0.3s ease;
        }

        .form-control {
            width: 100%;
            height: 58px;
            padding: 0 20px 0 50px;
            border: 2px solid #e9ecef;
            border-radius: 16px;
            font-size: 1rem;
            font-weight: 500;
            transition: all 0.3s ease;
            background: #f8fafc;
        }

        .form-control:focus {
            border-color: #1e4d8c;
            box-shadow: 0 0 0 5px rgba(30, 77, 140, 0.15);
            outline: none;
            background: white;
            transform: translateY(-2px);
        }

        .form-control:focus + .input-icon {
            transform: translateY(-50%) scale(1.1);
        }

        /* Password toggle */
        .password-toggle {
            position: absolute;
            right: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
            cursor: pointer;
            z-index: 10;
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }

        .password-toggle:hover {
            color: #1e4d8c;
            transform: translateY(-50%) scale(1.1);
        }

        /* Opções */
        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            opacity: 0;
            transform: translateY(20px);
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }

        .remember-me input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: #1e4d8c;
        }

        .remember-me label {
            color: #666;
            font-size: 0.95rem;
            cursor: pointer;
            margin: 0;
        }

        .forgot-password {
            color: #1e4d8c;
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 500;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .forgot-password:hover {
            color: #163a66;
            transform: translateX(3px);
        }

        /* Botão */
        .btn-login {
            width: 100%;
            height: 58px;
            background: linear-gradient(135deg, #1e4d8c 0%, #2c5f9e 100%);
            color: white;
            border: none;
            border-radius: 16px;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            position: relative;
            overflow: hidden;
            opacity: 0;
            transform: translateY(20px);
        }

        .btn-login::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }

        .btn-login:hover::before {
            width: 300px;
            height: 300px;
        }

        .btn-login:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(30, 77, 140, 0.4);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        /* Info de acesso */
        .login-info {
            margin-top: 30px;
            padding: 20px;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            opacity: 0;
            transform: scale(0.95);
        }

        .info-title {
            font-size: 0.9rem;
            font-weight: 700;
            color: #1e4d8c;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .info-list li {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #475569;
            font-size: 0.85rem;
            margin-bottom: 10px;
            padding: 5px 0;
        }

        .info-list li i {
            color: #1e4d8c;
            font-size: 0.9rem;
            width: 20px;
        }

        .back-home {
            text-align: center;
            margin-top: 25px;
            opacity: 0;
        }

        .back-home a {
            color: #666;
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .back-home a:hover {
            color: #1e4d8c;
            transform: translateX(-3px);
        }

        /* Responsividade */
        @media (max-width: 992px) {
            .login-wrapper {
                flex-direction: column;
                max-width: 550px;
            }

            .brand-side {
                display: none;
            }

            .form-side {
                padding: 45px 35px;
            }

            .mobile-logo {
                display: block;
            }
        }

        @media (max-width: 576px) {
            .form-side {
                padding: 35px 25px;
            }

            .form-header h2 {
                font-size: 1.8rem;
            }

            .form-options {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
        }

        /* Animações */
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }

        .floating {
            animation: float 4s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .pulse {
            animation: pulse 2s ease-in-out infinite;
        }
    </style>
</head>
<body>
    <!-- Background Particles -->
    <div class="bg-particles" id="particles"></div>

    <div class="login-wrapper">
        <!-- LADO ESQUERDO - BRANDING -->
        <div class="brand-side">
            <div class="brand-content">
                <div class="logo-wrapper" id="logoWrapper">
                    <img src="assets/img/logo.png" alt="IPOK Logo" class="logo floating" onerror="this.onerror=null; this.src='https://via.placeholder.com/130?text=IPOK';">
                    <h1 class="brand-title" id="brandTitle">SISG NOTA</h1>
                    <p class="brand-subtitle" id="brandSubtitle">Sistema Integrado de Gestão de Notas</p>
                </div>

                <div class="brand-features">
                    <div class="feature-item" id="feature1">
                        <div class="feature-icon">
                            <i class="fas fa-graduation-cap"></i>
                        </div>
                        <div class="feature-text">
                            <h4>Para Alunos</h4>
                            <p>Consulte suas notas, boletins e histórico escolar</p>
                        </div>
                    </div>

                    <div class="feature-item" id="feature2">
                        <div class="feature-icon">
                            <i class="fas fa-chalkboard-teacher"></i>
                        </div>
                        <div class="feature-text">
                            <h4>Para Professores</h4>
                            <p>Lance e gerencie notas das suas turmas</p>
                        </div>
                    </div>

                    <div class="feature-item" id="feature3">
                        <div class="feature-icon">
                            <i class="fas fa-user-cog"></i>
                        </div>
                        <div class="feature-text">
                            <h4>Para Administradores</h4>
                            <p>Gestão completa de utilizadores, turmas e períodos</p>
                        </div>
                    </div>
                </div>

                <div class="divider"></div>

                <p style="text-align: center; opacity: 0.8; margin-top: 20px;">
                    <i class="fas fa-shield-alt"></i> Acesso seguro e protegido
                </p>
            </div>
        </div>

        <!-- LADO DIREITO - FORMULÁRIO -->
        <div class="form-side">
            <div class="form-container">
                <!-- Logo para mobile -->
                <div class="mobile-logo" id="mobileLogo">
                    <img src="assets/img/ipok_logo.jpeg" alt="IPOK Logo" onerror="this.onerror=null; this.src='https://via.placeholder.com/80?text=IPOK';">
                    <h2 style="color: #1e4d8c; margin-top: 15px;">SISG NOTA</h2>
                </div>

                <div class="form-header" id="formHeader">
                    <h2>Bem-vindo</h2>
                    <p>Faça login para acessar o sistema</p>
                </div>

                <!-- Mensagens de alerta -->
                <?php if ($error): ?>
                    <div class="alert-custom alert-error" id="alertMessage">
                        <i class="fas fa-exclamation-circle"></i>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert-custom alert-success" id="alertMessage">
                        <i class="fas fa-check-circle"></i>
                        <?= htmlspecialchars($success) ?>
                    </div>
                <?php endif; ?>

                <!-- Formulário de login -->
                <form action="" method="POST" id="loginForm">
                    <!-- Token CSRF -->
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">

                    <!-- Campo de identificação -->
                    <div class="input-group" id="inputIdentificador">
                        <i class="fas fa-user input-icon"></i>
                        <input type="text" 
                               class="form-control" 
                               id="identificador"
                               name="identificador" 
                               placeholder="Email ou número de matrícula"
                               required
                               autofocus>
                    </div>

                    <!-- Campo de senha -->
                    <div class="input-group" id="inputSenha">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" 
                               class="form-control" 
                               id="senha"
                               name="senha" 
                               placeholder="Palavra-passe"
                               required>
                        <i class="fas fa-eye password-toggle" id="toggleSenha" onclick="togglePassword()"></i>
                    </div>

                    <!-- Opções -->
                    <div class="form-options" id="formOptions">
                        <label class="remember-me">
                            <input type="checkbox" name="lembrar" id="lembrar">
                            <label for="lembrar">Lembrar-me</label>
                        </label>
                        <a href="#" class="forgot-password" onclick="mostrarInfo(event)">
                            <i class="fas fa-question-circle"></i> Ajuda
                        </a>
                    </div>

                    <!-- Botão de login -->
                    <button type="submit" class="btn-login" id="btnLogin">
                        <i class="fas fa-sign-in-alt"></i>
                        <span>Entrar</span>
                    </button>
                </form>

                <!-- Informações de acesso -->
                <div class="login-info" id="infoAcesso" style="display: none;">
                    <div class="info-title">
                        <i class="fas fa-info-circle"></i>
                        Como aceder:
                    </div>
                    <ul class="info-list">
                        <li>
                            <i class="fas fa-user-graduate"></i>
                            <strong>Alunos:</strong> use o número de matrícula
                        </li>
                        <li>
                            <i class="fas fa-chalkboard-teacher"></i>
                            <strong>Professores:</strong> use o e-mail institucional
                        </li>
                        <li>
                            <i class="fas fa-user-cog"></i>
                            <strong>Administradores:</strong> use o e-mail de acesso
                        </li>
                    </ul>
                    <p style="color: #999; font-size: 0.8rem; margin-top: 10px; text-align: center;">
                        <i class="fas fa-lock"></i> Palavra-passe: qualquer comprimento
                    </p>
                </div>

                <!-- Link para voltar -->
                <div class="back-home" id="backHome">
                    <a href="index.php">
                        <i class="fas fa-arrow-left"></i> Voltar para o início
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- GSAP e ScrollTrigger -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js"></script>

    <script>
        // ============================================
        // PARTÍCULAS ANIMADAS
        // ============================================
        function createParticles() {
            const particlesContainer = document.getElementById('particles');
            const particleCount = 50;
            
            for (let i = 0; i < particleCount; i++) {
                const particle = document.createElement('div');
                particle.classList.add('particle');
                const size = Math.random() * 15 + 5;
                particle.style.width = size + 'px';
                particle.style.height = size + 'px';
                particle.style.left = Math.random() * 100 + '%';
                particle.style.top = Math.random() * 100 + '%';
                particle.style.animation = `float ${Math.random() * 10 + 5}s ease-in-out infinite`;
                particle.style.animationDelay = Math.random() * 5 + 's';
                particlesContainer.appendChild(particle);
            }
        }
        createParticles();

        // ============================================
        // ANIMAÇÕES GSAP
        // ============================================
        document.addEventListener('DOMContentLoaded', function() {
            // Timeline principal
            const tl = gsap.timeline();
            
            // Logo e brand
            tl.to('.logo-wrapper', {
                opacity: 1,
                scale: 1,
                duration: 0.8,
                ease: "back.out(1.2)"
            })
            .to('#brandTitle', {
                opacity: 1,
                y: 0,
                duration: 0.6,
                ease: "power2.out"
            }, "-=0.4")
            .to('#brandSubtitle', {
                opacity: 1,
                y: 0,
                duration: 0.6,
                ease: "power2.out"
            }, "-=0.3")
            
            // Features (stagger)
            .to('.feature-item', {
                opacity: 1,
                x: 0,
                duration: 0.6,
                stagger: 0.15,
                ease: "power2.out"
            }, "-=0.2")
            
            // Lado direito do formulário
            .to('#formHeader', {
                opacity: 1,
                y: 0,
                duration: 0.6,
                ease: "power2.out"
            }, "-=0.5")
            
            // Inputs (stagger)
            .to('#inputIdentificador, #inputSenha', {
                opacity: 1,
                x: 0,
                duration: 0.5,
                stagger: 0.1,
                ease: "power2.out"
            }, "-=0.3")
            
            // Opções
            .to('#formOptions', {
                opacity: 1,
                y: 0,
                duration: 0.5,
                ease: "power2.out"
            }, "-=0.2")
            
            // Botão
            .to('#btnLogin', {
                opacity: 1,
                y: 0,
                duration: 0.5,
                ease: "back.out(1)"
            }, "-=0.2")
            
            // Link voltar
            .to('#backHome', {
                opacity: 1,
                duration: 0.4,
                ease: "power2.out"
            }, "-=0.1");
            
            // Animações de hover com GSAP
            document.querySelectorAll('.feature-item').forEach(item => {
                item.addEventListener('mouseenter', () => {
                    gsap.to(item.querySelector('.feature-icon'), {
                        scale: 1.1,
                        duration: 0.3,
                        ease: "power2.out"
                    });
                });
                item.addEventListener('mouseleave', () => {
                    gsap.to(item.querySelector('.feature-icon'), {
                        scale: 1,
                        duration: 0.3,
                        ease: "power2.out"
                    });
                });
            });
            
            // Animação de foco nos inputs
            const inputs = document.querySelectorAll('.form-control');
            inputs.forEach(input => {
                input.addEventListener('focus', () => {
                    gsap.to(input, {
                        scale: 1.02,
                        duration: 0.2,
                        ease: "power2.out"
                    });
                });
                input.addEventListener('blur', () => {
                    gsap.to(input, {
                        scale: 1,
                        duration: 0.2,
                        ease: "power2.out"
                    });
                });
            });
            
            // Animação do botão
            const btn = document.getElementById('btnLogin');
            btn.addEventListener('mouseenter', () => {
                gsap.to(btn, {
                    scale: 1.02,
                    duration: 0.2,
                    ease: "power2.out"
                });
            });
            btn.addEventListener('mouseleave', () => {
                gsap.to(btn, {
                    scale: 1,
                    duration: 0.2,
                    ease: "power2.out"
                });
            });
            
            // Se houver alerta, animar entrada
            const alertMessage = document.getElementById('alertMessage');
            if (alertMessage) {
                gsap.fromTo(alertMessage, 
                    { opacity: 0, y: -20, scale: 0.9 },
                    { opacity: 1, y: 0, scale: 1, duration: 0.5, ease: "back.out(1)" }
                );
            }
        });
        
        // ============================================
        // FUNÇÕES INTERATIVAS
        // ============================================
        
        // Mostrar/ocultar senha
        function togglePassword() {
            const senhaInput = document.getElementById('senha');
            const toggleIcon = document.getElementById('toggleSenha');
            
            if (senhaInput.type === 'password') {
                senhaInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
                gsap.to(toggleIcon, { scale: 1.2, duration: 0.2, yoyo: true, repeat: 1 });
            } else {
                senhaInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
                gsap.to(toggleIcon, { scale: 1.2, duration: 0.2, yoyo: true, repeat: 1 });
            }
        }
        
        // Mostrar/ocultar informações de acesso
        function mostrarInfo(event) {
            event.preventDefault();
            const infoDiv = document.getElementById('infoAcesso');
            
            if (infoDiv.style.display === 'none') {
                infoDiv.style.display = 'block';
                gsap.fromTo(infoDiv, 
                    { opacity: 0, scale: 0.9, y: -10 },
                    { opacity: 1, scale: 1, y: 0, duration: 0.4, ease: "back.out(0.8)" }
                );
            } else {
                gsap.to(infoDiv, {
                    opacity: 0,
                    scale: 0.9,
                    y: -10,
                    duration: 0.3,
                    ease: "power2.in",
                    onComplete: () => {
                        infoDiv.style.display = 'none';
                    }
                });
            }
        }
        
        // Animação de submit
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const btn = document.getElementById('btnLogin');
            const originalText = btn.innerHTML;
            
            // Animação de loading
            gsap.to(btn, { scale: 0.98, duration: 0.1 });
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>A processar...';
            btn.disabled = true;
            
            // Permitir o envio (não cancelamos o submit)
            setTimeout(() => {
                btn.disabled = false;
            }, 5000);
        });
        
        // Lembrar-me (localStorage)
        if (localStorage.getItem('lembrar') === 'true') {
            document.getElementById('identificador').value = localStorage.getItem('identificador') || '';
            document.getElementById('lembrar').checked = true;
        }
        
        document.getElementById('lembrar').addEventListener('change', function(e) {
            if (e.target.checked) {
                localStorage.setItem('identificador', document.getElementById('identificador').value);
                localStorage.setItem('lembrar', 'true');
                gsap.to(e.target, { scale: 1.2, duration: 0.2, yoyo: true, repeat: 1 });
            } else {
                localStorage.removeItem('identificador');
                localStorage.removeItem('lembrar');
            }
        });
        
        // Animação de entrada do identificador
        document.getElementById('identificador').addEventListener('focus', function() {
            gsap.to('.input-icon', { color: '#1e4d8c', duration: 0.2 });
        });
        
        document.getElementById('identificador').addEventListener('blur', function() {
            if (!this.value) {
                gsap.to('.input-icon', { color: '#666', duration: 0.2 });
            }
        });
        
        // Parallax suave no mouse move
        document.addEventListener('mousemove', function(e) {
            const mouseX = e.clientX / window.innerWidth;
            const mouseY = e.clientY / window.innerHeight;
            
            gsap.to('.brand-side::before', {
                x: mouseX * 20,
                y: mouseY * 20,
                duration: 0.5,
                ease: "power2.out"
            });
            
            gsap.to('.brand-side::after', {
                x: -mouseX * 15,
                y: -mouseY * 15,
                duration: 0.5,
                ease: "power2.out"
            });
        });
    </script>
</body>
</html>