<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/funcoes.php';

$body = json_decode(file_get_contents('php://input'), true) ?? [];

$nomeCli   = trim($body['nome'] ?? '');
$waCliente = preg_replace('/\D/', '', $body['whatsapp'] ?? '');
$itens      = $body['itens'] ?? [];
$endereço  = trim($body['endereco'] ?? '');
$obs       = trim($body['observacoes'] ?? '');
$tipoEnt   = in_array($body['tipo_entrega'] ?? 'retirada', ['entrega','retirada']) ? $body['tipo_entrega'] : 'retirada';
$pagamento = in_array($body['pagamento']??'',['dinheiro','pix','cartao']) ? $body['pagamento'] : 'dinheiro';
$cupomCode = strtoupper(trim($body['cupom'] ?? ''));
$bairro    = trim($body['bairro'] ?? '');

if (empty($itens)) { echo json_encode(['ok'=>false,'erro'=>'Carrinho vazio']); exit; }

$config = $pdo->query("SELECT * FROM config WHERE id=1")->fetch();

// Verifica loja aberta
if (!loja_aberta($config)) {
    echo json_encode(['ok'=>false,'erro'=>'Loja fechada no momento.']); exit;
}

// Calcular subtotal
$subtotal = 0;
foreach ($itens as &$item) {
    $stmt = $pdo->prepare("SELECT * FROM produtos WHERE id=? AND disponivel=1");
    $stmt->execute([$item['produto_id']]); $prod = $stmt->fetch();
    if (!$prod) { echo json_encode(['ok'=>false,'erro'=>'Produto inválido']); exit; }
    $item['nome_produto'] = $prod['nome'];
    $precoUnit = (float)$prod['preco'];
    // variações
    $customNomes = []; $precoExtra = 0;
    if (!empty($item['variacoes']) && is_array($item['variacoes'])) {
        foreach ($item['variacoes'] as $varId) {
            $v = $pdo->prepare("SELECT * FROM produto_variacoes WHERE id=? AND produto_id=?");
            $v->execute([$varId, $item['produto_id']]); $vr = $v->fetch();
            if ($vr) { $precoExtra += (float)$vr['preco_extra']; $customNomes[] = $vr['nome']; }
        }
    }
    $item['preco_unit'] = $precoUnit + $precoExtra;
    $item['subtotal']   = $item['preco_unit'] * max(1,(int)$item['quantidade']);
    $item['customizacoes'] = implode(', ', $customNomes);
    $subtotal += $item['subtotal'];
}
unset($item);

// Pedido mínimo
$minPedido = (float)$config['pedido_minimo'];
if ($minPedido > 0 && $subtotal < $minPedido) {
    echo json_encode(['ok'=>false,'erro'=>'Pedido mínimo de '.formatar_dinheiro($minPedido)]); exit;
}

// Taxa de entrega
$taxaEntrega = 0;
if ($tipoEnt === 'entrega') {
    if ((int)$config['frete_por_zona'] && $bairro) {
        $zonas = $pdo->query("SELECT * FROM zonas_entrega WHERE ativo=1")->fetchAll();
        foreach ($zonas as $z) {
            $bairrosZona = array_map('trim', explode(',', strtolower($z['bairros'])));
            if (in_array(strtolower($bairro), $bairrosZona)) { $taxaEntrega = (float)$z['taxa']; break; }
        }
        if (!$taxaEntrega) $taxaEntrega = (float)$config['taxa_entrega'];
    } else {
        $taxaEntrega = (float)$config['taxa_entrega'];
    }
}

// Promoção relâmpago
$descontoPromo = 0;
if ((int)$config['promo_ativa'] && (!$config['promo_fim'] || strtotime($config['promo_fim']) > time())) {
    $descontoPromo = round($subtotal * (float)$config['promo_desconto'] / 100, 2);
}

// Cupom de desconto
$descontoCupom = 0; $cupomAplicado = null;
if ($cupomCode) {
    $cu = $pdo->prepare("SELECT * FROM cupons WHERE codigo=? AND ativo=1"); $cu->execute([$cupomCode]); $cu=$cu->fetch();
    if ($cu && (!$cu['valido_ate'] || strtotime($cu['valido_ate'])>time()) && (!$cu['uso_maximo'] || $cu['uso_atual']<$cu['uso_maximo'])) {
        $descontoCupom = $cu['tipo']==='percentual' ? round(($subtotal-$descontoPromo)*$cu['valor']/100,2) : min((float)$cu['valor'],$subtotal);
        $cupomAplicado = $cu;
    }
}

$total = max(0, $subtotal - $descontoPromo - $descontoCupom + $taxaEntrega);

// Gravar pedido
$numero = gerar_numero_pedido();
$wa = $config['whatsapp'] ? json_encode($config['whatsapp']) : '""';
$pdo->prepare("INSERT INTO pedidos (numero,nome_cliente,whatsapp_cliente,tipo_entrega,endereco_entrega,observacoes,subtotal,taxa_entrega,total,status,pagamento,mensagem_whatsapp,cupom_codigo,cupom_desconto,created_at) VALUES (?,?,?,?,?,?,?,?,?,'novo',?,?,?,?,NOW())")
    ->execute([$numero,$nomeCli,$waCliente,$tipoEnt,$endereço,$obs,$subtotal,$taxaEntrega,$total,$pagamento,$waCliente,$cupomCode,$descontoCupom]);
$pedidoId = $pdo->lastInsertId();

foreach ($itens as $item) {
    $pdo->prepare("INSERT INTO pedido_itens (pedido_id,produto_id,nome_produto,quantidade,preco_unit,subtotal,obs,customizacoes) VALUES (?,?,?,?,?,?,?,?)")
        ->execute([$pedidoId,$item['produto_id'],$item['nome_produto'],$item['quantidade'],$item['preco_unit'],$item['subtotal'],$item['obs']??'',$item['customizacoes']]);
    $pdo->prepare("UPDATE produtos SET total_vendido=total_vendido+? WHERE id=?")->execute([$item['quantidade'],$item['produto_id']]);
}

// Atualizar uso do cupom
if ($cupomAplicado) $pdo->prepare("UPDATE cupons SET uso_atual=uso_atual+1 WHERE id=?")->execute([$cupomAplicado['id']]);

// Atualizar mais vendidos
$pdo->query("UPDATE produtos p SET mais_vendido=0");
$pdo->query("UPDATE produtos SET mais_vendido=1 WHERE id IN (SELECT produto_id FROM (SELECT produto_id FROM pedido_itens GROUP BY produto_id ORDER BY SUM(quantidade) DESC LIMIT 3) t)");

// CRM — upsert cliente
$cliente = null;
if ($waCliente) {
    $cliente = upsert_cliente($pdo, $waCliente, $nomeCli, $total);
}

// Mensagem de fidelidade
$msgFidelidade = '';
if ($cliente && $config) {
    $fid = verificar_fidelidade($pdo, $config, $cliente);
    if ($fid) $msgFidelidade = $fid['msg'];
}

echo json_encode([
    'ok' => true,
    'numero' => $numero,
    'pedido_id' => $pedidoId,
    'total' => $total,
    'desconto_promo' => $descontoPromo,
    'desconto_cupom' => $descontoCupom,
    'taxa_entrega' => $taxaEntrega,
    'whatsapp_loja' => preg_replace('/\D/','',$config['whatsapp']),
    'pix_chave' => $config['pix_chave'] ?? '',
    'pix_tipo'  => $config['pix_tipo']  ?? '',
    'pix_nome'  => $config['pix_nome']  ?? '',
    'fidelidade_msg' => $msgFidelidade,
]);