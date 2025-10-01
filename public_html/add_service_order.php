<?php

// add_service_order.php

// Formulário para adicionar uma nova ordem de serviço



require_once 'header.php';

require_once 'db_connect.php';



$client_id = $description = $status = $value = $solution = "";

$client_id_err = $description_err = "";

$success_message = "";

$error_message = "";



// Obtém a lista de clientes para o dropdown

$clients_query = "SELECT id, name, cnpj, document_type FROM clients ORDER BY name ASC";

$clients_result = $mysqli->query($clients_query);



// Obtém a lista de todos os produtos (serviços e produtos com estoque > 0)

$products_query = "SELECT id, name, price, category FROM products WHERE status = 'Ativo' AND stock_quantity > 0 ORDER BY category, name ASC";

$products_result = $mysqli->query($products_query);



if ($_SERVER["REQUEST_METHOD"] == "POST") {

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

    if (empty($status)) {

        $status = "Pendente"; // Define um status padrão se não for fornecido

    }



    // Valida e sanitiza o valor

    $value = filter_input(INPUT_POST, 'value', FILTER_VALIDATE_FLOAT, FILTER_FLAG_ALLOW_FRACTION | FILTER_FLAG_ALLOW_THOUSAND);

    if ($value === false || $value < 0) {

        $value = 0.00; // Define um valor padrão seguro ou lida com o erro

    }



    $solution = trim($_POST["solution"]); // Obtém o valor do campo solução

    

    // Obtém os itens selecionados (formato JSON)

    $items_json = isset($_POST["items_json"]) ? $_POST["items_json"] : "[]";

    $items = json_decode($items_json, true);



    // Verifica erros de entrada antes de inserir no banco de dados

    if (empty($client_id_err) && empty($description_err)) {

        // Inicia transação

        $mysqli->begin_transaction();

        

        try {

            // Verifica se as colunas de faturamento existem
            $check_columns = "SHOW COLUMNS FROM service_orders LIKE 'payment_value'";
            $column_exists = $mysqli->query($check_columns);
            
            // Insere a ordem de serviço
            if ($column_exists && $column_exists->num_rows > 0) {
                // Com colunas de faturamento
                $sql = "INSERT INTO service_orders (client_id, description, status, value, payment_status, payment_value, solution) VALUES (?, ?, ?, ?, 'pendente', ?, ?)";
            } else {
                // Sem colunas de faturamento
                $sql = "INSERT INTO service_orders (client_id, description, status, value, solution) VALUES (?, ?, ?, ?, ?)";
            }

            if ($stmt = $mysqli->prepare($sql)) {
                if ($column_exists && $column_exists->num_rows > 0) {
                    $stmt->bind_param("issdds", $param_client_id, $param_description, $param_status, $param_value, $param_value, $param_solution);
                } else {
                    $stmt->bind_param("issds", $param_client_id, $param_description, $param_status, $param_value, $param_solution);
                }



                $param_client_id = $client_id;

                $param_description = $description;

                $param_status = $status;

                $param_value = $value; // Atribui o valor sanitizado

                $param_solution = $solution; // Atribui a solução



                if ($stmt->execute()) {

                    $service_order_id = $mysqli->insert_id;

                    

                    // Insere os itens da ordem de serviço

                    if (!empty($items)) {

                        $sql_items = "INSERT INTO service_order_items (service_order_id, product_id, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?)";

                        

                        if ($stmt_items = $mysqli->prepare($sql_items)) {

                            foreach ($items as $item) {

                                $product_id = $item['product_id'];

                                $quantity = $item['quantity'];

                                $unit_price = $item['unit_price'];

                                $total_price = $quantity * $unit_price;

                                

                                $stmt_items->bind_param("iiidd", $service_order_id, $product_id, $quantity, $unit_price, $total_price);

                                $stmt_items->execute();

                            }

                            $stmt_items->close();

                        }

                    }

                    

                    $mysqli->commit();

                    // Redireciona para a lista de OS após adicionar com sucesso

                    header("Location: list_service_orders.php?success=1&message=" . urlencode("Ordem de Serviço adicionada com sucesso!"));

                    exit();

                    $client_id = $description = $status = $value = $solution = "";

                } else {

                    throw new Exception("Erro ao inserir ordem de serviço");

                }

                

                $stmt->close();

            } else {

                throw new Exception("Erro ao preparar consulta");

            }

            

        } catch (Exception $e) {

            $mysqli->rollback();

            $error_message = "Erro ao adicionar Ordem de Serviço: " . $e->getMessage();

        }

    }

    $mysqli->close(); // Fecha a conexão após a operação

} else {

    // Se for GET, pode ter um client_id na URL

    if (isset($_GET['client_id'])) {

        $client_id = trim($_GET['client_id']);

    }

    $value = 0.00; // Valor padrão ao carregar o formulário

    $solution = ""; // Solução padrão ao carregar o formulário

}

?>



<h2>Adicionar Nova Ordem de Serviço</h2>



<?php if (!empty($success_message)): ?>

    <div class="alert-message alert-success"><?php echo $success_message; ?></div>

<?php endif; ?>

<?php if (!empty($error_message)): ?>

    <div class="alert-message alert-error"><?php echo $error_message; ?></div>

<?php endif; ?>



<form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">

    <div class="form-group">

        <label>Cliente: <span style="color: red;">*</span></label>

        <select name="client_id">

            <option value="">Selecione um cliente</option>

            <?php

            if ($clients_result && $clients_result->num_rows > 0) {

                while ($row = $clients_result->fetch_assoc()) {

                    echo '<option value="' . htmlspecialchars($row['id']) . '"';

                    if ($client_id == $row['id']) {

                        echo ' selected';

                    }

                    // Monta o texto da opção com nome e CNPJ/CPF
                    $option_text = htmlspecialchars($row['name']);
                    if (!empty($row['cnpj'])) {
                        $option_text .= ' (' . htmlspecialchars($row['cnpj']) . ')';
                    }

                    echo '>' . $option_text . '</option>';

                }

            } else {

                echo '<option value="">Nenhum cliente cadastrado</option>';

            }

            ?>

        </select>

        <span class="help-block" style="color: red;"><?php echo $client_id_err; ?></span>

    </div>

    <div class="form-group">

        <label>Descrição: <span style="color: red;">*</span></label>

        <textarea name="description"><?php echo htmlspecialchars($description); ?></textarea>

        <span class="help-block" style="color: red;"><?php echo $description_err; ?></span>

    </div>

    <div class="form-group">

        <label>Status:</label>

        <select name="status">

            <option value="Pendente" <?php echo ($status == 'Pendente') ? 'selected' : ''; ?>>Pendente</option>

            <option value="Em Andamento" <?php echo ($status == 'Em Andamento') ? 'selected' : ''; ?>>Em Andamento</option>

            <option value="Concluída" <?php echo ($status == 'Concluída') ? 'selected' : ''; ?>>Concluída</option>

            <option value="Cancelada" <?php echo ($status == 'Cancelada') ? 'selected' : ''; ?>>Cancelada</option>

        </select>

    </div>

    <div class="form-group">

        <label>Solução:</label>

        <textarea name="solution"><?php echo htmlspecialchars($solution); ?></textarea>

    </div>

    <div class="form-group">

        <label>Adicionar Produtos/Serviços:</label>

        <div style="display: flex; gap: 10px; margin-bottom: 10px; flex-wrap: wrap;">

            <select id="product_select" style="flex: 2; min-width: 200px; font-size: 13px;">

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

            <input type="number" id="quantity_input" placeholder="Qtd" min="1" value="1" style="width: 80px;">

            <button type="button" onclick="addItem()" class="btn" style="white-space: nowrap;">Adicionar Item</button>

        </div>

        <div id="selected_price" style="font-size: 12px; color: #28a745; margin-bottom: 10px; font-weight: bold;"></div>

    </div>

    <div class="form-group">

        <label>Itens Selecionados:</label>

        <div id="items_list" style="border: 1px solid #ddd; border-radius: 4px; padding: 10px; min-height: 50px; background-color: #f9f9f9; font-size: 13px;">

            <p style="color: #666; margin: 0; text-align: center;">Nenhum item adicionado</p>

        </div>

    </div>

    <div class="form-group">

        <label>Valor Total (R$):</label>

        <input type="number" name="value" id="total_value" step="0.01" min="0" value="<?php echo htmlspecialchars($value); ?>">

        <input type="hidden" name="items_json" id="items_json" value="[]">

        <div style="font-size: 12px; color: #666; margin-top: 5px;">

            <span>Valor calculado: R$ <span id="calculated_value">0,00</span></span>

            <br><span style="font-style: italic;">Você pode editar este valor para aplicar descontos ou ajustes</span>

        </div>

    </div>

    <div class="form-actions">

        <input type="submit" class="btn" value="Adicionar OS">

        <a href="list_service_orders.php" class="btn btn-secondary">Cancelar</a>

    </div>

</form>



<?php

// Reabre a conexão se ela foi fechada no POST e este é um GET

if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($mysqli) && $mysqli->connect_error) {

    require_once 'db_connect.php';

}



require_once 'footer.php';

?>

<script>
// Array para armazenar os itens selecionados
let selectedItems = [];

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
    calculateTotal();
}

// Função para remover item da lista
function removeItem(index) {
    selectedItems.splice(index, 1);
    updateItemsList();
    calculateTotal();
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
function calculateTotal() {
    const totalValueInput = document.getElementById('total_value');
    const calculatedValueSpan = document.getElementById('calculated_value');
    const itemsJsonInput = document.getElementById('items_json');
    
    // Calcula o total dos itens
    const totalCalculated = selectedItems.reduce((sum, item) => sum + item.total_price, 0);
    
    // Atualiza o valor calculado na tela
    calculatedValueSpan.textContent = formatCurrency(totalCalculated);
    
    // Atualiza o campo de valor total apenas se estiver vazio ou igual ao valor calculado anterior
    const currentValue = parseFloat(totalValueInput.value) || 0;
    if (currentValue === 0 || totalValueInput.getAttribute('data-last-calculated') == currentValue) {
        totalValueInput.value = totalCalculated.toFixed(2);
    }
    
    // Armazena o último valor calculado para comparação
    totalValueInput.setAttribute('data-last-calculated', totalCalculated.toFixed(2));
    
    // Atualiza o campo hidden com os itens em formato JSON
    itemsJsonInput.value = JSON.stringify(selectedItems);
}

// Event listeners
document.addEventListener('DOMContentLoaded', function() {
    const productSelect = document.getElementById('product_select');
    const quantityInput = document.getElementById('quantity_input');
    
    if (productSelect) {
        productSelect.addEventListener('change', updateSelectedPrice);
    }
    
    if (quantityInput) {
        quantityInput.addEventListener('input', updateSelectedPrice);
    }
    
    calculateTotal();
});

// Permite adicionar item com Enter
document.addEventListener('keypress', function(e) {
    if (e.key === 'Enter' && (e.target.id === 'product_select' || e.target.id === 'quantity_input')) {
        e.preventDefault();
        addItem();
    }
});
</script>