<?php
session_start();
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Os dados vêm em JSON
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $data['id'] ?? null;

    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID não fornecido.']);
        exit;
    }

    $stmt = $pdo->prepare("DELETE FROM cronograma WHERE id = ?");
    $ok = $stmt->execute([$id]);

    if ($ok) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao excluir tarefa.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Método inválido.']);
}
