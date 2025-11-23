<?php
// // verifica_login.php
// session_start();
// if (session_status() === PHP_SESSION_NONE) {
//    // Inicia a sessão somente se ainda não estiver iniciada
// }

// // Verifica se o usuário está logado
// if (!isset($_SESSION['usuario'])) {
//     header("Location: login.php");
//     exit;
// }

// $usuario = $_SESSION['usuario']; // Deixa disponível para uso na página
?>



<?php
session_start();

if (!isset($_SESSION['usuario'])) {
    // Captura a URL de destino
    $urlAtual = $_SERVER['REQUEST_URI'];
    $_SESSION['url_destino'] = $urlAtual;

    header("Location: login.php");
    exit;
}
// Define a variável $usuario com os dados da sessão
$usuario = $_SESSION['usuario'];
?>