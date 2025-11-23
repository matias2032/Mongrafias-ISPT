<?php 
include "conexao.php";
include "verifica_login.php";
// include "usuario_logica.php";

$usuario = $_SESSION['usuario'];

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

// Parâmetros GET
$pesquisa = isset($_GET['pesquisa']) ? trim($_GET['pesquisa']) : '';
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$ordenar_por = isset($_GET['ordenar_por']) ? $_GET['ordenar_por'] : 'nome';
$ordem = (isset($_GET['ordem']) && $_GET['ordem'] === 'desc') ? 'DESC' : 'ASC';

// Validar campo de ordenação
$colunas_validas = ['nome', 'apelido'];
if (!in_array($ordenar_por, $colunas_validas)) {
    $ordenar_por = 'nome';
}

// Paginação
$limite = 5;
$offset = ($pagina - 1) * $limite;

// Condição base
$condicao = "id_perfil IN (2,3,4)";
$tipos = "";
$parametros = [];
$sql_where = $condicao;

if (!empty($pesquisa)) {
    $sql_where .= " AND (nome LIKE ? OR apelido LIKE ?)";
    $tipos = "ss";
    $parametros[] = "%$pesquisa%";
    $parametros[] = "%$pesquisa%";
}

// Contagem total de registros
$sql_total = "SELECT COUNT(*) AS total FROM usuario WHERE $sql_where";
$stmt_total = $conexao->prepare($sql_total);
if (!empty($pesquisa)) {
    $stmt_total->bind_param($tipos, ...$parametros);
}
$stmt_total->execute();
$resultado_total = $stmt_total->get_result();
$total_registros = $resultado_total->fetch_assoc()['total'];
$total_paginas = ceil($total_registros / $limite);
$stmt_total->close();

// Consulta de dados
// $sql = "SELECT * FROM usuario WHERE $sql_where ORDER BY $ordenar_por $ordem LIMIT $limite OFFSET $offset";
// $stmt = $conexao->prepare($sql);

$sql = "SELECT * FROM vw_usuario_geral WHERE $sql_where ORDER BY $ordenar_por $ordem LIMIT $limite OFFSET $offset";
$stmt = $conexao->prepare($sql);
if (!empty($pesquisa)) {
    $stmt->bind_param($tipos, ...$parametros);
}
$stmt->execute();
$resultado = $stmt->get_result();
?>
<?php include "info_usuario.php"; ?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"  content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">

    <title>Gerenciar Usuários</title>
   
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

<br><br>
        <a href="dashboard.php">Voltar ao Menu Principal</a>
               <a href="cadastro2.php">Cadastrar Novo usuário</a>
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


<div class="container">
   
    <h3>Buscar Usuários</h3>
    <form method="GET" action="">
        <input type="search" name="pesquisa" id="texto" placeholder="Nome ou apelido" value="<?php echo htmlspecialchars($pesquisa); ?>">
        <input type="submit" value="Buscar" id="Busca">
    </form>

    <p>Ordenar por:
        <a href="?pesquisa=<?= urlencode($pesquisa) ?>&ordenar_por=nome&ordem=<?= ($ordenar_por == 'nome' && $ordem == 'ASC') ? 'desc' : 'asc'; ?>">Nome</a> |
        <a href="?pesquisa=<?= urlencode($pesquisa) ?>&ordenar_por=apelido&ordem=<?= ($ordenar_por == 'apelido' && $ordem == 'ASC') ? 'desc' : 'asc'; ?>">Apelido</a>
    </p>

    <h3>Total de Usuários Encontrados: <?= $total_registros ?></h3>

<table id="tabela-user">
        <thead>
        <tr>
            <!-- <th>ID</th> -->
            <th>Nome</th>
            <th>Apelido</th>
            <th>Telefone</th>
            <th>Email</th>
            <th>Idioma Preferido</th>  
            <th>Perfil</th>
            <th>Ação</th>
        </tr>
        </thead>
        <tbody>
       <?php if ($resultado->num_rows > 0): ?>
         <?php while ($linha = $resultado->fetch_assoc()): ?>
           <tr>
    <!-- <td><?= $linha['id_usuario'] ?></td> -->
    <td><?= $linha['nome'] ?></td>
    <td><?= $linha['apelido'] ?></td>
    <td><?= $linha['telefone'] ?></td>
    <td><?= $linha['email'] ?></td>
     <td><?= $linha['nome_idioma']?></td>
    <td><?= $linha['nome_perfil']?></td>
   
    <td>
     
            <a href='editarusuario.php?id_usuario=<?= $linha['id_usuario'] ?>'><button id="end">Editar</button></a> 
                <a href='excluirusuario.php?id_usuario=<?= $linha['id_usuario'] ?>' 
                onclick="return confirm('Tem certeza que deseja excluir?');"><button id="remove">Excluir</button></a>
            
    </td>
</tr>
 <?php endwhile; ?>

    <?php else: ?>
        <p>Nenhum usuário encontrado.</p>
    <?php endif; ?>

    <?php if ($total_paginas > 1): ?>
        <p>Páginas:
        <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
            <?php if ($i == $pagina): ?>
                <strong><?= $i ?></strong>
            <?php else: ?>
                <a href="?pesquisa=<?= urlencode($pesquisa) ?>&pagina=<?= $i ?>&ordenar_por=<?= $ordenar_por ?>&ordem=<?= $ordem ?>">
                    <?= $i ?>
                </a>
            <?php endif; ?>
        <?php endfor; ?>
        </p>
    <?php endif; ?>

  
</div>
</body>
</html>
<?php
if (!empty($pesquisa)) {
    echo "<script>
        setTimeout(function() {
            window.location.href = window.location.pathname;
        }, 4000);
    </script>";
}
$conexao->close();
?>
