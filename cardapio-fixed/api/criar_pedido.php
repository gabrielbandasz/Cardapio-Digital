<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/funcoes.php';
require_once __DIR__ . '/../includes/rate_limit.php';
rate_limit('criar_pedido', 5, 60); // máx 5 pedidos/min por IP

function resposta($ok, $dados = [], $status = 200) {
    http_response_code($status);
    echo json_encode(array_merge(['ok' => $ok], $dados), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $body = json_decode(file_get_contents('php://input'), true);

    if (!is_array($body)) {
        resposta(false, ['erro' => 'JSON inválido recebido.'], 400);
    }

    $nomeCli   = trim($body['nome'] ?? '');
    $waCliente = preg_replace('/\D/', '', $body['whatsapp'] ?? '');
    $itens     = $body['itens'] ?? [];
    $endereco  = trim($body['endereco'] ?? '');
    $obs       = trim($body['observacoes'] ?? '');
    $bairro    = trim($body['bairro'] ?? '');
    $cupomCode = strtoupper(trim($body['cupom'] ?? ''));

    $tipoEnt = $body['tipo_entrega'] ?? 'retirada';
    if (!in_array($tipoEnt, ['entrega', 'retirada'], true)) {
        $tipoEnt = 'retirada';
    }

    $pagamento = $body['pagamento'] ?? 'dinheiro';
    if (!in_array($pagamento, ['dinheiro', 'pix', 'cartao', 'mercadopago'], true)) {
        $pagamento = 'dinheiro';
    }
    // Normalizar para o enum do banco
    $pagamentoDB = in_array($pagamento, ['dinheiro','pix','cartao']) ? $pagamento : 'cartao';

    if ($nomeCli === '') {
        resposta(false, ['erro' => 'Informe o nome do cliente.'], 400);
    }

    if ($waCliente === '' || strlen($waCliente) < 10) {
        resposta(false, ['erro' => 'Informe um WhatsApp válido com DDD.'], 400);
    }

    if (!is_array($itens) || empty($itens)) {
        resposta(false, ['erro' => 'Carrinho vazio.'], 400);
    }

    if ($tipoEnt === 'entrega' && $endereco === '') {
        resposta(false, ['erro' => 'Informe o endereço de entrega.'], 400);
    }

    $config = $pdo->query("SELECT * FROM config WHERE id = 1")->fetch(PDO::FETCH_ASSOC);

    if (!$config) {
        resposta(false, ['erro' => 'Configuração da loja não encontrada.'], 500);
    }

    if (!loja_aberta($config)) {
        resposta(false, ['erro' => 'Loja fechada no momento.'], 403);
    }

    $subtotal = 0;
    $itensProcessados = [];

    foreach ($itens as $item) {
        $produtoId  = (int)($item['produto_id'] ?? 0);
        $quantidade = max(1, (int)($item['quantidade'] ?? 1));
        $obsItem    = trim($item['obs'] ?? '');
        $variacoes  = $item['variacoes'] ?? [];
        $extrasIn   = is_array($item['extras'] ?? null) ? $item['extras'] : [];
        $removidosIn= is_array($item['removidos'] ?? null) ? $item['removidos'] : [];

        if ($produtoId <= 0) {
            resposta(false, ['erro' => 'Produto inválido no carrinho.'], 400);
        }

        // Buscar produto real no banco (não confiar no preço do frontend)
        $stmt = $pdo->prepare("SELECT * FROM produtos WHERE id = ? AND disponivel = 1 LIMIT 1");
        $stmt->execute([$produtoId]);
        $produto = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$produto) {
            resposta(false, ['erro' => 'Produto inválido ou indisponível.'], 400);
        }

        $precoUnit   = (float)$produto['preco'];
        $customNomes = [];

        // ── Variações legadas (produto_variacoes) ────────────────────
        if (!empty($variacoes) && is_array($variacoes)) {
            foreach ($variacoes as $varId) {
                $varId = (int)$varId;
                if ($varId <= 0) continue;

                $stmtVar = $pdo->prepare(
                    "SELECT * FROM produto_variacoes WHERE id = ? AND produto_id = ? LIMIT 1"
                );
                $stmtVar->execute([$varId, $produtoId]);
                $variacao = $stmtVar->fetch(PDO::FETCH_ASSOC);

                if ($variacao) {
                    $precoUnit   += (float)$variacao['preco_extra'];
                    $customNomes[] = $variacao['nome'];
                }
            }
        }

        // ── Extras (produto_opcoes tipo='extra') — preço buscado no banco ─
        $extrasValidos   = [];
        $removidosValidos = [];

        if (!empty($extrasIn)) {
            foreach ($extrasIn as $ex) {
                $exId = (int)($ex['id'] ?? 0);
                if ($exId <= 0) continue;

                $stmtEx = $pdo->prepare(
                    "SELECT * FROM produto_opcoes
                     WHERE id = ? AND produto_id = ? AND tipo = 'extra' AND ativo = 1
                     LIMIT 1"
                );
                $stmtEx->execute([$exId, $produtoId]);
                $opcaoEx = $stmtEx->fetch(PDO::FETCH_ASSOC);

                if ($opcaoEx) {
                    $precoExtra = (float)$opcaoEx['preco'];
                    $precoUnit += $precoExtra;
                    $extrasValidos[] = [
                        'id'    => (int)$opcaoEx['id'],
                        'nome'  => $opcaoEx['nome'],
                        'preco' => $precoExtra,
                        'emoji' => $opcaoEx['emoji'] ?? ''
                    ];
                    $customNomes[] = ($opcaoEx['emoji'] ? $opcaoEx['emoji'] . ' ' : '') . $opcaoEx['nome'];
                }
            }
        }

        // ── Removidos (produto_opcoes tipo='remover') ─────────────────
        if (!empty($removidosIn)) {
            // removidos chegam como array de strings (nomes) ou de ids
            // Buscar apenas os que realmente pertencem a esse produto
            foreach ($removidosIn as $rem) {
                $remNome = trim(is_array($rem) ? ($rem['nome'] ?? '') : (string)$rem);
                if ($remNome === '') continue;

                // Validar que o ingrediente removível realmente existe nesse produto
                $stmtRem = $pdo->prepare(
                    "SELECT nome, emoji FROM produto_opcoes
                     WHERE produto_id = ? AND tipo = 'remover' AND ativo = 1 AND nome = ?
                     LIMIT 1"
                );
                $stmtRem->execute([$produtoId, $remNome]);
                $opcaoRem = $stmtRem->fetch(PDO::FETCH_ASSOC);

                if ($opcaoRem) {
                    $removidosValidos[] = $opcaoRem['nome'];
                }
            }
        }

        $subtotalItem = $precoUnit * $quantidade;

        // Montar campo customizacoes como JSON estruturado para o admin
        $customizacoesJson = json_encode([
            'extras'    => $extrasValidos,
            'removidos' => $removidosValidos,
            'variacoes' => $customNomes
        ], JSON_UNESCAPED_UNICODE);

        $itensProcessados[] = [
            'produto_id'     => $produtoId,
            'nome_produto'   => $produto['nome'],
            'quantidade'     => $quantidade,
            'preco_unitario' => $precoUnit,
            'subtotal'       => $subtotalItem,
            'obs'            => $obsItem,
            'customizacoes'  => $customizacoesJson,
            // Auxiliar para a mensagem WhatsApp (não vai ao banco)
            'extras_validos'   => $extrasValidos,
            'removidos_validos'=> $removidosValidos,
        ];

        $subtotal += $subtotalItem;
    }

    $minPedido = (float)($config['pedido_minimo'] ?? 0);

    if ($minPedido > 0 && $subtotal < $minPedido) {
        resposta(false, [
            'erro' => 'Pedido mínimo de ' . formatar_dinheiro($minPedido) . '.'
        ], 400);
    }

    $taxaEntrega = 0;

    if ($tipoEnt === 'entrega') {
        $taxaEntrega = (float)($config['taxa_entrega'] ?? 0);

        if ((int)($config['frete_por_zona'] ?? 0) === 1 && $bairro !== '') {
            $zonas = $pdo->query("SELECT * FROM zonas_entrega WHERE ativo = 1")->fetchAll(PDO::FETCH_ASSOC);

            foreach ($zonas as $z) {
                $bairrosZona = array_map('trim', explode(',', strtolower($z['bairros'] ?? '')));

                if (in_array(strtolower($bairro), $bairrosZona, true)) {
                    $taxaEntrega = (float)$z['taxa'];
                    break;
                }
            }
        }
    }

    $descontoPromo = 0;

    if (
        (int)($config['promo_ativa'] ?? 0) === 1 &&
        (
            empty($config['promo_fim']) ||
            strtotime($config['promo_fim']) > time()
        )
    ) {
        $descontoPromo = round($subtotal * (float)($config['promo_desconto'] ?? 0) / 100, 2);
    }

    $descontoCupom = 0;
    $cupomAplicado = null;

    if ($cupomCode !== '') {
        $stmtCupom = $pdo->prepare("SELECT * FROM cupons WHERE codigo = ? AND ativo = 1 LIMIT 1");
        $stmtCupom->execute([$cupomCode]);
        $cupom = $stmtCupom->fetch(PDO::FETCH_ASSOC);

        if ($cupom) {
            $validoAte   = $cupom['valido_ate'] ?? null;
            $usoMaximo   = (int)($cupom['uso_maximo'] ?? 0);
            $usoAtual    = (int)($cupom['uso_atual'] ?? 0);

            $cupomValido     = !$validoAte || strtotime($validoAte) > time();
            $usoDisponivel   = !$usoMaximo || $usoAtual < $usoMaximo;

            if ($cupomValido && $usoDisponivel) {
                $baseCupom = max(0, $subtotal - $descontoPromo);

                if ($cupom['tipo'] === 'percentual') {
                    $descontoCupom = round($baseCupom * (float)$cupom['valor'] / 100, 2);
                } else {
                    $descontoCupom = min((float)$cupom['valor'], $baseCupom);
                }

                $cupomAplicado = $cupom;
            }
        }
    }

    $total = max(0, $subtotal - $descontoPromo - $descontoCupom + $taxaEntrega);

    $numero = gerar_numero_pedido();

    $mensagemWhatsapp = "Pedido #{$numero} - {$nomeCli}";

    $pdo->beginTransaction();

    $stmtPedido = $pdo->prepare("
        INSERT INTO pedidos (
            numero,
            nome_cliente,
            whatsapp_cliente,
            tipo_entrega,
            endereco_entrega,
            subtotal,
            taxa_entrega,
            total,
            observacoes,
            status,
            pagamento,
            mensagem_whatsapp,
            cupom_codigo,
            cupom_desconto,
            created_at
        ) VALUES (
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            'novo',
            ?,
            ?,
            ?,
            ?,
            NOW()
        )
    ");

    $stmtPedido->execute([
        $numero,
        $nomeCli,
        $waCliente,
        $tipoEnt,
        $endereco,
        $subtotal,
        $taxaEntrega,
        $total,
        $obs,
        $pagamentoDB,
        $mensagemWhatsapp,
        $cupomAplicado ? $cupomCode : null,
        $descontoCupom
    ]);

    $pedidoId = (int)$pdo->lastInsertId();

    $stmtItem = $pdo->prepare("
        INSERT INTO pedido_itens (
            pedido_id,
            produto_id,
            nome_produto,
            preco_unitario,
            quantidade,
            subtotal,
            obs,
            customizacoes
        ) VALUES (
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?
        )
    ");

    foreach ($itensProcessados as $item) {
        $stmtItem->execute([
            $pedidoId,
            $item['produto_id'],
            $item['nome_produto'],
            $item['preco_unitario'],
            $item['quantidade'],
            $item['subtotal'],
            $item['obs'],
            $item['customizacoes']
        ]);

        $stmtUpdate = $pdo->prepare("
            UPDATE produtos
            SET total_vendido = total_vendido + ?
            WHERE id = ?
        ");

        $stmtUpdate->execute([
            $item['quantidade'],
            $item['produto_id']
        ]);
    }

    if ($cupomAplicado) {
        $stmtCupomUso = $pdo->prepare("
            UPDATE cupons
            SET uso_atual = uso_atual + 1
            WHERE id = ?
        ");

        $stmtCupomUso->execute([$cupomAplicado['id']]);
    }

    $pdo->query("UPDATE produtos SET mais_vendido = 0");

    $pdo->query("
        UPDATE produtos
        SET mais_vendido = 1
        WHERE id IN (
            SELECT produto_id
            FROM (
                SELECT produto_id
                FROM pedido_itens
                GROUP BY produto_id
                ORDER BY SUM(quantidade) DESC
                LIMIT 3
            ) AS ranking
        )
    ");

    $pdo->commit();

    $msgFidelidade = '';

    try {
        if (function_exists('upsert_cliente') && function_exists('verificar_fidelidade')) {
            $cliente = upsert_cliente($pdo, $waCliente, $nomeCli, $total);

            if ($cliente) {
                $fid = verificar_fidelidade($pdo, $config, $cliente);

                if ($fid && isset($fid['msg'])) {
                    $msgFidelidade = $fid['msg'];
                }
            }
        }
    } catch (Throwable $e) {
        $msgFidelidade = '';
    }

    $whatsappLoja = preg_replace('/\D/', '', $config['whatsapp'] ?? '');

    if ($whatsappLoja === '') {
    resposta(false, ['erro' => 'WhatsApp da loja não configurado. Contate o administrador.'], 500);
}

    if (substr($whatsappLoja, 0, 2) !== '55') {
        $whatsappLoja = '55' . $whatsappLoja;
    }

    resposta(true, [
        'numero'          => $numero,
        'pedido_id'       => $pedidoId,
        'subtotal'        => $subtotal,
        'total'           => $total,
        'desconto_promo'  => $descontoPromo,
        'desconto_cupom'  => $descontoCupom,
        'taxa_entrega'    => $taxaEntrega,
        'whatsapp_loja'   => $whatsappLoja,
        'pix_chave'       => $config['pix_chave'] ?? '',
        'pix_tipo'        => $config['pix_tipo'] ?? '',
        'pix_nome'        => $config['pix_nome'] ?? '',
        'fidelidade_msg'  => $msgFidelidade
    ]);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

   resposta(false, ['erro' => 'Erro ao criar o pedido.'], 500);
}
