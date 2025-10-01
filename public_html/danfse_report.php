<?php
// danfse_report.php - Relatório de DANFSe

// Configura o fuso horário para Brasília
date_default_timezone_set('America/Sao_Paulo');

require_once 'header.php';
require_once 'db_connect.php';

// Configurações de paginação
$records_per_page = 15;
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $records_per_page;

// Query para contar o total de registros
$count_query = "SELECT COUNT(*) as total_records 
                FROM service_orders so 
                JOIN clients c ON so.client_id = c.id 
                WHERE c.document_type = 'cnpj'
                AND IFNULL(so.payment_status, 'pendente') IN ('recebido', 'faturado')";

$count_result = $mysqli->query($count_query);
$total_records = $count_result->fetch_assoc()['total_records'];
$total_pages = ceil($total_records / $records_per_page);

// Obtém as ordens de serviço para o relatório DANFSe (apenas recebido ou faturado) com paginação
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
                  WHERE c.document_type = 'cnpj'
                  AND IFNULL(so.payment_status, 'pendente') IN ('recebido', 'faturado')
                  ORDER BY so.payment_date DESC
                  LIMIT ? OFFSET ?";

$summary_stmt = $mysqli->prepare($summary_query);
$summary_stmt->bind_param("ii", $records_per_page, $offset);
$summary_stmt->execute();
$summary_result = $summary_stmt->get_result();

// Query para calcular estatísticas totais (sem paginação)
$stats_query = "SELECT 
                    COUNT(*) as total_orders,
                    SUM(so.value) as total_value,
                    SUM(CASE WHEN so.nfse_pdf_path IS NOT NULL AND so.nfse_pdf_path != '' THEN 1 ELSE 0 END) as danfse_existing,
                    COUNT(*) as danfse_expected
                  FROM service_orders so 
                  JOIN clients c ON so.client_id = c.id 
                  WHERE c.document_type = 'cnpj'
                  AND IFNULL(so.payment_status, 'pendente') IN ('recebido', 'faturado')";

$stats_result = $mysqli->query($stats_query);
$stats = $stats_result->fetch_assoc();

$total_value = $stats['total_value'] ?? 0;
$total_orders = $stats['total_orders'] ?? 0;
$danfse_existing = $stats['danfse_existing'] ?? 0;
$danfse_expected = $stats['danfse_expected'] ?? 0;
?>

<h2>Relatório de Notas Fiscais (DANFSe)</h2>

<div style="margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
    <h3>Resumo Geral</h3>
    <p><strong>Total de Ordens CNPJ (Recebido/Faturado):</strong> <?php echo $total_orders; ?> ordens de serviços</p>
    <p><strong>DANFSe existentes:</strong> <?php echo $danfse_existing; ?> / <?php echo $danfse_expected; ?> esperadas</p>
    <p><strong>Valor total:</strong> R$ <?php echo number_format($total_value, 2, ',', '.'); ?></p>
    
    <?php if ($total_pages > 1): ?>
    <div style="margin-top: 10px; padding: 10px; background: #e9ecef; border-radius: 4px;">
        <strong>Página atual:</strong> <?php echo $current_page; ?> de <?php echo $total_pages; ?> 
        (<?php echo $records_per_page; ?> registros por página)
    </div>
    <?php endif; ?>
</div>

<!-- Tabela de Ordens de Serviços -->
<div style="overflow-x: auto;" class="list_danfse">
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
                    // Cores para status de pagamento
                    $payment_status_colors = [
                        'pendente' => '#dc3545',
                        'previsao' => '#ffc107',
                        'faturado' => '#17a2b8', 
                        'recebido' => '#28a745'
                    ];
                    
                    $payment_color = $payment_status_colors[$row['payment_status']] ?? '#6c757d';
                    
                    // Lógica para coluna DANFSe
                    $danfse_content = '-';
                    if (in_array($row['payment_status'], ['recebido', 'faturado'])) {
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
                echo '<tr><td colspan="8" style="text-align: center; color: #666;">Nenhuma ordem encontrada</td></tr>';
            }
            ?>
        </tbody>
    </table>
</div>

<!-- Paginação -->
<?php if ($total_pages > 1): ?>
<div style="margin-top: 20px; text-align: center;">
    <div style="display: inline-block; background: #f8f9fa; padding: 10px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <span style="margin-right: 15px; color: #666;">
            Página <?php echo $current_page; ?> de <?php echo $total_pages; ?> 
            (<?php echo $total_records; ?> registros no total)
        </span>
        
        <!-- Botão Primeira Página -->
        <?php if ($current_page > 1): ?>
            <a href="?page=1" style="display: inline-block; padding: 8px 12px; margin: 0 2px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; font-size: 14px;">««</a>
        <?php endif; ?>
        
        <!-- Botão Página Anterior -->
        <?php if ($current_page > 1): ?>
            <a href="?page=<?php echo $current_page - 1; ?>" style="display: inline-block; padding: 8px 12px; margin: 0 2px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; font-size: 14px;">«</a>
        <?php endif; ?>
        
        <!-- Números das páginas -->
        <?php
        $start_page = max(1, $current_page - 2);
        $end_page = min($total_pages, $current_page + 2);
        
        for ($i = $start_page; $i <= $end_page; $i++):
        ?>
            <?php if ($i == $current_page): ?>
                <span style="display: inline-block; padding: 8px 12px; margin: 0 2px; background: #28a745; color: white; border-radius: 4px; font-size: 14px; font-weight: bold;"><?php echo $i; ?></span>
            <?php else: ?>
                <a href="?page=<?php echo $i; ?>" style="display: inline-block; padding: 8px 12px; margin: 0 2px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px; font-size: 14px;"><?php echo $i; ?></a>
            <?php endif; ?>
        <?php endfor; ?>
        
        <!-- Botão Próxima Página -->
        <?php if ($current_page < $total_pages): ?>
            <a href="?page=<?php echo $current_page + 1; ?>" style="display: inline-block; padding: 8px 12px; margin: 0 2px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; font-size: 14px;">»</a>
        <?php endif; ?>
        
        <!-- Botão Última Página -->
        <?php if ($current_page < $total_pages): ?>
            <a href="?page=<?php echo $total_pages; ?>" style="display: inline-block; padding: 8px 12px; margin: 0 2px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; font-size: 14px;">»»</a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

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
$summary_stmt->close();
$mysqli->close();
require_once 'footer.php'; 
?>
