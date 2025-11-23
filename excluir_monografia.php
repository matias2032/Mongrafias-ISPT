<?php
include "conexao.php";
include "verifica_login.php";
include "info_usuário.php";

if (!isset($_GET['id_monografia']) || empty($_GET['id_monografia'])) {
    die("ID do exame não informado.");
}

$idexame = intval($_GET['id_monografia']);

// 🔄 Passo 2: Exclui uploads relacionados
$stmtUpload = $conexao->prepare("DELETE FROM upload WHERE id_monografia = ?");
$stmtUpload->bind_param("i", $idexame);
$stmtUpload->execute();

// 🔄 Passo 3: Exclui o próprio exame
$stmtExame = $conexao->prepare("DELETE FROM monografia WHERE id_monografia = ?");
$stmtExame->bind_param("i", $idexame);
$stmtExame->execute();

$mensagem = "";
$tipo_mensagem = "";
$redirecionar = false;

if ($stmtExame->affected_rows > 0) {
    $mensagem = "Monografia excluída com sucesso! Redirecionando...";
    $tipo_mensagem = "success";
    $redirecionar = true;
} else {
    $mensagem = "Monografia não encontrada ou já foi excluída. Redirecionando...";
    $tipo_mensagem = "error";
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Exclusão de Exame</title>
</head>
<body>
       <?php if ($mensagem): ?>
      <div class="mensagem <?= htmlspecialchars($tipo_mensagem) ?>"><?= htmlspecialchars($mensagem) ?></div>
    <?php endif; ?>

<?php if ($redirecionar): ?>
<script>
    // Redireciona em 3 segundos
    setTimeout(() => {
        window.location.href = 'gerenciar_monografias.php';
    }, 3000);
</script>
<?php endif; ?>
</body>
</html>
<?php
$conexao->close();
?>
