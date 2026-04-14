<!DOCTYPE html>
<html lang="pt">

<head>
    
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IPOK - Sistema Integrado de Gestão de Notas</title>
    <link rel="shortcut icon" href="assets/img/logo.png" type="image/x-icon">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary-blue: #1e3c72;
            --secondary-blue: #2a5298;
            --accent-blue: #3a6ab5;
            --light-blue: #e6f0fa;
            --white: #ffffff;
            --gray-light: #f8f9fa;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            color: #333;
            overflow-x: hidden;
        }

        /* Navbar */
        .navbar {
            background: var(--white);
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.1);
            padding: 15px 0;
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
            transition: all 0.3s ease;
        }

        .navbar.scrolled {
            padding: 10px 0;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
        }

        .navbar-brand {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary-blue) !important;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .navbar-brand img {
            height: 50px;
            width: auto;
            object-fit: contain;
        }

        .nav-link {
            color: var(--primary-blue) !important;
            font-weight: 500;
            margin: 0 15px;
            position: relative;
            padding: 5px 0;
        }

        .nav-link::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--secondary-blue);
            transition: width 0.3s ease;
        }

        .nav-link:hover::after {
            width: 100%;
        }

        .btn-login-nav {
            background: transparent;
            border: 2px solid var(--primary-blue);
            color: var(--primary-blue);
            padding: 8px 25px;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s ease;
            margin-left: 15px;
            text-decoration: none;
        }

        .btn-login-nav:hover {
            background: var(--primary-blue);
            color: white !important;
            transform: translateY(-2px);
        }

        /* Hero Section com Carrossel */
        .hero {
            min-height: 100vh;
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
            padding: 100px 0;
            margin-top: 0;
        }

        /* Carrossel de Fundo */
        .hero-carousel {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
        }

        .hero-carousel .carousel-item {
            height: 100vh;
            min-height: 100vh;
        }

        .hero-carousel .carousel-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            /* Reduzido o escurecimento para as imagens ficarem mais nítidas */
            filter: brightness(0.65);
            transition: transform 0.5s ease, filter 0.5s ease;
        }

        /* Efeito de zoom suave nas imagens */
        .hero-carousel .carousel-item.active img {
            transform: scale(1.05);
        }

        /* Overlay gradiente mais suave - degrade sutil nas bordas */
        .hero-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, 
                rgba(30, 60, 114, 0.6) 0%, 
                rgba(42, 82, 152, 0.5) 50%,
                rgba(30, 60, 114, 0.6) 100%);
            z-index: 1;
        }

        /* Conteúdo do Hero */
        .hero-content {
            position: relative;
            z-index: 2;
            color: white;
            text-align: center;
        }

        .hero-title {
            font-size: 3.5rem;
            font-weight: 700;
            margin-bottom: 20px;
            animation: fadeInUp 1s ease;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }

        .hero-subtitle {
            font-size: 1.2rem;
            margin-bottom: 30px;
            opacity: 0.95;
            animation: fadeInUp 1s ease 0.2s both;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.3);
        }

        .hero-buttons {
            animation: fadeInUp 1s ease 0.4s both;
        }

        .btn-primary-custom {
            background: white;
            color: var(--primary-blue);
            padding: 15px 40px;
            border-radius: 50px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            margin-right: 15px;
            transition: all 0.3s ease;
            border: 2px solid white;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .btn-primary-custom:hover {
            background: transparent;
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .btn-outline-custom {
            background: transparent;
            color: white;
            padding: 15px 40px;
            border-radius: 50px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            border: 2px solid white;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .btn-outline-custom:hover {
            background: white;
            color: var(--primary-blue);
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        /* Logo com efeito de brilho */
        .hero-logo {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            padding: 10px;
            display: inline-block;
            backdrop-filter: blur(5px);
            transition: all 0.3s ease;
        }

        .hero-logo:hover {
            transform: scale(1.05);
            background: rgba(255, 255, 255, 0.2);
        }

        /* Indicadores do Carrossel - Estilizados */
        .hero-carousel .carousel-indicators {
            bottom: 30px;
            z-index: 2;
        }

        .hero-carousel .carousel-indicators button {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background-color: white;
            opacity: 0.6;
            margin: 0 6px;
            transition: all 0.3s ease;
            border: none;
        }

        .hero-carousel .carousel-indicators button.active {
            opacity: 1;
            background-color: var(--secondary-blue);
            transform: scale(1.2);
            box-shadow: 0 0 10px rgba(255, 255, 255, 0.5);
        }

        /* Controles do Carrossel */
        .hero-carousel .carousel-control-prev,
        .hero-carousel .carousel-control-next {
            width: 5%;
            opacity: 0;
            transition: opacity 0.4s ease;
            z-index: 2;
        }

        .hero:hover .carousel-control-prev,
        .hero:hover .carousel-control-next {
            opacity: 0.8;
        }

        .hero-carousel .carousel-control-prev:hover,
        .hero-carousel .carousel-control-next:hover {
            opacity: 1;
            transform: scale(1.1);
        }

        .hero-carousel .carousel-control-prev-icon,
        .hero-carousel .carousel-control-next-icon {
            background-color: rgba(0, 0, 0, 0.5);
            border-radius: 50%;
            padding: 20px;
            background-size: 50%;
            transition: all 0.3s ease;
        }

        /* Features Section */
        .features {
            padding: 100px 0;
            background: var(--white);
            position: relative;
            z-index: 2;
        }

        .section-title {
            text-align: center;
            margin-bottom: 60px;
        }

        .section-title h2 {
            color: var(--primary-blue);
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 15px;
        }

        .section-title p {
            color: #6c757d;
            font-size: 1.1rem;
            max-width: 700px;
            margin: 0 auto;
        }

        .feature-card {
            background: var(--white);
            padding: 40px 30px;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            text-align: center;
            height: 100%;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 60px rgba(30, 60, 114, 0.2);
        }

        .feature-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
            color: white;
            font-size: 2rem;
        }

        .feature-card h3 {
            color: var(--primary-blue);
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 15px;
        }

        .feature-card p {
            color: #6c757d;
            margin-bottom: 0;
        }

        /* Stats Section */
        .stats {
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            padding: 80px 0;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .stats::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"><path fill="%23ffffff" fill-opacity="0.1" d="M0,96L48,112C96,128,192,160,288,160C384,160,480,128,576,122.7C672,117,768,139,864,154.7C960,171,1056,181,1152,170.7C1248,160,1344,128,1392,112L1440,96L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path></svg>');
            background-repeat: no-repeat;
            background-position: bottom;
            background-size: cover;
            opacity: 0.1;
        }

        .stat-item {
            text-align: center;
            position: relative;
            z-index: 2;
        }

        .stat-number {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .stat-label {
            font-size: 1.1rem;
            opacity: 0.9;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* CTA Section */
        .cta {
            padding: 100px 0;
            background: var(--light-blue);
            text-align: center;
        }

        .cta h2 {
            color: var(--primary-blue);
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 20px;
        }

        .cta p {
            color: #6c757d;
            font-size: 1.2rem;
            max-width: 700px;
            margin: 0 auto 40px;
        }

        .btn-cta {
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            color: white;
            padding: 18px 50px;
            border-radius: 50px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            border: none;
            font-size: 1.1rem;
        }

        .btn-cta:hover {
            transform: translateY(-3px);
            box-shadow: 0 20px 40px rgba(30, 60, 114, 0.4);
            color: white;
        }

        /* Footer */
        .footer {
            background: var(--primary-blue);
            color: white;
            padding: 60px 0 30px;
        }

        .footer h5 {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 25px;
            position: relative;
            padding-bottom: 10px;
        }

        .footer h5::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 50px;
            height: 2px;
            background: var(--secondary-blue);
        }

        .footer-links {
            list-style: none;
            padding: 0;
        }

        .footer-links li {
            margin-bottom: 12px;
        }

        .footer-links a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .footer-links a:hover {
            color: white;
        }

        .social-links a {
            display: inline-block;
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            text-align: center;
            line-height: 40px;
            color: white;
            margin-right: 10px;
            transition: all 0.3s ease;
        }

        .social-links a:hover {
            background: var(--secondary-blue);
            transform: translateY(-3px);
        }

        .copyright {
            text-align: center;
            padding-top: 30px;
            margin-top: 30px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.9rem;
        }

        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .hero-title {
                font-size: 2.5rem;
            }

            .hero-subtitle {
                font-size: 1rem;
            }

            .btn-primary-custom,
            .btn-outline-custom {
                padding: 12px 25px;
                margin-bottom: 10px;
                display: block;
                text-align: center;
                margin-right: 0;
            }

            .section-title h2 {
                font-size: 2rem;
            }

            .feature-card {
                margin-bottom: 30px;
            }

            .stat-item {
                margin-bottom: 30px;
            }

            .hero-carousel .carousel-control-prev,
            .hero-carousel .carousel-control-next {
                opacity: 0.5;
            }
        }
    </style>
</head>

<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <img src="assets/img/logo.png" alt="IPOK" class="ipok-logo" onerror="this.src='https://via.placeholder.com/50?text=IPOK'">
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#recursos">Recursos</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#beneficios">Benefícios</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#sobre">Sobre</a>
                    </li>
                    <li class="nav-item">
                        <a class="btn-login-nav" href="login.php">
                            <i class="fas fa-sign-in-alt me-2"></i>Portal do Aluno
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section com Carrossel -->
    <section class="hero">
        <!-- Carrossel de Imagens de Fundo -->
        <div id="heroCarousel" class="carousel slide hero-carousel" data-bs-ride="carousel" data-bs-interval="5000">
            <div class="carousel-inner">
                <div class="carousel-item active">
                    <img src="assets/img/escola3.png" alt="IPOK - Vista da Escola">
                </div>
                <div class="carousel-item">
                    <img src="assets/img/escola1.png" alt="IPOK - Sala de Aula">
                </div>
                <div class="carousel-item">
                    <img src="assets/img/escola3.png" alt="IPOK - Biblioteca">
                </div>
            </div>
            
            <!-- Controles do Carrossel -->
            <button class="carousel-control-prev" type="button" data-bs-target="#heroCarousel" data-bs-slide="prev">
                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Anterior</span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#heroCarousel" data-bs-slide="next">
                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Próximo</span>
            </button>
            
            <!-- Indicadores -->
            <div class="carousel-indicators">
                <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Slide 1"></button>
                <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="1" aria-label="Slide 2"></button>
                <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="2" aria-label="Slide 3"></button>
            </div>
        </div>
        
        <!-- Overlay mais suave para melhor contraste -->
        <div class="hero-overlay"></div>
        
        <!-- Conteúdo -->
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8 mx-auto text-center hero-content">
                    <div class="hero-logo mb-4">
                        <img src="assets/img/logo.png" alt="Logotipo SISG" class="img-fluid" style="max-width: 180px;" onerror="this.src='https://via.placeholder.com/180?text=IPOK'">
                    </div>

                    <h1 class="hero-title">
                        <span style="font-weight: 300;">SISG</span><br>
                        Sistema Integrado de Gestão de Notas
                    </h1>
                    <p class="hero-subtitle">
                        Transformando a experiência acadêmica com tecnologia moderna e eficiente
                    </p>
                    <div class="hero-buttons">
                        <a href="login.php" class="btn-primary-custom">
                            <i class="fas fa-user-graduate me-2"></i>Portal do Aluno
                        </a>
                        <a href="#recursos" class="btn-outline-custom">
                            <i class="fas fa-info-circle me-2"></i>Saiba Mais
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="recursos" class="features">
        <div class="container">
            <div class="section-title">
                <h2>Recursos Principais</h2>
                <p>Funcionalidades completas para uma gestão acadêmica moderna e integrada</p>
            </div>

            <div class="row g-4">
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3>Gestão de Utilizadores</h3>
                        <p>Administre alunos, professores e administrativos com diferentes níveis de acesso</p>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-book-open"></i>
                        </div>
                        <h3>Lançamento de Notas</h3>
                        <p>Professores podem lançar notas de forma intuitiva por turma e disciplina</p>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <h3>Relatórios Acadêmicos</h3>
                        <p>Gere relatórios detalhados e boletins automaticamente</p>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <h3>Períodos Letivos</h3>
                        <p>Controle total sobre a abertura e fechamento de trimestres</p>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-lock"></i>
                        </div>
                        <h3>Segurança</h3>
                        <p>Sistema seguro com proteção contra brute force e logs de auditoria</p>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-mobile-alt"></i>
                        </div>
                        <h3>Acesso Mobile</h3>
                        <p>Interface responsiva acessível de qualquer dispositivo</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats">
        <div class="container">
            <div class="row">
                <div class="col-md-3 col-6">
                    <div class="stat-item">
                        <div class="stat-number">500+</div>
                        <div class="stat-label">Alunos</div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-item">
                        <div class="stat-number">30+</div>
                        <div class="stat-label">Professores</div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-item">
                        <div class="stat-number">15</div>
                        <div class="stat-label">Cursos</div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-item">
                        <div class="stat-number">100%</div>
                        <div class="stat-label">Digital</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section id="beneficios" class="cta">
        <div class="container">
            <h2>Pronto para modernizar a gestão acadêmica?</h2>
            <p>Faça parte do futuro da educação no Instituto Politécnico do Kituma</p>
            <a href="login.php" class="btn-cta">
                <i class="fas fa-user-graduate me-2"></i>Acessar Portal do Aluno
            </a>
        </div>
    </section>

    <!-- Footer -->
    <footer id="sobre" class="footer">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 mb-4">
                    <h5>Sobre o IPOK</h5>
                    <p style="color: rgba(255,255,255,0.8);">
                        Instituto Politécnico do Kituma, formando profissionais de excelência para o futuro de Angola.
                    </p>
                    <div class="social-links mt-3">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-linkedin-in"></i></a>
                        <a href="#"><i class="fab fa-whatsapp"></i></a>
                    </div>
                </div>

                <div class="col-lg-2 col-md-6 mb-4">
                    <h5>Links Rápidos</h5>
                    <ul class="footer-links">
                        <li><a href="#recursos">Recursos</a></li>
                        <li><a href="#beneficios">Benefícios</a></li>
                        <li><a href="#sobre">Sobre</a></li>
                        <li><a href="login.php">Portal do Aluno</a></li>
                    </ul>
                </div>

                <div class="col-lg-3 col-md-6 mb-4">
                    <h5>Contactos</h5>
                    <ul class="footer-links">
                        <li><i class="fas fa-map-marker-alt me-2"></i> Uíge, Angola</li>
                        <li><i class="fas fa-phone me-2"></i> +244 123 456 789</li>
                        <li><i class="fas fa-envelope me-2"></i> info@ipok.ao</li>
                    </ul>
                </div>

                <div class="col-lg-3 mb-4">
                    <h5>Horário</h5>
                    <ul class="footer-links">
                        <li>Seg - Sex: 08:00 - 18:00</li>
                        <li>Sábado: 08:00 - 12:00</li>
                        <li>Domingo: Fechado</li>
                    </ul>
                </div>
            </div>

            <div class="copyright">
                <p>&copy; 2026 Instituto Politécnico do Kituma (IPOK). Todos os direitos reservados.</p>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Navbar scroll effect -->
    <script>
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });

        // Smooth scroll para links internos
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Controles do carrossel
        const carousel = document.getElementById('heroCarousel');
        if (carousel) {
            // Pausar carrossel ao passar o mouse
            carousel.addEventListener('mouseenter', () => {
                const bsCarousel = bootstrap.Carousel.getInstance(carousel);
                if (bsCarousel) bsCarousel.pause();
            });
            
            carousel.addEventListener('mouseleave', () => {
                const bsCarousel = bootstrap.Carousel.getInstance(carousel);
                if (bsCarousel) bsCarousel.cycle();
            });
        }

        // Efeito de parallax suave no scroll (opcional)
        window.addEventListener('scroll', () => {
            const scrolled = window.pageYOffset;
            const hero = document.querySelector('.hero');
            if (hero) {
                hero.style.backgroundPositionY = scrolled * 0.5 + 'px';
            }
        });
    </script>
</body>

</html>