<?php
include "conexao.php";
include "verifica_login.php";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['monografias_ids']) && is_array($_POST['monografias_ids'])) {
    $idsParaExcluir = $_POST['monografias_ids'];

    // Iniciar uma transação para garantir a integridade dos dados
    $conexao->begin_transaction();

    try {
        // Preparar as instruções de exclusão fora do loop
        // Você precisa de uma instrução DELETE para cada tabela filha
        $stmtUpload = $conexao->prepare("DELETE FROM upload WHERE id_monografia = ?");
               $stmtCliques = $conexao->prepare("DELETE FROM cliques_download WHERE id_monografia = ?");
        $stmtfeedback = $conexao->prepare("DELETE FROM feedback WHERE id_monografia = ?");
        $stmtmonografia = $conexao->prepare("DELETE FROM monografia WHERE id_monografia = ?");

        foreach ($idsParaExcluir as $id_monografia) {
            // Excluir registros das tabelas filhas primeiro
            $stmtUpload->bind_param("i", $id_monografia);
            $stmtUpload->execute();

            $stmtfeedback->bind_param("i", $id_monografia);
            $stmtfeedback->execute();

            $stmtCliques->bind_param("i", $id_monografia);
            $stmtCliques->execute();

            // Depois, excluir o registro da tabela exame
            $stmtmonografia->bind_param("i", $id_monografia);
            $stmtmonografia->execute();
        }

        // Confirmar a transação
        $conexao->commit();

        echo "<script>alert('Monografias selecionadas excluídas com sucesso!'); window.location.href='gerenciar_monografias.php';</script>";
        exit();

    } catch (mysqli_sql_exception $e) {
        // Em caso de erro, reverter todas as operações
        $conexao->rollback();
        echo "<script>alert('Erro ao excluir monografias: " . $e->getMessage() . "'); window.location.href='gerenciar_monografias.php';</script>";
        exit();
    } finally {
        // Fechar as declarações preparadas
        if (isset($stmtUpload)) $stmtUpload->close();
        if (isset($stmtfeedback)) $stmtfeedback->close();
        if (isset($stmtCliques)) $stmtCliques->close();
        if (isset($stmtmonografia)) $stmtmonografia->close();
        $conexao->close();
    }
} else {
    echo "<script>alert('Nenhuma monografias foi selecionada para exclusão.'); window.location.href='gerenciar_monografias.php';</script>";
    exit();
}
?>