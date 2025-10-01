/**
 * Modal reutilizável para exclusão de registros
 * Verifica se há registros relacionados antes de permitir exclusão
 */

class DeleteModal {
    constructor() {
        this.modal = null;
        this.init();
    }

    init() {
        // Cria o modal se não existir
        if (!document.getElementById('deleteModal')) {
            this.createModal();
        }
        this.modal = document.getElementById('deleteModal');
    }

    createModal() {
        const modalHTML = `
            <div id="deleteModal" class="modal-overlay" style="display: none;">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>Confirmar Exclusão</h3>
                        <span class="modal-close" onclick="deleteModal.close()">&times;</span>
                    </div>
                    <div class="modal-body">
                        <div id="modalMessage"></div>
                        <div id="modalWarning" class="modal-warning" style="display: none;">
                            <strong>⚠️ ATENÇÃO:</strong> Este registro possui ordens de serviço relacionadas e não pode ser excluído.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="deleteModal.close()">Cancelar</button>
                        <button type="button" id="confirmDeleteBtn" class="btn btn-danger" onclick="deleteModal.confirmDelete()">Excluir</button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', modalHTML);
    }

    show(itemId, itemName, itemType = 'registro', checkRelated = true, status = null, paymentStatus = null) {
        this.currentItemId = itemId;
        this.currentItemName = itemName;
        this.currentItemType = itemType;
        this.checkRelated = checkRelated;
        this.status = status;
        this.paymentStatus = paymentStatus;

        // Verifica se pode excluir baseado no status e payment_status (para ordens de serviço)
        if (itemType === 'ordem_servico' || itemType === 'service_order') {
            if (status === 'Concluída') {
                this.showCannotDeleteWarning('Esta Ordem esta marcada como Concluída e não pode ser excluída!');
                return;
            }
            
            if (paymentStatus === 'recebido' || paymentStatus === 'faturado') {
                this.showCannotDeleteWarning('Esta Ordem esta marcada como Concluída e não pode ser excluída!');
                return;
            }
        }

        // Verifica se pode excluir baseado no status (para despesas)
        if (itemType === 'despesa' || itemType === 'expense') {
            if (paymentStatus === 'Pago') {
                this.showCannotDeleteWarning('Esta despesa já está marcada como Pago e não pode ser excluída!');
                return;
            }
        }

        // Mostra mensagem inicial
        document.getElementById('modalMessage').innerHTML = `
            <p>Tem certeza que deseja excluir este ${itemType}?</p>
            <p><strong>${itemName}</strong></p>
        `;

        // Esconde aviso de OS relacionadas
        document.getElementById('modalWarning').style.display = 'none';
        
        // Desabilita botão de confirmação temporariamente
        const confirmBtn = document.getElementById('confirmDeleteBtn');
        confirmBtn.disabled = true;
        confirmBtn.textContent = 'Verificando...';

        this.modal.style.display = 'flex';

        // Se deve verificar registros relacionados
        if (checkRelated) {
            this.checkRelatedRecords(itemId);
        } else {
            this.enableDelete();
        }
    }

    async checkRelatedRecords(itemId) {
        try {
            const response = await fetch('check_related_records.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    item_id: itemId,
                    item_type: this.currentItemType
                })
            });

            if (!response.ok) {
                this.enableDelete();
                return;
            }

            const data = await response.json();
            
            // Normaliza resposta
            const hasRelated = !!(data && (data.hasRelated === true || data.hasRelated === 'true' || data.count > 0));
            const count = typeof data?.count === 'number' ? data.count : 0;
            const message = data?.message && String(data.message).trim().length > 0
                ? data.message
                : hasRelated
                    ? `Este ${this.currentItemType === 'produto' || this.currentItemType === 'product' ? 'produto' : 'registro'} possui ${count} ordem(ns) de serviço relacionada(s) e não pode ser excluído.`
                    : '';

            if (hasRelated) {
                this.showRelatedWarning(message);
            } else {
                this.enableDelete();
            }
        } catch (error) {
            this.enableDelete(); // Em caso de erro, permite exclusão
        }
    }

    showRelatedWarning(message) {
        document.getElementById('modalWarning').style.display = 'block';
        document.getElementById('modalWarning').innerHTML = `<strong>ATENÇÃO:</strong> ${message}`;
        
        const confirmBtn = document.getElementById('confirmDeleteBtn');
        confirmBtn.disabled = true;
        confirmBtn.textContent = 'Excluir';
        confirmBtn.classList.add('btn-disabled');
    }

    showCannotDeleteWarning(message) {
        // Mostra mensagem de que não pode excluir
        document.getElementById('modalMessage').innerHTML = `
            <p><strong>${this.currentItemName}</strong></p>
        `;
        
        document.getElementById('modalWarning').style.display = 'block';
        document.getElementById('modalWarning').innerHTML = `<strong>⚠️ ATENÇÃO:</strong> ${message}`;
        
        const confirmBtn = document.getElementById('confirmDeleteBtn');
        confirmBtn.disabled = true;
        confirmBtn.textContent = 'Excluir';
        confirmBtn.classList.add('btn-disabled');
        
        this.modal.style.display = 'flex';
    }

    enableDelete() {
        const confirmBtn = document.getElementById('confirmDeleteBtn');
        confirmBtn.disabled = false;
        confirmBtn.textContent = 'Excluir';
        confirmBtn.classList.remove('btn-disabled');
    }

    confirmDelete() {
        if (this.currentItemId && !document.getElementById('confirmDeleteBtn').disabled) {
            // Mapeia os tipos para os nomes corretos dos arquivos
            const fileMap = {
                'cliente': 'delete_client.php',
                'client': 'delete_client.php',
                'produto': 'delete_product.php',
                'product': 'delete_product.php',
                'ordem_servico': 'delete_service_order.php',
                'service_order': 'delete_service_order.php',
                'despesa': 'delete_expense.php',
                'expense': 'delete_expense.php'
            };
            
            const fileName = fileMap[this.currentItemType] || `delete_${this.currentItemType}.php`;
            window.location.href = `${fileName}?id=${this.currentItemId}`;
        }
    }

    close() {
        this.modal.style.display = 'none';
        this.currentItemId = null;
        this.currentItemName = null;
        this.currentItemType = null;
    }
}

// Instância global do modal
const deleteModal = new DeleteModal();

// Fecha modal ao clicar fora dele
window.onclick = function(event) {
    if (event.target === deleteModal.modal) {
        deleteModal.close();
    }
}

// Fecha modal com tecla ESC
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape' && deleteModal.modal.style.display === 'flex') {
        deleteModal.close();
    }
});
