<?php
include "conexao.php";
include "verifica_login.php";
include "info_usuario.php";

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

$usuario = $_SESSION['usuario'];
$id_perfil = $usuario['id_perfil'] ?? null;
$mensagem = "";
$tipo_mensagem = "info";
$redirecionar = false;

// ----------------------------------------------------------------------
// LÓGICA AJAX PARA CARREGAMENTO DINÂMICO DOS SELECTS
// ----------------------------------------------------------------------

if (isset($_GET['ajax']) && $_GET['ajax'] == 'cursos') {
    if (!isset($_GET['divisao'])) exit;
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

if (isset($_GET['ajax']) && $_GET['ajax'] == 'areas_pesquisa') {
    if (!isset($_GET['curso'])) exit;
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
// PREENCHIMENTO DOS SELECTS INICIAIS
// ----------------------------------------------------------------------
$divisoes = $conexao->query("SELECT id_divisao, nome_divisao FROM divisao ORDER BY nome_divisao"); 
$anos_submissao = $conexao->query("SELECT id_ano_submissao, ano FROM ano_submissao ORDER BY ano DESC"); 
$periodos = $conexao->query("SELECT id_periodo, nome_periodo FROM periodo ORDER BY id_periodo ASC");
$supervisores = $conexao->query("SELECT id_usuario, nome, apelido FROM usuario WHERE id_perfil = 4 ORDER BY nome");

// ----------------------------------------------------------------------
// LÓGICA DE CADASTRO (POST)
// ----------------------------------------------------------------------

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // 1. Coleta de dados
    $tema = trim($_POST['tema'] ?? '');
    $nome_estudante = trim($_POST['nome_estudante'] ?? '');
    $apelido_estudante = trim($_POST['apelido_estudante'] ?? '');
    
    $id_divisao = intval($_POST['divisao'] ?? 0);
    $id_curso = intval($_POST['curso'] ?? 0);
    $id_area_pesquisa = intval($_POST['area_pesquisa'] ?? 0);
    $id_ano_submissao = intval($_POST['ano_submissao'] ?? 0);
    $id_supervisor = intval($_POST['supervisor'] ?? 0);
    $periodo = intval($_POST['periodo'] ?? 0);
    
    // [NOVO] Captura o valor do Destaque (Checkbox)
    // Se estiver marcado, vale 1, senão 0
    $destaque = isset($_POST['destaque']) ? 1 : 0;

    $ano_submissao_int = $id_ano_submissao; 
    $arquivo_info = $_FILES['monografia_file'] ?? null;

    // 2. Validação
    if (empty($tema) || empty($nome_estudante) || empty($apelido_estudante) || 
        $id_divisao == 0 || $id_curso == 0 || $id_area_pesquisa == 0 || 
        $id_ano_submissao == 0 || $id_supervisor == 0 || $periodo == 0) {
        
        $mensagem = "⚠️ Por favor, preencha todos os campos obrigatórios.";
        $tipo_mensagem = "error";

    } elseif (!isset($arquivo_info) || $arquivo_info['error'] != UPLOAD_ERR_OK) {
        $mensagem = "⚠️ Arquivo da monografia não enviado ou erro no upload.";
        $tipo_mensagem = "error";
    } else {
        // 3. Processamento do arquivo
        $diretorio_upload = "uploads/monografias/";
        if (!is_dir($diretorio_upload)) {
            mkdir($diretorio_upload, 0777, true);
        }
        
        $extensao = pathinfo($arquivo_info['name'], PATHINFO_EXTENSION);
        $novo_nome_arquivo = uniqid("mono_") . "." . strtolower($extensao);
        $caminho_completo = $diretorio_upload . $novo_nome_arquivo;

        $allowed_mime_types = ['application/pdf'];
        if (!in_array($arquivo_info['type'], $allowed_mime_types)) {
            $mensagem = "Apenas arquivos PDF são permitidos.";
            $tipo_mensagem = "error";
        } elseif ($arquivo_info['size'] > 50 * 1024 * 1024) { 
            $mensagem = "O arquivo é muito grande. O limite é de 50MB.";
            $tipo_mensagem = "error";
        } elseif (move_uploaded_file($arquivo_info['tmp_name'], $caminho_completo)) {
            
            // 4. Inserção no Banco de Dados
            // [ATUALIZADO] Adicionada a coluna `destaque` na query
            $sql_insert = "INSERT INTO monografia 
            (id_curso, id_supervisor, tema, nome_estudante, apelido_estudante, ano_submissao, caminho_arquivo, id_ano_submissao, id_area_pesquisa, id_divisao, id_periodo, data_submissao, status_monografia, destaque) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'aprovada', ?)";
            
            $stmt_insert = $conexao->prepare($sql_insert);
            
            // [ATUALIZADO] Bind Param
            // Adicionado mais um 'i' no final da string de tipos (total 12 parametros agora)
            // String antiga: "iisssisiiii" -> Nova: "iisssisiiiii"
            $stmt_insert->bind_param("iisssisiiiii", 
                $id_curso,
                $id_supervisor,
                $tema,
                $nome_estudante,
                $apelido_estudante,
                $ano_submissao_int,
                $caminho_completo,
                $id_ano_submissao,
                $id_area_pesquisa,
                $id_divisao,
                $periodo,
                $destaque // [NOVO] Variável do destaque
            );

            if ($stmt_insert->execute()) {
                $id_monografia_inserida = $stmt_insert->insert_id;
                $idUsuarioLogado = $usuario['id_usuario']; 

                // Registrar upload
                $stmtUpload = $conexao->prepare("INSERT INTO upload (id_monografia, id_usuario, data_upload) VALUES (?, ?, NOW())");
                $stmtUpload->bind_param("ii", $id_monografia_inserida, $idUsuarioLogado);
                $stmtUpload->execute();
                $stmtUpload->close();

                // Notificações (Apenas para funcionarios notificarem Admins)
                if ($usuario['id_perfil'] == 2) {
                    $sql_admins = "SELECT id_usuario FROM usuario WHERE id_perfil = 1";
                    $res_admins = $conexao->query($sql_admins);
                    if ($res_admins->num_rows > 0) {
                        $stmtNotif = $conexao->prepare("
                            INSERT INTO notificacao (id_monografia, id_usuario, mensagem, lida, data_criacao, tipo)
                            VALUES (?, ?, 'Nova monografia cadastrada', 0, NOW(), 'submissao_pendente')
                        ");
                        while ($admin = $res_admins->fetch_assoc()) {
                            $stmtNotif->bind_param("ii", $id_monografia_inserida, $admin['id_usuario']);
                            $stmtNotif->execute();
                        }
                        $stmtNotif->close();
                    }
                }

                $mensagem = "Monografia cadastrada com sucesso!";
                $tipo_mensagem = "success";
                $redirecionar = true;
                $_POST = [];
            } else {
                $mensagem = "Erro ao salvar dados no banco: " . $conexao->error;
                $tipo_mensagem = "error";
                unlink($caminho_completo); // Remove arquivo se falhar BD
            }
            $stmt_insert->close();

        } else {
            $mensagem = "Erro desconhecido ao mover o arquivo.";
            $tipo_mensagem = "error";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Cadastrar Monografia</title>
    <script src="logout_auto.js"></script>
    <script src="js/darkmode2.js"></script>     
    <link rel="stylesheet" href="css/admin.css">
    <script src="js/sidebar.js"></script>
  <script src="js/dropdown2.js"></script>
  
    <style>
        .drop-zone {
            width: 100%; min-height: 150px; padding: 20px; margin-bottom: 20px;
            text-align: center; border: 2px dashed #3498db; border-radius: 10px;
            background-color: #ecf0f1; transition: background-color 0.3s, border-color 0.3s;
        }
        .drop-zone.drag-over { background-color: #d0e7f7; border-color: #2980b9; }
        .drop-zone-text { color: #7f8c8d; font-size: 1.1em; margin-bottom: 10px; }
        .file-input { display: none; }
        .file-name { font-weight: bold; color: #27ae60; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .main button[type="submit"] { margin-top: 20px; }
        
        /* Estilo para o Checkbox de Destaque */
        .checkbox-wrapper {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 15px;
            padding: 10px;
            background-color: rgba(52, 152, 219, 0.1);
            border-radius: 5px;
            border-left: 4px solid #3498db;
        }
        .checkbox-wrapper input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }
        .checkbox-wrapper label {
            margin-bottom: 0 !important; /* Sobrescreve o display block padrão */
            cursor: pointer;
        }
    </style>
</head>
<body>

    <button class="menu-btn">☰</button>
    <div class="sidebar-overlay"></div>

    <?php if ($id_perfil == 1): ?>
        <sidebar class="sidebar">
            <br><br>
            <a href="gerenciar_monografias.php">Voltar à área de Monografias</a>
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
                            <img class="icone" src="icones/user1.png" alt="Editar"> Editar Dados Pessoais
                        </a>
                        <a href="alterar_senha2.php">
                            <img class="icone" src="icones/cadeado1.png" alt="Alterar"> Alterar Senha
                        </a>
                        <a href="logout.php">
                            <img class="iconelogout" src="icones/logout1.png" alt="Logout"> Sair
                        </a>
                    </div>
                </div>
                <img class="dark-toggle" id="darkToggle" src="icones/lua.png" alt="Modo Escuro">
            </div>
        </sidebar>
    <?php else: ?>
        <sidebar class="sidebar">
            <br><br>
            <a href="ver_monografias.php">Voltar</a>
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
                            <img class="icone" src="icones/user1.png" alt="Editar"> Editar Dados Pessoais
                        </a>
                        <a href="alterar_senha2.php">
                            <img class="icone" src="icones/cadeado1.png" alt="Alterar"> Alterar Senha
                        </a>
                        <a href="logout.php">
                            <img class="iconelogout" src="icones/logout1.png" alt="Logout"> Sair
                        </a>
                    </div>
                </div>
                <img class="dark-toggle" id="darkToggle" src="icones/lua.png" alt="Modo Escuro">
            </div>
        </sidebar>
    <?php endif; ?>

<div class="content">
    <div class="main">
        <h1>Cadastrar Nova Monografia</h1>

        <?php if (!empty($mensagem)): ?>
            <div class="mensagem <?= htmlspecialchars($tipo_mensagem) ?>">
                <?= htmlspecialchars($mensagem) ?>
            </div>
        <?php endif; ?>

        <form method="post" action="" enctype="multipart/form-data" class="form-container">
            
            <div class="form-group">
                <label for="tema">Tema da Monografia:</label>
                <input type="text" name="tema" id="tema" value="<?= htmlspecialchars($_POST['tema'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label for="nome_estudante">Nome do Estudante:</label>
                <input type="text" name="nome_estudante" id="nome_estudante" value="<?= htmlspecialchars($_POST['nome_estudante'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label for="apelido_estudante">Apelido do Estudante:</label>
                <input type="text" name="apelido_estudante" id="apelido_estudante" value="<?= htmlspecialchars($_POST['apelido_estudante'] ?? '') ?>" required>
            </div>
            
            <div class="form-group">
                <label for="divisao">Divisão:</label>
                <select name="divisao" id="divisao" onchange="carregarCursos()" required>
                    <option value="">Selecione a Divisão</option>
                    <?php $divisoes->data_seek(0); while ($f = $divisoes->fetch_assoc()) { ?>
                        <option value="<?= $f['id_divisao'] ?>" <?= isset($_POST['divisao']) && $_POST['divisao'] == $f['id_divisao'] ? 'selected' : '' ?>>
                            <?= $f['nome_divisao'] ?>
                        </option>
                    <?php } ?>
                </select>
            </div>

            <div class="form-group">
                <label for="curso">Curso:</label>
                <select name="curso" id="curso" onchange="carregarAreasPesquisa()" disabled required>
                    <option value="">Selecione a divisão primeiro</option>
                </select>
            </div>

            <div class="form-group">
                <label for="area_pesquisa">Área de Pesquisa:</label>
                <select name="area_pesquisa" id="area_pesquisa" disabled required>
                    <option value="">Selecione o curso primeiro</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="ano_submissao">Ano de Submissão:</label>
                <select name="ano_submissao" id="ano_submissao" required>
                    <option value="">Selecione o Ano</option>
                    <?php $anos_submissao->data_seek(0); while ($a = $anos_submissao->fetch_assoc()) { ?>
                        <option value="<?= $a['id_ano_submissao'] ?>" <?= isset($_POST['ano_submissao']) && $_POST['ano_submissao'] == $a['id_ano_submissao'] ? 'selected' : '' ?>>
                            <?= $a['ano'] ?>
                        </option>
                    <?php } ?>
                </select>
            </div>

            <div class="form-group">
                <label for="periodo">Período:</label>
                <select name="periodo" id="periodo" required>
                    <option value="">Selecione o Período</option>
                    <?php 
                    if ($periodos && $periodos->num_rows > 0) {
                        $periodos->data_seek(0);
                        while ($p = $periodos->fetch_assoc()) { ?>
                            <option value="<?= $p['id_periodo'] ?>" <?= isset($_POST['periodo']) && $_POST['periodo'] == $p['id_periodo'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p['nome_periodo']) ?>
                            </option>
                        <?php }
                    } else {
                        echo '<option value="" disabled>Nenhum período encontrado</option>';
                    }
                    ?>
                </select>
            </div>

            <div class="form-group">
                <label for="supervisor">Supervisor:</label>
                <select name="supervisor" id="supervisor" required>
                    <option value="">Selecione o Docente</option>
                    <?php 
                    if ($supervisores->num_rows > 0) {
                        $supervisores->data_seek(0); 
                        while ($s = $supervisores->fetch_assoc()) { 
                            $nome_display = $s['nome'];
                            if (!empty($s['apelido'])) { $nome_display .= ' (' . $s['apelido'] . ')'; }
                            ?>
                            <option value="<?= $s['id_usuario'] ?>" <?= isset($_POST['supervisor']) && $_POST['supervisor'] == $s['id_usuario'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($nome_display) ?> 
                            </option>
                        <?php }
                    } else {
                        echo '<option value="" disabled>Nenhum Supervisor encontrado</option>';
                    }
                    ?>
                </select>
            </div>

            <input type="file" name="monografia_file" id="monografia_file" accept=".pdf" class="file-input" required>
            
            <div class="drop-zone" id="dropZone">
                <div class="drop-zone-text">Arraste e solte o arquivo PDF aqui</div>
                <button type="button" onclick="document.getElementById('monografia_file').click()">
                    Ou Clique para Escolher o Arquivo (.pdf)
                </button>
                <p class="file-name" id="fileName"></p>
            </div>

            <?php if ($id_perfil == 1): ?>
                <div class="form-group checkbox-wrapper">
                    <input type="checkbox" name="destaque" id="destaque" value="1">
                    <label for="destaque">⭐ Marcar esta Monografia como Destaque</label>
                </div>
            <?php endif; ?>

            <button type="submit">Cadastrar Monografia</button>
        </form>
    </div>
</div>

<?php if ($redirecionar): ?>
    <script>
        setTimeout(() => {
            <?php if ($id_perfil == 1): ?>
                window.location.href = 'gerenciar_monografias.php';
            <?php else: ?>
                window.location.href = 'ver_monografias.php?novo=1';
            <?php endif; ?>
        }, 3000);
    </script>
<?php endif; ?>

<script>
    const BASE_URL = '?ajax=';

    function carregarCursos(selectedCurso = null) {
        const divisao = document.getElementById("divisao").value;
        const cursoSelect = document.getElementById("curso");
        const areaPesquisaSelect = document.getElementById("area_pesquisa");

        cursoSelect.innerHTML = '<option value="">Carregando...</option>';
        cursoSelect.disabled = true;
        areaPesquisaSelect.innerHTML = '<option value="">Selecione o curso primeiro</option>';
        areaPesquisaSelect.disabled = true;

        if (!divisao) {
            cursoSelect.innerHTML = '<option value="">Selecione a divisão primeiro</option>';
            return;
        }

        fetch(`${BASE_URL}cursos&divisao=${divisao}`)
            .then(res => res.text())
            .then(data => {
                cursoSelect.innerHTML = data || '<option value="">Nenhum curso cadastrado</option>';
                cursoSelect.disabled = false;
                if (selectedCurso) {
                    cursoSelect.value = selectedCurso;
                }
                if (cursoSelect.value) {
                    carregarAreasPesquisa(document.getElementById("area_pesquisa").dataset.selected);
                }
            })
            .catch(() => { cursoSelect.innerHTML = '<option value="">Erro ao carregar cursos</option>'; });
    }

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
                areaPesquisaSelect.innerHTML = data || '<option value="">Nenhuma área de pesquisa cadastrada</option>';
                areaPesquisaSelect.disabled = false;
                if (selectedArea) {
                    areaPesquisaSelect.value = selectedArea;
                }
            })
            .catch(() => { areaPesquisaSelect.innerHTML = '<option value="">Erro ao carregar áreas</option>'; });
    }

    // Drag and Drop Logic
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('monografia_file');
    const fileNameDisplay = document.getElementById('fileName');

    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, (e) => { e.preventDefault(); e.stopPropagation(); }, false);
    });

    ['dragenter', 'dragover'].forEach(eventName => {
        dropZone.addEventListener(eventName, () => dropZone.classList.add('drag-over'), false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, () => dropZone.classList.remove('drag-over'), false);
    });

    dropZone.addEventListener('drop', (e) => {
        const dt = e.dataTransfer;
        const files = dt.files;
        if (files.length > 0) {
            const file = files[0];
            if (file.type === 'application/pdf') {
                fileInput.files = files; 
                fileNameDisplay.textContent = `Arquivo selecionado: ${file.name}`;
            } else {
                alert("❌ Apenas arquivos PDF são aceitos.");
                fileNameDisplay.textContent = '';
            }
        }
    }, false);
    
    fileInput.addEventListener('change', () => {
        if (fileInput.files.length > 0) {
            const file = fileInput.files[0];
            if (file.type === 'application/pdf') {
                 fileNameDisplay.textContent = `Arquivo selecionado: ${file.name}`;
            } else {
                alert("❌ Apenas arquivos PDF são aceitos.");
                fileInput.value = ''; 
                fileNameDisplay.textContent = '';
            }
        } else {
            fileNameDisplay.textContent = '';
        }
    });

    document.addEventListener("DOMContentLoaded", () => {
        const postDivisao = "<?= $_POST['divisao'] ?? '' ?>";
        const postCurso = "<?= $_POST['curso'] ?? '' ?>";
        const postArea = "<?= $_POST['area_pesquisa'] ?? '' ?>";
        
        if(postArea) document.getElementById("area_pesquisa").dataset.selected = postArea;

        if (postDivisao) {
            document.getElementById("divisao").value = postDivisao;
            carregarCursos(postCurso); 
        }
    });
</script>

</body>
</html>