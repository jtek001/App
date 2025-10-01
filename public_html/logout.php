<?php
// logout.php
// Encerra a sessão do usuário

session_start();

// Destrói todas as variáveis de sessão
$_SESSION = array();

// Destrói a sessão
session_destroy();

// Redireciona para a página de login
header("location: index.php");
exit;
?>
