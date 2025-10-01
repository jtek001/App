<?php
// login.php
// Página de login do sistema

session_start(); // Inicia a sessão

// Verifica se o usuário já está logado, redireciona para o dashboard
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    header("location: dashboard.php");
    exit;
}

// Inclui o arquivo de conexão com o banco de dados
require_once 'db_connect.php';

$username = $password = "";
$username_err = $password_err = $login_err = "";

// Processa dados do formulário quando ele é enviado
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Verifica se o nome de usuário está vazio
    if (empty(trim($_POST["username"]))) {
        $username_err = "Por favor, insira o nome de usuário.";
    } else {
        $username = trim($_POST["username"]);
    }

    // Verifica se a senha está vazia
    if (empty(trim($_POST["password"]))) {
        $password_err = "Por favor, insira sua senha.";
    } else {
        $password = trim($_POST["password"]);
    }

    // Valida as credenciais
    if (empty($username_err) && empty($password_err)) {
        // Prepara uma declaração SELECT
        $sql = "SELECT id, username, password FROM users WHERE username = ?";

        if ($stmt = $mysqli->prepare($sql)) {
            // Vincula variáveis à declaração preparada como parmetros
            $stmt->bind_param("s", $param_username);

            // Define parâmetros
            $param_username = $username;

            // Tenta executar a declaração preparada
            if ($stmt->execute()) {
                // Armazena o resultado
                $stmt->store_result();

                // Verifica se o nome de usuário existe, se sim, verifica a senha
                if ($stmt->num_rows == 1) {
                    // Vincula variáveis de resultado
                    $stmt->bind_result($id, $username, $hashed_password);
                    if ($stmt->fetch()) {
                        if (password_verify($password, $hashed_password)) {
                            // Senha correta, inicia uma nova sessão
                            session_start();

                            // Armazena dados em variáveis de sessão
                            $_SESSION["loggedin"] = true;
                            $_SESSION["id"] = $id;
                            $_SESSION["username"] = $username;
                            $_SESSION["nome"] = $nome;

                            // Redireciona o usuário para a página de dashboard
                            header("location: dashboard.php");
                        } else {
                            // Senha inválida
                            $login_err = "Nome de usurio ou senha inválidos.";
                        }
                    }
                } else {
                    // Nome de usuário não existe
                    $login_err = "Nome de usuário ou senha inválidos.";
                }
            } else {
                echo "Ops! Algo deu errado. Por favor, tente novamente mais tarde.";
            }

            // Fecha a declaração
            $stmt->close();
        }
    }

    // Fecha a conexão
    $mysqli->close();
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JtekInfo-OS</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }
        .login-container {
            background-color: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            width: 250px;
            text-align: center;
        }
        .login-container h2 {
            margin-bottom: 20px;
            color: #333;
        }
        .form-group {
            margin-bottom: 15px;
            text-align: left;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #555;
        }
        .form-group input[type="text"],
        .form-group input[type="password"] {
            width: calc(100% - 22px);
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .form-group .help-block {
            color: red;
            font-size: 0.9em;
            margin-top: 5px;
        }
        .btn {
            background-color: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
            box-sizing: border-box;
        }
        .btn:hover {
            background-color: #0056b3;
        }
        .alert {
            color: red;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2><img src="logo.png" height="50"></h2>
        <?php
        if (!empty($login_err)) {
            echo '<div class="alert">' . $login_err . '</div>';
        }
        ?>
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="form-group">
                <label>Usuário:</label>
                <input type="text" name="username" class="form-control <?php echo (!empty($username_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $username; ?>">
                <span class="help-block"><?php echo $username_err; ?></span>
            </div>
            <div class="form-group">
                <label>Senha:</label>
                <input type="password" name="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>">
                <span class="help-block"><?php echo $password_err; ?></span>
            </div>
            <div class="form-group">
                <input type="submit" class="btn" value="Entrar">
            </div>
        </form>
    </div>
</body>
</html>