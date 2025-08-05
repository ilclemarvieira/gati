<?php
include 'db.php';
session_start();

// Verifique se o usuário está autenticado
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado.']);
    exit;
}

// Verifique se o ID da sprint foi fornecido
if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID da sprint não fornecido.']);
    exit;
}

$sprintId = $_GET['id'];

try {
    // Consulta para buscar a sprint pelo ID
    $stmt = $pdo->prepare("SELECT * FROM sprints WHERE id = :id");
    $stmt->execute([':id' => $sprintId]);
    $sprint = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($sprint) {
        // Retornar os dados da sprint
        echo json_encode([
            'success' => true,
            'id' => $sprint['id'],
            'titulo' => $sprint['titulo'],
            'data_inicio' => $sprint['data_inicio'],
            'data_fim' => $sprint['data_fim'],
            'descricao' => $sprint['descricao'], // Inclua outros campos se necessário
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Sprint não encontrada.']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao buscar sprint: ' . $e->getMessage()]);
}
?>
