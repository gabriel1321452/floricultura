<?php
session_start();

if (!isset($_SESSION["usuario"])) {
    header("Location: login.php");
    exit;
}

require "conexao.php";
require "carrinho_funcoes.php";

if (!isset($_SESSION["carrinho"])) {
    $_SESSION["carrinho"] = [];
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $acao = $_POST["acao"] ?? "";
    $id = (int) ($_POST["id"] ?? 0);

    switch ($acao) {

        case "adicionar":
            if ($id > 0) {
                $_SESSION["carrinho"][$id] = ($_SESSION["carrinho"][$id] ?? 0) + 1;
            }
            break;

        case "aumentar":
            if (isset($_SESSION["carrinho"][$id])) {
                $_SESSION["carrinho"][$id]++;
            }
            break;

        case "diminuir":
            if (isset($_SESSION["carrinho"][$id])) {
                $_SESSION["carrinho"][$id]--;
                if ($_SESSION["carrinho"][$id] <= 0) {
                    unset($_SESSION["carrinho"][$id]);
                }
            }
            break;

        case "remover":
            unset($_SESSION["carrinho"][$id]);
            break;

        case "limpar":
            $_SESSION["carrinho"] = [];
            break;
    }

    header("Location: carrinho.php");
    exit;
}

list($itens, $total) = carregarCarrinho($conexao);
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Floricultura Tulipa - Carrinho</title>

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
        }

        header nav {
            display: flex;
            gap: 25px;
        }

        header nav a {
            color: var(--claro);
            text-decoration: none;
            font-weight: 600;
            font-size: 1.1rem;
        }

        header nav a:hover {
            color: var(--amarelo);
        }

        .carrinho-wrapper {
            width: 90%;
            max-width: 850px;
            background-color: var(--laranja);
            border: 4px solid var(--amarelo);
            border-radius: 12px;
            padding: 40px 25px;
            margin: 40px auto;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.4);
        }

        .carrinho-wrapper h2 {
            font-family: 'Pacifico', cursive;
            font-size: 3.2rem;
            color: var(--claro);
            font-weight: normal;
            text-align: center;
            margin-bottom: 25px;
        }

        .lista {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .item {
            display: flex;
            align-items: center;
            gap: 18px;
            background-color: var(--escuro);
            border: 2px solid var(--amarelo);
            border-radius: 30px;
            padding: 12px 22px;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.2);
            color: var(--claro);
        }

        .item img {
            width: 70px;
            height: 70px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid var(--amarelo);
            flex-shrink: 0;
        }

        .item-info {
            flex: 1;
        }

        .item-info h3 {
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 2px;
        }

        .preco-unit {
            font-size: 0.9rem;
            color: rgba(255, 249, 205, 0.7);
            margin-bottom: 6px;
        }

        .qtd {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .qtd span {
            font-size: 1.2rem;
            font-weight: 700;
            min-width: 24px;
            text-align: center;
        }

        .subtotal {
            font-size: 1.2rem;
            font-weight: 700;
            min-width: 100px;
            text-align: right;
            color: var(--amarelo);
        }

        .total {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
            padding: 12px 25px;
            background-color: var(--escuro);
            border: 2px solid var(--amarelo);
            border-radius: 30px;
            color: var(--claro);
            font-size: 1.5rem;
            font-weight: 700;
        }

        .total span:last-child {
            color: var(--amarelo);
        }

        .btn {
            background-color: var(--amarelo);
            color: var(--escuro);
            border: none;
            padding: 12px 25px;
            font-size: 1.1rem;
            font-weight: 700;
            border-radius: 25px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.2);
            transition: all 0.2s ease;
        }

        .btn:hover {
            background-color: var(--claro);
            transform: translateY(-2px);
        }

        .btn-pequeno {
            padding: 4px 14px;
            font-size: 0.9rem;
        }

        .btn-redondo {
            width: 34px;
            height: 34px;
            padding: 0;
            font-size: 1.2rem;
            line-height: 34px;
            border-radius: 50%;
        }

        .acoes {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 25px;
        }

        .acoes .btn {
            flex: 1;
            max-width: 280px;
        }

        .vazio {
            text-align: center;
            color: var(--claro);
        }

        .vazio p {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 20px;
        }

        @media (max-width: 768px) {
            .item {
                flex-wrap: wrap;
                border-radius: 20px;
                justify-content: center;
                text-align: center;
            }

            .item-info {
                flex-basis: 100%;
            }

            .qtd {
                justify-content: center;
            }

            .subtotal {
                text-align: center;
            }

            .acoes {
                flex-direction: column;
                align-items: center;
            }

            .acoes .btn {
                width: 100%;
                max-width: none;
            }
        }
    </style>
</head>

<body>

<header>
    <h1>Floricultura Tulipa</h1>
    <nav>
        <a href="index.php">Continuar comprando</a>
        <a href="logout.php">Sair</a>
    </nav>
</header>

<main class="carrinho-wrapper">

    <h2>Meu carrinho</h2>

    <?php if (empty($itens)) { ?>

        <div class="vazio">
            <p>Seu carrinho está vazio.</p>
            <a href="index.php" class="btn">Ver produtos</a>
        </div>

    <?php } else { ?>

        <div class="lista">

            <?php foreach ($itens as $item) { ?>

                <div class="item">

                    <img
                        src="<?php echo htmlspecialchars(preg_match('#^https?://#i', $item["imagem"]) ? $item["imagem"] : "imagens/" . $item["imagem"]); ?>"
                        alt="<?php echo htmlspecialchars($item["nome"]); ?>"
                    >

                    <div class="item-info">
                        <h3><?php echo htmlspecialchars($item["nome"]); ?></h3>
                        <div class="preco-unit"><?php echo dinheiro($item["preco"]); ?> cada</div>

                        <form method="POST">
                            <input type="hidden" name="acao" value="remover">
                            <input type="hidden" name="id" value="<?php echo $item["id"]; ?>">
                            <button type="submit" class="btn btn-pequeno">Remover</button>
                        </form>
                    </div>

                    <div class="qtd">
                        <form method="POST">
                            <input type="hidden" name="acao" value="diminuir">
                            <input type="hidden" name="id" value="<?php echo $item["id"]; ?>">
                            <button type="submit" class="btn btn-redondo">−</button>
                        </form>

                        <span><?php echo $item["quantidade"]; ?></span>

                        <form method="POST">
                            <input type="hidden" name="acao" value="aumentar">
                            <input type="hidden" name="id" value="<?php echo $item["id"]; ?>">
                            <button type="submit" class="btn btn-redondo">+</button>
                        </form>
                    </div>

                    <div class="subtotal"><?php echo dinheiro($item["subtotal"]); ?></div>

                </div>

            <?php } ?>

        </div>

        <div class="total">
            <span>Total</span>
            <span><?php echo dinheiro($total); ?></span>
        </div>

        <div class="acoes">
            <form method="POST">
                <input type="hidden" name="acao" value="limpar">
                <button type="submit" class="btn">Esvaziar carrinho</button>
            </form>

            <a href="confirmacao.php" class="btn">Finalizar compra</a>
        </div>

    <?php } ?>

</main>

</body>
</html>
