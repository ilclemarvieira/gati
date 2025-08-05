<?php
include 'db.php';

$task_id = isset($_POST['task_id']) ? $_POST['task_id'] : 0;

if ($task_id > 0) {
    $stmt = $pdo->prepare("SELECT u.Id, u.Nome FROM tarefas_compartilhadas AS tc JOIN usuarios AS u ON tc.id_usuario_compartilhado = u.Id WHERE tc.id_tarefa = :task_id");

    $stmt->bindParam(':task_id', $task_id, PDO::PARAM_INT);
    $stmt->execute();
    $usuariosCompartilhados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($usuariosCompartilhados);
} else {
    echo json_encode([]);
}

?>
