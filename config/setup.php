<?php
/**
 * Setup inicial — cria a tabela de usuários e insere o admin padrão.
 * Execute UMA VEZ via navegador: http://localhost/didicontas/config/setup.php
 * Após o setup, DELETE ou renomeie este arquivo.
 */

// Bloco simples de HTML para deixar legível no browser
header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/conexao.php';

echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8">
<title>Setup — Didi Contas</title>
<style>
  body { font-family: system-ui, sans-serif; background: #0b0f1a; color: #f1f5f9; padding: 2rem; max-width: 600px; margin: 0 auto; }
  h1 { color: #60a5fa; } pre { background: #161d2e; border: 1px solid rgba(255,255,255,0.1); border-radius: 10px; padding: 1.25rem; line-height: 1.7; }
  .ok { color: #34d399; } .warn { color: #f59e0b; } .err { color: #f87171; }
</style></head><body>
<h1>⚙️ Setup — Didi Contas</h1><pre>';

$log = [];

try {
    /* ── 1. Cria tabela usuarios ── */
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `usuarios` (
            `id`            INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
            `usuario`       VARCHAR(50)     NOT NULL UNIQUE,
            `senha`         VARCHAR(255)    NOT NULL,
            `nome_display`  VARCHAR(100)    DEFAULT NULL,
            `criado_em`     DATETIME        DEFAULT CURRENT_TIMESTAMP,
            `atualizado_em` DATETIME        DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    $log[] = '<span class="ok">✅ Tabela `usuarios` criada (ou já existia).</span>';

    /* ── 2. Insere usuário padrão se ainda não houver nenhum ── */
    $count = (int) $pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();

    if ($count === 0) {
        $hash = password_hash('didi2025', PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO usuarios (usuario, senha, nome_display) VALUES (?, ?, ?)")
            ->execute(['didi', $hash, 'Didi']);
        $log[] = '<span class="ok">✅ Usuário criado com sucesso:</span>';
        $log[] = '   <strong>Usuário :</strong> didi';
        $log[] = '   <strong>Senha   :</strong> didi2025';
        $log[] = '';
        $log[] = '<span class="warn">⚠️  Troque a senha em Admin → Configurações assim que possível.</span>';
        $log[] = '<span class="warn">⚠️  Apague ou renomeie este arquivo após o setup.</span>';
    } else {
        $log[] = '<span class="warn">ℹ️  Já existem ' . $count . ' usuário(s) — inserção ignorada.</span>';
    }

    $log[] = '';
    $log[] = '<span class="ok">✅ Setup concluído.</span>';
    $log[] = '';
    $log[] = '→ Acesse o painel em: <a href="/didicontas/admin/" style="color:#60a5fa;">/didicontas/admin/</a>';

} catch (PDOException $e) {
    $log[] = '<span class="err">❌ Erro: ' . htmlspecialchars($e->getMessage()) . '</span>';
}

echo implode("\n", $log);
echo '</pre></body></html>';
