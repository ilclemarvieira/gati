<?php
include 'db.php';

header('Content-Type: application/json');

// Captura o ID da subtarefa da URL, garantindo que esteja no formato correto
$subtaskId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$subtaskId) {
    error_log("Erro: ID da sub-tarefa não fornecido ou inválido.");
    echo json_encode(['success' => false, 'message' => 'ID da sub-tarefa não fornecido ou inválido.']);
    exit;
}

try {
    // Consulta SQL para capturar o número, o nome da OS e o status da contratada associados à subtarefa
    $stmt = $pdo->prepare("
        SELECT si.id, si.descricao, os.N_os as numero_os, os.Nome_os as nome_os, os.Status_contratada
        FROM sprint_itens si
        LEFT JOIN os ON si.item_id = os.Id
        WHERE si.id = :id
    ");
    $stmt->bindParam(':id', $subtaskId, PDO::PARAM_INT);
    $stmt->execute();
    $subtask = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($subtask) {
        // Retorna os dados da subtarefa, incluindo o status da contratada, em formato JSON
        echo json_encode([
            'success' => true,
            'id' => $subtask['id'],
            'descricao' => $subtask['descricao'],
            'numero_os' => $subtask['numero_os'],
            'nome_os' => $subtask['nome_os'],
            'status_contratada' => $subtask['Status_contratada'] // Inclui o status da contratada
        ]);
    } else {
        error_log("Sub-tarefa não encontrada para o ID: " . $subtaskId);
        echo json_encode(['success' => false, 'message' => 'Sub-tarefa não encontrada.']);
    }
} catch (Exception $e) {
    error_log("Erro ao obter sub-tarefa: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao obter sub-tarefa: ' . $e->getMessage()]);
}
?>