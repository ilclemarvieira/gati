<?php
include 'db.php'; // Inclui a conexão com o banco de dados

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $osId = $_POST['osId'];

    $sql = "DELETE FROM bi WHERE Id = :osId";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':osId', $osId, PDO::PARAM_INT);

    try {
        $stmt->execute();
        echo "Projeto BI excluído com sucesso!";
    } catch (PDOException $e) {
        echo "Erro ao excluir o projeto: " . $e->getMessage();
    }
}
?>
