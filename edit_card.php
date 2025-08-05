<?php
session_start();
include 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'error' => 'Usuário não está logado.']);
    exit;
}

if (!isset($_POST['cardId'], $_POST['title'], $_POST['responsibleUsers'])) {
    echo json_encode(['success' => false, 'error' => 'Dados insuficientes fornecidos.']);
    exit;
}

$cardId = filter_var($_POST['cardId'], FILTER_VALIDATE_INT);
$title = filter_var($_POST['title'], FILTER_SANITIZE_STRING);
$responsibleUsers = filter_var($_POST['responsibleUsers'], FILTER_SANITIZE_STRING);

if (!$cardId || !$title) {
    echo json_encode(['success' => false, 'error' => 'Dados inválidos fornecidos.']);
    exit;
}

$stmt = $pdo->prepare("UPDATE cards SET title = ?, responsible_users = ? WHERE id = ?");
$result = $stmt->execute([$title, $responsibleUsers, $cardId]);

if ($result) {
    echo json_encode(['success' => true, 'message' => 'Card atualizado com sucesso.']);
} else {
    echo json_encode(['success' => false, 'error' => 'Erro ao atualizar o card.']);
}
?>
