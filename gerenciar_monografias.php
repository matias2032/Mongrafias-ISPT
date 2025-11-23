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

// >>> NOVO: Busca por Supervisores (id_perfil = 4)
$supervisores = $conexao->query("SELECT id_usuario, nome, apelido FROM usuario WHERE id_perfil = 4 ORDER BY nome");


$filtros = [];
$tipos_bind = ""; // Variável para armazenar os tipos de bind (i, s, etc.)

// ----------------------------------------------------------------------
// CONSULTA PRINCIPAL DE BUSCA (FILTROS E NOVOS CAMPOS)
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
    p.nome_periodo,                   -- ADICIONADO
    m.caminho_arquivo as link_arquivo
FROM monografia m
INNER JOIN divisao d ON m.id_divisao = d.id_divisao
INNER JOIN curso c ON m.id_curso = c.id_curso
INNER JOIN area_pesquisa ap ON m.id_area_pesquisa = ap.id_area_pesquisa
INNER JOIN ano_submissao ans ON m.id_ano_submissao = ans.id_ano_submissao
INNER JOIN usuario u ON m.id_supervisor = u.id_usuario
INNER JOIN periodo p ON m.id_periodo = p.id_periodo   -- ADICIONADO
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

// >>> NOVO FILTRO: Supervisor (SELECT)
if (!empty($_GET['supervisor'])) {
    $sql .= " AND m.id_supervisor = ?";
    $tipos_bind .= "i";
    $filtros[] = $_GET['supervisor'];
}



// 2. Filtros de TEXTO (Estudante e Tema)
// >>> NOVO FILTRO: Pesquisa por Nome/Apelido do Estudante
if (!empty($_GET['estudante'])) {
    $estudante = "%" . trim($_GET['estudante']) . "%";
    $sql .= " AND (m.nome_estudante LIKE ? OR m.apelido_estudante LIKE ?)";
    $tipos_bind .= "ss"; // Dois 's' para nome_estudante LIKE e apelido_estudante LIKE
    $filtros[] = $estudante;
    $filtros[] = $estudante;
}

// >>> NOVO FILTRO: Pesquisa por Tema do Projeto
if (!empty($_GET['tema_projeto'])) {
    $tema_projeto = "%" . trim($_GET['tema_projeto']) . "%";
    $sql .= " AND m.tema LIKE ?";
    $tipos_bind .= "s";
    $filtros[] = $tema_projeto;
}
// >>> NOVO FILTRO: Período
if (!empty($_GET['periodo'])) {
    $sql .= " AND m.id_periodo = ?";
    $tipos_bind .= "i";
    $filtros[] = $_GET['periodo'];
}



$sql .= " ORDER BY m.data_submissao DESC"; // Boa prática para ordem

$stmt = $conexao->prepare($sql);
if ($stmt === false) {
    die('Erro na preparação da consulta: ' . htmlspecialchars($conexao->error));
}


if (!empty($filtros)) {
    // A função call_user_func_array é a forma segura de passar um array
    // de argumentos (tipos e valores) para bind_param quando os tipos são variáveis.
    // O array de argumentos deve começar com a string de tipos.
    $bind_args = array_merge([$tipos_bind], $filtros);
    call_user_func_array([$stmt, 'bind_param'], array_by_ref($bind_args));
}

// Função auxiliar para passar array por referência (necessário para bind_param com call_user_func_array)
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
    <script src="logout_auto.js"></script>
    <script src="js/darkmode2.js"></script> 	
    <link rel="stylesheet" href="css/admin.css">
    <script src="js/paginacao.js"></script>
    <script src="js/sidebar.js"></script>

</head>
<body>

    <button class="menu-btn">☰</button>

<!-- Overlay -->
<div class="sidebar-overlay"></div>

    <!-- ===== SIDEBAR ===== -->
    <sidebar class="sidebar">
   <br><br>
        <a href="dashboard.php">Voltar ao Menu Principal</a>
        <a href="cadastrar_monografia.php">Cadastrar Nova Monografia</a>

        <div class="sidebar-footer">
            <a href="logout.php" title="Sair">
                <img id="iconelogout" src="icones/logout1.png" alt="Logout">
            </a>
            <img class="dark-toggle" id="darkToggle" src="icones/lua.png" alt="Modo Escuro" title="Alternar modo escuro">
        </div>
    </sidebar>

    <!-- ===== CONTENT ===== -->
    <div class="content">
        <div class="main">
            <h1>Gerenciar Monografias</h1>

            <!-- ===== FORM FILTROS ===== -->
            <form method="get" class="filters">

                <input type="text" name="estudante" placeholder="Nome ou Apelido do Estudante"
                    value="<?= htmlspecialchars($_GET['estudante'] ?? '') ?>">

                <input type="text" name="tema_projeto" placeholder="Tema do Projeto"
                    value="<?= htmlspecialchars($_GET['tema_projeto'] ?? '') ?>">

                <!-- Divisão -->
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

                <!-- Curso -->
                <select name="curso" id="curso" onchange="carregarAreasPesquisa()" disabled>
                    <option value="">Selecione a divisão primeiro</option>
                </select>

                <!-- Área de Pesquisa -->
                <select name="area_pesquisa" id="area_pesquisa" disabled>
                    <option value="">Selecione o curso primeiro</option>
                </select>

                <!-- Ano de Submissão -->
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

                <!-- Supervisor -->
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

                <!-- Período -->
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

                <!-- Botões -->
                <button type="submit">Pesquisar</button>
                <button type="button" onclick="limparFiltros()">Limpar Filtros</button>

            </form>

            <br>

            <!-- ===== FORM EXCLUSÃO ===== -->
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
                        ?>
                            <div class="card">
                                <div class="exam-card status-<?= $linha['status_exame'] ?>">

                                    <input type="checkbox" name="monografias_ids[]" value="<?= $linha['id_monografia'] ?>">

                                    <div class="card-content">

                                        <div class="card-title">
                                            <?= htmlspecialchars($linha['tema']) ?>
                                        </div>

                                        <div class="card-details">
                                            <div class="card-student">
                                                <strong style="color: var(--text-color-dark);">Estudante:</strong>
                                                <?= htmlspecialchars($linha['nome_estudante'] . ' ' . $linha['apelido_estudante']) ?>
                                            </div>

                                            <p><strong>Área:</strong> <?= htmlspecialchars($linha['nome_area_pesquisa']) ?></p>
                                            <p><strong>Supervisor:</strong> <?= htmlspecialchars($nome_supervisor_display) ?></p>
                                            <p><strong>Curso:</strong> <?= htmlspecialchars($linha['nome_curso']) ?></p>
                                            <p><strong>Divisão:</strong> <?= htmlspecialchars($linha['nome_divisao']) ?></p>
                                            <p><strong>Ano:</strong> <?= htmlspecialchars($linha['ano']) ?></p>
                                            <p><strong>Período:</strong> <?= htmlspecialchars($linha['nome_periodo']) ?></p>
                                        </div>
                                    </div>

                                    <div class="card-actions">
                                        <!-- <a href="uploads/monografias/<?= htmlspecialchars($linha['link_arquivo']) ?>" target="_blank">
                                            <button type="button">Ver PDF</button>
                                        </a> -->
                                         <div class="form-group">
        <?php if ($linha['link_arquivo'] && file_exists($linha['link_arquivo'])): ?>
            <p><a href="<?= $linha['link_arquivo'] ?>" target="_blank"><button type="button">Abrir PDF atual</button></a></p>
                <?php else: ?>
            <p><em>Nenhum arquivo anexado.</em></p>
        <?php endif; ?>
      </div>


                                        
                                        <a href="editar_monografia.php?id_monografia=<?= $linha['id_monografia'] ?>">
                                            <button type="button">Editar</button>
                                        </a>
                                    </div>

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
                    const selectedAreaPesquisa = "<?= $_GET['area_pesquisa'] ?? '' ?>";
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
        const selectedDivisao = divisaoSelect.value || null;
        const selectedCurso = "<?= $_GET['curso'] ?? '' ?>";
        const selectedAreaPesquisa = "<?= $_GET['area_pesquisa'] ?? '' ?>";

        // Inicializa o SELECT de Curso e Área com base nos filtros GET, se existirem.
        if (selectedDivisao) {
            // Se uma divisão estiver selecionada, carrega os cursos e, em seguida, as áreas
            carregarCursos(selectedCurso, () => {
                if (selectedCurso) {
                    carregarAreasPesquisa(selectedAreaPesquisa); 
                }
            });
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