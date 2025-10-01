<?php
// db_connect.php
// Arquivo para conexão com o banco de dados

// Define as credenciais do banco de dados
define('DB_SERVER', 'localhost'); // Geralmente 'localhost'
define('DB_USERNAME', 'app_user');
define('DB_PASSWORD', 'app_pwd');
define('DB_NAME', 'app_data');

// Tenta conectar ao banco de dados MySQL
$mysqli = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Verifica a conexão
if ($mysqli->connect_error) {
    die("Erro na conexão com o banco de dados: " . $mysqli->connect_error);
}
?>