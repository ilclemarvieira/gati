<?php
include 'db.php';

$sprints = $pdo->query("SELECT * FROM sprints")->fetchAll(PDO::FETCH_ASSOC);
foreach ($sprints as $index => $sprint) {
    $sub_tarefas = $pdo->prepare("SELECT * FROM sub_tarefas WHERE sprint_id = ?");
    $sub_tarefas->execute([$sprint['id']]);
    $sprints[$index]['tarefas'] = $sub_tarefas->fetchAll(PDO::FETCH_ASSOC);
}

header('Content-Type: application/json');
echo json_encode($sprints);
?>
