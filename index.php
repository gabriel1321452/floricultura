<?php
session_start();

if (!isset($_SESSION["usuario"])) {
    header("Location: login.php");
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

$stmt = $conexao->prepare("SELECT id, is_admin FROM usuarios_login WHERE usuario = ?");
$stmt->bind_param("s", $_SESSION["usuario"]);
$stmt->execute();
$usuarioLoja = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$usuarioLoja) {
    session_destroy();
    header("Location: login.php");
    exit;
}

$_SESSION["user_id"] = (int) $usuarioLoja["id"];
$_SESSION["admin"] = (int) $usuarioLoja["is_admin"] === 1;

if (empty($_SESSION["csrf_index"])) {
    $_SESSION["csrf_index"] = bin2hex(random_bytes(32));
}

if (!empty($_SESSION["favoritos"])) {
    $stmtMigrarFavorito = $conexao->prepare(
        "INSERT IGNORE INTO favoritos (usuario_id, produto_id) SELECT ?, id FROM produtos WHERE id = ?"
    );
    foreach (array_keys($_SESSION["favoritos"]) as $favoritoAntigoId) {
        $favoritoAntigoId = (int) $favoritoAntigoId;
        $stmtMigrarFavorito->bind_param("ii", $_SESSION["user_id"], $favoritoAntigoId);
        $stmtMigrarFavorito->execute();
    }
    $stmtMigrarFavorito->close();
    unset($_SESSION["favoritos"]);
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["acao"] ?? "") === "favoritar") {
    $idPostado = $_POST["id"] ?? "";
    $csrfPostado = $_POST["csrf_index"] ?? "";

    if (!is_string($idPostado)
        || !ctype_digit($idPostado)
        || !is_string($csrfPostado)
        || !hash_equals($_SESSION["csrf_index"], $csrfPostado)) {
        http_response_code(400);
        exit("Solicitação inválida. Atualize a página e tente novamente.");
    }

    $id = (int) $idPostado;
    $stmt = $conexao->prepare("SELECT 1 FROM favoritos WHERE usuario_id = ? AND produto_id = ?");
    $stmt->bind_param("ii", $_SESSION["user_id"], $id);
    $stmt->execute();
        $favoritoExiste = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        if ($favoritoExiste) {
            $stmt = $conexao->prepare("DELETE FROM favoritos WHERE usuario_id = ? AND produto_id = ?");
        } else {
            $stmt = $conexao->prepare(
                "INSERT INTO favoritos (usuario_id, produto_id) SELECT ?, id FROM produtos WHERE id = ?"
            );
        }
        $stmt->bind_param("ii", $_SESSION["user_id"], $id);
        $stmt->execute();
        $stmt->close();

        header("Location: index.php");
        exit;
    }

    $resultado = $conexao->query("SELECT * FROM produtos WHERE estoque > 0");

    $carrinho = $_SESSION["carrinho"] ?? [];
    $itensCarrinho = [];
    $totalCarrinho = 0;
    $qtdCarrinho = array_sum($carrinho);
    $favoritos = [];

    $stmt = $conexao->prepare(
    "SELECT p.id, p.nome, p.preco, p.imagem FROM favoritos f JOIN produtos p ON p.id = f.produto_id WHERE f.usuario_id = ? ORDER BY f.criado_em DESC"
);
$stmt->bind_param("i", $_SESSION["user_id"]);
$stmt->execute();
$resFavoritos = $stmt->get_result();
while ($fav = $resFavoritos->fetch_assoc()) {
    $favoritos[$fav["id"]] = $fav;
}
$stmt->close();

if (!empty($carrinho)) {
    $ids = implode(",", array_map("intval", array_keys($carrinho)));
    $resCarrinho = $conexao->query("SELECT id, nome, preco FROM produtos WHERE id IN ($ids)");
    while ($p = $resCarrinho->fetch_assoc()) {
        $qtd = (int) ($carrinho[$p["id"]] ?? 0);
        $p["qtd"] = $qtd;
        $totalCarrinho += $qtd * $p["preco"];
        $itensCarrinho[] = $p;
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Floricultura Tulipa</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Pacifico&family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">

    <script>
        try {
            if (localStorage.getItem("tema") === "claro") {
                document.documentElement.setAttribute("data-tema", "claro");
            }
        } catch (e) {}
    </script>

    <style>
        :root {
            --claro: #fff9cd;
            --amarelo: #ffc04b;
            --laranja: #d56a42;
            --verde: #58610c;
            --escuro: #703227;

            --bg: #703227;
            --texto: #fff9cd;
            --destaque: #ffc04b;
            --painel: #5a2820;
            --painel-borda: #ffc04b;
            --campo-bg: #fff9cd;
            --campo-texto: #703227;
        }

        [data-tema="claro"] {
            --bg: #fff9cd;
            --texto: #703227;
            --destaque: #d56a42;
            --painel: #fffdf0;
            --painel-borda: #d56a42;
            --campo-bg: #ffffff;
            --campo-texto: #703227;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background-color: var(--bg);
            color: var(--texto);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        header {
            background-color: var(--laranja);
            border-bottom: 4px solid var(--amarelo);
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
        }

        .header-esq,
        .header-dir {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        header h1 {
            font-family: 'Pacifico', cursive;
            font-size: 2rem;
            color: var(--claro);
            font-weight: normal;
        }

        header a,
        .btn-menu {
            color: var(--escuro);
            background-color: var(--amarelo);
            text-decoration: none;
            padding: 8px 18px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 1rem;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        header a:hover,
        .btn-menu:hover {
            background-color: var(--claro);
            transform: translateY(-2px);
        }

        .overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.55);
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
            z-index: 90;
        }

        .overlay.aberto {
            opacity: 1;
            visibility: visible;
        }

        .menu-lateral {
            position: fixed;
            top: 0;
            left: 0;
            height: 100%;
            width: 320px;
            max-width: 88vw;
            background-color: var(--painel);
            color: var(--texto);
            border-right: 4px solid var(--painel-borda);
            box-shadow: 6px 0 20px rgba(0, 0, 0, 0.4);
            transform: translateX(-105%);
            transition: transform 0.3s ease, background-color 0.3s ease;
            z-index: 100;
            display: flex;
            flex-direction: column;
        }

        .menu-lateral.aberto {
            transform: translateX(0);
        }

        .menu-topo {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 18px 20px;
            background-color: var(--laranja);
            border-bottom: 4px solid var(--amarelo);
        }

        .menu-topo h2 {
            font-family: 'Pacifico', cursive;
            font-weight: normal;
            font-size: 1.5rem;
            color: var(--claro);
        }

        .btn-fechar {
            background: var(--amarelo);
            color: var(--escuro);
            border: none;
            width: 34px;
            height: 34px;
            border-radius: 50%;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
        }

        .menu-conteudo {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .menu-bloco h3 {
            font-size: 1rem;
            color: var(--destaque);
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .campo {
            display: flex;
            flex-direction: column;
            gap: 4px;
            margin-bottom: 10px;
            font-size: 0.85rem;
        }

        .campo input,
        .campo select {
            padding: 9px 12px;
            border-radius: 10px;
            border: 2px solid var(--amarelo);
            background: var(--campo-bg);
            color: var(--campo-texto);
            font-size: 0.95rem;
            width: 100%;
        }

        .campo-linha {
            display: flex;
            gap: 10px;
        }

        .btn-limpar {
            background: transparent;
            color: var(--texto);
            border: 2px solid var(--destaque);
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
        }

        .btn-limpar:hover {
            background: var(--destaque);
            color: var(--painel);
        }

        .carrinho-lista {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 12px;
        }

        .carrinho-lista li {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            font-size: 0.9rem;
            padding-bottom: 6px;
            border-bottom: 1px dashed var(--painel-borda);
        }

        .favorito-lista {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 12px;
        }

        .favorito-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 0;
            border-bottom: 1px dashed var(--painel-borda);
        }

        .favorito-item img {
            width: 42px;
            height: 42px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid var(--amarelo);
            flex-shrink: 0;
        }

        .favorito-info {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 2px;
            min-width: 0;
        }

        .favorito-info span {
            font-size: 0.9rem;
            font-weight: 700;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .favorito-info small {
            color: var(--texto);
            opacity: 0.8;
        }

        .form-favorito-inline {
            display: flex;
            align-items: center;
        }

        .btn-remover-favorito {
            background: transparent;
            border: 1px solid var(--amarelo);
            color: var(--texto);
            width: 28px;
            height: 28px;
            border-radius: 50%;
            cursor: pointer;
            font-weight: 700;
        }

        .btn-remover-favorito:hover {
            background: var(--amarelo);
            color: var(--escuro);
        }

        .carrinho-vazio,
        .favorito-vazio {
            font-size: 0.9rem;
            opacity: 0.8;
            margin-bottom: 12px;
        }

        .carrinho-total {
            display: flex;
            justify-content: space-between;
            font-weight: 700;
            margin-bottom: 12px;
        }

        .btn-ver-carrinho {
            display: block;
            text-align: center;
            text-decoration: none;
            background: var(--amarelo);
            color: var(--escuro);
            padding: 10px;
            border-radius: 25px;
            font-weight: 700;
        }

        .btn-ver-carrinho:hover {
            background: var(--claro);
        }

        .menu-rodape {
            padding: 16px 20px;
            border-top: 2px solid var(--painel-borda);
        }

        .btn-tema {
            width: 100%;
            background: var(--amarelo);
            color: var(--escuro);
            border: none;
            padding: 12px;
            border-radius: 25px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: transform 0.1s ease, background-color 0.2s ease;
        }

        .btn-tema:hover {
            background: var(--claro);
        }

        .btn-tema:active {
            transform: scale(0.98);
        }

        main {
            text-align: center;
            margin: 30px 20px 10px 20px;
        }

        main h2 {
            font-size: 2rem;
            color: var(--destaque);
            margin-bottom: 5px;
        }

        main p {
            color: var(--texto);
            font-size: 1.1rem;
            opacity: 0.9;
        }

        section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            padding: 30px;
            max-width: 1200px;
            margin: 0 auto;
            width: 100%;
        }

        section > div {
            background-color: var(--laranja);
            color: var(--claro);
            border: 3px solid var(--amarelo);
            border-radius: 15px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            box-shadow: 0 6px 12px rgba(0,0,0,0.25);
            transition: transform 0.2s ease;
        }

        section > div:hover {
            transform: translateY(-5px);
        }

        section img {
            width: 100%;
            height: 180px;
            object-fit: cover;
            border-radius: 10px;
            border: 2px solid var(--amarelo);
            background-color: var(--claro);
            margin-bottom: 15px;
        }

        section h3 {
            color: var(--claro);
            font-size: 1.3rem;
            margin-bottom: 8px;
        }

        section div > div {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--amarelo);
            margin-bottom: 15px;
        }

        section button {
            background-color: var(--amarelo);
            color: var(--escuro);
            border: none;
            padding: 10px 30px;
            font-size: 1.1rem;
            font-weight: 700;
            border-radius: 25px;
            cursor: pointer;
            transition: background-color 0.2s ease, transform 0.1s ease;
            box-shadow: 0 3px 6px rgba(0,0,0,0.2);
        }

        section button:hover {
            background-color: var(--claro);
            transform: scale(1.05);
        }

        section button:active {
            transform: scale(0.98);
        }

        .btn-detalhes {
            display: block;
            width: 100%;
            margin: 0 0 10px;
            padding: 9px 16px;
            border: 2px solid var(--amarelo);
            border-radius: 22px;
            color: var(--claro);
            text-decoration: none;
            font-weight: 700;
        }

        .btn-detalhes:hover {
            background: var(--amarelo);
            color: var(--escuro);
        }

        .form-favorito {
            width: 100%;
            margin-top: 12px;
        }

        .btn-favorito {
            width: 100%;
            background-color: transparent;
            border: 2px solid var(--amarelo);
            color: var(--claro);
            padding: 10px 15px;
            font-size: 0.95rem;
            font-weight: 700;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-favorito.ativo {
            background-color: var(--amarelo);
            color: var(--escuro);
        }

        .btn-favorito:hover {
            background-color: var(--claro);
            color: var(--escuro);
        }

        .sem-resultado {
            text-align: center;
            font-size: 1.1rem;
            padding: 20px;
            display: none;
        }

        @media (max-width: 520px) {
            header { padding: 12px 14px; }
            header h1 { font-size: 1.4rem; }
            .header-esq, .header-dir { gap: 8px; }
            header a, .btn-menu { padding: 7px 12px; font-size: 0.9rem; }
        }
    </style>
</head>

<body>

<header>
    <div class="header-esq">
        <button type="button" class="btn-menu" id="abrirMenu" aria-label="Abrir menu de filtros">☰ Filtros</button>
        <h1>Floricultura Tulipa</h1>
    </div>
    <div class="header-dir">
        <?php if (!empty($_SESSION["admin"])) { ?>
            <a href="admin.php">Admin</a>
        <?php } ?>
        <a href="pedidos.php">Meus pedidos</a>
        <a href="carrinho.php">Carrinho (<?php echo $qtdCarrinho; ?>)</a>
        <a href="logout.php">Sair</a>
    </div>
</header>

<div class="overlay" id="overlay"></div>

<aside class="menu-lateral" id="menuLateral" aria-hidden="true">
    <div class="menu-topo">
        <h2>Menu</h2>
        <button type="button" class="btn-fechar" id="fecharMenu" aria-label="Fechar menu">✕</button>
    </div>

    <div class="menu-conteudo">
        <div class="menu-bloco">
            <h3>Filtros</h3>

            <label class="campo">
                Buscar por nome
                <input type="text" id="filtroBusca" placeholder="Ex: rosa">
            </label>

            <label class="campo">
                Ordenar por
                <select id="filtroOrdem">
                    <option value="padrao">Padrão</option>
                    <option value="az">Nome (A → Z)</option>
                    <option value="za">Nome (Z → A)</option>
                    <option value="menor">Preço (menor → maior)</option>
                    <option value="maior">Preço (maior → menor)</option>
                </select>
            </label>

            <div class="campo-linha">
                <label class="campo">
                    Preço mín. (R$)
                    <input type="number" id="filtroMin" min="0" step="0.01" placeholder="0">
                </label>
                <label class="campo">
                    Preço máx. (R$)
                    <input type="number" id="filtroMax" min="0" step="0.01" placeholder="∞">
                </label>
            </div>

            <button type="button" class="btn-limpar" id="limparFiltros">Limpar filtros</button>
        </div>

        <div class="menu-bloco">
            <h3>Favoritos (<?php echo count($favoritos); ?>)</h3>

            <?php if (empty($favoritos)) { ?>
                <p class="favorito-vazio">Você ainda não marcou nenhum item.</p>
            <?php } else { ?>
                <ul class="favorito-lista">
                    <?php foreach ($favoritos as $id => $item) { ?>
                        <li class="favorito-item">
                            <img
                                src="<?php echo htmlspecialchars(preg_match('#^https?://#i', $item["imagem"]) ? $item["imagem"] : "imagens/" . $item["imagem"]); ?>"
                                alt="<?php echo htmlspecialchars($item["nome"]); ?>"
                            >
                            <div class="favorito-info">
                                <span><?php echo htmlspecialchars($item["nome"]); ?></span>
                                <small>R$ <?php echo number_format($item["preco"], 2, ",", "."); ?></small>
                            </div>
                            <form method="POST" action="index.php" class="form-favorito-inline">
                                <input type="hidden" name="csrf_index" value="<?php echo htmlspecialchars($_SESSION["csrf_index"]); ?>">
                                <input type="hidden" name="acao" value="favoritar">
                                <input type="hidden" name="id" value="<?php echo (int) $id; ?>">
                                <button type="submit" class="btn-remover-favorito" aria-label="Remover dos favoritos">✕</button>
                            </form>
                        </li>
                    <?php } ?>
                </ul>
            <?php } ?>
        </div>

        <div class="menu-bloco">
            <h3>Carrinho (<?php echo $qtdCarrinho; ?>)</h3>

            <?php if (empty($itensCarrinho)) { ?>
                <p class="carrinho-vazio">Seu carrinho está vazio.</p>
            <?php } else { ?>
                <ul class="carrinho-lista">
                    <?php foreach ($itensCarrinho as $item) { ?>
                        <li>
                            <span><?php echo (int) $item["qtd"]; ?>x <?php echo htmlspecialchars($item["nome"]); ?></span>
                            <span>R$ <?php echo number_format($item["qtd"] * $item["preco"], 2, ",", "."); ?></span>
                        </li>
                    <?php } ?>
                </ul>
                <div class="carrinho-total">
                    <span>Total</span>
                    <span>R$ <?php echo number_format($totalCarrinho, 2, ",", "."); ?></span>
                </div>
            <?php } ?>

            <a href="carrinho.php" class="btn-ver-carrinho">Ver carrinho</a>
        </div>
    </div>

    <div class="menu-rodape">
        <button type="button" class="btn-tema" id="btnTema">Modo escuro</button>
    </div>
</aside>

<main>
    <h2>Olá, <?php echo htmlspecialchars($_SESSION["usuario"]); ?>!</h2>
    <p>Confira nossas flores em destaque:</p>
</main>

<section id="listaProdutos">
    <?php while ($produto = $resultado->fetch_assoc()) { ?>
        <div
            class="produto"
            data-nome="<?php echo htmlspecialchars(mb_strtolower($produto["nome"], "UTF-8")); ?>"
            data-preco="<?php echo (float) $produto["preco"]; ?>"
        >
            <img
                src="<?php echo htmlspecialchars(preg_match('#^https?://#i', $produto["imagem"]) ? $produto["imagem"] : "imagens/" . $produto["imagem"]); ?>"
                alt="<?php echo htmlspecialchars($produto["nome"]); ?>"
            >

            <div>
                <h3>
                    <?php echo htmlspecialchars($produto["nome"]); ?>
                </h3>

                <div>
                    R$ <?php echo number_format($produto["preco"], 2, ",", "."); ?>
                </div>

                <a class="btn-detalhes" href="produto.php?id=<?php echo (int) $produto["id"]; ?>">Ver detalhes</a>

                <form method="POST" action="carrinho.php">
                    <input type="hidden" name="acao" value="adicionar">
                    <input type="hidden" name="id" value="<?php echo (int) $produto["id"]; ?>">
                    <button type="submit">
                        Comprar
                    </button>
                </form>

                <form method="POST" action="index.php" class="form-favorito">
                    <input type="hidden" name="csrf_index" value="<?php echo htmlspecialchars($_SESSION["csrf_index"]); ?>">
                    <input type="hidden" name="acao" value="favoritar">
                    <input type="hidden" name="id" value="<?php echo (int) $produto["id"]; ?>">
                    <button type="submit" class="btn-favorito <?php echo isset($favoritos[$produto["id"]]) ? "ativo" : ""; ?>">
                        <?php echo isset($favoritos[$produto["id"]]) ? "★ Favoritado" : "☆ Favoritar"; ?>
                    </button>
                </form>
            </div>
        </div>
    <?php } ?>
</section>

<p class="sem-resultado" id="semResultado">Nenhuma flor encontrada com esses filtros</p>

<script>
    const menu = document.getElementById("menuLateral");
    const overlay = document.getElementById("overlay");

    function abrirMenu() {
        menu.classList.add("aberto");
        overlay.classList.add("aberto");
        menu.setAttribute("aria-hidden", "false");
    }

    function fecharMenu() {
        menu.classList.remove("aberto");
        overlay.classList.remove("aberto");
        menu.setAttribute("aria-hidden", "true");
    }

    document.getElementById("abrirMenu").addEventListener("click", abrirMenu);
    document.getElementById("fecharMenu").addEventListener("click", fecharMenu);
    overlay.addEventListener("click", fecharMenu);
    document.addEventListener("keydown", (e) => {
        if (e.key === "Escape") fecharMenu();
    });

    const lista = document.getElementById("listaProdutos");
    const cards = Array.from(lista.querySelectorAll(".produto"));
    const ordemOriginal = cards.slice();
    const semResultado = document.getElementById("semResultado");

    const fBusca = document.getElementById("filtroBusca");
    const fOrdem = document.getElementById("filtroOrdem");
    const fMin = document.getElementById("filtroMin");
    const fMax = document.getElementById("filtroMax");

    function aplicarFiltros() {
        const busca = fBusca.value.trim().toLowerCase();
        const min = fMin.value === "" ? 0 : parseFloat(fMin.value);
        const max = fMax.value === "" ? Infinity : parseFloat(fMax.value);

        let ordenados = ordemOriginal.slice();
        switch (fOrdem.value) {
            case "az":
                ordenados.sort((a, b) => a.dataset.nome.localeCompare(b.dataset.nome, "pt-BR"));
                break;
            case "za":
                ordenados.sort((a, b) => b.dataset.nome.localeCompare(a.dataset.nome, "pt-BR"));
                break;
            case "menor":
                ordenados.sort((a, b) => a.dataset.preco - b.dataset.preco);
                break;
            case "maior":
                ordenados.sort((a, b) => b.dataset.preco - a.dataset.preco);
                break;
        }

        let visiveis = 0;
        ordenados.forEach((card) => {
            const preco = parseFloat(card.dataset.preco);
            const passa =
                card.dataset.nome.includes(busca) &&
                preco >= min &&
                preco <= max;

            card.style.display = passa ? "" : "none";
            if (passa) visiveis++;
            lista.appendChild(card);
        });

        semResultado.style.display = visiveis === 0 ? "block" : "none";
    }

    [fBusca, fOrdem, fMin, fMax].forEach((el) => {
        el.addEventListener("input", aplicarFiltros);
    });

    document.getElementById("limparFiltros").addEventListener("click", () => {
        fBusca.value = "";
        fOrdem.value = "padrao";
        fMin.value = "";
        fMax.value = "";
        aplicarFiltros();
    });

    const btnTema = document.getElementById("btnTema");
    const html = document.documentElement;

    function atualizarTextoBotao() {
        const claro = html.getAttribute("data-tema") === "claro";
        btnTema.textContent = claro ? "Modo escuro" : "Modo claro";
    }

    btnTema.addEventListener("click", () => {
        const claro = html.getAttribute("data-tema") === "claro";
        if (claro) {
            html.removeAttribute("data-tema");
        } else {
            html.setAttribute("data-tema", "claro");
        }
        try {
            localStorage.setItem("tema", claro ? "escuro" : "claro");
        } catch (e) {}
        atualizarTextoBotao();
    });

    atualizarTextoBotao();
</script>

</body>
</html>
