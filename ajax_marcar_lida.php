<?php
include "conexao.php";
include "verifica_login.php"; // Garante que apenas usuários logados possam fazer isso

header('Content-Type: application/json');

if (!isset($_SESSION['usuario'])) {
    http_response_code(401); // Unauthorized
    echo json_encode(['sucesso' => false, 'erro' => 'Usuário não autenticado.']);
    exit;
}

$id_usuario_logado = $_SESSION['usuario']['id_usuario'];

if ($_SESSION['usuario']['id_perfil'] != 1) {
    // Apenas Administradores (id_perfil = 1) podem marcar este tipo de notificação como lida
    http_response_code(403); // Forbidden
    echo json_encode(['sucesso' => false, 'erro' => 'Permissão negada.']);
    exit;
}

// ALTERAÇÃO: Adicionar WHERE n.id_usuario = ? para filtrar apenas as notificações do Admin logado.
$sql = "UPDATE notificacao SET lida = 1 
        WHERE tipo = 'submissao_pendente' 
        AND lida = 0
        AND id_usuario = ?"; 

$stmt = $conexao->prepare($sql);
$stmt->bind_param("i", $id_usuario_logado);

if ($stmt->execute()) {
    echo json_encode(['sucesso' => true, 'linhas_afetadas' => $stmt->affected_rows]);
} else {
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'erro' => $conexao->error]);
}
$stmt->close();
?>