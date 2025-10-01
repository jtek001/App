<?php

// dashboard.php

// Página inicial após o login



require_once 'header.php'; // Inclui o cabeçalho e inicia a sessão

require_once 'db_connect.php'; // Inclui a conexão com o banco de dados



// Define o fuso horário para garantir consistência nas datas

// Mantenha esta linha que já resolveu parte do problema

date_default_timezone_set('America/Sao_Paulo'); // Altere para seu fuso horário se for diferente



// Contagem de clientes

$sql_clients = "SELECT COUNT(*) AS total_clients FROM clients";

$result_clients = $mysqli->query($sql_clients);

$total_clients = 0;

if ($result_clients) {

    $row_clients = $result_clients->fetch_assoc();

    $total_clients = $row_clients['total_clients'];

}



// Contagem de ordens de serviço pendentes

$sql_pending_os = "SELECT COUNT(*) AS total_pending FROM service_orders WHERE status = 'Pendente'";

$result_pending_os = $mysqli->query($sql_pending_os);

$total_pending_os = 0;

if ($result_pending_os) {

    $row_pending_os = $result_pending_os->fetch_assoc();

    $total_pending_os = $row_pending_os['total_pending'];

}



// Contagem de ordens de serviço concluídas (Mês Atual)

$current_year_month = date('Y-m'); // Obtém o ano e mês atual para filtrar

$sql_completed_os = "SELECT COUNT(*) AS total_completed FROM service_orders WHERE status = 'Concluída' AND DATE_FORMAT(close_date, '%Y-%m') = ?";

$total_completed_os = 0;

if ($stmt_completed_os_count = $mysqli->prepare($sql_completed_os)) {

    $stmt_completed_os_count->bind_param("s", $current_year_month);

    $stmt_completed_os_count->execute();

    $result_completed_os = $stmt_completed_os_count->get_result();

    $row_completed_os = $result_completed_os->fetch_assoc();

    $total_completed_os = $row_completed_os['total_completed'] ?? 0;

    $stmt_completed_os_count->close();

}



// --- Novas consultas para total de valores ---



// Obtém o ano e mês atual para filtrar

$current_year_month = date('Y-m');



// Soma dos valores de pagamentos recebidos para o mês atual
// Verifica se as colunas de faturamento existem
$check_columns = "SHOW COLUMNS FROM service_orders LIKE 'payment_value'";
$column_exists = $mysqli->query($check_columns);

$total_value_completed = 0;

if ($column_exists && $column_exists->num_rows > 0) {
    // Com colunas de faturamento - usa payment_value com status recebido E status concluída baseado na close_date
    $sql_value_completed = "SELECT SUM(payment_value) AS total_value_completed FROM service_orders WHERE status = 'Concluída' AND payment_status = 'recebido' AND DATE_FORMAT(close_date, '%Y-%m') = ?";
} else {
    // Sem colunas de faturamento - usa value com status concluída
    $sql_value_completed = "SELECT SUM(value) AS total_value_completed FROM service_orders WHERE status = 'Concluída' AND DATE_FORMAT(close_date, '%Y-%m') = ?";
}

if ($stmt_completed = $mysqli->prepare($sql_value_completed)) {

    $stmt_completed->bind_param("s", $current_year_month);

    $stmt_completed->execute();

    $result_value_completed = $stmt_completed->get_result();

    $row_value_completed = $result_value_completed->fetch_assoc();

    $total_value_completed = $row_value_completed['total_value_completed'] ?? 0;

    $stmt_completed->close();

}





// Consulta para DANFSe - OS CNPJ com payment_status recebido/faturado com PDF vs total de OS CNPJ recebido/faturado
$sql_danfse_stats = "SELECT 
    COUNT(*) as total_cnpj_os,
    SUM(CASE WHEN nfse_pdf_path IS NOT NULL AND nfse_pdf_path != '' THEN 1 ELSE 0 END) as os_with_pdf
FROM service_orders so 
JOIN clients c ON so.client_id = c.id 
WHERE c.document_type = 'cnpj' 
AND IFNULL(so.payment_status, 'pendente') IN ('recebido', 'faturado')";

$result_danfse_stats = $mysqli->query($sql_danfse_stats);
$total_cnpj_os = 0;
$os_with_pdf = 0;
$os_without_pdf = 0;

if ($result_danfse_stats) {
    $row_danfse = $result_danfse_stats->fetch_assoc();
    $total_cnpj_os = $row_danfse['total_cnpj_os'] ?? 0;
    $os_with_pdf = $row_danfse['os_with_pdf'] ?? 0;
    $os_without_pdf = $total_cnpj_os - $os_with_pdf;
}

// Soma total de despesas para o mês atual
$current_month = date('Y-m');
$sql_monthly_expenses = "SELECT SUM(amount) AS total_expenses_month FROM expenses WHERE DATE_FORMAT(expense_date, '%Y-%m') = ? and status = 'Pago'";
$total_expenses_month = 0;

if ($stmt_expenses = $mysqli->prepare($sql_monthly_expenses)) {
    $stmt_expenses->bind_param("s", $current_month);
    $stmt_expenses->execute();
    $result_expenses = $stmt_expenses->get_result();
    $row_expenses = $result_expenses->fetch_assoc();
    $total_expenses_month = $row_expenses['total_expenses_month'] ?? 0;
    $stmt_expenses->close();
}





// Soma total de valores por mês para os últimos 6 meses - Lógica de Data CORRIGIDA

$monthly_values = [];

$labels_months = [];

for ($i = 5; $i >= 0; $i--) {

    // Calcula o timestamp para o primeiro dia do mês, N meses atrás.

    // Isso evita problemas de "rolagem" de dia do mês que podem ocorrer com strtotime em certas datas.

    $target_month_timestamp = mktime(0, 0, 0, date("m") - $i, 1, date("Y"));



    $month_for_query = date('Y-m', $target_month_timestamp);

    $month_name_for_label = date('M/Y', $target_month_timestamp); // Ex: Jan/2023

    

    $labels_months[] = $month_name_for_label;



    // Usa payment_value com status recebido E concluída baseado na close_date se as colunas existem
    if ($column_exists && $column_exists->num_rows > 0) {
        $sql_monthly_sum = "SELECT SUM(payment_value) AS monthly_total FROM service_orders WHERE status = 'Concluída' AND payment_status = 'recebido' and DATE_FORMAT(close_date, '%Y-%m') = ?";
    } else {
        $sql_monthly_sum = "SELECT SUM(value) AS monthly_total FROM service_orders WHERE status = 'Concluída' and DATE_FORMAT(close_date, '%Y-%m') = ?";
    }

    if ($stmt_monthly = $mysqli->prepare($sql_monthly_sum)) {

        $stmt_monthly->bind_param("s", $month_for_query);

        $stmt_monthly->execute();

        $result_monthly = $stmt_monthly->get_result();

        $row_monthly = $result_monthly->fetch_assoc();

        $monthly_values[] = $row_monthly['monthly_total'] ?? 0;

        $stmt_monthly->close();

    } else {

        $monthly_values[] = 0; // Em caso de erro, adiciona 0

    }

}

// Verifica se a tabela service_order_items existe para calcular valores de serviços
$check_items_table = "SHOW TABLES LIKE 'service_order_items'";
$items_table_exists = $mysqli->query($check_items_table);

// Soma total de valores de SERVIÇOS por mês para os últimos 6 meses
$monthly_services_values = [];

for ($i = 5; $i >= 0; $i--) {
    $target_month_timestamp = mktime(0, 0, 0, date("m") - $i, 1, date("Y"));
    $month_for_query = date('Y-m', $target_month_timestamp);
    
    if ($items_table_exists && $items_table_exists->num_rows > 0) {
        // Calcula valores apenas de serviços (subtraindo produtos dos itens)
        if ($column_exists && $column_exists->num_rows > 0) {
            $sql_monthly_services = "SELECT SUM(
                                        CASE 
                                            WHEN EXISTS(SELECT 1 FROM service_order_items soi WHERE soi.service_order_id = so.id) THEN
                                                GREATEST(0, so.payment_value - IFNULL((
                                                    SELECT SUM(soi.total_price) 
                                                    FROM service_order_items soi 
                                                    JOIN products p ON soi.product_id = p.id 
                                                    WHERE soi.service_order_id = so.id AND p.category != 'Serviços'
                                                ), 0))
                                            ELSE so.payment_value
                                        END
                                    ) AS monthly_services_total 
                                    FROM service_orders so 
                                    WHERE status = 'Concluída' AND payment_status = 'recebido' AND DATE_FORMAT(close_date, '%Y-%m') = ?";
        } else {
            $sql_monthly_services = "SELECT SUM(
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
                                    ) AS monthly_services_total 
                                    FROM service_orders so 
                                    WHERE status = 'Concluída' AND DATE_FORMAT(close_date, '%Y-%m') = ?";
        }
    } else {
        // Sem tabela de itens, usa o valor total (assumindo que é tudo serviço)
        if ($column_exists && $column_exists->num_rows > 0) {
            $sql_monthly_services = "SELECT SUM(payment_value) AS monthly_services_total FROM service_orders WHERE status = 'Concluída' AND payment_status = 'recebido' and DATE_FORMAT(close_date, '%Y-%m') = ?";
        } else {
            $sql_monthly_services = "SELECT SUM(value) AS monthly_services_total FROM service_orders WHERE status = 'Concluída' and DATE_FORMAT(close_date, '%Y-%m') = ?";
        }
    }

    if ($stmt_monthly_services = $mysqli->prepare($sql_monthly_services)) {
        $stmt_monthly_services->bind_param("s", $month_for_query);
        $stmt_monthly_services->execute();
        $result_monthly_services = $stmt_monthly_services->get_result();
        $row_monthly_services = $result_monthly_services->fetch_assoc();
        $monthly_services_values[] = $row_monthly_services['monthly_services_total'] ?? 0;
        $stmt_monthly_services->close();
    } else {
        $monthly_services_values[] = 0; // Em caso de erro, adiciona 0
    }
}



// Consulta para ordens pendentes há mais de 5 dias

$sql_late_pending_os = "SELECT COUNT(*) AS total_late_pending FROM service_orders WHERE payment_status = 'pendente' AND payment_date < DATE_SUB(NOW(), INTERVAL 5 DAY)";

$result_late_pending_os = $mysqli->query($sql_late_pending_os);

$total_late_pending_os = 0;

if ($result_late_pending_os) {

    $row_late_pending_os = $result_late_pending_os->fetch_assoc();

    $total_late_pending_os = $row_late_pending_os['total_late_pending'];

}

// Consulta para despesas em atraso (status pendente e data menor ou igual a hoje)
$sql_overdue_expenses = "SELECT COUNT(*) AS total_overdue_expenses FROM expenses WHERE status = 'Pendente' AND DATE(expense_date) <= CURDATE()";

$result_overdue_expenses = $mysqli->query($sql_overdue_expenses);

$total_overdue_expenses = 0;

if ($result_overdue_expenses) {
    $row_overdue_expenses = $result_overdue_expenses->fetch_assoc();
    $total_overdue_expenses = $row_overdue_expenses['total_overdue_expenses'];
}



// Últimas 10 ordens de serviço (recentes)
// Inclui payment_status se as colunas existem
if ($column_exists && $column_exists->num_rows > 0) {
    $sql_latest_os = "SELECT so.id, c.name AS client_name, so.description, so.status, so.value, so.solution, so.open_date, so.close_date, IFNULL(so.payment_status, 'pendente') AS payment_status
                      FROM service_orders so
                      JOIN clients c ON so.client_id = c.id
                      ORDER BY so.open_date DESC
                      LIMIT 10";
} else {
    $sql_latest_os = "SELECT so.id, c.name AS client_name, so.description, so.status, so.value, so.solution, so.open_date, so.close_date, 'pendente' AS payment_status
                      FROM service_orders so
                      JOIN clients c ON so.client_id = c.id
                      ORDER BY so.open_date DESC
                      LIMIT 10";
}

$result_latest_os = $mysqli->query($sql_latest_os);



// --- Dados para o gráfico de Pareto (Top 6 Clientes por Valor) ---
// Usa payment_value com status recebido se as colunas existem
if ($column_exists && $column_exists->num_rows > 0) {
    $sql_top_clients = "SELECT c.name AS client_name, SUM(so.payment_value) AS total_client_value
                        FROM service_orders so
                        JOIN clients c ON so.client_id = c.id
                        WHERE so.status = 'Concluída' AND so.payment_status = 'recebido' AND c.id <> '12' AND c.id <> '35'
                        GROUP BY c.name
                        ORDER BY total_client_value DESC
                        LIMIT 6";
} else {
    $sql_top_clients = "SELECT c.name AS client_name, SUM(so.value) AS total_client_value
                        FROM service_orders so
                        JOIN clients c ON so.client_id = c.id
                        WHERE so.status = 'Concluída' AND c.id <> '12' AND c.id <> '35'
                        GROUP BY c.name
                        ORDER BY total_client_value DESC
                        LIMIT 6";
}

$result_top_clients = $mysqli->query($sql_top_clients);



$pareto_client_labels = [];

$pareto_client_values = [];

$pareto_cumulative_percentages = [];

$total_all_os_values = 0;



// Primeiro, obtenha o valor total de todas as ordens de serviço para o cálculo da porcentagem acumulada
if ($column_exists && $column_exists->num_rows > 0) {
    $sql_overall_total_os_value = "SELECT SUM(payment_value) AS overall_total FROM service_orders WHERE status = 'Concluída' AND payment_status = 'recebido' AND client_id <> '12' AND client_id <> '35'";
} else {
    $sql_overall_total_os_value = "SELECT SUM(value) AS overall_total FROM service_orders WHERE status = 'Concluída' AND client_id <> '12' AND client_id <> '35'";
}

$res_overall_total = $mysqli->query($sql_overall_total_os_value);

if ($res_overall_total) {

    $row_overall_total = $res_overall_total->fetch_assoc();

    $total_all_os_values = $row_overall_total['overall_total'] ?? 0;

} else {

    error_log("Erro na consulta de Total Geral de OS para Pareto: " . $mysqli->error);

}





$current_cumulative_sum_pareto = 0;

if ($result_top_clients) { // Verifique se a consulta foi bem-sucedida

    if ($result_top_clients->num_rows > 0) {

        while ($row = $result_top_clients->fetch_assoc()) {

            $pareto_client_labels[] = htmlspecialchars($row['client_name']);

            $pareto_client_values[] = $row['total_client_value'];

            $current_cumulative_sum_pareto += $row['total_client_value'];

            

            $cumulative_percentage = ($total_all_os_values > 0) ? ($current_cumulative_sum_pareto / $total_all_os_values) * 100 : 0;

            $pareto_cumulative_percentages[] = round($cumulative_percentage, 2);

        }

    }

} else {

    error_log("Erro na consulta de Top Clientes para Pareto: " . $mysqli->error);

}



// Log dos dados para depuração no console do navegador

// var_dump($pareto_client_labels);

// var_dump($pareto_client_values);

// var_dump($pareto_cumulative_percentages);

// var_dump($total_all_os_values);





// --- Dados para o gráfico de rosca de grupos de serviços ---
$service_groups_data = [];

// Consulta para obter faturamento por item individual da categoria Serviços (mês atual)
// Calcula o valor real dos serviços: (total OS - itens não-serviços) distribuído proporcionalmente
if ($items_table_exists && $items_table_exists->num_rows > 0) {
    // Com tabela de itens - calcula valor real dos serviços
    if ($column_exists && $column_exists->num_rows > 0) {
        $sql_service_groups = "SELECT 
                                p.name as service_name,
                                SUM(
                                    -- Calcula o valor proporcional do serviço
                                    (soi.total_price / 
                                        (SELECT SUM(soi2.total_price) 
                                         FROM service_order_items soi2 
                                         JOIN products p2 ON soi2.product_id = p2.id 
                                         WHERE soi2.service_order_id = so.id AND p2.category = 'Serviços')
                                    ) * 
                                    GREATEST(0, so.payment_value - IFNULL((
                                        SELECT SUM(soi3.total_price) 
                                        FROM service_order_items soi3 
                                        JOIN products p3 ON soi3.product_id = p3.id 
                                        WHERE soi3.service_order_id = so.id AND p3.category != 'Serviços'
                                    ), 0))
                                ) AS group_total
                            FROM service_orders so
                            JOIN service_order_items soi ON so.id = soi.service_order_id
                            JOIN products p ON soi.product_id = p.id
                            WHERE p.category = 'Serviços' 
                            AND so.status = 'Concluída' 
                            AND so.payment_status = 'recebido' 
                            AND DATE_FORMAT(so.close_date, '%Y-%m') = ?
                            GROUP BY p.id, p.name
                            HAVING group_total > 0
                            ORDER BY group_total DESC
                            LIMIT 10";
    } else {
        $sql_service_groups = "SELECT 
                                p.name as service_name,
                                SUM(
                                    -- Calcula o valor proporcional do serviço
                                    (soi.total_price / 
                                        (SELECT SUM(soi2.total_price) 
                                         FROM service_order_items soi2 
                                         JOIN products p2 ON soi2.product_id = p2.id 
                                         WHERE soi2.service_order_id = so.id AND p2.category = 'Serviços')
                                    ) * 
                                    GREATEST(0, so.value - IFNULL((
                                        SELECT SUM(soi3.total_price) 
                                        FROM service_order_items soi3 
                                        JOIN products p3 ON soi3.product_id = p3.id 
                                        WHERE soi3.service_order_id = so.id AND p3.category != 'Serviços'
                                    ), 0))
                                ) AS group_total
                            FROM service_orders so
                            JOIN service_order_items soi ON so.id = soi.service_order_id
                            JOIN products p ON soi.product_id = p.id
                            WHERE p.category = 'Serviços' 
                            AND so.status = 'Concluída' 
                            AND DATE_FORMAT(so.close_date, '%Y-%m') = ?
                            GROUP BY p.id, p.name
                            HAVING group_total > 0
                            ORDER BY group_total DESC
                            LIMIT 10";
    }
} else {
    // Sem tabela de itens - não há como separar por item individual
    // Retorna array vazio para não quebrar o gráfico
    $service_groups_data = [];
}

if ($items_table_exists && $items_table_exists->num_rows > 0) {
    if ($stmt_service_groups = $mysqli->prepare($sql_service_groups)) {
        $stmt_service_groups->bind_param("s", $current_year_month);
        $stmt_service_groups->execute();
        $result_service_groups = $stmt_service_groups->get_result();
        
        $all_services = [];
        while ($row = $result_service_groups->fetch_assoc()) {
            $all_services[] = [
                'category' => $row['service_name'],
                'total' => $row['group_total'] ?? 0
            ];
        }
        
        // Se há mais de 8 itens, agrupa os menores em "Outros"
        if (count($all_services) > 8) {
            $top_services = array_slice($all_services, 0, 7);
            $other_services = array_slice($all_services, 7);
            
            $others_total = 0;
            foreach ($other_services as $service) {
                $others_total += $service['total'];
            }
            
            $service_groups_data = $top_services;
            if ($others_total > 0) {
                $service_groups_data[] = [
                    'category' => 'Outros Serviços',
                    'total' => $others_total
                ];
            }
        } else {
            $service_groups_data = $all_services;
        }
        $stmt_service_groups->close();
    }
}

// Se não há dados de serviços específicos, mas há faturamento geral, cria entrada genérica
if (empty($service_groups_data) && $total_value_completed > 0) {
    $service_groups_data[] = [
        'category' => 'Serviços Gerais',
        'total' => $total_value_completed
    ];
}

// Fecha a conexão (será reaberta em outras páginas se necessário)

$mysqli->close();





?>



<h2>Bem-vindo(a), <?php echo htmlspecialchars($_SESSION["username"]); ?>!</h2>

<p>Painel de controle de Ordens de Serviços.</p>



<?php if ($total_late_pending_os > 0): ?>

    <div class="alert-message alert-warning">

        Atenção: Existem <?php echo $total_late_pending_os; ?> ordens de serviço pendentes há mais de 5 dias!

        <a href="reports.php?start_date=&end_date=&status=&payment_status=pendente&client_id=" class="btn btn-warning" style="margin-left: 10px;">Ver OS Pendentes</a>

    </div>

<?php endif; ?>

<?php if ($total_overdue_expenses > 0): ?>

    <div class="alert-message alert-danger">

        <strong>⚠️ Despesas em Atraso:</strong> Existem <?php echo $total_overdue_expenses; ?> despesas pendentes com data vencida!

        <?php 
        // Calcula o primeiro e último dia do mês atual para o filtro
        $first_day_month = date('Y-m-01');
        $last_day_month = date('Y-m-t');
        ?>
        <a href="list_expenses.php?start_date=<?php echo $first_day_month; ?>&end_date=<?php echo $last_day_month; ?>&status=Pendente" class="btn btn-danger" style="margin-left: 10px;">Ver Despesas em Atraso</a>

    </div>

<?php endif; ?>



<div class="dashboard-cards">

    <div>

        <h3>Total de Clientes</h3>

        <p style="color: #007bff;"><?php echo $total_clients; ?></p>

        <a href="list_clients.php" class="btn">Ver Clientes</a>

    </div>

    <div>

        <h3>OS Pendentes</h3>

        <p style="color: #ffc107;"><?php echo $total_pending_os; ?></p>

        <a href="list_service_orders.php?status=Pendente" class="btn btn-warning">Ver Pendentes</a>

    </div>

    <div>

        <h3>OS Conclu&iacutedas</h3>

        <p style="color: #28a745;"><?php echo $total_completed_os; ?></p>

        <a href="reports.php?status=Concluída&start_date=<?php echo date('Y-m-01'); ?>&end_date=<?php echo date('Y-m-t'); ?>" class="btn">Ver Concludas</a>

    </div>

    <div>

        <h3>DANFSe</h3>

        <?php 
        // Define a cor baseada na condição: laranja se há OS sem PDF (os_without_pdf > 0)
        $danfse_color = ($os_without_pdf > 0) ? '#ffc107' : '#28a745';
        $danfse_btn = ($os_without_pdf > 0) ? 'btn btn-warning' : 'btn';
        ?>

        <p style="color: <?php echo $danfse_color; ?>;"><?php echo $os_with_pdf; ?> / <?php echo $total_cnpj_os; ?></p>

        <a href="danfse_report.php" class="<?php echo $danfse_btn; ?>">Ver Relatório</a>

    </div>

</div>



<h3>Faturamento vs. Despesas (Mês Atual)</h3>

<div class="chart-container">
    <canvas id="valueChart"></canvas>
</div>

<h3>Faturamento por Serviços (Mês Atual)</h3>
<div class="chart-container">
    <canvas id="serviceGroupsChart"></canvas>
</div>

<script>

    // Dados para o gráfico (obtidos do PHP)

    const totalValueCompleted = <?php echo json_encode($total_value_completed); ?>;

    const totalExpensesMonth = <?php echo json_encode($total_expenses_month); ?>;



    const ctx = document.getElementById('valueChart').getContext('2d');

    const valueChart = new Chart(ctx, {

        type: 'doughnut', // Gráfico de pizza (doughnut)

        data: {

            labels: ['Faturamento', 'Despesas'],

            datasets: [{

                data: [totalValueCompleted, totalExpensesMonth],

                backgroundColor: [

                    '#28a745', // Verde para faturamento

                    '#dc3545'  // Vermelho para despesas

                ],

                hoverOffset: 4

            }]

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

                            const value = tooltipItem.raw;

                            return tooltipItem.label + ': R$ ' + value.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

                        }

                    }

                }

            }

        }

    });

</script>

<script>
    // Dados para o gráfico de grupos de serviços (obtidos do PHP)
    const serviceGroupsData = <?php echo json_encode($service_groups_data); ?>;
    
    // Prepara os dados para o Chart.js
    const serviceGroupsLabels = serviceGroupsData.map(item => {
        // Trunca nomes muito longos para melhor visualização
        return item.category.length > 25 ? item.category.substring(0, 22) + '...' : item.category;
    });
    const serviceGroupsValues = serviceGroupsData.map(item => parseFloat(item.total));
    
    // Cores para diferentes grupos de serviços
    const serviceGroupsColors = [
        '#28a745', // Verde
        '#007bff', // Azul
        '#ffc107', // Amarelo
        '#dc3545', // Vermelho
        '#6f42c1', // Roxo
        '#20c997', // Verde água
        '#fd7e14', // Laranja
        '#6c757d', // Cinza
        '#e83e8c', // Rosa
        '#17a2b8'  // Azul claro
    ];

    const serviceGroupsCtx = document.getElementById('serviceGroupsChart').getContext('2d');
    const serviceGroupsChart = new Chart(serviceGroupsCtx, {
        type: 'bar', // Gráfico de barras
        data: {
            labels: serviceGroupsLabels,
            datasets: [{
                label: 'Faturamento (R$)',
                data: serviceGroupsValues,
                backgroundColor: serviceGroupsColors.slice(0, serviceGroupsLabels.length),
                borderColor: serviceGroupsColors.slice(0, serviceGroupsLabels.length).map(color => color.replace('0.7', '1')),
                borderWidth: 1,
                borderRadius: 4,
                borderSkipped: false,
            }]
        },
        options: {
            indexAxis: 'y', // Barras horizontais
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    display: false // Remove a legenda pois os labels já estão nas barras
                },
                tooltip: {
                    callbacks: {
                        label: function(tooltipItem) {
                            const value = tooltipItem.raw;
                            const total = serviceGroupsValues.reduce((a, b) => a + b, 0);
                            const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                            return tooltipItem.label + ': R$ ' + value.toLocaleString('pt-BR', { 
                                minimumFractionDigits: 2, 
                                maximumFractionDigits: 2 
                            }) + ' (' + percentage + '%)';
                        }
                    }
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Valor (R$)',
                        font: {
                            size: 12,
                            weight: 'bold'
                        }
                    },
                    ticks: {
                        callback: function(value, index, values) {
                            return 'R$ ' + value.toLocaleString('pt-BR', { 
                                minimumFractionDigits: 0, 
                                maximumFractionDigits: 0 
                            });
                        }
                    }
                },
                y: {
                    title: {
                        display: true,
                        text: 'Serviços',
                        font: {
                            size: 12,
                            weight: 'bold'
                        }
                    },
                    ticks: {
                        font: {
                            size: 11
                        }
                    }
                }
            },
            // Configurações para diminuir a altura das barras e espaçamento
            barThickness: 25, // Define a espessura das barras (bem reduzida)
            maxBarThickness: 25, // Define a espessura máxima das barras (bem reduzida)
            categoryPercentage: 0.8, // Aumenta para comprimir melhor as barras
            barPercentage: 0.9 // Aumenta para preencher melhor o espaço da categoria
        }
    });

</script>

<h3>Faturamento Mensal (Últimos 6 Meses)</h3>

<div class="chart-container bar-chart">

    <canvas id="monthlyValueChart"></canvas>

</div>



<script>

    // Dados para o gráfico de área (obtidos do PHP)

    const monthlyLabels = <?php echo json_encode($labels_months); ?>;

    const monthlyData = <?php echo json_encode($monthly_values); ?>;

    const monthlyServicesData = <?php echo json_encode($monthly_services_values); ?>;



    const barCtx = document.getElementById('monthlyValueChart').getContext('2d');

    const monthlyValueChart = new Chart(barCtx, {

        type: 'line', // Gráfico de área

        data: {

            labels: monthlyLabels,

            datasets: [
                {
                    label: 'Faturamento (R$)',

                    data: monthlyData,

                    backgroundColor: 'rgba(0, 123, 255, 0.3)', // Cor azul com transparência para área

                    borderColor: 'rgba(0, 123, 255, 1)',

                    borderWidth: 2,

                    fill: true, // Preenche a área abaixo da linha

                    tension: 0.2 // Suaviza a linha

                },
                {
                    label: 'Serviços (R$)',

                    data: monthlyServicesData,

                    backgroundColor: 'rgba(40, 167, 69, 0.4)', // Cor verde com transparência para área

                    borderColor: 'rgba(40, 167, 69, 1)',

                    borderWidth: 2,

                    fill: true, // Preenche a área abaixo da linha

                    tension: 0.2 // Suaviza a linha

                }
            ]

        },

        options: {

            responsive: true,

            maintainAspectRatio: true,

            plugins: {

                legend: {

                    display: true, // Mostra a legenda para múltiplos datasets

                    position: 'top'

                },

                tooltip: {

                    callbacks: {

                        label: function(tooltipItem) {

                            const value = tooltipItem.raw;

                            return tooltipItem.dataset.label + ': R$ ' + value.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

                        }

                    }

                }

            },

            scales: {

                y: {

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

                x: {

                    title: {

                        display: true,

                        text: 'Mês/Ano'

                    }

                }

            }

        }

    });

</script>



<h3>Top Clientes por Faturamento</h3>

<div class="chart-container pareto-chart">

    <canvas id="paretoChart"></canvas>

</div>



<script>

    // Dados para o gráfico de Pareto (obtidos do PHP)

    const paretoClientLabels = <?php echo json_encode($pareto_client_labels); ?>;

    const paretoClientValues = <?php echo json_encode($pareto_client_values); ?>;

    const paretoCumulativePercentages = <?php echo json_encode($pareto_cumulative_percentages); ?>;



    const paretoCtx = document.getElementById('paretoChart').getContext('2d');

    const paretoChart = new Chart(paretoCtx, {

        type: 'bar', // Tipo principal é barra

        data: {

            labels: paretoClientLabels,

            datasets: [

                {

                    label: 'Valor Total (R$)',

                    data: paretoClientValues,

                    backgroundColor: 'rgba(75, 192, 192, 0.7)', // Cor para as barras

                    borderColor: 'rgba(75, 192, 192, 1)',

                    borderWidth: 1,

                    yAxisID: 'y-axis-value', // Eixo Y para valores

                },

                {

                    label: 'Acumulado (%)',

                    data: paretoCumulativePercentages,

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

                                return tooltipItem.label + ': R$ ' + tooltipItem.raw.toLocaleString('pt-BR', { minimumFractionDigits: 0, maximumFractionDigits: 0 });

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

                            return 'R$ ' + value.toLocaleString('pt-BR', { minimumFractionDigits: 0, maximumFractionDigits: 0 });

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



<h3 style="margin-top: 40px;">Últimas Ordens de Serviços</h3>

<?php if ($result_latest_os && $result_latest_os->num_rows > 0): ?>

    <div class="dashboard-latest-os">

<table>

            <thead>

                <tr>

                    <th>Cliente</th>

                    <th>Descrição</th>

                    <th>Valor</th>

                    <th>Abertura</th>

                    <th>Fechamento</th>

                    <th>Status</th>

                </tr>

            </thead>

            <tbody>

                <?php while ($row = $result_latest_os->fetch_assoc()): ?>
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

                        <td><?php echo htmlspecialchars($row['client_name']); ?></td>

                        <td><?php echo htmlspecialchars(substr($row['description'], 0, 50)) . (strlen($row['description']) > 50 ? '...' : ''); ?></td>

                        <td>R$ <?php echo htmlspecialchars(number_format($row['value'], 2, ',', '.')); ?></td>

                        <td><?php echo htmlspecialchars(date('d/m/Y', strtotime($row['open_date']))); ?></td>

                        <td><?php echo $row['close_date'] ? htmlspecialchars(date('d/m/Y', strtotime($row['close_date']))) : '-'; ?></td>

                        <td><span style="color: <?php echo $payment_color; ?>; font-weight: bold;"><?php echo ucfirst($row['payment_status']); ?></span></td>

                    </tr>

                <?php endwhile; ?>

            </tbody>

        </table>

    </div>

<?php else: ?>

    <p>Nenhuma ordem de serviço encontrada.</p>

<?php endif; ?>



<?php

require_once 'footer.php'; // Inclui o rodap

?>