# Modal de Exclusão Reutilizável

Este modal foi criado para ser reutilizado em todas as páginas que precisam de confirmação de exclusão de registros, com validação automática de registros relacionados.

## Como Usar

### 1. Incluir o Script JavaScript

Adicione o script no final de cada página que usar o modal:

```html
<script src="js/delete_modal.js"></script>
```

### 2. Substituir o Botão de Exclusão

Substitua o link de exclusão por um botão que chama o modal:

**Antes:**
```html
<a href="delete_item.php?id=<?php echo $row['id']; ?>" class="btn btn-danger" 
   onclick="return confirm('Tem certeza?');">Excluir</a>
```

**Depois:**
```html
<button type="button" class="btn btn-danger" 
        onclick="deleteModal.show(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['name'], ENT_QUOTES); ?>', 'tipo_item', true);">
    Excluir
</button>
```

### 3. Parâmetros do Modal

```javascript
deleteModal.show(itemId, itemName, itemType, checkRelated)
```

- `itemId`: ID do registro a ser excluído
- `itemName`: Nome/descrição do registro (para exibição)
- `itemType`: Tipo do item ('cliente', 'produto', 'ordem_servico', etc.)
- `checkRelated`: true/false - se deve verificar registros relacionados

### 4. Tipos de Item Suportados

O modal verifica automaticamente registros relacionados para:

- **cliente**: Verifica se há ordens de serviço relacionadas
- **produto**: Verifica se está sendo usado em ordens de serviço
- **ordem_servico**: Pode ser excluída normalmente

### 5. Personalização

Para adicionar novos tipos de validação, edite o arquivo `check_related_records.php`:

```php
case 'novo_tipo':
    // Sua lógica de verificação aqui
    $sql = "SELECT COUNT(*) as count FROM tabela_relacionada WHERE campo_id = ?";
    // ... resto da lógica
    break;
```

## Funcionalidades

- ✅ Modal responsivo e moderno
- ✅ Verificação automática de registros relacionados
- ✅ Desabilita exclusão quando há registros relacionados
- ✅ Mensagens de aviso claras
- ✅ Fechamento com ESC ou clique fora do modal
- ✅ Reutilizável em qualquer página
- ✅ Mantém formatação dos botões existentes

## Exemplo Completo

```php
// Na sua página de listagem
<?php while ($row = $result->fetch_assoc()): ?>
    <tr>
        <td><?php echo htmlspecialchars($row['name']); ?></td>
        <td>
            <a href="edit_item.php?id=<?php echo $row['id']; ?>" class="btn btn-warning">Editar</a>
            <button type="button" class="btn btn-danger" 
                    onclick="deleteModal.show(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['name'], ENT_QUOTES); ?>', 'meu_tipo', true);">
                Excluir
            </button>
        </td>
    </tr>
<?php endwhile; ?>

<script src="js/delete_modal.js"></script>
```

## Arquivos Modificados

- `js/delete_modal.js` - Script principal do modal
- `check_related_records.php` - Endpoint para verificar registros relacionados
- `style.css` - Estilos do modal
- `list_clients.php` - Exemplo de implementação
- `list_products.php` - Exemplo de implementação
