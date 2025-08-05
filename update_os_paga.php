<?php
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['osId']) && isset($_POST['osPaga'])) {
        $osId = $_POST['osId'];
        $osPaga = $_POST['osPaga'];

        $stmt = $pdo->prepare("UPDATE os SET Os_paga = :osPaga WHERE Id = :osId");
        $stmt->bindParam(':osPaga', $osPaga, PDO::PARAM_INT);
        $stmt->bindParam(':osId', $osId, PDO::PARAM_INT);

        if ($stmt->execute()) {
            echo 'Status de pagamento atualizado com sucesso.';
        } else {
            echo 'Erro ao atualizar o status de pagamento.';
        }
    } else {
        echo 'Dados inválidos.';
    }
} else {
    echo 'Método de requisição inválido.';
}
?>
