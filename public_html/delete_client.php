<?php
// delete_client.php
// Exclui um cliente existente

// Importante: A lógica de processamento e redirecionamento deve vir ANTES de qualquer output HTML.
require_once 'db_connect.php'; // Inclua a conexão com o banco de dados primeiro

if (isset($_GET["id"]) && !empty(trim($_GET["id"]))) {
    $id = trim($_GET["id"]);

    // Prepara uma declaração de exclusão
    // ON DELETE CASCADE no SQL garante que as OSs relacionadas também serão excluídas
    $sql = "DELETE FROM clients WHERE id = ?";

    if ($stmt = $mysqli->prepare($sql)) {
        $stmt->bind_param("i", $param_id);
        $param_id = $id;

        if ($stmt->execute()) {
            // Redireciona para a lista de clientes após a exclusão com mensagem de sucesso
            header("location: list_clients.php?status=deleted");
            exit(); // Garante que o script pare após o redirecionamento
        } else {
            // Se houver um erro, exiba uma mensagem ou redirecione para uma página de erro.
            echo "Ops! Algo deu errado ao excluir o cliente. Por favor, tente novamente mais tarde.";
        }
        $stmt->close();
    } else {
        echo "Erro ao preparar a declaração SQL (Delete Client): " . $mysqli->error;
    }
} else {
    // Se o ID não foi fornecido, redireciona para a lista de clientes
    header("location: list_clients.php");
    exit();
}
$mysqli->close();
?>