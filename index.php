<?php
session_start();
include "conexao.php";
include "verifica_login_opcional.php";

/* ==============================
   BUSCAR APENAS BANNERS DO HERO/CARROSSEL
================================= */
$sql_carrossel = "
    SELECT id_banner, titulo, descricao
    FROM banner_site 
    WHERE 
        (posicao = 'hero' OR posicao = 'carrossel')
        AND destino = 'Início' 
        AND ativo = 1 
        AND (data_inicio IS NULL OR data_inicio <= CURDATE())
        AND (data_fim IS NULL OR data_fim >= CURDATE())
    ORDER BY id_banner DESC 
    LIMIT 1
";

$result_carrossel = $conexao->query($sql_carrossel);
$carrossel_ativo = $result_carrossel->fetch_assoc();

/* ==============================
   BUSCAR IMAGENS DO BANNER ATIVO
================================= */
$imagens_carrossel = [];

if ($carrossel_ativo) {
    $banner_id = $carrossel_ativo['id_banner'];

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
        $imagens_carrossel[] = $img;
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restaurante Virtual | Início</title>
    <link rel="stylesheet" href="css/index.css">
    <script src="js/darkmode1.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-white">

<!-- ================================================
     NOVO CABEÇALHO
================================================ -->
<header class="topbar">
  <div class="container">

    <!-- LOGO -->
    <div class="logo">
      <a href="index.php">
        <img class="logo" src="icones/logo.png" alt="Logo do ISPT">
      </a>
    </div>

    <!-- MENU DESKTOP -->
    <nav class="nav-desktop">
      <a href="index.php" class="active">Início</a>
      <a href="ver_monografias.php">Ver Monografias</a>
      <a href="acerca_de_nos.php">Acerca de nós</a>
      <a href="contactos.php">Contactos</a>
    </nav>

    <!-- BOTÃO MOBILE -->
    <button class="menu-btn" id="menuBtnMobile">&#9776;</button>
  </div>

  <!-- MENU MOBILE -->
  <nav id="mobileMenu" class="nav-mobile hidden">
    <a href="index.php" class="active">Início</a>
    <a href="ver_monografias.php">Ver monografias</a>
    <a href="acerca_de_nos.php">Acerca de nós</a>
    <a href="ajuda.php">Ajuda</a>
  </nav>
</header>


<!-- ================================================
     CARROSSEL HERO
================================================ -->
<section class="hero-container fade-in">
    <?php if ($carrossel_ativo && !empty($imagens_carrossel)): ?>
        
        <div class="carrossel-wrapper">
            <button class="carrossel-btn prev" aria-label="Anterior">&#10094;</button>

            <div class="banner-carrossel">
                <?php foreach ($imagens_carrossel as $img): ?>
                    <div class="carrossel-slide" 
                         style="background-image: url('<?= htmlspecialchars($img['caminho_imagem']) ?>');">
                        <div class="hero-content">
                            <h2><?= htmlspecialchars($carrossel_ativo['titulo']) ?></h2>
                            <p><?= htmlspecialchars($carrossel_ativo['descricao']) ?></p>
                  
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <button class="carrossel-btn next" aria-label="Próximo">&#10095;</button>
        </div>

    <?php else: ?>
        <div class="hero" style="background-image: url('https://i.imgur.com/8R2u6rj.jpg');">
            <div class="hero-content">
                <h2>Bem-vindo ao sabor!</h2>
                <p>Peça os seus hambúrgueres favoritos com apenas um clique.</p>
            
            </div>
        </div>
    <?php endif; ?>
</section>



<!-- ================================================
     RODAPÉ
================================================ -->
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
                    <a href="ajuda.php">Ajuda</a>
                </div>
                <div class="footer-section">
                    <h4>Recursos</h4>
                    <a href="#">Propinas</a>
                    <a href="#">Formulários</a>
                    <a href="#">E-SURA</a>
                    <a href="#">Moodle ISPT</a>
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

<!-- ================================================
     JS DO MENU MOBILE + CARROSSEL
================================================ -->
<script>
const menuBtnMobile = document.getElementById("menuBtnMobile");
const mobileMenu = document.getElementById("mobileMenu");

menuBtnMobile.addEventListener("click", () => {
    mobileMenu.classList.toggle("hidden");
});

const bannerCarrossel = document.querySelector('.banner-carrossel');
const carrosselSlides = document.querySelectorAll('.carrossel-slide');
const prevBtn = document.querySelector('.carrossel-btn.prev');
const nextBtn = document.querySelector('.carrossel-btn.next');

let currentSlide = 0;

function showSlide(index) {
    if (!bannerCarrossel || carrosselSlides.length === 0) return;

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

showSlide(0);
</script>

</body>
</html>
