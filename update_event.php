<?php
session_start();
require 'db.php'; // Ajuste o caminho conforme necessário

// Verifique se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    // Redirecionar para página de login se não estiver logado
    header('Location: login.php');
    exit;
}

// Verifica se os dados do formulário foram enviados
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Colete os dados do formulário
    $eventId = $_POST['eventId'] ?? null;
    $title = $_POST['title'] ?? null;
    $start_time = $_POST['start_time'] ?? null;
    $description = $_POST['description'] ?? null;
    $start_date = $_POST['start_date'] ?? null;
    $end_date = $_POST['end_date'] ?? null;
    $category = $_POST['category'] ?? null;
    $link = $_POST['link'] ?? null; // Coleta o novo campo link

    // Validação simples dos dados (pode ser expandida conforme necessário)
    if (!$eventId || !$title || !$start_date) {
        echo "Dados obrigatórios faltando.";
        exit;
    }

    // Prepara a consulta de atualização incluindo o campo link
    $sql = "UPDATE eventos SET titulo = ?, horario_inicio = ?, descricao = ?, data_inicio = ?, data_fim = ?, categoria = ?, link = ? WHERE id = ?";
    $stmt = $pdo->prepare($sql);

    // Tenta executar a consulta preparada
    if ($stmt->execute([$title, $start_time, $description, $start_date, $end_date, $category, $link, $eventId])) {
        echo "Evento atualizado com sucesso!";
    } else {
        echo "Erro ao atualizar evento.";
    }
} else {
    // Caso o método de requisição não seja POST
    echo "Método de requisição inválido.";
}
?>
