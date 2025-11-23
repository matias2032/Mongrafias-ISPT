<?php
// Inclua os arquivos necessários
session_start();
include "conexao.php";
include "verifica_login_opcional.php"; 

// Lógica do Banner Hero
$sql_banner_acerca = "
    SELECT id_banner, titulo, descricao
    FROM banner_site 
    WHERE 
        posicao = 'hero' OR posicao = 'carrossel' 
        AND destino = 'Acerca de Nós' 
        AND ativo = 1 
        AND (data_inicio IS NULL OR data_inicio <= CURDATE()) 
        AND (data_fim IS NULL OR data_fim >= CURDATE())
    ORDER BY 
        id_banner DESC 
    LIMIT 1
";
$result_banner = $conexao->query($sql_banner_acerca);
$banner_ativo = $result_banner->fetch_assoc();

$imagens_banner = [];
if ($banner_ativo) {
    $banner_id = $banner_ativo['id_banner'];
    $sql_imagens = "
        SELECT caminho_imagem, ordem 
        FROM banner_imagens 
        WHERE id_banner = ? 
        ORDER BY ordem ASC
    ";
    $stmt_imagens = $conexao->prepare($sql_imagens);
    $stmt_imagens->bind_param("i", $banner_id);
    $stmt_imagens->execute();
    $result_imagens = $stmt_imagens->get_result();
    
    while ($img = $result_imagens->fetch_assoc()) {
        $imagens_banner[] = $img;
    }
    $stmt_imagens->close();
}
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acerca do ISPT | Instituto Superior Politécnico de Tete</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="css/index.css">
    <link rel="stylesheet" href="css/acerca.css">
</head>
<body>

   <header class="topbar">
  <div class="container">
    <div class="logo">
      <a href="index.php">
        <img class="logo" src="icones/logo.png" alt="Logo do ISPT">
      </a>
    </div>

    <nav class="nav-desktop">
      <a href="index.php">Início</a>
      <a href="ver_monografias.php">Ver Monografias</a>
      <a href="acerca_de_nos.php" class="active">Acerca de nós</a>
      <a href="contactos.php">Contactos</a>
    </nav>

    <button class="menu-btn" id="menuBtnMobile">&#9776;</button>
  </div>

  <nav id="mobileMenu" class="nav-mobile hidden">
    <a href="index.php">Início</a>
    <a href="ver_monografias.php">Ver monografias</a>
    <a href="acerca_de_nos.php" class="active">Acerca de nós</a>
    <a href="contactos.php">Contactos</a>
  </nav>
</header>

    <section class="hero-container fade-in">
        <?php if ($banner_ativo && !empty($imagens_banner)): ?>
            <div class="carrossel-wrapper">
                <button class="carrossel-btn prev" aria-label="Anterior">&#10094;</button>
                <div class="banner-carrossel">
                    <?php foreach ($imagens_banner as $i => $img): ?>
                    <div class="carrossel-slide" style="background-image: url('<?= htmlspecialchars($img['caminho_imagem']) ?>');">
                        <div class="hero-content">
                            <h1><?= htmlspecialchars($banner_ativo['titulo']) ?></h1>
                            <p class="hero-subtitle"><?= htmlspecialchars($banner_ativo['descricao']) ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button class="carrossel-btn next" aria-label="Próximo">&#10095;</button>
            </div>
        <?php else: ?>
            <div class="hero" style="background-image: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.5)), url('https://placehold.co/1200x600/2b2b2b/89b67f?text=ISPT');">
                   <div class="hero-content">
                        <h1>Instituto Superior Politécnico de Tete</h1>
                        <p class="hero-subtitle">Formando profissionais qualificados para o desenvolvimento de Moçambique</p>
                   </div>
            </div>
        <?php endif; ?>
    </section>

    <main class="main-container">
        
        <!-- NAVEGAÇÃO POR ABAS -->
        <section class="tabs-section">
            <div class="tabs-header">
                <button class="tab-btn active" data-tab="quem-somos">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                    </svg>
                    Quem Somos
                </button>
                <button class="tab-btn" data-tab="missao-visao">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                    Missão, Visão e Valores
                </button>
                <button class="tab-btn" data-tab="perfil">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                    </svg>
                    Perfil Educacional
                </button>
            </div>

            <!-- CONTEÚDO DAS ABAS -->
            <div class="tabs-content">
                
                <!-- ABA 1: QUEM SOMOS -->
                <div class="tab-panel active" id="quem-somos">
                    <div class="section-padding section-quem-somos">
                        <div class="section-header">
                            <span class="section-badge">Conheça-nos</span>
                            <h2 class="section-title">Quem Somos</h2>
                        </div>

                        <div class="content-card">
                            <div class="card-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                </svg>
                            </div>
                            <div class="card-content">
                                <h3 class="card-title">Denominação e Natureza</h3>
                                <p>O Instituto Superior Politécnico de Tete, também designado por ISPT ou o Politécnico, criado pelo Decreto nº 32/2005 de Conselho de Ministros de 23 de Agosto, é uma pessoa colectiva de direito público, dotada de personalidade jurídica, e goza de autonomia científica, pedagógica e administrativa.</p>
                                <p>O Politécnico é de âmbito nacional e desenvolve as suas actividades em todo o território da República de Moçambique.</p>
                                <p>O Politécnico de Tete é uma Instituição de âmbito Central com Sede na Cidade de Tete, com a missão de promover o desenvolvimento económico e social das comunidades locais, da região e do país, através do ensino técnico-profissional, da educação orientada para a economia, da incubação de empresas, assim como da prestação de serviços profissionais.</p>
                            </div>
                        </div>

                        <div class="content-card">
                            <div class="card-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
                                </svg>
                            </div>
                            <div class="card-content">
                                <h3 class="card-title">Atribuições e Objectivos</h3>
                                <ul class="objectives-list">
                                    <li>Contribuir, através da formação de técnicos moçambicanos qualificados, nos esforços nacionais de aumento dos índices de crescimento económico e de combate à pobreza absoluta no país</li>
                                    <li>Formar profissionais qualificados e que sejam capazes de responder às necessidades do desenvolvimento da produção e criação material e intelectual relacionadas com as suas áreas de estudo e formação</li>
                                    <li>Contribuir na provisão de necessidades das comunidades locais através da prestação de serviço que se enquadram nas atribuições das alíneas anteriores</li>
                                    <li>Contribuir na promoção da geração, transferência e difusão de conhecimentos e tecnologias, visando o desenvolvimento sustentável local, regional e nacional</li>
                                    <li>Promover o estudo da aplicação da ciência e da técnica nas áreas prioritárias do desenvolvimento local, regional e nacional e divulgar os seus resultados</li>
                                    <li>Criar e viabilizar no seio dos seus formandos um espírito empreendedor e orientado ao auto-emprego</li>
                                    <li>Constituir-se num centro de recursos técnico e tecnológico para a indústria e as comunidades locais e regionais</li>
                                </ul>
                            </div>
                        </div>

                        <div class="programs-grid">
                            <h3 class="programs-title">Programas Académicos</h3>
                            <div class="program-card">
                                <div class="program-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                                    </svg>
                                </div>
                                <h4>Programa de Docência</h4>
                                <p>Ensino técnico-profissional de excelência</p>
                            </div>
                            <div class="program-card">
                                <div class="program-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                                    </svg>
                                </div>
                                <h4>Programa de Investigação e Extensão</h4>
                                <p>Pesquisa aplicada e inovação tecnológica</p>
                            </div>
                            <div class="program-card">
                                <div class="program-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <h4>Programa de Apoio Institucional</h4>
                                <p>Suporte à gestão e desenvolvimento institucional</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ABA 2: MISSÃO, VISÃO E VALORES -->
                <div class="tab-panel" id="missao-visao">
                    <div class="section-padding section-mvv">
                        <div class="section-header">
                            <span class="section-badge">Nossos Pilares</span>
                            <h2 class="section-title">Missão, Visão e Valores</h2>
                        </div>

                        <div class="mvv-grid">
                            <div class="mvv-card">
                                <div class="mvv-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                    </svg>
                                </div>
                                <h3>Missão</h3>
                                <p>Promover o desenvolvimento económico e social das comunidades locais, da região e do país, através do ensino técnico-profissional, da educação orientada para a economia, da incubação de empresas, assim como da prestação de serviços profissionais.</p>
                            </div>

                            <div class="mvv-card">
                                <div class="mvv-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                </div>
                                <h3>Visão</h3>
                                <p>Ser considerada uma instituição de referência privilegiada na formação de quadros no sector mineiro em Moçambique.</p>
                            </div>

                            <div class="mvv-card mvv-card-wide">
                                <div class="mvv-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                                    </svg>
                                </div>
                                <h3>Valores</h3>
                                <div class="values-tags">
                                    <span class="value-tag">Conhecimento</span>
                                    <span class="value-tag">Qualidade</span>
                                    <span class="value-tag">Ética</span>
                                    <span class="value-tag">Eficiência</span>
                                    <span class="value-tag">Igualdade</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ABA 3: PERFIL EDUCACIONAL -->
                <div class="tab-panel" id="perfil">
                    <div class="section-padding section-perfil">
                        <div class="section-header">
                            <span class="section-badge">Excelência Educativa</span>
                            <h2 class="section-title">Perfil Educacional</h2>
                        </div>

                        <p class="perfil-intro">O Instituto Superior Politécnico de Tete adota metodologias de ensino modernas e programas académicos orientados para o desenvolvimento de competências práticas e aplicáveis no mercado de trabalho. O modelo formativo assenta em quatro pilares fundamentais:</p>

                        <div class="pilares-grid">
                            <div class="pilar-card">
                                <div class="pilar-number">01</div>
                                <div class="pilar-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                    </svg>
                                </div>
                                <h3>Eficiência</h3>
                                <p>O ISPT utiliza um sistema educacional baseado em competências, integrado ao ensino tradicional orientado para o conhecimento disciplinar. Os estudantes adquirem uma base sólida de conhecimentos essenciais, que posteriormente são aplicados em contextos reais. O objetivo é formar profissionais capazes de atuar com eficácia no mercado, privilegiando a prática e a resolução de problemas.</p>
                            </div>

                            <div class="pilar-card">
                                <div class="pilar-number">02</div>
                                <div class="pilar-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                    </svg>
                                </div>
                                <h3>Flexibilidade</h3>
                                <p>Os programas de estudo oferecem percursos formativos diversificados com diferentes pontos de saída. Após dois anos, o estudante pode obter um Certificado que o habilita ao exercício profissional. Pode prosseguir para o Grau de Bacharel e posteriormente para a Licenciatura. O estudante pode integrar-se no mercado de trabalho de forma progressiva e ajustada aos seus objetivos.</p>
                            </div>

                            <div class="pilar-card">
                                <div class="pilar-number">03</div>
                                <div class="pilar-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <h3>Globalização</h3>
                                <p>Os cursos do ISPT estão alinhados aos padrões de formação da África Austral, respondendo à dinâmica da Comunidade de Desenvolvimento da África Austral (SADC). Este enquadramento promove a mobilidade académica e aumenta o reconhecimento regional e internacional das qualificações, ampliando as oportunidades de carreira para os nossos graduados.</p>
                            </div>

                            <div class="pilar-card">
                                <div class="pilar-number">04</div>
                                <div class="pilar-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <h3>Praticabilidade</h3>
                                <p>A componente prática é parte essencial do ensino no ISPT. Todos os estudantes passam por períodos de estágio ou prática profissional em instituições e empresas relevantes, assegurando experiência real de trabalho antes da conclusão do curso. Esse contacto direto com o ambiente profissional torna a formação mais completa e prepara o estudante para uma inserção imediata e competitiva no mercado.</p>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </section>

    </main>
    
    <footer class="footer-main">
        <div class="footer-container">
            <div class="footer-content">
                <div class="footer-section">
                    <h4>ISPT</h4>
                    <p>Instituto Superior Politécnico de Tete - Formando o futuro de Moçambique</p>
                    <div class="social-links">
                        <a href="https://facebook.com/ispttete" target="_blank" aria-label="Facebook" class="social-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                            </svg>
                        </a>
                        <a href="https://twitter.com/ispt" target="_blank" aria-label="Twitter" class="social-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/>
                            </svg>
                        </a>
                        <a href="https://instagram.com/ispttete" target="_blank" aria-label="Instagram" class="social-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 0C8.74 0 8.333.015 7.053.072 5.775.132 4.905.333 4.14.63c-.789.306-1.459.717-2.126 1.384S.935 3.35.63 4.14C.333 4.905.131 5.775.072 7.053.012 8.333 0 8.74 0 12s.015 3.667.072 4.947c.06 1.277.261 2.148.558 2.913.306.788.717 1.459 1.384 2.126.667.666 1.336 1.079 2.126 1.384.766.296 1.636.499 2.913.558C8.333 23.988 8.74 24 12 24s3.667-.015 4.947-.072c1.277-.06 2.148-.262 2.913-.558.788-.306 1.459-.718 2.126-1.384.666-.667 1.079-1.335 1.384-2.126.296-.765.499-1.636.558-2.913.06-1.28.072-1.687.072-4.947s-.015-3.667-.072-4.947c-.06-1.277-.262-2.149-.558-2.913-.306-.789-.718-1.459-1.384-2.126C21.319 1.347 20.651.935 19.86.63c-.765-.297-1.636-.499-2.913-.558C15.667.012 15.26 0 12 0zm0 2.16c3.203 0 3.585.016 4.85.071 1.17.055 1.805.249 2.227.415.562.217.96.477 1.382.896.419.42.679.819.896 1.381.164.422.36 1.057.413 2.227.057 1.266.07 1.646.07 4.85s-.015 3.585-.074 4.85c-.061 1.17-.256 1.805-.421 2.227-.224.562-.479.96-.899 1.382-.419.419-.824.679-1.38.896-.42.164-1.065.36-2.235.413-1.274.057-1.649.07-4.859.07-3.211 0-3.586-.015-4.859-.074-1.171-.061-1.816-.256-2.236-.421-.569-.224-.96-.479-1.379-.899-.421-.419-.69-.824-.9-1.38-.165-.42-.359-1.065-.42-2.235-.045-1.26-.061-1.649-.061-4.844 0-3.196.016-3.586.061-4.861.061-1.17.255-1.814.42-2.234.21-.57.479-.96.9-1.381.419-.419.81-.689 1.379-.898.42-.166 1.051-.361 2.221-.421 1.275-.045 1.65-.06 4.859-.06l.045.03zm0 3.678c-3.405 0-6.162 2.76-6.162 6.162 0 3.405 2.76 6.162 6.162 6.162 3.405 0 6.162-2.76 6.162-6.162 0-3.405-2.76-6.162-6.162-6.162zM12 16c-2.21 0-4-1.79-4-4s1.79-4 4-4 4 1.79 4 4-1.79 4-4 4zm7.846-10.405c0 .795-.646 1.44-1.44 1.44-.795 0-1.44-.646-1.44-1.44 0-.794.646-1.439 1.44-1.439.793-.001 1.44.645 1.44 1.439z"/>
                            </svg>
                        </a>
                        <a href="https://youtube.com/ispttete" target="_blank" aria-label="YouTube" class="social-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>
                            </svg>
                        </a>
                        <a href="https://linkedin.com/company/ispt" target="_blank" aria-label="LinkedIn" class="social-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
                            </svg>
                        </a>
                    </div>
                </div>
                <div class="footer-section">
                    <h4>Links Rápidos</h4>
                    <a href="index.php">Início</a>
                    <a href="ver_monografias.php">Ver Monografias</a>
                    <a href="acerca_de_nos.php">Acerca de Nós</a>
                    <a href="contactos.php">Contactos</a>
                </div>
                <div class="footer-section">
                    <h4>Recursos</h4>
                            <a href="https://propinas.ispt.ac.mz/login.php">Propinas</a>
                    <a href="https://esura.ispt.ac.mz/esura_ispt/">E-SURA</a>
                    <a href="https://elearning.ispt.ac.mz/">Moodle ISPT</a>
                </div>
                <div class="footer-section">
                    <h4>Contacto</h4>
                    <p>Email: info@ispt.ac.mz</p>
                    <p>Tel: (+258) 252 20454</p>
                    <p>Estrada Nacional nº 7, Km 1<br>Bairro Matundo, Tete</p>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2025 Instituto Superior Politécnico de Tete. All Rights Reserved.</p>
            </div>
        </div>
    </footer>

    <script>
    // ============================================================
    // NAVEGAÇÃO MOBILE
    // ============================================================
    const menuBtnMobile = document.getElementById("menuBtnMobile");
    const mobileMenu = document.getElementById("mobileMenu");

    if (menuBtnMobile && mobileMenu) {
        menuBtnMobile.addEventListener("click", () => {
            mobileMenu.classList.toggle("hidden");
        });
    }

    // ============================================================
    // CARROSSEL HERO
    // ============================================================
    const bannerCarrossel = document.querySelector('.banner-carrossel');
    const carrosselSlides = document.querySelectorAll('.carrossel-slide');
    const prevBtn = document.querySelector('.carrossel-btn.prev');
    const nextBtn = document.querySelector('.carrossel-btn.next');

    let currentSlide = 0;

    function showSlide(index) {
        if (!bannerCarrossel || !carrosselSlides.length) return;

        carrosselSlides.forEach(slide => {
            const content = slide.querySelector('.hero-content');
            if (content) {
                content.style.animation = 'none';
                content.offsetHeight;
                content.style.animation = '';
            }
        });

        bannerCarrossel.scrollTo({
            left: carrosselSlides[index].offsetLeft,
            behavior: 'smooth'
        });
        currentSlide = index;
    }

    function nextSlide() {
        currentSlide = (currentSlide + 1) % carrosselSlides.length;
        showSlide(currentSlide);
    }

    function prevSlide() {
        currentSlide = (currentSlide - 1 + carrosselSlides.length) % carrosselSlides.length;
        showSlide(currentSlide);
    }

    if (prevBtn) {
        prevBtn.addEventListener('click', prevSlide);
        nextBtn.addEventListener('click', nextSlide);
    }

    if (bannerCarrossel) {
        bannerCarrossel.addEventListener('scroll', () => {
            const scrollLeft = bannerCarrossel.scrollLeft;
            const slideWidth = carrosselSlides.length > 0 ? carrosselSlides[0].offsetWidth : 0;
            if (slideWidth > 0) {
                currentSlide = Math.round(scrollLeft / slideWidth);
            }
        });
    }

    if (carrosselSlides.length > 0) {
        showSlide(0);
    }

    // ============================================================
    // SISTEMA DE NAVEGAÇÃO POR ABAS
    // ============================================================
    document.addEventListener("DOMContentLoaded", () => {
        const tabButtons = document.querySelectorAll('.tab-btn');
        const tabPanels = document.querySelectorAll('.tab-panel');

        function switchTab(targetTab) {
            // Remove active de todos os botões e painéis
            tabButtons.forEach(btn => btn.classList.remove('active'));
            tabPanels.forEach(panel => panel.classList.remove('active'));

            // Adiciona active ao botão clicado e ao painel correspondente
            const activeButton = document.querySelector(`[data-tab="${targetTab}"]`);
            const activePanel = document.getElementById(targetTab);

            if (activeButton && activePanel) {
                activeButton.classList.add('active');
                activePanel.classList.add('active');

                // Scroll suave para o topo da seção de abas
                document.querySelector('.tabs-section').scrollIntoView({ 
                    behavior: 'smooth', 
                    block: 'start' 
                });
            }
        }

        // Event listeners para os botões
        tabButtons.forEach(button => {
            button.addEventListener('click', () => {
                const targetTab = button.getAttribute('data-tab');
                switchTab(targetTab);
            });
        });

        // Verifica se há hash na URL para abrir aba específica
        if (window.location.hash) {
            const hash = window.location.hash.substring(1);
            if (document.getElementById(hash)) {
                switchTab(hash);
            }
        }
    });

    // ============================================================
    // ANIMAÇÃO DE SCROLL PARA CARDS
    // ============================================================
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, observerOptions);

    // Observa todos os cards para animação
    document.querySelectorAll('.content-card, .program-card, .mvv-card, .pilar-card').forEach(el => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(30px)';
        el.style.transition = 'all 0.6s ease-out';
        observer.observe(el);
    });
    </script>
</body>
</html>