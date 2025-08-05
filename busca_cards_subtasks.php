<?php
header('Content-Type: application/json');

include 'db.php'; // Assegure-se de incluir seu script de conexão com o banco de dados aqui

// Este código deve ser adaptado para corresponder à lógica de sua aplicação e estruturas de banco de dados
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['termoBusca'])) {
    $termoBusca = $_POST['termoBusca'];

    // Preparar a query para buscar os cards
    $queryCards = $pdo->prepare("SELECT * FROM cards WHERE title LIKE :termoBusca OR responsible_users LIKE :termoBusca");
    $queryCards->execute(['termoBusca' => '%'.$termoBusca.'%']);
    $cards = $queryCards->fetchAll(PDO::FETCH_ASSOC);

    // Preparar a query para buscar as subtasks
    $querySubtasks = $pdo->prepare("SELECT * FROM subtasks WHERE title LIKE :termoBusca");
    $querySubtasks->execute(['termoBusca' => '%'.$termoBusca.'%']);
    $subtasks = $querySubtasks->fetchAll(PDO::FETCH_ASSOC);

    // Retornar os resultados como JSON
    echo json_encode(['cards' => $cards, 'subtasks' => $subtasks]);
    exit;
} else {
    echo json_encode(['error' => 'No search term provided']);
    exit;
}

?>
