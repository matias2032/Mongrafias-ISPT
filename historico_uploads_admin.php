<?php
include "conexao.php";
include "verifica_login.php";

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// ==========================================================
// 🔹 Funções AJAX
// ==========================================================
if (isset($_GET['ajax'])) {
    if ($_GET['ajax'] === 'cursos' && isset($_GET['divisao'])) {
        $id_divisao = (int)$_GET['divisao'];
        $stmt = $conexao->prepare("SELECT id_curso, nome_curso FROM curso WHERE id_divisao = ?");
        $stmt->bind_param("i", $id_divisao);
        $stmt->execute();
        $result = $stmt->get_result();

        echo '<option value="">Curso</option>';
        while ($row = $result->fetch_assoc()) {
            echo '<option value="'.$row['id_curso'].'">'.htmlspecialchars($row['nome_curso']).'</option>';
        }
        exit;
    }

    if ($_GET['ajax'] === 'areas_pesquisa' && isset($_GET['curso'])) {
        $id_curso = (int)$_GET['curso'];
        $stmt = $conexao->prepare("SELECT id_area_pesquisa, nome_area_pesquisa FROM vw_curso_area_pesquisa WHERE id_curso = ?");
        $stmt->bind_param("i", $id_curso);
        $stmt->execute();
        $result = $stmt->get_result();

        echo '<option value="">Área de Pesquisa</option>';
        while ($row = $result->fetch_assoc()) {
            echo '<option value="'.$row['id_area_pesquisa'].'">'.htmlspecialchars($row['nome_area_pesquisa']).'</option>';
        }
        exit;
    }
}

// ==========================================================
// 🔹 Consultas iniciais
// ==========================================================
$divisoes = $conexao->query("SELECT id_divisao, nome_divisao FROM divisao ORDER BY nome_divisao");
$anos_submissao = $conexao->query("SELECT id_ano_submissao, ano FROM ano_submissao ORDER BY ano DESC");
$supervisores = $conexao->query("SELECT id_usuario, nome, apelido FROM usuario WHERE id_perfil = 4 ORDER BY nome");
$periodos = $conexao->query("SELECT id_periodo, nome_periodo FROM periodo ORDER BY id_periodo ASC");

// ==========================================================
// 🔹 Consulta principal com filtros
// ==========================================================
$sql = "SELECT 
            m.id_monografia,
            m.tema,
            m.nome_estudante,
            m.apelido_estudante,
            c.nome_curso,
            d.nome_divisao,
            ap.nome_area_pesquisa,
            ans.ano,
            p.nome_periodo,
            u.nome AS funcionario,
            u2.nome AS nome_supervisor,
            u2.apelido AS apelido_supervisor,
            m.data_submissao
        FROM monografia m
        JOIN upload up ON up.id_monografia = m.id_monografia
        JOIN usuario u ON up.id_usuario = u.id_usuario
        JOIN curso c ON c.id_curso = m.id_curso
        JOIN divisao d ON d.id_divisao = m.id_divisao
        JOIN area_pesquisa ap ON ap.id_area_pesquisa = m.id_area_pesquisa
        JOIN ano_submissao ans ON ans.id_ano_submissao = m.id_ano_submissao
        JOIN periodo p ON p.id_periodo = m.id_periodo
        JOIN usuario u2 ON m.id_supervisor = u2.id_usuario
        WHERE 1=1";

$filtros = [];
$tipos = "";

// Filtros
$mapaFiltros = [
    'divisao'        => ['m.id_divisao', 'i'],
    'curso'          => ['m.id_curso', 'i'],
    'area_pesquisa'  => ['m.id_area_pesquisa', 'i'],
    'ano_submissao'  => ['m.id_ano_submissao', 'i'],
    'periodo'        => ['m.id_periodo', 'i'],
    'supervisor'     => ['m.id_supervisor', 'i']
];

foreach ($mapaFiltros as $param => [$campo, $tipo]) {
    if (!empty($_GET[$param])) {
        $sql .= " AND $campo = ?";
        $tipos .= $tipo;
        $filtros[] = $_GET[$param];
    }
}

if (!empty($_GET['estudante'])) {
    $like = "%" . trim($_GET['estudante']) . "%";
    $sql .= " AND (m.nome_estudante LIKE ? OR m.apelido_estudante LIKE ?)";
    $tipos .= "ss";
    $filtros[] = $like;
    $filtros[] = $like;
}

if (!empty($_GET['tema_projeto'])) {
    $like = "%" . trim($_GET['tema_projeto']) . "%";
    $sql .= " AND m.tema LIKE ?";
    $tipos .= "s";
    $filtros[] = $like;
}

$sql .= " ORDER BY m.data_submissao DESC";
$stmt = $conexao->prepare($sql);

if ($filtros) {
    $refs = [];
    foreach ($filtros as $k => $v) $refs[$k] = &$filtros[$k];
    $binds = array_merge([$tipos], $refs);
    call_user_func_array([$stmt, 'bind_param'], $binds);
}

$stmt->execute();
$res = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<meta name="viewport"  content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">

<title>Histórico de Submissões</title>
<link rel="stylesheet" href="css/basico.css">
<link rel="stylesheet" href="css/cards_monografias.css">
<link rel="stylesheet" href="css/historico_uploads_admin.css">
<script src="js/darkmode2.js"></script>
<script src="js/paginacao.js"></script>
<script src="js/sidebar.js"></script>

</head>
<body>

    <button class="menu-btn">☰</button>

<!-- Overlay -->
<div class="sidebar-overlay"></div>

<sidebar class="sidebar">
<br><br>
    <a href="dashboard.php">Voltar ao Menu Principal</a>
    <div class="sidebar-footer">
        <a href="logout.php" title="Sair"><img id="iconelogout" src="icones/logout1.png" alt="Logout"></a>
        <img class="dark-toggle" id="darkToggle" src="icones/lua.png" alt="Modo Escuro" title="Alternar modo escuro">
    </div>
</sidebar>

<div class="main">
  <h1>Histórico de Submissões de Monografias</h1>

  <form method="get" class="filters">
    <input type="text" name="estudante" placeholder="Nome ou Apelido do Estudante" value="<?= htmlspecialchars($_GET['estudante'] ?? '') ?>">
    <input type="text" name="tema_projeto" placeholder="Tema do Projeto" value="<?= htmlspecialchars($_GET['tema_projeto'] ?? '') ?>">

    <select name="divisao" id="divisao" onchange="carregarCursos()">
      <option value="">Divisão</option>
      <?php while ($d = $divisoes->fetch_assoc()): ?>
        <option value="<?= $d['id_divisao'] ?>" <?= ($_GET['divisao'] ?? '') == $d['id_divisao'] ? 'selected' : '' ?>>
          <?= htmlspecialchars($d['nome_divisao']) ?>
        </option>
      <?php endwhile; ?>
    </select>

    <select name="curso" id="curso" onchange="carregarAreasPesquisa()" disabled>
      <option value="">Selecione a divisão primeiro</option>
    </select>

    <select name="area_pesquisa" id="area_pesquisa" disabled>
      <option value="">Selecione o curso primeiro</option>
    </select>

    <select name="ano_submissao">
      <option value="">Ano</option>
      <?php while ($a = $anos_submissao->fetch_assoc()): ?>
        <option value="<?= $a['id_ano_submissao'] ?>" <?= ($_GET['ano_submissao'] ?? '') == $a['id_ano_submissao'] ? 'selected' : '' ?>>
          <?= htmlspecialchars($a['ano']) ?>
        </option>
      <?php endwhile; ?>
    </select>

    <select name="supervisor">
      <option value="">Supervisor</option>
      <?php while ($s = $supervisores->fetch_assoc()): ?>
        <option value="<?= $s['id_usuario'] ?>" <?= ($_GET['supervisor'] ?? '') == $s['id_usuario'] ? 'selected' : '' ?>>
          <?= htmlspecialchars($s['nome'] . ' ' . $s['apelido']) ?>
        </option>
      <?php endwhile; ?>
    </select>

    <select name="periodo">
      <option value="">Período</option>
      <?php while ($p = $periodos->fetch_assoc()): ?>
        <option value="<?= $p['id_periodo'] ?>" <?= ($_GET['periodo'] ?? '') == $p['id_periodo'] ? 'selected' : '' ?>>
          <?= htmlspecialchars($p['nome_periodo']) ?>
        </option>
      <?php endwhile; ?>
    </select>

    <button type="submit" id="pesquisa">Pesquisar</button>
    <button type="button" onclick="window.location='historico_uploads_admin.php'" id="limpeza">Limpar</button>
  </form>

  <p><?= $res->num_rows ?> submissão(ões) encontrada(s).</p>

  <div class="cards-container">
      <div id="pagination" class="pagination"></div>
    <?php while ($r = $res->fetch_assoc()): ?>
      <div class="card">
        <h3><?= htmlspecialchars($r['tema']) ?></h3>
        <p><strong>Estudante:</strong> <?= htmlspecialchars($r['nome_estudante'].' '.$r['apelido_estudante']) ?></p>
        <p><strong>Supervisor:</strong> <?= htmlspecialchars($r['nome_supervisor'].' '.$r['apelido_supervisor']) ?></p>
        <p><strong>Curso:</strong> <?= htmlspecialchars($r['nome_curso']) ?></p>
        <p><strong>Divisão:</strong> <?= htmlspecialchars($r['nome_divisao']) ?></p>
        <p><strong>Área:</strong> <?= htmlspecialchars($r['nome_area_pesquisa']) ?></p>
        <p><strong>Ano:</strong> <?= htmlspecialchars($r['ano']) ?> | <strong>Período:</strong> <?= htmlspecialchars($r['nome_periodo']) ?></p>
        <p><strong>Funcionário:</strong> <?= htmlspecialchars($r['funcionario']) ?></p>
        <p><strong>Submetida em:</strong> <?= date("d/m/Y", strtotime($r['data_submissao'])) ?></p>
      </div>
    <?php endwhile; ?>
  </div>
</div>

<script>
const BASE_URL = '?ajax=';

// Carregar cursos conforme divisão
function carregarCursos() {
  const divisao = document.getElementById("divisao").value;
  const cursoSelect = document.getElementById("curso");
  const areaSelect = document.getElementById("area_pesquisa");

  cursoSelect.disabled = true;
  cursoSelect.innerHTML = '<option>Carregando...</option>';
  areaSelect.innerHTML = '<option>Selecione o curso primeiro</option>';
  areaSelect.disabled = true;

  if (!divisao) {
    cursoSelect.innerHTML = '<option>Selecione a divisão primeiro</option>';
    return;
  }

  fetch(`${BASE_URL}cursos&divisao=${divisao}`)
    .then(r => r.text())
    .then(html => {
      cursoSelect.innerHTML = html;
      cursoSelect.disabled = false;
      const selectedCurso = "<?= $_GET['curso'] ?? '' ?>";
      if (selectedCurso) {
        cursoSelect.value = selectedCurso;
        carregarAreasPesquisa();
      }
    });
}

// Carregar áreas conforme curso
function carregarAreasPesquisa() {
  const curso = document.getElementById("curso").value;
  const areaSelect = document.getElementById("area_pesquisa");

  areaSelect.innerHTML = '<option>Carregando...</option>';
  areaSelect.disabled = true;

  if (!curso) {
    areaSelect.innerHTML = '<option>Selecione o curso primeiro</option>';
    return;
  }

  fetch(`${BASE_URL}areas_pesquisa&curso=${curso}`)
    .then(r => r.text())
    .then(html => {
      areaSelect.innerHTML = html;
      areaSelect.disabled = false;
      const selectedArea = "<?= $_GET['area_pesquisa'] ?? '' ?>";
      if (selectedArea) areaSelect.value = selectedArea;
    });
}

// Inicialização automática quando filtros já existem
window.addEventListener('DOMContentLoaded', () => {
  if ("<?= $_GET['divisao'] ?? '' ?>") carregarCursos();
});
</script>

</body>
</html>
