<?php
include 'db.php';

header('Content-Type: application/json');

$titulo = $_POST['titulo'];
$data_inicio = $_POST['data_inicio'];
$data_fim = $_POST['data_fim'];
$descricao = $_POST['descricao'] ?? '';

try {
    $stmt = $pdo->prepare("INSERT INTO sprints (titulo, data_inicio, data_fim, descricao) VALUES (:titulo, :data_inicio, :data_fim, :descricao)");
    $stmt->bindParam(':titulo', $titulo);
    $stmt->bindParam(':data_inicio', $data_inicio);
    $stmt->bindParam(':data_fim', $data_fim);
    $stmt->bindParam(':descricao', $descricao);
    $stmt->execute();

    $sprintId = $pdo->lastInsertId();

    // Retornar a sprint criada
    $stmt = $pdo->prepare("SELECT * FROM sprints WHERE id = :id");
    $stmt->bindParam(':id', $sprintId, PDO::PARAM_INT);
    $stmt->execute();
    $sprint = $stmt->fetch(PDO::FETCH_ASSOC);
    $sprint['tarefas'] = [];

    echo json_encode(['success' => true, 'data' => $sprint]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao cadastrar a sprint: ' . $e->getMessage()]);
}
?>
