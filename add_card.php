<?php
session_start();
include 'db.php';

ob_start(); // Inicia o controle de buffer de saída
header('Content-Type: application/json'); // Define o cabeçalho como JSON

$response = array('success' => false);

if(isset($_POST['cardTitle'], $_POST['responsibleUsers'])) {
    $cardTitle = filter_var($_POST['cardTitle'], FILTER_SANITIZE_STRING);
    $responsibleUsers = filter_var($_POST['responsibleUsers'], FILTER_SANITIZE_STRING); // Sanitize a string of user IDs

    $stmt = $pdo->prepare("INSERT INTO cards (title, responsible_users) VALUES (?, ?)");
    $result = $stmt->execute([$cardTitle, $responsibleUsers]);
    
    if($result) {
        $response['success'] = true;
        $response['cardId'] = $pdo->lastInsertId();
        $response['cardTitle'] = $cardTitle;
        $response['responsibleUsers'] = $responsibleUsers;
    } else {
        $response['error'] = 'Não foi possível inserir a tarefa.';
    }
}

ob_end_clean(); // Limpa o buffer e desliga o controle de buffer de saída
echo json_encode($response);
?>
