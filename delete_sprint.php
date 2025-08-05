<?php
include 'db.php';

header('Content-Type: application/json');

$sprintId = $_POST['sprint_id'] ?? null;

if (!$sprintId) {
    echo json_encode(['success' => false, 'message' => 'ID da sprint não fornecido.']);
    exit;
}

try {
    // Obter as OS das subtarefas para reintroduzir no availableOSes
    $stmt = $pdo->prepare("
        SELECT si.item_id as id, os.N_os as nome
        FROM sprint_itens si
        LEFT JOIN os ON si.item_id = os.Id
        WHERE si.sprint_id = :sprint_id AND si.tipo = 'os'
    ");
    $stmt->bindParam(':sprint_id', $sprintId, PDO::PARAM_INT);
    $stmt->execute();
    $osList = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Excluir as subtarefas da sprint
    $stmt = $pdo->prepare("DELETE FROM sprint_itens WHERE sprint_id = :sprint_id");
    $stmt->bindParam(':sprint_id', $sprintId, PDO::PARAM_INT);
    $stmt->execute();

    // Excluir a sprint
    $stmt = $pdo->prepare("DELETE FROM sprints WHERE id = :id");
    $stmt->bindParam(':id', $sprintId, PDO::PARAM_INT);
    $stmt->execute();

    echo json_encode(['success' => true, 'os_list' => $osList]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao excluir sprint: ' . $e->getMessage()]);
}
?>
