<?php
// dados_grafico.php

include 'db.php';

$ano = isset($_GET['year']) ? $_GET['year'] : date('Y');

if ($ano === 'all') {
    $sql = "SELECT status, COUNT(*) as count FROM cronograma GROUP BY status";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
} else {
    $sql = "SELECT status, COUNT(*) as count FROM cronograma WHERE ano = ? GROUP BY status";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$ano]);
}
$result = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pendente = 0;
$em_andamento = 0;
$concluida = 0;

foreach ($result as $row) {
    if ($row['status'] == 'Pendente') {
        $pendente = $row['count'];
    } elseif ($row['status'] == 'Em andamento') {
        $em_andamento = $row['count'];
    } elseif ($row['status'] == 'Concluída') {
        $concluida = $row['count'];
    }
}

echo json_encode([
    'pendente' => $pendente,
    'em_andamento' => $em_andamento,
    'concluida' => $concluida
]);
?>
