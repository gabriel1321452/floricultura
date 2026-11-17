<?php
session_start();

if (empty($_SESSION["usuario"])) {
    header("Location: login.php");
    exit;
}

require "conexao.php";

$stmt = $conexao->prepare("SELECT id, is_admin FROM usuarios_login WHERE usuario = ?");
$stmt->bind_param("s", $_SESSION["usuario"]);
$stmt->execute();
$usuarioAutenticado = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$usuarioAutenticado || (int) $usuarioAutenticado["is_admin"] !== 1) {
    unset($_SESSION["admin"]);
    header("Location: index.php");
    exit;
}

$_SESSION["user_id"] = (int) $usuarioAutenticado["id"];
$_SESSION["admin"] = true;

if (empty($_SESSION["csrf_admin"])) {
    $_SESSION["csrf_admin"] = bin2hex(random_bytes(32));
}

function salvarImagemEnviada($arquivo)
{
    $tipos = [
        IMAGETYPE_JPEG => "jpg",
        IMAGETYPE_PNG => "png",
        IMAGETYPE_GIF => "gif",
        IMAGETYPE_WEBP => "webp"
    ];

    if (($arquivo["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException("Falha ao enviar a imagem. Tente novamente.");
    }

    if (($arquivo["size"] ?? 0) > 5 * 1024 * 1024) {
        throw new RuntimeException("A imagem deve ter no máximo 5 MB.");
    }

    $info = @getimagesize($arquivo["tmp_name"]);
    if (!$info || !isset($tipos[$info[2]])) {
        throw new RuntimeException("Envie uma imagem JPG, PNG, GIF ou WEBP.");
    }

    $pasta = __DIR__ . "/imagens";
    if (!is_dir($pasta) && !mkdir($pasta, 0755, true) && !is_dir($pasta)) {
        throw new RuntimeException("Não foi possível criar a pasta de imagens.");
    }

    $nomeArquivo = bin2hex(random_bytes(8)) . "." . $tipos[$info[2]];
    if (!move_uploaded_file($arquivo["tmp_name"], $pasta . "/" . $nomeArquivo)) {
        throw new RuntimeException("Não foi possível salvar a imagem enviada.");
    }

    return $nomeArquivo;
}

$erro = "";
$msg = $_SESSION["flash"] ?? "";
unset($_SESSION["flash"]);

$nome = "";
$descricao = "";
$preco = "";
$estoque = "";
$url = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $csrfPostado = $_POST["csrf_admin"] ?? "";
    if (!is_string($csrfPostado) || !hash_equals($_SESSION["csrf_admin"], $csrfPostado)) {
        http_response_code(400);
        exit("Solicitação inválida. Atualize a página e tente novamente.");
    }

    $acao = $_POST["acao"] ?? "";

    if ($acao === "cadastrar") {

        $nome = trim($_POST["nome"] ?? "");
        $descricao = trim($_POST["descricao"] ?? "");
        $preco = trim($_POST["preco"] ?? "");
        $estoque = trim($_POST["estoque"] ?? "");
        $url = trim($_POST["imagem_url"] ?? "");

        $preco_num = (float) str_replace(",", ".", $preco);
        $estoque_num = (int) $estoque;
        $imagem = "";

        if ($nome === "") {
            $erro = "Informe o nome do produto.";
        } elseif (mb_strlen($nome, "UTF-8") > 150) {
            $erro = "O nome deve ter até 150 caracteres.";
        } elseif (mb_strlen($descricao, "UTF-8") > 5000) {
            $erro = "A descrição deve ter até 5.000 caracteres.";
        } elseif ($preco_num <= 0) {
            $erro = "Informe um preço maior que zero.";
        } elseif ($estoque === "" || $estoque_num < 0) {
            $erro = "Informe um estoque válido (0 ou mais).";
        } elseif (!empty($_FILES["imagem_arquivo"]["name"])) {
            try {
                $imagem = salvarImagemEnviada($_FILES["imagem_arquivo"]);
            } catch (RuntimeException $e) {
                $erro = $e->getMessage();
            }
        } elseif ($url !== "") {

            if (preg_match('#^https?://#i', $url) && filter_var($url, FILTER_VALIDATE_URL)) {
                $imagem = $url;
            } else {
                $erro = "O link da imagem precisa começar com http:// ou https://.";
            }

        } else {
            $erro = "Envie uma imagem ou informe o link dela.";
        }

        if ($erro === "") {
            $stmt = $conexao->prepare(
                "INSERT INTO produtos (nome, descricao, preco, imagem, estoque) VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->bind_param("ssdsi", $nome, $descricao, $preco_num, $imagem, $estoque_num);
            $stmt->execute();
            $stmt->close();

            $_SESSION["flash"] = "Produto \"" . $nome . "\" cadastrado com sucesso!";
            header("Location: admin.php");
            exit;
        }
    }

    if ($acao === "editar") {
        $id = (int) ($_POST["id"] ?? 0);
        $nomeEditado = trim($_POST["nome"] ?? "");
        $descricaoEditada = trim($_POST["descricao"] ?? "");
        $precoEditado = str_replace(",", ".", trim($_POST["preco"] ?? ""));
        $estoqueEditado = trim($_POST["estoque"] ?? "");
        $urlEditada = trim($_POST["imagem_url"] ?? "");
        $precoNumero = (float) $precoEditado;
        $estoqueNumero = (int) $estoqueEditado;

        $stmt = $conexao->prepare("SELECT imagem FROM produtos WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $produtoAtual = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$produtoAtual) {
            $erro = "O produto que você tentou editar não foi encontrado.";
        } elseif ($nomeEditado === "" || mb_strlen($nomeEditado, "UTF-8") > 150) {
            $erro = "Informe um nome com até 150 caracteres.";
        } elseif (mb_strlen($descricaoEditada, "UTF-8") > 5000) {
            $erro = "A descrição deve ter até 5.000 caracteres.";
        } elseif ($precoNumero <= 0) {
            $erro = "Informe um preço maior que zero.";
        } elseif ($estoqueEditado === "" || $estoqueNumero < 0) {
            $erro = "Informe um estoque válido (0 ou mais).";
        } else {
            $imagemEditada = $produtoAtual["imagem"];

            if (!empty($_FILES["imagem_arquivo"]["name"])) {
                try {
                    $imagemEditada = salvarImagemEnviada($_FILES["imagem_arquivo"]);
                } catch (RuntimeException $e) {
                    $erro = $e->getMessage();
                }
            } elseif ($urlEditada !== "") {
                if (preg_match('#^https?://#i', $urlEditada) && filter_var($urlEditada, FILTER_VALIDATE_URL)) {
                    $imagemEditada = $urlEditada;
                } else {
                    $erro = "O link da imagem precisa começar com http:// ou https://.";
                }
            }

            if ($erro === "") {
                $stmt = $conexao->prepare(
                    "UPDATE produtos SET nome = ?, descricao = ?, preco = ?, estoque = ?, imagem = ? WHERE id = ?"
                );
                $stmt->bind_param(
                    "ssdisi",
                    $nomeEditado,
                    $descricaoEditada,
                    $precoNumero,
                    $estoqueNumero,
                    $imagemEditada,
                    $id
                );
                $stmt->execute();
                $stmt->close();

                if ($produtoAtual["imagem"] !== $imagemEditada
                    && preg_match('/^[a-f0-9]{16}\.(jpg|png|gif|webp)$/', $produtoAtual["imagem"])) {
                    $imagemAntiga = __DIR__ . "/imagens/" . $produtoAtual["imagem"];
                    if (is_file($imagemAntiga)) {
                        unlink($imagemAntiga);
                    }
                }

                $_SESSION["flash"] = "Produto \"" . $nomeEditado . "\" atualizado.";
                header("Location: admin.php");
                exit;
            }
        }
    }

    if ($acao === "estoque") {

        $id = (int) ($_POST["id"] ?? 0);
        $novo = (int) ($_POST["estoque"] ?? 0);

        if ($id > 0 && $novo >= 0) {
            $stmt = $conexao->prepare("UPDATE produtos SET estoque = ? WHERE id = ?");
            $stmt->bind_param("ii", $novo, $id);
            $stmt->execute();
            $stmt->close();

            $_SESSION["flash"] = "Estoque atualizado.";
        }

        header("Location: admin.php");
        exit;
    }

    if ($acao === "devolucao") {
        $id = (int) ($_POST["id"] ?? 0);
        $status = $_POST["status"] ?? "";

        if ($id < 1 || !in_array($status, ["aprovada", "recusada"], true)) {
            http_response_code(400);
            exit("Solicitação de devolução inválida.");
        }

        $stmt = $conexao->prepare(
            "UPDATE solicitacoes_devolucao SET status = ? WHERE id = ? AND status = 'solicitada'"
        );
        $stmt->bind_param("si", $status, $id);
        $stmt->execute();
        $atualizada = $stmt->affected_rows === 1;
        $stmt->close();

        $_SESSION["flash"] = $atualizada
            ? "Status da solicitação atualizado."
            : "A solicitação não existe ou já foi analisada.";
        header("Location: admin.php");
        exit;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && ($_POST["acao"] ?? "") === "remover") {

    $id = (int) ($_POST["id"] ?? 0);

    if ($id > 0) {
        $stmt = $conexao->prepare("SELECT nome, imagem FROM produtos WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $prod = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($prod) {
            $stmt = $conexao->prepare("DELETE FROM produtos WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();

            if (preg_match('/^[a-f0-9]{16}\.(jpg|png|gif|webp)$/', $prod["imagem"])) {
                $arquivo = __DIR__ . "/imagens/" . $prod["imagem"];
                if (is_file($arquivo)) {
                    unlink($arquivo);
                }
            }

            $_SESSION["flash"] = "Produto \"" . $prod["nome"] . "\" removido.";
        }
    }

    header("Location: admin.php");
    exit;
}

$produtos = $conexao->query("SELECT * FROM produtos ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);
$devolucoes = $conexao->query(
    "SELECT sd.id, sd.motivo, sd.status, sd.criado_em, ip.nome, ip.quantidade, p.id AS pedido_id, p.usuario
     FROM solicitacoes_devolucao sd
     JOIN itens_pedido ip ON ip.id = sd.item_pedido_id
     JOIN pedidos p ON p.id = ip.pedido_id
     WHERE sd.status = 'solicitada'
     ORDER BY sd.criado_em ASC"
)->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Floricultura Tulipa - Admin</title>

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

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }

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

        header nav { display: flex; gap: 25px; }

        header nav a {
            color: var(--claro);
            text-decoration: none;
            font-weight: 600;
            font-size: 1.1rem;
        }

        header nav a:hover { color: var(--amarelo); }

        .wrapper {
            width: 90%;
            max-width: 850px;
            background-color: var(--laranja);
            border: 4px solid var(--amarelo);
            border-radius: 12px;
            padding: 40px 25px;
            margin: 40px auto 0;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.4);
        }

        .wrapper:last-of-type { margin-bottom: 40px; }

        .wrapper h2 {
            font-family: 'Pacifico', cursive;
            font-size: 3rem;
            color: var(--claro);
            font-weight: normal;
            text-align: center;
            margin-bottom: 25px;
        }

        .erro-msg, .ok-msg {
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 600;
            text-align: center;
        }

        .erro-msg { background-color: #ff4d4d; color: #fff; }
        .ok-msg { background-color: var(--amarelo); color: var(--escuro); font-weight: 700; }

        form.cadastro { display: flex; flex-direction: column; gap: 15px; }

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
            font-size: 1.1rem;
            font-weight: 700;
            margin-right: 10px;
            white-space: nowrap;
        }

        .input-group input {
            background: transparent;
            border: none;
            outline: none;
            color: var(--claro);
            font-size: 1.1rem;
            width: 100%;
            font-weight: 600;
        }

        .input-group textarea {
            background: transparent;
            border: none;
            outline: none;
            color: var(--claro);
            font-size: 1rem;
            width: 100%;
            min-height: 100px;
            resize: vertical;
        }

        .input-group input::placeholder { color: rgba(255, 249, 205, 0.6); }
        .input-group textarea::placeholder { color: rgba(255, 249, 205, 0.6); }

        .input-group input[type="file"] { font-size: 0.95rem; }

        .input-group input[type="file"]::file-selector-button {
            background-color: var(--amarelo);
            color: var(--escuro);
            border: none;
            border-radius: 20px;
            padding: 6px 14px;
            font-weight: 700;
            cursor: pointer;
            margin-right: 10px;
        }

        .ou {
            text-align: center;
            color: var(--claro);
            font-weight: 600;
            margin: -5px 0;
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
            box-shadow: 0 4px 6px rgba(0,0,0,0.2);
            transition: all 0.2s ease;
        }

        .btn:hover { background-color: var(--claro); transform: translateY(-2px); }

        .btn-pequeno { padding: 6px 16px; font-size: 0.95rem; }

        .btn-remover { background-color: #ff4d4d; color: #fff; }
        .btn-remover:hover { background-color: #ff7a7a; }

        .lista { display: flex; flex-direction: column; gap: 12px; }

        .item {
            display: flex;
            align-items: center;
            gap: 15px;
            background-color: var(--escuro);
            border: 2px solid var(--amarelo);
            border-radius: 30px;
            padding: 10px 20px;
            color: var(--claro);
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.2);
        }

        .item img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid var(--amarelo);
            background-color: var(--claro);
            flex-shrink: 0;
        }

        .item-info { flex: 1; }
        .item-info h3 { font-size: 1.1rem; font-weight: 700; }
        .item-info .preco { color: var(--amarelo); font-weight: 700; }

        .tag-esgotado {
            display: inline-block;
            background-color: #ff4d4d;
            color: #fff;
            font-size: 0.75rem;
            font-weight: 700;
            padding: 2px 10px;
            border-radius: 12px;
            margin-left: 6px;
        }

        .form-estoque { display: flex; align-items: center; gap: 8px; }
        .editar-produto { flex-basis: 100%; }
        .editar-produto summary { cursor: pointer; list-style: none; }
        .produto-edit { display: flex; flex-direction: column; gap: 12px; margin-top: 12px; }
        .produto-edit .input-group { border-radius: 18px; }
        .produto-edit .imagem-atual { color: var(--claro); font-size: 0.9rem; }

        .form-estoque input {
            width: 80px;
            background: var(--laranja);
            border: 2px solid var(--amarelo);
            border-radius: 20px;
            color: var(--claro);
            font-weight: 700;
            font-size: 1rem;
            padding: 6px 12px;
            outline: none;
            text-align: center;
        }

        .vazio { text-align: center; color: var(--claro); font-weight: 600; }
        .devolucao-info { flex: 1; }
        .devolucao-acoes { display: flex; gap: 8px; }

        @media (max-width: 768px) {
            .input-group { flex-direction: column; align-items: stretch; border-radius: 20px; gap: 5px; }
            .item { flex-wrap: wrap; justify-content: center; text-align: center; border-radius: 20px; }
            .item-info { flex-basis: 100%; }
        }
    </style>
</head>

<body>

<header>
    <h1>Floricultura Tulipa</h1>
    <nav>
        <a href="index.php">Ver loja</a>
        <a href="logout.php">Sair</a>
    </nav>
</header>

<main class="wrapper">

    <h2>Novo produto</h2>

    <?php if ($erro != "") { ?>
        <div class="erro-msg"><?php echo htmlspecialchars($erro); ?></div>
    <?php } ?>

    <?php if ($msg != "") { ?>
        <div class="ok-msg"><?php echo htmlspecialchars($msg); ?></div>
    <?php } ?>

    <form method="POST" enctype="multipart/form-data" class="cadastro">
        <input type="hidden" name="csrf_admin" value="<?php echo htmlspecialchars($_SESSION["csrf_admin"]); ?>">
        <input type="hidden" name="acao" value="cadastrar">

        <div class="input-group">
            <label for="nome">Nome:</label>
            <input type="text" id="nome" name="nome" placeholder="Ex: Rosa vermelha"
                   value="<?php echo htmlspecialchars($nome); ?>" required>
        </div>

        <div class="input-group">
            <label for="descricao">Descrição:</label>
            <textarea id="descricao" name="descricao" maxlength="5000" placeholder="Detalhes e cuidados da flor"><?php echo htmlspecialchars($descricao); ?></textarea>
        </div>

        <div class="input-group">
            <label for="preco">Preço (R$):</label>
            <input type="number" id="preco" name="preco" step="0.01" min="0.01" placeholder="12.90"
                   value="<?php echo htmlspecialchars($preco); ?>" required>
        </div>

        <div class="input-group">
            <label for="estoque">Estoque:</label>
            <input type="number" id="estoque" name="estoque" min="0" step="1" placeholder="Quantidade disponível"
                   value="<?php echo htmlspecialchars($estoque); ?>" required>
        </div>

        <div class="input-group">
            <label for="imagem_arquivo">Imagem:</label>
            <input type="file" id="imagem_arquivo" name="imagem_arquivo" accept="image/*">
        </div>

        <div class="ou">— ou —</div>

        <div class="input-group">
            <label for="imagem_url">Link:</label>
            <input type="text" id="imagem_url" name="imagem_url" placeholder="https://..."
                   value="<?php echo htmlspecialchars($url); ?>">
        </div>

        <button type="submit" class="btn">Cadastrar produto</button>
    </form>

</main>

<main class="wrapper">
    <h2>Solicitações de devolução</h2>
    <?php if (empty($devolucoes)) { ?>
        <p class="vazio">Não há solicitações pendentes.</p>
    <?php } else { ?>
        <div class="lista">
            <?php foreach ($devolucoes as $devolucao) { ?>
                <section class="item">
                    <div class="devolucao-info">
                        <h3>Pedido nº <?php echo (int) $devolucao["pedido_id"]; ?> — <?php echo htmlspecialchars($devolucao["nome"]); ?></h3>
                        <p>Cliente: <?php echo htmlspecialchars($devolucao["usuario"]); ?> | Quantidade: <?php echo (int) $devolucao["quantidade"]; ?></p>
                        <p>Solicitado em: <?php echo htmlspecialchars($devolucao["criado_em"]); ?></p>
                        <p>Motivo: <?php echo nl2br(htmlspecialchars($devolucao["motivo"])); ?></p>
                    </div>
                    <div class="devolucao-acoes">
                        <?php foreach (["aprovada" => "Aprovar", "recusada" => "Recusar"] as $status => $rotulo) { ?>
                            <form method="POST">
                                <input type="hidden" name="csrf_admin" value="<?php echo htmlspecialchars($_SESSION["csrf_admin"]); ?>">
                                <input type="hidden" name="acao" value="devolucao">
                                <input type="hidden" name="id" value="<?php echo (int) $devolucao["id"]; ?>">
                                <input type="hidden" name="status" value="<?php echo htmlspecialchars($status); ?>">
                                <button type="submit" class="btn btn-pequeno"><?php echo htmlspecialchars($rotulo); ?></button>
                            </form>
                        <?php } ?>
                    </div>
                </section>
            <?php } ?>
        </div>
    <?php } ?>
</main>

<main class="wrapper">

    <h2>Produtos</h2>

    <?php if (empty($produtos)) { ?>
        <p class="vazio">Nenhum produto cadastrado ainda.</p>
    <?php } else { ?>

        <div class="lista">
            <?php foreach ($produtos as $p) { ?>
                <div class="item">
                    <img
                        src="<?php echo htmlspecialchars(preg_match('#^https?://#i', $p["imagem"]) ? $p["imagem"] : "imagens/" . $p["imagem"]); ?>"
                        alt="<?php echo htmlspecialchars($p["nome"]); ?>"
                    >

                    <div class="item-info">
                        <h3>
                            <?php echo htmlspecialchars($p["nome"]); ?>
                            <?php if ((int) $p["estoque"] <= 0) { ?>
                                <span class="tag-esgotado">Esgotado</span>
                            <?php } ?>
                        </h3>
                        <div class="preco">R$ <?php echo number_format($p["preco"], 2, ",", "."); ?></div>
                    </div>

                    <form method="POST" class="form-estoque">
                        <input type="hidden" name="csrf_admin" value="<?php echo htmlspecialchars($_SESSION["csrf_admin"]); ?>">
                        <input type="hidden" name="acao" value="estoque">
                        <input type="hidden" name="id" value="<?php echo (int) $p["id"]; ?>">
                        <input type="number" name="estoque" min="0" step="1" value="<?php echo (int) $p["estoque"]; ?>">
                        <button type="submit" class="btn btn-pequeno">Salvar</button>
                    </form>

                    <details class="editar-produto">
                        <summary class="btn btn-pequeno">Editar dados</summary>
                        <form method="POST" enctype="multipart/form-data" class="produto-edit">
                            <input type="hidden" name="csrf_admin" value="<?php echo htmlspecialchars($_SESSION["csrf_admin"]); ?>">
                            <input type="hidden" name="acao" value="editar">
                            <input type="hidden" name="id" value="<?php echo (int) $p["id"]; ?>">

                            <div class="input-group">
                                <label for="nome-<?php echo (int) $p["id"]; ?>">Nome:</label>
                                <input type="text" id="nome-<?php echo (int) $p["id"]; ?>" name="nome" maxlength="150" value="<?php echo htmlspecialchars($p["nome"]); ?>" required>
                            </div>

                            <div class="input-group">
                                <label for="descricao-<?php echo (int) $p["id"]; ?>">Descrição:</label>
                                <textarea id="descricao-<?php echo (int) $p["id"]; ?>" name="descricao" maxlength="5000"><?php echo htmlspecialchars($p["descricao"] ?? ""); ?></textarea>
                            </div>

                            <div class="input-group">
                                <label for="preco-<?php echo (int) $p["id"]; ?>">Preço (R$):</label>
                                <input type="number" id="preco-<?php echo (int) $p["id"]; ?>" name="preco" min="0.01" step="0.01" value="<?php echo htmlspecialchars($p["preco"]); ?>" required>
                            </div>

                            <div class="input-group">
                                <label for="estoque-<?php echo (int) $p["id"]; ?>">Estoque:</label>
                                <input type="number" id="estoque-<?php echo (int) $p["id"]; ?>" name="estoque" min="0" step="1" value="<?php echo (int) $p["estoque"]; ?>" required>
                            </div>

                            <p class="imagem-atual">Imagem atual: <?php echo htmlspecialchars(basename($p["imagem"])); ?>. Envie uma nova imagem ou informe um link para substituí-la.</p>

                            <div class="input-group">
                                <label for="imagem-arquivo-<?php echo (int) $p["id"]; ?>">Nova imagem:</label>
                                <input type="file" id="imagem-arquivo-<?php echo (int) $p["id"]; ?>" name="imagem_arquivo" accept="image/jpeg,image/png,image/gif,image/webp">
                            </div>

                            <div class="input-group">
                                <label for="imagem-url-<?php echo (int) $p["id"]; ?>">Link da imagem:</label>
                                <input type="url" id="imagem-url-<?php echo (int) $p["id"]; ?>" name="imagem_url" placeholder="https://...">
                            </div>

                            <button type="submit" class="btn btn-pequeno">Salvar alterações</button>
                        </form>
                    </details>

                    <form method="POST" onsubmit="return confirm('Remover &quot;<?php echo htmlspecialchars(addslashes($p["nome"]), ENT_QUOTES); ?>&quot; da loja?');">
                        <input type="hidden" name="csrf_admin" value="<?php echo htmlspecialchars($_SESSION["csrf_admin"]); ?>">
                        <input type="hidden" name="acao" value="remover">
                        <input type="hidden" name="id" value="<?php echo (int) $p["id"]; ?>">
                        <button type="submit" class="btn btn-pequeno btn-remover">Remover</button>
                    </form>
                </div>
            <?php } ?>
        </div>

    <?php } ?>

</main>

</body>
</html>
