<?php
session_start();

if (!isset($_SESSION['usuario_id']) || $_SERVER['REQUEST_METHOD'] != 'POST') {
    header('Location: login.php');
    exit;
}

require 'db.php';

// Buscar eventos
$stmt = $pdo->query("SELECT * FROM eventos WHERE usuario_id = {$_SESSION['usuario_id']}");
$eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($eventos); // Retorna os eventos em formato JSON
?>
