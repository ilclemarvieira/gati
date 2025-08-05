<?php
session_start();
include 'db.php';

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['error' => true, 'message' => 'Usuário não autenticado']);
    exit;
}


// Inicialize as variáveis para evitar erros de "undefined variable"
$isImportant = null;
$isComplete = null;

// Verifica se o ID da tarefa e o novo status foram fornecidos
if (isset($_POST['tarefa_id'], $_POST['action'])) {
    $tarefaId = $_POST['tarefa_id'];
    $action = $_POST['action']; // Pode ser 'marcar_importante', 'marcar_concluida', 'desmarcar_importante', ou 'desmarcar_concluida'
    
    switch ($action) {
        case 'marcar_importante':
            $isImportant = 1;
            break;
        case 'marcar_concluida':
            $isComplete = 1;
            break;
        case 'desmarcar_importante':
            $isImportant = 0;
            break;
        case 'desmarcar_concluida':
            $isComplete = 0;
            break;
    }
    
    try {
    if ($action === 'marcar_importante' || $action === 'desmarcar_importante') {
        $isImportant = ($action === 'marcar_importante') ? 1 : 0;
        $sql = "UPDATE tarefas SET is_important = :isImportant WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['isImportant' => $isImportant, 'id' => $tarefaId]);
    } elseif ($action === 'marcar_concluida' || $action === 'desmarcar_concluida') {
        $isComplete = ($action === 'marcar_concluida') ? 1 : 0;
        $sql = "UPDATE tarefas SET is_complete = :isComplete WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['isComplete' => $isComplete, 'id' => $tarefaId]);
    }

    echo json_encode(['success' => true, 'message' => 'Status da tarefa atualizado com sucesso']);
} catch (PDOException $e) {
    echo json_encode(['error' => true, 'message' => 'Erro ao atualizar a tarefa: ' . $e->getMessage()]);
}
?>
