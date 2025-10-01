<?php
// remove_service_photo.php
// Remove uma foto individual dos serviços realizados

require_once 'db_connect.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['photo_id'])) {
    $photo_id = intval($_POST['photo_id']);
    
    if ($photo_id > 0) {
        // Busca o caminho da foto antes de remover
        $sql = "SELECT photo_path FROM service_order_photos WHERE id = ?";
        
        if ($stmt = $mysqli->prepare($sql)) {
            $stmt->bind_param("i", $photo_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                $photo_path = $row['photo_path'];
                
                // Remove do banco de dados
                $delete_sql = "DELETE FROM service_order_photos WHERE id = ?";
                if ($delete_stmt = $mysqli->prepare($delete_sql)) {
                    $delete_stmt->bind_param("i", $photo_id);
                    
                    if ($delete_stmt->execute()) {
                        // Remove o arquivo físico
                        if (file_exists($photo_path)) {
                            unlink($photo_path);
                        }
                        
                        $response['success'] = true;
                        $response['message'] = 'Foto removida com sucesso!';
                    } else {
                        $response['message'] = 'Erro ao remover foto do banco de dados.';
                    }
                    $delete_stmt->close();
                } else {
                    $response['message'] = 'Erro ao preparar consulta de remoção.';
                }
            } else {
                $response['message'] = 'Foto não encontrada.';
            }
            $stmt->close();
        } else {
            $response['message'] = 'Erro ao preparar consulta.';
        }
    } else {
        $response['message'] = 'ID da foto inválido.';
    }
} else {
    $response['message'] = 'Requisição inválida.';
}

$mysqli->close();
echo json_encode($response);
?>
