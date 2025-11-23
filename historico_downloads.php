<?php
include 'conexao.php'; 
include "verifica_login.php";

$usuarioLogado = $_SESSION['usuario'] ?? null;

if (!$usuarioLogado) {
    header("Location: login.php");
    exit();
}

$id_usuario = $usuarioLogado['id_usuario'] ?? null;
$nomeCompleto = ($usuarioLogado['nome'] ?? '') . ' ' . ($usuarioLogado['apelido'] ?? '');
$id_perfil = $usuarioLogado['id_perfil'] ?? null;

if (!$id_usuario) {
    header("Location: login.php");
    exit();
}

/* =======================================================
   BLOCO AJAX: Ocultar downloads do histórico
======================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ocultar_downloads'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    $downloadsParaOcultar = $_POST['ocultar_downloads'] ?? [];
    $conexao->begin_transaction();

    try {
        $sql = "UPDATE cliques_download SET oculto = TRUE WHERE id_usuario = ? AND id = ?";
        $stmt = $conexao->prepare($sql);
        
        foreach ($downloadsParaOcultar as $idClique) {
            $idClique = intval($idClique);
            $stmt->bind_param("ii", $id_usuario, $idClique);
            $stmt->execute();
        }
        
        $conexao->commit();
        echo json_encode(['status' => 'success', 'message' => 'Downloads ocultados com sucesso.']);
    } catch (Exception $e) {
        $conexao->rollback();
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Erro ao ocultar downloads.']);
    } finally {
        if ($stmt) $stmt->close();
        $conexao->close();
    }
    exit;
}

/* =======================================================
   CONSULTA: Histórico de downloads de monografias
======================================================= */
$sql = "SELECT 
            cd.id,
            cd.data_clique,
            m.tema,
            ap.nome_area_pesquisa,
            d.nome_divisao,
            c.nome_curso,
            a.ano AS ano_submissao
        FROM 
            cliques_download cd
        JOIN 
            monografia m ON cd.id_monografia = m.id_monografia
        JOIN
            divisao d ON m.id_divisao = d.id_divisao
        JOIN
            curso c ON m.id_curso = c.id_curso
        JOIN 
            area_pesquisa ap ON m.id_area_pesquisa = ap.id_area_pesquisa
        JOIN
            ano_submissao a ON m.id_ano_submissao = a.id_ano_submissao
        WHERE 
            cd.id_usuario = ? AND cd.oculto = FALSE
        ORDER BY 
            cd.data_clique DESC";

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

    <title>Histórico de Downloads - Monografias</title>
    <script src="js/darkmode1.js"></script>  
    <link rel="stylesheet" href="css/basico.css">
      <script src="js/dropdown.js"></script>
      <script src="js/paginacao.js"></script>

                     <script src="js/sidebar2.js"></script> 
      
</head>
<body>

<?php if ($usuarioLogado): ?>
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
      Voltar à área de Monografias</a>
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

  
</nav>
<?php endif; ?>

<div class="container">
    <h2>Histórico de Downloads de Monografias</h2>
    
    <?php if ($result->num_rows > 0): ?>
        <div class="select-all">
            <label class="checkbox-container">
                <input type="checkbox" id="selecionar-todos">
                <span class="checkmark"></span>
            </label>
            <label for="selecionar-todos">Selecionar Todos</label>
        </div>
        
        
        <form id="form-ocultar">
            <div class="cards-container">
                <div id="pagination" class="pagination"></div>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <div class="card" data-id="<?= htmlspecialchars($row['id']) ?>">
                        <div class="card-content">
                            <div class="checkbox-wrapper">
                                <label class="checkbox-container">
                                    <input type="checkbox" name="downloads[]" value="<?= htmlspecialchars($row['id']) ?>">
                                    <span class="checkmark"></span>
                                </label>
                                <h3><?= htmlspecialchars($row['tema']) ?></h3>
                            </div>
                            <span><?= (new DateTime($row['data_clique']))->format('d/m/Y H:i') ?></span>
                        </div>
                        <p><strong>Divisão:</strong> <?= htmlspecialchars($row['nome_divisao']) ?></p>
                        <p><strong>Curso:</strong> <?= htmlspecialchars($row['nome_curso']) ?></p>
                        <p><strong>Área de Pesquisa:</strong> <?= htmlspecialchars($row['nome_area_pesquisa']) ?></p>
                        <p><strong>Ano de Submissão:</strong> <?= htmlspecialchars($row['ano_submissao']) ?></p>
                    </div>
                <?php endwhile; ?>
            </div>
            <div class="acoes">
                <button type="button" id="btn-ocultar-selecionados" class="btn-ocultar" disabled>Eliminar</button>
            </div>
        </form>

    <?php else: ?>
        <p style="text-align: center; margin-top: 50px;">
            Você ainda não fez nenhum download de monografia. 
            <a href="cadastrar_monografia.php">Clique Aqui</a> e encontre o trabalho que precisa.
        </p>
    <?php endif; ?>
</div>

<!-- Modal genérico -->
<div id="customModal" class="modal">
  <div class="modal-content">
    <span class="close-btn">&times;</span>
    <p id="modal-message">Mensagem aqui...</p>
    <div class="modal-actions">
      <button id="modal-confirm" class="btn-confirm">Confirmar</button>
      <button id="modal-cancel" class="btn-cancel">Cancelar</button>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selecionarTodos = document.getElementById('selecionar-todos');
    const checkboxes = document.querySelectorAll('input[name="downloads[]"]');
    const btnOcultar = document.getElementById('btn-ocultar-selecionados');

    const modal = document.getElementById('customModal');
    const modalMessage = document.getElementById('modal-message');
    const btnConfirm = document.getElementById('modal-confirm');
    const btnCancel = document.getElementById('modal-cancel');
    const closeBtn = document.querySelector('.close-btn');

    function openModal(message, onConfirm, options = {}) {
        modalMessage.textContent = message;
        modal.style.display = 'flex';
        const actionsDiv = document.querySelector('.modal-actions');
        actionsDiv.style.display = options.hideActions ? 'none' : 'flex';

        btnConfirm.onclick = () => {
            modal.style.display = 'none';
            if (typeof onConfirm === 'function') onConfirm();
        };
        btnCancel.onclick = () => { modal.style.display = 'none'; };
        closeBtn.onclick = () => { modal.style.display = 'none'; };
        window.onclick = (e) => { if (e.target === modal) modal.style.display = 'none'; };

        if (options.autoClose) {
            setTimeout(() => {
                modal.style.display = 'none';
                if (options.reload) window.location.reload();
            }, options.autoClose);
        }
    }

    function toggleBtnState() {
        const checkedCount = document.querySelectorAll('input[name="downloads[]"]:checked').length;
        btnOcultar.disabled = checkedCount === 0;
    }

    selecionarTodos?.addEventListener('change', function() {
        checkboxes.forEach(cb => cb.checked = selecionarTodos.checked);
        toggleBtnState();
    });

    checkboxes.forEach(cb => cb.addEventListener('change', function() {
        if (!this.checked) selecionarTodos.checked = false;
        else selecionarTodos.checked = Array.from(checkboxes).every(x => x.checked);
        toggleBtnState();
    }));

    btnOcultar.addEventListener('click', function(e) {
        e.preventDefault();
        const selected = Array.from(checkboxes).filter(cb => cb.checked).map(cb => cb.value);

        if (selected.length === 0) {
            openModal('Por favor, selecione pelo menos um download para eliminar.', null, { hideActions: true, autoClose: 3000 });
            return;
        }

        openModal(`Tem certeza que deseja eliminar ${selected.length} download(s)?`, () => {
            fetch('historico_downloads.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `ocultar_downloads[]=${selected.join('&ocultar_downloads[]=')}`
            })
            .then(resp => resp.json())
            .then(data => {
                if (data.status === 'success') {
                    openModal('Download(s) eliminado(s) com sucesso do histórico!', null, { hideActions: true, autoClose: 3000, reload: true });
                } else {
                    openModal('Erro: ' + data.message, null, { hideActions: true, autoClose: 3000 });
                }
            })
            .catch(() => {
                openModal('Erro ao processar a requisição.', null, { hideActions: true, autoClose: 3000 });
            });
        });
    });

    toggleBtnState();
});
</script>
</body>
</html>

<?php
$result->free_result();
$conexao->close();
?>
