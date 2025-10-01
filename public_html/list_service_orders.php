<?php

// list_service_orders.php

// Lista todas as ordens de serviço



require_once 'header.php';

require_once 'db_connect.php';



// Configurações de paginação

$records_per_page = 10;

$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;

$offset = ($current_page - 1) * $records_per_page;



// Inicializa variáveis de filtro

$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

$client_id_filter = isset($_GET['client_id']) ? $_GET['client_id'] : '';



// Query base para obter os registros

$sql_base = "SELECT so.id, c.name AS client_name, so.description, so.status, so.value, so.solution, so.open_date, so.close_date, IFNULL(so.payment_status, 'pendente') as payment_status

             FROM service_orders so

             JOIN clients c ON so.client_id = c.id";



// Query para contar o total de registros (para paginação)

$sql_count = "SELECT COUNT(*) AS total_records

              FROM service_orders so

              JOIN clients c ON so.client_id = c.id";



$where_clauses = [];

$params = [];

$param_types = "";



// Adiciona filtros

if (!empty($status_filter)) {

    $where_clauses[] = "so.status = ?";

    $params[] = $status_filter;

    $param_types .= "s";

}



if (!empty($client_id_filter) && is_numeric($client_id_filter)) {

    $where_clauses[] = "so.client_id = ?";

    $params[] = (int)$client_id_filter;

    $param_types .= "i";

}



if (!empty($where_clauses)) {

    $where_clause_str = " WHERE " . implode(" AND ", $where_clauses);

    $sql_base .= $where_clause_str;

    $sql_count .= $where_clause_str;

}



$sql_base .= " ORDER BY so.open_date DESC LIMIT ? OFFSET ?";



$stmt = null;

$result = null;

$total_records = 0;



// Obter o total de registros

if ($stmt_count = $mysqli->prepare($sql_count)) {

    // Prepare parameters for bind_param

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

    // Create an array of all parameters for bind_param

    $all_params = $params;

    $all_params[] = $records_per_page;

    $all_params[] = $offset;



    // Create the type string for all parameters

    $all_param_types = $param_types . "ii";



    // Use call_user_func_array for dynamic bind_param

    // This is a more robust way to handle dynamic parameters with bind_param

    // as it allows passing an `array` of parameters.

    call_user_func_array([$stmt, 'bind_param'], array_merge([$all_param_types], $all_params));

    

    $stmt->execute();

    $result = $stmt->get_result();

    $stmt->close();

} else {

    echo "<div class='alert-message alert-error'>Erro ao preparar a consulta de registros: " . $mysqli->error . "</div>";

}



// Obtém a lista de clientes para o filtro do dropdown

$clients_query = "SELECT id, name FROM clients ORDER BY name ASC";

$clients_result_filter = $mysqli->query($clients_query);



// Mensagem de sucesso de exclusão

$delete_success_message = "";

if (isset($_GET['status']) && $_GET['status'] == 'deleted') {

    $delete_success_message = "Ordem de Serviço excluída com sucesso!";

}



// Fecha a conexão após a consulta

$mysqli->close();

?>



<h2>Lista de Ordens de Serviço</h2>

<?php
// Exibe mensagem de sucesso se foi redirecionado após salvar
if (isset($_GET['success']) && $_GET['success'] == '1' && isset($_GET['message'])) {
    echo '<div class="alert-message alert-success">' . htmlspecialchars($_GET['message']) . '</div>';
}

// Exibe mensagem de erro se houve problema na exclusão
if (isset($_GET['error']) && $_GET['error'] == '1' && isset($_GET['message'])) {
    echo '<div class="alert-message alert-error">' . htmlspecialchars($_GET['message']) . '</div>';
}
?>

<p>

    <a href="add_service_order.php" class="btn">Adicionar Nova OS</a>

</p>



<?php if (!empty($delete_success_message)): ?>

    <div class="alert-message alert-success"><?php echo $delete_success_message; ?></div>

<?php endif; ?>



<form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="get" class="filter-form">

    <div class="form-group">

        <label for="status_filter">Filtrar por Status:</label>

        <select name="status" id="status_filter">

            <option value="">Todos</option>

            <option value="Pendente" <?php echo ($status_filter == 'Pendente') ? 'selected' : ''; ?>>Pendente</option>

            <option value="Em Andamento" <?php echo ($status_filter == 'Em Andamento') ? 'selected' : ''; ?>>Em Andamento</option>

            <option value="Concluída" <?php echo ($status_filter == 'Concluída') ? 'selected' : ''; ?>>Concluída</option>

            <option value="Cancelada" <?php echo ($status_filter == 'Cancelada') ? 'selected' : ''; ?>>Cancelada</option>

        </select>

    </div>

    <div class="form-group">

        <label for="client_filter">Cliente:</label>

        <select name="client_id" id="client_filter">

            <option value="">Todos os Clientes</option>

            <?php

            if ($clients_result_filter && $clients_result_filter->num_rows > 0) {

                while ($client_row = $clients_result_filter->fetch_assoc()) {

                    echo '<option value="' . htmlspecialchars($client_row['id']) . '"';

                    if ($client_id_filter == $client_row['id']) {

                        echo ' selected';

                    }

                    echo '>' . htmlspecialchars($client_row['name']) . '</option>';

                }

            }

            ?>

        </select>

    </div>

    <div>

        <input type="submit" class="btn" value="Aplicar Filtro">

        <a href="list_service_orders.php" class="btn btn-secondary">Limpar Filtro</a>

    </div>

</form>



<?php if ($result && $result->num_rows > 0): ?>

<div class="list_orders">

    <table class="list_service_orders_table">

        <thead>

            <tr>

                <th>Cliente</th>

                <th>Descrição</th>

                <th>Status</th>

                <th>Valor</th>

                <th>Abertura</th>

                <th>Fechamento</th>

                <th>Ações</th>

            </tr>

        </thead>

        <tbody>

            <?php while ($row = $result->fetch_assoc()): ?>

                <tr>

                    <td><?php echo htmlspecialchars($row['client_name']); ?></td>

                    <td><?php echo htmlspecialchars(substr($row['description'], 0, 70)) . (strlen($row['description']) > 70 ? '...' : ''); ?></td>

                    <td><?php echo htmlspecialchars($row['status']); ?></td>

                    <td>R$ <?php echo htmlspecialchars(number_format($row['value'], 2, ',', '.')); ?></td>

                    <td><?php echo htmlspecialchars(date('d/m/Y', strtotime($row['open_date']))); ?></td>

                    <td><?php echo $row['close_date'] ? htmlspecialchars(date('d/m/Y', strtotime($row['close_date']))) : 'N/A'; ?></td>

                    <td>

                        <a href="edit_service_order.php?id=<?php echo $row['id']; ?>" class="btn btn-warning">Editar</a>

                        <button type="button" class="btn btn-danger" onclick="deleteModal.show(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['client_name']); ?>', 'ordem_servico', false, '<?php echo $row['status']; ?>', '<?php echo $row['payment_status']; ?>')">Excluir</button>

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

                <a href="?<?php echo http_build_query($query_params); ?>" class="btn">Última</a>

            <?php endif; ?>

        <?php endif; ?>

    </div>



<?php else: ?>

    <p>Nenhuma ordem de serviço encontrada com os filtros aplicados.</p>

<?php endif; ?>



<!-- Inclui o script do modal de exclusão -->
<script src="js/delete_modal.js"></script>

<?php

require_once 'footer.php';

?>