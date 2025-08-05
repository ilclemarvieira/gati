<?php
session_start();
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_SESSION['usuario_id'], $_POST['card_id'], $_POST['posicao'])) {
    $usuario_id = $_SESSION['usuario_id'];
    $card_id = $_POST['card_id'];
    $novaPosicao = intval($_POST['posicao']);  // Convertendo para número inteiro direto na leitura

    $pdo->beginTransaction(); // Começar transação para garantir atomicidade
    try {
        // Verificar se existe algum card na posição nova
        $stmt = $pdo->prepare("SELECT card_id FROM posicoes_cards WHERE usuario_id = ? AND posicao = ?");
        $stmt->execute([$usuario_id, $novaPosicao]);
        $existingCardId = $stmt->fetchColumn();

        if ($existingCardId && $existingCardId != $card_id) {
            // Troca as posições dos dois cards se houver um card diferente na posição nova
            $stmt = $pdo->prepare("UPDATE posicoes_cards SET posicao = (CASE card_id WHEN ? THEN ? WHEN ? THEN ? ELSE posicao END) WHERE usuario_id = ? AND (card_id = ? OR card_id = ?)");
            $stmt->execute([$card_id, $novaPosicao, $existingCardId, $posicaoAntiga, $usuario_id, $card_id, $existingCardId]);
        } else {
            // Atualiza ou insere o card na nova posição se não houver troca
            $stmt = $pdo->prepare("INSERT INTO posicoes_cards (usuario_id, card_id, posicao) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE posicao = ?");
            $stmt->execute([$usuario_id, $card_id, $novaPosicao, $novaPosicao]);
        }

        $pdo->commit(); // Commitar a transação se tudo estiver correto
        echo json_encode(['status' => 'success']);
    } catch (PDOException $e) {
        $pdo->rollBack(); // Rollback se ocorrer algum erro
        echo json_encode(['status' => 'error', 'message' => 'Failed to save position.', 'errorInfo' => $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request or missing data.']);
}
?>
