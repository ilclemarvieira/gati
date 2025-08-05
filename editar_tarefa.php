<?php
session_start();
include 'db.php';

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Não autenticado.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'] ?? null;
    $trimestre = $_POST['trimestre'] ?? null;
    $mes = $_POST['mes'] ?? null;
    $ano = $_POST['ano'] ?? null;
    $status = $_POST['status'] ?? null;

    if (!$id || !$trimestre || !$mes || !$ano || !$status) {
        echo json_encode(['success' => false, 'message' => 'Dados incompletos.']);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE cronograma SET trimestre = ?, mes = ?, ano = ?, status = ? WHERE id = ?");
    $ok = $stmt->execute([$trimestre, $mes, $ano, $status, $id]);

    if ($ok) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao editar tarefa.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Método inválido.']);
}
