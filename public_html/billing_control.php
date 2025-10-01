<?php
// billing_control.php - Controle de Faturamento

// Configura o fuso horário para Brasília
date_default_timezone_set('America/Sao_Paulo');

require_once 'header.php';
require_once 'db_connect.php';

$success_message = "";
$error_message = "";
$preselected_order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : null;

// Processa dados do formulário quando enviado
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $service_order_id = trim($_POST["service_order_id"]);
    $payment_status = trim($_POST["payment_status"]);
    $payment_value = filter_input(INPUT_POST, 'payment_value', FILTER_VALIDATE_FLOAT, FILTER_FLAG_ALLOW_FRACTION | FILTER_FLAG_ALLOW_THOUSAND);
    $payment_date = trim($_POST["payment_date"]);
    
    // Processamento do upload de PDF da NFSe
    $nfse_pdf_path = null;
    if (isset($_FILES['nfse_pdf']) && $_FILES['nfse_pdf']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'nfse/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES['nfse_pdf']['name'], PATHINFO_EXTENSION));
        $file_size = $_FILES['nfse_pdf']['size'];
        
        // Validação do arquivo
        if ($file_extension !== 'pdf') {
            $error_message = "Apenas arquivos PDF são aceitos para a NFSe.";
        } elseif ($file_size > 10 * 1024 * 1024) { // 10MB
            $error_message = "O arquivo PDF deve ter no máximo 10MB.";
        } else {
            // Verifica se já existe um arquivo PDF para esta OS e o remove
            $check_existing = "SELECT nfse_pdf_path FROM service_orders WHERE id = ?";
            if ($stmt_check = $mysqli->prepare($check_existing)) {
                $stmt_check->bind_param("i", $service_order_id);
                $stmt_check->execute();
                $result_check = $stmt_check->get_result();
                if ($row_check = $result_check->fetch_assoc()) {
                    $old_file_path = $row_check['nfse_pdf_path'];
                    if (!empty($old_file_path) && file_exists($old_file_path)) {
                        unlink($old_file_path); // Remove o arquivo antigo
                    }
                }
                $stmt_check->close();
            }
            
            // Gera nome único para o arquivo
            $filename = 'nfse_' . $service_order_id . '_' . time() . '.pdf';
            $upload_path = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['nfse_pdf']['tmp_name'], $upload_path)) {
                $nfse_pdf_path = $upload_path;
            } else {
                $error_message = "Erro ao fazer upload do arquivo PDF.";
            }
        }
    }
    
    // Validação básica
    if (empty($service_order_id)) {
        $error_message = "Por favor, selecione uma Ordem de Serviço.";
    } elseif (empty($payment_status)) {
        $error_message = "Por favor, selecione o status do pagamento.";
    } elseif ($payment_value === false || $payment_value < 0) {
        $error_message = "Por favor, insira um valor válido.";
    } elseif (!empty($error_message)) {
        // Se já há erro de upload, não continua
    } else {
        // Atualiza os dados de faturamento
        if ($nfse_pdf_path) {
            $sql = "UPDATE service_orders SET payment_status = ?, payment_value = ?, payment_date = ?, nfse_pdf_path = ? WHERE id = ?";
        } else {
            $sql = "UPDATE service_orders SET payment_status = ?, payment_value = ?, payment_date = ? WHERE id = ?";
        }
        
        if ($stmt = $mysqli->prepare($sql)) {
            // Se a data estiver vazia, define como NULL
            $payment_date_param = !empty($payment_date) ? $payment_date : null;
            
            if ($nfse_pdf_path) {
                $stmt->bind_param("sdssi", $payment_status, $payment_value, $payment_date_param, $nfse_pdf_path, $service_order_id);
            } else {
                $stmt->bind_param("sdsi", $payment_status, $payment_value, $payment_date_param, $service_order_id);
            }
            
            if ($stmt->execute()) {
                $success_message = "Dados de faturamento atualizados com sucesso!";
                
                // Limpa os campos após sucesso
                $service_order_id = $payment_status = $payment_value = $payment_date = "";
            } else {
                $error_message = "Erro ao atualizar dados de faturamento: " . $stmt->error;
            }
            
            $stmt->close();
        } else {
            $error_message = "Erro ao preparar consulta: " . $mysqli->error;
        }
    }
}

// Obtém as ordens de serviço que não estão com status "recebido"
// Verifica se as colunas de faturamento existem
$check_columns = "SHOW COLUMNS FROM service_orders LIKE 'payment_status'";
$column_exists = $mysqli->query($check_columns);

if ($column_exists && $column_exists->num_rows > 0) {
    // Colunas existem, usa query completa
    $orders_query = "SELECT so.id, so.payment_status, so.payment_value, so.payment_date, so.value, c.name AS client_name, c.document_type, so.nfse_pdf_path 
                     FROM service_orders so 
                     JOIN clients c ON so.client_id = c.id 
                     WHERE IFNULL(so.payment_status, 'pendente') != 'recebido' 
                     ORDER BY so.id DESC";
} else {
    // Colunas não existem, usa query básica
    $orders_query = "SELECT so.id, 'pendente' as payment_status, NULL as payment_value, NULL as payment_date, so.value, c.name AS client_name, c.document_type, NULL as nfse_pdf_path 
                     FROM service_orders so 
                     JOIN clients c ON so.client_id = c.id 
                     ORDER BY so.id DESC";
}

$orders_result = $mysqli->query($orders_query);

// Debug: verifica se a query foi executada
if (!$orders_result) {
    echo "<div class='alert-message alert-error'>Erro na consulta: " . $mysqli->error . "</div>";
}
?>

<h2>Controle de Faturamento</h2>


<?php if ($column_exists && $column_exists->num_rows == 0): ?>
    <div class="alert-message alert-error">
        <strong>⚠️ Atenção:</strong> As colunas de faturamento não foram criadas no banco de dados.<br>
        Execute o script SQL: <code>add_billing_columns.sql</code> para habilitar todas as funcionalidades.
    </div>
<?php endif; ?>

<?php if (!empty($success_message)): ?>
    <div class="alert-message alert-success"><?php echo $success_message; ?></div>
<?php endif; ?>

<?php if (!empty($error_message)): ?>
    <div class="alert-message alert-error"><?php echo $error_message; ?></div>
<?php endif; ?>

<div class="billing-container" style="max-width: 800px;">
    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data" id="billing-form">
        
        <!-- Campo 1: Lista de Ordens de Serviço -->
        <div class="form-group">
            <label for="service_order_id">Ordem de Serviço:</label>
            <select name="service_order_id" id="service_order_id" required>
                <option value="">Selecione uma Ordem de Serviço</option>
                <?php
                if ($orders_result && $orders_result->num_rows > 0) {
                    while ($row = $orders_result->fetch_assoc()) {
                        $display_text = "#" . $row['id'] . " - " . htmlspecialchars($row['client_name']) . 
                                       " (R$ " . number_format($row['value'], 2, ',', '.') . ")";
                        
                        $selected = ($preselected_order_id && $preselected_order_id == $row['id']) ? ' selected' : '';
                        
                        echo '<option value="' . $row['id'] . '" data-payment-status="' . $row['payment_status'] . '" data-payment-value="' . $row['payment_value'] . '" data-payment-date="' . $row['payment_date'] . '" data-order-value="' . $row['value'] . '" data-document-type="' . $row['document_type'] . '" data-nfse-pdf-path="' . htmlspecialchars($row['nfse_pdf_path'] ?? '') . '"' . $selected . '>' . $display_text . '</option>';
                    }
                } else {
                    echo '<option value="">Todas as ordens estão com pagamento recebido</option>';
                }
                ?>
            </select>
        </div>

        <!-- Campos de faturamento (aparecem após seleção) -->
        <div id="billing-details" style="display: none;">
            
            <!-- Campo 2: Status do Pagamento -->
            <div class="form-group">
                <label for="payment_status">Status do Pagamento:</label>
                <select name="payment_status" id="payment_status" required>
                    <option value="">Selecione o status</option>
                    <option value="pendente">Pendente</option>
                    <option value="previsao">Previsão</option>
                    <option value="faturado">Faturado</option>
                    <option value="recebido">Recebido</option>
                </select>
            </div>

            <!-- Campo 3: Valor do Pagamento -->
            <div class="form-group">
                <label for="payment_value">Valor (R$):</label>
                <input type="number" name="payment_value" id="payment_value" step="0.01" min="0" required>
                <small style="color: #666; display: block; margin-top: 5px;">
                    <span id="order-value-info"></span>
                </small>
            </div>

            <!-- Campo 4: Data do Pagamento -->
            <div class="form-group">
                <label for="payment_date">Data do Pagamento:</label>
                <input type="date" name="payment_date" id="payment_date">
                <small style="color: #666; display: block; margin-top: 5px;">
                    Deixe vazio se ainda não foi pago
                </small>
            </div>

            <!-- Campo 5: Upload de PDF da NFSe (apenas para CNPJ e status recebido) -->
            <div class="form-group" id="nfse-upload-group" style="display: none;">
                <label for="nfse_pdf">PDF da NFSe (DANFSe):</label>
                <input type="file" name="nfse_pdf" id="nfse_pdf" accept=".pdf" />
                <div id="current-file-info" style="margin-top: 8px; padding: 8px; background: #f8f9fa; border-radius: 4px; display: none;">
                    <span id="current-filename" style="color: #28a745; font-weight: bold;"></span>
                </div>
                <small style="color: #666; display: block; margin-top: 5px;">
                    Apenas arquivos PDF são aceitos. Máximo 10MB.
                </small>
            </div>

            <!-- Botões -->
            <div class="form-actions">
                <input type="submit" class="btn" value="Atualizar Faturamento">
                <button type="button" class="btn btn-secondary" onclick="clearForm()">Limpar</button>
            </div>
        </div>
    </form>
</div>

<!-- Resumo de Ordens de Serviços -->
<div style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
    <h3>Resumo de Ordens de Serviços</h3>
    
    <!-- Filtros -->
    <form method="GET" action="" style="margin-bottom: 20px;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; align-items: end;">
            <?php
            // Define primeiro e último dia do mês atual como padrão
            $first_day_month = isset($_GET['filter_start_date']) ? htmlspecialchars($_GET['filter_start_date']) : date('Y-m-01');
            $last_day_month = isset($_GET['filter_end_date']) ? htmlspecialchars($_GET['filter_end_date']) : date('Y-m-t');
            ?>
            
            <div class="form-group">
                <label for="filter_start_date">Data Início:</label>
                <input type="date" id="filter_start_date" name="filter_start_date" 
                       value="<?php echo $first_day_month; ?>">
            </div>
            
            <div class="form-group">
                <label for="filter_end_date">Data Final:</label>
                <input type="date" id="filter_end_date" name="filter_end_date" 
                       value="<?php echo $last_day_month; ?>">
            </div>
            
            <div class="form-group">
                <label for="filter_payment_status">Status de Pagamento:</label>
                <select id="filter_payment_status" name="filter_payment_status">
                    <option value="">Todos os Status</option>
                    <option value="pendente" <?php echo (isset($_GET['filter_payment_status']) && $_GET['filter_payment_status'] == 'pendente') ? 'selected' : ''; ?>>Pendente</option>
                    <option value="previsao" <?php echo (isset($_GET['filter_payment_status']) && $_GET['filter_payment_status'] == 'previsao') ? 'selected' : ''; ?>>Previsão</option>
                    <option value="faturado" <?php echo (isset($_GET['filter_payment_status']) && $_GET['filter_payment_status'] == 'faturado') ? 'selected' : ''; ?>>Faturado</option>
                    <option value="recebido" <?php echo (isset($_GET['filter_payment_status']) && $_GET['filter_payment_status'] == 'recebido') ? 'selected' : ''; ?>>Recebido</option>
                </select>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn btn-primary">Filtrar</button>
                <a href="billing_control.php" class="btn btn-secondary" style="margin-left: 10px;">Limpar</a>
            </div>
        </div>
    </form>
    
    <?php
    // Reconecta para buscar dados da tabela
    require_once 'db_connect.php';
    
    // Constrói a query com filtros
    $where_conditions = [];
    $params = [];
    $param_types = '';
    
    // Filtro por data de abertura - aplica filtro do mês atual por padrão
    $start_date = !empty($_GET['filter_start_date']) ? $_GET['filter_start_date'] : date('Y-m-01');
    $end_date = !empty($_GET['filter_end_date']) ? $_GET['filter_end_date'] : date('Y-m-t');
    
    // Sempre aplica o filtro de data (mês atual se não especificado)
    $where_conditions[] = "DATE(so.close_date) >= ?";
    $params[] = $start_date;
    $param_types .= 's';
    
    $where_conditions[] = "DATE(so.close_date) <= ?";
    $params[] = $end_date;
    $param_types .= 's';
    
    // Filtro por status de pagamento
    if (!empty($_GET['filter_payment_status'])) {
        $where_conditions[] = "IFNULL(so.payment_status, 'pendente') = ?";
        $params[] = $_GET['filter_payment_status'];
        $param_types .= 's';
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    $summary_query = "SELECT 
                        so.id,
                        c.name AS client_name,
                        c.document_type,
                        so.status,
                        IFNULL(so.payment_status, 'pendente') as payment_status,
                        so.value,
                        DATE(so.close_date) as close_date,
                        so.payment_date,
                        so.nfse_pdf_path
                      FROM service_orders so 
                      JOIN clients c ON so.client_id = c.id 
                      $where_clause
                      ORDER BY so.close_date DESC";
    
    $summary_result = null;
    if (!empty($params)) {
        $stmt = $mysqli->prepare($summary_query);
        $stmt->bind_param($param_types, ...$params);
        $stmt->execute();
        $summary_result = $stmt->get_result();
    } else {
        $summary_result = $mysqli->query($summary_query);
    }
    
    $total_value = 0;
    $total_orders = 0;
    ?>
    
    <!-- Tabela de Resumo -->
    <div style="overflow-x: auto;">
        <table class="table" style="margin-bottom: 0;">
            <thead>
                <tr>
                    <th>OS</th>
                    <th>Cliente</th>
                    <th>Status</th>
                    <th>Status Pagamento</th>
                    <th>Valor</th>
                    <th>Data Fechamento</th>
                    <th>Data Pagamento</th>
                    <th>DANFSe</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ($summary_result && $summary_result->num_rows > 0) {
                    while ($row = $summary_result->fetch_assoc()) {
                        $total_value += $row['value'];
                        $total_orders++;
                        
                        // Cores para status de pagamento
                        $payment_status_colors = [
                            'pendente' => '#dc3545',
                            'previsao' => '#ffc107',
                            'faturado' => '#17a2b8', 
                            'recebido' => '#28a745'
                        ];
                        
                        $payment_color = $payment_status_colors[$row['payment_status']] ?? '#6c757d';
                        
                        // Lógica para coluna DANFSe - apenas para clientes CNPJ
                        $danfse_content = '-';
                        if ($row['document_type'] == 'cnpj' && ($row['payment_status'] == 'recebido' || $row['payment_status'] == 'faturado')) {
                            if (!empty($row['nfse_pdf_path']) && file_exists($row['nfse_pdf_path'])) {
                                $filename = basename($row['nfse_pdf_path']);
                                $danfse_content = '<button type="button" onclick="openPdfLightbox(\'' . htmlspecialchars($row['nfse_pdf_path']) . '\', \'' . htmlspecialchars($filename) . '\')" style="background: none; border: none; color: #007bff; text-decoration: none; cursor: pointer; padding: 0; font-size: inherit;">📄 Visualizar DANFSe</button>';
                            } else {
                                $danfse_content = '<span style="color: #fd7e14; font-weight: bold;">Nota fiscal pendente!</span>';
                            }
                        }
                        
                        echo '<tr>';
                        echo '<td><a href="edit_service_order.php?id=' . $row['id'] . '" style="color: #007bff; text-decoration: none; font-weight: bold;">#' . $row['id'] . '</a></td>';
                        echo '<td>' . htmlspecialchars($row['client_name']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['status']) . '</td>';
                        echo '<td><span style="color: ' . $payment_color . '; font-weight: bold;">' . ucfirst($row['payment_status']) . '</span></td>';
                        echo '<td>R$ ' . number_format($row['value'], 2, ',', '.') . '</td>';
                        echo '<td>' . date('d/m/Y', strtotime($row['close_date'])) . '</td>';
                        echo '<td>' . ($row['payment_date'] ? date('d/m/Y', strtotime($row['payment_date'])) : '-') . '</td>';
                        echo '<td>' . $danfse_content . '</td>';
                        echo '</tr>';
                    }
                } else {
                    echo '<tr><td colspan="8" style="text-align: center; color: #666;">Nenhuma ordem encontrada com os filtros aplicados</td></tr>';
                }
                ?>
            </tbody>
            <tfoot>
                <tr style="background-color: #f8f9fa; font-weight: bold;">
                    <td colspan="4">TOTAL</td>
                    <td>R$ <?php echo number_format($total_value, 2, ',', '.'); ?></td>
                    <td><?php echo $total_orders; ?> ordem(ns)</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
    
    <?php 
    // Conexão será fechada no final do arquivo
    ?>
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

<script>
function loadOrderData() {
    const select = document.getElementById('service_order_id');
    const paymentStatus = document.getElementById('payment_status');
    const paymentValue = document.getElementById('payment_value');
    const paymentDate = document.getElementById('payment_date');
    const orderValueInfo = document.getElementById('order-value-info');
    const billingDetails = document.getElementById('billing-details');
    const nfseUploadGroup = document.getElementById('nfse-upload-group');
    
    if (select && select.value) {
        // Mostra o formulário de faturamento
        if (billingDetails) {
            billingDetails.style.display = 'block';
        }
        const selectedOption = select.options[select.selectedIndex];
        
        // Carrega os dados existentes
        const currentStatus = selectedOption.getAttribute('data-payment-status');
        const currentValue = selectedOption.getAttribute('data-payment-value');
        const currentDate = selectedOption.getAttribute('data-payment-date');
        const orderValue = selectedOption.getAttribute('data-order-value');
        const documentType = selectedOption.getAttribute('data-document-type');
        const existingPdfPath = selectedOption.getAttribute('data-nfse-pdf-path');
        
        // Preenche os campos
        if (paymentStatus) paymentStatus.value = currentStatus || '';
        if (paymentValue) {
            // Se há um valor de pagamento salvo, usa ele; senão usa o valor total da OS
            if (currentValue && currentValue !== 'null' && currentValue !== '') {
                paymentValue.value = currentValue;
            } else {
                paymentValue.value = orderValue || '';
            }
        }
        if (paymentDate) {
            // Se não há data salva, usa a data atual
            if (currentDate) {
                paymentDate.value = currentDate;
            } else {
                // Usa data atual no fuso horário local (Brasil)
                const today = new Date();
                const year = today.getFullYear();
                const month = String(today.getMonth() + 1).padStart(2, '0');
                const day = String(today.getDate()).padStart(2, '0');
                paymentDate.value = `${year}-${month}-${day}`;
            }
        }
        
        // Mostra informação do valor da OS
        if (orderValueInfo && orderValue) {
            orderValueInfo.textContent = `Valor original da OS: R$ ${parseFloat(orderValue).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
        }
        
        // Controla visibilidade do campo de upload de NFSe
        updateNfseUploadVisibility();
        
        // Mostra arquivo existente se houver
        showExistingFile(existingPdfPath);
        
    } else {
        // Esconde o formulário e limpa os campos quando nenhuma OS está selecionada
        if (billingDetails) {
            billingDetails.style.display = 'none';
        }
        if (paymentStatus) paymentStatus.value = '';
        if (paymentValue) paymentValue.value = '';
        if (paymentDate) paymentDate.value = '';
        if (orderValueInfo) orderValueInfo.textContent = '';
        if (nfseUploadGroup) nfseUploadGroup.style.display = 'none';
        hideFileInfo();
    }
}

function updateNfseUploadVisibility() {
    const paymentStatus = document.getElementById('payment_status');
    const nfseUploadGroup = document.getElementById('nfse-upload-group');
    const select = document.getElementById('service_order_id');
    
    if (paymentStatus && nfseUploadGroup && select && select.value) {
        const selectedOption = select.options[select.selectedIndex];
        const documentType = selectedOption.getAttribute('data-document-type');
        const currentStatus = paymentStatus.value;
        
        // Mostra o campo de upload apenas se:
        // 1. O status for "recebido" OU
        // 2. O status for "faturado" E o tipo de documento for "cnpj"
        if ((currentStatus === 'recebido') || (currentStatus === 'faturado' && documentType === 'cnpj')) {
            nfseUploadGroup.style.display = 'block';
        } else {
            nfseUploadGroup.style.display = 'none';
        }
    }
}

function showExistingFile(pdfPath) {
    const fileInfo = document.getElementById('current-file-info');
    const filename = document.getElementById('current-filename');
    const fileInput = document.getElementById('nfse_pdf');
    
    if (pdfPath && pdfPath.trim() !== '') {
        const fileName = pdfPath.split('/').pop(); // Pega apenas o nome do arquivo
        filename.textContent = '📄 ' + fileName;
        fileInfo.style.display = 'block';
        fileInput.value = ''; // Limpa o input de arquivo
    } else {
        hideFileInfo();
    }
}

function hideFileInfo() {
    const fileInfo = document.getElementById('current-file-info');
    if (fileInfo) {
        fileInfo.style.display = 'none';
    }
}

function removeCurrentFile() {
    const fileInput = document.getElementById('nfse_pdf');
    const fileInfo = document.getElementById('current-file-info');
    
    // Limpa o input de arquivo
    if (fileInput) {
        fileInput.value = '';
    }
    
    // Esconde a informação do arquivo
    hideFileInfo();
}

function clearForm() {
    document.getElementById('billing-form').reset();
    document.getElementById('order-value-info').textContent = '';
    const billingDetails = document.getElementById('billing-details');
    const nfseUploadGroup = document.getElementById('nfse-upload-group');
    if (billingDetails) {
        billingDetails.style.display = 'none';
    }
    if (nfseUploadGroup) {
        nfseUploadGroup.style.display = 'none';
    }
    hideFileInfo();
}

// Inicializa todos os event listeners após o DOM carregar
document.addEventListener('DOMContentLoaded', function() {
    const orderSelect = document.getElementById('service_order_id');
    const paymentStatus = document.getElementById('payment_status');
    const paymentDate = document.getElementById('payment_date');
    
    // Event listener para mudança de OS
    if (orderSelect) {
        orderSelect.addEventListener('change', loadOrderData);
    }
    
    // Auto-preenche a data quando status for "recebido" e controla visibilidade do upload
    if (paymentStatus) {
        paymentStatus.addEventListener('change', function() {
            if (this.value === 'recebido' && paymentDate && !paymentDate.value) {
                // Define a data atual no fuso horário local (Brasil)
                const today = new Date();
                const year = today.getFullYear();
                const month = String(today.getMonth() + 1).padStart(2, '0');
                const day = String(today.getDate()).padStart(2, '0');
                paymentDate.value = `${year}-${month}-${day}`;
            }
            
            // Atualiza visibilidade do campo de upload de NFSe
            updateNfseUploadVisibility();
        });
    }
    
    // Carrega automaticamente se há valor pré-selecionado
    if (orderSelect && orderSelect.value) {
        loadOrderData();
    }
    
    // Event listener para mudança de arquivo
    const fileInput = document.getElementById('nfse_pdf');
    if (fileInput) {
        fileInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const fileName = this.files[0].name;
                const filename = document.getElementById('current-filename');
                const fileInfo = document.getElementById('current-file-info');
                
                if (filename && fileInfo) {
                    filename.textContent = '📄 ' + fileName + ' (novo arquivo)';
                    fileInfo.style.display = 'block';
                }
            }
        });
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

<style>
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

<?php 
$mysqli->close();
require_once 'footer.php'; 
?>
