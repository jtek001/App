<?php
// list_products.php
// Lista todos os produtos cadastrados

require_once 'header.php';
require_once 'db_connect.php';

// Configurações de paginação
$records_per_page = 10;
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $records_per_page;

// Inicializa variáveis de filtro
$search_name = isset($_GET['search_name']) ? trim($_GET['search_name']) : '';
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';

// Query base para obter os registros
$sql_base = "SELECT id, name, code, category, brand, price, cost_price, stock_quantity, status FROM products";

// Query para contar o total de registros (para paginação)
$sql_count = "SELECT COUNT(*) AS total_records FROM products";

$where_clauses = [];
$params = [];
$param_types = "";

// Adiciona filtros
if (!empty($search_name)) {
    $where_clauses[] = "name LIKE ?";
    $params[] = "%" . $search_name . "%";
    $param_types .= "s";
}

if (!empty($category_filter)) {
    $where_clauses[] = "category = ?";
    $params[] = $category_filter;
    $param_types .= "s";
}


if (!empty($where_clauses)) {
    $where_clause_str = " WHERE " . implode(" AND ", $where_clauses);
    $sql_base .= $where_clause_str;
    $sql_count .= $where_clause_str;
}

$sql_base .= " ORDER BY name ASC LIMIT ? OFFSET ?";

$stmt = null;
$result = null;
$total_records = 0;

// Obter o total de registros
if ($stmt_count = $mysqli->prepare($sql_count)) {
    $count_params = $params;
    $count_param_types = $param_types;

    if (!empty($count_params)) {
        call_user_func_array([$stmt_count, 'bind_param'], array_merge([$count_param_types], $count_params));
    }
    
    $stmt_count->execute();
    $count_result = $stmt_count->get_result();
    $row_count = $count_result->fetch_assoc();
    $total_records = $row_count['total_records'];
    $stmt_count->close();
} else {
    echo "<div class='alert-message alert-error'>Erro ao preparar a consulta de contagem: " . $mysqli->error . "</div>";
}

$total_pages = ceil($total_records / $records_per_page);

// Obter os registros da página atual
if ($stmt = $mysqli->prepare($sql_base)) {
    $all_params = $params;
    $all_params[] = $records_per_page;
    $all_params[] = $offset;

    $all_param_types = $param_types . "ii";

    call_user_func_array([$stmt, 'bind_param'], array_merge([$all_param_types], $all_params));
    
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
} else {
    echo "<div class='alert-message alert-error'>Erro ao preparar a consulta de registros: " . $mysqli->error . "</div>";
}

// Mensagens de feedback
$delete_success_message = "";
$delete_error_message = "";
$create_success_message = "";

if (isset($_GET['status']) && $_GET['status'] == 'deleted') {
    $delete_success_message = "Produto excluído com sucesso!";
}

if (isset($_GET['status']) && $_GET['status'] == 'created') {
    $create_success_message = "Produto cadastrado com sucesso!";
}

if (isset($_GET['error']) && $_GET['error'] == '1' && isset($_GET['message'])) {
    $delete_error_message = urldecode($_GET['message']);
}

// Buscar categorias para o filtro
$categories_query = "SELECT DISTINCT category FROM products ORDER BY category ASC";
$categories_result = $mysqli->query($categories_query);

// Fecha a conexão após a consulta
$mysqli->close();
?>

<h2>Lista de Produtos</h2>
<p>
    <a href="add_product.php" class="btn">Adicionar Novo Produto</a>
</p>

<?php if (!empty($create_success_message)): ?>
    <div class="alert-message alert-success"><?php echo $create_success_message; ?></div>
<?php endif; ?>

<?php if (!empty($delete_success_message)): ?>
    <div class="alert-message alert-success"><?php echo $delete_success_message; ?></div>
<?php endif; ?>

<?php if (!empty($delete_error_message)): ?>
    <div class="alert-message alert-error"><?php echo $delete_error_message; ?></div>
<?php endif; ?>

<!-- Formulário de Filtros -->
<form method="get" action="list_products.php" class="filter-form">
    <div class="form-group">
        <label for="search_name">Buscar por Nome:</label>
        <input type="text" id="search_name" name="search_name" value="<?php echo htmlspecialchars($search_name); ?>" placeholder="Digite o nome do produto">
    </div>
    
    <div class="form-group">
        <label for="category">Categoria:</label>
        <select id="category" name="category">
            <option value="">Todas as Categorias</option>
            <?php if ($categories_result && $categories_result->num_rows > 0): ?>
                <?php while ($cat_row = $categories_result->fetch_assoc()): ?>
                    <option value="<?php echo htmlspecialchars($cat_row['category']); ?>" 
                            <?php echo ($category_filter == $cat_row['category']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cat_row['category']); ?>
                    </option>
                <?php endwhile; ?>
            <?php endif; ?>
        </select>
    </div>
    
    
    <div class="form-actions">
        <button type="submit" class="btn">Filtrar</button>
        <a href="list_products.php" class="btn btn-secondary">Limpar Filtros</a>
    </div>
</form>

<?php if ($result && $result->num_rows > 0): ?>

    <div class="list_products">
    <table>
        <thead>
            <tr>
                <th>Código</th>
                <th>Nome</th>
                <th>Categoria</th>
                <th>Marca</th>
                <th>Preço</th>
                <th>Estoque</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['code'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                    <td><?php echo htmlspecialchars($row['category']); ?></td>
                    <td><?php echo htmlspecialchars($row['brand'] ?? '-'); ?></td>
                    <td>R$ <?php echo number_format($row['price'], 2, ',', '.'); ?></td>
                    <td><?php echo $row['stock_quantity'] ?? 0; ?></td>
                    <td>
                        <a href="edit_product.php?id=<?php echo $row['id']; ?>" class="btn btn-secondary">Editar</a>
                        <button type="button" class="btn btn-danger" onclick="deleteModal.show(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['name'], ENT_QUOTES); ?>', 'produto', true);">Excluir</button>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    </div>

    <!-- Paginação -->
    <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php
            $query_params = http_build_query(array_filter([
                'search_name' => $search_name,
                'category' => $category_filter
            ]));
            $query_string = !empty($query_params) ? '&' . $query_params : '';
            ?>
            
            <?php if ($current_page > 1): ?>
                <a href="?page=<?php echo ($current_page - 1) . $query_string; ?>" class="btn btn-secondary">« Anterior</a>
            <?php endif; ?>
            
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <?php if ($i == $current_page): ?>
                    <span class="btn current-page"><?php echo $i; ?></span>
                <?php else: ?>
                    <a href="?page=<?php echo $i . $query_string; ?>" class="btn btn-secondary"><?php echo $i; ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            
            <?php if ($current_page < $total_pages): ?>
                <a href="?page=<?php echo ($current_page + 1) . $query_string; ?>" class="btn btn-secondary">Próxima »</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

<?php else: ?>
    <p>Nenhum produto encontrado.</p>
<?php endif; ?>

<script src="js/delete_modal.js?v=1.0"></script>

<?php require_once 'footer.php'; ?>





















