<?php
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $osId = $_POST['osId'];
    
    $sql = "SELECT o.*, u.Nome as NomeResponsavel, c.Nome as NomeContratada 
            FROM os o 
            LEFT JOIN usuarios u ON o.Responsavel = u.Id 
            LEFT JOIN contratadas c ON o.Id_contratada = c.Id 
            WHERE o.Id = :osId";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':osId', $osId, PDO::PARAM_INT);
    $stmt->execute();
    
    $osDetails = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode($osDetails);
}
?>
