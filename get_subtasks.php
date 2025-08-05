<?php
// get_subtasks.php
session_start();
include 'db.php';

// Obtém o cardId da requisição
$cardId = isset($_GET['cardId']) ? (int)$_GET['cardId'] : 0;

// Prepara a consulta SQL para buscar as subtarefas relacionadas ao card
$query = "SELECT * FROM subtasks WHERE card_id = :cardId";
$stmt = $pdo->prepare($query);
$stmt->bindParam(':cardId', $cardId, PDO::PARAM_INT);
$stmt->execute();

// Busca as subtarefas e as retorna como JSON
$subtasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($subtasks);
?>
