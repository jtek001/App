<?php
// check_related_records.php
// Verifica se um registro possui registros relacionados antes da exclusão

header('Content-Type: application/json');
require_once 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['item_id']) || !isset($input['item_type'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Parâmetros obrigatórios não fornecidos']);
    exit;
}

$itemId = (int)$input['item_id'];
$itemType = $input['item_type'];

try {
    $hasRelated = false;
    $message = '';

    switch ($itemType) {
        case 'cliente':
        case 'client':
            // Verifica se o cliente tem ordens de serviço relacionadas
            $sql = "SELECT COUNT(*) as count FROM service_orders WHERE client_id = ?";
            $stmt = $mysqli->prepare($sql);
            $stmt->bind_param("i", $itemId);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();

            if ($row['count'] > 0) {
                $hasRelated = true;
                $message = "Este cliente possui {$row['count']} ordem(ns) de serviço relacionada(s) e não pode ser excluído.";
            }
            break;

        case 'produto':
        case 'product':
            // Verifica se o produto está sendo usado em ordens de serviço
            $sql = "SELECT COUNT(*) as count FROM service_order_items WHERE product_id = ?";
            $stmt = $mysqli->prepare($sql);
            $stmt->bind_param("i", $itemId);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();

            if ($row['count'] > 0) {
                $hasRelated = true;
                // Mensagem alinhada ao padrão solicitado
                $message = "Este produto possui {$row['count']} ordem(ns) de serviço relacionada(s) e não pode ser excluído.";
            }
            break;

        case 'ordem_servico':
        case 'service_order':
            // Ordens de serviço podem ser excluídas normalmente
            $hasRelated = false;
            break;

        default:
            // Para outros tipos, assume que pode ser excluído
            $hasRelated = false;
            break;
    }

    echo json_encode([
        'hasRelated' => $hasRelated,
        'message' => $message,
        'count' => isset($row['count']) ? (int)$row['count'] : 0
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Erro interno do servidor',
        'message' => $e->getMessage()
    ]);
} finally {
    $mysqli->close();
}
?>
