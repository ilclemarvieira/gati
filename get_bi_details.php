<?php
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $osId = $_POST['osId'];
    
    // Consulta para obter os detalhes da OS, sem buscar diretamente o nome dos responsáveis
    $sql = "SELECT b.*, c.Nome as NomeContratada 
            FROM bi b 
            LEFT JOIN contratadas c ON b.Id_contratada = c.Id 
            WHERE b.Id = :osId";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':osId', $osId, PDO::PARAM_INT);
    $stmt->execute();
    
    $osDetails = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($osDetails) {
        // Recupera os IDs dos responsáveis como array, separados por vírgulas
        $responsaveisIds = explode(',', $osDetails['Responsavel']);
        
        // Prepara a consulta para buscar os nomes dos responsáveis pelos IDs
        $placeholders = implode(',', array_fill(0, count($responsaveisIds), '?')); // Cria placeholders para a query
        $sqlResponsaveis = "SELECT Id, Nome FROM usuarios WHERE Id IN ($placeholders)";
        $stmtResponsaveis = $pdo->prepare($sqlResponsaveis);
        $stmtResponsaveis->execute($responsaveisIds);
        
        // Constrói um array associativo de ID para Nome
        $nomesResponsaveis = [];
        while ($row = $stmtResponsaveis->fetch(PDO::FETCH_ASSOC)) {
            $nomesResponsaveis[$row['Id']] = $row['Nome'];
        }
        
        // Adiciona os nomes dos responsáveis ao resultado
        $osDetails['NomesResponsaveis'] = $nomesResponsaveis;
    }

    // Retorna os detalhes da OS com os nomes dos responsáveis
    echo json_encode($osDetails);
}
?>
