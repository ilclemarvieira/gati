<?php
session_start(); // Assegura que a sessão foi iniciada
include 'db.php';

header('Content-Type: application/json'); // Define o cabeçalho como JSON

if (isset($_SESSION['usuario_id'])) {
    $usuario_id = $_SESSION['usuario_id'];
    $stmt = $pdo->prepare("SELECT card_id, posicao FROM posicoes_cards WHERE usuario_id = ?");
    if ($stmt->execute([$usuario_id])) {
        $posicoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($posicoes);
    } else {
        // Em caso de erro na execução da consulta
        echo json_encode(['error' => 'Falha ao buscar posições dos cards.']);
    }
} else {
    // Se o usuário_id não está definido na sessão
    echo json_encode(['error' => 'Usuário não identificado.']);
}
?>
