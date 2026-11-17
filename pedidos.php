<?php
session_start();

if (empty($_SESSION["usuario"])) {
    header("Location: login.php");
    exit;
}

require "conexao.php";
require "carrinho_funcoes.php";

if (empty($_SESSION["csrf_devolucao"])) {
    $_SESSION["csrf_devolucao"] = bin2hex(random_bytes(32));
}

$mensagem = $_SESSION["flash_devolucao"] ?? "";
unset($_SESSION["flash_devolucao"]);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $itemIdPostado = $_POST["item_pedido_id"] ?? "";
    $motivoPostado = $_POST["motivo"] ?? "";
    $csrfPostado = $_POST["csrf_devolucao"] ?? "";

    if (!is_string($itemIdPostado)
        || !ctype_digit($itemIdPostado)
        || (int) $itemIdPostado < 1
        || !is_string($motivoPostado)
        || !is_string($csrfPostado)
        || !hash_equals($_SESSION["csrf_devolucao"], $csrfPostado)) {
        http_response_code(400);
        exit("Solicitação inválida. Atualize a página e tente novamente.");
    }

    $itemId = (int) $itemIdPostado;
    $motivo = trim($motivoPostado);

    if ($motivo === "" || mb_strlen($motivo, "UTF-8") > 1000) {
        $_SESSION["flash_devolucao"] = "Informe um motivo com até 1.000 caracteres.";
    } else {
        $stmt = $conexao->prepare(
            "INSERT INTO solicitacoes_devolucao (item_pedido_id, motivo)
             SELECT ip.id, ?
             FROM itens_pedido ip
             JOIN pedidos p ON p.id = ip.pedido_id
             WHERE ip.id = ? AND p.usuario = ?
               AND NOT EXISTS (
                   SELECT 1 FROM solicitacoes_devolucao sd
                   WHERE sd.item_pedido_id = ip.id
               )"
        );
        $stmt->bind_param("sis", $motivo, $itemId, $_SESSION["usuario"]);
        $stmt->execute();
        $solicitacaoCriada = $stmt->affected_rows === 1;
        $stmt->close();

        $_SESSION["flash_devolucao"] = $solicitacaoCriada
            ? "Solicitação enviada. A loja irá analisá-la."
            : "Não foi possível solicitar a devolução deste item. Confira se ele pertence a um pedido seu e se não há outra solicitação.";
    }

    header("Location: pedidos.php");
    exit;
}

$stmt = $conexao->prepare("SELECT id, endereco, forma_pagamento, total, criado_em FROM pedidos WHERE usuario = ? ORDER BY criado_em DESC, id DESC");
$stmt->bind_param("s", $_SESSION["usuario"]);
$stmt->execute();
$pedidos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$pagamentos = [
    "pix" => "Pix",
    "cartao" => "Cartão de crédito",
    "boleto" => "Boleto"
];
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meus pedidos - Floricultura Tulipa</title>
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

        main {
            width: min(900px, 92%);
            margin: 36px auto;
        }

        h2 {
            margin-bottom: 24px;
            color: var(--amarelo);
            text-align: center;
            font-size: 2rem;
        }

        .pedido {
            margin-bottom: 20px;
            padding: 22px;
            background: var(--laranja);
            border: 3px solid var(--amarelo);
            border-radius: 16px;
        }

        .pedido-cabecalho {
            display: flex;
            justify-content: space-between;
            gap: 14px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--amarelo);
        }

        .pedido h3,
        .pedido strong {
            color: var(--amarelo);
        }

        .itens {
            display: grid;
            gap: 8px;
            margin: 14px 0;
        }

        .item {
            display: grid;
            gap: 10px;
            padding: 8px 12px;
            background: var(--escuro);
            border-radius: 12px;
        }

        .item-detalhe {
            display: flex;
            justify-content: space-between;
            gap: 12px;
        }

        .devolucao-form {
            display: grid;
            gap: 8px;
        }

        .devolucao-form textarea {
            width: 100%;
            min-height: 70px;
            padding: 9px;
            border: 1px solid var(--amarelo);
            border-radius: 8px;
            resize: vertical;
        }

        .devolucao-status,
        .aviso {
            font-size: 0.9rem;
        }

        .total {
            text-align: right;
            font-size: 1.1rem;
        }

        .vazio {
            padding: 24px;
            background: var(--laranja);
            border: 3px solid var(--amarelo);
            border-radius: 16px;
            text-align: center;
        }

        @media (max-width: 600px) {
            header {
                align-items: flex-start;
                flex-direction: column;
                padding: 14px;
            }

            .pedido-cabecalho {
                flex-direction: column;
            }
        }
    </style>
</head>

<body>

<header>
    <h1>Floricultura Tulipa</h1>
    <nav>
        <a href="index.php">Loja</a>
        <a href="carrinho.php">Carrinho</a>
    </nav>
</header>

<main>
    <h2>Meus pedidos</h2>

    <?php if ($mensagem !== "") { ?>
        <p class="vazio"><?php echo htmlspecialchars($mensagem); ?></p>
    <?php } ?>

    <?php if (empty($pedidos)) { ?>
        <p class="vazio">Você ainda não fez pedidos.</p>
    <?php } else { ?>
        <?php foreach ($pedidos as $pedido) { ?>
            <?php
            $stmtItens = $conexao->prepare(
                "SELECT ip.id, ip.nome, ip.preco, ip.quantidade, sd.id AS solicitacao_id, sd.motivo, sd.status
                 FROM itens_pedido ip
                 LEFT JOIN solicitacoes_devolucao sd ON sd.item_pedido_id = ip.id
                 WHERE ip.pedido_id = ?
                 ORDER BY ip.id"
            );
            $stmtItens->bind_param("i", $pedido["id"]);
            $stmtItens->execute();
            $itensPedido = $stmtItens->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmtItens->close();
            ?>
            <section class="pedido">
                <div class="pedido-cabecalho">
                    <h3>Pedido nº <?php echo (int) $pedido["id"]; ?></h3>
                    <span><?php echo htmlspecialchars($pedido["criado_em"]); ?></span>
                </div>
                <p><strong>Entrega:</strong> <?php echo htmlspecialchars($pedido["endereco"]); ?></p>
                <p><strong>Pagamento:</strong> <?php echo htmlspecialchars($pagamentos[$pedido["forma_pagamento"]] ?? $pedido["forma_pagamento"]); ?> (demonstração)</p>
                <div class="itens">
                    <?php foreach ($itensPedido as $item) { ?>
                        <div class="item">
                            <div class="item-detalhe">
                                <span><?php echo (int) $item["quantidade"]; ?> × <?php echo htmlspecialchars($item["nome"]); ?></span>
                                <span><?php echo dinheiro((float) $item["preco"] * (int) $item["quantidade"]); ?></span>
                            </div>
                            <?php if ($item["solicitacao_id"] === null) { ?>
                                <form method="POST" class="devolucao-form">
                                    <input type="hidden" name="csrf_devolucao" value="<?php echo htmlspecialchars($_SESSION["csrf_devolucao"]); ?>">
                                    <input type="hidden" name="item_pedido_id" value="<?php echo (int) $item["id"]; ?>">
                                    <label for="motivo-<?php echo (int) $item["id"]; ?>">Solicitar devolução:</label>
                                    <textarea id="motivo-<?php echo (int) $item["id"]; ?>" name="motivo" maxlength="1000" placeholder="Conte o motivo da solicitação" required></textarea>
                                    <button class="btn" type="submit">Enviar solicitação</button>
                                </form>
                            <?php } else { ?>
                                <p class="devolucao-status">
                                    <strong>Devolução:</strong> <?php echo htmlspecialchars(ucfirst($item["status"])); ?>
                                    — <?php echo htmlspecialchars($item["motivo"]); ?>
                                </p>
                            <?php } ?>
                        </div>
                    <?php } ?>
                </div>
                <p class="total"><strong>Total: <?php echo dinheiro($pedido["total"]); ?></strong></p>
            </section>
        <?php } ?>
        <p class="aviso">As solicitações são analisadas pela loja. Como este checkout é demonstrativo, não há reembolso automático nem alteração automática do estoque.</p>
    <?php } ?>
</main>

</body>
</html>
