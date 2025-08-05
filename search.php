<?php
// search.php
include 'db.php';

$searchTerm = isset($_POST['searchTerm']) ? $_POST['searchTerm'] : '';

$query = $pdo->prepare("
    SELECT c.id, c.title, c.is_archived, c.responsible_users,
    (SELECT COUNT(*) FROM subtasks WHERE card_id = c.id) AS subtaskCount,
    (SELECT GROUP_CONCAT(title SEPARATOR '|') FROM subtasks WHERE card_id = c.id) AS subtasksTitles
    FROM cards c
    WHERE c.title LIKE :term1 OR c.responsible_users LIKE :term2
");

$searchTermWildcard = "%$searchTerm%";
$query->bindParam(':term1', $searchTermWildcard);
$query->bindParam(':term2', $searchTermWildcard);
$query->execute();

$results = $query->fetchAll(PDO::FETCH_ASSOC);

// Organizar os dados
$cards = [];
foreach ($results as $result) {
    $subtasks = explode('|', $result['subtasksTitles']); // Separa os títulos das subtarefas
    $cards[] = [
        'id' => $result['id'],
        'title' => $result['title'],
        'is_archived' => $result['is_archived'], // Adicione esta linha
        'responsible_users' => $result['responsible_users'],
        'subtaskCount' => $result['subtaskCount'],
        'subtasks' => $subtasks // Adiciona as subtarefas
    ];
}

// Retorne os dados como JSON
header('Content-Type: application/json');
echo json_encode($cards);
?>
