<?php
// Certifique-se de incluir o arquivo que configura o $pdo
include 'db.php';

$searchTerm = $_GET['searchTerm'] ?? '';

// SQL para buscar cards que correspondem ao termo de busca
$cards = $pdo->prepare("
    SELECT * FROM cards 
    WHERE title LIKE :searchTerm OR responsible_users LIKE :searchTerm
    AND is_archived = 0
");
$cards->execute(['searchTerm' => "%$searchTerm%"]);
$cards = $cards->fetchAll(PDO::FETCH_ASSOC);

// SQL para buscar subtarefas que correspondem ao termo de busca
$subtasks = $pdo->prepare("
    SELECT subtasks.*, usuarios.Nome AS usuario_nome
    FROM subtasks
    LEFT JOIN usuarios ON subtasks.user_id = usuarios.Id
    WHERE subtasks.title LIKE :searchTerm
    ORDER BY subtasks.created_at ASC
");
$subtasks->execute(['searchTerm' => "%$searchTerm%"]);
$subtasks = $subtasks->fetchAll(PDO::FETCH_ASSOC);

// Retorne os resultados como JSON
echo json_encode(['cards' => $cards, 'subtasks' => $subtasks]);
