<?php
// splash.php
session_start();
require_once 'config/database.php';
require_once 'includes/auth.php';

// Verificar se o usuário está logado
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Timeout de sessão (30 minutos)
if (isset($_SESSION['ultima_atividade']) && (time() - $_SESSION['ultima_atividade']) > 1800) {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit;
}
$_SESSION['ultima_atividade'] = time();

$user_nome = $_SESSION['user_nome'];
$user_nivel = $_SESSION['user_nivel']; // admin, professor, aluno
$user_matricula = $_SESSION['user_matricula'] ?? '';

// Mensagens personalizadas por tipo de usuário
$mensagens = [
    'admin' => 'Bem-vindo ao SISG NOTA',
    'professor' => 'Bem-vindo ao SISG NOTA',
    'aluno' => 'Bem-vindo ao SISG NOTA'
];

$submensagens = [
    'admin' => 'Administrador do Sistema',
    'professor' => 'Portal do Professor',
    'aluno' => 'Portal do Aluno'
];

$mensagem = $mensagens[$user_nivel] ?? 'Bem-vindo ao SISG NOTA';
$submensagem = $submensagens[$user_nivel] ?? 'Sistema de Gestão de Notas';

// Ícone personalizado por tipo de usuário
$icone = '';
switch ($user_nivel) {
    case 'admin':
        $icone = 'bi-shield-check';
        break;
    case 'professor':
        $icone = 'bi-easel';
        break;
    case 'aluno':
        $icone = 'bi-mortarboard-fill';
        break;
    default:
        $icone = 'bi-person';
}

// Definir URL de destino baseado no tipo
$destino = '';
switch ($user_nivel) {
    case 'admin':
        $destino = 'admin/dashboard.php';
        break;
    case 'professor':
        $destino = 'professor/dashboard.php';
        break;
    case 'aluno':
        $destino = 'aluno/dashboard.php';
        break;
    default:
        $destino = 'index.php';
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SISG NOTA - Bem-vindo</title>
    <link rel="shortcut icon" href="assets/img/logo.png" type="image/x-icon">
    <link rel="icon" type="image/jpeg" href="assets/img/logo.png">
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            height: 100%;
            width: 100%;
            overflow: hidden;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Oxygen', 'Ubuntu', 'Cantarell', sans-serif;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #0052cc;
            position: relative;
        }

        /* Efeito de fundo animado */
        body::before {
            content: '';
            position: absolute;
            width: 400px;
            height: 400px;
            background: rgba(0, 102, 204, 0.08);
            border-radius: 50%;
            top: -100px;
            left: -100px;
            animation: float 6s ease-in-out infinite;
        }

        body::after {
            content: '';
            position: absolute;
            width: 300px;
            height: 300px;
            background: rgba(0, 102, 204, 0.05);
            border-radius: 50%;
            bottom: -80px;
            right: -80px;
            animation: float 8s ease-in-out infinite reverse;
        }

        @keyframes float {
            0%, 100% {
                transform: translateY(0px);
            }
            50% {
                transform: translateY(30px);
            }
        }

        .splash-wrapper {
            position: relative;
            z-index: 1;
        }

        .splash-container {
            text-align: center;
            padding: 3rem 2rem;
            max-width: 500px;
            width: 90%;
            position: relative;
            z-index: 1;
        }

        @keyframes containerEntry {
            from {
                opacity: 0;
                transform: scale(0.95);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .icon-wrapper {
            font-size: 5rem;
            margin-bottom: 1.5rem;
            opacity: 0;
            transform: scale(0.5) translateY(20px);
            color: #0052cc;
            text-shadow: 0 2px 8px rgba(0, 82, 204, 0.2);
        }

        .logo-splash {
            width: 80px;
            height: 80px;
            margin: 0 auto 2rem;
            opacity: 0;
            transform: scale(0.5) translateY(20px);
            border-radius: 50%;
            background: #f0f7ff;
            padding: 8px;
            border: 2px solid #0052cc;
            box-shadow: 0 4px 12px rgba(0, 102, 204, 0.15);
        }

        .logo-splash img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
        }

        .message {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            opacity: 0;
            transform: translateY(30px);
            letter-spacing: -0.5px;
            line-height: 1.2;
            color: #0052cc;
        }

        .submessage {
            font-size: 1rem;
            margin-bottom: 2.5rem;
            opacity: 0;
            transform: translateY(20px);
            letter-spacing: 0.5px;
            text-transform: uppercase;
            font-size: 0.85rem;
            font-weight: 500;
            color: #666;
        }

        .user-info {
            background: #f0f7ff;
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            opacity: 0;
            transform: translateY(20px);
            border: 1px solid #d4e6f7;
        }

        .user-name {
            font-size: 0.95rem;
            line-height: 1.6;
            color: #0052cc;
        }

        .user-name strong {
            display: block;
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .user-type {
            font-size: 0.8rem;
            opacity: 0.6;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #666;
        }

        .loader {
            display: flex;
            justify-content: center;
            gap: 0.6rem;
            margin-top: 2rem;
            margin-bottom: 1.5rem;
        }

        .loader-dot {
            width: 10px;
            height: 10px;
            background: #0052cc;
            border-radius: 50%;
            opacity: 0;
            transform: scale(0);
            box-shadow: 0 2px 8px rgba(0, 82, 204, 0.2);
        }

        .footer-text {
            font-size: 0.9rem;
            margin-top: 1rem;
            opacity: 0;
            transform: translateY(10px);
            color: #999;
        }

        .progress-bar {
            width: 60%;
            height: 2px;
            background: #e0e7f1;
            margin: 1.5rem auto 0;
            border-radius: 1px;
            opacity: 0;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: #0052cc;
            width: 0%;
            border-radius: 1px;
        }

        /* Responsividade */
        @media (max-width: 576px) {
            .splash-container {
                padding: 2.5rem 1.5rem;
            }

            .logo-splash {
                width: 70px;
                height: 70px;
                margin: 0 auto 1.5rem;
            }

            .icon-wrapper {
                font-size: 3.5rem;
                margin-bottom: 1rem;
            }

            .message {
                font-size: 1.5rem;
            }

            .submessage {
                font-size: 0.8rem;
                margin-bottom: 1.5rem;
            }

            .user-info {
                padding: 1rem;
                margin-bottom: 1.5rem;
            }

            .user-name strong {
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="splash-wrapper">
        <div class="splash-container">
            <div class="logo-splash">
                <img src="assets/img/logo.png" alt="IPOK Logo" onerror="this.onerror=null; this.src='https://via.placeholder.com/80?text=IPOK';">
            </div>

            <div class="icon-wrapper">
                <i class="bi <?php echo $icone; ?>"></i>
            </div>

            <div class="message">
                <?php echo htmlspecialchars($mensagem); ?>
            </div>

            <div class="submessage">
                <?php echo htmlspecialchars($submensagem); ?>
            </div>

            <?php if ($user_nome): ?>
                <div class="user-info">
                    <div class="user-name">
                        <strong><?php echo htmlspecialchars($user_nome); ?></strong>
                        <div class="user-type">
                            <?php 
                            $tipo_display = [
                                'admin' => 'Administrador',
                                'professor' => 'Professor',
                                'aluno' => 'Aluno'
                            ];
                            echo $tipo_display[$user_nivel] ?? ucfirst($user_nivel); 
                            ?>
                            <?php if ($user_matricula): ?>
                                - Matrícula: <?php echo htmlspecialchars($user_matricula); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="loader">
                <div class="loader-dot"></div>
                <div class="loader-dot"></div>
                <div class="loader-dot"></div>
            </div>

            <div class="progress-bar">
                <div class="progress-fill"></div>
            </div>

            <div class="footer-text">
                A preparar o seu ambiente...
            </div>
        </div>
    </div>

    <script>
        window.addEventListener('load', () => {
            const tl = gsap.timeline();

            // Logo com bounce
            tl.to('.logo-splash', {
                opacity: 1,
                scale: 1,
                y: 0,
                duration: 0.8,
                ease: 'back.out(1.7)'
            })

            // Ícone com bounce
            .to('.icon-wrapper', {
                opacity: 1,
                scale: 1,
                y: 0,
                duration: 0.8,
                ease: 'back.out(1.7)'
            }, '-=0.4')

            // Mensagem principal
            .to('.message', {
                opacity: 1,
                y: 0,
                duration: 0.6,
                ease: 'power2.out'
            }, '-=0.4')

            // Submensagem
            .to('.submessage', {
                opacity: 1,
                y: 0,
                duration: 0.5,
                ease: 'power2.out'
            }, '-=0.3')

            // Info do usuário
            .to('.user-info', {
                opacity: 1,
                y: 0,
                duration: 0.5,
                ease: 'power2.out'
            }, '-=0.3')

            // Dots do loader (staggered)
            .to('.loader-dot', {
                opacity: 1,
                scale: 1,
                duration: 0.4,
                stagger: 0.1,
                ease: 'back.out(1.7)'
            }, '-=0.2')

            // Barra de progresso
            .to('.progress-bar', {
                opacity: 1,
                duration: 0.3
            }, '-=0.2')

            // Preencher barra de progresso
            .to('.progress-fill', {
                width: '100%',
                duration: 2.2,
                ease: 'power1.inOut'
            }, '-=0.1')

            // Footer
            .to('.footer-text', {
                opacity: 1,
                y: 0,
                duration: 0.4
            }, '-=1.8');

            // Redirecionar após animação
            setTimeout(() => {
                window.location.href = '<?php echo $destino; ?>';
            }, 3000); // 3 segundos total
        });
    </script>
</body>
</html>