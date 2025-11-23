<?php

include "conexao.php";
include "verifica_login.php"; 


header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['usuario'])) {
header("Location: login.php");
exit;
}
?>
<?php include "info_usuario.php"; ?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
<meta name="viewport"  content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">

    <title>Dashboard</title>
   <script src="logout_auto.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="css/admin.css">
     <script src="js/darkmode2.js"></script>
<script src="js/sidebar.js"></script>
   
</head>
<body>
    
    <button class="menu-btn">☰</button>

<!-- Overlay -->
<div class="sidebar-overlay"></div>
    
    <sidebar class="sidebar">
          <br><br>
            <a href="usuarios.php">Gerenciar Usuários</a>
            <a href="gerenciar_monografias.php">Gerenciar Monografias</a>
<a href="visualizar_feedbacks.php">Ver Feedback</a>
<a href="gerenciar_banner.php">Gerenciar Banners</a>
                <a href="historico_uploads_admin.php" id="link-historico-monografias">Ver histórico de Uploads
                  <div class="notification-container">
                    <i class="fa-solid fa-bell"></i>
                    <span class="notification-count" id="notification-count">0</span>
                </div>
                    </a>

                                        <div class="sidebar-footer">
        <a href="logout.php" title="Sair"><img id="iconelogout" src="icones/logout1.png" alt="Logout"></a>
        <img class="dark-toggle" id="darkToggle" src="icones/lua.png" alt="Modo Escuro" title="Alternar modo escuro">
      </div>

    </sidebar>

   
<script>

// Variável para armazenar o ID do intervalo, para que possamos limpá-lo se necessário
let notificationInterval;

// Função que marca as notificações como lidas e redireciona
function markAsReadAndRedirect() {
    // 1. Limpa o intervalo
    clearInterval(notificationInterval); 
    
    // 2. Limpa os popups visuais
    document.querySelectorAll('.toast-notificacao').forEach(toast => toast.remove());

    // 3. Marca como lido e redireciona (copiado do listener do sidebar)
    fetch("ajax_marcar_lida.php")
        .then(res => res.json())
        .then(data => {
            if (data.sucesso) {
                fetchMonografias(); 
            } else {
                console.error("Erro do servidor ao marcar notificações:", data.erro);
            }
        })
        .catch(error => {
            console.error("Erro na comunicação para marcar como lida:", error);
        })
        .finally(() => {
            window.location.href = 'historico_uploads_admin.php';
        });
}

// Função para buscar novas notificações de monografias
function fetchMonografias() {
    fetch("ajax_notificacoes_monografias.php")
        .then(response => {
            if (!response.ok) {
                throw new Error("Erro ao carregar notificações");
            }
            return response.json();
        })
        .then(data => {
            const badge = document.getElementById("notification-count");
            
            // Limpa todos os popups existentes antes de criar novos
            document.querySelectorAll('.toast-notificacao').forEach(toast => toast.remove());

            if (data.novas > 0) {
                // Atualiza o contador (badge)
                if (badge) {
                    badge.style.display = "inline-block";
                    badge.textContent = data.novas;
                }
                
                // Exibe toast de notificação para cada nova monografia
                data.novas_monografias.forEach(monografia => {
                    const toast = document.createElement("div");
                    toast.className = "toast-notificacao";
                    
                    // Adicionar evento para fechar o toast se for clicado fora do botão
                    // toast.onclick = function() {
                    //    this.remove(); 
                    // };

                    toast.innerHTML = `
                        <strong>Nova Monografia Cadastrada</strong><br>
                        <small><b>Tema:</b> ${monografia.tema}</small><br>
                        <small><b>Estudante:</b> ${monografia.nome_estudante} ${monografia.apelido_estudante}</small><br>
                        <small><b>Submetida por:</b> ${monografia.nome_funcionario}</small><br>
                        <button class="btn-toast" onclick="markAsReadAndRedirect();">
                            Ver Submissão
                        </button>
                    `;
                    document.body.appendChild(toast);
                });
            } else {
                // Limpar contador se não houver notificações
                if (badge) {
                    badge.textContent = "0";
                    badge.style.display = "none";
                }
            }
        })
        .catch(error => {
            console.error("Erro ao buscar notificações de monografias:", error);
        });
}

// Inicia busca imediata e repete a cada 10 segundos
fetchMonografias();
notificationInterval = setInterval(fetchMonografias, 10000); // Salva o ID do intervalo

// ==============================
// 🔕 RESETAR CONTADOR E MARCAR COMO LIDA AO ABRIR HISTÓRICO
// ==============================
document.addEventListener("DOMContentLoaded", () => {
    const historicoLink = document.getElementById("link-historico-monografias");
    
    if (historicoLink) {
        historicoLink.addEventListener("click", (e) => {
            e.preventDefault(); 
            markAsReadAndRedirect(); // Usa a função unificada
        });
    }
});

</script>
</body>
</html>