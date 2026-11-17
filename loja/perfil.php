<?php
session_start();

if (empty($_SESSION["usuario"])) {
    header("Location: login.php");
    exit;
}

require "conexao.php";
require_once "garantir_admin.php";
require_once "layout_tulipa.php";

garantirAdmin($conexao);

/* Cria as colunas de e-mail e foto automaticamente, se ainda não existirem. */
function garantirColuna(mysqli $conexao, string $coluna, string $definicao): void
{
    $stmt = $conexao->prepare(
        "SELECT COUNT(*) FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = 'usuarios_login' AND column_name = ?"
    );
    $stmt->bind_param("s", $coluna);
    $stmt->execute();
    $stmt->bind_result($existe);
    $stmt->fetch();
    $stmt->close();

    if ((int) $existe === 0) {
        $conexao->query("ALTER TABLE usuarios_login ADD COLUMN `$coluna` $definicao");
    }
}
garantirColuna($conexao, "email", "VARCHAR(150) NULL");
garantirColuna($conexao, "foto", "VARCHAR(255) NULL");

function salvarFotoPerfil(array $arquivo): string
{
    $tipos = [IMAGETYPE_JPEG => "jpg", IMAGETYPE_PNG => "png", IMAGETYPE_GIF => "gif", IMAGETYPE_WEBP => "webp"];

    if (($arquivo["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException("Falha ao enviar a foto. Tente novamente.");
    }
    if (($arquivo["size"] ?? 0) > 5 * 1024 * 1024) {
        throw new RuntimeException("A foto deve ter no máximo 5 MB.");
    }
    $info = @getimagesize($arquivo["tmp_name"]);
    if (!$info || !isset($tipos[$info[2]])) {
        throw new RuntimeException("Envie uma imagem JPG, PNG, GIF ou WEBP.");
    }

    $pasta = __DIR__ . "/imagens/perfis";
    if (!is_dir($pasta) && !mkdir($pasta, 0755, true) && !is_dir($pasta)) {
        throw new RuntimeException("Não foi possível criar a pasta das fotos.");
    }

    $nome = bin2hex(random_bytes(8)) . "." . $tipos[$info[2]];
    if (!move_uploaded_file($arquivo["tmp_name"], $pasta . "/" . $nome)) {
        throw new RuntimeException("Não foi possível salvar a foto.");
    }
    return "perfis/" . $nome;
}

function apagarFotoAntiga(?string $foto): void
{
    if ($foto && preg_match('#^perfis/[a-f0-9]{16}\.(jpg|png|gif|webp)$#', $foto)) {
        $caminho = __DIR__ . "/imagens/" . $foto;
        if (is_file($caminho)) {
            unlink($caminho);
        }
    }
}

$stmt = $conexao->prepare("SELECT id, usuario, email, foto, is_admin FROM usuarios_login WHERE usuario = ?");
$stmt->bind_param("s", $_SESSION["usuario"]);
$stmt->execute();
$u = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$u) {
    session_destroy();
    header("Location: login.php");
    exit;
}

$_SESSION["user_id"] = (int) $u["id"];
$_SESSION["admin"] = (int) $u["is_admin"] === 1;

if (empty($_SESSION["csrf_perfil"])) {
    $_SESSION["csrf_perfil"] = bin2hex(random_bytes(32));
}

$ok = $_SESSION["flash_perfil"] ?? "";
unset($_SESSION["flash_perfil"]);
$erro = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $csrf = $_POST["csrf_perfil"] ?? "";
    if (!is_string($csrf) || !hash_equals($_SESSION["csrf_perfil"], $csrf)) {
        http_response_code(400);
        exit("Solicitação inválida. Atualize a página e tente novamente.");
    }

    $acao = $_POST["acao"] ?? "";

    if ($acao === "foto") {
        try {
            $novaFoto = salvarFotoPerfil($_FILES["foto"] ?? []);
            $stmt = $conexao->prepare("UPDATE usuarios_login SET foto = ? WHERE id = ?");
            $stmt->bind_param("si", $novaFoto, $u["id"]);
            $stmt->execute();
            $stmt->close();
            apagarFotoAntiga($u["foto"]);
            $_SESSION["flash_perfil"] = "Foto atualizada!";
            header("Location: perfil.php");
            exit;
        } catch (RuntimeException $e) {
            $erro = $e->getMessage();
        }
    }

    if ($acao === "remover_foto") {
        $stmt = $conexao->prepare("UPDATE usuarios_login SET foto = NULL WHERE id = ?");
        $stmt->bind_param("i", $u["id"]);
        $stmt->execute();
        $stmt->close();
        apagarFotoAntiga($u["foto"]);
        $_SESSION["flash_perfil"] = "Foto removida.";
        header("Location: perfil.php");
        exit;
    }

    if ($acao === "email") {
        $email = trim($_POST["email"] ?? "");
        if ($email !== "" && (mb_strlen($email, "UTF-8") > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL))) {
            $erro = "Informe um e-mail válido.";
        } else {
            $valor = $email === "" ? null : $email;
            $stmt = $conexao->prepare("UPDATE usuarios_login SET email = ? WHERE id = ?");
            $stmt->bind_param("si", $valor, $u["id"]);
            $stmt->execute();
            $stmt->close();
            $_SESSION["flash_perfil"] = "E-mail atualizado!";
            header("Location: perfil.php");
            exit;
        }
    }
}

$stmt = $conexao->prepare(
    "SELECT p.id, p.nome, p.preco, p.imagem
     FROM favoritos f JOIN produtos p ON p.id = f.produto_id
     WHERE f.usuario_id = ? ORDER BY f.criado_em DESC"
);
$stmt->bind_param("i", $u["id"]);
$stmt->execute();
$favoritos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$fotoUrl = $u["foto"] ? "imagens/" . $u["foto"] : "";
$inicial = mb_strtoupper(mb_substr($u["usuario"], 0, 1, "UTF-8"), "UTF-8");

tulipa_cabeca("Perfil");
tulipa_topo(!empty($_SESSION["admin"]));
?>

<main class="wrapper">
    <h1 class="titulo">Perfil</h1>

    <?php if ($erro !== "") { ?><div class="aviso erro"><?php echo tulipa_h($erro); ?></div><?php } ?>
    <?php if ($ok !== "") { ?><div class="aviso ok"><?php echo tulipa_h($ok); ?></div><?php } ?>

    <div class="perfil-card">

        <div class="perfil-topo">
            <div class="avatar">
                <?php if ($fotoUrl !== "") { ?>
                    <img src="<?php echo tulipa_h($fotoUrl); ?>" alt="Foto de <?php echo tulipa_h($u["usuario"]); ?>">
                <?php } else { ?>
                    <?php echo tulipa_h($inicial); ?>
                <?php } ?>
            </div>

            <div class="perfil-dados">
                <div class="perfil-nome"><?php echo tulipa_h($u["usuario"]); ?></div>
                <div class="perfil-email"><?php echo $u["email"] ? tulipa_h($u["email"]) : "Sem e-mail cadastrado"; ?></div>

                <form method="POST" enctype="multipart/form-data" id="formFoto">
                    <input type="hidden" name="csrf_perfil" value="<?php echo tulipa_h($_SESSION["csrf_perfil"]); ?>">
                    <input type="hidden" name="acao" value="foto">
                    <label class="btn btn-pequeno" for="inputFoto">Alterar foto</label>
                    <input type="file" id="inputFoto" name="foto" accept="image/jpeg,image/png,image/gif,image/webp" hidden
                           onchange="document.getElementById('formFoto').submit()">
                </form>

                <?php if ($fotoUrl !== "") { ?>
                    <form method="POST">
                        <input type="hidden" name="csrf_perfil" value="<?php echo tulipa_h($_SESSION["csrf_perfil"]); ?>">
                        <input type="hidden" name="acao" value="remover_foto">
                        <button type="submit" class="link-mini">remover foto</button>
                    </form>
                <?php } ?>
            </div>
        </div>

        <details class="email-box">
            <summary class="btn btn-pequeno">Editar e-mail</summary>
            <form method="POST" class="form-email">
                <input type="hidden" name="csrf_perfil" value="<?php echo tulipa_h($_SESSION["csrf_perfil"]); ?>">
                <input type="hidden" name="acao" value="email">
                <div class="input-group">
                    <input type="email" name="email" maxlength="150" placeholder="seu@email.com"
                           value="<?php echo tulipa_h($u["email"] ?? ""); ?>" aria-label="E-mail">
                </div>
                <button type="submit" class="btn btn-pequeno">Salvar</button>
            </form>
        </details>

        <div class="acoes">
            <a class="btn" href="pedidos.php">Meus pedidos</a>
            <a class="btn" href="#favoritos">Meus favoritos</a>

            <div class="input-group tema-linha">
                <span class="rotulo">Tema:</span>
                <span class="estado" id="temaTexto">Escuro</span>
                <button type="button" class="switch" id="switchTema" role="switch" aria-checked="false" aria-label="Alternar tema claro ou escuro">
                    <span class="bolinha"></span>
                </button>
            </div>

            <a class="btn btn-sair" href="logout.php">Sair da conta</a>
        </div>
    </div>

    <section class="favoritos" id="favoritos">
        <h3>Meus favoritos</h3>
        <?php if (empty($favoritos)) { ?>
            <p class="fav-vazio">Você ainda não favoritou nenhuma flor.</p>
        <?php } else { ?>
            <div class="fav-lista">
                <?php foreach ($favoritos as $f) {
                    $img = preg_match('#^https?://#i', $f["imagem"]) ? $f["imagem"] : "imagens/" . $f["imagem"]; ?>
                    <a class="fav-item" href="produto.php?id=<?php echo (int) $f["id"]; ?>">
                        <img src="<?php echo tulipa_h($img); ?>" alt="">
                        <div>
                            <strong><?php echo tulipa_h($f["nome"]); ?></strong>
                            <small>R$ <?php echo number_format((float) $f["preco"], 2, ",", "."); ?></small>
                        </div>
                    </a>
                <?php } ?>
            </div>
        <?php } ?>
    </section>
</main>

<script>
    (function () {
        var botao = document.getElementById("switchTema");
        var texto = document.getElementById("temaTexto");
        var html = document.documentElement;

        function aplicar(claro) {
            if (claro) html.setAttribute("data-tema", "claro");
            else html.removeAttribute("data-tema");
            botao.setAttribute("aria-checked", claro ? "true" : "false");
            texto.textContent = claro ? "Claro" : "Escuro";
        }

        aplicar(html.getAttribute("data-tema") === "claro");

        botao.addEventListener("click", function () {
            var claro = html.getAttribute("data-tema") !== "claro";
            aplicar(claro);
            try { localStorage.setItem("tema", claro ? "claro" : "escuro"); } catch (e) {}
        });
    })();
</script>

</body>
</html>
