<?php
// list_expenses.php
// Lista todas as despesas cadastradas

require_once 'header.php';
require_once 'db_connect.php';

// Configurações de paginação
$records_per_page = 10;
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $records_per_page;

// Inicializa variáveis de filtro
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';

// Query base para obter os registros
$sql_base = "SELECT id, description, amount, expense_date, category, notes, status FROM expenses";

// Query para contar o total de registros (para paginação)
$sql_count = "SELECT COUNT(*) AS total_records FROM expenses";

$where_clauses = [];
$params = [];
$param_types = "";

// Adiciona filtros
if (!empty($start_date)) {
    $where_clauses[] = "expense_date >= ?";
    $params[] = $start_date . " 00:00:00";
    $param_types .= "s";
}
if (!empty($end_date)) {
    $where_clauses[] = "expense_date <= ?";
    $params[] = $end_date . " 23:59:59";
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

$sql_base .= " ORDER BY expense_date DESC LIMIT ? OFFSET ?";

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

// Calcula o total das despesas filtradas (já está acima, mas para garantir que o $total_expenses seja atualizado com os filtros)
$sql_total_expenses_filtered = "SELECT SUM(amount) AS total_sum FROM expenses";
if (!empty($where_clauses)) {
    $sql_total_expenses_filtered .= " WHERE " . implode(" AND ", $where_clauses);
}

if ($stmt_total = $mysqli->prepare($sql_total_expenses_filtered)) {
    if (!empty($params)) {
        call_user_func_array([$stmt_total, 'bind_param'], array_merge([$param_types], $params));
    }
    $stmt_total->execute();
    $total_result = $stmt_total->get_result();
    $row_total = $total_result->fetch_assoc();
    $total_expenses = $row_total['total_sum'] ?? 0;
    $stmt_total->close();
}


// Obtém lista de categorias para o filtro
$categories_query = "SELECT DISTINCT category FROM expenses WHERE category IS NOT NULL AND category != '' ORDER BY category ASC";
$categories_result = $mysqli->query($categories_query);

// Mensagens de sucesso e erro
$delete_success_message = "";
$delete_error_message = "";

if (isset($_GET['status'])) {
    switch ($_GET['status']) {
        case 'deleted':
            $delete_success_message = "Despesa excluída com sucesso!";
            break;
        case 'added':
            $delete_success_message = "Despesa adicionada com sucesso!";
            break;
    }
}

if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'paid_expense':
            $delete_error_message = "Esta despesa já está marcada como Pago e não pode ser excluída!";
            break;
        case 'delete_failed':
            $delete_error_message = "Erro ao excluir despesa. Tente novamente.";
            break;
        case 'not_found':
            $delete_error_message = "Despesa não encontrada.";
            break;
        case 'check_failed':
            $delete_error_message = "Erro ao verificar despesa. Tente novamente.";
            break;
        default:
            $delete_error_message = "Erro desconhecido.";
    }
}

// Fecha a conexão após a consulta
$mysqli->close();
?>

<h2>Lista de Despesas</h2>
<p>
    <a href="add_expense.php" class="btn">Adicionar Nova Despesa</a>
</p>

<?php if (!empty($delete_success_message)): ?>
    <div class="alert-message alert-success"><?php echo $delete_success_message; ?></div>
<?php endif; ?>

<?php if (!empty($delete_error_message)): ?>
    <div class="alert-message alert-error"><?php echo $delete_error_message; ?></div>
<?php endif; ?>

<form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="get" class="filter-form">
    <div class="form-group">
        <label for="start_date">Data de Início:</label>
        <input type="date" name="start_date" id="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
    </div>
    <div class="form-group">
        <label for="end_date">Data de Fim:</label>
        <input type="date" name="end_date" id="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
    </div>
    <div class="form-group">
        <label for="category_filter">Categoria:</label>
        <select name="category" id="category_filter">
            <option value="">Todas</option>
            <?php
            if ($categories_result && $categories_result->num_rows > 0) {
                while ($cat_row = $categories_result->fetch_assoc()) {
                    echo '<option value="' . htmlspecialchars($cat_row['category']) . '"';
                    if ($category_filter == $cat_row['category']) {
                        echo ' selected';
                    }
                    echo '>' . htmlspecialchars($cat_row['category']) . '</option>';
                }
            }
            ?>
        </select>
    </div>
    <div>
        <input type="submit" class="btn" value="Aplicar Filtro">
        <a href="list_expenses.php" class="btn btn-secondary">Limpar Filtro</a>
    </div>
</form>

<h3 style="margin-top: 30px;">Total de Despesas Filtradas: <span style="color: #dc3545;">R$ <?php echo htmlspecialchars(number_format($total_expenses, 2, ',', '.')); ?></span></h3>

<?php if ($result && $result->num_rows > 0): ?>
<div class="list_faturamento">
    <table class="list_expenses_table">         <thead>
            <tr>
                <th>Descrição</th>
                <th>Valor</th>
                <th>Data</th>
                <th>Categoria</th>
                <th>Status</th>
                <th>Observações</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars(substr($row['description'], 0, 50)) . (strlen($row['description']) > 50 ? '...' : ''); ?></td>
                    <td>R$ <?php echo htmlspecialchars(number_format($row['amount'], 2, ',', '.')); ?></td>
                    <td><?php echo htmlspecialchars(date('d/m/Y', strtotime($row['expense_date']))); ?></td>
                    <td><?php echo htmlspecialchars($row['category']); ?></td>
                    <td>
                        <span class="status-badge status-<?php echo strtolower($row['status']); ?>">
                            <?php echo htmlspecialchars($row['status']); ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars(substr($row['notes'], 0, 70)) . (strlen($row['notes']) > 70 ? '...' : ''); ?></td>
                    <td>
                        <a href="edit_expense.php?id=<?php echo $row['id']; ?>" class="btn btn-warning">Editar</a>
                        <button type="button" class="btn btn-danger" onclick="deleteModal.show(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars(addslashes($row['description'])); ?>', 'despesa', false, null, '<?php echo $row['status']; ?>')">Excluir</button>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

    <div class="pagination" style="margin-top: 20px; text-align: center;">
        <?php if ($total_pages > 1): ?>
            <?php
            $query_params = $_GET; // Mantém os filtros existentes na URL
            ?>
            <?php if ($current_page > 1): ?>
                <?php $query_params['page'] = 1; ?>
                <a href="?<?php echo http_build_query($query_params); ?>" class="btn">Primeira</a>
                <?php $query_params['page'] = $current_page - 1; ?>
                <a href="?<?php echo http_build_query($query_params); ?>" class="btn">Anterior</a>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <?php $query_params['page'] = $i; ?>
                <a href="?<?php echo http_build_query($query_params); ?>" class="btn <?php echo ($i == $current_page) ? 'btn-primary' : 'btn-secondary'; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>

            <?php if ($current_page < $total_pages): ?>
                <?php $query_params['page'] = $current_page + 1; ?>
                <a href="?<?php echo http_build_query($query_params); ?>" class="btn">Próxima</a>
                <?php $query_params['page'] = $total_pages; ?>
                <a href="?<?php echo http_build_query($query_params); ?>" class="btn">ltima</a>
            <?php endif; ?>
        <?php endif; ?>
    </div>

<?php else: ?>
    <p>Nenhuma despesa encontrada com os filtros aplicadas.</p>
<?php endif; ?>

<script src="js/delete_modal.js"></script>

<?php
require_once 'footer.php';
?>