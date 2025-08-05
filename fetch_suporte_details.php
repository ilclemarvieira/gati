<?php
// Inclua seu arquivo de conexão com o banco de dados
include 'db.php';

// Checa se o ID foi enviado e se é um número
if (isset($_POST['id']) && is_numeric($_POST['id'])) {
    $suporteId = $_POST['id'];

    // Preparar a consulta SQL para buscar os detalhes do suporte com nomes de usuário e contratada
    $sql = "SELECT s.*, u.Nome as NomeSolicitante, c.Nome as NomeContratada
            FROM suporte s
            LEFT JOIN usuarios u ON s.Solicitado_por = u.Id
            LEFT JOIN contratadas c ON s.Para_contratada = c.Id
            WHERE s.Id = :id";
    $query = $pdo->prepare($sql);
    $query->bindParam(':id', $suporteId, PDO::PARAM_INT);
    $query->execute();

    // Busca os detalhes como um array associativo
    $suporte = $query->fetch(PDO::FETCH_ASSOC);

    if ($suporte) {
        // Envio dos detalhes do suporte em formato JSON
        echo json_encode(['success' => true, 'data' => $suporte]);
    } else {
        // Nenhum suporte encontrado com o ID fornecido
        echo json_encode(['success' => false, 'error' => 'Nenhum suporte encontrado com o ID especificado.']);
    }
} else {
    // ID não fornecido ou inválido
    echo json_encode(['success' => false, 'error' => 'ID do suporte não fornecido ou inválido.']);
}
?>
