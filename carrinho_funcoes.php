<?php

function carregarCarrinho($conexao)
{
    $itens = [];
    $total = 0;

    if (empty($_SESSION["carrinho"])) {
        return [$itens, $total];
    }

    $stmt = $conexao->prepare("SELECT * FROM produtos WHERE id = ?");

    foreach ($_SESSION["carrinho"] as $id => $quantidade) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $produto = $stmt->get_result()->fetch_assoc();

        if (!$produto) {
            unset($_SESSION["carrinho"][$id]);
            continue;
        }

        $produto["quantidade"] = $quantidade;
        $produto["subtotal"] = $produto["preco"] * $quantidade;
        $total += $produto["subtotal"];
        $itens[] = $produto;
    }

    $stmt->close();

    return [$itens, $total];
}

function dinheiro($valor)
{
    return "R$ " . number_format($valor, 2, ",", ".");
}

?>
