<?php
require 'db.php'; // Inclua a conexão com o banco de dados

// Captura os dados enviados pelo formulário
$n_os = $_POST['n_os'];
$nome_os = $_POST['nome_os'];
$apf = $_POST['apf'];
$valor = $_POST['valor_numerico'];
$dt_inicial = $_POST['dt_inicial'];
$prazo_entrega = $_POST['prazo_entrega'];
$prioridade = $_POST['prioridade'];
$status_inova = $_POST['status_inova'];
$status_contratada = $_POST['status_contratada'];
$id_contratada = $_POST['id_contratada'];
$descricao = $_POST['descricao'];
$os_paga = $_POST['os_paga'];
$observacao = $_POST['observacao'];

// Captura os IDs dos responsáveis e converte para string separada por vírgulas
$responsaveis = isset($_POST['responsaveis']) ? implode(',', $_POST['responsaveis']) : ''; // Ajuste para capturar múltiplos responsáveis

try {
    $stmt = $pdo->prepare("
        INSERT INTO bi (N_os, Nome_os, Apf, Valor, Dt_inicial, Prazo_entrega, Prioridade, 
        Status_inova, Status_contratada, Responsavel, Id_contratada, Descricao, Os_paga, Observacao)
        VALUES (:n_os, :nome_os, :apf, :valor, :dt_inicial, :prazo_entrega, :prioridade, 
        :status_inova, :status_contratada, :responsavel, :id_contratada, :descricao, :os_paga, :observacao)
    ");
    
    // Vincula os parâmetros
    $stmt->bindParam(':n_os', $n_os);
    $stmt->bindParam(':nome_os', $nome_os);
    $stmt->bindParam(':apf', $apf);
    $stmt->bindParam(':valor', $valor);
    $stmt->bindParam(':dt_inicial', $dt_inicial);
    $stmt->bindParam(':prazo_entrega', $prazo_entrega);
    $stmt->bindParam(':prioridade', $prioridade);
    $stmt->bindParam(':status_inova', $status_inova);
    $stmt->bindParam(':status_contratada', $status_contratada);
    $stmt->bindParam(':responsavel', $responsaveis);
    $stmt->bindParam(':id_contratada', $id_contratada);
    $stmt->bindParam(':descricao', $descricao);
    $stmt->bindParam(':os_paga', $os_paga);
    $stmt->bindParam(':observacao', $observacao);
    
    // Executa a consulta
    $stmt->execute();

    // Redireciona após o sucesso
    header('Location: bi.php?status=success');
    exit;
} catch (PDOException $e) {
    // Exibe mensagem de erro em caso de falha
    echo 'Erro ao cadastrar o projeto: ' . $e->getMessage();
}
?>
