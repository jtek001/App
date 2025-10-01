<?php

// header.php

// Cabeçalho comum para páginas autenticadas, agora com CSS responsivo e menu dropdown



session_start();



// Verifica se o usuário não está logado, redireciona para a página de login

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {

    header("location: index.php");

    exit;

}

?>

<!DOCTYPE html>

<html lang="pt-br">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>JtekInfo-OS</title>

    <link rel="shortcut icon" type="image/x-icon" href="logojtek.png">

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <link rel="stylesheet" href="style.css"> </head>

<body>

    <div class="header">

        <h1><a href="https://app.jtekinfo.com.br/"><img src="logo.png" height="40" title="Início"></a></h1>

        <button class="menu-toggle" id="menuToggle">&#9776;</button> <nav>

            <ul id="mainMenu">

                <li><a href="dashboard.php">Início</a></li>

                <li><a href="list_clients.php">Clientes</a></li>

                <li><a href="list_service_orders.php">O.S</a></li>
                <li><a href="list_products.php">Produtos</a></li>

                <li><a href="billing_control.php">Faturamento</a></li>

                <li><a href="list_expenses.php?start_date=<?php echo date('Y-m-01'); ?>&end_date=<?php echo date('Y-m-t'); ?>">Despesas</a></li>

                <li><a href="reports.php?start_date=<?php echo date('Y-m-01'); ?>&end_date=<?php echo date('Y-m-t'); ?>">Relatórios</a></li>

                <li><a href="logout.php">Sair</a></li>

            </ul>

        </nav>

    </div>

    <div class="container">

    <script>

        document.addEventListener('DOMContentLoaded', function() {

            const menuToggle = document.getElementById('menuToggle');

            const mainMenu = document.getElementById('mainMenu');



            if (menuToggle && mainMenu) {

                menuToggle.addEventListener('click', function() {

                    mainMenu.classList.toggle('active');

                });

            }

        });

    </script>