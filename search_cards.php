<?php
// Inicia a sessão e inclui o arquivo de conexão com o banco
session_start();
include 'db.php';

// Função auxiliar para formatar CPF (pode ser removida se não for utilizada)
function formatarCPF($cpf) {
    return preg_replace("/^(\d{3})(\d{3})(\d{3})(\d{2})$/", "$1.$2.$3-$4", $cpf);
}

// Verifica se o parâmetro 'busca' está definido na URL
if (isset($_GET['busca'])) {
    $searchTerm = filter_var($_GET['busca'], FILTER_SANITIZE_STRING);

    // Prepara a consulta SQL com placeholders para evitar injeção de SQL
    $stmt = $pdo->prepare("SELECT * FROM cards WHERE title LIKE :search OR responsible_users LIKE :search");

    // Executa a consulta com o termo de busca
    $stmt->execute(['search' => '%' . $searchTerm . '%']);
    
    // Obtém todos os resultados
    $cards = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Define o cabeçalho como JSON para que o JavaScript possa entender
    header('Content-Type: application/json');
    
    // Envia os resultados como JSON
    echo json_encode($cards);

    // Encerra a execução para não continuar com o resto da página
    exit;
}

// Resto do código PHP da página...
?>
