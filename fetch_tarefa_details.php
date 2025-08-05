<?php
// Inclua seu arquivo de conexão com o banco de dados
include 'db.php';

// Checa se o ID da tarefa foi enviado e se é um número
if (isset($_POST['id']) && is_numeric($_POST['id'])) {
    $tarefaId = $_POST['id'];

    // Preparar a consulta SQL para buscar os detalhes da tarefa e informações dos usuários
    $query = $pdo->prepare("
        SELECT t.id, t.nome_tarefa, t.descricao_tarefa, u.Nome as nome_usuario, u2.Nome as nome_usuario_compartilhado
        FROM tarefas t
        LEFT JOIN usuarios u ON t.id_usuario = u.Id
        LEFT JOIN tarefas_compartilhadas tc ON t.id = tc.id_tarefa
        LEFT JOIN usuarios u2 ON tc.id_usuario_compartilhado = u2.Id
        WHERE t.id = :id
    ");
    $query->bindParam(':id', $tarefaId, PDO::PARAM_INT);
    $query->execute();

    // Fetch the details as an associative array
    $tarefa = $query->fetch(PDO::FETCH_ASSOC);

    if ($tarefa) {
        // Envio dos detalhes da tarefa em formato JSON
        echo json_encode(['success' => true, 'data' => $tarefa]);
    } else {
        // Nenhuma tarefa encontrada com o ID fornecido
        echo json_encode(['success' => false, 'error' => 'Nenhuma tarefa encontrada com o ID especificado.']);
    }
} else {
    // ID da tarefa não fornecido ou inválido
    echo json_encode(['success' => false, 'error' => 'ID da tarefa não fornecido ou inválido.']);
}

?>
