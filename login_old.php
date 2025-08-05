<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>iNOVA ERP - Login</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f4f4;
            padding: 20px;
        }

        .login-container {
            background: #fff;
            max-width: 300px;
            margin: 50px auto;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .form-group {
            margin-bottom: 15px;
        }

        label {
            display: block;
            margin-bottom: 5px;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }

        input[type="submit"] {
            width: 100%;
            padding: 10px;
            border: none;
            border-radius: 4px;
            background: #333;
            color: white;
            cursor: pointer;
        }

        input[type="submit"]:hover {
            background: #555;
        }

        .link-container {
            text-align: center;
            margin-top: 15px;
        }

        a {
            color: #333;
            text-decoration: none;
        }

        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <form action="process_login.php" method="post">
            <h2>Login no iNOVA ERP</h2>
            
            <div class="form-group">
                <label for="cpf">CPF:</label>
                <input type="text" name="cpf" id="cpf" required>
            </div>
            
            <div class="form-group">
                <label for="senha">Senha:</label>
                <input type="password" name="senha" id="senha" required>
            </div>
            
            <input type="submit" value="Entrar">
        </form>
        <div class="link-container">
            <a href="cadastro_usuario.php">Criar uma conta</a>
        </div>
    </div>


   <script>
       
       function applyCpfMask(value) {
  return value
    .replace(/\D/g, '') // Remove tudo o que não é dígito
    .replace(/(\d{3})(\d)/, '$1.$2') // Coloca um ponto entre o terceiro e o quarto dígito
    .replace(/(\d{3})(\d)/, '$1.$2') // Coloca um ponto entre o terceiro e o quarto dígitos de novo (para o segundo bloco de números)
    .replace(/(\d{3})(\d{1,2})$/, '$1-$2'); // Coloca um hífen antes dos dois últimos dígitos
}

// Adiciona a máscara de CPF ao campo
document.getElementById('cpf').addEventListener('input', function(e) {
  e.target.value = applyCpfMask(e.target.value);
});


   </script>

       <!-- Outras partes do seu HTML -->
    <script>
        function applyCpfMask(value) {
          return value
            .replace(/\D/g, '') // Remove tudo o que não é dígito
            .replace(/(\d{3})(\d)/, '$1.$2') // Coloca um ponto entre o terceiro e o quarto dígito
            .replace(/(\d{3})(\d)/, '$1.$2') // Coloca um ponto entre o terceiro e o quarto dígitos de novo (para o segundo bloco de números)
            .replace(/(\d{3})(\d{1,2})$/, '$1-$2'); // Coloca um hífen antes dos dois últimos dígitos
        }

        // Adiciona a máscara de CPF ao campo
        document.getElementById('cpf').addEventListener('input', function(e) {
          e.target.value = applyCpfMask(e.target.value);
        });
    </script>
</body>
</html>



</body>
</html>
