<?php
include 'db.php';

$titulo = $_POST['titulo'] ?? '';
$data = $_POST['data'] ?? '';
$descricao = $_POST['descricao'] ?? '';

if ($titulo && $data && $descricao) {
    $stmt = $pdo->prepare("INSERT INTO sprints (titulo, data, descricao) VALUES (?, ?, ?)");
    if ($stmt->execute([$titulo, $data, $descricao])) {
        $sprintId = $pdo->lastInsertId();
        echo json_encode([
            'success' => true, 
            'data' => [
                'id' => $sprintId, 
                'titulo' => $titulo, 
                'data' => $data, 
                'descricao' => $descricao,
                'tarefas' => [] // Incluir uma lista de tarefas vazia
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao inserir a sprint']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Dados incompletos']);
}
?>
