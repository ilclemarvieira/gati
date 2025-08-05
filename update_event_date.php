<?php
session_start();
require 'db.php'; // Certifique-se de que db.php conecta ao seu banco de dados

if (!isset($_SESSION['usuario_id'])) {
    echo "Usuário não logado";
    exit;
}

// Pegar dados do AJAX
$eventId = $_POST['eventId'] ?? null;
$newStartDate = $_POST['newStartDate'] ?? null;
$newEndDate = $_POST['newEndDate'] ?? null;

if ($eventId && $newStartDate) {
    // Preparar SQL para atualizar a data do evento
    $sql = "UPDATE eventos SET data_inicio = ?, data_fim = ? WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    
    if ($stmt->execute([$newStartDate, $newEndDate, $eventId])) {
        echo "Evento atualizado com sucesso!";
    } else {
        echo "Erro ao atualizar o evento.";
    }
} else {
    echo "Dados inválidos.";
}
?>
