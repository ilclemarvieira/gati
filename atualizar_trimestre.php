<?php
include 'db.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents("php://input"), true);

if(!isset($input['ids']) || !isset($input['trimestre']) || !isset($input['mes'])){
    echo json_encode(['success'=>false,'message'=>'Parâmetros inválidos']);
    exit;
}

$ids = $input['ids'];
$trimestre = (int)$input['trimestre'];
$mes = (int)$input['mes'];

if(!is_array($ids) || empty($ids) || $trimestre < 1 || $trimestre > 4 || $mes < 1 || $mes > 12){
    echo json_encode(['success'=>false,'message'=>'Parâmetros inválidos ou fora do esperado.']);
    exit;
}

try {
    $pdo->beginTransaction();
    foreach($ids as $posicao => $id) {
        $id = (int)$id;
        $stmt = $pdo->prepare("UPDATE cronograma SET trimestre=?, mes=?, posicao=? WHERE id=?");
        $stmt->execute([$trimestre, $mes, $posicao, $id]);
    }
    $pdo->commit();
    echo json_encode(['success'=>true]);
} catch(Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success'=>false,'message'=>'Erro: '.$e->getMessage()]);
}
