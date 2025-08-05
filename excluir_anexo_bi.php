<?php
include 'db.php'; // Substitua pelo caminho correto do seu script de conexão com o banco

// Define o caminho base onde os anexos são armazenados
define('CAMINHO_ANEXOS', __DIR__ . '/uploads/bi');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['id'])) {
        echo json_encode(['success' => false, 'message' => 'ID do anexo não fornecido.']);
        exit;
    }

    $anexoId = $_POST['id'];
    $sql = "SELECT arquivo FROM bi_anexos WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id', $anexoId, PDO::PARAM_INT);
    $stmt->execute();
    $anexo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$anexo) {
        echo json_encode(['success' => false, 'message' => 'Anexo não encontrado.']);
        exit;
    }

    $arquivo = CAMINHO_ANEXOS . $anexo['arquivo'];

    $sql = "DELETE FROM bi_anexos WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    if ($stmt->execute([':id' => $anexoId])) {
        if (file_exists($arquivo)) {
            unlink($arquivo);
            echo json_encode(['success' => true, 'message' => 'Anexo removido com sucesso.']);
        } else {
            echo json_encode(['success' => true, 'message' => 'Registro removido, mas o arquivo físico não foi encontrado.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Falha ao remover o registro do banco de dados.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Método de requisição inválido.']);
}
?>
