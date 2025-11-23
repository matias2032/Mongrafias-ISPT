<?php

include "conexao.php";
include "verifica_login.php";
include "info_usuario.php";

$usuario = $_SESSION['usuario'];

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

// ----------------------------------------------------------------------
// LÓGICA AJAX PARA CARREGAMENTO DINÂMICO DOS SELECTS (SEM ALTERAÇÃO)
// ----------------------------------------------------------------------

// 1. Função para carregar CURSOS via DIVISÃO
if (isset($_GET['ajax']) && $_GET['ajax'] == 'cursos') {
    if (!isset($_GET['divisao'])) {
        exit;
    }

    $id_divisao = $_GET['divisao'];

    $sql = "SELECT id_curso, nome_curso FROM curso WHERE id_divisao = ?";
    $stmt = $conexao->prepare($sql);
    $stmt->bind_param("i", $id_divisao);
    $stmt->execute();

    $result = $stmt->get_result();

    echo '<option value="">Curso</option>';
    while ($row = $result->fetch_assoc()) {
        echo '<option value="' . $row['id_curso'] . '">' . $row['nome_curso'] . '</option>';
    }
    exit;
}

// 2. Função para carregar ÁREAS DE PESQUISA via CURSO
if (isset($_GET['ajax']) && $_GET['ajax'] == 'areas_pesquisa') {
    if (!isset($_GET['curso'])) {
        exit;
    }

    $id_curso = $_GET['curso'];

    $sql = "SELECT id_area_pesquisa, nome_area_pesquisa FROM vw_curso_area_pesquisa WHERE id_curso = ?";
    $stmt = $conexao->prepare($sql);
    $stmt->bind_param("i", $id_curso);
    $stmt->execute();

    $result = $stmt->get_result();

    echo '<option value="">Área de Pesquisa</option>';
    while ($row = $result->fetch_assoc()) {
        echo '<option value="' . $row['id_area_pesquisa'] . '">' . $row['nome_area_pesquisa'] . '</option>';
    }
    exit;
}

// ----------------------------------------------------------------------
// PREENCHIMENTO DOS SELECTS INICIAIS (FULL LOAD)
// ----------------------------------------------------------------------
$cursos_full = $conexao->query("SELECT id_curso, nome_curso FROM curso");
$anos_submissao = $conexao->query("SELECT id_ano_submissao, ano FROM ano_submissao ORDER BY ano DESC");
$divisoes = $conexao->query("SELECT id_divisao, nome_divisao FROM divisao ORDER BY nome_divisao");
$supervisores = $conexao->query("SELECT id_usuario, nome, apelido FROM usuario WHERE id_perfil = 4 ORDER BY nome");


$filtros = [];
$tipos_bind = ""; // Variável para armazenar os tipos de bind (i, s, etc.)

// ----------------------------------------------------------------------
// CONSULTA PRINCIPAL DE BUSCA (INCLUINDO DESTAQUE)
// ----------------------------------------------------------------------

$sql = "SELECT
    m.id_monografia,
    m.tema,
    m.nome_estudante,
    m.apelido_estudante,
    u.nome AS nome_supervisor,
    u.apelido AS apelido_supervisor,
    'aprovado' AS status_exame,
    d.nome_divisao,
    c.nome_curso,
    ap.nome_area_pesquisa,
    ans.ano,
    p.nome_periodo,
    m.caminho_arquivo as link_arquivo,
    m.destaque                           -- NOVO: Campo destaque
FROM monografia m
INNER JOIN divisao d ON m.id_divisao = d.id_divisao
INNER JOIN curso c ON m.id_curso = c.id_curso
INNER JOIN area_pesquisa ap ON m.id_area_pesquisa = ap.id_area_pesquisa
INNER JOIN ano_submissao ans ON m.id_ano_submissao = ans.id_ano_submissao
INNER JOIN usuario u ON m.id_supervisor = u.id_usuario
INNER JOIN periodo p ON m.id_periodo = p.id_periodo
WHERE 1=1";


// 1. Filtros de SELECT (FKs)
if (!empty($_GET['divisao'])) {
    $sql .= " AND m.id_divisao = ?";
    $tipos_bind .= "i";
    $filtros[] = $_GET['divisao'];
}

if (!empty($_GET['curso'])) {
    $sql .= " AND m.id_curso = ?";
    $tipos_bind .= "i";
    $filtros[] = $_GET['curso'];
}

if (!empty($_GET['area_pesquisa'])) {
    $sql .= " AND m.id_area_pesquisa = ?";
    $tipos_bind .= "i";
    $filtros[] = $_GET['area_pesquisa'];
}

if (!empty($_GET['ano_submissao'])) {
    $sql .= " AND m.id_ano_submissao = ?";
    $tipos_bind .= "i";
    $filtros[] = $_GET['ano_submissao'];
}

if (!empty($_GET['supervisor'])) {
    $sql .= " AND m.id_supervisor = ?";
    $tipos_bind .= "i";
    $filtros[] = $_GET['supervisor'];
}

if (!empty($_GET['periodo'])) {
    $sql .= " AND m.id_periodo = ?";
    $tipos_bind .= "i";
    $filtros[] = $_GET['periodo'];
}


// [NOVO FILTRO] Filtro de Destaque
if (isset($_GET['destaque_filtro']) && $_GET['destaque_filtro'] !== '') {
    $destaque_filtro = intval($_GET['destaque_filtro']);
    $sql .= " AND m.destaque = ?";
    $tipos_bind .= "i";
    $filtros[] = $destaque_filtro;
}


// 2. Filtros de TEXTO (Estudante e Tema)
if (!empty($_GET['estudante'])) {
    $estudante = "%" . trim($_GET['estudante']) . "%";
    $sql .= " AND (m.nome_estudante LIKE ? OR m.apelido_estudante LIKE ?)";
    $tipos_bind .= "ss";
    $filtros[] = $estudante;
    $filtros[] = $estudante;
}

if (!empty($_GET['tema_projeto'])) {
    $tema_projeto = "%" . trim($_GET['tema_projeto']) . "%";
    $sql .= " AND m.tema LIKE ?";
    $tipos_bind .= "s";
    $filtros[] = $tema_projeto;
}


// Ordem: Destaques primeiro, depois por data
$sql .= " ORDER BY m.destaque DESC, m.data_submissao DESC"; 

$stmt = $conexao->prepare($sql);
if ($stmt === false) {
    die('Erro na preparação da consulta: ' . htmlspecialchars($conexao->error));
}


if (!empty($filtros)) {
    $bind_args = array_merge([$tipos_bind], $filtros);
    call_user_func_array([$stmt, 'bind_param'], array_by_ref($bind_args));
}

// Função auxiliar para passar array por referência
function array_by_ref(&$arr) {
    $refs = [];
    foreach ($arr as $key => $value)
        $refs[$key] = &$arr[$key];
    return $refs;
}

$stmt->execute();
$resultado = $stmt->get_result();
$quantidade = $resultado->num_rows;
$periodos = $conexao->query("SELECT id_periodo, nome_periodo FROM periodo ORDER BY id_periodo ASC");

?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"  content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">

    <title>Gerenciar Monografias</title>
    <link rel="stylesheet" href="css/admin.css">
    <script src="logout_auto.js"></script>
    <script src="js/darkmode2.js"></script>     
    <script src="js/paginacao.js"></script>
    <script src="js/sidebar.js"></script>
    <script src="js/dropdown2.js"></script>

    <style>
        .tag-destaque {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #f39c12;
            font-weight: bold;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        /* Estilo dos cards simplificado como no histórico */
        .cards-container {
            display: flex;
            flex-direction: column;
            gap: 20px;
            padding: 15px;
        }

        .card {
            display: flex;
            flex-direction: column;
            gap: 12px;
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.08);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            position: relative;
        }

        .card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 14px rgba(0,0,0,0.12);
        }

        .card h3 {
            color: #89b67f;
            margin: 0 0 10px 0;
            font-size: 1.2em;
            line-height: 1.3;
        }

        .card p {
            margin: 4px 0;
            color: #555;
            font-size: 0.95em;
            line-height: 1.5;
        }

        .card strong {
            color: #333;
        }

        /* Checkbox no canto superior esquerdo */
        .card-checkbox {
            position: absolute;
            top: 15px;
            left: 15px;
            transform: scale(1.3);
            cursor: pointer;
        }

        /* Container de ações no final do card */
        .card-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
            flex-wrap: wrap;
        }

        .card-actions button,
        .card-actions a button {
            padding: 8px 16px;
            font-size: 0.9em;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .card-actions button:first-child {
            background-color: #3498db;
            color: #fff;
        }

        .card-actions button:first-child:hover {
            background-color: #2c80b4;
        }

        .card-actions a button {
            background-color: #89b67f;
            color: #fff;
        }

        .card-actions a button:hover {
            background-color: #6da168;
        }

        /* Dark mode */
        body.dark-mode .card {
            background: #1a1a1a;
            color: #fff;
        }

        body.dark-mode .card h3 {
            color: #89b67f;
        }

        body.dark-mode .card p {
            color: #ddd;
        }

        body.dark-mode .card strong {
            color: #fff;
        }

        body.dark-mode .card-actions {
            border-top-color: #333;
        }

        /* Responsividade */
        @media (max-width: 768px) {
            .card-actions {
                flex-direction: column;
            }

            .card-actions button,
            .card-actions a {
                width: 100%;
            }

            .card-actions a button {
                width: 100%;
            }
        }
    </style>
</head>
<body>

    <button class="menu-btn">☰</button>

<div class="sidebar-overlay"></div>

    <sidebar class="sidebar">
   <br><br>
        <a href="dashboard.php">Voltar ao Menu Principal</a>
        <a href="cadastrar_monografia.php">Cadastrar Nova Monografia</a>

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
            <h1>Gerenciar Monografias</h1>

   <p class="btn-filtro">
  <img id="imgFiltro" src="icones/filtro1.png" alt="filtro1" title="filtro1" class="icone3" style="cursor:pointer;">
  Filtros
</p>
            <form method="get" class="filters" id="formFiltros">

                <input type="text" name="estudante" placeholder="Nome ou Apelido do Estudante"
                    value="<?= htmlspecialchars($_GET['estudante'] ?? '') ?>">

                <input type="text" name="tema_projeto" placeholder="Tema do Projeto"
                    value="<?= htmlspecialchars($_GET['tema_projeto'] ?? '') ?>">

                <select name="divisao" id="divisao" onchange="carregarCursos()">
                    <?php if ($divisoes->num_rows > 0): ?>
                        <option value="">Divisão</option>
                        <?php 
                            $divisoes->data_seek(0); 
                            while ($f = $divisoes->fetch_assoc()): 
                        ?>
                            <option value="<?= $f['id_divisao'] ?>"
                                <?= isset($_GET['divisao']) && $_GET['divisao'] == $f['id_divisao'] ? 'selected' : '' ?>>
                                <?= $f['nome_divisao'] ?>
                            </option>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <option value="">Nenhuma Divisão</option>
                    <?php endif; ?>
                </select>

                <select name="curso" id="curso" onchange="carregarAreasPesquisa()" disabled>
                    <option value="">Selecione a divisão primeiro</option>
                </select>

                <select name="area_pesquisa" id="area_pesquisa" disabled>
                    <option value="">Selecione o curso primeiro</option>
                </select>

                <select name="ano_submissao">
                    <?php if ($anos_submissao->num_rows > 0): ?>
                        <option value="">Ano de Submissão</option>
                        <?php 
                            $anos_submissao->data_seek(0); 
                            while ($a = $anos_submissao->fetch_assoc()): 
                        ?>
                            <option value="<?= $a['id_ano_submissao'] ?>"
                                <?= isset($_GET['ano_submissao']) && $_GET['ano_submissao'] == $a['id_ano_submissao'] ? 'selected' : '' ?>>
                                <?= $a['ano'] ?>
                            </option>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <option value="">Nenhum ano</option>
                    <?php endif; ?>
                </select>

                <select name="supervisor" id="supervisor">
                    <?php if ($supervisores->num_rows > 0): ?>
                        <option value="">Supervisor</option>
                        <?php 
                            $supervisores->data_seek(0); 
                            while ($s = $supervisores->fetch_assoc()): 
                                $nome_display = $s['nome'];
                                if (!empty($s['apelido'])) {
                                    $nome_display .= ' (' . $s['apelido'] . ')';
                                }
                        ?>
                            <option value="<?= $s['id_usuario'] ?>"
                                <?= isset($_GET['supervisor']) && $_GET['supervisor'] == $s['id_usuario'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($nome_display) ?>
                            </option>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <option value="">Nenhum Supervisor</option>
                    <?php endif; ?>
                </select>

                <select name="periodo" id="periodo">
                    <?php if ($periodos->num_rows > 0): ?>
                        <option value="">Período</option>
                        <?php 
                            $periodos->data_seek(0); 
                            while ($p = $periodos->fetch_assoc()): 
                        ?>
                            <option value="<?= $p['id_periodo'] ?>"
                                <?= isset($_GET['periodo']) && $_GET['periodo'] == $p['id_periodo'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p['nome_periodo']) ?>
                            </option>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <option value="">Nenhum Período</option>
                    <?php endif; ?>
                </select>
                
                <select name="destaque_filtro">
                    <option value="">Destaques (Todos)</option>
                    <option value="1" <?= isset($_GET['destaque_filtro']) && $_GET['destaque_filtro'] === '1' ? 'selected' : '' ?>>Somente Destaques (Sim)</option>
                    <option value="0" <?= isset($_GET['destaque_filtro']) && $_GET['destaque_filtro'] === '0' ? 'selected' : '' ?>>Sem Destaque (Não)</option>
                </select>

                <button type="submit">Pesquisar</button>
                <button type="button" onclick="limparFiltros()">Limpar Filtros</button>

            </form>

            <br>

            <form id="deleteForm" method="post" action="excluir_monografias_multiplas.php">

                <div class="count">
                    <?php if (isset($quantidade)) echo "$quantidade monografia(s) encontrada(s)."; ?>
                </div>

                <div class="select-actions">
                    <button type="button" id="deleteSelected" style="display:none;">Excluir Monografias Selecionadas</button>
                </div>

                <div class="select-all-container">
                    <input type="checkbox" id="selectAllCheckboxes">
                    <label for="selectAllCheckboxes">Selecionar Todas</label>
                </div>

                <?php if ($resultado->num_rows > 0): ?>
                    <div class="cards-container">
                        <div id="pagination" class="pagination"></div>

                        <?php while ($linha = $resultado->fetch_assoc()): 
                            $nome_supervisor_display = $linha['nome_supervisor'];
                            if (!empty($linha['apelido_supervisor'])) {
                                $nome_supervisor_display .= ' (' . $linha['apelido_supervisor'] . ')';
                            }
                            $is_destaque = $linha['destaque'] == 1;
                        ?>
                            <div class="card">
                                <input type="checkbox" name="monografias_ids[]" value="<?= $linha['id_monografia'] ?>" class="card-checkbox"><br>

                                <?php if ($is_destaque): ?>
                                    <div class="tag-destaque"> Destaque</div>
                                <?php endif; ?>

                                <h3><?= htmlspecialchars($linha['tema']) ?></h3>

                                <p><strong>Estudante:</strong> <?= htmlspecialchars($linha['nome_estudante'] . ' ' . $linha['apelido_estudante']) ?></p>
                                <p><strong>Supervisor:</strong> <?= htmlspecialchars($nome_supervisor_display) ?></p>
                                <p><strong>Curso:</strong> <?= htmlspecialchars($linha['nome_curso']) ?></p>
                                <p><strong>Divisão:</strong> <?= htmlspecialchars($linha['nome_divisao']) ?></p>
                                <p><strong>Área:</strong> <?= htmlspecialchars($linha['nome_area_pesquisa']) ?></p>
                                <p><strong>Ano:</strong> <?= htmlspecialchars($linha['ano']) ?></p>
                                <p><strong>Período:</strong> <?= htmlspecialchars($linha['nome_periodo']) ?></p>

                                <div class="card-actions">
                                    <?php if ($linha['link_arquivo'] && file_exists($linha['link_arquivo'])): ?>
                                        <a href="<?= $linha['link_arquivo'] ?>" target="_blank">
                                            <button type="button">Abrir PDF</button>
                                        </a>
                                    <?php else: ?>
                                        <button type="button" disabled>Sem arquivo</button>
                                    <?php endif; ?>

                                    <a href="editar_monografia.php?id_monografia=<?= $linha['id_monografia'] ?>">
                                        <button type="button">Editar</button>
                                    </a>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <p style="text-align: center;">Nenhuma monografia encontrada.</p>
                <?php endif; ?>

            </form>
        </div>
    </div>

<script>

      document.getElementById("imgFiltro").addEventListener("click", function() {
    const form = document.getElementById("formFiltros");

    if (form.style.display === "none" || form.style.display === "") {
        form.style.display = "block";
    } else {
        form.style.display = "none";
    }
});


    // URL base para requisições AJAX
    const BASE_URL = '?ajax=';
    
    // Função para carregar cursos com base na divisão selecionada
    function carregarCursos(selectedCurso = null, callback = null) {
        const divisao = document.getElementById("divisao").value;
        const cursoSelect = document.getElementById("curso");
        const areaPesquisaSelect = document.getElementById("area_pesquisa");

        cursoSelect.innerHTML = '<option value="">Carregando...</option>';
        cursoSelect.disabled = true;
        areaPesquisaSelect.innerHTML = '<option value="">Selecione o curso primeiro</option>';
        areaPesquisaSelect.disabled = true;

        if (!divisao) {
            cursoSelect.innerHTML = '<option value="">Selecione a divisão primeiro</option>';
            if (callback) callback();
            return;
        }

        fetch(`${BASE_URL}cursos&divisao=${divisao}`)
            .then(res => res.text())
            .then(data => {
                const isValidData = data.trim() !== '' && data.indexOf('<option') !== -1;
                cursoSelect.innerHTML = isValidData ? data : '<option value="">Nenhum curso cadastrado</option>';
                cursoSelect.disabled = !isValidData;
                if (selectedCurso) {
                    cursoSelect.value = selectedCurso;
                }
                
                // Força o carregamento da área se o curso for válido
                if(cursoSelect.value) {
                    const urlParams = new URLSearchParams(window.location.search);
                    const selectedAreaPesquisa = urlParams.get('area_pesquisa') || '';
                    carregarAreasPesquisa(selectedAreaPesquisa);
                }

                if (callback) callback();
            })
            .catch(() => {
                alert("Erro ao carregar cursos.");
                if (callback) callback();
            });
    }

    // Função para carregar Áreas de Pesquisa
    function carregarAreasPesquisa(selectedArea = null) {
        const curso = document.getElementById("curso").value;
        const areaPesquisaSelect = document.getElementById("area_pesquisa");

        areaPesquisaSelect.innerHTML = '<option value="">Carregando...</option>';
        areaPesquisaSelect.disabled = true;

        if (!curso) {
            areaPesquisaSelect.innerHTML = '<option value="">Selecione o curso primeiro</option>';
            return;
        }

        fetch(`${BASE_URL}areas_pesquisa&curso=${curso}`)
            .then(res => res.text())
            .then(data => {
                const isValidData = data.trim() !== '' && data.indexOf('<option') !== -1;
                areaPesquisaSelect.innerHTML = isValidData ? data : '<option value="">Nenhuma área de pesquisa cadastrada</option>';
                areaPesquisaSelect.disabled = !isValidData;
                if (selectedArea) {
                    areaPesquisaSelect.value = selectedArea;
                }
            })
            .catch(() => {
                alert("Erro ao carregar áreas de pesquisa.");
                areaPesquisaSelect.disabled = true;
            });
    }

    // Função para limpar todos os filtros
    function limparFiltros() {
        window.location.href = window.location.pathname;
    }

    // Inicialização ao carregar a página
    document.addEventListener("DOMContentLoaded", () => {
        const divisaoSelect = document.getElementById("divisao");
        const urlParams = new URLSearchParams(window.location.search);
        const selectedDivisao = urlParams.get('divisao') || null;
        const selectedCurso = urlParams.get('curso') || '';

        // Inicializa o SELECT de Curso e Área com base nos filtros GET, se existirem.
        if (selectedDivisao) {
            // Se uma divisão estiver selecionada, carrega os cursos e, em seguida, as áreas
            carregarCursos(selectedCurso);
        }
        
        // Lógica de Exclusão Múltipla
        const selectAllCheckbox = document.getElementById('selectAllCheckboxes');
        const monografiaCheckboxes = document.querySelectorAll('input[name="monografias_ids[]"]');
        const deleteButton = document.getElementById('deleteSelected');
        const deleteForm = document.getElementById('deleteForm');

        function updateDeleteButton() {
            const checkedCount = document.querySelectorAll('input[name="monografias_ids[]"]:checked').length;
            if (checkedCount > 0) {
                deleteButton.style.display = 'block';
                deleteButton.textContent = `Excluir (${checkedCount}) Monografia(s) Selecionada(s)`;
            } else {
                deleteButton.style.display = 'none';
            }
            // Atualiza o estado do 'Selecionar Todos'
            selectAllCheckbox.checked = checkedCount > 0 && checkedCount === monografiaCheckboxes.length;
        }

        selectAllCheckbox.addEventListener('change', () => {
            monografiaCheckboxes.forEach(checkbox => {
                checkbox.checked = selectAllCheckbox.checked;
            });
            updateDeleteButton();
        });

        monografiaCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', updateDeleteButton);
        });

        deleteButton.addEventListener('click', () => {
            if (confirm('Tem certeza que deseja excluir as monografias selecionadas?')) {
                deleteForm.submit();
            }
        });

        updateDeleteButton(); // Executa ao carregar para verificar o estado inicial
    });
</script>

</body>
</html>