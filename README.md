# Sistema de Gestão de Ordens de Serviços - JtekInfo

## 📋 Visão Geral

O **Sistema de Gestão de Ordens de Serviços JtekInfo** é uma aplicação web desenvolvida em PHP para controle completo de ordens de serviço, clientes, produtos, faturamento e despesas. O sistema oferece uma interface intuitiva e funcionalidades robustas para empresas de prestação de serviços de TI.

## 🚀 Funcionalidades Principais

### 1. **Gestão de Ordens de Serviço (OS)**
- ✅ Criação, edição e exclusão de ordens de serviço
- ✅ Controle de status (Pendente, Em Andamento, Concluída, Cancelada)
- ✅ Gestão de itens/produtos por OS
- ✅ Upload de fotos dos serviços realizados (até 6 fotos por OS)
- ✅ Impressão de ordens de serviço
- ✅ Controle de datas de abertura e fechamento
- ✅ Bloqueio de edição para OS com pagamento recebido

### 2. **Gestão de Clientes**
- ✅ Cadastro completo de clientes (CNPJ/CPF)
- ✅ Controle de endereço, telefone e email
- ✅ Filtros e busca por cliente
- ✅ Validação de registros relacionados antes da exclusão

### 3. **Gestão de Produtos/Serviços**
- ✅ Catálogo de produtos e serviços
- ✅ Controle de estoque
- ✅ Categorização (Produtos/Serviços)
- ✅ Preços e quantidades
- ✅ Status ativo/inativo

### 4. **Controle de Faturamento**
- ✅ Gestão de status de pagamento (Pendente, Previsão, Faturado, Recebido)
- ✅ Upload de PDFs de NFSe
- ✅ Controle de valores de pagamento
- ✅ Relatórios de faturamento por período
- ✅ Análise de DANFSe (Documento Auxiliar da Nota Fiscal de Serviços Eletrônica)

### 5. **Gestão de Despesas**
- ✅ Cadastro de despesas
- ✅ Controle de status de pagamento
- ✅ Categorização de despesas
- ✅ Relatórios por período
- ✅ Alertas de despesas em atraso

### 6. **Dashboard e Relatórios**
- ✅ Dashboard com métricas em tempo real
- ✅ Gráficos de faturamento mensal
- ✅ Análise de Pareto (top clientes)
- ✅ Relatórios detalhados com filtros
- ✅ Exportação de dados
- ✅ Alertas de OS pendentes há mais de 5 dias

## 🏗️ Arquitetura do Sistema

### **Tecnologias Utilizadas**
- **Backend**: PHP 8.0+
- **Banco de Dados**: MySQL/MariaDB
- **Frontend**: HTML5, CSS3, JavaScript (Vanilla)
- **Bibliotecas**: Chart.js para gráficos
- **Servidor Web**: Apache/Nginx

### **Estrutura de Arquivos**
```
public_html/
├── 📁 Sistema Principal
│   ├── index.php                 # Página de login
│   ├── dashboard.php             # Dashboard principal
│   ├── db_connect.php           # Conexão com banco de dados
│   ├── header.php               # Cabeçalho comum
│   ├── footer.php               # Rodapé comum
│   └── logout.php               # Logout do sistema
│
├── 📁 Gestão de Ordens de Serviço
│   ├── add_service_order.php    # Adicionar nova OS
│   ├── edit_service_order.php   # Editar OS existente
│   ├── list_service_orders.php  # Listar todas as OS
│   ├── delete_service_order.php # Excluir OS
│   └── print_service_order.php  # Impressão de OS
│
├── 📁 Gestão de Clientes
│   ├── add_client.php           # Adicionar cliente
│   ├── edit_client.php          # Editar cliente
│   ├── list_clients.php         # Listar clientes
│   └── delete_client.php        # Excluir cliente
│
├── 📁 Gestão de Produtos
│   ├── add_product.php          # Adicionar produto
│   ├── edit_product.php         # Editar produto
│   ├── list_products.php        # Listar produtos
│   └── delete_product.php       # Excluir produto
│
├── 📁 Controle Financeiro
│   ├── billing_control.php      # Controle de faturamento
│   ├── add_expense.php          # Adicionar despesa
│   ├── edit_expense.php         # Editar despesa
│   ├── list_expenses.php        # Listar despesas
│   └── delete_expense.php       # Excluir despesa
│
├── 📁 Relatórios
│   ├── reports.php              # Relatórios gerais
│   └── danfse_report.php        # Relatório de DANFSe
│
├── 📁 Recursos Auxiliares
│   ├── check_related_records.php # Verificação de registros relacionados
│   ├── remove_service_photo.php  # Remoção de fotos de serviços
│   └── js/
│       └── delete_modal.js      # Modal de confirmação de exclusão
│
├── 📁 Arquivos Estáticos
│   ├── style.css                # Estilos CSS
│   ├── logo.png                 # Logo da empresa
│   ├── logojtek.png            # Ícone do sistema
│   └── service_photos/          # Fotos dos serviços
│
└── 📁 NFSe
    └── nfse/                    # PDFs de NFSe
```

## 🗄️ Estrutura do Banco de Dados

### **Tabelas Principais**

#### **1. `clients` - Clientes**
```sql
- id (int, PK)
- name (varchar) - Nome do cliente
- address (varchar) - Endereço
- phone (varchar) - Telefone
- email (varchar) - Email
- cnpj (varchar) - CNPJ/CPF
- document_type (enum) - Tipo de documento (cnpj/cpf)
```

#### **2. `service_orders` - Ordens de Serviço**
```sql
- id (int, PK)
- client_id (int, FK) - Referência ao cliente
- description (text) - Descrição do serviço
- status (varchar) - Status da OS
- value (decimal) - Valor da OS
- solution (text) - Solução aplicada
- open_date (datetime) - Data de abertura
- close_date (datetime) - Data de fechamento
- payment_status (varchar) - Status do pagamento
- payment_value (decimal) - Valor do pagamento
- payment_date (date) - Data do pagamento
- nfse_pdf_path (varchar) - Caminho do PDF da NFSe
```

#### **3. `products` - Produtos/Serviços**
```sql
- id (int, PK)
- name (varchar) - Nome do produto/serviço
- price (decimal) - Preço
- category (varchar) - Categoria
- stock_quantity (int) - Quantidade em estoque
- status (varchar) - Status (Ativo/Inativo)
```

#### **4. `service_order_items` - Itens da OS**
```sql
- id (int, PK)
- service_order_id (int, FK) - Referência à OS
- product_id (int, FK) - Referência ao produto
- quantity (int) - Quantidade
- unit_price (decimal) - Preço unitário
- total_price (decimal) - Preço total
```

#### **5. `service_order_photos` - Fotos dos Serviços**
```sql
- id (int, PK)
- service_order_id (int, FK) - Referência à OS
- photo_path (varchar) - Caminho da foto
- photo_name (varchar) - Nome original da foto
- uploaded_at (datetime) - Data do upload
```

#### **6. `expenses` - Despesas**
```sql
- id (int, PK)
- description (varchar) - Descrição da despesa
- amount (decimal) - Valor
- category (varchar) - Categoria
- expense_date (date) - Data da despesa
- status (varchar) - Status (Pendente/Pago)
```

#### **7. `users` - Usuários**
```sql
- id (int, PK)
- username (varchar) - Nome de usuário
- password (varchar) - Senha (hash)
```

## 🔧 Instalação e Configuração

### **Pré-requisitos**
- PHP 8.0 ou superior
- MySQL 5.7+ ou MariaDB 10.3+
- Servidor web (Apache/Nginx)
- Extensões PHP: mysqli, gd, fileinfo

### **Passos de Instalação**

1. **Clone/Download do Projeto**
   ```bash
   # Faça download dos arquivos para o diretório do servidor web
   ```

2. **Configuração do Banco de Dados**
   ```sql
   -- Importe o arquivo SQL fornecido
   mysql -u root -p < app_backup.sql
   ```

3. **Configuração da Conexão**
   ```php
   // Edite o arquivo db_connect.php
   define('DB_SERVER', 'localhost');
   define('DB_USERNAME', 'seu_usuario');
   define('DB_PASSWORD', 'sua_senha');
   define('DB_NAME', 'app_app');
   ```

4. **Permissões de Diretório**
   ```bash
   chmod 755 service_photos/
   chmod 755 nfse/
   ```

5. **Configuração do Servidor Web**
   - Configure o DocumentRoot para apontar para a pasta `public_html`
   - Ative mod_rewrite se necessário

## 🎯 Fluxo de Trabalho

### **1. Cadastro Inicial**
1. Cadastrar clientes
2. Cadastrar produtos/serviços
3. Configurar usuários do sistema

### **2. Gestão de Ordens de Serviço**
1. **Criar OS**: Selecionar cliente, descrever problema, adicionar itens
2. **Executar Serviço**: Atualizar status, adicionar fotos, registrar solução
3. **Finalizar OS**: Marcar como concluída, definir data de fechamento
4. **Faturar**: Gerar NFSe, controlar pagamento

### **3. Controle Financeiro**
1. **Faturamento**: Acompanhar status de pagamento das OS
2. **Despesas**: Registrar gastos operacionais
3. **Relatórios**: Analisar performance financeira

## 📊 Dashboard e Métricas

### **Indicadores Principais**
- Total de clientes cadastrados
- OS pendentes (com alerta para > 5 dias)
- OS concluídas no mês
- Faturamento vs despesas
- Status de DANFSe (PDFs gerados)
- Top clientes por faturamento
- Análise de Pareto

### **Gráficos Disponíveis**
- Faturamento mensal (últimos 6 meses)
- Faturamento por serviços
- Análise de Pareto de clientes
- Comparativo faturamento vs despesas

## 🔒 Segurança

### **Medidas Implementadas**
- ✅ Autenticação obrigatória
- ✅ Validação de entrada de dados
- ✅ Prepared statements (SQL injection)
- ✅ Sanitização de outputs
- ✅ Controle de sessão
- ✅ Validação de tipos de arquivo
- ✅ Limitação de tamanho de uploads

### **Controles de Acesso**
- Login obrigatório para todas as páginas
- Redirecionamento automático para login
- Logout seguro com destruição de sessão

## 🚨 Alertas e Notificações

### **Alertas Automáticos**
- OS pendentes há mais de 5 dias
- Despesas em atraso
- OS sem PDF de NFSe (para clientes CNPJ)

### **Validações de Negócio**
- Não permite excluir OS concluídas
- Não permite excluir OS com pagamento recebido
- Não permite editar OS com pagamento recebido
- Verifica registros relacionados antes de exclusão

## 📱 Responsividade

O sistema é totalmente responsivo e funciona em:
- 💻 Desktops
- 📱 Tablets
- 📱 Smartphones

### **Recursos Mobile**
- Menu hambúrguer para dispositivos móveis
- Formulários otimizados para touch
- Galeria de fotos adaptável
- Gráficos responsivos

## 🔧 Manutenção e Suporte

### **Backup Recomendado**
- Backup diário do banco de dados
- Backup das pastas `service_photos/` e `nfse/`
- Backup dos arquivos de configuração

### **Logs e Monitoramento**
- Logs de erro do PHP
- Monitoramento de espaço em disco
- Verificação de integridade do banco

### **Atualizações**
- Manter PHP e MySQL atualizados
- Backup antes de qualquer alteração
- Testar em ambiente de desenvolvimento

## 📈 Melhorias Futuras Sugeridas

### **Funcionalidades Adicionais**
- [ ] Sistema de notificações por email
- [ ] API REST para integrações
- [ ] App mobile nativo
- [ ] Relatórios em PDF
- [ ] Integração com gateways de pagamento
- [ ] Sistema de backup automático
- [ ] Dashboard personalizável
- [ ] Histórico de alterações (auditoria)

### **Melhorias Técnicas**
- [ ] Migração para framework (Laravel/CodeIgniter)
- [ ] Implementação de cache
- [ ] Otimização de consultas SQL
- [ ] Testes automatizados
- [ ] CI/CD pipeline

## 📞 Suporte

Para suporte técnico ou dúvidas sobre o sistema:
- **Desenvolvedor**: JtekInfo
- **Versão**: 1.0
- **Última Atualização**: Setembro 2025

---

## 📄 Licença

Este sistema foi desenvolvido para uso interno da JtekInfo. Todos os direitos reservados.

---

*Sistema desenvolvido com foco na praticidade e eficiência para gestão de ordens de serviço em empresas de TI.*
