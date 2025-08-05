<?php
include 'db.php';

// Verifica se os parâmetros foram enviados
if (isset($_POST['osId']) && isset($_POST['os_paga'])) {
    $osId = intval($_POST['osId']);
    $os_paga = intval($_POST['os_paga']);

    try {
        // Prepara a consulta de atualização
        $stmt = $pdo->prepare("UPDATE bi SET Os_paga = :os_paga WHERE Id = :id");
        $stmt->bindParam(':os_paga', $os_paga, PDO::PARAM_INT);
        $stmt->bindParam(':id', $osId, PDO::PARAM_INT);

        // Executa a consulta
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Parâmetros ausentes.']);
}
?>
