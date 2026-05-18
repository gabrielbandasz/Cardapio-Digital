<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
if (!admin_logado()) { http_response_code(403); exit; }

$tabelas = ['config','categorias','produtos','produto_variacoes','admins','pedidos','pedido_itens','clientes','cupons','zonas_entrega'];
$out = "-- Backup Cardápio Digital PLUS\n-- Gerado em: " . date('Y-m-d H:i:s') . "\n\nUSE cardapio_digital;\n\n";

foreach ($tabelas as $tabela) {
    try {
        $rows = $pdo->query("SELECT * FROM `$tabela`")->fetchAll();
        if (empty($rows)) { $out .= "-- $tabela: vazia\n\n"; continue; }
        $out .= "-- $tabela\nTRUNCATE TABLE `$tabela`;\n";
        foreach ($rows as $row) {
            $vals = array_map(fn($v) => $v === null ? 'NULL' : "'" . addslashes($v) . "'", array_values($row));
            $out .= "INSERT INTO `$tabela` VALUES (" . implode(',',$vals) . ");\n";
        }
        $out .= "\n";
    } catch (Exception $e) { $out .= "-- Erro em $tabela: ".$e->getMessage()."\n\n"; }
}

$filename = 'backup_cardapio_' . date('Ymd_His') . '.sql';
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $filename . '"');
echo $out;
