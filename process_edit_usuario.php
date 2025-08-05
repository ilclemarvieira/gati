<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Recebe os campos
    $id        = $_POST['id'];
    $nome      = trim($_POST['nome']);
    $cpf       = trim($_POST['cpf']);
    $email     = trim($_POST['email']);
    $senha     = trim($_POST['senha'] ?? ''); // Nova senha (opcional)
    $empresaId = !empty($_POST['empresaId']) ? $_POST['empresaId'] : null;
    $setorId   = !empty($_POST['setorId'])   ? $_POST['setorId']   : null;

    // 2. Converte o perfil para inteiro (valor enviado no <option value="X">)
    //    Se algo estranho vier nulo ou vazio, cai em 0
    $perfil = isset($_POST['perfil']) 
        ? intval($_POST['perfil']) 
        : 0;

    // 3. Validações básicas
    if (empty($nome) || empty($cpf) || empty($email) || $perfil === 0) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Todos os campos obrigatórios devem ser preenchidos.']);
        exit;
    }

    // 4. Validação de senha (se fornecida)
    if (!empty($senha)) {
        if (strlen($senha) < 8 ||
            !preg_match('/[A-Z]/', $senha) ||
            !preg_match('/[a-z]/', $senha) ||
            !preg_match('/[0-9]/', $senha) ||
            !preg_match('/[^A-Za-z0-9]/', $senha)) {
            
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'Senha fraca. Use no mínimo 8 caracteres, incluindo maiúscula, minúscula, número e símbolo.']);
            exit;
        }
    }

    // 5. Prepara o UPDATE
    if (!empty($senha)) {
        // Se senha foi fornecida, inclui no UPDATE
        $senhaCriptografada = password_hash($senha, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
          UPDATE usuarios SET
            Nome         = :nome,
            Cpf          = :cpf,
            E_mail       = :email,
            Senha        = :senha,
            PerfilAcesso = :perfil,
            EmpresaId    = :empresaId,
            SetorId      = :setorId
          WHERE Id = :id
        ");
        
        $ok = $stmt->execute([
          ':nome'      => $nome,
          ':cpf'       => $cpf,
          ':email'     => $email,
          ':senha'     => $senhaCriptografada,
          ':perfil'    => $perfil,
          ':empresaId' => $empresaId,
          ':setorId'   => $setorId,
          ':id'        => $id
        ]);
    } else {
        // Se senha não foi fornecida, não inclui no UPDATE (mantém a atual)
        $stmt = $pdo->prepare("
          UPDATE usuarios SET
            Nome         = :nome,
            Cpf          = :cpf,
            E_mail       = :email,
            PerfilAcesso = :perfil,
            EmpresaId    = :empresaId,
            SetorId      = :setorId
          WHERE Id = :id
        ");
        
        $ok = $stmt->execute([
          ':nome'      => $nome,
          ':cpf'       => $cpf,
          ':email'     => $email,
          ':perfil'    => $perfil,
          ':empresaId' => $empresaId,
          ':setorId'   => $setorId,
          ':id'        => $id
        ]);
    }

    // 6. Retorno JSON
    header('Content-Type: application/json; charset=utf-8');
    if ($ok) {
        $mensagem = !empty($senha) ? 'Usuário e senha atualizados com sucesso.' : 'Usuário atualizado com sucesso.';
        echo json_encode(['success' => true, 'message' => $mensagem]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao atualizar usuário.']);
    }
}