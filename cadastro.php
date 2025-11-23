<?php 
// 1. INCLUSÃO DA CONEXÃO NO TOPO
// Isso permite que o SELECT de idiomas seja executado mesmo que o formulário não seja submetido (método GET).
include "conexao.php"; 

$mensagem = "";
$redirecionar = false;

// ----------------------------------------------------------------------
// LÓGICA DE PROCESSAMENTO (POST)
// ----------------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // A conexão já está incluída no topo

    $nome       = htmlspecialchars(trim($_POST['nome']));
    $apelido    = htmlspecialchars(trim($_POST['apelido']));
    $telefone   = htmlspecialchars(trim($_POST['telefone']));
    $email      = htmlspecialchars(trim($_POST['email']));
    $senha      = trim($_POST['senha']);
    $conf       = htmlspecialchars(trim($_POST['conf']));
    // CAPTURA DO IDIOMA
    $id_idioma  = (int)($_POST['idioma'] ?? 0); 

    // Verificação dos campos obrigatórios
    if (empty($nome) || empty($apelido) || empty($telefone) || empty($email) || empty($senha) || empty($conf) || $id_idioma === 0) {
        $mensagem = "⚠️ Todos os campos, incluindo o idioma, são obrigatórios!";
    } 
    else if ($senha != $conf) {
        $mensagem = "❌ A senha e a confirmação não coincidem.";
    } 
    else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensagem = "❌ Email inválido.";
    } 
    elseif (!preg_match('/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d).{6,}$/', $senha)) {
        $mensagem = "❌ A senha deve ter pelo menos 6 caracteres, uma letra maiúscula, uma minúscula e um número.";
    }
    else { 
        // Criptografa a senha definida pelo usuário
        $senha_hash = password_hash($senha, PASSWORD_DEFAULT);

        // 2. MODIFICAÇÃO DA QUERY PARA INCLUIR id_idioma
        // Adicionamos 'id_idioma' na lista de colunas e um novo placeholder '?' na lista de VALUES.
        $sql = "INSERT INTO usuario (nome, apelido, telefone, email, senha_hash, id_perfil, primeira_senha, id_idioma) 
                 VALUES (?, ?, ?, ?, ?, 3, 0, ?)";
        
        $stmt = $conexao->prepare($sql);
        // O tipo de bind_param é 'sssssi' (string, string, string, string, string, integer)
        $stmt->bind_param("sssssi", $nome, $apelido, $telefone, $email, $senha_hash, $id_idioma);

        if ($stmt->execute()) {
            $mensagem = "✅ Cadastro realizado com sucesso! Redirecionando para a tela de login...";
            $redirecionar = true;
        } else {
            // Se for erro 1062 (duplicidade), dê uma mensagem mais amigável
            if ($conexao->errno === 1062) {
                $mensagem = "❌ O email ou telefone já está cadastrado.";
            } else {
                $mensagem = "❌ Erro ao cadastrar: " . $conexao->error;
            }
        }

        $stmt->close();
        $conexao->close();
    }
}

// ----------------------------------------------------------------------
// PREPARAÇÃO DOS DADOS PARA O FORMULÁRIO (GET)
// ----------------------------------------------------------------------
// Busca todos os idiomas cadastrados para preencher o SELECT.
$idiomas_lista = null;
if (isset($conexao)) { // Garante que a conexão exista (o que deve acontecer, pois foi incluída no topo)
    $idiomas_lista = $conexao->query("SELECT id_idioma, nome_idioma FROM idioma ORDER BY nome_idioma");
}
?>


<!DOCTYPE html>
<html>
<head>
    <title>Cadastro</title>
    <meta charset="UTF-8">
    <meta name="viewport"  content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">

          <script src="logout_auto.js"></script>
               <script src="js/mostrarSenha.js"></script>
               <link rel="stylesheet" href="css/admin.css">
    
    <style>
          body {
              font-family: Arial, sans-serif;
              background: #f5f5f5;
              padding: 20px;
          }

          h2 {
              text-align: center;
              color: #333;
          }

          form {
              max-width: 500px;
              margin: auto;
              background: white;
              padding: 30px;
              border-radius: 10px;
              box-shadow: 0 0 10px #ccc;
          }

          label {
              display: block;
              margin-bottom: 5px;
              font-weight: bold;
          }

          input, select {
              width: 100%;
              padding: 10px;
              margin-bottom: 15px;
              border-radius: 5px;
              border: 1px solid #aaa;
          }

          button {
              width: 100%;
              padding: 12px;
              background-color: #2c3e50;
              color: white;
              border: none;
              border-radius: 5px;
              font-size: 16px;
              cursor: pointer;
          }

          button:hover {
               background-color: #385470ff;
          }
          
           .mensagem {
               max-width: 500px;
               margin: 20px auto;
               padding: 15px;
               border-radius: 8px;
               font-weight: bold;
           }

           .mensagem.success {
               background-color: #d4edda;
               color: #155724;
           }

           .mensagem.error {
               background-color: #f8d7da;
               color: #721c24;
           }
            .topbar {
        width: 100%;
        height: 40px;
        background: #2c3e50; /* azul escuro elegante */
        display: flex;
        align-items: center;

        padding: 0 20px;
        position: fixed; /* fixa no topo */
        top: 0;
        left: 0;
        z-index: 1000;
        box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    }

    .topbar a {
        color: #ecf0f1;
        margin-left: 20px;
        text-decoration: none;
        font-weight: 500;
        transition: color 0.3s;
    }

    .topbar a:hover {
     
        color: #1abc9c;
    }
    </style>
</head>
<body>

<nav class="topbar">

             <a href="login.php">Voltar ao Login</a>
            
</nav>



    <?php if ($mensagem): ?>
        <div class="mensagem <?= str_contains($mensagem, '✅') ? 'success' : 'error' ?>">
            <?= $mensagem ?>
        </div>
    <?php endif; ?> <br><br>

    <h2>Cadastro de Usuário</h2>
    <form method="post" action="">
        <label>Nome:</label>
        <input type="text" name="nome" required value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>"><br>

        <label>Apelido:</label>
        <input type="text" name="apelido" required value="<?= htmlspecialchars($_POST['apelido'] ?? '') ?>"><br>

        <label>Telefone:</label>
        <input type="text" name="telefone" required placeholder="84/87/83 *******" value="<?= htmlspecialchars($_POST['telefone'] ?? '') ?>"><br>
        
        <label>Email:</label>
        <input type="email" name="email" placeholder="xxx@gmail.com" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"><br>

        <label>Idioma Preferido:</label>
        <select name="idioma" required>
            <option value="">Selecione seu idioma preferido</option>
            <?php
            $selected_idioma = $_POST['idioma'] ?? '';
            if ($idiomas_lista && $idiomas_lista->num_rows > 0) {
                while ($id = $idiomas_lista->fetch_assoc()) {
                    $selected = $selected_idioma == $id['id_idioma'] ? 'selected' : '';
                    echo "<option value='{$id['id_idioma']}' {$selected}>" . htmlspecialchars($id['nome_idioma']) . "</option>";
                }
            } else {
                echo "<option value='' disabled>Nenhum idioma cadastrado</option>";
            }
            ?>
        </select><br>

        <label>Senha:</label>
        <div style="position: relative; display: flex; align-items: center; justify-content: center;">
            <input type="password" name="senha" class="campo-senha-nova" required
                   style="width: 100%; padding-right: 35px; box-sizing: border-box;">
            <img src="icones/olho_fechado1.png"
                 alt="Mostrar nova senha"
                 class="toggle-senha"
                 data-target="campo-senha-nova"
                 style="position: absolute; right: 10px; cursor: pointer; width: 22px; opacity: 0.8;">
        </div>

        <label>Confirme a sua senha:</label>
        <div style="position: relative; display: flex; align-items: center; justify-content: center;">
            <input type="password" name="conf" class="campo-senha-confirmacao" required
                   style="width: 100%; padding-right: 35px; box-sizing: border-box;">
            <img src="icones/olho_fechado1.png"
                 alt="Mostrar confirmação de senha"
                 class="toggle-senha"
                 data-target="campo-senha-confirmacao"
                 style="position: absolute; right: 10px; cursor: pointer; width: 22px; opacity: 0.8;">
        </div><br><br>

        <button type="submit">Cadastrar-se</button><br><br>
    </form>

    <?php if ($redirecionar): ?>
<script>
    // Redireciona em 3 segundos
    setTimeout(() => {
        window.location.href = 'login.php';
    }, 3000);
</script>
<?php endif; ?>


</body>
</html>