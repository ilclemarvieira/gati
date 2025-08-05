<?php
// Verifica se o ID do empenho foi enviado através do método POST
if(isset($_POST['id'])) {
    // Inclui o arquivo de conexão com o banco de dados
    include 'db.php';

    // Obtém o ID do empenho a ser excluído
    $id = $_POST['id'];

    try {
        // Prepara e executa a query SQL para excluir o empenho com o ID fornecido
        $stmt = $pdo->prepare("DELETE FROM empenho WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        // Retorna uma resposta de sucesso em formato JSON
        echo json_encode(['success' => true, 'message' => 'Empenho excluído com sucesso']);
    } catch(PDOException $e) {
        // Em caso de erro, retorna uma resposta de erro em formato JSON
        echo json_encode(['success' => false, 'message' => 'Erro ao excluir empenho: ' . $e->getMessage()]);
    }
} else {
    // Se o ID do empenho não foi enviado, retorna uma resposta de erro em formato JSON
    echo json_encode(['success' => false, 'message' => 'ID do empenho não fornecido']);
}
?>
