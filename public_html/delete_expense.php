<?php
// delete_expense.php
// Exclui uma despesa existente

// Importante: A lógica de processamento e redirecionamento deve vir ANTES de qualquer output HTML.
require_once 'db_connect.php'; // Inclua a conexão com o banco de dados primeiro

if (isset($_GET["id"]) && !empty(trim($_GET["id"]))) {
    $id = trim($_GET["id"]);

    // Primeiro, verifica o status da despesa
    $check_sql = "SELECT status FROM expenses WHERE id = ?";
    
    if ($check_stmt = $mysqli->prepare($check_sql)) {
        $check_stmt->bind_param("i", $param_id);
        $param_id = $id;
        
        if ($check_stmt->execute()) {
            $result = $check_stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $status = $row['status'];
                
                // Verifica se a despesa está marcada como Pago
                if ($status === 'Pago') {
                    // Redireciona com mensagem de erro
                    $first_day = date('Y-m-01');
                    $last_day = date('Y-m-t');
                    header("location: list_expenses.php?error=paid_expense&start_date=" . $first_day . "&end_date=" . $last_day);
                    exit();
                }
                
                // Se não for Pago, procede com a exclusão
                $delete_sql = "DELETE FROM expenses WHERE id = ?";
                
                if ($delete_stmt = $mysqli->prepare($delete_sql)) {
                    $delete_stmt->bind_param("i", $param_id);
                    
                    if ($delete_stmt->execute()) {
                        // Redireciona para a lista de despesas após a exclusão com mensagem de sucesso
                        // Com filtro de data do primeiro e último dia do mês atual
                        $first_day = date('Y-m-01');
                        $last_day = date('Y-m-t');
                        header("location: list_expenses.php?status=deleted&start_date=" . $first_day . "&end_date=" . $last_day);
                        exit();
                    } else {
                        // Se houver um erro, redireciona com mensagem de erro
                        $first_day = date('Y-m-01');
                        $last_day = date('Y-m-t');
                        header("location: list_expenses.php?error=delete_failed&start_date=" . $first_day . "&end_date=" . $last_day);
                        exit();
                    }
                    $delete_stmt->close();
                } else {
                    $first_day = date('Y-m-01');
                    $last_day = date('Y-m-t');
                    header("location: list_expenses.php?error=delete_failed&start_date=" . $first_day . "&end_date=" . $last_day);
                    exit();
                }
            } else {
                // Despesa não encontrada
                $first_day = date('Y-m-01');
                $last_day = date('Y-m-t');
                header("location: list_expenses.php?error=not_found&start_date=" . $first_day . "&end_date=" . $last_day);
                exit();
            }
        } else {
            $first_day = date('Y-m-01');
            $last_day = date('Y-m-t');
            header("location: list_expenses.php?error=check_failed&start_date=" . $first_day . "&end_date=" . $last_day);
            exit();
        }
        $check_stmt->close();
    } else {
        $first_day = date('Y-m-01');
        $last_day = date('Y-m-t');
        header("location: list_expenses.php?error=check_failed&start_date=" . $first_day . "&end_date=" . $last_day);
        exit();
    }
} else {
    // Se o ID não foi fornecido, redireciona para a lista de despesas
    $first_day = date('Y-m-01');
    $last_day = date('Y-m-t');
    header("location: list_expenses.php?start_date=" . $first_day . "&end_date=" . $last_day);
    exit();
}
$mysqli->close();
?>
