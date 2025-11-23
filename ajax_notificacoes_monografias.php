<?php
include "conexao.php";
session_start();

header('Content-Type: application/json');

// 1. Verificar se o Administrador está logado
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['id_perfil'] != 1) {
    echo json_encode(["novas" => 0, "novas_monografias" => []]);
    exit;
}

$id_admin_logado = $_SESSION['usuario']['id_usuario'];

// 2. Consulta Atualizada
// n.id_usuario = ID do Destinatário (Admin)
// u.id_usuario (JOIN via upload) = ID do Remetente (Funcionário)

$sql = "SELECT 
            n.id_notificacao, 
            m.tema, 
            m.nome_estudante, 
            m.apelido_estudante,
            u_remetente.nome AS nome_funcionario
        FROM 
            notificacao n
        JOIN 
            monografia m ON n.id_monografia = m.id_monografia
        JOIN 
            upload up ON m.id_monografia = up.id_monografia
        JOIN 
            usuario u_remetente ON up.id_usuario = u_remetente.id_usuario
        WHERE 
            n.lida = 0 
            AND n.tipo = 'submissao_pendente'
            AND n.id_usuario = ?  
        ORDER BY 
            n.data_criacao DESC";

$stmt = $conexao->prepare($sql);
$stmt->bind_param("i", $id_admin_logado);
$stmt->execute();
$res = $stmt->get_result();

$notificacoes = [];
while ($row = $res->fetch_assoc()) {
    $notificacoes[] = $row;
}

echo json_encode([
    "novas" => count($notificacoes),
    "novas_monografias" => $notificacoes
]);

$stmt->close();

// Nota: A correção sugerida anteriormente para ajax_marcar_lida.php
// (filtrar pelo id_usuario logado) também deve ser aplicada para manter a consistência.
?>