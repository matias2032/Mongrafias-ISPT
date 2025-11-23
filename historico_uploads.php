<?php
include "conexao.php";
include "verifica_login.php";

$usuarioLogado = $_SESSION['usuario'] ?? null;
$id_usuario = $usuarioLogado['id_usuario'] ?? null;
$nomeCompleto = ($usuarioLogado['nome'] ?? '') . ' ' . ($usuarioLogado['apelido'] ?? '');
$id_perfil = $usuarioLogado['id_perfil'] ?? null;

if (!$id_usuario) {
    header("Location: login.php");
    exit();
}

/* =======================================================
   PROCESSAMENTO: Ocultar uploads (AJAX)
======================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ocultar_uploads'])) {
    $ids = $_POST['ocultar_uploads'];

    if (is_array($ids) && count($ids) > 0) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids) + 1); // +1 para o id_usuario

        $sql = "UPDATE upload SET oculto = 1 WHERE id_upload IN ($placeholders) AND id_usuario = ?";
        $stmt = $conexao->prepare($sql);

        // Monta array de parâmetros (todos por referência)
        $params = array_merge($ids, [$id_usuario]);
        $refs = [];
        foreach ($params as $key => $value) {
            $refs[$key] = &$params[$key];
        }

        array_unshift($refs, $types); // coloca os tipos no início
        call_user_func_array([$stmt, 'bind_param'], $refs);

        $ok = $stmt->execute();
        echo json_encode(['success' => $ok]);
        exit();
    }

    echo json_encode(['success' => false, 'error' => 'Nenhum ID recebido.']);
    exit();
}


/* =======================================================
   CONSULTA: Histórico de uploads visíveis
======================================================= */
$sql = "SELECT 
            u.id_upload,
            m.id_monografia,
            m.tema,
            m.nome_estudante,
            m.apelido_estudante,
            m.data_submissao,
            c.nome_curso,
            d.nome_divisao,
            ap.nome_area_pesquisa,
            ans.ano AS ano_submissao,
            p.nome_periodo
        FROM monografia m
        JOIN upload u ON u.id_monografia = m.id_monografia
        JOIN curso c ON m.id_curso = c.id_curso
        JOIN divisao d ON m.id_divisao = d.id_divisao
        JOIN area_pesquisa ap ON m.id_area_pesquisa = ap.id_area_pesquisa
        JOIN ano_submissao ans ON m.id_ano_submissao = ans.id_ano_submissao
        JOIN periodo p ON m.id_periodo = p.id_periodo
        WHERE u.id_usuario = ? AND u.oculto = 0
        ORDER BY m.data_submissao DESC";

$stmt = $conexao->prepare($sql);
$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<meta name="viewport"  content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">

<title>Histórico de Uploads - Monografias</title>
<link rel="stylesheet" href="css/basico.css">
<link rel="stylesheet" href="css/cards_monografias.css">
<script src="js/darkmode1.js"></script>
<script src="js/dropdown.js"></script>
<script src="js/paginacao.js"></script>
                     <script src="js/sidebar2.js"></script> 

<style>
.card-selecionada {
  border: 2px solid #007bff;
  box-shadow: 0 0 10px rgba(0,123,255,0.3);
}

.btn-ocultar {
  background-color: #dc3545;
  color: white;
  border: none;
  border-radius: 8px;
  padding: 10px 20px;
  cursor: pointer;
  font-weight: bold;
  transition: background-color 0.2s ease;
}

.btn-ocultar:hover {
  background-color: #c82333;
}

.checkbox-upload,
#selecionarTodos {
  transform: scale(1.3);
  margin-right: 8px;
}

.acoes-superiores {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin: 15px 0;
}

.div-selecionar-todos {
  display: flex;
  align-items: center;
  gap: 6px;
  font-weight: bold;
  user-select: none;
}

</style>

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
      <a href="ver_monografias.php">
      <img class="icone2" src="icones/voltar1.png" alt="voltar" title="voltar">    
      Voltar</a>
    </div>

    <!-- 🔹 AÇÕES USUÁRIO (sempre visível) -->
    <div class="acoes-usuario">
      <!-- Dark Mode Toggle -->
      <img class="dark-toggle" id="darkToggle" src="icones/lua.png" alt="Alternar modo escuro" title="Alternar modo escuro">

      <!-- Usuário -->
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
    </div>
  </div>
</header>

<!-- 🔹 MENU MOBILE SIDEBAR -->
<nav id="mobileMenu" class="nav-mobile-sidebar hidden">
  <div class="sidebar-header">
    <button class="close-btn" id="closeMobileMenu">&times;</button>
  </div>

  <ul class="sidebar-links">
    <li><a href="ver_monografias.php">
          <img class="icone2" src="icones/voltar1.png" alt="voltar" title="voltar">     
    Voltar à área de Monografias</a></li>
  </ul>

  <!-- 🔹 USUÁRIO NO FUNDO DO MENU -->
  
</nav>

<div class="container">
  <h2>Histórico de Monografias Enviadas</h2>

  <?php if ($result->num_rows > 0): ?>
  <div class="acoes-superiores">
    <div class="div-selecionar-todos">
      <label>
        <input type="checkbox" id="selecionarTodos">
        Selecionar Todos
      </label>
    </div>

    <button class="btn-ocultar" id="btnOcultar">Ocultar Selecionado(s)</button>
  </div>

  <div class="cards-container">
    <div id="pagination" class="pagination"></div>
    <?php while($m = $result->fetch_assoc()): ?>
      <div class="card" data-id="<?= $m['id_upload'] ?>">
        <label style="display:flex;align-items:center;margin-bottom:6px;">
          <input type="checkbox" class="checkbox-upload">
          <strong><?= htmlspecialchars($m['tema']) ?></strong>
        </label>
        <p><strong>Autor:</strong> <?= htmlspecialchars($m['nome_estudante'] . ' ' . $m['apelido_estudante']) ?></p>
        <p><strong>Divisão:</strong> <?= htmlspecialchars($m['nome_divisao']) ?></p>
        <p><strong>Curso:</strong> <?= htmlspecialchars($m['nome_curso']) ?></p>
        <p><strong>Área:</strong> <?= htmlspecialchars($m['nome_area_pesquisa']) ?></p>
        <p><strong>Ano:</strong> <?= htmlspecialchars($m['ano_submissao']) ?><br>
           <strong>Período:</strong> <?= htmlspecialchars($m['nome_periodo']) ?></p>
        <p class="data-submissao"><strong>Data de Submissão:</strong> <?= date("d/m/Y", strtotime($m['data_submissao'])) ?></p>
      </div>
    <?php endwhile; ?>
  </div>
<?php else: ?>
  <p style="text-align:center; margin-top:50px;">
    Nenhuma monografia enviada ainda. <a href="cadastrar_monografia.php">Clique Aqui</a> e faça um upload.
  </p>
<?php endif; ?>

</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const btnOcultar = document.getElementById('btnOcultar');
  const chkTodos = document.getElementById('selecionarTodos');
  const checkboxes = document.querySelectorAll('.checkbox-upload');

  // === Alternar seleção de todos ===
  chkTodos?.addEventListener('change', e => {
    const marcado = e.target.checked;
    checkboxes.forEach(chk => {
      chk.checked = marcado;
      chk.closest('.card').classList.toggle('card-selecionada', marcado);
    });
  });

  // === Seleção individual ===
  checkboxes.forEach(chk => {
    chk.addEventListener('change', e => {
      const card = e.target.closest('.card');
      card.classList.toggle('card-selecionada', e.target.checked);

      // Atualiza "Selecionar Todos"
      const todosMarcados = Array.from(checkboxes).every(c => c.checked);
      chkTodos.checked = todosMarcados;
    });
  });

  // === Ocultar selecionados ===
  btnOcultar?.addEventListener('click', () => {
    const selecionados = Array.from(document.querySelectorAll('.checkbox-upload:checked'))
      .map(chk => chk.closest('.card').dataset.id);

    if (selecionados.length === 0) {
      alert('Selecione pelo menos um upload para ocultar.');
      return;
    }

    if (!confirm('Deseja realmente ocultar o(s) upload(s) selecionado(s)?')) return;

    // Monta corpo da requisição com segurança
    const body = new URLSearchParams();
    selecionados.forEach(id => body.append('ocultar_uploads[]', id));

    fetch('historico_uploads.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: body.toString()
    })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        alert('Uploads ocultados com sucesso!');
        location.reload();
      } else {
        alert(data.message || 'Erro ao ocultar uploads.');
      }
    })
    .catch(err => {
      console.error(err);
      alert('Erro ao processar a requisição.');
    });
  });
});
</script>
</body>
</html>

<?php
$result->free_result();
$conexao->close();
?>
