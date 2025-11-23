<?php
include "conexao.php";

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}
// ----------------------------------------------------------------------
// 1. Verificação e Sanitização
// ----------------------------------------------------------------------
if (!isset($_GET['id_usuario']) || !is_numeric($_GET['id_usuario'])) {
    die("ID do usuário inválido ou não informado.");
}

$id_usuario = (int)$_GET['id_usuario'];

// Inicia uma transação para garantir que TUDO seja deletado ou NADA seja deletado
$conexao->begin_transaction();
$exclusao_bem_sucedida = false;

try {
    // ----------------------------------------------------------------------
    // 2. EXCLUSÃO DE DEPENDÊNCIAS (CRÍTICO DEVIDO ÀS FOREIGN KEYS)
    // Se o usuário tiver registros em outras tabelas que referenciam seu ID,
    // o MySQL bloqueará o DELETE, a menos que deletemos os registros dependentes primeiro.
    // Usamos prepared statements para cada DELETE.
    // ----------------------------------------------------------------------
    $dependent_tables = [
        'cliques_download', 'logs', 'feedback', 'notificacao', 
        'recuperacao_senha', 'tokens_redefinicao', 'upload', 'supervisor'
    ];

    foreach ($dependent_tables as $table) {
        $sql = "DELETE FROM {$table} WHERE id_usuario = ?";
        $stmt_dep = $conexao->prepare($sql);
        if ($stmt_dep === false) {
             throw new Exception("Erro na preparação da exclusão da tabela {$table}: " . $conexao->error);
        }
        $stmt_dep->bind_param("i", $id_usuario);
        $stmt_dep->execute();
        $stmt_dep->close();
    }
    
    // ----------------------------------------------------------------------
    // 3. EXCLUSÃO PRINCIPAL (Tabela 'usuario')
    // ----------------------------------------------------------------------
    $sql_user = "DELETE FROM usuario WHERE id_usuario = ?";
    $stmt_user = $conexao->prepare($sql_user);

    if ($stmt_user === false) {
        throw new Exception("Erro na preparação da exclusão do usuário: " . $conexao->error);
    }
    
    $stmt_user->bind_param("i", $id_usuario);
    $stmt_user->execute();

    if ($stmt_user->affected_rows > 0) {
        // Se a exclusão do usuário for bem-sucedida, confirma todas as exclusões dependentes
        $conexao->commit();
        $exclusao_bem_sucedida = true;
    } else {
        // Se o usuário não foi encontrado (0 linhas afetadas), desfaz tudo
        $conexao->rollback();
    }
    
    $stmt_user->close();

} catch (Exception $e) {
    // Em caso de qualquer erro (preparação, execução), desfaz a transação
    $conexao->rollback();
    die("❌ Erro fatal durante a exclusão: " . $e->getMessage());
}

// ----------------------------------------------------------------------
// 4. Feedback e Redirecionamento
// ----------------------------------------------------------------------
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Exclusão de Usuário</title>
</head>
<body>

<?php
if ($exclusao_bem_sucedida) {
    echo "<h1>✅ Usuário excluído com sucesso!</h1>";
    echo "<p>Redirecionando para a lista de usuários...</p>";
    ?>
    <script>
        // Redireciona em 3 segundos
        setTimeout(() => {
            window.location.href = 'usuarios.php';
        }, 3000);
    </script>
    <?php
} else {
    echo "<h1>⚠️ Usuário não encontrado ou já foi excluído.</h1>";
    echo "<a href='usuarios.php'>Voltar para a lista de usuários</a>";
}

$conexao->close();
?>

</body>
</html>