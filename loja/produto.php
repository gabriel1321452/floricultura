<?php
session_start();

if (empty($_SESSION["usuario"])) {
    header("Location: login.php");
    exit;
}

require "conexao.php";

$id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    header("Location: index.php");
    exit;
}

$stmt = $conexao->prepare("SELECT id, nome, descricao, preco, imagem, estoque FROM produtos WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$produto = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$produto) {
    http_response_code(404);
    $produto = null;
}

$imagem = $produto
    ? (preg_match('#^https?://#i', $produto["imagem"] ?? "")
        ? $produto["imagem"]
        : "imagens/" . ($produto["imagem"] ?? ""))
    : "";
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $produto ? htmlspecialchars($produto["nome"]) . " - Floricultura Tulipa" : "Produto não encontrado"; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Pacifico&family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --claro: #fff9cd;
            --amarelo: #ffc04b;
            --laranja: #d56a42;
            --escuro: #703227;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            font-family: 'Poppins', sans-serif;
        }

        body {
            min-height: 100vh;
            background: var(--escuro);
            color: var(--claro);
        }

        header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 14px 28px;
            background: var(--laranja);
            border-bottom: 4px solid var(--amarelo);
        }

        header h1 {
            color: var(--claro);
            font: normal 1.8rem 'Pacifico', cursive;
        }

        header nav {
            display: flex;
            gap: 14px;
        }

        header a {
            color: var(--escuro);
            background: var(--amarelo);
            border-radius: 20px;
            padding: 8px 14px;
            text-decoration: none;
            font-weight: 700;
        }

        .produto-detalhe {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(280px, 1fr);
            gap: 30px;
            width: min(1000px, 92%);
            margin: 40px auto;
            padding: 28px;
            background: var(--laranja);
            border: 4px solid var(--amarelo);
            border-radius: 18px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.35);
        }

        .produto-detalhe img {
            width: 100%;
            min-height: 320px;
            max-height: 520px;
            object-fit: cover;
            border: 3px solid var(--amarelo);
            border-radius: 14px;
            background: var(--claro);
        }

        .produto-info {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .produto-info h2 {
            color: var(--claro);
            font: normal 2.5rem 'Pacifico', cursive;
        }

        .preco {
            color: var(--amarelo);
            font-size: 1.7rem;
            font-weight: 700;
        }

        .descricao {
            line-height: 1.7;
            white-space: pre-line;
        }

        .estoque {
            padding: 12px 16px;
            background: var(--escuro);
            border: 2px solid var(--amarelo);
            border-radius: 22px;
        }

        .btn {
            display: inline-block;
            border: 0;
            border-radius: 25px;
            padding: 12px 20px;
            color: var(--escuro);
            background: var(--amarelo);
            text-decoration: none;
            text-align: center;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
        }

        .btn:hover {
            background: var(--claro);
        }

        .btn[disabled] {
            cursor: not-allowed;
            opacity: 0.6;
        }

        @media (max-width: 700px) {
            header {
                align-items: flex-start;
                flex-direction: column;
                padding: 14px;
            }

            .produto-detalhe {
                grid-template-columns: 1fr;
                padding: 18px;
            }

            .produto-detalhe img {
                min-height: 240px;
                max-height: 360px;
            }

            .produto-info h2 {
                font-size: 2rem;
            }
        }
    </style>
</head>

<body>

<header>
    <h1>Floricultura Tulipa</h1>
    <nav>
        <a href="index.php">Loja</a>
        <a href="perfil.php">Perfil</a>
        <a href="pedidos.php">Meus pedidos</a>
        <a href="carrinho.php">Carrinho</a>
    </nav>
</header>

<?php if (!$produto) { ?>
    <main class="produto-detalhe">
        <div class="produto-info">
            <h2>Produto não encontrado</h2>
            <p>Este produto não está mais disponível na loja.</p>
            <a class="btn" href="index.php">Voltar à loja</a>
        </div>
    </main>
<?php } else { ?>
    <main class="produto-detalhe">
        <img src="<?php echo htmlspecialchars($imagem); ?>" alt="<?php echo htmlspecialchars($produto["nome"]); ?>">
        <div class="produto-info">
            <h2><?php echo htmlspecialchars($produto["nome"]); ?></h2>
            <p class="preco">R$ <?php echo number_format((float) $produto["preco"], 2, ",", "."); ?></p>
            <p class="descricao"><?php echo htmlspecialchars(trim($produto["descricao"] ?? "") ?: "Consulte nossa equipe para mais informações sobre esta flor."); ?></p>
            <p class="estoque">
                <?php if ((int) $produto["estoque"] > 0) { ?>
                    Disponível: <?php echo (int) $produto["estoque"]; ?> unidade(s)
                <?php } else { ?>
                    Produto esgotado no momento.
                <?php } ?>
            </p>
            <form method="POST" action="carrinho.php">
                <input type="hidden" name="acao" value="adicionar">
                <input type="hidden" name="id" value="<?php echo (int) $produto["id"]; ?>">
                <button class="btn" type="submit" <?php echo (int) $produto["estoque"] < 1 ? "disabled" : ""; ?>>Adicionar ao carrinho</button>
            </form>
            <a class="btn" href="index.php">Voltar à loja</a>
        </div>
    </main>
<?php } ?>

</body>
</html>
