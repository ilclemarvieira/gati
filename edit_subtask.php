<?php
session_start();
include 'db.php';

$response = array('success' => false);

if (isset($_POST['subtaskId'], $_POST['title']) && isset($_SESSION['usuario_id'])) {
    $subtaskId = $_POST['subtaskId'];
    $newTitle = $_POST['title'];
    $userId = $_SESSION['usuario_id'];

    // Atualiza a subtarefa no banco de dados
    $stmt = $pdo->prepare("UPDATE subtasks SET title = ?, updated_at = NOW(), last_edited_by = ? WHERE id = ?");
    if ($stmt->execute([$newTitle, $userId, $subtaskId])) {
        $response['success'] = true;
        $response['editedAt'] = date('Y-m-d H:i:s'); // Use a data/hora atual para simplificar

        // Busca o nome do usuário que editou a subtarefa
        $stmt = $pdo->prepare("SELECT nome FROM usuarios WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $response['usuarioNome'] = $user ? $user['nome'] : 'Usuário desconhecido';
    } else {
        $response['error'] = 'Não foi possível atualizar a subtarefa.';
    }
} else {
    $response['error'] = 'Dados insuficientes fornecidos ou usuário não logado.';
}

echo json_encode($response);
?>
