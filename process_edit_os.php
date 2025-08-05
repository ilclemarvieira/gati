<?php
include 'db.php';

// Verifica se o formulário foi enviado
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $os_id = $_POST['os_id'];

    // Busca o valor atual da OS no banco de dados
    $currentSql = "SELECT Valor FROM os WHERE Id = :os_id";
    $currentStmt = $pdo->prepare($currentSql);
    $currentStmt->execute([':os_id' => $os_id]);
    $currentData = $currentStmt->fetch(PDO::FETCH_ASSOC);
    $currentValor = $currentData['Valor'];  // Valor atual no banco

    $valor = $_POST['valor_numerico'] ?? $currentValor; // Usa o valor atual se o novo valor não for fornecido
    $valor = empty($valor) ? $currentValor : $valor; // Garante que o valor não é zerado

    // Outros dados do formulário
    $n_os = $_POST['n_os'];
    $nome_os = $_POST['nome_os'];
    $apf = $_POST['apf'];
    $dt_inicial = !empty($_POST['dt_inicial']) ? date('Y-m-d', strtotime($_POST['dt_inicial'])) : null;
    $prazo_entrega = !empty($_POST['prazo_entrega']) ? date('Y-m-d', strtotime($_POST['prazo_entrega'])) : null;
    $prioridade = $_POST['prioridade'];
    $status_inova = $_POST['status_inova'];
    $status_contratada = $_POST['status_contratada'];
    $responsavel = $_POST['responsavel'];
    $id_contratada = $_POST['id_contratada'];
    $os_paga = $_POST['os_paga'];
    $descricao = $_POST['descricao'];
    $observacao = $_POST['observacao'];

    // Atualiza os dados da OS
    $sql = "UPDATE os SET N_os = :n_os, Nome_os = :nome_os, Apf = :apf, Valor = :valor, Dt_inicial = :dt_inicial, Prazo_entrega = :prazo_entrega, Prioridade = :prioridade, Status_inova = :status_inova, Status_contratada = :status_contratada, Responsavel = :responsavel, Id_contratada = :id_contratada, Os_paga = :os_paga, Descricao = :descricao, Observacao = :observacao WHERE Id = :os_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':os_id' => $os_id,
        ':n_os' => $n_os,
        ':nome_os' => $nome_os,
        ':apf' => $apf,
        ':valor' => $valor,
        ':dt_inicial' => $dt_inicial,
        ':prazo_entrega' => $prazo_entrega,
        ':prioridade' => $prioridade,
        ':status_inova' => $status_inova,
        ':status_contratada' => $status_contratada,
        ':responsavel' => $responsavel,
        ':id_contratada' => $id_contratada,
        ':os_paga' => $os_paga,
        ':descricao' => $descricao,
        ':observacao' => $observacao
    ]);

    // Trata os uploads dos arquivos anexos, se houver
    if (!empty($_FILES['anexo_nf']['name'][0])) {
        $uploadDir = 'uploads/';
        foreach ($_FILES['anexo_nf']['name'] as $key => $name) {
            if ($_FILES['anexo_nf']['error'][$key] == 0) {
                $tmpName = $_FILES['anexo_nf']['tmp_name'][$key];
                $fileName = time() . '_' . basename($name);
                $uploadPath = $uploadDir . $fileName;

                if (move_uploaded_file($tmpName, $uploadPath)) {
                    // Insere o caminho do arquivo na tabela de anexos
                    $insertAnexo = "INSERT INTO os_anexos (os_id, arquivo) VALUES (:os_id, :arquivo)";
                    $stmtAnexo = $pdo->prepare($insertAnexo);
                    $stmtAnexo->execute([':os_id' => $os_id, ':arquivo' => $uploadPath]);
                } else {
                    echo "Erro ao enviar o arquivo: $name.";
                }
            }
        }
    }

    $_SESSION['mensagem_sucesso'] = 'OS atualizada com sucesso!';
    header('Location: os.php?success=edit');
} else {
    header('Location: os.php');
    exit;
}
?>
