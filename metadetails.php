<?php
// Inclua o arquivo de conexão com o banco de dados
include 'db.php';

// Verifica se o ID da meta foi enviado
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $metaId = intval($_GET['id']);

    // Prepara a consulta para buscar os detalhes da meta
    $stmt = $pdo->prepare("SELECT * FROM metas WHERE id = ?");
    $stmt->execute([$metaId]);
    $meta = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($meta) {
        // Retornar os dados da meta em formato JSON
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'meta' => $meta
        ]);
    } else {
        // Retornar erro se não encontrar a meta
        echo json_encode([
            'success' => false,
            'message' => 'Meta não encontrada.'
        ]);
    }
} else {
    // Retornar erro se o ID não for válido
    echo json_encode([
        'success' => false,
        'message' => 'ID inválido.'
    ]);
}
?>
