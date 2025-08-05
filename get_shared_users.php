// get_shared_users.php
<?php
session_start();
include 'db.php'; // Certifique-se de ter um arquivo de conexão com o banco

$response = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $taskId = $data['task_id'];

    if (isset($_SESSION['usuario_id'])) {
        $sql = "SELECT u.id, u.Nome 
                FROM usuarios AS u
                INNER JOIN tarefas_compartilhadas AS tc ON u.id = tc.id_usuario_compartilhado
                WHERE tc.id_tarefa = :taskId";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['taskId' => $taskId]);

        $response = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

echo json_encode($response);
?>
