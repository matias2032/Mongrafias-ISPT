<?php

include "conexao.php";
include "verifica_login.php"; 
include "info_usuario.php";

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

// ========================================
// CONSULTAS PARA OS GRÁFICOS
// ========================================

// 1. Total de downloads
$sqlTotalDownloads = "SELECT COUNT(*) as total FROM cliques_download";
$resultTotalDownloads = $conexao->query($sqlTotalDownloads);
$totalDownloads = $resultTotalDownloads->fetch_assoc()['total'];

// 2. Downloads por divisão
$sqlDownloadsDivisao = "
    SELECT 
        d.nome_divisao,
        COUNT(cd.id) as total_downloads
    FROM cliques_download cd
    INNER JOIN monografia m ON cd.id_monografia = m.id_monografia
    INNER JOIN divisao d ON m.id_divisao = d.id_divisao
    GROUP BY d.id_divisao, d.nome_divisao
    ORDER BY total_downloads DESC
";
$resultDownloadsDivisao = $conexao->query($sqlDownloadsDivisao);

$divisoes = [];
$downloads = [];
while ($row = $resultDownloadsDivisao->fetch_assoc()) {
    $divisoes[] = $row['nome_divisao'];
    $downloads[] = $row['total_downloads'];
}

// 3. Top 5 monografias mais baixadas
$sqlTopMonografias = "
    SELECT 
        m.tema,
        COUNT(cd.id) as total_downloads
    FROM cliques_download cd
    INNER JOIN monografia m ON cd.id_monografia = m.id_monografia
    GROUP BY m.id_monografia, m.tema
    ORDER BY total_downloads DESC
    LIMIT 5
";
$resultTopMonografias = $conexao->query($sqlTopMonografias);

$topMonografias = [];
$topDownloads = [];
while ($row = $resultTopMonografias->fetch_assoc()) {
    // Limita o tamanho do título para melhor visualização
    $tema = strlen($row['tema']) > 40 ? substr($row['tema'], 0, 40) . '...' : $row['tema'];
    $topMonografias[] = $tema;
    $topDownloads[] = $row['total_downloads'];
}

// 4. Estatísticas gerais
$sqlTotalMonografias = "SELECT COUNT(*) as total FROM monografia";
$totalMonografias = $conexao->query($sqlTotalMonografias)->fetch_assoc()['total'];

$sqlTotalUsuarios = "SELECT COUNT(*) as total FROM usuario";
$totalUsuarios = $conexao->query($sqlTotalUsuarios)->fetch_assoc()['total'];

$sqlMonografiasDestaque = "SELECT COUNT(*) as total FROM monografia WHERE destaque = 1";
$totalDestaque = $conexao->query($sqlMonografiasDestaque)->fetch_assoc()['total'];

?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Dashboard</title>
    <script src="logout_auto.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="css/admin.css">
    <script src="js/darkmode2.js"></script>
    <script src="js/sidebar.js"></script>
    <script src="js/dropdown2.js"></script>
    
    <style>
        .dashboard-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: linear-gradient(135deg, #89b67f, #75a768);
            color: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.15);
        }

        .stat-card h3 {
            margin: 0 0 10px 0;
            font-size: 0.9em;
            text-transform: uppercase;
            opacity: 0.9;
            color: white;
            text-align: left;
        }

        .stat-card .number {
            font-size: 2.5em;
            font-weight: bold;
            margin: 0;
        }

        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 30px;
            margin-top: 30px;
        }

        .chart-container {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.08);
        }

        .chart-container h2 {
            margin: 0 0 20px 0;
            font-size: 1.2em;
            color: #333;
            text-align: left;
        }

        .chart-wrapper {
            position: relative;
            height: 300px;
        }

        .full-width-chart {
            grid-column: 1 / -1;
        }

        /* Dark Mode */
        body.dark-mode .chart-container {
            background: #1a1a1a;
            color: #fff;
        }

        body.dark-mode .chart-container h2 {
            color: #fff;
        }

        body.dark-mode .stat-card {
            background: linear-gradient(135deg, #2c5f2d, #1a4d2e);
        }

        /* ========================================
           RESPONSIVIDADE TOTAL
        ======================================== */

        /* Tablets grandes e pequenos (768px - 1024px) */
        @media (max-width: 1024px) {
            .dashboard-container {
                padding: 15px;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
                margin-bottom: 30px;
            }

            .stat-card {
                padding: 20px;
            }

            .stat-card .number {
                font-size: 2.2em;
            }

            .charts-grid {
                grid-template-columns: 1fr;
                gap: 25px;
            }

            .chart-wrapper {
                height: 280px;
            }
        }

        /* Tablets em modo retrato (768px) */
        @media (max-width: 768px) {
            .dashboard-container {
                padding: 12px;
            }

            h1 {
                font-size: 1.5em;
                margin-bottom: 20px;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
                margin-bottom: 25px;
            }

            .stat-card {
                padding: 18px;
            }

            .stat-card h3 {
                font-size: 0.8em;
                margin-bottom: 8px;
            }

            .stat-card .number {
                font-size: 2em;
            }

            .charts-grid {
                grid-template-columns: 1fr;
                gap: 20px;
                margin-top: 20px;
            }

            .chart-container {
                padding: 20px;
            }

            .chart-container h2 {
                font-size: 1.1em;
                margin-bottom: 15px;
            }

            .chart-wrapper {
                height: 250px;
            }
        }

        /* Mobile grande (576px - 640px) */
        @media (max-width: 640px) {
            .dashboard-container {
                padding: 10px;
            }

            h1 {
                font-size: 1.3em;
                margin-bottom: 15px;
            }

            .stats-grid {
                grid-template-columns: 1fr 1fr;
                gap: 10px;
                margin-bottom: 20px;
            }

            .stat-card {
                padding: 15px;
            }

            .stat-card h3 {
                font-size: 0.75em;
                margin-bottom: 6px;
            }

            .stat-card .number {
                font-size: 1.8em;
            }

            .chart-container {
                padding: 15px;
            }

            .chart-container h2 {
                font-size: 1em;
                margin-bottom: 12px;
            }

            .chart-wrapper {
                height: 220px;
            }
        }

        /* Mobile médio (480px) */
        @media (max-width: 480px) {
            .dashboard-container {
                padding: 8px;
            }

            h1 {
                font-size: 1.2em;
                margin-bottom: 12px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                gap: 10px;
                margin-bottom: 20px;
            }

            .stat-card {
                padding: 15px;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            .stat-card h3 {
                font-size: 0.85em;
                margin: 0;
                flex: 1;
            }

            .stat-card .number {
                font-size: 2em;
                margin-left: 10px;
            }

            .charts-grid {
                gap: 15px;
                margin-top: 15px;
            }

            .chart-container {
                padding: 12px;
            }

            .chart-container h2 {
                font-size: 0.95em;
                margin-bottom: 10px;
            }

            .chart-wrapper {
                height: 200px;
            }
        }

        /* Mobile pequeno (≤ 360px) */
        @media (max-width: 360px) {
            .dashboard-container {
                padding: 5px;
            }

            h1 {
                font-size: 1.1em;
                margin-bottom: 10px;
            }

            .stats-grid {
                gap: 8px;
                margin-bottom: 15px;
            }

            .stat-card {
                padding: 12px;
            }

            .stat-card h3 {
                font-size: 0.75em;
            }

            .stat-card .number {
                font-size: 1.6em;
            }

            .chart-container {
                padding: 10px;
            }

            .chart-container h2 {
                font-size: 0.9em;
                margin-bottom: 8px;
            }

            .chart-wrapper {
                height: 180px;
            }
        }

        /* Landscape em mobile */
        @media (max-width: 900px) and (orientation: landscape) {
            .stats-grid {
                grid-template-columns: repeat(4, 1fr);
                gap: 10px;
            }

            .stat-card {
                padding: 12px;
            }

            .stat-card h3 {
                font-size: 0.75em;
            }

            .stat-card .number {
                font-size: 1.5em;
            }

            .charts-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
            }

            .full-width-chart {
                grid-column: 1 / -1;
            }

            .chart-wrapper {
                height: 200px;
            }
        }

        /* Ajustes para telas muito grandes */
        @media (min-width: 1920px) {
            .dashboard-container {
                max-width: 1800px;
            }

            .stat-card .number {
                font-size: 3em;
            }

            .chart-wrapper {
                height: 400px;
            }
        }
    </style>
</head>
<body>
    
    <button class="menu-btn">☰</button>
    <div class="sidebar-overlay"></div>
    
    <sidebar class="sidebar">
        <br><br>
        <a href="usuarios.php">Gerenciar Usuários</a>
        <a href="gerenciar_monografias.php">Gerenciar Monografias</a>
        <a href="visualizar_feedbacks.php">Ver Feedback</a>
        <a href="gerenciar_banner.php">Gerenciar Banners</a>
        <a href="historico_uploads_admin.php" id="link-historico-monografias">
            Ver histórico de Uploads
            <div class="notification-container">
                <i class="fa-solid fa-bell"></i>
                <span class="notification-count" id="notification-count">0</span>
            </div>
        </a>

        <div class="sidebar-user-wrapper">
            <div class="sidebar-user" id="usuarioDropdown">
                <div class="usuario-avatar" style="background-color: <?= $corAvatar ?>;">
                    <?= $iniciais ?>
                </div>
                <div class="usuario-dados">
                    <div class="usuario-nome"><?= $nome ?></div>
                    <div class="usuario-apelido"><?= $apelido ?></div>
                </div>
                <div class="usuario-menu" id="menuPerfil">
                    <a href='editarusuario.php?id_usuario=<?= $usuario['id_usuario'] ?>'>
                        <img class="icone" src="icones/user1.png" alt="Editar" title="Editar" id="iconeuser">  
                        Editar Dados Pessoais
                    </a>
                    <a href="alterar_senha2.php">
                        <img class="icone" src="icones/cadeado1.png" alt="Alterar" title="Alterar" id="iconecadeado"> 
                        Alterar Senha
                    </a>
                    <a href="logout.php">
                        <img class="iconelogout" src="icones/logout1.png" alt="Logout" title="Sair">  
                        Sair
                    </a>
                </div>
            </div>
            <img class="dark-toggle" id="darkToggle"
                   src="icones/lua.png"
                   alt="Modo Escuro"
                   title="Alternar modo escuro">
        </div>
    </sidebar>

    <div class="main">
        <div class="dashboard-container">
            <h1>Dashboard - Sistema de Monografias</h1>

            <!-- Cards de Estatísticas -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total de Downloads</h3>
                    <p class="number"><?= $totalDownloads ?></p>
                </div>
                <div class="stat-card">
                    <h3>Monografias Cadastradas</h3>
                    <p class="number"><?= $totalMonografias ?></p>
                </div>
                <div class="stat-card">
                    <h3>Usuários Registrados</h3>
                    <p class="number"><?= $totalUsuarios ?></p>
                </div>
                <div class="stat-card">
                    <h3>Monografias em Destaque</h3>
                    <p class="number"><?= $totalDestaque ?></p>
                </div>
            </div>

            <!-- Gráficos -->
            <div class="charts-grid">
                <!-- Gráfico de Pizza - Downloads por Divisão -->
                <div class="chart-container">
                    <h2>Downloads por Divisão</h2>
                    <div class="chart-wrapper">
                        <canvas id="chartDivisaoPizza"></canvas>
                    </div>
                </div>

                <!-- Gráfico de Barras - Downloads por Divisão -->
                <div class="chart-container">
                    <h2>Comparativo de Downloads</h2>
                    <div class="chart-wrapper">
                        <canvas id="chartDivisaoBarras"></canvas>
                    </div>
                </div>

                <!-- Gráfico de Barras - Top 5 Monografias -->
                <div class="chart-container full-width-chart">
                    <h2>Top 5 Monografias Mais Baixadas</h2>
                    <div class="chart-wrapper">
                        <canvas id="chartTopMonografias"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

<script>
// ========================================
// CONFIGURAÇÃO DOS GRÁFICOS
// ========================================

// Cores vibrantes para os gráficos
const coresPrincipais = [
    '#89b67f', // Verde principal
    '#ff6600', // Laranja
    '#3498db', // Azul
    '#e74c3c', // Vermelho
    '#f39c12', // Amarelo
    '#9b59b6', // Roxo
    '#1abc9c', // Turquesa
    '#34495e'  // Cinza escuro
];

// Dados PHP para JavaScript
const divisoes = <?= json_encode($divisoes) ?>;
const downloads = <?= json_encode($downloads) ?>;
const topMonografias = <?= json_encode($topMonografias) ?>;
const topDownloads = <?= json_encode($topDownloads) ?>;

// ========================================
// GRÁFICO DE PIZZA - Downloads por Divisão
// ========================================
const ctxPizza = document.getElementById('chartDivisaoPizza').getContext('2d');
const chartPizza = new Chart(ctxPizza, {
    type: 'pie',
    data: {
        labels: divisoes,
        datasets: [{
            data: downloads,
            backgroundColor: coresPrincipais,
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    padding: 15,
                    font: {
                        size: 12
                    }
                }
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const label = context.label || '';
                        const value = context.parsed || 0;
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = ((value / total) * 100).toFixed(1);
                        return `${label}: ${value} downloads (${percentage}%)`;
                    }
                }
            }
        }
    }
});

// ========================================
// GRÁFICO DE BARRAS - Downloads por Divisão
// ========================================
const ctxBarras = document.getElementById('chartDivisaoBarras').getContext('2d');
const chartBarras = new Chart(ctxBarras, {
    type: 'bar',
    data: {
        labels: divisoes,
        datasets: [{
            label: 'Downloads',
            data: downloads,
            backgroundColor: coresPrincipais[0],
            borderColor: coresPrincipais[0],
            borderWidth: 2,
            borderRadius: 8
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return `Downloads: ${context.parsed.y}`;
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        }
    }
});

// ========================================
// GRÁFICO DE BARRAS HORIZONTAL - Top 5 Monografias
// ========================================
const ctxTop = document.getElementById('chartTopMonografias').getContext('2d');
const chartTop = new Chart(ctxTop, {
    type: 'bar',
    data: {
        labels: topMonografias,
        datasets: [{
            label: 'Downloads',
            data: topDownloads,
            backgroundColor: coresPrincipais.slice(0, 5),
            borderColor: coresPrincipais.slice(0, 5),
            borderWidth: 2,
            borderRadius: 8
        }]
    },
    options: {
        indexAxis: 'y', // Barras horizontais
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return `Downloads: ${context.parsed.x}`;
                    }
                }
            }
        },
        scales: {
            x: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        }
    }
});

// ========================================
// DARK MODE - Atualizar cores dos gráficos
// ========================================
function updateChartColors() {
    const isDark = document.body.classList.contains('dark-mode');
    const textColor = isDark ? '#fff' : '#333';
    const gridColor = isDark ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.1)';

    [chartPizza, chartBarras, chartTop].forEach(chart => {
        if (chart.options.plugins.legend) {
            chart.options.plugins.legend.labels.color = textColor;
        }
        if (chart.options.scales) {
            Object.keys(chart.options.scales).forEach(scale => {
                chart.options.scales[scale].ticks.color = textColor;
                chart.options.scales[scale].grid.color = gridColor;
            });
        }
        chart.update();
    });
}

// Observar mudanças no dark mode
const observer = new MutationObserver(() => {
    updateChartColors();
});

observer.observe(document.body, {
    attributes: true,
    attributeFilter: ['class']
});

// ========================================
// SISTEMA DE NOTIFICAÇÕES
// ========================================
let notificationInterval;

function markAsReadAndRedirect() {
    clearInterval(notificationInterval); 
    document.querySelectorAll('.toast-notificacao').forEach(toast => toast.remove());

    fetch("ajax_marcar_lida.php")
        .then(res => res.json())
        .then(data => {
            if (data.sucesso) {
                fetchMonografias(); 
            } else {
                console.error("Erro do servidor ao marcar notificações:", data.erro);
            }
        })
        .catch(error => {
            console.error("Erro na comunicação para marcar como lida:", error);
        })
        .finally(() => {
            window.location.href = 'historico_uploads_admin.php';
        });
}

function fetchMonografias() {
    fetch("ajax_notificacoes_monografias.php")
        .then(response => {
            if (!response.ok) {
                throw new Error("Erro ao carregar notificações");
            }
            return response.json();
        })
        .then(data => {
            const badge = document.getElementById("notification-count");
            document.querySelectorAll('.toast-notificacao').forEach(toast => toast.remove());

            if (data.novas > 0) {
                if (badge) {
                    badge.style.display = "inline-block";
                    badge.textContent = data.novas;
                }
                
                data.novas_monografias.forEach(monografia => {
                    const toast = document.createElement("div");
                    toast.className = "toast-notificacao";
                    toast.innerHTML = `
                        <strong>Nova Monografia Cadastrada</strong><br>
                        <small><b>Tema:</b> ${monografia.tema}</small><br>
                        <small><b>Estudante:</b> ${monografia.nome_estudante} ${monografia.apelido_estudante}</small><br>
                        <small><b>Submetida por:</b> ${monografia.nome_funcionario}</small><br>
                        <button class="btn-toast" onclick="markAsReadAndRedirect();">
                            Ver Submissão
                        </button>
                    `;
                    document.body.appendChild(toast);
                });
            } else {
                if (badge) {
                    badge.textContent = "0";
                    badge.style.display = "none";
                }
            }
        })
        .catch(error => {
            console.error("Erro ao buscar notificações de monografias:", error);
        });
}

fetchMonografias();
notificationInterval = setInterval(fetchMonografias, 10000);

document.addEventListener("DOMContentLoaded", () => {
    const historicoLink = document.getElementById("link-historico-monografias");
    if (historicoLink) {
        historicoLink.addEventListener("click", (e) => {
            e.preventDefault(); 
            markAsReadAndRedirect();
        });
    }
});
</script>

</body>
</html>