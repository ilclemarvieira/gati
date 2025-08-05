<?php
include 'db.php'; // Certifique-se de que este arquivo estabelece a conexão com o banco de dados

header('Content-Type: application/json'); // Define o cabeçalho para retornar JSON

// Query para buscar o último sprint adicionado
$stmt = $pdo->query("SELECT * FROM sprints ORDER BY id DESC LIMIT 1");

// Verifica se encontramos um sprint
$sprint = $stmt->fetch(PDO::FETCH_ASSOC);
if ($sprint) {
    // Codifica o sprint em JSON e retorna
    echo json_encode([
        "success" => true,
        "data" => $sprint
    ]);
} else {
    // Retorna um erro se nenhum sprint foi encontrado
    echo json_encode([
        "success" => false,
        "message" => "Nenhum sprint encontrado"
    ]);
}

// Lembre-se de que, para que isso funcione, sua configuração do PDO deve estar com o modo de erro definido como exceção para que possa lidar com qualquer possível erro de banco de dados.
?>
