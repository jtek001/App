<?php

// reports.php

// Página de relatórios de ordens de serviço



require_once 'header.php';

require_once 'db_connect.php';



// Inicializa variáveis de filtro

$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01'); // Início do mês atual

$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d'); // Hoje

$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

$payment_status_filter = isset($_GET['payment_status']) ? $_GET['payment_status'] : '';

$client_id_filter = isset($_GET['client_id']) ? $_GET['client_id'] : '';

$services_only_filter = isset($_GET['services_only']) ? $_GET['services_only'] : '';



// Verifica se as colunas de faturamento existem
$check_columns = "SHOW COLUMNS FROM service_orders LIKE 'payment_status'";
$column_exists = $mysqli->query($check_columns);

// Verifica se a tabela service_order_items existe
$check_items_table = "SHOW TABLES LIKE 'service_order_items'";
$items_table_exists = $mysqli->query($check_items_table);

if ($column_exists && $column_exists->num_rows > 0) {
    if (!empty($services_only_filter) && $items_table_exists && $items_table_exists->num_rows > 0) {
        // Query para apenas serviços - calcula valor subtraindo produtos dos itens
        $sql_report = "SELECT so.id, c.name AS client_name, so.description, so.status, 
                       CASE 
                           WHEN EXISTS(SELECT 1 FROM service_order_items soi WHERE soi.service_order_id = so.id) THEN
                               GREATEST(0, so.value - IFNULL((
                                   SELECT SUM(soi.total_price) 
                                   FROM service_order_items soi 
                                   JOIN products p ON soi.product_id = p.id 
                                   WHERE soi.service_order_id = so.id AND p.category != 'Serviços'
                               ), 0))
                           ELSE so.value
                       END AS value,
                       so.solution, so.open_date, so.close_date, IFNULL(so.payment_status, 'pendente') AS payment_status";
    } else {
        $sql_report = "SELECT so.id, c.name AS client_name, so.description, so.status, so.value, so.solution, so.open_date, so.close_date, IFNULL(so.payment_status, 'pendente') AS payment_status";
    }
} else {
    if (!empty($services_only_filter) && $items_table_exists && $items_table_exists->num_rows > 0) {
        // Query para apenas serviços - calcula valor subtraindo produtos dos itens
        $sql_report = "SELECT so.id, c.name AS client_name, so.description, so.status, 
                       CASE 
                           WHEN EXISTS(SELECT 1 FROM service_order_items soi WHERE soi.service_order_id = so.id) THEN
                               GREATEST(0, so.value - IFNULL((
                                   SELECT SUM(soi.total_price) 
                                   FROM service_order_items soi 
                                   JOIN products p ON soi.product_id = p.id 
                                   WHERE soi.service_order_id = so.id AND p.category != 'Serviços'
                               ), 0))
                           ELSE so.value
                       END AS value,
                       so.solution, so.open_date, so.close_date, 'pendente' AS payment_status";
    } else {
        $sql_report = "SELECT so.id, c.name AS client_name, so.description, so.status, so.value, so.solution, so.open_date, so.close_date, 'pendente' AS payment_status";
    }
}

if (!empty($services_only_filter) && $items_table_exists && $items_table_exists->num_rows > 0) {
    // Query para somar apenas valores de serviços
    $sql_total_value = "SELECT SUM(
                            CASE 
                                WHEN EXISTS(SELECT 1 FROM service_order_items soi WHERE soi.service_order_id = so.id) THEN
                                    GREATEST(0, so.value - IFNULL((
                                        SELECT SUM(soi.total_price) 
                                        FROM service_order_items soi 
                                        JOIN products p ON soi.product_id = p.id 
                                        WHERE soi.service_order_id = so.id AND p.category != 'Serviços'
                                    ), 0))
                                ELSE so.value
                            END
                        ) AS total_sum";
} else {
    $sql_total_value = "SELECT SUM(so.value) AS total_sum";
}



$where_clauses = [];

$params = [];

$param_types = "";



// Adiciona filtros

if (!empty($start_date)) {

    $where_clauses[] = "so.open_date >= ?";

    $params[] = $start_date . " 00:00:00";

    $param_types .= "s";

}

if (!empty($end_date)) {

    $where_clauses[] = "so.open_date <= ?";

    $params[] = $end_date . " 23:59:59";

    $param_types .= "s";

}

if (!empty($status_filter)) {

    $where_clauses[] = "so.status = ?";

    $params[] = $status_filter;

    $param_types .= "s";

}

if (!empty($payment_status_filter)) {
    if ($column_exists && $column_exists->num_rows > 0) {
        $where_clauses[] = "IFNULL(so.payment_status, 'pendente') = ?";
    } else {
        $where_clauses[] = "? = 'pendente'"; // Se as colunas não existem, só aceita filtro 'pendente'
    }
    $params[] = $payment_status_filter;
    $param_types .= "s";
}

if (!empty($client_id_filter) && is_numeric($client_id_filter)) {

    $where_clauses[] = "so.client_id = ?";

    $params[] = (int)$client_id_filter;

    $param_types .= "i";

}



// Constrói a cláusula WHERE para todas as consultas baseadas nos filtros

$where_clause_str = "";

if (!empty($where_clauses)) {

    $where_clause_str = " WHERE " . implode(" AND ", $where_clauses);

}



$sql_report .= " FROM service_orders so JOIN clients c ON so.client_id = c.id" . $where_clause_str;

$sql_total_value .= " FROM service_orders so JOIN clients c ON so.client_id = c.id" . $where_clause_str;



$sql_report .= " ORDER BY so.open_date DESC";



$report_result = null;

$total_value_result = null;

$total_sum = 0;



// Prepara e executa a consulta de relatório

if ($stmt_report = $mysqli->prepare($sql_report)) {

    if (!empty($params)) {

        call_user_func_array([$stmt_report, 'bind_param'], array_merge([$param_types], $params));

    }

    $stmt_report->execute();

    $report_result = $stmt_report->get_result();

    $stmt_report->close();

} else {

    echo "<div class='alert-message alert-error'>Erro ao preparar a consulta de relatório: " . $mysqli->error . "</div>";

}



// Prepara e executa a consulta de totalização

if ($stmt_total_value = $mysqli->prepare($sql_total_value)) {

    if (!empty($params)) {

        call_user_func_array([$stmt_total_value, 'bind_param'], array_merge([$param_types], $params));

    }

    $stmt_total_value->execute();

    $total_value_result = $stmt_total_value->get_result();

    if ($total_value_result && $row_total = $total_value_result->fetch_assoc()) {

        $total_sum = $row_total['total_sum'] ?? 0;

    }

    $stmt_total_value->close();

} else {

    echo "<div class='alert-message alert-error'>Erro ao preparar a consulta de totalização: " . $mysqli->error . "</div>";

}



// --- Dados para o gráfico de Pareto (Top 6 Clientes por Faturamento com filtros) ---



// Adiciona o filtro de status 'Concluída' e os IDs de cliente '12' e '35'

// Certifique-se de que esses IDs não entrem em conflito com os filtros dinâmicos

$pareto_specific_where = [];

$pareto_specific_params = [];

$pareto_specific_param_types = "";



// Apenas se o status não for "Concluída" ou se não houver filtro de status, adicionamos



// Adiciona os IDs de cliente a serem excluídos, se não houver um filtro de cliente específico

// Se já houver um filtro de cliente, o Pareto o respeitará.

if (empty($client_id_filter)) {

    $pareto_specific_where[] = "c.id <> '12'";

    $pareto_specific_where[] = "c.id <> '35'";

}





// Combina as cláusulas WHERE existentes com as específicas do Pareto

$final_pareto_where_clauses = $where_clauses;

foreach ($pareto_specific_where as $clause) {

    if (!in_array($clause, $final_pareto_where_clauses)) { // Evita duplicatas

        $final_pareto_where_clauses[] = $clause;

    }

}



$final_pareto_params = $params;

foreach ($pareto_specific_params as $param) {

    $final_pareto_params[] = $param;

}

$final_pareto_param_types = $param_types . $pareto_specific_param_types;





$final_pareto_where_clause_str = "";

if (!empty($final_pareto_where_clauses)) {

    $final_pareto_where_clause_str = " WHERE " . implode(" AND ", $final_pareto_where_clauses);

}



if (!empty($services_only_filter) && $items_table_exists && $items_table_exists->num_rows > 0) {
    // Consulta final do Pareto com apenas serviços
    $sql_top_clients_filtered_final = "SELECT c.name AS client_name, SUM(
                                           CASE 
                                               WHEN EXISTS(SELECT 1 FROM service_order_items soi WHERE soi.service_order_id = so.id) THEN
                                                   GREATEST(0, so.value - IFNULL((
                                                       SELECT SUM(soi.total_price) 
                                                       FROM service_order_items soi 
                                                       JOIN products p ON soi.product_id = p.id 
                                                       WHERE soi.service_order_id = so.id AND p.category != 'Serviços'
                                                   ), 0))
                                               ELSE so.value
                                           END
                                       ) AS total_client_value
                                       FROM service_orders so
                                       JOIN clients c ON so.client_id = c.id" . $final_pareto_where_clause_str . "
                                       GROUP BY c.name
                                       ORDER BY total_client_value DESC
                                       LIMIT 6";

    $sql_overall_total_os_value_filtered_final = "SELECT SUM(
                                                      CASE 
                                                          WHEN EXISTS(SELECT 1 FROM service_order_items soi WHERE soi.service_order_id = so.id) THEN
                                                              GREATEST(0, so.value - IFNULL((
                                                                  SELECT SUM(soi.total_price) 
                                                                  FROM service_order_items soi 
                                                                  JOIN products p ON soi.product_id = p.id 
                                                                  WHERE soi.service_order_id = so.id AND p.category != 'Serviços'
                                                              ), 0))
                                                          ELSE so.value
                                                      END
                                                  ) AS overall_total
                                                  FROM service_orders so
                                                  JOIN clients c ON so.client_id = c.id" . $final_pareto_where_clause_str;
} else {
    // Consulta final padrão do Pareto
    $sql_top_clients_filtered_final = "SELECT c.name AS client_name, SUM(so.value) AS total_client_value
                                       FROM service_orders so
                                       JOIN clients c ON so.client_id = c.id" . $final_pareto_where_clause_str . "
                                       GROUP BY c.name
                                       ORDER BY total_client_value DESC
                                       LIMIT 6";

    $sql_overall_total_os_value_filtered_final = "SELECT SUM(value) AS overall_total
                                                  FROM service_orders so
                                                  JOIN clients c ON so.client_id = c.id" . $final_pareto_where_clause_str;
}





$pareto_client_labels_filtered = [];

$pareto_client_values_filtered = [];

$pareto_cumulative_percentages_filtered = [];

$total_all_os_values_filtered = 0;





// Prepara e executa a consulta de total geral para o Pareto filtrado

if ($stmt_overall_pareto = $mysqli->prepare($sql_overall_total_os_value_filtered_final)) {

    if (!empty($final_pareto_params)) {

        call_user_func_array([$stmt_overall_pareto, 'bind_param'], array_merge([$final_pareto_param_types], $final_pareto_params));

    }

    $stmt_overall_pareto->execute();

    $res_overall_total_filtered = $stmt_overall_pareto->get_result();

    if ($res_overall_total_filtered && $row_overall_total_filtered = $res_overall_total_filtered->fetch_assoc()) {

        $total_all_os_values_filtered = $row_overall_total_filtered['overall_total'] ?? 0;

    }

    $stmt_overall_pareto->close();

} else {

    error_log("Erro ao preparar consulta de Total Geral de OS para Pareto (filtrado): " . $mysqli->error);

}



// Prepara e executa a consulta dos top clientes para o Pareto filtrado

if ($stmt_top_clients_pareto = $mysqli->prepare($sql_top_clients_filtered_final)) {

    if (!empty($final_pareto_params)) {

        call_user_func_array([$stmt_top_clients_pareto, 'bind_param'], array_merge([$final_pareto_param_types], $final_pareto_params));

    }

    $stmt_top_clients_pareto->execute();

    $result_top_clients_filtered = $stmt_top_clients_pareto->get_result();



    $current_cumulative_sum_pareto_filtered = 0;

    if ($result_top_clients_filtered && $result_top_clients_filtered->num_rows > 0) {

        while ($row = $result_top_clients_filtered->fetch_assoc()) {

            $pareto_client_labels_filtered[] = htmlspecialchars($row['client_name']);

            $pareto_client_values_filtered[] = $row['total_client_value'];

            $current_cumulative_sum_pareto_filtered += $row['total_client_value'];

            

            $cumulative_percentage = ($total_all_os_values_filtered > 0) ? ($current_cumulative_sum_pareto_filtered / $total_all_os_values_filtered) * 100 : 0;

            $pareto_cumulative_percentages_filtered[] = round($cumulative_percentage, 2);

        }

    }

    $stmt_top_clients_pareto->close();

} else {

    echo "<div class='alert-message alert-error'>Erro ao preparar a consulta de Top Clientes para Pareto (filtrado): " . $mysqli->error . "</div>";

}





// Obtém a lista de clientes para o filtro do dropdown

$clients_query = "SELECT id, name FROM clients ORDER BY name ASC";

$clients_result_filter = $mysqli->query($clients_query);



$mysqli->close();

?>



<h2>Relatório de Ordens de Serviço</h2>



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

        <label for="status_filter">Status:</label>

        <select name="status" id="status_filter">

            <option value="">Todos</option>

            <option value="Pendente" <?php echo ($status_filter == 'Pendente') ? 'selected' : ''; ?>>Pendente</option>

            <option value="Em Andamento" <?php echo ($status_filter == 'Em Andamento') ? 'selected' : ''; ?>>Em Andamento</option>

            <option value="Concluída" <?php echo ($status_filter == 'Concluída') ? 'selected' : ''; ?>>Concluída</option>

            <option value="Cancelada" <?php echo ($status_filter == 'Cancelada') ? 'selected' : ''; ?>>Cancelada</option>

        </select>

    </div>

    <div class="form-group">

        <label for="payment_status_filter">Pagamento:</label>

        <select name="payment_status" id="payment_status_filter">

            <option value="">Todos</option>

            <option value="pendente" <?php echo ($payment_status_filter == 'pendente') ? 'selected' : ''; ?>>Pendente</option>

            <option value="previsao" <?php echo ($payment_status_filter == 'previsao') ? 'selected' : ''; ?>>Previsão</option>

            <option value="faturado" <?php echo ($payment_status_filter == 'faturado') ? 'selected' : ''; ?>>Faturado</option>

            <option value="recebido" <?php echo ($payment_status_filter == 'recebido') ? 'selected' : ''; ?>>Recebido</option>

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

    <div class="form-group">

        <label>

            <input type="checkbox" name="services_only" value="1" <?php echo (!empty($services_only_filter)) ? 'checked' : ''; ?>>

            Apenas Serviços

        </label>

        <small style="color: #666; display: block; margin-top: 5px;">

            Calcula OS sem adicional.

        </small>

    </div>

    <div>

        <input type="submit" class="btn" value="Aplicar Filtro">

        <a href="reports.php" class="btn btn-secondary">Limpar Filtros</a>

    </div>

</form>



<?php if (!empty($services_only_filter)): ?>
<h3 style="margin-top: 30px;">Total de Valores de Serviços no Período: <span style="color: #28a745;">R$ <?php echo htmlspecialchars(number_format($total_sum, 2, ',', '.')); ?></span></h3>
<?php else: ?>
<h3 style="margin-top: 30px;">Total de Valores no Período: <span style="color: #28a745;">R$ <?php echo htmlspecialchars(number_format($total_sum, 2, ',', '.')); ?></span></h3>
<?php endif; ?>



<?php if ($report_result && $report_result->num_rows > 0): ?>

<div class="list_report">    

	<table>

        <thead>

            <tr>

                <th>Cliente</th>

				<th>Descrição</th>

                <th>Status</th>

                <th>Pagamento</th>

                <th>Valor</th>

                <th>Abertura</th>

                <th>Fechamento</th>

            </tr>

        </thead>

        <tbody>

            <?php while ($row = $report_result->fetch_assoc()): ?>
                <?php
                // Cores para status de pagamento
                $payment_status_colors = [
                    'pendente' => '#dc3545',
                    'previsao' => '#ffc107',
                    'faturado' => '#17a2b8', 
                    'recebido' => '#28a745'
                ];
                $payment_color = $payment_status_colors[$row['payment_status']] ?? '#6c757d';
                ?>
                <tr>

                    <td><?php echo htmlspecialchars(substr($row['client_name'], 0, 70)) . (strlen($row['client_name']) > 70 ? '...' : ''); ?></td>

					<td><?php echo htmlspecialchars(substr($row['description'], 0, 70)) . (strlen($row['description']) > 70 ? '...' : ''); ?></td>

                    <td><?php echo htmlspecialchars($row['status']); ?></td>

                    <td><span style="color: <?php echo $payment_color; ?>; font-weight: bold;"><?php echo ucfirst($row['payment_status']); ?></span></td>

                    <td>R$ <?php echo htmlspecialchars(number_format($row['value'], 2, ',', '.')); ?></td>

                    <td><?php echo htmlspecialchars(date('d/m/Y', strtotime($row['open_date']))); ?></td>

                    <td><?php echo $row['close_date'] ? htmlspecialchars(date('d/m/Y', strtotime($row['close_date']))) : 'N/A'; ?></td>

                </tr>

            <?php endwhile; ?>

        </tbody>

    </table>

</div>

<?php else: ?>

    <p>Nenhuma ordem de serviço encontrada para os filtros selecionados.</p>

<?php endif; ?>



<?php if (!empty($services_only_filter)): ?>
<h3 style="margin-top: 40px;">Pareto de Clientes - Apenas Serviços</h3>
<?php else: ?>
<h3 style="margin-top: 40px;">Pareto de Clientes</h3>
<?php endif; ?>

<div class="chart-container pareto-chart">

    <canvas id="paretoReportChart"></canvas>

</div>



<?php if (empty($pareto_client_labels_filtered)): ?>

    <p style="text-align: center; margin-top: 20px;">Nenhum dado de ordem de serviço encontrado para gerar o gráfico de Pareto com os filtros atuais.</p>

<?php else: ?>

<script>

    // Dados para o grfico de Pareto (obtidos do PHP)

    const paretoReportClientLabels = <?php echo json_encode($pareto_client_labels_filtered); ?>;

    const paretoReportClientValues = <?php echo json_encode($pareto_client_values_filtered); ?>;

    const paretoReportCumulativePercentages = <?php echo json_encode($pareto_cumulative_percentages_filtered); ?>;



    const paretoReportCtx = document.getElementById('paretoReportChart').getContext('2d');

    const paretoReportChart = new Chart(paretoReportCtx, {

        type: 'bar', // Tipo principal é barra

        data: {

            labels: paretoReportClientLabels,

            datasets: [

                {

                    label: 'Valor Total (R$)',

                    data: paretoReportClientValues,

                    backgroundColor: 'rgba(75, 192, 192, 0.7)', // Cor para as barras

                    borderColor: 'rgba(75, 192, 192, 1)',

                    borderWidth: 1,

                    yAxisID: 'y-axis-value', // Eixo Y para valores

                },

                {

                    label: 'Acumulado (%)',

                    data: paretoReportCumulativePercentages,

                    type: 'line', // Tipo secundário é linha

                    borderColor: 'rgba(255, 99, 132, 1)', // Cor para a linha

                    backgroundColor: 'rgba(255, 99, 132, 0.2)',

                    fill: false,

                    yAxisID: 'y-axis-percentage', // Eixo Y para porcentagem

                    tension: 0.1, // Suaviza a linha

                    pointRadius: 5,

                    pointHoverRadius: 7,

                }

            ]

        },

        options: {

            responsive: true,

            maintainAspectRatio: true,

            plugins: {

                legend: {

                    position: 'top',

                },

                tooltip: {

                    callbacks: {

                        label: function(tooltipItem) {

                            if (tooltipItem.dataset.label === 'Valor Total (R$)') {

                                return tooltipItem.label + ': R$ ' + tooltipItem.raw.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

                            } else {

                                return tooltipItem.label + ': ' + tooltipItem.raw + '%';

                            }

                        }

                    }

                }

            },

            scales: {

                'y-axis-value': {

                    type: 'linear',

                    position: 'left',

                    beginAtZero: true,

                    title: {

                        display: true,

                        text: 'Valor (R$)'

                    },

                    ticks: {

                        callback: function(value, index, values) {

                            return 'R$ ' + value.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

                        }

                    }

                },

                'y-axis-percentage': {

                    type: 'linear',

                    position: 'right',

                    beginAtZero: true,

                    max: 100, // Porcentagem vai até 100%

                    title: {

                        display: true,

                        text: 'Acumulado (%)'

                    },

                    ticks: {

                        callback: function(value, index, values) {

                            return value + '%';

                        }

                    },

                    grid: {

                        drawOnChartArea: false, // Oculta as linhas de grade para este eixo

                    },

                }

            }

        }

    });

</script>

<?php endif; ?>



<?php

require_once 'footer.php';

?>

