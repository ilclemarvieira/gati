<?php
// Desativar exibição de erros para evitar respostas HTML
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Definir cabeçalho para JSON
header('Content-Type: application/json');

// Incluir a conexão com o banco de dados
include 'db.php';

$response = ['success' => false, 'message' => 'Erro inesperado.'];

try {
    // Verificar se os dados necessários foram enviados
    if (!isset($_POST['id']) || !isset($_POST['nome'])) {
        throw new Exception('Dados incompletos.');
    }

    $id = $_POST['id']; // ID do item a ser atualizado
    $item_id = $_POST['nome']; // ID da nova OS

    // Validar IDs recebidos
    if (!is_numeric($id) || !is_numeric($item_id) || intval($id) <= 0 || intval($item_id) <= 0) {
        throw new Exception('ID da OS inválido recebido.');
    }

    // Atualizar o item da sprint no banco de dados
    $stmt = $pdo->prepare("UPDATE sprint_itens SET item_id = :item_id WHERE id = :id");
    $stmt->bindParam(':item_id', $item_id, PDO::PARAM_INT);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);

    if ($stmt->execute()) {
        // Obter os detalhes da OS
        $osStmt = $pdo->prepare("SELECT N_os, Nome_os FROM os WHERE Id = :item_id");
        $osStmt->bindParam(':item_id', $item_id, PDO::PARAM_INT);
        $osStmt->execute();
        $osData = $osStmt->fetch(PDO::FETCH_ASSOC);

        if ($osData) {
            $response = [
                'success' => true,
                'id' => $id,
                'nome' => $osData['N_os'],
                'nome_os' => $osData['Nome_os']
            ];
        } else {
            throw new Exception('Erro ao buscar detalhes da OS.');
        }
    } else {
        throw new Exception('Erro ao editar o item da sprint.');
    }
} catch (Exception $e) {
    // Log do erro e definir a mensagem de resposta
    error_log("Erro ao editar subtarefa: " . $e->getMessage());
    $response['message'] = $e->getMessage();
}

// Retornar a resposta em formato JSON
echo json_encode($response);
exit;
?>
