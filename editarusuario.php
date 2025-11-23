<?php
include "conexao.php";
include "verifica_login.php";

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Verifica se o usuário está logado
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

// Inicialização da variável $usuario para evitar erros de undefined
$usuario = null;
$id_usuario = null;
$id_perfil = $_SESSION['usuario']['id_perfil'] ?? null;
$usuario_logado = $_SESSION['usuario'];

// --- 1. CARREGA A LISTA DE IDIOMAS PARA O FORMULÁRIO (GET) ---
$idiomas_lista = $conexao->query("SELECT id_idioma, nome_idioma FROM idioma ORDER BY nome_idioma");

// --- BUSCA INICIAL DOS DADOS NO CARREGAMENTO DA PÁGINA (GET) ---
if (isset($_GET['id_usuario'])) {
    $id_usuario = intval($_GET['id_usuario']);

    // Verifica a permissão de acesso
    if ($id_perfil != 1 && $usuario_logado['id_usuario'] != $id_usuario) {
        die("❌ Acesso negado. Você não pode editar o perfil de outro usuário.");
    }

    $stmt_fetch = $conexao->prepare("SELECT * FROM usuario WHERE id_usuario = ?");
    if ($stmt_fetch === false) {
        die("Erro na preparação da consulta de busca: " . $conexao->error);
    }
    $stmt_fetch->bind_param("i", $id_usuario);
    $stmt_fetch->execute();
    $resultado = $stmt_fetch->get_result();
    $usuario = $resultado->fetch_assoc();
    $stmt_fetch->close(); // Fechamento seguro da variável de busca
    
    if (!$usuario) {
        die("Usuário não encontrado.");
    }
} else {
    // Se não houver ID no GET e não for POST, redireciona ou avisa.
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        die("ID do usuário não foi informado.");
    }
}

// Mensagem opcional de sucesso (feedback amigável)
if (isset($_GET['atualizado']) && $_GET['atualizado'] == 1) {
    echo "<div class='sucesso'>✅ Usuário atualizado com sucesso!</div>";
}

$mensagem = "";
$redirecionar = false;

// --- PROCESSAMENTO DO FORMULÁRIO (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_usuario = $_POST['id_usuario'] ?? '';
    $nome = trim($_POST['nome'] ?? '');
    $apelido = trim($_POST['apelido'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    // CAPTURA E CAST DO IDIOMA
    $id_idioma = intval($_POST['idioma'] ?? 0); 
    $tipo_mensagem = "error";
    
    // Variável para o Statement de UPDATE - Resolve o conflito de reutilização de $stmt
    $stmt_update = null; 
    $idperfil_update = null; // Inicializa para evitar warnings

    // Lógica para Admin (id_perfil == 1)
    if ($id_perfil == 1) {
        $opc = $_POST['opcao'] ?? '';
        switch (strtolower($opc)) {
            
            case 'funcionario': // Trata sem acento
                $idperfil_update = 2;
                break; 
            case 'estudante':
                $idperfil_update = 3;
                break;
            
            case 'docente':
                $idperfil_update = 4;
                break;
            default:
                $mensagem = "Erro! Perfil inválido.";
                break;
        }

        if (empty($mensagem)) {
            // 2A. QUERY DE UPDATE PARA ADMIN (Inclui id_perfil e id_idioma)
            $sql_update = "UPDATE usuario SET nome = ?, apelido = ?, telefone = ?, email = ?, id_perfil = ?, id_idioma = ? WHERE id_usuario = ?";
            $stmt_update = $conexao->prepare($sql_update);
            // TIPOS: nome(s), apelido(s), telefone(s), email(s), id_perfil(i), id_idioma(i), id_usuario(i)
            // SEQUÊNCIA: "ssssiii"
            if ($stmt_update) {
                // CORREÇÃO AQUI: bind_param correto para 7 variáveis (4 strings, 3 integers)
                $stmt_update->bind_param("ssssiii", $nome, $apelido, $telefone, $email, $idperfil_update, $id_idioma, $id_usuario);
            }
        }
    } else { // Lógica para Usuários Normais (não podem mudar id_perfil, mas podem mudar idioma)
        // 2B. QUERY DE UPDATE PARA USUÁRIO NORMAL (Inclui apenas id_idioma no final)
        $sql_update = "UPDATE usuario SET nome = ?, apelido = ?, telefone = ?, email = ?, id_idioma = ? WHERE id_usuario = ?";
        $stmt_update = $conexao->prepare($sql_update);
        // TIPOS: nome(s), apelido(s), telefone(s), email(s), id_idioma(i), id_usuario(i)
        // SEQUÊNCIA: "ssssii"
        if ($stmt_update) {
             // CORREÇÃO AQUI: bind_param correto para 6 variáveis (4 strings, 2 integers)
            $stmt_update->bind_param("ssssii", $nome, $apelido, $telefone, $email, $id_idioma, $id_usuario);
        }
    }

    // --- Execução da Query de UPDATE ---
    if (isset($stmt_update) && $stmt_update) {
        if ($stmt_update->execute()) {
            if ($stmt_update->affected_rows > 0) {
                $tipo_mensagem = "success";
                if ($id_perfil == 1) { 
                    $mensagem = "Usuário atualizado com sucesso."; 
                } else { 
                    $mensagem = "Dados atualizados com sucesso! Você será desconectado em breve para aplicar as mudanças."; 
                }
                $redirecionar = true;

                // Recarrega os dados atualizados para exibir no formulário
                $stmt_refresh = $conexao->prepare("SELECT * FROM usuario WHERE id_usuario = ?");
                if ($stmt_refresh) {
                    $stmt_refresh->bind_param("i", $id_usuario);
                    $stmt_refresh->execute();
                    $resultado_refresh = $stmt_refresh->get_result();
                    $usuario = $resultado_refresh->fetch_assoc();
                    $stmt_refresh->close();
                }

            } else {
                $tipo_mensagem = "info"; // Mudado para 'info' para não parecer um erro fatal
                $mensagem = "Nenhuma alteração foi feita, os dados são os mesmos.";
            }
        } else {
             $tipo_mensagem = "error";
             $mensagem = "Erro na execução: " . $stmt_update->error;
        }
        $stmt_update->close(); // Fechamento seguro do statement de UPDATE
    } elseif (empty($mensagem)) {
        // Isso cobre falhas no prepare ou lógica não tratada
        $tipo_mensagem = "error";
        $mensagem = "Erro ao processar a atualização. Tente novamente.";
    }
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<meta name="viewport"  content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">

<title>Editar Usuário</title>
      <script src="logout_auto.js"></script>
      <link rel="stylesheet" href="css/admin.css">
      <script src="js/darkmode2.js"></script>
      <script src="js/sidebar.js"></script>
        <script src="js/dropdown2.js"></script>
</head>
<body>

    <button class="menu-btn">☰</button>

<!-- Overlay -->
<div class="sidebar-overlay"></div>

<sidebar class="sidebar">
        <?php if ($id_perfil==1): ?>
  <br><br>
             <?php else: ?>
<h2>Menu Do(a) <?= htmlspecialchars($usuario['nome'] ?? 'Usuário') ?></h2>
  <?php endif; ?>
             <?php if ($id_perfil==1): ?>
    <a href="usuarios.php">Voltar aos Usuários</a>
    <?php else: ?>
<a href="ver_monografias.php">Voltar ás Monografias</a>
         <?php endif; ?>
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




<?php if (!empty($mensagem)): ?>
    <div class="mensagem <?= $tipo_mensagem ?? 'info' ?>">
        <?= htmlspecialchars($mensagem) ?>
    </div>
<?php endif; ?>

<h2>Editar Usuário</h2>

<div class="container">
<?php if ($usuario): // Exibe o formulário apenas se os dados do usuário foram carregados ?>
<form method="POST" action="" class="edit_user">
  <input type="hidden" name="id_usuario" value="<?php echo htmlspecialchars($usuario['id_usuario']); ?>">

  <label>Nome:</label>
  <input type="text" name="nome" value="<?php echo htmlspecialchars($usuario['nome']); ?>"><br>

  <label>Apelido:</label>
  <input type="text" name="apelido" value="<?php echo htmlspecialchars($usuario['apelido']); ?>"><br>

  <label>Telefone:</label>
  <input type="text" name="telefone" value="<?php echo htmlspecialchars($usuario['telefone']); ?>"><br>

  <label>Email:</label>
  <input type="email" name="email" value="<?php echo htmlspecialchars($usuario['email']); ?>"><br>

    <label>Idioma Preferido:</label>
    <select name="idioma">
        <option value="">Selecione o idioma</option>
        <?php
        // Usa o valor atual do usuário, mas se o POST falhar, usa o valor submetido
        $current_idioma_id = $_POST['idioma'] ?? $usuario['id_idioma'] ?? '';

        if ($idiomas_lista && $idiomas_lista->num_rows > 0) {
            while ($id = $idiomas_lista->fetch_assoc()) {
                $selected = $current_idioma_id == $id['id_idioma'] ? 'selected' : '';
                echo "<option value='{$id['id_idioma']}' {$selected}>" . htmlspecialchars($id['nome_idioma']) . "</option>";
            }
        } else {
            echo "<option value='' disabled>Nenhum idioma encontrado</option>";
        }
        ?>
    </select><br>

    <?php if ($id_perfil==1): ?>
    <label>Perfil:</label>
        <select name="opcao">
              <option value="funcionario" <?php if ($usuario['id_perfil'] == 2) echo 'selected'; ?>>Funcionário</option>
            <option value="estudante" <?php if ($usuario['id_perfil'] == 3) echo 'selected'; ?>>Estudante</option>
                      <option value="docente" <?php if ($usuario['id_perfil'] == 4) echo 'selected'; ?>>Docente</option>
        </select><br><br>
            <?php endif; ?>

  <button type="submit">Atualizar</button>
</form>
<?php endif; ?>
</div>


<?php if ($redirecionar): ?>
<script>
    setTimeout(() => {
        // Redireciona o Admin para a lista de usuários, e o usuário normal para o login/página inicial
        window.location.href = '<?php echo ($id_perfil == 1) ? 'usuarios.php' : 'logout.php'; ?>';
    }, 3000);
</script>
<?php endif; ?>
</body>
</html>