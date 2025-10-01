<?php
// edit_product.php
// Formulário para editar um produto existente

require_once 'header.php';
require_once 'db_connect.php';

$id = $name = $code = $description = $category = $brand = $price = $cost_price = $unit = $stock_quantity = $supplier = $warranty_period = $status = $notes = "";
$name_err = $price_err = $category_err = "";
$success_message = "";
$error_message = "";

// Verifica se o ID foi fornecido
if (isset($_GET["id"]) && !empty(trim($_GET["id"]))) {
    $id = trim($_GET["id"]);

    // Busca os dados do produto
    $sql = "SELECT * FROM products WHERE id = ?";
    if ($stmt = $mysqli->prepare($sql)) {
        $stmt->bind_param("i", $param_id);
        $param_id = $id;

        if ($stmt->execute()) {
            $result = $stmt->get_result();
            if ($result->num_rows == 1) {
                $row = $result->fetch_assoc();
                $name = $row["name"];
                $code = $row["code"];
                $description = $row["description"];
                $category = $row["category"];
                $brand = $row["brand"];
                $price = $row["price"];
                $cost_price = $row["cost_price"];
                $unit = $row["unit"];
                $stock_quantity = $row["stock_quantity"];
                $supplier = $row["supplier"];
                $warranty_period = $row["warranty_period"];
                $status = $row["status"];
                $notes = $row["notes"];
            } else {
                header("location: list_products.php");
                exit();
            }
        } else {
            echo "Ops! Algo deu errado. Por favor, tente novamente mais tarde.";
        }
        $stmt->close();
    }
} else {
    header("location: list_products.php");
    exit();
}

// Processa dados do formulário quando enviado
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
    $stock_quantity = filter_var(trim($_POST["stock_quantity"]), FILTER_VALIDATE_INT);
    if ($stock_quantity === false) $stock_quantity = 0;
    
    $supplier = trim($_POST["supplier"]);
    $warranty_period = filter_var(trim($_POST["warranty_period"]), FILTER_VALIDATE_INT);
    if ($warranty_period === false) $warranty_period = null;
    
    $status = trim($_POST["status"]);
    $notes = trim($_POST["notes"]);

    // Verifica erros de entrada antes de atualizar
    if (empty($name_err) && empty($category_err) && empty($price_err)) {
        $sql = "UPDATE products SET name=?, code=?, description=?, category=?, brand=?, price=?, cost_price=?, unit=?, stock_quantity=?, supplier=?, warranty_period=?, status=?, notes=? WHERE id=?";

        if ($stmt = $mysqli->prepare($sql)) {
            $stmt->bind_param("sssssddsissssi", $param_name, $param_code, $param_description, $param_category, $param_brand, $param_price, $param_cost_price, $param_unit, $param_stock_quantity, $param_supplier, $param_warranty_period, $param_status, $param_notes, $param_id);

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
            $param_id = $id;

            if ($stmt->execute()) {
                $success_message = "Produto atualizado com sucesso!";
            } else {
                if ($mysqli->errno == 1062) { // Duplicate entry
                    $error_message = "Erro: Já existe um produto com este código.";
                } else {
                    $error_message = "Erro ao atualizar produto. Por favor, tente novamente.";
                }
            }

            $stmt->close();
        }
    }
}

$mysqli->close();
?>

<h2>Editar Produto</h2>

<?php if (!empty($success_message)): ?>
    <div class="alert-message alert-success"><?php echo $success_message; ?></div>
<?php endif; ?>

<?php if (!empty($error_message)): ?>
    <div class="alert-message alert-error"><?php echo $error_message; ?></div>
<?php endif; ?>

<form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . "?id=" . $id; ?>" method="post">
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
        <input type="submit" value="Atualizar Produto" class="btn">
        <a href="list_products.php" class="btn btn-secondary">Cancelar</a>
    </div>
</form>

<?php require_once 'footer.php'; ?>


























