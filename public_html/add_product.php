<?php
// add_product.php
// Formulário para adicionar um novo produto

require_once 'header.php';
require_once 'db_connect.php';

$name = $code = $description = $category = $brand = $price = $cost_price = $unit = $stock_quantity = $supplier = $warranty_period = $status = $notes = "";
$name_err = $price_err = $category_err = "";
$success_message = "";
$error_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Valida o nome do produto
    if (empty(trim($_POST["name"]))) {
        $name_err = "Por favor, insira o nome do produto.";
    } else {
        $name = trim($_POST["name"]);
    }

    // Valida a categoria
    if (empty(trim($_POST["category"]))) {
        $category_err = "Por favor, selecione uma categoria.";
    } else {
        $category = trim($_POST["category"]);
    }

    // Valida o preço
    if (empty(trim($_POST["price"]))) {
        $price_err = "Por favor, insira o preço do produto.";
    } else {
        $price = filter_var(trim($_POST["price"]), FILTER_VALIDATE_FLOAT);
        if ($price === false || $price < 0) {
            $price_err = "Por favor, insira um preço válido.";
        }
    }

    // Obtém outros campos
    $code = trim($_POST["code"]);
    $description = trim($_POST["description"]);
    $brand = trim($_POST["brand"]);
    $cost_price = filter_var(trim($_POST["cost_price"]), FILTER_VALIDATE_FLOAT);
    if ($cost_price === false) $cost_price = null;
    
    $unit = trim($_POST["unit"]);
    if (empty($unit)) $unit = "UN";
    
    $stock_quantity = filter_var(trim($_POST["stock_quantity"]), FILTER_VALIDATE_INT);
    if ($stock_quantity === false) $stock_quantity = 0;
    
    $supplier = trim($_POST["supplier"]);
    $warranty_period = filter_var(trim($_POST["warranty_period"]), FILTER_VALIDATE_INT);
    if ($warranty_period === false) $warranty_period = null;
    
    $status = trim($_POST["status"]);
    if (empty($status)) $status = "Ativo";
    
    $notes = trim($_POST["notes"]);

    // Verifica erros de entrada antes de inserir no banco de dados
    if (empty($name_err) && empty($category_err) && empty($price_err)) {
        // Checagens de duplicidade por nome e por código (se informado)
        $duplicate_name = false;
        $duplicate_code = false;

        if ($check_name_stmt = $mysqli->prepare("SELECT id FROM products WHERE name = ? LIMIT 1")) {
            $check_name_stmt->bind_param("s", $name);
            $check_name_stmt->execute();
            $check_name_stmt->store_result();
            if ($check_name_stmt->num_rows > 0) {
                $duplicate_name = true;
            }
            $check_name_stmt->close();
        }

        if (!empty($code)) {
            if ($check_code_stmt = $mysqli->prepare("SELECT id FROM products WHERE code = ? LIMIT 1")) {
                $check_code_stmt->bind_param("s", $code);
                $check_code_stmt->execute();
                $check_code_stmt->store_result();
                if ($check_code_stmt->num_rows > 0) {
                    $duplicate_code = true;
                }
                $check_code_stmt->close();
            }
        }

        if ($duplicate_name || $duplicate_code) {
            if ($duplicate_name && $duplicate_code) {
                $error_message = "Erro: Já existe um produto com este nome e este código.";
            } elseif ($duplicate_name) {
                $error_message = "Erro: Já existe um produto com este nome.";
            } else {
                $error_message = "Erro: Já existe um produto com este código.";
            }
        } else {
            $sql = "INSERT INTO products (name, code, description, category, brand, price, cost_price, unit, stock_quantity, supplier, warranty_period, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            if ($stmt = $mysqli->prepare($sql)) {
                $stmt->bind_param("sssssddsissss", $param_name, $param_code, $param_description, $param_category, $param_brand, $param_price, $param_cost_price, $param_unit, $param_stock_quantity, $param_supplier, $param_warranty_period, $param_status, $param_notes);

                $param_name = $name;
                $param_code = !empty($code) ? $code : null;
                $param_description = $description;
                $param_category = $category;
                $param_brand = $brand;
                $param_price = $price;
                $param_cost_price = $cost_price;
                $param_unit = $unit;
                $param_stock_quantity = $stock_quantity;
                $param_supplier = $supplier;
                $param_warranty_period = $warranty_period;
                $param_status = $status;
                $param_notes = $notes;

                if ($stmt->execute()) {
                    echo "<script>window.location.href='list_products.php?status=created';</script>";
                } else {
                    if ($mysqli->errno == 1062) { // Duplicate entry (garantia extra caso haja índice único)
                        $error_message = "Erro: Já existe um produto com estes dados.";
                    } else {
                        $error_message = "Erro ao adicionar produto. Por favor, tente novamente.";
                    }
                }

                $stmt->close();
            }
        }
    }
    $mysqli->close();
}
?>

<h2>Adicionar Novo Produto</h2>

<?php if (!empty($success_message)): ?>
    <div class="alert-message alert-success"><?php echo $success_message; ?></div>
<?php endif; ?>

<?php if (!empty($error_message)): ?>
    <div class="alert-message alert-error"><?php echo $error_message; ?></div>
<?php endif; ?>

<form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
    <div class="form-group">
        <label>Nome do Produto: <span style="color: red;">*</span></label>
        <input type="text" name="name" value="<?php echo htmlspecialchars($name); ?>" required>
        <span class="help-block" style="color: red;"><?php echo $name_err; ?></span>
    </div>

    <div class="form-group">
        <label>Código do Produto:</label>
        <input type="text" name="code" value="<?php echo htmlspecialchars($code); ?>" placeholder="SKU ou código interno">
    </div>

    <div class="form-group">
        <label>Descrição:</label>
        <textarea name="description" rows="4" placeholder="Descrição detalhada do produto"><?php echo htmlspecialchars($description); ?></textarea>
    </div>

    <div class="form-group">
        <label>Categoria: <span style="color: red;">*</span></label>
        <select name="category" required>
            <option value="">Selecione uma categoria</option>
            <option value="Hardware" <?php echo ($category == 'Hardware') ? 'selected' : ''; ?>>Hardware</option>
            <option value="Software" <?php echo ($category == 'Software') ? 'selected' : ''; ?>>Software</option>
            <option value="Impressora" <?php echo ($category == 'Impressora') ? 'selected' : ''; ?>>Impressora</option>
            <option value="Rede" <?php echo ($category == 'Rede') ? 'selected' : ''; ?>>Rede</option>
            <option value="Cftv" <?php echo ($category == 'Cftv') ? 'selected' : ''; ?>>Cftv</option>
            <option value="Peças" <?php echo ($category == 'Peças') ? 'selected' : ''; ?>>Peças</option>
            <option value="Serviços" <?php echo ($category == 'Serviços') ? 'selected' : ''; ?>>Serviços</option>
            <option value="Consumíveis" <?php echo ($category == 'Consumíveis') ? 'selected' : ''; ?>>Consumíveis</option>
            <option value="Acessórios" <?php echo ($category == 'Acessórios') ? 'selected' : ''; ?>>Acessórios</option>
        </select>
        <span class="help-block" style="color: red;"><?php echo $category_err; ?></span>
    </div>

    <div class="form-group">
        <label>Marca/Fabricante:</label>
        <input type="text" name="brand" value="<?php echo htmlspecialchars($brand); ?>" placeholder="Nome da marca">
    </div>

    <div class="form-group">
        <label>Preço de Venda (R$): <span style="color: red;">*</span></label>
        <input type="number" name="price" value="<?php echo htmlspecialchars($price); ?>" step="0.01" min="0" required>
        <span class="help-block" style="color: red;"><?php echo $price_err; ?></span>
    </div>

    <div class="form-group">
        <label>Preço de Custo (R$):</label>
        <input type="number" name="cost_price" value="<?php echo htmlspecialchars($cost_price); ?>" step="0.01" min="0">
    </div>

    <div class="form-group">
        <label>Unidade de Medida:</label>
        <select name="unit">
            <option value="UN" <?php echo ($unit == 'UN') ? 'selected' : ''; ?>>Unidade (UN)</option>
            <option value="M" <?php echo ($unit == 'M') ? 'selected' : ''; ?>>Metro (M)</option>
            <option value="KG" <?php echo ($unit == 'KG') ? 'selected' : ''; ?>>Quilograma (KG)</option>
            <option value="L" <?php echo ($unit == 'L') ? 'selected' : ''; ?>>Litro (L)</option>
            <option value="Hora" <?php echo ($unit == 'Hora') ? 'selected' : ''; ?>>Hora</option>
            <option value="Pacote" <?php echo ($unit == 'Pacote') ? 'selected' : ''; ?>>Pacote</option>
        </select>
    </div>

    <div class="form-group">
        <label>Quantidade em Estoque:</label>
        <input type="number" name="stock_quantity" value="<?php echo htmlspecialchars($stock_quantity); ?>" min="0">
    </div>

    <div class="form-group">
        <label>Fornecedor:</label>
        <input type="text" name="supplier" value="<?php echo htmlspecialchars($supplier); ?>" placeholder="Nome do fornecedor">
    </div>

    <div class="form-group">
        <label>Período de Garantia (meses):</label>
        <input type="number" name="warranty_period" value="<?php echo htmlspecialchars($warranty_period); ?>" min="0">
    </div>

    <div class="form-group">
        <label>Status:</label>
        <select name="status">
            <option value="Ativo" <?php echo ($status == 'Ativo') ? 'selected' : ''; ?>>Ativo</option>
            <option value="Inativo" <?php echo ($status == 'Inativo') ? 'selected' : ''; ?>>Inativo</option>
            <option value="Descontinuado" <?php echo ($status == 'Descontinuado') ? 'selected' : ''; ?>>Descontinuado</option>
        </select>
    </div>

    <div class="form-group">
        <label>Observações:</label>
        <textarea name="notes" rows="3" placeholder="Informações adicionais"><?php echo htmlspecialchars($notes); ?></textarea>
    </div>

    <div class="form-actions">
        <input type="submit" value="Adicionar Produto" class="btn">
        <a href="list_products.php" class="btn btn-secondary">Cancelar</a>
    </div>
</form>

<?php require_once 'footer.php'; ?>


























