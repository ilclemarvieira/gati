<?php
include 'db.php';

$compartilhamento_id = $_POST['compartilhamento_id'] ?? null;

if ($compartilhamento_id) {
    $stmt = $pdo->prepare("DELETE FROM tarefas_compartilhadas WHERE id = :compartilhamento_id");
    $stmt->bindParam(':compartilhamento_id', $compartilhamento_id, PDO::PARAM_INT);
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Falha ao desvincular compartilhamento.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'ID de compartilhamento não fornecido.']);
}
?>
