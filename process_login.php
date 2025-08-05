<?php
session_start();
require 'db.php';

// Verificação do token CSRF
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die('Acesso negado devido à inconsistência do token.');
}

// Limpa CPF (aceita com ou sem máscara)
$cpf = isset($_POST['cpf']) ? preg_replace('/\D/', '', $_POST['cpf']) : '';
$senha = $_POST['senha'] ?? '';

if (!empty($cpf) && !empty($senha)) {
    $limite_tentativas = 3; // Número de tentativas permitidas
    $intervalo_bloqueio = 300; // Tempo de bloqueio em segundos (5 minutos)

    // Remove tentativas antigas (boa prática)
    $pdo->prepare("DELETE FROM login_attempts WHERE attempt_time < NOW() - INTERVAL 1 DAY")->execute();

    // Conta tentativas recentes
    $stmt = $pdo->prepare("SELECT COUNT(*) AS tentativas FROM login_attempts WHERE cpf = ? AND attempt_time > NOW() - INTERVAL ? SECOND");
    $stmt->execute([$cpf, $intervalo_bloqueio]);
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

    $tentativas = (int)($resultado['tentativas'] ?? 0);

    // Se excedeu tentativas, bloqueia por 5 minutos
    if ($tentativas >= $limite_tentativas) {
        $ultimo_tentativa_stmt = $pdo->prepare("SELECT MAX(attempt_time) AS ultimo_tentativa FROM login_attempts WHERE cpf = ?");
        $ultimo_tentativa_stmt->execute([$cpf]);
        $ultimo_tentativa = $ultimo_tentativa_stmt->fetch(PDO::FETCH_ASSOC);

        $segundos_desde_ultima = time() - strtotime($ultimo_tentativa['ultimo_tentativa']);
        $tempo_restante = $intervalo_bloqueio - $segundos_desde_ultima;
        $minutos = max(1, ceil($tempo_restante / 60));

        $_SESSION['error_message'] = "Muitas tentativas de login. Por favor, tente novamente em {$minutos} minuto(s).";
        header('Location: login');
        exit;
    }

    // Busca usuário
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE Cpf = ?");
    $stmt->execute([$cpf]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario && password_verify($senha, $usuario['Senha'])) {
        if ($usuario['bloqueado'] == 1) {
            $_SESSION['error_message'] = 'Sua conta está bloqueada. Você não pode acessar o sistema.';
            header('Location: login');
            exit;
        } else {
            // Login OK, limpa tentativas
            $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE cpf = ?");
            $stmt->execute([$cpf]);

            $_SESSION['usuario_id'] = $usuario['Id'];
            $_SESSION['PerfilAcesso'] = $usuario['PerfilAcesso'];
            $_SESSION['nome_usuario'] = $usuario['Nome'];
            $_SESSION['mostrar_mensagem_boas_vindas'] = false;

            // Redireciona de acordo com o perfil de acesso
            if (in_array($_SESSION['PerfilAcesso'], [1, 4, 9])) {
                header('Location: meuespaco');
            } else {
                header('Location: minhastarefas');
            }
            exit;
        }
    } else {
        // Registra tentativa de login falha
        $stmt = $pdo->prepare("INSERT INTO login_attempts (cpf, attempt_time) VALUES (?, NOW())");
        $stmt->execute([$cpf]);

        // Atualiza número de tentativas após a inserção
        $stmt = $pdo->prepare("SELECT COUNT(*) AS tentativas FROM login_attempts WHERE cpf = ? AND attempt_time > NOW() - INTERVAL ? SECOND");
        $stmt->execute([$cpf, $intervalo_bloqueio]);
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        $tentativas_atual = (int)($resultado['tentativas'] ?? 1);

        $tentativa_texto = match($tentativas_atual) {
            1 => "1ª tentativa de 3.",
            2 => "2ª tentativa de 3.",
            3 => "3ª tentativa de 3 (última tentativa).",
            default => "{$tentativas_atual}ª tentativa."
        };

        $_SESSION['error_message'] = "CPF ou senha inválidos. $tentativa_texto";

        // Se atingiu o limite, já mostra a mensagem de bloqueio ao recarregar
        if ($tentativas_atual >= $limite_tentativas) {
            $_SESSION['error_message'] = "Muitas tentativas de login. Por favor, tente novamente em 5 minuto(s).";
        }

        header('Location: login');
        exit;
    }
} else {
    $_SESSION['error_message'] = 'Por favor, preencha todos os campos.';
    header('Location: login');
    exit;
}
?>
