<?php
session_start();
include 'db.php';

$response = array('success' => false);

date_default_timezone_set('America/Sao_Paulo');
$pdo->exec("SET time_zone='-03:00'");
$response['createdAt'] = date('Y-m-d H:i:s'); // Envie a data de criação atual no formato apropriado


// Verifique se o usuário está logado e se os dados necessários foram enviados
if (!isset($_SESSION['usuario_id'])) {
    $response['error'] = 'Usuário não logado.';
    echo json_encode($response);
    exit;
}

if(isset($_POST['cardId'], $_POST['subtaskTitle'])) {
    $cardId = filter_var($_POST['cardId'], FILTER_VALIDATE_INT);
    $subtaskTitle = filter_var($_POST['subtaskTitle'], FILTER_SANITIZE_STRING);
    $userId = $_SESSION['usuario_id']; // ID do usuário logado

    // Prepare a query para inserir a nova subtarefa incluindo o user_id
    $stmt = $pdo->prepare("INSERT INTO subtasks (card_id, title, status, user_id, created_at, last_edited_by) VALUES (?, ?, 'todo', ?, NOW(), ?)");
    $result = $stmt->execute([$cardId, $subtaskTitle, $userId, $userId]);
    
    if($result) {
        $response['success'] = true;
        $response['subtaskId'] = $pdo->lastInsertId();
        $response['createdAt'] = date('Y-m-d H:i:s'); // Envie a data de criação atual no formato apropriado
        // Busque o nome do usuário para enviar de volta na resposta
        $userStmt = $pdo->prepare("SELECT Nome FROM usuarios WHERE Id = ?");
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch();
        $response['usuarioNome'] = $user ? $user['Nome'] : 'Desconhecido';
    } else {
        $response['error'] = 'Não foi possível inserir a subtarefa.';
    }
} else {
    $response['error'] = 'Dados insuficientes fornecidos.';
}

echo json_encode($response);
?>
