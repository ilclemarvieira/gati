<?php
include 'db.php';

function getUpdatedData($pdo) {
    $selectedYear = date('Y'); // Sempre usa o ano atual

    $data = [];
    $yearCondition = " AND YEAR(Dt_inicial) = $selectedYear";

    // Se o ano selecionado não for nulo, ajusta a condição para filtrar por ano
    if ($selectedYear !== null) {
        $yearCondition = " AND YEAR(Dt_inicial) = :year";
    }

    // Lista de consultas para obter os dados atualizados, ajustadas para filtrar por ano, se aplicável
    $queries = [
        'totalOs' => "SELECT COUNT(*) FROM os WHERE 1=1" . $yearCondition,
        'totalBacklogs' => "SELECT COUNT(*) FROM backlog WHERE Encaminhado_os = 0" . str_replace("Dt_inicial", "Dt_criacao", $yearCondition),
        'totalBacklogBI' => "SELECT COUNT(*) FROM backlogbi WHERE Encaminhado_os = 0" . str_replace("Dt_inicial", "Dt_criacao", $yearCondition),
        'totalSuporte' => "SELECT COUNT(*) FROM suporte WHERE Status_suporte NOT IN ('Cancelada', 'Resolvida')" . str_replace("Dt_inicial", "Dt_criacao", $yearCondition),
        'totalSubtasks' => "SELECT COUNT(*) FROM subtasks" . str_replace("Dt_inicial", "created_at", $yearCondition),
        'totalCompletedTasks' => "SELECT COUNT(*) FROM subtasks WHERE last_edited_by = 1" . str_replace("Dt_inicial", "created_at", $yearCondition),
        'totalPendingTasks' => "SELECT COUNT(*) FROM subtasks WHERE last_edited_by IS NULL" . str_replace("Dt_inicial", "created_at", $yearCondition),
        'totalArchived' => "SELECT COUNT(*) FROM cards WHERE is_archived = 1" . str_replace("Dt_inicial", "created_at", $yearCondition),
        'totalValorOsPagas' => "SELECT SUM(Valor) FROM os WHERE Os_paga = 1" . $yearCondition,
        'totalValorOsNaoPagas' => "SELECT SUM(Valor) FROM os WHERE Os_paga = 0" . $yearCondition,
    ];

    foreach ($queries as $key => $query) {
        $stmt = $pdo->prepare($query);
        // Já que estamos sempre usando o ano atual, não precisamos verificar se $selectedYear é nulo
        $stmt->execute(); // Remova a ligação de parâmetro ':year' pois estamos inserindo diretamente
        $data[$key] = $stmt->fetchColumn();
    }

    return $data;
}

$updatedData = getUpdatedData($pdo);
echo json_encode($updatedData);
?>
