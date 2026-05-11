<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

$c = $pdo->query("SELECT aberto, horario_auto, horario_abre, horario_fecha, dias_funcionamento FROM config WHERE id=1")->fetch();

if (!(int)$c['horario_auto']) {
    echo json_encode(['aberto'=>(bool)$c['aberto'],'auto'=>false]);
    exit;
}

$agora = new DateTime('now', new DateTimeZone('America/Sao_Paulo'));
$diaSemana = (int)$agora->format('N'); // 1=seg, 7=dom
$diasArr = array_map('intval', explode(',', $c['dias_funcionamento'] ?? '1,2,3,4,5,6'));
$horaAtual = $agora->format('H:i');

$aberto = in_array($diaSemana, $diasArr) && $horaAtual >= $c['horario_abre'] && $horaAtual < $c['horario_fecha'];

if ($aberto !== (bool)$c['aberto']) {
    $pdo->prepare("UPDATE config SET aberto=? WHERE id=1")->execute([$aberto ? 1 : 0]);
}

echo json_encode(['aberto'=>$aberto,'auto'=>true,'abre'=>$c['horario_abre'],'fecha'=>$c['horario_fecha']]);
