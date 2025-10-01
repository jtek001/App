<?php

// delete_service_order.php

// Exclui uma ordem de serviço existente



// Importante: A lógica de processamento e redirecionamento deve vir ANTES de qualquer output HTML.

require_once 'db_connect.php'; // Inclua a conexão com o banco de dados primeiro



if (isset($_GET["id"]) && !empty(trim($_GET["id"]))) {

    $id = trim($_GET["id"]);

    // Primeiro verifica se a OS pode ser excluída
    $check_sql = "SELECT status, IFNULL(payment_status, 'pendente') as payment_status FROM service_orders WHERE id = ?";
    if ($check_stmt = $mysqli->prepare($check_sql)) {
        $check_stmt->bind_param("i", $id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows == 1) {
            $os_data = $check_result->fetch_assoc();
            $status = $os_data['status'];
            $payment_status = $os_data['payment_status'];
            
            // Verifica se pode excluir
            if ($status === 'Concluída') {
                header("location: list_service_orders.php?error=1&message=" . urlencode("Esta Ordem não pode ser excluída!"));
                exit();
            }
            
            if ($payment_status === 'recebido' || $payment_status === 'faturado') {
                header("location: list_service_orders.php?error=1&message=" . urlencode("Esta Ordem não pode ser excluída!"));
                exit();
            }
        } else {
            header("location: list_service_orders.php?error=1&message=" . urlencode("Ordem de Serviço não encontrada!"));
            exit();
        }
        $check_stmt->close();
    } else {
        header("location: list_service_orders.php?error=1&message=" . urlencode("Erro ao verificar Ordem de Serviço!"));
        exit();
    }

    // Inicia transação para exclusão segura

    $mysqli->begin_transaction();

    

    try {

        // Primeiro exclui os itens da OS (se existirem)

        $sql_items = "DELETE FROM service_order_items WHERE service_order_id = ?";

        if ($stmt_items = $mysqli->prepare($sql_items)) {

            $stmt_items->bind_param("i", $id);

            $stmt_items->execute();

            $stmt_items->close();

        }

        

        // Depois exclui a ordem de serviço

        $sql = "DELETE FROM service_orders WHERE id = ?";

        if ($stmt = $mysqli->prepare($sql)) {

            $stmt->bind_param("i", $param_id);

            $param_id = $id;



            if ($stmt->execute()) {

                $mysqli->commit();

                

                // Redireciona com mensagem de sucesso

                header("location: list_service_orders.php?success=1&message=" . urlencode("Ordem de Serviço excluída com sucesso!"));

                exit();

            } else {

                throw new Exception("Erro ao excluir ordem de serviço: " . $stmt->error);

            }

            $stmt->close();

        } else {

            throw new Exception("Erro ao preparar exclusão da OS: " . $mysqli->error);

        }

        

    } catch (Exception $e) {

        $mysqli->rollback();

        

        // Redireciona com mensagem de erro

        header("location: list_service_orders.php?error=1&message=" . urlencode("Erro ao excluir OS: " . $e->getMessage()));

        exit();

    }

} else {

    // Se o ID no foi fornecido, redireciona para a lista de ordens de serviço

    header("location: list_service_orders.php");

    exit();

}

$mysqli->close();



// Não incluir header.php ou footer.php aqui, pois o script já deve ter redirecionado

// Se o script não redirecionar devido a um erro, a mensagem será exibida na página em branco.

// Para exibir com um layout, seria necessrio estruturar de forma diferente (ex: redirecionar para delete_confirm.php)

?>