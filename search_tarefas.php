<?php
session_start();
include 'db.php';

header('Content-Type: application/json');

if (isset($_POST['search']) && strlen(trim($_POST['search'])) >= 3) {
    $search = '%' . trim($_POST['search']) . '%';
    $id_usuario = $_SESSION['usuario_id'] ?? 0;

    if ($id_usuario > 0) {
        $sql = "SELECT id, nome_tarefa, descricao_tarefa, is_important, is_complete 
        FROM tarefas 
        WHERE id_usuario = :id_usuario 
        AND (nome_tarefa LIKE :search1 OR descricao_tarefa LIKE :search2)";


        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id_usuario', $id_usuario, PDO::PARAM_INT);
$stmt->bindParam(':search1', $search, PDO::PARAM_STR);
$stmt->bindParam(':search2', $search, PDO::PARAM_STR);


        $stmt->execute();
        $tarefas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode($tarefas);
    } else {
        echo json_encode(['error' => 'ID de usuário não está definido na sessão.']);
    }
} else {
    echo json_encode(['error' => 'Por favor, digite pelo menos 3 caracteres para buscar.']);
}
?>
