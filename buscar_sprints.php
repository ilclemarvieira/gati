<?php
include 'db.php'; // Inclua seu arquivo de conexão com o banco de dados

// Query para buscar todas as sprints
$stmt = $pdo->query("SELECT * FROM sprints");
$sprints = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Agora, você pode iterar sobre $sprints para exibir no HTML
?>
