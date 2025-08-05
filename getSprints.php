<?php
include 'db.php';

header('Content-Type: application/json');

// Consulta para obter os anos distintos das sprints (data_inicio)
$stmtAnos = $pdo->prepare("SELECT DISTINCT YEAR(data_inicio) as ano FROM sprints ORDER BY ano DESC"); 
$stmtAnos->execute();
$anosDisponiveis = $stmtAnos->fetchAll(PDO::FETCH_ASSOC);

// Obter o ano da query string, ou usar o ano atual se não estiver definido
$ano = isset($_GET['ano']) ? $_GET['ano'] : date('Y');

// Consulta para buscar as sprints com base na data_inicio no ano selecionado, ordenadas pela data_inicio em ordem descendente
$stmt = $pdo->prepare("SELECT * FROM sprints WHERE YEAR(data_inicio) = :ano ORDER BY data_inicio DESC"); 
$stmt->bindParam(':ano', $ano, PDO::PARAM_INT);
$stmt->execute();
$sprints = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Verificar se a consulta retornou resultados
if (empty($sprints)) {
    // Retornar uma mensagem de erro caso não haja sprints para o ano selecionado
    echo json_encode(['success' => false, 'message' => 'Nenhuma sprint encontrada para o ano selecionado.']);
    exit;
}

foreach ($sprints as &$sprint) {
    // Buscar as subtarefas desta sprint
    $sprint_id = $sprint['id'];
    $stmt2 = $pdo->prepare("
        SELECT si.id, si.tipo, si.item_id, si.position, si.descricao, os.N_os, os.Nome_os, os.Status_contratada
        FROM sprint_itens si
        LEFT JOIN os ON si.item_id = os.Id
        WHERE si.sprint_id = :sprint_id
        ORDER BY si.position ASC
    ");
    $stmt2->bindParam(':sprint_id', $sprint_id, PDO::PARAM_INT);
    $stmt2->execute();
    $tarefas = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    $sprint['tarefas'] = $tarefas;
}

// Retornar as sprints encontradas e os anos disponíveis
echo json_encode(['success' => true, 'data' => $sprints, 'anosDisponiveis' => $anosDisponiveis]);
?>