<?php
require 'db.php'; // Inclua a conexão com o banco de dados

// Captura os dados enviados pelo formulário
$os_id = $_POST['os_id'];
$n_os = $_POST['n_os'];
$nome_os = $_POST['nome_os'];
$apf = $_POST['apf'];
$valor = $_POST['valor_numerico'];
$dt_inicial = $_POST['dt_inicial'];
$prazo_entrega = $_POST['prazo_entrega'];
$prioridade = $_POST['prioridade'];
$status_inova = $_POST['status_inova'];
$status_contratada = $_POST['status_contratada'] ?? 'Não Começou';
$id_contratada = $_POST['id_contratada'];
$descricao = $_POST['descricao'];
$os_paga = $_POST['os_paga'];
$observacao = $_POST['observacao'];

// Captura os IDs dos responsáveis e converte para string separada por vírgulas
$responsaveis = isset($_POST['responsavel']) ? implode(',', $_POST['responsavel']) : ''; 


// Validação e processamento dos campos adicionais
try {
    // Prepara a consulta SQL para atualizar os dados no banco de dados
    $stmt = $pdo->prepare("
    UPDATE bi 
    SET N_os = :n_os, Nome_os = :nome_os, Apf = :apf, Valor = :valor, Dt_inicial = :dt_inicial, 
        Prazo_entrega = :prazo_entrega, Prioridade = :prioridade, Status_inova = :status_inova, 
        Status_contratada = :status_contratada, Responsavel = :responsavel, Id_contratada = :id_contratada, 
        Descricao = :descricao, Os_paga = :os_paga, Observacao = :observacao
    WHERE Id = :os_id
");
    
    // Vincula os parâmetros
    $stmt->bindParam(':os_id', $os_id);
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
    header('Location: bi.php?status=updated');
    exit;
} catch (PDOException $e) {
    // Exibe mensagem de erro em caso de falha
    echo 'Erro ao editar o projeto: ' . $e->getMessage();
}
?>
