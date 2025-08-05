<?php

session_start();
include 'db.php';

// Verifica se o usuário está logado antes de prosseguir
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

// Verifica se o formulário foi submetido
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = $_SESSION['usuario_id']; // Pega o ID do usuário da sessão
    $nome = $_POST['nome'];
    $email = $_POST['email'];
    $password = $_POST['password'];

    try {
        $pdo->beginTransaction();
        
        $updateFields = [
            'Nome' => $nome,
            'E_mail' => $email,
        ];

        if (!empty($password)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $updateFields['Senha'] = $hashed_password;
        }

        if (isset($_FILES['image']) && $_FILES['image']['error'] == UPLOAD_ERR_OK) {
            $imagem = $_FILES['image'];
            $uploadDir = 'img/perfil/';
            $extensao = strtolower(pathinfo($imagem['name'], PATHINFO_EXTENSION));
            $nomeArquivo = "perfil_" . $id . "." . $extensao;
            $uploadFile = $uploadDir . $nomeArquivo;
            if (move_uploaded_file($imagem['tmp_name'], $uploadFile)) {
                // Redimensionar a imagem
                list($largura_orig, $altura_orig) = getimagesize($uploadFile);
                $imagemFinal = imagecreatetruecolor(80, 80);
                if ($extensao == 'jpg' || $extensao == 'jpeg') {
                    $imagemOrig = imagecreatefromjpeg($uploadFile);
                } else if ($extensao == 'png') {
                    $imagemOrig = imagecreatefrompng($uploadFile);
                }
                imagecopyresampled($imagemFinal, $imagemOrig, 0, 0, 0, 0, 80, 80, $largura_orig, $altura_orig);
                
                // Salvar a imagem redimensionada
                if ($extensao == 'jpg' || $extensao == 'jpeg') {
                    imagejpeg($imagemFinal, $uploadFile);
                } else if ($extensao == 'png') {
                    imagepng($imagemFinal, $uploadFile);
                }
                imagedestroy($imagemOrig);
                imagedestroy($imagemFinal);

                $updateFields['FotoPerfil'] = $nomeArquivo;
            }
        }

        // Atualizar o banco de dados com os campos modificados
        $setFields = '';
        foreach ($updateFields as $key => $value) {
            $setFields .= "`$key` = :$key, ";
        }
        $setFields = rtrim($setFields, ', ');

        $stmt = $pdo->prepare("UPDATE usuarios SET $setFields WHERE Id = :id");
        $updateFields['id'] = $id;
        $stmt->execute($updateFields);

        $pdo->commit();

        // Se o email foi alterado, atualize a sessão
        if ($email !== $_SESSION['email']) {
            $_SESSION['email'] = $email;
        }

        $_SESSION['mensagem'] = 'Perfil atualizado com sucesso.';
        header('Location: perfil.php');
        exit;

    } catch (PDOException $e) {
        $pdo->rollBack();
        die("Erro ao atualizar o usuário: " . $e->getMessage());
    }
} else {
    header('Location: perfil.php');
    exit;
}


?>
