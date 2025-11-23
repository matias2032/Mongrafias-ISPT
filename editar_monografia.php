<?php
include "conexao.php";
include "verifica_login.php";
include "info_usuario.php";

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}
$usuario = $_SESSION['usuario']; // Garante acesso aos dados do utilizador logado

/* ===========================================================
   AJAX - Carregar Cursos e Áreas dinamicamente
=========================================================== */
if (isset($_GET['ajax']) && $_GET['ajax'] === 'cursos') {
    ob_clean();
    $id_divisao = intval($_GET['divisao'] ?? 0);

    $sql = "SELECT id_curso, nome_curso FROM curso WHERE id_divisao = ? ORDER BY nome_curso";
    $stmt = $conexao->prepare($sql);
    $stmt->bind_param("i", $id_divisao);
    $stmt->execute();
    $res = $stmt->get_result();

    echo '<option value="">Selecione o curso</option>';
    while ($r = $res->fetch_assoc()) {
        echo '<option value="'.$r['id_curso'].'">'.htmlspecialchars($r['nome_curso']).'</option>';
    }
    exit;
}

if (isset($_GET['ajax']) && $_GET['ajax'] === 'areas_pesquisa') {
    ob_clean();
    $id_curso = intval($_GET['curso'] ?? 0);

    $sql = "SELECT a.id_area_pesquisa, a.nome_area_pesquisa
            FROM area_pesquisa a
            INNER JOIN curso_area_pesquisa cap ON cap.id_area_pesquisa = a.id_area_pesquisa
            WHERE cap.id_curso = ?
            ORDER BY a.nome_area_pesquisa";
    $stmt = $conexao->prepare($sql);
    $stmt->bind_param("i", $id_curso);
    $stmt->execute();
    $res = $stmt->get_result();

    echo '<option value="">Selecione a área de pesquisa</option>';
    while ($r = $res->fetch_assoc()) {
        echo '<option value="'.$r['id_area_pesquisa'].'">'.htmlspecialchars($r['nome_area_pesquisa']).'</option>';
    }
    exit;
}

/* ===========================================================
   Dados principais
=========================================================== */
$id_monografia = intval($_GET['id_monografia'] ?? 0);
if ($id_monografia <= 0) {
    header("Location: gerenciar_monografias.php");
    exit;
}

$mensagem = "";
$tipo_mensagem = "";
$redirecionar = false;

/* ===========================================================
   Buscar dados atuais da monografia
=========================================================== */
function carregarDadosMonografia($conexao, $id_monografia) {
    // SELECT m.* já traz a coluna 'destaque' se ela existir na tabela
    $sql = "SELECT m.*, c.id_divisao 
            FROM monografia m
            INNER JOIN curso c ON m.id_curso = c.id_curso
            WHERE m.id_monografia = ?";
    $stmt = $conexao->prepare($sql);
    $stmt->bind_param("i", $id_monografia);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

$monografia = carregarDadosMonografia($conexao, $id_monografia);
if (!$monografia) {
    die("Monografia não encontrada.");
}

/* ===========================================================
   Atualizar monografia
=========================================================== */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $tema = trim($_POST['tema']);
    $nome_estudante = trim($_POST['nome_estudante']);
    $apelido_estudante = trim($_POST['apelido_estudante']);
    $id_divisao = intval($_POST['divisao']);
    $id_curso = intval($_POST['curso']);
    $id_area_pesquisa = intval($_POST['area_pesquisa']);
    $id_ano_submissao = intval($_POST['ano_submissao']);
    $id_periodo = intval($_POST['periodo']);
    $id_supervisor = intval($_POST['supervisor']);
    $remover_arquivo = isset($_POST['remover_arquivo']);
    
    // [NOVO] Lógica do Destaque
    // Se for Admin (perfil 1), pega o valor do POST. Se não for, mantém o valor atual do banco.
    if ($usuario['id_perfil'] == 1) {
        $destaque = isset($_POST['destaque']) ? 1 : 0;
    } else {
        $destaque = $monografia['destaque'];
    }

    $novo_arquivo = $_FILES['arquivo']['name'] ?? "";
    
    // ✅ Verificação de alterações (Incluindo Destaque)
    $houveAlteracao = (
        $tema !== $monografia['tema'] ||
        $nome_estudante !== $monografia['nome_estudante'] ||
        $apelido_estudante !== $monografia['apelido_estudante'] ||
        $id_divisao != $monografia['id_divisao'] ||
        $id_curso != $monografia['id_curso'] ||
        $id_area_pesquisa != $monografia['id_area_pesquisa'] ||
        $id_ano_submissao != $monografia['id_ano_submissao'] ||
        $id_periodo != $monografia['id_periodo'] ||
        $id_supervisor != $monografia['id_supervisor'] ||
        $destaque != $monografia['destaque'] || // [NOVO] Verifica alteração no destaque
        (!empty($novo_arquivo)) || 
        (!empty($remover_arquivo) && !empty($monografia['caminho_arquivo']))
    );

    if (!$houveAlteracao) {
        $mensagem = "Nenhuma alteração foi feita.";
        $tipo_mensagem = "error";
    } else {
        $caminho_atual = $monografia['caminho_arquivo'];
        $novo_caminho = $caminho_atual;
        $upload_realizado = false;

        // Se pediu para remover o arquivo
        if ($remover_arquivo && $caminho_atual && file_exists($caminho_atual)) {
            unlink($caminho_atual);
            $novo_caminho = null;
        }

        // Se enviou novo arquivo
        if (!empty($novo_arquivo) && $_FILES['arquivo']['error'] === UPLOAD_ERR_OK) {
            $extensao = strtolower(pathinfo($novo_arquivo, PATHINFO_EXTENSION));
            if ($extensao !== 'pdf') {
                $mensagem = "❌ O arquivo deve ser em formato PDF.";
                $tipo_mensagem = "error";
            } else {
                $novo_nome = "monografia_" . time() . "_" . uniqid() . ".pdf";
                $destino = "uploads/monografias/" . $novo_nome;
                if (!is_dir("uploads/monografias")) {
                    mkdir("uploads/monografias", 0777, true);
                }
                if (move_uploaded_file($_FILES['arquivo']['tmp_name'], $destino)) {
                    if ($caminho_atual && file_exists($caminho_atual)) unlink($caminho_atual);
                    $novo_caminho = $destino;
                    $upload_realizado = true;
                }
            }
        }

        if (empty($mensagem)) {
            // [ATUALIZADO] Adicionado campo `destaque` na query
            $sql_update = "UPDATE monografia 
                SET tema=?, nome_estudante=?, apelido_estudante=?, 
                    id_curso=?, id_area_pesquisa=?, id_divisao=?, 
                    id_ano_submissao=?, id_periodo=?, id_supervisor=?, 
                    caminho_arquivo=?, destaque=?
                WHERE id_monografia=?";
            
            $stmt_up = $conexao->prepare($sql_update);
            
            // [ATUALIZADO] Adicionado 'i' extra no bind_param e a variável $destaque
            // sssiiiiissii (12 parametros)
            $stmt_up->bind_param("sssiiiiissii",
                $tema, $nome_estudante, $apelido_estudante,
                $id_curso, $id_area_pesquisa, $id_divisao,
                $id_ano_submissao, $id_periodo, $id_supervisor,
                $novo_caminho, $destaque, $id_monografia
            );
            
            if ($stmt_up->execute()) {
                $mensagem = "Monografia atualizada com sucesso!";
                $tipo_mensagem = "success";
                $redirecionar = true;
                // Recarrega dados atualizados imediatamente
                $monografia = carregarDadosMonografia($conexao, $id_monografia);
            } else {
                $mensagem = "Erro ao atualizar: " . $stmt_up->error;
                $tipo_mensagem = "error";
            }
        }
    }
}

/* ===========================================================
   Dados para selects
=========================================================== */
$divisoes = $conexao->query("SELECT id_divisao, nome_divisao FROM divisao ORDER BY nome_divisao");
$cursos = $conexao->query("SELECT id_curso, nome_curso FROM curso WHERE id_divisao = {$monografia['id_divisao']} ORDER BY nome_curso");
$areas = $conexao->query("SELECT a.id_area_pesquisa, a.nome_area_pesquisa
                          FROM area_pesquisa a
                          INNER JOIN curso_area_pesquisa cap ON cap.id_area_pesquisa = a.id_area_pesquisa
                          WHERE cap.id_curso = {$monografia['id_curso']}
                          ORDER BY a.nome_area_pesquisa");
$anos_submissao = $conexao->query("SELECT id_ano_submissao, ano FROM ano_submissao ORDER BY ano DESC");
$periodos = $conexao->query("SELECT id_periodo, nome_periodo FROM periodo ORDER BY id_periodo ASC");
$supervisores = $conexao->query("SELECT id_usuario, nome, apelido FROM usuario WHERE id_perfil = 4 ORDER BY nome");
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<meta name="viewport"  content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">

<title>Editar Monografia</title>
<link rel="stylesheet" href="css/admin.css">
<script src="logout_auto.js"></script>
<script src="js/darkmode2.js"></script>
<script src="js/sidebar.js"></script>
  <script src="js/dropdown2.js"></script>

<style>
.drop-zone {
    width:100%;min-height:150px;padding:20px;margin-bottom:20px;text-align:center;
    border:2px dashed #3498db;border-radius:10px;background:#ecf0f1;
    transition:background 0.3s,border-color 0.3s;
}
.drop-zone.drag-over{background:#d0e7f7;border-color:#2980b9;}
.file-input{display:none;}
.file-name{font-weight:bold;color:#27ae60;}
/* Estilo para o Checkbox de Destaque */
.checkbox-wrapper {
    display: flex; align-items: center; gap: 10px; margin: 15px 0; padding: 10px;
    background-color: rgba(52, 152, 219, 0.1); border-radius: 5px; border-left: 4px solid #3498db;
}
.checkbox-wrapper input[type="checkbox"] { width: 20px; height: 20px; cursor: pointer; }
.checkbox-wrapper label { margin-bottom: 0 !important; cursor: pointer; font-weight: bold; color: #2c3e50; }
</style>
</head>
<body>

    <button class="menu-btn">☰</button>
    <div class="sidebar-overlay"></div>

<sidebar class="sidebar">
<br><br>
  <a href="gerenciar_monografias.php">Voltar á área de Monografias</a>
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
            Editar Dados Pessoais</a>
            <a href="alterar_senha2.php">
            
            <img class="icone" src="icones/cadeado1.png" alt="Alterar" title="Alterar"id="iconecadeado"> 
            Alterar Senha</a>
            <a href="logout.php">
            <img class="iconelogout" src="icones/logout1.png" alt="Logout" title="Sair">  
            Sair</a>
        </div>

    </div>

    <img class="dark-toggle" id="darkToggle"
           src="icones/lua.png"
           alt="Modo Escuro"
           title="Alternar modo escuro">
</div>
</sidebar>

<div class="content">
  <div class="main">
    <h1>Editar Monografia</h1>
    <?php if ($mensagem): ?>
      <div class="mensagem <?= htmlspecialchars($tipo_mensagem) ?>"><?= htmlspecialchars($mensagem) ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
      <label>Tema:</label>
      <input type="text" name="tema" value="<?= htmlspecialchars($monografia['tema']) ?>" required>

      <label>Nome do Estudante:</label>
      <input type="text" name="nome_estudante" value="<?= htmlspecialchars($monografia['nome_estudante']) ?>" required>

      <label>Apelido do Estudante:</label>
      <input type="text" name="apelido_estudante" value="<?= htmlspecialchars($monografia['apelido_estudante']) ?>" required>

      <label>Divisão:</label>
      <select name="divisao" id="divisao" onchange="carregarCursos()" required>
        <option value="">Selecione</option>
        <?php while ($d = $divisoes->fetch_assoc()) { ?>
          <option value="<?= $d['id_divisao'] ?>" <?= $d['id_divisao']==$monografia['id_divisao']?'selected':'' ?>>
            <?= htmlspecialchars($d['nome_divisao']) ?>
          </option>
        <?php } ?>
      </select>

      <label>Curso:</label>
      <select name="curso" id="curso" onchange="carregarAreasPesquisa()" required>
        <?php while ($c = $cursos->fetch_assoc()) { ?>
          <option value="<?= $c['id_curso'] ?>" <?= $c['id_curso']==$monografia['id_curso']?'selected':'' ?>>
            <?= htmlspecialchars($c['nome_curso']) ?>
          </option>
        <?php } ?>
      </select>

      <label>Área de Pesquisa:</label>
      <select name="area_pesquisa" id="area_pesquisa" required>
        <?php while ($a = $areas->fetch_assoc()) { ?>
          <option value="<?= $a['id_area_pesquisa'] ?>" <?= $a['id_area_pesquisa']==$monografia['id_area_pesquisa']?'selected':'' ?>>
            <?= htmlspecialchars($a['nome_area_pesquisa']) ?>
          </option>
        <?php } ?>
      </select>

      <label>Ano de Submissão:</label>
      <select name="ano_submissao" required>
        <?php while ($ano = $anos_submissao->fetch_assoc()) { ?>
          <option value="<?= $ano['id_ano_submissao'] ?>" <?= $ano['id_ano_submissao']==$monografia['id_ano_submissao']?'selected':'' ?>>
            <?= htmlspecialchars($ano['ano']) ?>
          </option>
        <?php } ?>
      </select>

      <label>Período:</label>
      <select name="periodo" required>
        <?php while ($p = $periodos->fetch_assoc()) { ?>
          <option value="<?= $p['id_periodo'] ?>" <?= $p['id_periodo']==$monografia['id_periodo']?'selected':'' ?>>
            <?= htmlspecialchars($p['nome_periodo']) ?>
          </option>
        <?php } ?>
      </select>

      <label>Supervisor:</label>
      <select name="supervisor" required>
        <?php while ($s = $supervisores->fetch_assoc()) { 
          $nome_display = $s['nome'] . (!empty($s['apelido']) ? " ({$s['apelido']})" : "");
        ?>
          <option value="<?= $s['id_usuario'] ?>" <?= $s['id_usuario']==$monografia['id_supervisor']?'selected':'' ?>>
            <?= htmlspecialchars($nome_display) ?>
          </option>
        <?php } ?>
      </select>

      <div class="form-group">
        <?php if ($monografia['caminho_arquivo'] && file_exists($monografia['caminho_arquivo'])): ?>
            <p>📄 <a href="<?= $monografia['caminho_arquivo'] ?>" target="_blank">Abrir PDF atual</a></p>
            <label><input type="checkbox" name="remover_arquivo"> Remover arquivo atual</label>
        <?php else: ?>
            <p><em>Nenhum arquivo anexado.</em></p>
        <?php endif; ?>
      </div>

      <input type="file" name="arquivo" id="monografia_file" accept="application/pdf" class="file-input">
      <div class="drop-zone" id="dropZone">
          <p>Arraste e solte o PDF aqui ou <button type="button" onclick="document.getElementById('monografia_file').click()">clique para escolher</button></p>
          <p id="fileName" class="file-name"></p>
      </div>

      <?php if ($usuario['id_perfil'] == 1): ?>
        <div class="checkbox-wrapper">
            <input type="checkbox" name="destaque" id="destaque" value="1" <?= $monografia['destaque'] == 1 ? 'checked' : '' ?>>
            <label for="destaque">⭐ Definir como Monografia em Destaque</label>
        </div>
      <?php endif; ?>

      <button type="submit">Salvar Alterações</button>
    </form>
  </div>
</div>

<?php if ($redirecionar): ?>
<script>
    // Redireciona em 3 segundos
    setTimeout(() => {
        window.location.href = 'gerenciar_monografias.php';
    }, 3000);
</script>
<?php endif; ?>

<script>
  window.preSelectedCurso = "<?= $monografia['id_curso'] ?>";
  window.preSelectedArea = "<?= $monografia['id_area_pesquisa'] ?>";
  
  // Script simples para visualização do arquivo selecionado no Drag & Drop
  const fileInput = document.getElementById('monografia_file');
  const fileNameDisplay = document.getElementById('fileName');
  fileInput.addEventListener('change', function() {
      if(this.files && this.files.length > 0) {
          fileNameDisplay.textContent = "Selecionado: " + this.files[0].name;
      }
  });
</script>
<script src="js/monografia_selects.js"></script>
</body>
</html>