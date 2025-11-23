<?php
include "conexao.php";
include "verifica_login.php";
include "usuario_logica.php";


$usuario = $_SESSION['usuario'];
// Consulta os logs com nome do usuário
$sql = "
SELECT logs.idlog, logs.data_log,  CONCAT(usuario.nome, ' ', usuario.apelido) AS nome_completo 
FROM logs 
INNER JOIN usuario ON logs.idusuario = usuario.idusuario
ORDER BY logs.data_log DESC
";

$result = $conexao->query($sql);
?>
<?php include "info_usuário.php"; ?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Registros de Log</title>
       <script src="logout_auto.js"></script>
            <script src="js/darkmode2.js"></script>  
                <link rel="stylesheet" href="css/admin.css">
 
</head>
<body>
<!-- Saudação-->


<sidebar class="sidebar">
    
        <h2>Menu Admin</h2>
        <a href="dashboard.php">Voltar ao Menu Principal</a>
          <a href="logout.php">Sair</a>
                   <div class="sidebar-footer">
        <a href="logout.php" title="Sair"><img id="iconelogout" src="icones/logout1.png" alt="Logout"></a>
        <img class="dark-toggle" id="darkToggle" src="icones/lua.png" alt="Modo Escuro" title="Alternar modo escuro">
      </div>

          
</sidebar>

<div class="main">



   <h2>Histórico de Ações dos Usuários</h2>
    
    


    <table>
        <tr>
            <th>ID do Log</th>
            <th>Data e Hora</th>
            <th>Usuário</th>
        </tr>

        <?php
        if ($result->num_rows > 0) {
            while($linha = $result->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . $linha["idlog"] . "</td>";
                echo "<td>" . $linha["data_log"] . "</td>";
                echo "<td>" . htmlspecialchars($linha["nome_completo" ]) . "</td>";
                echo "</tr>";
            }
        } else {
            echo "<tr><td colspan='3'>Nenhum log encontrado.</td></tr>";
        }
        ?>

    </table>
  
</div>

</body>
</html>

<?php
$conexao->close();
?>
