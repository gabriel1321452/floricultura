<?php
/**
 * Garante que SEMPRE exista o administrador:
 *   usuário: adm   |   senha: adm
 *
 * - Se já existir "adm", só corrige senha/permissão quando estiverem diferentes.
 * - Se não existir "adm" mas existir "admin", o "admin" é renomeado para "adm".
 * - Caso contrário, cria o usuário.
 *
 * É chamada automaticamente pelo index.php, admin.php, vendas.php e perfil.php.
 * Também pode ser aberta direto no navegador (garantir_admin.php) para a primeira configuração.
 */
function garantirAdmin(mysqli $conexao): void
{
    static $feito = false;
    if ($feito) {
        return;
    }
    $feito = true;

    $loginAdm = "adm";
    $senhaAdm = "adm";

    try {
        $stmt = $conexao->prepare(
            "SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = 'usuarios_login' AND column_name = 'is_admin'"
        );
        $stmt->execute();
        $stmt->bind_result($temColuna);
        $stmt->fetch();
        $stmt->close();
        if ((int) $temColuna === 0) {
            $conexao->query("ALTER TABLE usuarios_login ADD COLUMN is_admin TINYINT(1) NOT NULL DEFAULT 0");
        }

        // Descobre se as senhas do sistema são guardadas com hash (password_hash) ou em texto puro.
        $res = $conexao->query(
            "SELECT senha FROM usuarios_login WHERE usuario NOT IN ('adm', 'admin') AND senha <> '' LIMIT 1"
        );
        $outra = $res ? $res->fetch_row() : null;
        $usaHash = !$outra || preg_match('/^\$(2[aby]|argon2)/', (string) $outra[0]) === 1;

        $stmt = $conexao->prepare("SELECT id, senha, is_admin FROM usuarios_login WHERE usuario = ?");
        $stmt->bind_param("s", $loginAdm);
        $stmt->execute();
        $adm = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $senhaOk = fn($salva) => $usaHash ? password_verify($senhaAdm, (string) $salva) : $salva === $senhaAdm;
        $novaSenha = $usaHash ? password_hash($senhaAdm, PASSWORD_DEFAULT) : $senhaAdm;

        if ($adm) {
            if (!$senhaOk($adm["senha"]) || (int) $adm["is_admin"] !== 1) {
                $stmt = $conexao->prepare("UPDATE usuarios_login SET senha = ?, is_admin = 1 WHERE id = ?");
                $stmt->bind_param("si", $novaSenha, $adm["id"]);
                $stmt->execute();
                $stmt->close();
            }
            return;
        }

        $antigo = "admin";
        $stmt = $conexao->prepare("SELECT id FROM usuarios_login WHERE usuario = ?");
        $stmt->bind_param("s", $antigo);
        $stmt->execute();
        $admin = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($admin) {
            $stmt = $conexao->prepare("UPDATE usuarios_login SET usuario = ?, senha = ?, is_admin = 1 WHERE id = ?");
            $stmt->bind_param("ssi", $loginAdm, $novaSenha, $admin["id"]);
        } else {
            $stmt = $conexao->prepare("INSERT INTO usuarios_login (usuario, senha, is_admin) VALUES (?, ?, 1)");
            $stmt->bind_param("ss", $loginAdm, $novaSenha);
        }
        $stmt->execute();
        $stmt->close();
    } catch (mysqli_sql_exception $e) {
        // Não derruba a página caso algo dê errado.
    }
}

if (realpath($_SERVER["SCRIPT_FILENAME"] ?? "") === __FILE__) {
    require __DIR__ . "/conexao.php";
    garantirAdmin($conexao);
    header("Content-Type: text/plain; charset=utf-8");
    echo "Pronto! Administrador garantido.\nUsuário: adm\nSenha: adm\n\nPode apagar este acesso direto se quiser; as outras páginas já fazem essa verificação sozinhas.";
}
