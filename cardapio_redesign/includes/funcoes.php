<?php
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function formatar_dinheiro($v){ return 'R$ ' . number_format($v, 2, ',', '.'); }

function status_label($s){
    return ['novo'=>'Novo','confirmado'=>'Confirmado','preparo'=>'Em Preparo','pronto'=>'Pronto p/ entrega','entregue'=>'Entregue','cancelado'=>'Cancelado'][$s] ?? $s;
}
function status_emoji($s){
    return ['novo'=>'📋','confirmado'=>'✅','preparo'=>'🍳','pronto'=>'🔔','entregue'=>'🛵','cancelado'=>'❌'][$s] ?? '❓';
}
function status_class($s){
    return ['novo'=>'badge-novo','confirmado'=>'badge-confirmado','preparo'=>'badge-preparo','pronto'=>'badge-pronto','entregue'=>'badge-entregue','cancelado'=>'badge-cancelado'][$s] ?? '';
}

function gerar_numero_pedido(){
    return strtoupper(substr(md5(uniqid()),0,6));
}

function loja_aberta(array $config): bool {
    if (!(int)($config['horario_auto'] ?? 0)) return (bool)$config['aberto'];
    $tz = new DateTimeZone('America/Sao_Paulo');
    $agora = new DateTime('now', $tz);
    $dia = (int)$agora->format('N');
    $hora = $agora->format('H:i');
    $dias = array_map('intval', explode(',', $config['dias_funcionamento'] ?? '1,2,3,4,5,6'));
    return in_array($dia, $dias) && $hora >= ($config['horario_abre'] ?? '11:00') && $hora < ($config['horario_fecha'] ?? '23:00');
}

function proximo_horario_abertura(array $config): string {
    if (!($config['horario_abre'] ?? null)) return '';
    $tz = new DateTimeZone('America/Sao_Paulo');
    $agora = new DateTime('now', $tz);
    $dias = array_map('intval', explode(',', $config['dias_funcionamento'] ?? '1,2,3,4,5,6'));
    $diasNomes = [1=>'seg',2=>'ter',3=>'qua',4=>'qui',5=>'sex',6=>'sáb',7=>'dom'];
    for ($i = 0; $i <= 7; $i++) {
        $d = clone $agora; if ($i > 0) $d->modify("+{$i} day");
        $diaN = (int)$d->format('N');
        if (in_array($diaN, $dias)) {
            if ($i === 0) { $h = $d->format('H:i'); if ($h < $config['horario_abre']) return 'Abrimos hoje às ' . $config['horario_abre']; }
            else return 'Abrimos ' . $diasNomes[$diaN] . ' às ' . $config['horario_abre'];
        }
    }
    return 'em breve';
}

function upsert_cliente(PDO $pdo, string $whatsapp, string $nome, float $total): array {
    $wa = preg_replace('/\D/', '', $whatsapp);
    $stmt = $pdo->prepare("SELECT * FROM clientes WHERE whatsapp=?");
    $stmt->execute([$wa]);
    $cliente = $stmt->fetch();
    if ($cliente) {
        $pdo->prepare("UPDATE clientes SET nome=COALESCE(NULLIF(?,nome),nome), total_pedidos=total_pedidos+1, total_gasto=total_gasto+?, pontos=pontos+1, ultimo_pedido=NOW() WHERE whatsapp=?")->execute([$nome,$total,$wa]);
        $cliente['total_pedidos'] += 1;
    } else {
        $pdo->prepare("INSERT INTO clientes (nome,whatsapp,total_pedidos,total_gasto,pontos,ultimo_pedido) VALUES (?,?,1,?,1,NOW())")->execute([$nome,$wa,$total]);
        $cliente = ['total_pedidos'=>1,'pontos'=>1,'total_gasto'=>$total];
    }
    return $cliente;
}

function verificar_fidelidade(PDO $pdo, array $config, array $cliente): array {
    if (!(int)($config['fidelidade_ativo'] ?? 0)) return [];
    $pedidos = (int)$cliente['total_pedidos'];
    $meta = (int)$config['fidelidade_pedidos'];
    $desconto = (int)$config['fidelidade_desconto'];
    if ($meta <= 0) return [];
    $faltam = $meta - ($pedidos % $meta);
    if ($faltam === $meta) return ['ganhou'=>true,'desconto'=>$desconto,'msg'=>"🎉 Você ganhou {$desconto}% de desconto! Peça agora e aproveite."];
    return ['ganhou'=>false,'faltam'=>$faltam,'msg'=>"⭐ Faltam {$faltam} pedido(s) para você ganhar {$desconto}% de desconto!"];
}
