<?php
include 'db.php';

// Verifica se o formulário foi enviado
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Recebe os dados do formulário
    $n_os = $_POST['n_os'];
    $nome_os = $_POST['nome_os'];
    $apf = empty($_POST['apf']) ? '0' : $_POST['apf'];
    $valor = $_POST['valor_numerico']; // Usa o valor numérico diretamente
    $dt_inicial = !empty($_POST['dt_inicial']) ? date('Y-m-d', strtotime($_POST['dt_inicial'])) : null;
    $prazo_entrega = !empty($_POST['prazo_entrega']) ? date('Y-m-d', strtotime($_POST['prazo_entrega'])) : '0000-00-00'; // ou use null se a coluna permitir valores nulos
    $prioridade = $_POST['prioridade'];
    $status_inova = $_POST['status_inova'];
    $status_contratada = $_POST['status_contratada'];
    $responsavel = $_POST['responsavel'];
    $id_contratada = $_POST['id_contratada'];
    $os_paga = $_POST['os_paga'];
    $descricao = $_POST['descricao'];
    $observacao = $_POST['observacao'];

    // Prepara a consulta SQL para inserir a OS
    $sql = "INSERT INTO os (N_os, Nome_os, Apf, Valor, Dt_inicial, Prazo_entrega, Prioridade, Status_inova, Status_contratada, Responsavel, Id_contratada, Os_paga, Descricao, Observacao) VALUES (:n_os, :nome_os, :apf, :valor, :dt_inicial, :prazo_entrega, :prioridade, :status_inova, :status_contratada, :responsavel, :id_contratada, :os_paga, :descricao, :observacao)";
    $stmt = $pdo->prepare($sql);

    // Vincula os parâmetros aos valores recebidos do formulário
    $stmt->execute([
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

    // Após inserir a OS, obtemos o ID da nova OS
    $osId = $pdo->lastInsertId();

    // Verifica se os anexos foram enviados
    if (isset($_FILES['anexo_nf']['name'][0]) && !empty($_FILES['anexo_nf']['name'][0])) {
        $uploadDir = 'uploads/';
        
        // Cria o diretório de uploads se não existir
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        foreach ($_FILES['anexo_nf']['name'] as $key => $name) {
            if ($_FILES['anexo_nf']['error'][$key] == 0) {
                $tmpName = $_FILES['anexo_nf']['tmp_name'][$key];
                $fileName = time() . "_" . basename($name); // Adiciona timestamp ao nome para evitar sobreposição
                $uploadPath = $uploadDir . $fileName;

                if (move_uploaded_file($tmpName, $uploadPath)) {
                    // Insere o caminho do arquivo na tabela de anexos
                    $insertAnexo = "INSERT INTO os_anexos (os_id, arquivo) VALUES (:os_id, :arquivo)";
                    $stmtAnexo = $pdo->prepare($insertAnexo);
                    $stmtAnexo->execute([':os_id' => $osId, ':arquivo' => $uploadPath]);
                } else {
                    echo "Falha ao enviar o arquivo: $name.";
                }
            }
        }
    }

    // Redireciona com mensagem de sucesso
    $_SESSION['mensagem_sucesso'] = 'OS cadastrada com sucesso!';
    header('Location: os.php?success=create');
} else {
    header('Location: os.php');
    exit;
}
?>
