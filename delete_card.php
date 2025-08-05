<?php
session_start();
include 'db.php';

header('Content-Type: application/json');

$response = ['success' => false];

if (isset($_POST['cardId'])) {
    $cardId = filter_var($_POST['cardId'], FILTER_SANITIZE_NUMBER_INT);

    try {
        // Inicia uma transação
        $pdo->beginTransaction();

        // Exclui todas as subtarefas associadas ao card
        $stmtSubtasks = $pdo->prepare("DELETE FROM subtasks WHERE card_id = ?");
        $stmtSubtasks->execute([$cardId]);

        // Exclui o card
        $stmtCard = $pdo->prepare("DELETE FROM cards WHERE id = ?");
        $stmtCard->execute([$cardId]);

        // Se chegamos até aqui, está tudo bem
        $pdo->commit();
        $response['success'] = true;

    } catch (Exception $e) {
        // Algo deu errado, reverta a transação
        $pdo->rollback();
        $response['error'] = 'Erro ao excluir: ' . $e->getMessage();
    }
} else {
    $response['error'] = 'ID da tarefa não fornecido.';
}

echo json_encode($response);
?>
