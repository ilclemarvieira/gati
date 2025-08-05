<?php
include 'db.php';

// Receber os dados do AJAX
$osId = $_POST['osId'];
$andamento = $_POST['andamento'];

// Validar os dados recebidos
if (ctype_digit($osId) && ctype_digit($andamento) && $andamento >= 0 && $andamento <= 5) {
    // Atualizar o andamento no banco de dados
    $stmt = $pdo->prepare("UPDATE bi SET Andamento = :andamento WHERE Id = :osId");
    $stmt->bindParam(':andamento', $andamento, PDO::PARAM_INT);
    $stmt->bindParam(':osId', $osId, PDO::PARAM_INT);

    if ($stmt->execute()) {
        echo "Andamento atualizado com sucesso!";
    } else {
        echo "Erro ao atualizar o andamento.";
    }
} else {
    echo "Dados inválidos.";
}
?>
