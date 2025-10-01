<?php
// list_clients.php
// Lista todos os clientes cadastrados

require_once 'header.php';
require_once 'db_connect.php';

// Configurações de paginação
$records_per_page = 10;
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $records_per_page;

// Inicializa variável de filtro
$search_name = isset($_GET['search_name']) ? trim($_GET['search_name']) : ''; // Novo filtro

// Query base para obter os registros (adicionado 'cnpj' e 'document_type')
$sql_base = "SELECT id, name, address, phone, email, cnpj, document_type FROM clients";

// Query para contar o total de registros (para paginação)
$sql_count = "SELECT COUNT(*) AS total_records FROM clients";

$where_clauses = [];
$params = [];
$param_types = "";

// Adiciona filtro de pesquisa por nome
if (!empty($search_name)) {
    $where_clauses[] = "name LIKE ?";
    $params[] = "%" . $search_name . "%";
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
$create_success_message = "";
$delete_success_message = "";

if (isset($_GET['status']) && $_GET['status'] == 'added') {
    $create_success_message = "Cliente cadastrado com sucesso!";
}

if (isset($_GET['status']) && $_GET['status'] == 'deleted') {
    $delete_success_message = "Cliente excluído com sucesso!";
}


// Fecha a conexão após a consulta
$mysqli->close();
?>

<h2>Lista de Clientes</h2>
<p>
    <a href="add_client.php" class="btn">Adicionar Novo Cliente</a>
</p>

<?php if (!empty($create_success_message)): ?>
    <div class="alert-message alert-success"><?php echo $create_success_message; ?></div>
<?php endif; ?>

<?php if (!empty($delete_success_message)): ?>
    <div class="alert-message alert-success"><?php echo $delete_success_message; ?></div>
<?php endif; ?>

<form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="get" class="filter-form">
    <div class="form-group" style="flex: 2;">
        <label for="search_name">Pesquisar por Nome:</label>
        <input type="text" name="search_name" id="search_name" value="<?php echo htmlspecialchars($search_name); ?>" placeholder="Digite o nome do cliente">
    </div>
    <div>
        <input type="submit" class="btn" value="Pesquisar">
        <a href="list_clients.php" class="btn btn-secondary">Limpar Pesquisa</a>
    </div>
</form>

<?php if ($result && $result->num_rows > 0): ?>
    <div class="list_clients_table">
        <table>
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Telefone</th>
                    <th>Email</th>
                    <th>Cnpj/Cpf</th> <!-- NOVO CAMPO -->
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                        <td><?php echo htmlspecialchars($row['phone']); ?></td>
                        <td><?php echo htmlspecialchars($row['email']); ?></td>
                        <td>
                            <?php 
                            if (!empty($row['cnpj'])) {
                                echo strtoupper($row['document_type']) . ': ' . htmlspecialchars($row['cnpj']);
                            } else {
                                echo '-';
                            }
                            ?>
                        </td> <!-- NOVO CAMPO -->
                        <td>
                            <a href="edit_client.php?id=<?php echo $row['id']; ?>" class="btn btn-warning">Editar</a>
                            <button type="button" class="btn btn-danger" onclick="deleteModal.show(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['name'], ENT_QUOTES); ?>', 'cliente', true);">Excluir</button>
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
            // Remove o parâmetro 'page' para que ele seja adicionado corretamente abaixo
            unset($query_params['page']);
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
                <a href="?<?php echo http_build_query($query_params); ?>" class="btn">Última</a>
            <?php endif; ?>
        <?php endif; ?>
    </div>

<?php else: ?>
    <p>Nenhum cliente encontrado.</p>
<?php endif; ?>

<script src="js/delete_modal.js?v=1.0"></script>

<?php
require_once 'footer.php';
?>
