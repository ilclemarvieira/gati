<?php

include 'db.php';
$osId = $_GET['osId'] ?? 0;
$stmt = $pdo->prepare("SELECT * FROM bi WHERE Id = :osId");
$stmt->execute([':osId' => $osId]);
$osData = $stmt->fetch(PDO::FETCH_ASSOC);
echo json_encode($osData);
?>