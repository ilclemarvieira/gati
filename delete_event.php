<?php
session_start();
include 'db.php'; // Inclui a conexão com o banco de dados

// Verifica se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    echo 'Usuário não está logado.';
    exit;
}

// Recebe o ID do evento a ser excluído
$eventId = $_POST['id'] ?? null;

if ($eventId) {
    // Prepara a consulta para excluir o evento
    $sql = "DELETE FROM eventos WHERE id = ?";
    $stmt = $pdo->prepare($sql);

    // Executa a consulta
    if ($stmt->execute([$eventId])) {
        echo 'success'; // Resposta de sucesso
    } else {
        echo 'error'; // Resposta de erro
    }
} else {
    echo 'error'; // Resposta de erro se o ID não foi recebido
}
?>
