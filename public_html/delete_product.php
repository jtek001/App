<?php
// delete_product.php
// Exclui um produto (padrão igual ao de clientes: processa e redireciona)

require_once 'db_connect.php';

if (isset($_GET["id"]) && !empty(trim($_GET["id"]))) {
    $id = (int)trim($_GET["id"]);

    // Verifica se o produto está sendo usado em ordens de serviço
    $sql_related = "SELECT COUNT(*) AS count FROM service_order_items WHERE product_id = ?";
    if ($stmt_related = $mysqli->prepare($sql_related)) {
        $stmt_related->bind_param("i", $id);
        $stmt_related->execute();
        $result_related = $stmt_related->get_result();
        $row_related = $result_related->fetch_assoc();
        $related_count = (int)($row_related['count'] ?? 0);
        $stmt_related->close();

        if ($related_count > 0) {
            $mysqli->close();
            header("location: list_products.php?error=1&message=" . urlencode("Este produto está sendo usado em {$related_count} ordem(ns) de serviço e não pode ser excluído."));
            exit();
        }
    } else {
        $mysqli->close();
        header("location: list_products.php?error=1&message=" . urlencode("Erro ao verificar relações do produto."));
        exit();
    }

    // Exclui o produto
    $sql = "DELETE FROM products WHERE id = ?";
    if ($stmt = $mysqli->prepare($sql)) {
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $stmt->close();
            $mysqli->close();
            header("location: list_products.php?status=deleted");
            exit();
        } else {
            $stmt->close();
            $mysqli->close();
            header("location: list_products.php?error=1&message=" . urlencode("Erro ao excluir o produto. Tente novamente."));
            exit();
        }
    } else {
        $mysqli->close();
        header("location: list_products.php?error=1&message=" . urlencode("Erro ao preparar exclusão do produto."));
        exit();
    }
} else {
    $mysqli->close();
    header("location: list_products.php?error=1&message=" . urlencode("ID do produto não fornecido."));
    exit();
}
















