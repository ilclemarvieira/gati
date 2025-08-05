<?php
session_start();
include 'db.php';

header('Content-Type: application/json');
$response = ['success' => false];

if (isset($_POST['cardId'])) {
    $cardId = filter_var($_POST['cardId'], FILTER_SANITIZE_NUMBER_INT);
    
    // Primeiro, obtenha o estado atual da tarefa
    $checkStmt = $pdo->prepare("SELECT is_archived FROM cards WHERE id = ?");
    $checkStmt->execute([$cardId]);
    $card = $checkStmt->fetch();

    if ($card) {
        // Inverte o estado de 'is_archived'
        $newState = $card['is_archived'] ? 0 : 1;
        $stmt = $pdo->prepare("UPDATE cards SET is_archived = ? WHERE id = ?");
        $result = $stmt->execute([$newState, $cardId]);

        if ($result) {
            $response['success'] = true;
            $response['newState'] = $newState; // Adicione o novo estado à resposta
        } else {
            $response['error'] = 'Não foi possível alterar o estado da tarefa.';
        }
    } else {
        $response['error'] = 'Tarefa não encontrada.';
    }
} else {
    $response['error'] = 'ID da tarefa não fornecido.';
}

echo json_encode($response);
?>
