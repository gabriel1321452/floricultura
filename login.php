<?php
session_start();

if (isset($_SESSION["usuario"])) {
    header("Location: " . (!empty($_SESSION["admin"]) ? "admin.php" : "index.php"));
    exit;
}

$servidor = "localhost";
$usuario = "root";
$senha = "";
$banco = "loja";

$conexao = new mysqli($servidor, $usuario, $senha, $banco);

if ($conexao->connect_error) {
    die("Erro na conexão: " . $conexao->connect_error);
}

$conexao->set_charset("utf8mb4");

$erro = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $usuario_login = $_POST["usuario"];
    $senha_login = $_POST["senha"];

    $sql = "SELECT id, usuario, senha, is_admin FROM usuarios_login WHERE usuario = ?";

    $stmt = $conexao->prepare($sql);
    $stmt->bind_param("s", $usuario_login);
    $stmt->execute();

    $resultado = $stmt->get_result();

    if ($resultado->num_rows == 1) {

        $dados = $resultado->fetch_assoc();

        if (password_verify($senha_login, $dados["senha"])) {

            session_regenerate_id(true);

            $_SESSION["usuario"] = $dados["usuario"];
            $_SESSION["user_id"] = (int) $dados["id"];
            $_SESSION["admin"] = (int) $dados["is_admin"] === 1;

            header("Location: " . ($_SESSION["admin"] ? "admin.php" : "index.php"));
            exit;

        } else {
            $erro = "Usuário ou senha incorretos.";
        }

    } else {
        $erro = "Usuário ou senha incorretos.";
    }

    $stmt->close();
}

$conexao->close();
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Floricultura Tulipa - Login</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Pacifico&family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --claro: #fff9cd;
            --amarelo: #ffc04b;
            --laranja: #d56a42;
            --verde: #58610c;
            --escuro: #703227;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background-color: var(--escuro);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        header {
            width: 100%;
            background-color: var(--laranja);
            border-bottom: 4px solid var(--amarelo);
            padding: 12px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        header h1 {
            font-family: 'Pacifico', cursive;
            font-size: 1.8rem;
            color: var(--claro);
            font-weight: normal;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        header h1::before {
            font-size: 1.6rem;
        }

        .header-links a {
            color: var(--claro);
            text-decoration: none;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .login-wrapper {
            position: relative;
            width: 90%;
            max-width: 850px;
            background-color: var(--laranja);
            border: 4px solid var(--amarelo);
            border-radius: 12px;
            padding: 40px 20px;
            margin: 40px auto;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.4);
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .login-card {
            width: 100%;
            max-width: 500px;
            text-align: center;
        }

        .login-card h2 {
            font-family: 'Pacifico', cursive;
            font-size: 4rem;
            color: var(--claro);
            font-weight: normal;
            margin-bottom: 25px;
        }

        .erro-msg {
            background-color: #ff4d4d;
            color: #fff;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 600;
        }

        form {
            display: flex;
            flex-direction: column;
            gap: 15px;
            align-items: center;
        }

        .input-group {
            width: 100%;
            display: flex;
            align-items: center;
            background-color: var(--escuro);
            border: 2px solid var(--amarelo);
            border-radius: 30px;
            padding: 10px 20px;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.2);
        }

        .input-group label {
            color: var(--claro);
            font-size: 1.3rem;
            font-weight: 700;
            margin-right: 10px;
            white-space: nowrap;
        }

        .input-group input {
            background: transparent;
            border: none;
            outline: none;
            color: var(--claro);
            font-size: 1.2rem;
            width: 100%;
            font-weight: 600;
        }

        .input-group input::placeholder {
            color: rgba(255, 249, 205, 0.6);
        }

        .buttons-group {
            display: flex;
            gap: 15px;
            width: 100%;
            justify-content: center;
            margin-top: 15px;
        }

        .btn {
            flex: 1;
            background-color: var(--amarelo);
            color: var(--escuro);
            border: none;
            padding: 12px 20px;
            font-size: 1.3rem;
            font-weight: 700;
            border-radius: 25px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0,0,0,0.2);
            transition: all 0.2s ease;
        }

        .btn:hover {
            background-color: var(--claro);
            transform: translateY(-2px);
        }

        .tulipa-decor {
            position: absolute;
            bottom: -10px;
            font-size: 5rem;
            user-select: none;
        }

        .tulipa-esquerda {
            left: 20px;
        }

        .tulipa-direita {
            right: 20px;
        }

        @media (max-width: 768px) {
            .tulipa-decor {
                display: none;
            }
            .buttons-group {
                flex-direction: column;
            }
        }
    </style>
</head>

<body>

<header>
    <h1>Floricultura Tulipa</h1>
    <div class="header-links">
        <a href="login.php">Fazer Login</a>
    </div>
</header>

<div class="login-wrapper">

    <div class="tulipa-decor tulipa-esquerda"></div>
    <div class="tulipa-decor tulipa-direita"></div>

    <div class="login-card">

        <h2>Login</h2>

        <?php if ($erro != "") { ?>
            <div class="erro-msg">
                <?php echo htmlspecialchars($erro); ?>
            </div>
        <?php } ?>

        <form method="POST">

            <div class="input-group">
                <label>Usuário:</label>
                <input
                    type="text"
                    name="usuario"
                    required
                >
            </div>

            <div class="input-group">
                <label>Senha:</label>
                <input
                    type="password"
                    name="senha"
                    required
                >
            </div>

            <div class="buttons-group">
                <button type="submit" class="btn">
                    Entrar
                </button>

                <a href="cadastro.php" class="btn">
                    Cadastrar
                </a>
            </div>

        </form>

    </div>

</div>

</body>

</html>