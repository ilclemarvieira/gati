<?php
session_start();
include 'db.php';

$response = array('success' => false);

if (isset($_POST['taskId'], $_POST['status'])) {
    $taskId = $_POST['taskId'];
    $status = $_POST['status'] === 'done' ? 'done' : 'todo'; // Ajuste para alternar entre 'done' e 'todo'

    // Certifique-se de que seu SQL está correto e que você tem permissão para atualizar a tabela
    $stmt = $pdo->prepare("UPDATE subtasks SET status = ? WHERE id = ?");
    $result = $stmt->execute([$status, $taskId]);
    
    if ($result) {
        $response['success'] = true;
        $response['status'] = $status; // Retorne o novo status para o cliente
    } else {
        $response['error'] = 'Não foi possível atualizar o status da tarefa.';
    }
}

echo json_encode($response);
?>
