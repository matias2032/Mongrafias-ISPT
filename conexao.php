<?php
$host = "localhost";
$usuario = "root";
$password = "";
$basededados = "monografias2";

$conexao = new mysqli($host, $usuario, $password, $basededados);

// Verifica a conexão
if ($conexao->connect_error) {
    die("Falha na conexão: " . $conexao->connect_error);
}
?>

