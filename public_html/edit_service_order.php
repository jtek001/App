<?php

// edit_service_order.php

// Formulário para editar uma ordem de serviço existente



require_once 'header.php';

require_once 'db_connect.php';



$id = $client_id = $description = $status = $value = $solution = $open_date = $close_date = $nfse_pdf_path = $payment_status = "";

$client_id_err = $description_err = "";

$success_message = "";

$error_message = "";

// Variável para controlar se os campos devem estar bloqueados
$is_payment_received = false;



// Variável para armazenar dados do cliente
$client_data = null;



// Obtém a lista de todos os produtos (serviços e produtos com estoque > 0)

$products_query = "SELECT id, name, price, category FROM products WHERE status = 'Ativo' AND stock_quantity > 0 ORDER BY category, name ASC";

$products_result = $mysqli->query($products_query);



// Processa dados do formulário quando ele é enviado ou carrega dados para edição

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $id = $_POST["id"];
    
    // Verifica se o pagamento foi recebido antes de processar a edição
    $check_payment_sql = "SELECT IFNULL(payment_status, 'pendente') as payment_status FROM service_orders WHERE id = ?";
    if ($check_stmt = $mysqli->prepare($check_payment_sql)) {
        $check_stmt->bind_param("i", $id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        if ($check_result->num_rows == 1) {
            $check_row = $check_result->fetch_assoc();
            if ($check_row['payment_status'] === 'recebido') {
                $error_message = "Não é possível editar uma Ordem de Serviço com pagamento RECEBIDO.";
                $check_stmt->close();
                $mysqli->close();
                // Recarrega a página para mostrar o bloqueio
                header("Location: edit_service_order.php?id=" . $id);
                exit();
            }
        }
        $check_stmt->close();
    }



    // Valida o ID do cliente

    if (empty(trim($_POST["client_id"]))) {

        $client_id_err = "Por favor, selecione um cliente.";

    } else {

        $client_id = trim($_POST["client_id"]);

    }



    // Valida a descrição

    if (empty(trim($_POST["description"]))) {

        $description_err = "Por favor, insira a descrição da Ordem de Serviço.";

    } else {

        $description = trim($_POST["description"]);

    }



    $status = trim($_POST["status"]);

    $open_date = trim($_POST["open_date"]);

    $close_date = trim($_POST["close_date"]);



    // Valida e sanitiza o valor

    $value = filter_input(INPUT_POST, 'value', FILTER_VALIDATE_FLOAT, FILTER_FLAG_ALLOW_FRACTION | FILTER_FLAG_ALLOW_THOUSAND);

    if ($value === false || $value < 0) {

        $value = 0.00; // Define um valor padrão seguro ou lida com o erro

    }



    $solution = trim($_POST["solution"]); // Obtém o valor do campo solução

    

    // Obtém os itens selecionados (formato JSON)

    $items_json = isset($_POST["items_json"]) ? $_POST["items_json"] : "[]";

    $items = json_decode($items_json, true);
    
    // Processa upload de fotos dos serviços realizados
    $uploaded_photos = [];
    $photos_dir = 'service_photos/';
    
    // Cria o diretório se não existir
    if (!is_dir($photos_dir)) {
        mkdir($photos_dir, 0755, true);
    }
    
    // Processa cada foto enviada
    if (isset($_FILES['service_photos']) && !empty($_FILES['service_photos']['name'][0])) {
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        $max_file_size = 5 * 1024 * 1024; // 5MB por foto
        $max_photos = 6;
        
        $file_count = count($_FILES['service_photos']['name']);
        
        // Verifica limite de fotos
        if ($file_count > $max_photos) {
            $error_message = "Máximo de {$max_photos} fotos permitidas por OS.";
        } else {
            for ($i = 0; $i < $file_count; $i++) {
                if ($_FILES['service_photos']['error'][$i] == UPLOAD_ERR_OK) {
                    $file_type = $_FILES['service_photos']['type'][$i];
                    $file_size = $_FILES['service_photos']['size'][$i];
                    $file_name = $_FILES['service_photos']['name'][$i];
                    
                    // Valida tipo de arquivo
                    if (!in_array($file_type, $allowed_types)) {
                        $error_message = "Tipo de arquivo não permitido: " . $file_name . ". Apenas JPG, PNG, GIF e WebP são aceitos.";
                        break;
                    }
                    
                    // Valida tamanho do arquivo
                    if ($file_size > $max_file_size) {
                        $error_message = "Arquivo muito grande: " . $file_name . ". Tamanho máximo: 5MB.";
                        break;
                    }
                    
                    // Gera nome único para o arquivo
                    $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
                    $new_filename = 'photo_' . $id . '_' . time() . '_' . $i . '.' . $file_extension;
                    $upload_path = $photos_dir . $new_filename;
                    
                    // Move o arquivo para o diretório de destino
                    if (move_uploaded_file($_FILES['service_photos']['tmp_name'][$i], $upload_path)) {
                        $uploaded_photos[] = [
                            'path' => $upload_path,
                            'name' => $file_name
                        ];
                    } else {
                        $error_message = "Erro ao fazer upload da foto: " . $file_name;
                        break;
                    }
                }
            }
        }
    }

    



    

    // Debug: verificar se os itens estão sendo recebidos corretamente

    if (!is_array($items)) {

        $items = [];

    }



    // Se o status for "Concluída" e a data de fechamento estiver vazia, define para a data/hora atual

    if ($status == 'Concluída' && empty($close_date)) {

        $close_date = date('Y-m-d H:i:s');

    } elseif ($status != 'Concluída' && !empty($close_date)) {

        // Se o status não for "Concluída" mas a data de fechamento estiver preenchida, limpa-a

        $close_date = NULL;

    } elseif ($status != 'Concluída' && empty($close_date)) {

        $close_date = NULL; // Garante que seja NULL se não for concluída e não houver data

    }





    // Verifica erros de entrada antes de atualizar no banco de dados

    if (empty($client_id_err) && empty($description_err)) {

        // Inicia transação

        $mysqli->begin_transaction();

        

        try {

            // Verifica se as colunas de faturamento existem
            $check_columns = "SHOW COLUMNS FROM service_orders LIKE 'payment_value'";
            $column_exists = $mysqli->query($check_columns);
            
            // Atualiza a ordem de serviço
            if ($column_exists && $column_exists->num_rows > 0) {
                // Com colunas de faturamento - atualiza payment_value junto
                $sql = "UPDATE service_orders SET client_id = ?, description = ?, status = ?, value = ?, payment_value = ?, solution = ?, open_date = ?, close_date = ? WHERE id = ?";
            } else {
                // Sem colunas de faturamento
                $sql = "UPDATE service_orders SET client_id = ?, description = ?, status = ?, value = ?, solution = ?, open_date = ?, close_date = ? WHERE id = ?";
            }

            if ($stmt = $mysqli->prepare($sql)) {
                if ($column_exists && $column_exists->num_rows > 0) {
                    $stmt->bind_param("issddsssi", $param_client_id, $param_description, $param_status, $param_value, $param_value, $param_solution, $param_open_date, $param_close_date, $param_id);
                } else {
                    $stmt->bind_param("issdsssi", $param_client_id, $param_description, $param_status, $param_value, $param_solution, $param_open_date, $param_close_date, $param_id);
                }



                $param_client_id = $client_id;

                $param_description = $description;

                $param_status = $status;

                $param_value = $value; // Atribui o valor sanitizado

                $param_solution = $solution; // Atribui a solução

                $param_open_date = $open_date;

                $param_close_date = $close_date; // Pode ser NULL

                $param_id = $id;



                if ($stmt->execute()) {

                    // Remove todos os itens existentes da OS

                    $sql_delete = "DELETE FROM service_order_items WHERE service_order_id = ?";

                    if ($stmt_delete = $mysqli->prepare($sql_delete)) {

                        $stmt_delete->bind_param("i", $id);

                        $stmt_delete->execute();

                        $stmt_delete->close();

                    }

                    

                    // Insere os novos itens da ordem de serviço

                    if (!empty($items) && is_array($items)) {

                        $sql_items = "INSERT INTO service_order_items (service_order_id, product_id, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?)";

                        

                        if ($stmt_items = $mysqli->prepare($sql_items)) {

                            foreach ($items as $item) {

                                // Validação dos dados do item

                                if (!isset($item['product_id'], $item['quantity'], $item['unit_price'])) {

                                    continue; // Pula item inválido

                                }

                                

                                $product_id = intval($item['product_id']);

                                $quantity = intval($item['quantity']);

                                $unit_price = floatval($item['unit_price']);

                                $total_price = $quantity * $unit_price;

                                

                                if ($product_id > 0 && $quantity > 0 && $unit_price >= 0) {

                                    $stmt_items->bind_param("iiidd", $id, $product_id, $quantity, $unit_price, $total_price);

                                    if (!$stmt_items->execute()) {

                                        throw new Exception("Erro ao inserir item: " . $stmt_items->error);

                                    }

                                }

                            }

                            $stmt_items->close();

                        } else {

                            throw new Exception("Erro ao preparar inserção de itens");

                        }

                    }
                    
                    // Salva as fotos dos serviços realizados
                    if (!empty($uploaded_photos)) {
                        $sql_photos = "INSERT INTO service_order_photos (service_order_id, photo_path, photo_name) VALUES (?, ?, ?)";
                        
                        if ($stmt_photos = $mysqli->prepare($sql_photos)) {
                            foreach ($uploaded_photos as $photo) {
                                $stmt_photos->bind_param("iss", $id, $photo['path'], $photo['name']);
                                if (!$stmt_photos->execute()) {
                                    throw new Exception("Erro ao salvar foto: " . $stmt_photos->error);
                                }
                            }
                            $stmt_photos->close();
                        } else {
                            throw new Exception("Erro ao preparar inserção de fotos");
                        }
                    }

                    

                    $mysqli->commit();

                    // Redireciona para a lista de OS após salvar com sucesso

                    header("Location: list_service_orders.php?success=1&message=" . urlencode("Ordem de Serviço atualizada com sucesso!"));

                    exit();

                } else {

                    throw new Exception("Erro ao atualizar ordem de serviço");

                }

                

                $stmt->close();

            } else {

                throw new Exception("Erro ao preparar consulta");

            }

            

        } catch (Exception $e) {

            $mysqli->rollback();

            $error_message = "Erro ao atualizar Ordem de Serviço: " . $e->getMessage();
            
            // Debug adicional
            error_log("Erro na edição da OS: " . $e->getMessage());
            error_log("SQL Error (se houver): " . $mysqli->error);

        }

    }

    $mysqli->close(); // Fecha a conexão após a operação

} else {

    // Se a requisição for GET (para carregar o formulário de edição)

    if (isset($_GET["id"]) && !empty(trim($_GET["id"]))) {

        $id = trim($_GET["id"]);



        $sql = "SELECT client_id, description, status, value, solution, open_date, close_date, nfse_pdf_path, IFNULL(payment_status, 'pendente') as payment_status FROM service_orders WHERE id = ?";

        if ($stmt = $mysqli->prepare($sql)) {

            $stmt->bind_param("i", $param_id);

            $param_id = $id;



            if ($stmt->execute()) {

                $result = $stmt->get_result();

                if ($result->num_rows == 1) {

                    $row = $result->fetch_assoc();

                    $client_id = $row["client_id"];

                    $description = $row["description"];

                    $status = $row["status"];

                    $value = $row["value"]; // Obtém o valor

                    $solution = $row["solution"]; // Obtém a solução

                    $open_date = date('Y-m-d\TH:i', strtotime($row["open_date"])); // Formato para input datetime-local

                    $close_date = $row["close_date"] ? date('Y-m-d\TH:i', strtotime($row["close_date"])) : '';

                    $nfse_pdf_path = $row["nfse_pdf_path"]; // Obtém o caminho do PDF da NFSe
                    $payment_status = $row["payment_status"]; // Obtém o status do pagamento
                    
                    // Verifica se o pagamento foi recebido para bloquear edição
                    $is_payment_received = ($payment_status === 'recebido');

                    // Obtém os dados do cliente específico da OS
                    $client_query = "SELECT c.id, c.name, c.cnpj FROM clients c 
                                     JOIN service_orders so ON c.id = so.client_id 
                                     WHERE so.id = ?";
                    $client_stmt = $mysqli->prepare($client_query);
                    $client_stmt->bind_param("i", $id);
                    $client_stmt->execute();
                    $client_result = $client_stmt->get_result();
                    $client_data = $client_result->fetch_assoc();
                    $client_stmt->close();

                    // Carrega os itens existentes da OS

                    $existing_items = [];

                    



                    $sql_items = "SELECT soi.product_id, soi.quantity, soi.unit_price, soi.total_price, p.name as product_name 

                                  FROM service_order_items soi 

                                  JOIN products p ON soi.product_id = p.id 

                                  WHERE soi.service_order_id = ?";

                    

                    if ($stmt_items = $mysqli->prepare($sql_items)) {

                        $stmt_items->bind_param("i", $id);

                        $stmt_items->execute();

                        $result_items = $stmt_items->get_result();

                        

                        while ($item_row = $result_items->fetch_assoc()) {

                            $existing_items[] = [

                                'product_id' => intval($item_row['product_id']),

                                'product_name' => $item_row['product_name'],

                                'quantity' => intval($item_row['quantity']),

                                'unit_price' => floatval($item_row['unit_price']),

                                'total_price' => floatval($item_row['total_price'])

                            ];

                        }

                        

                        $stmt_items->close();

                        



                    }
                    
                    // Carrega as fotos existentes da OS
                    $existing_photos = [];
                    $sql_photos = "SELECT id, photo_path, photo_name, uploaded_at FROM service_order_photos WHERE service_order_id = ? ORDER BY uploaded_at ASC";
                    
                    if ($stmt_photos = $mysqli->prepare($sql_photos)) {
                        $stmt_photos->bind_param("i", $id);
                        $stmt_photos->execute();
                        $result_photos = $stmt_photos->get_result();
                        
                        while ($photo_row = $result_photos->fetch_assoc()) {
                            $existing_photos[] = [
                                'id' => intval($photo_row['id']),
                                'path' => $photo_row['photo_path'],
                                'name' => $photo_row['photo_name'],
                                'uploaded_at' => $photo_row['uploaded_at']
                            ];
                        }
                        
                        $stmt_photos->close();
                    }

                } else {

                    $error_message = "Ordem de Serviço não encontrada.";

                }

            } else {

                $error_message = "Ops! Algo deu errado. Por favor, tente novamente mais tarde.";

            }

            $stmt->close();

        }

    } else {

        // ID não fornecido, redireciona para a lista de ordens de serviço

        header("location: list_service_orders.php");

        exit();

    }

    $mysqli->close(); // Fecha a conexão após a consulta inicial

}

?>



<h2>Editar Ordem de Serviço</h2>

<?php if ($is_payment_received): ?>
    <div class="alert-message alert-warning" style="background-color: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
        <strong>⚠️ ATENÇÃO:</strong> Esta Ordem de Serviço está concluída e encerrada. Não é possível alterar informações.
    </div>
<?php endif; ?>

<?php if (!empty($success_message)): ?>

    <div class="alert-message alert-success"><?php echo $success_message; ?></div>

<?php endif; ?>

<?php if (!empty($error_message)): ?>

    <div class="alert-message alert-error"><?php echo $error_message; ?></div>

<?php endif; ?>



<form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data">

    <input type="hidden" name="id" value="<?php echo htmlspecialchars($id); ?>">

    <div class="form-group">

        <label>Cliente: <span style="color: red;">*</span></label>

        <input type="text" value="<?php echo $client_data ? htmlspecialchars($client_data['name']) : 'Cliente não encontrado'; ?>" readonly style="background-color: #f5f5f5;">

        <input type="hidden" name="client_id" value="<?php echo $client_data ? htmlspecialchars($client_data['id']) : ''; ?>">

        <span class="help-block" style="color: red;"><?php echo $client_id_err; ?></span>

    </div>

    <div class="form-group">

        <label>CNPJ/CPF:</label>

        <input type="text" value="<?php echo $client_data ? htmlspecialchars($client_data['cnpj']) : ''; ?>" readonly style="background-color: #f5f5f5;">

    </div>

    <?php if (!empty($nfse_pdf_path) && file_exists($nfse_pdf_path)): ?>
    <div class="form-group">

        <label>PDF da NFSe:</label>

        <div style="border: 1px solid #ddd; border-radius: 4px; padding: 10px; background-color: #f9f9f9;">
            <button type="button" onclick="openPdfLightbox('<?php echo htmlspecialchars($nfse_pdf_path); ?>', '<?php echo htmlspecialchars(basename($nfse_pdf_path)); ?>')" style="display: inline-flex; align-items: center; text-decoration: none; color: #007bff; font-weight: bold; background: none; border: none; cursor: pointer; padding: 0; font-size: inherit;">
                📄 Visualizar DANFSe
            </button>
            <br>
          
        </div>

    </div>
    <?php endif; ?>

    <div class="form-group">

        <label>Descrição: <span style="color: red;">*</span></label>

        <textarea name="description" <?php echo $is_payment_received ? 'readonly style="background-color: #f5f5f5; cursor: not-allowed;"' : ''; ?>><?php echo htmlspecialchars($description); ?></textarea>

        <span class="help-block" style="color: red;"><?php echo $description_err; ?></span>

    </div>

    <div class="form-group">

        <label>Status:</label>

        <select name="status" <?php echo $is_payment_received ? 'disabled style="background-color: #f5f5f5; cursor: not-allowed;"' : ''; ?>>

            <option value="Pendente" <?php echo ($status == 'Pendente') ? 'selected' : ''; ?>>Pendente</option>

            <option value="Em Andamento" <?php echo ($status == 'Em Andamento') ? 'selected' : ''; ?>>Em Andamento</option>

            <option value="Concluída" <?php echo ($status == 'Concluída') ? 'selected' : ''; ?>>Concluída</option>

            <option value="Cancelada" <?php echo ($status == 'Cancelada') ? 'selected' : ''; ?>>Cancelada</option>

        </select>
        
        <?php if ($is_payment_received): ?>
        <input type="hidden" name="status" value="<?php echo htmlspecialchars($status); ?>">
        <?php endif; ?>

    </div>

    <div class="form-group">

        <label>Solução:</label>

        <textarea name="solution" <?php echo $is_payment_received ? 'readonly style="background-color: #f5f5f5; cursor: not-allowed;"' : ''; ?>><?php echo htmlspecialchars($solution); ?></textarea>

    </div>
    
    <!-- Seção de Fotos dos Serviços Realizados -->
    <div class="form-group">
        <label>Fotos dos Serviços Realizados:</label>
        
        <!-- Upload de novas fotos -->
        <div style="margin-bottom: 20px; padding: 15px; border: 2px dashed #ddd; border-radius: 8px; background: #f9f9f9; <?php echo $is_payment_received ? 'opacity: 0.6; pointer-events: none;' : ''; ?>">
            <input type="file" name="service_photos[]" id="service_photos" multiple accept="image/*" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" <?php echo $is_payment_received ? 'disabled' : ''; ?>>
            <small style="color: #666; display: block; margin-top: 5px;">
                📸 Selecione até 6 fotos (JPG, PNG, GIF, WebP). Tamanho máximo: 5MB por foto.
            </small>
            <div id="photo-preview" style="margin-top: 10px; display: none;">
                <h4 style="margin: 0 0 10px 0; color: #333;">Pré-visualização:</h4>
                <div id="preview-container" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 10px;"></div>
            </div>
        </div>
        
        <!-- Galeria de fotos existentes -->
        <div id="existing-photos" style="margin-top: 20px;">
            <h4 style="margin: 0 0 15px 0; color: #333;">Fotos Atuais:</h4>
            <div id="photos-gallery" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px;">
                <?php if (!empty($existing_photos)): ?>
                    <?php foreach ($existing_photos as $photo): ?>
                        <div class="photo-item" data-photo-id="<?php echo $photo['id']; ?>" style="position: relative; border: 1px solid #ddd; border-radius: 8px; overflow: hidden; background: white; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                            <img src="<?php echo htmlspecialchars($photo['path']); ?>" alt="<?php echo htmlspecialchars($photo['name']); ?>" style="width: 100%; height: 150px; object-fit: cover; cursor: pointer;" onclick="openPhotoLightbox('<?php echo htmlspecialchars($photo['path']); ?>', '<?php echo htmlspecialchars($photo['name']); ?>')">
                            <div style="padding: 10px;">
                                <p style="margin: 0; font-size: 12px; color: #666; word-break: break-all;"><?php echo htmlspecialchars($photo['name']); ?></p>
                                <p style="margin: 5px 0 0 0; font-size: 11px; color: #999;"><?php echo date('d/m/Y H:i', strtotime($photo['uploaded_at'])); ?></p>
                            </div>
                            <?php if (!$is_payment_received): ?>
                            <button type="button" onclick="removePhoto(<?php echo $photo['id']; ?>)" style="position: absolute; top: 5px; right: 5px; background: #dc3545; color: white; border: none; border-radius: 50%; width: 25px; height: 25px; cursor: pointer; font-size: 12px; display: flex; align-items: center; justify-content: center;">×</button>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color: #666; text-align: center; padding: 20px; grid-column: 1 / -1;">Nenhuma foto adicionada ainda</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="form-group">

        <label>Adicionar Produtos/Serviços:</label>

        <div style="display: flex; gap: 10px; margin-bottom: 10px; flex-wrap: wrap; <?php echo $is_payment_received ? 'opacity: 0.6; pointer-events: none;' : ''; ?>">

            <select id="product_select" style="flex: 2; min-width: 200px; font-size: 13px;" <?php echo $is_payment_received ? 'disabled' : ''; ?>>

                <option value="">Selecione um produto ou serviço</option>

                <?php

                if ($products_result && $products_result->num_rows > 0) {

                    $current_category = '';

                    while ($row = $products_result->fetch_assoc()) {

                        if ($current_category != $row['category']) {

                            if ($current_category != '') echo '</optgroup>';

                            echo '<optgroup label="' . htmlspecialchars($row['category']) . '">';

                            $current_category = $row['category'];

                        }

                        echo '<option value="' . htmlspecialchars($row['id']) . '" data-price="' . $row['price'] . '" data-name="' . htmlspecialchars($row['name']) . '">';

                        echo htmlspecialchars($row['name']) . ' - R$ ' . number_format($row['price'], 2, ',', '.');

                        echo '</option>';

                    }

                    if ($current_category != '') echo '</optgroup>';

                } else {

                    echo '<option value="">Nenhum produto cadastrado</option>';

                }

                ?>

            </select>

            <input type="number" id="quantity_input" placeholder="Qtd" min="1" value="1" style="width: 80px;" <?php echo $is_payment_received ? 'disabled' : ''; ?>>

            <button type="button" onclick="addItem()" class="btn" style="white-space: nowrap;" <?php echo $is_payment_received ? 'disabled' : ''; ?>>Adicionar Item</button>
            
            <?php if ($is_payment_received): ?>
            <input type="hidden" name="items_json" value="<?php echo htmlspecialchars(json_encode(isset($existing_items) ? $existing_items : [])); ?>">
            <?php endif; ?>

        </div>

        <div id="selected_price" style="font-size: 12px; color: #28a745; margin-bottom: 10px; font-weight: bold;"></div>

    </div>

    <div class="form-group">

        <label>Itens Selecionados:</label>

        <div id="items_list" style="border: 1px solid #ddd; border-radius: 4px; padding: 10px; min-height: 50px; background-color: #f9f9f9; font-size: 13px;">

            <p style="color: #666; margin: 0; text-align: center;">Carregando itens...</p>

        </div>

    </div>

    <div class="form-group">

        <label>Valor Total (R$):</label>

        <input type="number" name="value" id="total_value" step="0.01" min="0" value="<?php echo htmlspecialchars($value); ?>" <?php echo $is_payment_received ? 'readonly style="background-color: #f5f5f5; cursor: not-allowed;"' : ''; ?>>

        <input type="hidden" name="items_json" id="items_json" value="[]">

        <div style="font-size: 12px; color: #666; margin-top: 5px;">

            <span>Valor calculado: R$ <span id="calculated_value">0,00</span></span>
            <br>
            <span style="font-style: italic;">💡 Dica: Você pode editar o valor manualmente para aplicar descontos ou ajustes.</span>

        </div>

    </div>

    <div class="form-group">

        <label>Data de Abertura:</label>

        <input type="datetime-local" name="open_date" value="<?php echo htmlspecialchars($open_date); ?>" <?php echo $is_payment_received ? 'readonly style="background-color: #f5f5f5; cursor: not-allowed;"' : ''; ?>>
        
        <?php if ($is_payment_received): ?>
        <input type="hidden" name="open_date" value="<?php echo htmlspecialchars($open_date); ?>">
        <?php endif; ?>

    </div>

    <div class="form-group">

        <label>Data de Fechamento:</label>

        <input type="datetime-local" name="close_date" value="<?php echo htmlspecialchars($close_date); ?>" <?php echo $is_payment_received ? 'readonly style="background-color: #f5f5f5; cursor: not-allowed;"' : ''; ?>>
        
        <?php if ($is_payment_received): ?>
        <input type="hidden" name="close_date" value="<?php echo htmlspecialchars($close_date); ?>">
        <?php endif; ?>

        <small style="color: #666;">Preencher automaticamente ao mudar o status para 'Concluída' se estiver vazio.</small>

    </div>

    <div class="form-actions">

        <?php if (!$is_payment_received): ?>
        <input type="submit" class="btn" value="Salvar Alterações">
        <?php endif; ?>

        <a href="list_service_orders.php" class="btn btn-secondary"><?php echo $is_payment_received ? 'Voltar' : 'Cancelar'; ?></a>

        <button type="button" class="btn" onclick="openPrintModal(<?php echo htmlspecialchars($id); ?>)">Imprimir OS</button>

    </div>

</form>

<!-- Lightbox para visualização de fotos -->
<div id="photo-lightbox" class="photo-lightbox">
    <div class="photo-lightbox-content">
        <div class="photo-lightbox-header">
            <h3 id="photo-title">Visualizar Foto</h3>
            <button onclick="closePhotoLightbox()" class="photo-lightbox-close">&times;</button>
        </div>
        <div class="photo-lightbox-body">
            <button id="prev-photo" onclick="previousPhoto()" class="photo-nav-btn photo-nav-prev" style="display: none;">‹</button>
            <img id="photo-viewer" src="" alt="">
            <button id="next-photo" onclick="nextPhoto()" class="photo-nav-btn photo-nav-next" style="display: none;">›</button>
        </div>
        <div class="photo-lightbox-footer">
            <div id="photo-counter" style="flex: 1; text-align: center; color: #666; font-size: 14px;"></div>
            <button onclick="downloadPhoto()" class="btn">⬇️ Download</button>
        </div>
    </div>
</div>

<!-- Modal para impressão da OS -->
<div id="printModal" class="print-modal">
    <div class="print-modal-content">
        <div class="print-modal-header">
            <button type="button" class="print-modal-close" onclick="closePrintModal()">&times;</button>
            <h3>Imprimir Ordem de Serviço</h3>
        </div>
        <div class="print-modal-body">
            <iframe id="printFrame" src="" frameborder="0"></iframe>
        </div>
        <div class="print-modal-footer">
            <button type="button" class="btn" onclick="printOrder()">Imprimir</button>
            <button type="button" class="btn btn-secondary" onclick="closePrintModal()">Fechar</button>
        </div>
    </div>
</div>

<!-- Modal para visualização de PDF da NFSe -->
<div id="pdf-lightbox" class="pdf-lightbox">
    <div class="pdf-lightbox-content">
        <div class="pdf-lightbox-header">
            <h3 id="pdf-title">Visualizar PDF</h3>
            <button onclick="closePdfLightbox()" class="pdf-lightbox-close">&times;</button>
        </div>
        <div class="pdf-lightbox-body">
            <iframe id="pdf-viewer" src="" frameborder="0"></iframe>
        </div>
        <div class="pdf-lightbox-footer">
            <button onclick="printPdf()" class="btn">🖨️ Imprimir</button>
            <button onclick="downloadPdf()" class="btn btn-secondary">⬇️ Download</button>
        </div>
    </div>
</div>

<?php

require_once 'footer.php';

?>

<style>
/* Estilos para o modal de impressão */
.print-modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
}

.print-modal-content {
    position: relative;
    background-color: #fefefe;
    margin: 2% auto;
    padding: 0;
    border: 1px solid #888;
    border-radius: 8px;
    width: 90%;
    height: 90%;
    max-width: 1200px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    display: flex;
    flex-direction: column;
}

.print-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    background-color: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
    border-radius: 8px 8px 0 0;
}

.print-modal-header h3 {
    margin: 0;
    color: #333;
    font-size: 18px;
}

.print-modal-close {
    background: none;
    border: none;
    font-size: 28px;
    font-weight: bold;
    color: #aaa;
    cursor: pointer;
    line-height: 1;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.print-modal-close:hover,
.print-modal-close:focus {
    color: #000;
    text-decoration: none;
}

.print-modal-body {
    flex: 1;
    padding: 0;
    overflow: hidden;
}

.print-modal-body iframe {
    width: 100%;
    height: 100%;
    border: none;
}

.print-modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding: 15px 20px;
    background-color: #f8f9fa;
    border-top: 1px solid #dee2e6;
    border-radius: 0 0 8px 8px;
}

.print-modal-footer .btn {
    padding: 8px 16px;
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

.print-modal-footer .btn:hover {
    background-color: #0056b3;
}

.print-modal-footer .btn-secondary {
    background-color: #6c757d;
    color: white;
}

.print-modal-footer .btn-secondary:hover {
    background-color: #545b62;
}

/* Estilos para o lightbox de fotos */
.photo-lightbox {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
}

.photo-lightbox-content {
    position: relative;
    background-color: #fefefe;
    margin: 2% auto;
    padding: 0;
    border: 1px solid #888;
    border-radius: 8px;
    width: 90%;
    height: 90%;
    max-width: 1200px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    display: flex;
    flex-direction: column;
}

.photo-lightbox-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    background-color: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
    border-radius: 8px 8px 0 0;
}

.photo-lightbox-header h3 {
    margin: 0;
    color: #333;
    font-size: 18px;
}

.photo-lightbox-close {
    background: none;
    border: none;
    font-size: 28px;
    font-weight: bold;
    color: #aaa;
    cursor: pointer;
    line-height: 1;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.photo-lightbox-close:hover,
.photo-lightbox-close:focus {
    color: #000;
    text-decoration: none;
}

.photo-lightbox-body {
    flex: 1;
    padding: 20px;
    overflow: auto;
    display: flex;
    align-items: center;
    justify-content: center;
}

.photo-lightbox-body img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
    border-radius: 4px;
}

.photo-nav-btn {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(0, 0, 0, 0.7);
    color: white;
    border: none;
    font-size: 24px;
    font-weight: bold;
    width: 50px;
    height: 50px;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10;
    transition: background-color 0.3s ease;
}

.photo-nav-btn:hover {
    background: rgba(0, 0, 0, 0.9);
}

.photo-nav-prev {
    left: 20px;
}

.photo-nav-next {
    right: 20px;
}

.photo-lightbox-footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding: 15px 20px;
    background-color: #f8f9fa;
    border-top: 1px solid #dee2e6;
    border-radius: 0 0 8px 8px;
}

/* Estilos para o lightbox de PDF */
.pdf-lightbox {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
}

.pdf-lightbox-content {
    position: relative;
    background-color: #fefefe;
    margin: 2% auto;
    padding: 0;
    border: 1px solid #888;
    border-radius: 8px;
    width: 90%;
    height: 90%;
    max-width: 1200px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    display: flex;
    flex-direction: column;
}

.pdf-lightbox-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    background-color: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
    border-radius: 8px 8px 0 0;
}

.pdf-lightbox-header h3 {
    margin: 0;
    color: #333;
    font-size: 18px;
}

.pdf-lightbox-close {
    background: none;
    border: none;
    font-size: 28px;
    font-weight: bold;
    color: #aaa;
    cursor: pointer;
    line-height: 1;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.pdf-lightbox-close:hover,
.pdf-lightbox-close:focus {
    color: #000;
    text-decoration: none;
}

.pdf-lightbox-body {
    flex: 1;
    padding: 0;
    overflow: hidden;
}

.pdf-lightbox-body iframe {
    width: 100%;
    height: 100%;
    border: none;
}

.pdf-lightbox-footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding: 15px 20px;
    background-color: #f8f9fa;
    border-top: 1px solid #dee2e6;
    border-radius: 0 0 8px 8px;
}

.pdf-lightbox-footer .btn {
    padding: 8px 16px;
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

.pdf-lightbox-footer .btn:hover {
    background-color: #0056b3;
}

.pdf-lightbox-footer .btn-secondary {
    background-color: #6c757d;
    color: white;
}

.pdf-lightbox-footer .btn-secondary:hover {
    background-color: #545b62;
}
</style>

<script>
// Array para armazenar os itens selecionados
let selectedItems = [];

// Itens existentes carregados do servidor
const existingItems = <?php echo json_encode(isset($existing_items) ? $existing_items : []); ?>;

// Verifica se o pagamento foi recebido para bloquear funcionalidades
const isPaymentReceived = <?php echo $is_payment_received ? 'true' : 'false'; ?>;

// DEBUG TEMPORÁRIO - REMOVER APÓS CORRIGIR
console.log('🐛 DEBUG: existingItems carregados do PHP:', existingItems);
console.log('🐛 DEBUG: Quantidade de existingItems:', existingItems.length);

// Função para formatar números em formato brasileiro
function formatCurrency(value) {
    return parseFloat(value).toLocaleString('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

// Função para mostrar o preço do produto selecionado
function updateSelectedPrice() {
    const productSelect = document.getElementById('product_select');
    const selectedPriceDiv = document.getElementById('selected_price');
    const quantityInput = document.getElementById('quantity_input');
    
    if (productSelect.value && productSelect.selectedIndex > 0) {
        const selectedOption = productSelect.options[productSelect.selectedIndex];
        const price = parseFloat(selectedOption.getAttribute('data-price'));
        const quantity = parseInt(quantityInput.value) || 1;
        const total = price * quantity;
        
        selectedPriceDiv.innerHTML = `Preço unitário: R$ ${formatCurrency(price)} | Quantidade: ${quantity} | Total: R$ ${formatCurrency(total)}`;
    } else {
        selectedPriceDiv.innerHTML = '';
    }
}

// Função para adicionar item à lista
function addItem() {
    if (isPaymentReceived) {
        alert('Não é possível adicionar itens em uma OS com pagamento RECEBIDO.');
        return;
    }
    
    const productSelect = document.getElementById('product_select');
    const quantityInput = document.getElementById('quantity_input');
    
    if (!productSelect.value) {
        alert('Por favor, selecione um produto ou serviço.');
        return;
    }
    
    const quantity = parseInt(quantityInput.value) || 1;
    if (quantity < 1) {
        alert('Por favor, insira uma quantidade válida.');
        return;
    }
    
    const selectedOption = productSelect.options[productSelect.selectedIndex];
    const productId = parseInt(productSelect.value);
    const productName = selectedOption.getAttribute('data-name');
    const unitPrice = parseFloat(selectedOption.getAttribute('data-price'));
    const totalPrice = unitPrice * quantity;
    
    // Verifica se o item já existe na lista
    const existingItemIndex = selectedItems.findIndex(item => item.product_id === productId);
    
    if (existingItemIndex >= 0) {
        // Se existe, atualiza a quantidade
        selectedItems[existingItemIndex].quantity += quantity;
        selectedItems[existingItemIndex].total_price = selectedItems[existingItemIndex].quantity * unitPrice;
    } else {
        // Se não existe, adiciona novo item
        selectedItems.push({
            product_id: productId,
            product_name: productName,
            quantity: quantity,
            unit_price: unitPrice,
            total_price: totalPrice
        });
    }
    
    // Limpa a seleção
    productSelect.value = '';
    quantityInput.value = 1;
    updateSelectedPrice();
    
    // Atualiza a exibição
    updateItemsList();
    calculateTotal(true); // forceUpdate = true quando adicionando itens
}

// Função para remover item da lista
function removeItem(index) {
    if (isPaymentReceived) {
        alert('Não é possível remover itens de uma OS com pagamento RECEBIDO.');
        return;
    }
    
    console.log('🐛 DEBUG: removeItem() chamada com index:', index);
    console.log('🐛 DEBUG: selectedItems antes da remoção:', selectedItems);
    console.log('🐛 DEBUG: selectedItems.length antes:', selectedItems.length);
    
    selectedItems.splice(index, 1);
    
    console.log('🐛 DEBUG: selectedItems após remoção:', selectedItems);
    console.log('🐛 DEBUG: selectedItems.length após:', selectedItems.length);
    
    updateItemsList();
    calculateTotal(true); // forceUpdate = true quando removendo itens
    
    console.log('🐛 DEBUG: removeItem() concluída');
}

// Função para atualizar a lista de itens
function updateItemsList() {
    const itemsList = document.getElementById('items_list');
    
    if (selectedItems.length === 0) {
        itemsList.innerHTML = '<p style="color: #666; margin: 0; text-align: center;">Nenhum item adicionado</p>';
        return;
    }
    
    let html = '<div style="display: grid; gap: 10px;">';
    
    selectedItems.forEach((item, index) => {
        html += `
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 8px; border: 1px solid #ddd; border-radius: 4px; background-color: white; font-size: 13px;">
                <div style="flex: 1;">
                    <strong style="font-size: 13px;">${item.product_name}</strong><br>
                    <small style="font-size: 12px;">Qtd: ${item.quantity} × R$ ${formatCurrency(item.unit_price)} = R$ ${formatCurrency(item.total_price)}</small>
                </div>
                <button type="button" onclick="removeItem(${index})" class="btn btn-danger" style="padding: 4px 8px; font-size: 12px;">
                    Remover
                </button>
            </div>
        `;
    });
    
    html += '</div>';
    itemsList.innerHTML = html;
}

// Função para calcular o valor total
function calculateTotal(forceUpdate = false) {
    const totalValueInput = document.getElementById('total_value');
    const calculatedValueSpan = document.getElementById('calculated_value');
    const itemsJsonInput = document.getElementById('items_json');
    
    // Calcula o total dos itens (garantindo que são números)
    const totalCalculated = selectedItems.reduce((sum, item) => {
        const itemTotal = parseFloat(item.total_price) || 0;
        return sum + itemTotal;
    }, 0);
    
    // Atualiza o valor calculado na tela (sempre)
    calculatedValueSpan.textContent = formatCurrency(totalCalculated);
    
    // Só atualiza o valor total se:
    // 1. forceUpdate for true (quando adicionando/removendo itens)
    // 2. OU o campo não foi editado manualmente pelo usuário
    // 3. OU o campo está vazio/zero
    const currentValue = parseFloat(totalValueInput.value) || 0;
    const shouldUpdate = forceUpdate || 
                        !totalValueInput.dataset.manuallyEdited || 
                        currentValue === 0;
    
    if (shouldUpdate) {
        totalValueInput.value = totalCalculated.toFixed(2);
    }
    
    // Atualiza o campo hidden com os itens em formato JSON
    itemsJsonInput.value = JSON.stringify(selectedItems);
    
    // Debug: log dos itens para verificar
    console.log('Itens selecionados:', selectedItems);
    console.log('JSON enviado:', itemsJsonInput.value);
}

// Função para carregar itens existentes
function loadExistingItems() {
    console.log('🐛 DEBUG: loadExistingItems() chamada');
    console.log('🐛 DEBUG: existingItems antes da cópia:', existingItems);
    
    selectedItems = [...existingItems];
    
    console.log('🐛 DEBUG: selectedItems após cópia:', selectedItems);
    console.log('🐛 DEBUG: selectedItems.length:', selectedItems.length);
    
    updateItemsList();
    
    // Atualiza apenas o valor calculado (para referência), mas preserva o valor salvo no campo
    const calculatedValueSpan = document.getElementById('calculated_value');
    const totalCalculated = selectedItems.reduce((sum, item) => {
        const itemTotal = parseFloat(item.total_price) || 0;
        return sum + itemTotal;
    }, 0);
    
    if (calculatedValueSpan) {
        calculatedValueSpan.textContent = formatCurrency(totalCalculated);
    }
    
    // Atualiza o campo JSON com os itens carregados
    const itemsJsonInput = document.getElementById('items_json');
    if (itemsJsonInput) {
        itemsJsonInput.value = JSON.stringify(selectedItems);
    }
    
    // NÃO chama calculateTotal() para preservar o valor salvo no campo
    console.log('🐛 DEBUG: loadExistingItems() concluída');
}

// Event listeners
document.addEventListener('DOMContentLoaded', function() {
    const productSelect = document.getElementById('product_select');
    const quantityInput = document.getElementById('quantity_input');
    const form = document.querySelector('form');
    
    if (productSelect) {
        productSelect.addEventListener('change', updateSelectedPrice);
    }
    
    if (quantityInput) {
        quantityInput.addEventListener('input', updateSelectedPrice);
    }
    
    // Event listeners para detectar edição manual do valor total
    const totalValueInput = document.getElementById('total_value');
    if (totalValueInput) {
        // Marca como editado manualmente quando o usuário digita
        totalValueInput.addEventListener('input', function() {
            this.dataset.manuallyEdited = 'true';
        });
        
        // Marca como editado manualmente quando o usuário cola texto
        totalValueInput.addEventListener('paste', function() {
            this.dataset.manuallyEdited = 'true';
        });
        
        // Removido: funcionalidade de duplo clique não necessária
    }
    
    // Validação antes do envio do formulário
    if (form) {
        form.addEventListener('submit', function(e) {
            if (isPaymentReceived) {
                e.preventDefault();
                alert('Não é possível editar uma OS com pagamento RECEBIDO.');
                return false;
            }
            
            const itemsJsonInput = document.getElementById('items_json');
            
            // Atualiza apenas o campo JSON com os itens atuais, sem recalcular o valor total
            itemsJsonInput.value = JSON.stringify(selectedItems);
    
            
            // Validação básica
            if (selectedItems.length === 0) {
                const confirmEmpty = window.confirm('⚠️ ATENÇÃO: Nenhum produto/serviço na lista!\\n\\nIsto vai REMOVER TODOS os itens da OS.\\n\\nDeseja continuar mesmo assim?');
                if (!confirmEmpty) {
                    e.preventDefault();
                    return false;
                }
            }
        });
    }
    
    // Carrega os itens existentes
    loadExistingItems();
});

// Permite adicionar item com Enter
document.addEventListener('keypress', function(e) {
    if (e.key === 'Enter' && (e.target.id === 'product_select' || e.target.id === 'quantity_input')) {
        e.preventDefault();
        addItem();
    }
});

// Funções para controle do modal de impressão
function openPrintModal(osId) {
    const modal = document.getElementById('printModal');
    const iframe = document.getElementById('printFrame');
    
    // Define a URL do iframe
    iframe.src = 'print_service_order.php?id=' + osId + '&modal=1';
    
    // Mostra o modal
    modal.style.display = 'block';
    
    // Adiciona event listener para fechar com ESC
    document.addEventListener('keydown', handleEscapeKey);
}

function closePrintModal() {
    const modal = document.getElementById('printModal');
    const iframe = document.getElementById('printFrame');
    
    // Esconde o modal
    modal.style.display = 'none';
    
    // Limpa o iframe
    iframe.src = '';
    
    // Remove event listener do ESC
    document.removeEventListener('keydown', handleEscapeKey);
}

function printOrder() {
    const iframe = document.getElementById('printFrame');
    
    try {
        // Tenta imprimir o conteúdo do iframe
        iframe.contentWindow.print();
    } catch (e) {
        // Fallback: abre em nova janela para impressão
        window.open(iframe.src, '_blank');
    }
}

function handleEscapeKey(e) {
    if (e.key === 'Escape') {
        closePrintModal();
    }
}

// Fecha o modal ao clicar fora dele
document.addEventListener('click', function(e) {
    const modal = document.getElementById('printModal');
    if (e.target === modal) {
        closePrintModal();
    }
});

// Funções para gerenciar fotos dos serviços
let currentPhotoUrl = '';
let currentPhotoIndex = 0;
let allPhotos = [];

function openPhotoLightbox(photoUrl, filename) {
    // Coleta todas as fotos da galeria
    allPhotos = [];
    const photoItems = document.querySelectorAll('.photo-item');
    photoItems.forEach((item, index) => {
        const img = item.querySelector('img');
        const name = item.querySelector('p').textContent;
        allPhotos.push({
            url: img.src,
            name: name,
            element: item
        });
    });
    
    // Encontra o índice da foto clicada
    currentPhotoIndex = allPhotos.findIndex(photo => {
        // Compara URLs de forma mais robusta
        return photo.url === photoUrl || photo.url.includes(photoUrl) || photoUrl.includes(photo.url);
    });
    
    // Se não encontrar, usa a primeira foto
    if (currentPhotoIndex === -1) {
        currentPhotoIndex = 0;
    }
    
    currentPhotoUrl = photoUrl;
    updatePhotoDisplay();
    document.getElementById('photo-lightbox').style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function updatePhotoDisplay() {
    if (allPhotos.length > 0 && currentPhotoIndex >= 0 && currentPhotoIndex < allPhotos.length) {
        const currentPhoto = allPhotos[currentPhotoIndex];
        document.getElementById('photo-title').textContent = '📸 ' + currentPhoto.name;
        document.getElementById('photo-viewer').src = currentPhoto.url;
        currentPhotoUrl = currentPhoto.url;
        
        // Atualiza contador
        document.getElementById('photo-counter').textContent = 
            `${currentPhotoIndex + 1} de ${allPhotos.length}`;
        
        // Mostra/esconde botões de navegação
        const prevBtn = document.getElementById('prev-photo');
        const nextBtn = document.getElementById('next-photo');
        
        prevBtn.style.display = allPhotos.length > 1 ? 'flex' : 'none';
        nextBtn.style.display = allPhotos.length > 1 ? 'flex' : 'none';
    }
}

function previousPhoto() {
    if (allPhotos.length > 1) {
        currentPhotoIndex = (currentPhotoIndex - 1 + allPhotos.length) % allPhotos.length;
        updatePhotoDisplay();
    }
}

function nextPhoto() {
    if (allPhotos.length > 1) {
        currentPhotoIndex = (currentPhotoIndex + 1) % allPhotos.length;
        updatePhotoDisplay();
    }
}

function closePhotoLightbox() {
    document.getElementById('photo-lightbox').style.display = 'none';
    document.getElementById('photo-viewer').src = '';
    document.body.style.overflow = 'auto';
    currentPhotoUrl = '';
}

function downloadPhoto() {
    if (currentPhotoUrl) {
        const link = document.createElement('a');
        link.href = currentPhotoUrl;
        link.download = currentPhotoUrl.split('/').pop();
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
}

function removePhoto(photoId) {
    if (isPaymentReceived) {
        alert('Não é possível remover fotos de uma OS com pagamento RECEBIDO.');
        return;
    }
    
    if (confirm('Tem certeza que deseja remover esta foto? Esta ação não pode ser desfeita.')) {
        // Envia requisição AJAX para remover a foto
        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'remove_service_photo.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4 && xhr.status === 200) {
                const response = JSON.parse(xhr.responseText);
                if (response.success) {
                    // Remove o elemento da galeria
                    const photoElement = document.querySelector(`[data-photo-id="${photoId}"]`);
                    if (photoElement) {
                        photoElement.remove();
                    }
                    
                    // Atualiza a lista de fotos se o lightbox estiver aberto
                    if (document.getElementById('photo-lightbox').style.display === 'block') {
                        // Recoleta todas as fotos após remoção
                        allPhotos = [];
                        const photoItems = document.querySelectorAll('.photo-item');
                        photoItems.forEach((item, index) => {
                            const img = item.querySelector('img');
                            const name = item.querySelector('p').textContent;
                            allPhotos.push({
                                url: img.src,
                                name: name,
                                element: item
                            });
                        });
                        
                        // Ajusta o índice atual se necessário
                        if (currentPhotoIndex >= allPhotos.length) {
                            currentPhotoIndex = Math.max(0, allPhotos.length - 1);
                        }
                        
                        // Atualiza a exibição
                        if (allPhotos.length > 0) {
                            updatePhotoDisplay();
                        } else {
                            closePhotoLightbox();
                        }
                    }
                    
                    // Verifica se não há mais fotos
                    const gallery = document.getElementById('photos-gallery');
                    if (gallery.children.length === 0) {
                        gallery.innerHTML = '<p style="color: #666; text-align: center; padding: 20px; grid-column: 1 / -1;">Nenhuma foto adicionada ainda</p>';
                    }
                } else {
                    alert('Erro ao remover foto: ' + response.message);
                }
            }
        };
        xhr.send('photo_id=' + photoId);
    }
}

// Preview de fotos selecionadas
document.getElementById('service_photos').addEventListener('change', function(e) {
    const files = e.target.files;
    const previewContainer = document.getElementById('preview-container');
    const photoPreview = document.getElementById('photo-preview');
    
    // Limpa preview anterior
    previewContainer.innerHTML = '';
    
    if (files.length > 0) {
        photoPreview.style.display = 'block';
        
        // Valida limite de 6 fotos
        if (files.length > 6) {
            alert('Máximo de 6 fotos permitidas. Apenas as primeiras 6 serão processadas.');
        }
        
        const maxFiles = Math.min(files.length, 6);
        
        for (let i = 0; i < maxFiles; i++) {
            const file = files[i];
            
            // Valida tipo de arquivo
            if (!file.type.startsWith('image/')) {
                alert('Arquivo não é uma imagem: ' + file.name);
                continue;
            }
            
            // Valida tamanho (5MB)
            if (file.size > 5 * 1024 * 1024) {
                alert('Arquivo muito grande: ' + file.name + ' (máximo 5MB)');
                continue;
            }
            
            const reader = new FileReader();
            reader.onload = function(e) {
                const previewDiv = document.createElement('div');
                previewDiv.style.cssText = 'position: relative; border: 1px solid #ddd; border-radius: 4px; overflow: hidden;';
                
                const img = document.createElement('img');
                img.src = e.target.result;
                img.style.cssText = 'width: 100%; height: 100px; object-fit: cover;';
                
                const nameDiv = document.createElement('div');
                nameDiv.style.cssText = 'padding: 5px; font-size: 11px; color: #666; word-break: break-all;';
                nameDiv.textContent = file.name;
                
                previewDiv.appendChild(img);
                previewDiv.appendChild(nameDiv);
                previewContainer.appendChild(previewDiv);
            };
            reader.readAsDataURL(file);
        }
    } else {
        photoPreview.style.display = 'none';
    }
});

// Navegação por teclado no lightbox de fotos
document.addEventListener('keydown', function(event) {
    const lightbox = document.getElementById('photo-lightbox');
    if (lightbox.style.display === 'block') {
        if (event.key === 'Escape') {
            closePhotoLightbox();
        } else if (event.key === 'ArrowLeft') {
            event.preventDefault();
            previousPhoto();
        } else if (event.key === 'ArrowRight') {
            event.preventDefault();
            nextPhoto();
        }
    }
});

// Fecha o lightbox de fotos ao clicar fora
document.getElementById('photo-lightbox').addEventListener('click', function(event) {
    if (event.target === this) {
        closePhotoLightbox();
    }
});

// Funções para controle do modal de PDF da NFSe
let currentPdfUrl = '';

function openPdfLightbox(pdfUrl, filename) {
    currentPdfUrl = pdfUrl;
    document.getElementById('pdf-title').textContent = '📄 ' + filename;
    document.getElementById('pdf-viewer').src = pdfUrl;
    document.getElementById('pdf-lightbox').style.display = 'block';
    document.body.style.overflow = 'hidden'; // Previne scroll da página
}

function closePdfLightbox() {
    document.getElementById('pdf-lightbox').style.display = 'none';
    document.getElementById('pdf-viewer').src = '';
    document.body.style.overflow = 'auto'; // Restaura scroll da página
    currentPdfUrl = '';
}

function printPdf() {
    if (currentPdfUrl) {
        const printWindow = window.open(currentPdfUrl, '_blank');
        printWindow.onload = function() {
            printWindow.print();
        };
    }
}

function downloadPdf() {
    if (currentPdfUrl) {
        const link = document.createElement('a');
        link.href = currentPdfUrl;
        link.download = currentPdfUrl.split('/').pop();
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
}

// Fecha o modal de PDF ao clicar fora
document.getElementById('pdf-lightbox').addEventListener('click', function(event) {
    if (event.target === this) {
        closePdfLightbox();
    }
});

// Fecha o modal de PDF com tecla ESC
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const pdfLightbox = document.getElementById('pdf-lightbox');
        if (pdfLightbox.style.display === 'block') {
            closePdfLightbox();
        }
    }
});
</script>