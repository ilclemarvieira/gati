<?php
// pending_cards.php
include 'db.php';

$query = $pdo->prepare("
    SELECT * FROM cards WHERE is_archived = 0
");
$query->execute();
$results = $query->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode($results);
?>
