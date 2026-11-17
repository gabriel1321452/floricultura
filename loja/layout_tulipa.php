<?php
/** Cabeçalho/estrutura comum das páginas novas (vendas.php e perfil.php). */

function tulipa_h($texto): string
{
    return htmlspecialchars((string) $texto, ENT_QUOTES, "UTF-8");
}

function tulipa_cabeca(string $titulo, string $classeBody = ""): void
{
    ?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo tulipa_h($titulo); ?> - Floricultura Tulipa</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bellota+Text:ital,wght@0,400;0,700;1,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="estilo_tulipa.css">
    <script>
        try {
            if (localStorage.getItem("tema") === "claro") {
                document.documentElement.setAttribute("data-tema", "claro");
            }
        } catch (e) {}
    </script>
</head>

<body class="<?php echo tulipa_h($classeBody); ?>">
<?php
}

function tulipa_topo(bool $ehAdmin): void
{
    ?>
<header class="topo">
    <a class="marca" href="index.php">
        <svg viewBox="0 0 64 64" aria-hidden="true">
            <path d="M18 12 L26 21 L32 8 L38 21 L46 12 C49 30 43 43 32 43 C21 43 15 30 18 12Z" fill="#ffc04b"/>
            <rect x="30" y="40" width="4" height="16" fill="#58610c"/>
            <path d="M32 56 C20 56 13 50 11 43 C22 43 30 47 32 56Z" fill="#58610c"/>
            <path d="M32 56 C44 56 51 50 53 43 C42 43 34 47 32 56Z" fill="#58610c"/>
        </svg>
        <span>Floricultura Tulipa</span>
    </a>

    <div class="topo-dir">
        <a class="usuario" href="perfil.php"><?php echo tulipa_h($_SESSION["usuario"] ?? "Usuário"); ?></a>
        <button type="button" class="btn-hamb" id="btnMenu" aria-label="Abrir menu" aria-expanded="false">
            <svg viewBox="0 0 48 48" aria-hidden="true" fill="#ffc04b">
                <circle cx="7" cy="10" r="5"/><rect x="16" y="5" width="30" height="10" rx="5"/>
                <circle cx="7" cy="24" r="5"/><rect x="16" y="19" width="30" height="10" rx="5"/>
                <circle cx="7" cy="38" r="5"/><rect x="16" y="33" width="30" height="10" rx="5"/>
            </svg>
        </button>
        <nav class="menu-drop" id="menuDrop">
            <a href="index.php">Loja</a>
            <a href="perfil.php">Meu perfil</a>
            <a href="pedidos.php">Meus pedidos</a>
            <a href="carrinho.php">Carrinho</a>
            <?php if ($ehAdmin) { ?>
                <a href="admin.php">Admin</a>
                <a href="vendas.php">Total de vendas</a>
            <?php } ?>
            <a class="sair" href="logout.php">Sair</a>
        </nav>
    </div>
</header>
<script>
    (function () {
        var btn = document.getElementById("btnMenu");
        var menu = document.getElementById("menuDrop");
        btn.addEventListener("click", function (e) {
            e.stopPropagation();
            var aberto = menu.classList.toggle("aberto");
            btn.setAttribute("aria-expanded", aberto ? "true" : "false");
        });
        document.addEventListener("click", function () { menu.classList.remove("aberto"); });
        document.addEventListener("keydown", function (e) { if (e.key === "Escape") menu.classList.remove("aberto"); });
    })();
</script>
<?php
}
