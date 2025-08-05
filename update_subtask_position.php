<?php
include 'db.php';

$subtaskId = $_POST['subtask_id'];
$oldSprintId = $_POST['old_sprint_id'];
$newSprintId = $_POST['new_sprint_id'];
$newPosition = $_POST['position'];

// Update both sprint_id and position
$query = "UPDATE sprint_itens SET sprint_id = ?, position = ? WHERE id = ?";

$stmt = $pdo->prepare($query);
$success = $stmt->execute([$newSprintId, $newPosition, $subtaskId]);

echo json_encode(['success' => $success]);
?>