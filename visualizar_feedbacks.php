<?php

include "conexao.php";
include "verifica_login.php";


$usuario = $_SESSION['usuario'];

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

// ----------------------------------------------------------------------
// LÓGICA AJAX PARA CARREGAMENTO DINÂMICO DOS FEEDBACKS
// ----------------------------------------------------------------------

if (isset($_GET['ajax']) && $_GET['ajax'] == 'carregar_feedback') {
    if (!isset($_GET['id_monografia'])) {
        echo json_encode(['status' => 'error', 'message' => 'ID da monografia não fornecido.']);
        exit;
    }

    $id_monografia = $_GET['id_monografia'];

    // Consulta para buscar todos os feedbacks para a monografia específica
    $sql = "SELECT
                f.texto_feedback,
                f.data_envio,
                u.nome AS nome_usuario,
                u.apelido AS apelido_usuario,
                p.nome_perfil
            FROM
                feedback f
            LEFT JOIN
                usuario u ON f.id_usuario = u.id_usuario
            LEFT JOIN
                perfil p ON u.id_perfil = p.id_perfil
            WHERE
                f.id_monografia = ?
            ORDER BY
                f.data_envio DESC";

    $stmt = $conexao->prepare($sql);
    if ($stmt === false) {
        // Retorna JSON em caso de erro na preparação
        echo json_encode(['status' => 'error', 'message' => 'Erro na preparação da consulta: ' . htmlspecialchars($conexao->error)]);
        exit;
    }
    
    $stmt->bind_param("i", $id_monografia);
    $stmt->execute();
    $result = $stmt->get_result();

    $feedbacks = [];
    while ($row = $result->fetch_assoc()) {
        $feedbacks[] = [
            'texto' => htmlspecialchars($row['texto_feedback']),
            'data' => date('d/m/Y H:i', strtotime($row['data_envio'])),
            'autor' => htmlspecialchars($row['nome_usuario'] . ' ' . $row['apelido_usuario'] . ' (' . $row['nome_perfil'] . ')')
        ];
    }
    
    // Retorna a lista de feedbacks em formato JSON
    echo json_encode(['status' => 'success', 'feedbacks' => $feedbacks]);
    exit;
}


// ----------------------------------------------------------------------
// CONSULTA PRINCIPAL PARA LISTAR AS MONOGRAFIAS (Simplificada)
// ----------------------------------------------------------------------

// Usamos a view 'vw_monografia_detalhe' criada anteriormente ou fazemos a JOIN.
// Vamos usar uma JOIN similar à do seu arquivo original para garantir compatibilidade.
$sql = "SELECT
    m.id_monografia,
    m.tema,
    m.nome_estudante,
    m.apelido_estudante,
    u.nome AS nome_supervisor,
    u.apelido AS apelido_supervisor,
    c.nome_curso,
    (SELECT COUNT(*) FROM feedback f WHERE f.id_monografia = m.id_monografia) AS total_feedbacks
FROM monografia m
INNER JOIN curso c ON m.id_curso = c.id_curso
INNER JOIN usuario u ON m.id_supervisor = u.id_usuario
ORDER BY m.data_submissao DESC";

$resultado = $conexao->query($sql);
$quantidade = $resultado->num_rows;

?>
<?php include "info_usuario.php";?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"  content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">

    <title>Visualizar Monografias e Feedbacks</title>
    <script src="logout_auto.js"></script>
    <script src="js/darkmode2.js"></script>
    <link rel="stylesheet" href="css/admin.css">
    <script src="js/sidebar.js"></script>
      <script src="js/dropdown2.js"></script>
   
</head>
<body>
    <button class="menu-btn">☰</button>

<!-- Overlay -->
<div class="sidebar-overlay"></div>

    <sidebar class="sidebar">
        <h2>Menu Admin</h2>
        <a href="dashboard.php">Voltar ao Menu Principal</a>


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

    <div class="content">
        <div class="main">
            <h1>Visualizar Monografias e Feedbacks</h1>
            <div class="count">
                <?php if (isset($quantidade)) echo "$quantidade monografia(s) encontrada(s)."; ?>
            </div>

            <?php if ($resultado->num_rows > 0): ?>
                <div class="cards-container">
                    <?php while ($linha = $resultado->fetch_assoc()): 
                        $nome_supervisor_display = $linha['nome_supervisor'];
                        if (!empty($linha['apelido_supervisor'])) {
                            $nome_supervisor_display .= ' (' . $linha['apelido_supervisor'] . ')';
                        }
                    ?>
                        <div class="card">
                            <div class="exam-card status-aprovado">
                                <div class="card-content">
                                    <div class="card-title">
                                        <?= htmlspecialchars($linha['tema']) ?>
                                    </div>
                                    <div class="card-details">
                                        <div class="card-student">
                                            <strong style="color: var(--text-color-dark);">Estudante:</strong>
                                            <?= htmlspecialchars($linha['nome_estudante'] . ' ' . $linha['apelido_estudante']) ?>
                                        </div>
                                        <p><strong>Curso:</strong> <?= htmlspecialchars($linha['nome_curso']) ?></p>
                                        <p><strong>Supervisor:</strong> <?= htmlspecialchars($nome_supervisor_display) ?></p>
                                    </div>
                                </div>

                                <div class="card-actions">
                                    <button 
                                        type="button" 
                                        class="feedback-button" 
                                        onclick="abrirModalFeedback(<?= $linha['id_monografia'] ?>, '<?= htmlspecialchars($linha['tema'], ENT_QUOTES) ?>')">
                                        Ver Feedback (<?= $linha['total_feedbacks'] ?>)
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <p style="text-align: center;">Nenhuma monografia encontrada.</p>
            <?php endif; ?>
        </div>
    </div>

    <div id="feedbackModal" class="modal">
        <div class="modal-content">
            <span class="close-btn">&times;</span>
            <h2 id="modalTitle">Feedbacks da Monografia</h2>
            <div id="feedbackList">
                <p>Carregando feedbacks...</p>
            </div>
        </div>
    </div>


<script>
    // URL base para requisições AJAX
    const BASE_URL = '?ajax=';
    const modal = document.getElementById('feedbackModal');
    const closeBtn = document.querySelector('.close-btn');
    const feedbackList = document.getElementById('feedbackList');
    const modalTitle = document.getElementById('modalTitle');

    // Função para abrir o modal e carregar os feedbacks
    function abrirModalFeedback(idMonografia, tema) {
        modalTitle.textContent = `Feedbacks da Monografia: ${tema}`;
        feedbackList.innerHTML = '<p>Carregando feedbacks...</p>';
        modal.style.display = 'block';

        fetch(`${BASE_URL}carregar_feedback&id_monografia=${idMonografia}`)
            .then(res => res.json())
            .then(data => {
                feedbackList.innerHTML = ''; // Limpa a mensagem de carregamento

                if (data.status === 'error') {
                    feedbackList.innerHTML = `<p style="color: red;">Erro: ${data.message}</p>`;
                    return;
                }

                if (data.feedbacks.length === 0) {
                    feedbackList.innerHTML = '<p>Não foram encontrados feedbacks para esta monografia.</p>';
                    return;
                }

                // Cria os elementos HTML para cada feedback
                data.feedbacks.forEach(feedback => {
                    const item = document.createElement('div');
                    item.className = 'feedback-item';
                    
                    const text = document.createElement('p');
                    text.className = 'feedback-text';
                    text.textContent = feedback.texto;
                    
                    const meta = document.createElement('span');
                    meta.className = 'feedback-meta';
                    meta.textContent = `Enviado por: ${feedback.autor} em ${feedback.data}`;

                    item.appendChild(text);
                    item.appendChild(meta);
                    feedbackList.appendChild(item);
                });
            })
            .catch(error => {
                console.error('Erro ao carregar feedbacks:', error);
                feedbackList.innerHTML = '<p style="color: red;">Ocorreu um erro ao comunicar com o servidor.</p>';
            });
    }

    // Função para fechar o modal
    function fecharModal() {
        modal.style.display = 'none';
    }

    // Event listeners para fechar o modal
    closeBtn.onclick = fecharModal;

    window.onclick = function(event) {
        if (event.target == modal) {
            fecharModal();
        }
    }

    // Lógica de inicialização (mantendo darkmode e logout)
    document.addEventListener("DOMContentLoaded", () => {
        // Inicialização de Dark Mode (Seus scripts)
        // carregarCursos(); // Não é necessário para esta página, pois não há filtros
    });

</script>
</body>
</html>