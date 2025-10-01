<?php

// add_expense.php

// Formulário para adicionar uma nova despesa



require_once 'header.php';

require_once 'db_connect.php';



$description = $amount = $category = $notes = $expense_date = $status = ""; // Adicionado $expense_date e $status

$description_err = $amount_err = "";

$error_message = "";



if ($_SERVER["REQUEST_METHOD"] == "POST") {

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



    // Verifica erros de entrada antes de inserir no banco de dados

    if (empty($description_err) && empty($amount_err)) {

        // Adicionado 'expense_date' e 'status' na query SQL

        $sql = "INSERT INTO expenses (description, amount, expense_date, category, notes, status) VALUES (?, ?, ?, ?, ?, ?)";



        if ($stmt = $mysqli->prepare($sql)) {

            // Adicionado 's' para o tipo de data e status

            $stmt->bind_param("sdssss", $param_description, $param_amount, $param_expense_date, $param_category, $param_notes, $param_status);



            $param_description = $description;

            $param_amount = $amount;

            $param_expense_date = $expense_date; // Atribui a data da despesa

            $param_category = $category;

            $param_notes = $notes;

            $param_status = $status; // Atribui o status da despesa



            if ($stmt->execute()) {

                // Redireciona para a lista de despesas após inserir com sucesso
                // Com filtro de data do primeiro e último dia do mês atual
                $first_day = date('Y-m-01');
                $last_day = date('Y-m-t');
                header("location: list_expenses.php?status=added&start_date=" . $first_day . "&end_date=" . $last_day);
                exit();

            } else {

                $error_message = "Erro ao adicionar despesa. Por favor, tente novamente.";

            }



            $stmt->close();

        }

    }

    $mysqli->close(); // Fecha a conexão após a operação

} else {

    $amount = 0.00; // Valor padrão ao carregar o formulário

    $expense_date = date('Y-m-d'); // Define a data atual como padrão ao carregar o formulário

    $status = 'Pendente'; // Define status padrão como Pendente

}

?>



<h2>Adicionar Nova Despesa</h2>



<?php if (!empty($error_message)): ?>

    <div class="alert-message alert-error"><?php echo $error_message; ?></div>

<?php endif; ?>



<form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">

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

        <input type="submit" class="btn" value="Adicionar Despesa">

        <a href="list_expenses.php" class="btn btn-secondary">Cancelar</a>

    </div>

</form>



<?php

require_once 'footer.php';

?>