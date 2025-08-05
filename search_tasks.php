<?php
session_start();
include 'db.php';

// search_tasks.php
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['searchTerm'])) {
    $searchTerm = '%' . $_POST['searchTerm'] . '%';
    $sql = "SELECT * FROM cards WHERE title LIKE :searchTerm OR responsible_users LIKE :searchTerm";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':searchTerm', $searchTerm);
    $stmt->execute();
    $cards = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $sql = "SELECT * FROM subtasks WHERE title LIKE :searchTerm";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':searchTerm', $searchTerm);
    $stmt->execute();
    $subtasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Aqui você combina os resultados de cards e subtasks de acordo com sua lógica de negócio.
    // Por exemplo, você pode querer retornar apenas os cards que possuem subtasks correspondentes ao termo de busca.

    echo json_encode(['success' => true, 'cards' => $cards]);
} else {
    echo json_encode(['success' => false, 'error' => 'Nenhum termo de busca fornecido.']);
}

?>
