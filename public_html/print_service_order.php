<?php

// print_service_order.php

// Página para impressão de uma ordem de serviço



require_once 'db_connect.php'; // Inclui apenas a conexão com o banco de dados



$id = null;

$service_order = null;

$client_info = null;

$error_message = "";



// Verifica se o ID da ordem de serviço foi fornecido

if (isset($_GET["id"]) && !empty(trim($_GET["id"]))) {

    $id = trim($_GET["id"]);



    // Consulta para obter os detalhes da ordem de serviço e do cliente (adicionado c.cnpj)

    $sql = "SELECT so.id AS os_id, so.description, so.status, so.value, so.solution, so.open_date, so.close_date,

                   c.name AS client_name, c.address AS client_address, c.phone AS client_phone, c.email AS client_email, c.cnpj AS client_cnpj

            FROM service_orders so

            JOIN clients c ON so.client_id = c.id

            WHERE so.id = ?";



    if ($stmt = $mysqli->prepare($sql)) {

        $stmt->bind_param("i", $param_id);

        $param_id = $id;



        if ($stmt->execute()) {

            $result = $stmt->get_result();

            if ($result->num_rows == 1) {

                $service_order = $result->fetch_assoc();

            } else {

                $error_message = "Ordem de Serviço não encontrada.";

            }

        } else {

            $error_message = "Ops! Algo deu errado ao buscar a Ordem de Serviço. Por favor, tente novamente mais tarde.";

        }

        $stmt->close();

    } else {

        $error_message = "Erro ao preparar a consulta SQL.";

    }

} else {

    $error_message = "ID da Ordem de Serviço não fornecido.";

}

$mysqli->close(); // Fecha a conexão

?>



<!DOCTYPE html>

<html lang="pt-br">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Imprimir Ordem de Serviço #<?php echo htmlspecialchars($id); ?></title>

    <style>

        body {

            font-family: Arial, sans-serif;

            margin: 20px;

            color: #333;

            line-height: 1.6;

        }

        .print-container {		

            max-width: 800px;

            margin: 0 auto;

            padding: 20px;

            border: 1px solid #ccc;

            border-radius: 8px;

            background-color: #fff;

        }

        h1, h2, h3 {

            color: #333;

            border-bottom: 1px solid #eee;

            padding-bottom: 10px;

            margin-top: 20px;

        }

        .info-item {

            margin-bottom: 10px;

        }

        .info-item strong {

            display: inline-block;

            width: 150px; /* Ajuste para alinhar os dois pontos */

            vertical-align: top;

        }

        .info-item span {

            display: inline-block;

            vertical-align: top;

            width: calc(100% - 160px); /* Ajusta a largura do conteúdo */

        }

        .section-break {

            border-top: 1px dashed #ccc;

            margin: 30px 0;

        }

        .signature-area {

            margin-top: 50px;

            padding-top: 20px;

            border-top: 1px solid #ccc;

            display: flex;

            justify-content: space-around;

            text-align: center;

        }

        .signature-box {

            width: 45%;

            padding: 10px;

            border: 1px dashed #aaa;

            min-height: 80px;

            display: flex;

            flex-direction: column;

            justify-content: flex-end;

            align-items: center;

        }

        .signature-line {

            border-top: 1px solid #000;

            width: 80%;

            margin-top: 40px; /* Espaço para a assinatura */

        }

        .no-print {

            text-align: center;

            margin-top: 20px;

        }

        .btn {
            padding: 8px 16px;
            margin: 5px;
            font-size: 14px;
            border-radius: 4px;
            cursor: pointer;
            border: 1px solid transparent;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            background-color: #007bff;
            color: white;
        }

        .btn:hover {
            background-color: #0056b3;
        }

        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background-color: #545b62;
        }

        @media print {

            .no-print {

                display: none;

            }

            body {

                margin: 0;

                padding: 0;

            }

            .print-container {

                border: none;

                box-shadow: none;

                padding: 0;

                margin: 0;

            }

        }

    </style>

</head>

<body<?php echo (isset($_GET['modal']) && $_GET['modal'] == '1') ? '' : ' onload="window.print()"'; ?>>

    <div class="print-container">

        <?php if (!empty($error_message)): ?>

            <p style="color: red;"><?php echo htmlspecialchars($error_message); ?></p>

        <?php elseif ($service_order): ?>

            <h2><img src="logo.png" height="40"><br /> Serviços de Ti - O.S #<?php echo htmlspecialchars($service_order['os_id']); ?></h2>



            <h2>Dados do Cliente</h2>

            <div class="info-item"><strong>Nome:</strong> <span><?php echo htmlspecialchars($service_order['client_name']); ?></span></div>

            <div class="info-item"><strong>Cnpj/Cpf:</strong> <span><?php echo htmlspecialchars($service_order['client_cnpj']) ? htmlspecialchars($service_order['client_cnpj']) : '___________________________________'; ?></span></div> <!-- NOVO CAMPO CNPJ -->

			<div class="info-item"><strong>Endereço:</strong> <span><?php echo htmlspecialchars($service_order['client_address']) ? htmlspecialchars($service_order['client_address']) : '___________________________________'; ?></span></div>						

			<div class="info-item"><strong>Telefone:</strong> <span><?php echo htmlspecialchars($service_order['client_phone']) ? htmlspecialchars($service_order['client_phone']) : '___________________________________'; ?></span></div>			

			<div class="info-item"><strong>Email:</strong> <span><?php echo htmlspecialchars($service_order['client_email']) ? htmlspecialchars($service_order['client_email']) : '___________________________________'; ?></span></div>

			

            <div class="section-break"></div>



            <h2>Detalhes da Ordem de Serviço</h2>

            <div class="info-item"><strong>Status:</strong> <span><?php echo htmlspecialchars($service_order['status']); ?></span></div>

            <div class="info-item"><strong>Abertura:</strong> <span><?php echo htmlspecialchars(date('d/m/Y', strtotime($service_order['open_date']))); ?></span></div>

            <div class="info-item"><strong>Fechamento:</strong> <span><?php echo $service_order['close_date'] ? htmlspecialchars(date('d/m/Y', strtotime($service_order['close_date']))) : '_____/_____/__________'; ?></span></div>

            <div class="info-item"><strong>Valor:</strong> <span>R$ <?php echo htmlspecialchars(number_format($service_order['value'], 2, ',', '.')); ?></span></div>

            <div class="info-item"><strong>Descrição:</strong> <span><?php echo nl2br(htmlspecialchars($service_order['description'])); ?></span></div>

			<div class="info-item"><strong>Solução:</strong> <span><?php echo $service_order['solution'] ? nl2br(htmlspecialchars($service_order['solution'])) : '___________________________________<br>___________________________________<br>___________________________________<br>___________________________________'; ?></span></div>

            



            <div class="section-break"></div>

			

            <div class="signature-area">

                <div class="signature-box">

                    <p>___________________________________</p>

                    <p>Assinatura do Técnico</p>

                </div>

                <div class="signature-box">

                    <p>___________________________________</p>

                    <p>Assinatura do Cliente</p>

                </div>

            </div>



        <?php endif; ?>




    </div>

</body>

</html>

