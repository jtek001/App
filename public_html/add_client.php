<?php

// add_client.php

// Formulário para adicionar um novo cliente



require_once 'header.php';

require_once 'db_connect.php';



$name = $address = $phone = $email = $cnpj = $document_type = ""; // Adicionado $cnpj e $document_type

$name_err = "";

$success_message = "";

$error_message = "";



if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Valida o nome do cliente

    if (empty(trim($_POST["name"]))) {

        $name_err = "Por favor, insira o nome do cliente.";

    } else {

        $name = trim($_POST["name"]);

    }



    $address = isset($_POST["address"]) ? trim($_POST["address"]) : "";

    $phone = isset($_POST["phone"]) ? trim($_POST["phone"]) : "";

    $email = isset($_POST["email"]) ? trim($_POST["email"]) : "";

    $cnpj = isset($_POST["cnpj"]) ? trim($_POST["cnpj"]) : ""; // Obtém o valor do campo CNPJ/CPF
    $document_type = isset($_POST["document_type"]) ? trim($_POST["document_type"]) : "cnpj"; // Obtém o tipo de documento
    
    // Validar se document_type é válido
    if (!in_array($document_type, ['cnpj', 'cpf'])) {
        $document_type = 'cnpj';
    }



    // Verifica erros de entrada antes de inserir no banco de dados

    if (empty($name_err)) {

        // Verificação de duplicidade por CNPJ/CPF e Nome
        $has_duplicate = false;

        if (!empty($cnpj)) {
            $sql_dup_cnpj = "SELECT COUNT(1) AS total FROM clients WHERE cnpj = ?";
            if ($stmt_dup_cnpj = $mysqli->prepare($sql_dup_cnpj)) {
                $stmt_dup_cnpj->bind_param("s", $cnpj);
                $stmt_dup_cnpj->execute();
                $res_dup_cnpj = $stmt_dup_cnpj->get_result();
                if ($res_dup_cnpj && ($row = $res_dup_cnpj->fetch_assoc()) && (int)$row['total'] > 0) {
                    $has_duplicate = true;
                    $error_message .= (empty($error_message) ? '' : ' ') . "Já existe um cliente cadastrado com este CNPJ/CPF.";
                }
                $stmt_dup_cnpj->close();
            }
        }

        $sql_dup_name = "SELECT COUNT(1) AS total FROM clients WHERE name = ?";
        if ($stmt_dup_name = $mysqli->prepare($sql_dup_name)) {
            $stmt_dup_name->bind_param("s", $name);
            $stmt_dup_name->execute();
            $res_dup_name = $stmt_dup_name->get_result();
            if ($res_dup_name && ($row = $res_dup_name->fetch_assoc()) && (int)$row['total'] > 0) {
                $has_duplicate = true;
                $error_message .= (empty($error_message) ? '' : ' ') . "Já existe um cliente cadastrado com este Nome.";
            }
            $stmt_dup_name->close();
        }

        if (!$has_duplicate) {
            // Prepara uma declaração de inserção (adicionado 'cnpj' na query)
            $sql = "INSERT INTO clients (name, address, phone, email, cnpj, document_type) VALUES (?, ?, ?, ?, ?, ?)";

            if ($stmt = $mysqli->prepare($sql)) {
                // Adicionado 's' para o tipo de cnpj e document_type
                $stmt->bind_param("ssssss", $param_name, $param_address, $param_phone, $param_email, $param_cnpj, $param_document_type);
                    
                $param_name = $name;
                $param_address = $address;
                $param_phone = $phone;
                $param_email = $email;
                $param_cnpj = $cnpj; // Atribui o CNPJ
                $param_document_type = $document_type; // Atribui o tipo de documento

                if ($stmt->execute()) {
                    // Fecha a conexão antes do redirecionamento
                    $stmt->close();
                    $mysqli->close();
                    // Limpa qualquer output buffer
                    if (ob_get_level()) { ob_end_clean(); }
                    // Redireciona para a lista de clientes após salvar com sucesso
                    header("Location: list_clients.php?status=added");
                    exit();
                } else {
                    $error_message = "Erro ao adicionar cliente. Por favor, tente novamente.";
                }

                $stmt->close();
            } else {
                $error_message = "Erro ao preparar a query: " . $mysqli->error;
            }
        }

    }

    $mysqli->close(); // Fecha a conexão após a operação

}

?>



<h2>Adicionar Novo Cliente</h2>



<?php if (!empty($success_message)): ?>

    <div class="alert-message alert-success"><?php echo $success_message; ?></div>

<?php endif; ?>

<?php if (!empty($error_message)): ?>

    <div class="alert-message alert-error"><?php echo $error_message; ?></div>

<?php endif; ?>



<form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">

    <div class="form-group">

        <label>Nome do Cliente: <span style="color: red;">*</span></label>

        <input type="text" name="name" value="<?php echo htmlspecialchars($name); ?>">

        <span class="help-block" style="color: red;">&nbsp;<?php echo $name_err; ?></span>

    </div>

    <div class="form-group">

        <label>Endereço:</label>

        <input type="text" name="address" value="<?php echo htmlspecialchars($address); ?>">

    </div>

    <div class="form-group">

        <label>Telefone:</label>

        <input type="tel" name="phone" value="<?php echo htmlspecialchars($phone); ?>">

    </div>

    <div class="form-group">

        <label>Email:</label>

        <input type="email" name="email" value="<?php echo htmlspecialchars($email); ?>">

    </div>

    <div class="form-group">

        <label>CNPJ/CPF:</label>

        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <select name="document_type" style="min-width: 100px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                <option value="cnpj" <?php echo ($document_type == 'cnpj') ? 'selected' : ''; ?>>CNPJ</option>
                <option value="cpf" <?php echo ($document_type == 'cpf') ? 'selected' : ''; ?>>CPF</option>
            </select>
            <input type="text" name="cnpj" value="<?php echo htmlspecialchars($cnpj); ?>" placeholder="Digite o CNPJ ou CPF" style="flex: 1; min-width: 200px;">
        </div>

    </div>

    <div class="form-actions">

        <input type="submit" class="btn" value="Adicionar Cliente">

        <a href="list_clients.php" class="btn btn-secondary">Cancelar</a>

    </div>

</form>



<?php

require_once 'footer.php';

?>

