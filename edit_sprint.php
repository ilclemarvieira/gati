<?php
include 'db.php';

header('Content-Type: application/json');

$sprintId = $_POST['sprint_id'] ?? null;
$titulo = $_POST['titulo'] ?? null;
$dataInicio = $_POST['data_inicio'] ?? null;
$dataFim = $_POST['data_fim'] ?? null;

if (!$sprintId || !$titulo || !$dataInicio || !$dataFim) {
    echo json_encode(['success' => false, 'message' => 'Dados incompletos.']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE sprints SET titulo = :titulo, data_inicio = :data_inicio, data_fim = :data_fim WHERE id = :id");
    $stmt->bindParam(':titulo', $titulo, PDO::PARAM_STR);
    $stmt->bindParam(':data_inicio', $dataInicio, PDO::PARAM_STR);
    $stmt->bindParam(':data_fim', $dataFim, PDO::PARAM_STR);
    $stmt->bindParam(':id', $sprintId, PDO::PARAM_INT);
    $stmt->execute();

    // Calcular dias restantes
    $today = new DateTime();
    $endDate = new DateTime($dataFim);
    $interval = $today->diff($endDate);
    $daysLeft = $interval->invert ? 0 : $interval->days;

    echo json_encode(['success' => true, 'days_left' => $daysLeft]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao editar sprint: ' . $e->getMessage()]);
}
?>
