<?php
// pegar-detalhes-card.php
include 'db.php';

// Certifique-se de que o ID do card foi enviado
if (isset($_GET['id'])) {
    $cardId = filter_var($_GET['id'], FILTER_SANITIZE_NUMBER_INT);

    // Prepare a consulta para evitar SQL Injection
    $stmt = $pdo->prepare("SELECT * FROM cards WHERE id = :cardId");
    $stmt->bindValue(':cardId', $cardId, PDO::PARAM_INT);
    $stmt->execute();
    $card = $stmt->fetch(PDO::FETCH_ASSOC);

    // Verifique se o card foi encontrado
    if ($card) {
        echo json_encode($card); // Retorna os dados do card em formato JSON
    } else {
        echo json_encode(['error' => 'Card não encontrado.']); // Retorna um erro se o card não for encontrado
    }
}
?>
