<?php
include 'db.php'; // Certifique-se de incluir o arquivo de conexão com o banco de dados

header('Content-Type: application/json');

// Verifica se o ID do card foi enviado
if(isset($_GET['cardId'])) {
    $cardId = $_GET['cardId'];

    // Consulta para buscar os detalhes do card
    $stmt = $pdo->prepare("SELECT * FROM cards WHERE id = ?");
    $stmt->execute([$cardId]);
    $card = $stmt->fetch(PDO::FETCH_ASSOC);

    if($card) {
        // Consulta para buscar as subtarefas relacionadas a este card
        $subtasksStmt = $pdo->prepare("SELECT subtasks.*, usuarios.Nome AS usuario_nome FROM subtasks LEFT JOIN usuarios ON subtasks.user_id = usuarios.Id WHERE card_id = ? ORDER BY subtasks.created_at ASC");
        $subtasksStmt->execute([$cardId]);
        $subtasks = $subtasksStmt->fetchAll(PDO::FETCH_ASSOC);

        // Adiciona as subtarefas ao array do card
        $card['subtasks'] = $subtasks;

        echo json_encode($card);
    } else {
        // Card não encontrado
        echo json_encode(['error' => 'Card não encontrado.']);
    }
} else {
    // ID do card não foi enviado
    echo json_encode(['error' => 'ID do card não fornecido.']);
}
?>
