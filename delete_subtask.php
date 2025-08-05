<?php
// Inicie a sessão e inclua o arquivo de conexão com o banco de dados.
session_start();
include 'db.php';

// Prepare a resposta padrão.
$response = array('success' => false);

// Verifique se o ID da subtarefa foi enviado.
if (isset($_POST['subtaskId'])) {
    $subtaskId = $_POST['subtaskId'];
    
    // Exclua a subtarefa do banco de dados.
    $stmt = $pdo->prepare("DELETE FROM subtasks WHERE id = ?");
    $result = $stmt->execute([$subtaskId]);
    
    // Verifique se a exclusão foi bem-sucedida.
    if ($result) {
        $response['success'] = true;
        $response['subtaskId'] = $subtaskId;
    } else {
        $response['error'] = 'Não foi possível excluir a subtarefa.';
    }
} else {
    $response['error'] = 'ID da subtarefa não fornecido.';
}

// Retorne a resposta em formato JSON.
echo json_encode($response);
?>
