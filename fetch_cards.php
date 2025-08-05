<?php
session_start();
include 'db.php';

$isArchived = isset($_GET['is_archived']) ? (int)$_GET['is_archived'] : 0;
error_log("is_archived: " . $isArchived); // Log para verificar o valor recebido


$cards = $pdo->prepare("SELECT * FROM cards WHERE is_archived = :is_archived");
$cards->execute(['is_archived' => $isArchived]);
$cards = $cards->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($cards);
?>
