<?php
include "verifica_login.php";
include "info_usuario.php"; 
include "conexao.php"; // <--- MOVIDO PARA O TOPO para ser usado no SELECT do Idioma

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

$mensagem = "";
$redirecionar = false;

// ----------------------------------------------------------------------
// 1. LÓGICA DE CADASTRO (POST) - AGORA COM PREPARED STATEMENTS
// ----------------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Note: $conexao já está incluído no topo
    
    // Captura e sanitiza dados (trim e htmlspecialchars são boas práticas, mas bind_param fará o papel principal de segurança)
    $nome = trim($_POST['nome']);
    $apelido = trim($_POST['apelido']);
    $telefone = trim($_POST['numero']); // Renomeado para 'telefone' para consistência
    $email = trim($_POST['email']);
    $opc = $_POST['opcao'] ?? '';
    // Converte para INT e usa 1 como fallback se não houver seleção (embora o required do HTML ajude)
    $idioma_preferido = (int)($_POST['idioma'] ?? 1); 

    // Mapeamento do perfil (seguro)
    $id_perfil = 0;
    switch (strtolower($opc)) {
        case 'funcionario':
            $id_perfil = 2;
            break;
        case 'estudante':
            $id_perfil = 3;
            break;
        case 'docente':
            $id_perfil = 4;
            break;
    }

    // Verificação dos campos obrigatórios
    if (empty($nome) || empty($apelido) || empty($email) || empty($telefone) || $id_perfil == 0) {
        $mensagem = "⚠️ Todos os campos obrigatórios (incluindo Perfil) devem ser preenchidos!";
    } 
    else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensagem = "❌ Email inválido.";
    } 
    else if ($idioma_preferido <= 0) {
        $mensagem = "⚠️ Por favor, selecione um Idioma Preferido válido.";
    }
    else { 
        // Cria hash da senha padrão
        $senhaPadrao = "123456";
        $senhaHash = password_hash($senhaPadrao, PASSWORD_DEFAULT);
        
        // Query UNIFICADA (mais limpa)
        $sql = "INSERT INTO usuario 
                (nome, apelido, telefone, email, senha_hash, id_perfil, id_idioma) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conexao->prepare($sql);

        if ($stmt === false) {
            $mensagem = "❌ Erro na preparação da consulta: " . htmlspecialchars($conexao->error);
        } else {
            // 'ssisssi' -> string, string, integer, string, string, integer, integer
            // O campo telefone no seu banco é INT, por isso o 'i'
            // O campo senha_hash é VARCHAR(255), por isso o 's'
            $stmt->bind_param("ssisssi", 
                $nome, 
                $apelido, 
                $telefone, 
                $email, 
                $senhaHash, 
                $id_perfil,
                $idioma_preferido
            );

            if ($stmt->execute()) {
                $mensagem = "✅ Usuário $nome $apelido cadastrado com sucesso!. Redirecionando para a lista de usuários...";
                $redirecionar = true;
            } else {
                // Erro de integridade, como email duplicado (UNIQUE KEY)
                if ($conexao->errno == 1062) {
                    $mensagem = "❌ Erro ao cadastrar: O email **$email** já está em uso.";
                } else {
                    $mensagem = "❌ Erro ao cadastrar: " . $stmt->error;
                }
            }
            $stmt->close();
        }
    }
}

// ----------------------------------------------------------------------
// 2. FUNÇÃO PARA CARREGAR OS IDIOMAS (FORA DO POST)
// ----------------------------------------------------------------------
// Como a $conexao foi incluída no topo, agora podemos usá-la aqui.
$idiomas_lista = $conexao->query("SELECT id_idioma, nome_idioma FROM idioma");

// Não feche a conexão aqui, ela é fechada após o POST ou no final do script se não houver POST.
?>

<!DOCTYPE html>
<html>
<head>
    <title>Cadastro</title>
    <meta charset="UTF-8">
    <meta name="viewport"  content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">

          <script src="logout_auto.js"></script>
          <link rel="stylesheet" href="css/admin.css">
               <script src="js/darkmode2.js"></script>
               <script src="js/sidebar.js"></script>
</head>
<body>

    <button class="menu-btn">☰</button>

<!-- Overlay -->
<div class="sidebar-overlay"></div>
    
<sidebar class="sidebar">

       <br><br>
        <a href="usuarios.php">Voltar aos usuários</a>
           <div class="sidebar-footer">
        <a href="logout.php" title="Sair"><img id="iconelogout" src="icones/logout1.png" alt="Logout"></a>
        <img class="dark-toggle" id="darkToggle" src="icones/lua.png" alt="Modo Escuro" title="Alternar modo escuro">
      </div>
</sidebar>
    <?php if ($mensagem): ?>
        <div class="mensagem <?= str_contains($mensagem, '✅') ? 'success' : 'error' ?>">
            <?= htmlspecialchars($mensagem) ?>
        </div>
    <?php endif; ?>
        <div class="main">
    <form method="post" action="" class="novo_user">
        <label>Nome:</label>
        <input type="text" name="nome" required value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>"><br>

        <label>Apelido:</label>
        <input type="text" name="apelido" required value="<?= htmlspecialchars($_POST['apelido'] ?? '') ?>"><br>
        
           <label>Telefone:</label>
        <input type="text" name="numero" required placeholder="84/87/83 *******" value="<?= htmlspecialchars($_POST['numero'] ?? '') ?>"><br>

        <label>Email:</label>
        <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"><br>

        <label> Idioma Preferido:<label>
        <select name="idioma" required>
            <option value="">Selecione o seu idioma preferido</option>

            <?php
            // Verifica se a query retornou resultados antes de iterar
            if ($idiomas_lista && $idiomas_lista->num_rows > 0) {
                while ($id = $idiomas_lista->fetch_assoc()) {
                    // Mantém a seleção em caso de erro no POST
                    $selected = ($_POST['idioma'] ?? '') == $id['id_idioma'] ? 'selected' : ''; 
                    echo "<option value='{$id['id_idioma']}' {$selected}>{$id['nome_idioma']}</option>";
                }
            } else {
                echo "<option value='' disabled>Nenhum idioma encontrado</option>";
            }
            ?>
        </select><br>


        <label>Perfil:</label>
        <select name="opcao" required>
            <option value="">Selecione o Perfil do Usuário</option>
            <?php $selected_opc = $_POST['opcao'] ?? ''; ?>
            <option value="funcionario" <?= $selected_opc == 'funcionario' ? 'selected' : '' ?>>Funcionário</option>
            <option value="estudante" <?= $selected_opc == 'estudante' ? 'selected' : '' ?>>Estudante</option>
            <option value="docente" <?= $selected_opc == 'docente' ? 'selected' : '' ?>>Docente</option>
        </select><br>
        
        <button type="submit">Cadastrar usuário</button><br><br>
    </form>
    </div>

    <?php if ($redirecionar): ?>
<script>
    // Redireciona em 3 segundos
    setTimeout(() => {
        window.location.href = 'usuarios.php';
    }, 3000);
</script>
<?php endif; ?>


</body>
</html>
<?php 
// Fecha a conexão com o banco de dados no final do script, se estiver aberta
if (isset($conexao)) {
    $conexao->close();
}
?>