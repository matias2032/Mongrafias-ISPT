<?php
/* ==========================================================
   🔹 INCLUDES E SESSÃO
========================================================== */
include "conexao.php";
include "verifica_login_opcional.php";

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// ----------------------------------------------------------------------
// LÓGICA AJAX PARA CARREGAMENTO DINÂMICO DOS SELECTS (SEM ALTERAÇÃO)
// ----------------------------------------------------------------------

// 1. Função para carregar CURSOS via DIVISÃO
if (isset($_GET['ajax']) && $_GET['ajax'] == 'cursos') {
    if (!isset($_GET['divisao'])) exit;
    $id_divisao = $_GET['divisao'];

    $sql = "SELECT id_curso, nome_curso FROM curso WHERE id_divisao = ?";
    $stmt = $conexao->prepare($sql);
    $stmt->bind_param("i", $id_divisao);
    $stmt->execute();
    $result = $stmt->get_result();

    echo '<option value="">Curso</option>';
    while ($row = $result->fetch_assoc()) {
        echo '<option value="' . $row['id_curso'] . '">' . $row['nome_curso'] . '</option>';
    }
    exit;
}

// 2. Função para carregar ÁREAS DE PESQUISA via CURSO
if (isset($_GET['ajax']) && $_GET['ajax'] == 'areas_pesquisa') {
    if (!isset($_GET['curso'])) exit;
    $id_curso = $_GET['curso'];

    $sql = "SELECT id_area_pesquisa, nome_area_pesquisa FROM vw_curso_area_pesquisa WHERE id_curso = ?";
    $stmt = $conexao->prepare($sql);
    $stmt->bind_param("i", $id_curso);
    $stmt->execute();
    $result = $stmt->get_result();

    echo '<option value="">Área de Pesquisa</option>';
    while ($row = $result->fetch_assoc()) {
        echo '<option value="' . $row['id_area_pesquisa'] . '">' . $row['nome_area_pesquisa'] . '</option>';
    }
    exit;
}

// ----------------------------------------------------------------------
// PREENCHIMENTO DOS SELECTS INICIAIS (FULL LOAD)
// ----------------------------------------------------------------------
$cursos_full = $conexao->query("SELECT id_curso, nome_curso FROM curso");
$anos_submissao = $conexao->query("SELECT id_ano_submissao, ano FROM ano_submissao ORDER BY ano DESC");
$divisoes = $conexao->query("SELECT id_divisao, nome_divisao FROM divisao ORDER BY nome_divisao");
$supervisores = $conexao->query("SELECT id_usuario, nome, apelido FROM usuario WHERE id_perfil = 4 ORDER BY nome");

$filtros = [];
$tipos_bind = "";

/* ==========================================================
   🔹 CONSULTA PRINCIPAL DE BUSCA (FILTROS + PAGINAÇÃO)
========================================================== */
$sql_base = "FROM monografia m
INNER JOIN divisao d ON m.id_divisao = d.id_divisao
INNER JOIN curso c ON m.id_curso = c.id_curso
INNER JOIN area_pesquisa ap ON m.id_area_pesquisa = ap.id_area_pesquisa
INNER JOIN ano_submissao ans ON m.id_ano_submissao = ans.id_ano_submissao
INNER JOIN usuario u ON m.id_supervisor = u.id_usuario
INNER JOIN periodo p ON m.id_periodo = p.id_periodo
WHERE 1=1";

if (!empty($_GET['divisao'])) {
    $sql_base .= " AND m.id_divisao = ?";
    $tipos_bind .= "i";
    $filtros[] = $_GET['divisao'];
}

if (!empty($_GET['curso'])) {
    $sql_base .= " AND m.id_curso = ?";
    $tipos_bind .= "i";
    $filtros[] = $_GET['curso'];
}

if (!empty($_GET['area_pesquisa'])) {
    $sql_base .= " AND m.id_area_pesquisa = ?";
    $tipos_bind .= "i";
    $filtros[] = $_GET['area_pesquisa'];
}

if (!empty($_GET['ano_submissao'])) {
    $sql_base .= " AND m.id_ano_submissao = ?";
    $tipos_bind .= "i";
    $filtros[] = $_GET['ano_submissao'];
}

if (!empty($_GET['supervisor'])) {
    $sql_base .= " AND m.id_supervisor = ?";
    $tipos_bind .= "i";
    $filtros[] = $_GET['supervisor'];
}

if (!empty($_GET['estudante'])) {
    $estudante = "%" . trim($_GET['estudante']) . "%";
    $sql_base .= " AND (m.nome_estudante LIKE ? OR m.apelido_estudante LIKE ?)";
    $tipos_bind .= "ss";
    $filtros[] = $estudante;
    $filtros[] = $estudante;
}

if (!empty($_GET['tema_projeto'])) {
    $tema_projeto = "%" . trim($_GET['tema_projeto']) . "%";
    $sql_base .= " AND m.tema LIKE ?";
    $tipos_bind .= "s";
    $filtros[] = $tema_projeto;
}

if (!empty($_GET['periodo'])) {
    $sql_base .= " AND m.id_periodo = ?";
    $tipos_bind .= "i";
    $filtros[] = $_GET['periodo'];
}

// ==================== PAGINAÇÃO SERVER-SIDE ====================
$limite = 12;
$pagina_atual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
if ($pagina_atual < 1) $pagina_atual = 1;
$offset = ($pagina_atual - 1) * $limite;

// 1️⃣ Contar total de registros filtrados
$sql_count = "SELECT COUNT(*) AS total " . $sql_base;
$stmt_count = $conexao->prepare($sql_count);
if (!empty($filtros)) {
    $bind_args = array_merge([$tipos_bind], $filtros);
    call_user_func_array([$stmt_count, 'bind_param'], array_by_ref($bind_args));
}
$stmt_count->execute();
$total_registros = $stmt_count->get_result()->fetch_assoc()['total'];
$total_paginas = ceil($total_registros / $limite);
$stmt_count->close();

// 2️⃣ Consulta principal com LIMIT/OFFSET
$sql = "SELECT
    m.id_monografia,
    m.tema,
    m.nome_estudante,
    m.apelido_estudante,
    u.nome AS nome_supervisor,
    u.apelido AS apelido_supervisor,
    'aprovado' AS status_exame,
    d.nome_divisao,
    c.nome_curso,
    ap.nome_area_pesquisa,
    ans.ano,
    p.nome_periodo,
    m.data_submissao,
    m.caminho_arquivo AS link_arquivo
" . $sql_base . " ORDER BY m.data_submissao DESC LIMIT ? OFFSET ?";


$stmt = $conexao->prepare($sql);
if ($stmt === false) {
    die('Erro na preparação da consulta: ' . htmlspecialchars($conexao->error));
}

$tipos_completo = $tipos_bind . "ii";
$parametros = array_merge($filtros, [$limite, $offset]);
if (!empty($parametros)) {
    $bind_args = array_merge([$tipos_completo], $parametros);
    call_user_func_array([$stmt, 'bind_param'], array_by_ref($bind_args));
}

function array_by_ref(&$arr) {
    $refs = [];
    foreach ($arr as $key => $value) $refs[$key] = &$arr[$key];
    return $refs;
}

$stmt->execute();
$resultado = $stmt->get_result();
$quantidade = $resultado->num_rows;

// 🔸 Outras consultas (sem alteração)
$periodos = $conexao->query("SELECT id_periodo, nome_periodo FROM periodo ORDER BY id_periodo ASC");

$usuarioLogado = $_SESSION['usuario'] ?? null;
$id_perfil = $usuarioLogado['id_perfil'] ?? null;
$idUsuario = $usuarioLogado['id_usuario'] ?? null;

/* ==========================================================
   🔹 DOWNLOAD DE MONOGRAFIA
========================================================== */
if (isset($_GET['download']) && is_numeric($_GET['download'])) {
    $idMonografia = intval($_GET['download']);

    $stmt = $conexao->prepare("SELECT caminho_arquivo, id_monografia FROM monografia WHERE id_monografia = ?");
    $stmt->bind_param("i", $idMonografia);
    $stmt->execute();
    $res = $stmt->get_result();
    $monografia = $res->fetch_assoc();
    $stmt->close();

    if ($monografia) {
        $link = $monografia['caminho_arquivo'];
        if (!$usuarioLogado) {
            $hoje = date("Y-m-d");
            if (!isset($_SESSION['downloads_temp']) || $_SESSION['downloads_temp']['data'] != $hoje) {
                $_SESSION['downloads_temp'] = ['data' => $hoje, 'quantidade' => 0];
            }
            $_SESSION['downloads_temp']['quantidade']++;
            if ($_SESSION['downloads_temp']['quantidade'] > 3) {
                echo "LIMITE";
                exit;
            }
        }
        $sqlLog = "INSERT INTO cliques_download (link, id_usuario, id_monografia) VALUES (?, ?, ?)";
        $stmt = $conexao->prepare($sqlLog);
        if ($usuarioLogado) {
            $stmt->bind_param("sii", $link, $idUsuario, $idMonografia);
        } else {
            $idUsuarioNulo = null;
            $stmt->bind_param("sii", $link, $idUsuarioNulo, $idMonografia);
        }
        $stmt->execute();
        $stmt->close();

        header("Location: " . $link);
        exit;
    } else {
        echo "Monografia não encontrada.";
        exit;
    }
}

/* ==========================================================
   🔹 TOTAL DE DOWNLOADS
========================================================== */
$totalDownloads = 0;
$resD = $conexao->query("SELECT id_monografia, COUNT(*) AS total FROM cliques_download GROUP BY id_monografia");
$downloads = [];
while ($r = $resD->fetch_assoc()) {
    $downloads[$r['id_monografia']] = $r['total'];
    $totalDownloads += $r['total'];
}

/* ==========================================================
   🔹 PAGINAÇÃO HTML (para o final do corpo)
========================================================== */
$query_string = '';
foreach ($_GET as $key => $value) {
    if ($key != 'pagina') {
        $query_string .= "&" . urlencode($key) . "=" . urlencode($value);
    }
}

// // Depois de exibir os cards:
// echo '<div class="pagination" style="text-align:center;margin-top:20px;">';
// if ($pagina_atual > 1) {
//     echo '<a class="page-btn" href="?pagina=1' . $query_string . '">« Primeira</a> ';
//     echo '<a class="page-btn" href="?pagina=' . ($pagina_atual - 1) . $query_string . '">‹ Anterior</a> ';
// }
// echo '<span style="margin:0 10px;">Página ' . $pagina_atual . ' de ' . $total_paginas . '</span>';
// if ($pagina_atual < $total_paginas) {
//     echo '<a class="page-btn" href="?pagina=' . ($pagina_atual + 1) . $query_string . '">Próxima ›</a> ';
//     echo '<a class="page-btn" href="?pagina=' . $total_paginas . $query_string . '">Última »</a>';
// }
// echo '</div>';

/* ==========================================================
   🔹 FEEDBACK: INSERIR E APAGAR
========================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json'); // retorna JSON para o JS

    // Inserir novo comentário
    if (isset($_POST['enviar_feedback']) && !empty($_POST['texto_feedback']) && !empty($_POST['id_monografia'])) {
        if ($usuarioLogado) {
            $texto = trim($_POST['texto_feedback']);
            $idMonografia = intval($_POST['id_monografia']);
            $stmt = $conexao->prepare("INSERT INTO feedback (id_usuario, id_monografia, texto_feedback) VALUES (?, ?, ?)");
            $stmt->bind_param("iis", $idUsuario, $idMonografia, $texto);
            $stmt->execute();
            $id_feedback = $stmt->insert_id;
            $stmt->close();

            // Retornar o novo comentário completo (para injetar no HTML)
            $dados = [
                'id_feedback' => $id_feedback,
                'nome' => $usuarioLogado['nome'],
                'apelido' => $usuarioLogado['apelido'],
                'data_envio' => date('d/m/Y H:i'),
                'texto_feedback' => htmlspecialchars($texto)
            ];
            echo json_encode(['success' => true, 'feedback' => $dados]);
            exit;
        }
        echo json_encode(['success' => false]);
        exit;
    }

    // Apagar comentário
    if (isset($_POST['delete_feedback'])) {
        $idFeedback = intval($_POST['delete_feedback']);
        $stmt = $conexao->prepare("DELETE FROM feedback WHERE id_feedback = ? AND id_usuario = ?");
        $stmt->bind_param("ii", $idFeedback, $idUsuario);
        $ok = $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => $ok]);
        exit;
    }
}


?>



<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<meta name="viewport"  content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">

<title>Repositório de Monografias</title>
<link rel="stylesheet" href="css/basico.css">
<link rel="stylesheet" href="css/cards_monografias.css">
<script src="js/darkmode1.js"></script>
    <script src="js/dropdown.js"></script>
<script src="js/paginacao.js"></script>
                     <script src="js/sidebar2.js"></script> 

</head>
<body>


<header class="topbar">
  <div class="container2">

    <!-- 🔹 BOTÃO MENU MOBILE (visível apenas em mobile - ESQUERDA) -->
    <button class="menu-btn-mobile" id="menuBtnMobile">&#9776;</button>

    <!-- 🟠 LOGO DA EMPRESA (CENTRO no mobile) -->
    <div class="logo">
      <a href="index.php">
        <img src="icones/logo.png" alt="Logo do ISPT" class="logo-img">
      </a>
    </div>

    <!-- 🔹 LINKS PRINCIPAIS (Desktop) -->
    <div class="links-menu">
      <a href="index.php"><img class="icone2" src="icones/casa1.png" alt="Início" title="casa"> Início</a>
      
      <?php if ($id_perfil == 2): ?>
        <a href="cadastrar_monografia.php">
        <img class="icone2" src="icones/upload1.png" alt="upload" title="upload">    
        Adicionar Monografias</a>
        <a href="historico_uploads.php" id="linkHistoricoUploads">
    <img class="icone2" src="icones/historico_uploads1.png" alt="upload" title="upload">    
Histórico de Uploads
          <span id="badgeUploads" style="background:#e74c3c;color:white;border-radius:50%;padding:2px 7px;font-size:12px;margin-left:6px;display:none;">0</span>
        </a>
      <?php endif; ?>

      <?php if ($usuarioLogado): ?>
        <a href="historico_downloads.php">
                <img class="icone2" src="icones/historico_downloads1.png" alt="download" title="download">        
        Histórico de Downloads</a>
      <?php else: ?>
        <a href="historico_downloads.php">
          <img class="icone2" src="icones/historico_downloads1.png" alt="download" title="download">    
        Histórico de Downloads</a>
        <a href="login.php" class="login-link"><img class="icone2" src="icones/login1.png" alt="login" title="login"> Fazer Login</a>
      <?php endif; ?>
    </div>

    <!-- 🔹 AÇÕES USUÁRIO (sempre visível) -->
    <div class="acoes-usuario">
      <!-- Dark Mode Toggle -->
      <img class="dark-toggle" id="darkToggle" src="icones/lua.png" alt="Alternar modo escuro" title="Alternar modo escuro">

      <!-- Usuário ou Login -->
      <?php if ($usuarioLogado): ?>
        <?php
          $nome2 = $usuarioLogado['nome'] ?? '';
          $apelido = $usuarioLogado['apelido'] ?? '';
          $iniciais = strtoupper(substr($nome2, 0, 1) . substr($apelido, 0, 1));
          $nomeCompleto = "$nome2 $apelido";
          function gerarCor($texto) {
              $hash = md5($texto);
              $r = hexdec(substr($hash, 0, 2));
              $g = hexdec(substr($hash, 2, 2));
              $b = hexdec(substr($hash, 4, 2));
              return "rgb($r, $g, $b)";
          }
          $corAvatar = gerarCor($nomeCompleto);
        ?>
        <!-- Desktop: Mostra perfil completo -->
        <div class="usuario-info usuario-desktop" id="usuarioDropdown">
          <div class="usuario-dropdown">
            <div class="usuario-iniciais" style="background-color: <?= $corAvatar ?>;">
              <?= $iniciais ?>
            </div>
      <div class="usuario-info-texto">
    <div class="usuario-nome"><?= $nomeCompleto ?></div>
    <div class="usuario-email"><?= $usuarioLogado["email"] ?></div>
</div>




            <div class="menu-perfil" id="menuPerfil">
              <a href='editarusuario.php?id_usuario=<?= $usuarioLogado['id_usuario'] ?>'>
                <img class="icone" src="icones/user1.png" alt="Alterar" title="Alterar" id="iconeuser">  
              Editar Dados Pessoais</a>
              <a href="alterar_senha2.php">
                <img class="icone" src="icones/cadeado1.png" alt="Alterar" title="Alterar" id="iconecadeado">  
              Alterar Senha</a>
              <a href="logout.php">
                <img class="iconelogout" src="icones/logout1.png" alt="Logout" title="Sair"> Sair
              </a>
            </div>
          </div>
        </div>
      <?php else: ?>
        <!-- <a href="login.php" class="login-link-mobile">Entrar</a> -->
      <?php endif; ?>
    </div>
  </div>
</header>

<!-- 🔹 MENU MOBILE SIDEBAR -->
<nav id="mobileMenu" class="nav-mobile-sidebar hidden">
  <div class="sidebar-header">
    <button class="close-btn" id="closeMobileMenu">&times;</button>
  </div>

  <ul class="sidebar-links">
    <li><a href="index.php"><img class="icone2" src="icones/casa1.png" alt="Início" title="casa"> Início</a></li>
    
    <?php if ($id_perfil == 2): ?>
      <li><a href="cadastrar_monografia.php">
        <img class="icone2" src="icones/upload1.png" alt="upload" title="upload">  
      Adicionar Monografias</a></li>
      <li>
        <a href="historico_uploads.php" id="linkHistoricoUploadsMobile">
              <img class="icone2" src="icones/historico_uploads1.png" alt="upload" title="upload">
      Histórico de Uploads
          <span id="badgeUploadsMobile" style="background:#e74c3c;color:white;border-radius:50%;padding:2px 7px;font-size:12px;margin-left:6px;display:none;">0</span>
        </a>
      </li>
    <?php endif; ?>

    <?php if ($usuarioLogado): ?>
      <li><a href="historico_downloads.php">
        <img class="icone2" src="icones/historico_downloads1.png" alt="download" title="download">  
  Histórico de Downloads</a></li>
    <?php else: ?>
      <li><a href="historico_downloads.php">
        <img class="icone2" src="icones/historico_downloads1.png" alt="download" title="download">  
Histórico de Downloads</a></li>
      <li><a href="login.php"><img class="icone2" src="icones/login1.png" alt="login" title="login"> Fazer Login  </a></li>
    <?php endif; ?>
  </ul>


  
</nav>


<div class="container">
  <h1>Repositório de Monografias</h1>


   <p class="btn-filtro">
  <img id="imgFiltro" src="icones/filtro1.png" alt="filtro1" title="filtro1" class="icone3" style="cursor:pointer;">
  Filtros
</p>

<form method="get" class="filters" id="formFiltros">

            
            <input type="text" name="estudante" placeholder="Nome ou Apelido do Estudante" 
                   value="<?= htmlspecialchars($_GET['estudante'] ?? '') ?>">
            
            <input type="text" name="tema_projeto" placeholder="Tema do Projeto" 
                   value="<?= htmlspecialchars($_GET['tema_projeto'] ?? '') ?>">
            <select name="divisao" id="divisao" onchange="carregarCursos()">
                <?php if ($divisoes->num_rows > 0): ?>
                        <option value="">Divisão</option>
                        <?php $divisoes->data_seek(0); while ($f = $divisoes->fetch_assoc()) { ?>
                            <option value="<?= $f['id_divisao'] ?>" <?= isset($_GET['divisao']) && $_GET['divisao'] == $f['id_divisao'] ? 'selected' : '' ?>>
                                <?= $f['nome_divisao'] ?>
                            </option>
                        <?php } ?>
                    <?php else: ?>
                        <option value="">Nenhuma Divisão</option>
                    <?php endif; ?>
            </select>

            <select name="curso" id="curso" onchange="carregarAreasPesquisa()" disabled>
                <option value="">Selecione a divisão primeiro</option>
            </select>

            <select name="area_pesquisa" id="area_pesquisa" disabled>
                <option value="">Selecione o curso primeiro</option>
            </select>

        

            <select name="ano_submissao">
                <?php if ($anos_submissao->num_rows > 0): ?>
                        <option value="">Ano de Submissão</option>
                        <?php $anos_submissao->data_seek(0); while ($a = $anos_submissao->fetch_assoc()) { ?>
                            <option value="<?= $a['id_ano_submissao'] ?>" <?= isset($_GET['ano_submissao']) && $_GET['ano_submissao'] == $a['id_ano_submissao'] ? 'selected' : '' ?>>
                                <?= $a['ano'] ?>
                            </option>
                        <?php } ?>
                    <?php else: ?>
                        <option value="">Nenhum ano</option>
                    <?php endif; ?>
            </select>
            
            <select name="supervisor" id="supervisor">
                <?php if ($supervisores->num_rows > 0): ?>
                        <option value="">Supervisor</option>
                        <?php $supervisores->data_seek(0); while ($s = $supervisores->fetch_assoc()) { 
                            $nome_display = $s['nome'];
                            if (!empty($s['apelido'])) {
                                $nome_display .= ' (' . $s['apelido'] . ')';
                            }
                            ?>
                            <option value="<?= $s['id_usuario'] ?>" <?= isset($_GET['supervisor']) && $_GET['supervisor'] == $s['id_usuario'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($nome_display) ?>
                            </option>
                        <?php } ?>
                    <?php else: ?>
                        <option value="">Nenhum Supervisor</option>
                    <?php endif; ?>
            </select>


<select name="periodo" id="periodo">
    <?php if ($periodos->num_rows > 0): ?>
        <option value="">Período</option>
        <?php $periodos->data_seek(0); while ($p = $periodos->fetch_assoc()) { ?>
            <option value="<?= $p['id_periodo'] ?>" <?= isset($_GET['periodo']) && $_GET['periodo'] == $p['id_periodo'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($p['nome_periodo']) ?>
            </option>
        <?php } ?>
    <?php else: ?>
        <option value="">Nenhum Período</option>
    <?php endif; ?>
</select>



    <input type="text" name="tema" placeholder="Pesquisar tema..." value="<?= htmlspecialchars($_GET['tema'] ?? '') ?>">
    <button type="submit" id="pesquisa"> Pesquisar</button>
    <button type="button" onclick="window.location='ver_monografias.php'" id="limpeza">Limpar</button>
  </form>

  <div class="count">
    <?= $resultado->num_rows ?> monografia(s) encontrada(s) |
    <?= $totalDownloads ?> download(s) no total.
  </div>

  <div class="cards-container">
    <div id="pagination" class="pagination"></div>

 <?php while ($m = $resultado->fetch_assoc()): ?>
  <div class="card">
    <div class="card-content">
      <h3><?= htmlspecialchars($m['tema']) ?></h3>
      <p><strong>Autor:</strong> <?= htmlspecialchars($m['nome_estudante'] . ' ' . $m['apelido_estudante']) ?></p>
      <p><strong>Docente:</strong> <?= htmlspecialchars($m['nome_supervisor'] . ' ' . $m['apelido_supervisor']) ?></p>
      <p><strong>Divisão:</strong> <?= htmlspecialchars($m['nome_divisao']) ?></p>
      <p><strong>Curso:</strong> <?= htmlspecialchars($m['nome_curso']) ?></p>
      <p><strong>Área:</strong> <?= htmlspecialchars($m['nome_area_pesquisa']) ?></p>
      <p><strong>Ano:</strong> <?= htmlspecialchars($m['ano']) ?><br>
        <strong>Período:</strong> <?= htmlspecialchars($m['nome_periodo']) ?>
      </p>
      <p>
        <strong>Data de Submissão:</strong>
        <small><?= date("d/m/Y", strtotime($m['data_submissao'])) ?></small>
      </p>

      <a class="btn-download" href="?download=<?= urlencode($m['id_monografia']) ?>">Baixar</a>
    </div>
      <?php if ($usuarioLogado): ?>
    <!-- ==================== SEÇÃO DE COMENTÁRIOS ==================== -->
    <div class="comentarios" style="margin-top:25px;padding:15px;border-top:1px solid #ddd;">
      <h4>Comentários</h4>
      <div class="lista-comentarios" id="comentarios-<?= $m['id_monografia'] ?>">
        <?php
          $stmtF = $conexao->prepare("
              SELECT f.id_feedback, f.texto_feedback, f.data_envio, u.nome, u.apelido, u.id_usuario
              FROM feedback f
              LEFT JOIN usuario u ON f.id_usuario = u.id_usuario
              WHERE f.id_monografia = ?
              ORDER BY f.data_envio DESC
          ");
          $stmtF->bind_param("i", $m['id_monografia']);
          $stmtF->execute();
          $resF = $stmtF->get_result();

          while ($c = $resF->fetch_assoc()):
              $iniciais = strtoupper(substr($c['nome'], 0, 1) . substr($c['apelido'], 0, 1));
              $corAvatar = gerarCor($c['nome'] . ' ' . $c['apelido']);
        ?>
          <div class="comentario" id="feedback-<?= $c['id_feedback'] ?>"
               style="margin-bottom:10px;padding:8px 10px;border-radius:8px;background:#f7f7f7;display:flex;gap:10px;align-items:flex-start;">
            <div class="avatar" style="width:36px;height:36px;border-radius:50%;background-color:<?= $corAvatar ?>;
                 display:flex;align-items:center;justify-content:center;color:#fff;font-weight:bold;">
              <?= $iniciais ?>
            </div>
            <div style="flex:1;">
              <strong><?= htmlspecialchars($c['nome'] . ' ' . $c['apelido']) ?></strong>
              <small style="color:#888;"> - <?= date('d/m/Y H:i', strtotime($c['data_envio'])) ?></small>
              <p style="margin:5px 0;"><?= nl2br(htmlspecialchars($c['texto_feedback'])) ?></p>
              <?php if ($usuarioLogado && $c['id_usuario'] == $idUsuario): ?>
              <form class="form-apagar" method="post" style="display:inline;">
    <input type="hidden" name="delete_feedback" value="<?= $c['id_feedback'] ?>">
    <button type="submit"
            style="background:none;border:none;color:#e74c3c;cursor:pointer;">Apagar</button>
  </form>
              <?php endif; ?>
            </div>
          </div>
        <?php endwhile; ?>
      </div>

      <?php if ($usuarioLogado): ?>
   <form class="form-feedback" data-id="<?= $m['id_monografia'] ?>" style="margin-top:10px;">

  <input type="hidden" name="enviar_feedback" value="1">
  <input type="hidden" name="id_monografia" value="<?= $m['id_monografia'] ?>">
  <textarea name="texto_feedback" rows="3" placeholder="Escreva um comentário..." required
            style="width:100%;padding:8px;border-radius:6px;border:1px solid #ccc;"></textarea>
  <button type="submit" class="coment">Enviar</button>
</form>

      <?php else: ?>
        <p style="color:#777;">Entre para deixar um comentário.</p>
      <?php endif; ?>
    </div> <!-- fecha .comentarios -->
        <?php endif; ?>

  </div> <!-- fecha .card -->
<?php endwhile; ?> <!-- fecha o laço das monografias -->



<script>

    document.getElementById("imgFiltro").addEventListener("click", function() {
    const form = document.getElementById("formFiltros");

    if (form.style.display === "none" || form.style.display === "") {
        form.style.display = "block";
    } else {
        form.style.display = "none";
    }
});


    // ======== ENVIAR NOVO COMENTÁRIO SEM RECARREGAR ========
document.querySelectorAll('form[data-id]').forEach(form => {
  form.addEventListener('submit', async e => {
    e.preventDefault();

    const idMonografia = form.dataset.id;
    const textarea = form.querySelector('textarea');
    const texto = textarea.value.trim();
    if (!texto) return;

    const formData = new FormData();
    formData.append('enviar_feedback', '1');
    formData.append('id_monografia', idMonografia);
    formData.append('texto_feedback', texto);

    const res = await fetch(window.location.href, {
      method: 'POST',
      body: formData
    });

    const data = await res.json();
    if (data.success) {
      const f = data.feedback;
      const avatarCor = '#3498db'; // opcional, pode gerar cor no PHP também
      const iniciais = (f.nome[0] + f.apelido[0]).toUpperCase();

      const novo = document.createElement('div');
      novo.classList.add('comentario');
      novo.innerHTML = `
        <div class="avatar" style="width:36px;height:36px;border-radius:50%;
             background-color:${avatarCor};
             display:flex;align-items:center;justify-content:center;color:#fff;font-weight:bold;">
          ${iniciais}
        </div>
        <div style="flex:1;">
          <strong>${f.nome} ${f.apelido}</strong>
          <small style="color:#888;"> - ${f.data_envio}</small>
          <p style="margin:5px 0;">${f.texto_feedback}</p>
          <form class="form-apagar" method="post" style="display:inline;">
            <input type="hidden" name="delete_feedback" value="${f.id_feedback}">
            <button type="submit" style="background:none;border:none;color:#e74c3c;cursor:pointer;">Apagar</button>
          </form>
        </div>
      `;
      form.closest('.comentarios').querySelector('.lista-comentarios').prepend(novo);
      textarea.value = '';
    }
  });
});


// ======== APAGAR COMENTÁRIO SEM RECARREGAR ========
document.addEventListener('submit', async e => {
  if (e.target.matches('.form-apagar')) {
    e.preventDefault();

    if (!confirm('Deseja mesmo apagar este comentário?')) return;

    const idFeedback = e.target.querySelector('input[name="delete_feedback"]').value;
    const formData = new FormData();
    formData.append('delete_feedback', idFeedback);

    const res = await fetch(window.location.href, { method: 'POST', body: formData });
    const data = await res.json();

    if (data.success) {
      e.target.closest('.comentario').remove();
    } else {
      alert('Erro ao apagar comentário.');
    }
  }
});

document.addEventListener("DOMContentLoaded", () => {
    const urlParams = new URLSearchParams(window.location.search);
    const badge = document.getElementById("badgeUploads");
    
    // Variável para rastrear se houve novo cadastro (para limpar a URL)
    let isNovoCadastro = urlParams.get("novo") === "1";

    // ✅ Verifica se houve novo cadastro
    if (isNovoCadastro) {
        // Exibir toast bonito e centralizado
        const toast = document.createElement("div");
        toast.textContent = "✅ A monografia foi cadastrada com sucesso!";
        Object.assign(toast.style, {
            position: "fixed",
            top: "50%",
            left: "50%",
            transform: "translate(-50%, -50%)",
            background: "#2ecc71",
            color: "white",
            padding: "18px 36px",
            borderRadius: "10px",
            fontSize: "16px",
            fontWeight: "600",
            boxShadow: "0 4px 10px rgba(0,0,0,0.25)",
            zIndex: "9999",
            opacity: "0",
            transition: "opacity 0.5s"
        });
        document.body.appendChild(toast);
        setTimeout(() => toast.style.opacity = "1", 100);
        setTimeout(() => {
            toast.style.opacity = "0";
            setTimeout(() => toast.remove(), 500);
        }, 4000);

        // Atualiza contador local
        let count = parseInt(localStorage.getItem("contadorUploads") || "0");
        localStorage.setItem("contadorUploads", count + 1);

        // 💡 SOLUÇÃO: Remove o parâmetro 'novo=1' da URL
        // Isso impede que o contador seja incrementado novamente em um F5.
        // O history.replaceState muda a URL na barra de endereços sem recarregar a página.
        const newUrl = window.location.pathname;
        history.replaceState(null, '', newUrl); // Limpa todos os parâmetros
    }

    // ✅ Exibir badge atualizado (mesmo após reload)
    const contadorAtual = parseInt(localStorage.getItem("contadorUploads") || "0");
    if (contadorAtual > 0 && badge) {
        badge.textContent = contadorAtual;
        badge.style.display = "inline-block";
    }

    // ✅ Quando o usuário acessar o histórico, zera o contador
    const linkHistorico = document.getElementById("linkHistoricoUploads");
    if (linkHistorico) {
        linkHistorico.addEventListener("click", () => {
            localStorage.setItem("contadorUploads", "0");
            badge.style.display = "none";
        });
    }
});

// URL base para requisições AJAX
    const BASE_URL = '?ajax=';
    
    // Função para carregar cursos com base na divisão selecionada
    function carregarCursos(selectedCurso = null, callback = null) {
        const divisao = document.getElementById("divisao").value;
        const cursoSelect = document.getElementById("curso");
        const areaPesquisaSelect = document.getElementById("area_pesquisa");

        cursoSelect.innerHTML = '<option value="">Carregando...</option>';
        cursoSelect.disabled = true;
        areaPesquisaSelect.innerHTML = '<option value="">Selecione o curso primeiro</option>';
        areaPesquisaSelect.disabled = true;

        if (!divisao) {
            cursoSelect.innerHTML = '<option value="">Selecione a divisão primeiro</option>';
            if (callback) callback();
            return;
        }

        fetch(`${BASE_URL}cursos&divisao=${divisao}`)
            .then(res => res.text())
            .then(data => {
                const isValidData = data.trim() !== '' && data.indexOf('<option') !== -1;
                cursoSelect.innerHTML = isValidData ? data : '<option value="">Nenhum curso cadastrado</option>';
                cursoSelect.disabled = !isValidData;
                if (selectedCurso) {
                    cursoSelect.value = selectedCurso;
                }
                
                // Força o carregamento da área se o curso for válido
                if(cursoSelect.value) {
                    const selectedAreaPesquisa = "<?= $_GET['area_pesquisa'] ?? '' ?>";
                    carregarAreasPesquisa(selectedAreaPesquisa);
                }

                if (callback) callback();
            })
            .catch(() => {
                alert("Erro ao carregar cursos.");
                if (callback) callback();
            });
    }

    // Função para carregar Áreas de Pesquisa
    function carregarAreasPesquisa(selectedArea = null) {
        const curso = document.getElementById("curso").value;
        const areaPesquisaSelect = document.getElementById("area_pesquisa");

        areaPesquisaSelect.innerHTML = '<option value="">Carregando...</option>';
        areaPesquisaSelect.disabled = true;

        if (!curso) {
            areaPesquisaSelect.innerHTML = '<option value="">Selecione o curso primeiro</option>';
            return;
        }

        fetch(`${BASE_URL}areas_pesquisa&curso=${curso}`)
            .then(res => res.text())
            .then(data => {
                const isValidData = data.trim() !== '' && data.indexOf('<option') !== -1;
                areaPesquisaSelect.innerHTML = isValidData ? data : '<option value="">Nenhuma área de pesquisa cadastrada</option>';
                areaPesquisaSelect.disabled = !isValidData;
                if (selectedArea) {
                    areaPesquisaSelect.value = selectedArea;
                }
            })
            .catch(() => {
                alert("Erro ao carregar áreas de pesquisa.");
                areaPesquisaSelect.disabled = true;
            });
    }

    // Função para limpar todos os filtros
    function limparFiltros() {
        window.location.href = window.location.pathname;
    }

// feedback suave ao clicar em download
document.querySelectorAll('.btn-download').forEach(btn=>{

  btn.addEventListener('click', ()=> {
    btn.textContent = '⏳ Baixando...';
    setTimeout(()=>btn.textContent='⬇️ Baixar',2500);
  });
});
</script>
</body>
</html>
