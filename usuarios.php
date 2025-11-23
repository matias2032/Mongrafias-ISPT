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
   <style>
        /* ======== GRID DE CARDS COMPACTOS ======== */
        .users-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }

        /* ======== CARD DE USUÁRIO COMPACTO ======== */
        .user-card {
            background: #ffffff;
            border-radius: 10px;
            padding: 15px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border-left: 4px solid #89b67f;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .user-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.15);
        }

        /* ======== CABEÇALHO COMPACTO ======== */
        .user-header {
            display: flex;
            align-items: center;
            gap: 12px;
            padding-bottom: 10px;
            border-bottom: 1px solid #f0f0f0;
        }

        .user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, #89b67f, #638b59);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            font-weight: bold;
            flex-shrink: 0;
        }

        .user-title {
            flex: 1;
            min-width: 0;
        }

        .user-title h4 {
            color: #333;
            font-size: 1em;
            margin: 0 0 3px 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .user-title .apelido {
            color: #89b67f;
            font-weight: 600;
            font-size: 0.85em;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* ======== INFORMAÇÕES COMPACTAS ======== */
        .user-info {
            display: flex;
            flex-direction: column;
            gap: 6px;
            font-size: 0.85em;
        }

        .info-item {
            display: flex;
            gap: 6px;
        }

        .info-label {
            font-weight: 600;
            color: #666;
            min-width: 60px;
            flex-shrink: 0;
        }

        .info-value {
            color: #333;
            word-break: break-word;
        }

        /* ======== BADGES COMPACTAS ======== */
        .user-badges {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        .badge {
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 0.75em;
            font-weight: 600;
            color: white;
        }

        .badge-perfil {
            background-color: #89b67f;
        }

        .badge-idioma {
            background-color: #ff6600;
        }

        /* ======== AÇÕES COMPACTAS ======== */
        .user-actions {
            display: flex;
            gap: 8px;
            margin-top: 8px;
            padding-top: 10px;
            border-top: 1px solid #f0f0f0;
        }

        .user-actions button {
            flex: 1;
            padding: 8px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.85em;
            cursor: pointer;
            transition: all 0.3s;
        }

        #end {
            background-color: #89b67f;
            color: white;
        }

        #end:hover {
            background-color: #6b9662;
            transform: scale(1.05);
        }

        #remove {
            background-color: #ee0000;
            color: white;
        }

        #remove:hover {
            background-color: #7e0e0e;
            transform: scale(1.05);
        }

        /* ======== ORDENAÇÃO ======== */
        .ordenacao {
            text-align: center;
            margin: 15px 0;
            color: #555;
        }

        .ordenacao a {
            color: #89b67f;
            text-decoration: none;
            font-weight: 600;
            margin: 0 8px;
            transition: color 0.3s;
        }

        .ordenacao a:hover {
            color: #638b59;
        }

        /* ======== PAGINAÇÃO ======== */
        .paginacao {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            margin-top: 30px;
            flex-wrap: wrap;
        }

        .paginacao a,
        .paginacao strong {
            padding: 8px 12px;
            border-radius: 6px;
            text-decoration: none;
            transition: all 0.3s;
            font-size: 0.9em;
        }

        .paginacao a {
            background-color: #f1f1f1;
            color: #333;
        }

        .paginacao a:hover {
            background-color: #89b67f;
            color: white;
        }

        .paginacao strong {
            background-color: #89b67f;
            color: white;
            font-weight: 600;
        }

        /* ======== MENSAGEM VAZIA ======== */
        .no-users {
            text-align: center;
            padding: 40px 20px;
            color: #777;
            font-size: 1em;
        }

        /* ======== DARK MODE ======== */
        body.dark-mode .user-card {
            background-color: #1a1a1a;
            border-left-color: #89b67f;
        }

        body.dark-mode .user-header {
            border-bottom-color: #333;
        }

        body.dark-mode .user-title h4 {
            color: #fff;
        }

        body.dark-mode .info-label {
            color: #ccc;
        }

        body.dark-mode .info-value {
            color: #fff;
        }

        body.dark-mode .user-actions {
            border-top-color: #333;
        }

        /* ======== RESPONSIVIDADE ======== */
        @media (max-width: 768px) {
            .users-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .user-info {
                font-size: 0.8em;
            }

            .info-item {
                flex-direction: column;
                gap: 2px;
            }

            .info-label {
                min-width: auto;
            }
        }

        @media (min-width: 769px) and (max-width: 1024px) {
            .users-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (min-width: 1025px) {
            .users-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (min-width: 1400px) {
            .users-grid {
                grid-template-columns: repeat(4, 1fr);
            }
        }
    </style>
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
          <button type="button" onclick="limparFiltros()">Limpar Filtros</button>
    </form>

    <div class="ordenacao">
        <p>Ordenar por:
            <a href="?pesquisa=<?= urlencode($pesquisa) ?>&ordenar_por=nome&ordem=<?= ($ordenar_por == 'nome' && $ordem == 'ASC') ? 'desc' : 'asc'; ?>">
                Nome <?= ($ordenar_por == 'nome') ? ($ordem == 'ASC' ? '↑' : '↓') : '' ?>
            </a> |
            <a href="?pesquisa=<?= urlencode($pesquisa) ?>&ordenar_por=apelido&ordem=<?= ($ordenar_por == 'apelido' && $ordem == 'ASC') ? 'desc' : 'asc'; ?>">
                Apelido <?= ($ordenar_por == 'apelido') ? ($ordem == 'ASC' ? '↑' : '↓') : '' ?>
            </a>
        </p>
    </div>

    <h3>Total de Usuários Encontrados: <?= $total_registros ?></h3>

    <?php if ($resultado->num_rows > 0): ?>
        <div class="users-grid">
            <?php while ($linha = $resultado->fetch_assoc()): 
                // Gerar iniciais
                $nomeCompleto = $linha['nome'];
                $palavras = explode(' ', $nomeCompleto);
                $iniciais = '';
                foreach ($palavras as $palavra) {
                    if (!empty($palavra)) {
                        $iniciais .= strtoupper(substr($palavra, 0, 1));
                    }
                }
                $iniciais = substr($iniciais, 0, 2);
                
                // Gerar cor do avatar
                $cores = ['#89b67f', '#ff6600', '#3498db', '#e74c3c', '#9b59b6', '#1abc9c', '#f39c12', '#2ecc71'];
                $corAvatar = $cores[ord($nomeCompleto[0]) % count($cores)];
            ?>
            
            <div class="user-card">
                <div class="user-header">
                    <div class="user-avatar" style="background: <?= $corAvatar ?>">
                        <?= $iniciais ?>
                    </div>
                    <div class="user-title">
                        <h4><?= htmlspecialchars($linha['nome']) ?></h4>
                        <div class="apelido">@<?= htmlspecialchars($linha['apelido']) ?></div>
                    </div>
                </div>

                <div class="user-info">
                    <div class="info-item">
                        <span class="info-label">Telefone:</span>
                        <span class="info-value"><?= htmlspecialchars($linha['telefone']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Email:</span>
                        <span class="info-value"><?= htmlspecialchars($linha['email']) ?></span>
                    </div>
                </div>

                <div class="user-badges">
                    <span class="badge badge-perfil"><?= htmlspecialchars($linha['nome_perfil']) ?></span>
                    <span class="badge badge-idioma"><?= htmlspecialchars($linha['nome_idioma']) ?></span>
                </div>

                <div class="user-actions">
                    <a href='editarusuario.php?id_usuario=<?= $linha['id_usuario'] ?>'>
                        <button id="end">Editar</button>
                    </a>
                    <a href='excluirusuario.php?id_usuario=<?= $linha['id_usuario'] ?>' 
                       onclick="return confirm('Tem certeza que deseja excluir?');">
                        <button id="remove">Excluir</button>
                    </a>
                </div>
            </div>
            
            <?php endwhile; ?>
        </div>

        <?php if ($total_paginas > 1): ?>
            <div class="paginacao">
                <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                    <?php if ($i == $pagina): ?>
                        <strong><?= $i ?></strong>
                    <?php else: ?>
                        <a href="?pesquisa=<?= urlencode($pesquisa) ?>&pagina=<?= $i ?>&ordenar_por=<?= $ordenar_por ?>&ordem=<?= $ordem ?>">
                            <?= $i ?>
                        </a>
                    <?php endif; ?>
                <?php endfor; ?>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <div class="no-users">
            <p>Nenhum usuário encontrado.</p>
        </div>
    <?php endif; ?>

    <script>
            // Função para limpar todos os filtros
    function limparFiltros() {
        window.location.href = window.location.pathname;
    }
    </script>
</div>
</body>
</html>
