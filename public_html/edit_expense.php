<?php

// edit_expense.php

// Formulário para editar uma despesa existente



require_once 'header.php';

require_once 'db_connect.php';



$id = $description = $amount = $category = $notes = $expense_date = $status = ""; // Adicionado $expense_date e $status

$description_err = $amount_err = "";

$success_message = "";

$error_message = "";



// Processa dados do formulário quando ele é enviado ou carrega dados para edição

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $id = $_POST["id"];



    // Valida a descrição

    if (empty(trim($_POST["description"]))) {

        $description_err = "Por favor, insira a descrição da despesa.";

    } else {

        $description = trim($_POST["description"]);

    }



    // Valida e sanitiza o valor

    $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT, FILTER_FLAG_ALLOW_FRACTION | FILTER_FLAG_ALLOW_THOUSAND);

    if ($amount === false || $amount <= 0) {

        $amount_err = "Por favor, insira um valor válido para a despesa.";

        $amount = 0.00; // Define um valor padrão seguro

    }



    $category = trim($_POST["category"]);

    $notes = trim($_POST["notes"]);

    $expense_date = trim($_POST["expense_date"]); // Obtém a data da despesa

    $status = trim($_POST["status"]); // Obtém o status da despesa



    // Se a data da despesa estiver vazia, usa a data e hora atual

    if (empty($expense_date)) {

        $expense_date = date('Y-m-d');

    }



    // Verifica erros de entrada antes de atualizar no banco de dados

    if (empty($description_err) && empty($amount_err)) {

        // Adicionado 'expense_date' e 'status' na query SQL

        $sql = "UPDATE expenses SET description = ?, amount = ?, expense_date = ?, category = ?, notes = ?, status = ? WHERE id = ?";



        if ($stmt = $mysqli->prepare($sql)) {

            // Adicionado 's' para o tipo de data e status

            $stmt->bind_param("sdssssi", $param_description, $param_amount, $param_expense_date, $param_category, $param_notes, $param_status, $param_id);



            $param_description = $description;

            $param_amount = $amount;

            $param_expense_date = $expense_date; // Atribui a data da despesa

            $param_category = $category;

            $param_notes = $notes;

            $param_status = $status; // Atribui o status da despesa

            $param_id = $id;



            if ($stmt->execute()) {

                $success_message = "Despesa atualizada com sucesso!";

            } else {

                $error_message = "Erro ao atualizar despesa. Por favor, tente novamente.";

            }



            $stmt->close();

        }

    }

    $mysqli->close(); // Fecha a conexão após a operação

} else {

    // Se a requisição for GET (para carregar o formulário de edição)

    if (isset($_GET["id"]) && !empty(trim($_GET["id"]))) {

        $id = trim($_GET["id"]);



        // Adicionado 'expense_date' e 'status' na query SELECT

        $sql = "SELECT description, amount, expense_date, category, notes, status FROM expenses WHERE id = ?";

        if ($stmt = $mysqli->prepare($sql)) {

            $stmt->bind_param("i", $param_id);

            $param_id = $id;



            if ($stmt->execute()) {

                $result = $stmt->get_result();

                if ($result->num_rows == 1) {

                    $row = $result->fetch_assoc();

                    $description = $row["description"];

                    $amount = $row["amount"];

                    $expense_date = date('Y-m-d', strtotime($row["expense_date"])); // Formata para input type="date"

                    $category = $row["category"];

                    $notes = $row["notes"];

                    $status = $row["status"];

                } else {

                    $error_message = "Despesa não encontrada.";

                }

            } else {

                $error_message = "Ops! Algo deu errado. Por favor, tente novamente mais tarde.";

            }

            $stmt->close();

        }

    } else {

        // ID não fornecido, redireciona para a lista de despesas

        header("location: list_expenses.php");

        exit();

    }

    $mysqli->close(); // Fecha a conexão após a consulta inicial

}

?>



<h2>Editar Despesa</h2>



<?php if (!empty($success_message)): ?>

    <div class="alert-message alert-success"><?php echo $success_message; ?></div>

<?php endif; ?>

<?php if (!empty($error_message)): ?>

    <div class="alert-message alert-error"><?php echo $error_message; ?></div>

<?php endif; ?>



<form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">

    <input type="hidden" name="id" value="<?php echo htmlspecialchars($id); ?>">

    <div class="form-group">

        <label>Descrição <span style="color: red;">*</span></label>

        <input type="text" name="description" value="<?php echo htmlspecialchars($description); ?>">

        <span class="help-block" style="color: red;"><?php echo $description_err; ?></span>

    </div>

    <div class="form-group">

        <label>Valor (R$) <span style="color: red;">*</span></label>

        <input type="number" name="amount" step="0.01" min="0.01" value="<?php echo htmlspecialchars($amount); ?>">

        <span class="help-block" style="color: red;"><?php echo $amount_err; ?></span>

    </div>

    <div class="form-group">

        <label>Data da Despesa</label>

        <input type="date" name="expense_date" value="<?php echo htmlspecialchars($expense_date); ?>">

    </div>

    <div class="form-group">

        <label>Categoria</label>

        <input type="text" name="category" value="<?php echo htmlspecialchars($category); ?>">

    </div>

    <div class="form-group">

        <label>Observações</label>

        <textarea name="notes"><?php echo htmlspecialchars($notes); ?></textarea>

    </div>

    <div class="form-group">

        <label>Status do Pagamento</label>

        <select name="status">

            <option value="Pendente" <?php echo ($status == 'Pendente') ? 'selected' : ''; ?>>Pendente</option>

            <option value="Pago" <?php echo ($status == 'Pago') ? 'selected' : ''; ?>>Pago</option>

        </select>

    </div>

    <div class="form-actions">

        <input type="submit" class="btn" value="Salvar Alterações">

        <a href="list_expenses.php" class="btn btn-secondary">Cancelar</a>

    </div>

</form>



<?php

require_once 'footer.php';

?>