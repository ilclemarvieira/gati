<?php
session_start();
include 'db.php';

$data = json_decode(file_get_contents('php://input'), true);
$response = ['hasUpdates' => false];

if(isset($data['subtaskIds'], $data['originalUpdatedAt']) && is_array($data['subtaskIds'])) {
    $subtaskIds = $data['subtaskIds'];
    $originalUpdatedAt = $data['originalUpdatedAt'];

    foreach ($subtaskIds as $id) {
    $stmt = $pdo->prepare("SELECT updated_at FROM subtasks WHERE id = ?");
    $stmt->execute([$id]);
    $subtask = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($subtask) {
        $dbUpdatedAt = new DateTime($subtask['updated_at']);
        $originalUpdatedAtDateTime = new DateTime($originalUpdatedAt[$id]);

        // Comparar os objetos DateTime
        if ($dbUpdatedAt != $originalUpdatedAtDateTime) {
            $response['hasUpdates'] = true;
            break;
        }
    }
}


echo json_encode($response);
