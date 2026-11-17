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
date_default_timezone_set("America/Sao_Paulo");

$stmt = $conexao->prepare("SELECT id, is_admin FROM usuarios_login WHERE usuario = ?");
$stmt->bind_param("s", $_SESSION["usuario"]);
$stmt->execute();
$logado = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$logado || (int) $logado["is_admin"] !== 1) {
    header("Location: index.php");
    exit;
}
$_SESSION["admin"] = true;

/* ---------- Números dos cards ---------- */
$totais = $conexao->query("SELECT COUNT(*) AS qtd, COALESCE(SUM(total), 0) AS soma FROM pedidos")->fetch_assoc();
$totalPedidos = (int) $totais["qtd"];
$totalVendas = (float) $totais["soma"];
$totalEstoque = (int) $conexao->query("SELECT COALESCE(SUM(estoque), 0) FROM produtos")->fetch_row()[0];
$totalClientes = (int) $conexao->query("SELECT COUNT(*) FROM usuarios_login WHERE is_admin = 0")->fetch_row()[0];

/* ---------- Últimas vendas ---------- */
$ultimas = $conexao->query(
    "SELECT usuario, total, criado_em FROM pedidos ORDER BY criado_em DESC, id DESC LIMIT 5"
)->fetch_all(MYSQLI_ASSOC);

/* ---------- Vendas dos últimos 7 dias ---------- */
$dias = [];
for ($i = 6; $i >= 0; $i--) {
    $chave = date("Y-m-d", strtotime("-$i days"));
    $dias[$chave] = ["rotulo" => date("d/m", strtotime($chave)), "total" => 0.0, "pedidos" => 0];
}

$inicio = array_key_first($dias) . " 00:00:00";
$stmt = $conexao->prepare(
    "SELECT DATE(criado_em) AS dia, COUNT(*) AS qtd, COALESCE(SUM(total), 0) AS soma
     FROM pedidos WHERE criado_em >= ? GROUP BY DATE(criado_em)"
);
$stmt->bind_param("s", $inicio);
$stmt->execute();
foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $linha) {
    if (isset($dias[$linha["dia"]])) {
        $dias[$linha["dia"]]["total"] = (float) $linha["soma"];
        $dias[$linha["dia"]]["pedidos"] = (int) $linha["qtd"];
    }
}
$stmt->close();

function escalaMaxima(float $max): float
{
    if ($max <= 0) {
        return 100;
    }
    $base = pow(10, floor(log10($max)));
    foreach ([1, 2, 2.5, 5, 10] as $m) {
        if ($max <= $m * $base) {
            return $m * $base;
        }
    }
    return 10 * $base;
}

$maxGrafico = escalaMaxima(max(array_column($dias, "total")));
$divisoes = 4;

function dinheiro(float $v): string
{
    return "R$ " . number_format($v, 2, ",", ".");
}

tulipa_cabeca("Total de vendas");
tulipa_topo(true);
?>

<main class="dash">

    <section class="card stat">
        <h3>Total de pedidos</h3>
        <div class="linha">
            <svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M3 5h6l5 24h24l5-17H12"/><circle cx="19" cy="39" r="3"/><circle cx="35" cy="39" r="3"/>
            </svg>
            <span class="valor"><?php echo number_format($totalPedidos, 0, ",", "."); ?></span>
        </div>
    </section>

    <section class="card stat">
        <h3>Total de vendas</h3>
        <div class="linha">
            <svg viewBox="0 0 48 48" aria-hidden="true">
                <text x="24" y="38" text-anchor="middle" font-size="46" font-weight="700" fill="currentColor" font-family="Bellota Text, sans-serif">$</text>
            </svg>
            <span class="valor"><?php echo dinheiro($totalVendas); ?></span>
        </div>
    </section>

    <section class="card stat">
        <h3>Qntd em estoque</h3>
        <div class="linha">
            <svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linejoin="round" aria-hidden="true">
                <path d="M24 4 43 13v22L24 44 5 35V13z"/><path d="M5 13l19 9 19-9M24 22v22"/><path d="M14 8.5l19 9"/>
            </svg>
            <span class="valor"><?php echo number_format($totalEstoque, 0, ",", "."); ?></span>
        </div>
    </section>

    <section class="card stat">
        <h3>Clientes registrados</h3>
        <div class="linha">
            <svg viewBox="0 0 48 48" fill="currentColor" aria-hidden="true">
                <circle cx="24" cy="14" r="9"/><path d="M5 44c0-11 8-17 19-17s19 6 19 17z"/>
            </svg>
            <span class="valor"><?php echo number_format($totalClientes, 0, ",", "."); ?></span>
        </div>
    </section>

    <section class="card card-vendas">
        <h2>Últimas vendas:</h2>
        <?php if (empty($ultimas)) { ?>
            <p class="sem-dados">Nenhuma venda registrada ainda.</p>
        <?php } else { ?>
            <?php foreach ($ultimas as $v) { ?>
                <div class="venda">
                    <span><?php echo tulipa_h($v["usuario"]); ?></span>
                    <span><?php echo date("d/m/Y H:i", strtotime($v["criado_em"])); ?></span>
                    <span><?php echo dinheiro((float) $v["total"]); ?></span>
                </div>
            <?php } ?>
        <?php } ?>
    </section>

    <section class="card card-grafico">
        <h2>Vendas dos últimos 7 dias:</h2>
        <div class="grafico" role="img" aria-label="Gráfico de barras com o total vendido por dia nos últimos 7 dias">
            <?php for ($i = 0; $i <= $divisoes; $i++) { ?>
                <div class="grade" style="bottom: <?php echo $i * 100 / $divisoes; ?>%">
                    <span><?php echo number_format($maxGrafico * $i / $divisoes, 0, ",", "."); ?></span>
                </div>
            <?php } ?>
            <div class="barras">
                <?php foreach ($dias as $d) { ?>
                    <div class="barra-col">
                        <div class="barra"
                             style="height: <?php echo round($d["total"] / $maxGrafico * 100, 2); ?>%"
                             title="<?php echo tulipa_h($d["rotulo"] . ": " . dinheiro($d["total"]) . " (" . $d["pedidos"] . " pedido(s))"); ?>"></div>
                    </div>
                <?php } ?>
            </div>
        </div>
        <div class="eixo">
            <?php foreach ($dias as $d) { ?><span><?php echo $d["rotulo"]; ?></span><?php } ?>
        </div>
    </section>

</main>

</body>
</html>
