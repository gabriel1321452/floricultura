<?php
session_start();

if (!isset($_SESSION["usuario"])) {
    header("Location: login.php");
    exit;
}

require "conexao.php";
require "carrinho_funcoes.php";

$erro = "";
$pagamentos = [
    "pix" => "Pix",
    "cartao" => "Cartão de crédito",
    "boleto" => "Boleto"
];

if (empty($_SESSION["csrf_checkout"])) {
    $_SESSION["csrf_checkout"] = bin2hex(random_bytes(32));
}

function estilo()
{
    ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

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

        .wrapper {
            width: 90%;
            max-width: 850px;
            background-color: var(--laranja);
            border: 4px solid var(--amarelo);
            border-radius: 12px;
            padding: 40px 25px;
            margin: 40px auto;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.4);
        }

        .wrapper h2 {
            font-family: 'Pacifico', cursive;
            font-size: 3.2rem;
            color: var(--claro);
            font-weight: normal;
            text-align: center;
            margin-bottom: 25px;
        }

        .wrapper h3.secao {
            font-family: 'Pacifico', cursive;
            font-size: 1.8rem;
            color: var(--claro);
            font-weight: normal;
            margin-bottom: 15px;
            text-align: center;
        }

        .secao-bloco {
            margin-bottom: 30px;
        }

        .erro-msg {
            background-color: #ff4d4d;
            color: #fff;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 600;
            text-align: center;
        }

        .lista {
            display: flex;
            flex-direction: column;
            gap: 12px;
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
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid var(--amarelo);
            flex-shrink: 0;
        }

        .item-info {
            flex: 1;
        }

        .item-info h3 {
            font-size: 1.1rem;
            font-weight: 700;
        }

        .item-info .detalhe {
            font-size: 0.9rem;
            color: rgba(255, 249, 205, 0.7);
        }

        .subtotal {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--amarelo);
            text-align: right;
            min-width: 100px;
        }

        .total {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
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

        form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .input-group {
            width: 100%;
            display: flex;
            align-items: center;
            background-color: var(--escuro);
            border: 2px solid var(--amarelo);
            border-radius: 30px;
            padding: 10px 20px;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .input-group label {
            color: var(--claro);
            font-size: 1.1rem;
            font-weight: 700;
            margin-right: 10px;
            white-space: nowrap;
        }

        .input-group input,
        .input-group select {
            background: transparent;
            border: none;
            outline: none;
            color: var(--claro);
            font-size: 1.1rem;
            width: 100%;
            font-weight: 600;
        }

        .input-group select option {
            background-color: var(--escuro);
            color: var(--claro);
        }

        .input-group input::placeholder {
            color: rgba(255, 249, 205, 0.6);
        }

        .payment-demo {
            display: none;
            margin-top: 10px;
            padding: 18px 20px;
            background-color: var(--escuro);
            border: 2px solid var(--amarelo);
            border-radius: 25px;
            color: var(--claro);
        }

        .payment-demo p {
            margin-bottom: 10px;
        }

        .payment-demo img {
            width: 180px;
            height: 180px;
            object-fit: contain;
            background: #fff;
            border-radius: 18px;
            padding: 10px;
            display: block;
            margin: 12px auto;
        }

        .payment-demo input {
            background: var(--claro);
            color: var(--escuro);
            border: 1px solid var(--amarelo);
            border-radius: 10px;
            padding: 10px;
            width: 100%;
            margin: 5px 0;
        }

        .btn {
            background-color: var(--amarelo);
            color: var(--escuro);
            border: none;
            padding: 12px 25px;
            font-size: 1.2rem;
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

        .buttons-group {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 10px;
        }

        .buttons-group .btn {
            flex: 1;
            max-width: 280px;
        }

        .sucesso {
            text-align: center;
            color: var(--claro);
            margin-bottom: 30px;
        }

        .sucesso .check {
            width: 90px;
            height: 90px;
            line-height: 90px;
            margin: 0 auto 15px;
            border-radius: 50%;
            background-color: var(--amarelo);
            color: var(--escuro);
            font-size: 3rem;
            font-weight: 700;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.2);
        }

        .sucesso p {
            font-size: 1.2rem;
        }

        .sucesso strong {
            color: var(--amarelo);
        }

        .info-entrega {
            margin-top: 20px;
            padding: 15px 25px;
            background-color: var(--escuro);
            border: 2px solid var(--amarelo);
            border-radius: 25px;
            color: var(--claro);
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .info-entrega strong {
            color: var(--amarelo);
        }

        .voltar {
            text-align: center;
            margin-top: 25px;
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

            .subtotal {
                text-align: center;
            }

            .input-group {
                flex-direction: column;
                align-items: stretch;
                border-radius: 20px;
                gap: 5px;
            }

            .buttons-group {
                flex-direction: column;
                align-items: center;
            }

            .buttons-group .btn {
                width: 100%;
                max-width: none;
            }
        }
    </style>
    <?php
}

if (isset($_GET["pedido"])) {

    $pedido_id = (int) $_GET["pedido"];

    $stmt = $conexao->prepare("SELECT * FROM pedidos WHERE id = ? AND usuario = ?");
    $stmt->bind_param("is", $pedido_id, $_SESSION["usuario"]);
    $stmt->execute();
    $pedido = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$pedido) {
        header("Location: index.php");
        exit;
    }

    $stmt = $conexao->prepare("SELECT * FROM itens_pedido WHERE pedido_id = ?");
    $stmt->bind_param("i", $pedido_id);
    $stmt->execute();
    $itens_pedido = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    ?>

    <!DOCTYPE html>
    <html lang="pt-br">
    <head>
        <meta charset="UTF-8">
        <title>Floricultura Tulipa - Compra confirmada</title>
        <?php estilo(); ?>
    </head>
    <body>

    <header>
        <h1>Floricultura Tulipa</h1>
        <nav><a href="index.php">Início</a><a href="logout.php">Sair</a></nav>
    </header>

    <main class="wrapper">
        <div class="sucesso">
            <div class="check">✔</div>
            <h2>Pedido demonstrativo criado</h2>
            <p>Pedido nº <strong><?php echo $pedido["id"]; ?></strong>. Nenhuma cobrança real foi feita.</p>
        </div>

        <div class="lista">
            <?php foreach ($itens_pedido as $item) { ?>
                <div class="item">
                    <div class="item-info">
                        <h3><?php echo htmlspecialchars($item["nome"]); ?></h3>
                        <div class="detalhe"><?php echo $item["quantidade"]; ?> x <?php echo dinheiro($item["preco"]); ?></div>
                    </div>
                    <div class="subtotal"><?php echo dinheiro($item["quantidade"] * $item["preco"]); ?></div>
                </div>
            <?php } ?>
        </div>

        <div class="total">
            <span>Total</span>
            <span><?php echo dinheiro($pedido["total"]); ?></span>
        </div>

        <div class="info-entrega">
            <p><strong>Entrega em:</strong> <?php echo htmlspecialchars($pedido["endereco"]); ?></p>
            <p><strong>Pagamento:</strong> <?php echo htmlspecialchars($pagamentos[$pedido["forma_pagamento"]] ?? $pedido["forma_pagamento"]); ?></p>
            <p><strong>Status:</strong> Simulação para apresentação — sem pagamento real.</p>
            <?php if ($pedido["forma_pagamento"] === "boleto") { ?>
                <p><strong>Linha digitável demonstrativa:</strong> 00000.00000 00000.000000 00000.000000 0 00000000000000</p>
                <p>Este boleto é fictício e não pode ser pago.</p>
            <?php } elseif ($pedido["forma_pagamento"] === "pix") { ?>
                <p><strong>Pix demonstrativo:</strong> QR Code fictício, sem cobrança real.</p>
                <img
                    src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&amp;data=<?php echo urlencode("DEMO-SEM-PAGAMENTO-PEDIDO-" . $pedido["id"]); ?>"
                    alt="QR Code fictício para demonstração"
                    width="180"
                    height="180"
                >
            <?php } else { ?>
                <p>Pagamento com cartão simulado. Nenhum dado de cartão foi enviado ou armazenado.</p>
            <?php } ?>
        </div>

        <div class="voltar">
            <a href="index.php" class="btn">Voltar à loja</a>
        </div>
    </main>

    </body>
    </html>
    <?php
    exit;
}

list($itens, $total) = carregarCarrinho($conexao);

if (empty($itens)) {
    header("Location: carrinho.php");
    exit;
}

$endereco = "";
$forma = "pix";
$cep = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $enderecoPostado = $_POST["endereco"] ?? "";
    $cepPostado = $_POST["cep"] ?? "";
    $formaPostada = $_POST["forma_pagamento"] ?? "";
    $csrfPostado = $_POST["csrf_checkout"] ?? "";
    $endereco = is_string($enderecoPostado) ? trim($enderecoPostado) : "";
    $cep = is_string($cepPostado) ? preg_replace("/\D/", "", $cepPostado) : "";
    $forma = is_string($formaPostada) ? $formaPostada : "";
    $csrfEnviado = is_string($csrfPostado) ? $csrfPostado : "";

    if (!hash_equals($_SESSION["csrf_checkout"], $csrfEnviado)) {
        $erro = "A sessão expirou. Atualize a página e tente novamente.";
    } elseif (strlen($cep) !== 8) {
        $erro = "Informe um CEP válido com 8 números.";
    } elseif ($endereco === "") {
        $erro = "Informe o endereço de entrega.";
    } elseif (!isset($pagamentos[$forma])) {
        $erro = "Escolha uma forma de pagamento válida.";
    } else {
        try {
            $conexao->begin_transaction();
            $enderecoPedido = "CEP " . $cep . " - " . $endereco;

            $stmt = $conexao->prepare(
                "INSERT INTO pedidos (usuario, endereco, forma_pagamento, total) VALUES (?, ?, ?, ?)"
            );
            $stmt->bind_param("sssd", $_SESSION["usuario"], $enderecoPedido, $forma, $total);
            $stmt->execute();
            $pedido_id = $conexao->insert_id;
            $stmt->close();

            $stmt = $conexao->prepare(
                "INSERT INTO itens_pedido (pedido_id, produto_id, nome, preco, quantidade) VALUES (?, ?, ?, ?, ?)"
            );
            $baixa = $conexao->prepare(
                "UPDATE produtos SET estoque = estoque - ? WHERE id = ? AND estoque >= ?"
            );

            foreach ($itens as $item) {
                $baixa->bind_param("iii", $item["quantidade"], $item["id"], $item["quantidade"]);
                $baixa->execute();

                if ($baixa->affected_rows === 0) {
                    throw new RuntimeException("estoque:" . $item["nome"]);
                }

                $stmt->bind_param(
                    "iisdi",
                    $pedido_id,
                    $item["id"],
                    $item["nome"],
                    $item["preco"],
                    $item["quantidade"]
                );
                $stmt->execute();
            }

            $stmt->close();
            $baixa->close();
            $conexao->commit();
            $_SESSION["carrinho"] = [];

            header("Location: confirmacao.php?pedido=" . $pedido_id);
            exit;
        } catch (Throwable $e) {
            $conexao->rollback();

            if (strpos($e->getMessage(), "estoque:") === 0) {
                $erro = "Estoque insuficiente para: " . substr($e->getMessage(), 8);
            } else {
                error_log("Falha ao registrar pedido demonstrativo: " . $e->getMessage());
                $erro = "Não foi possível registrar o pedido. Tente novamente.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <title>Floricultura Tulipa - Confirmar compra</title>
    <?php estilo(); ?>
</head>

<body>

<header>
    <h1>Floricultura Tulipa</h1>
    <nav>
        <a href="carrinho.php">Voltar ao carrinho</a>
        <a href="logout.php">Sair</a>
    </nav>
</header>

<main class="wrapper">

    <h2>Confirmar compra</h2>

    <?php if ($erro != "") { ?>
        <div class="erro-msg"><?php echo htmlspecialchars($erro); ?></div>
    <?php } ?>

    <div class="secao-bloco">
        <h3 class="secao">Resumo do pedido</h3>

        <div class="lista">
            <?php foreach ($itens as $item) { ?>
                <div class="item">
                    <img
                        src="<?php echo htmlspecialchars(preg_match('#^https?://#i', $item["imagem"]) ? $item["imagem"] : "imagens/" . $item["imagem"]); ?>"
                        alt="<?php echo htmlspecialchars($item["nome"]); ?>"
                    >
                    <div class="item-info">
                        <h3><?php echo htmlspecialchars($item["nome"]); ?></h3>
                        <div class="detalhe"><?php echo $item["quantidade"]; ?> x <?php echo dinheiro($item["preco"]); ?></div>
                    </div>
                    <div class="subtotal"><?php echo dinheiro($item["subtotal"]); ?></div>
                </div>
            <?php } ?>
        </div>

        <div class="total">
            <span>Total</span>
            <span><?php echo dinheiro($total); ?></span>
        </div>
    </div>

    <form method="POST">
        <input type="hidden" name="csrf_checkout" value="<?php echo htmlspecialchars($_SESSION["csrf_checkout"]); ?>">

        <h3 class="secao">Entrega e pagamento</h3>

        <div class="input-group">
            <label for="cep">CEP:</label>
            <input
                type="text"
                id="cep"
                name="cep"
                maxlength="9"
                placeholder="Digite o CEP"
                value="<?php echo htmlspecialchars($cep); ?>"
                required
            >
        </div>

        <div class="input-group">
            <label for="endereco">Endereço:</label>
            <input
                type="text"
                id="endereco"
                name="endereco"
                maxlength="240"
                placeholder="Rua, número, bairro, cidade"
                value="<?php echo htmlspecialchars($endereco); ?>"
                required
            >
        </div>

        <div class="input-group">
            <label for="forma_pagamento">Pagamento:</label>
            <select id="forma_pagamento" name="forma_pagamento">
                <?php foreach ($pagamentos as $valor => $rotulo) { ?>
                    <option value="<?php echo $valor; ?>" <?php echo $forma === $valor ? "selected" : ""; ?>>
                        <?php echo $rotulo; ?>
                    </option>
                <?php } ?>
            </select>
        </div>

        <div class="payment-demo" id="demo-pix">
            <p><strong>Pix — exemplo visual</strong></p>
            <p>Este QR Code é fictício e não aceita pagamentos.</p>
            <img
                src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&amp;data=<?php echo urlencode("DEMO-SEM-PAGAMENTO"); ?>"
                alt="QR Code Pix fictício para demonstração"
            >
        </div>

        <div class="payment-demo" id="demo-cartao">
            <p><strong>Cartão — dados de demonstração</strong></p>
            <p>Use apenas estes valores fictícios na apresentação. Os campos são apenas visuais e não são enviados nem salvos.</p>
            <label for="demo-nome-cartao">Nome no cartão</label>
            <input id="demo-nome-cartao" type="text" value="CLIENTE DEMONSTRACAO" readonly>
            <label for="demo-numero-cartao">Número do cartão</label>
            <input id="demo-numero-cartao" type="text" value="0000 0000 0000 0000" readonly>
            <label for="demo-validade-cartao">Validade</label>
            <input id="demo-validade-cartao" type="text" value="MM/AA" readonly>
            <label for="demo-cvv-cartao">Código de segurança</label>
            <input id="demo-cvv-cartao" type="text" value="000" readonly>
        </div>

        <div class="payment-demo" id="demo-boleto">
            <p><strong>Boleto — dados de demonstração</strong></p>
            <p>Pagador: CLIENTE DEMONSTRACAO</p>
            <p>CPF: 000.000.000-00</p>
            <p>Linha digitável fictícia: 00000.00000 00000.000000 00000.000000 0 00000000000000</p>
            <p>Este boleto é apenas ilustrativo e não pode ser pago.</p>
        </div>

        <div class="info-entrega">
            <p>Demonstração acadêmica: não há cobrança real. Nenhum dado de cartão, CVV ou documento é solicitado ao servidor ou armazenado.</p>
        </div>

        <div class="buttons-group">
            <a href="carrinho.php" class="btn">Voltar</a>
            <button type="submit" class="btn">Confirmar compra</button>
        </div>

    </form>

</main>

<script>
    const cepInput = document.getElementById("cep");
    const enderecoInput = document.getElementById("endereco");
    const formaPagamento = document.getElementById("forma_pagamento");
    const formasDemonstrativas = {
        pix: document.getElementById("demo-pix"),
        cartao: document.getElementById("demo-cartao"),
        boleto: document.getElementById("demo-boleto")
    };

    function atualizarDemonstracaoPagamento() {
        Object.keys(formasDemonstrativas).forEach(function (forma) {
            formasDemonstrativas[forma].style.display = formaPagamento.value === forma ? "block" : "none";
        });
    }

    formaPagamento.addEventListener("change", atualizarDemonstracaoPagamento);
    atualizarDemonstracaoPagamento();

    cepInput.addEventListener("blur", function () {
        const cep = cepInput.value.replace(/\D/g, "");

        if (cep.length !== 8) {
            return;
        }

        fetch("https://viacep.com.br/ws/" + cep + "/json/")
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                if (data.erro) {
                    return;
                }

                const rua = data.logradouro || "";
                const bairro = data.bairro || "";
                const cidade = data.localidade || "";
                const uf = data.uf || "";
                const enderecoCompleto = [rua, bairro, cidade + "-" + uf].filter(Boolean).join(", ");

                if (!enderecoInput.value.trim()) {
                    enderecoInput.value = enderecoCompleto;
                }
            })
            .catch(function () {
                console.log("Não foi possível buscar o endereço pelo CEP.");
            });
    });
</script>

</body>
</html>
