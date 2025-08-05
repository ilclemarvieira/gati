<?php
include 'db.php';

header('Content-Type: application/json');

$subtaskId = $_POST['subtask_id'] ?? null;

if (!$subtaskId) {
    echo json_encode(['success' => false, 'message' => 'ID da sub-tarefa não fornecido.']);
    exit;
}

try {
    // Obtenha o número e o nome da OS da subtarefa antes de excluir
    $stmt = $pdo->prepare("
        SELECT si.id, os.N_os, os.Nome_os 
        FROM sprint_itens si
        LEFT JOIN os ON si.item_id = os.Id
        WHERE si.id = :id
    ");
    $stmt->bindParam(':id', $subtaskId, PDO::PARAM_INT);
    $stmt->execute();
    $subtask = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$subtask) {
        echo json_encode(['success' => false, 'message' => 'Sub-tarefa não encontrada.']);
        exit;
    }

    // Exclua a subtarefa
    $deleteStmt = $pdo->prepare("DELETE FROM sprint_itens WHERE id = :id");
    $deleteStmt->bindParam(':id', $subtaskId, PDO::PARAM_INT);
    $deleteStmt->execute();

    // Retorne os detalhes da OS corretamente
    echo json_encode([
        'success' => true,
        'os_id' => $subtask['N_os'],
        'os_nome' => $subtask['Nome_os']
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao excluir sub-tarefa: ' . $e->getMessage()]);
}
?>
