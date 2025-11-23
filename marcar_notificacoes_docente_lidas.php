<?php
// Inclui o arquivo de conexão com o banco de dados
include "conexao.php";
// Inclui o arquivo de verificação de login para garantir que apenas usuários autenticados possam acessar
include "verifica_login.php";

// Define o cabeçalho para que a resposta seja interpretada como JSON
header('Content-Type: application/json');

// Resgata as informações do usuário logado da sessão
$usuarioLogado = $_SESSION['usuario'];
$idUsuario = $usuarioLogado['id_usuario'];

// Verifica se o usuário tem o perfil de docente (idperfil = 2)
// Se não for, ou se não estiver logado, retorna um erro e encerra o script.
if (!isset($usuarioLogado) || $usuarioLogado['id_perfil'] != 2) {
    http_response_code(403); // Acesso negado
    echo json_encode(['status' => 'error', 'message' => 'Acesso negado. Apenas docentes podem acessar este recurso.']);
    exit;
}

// O script deve ser executado apenas via requisição POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ✅ Recebe o ID da notificação via POST
    $id_Notificacao = $_POST['id_notificacao'] ?? null;

    if ($id_Notificacao === null) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'ID da notificação não fornecido.']);
        exit;
    }

    try {
        // ✅ Prepara a consulta SQL para atualizar o status de UMA notificação específica
        $stmt = $conexao->prepare("
            UPDATE notificacao 
            SET lida = 1 
            WHERE id_notificacao = ? AND id_usuario = ?
        ");
        
        // Vincula o ID da notificação e o ID do usuário como parâmetros
        $stmt->bind_param("ii", $id_Notificacao, $idUsuario);
        
        // Executa a atualização
        $stmt->execute();

        // Verifica se a operação foi bem-sucedida
        if ($stmt->affected_rows > 0) {
            echo json_encode(['status' => 'success', 'message' => 'Notificação marcada como lida com sucesso.']);
        } else {
            // Se nenhuma linha foi afetada, pode ser que a notificação já estivesse lida
            echo json_encode(['status' => 'success', 'message' => 'Notificação já estava marcada como lida ou não encontrada.']);
        }
        
    } catch (Exception $e) {
        // Em caso de erro, retorna uma resposta de erro no formato JSON
        http_response_code(500); // Erro interno do servidor
        echo json_encode(['status' => 'error', 'message' => 'Erro ao processar a requisição: ' . $e->getMessage()]);
    } finally {
        // Garante que o statement e a conexão sejam fechados
        if (isset($stmt)) {
            $stmt->close();
        }
        $conexao->close();
    }
} else {
    // Se a requisição não for POST, retorna um erro
    http_response_code(405); // Método não permitido
    echo json_encode(['status' => 'error', 'message' => 'Método não permitido.']);
}
?>