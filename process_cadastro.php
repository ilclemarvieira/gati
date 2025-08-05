<?php
session_start();
require 'db.php';

function valida_cpf($cpf) {
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    if (strlen($cpf) != 11 || preg_match('/(\d)\1{10}/', $cpf)) return false;
    for ($t = 9; $t < 11; $t++) {
        for ($d = 0, $c = 0; $c < $t; $c++) {
            $d += $cpf[$c] * (($t + 1) - $c);
        }
        $d = ((10 * $d) % 11) % 10;
        if ($cpf[$c] != $d) return false;
    }
    return true;
}

// Bloqueia bots (honeypot)
if (!empty($_POST['website'])) {
    $origem = $_POST['origin'] ?? 'cadastro_usuario';
    if ($origem == 'usuarios') {
        echo json_encode(['success' => false, 'message' => 'Bot detectado!']);
    } else {
        $_SESSION['error_message'] = "Bot detectado!";
        header('Location: cadastro_usuario.php');
    }
    exit;
}

// Verificação de reCAPTCHA apenas se não vier de usuarios.php
$origem = $_POST['origin'] ?? 'cadastro_usuario';
if ($origem !== 'usuarios') {
    $secret = '6LetkkUrAAAAAL4CdqbdC_6qcaNdqI3ukkUOmQsC'; // Sua secret do v3
    $captcha = $_POST['g-recaptcha-response'] ?? '';
    
    if (empty($captcha)) {
        $_SESSION['error_message'] = "Falha na verificação do reCAPTCHA.";
        header('Location: cadastro_usuario.php');
        exit;
    }

    $verify = file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret={$secret}&response={$captcha}");
    $response = json_decode($verify);

    if(!$response->success || $response->score < 0.5) { // score < 0.5 pode ser bot!
        $_SESSION['error_message'] = "Falha na verificação do reCAPTCHA.";
        header('Location: cadastro_usuario.php');
        exit;
    }
}

// Verificação de CSRF apenas se vier de usuarios.php (admin)
if ($origem === 'usuarios') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo json_encode(['success' => false, 'message' => 'Token CSRF inválido.']);
        exit;
    }
}

$cpfExistente = false;
$emailExistente = false;

if (
    !empty($_POST['nome']) &&
    !empty($_POST['cpf']) &&
    !empty($_POST['email']) &&
    !empty($_POST['senha'])
) {
    // Sanitização
    $nome = trim($_POST['nome']);
    $cpf = preg_replace('/[^0-9]/', '', $_POST['cpf']);
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $senha = $_POST['senha'];
    $empresaId = !empty($_POST['empresaId']) ? $_POST['empresaId'] : null;
    $setorId = !empty($_POST['setorId']) ? $_POST['setorId'] : null;
    $fotoPerfil = $_POST['fotoPerfil'] ?? null;

    // Validação forte
    if (!preg_match("/^[A-Za-zÀ-ÿ ']+$/u", $nome)) {
        $mensagemErro = "Nome inválido (apenas letras e espaços).";
    } elseif (!valida_cpf($cpf)) {
        $mensagemErro = "CPF inválido.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensagemErro = "E-mail inválido.";
    } elseif (
        strlen($senha) < 8 ||
        !preg_match('/[A-Z]/', $senha) ||
        !preg_match('/[a-z]/', $senha) ||
        !preg_match('/[0-9]/', $senha) ||
        !preg_match('/[^A-Za-z0-9]/', $senha)
    ) {
        $mensagemErro = "Senha fraca. Use no mínimo 8 caracteres, maiúscula, minúscula, número e símbolo.";
    } elseif (
        preg_match('/(http|www\.|bitcoin|free|consulting|bit\.ly|href|<|>)/i', $nome) ||
        preg_match('/(http|www\.|bitcoin|free|consulting|bit\.ly|href|<|>)/i', $email)
    ) {
        $mensagemErro = "Nome/E-mail contém termos proibidos.";
    }

    if (!empty($mensagemErro)) {
        if ($origem == 'usuarios') {
            echo json_encode(['success' => false, 'message' => $mensagemErro]);
        } else {
            $_SESSION['error_message'] = $mensagemErro;
            header('Location: cadastro_usuario.php');
        }
        exit;
    }

    // Checagem de duplicidade
    $stmt = $pdo->prepare("SELECT 1 FROM usuarios WHERE Cpf = ? OR E_mail = ?");
    $stmt->execute([$cpf, $email]);
    if ($stmt->rowCount() > 0) {
        $mensagemErro = 'Já existe um usuário cadastrado com o mesmo CPF ou E-mail.';
        if ($origem == 'usuarios') {
            echo json_encode(['success' => false, 'message' => $mensagemErro]);
        } else {
            $_SESSION['error_message'] = $mensagemErro;
            header('Location: cadastro_usuario.php');
        }
        exit;
    }

    // Criptografa senha
    $senhaCriptografada = password_hash($senha, PASSWORD_DEFAULT);
    $user_type = 8; // Perfil padrão
    
    // Se vem de usuarios.php (admin), usuário fica desbloqueado
    // Se vem de cadastro_usuario.php (público), fica bloqueado
    $bloqueado = ($origem === 'usuarios') ? 0 : 1;

    // Cadastro seguro
    $stmt = $pdo->prepare("INSERT INTO usuarios (Nome, Cpf, E_mail, Senha, PerfilAcesso, bloqueado, EmpresaId, SetorId, FotoPerfil) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if ($stmt->execute([$nome, $cpf, $email, $senhaCriptografada, $user_type, $bloqueado, $empresaId, $setorId, $fotoPerfil])) {
        $mensagemSucesso = 'Usuário cadastrado com sucesso!';
        // Implemente envio de e-mail de confirmação e token se desejar aqui
        if ($origem == 'usuarios') {
            echo json_encode(['success' => true, 'message' => $mensagemSucesso]);
        } else {
            $_SESSION['success_message'] = $mensagemSucesso;
            header('Location: login.php');
        }
    } else {
        if ($origem == 'usuarios') {
            echo json_encode(['success' => false, 'message' => "Erro ao cadastrar usuário."]);
        } else {
            $_SESSION['error_message'] = "Erro ao cadastrar usuário.";
            header('Location: cadastro_usuario.php');
        }
    }
    exit;
} else {
    $mensagemErro = "Todos os campos são obrigatórios.";
    if ($origem == 'usuarios') {
        echo json_encode(['success' => false, 'message' => $mensagemErro]);
    } else {
        $_SESSION['error_message'] = $mensagemErro;
        header('Location: cadastro_usuario.php');
    }
    exit;
}
?>