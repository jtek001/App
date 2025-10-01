<?php

// edit_client.php

// Formulário para editar um cliente existente



require_once 'header.php';

require_once 'db_connect.php';



$id = $name = $address = $phone = $email = $cnpj = $document_type = ""; // Adicionado $cnpj e $document_type

$name_err = "";

$success_message = "";

$error_message = "";



// Processa dados do formulário quando ele é enviado ou carrega dados para edição

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $id = $_POST["id"];



    // Valida o nome do cliente

    if (empty(trim($_POST["name"]))) {

        $name_err = "Por favor, insira o nome do cliente.";

    } else {

        $name = trim($_POST["name"]);

    }



    $address = trim($_POST["address"]);

    $phone = trim($_POST["phone"]);

    $email = trim($_POST["email"]);

    $cnpj = trim($_POST["cnpj"]); // Obtém o valor do campo CNPJ/CPF
    $document_type = trim($_POST["document_type"]); // Obtém o tipo de documento



    // Verifica erros de entrada antes de atualizar no banco de dados

    if (empty($name_err)) {

        // Adicionado 'cnpj' e 'document_type' na query SQL

        $sql = "UPDATE clients SET name = ?, address = ?, phone = ?, email = ?, cnpj = ?, document_type = ? WHERE id = ?";



        if ($stmt = $mysqli->prepare($sql)) {

            // Adicionado 's' para o tipo de cnpj e document_type

            $stmt->bind_param("ssssssi", $param_name, $param_address, $param_phone, $param_email, $param_cnpj, $param_document_type, $param_id);



            $param_name = $name;

            $param_address = $address;

            $param_phone = $phone;

            $param_email = $email;

            $param_cnpj = $cnpj; // Atribui o CNPJ
            $param_document_type = $document_type; // Atribui o tipo de documento

            $param_id = $id;



            if ($stmt->execute()) {

                $success_message = "Cliente atualizado com sucesso!";

            } else {

                $error_message = "Erro ao atualizar cliente. Por favor, tente novamente.";

            }



            $stmt->close();

        }

    }

    $mysqli->close(); // Fecha a conexão após a operação

} else {

    // Se a requisição for GET (para carregar o formulário de edição)

    if (isset($_GET["id"]) && !empty(trim($_GET["id"]))) {

        $id = trim($_GET["id"]);



        // Adicionado 'cnpj' e 'document_type' na query SELECT

        $sql = "SELECT name, address, phone, email, cnpj, document_type FROM clients WHERE id = ?";

        if ($stmt = $mysqli->prepare($sql)) {

            $stmt->bind_param("i", $param_id);

            $param_id = $id;



            if ($stmt->execute()) {

                $result = $stmt->get_result();

                if ($result->num_rows == 1) {

                    $row = $result->fetch_assoc();

                    $name = $row["name"];

                    $address = $row["address"];

                    $phone = $row["phone"];

                    $email = $row["email"];

                    $cnpj = $row["cnpj"]; // Obtém o valor do CNPJ/CPF
                    $document_type = $row["document_type"]; // Obtém o tipo de documento

                } else {

                    $error_message = "Cliente não encontrado.";

                }

            } else {

                $error_message = "Ops! Algo deu errado. Por favor, tente novamente mais tarde.";

            }

            $stmt->close();

        }

    } else {

        // ID não fornecido, redireciona para a lista de clientes

        header("location: list_clients.php");

        exit();

    }

    $mysqli->close(); // Fecha a conexão após a consulta inicial

}

?>



<h2>Editar Cliente</h2>



<?php if (!empty($success_message)): ?>

    <div class="alert-message alert-success"><?php echo $success_message; ?></div>

<?php endif; ?>

<?php if (!empty($error_message)): ?>

    <div class="alert-message alert-error"><?php echo $error_message; ?></div>

<?php endif; ?>



<form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">

    <input type="hidden" name="id" value="<?php echo htmlspecialchars($id); ?>">

    <div class="form-group">

        <label>Nome do Cliente: <span style="color: red;">*</span></label>

        <input type="text" name="name" value="<?php echo htmlspecialchars($name); ?>">

        <span class="help-block" style="color: red;"><?php echo $name_err; ?></span>

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

        <input type="submit" class="btn" value="Salvar Alterações">

        <a href="list_clients.php" class="btn btn-secondary">Cancelar</a>

    </div>

</form>



<?php

require_once 'footer.php';

?>

