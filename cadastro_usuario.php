<?php
session_start();
?>
<!DOCTYPE html>
<html lang="pt-BR" dir="ltr">
<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>GATI - Gestão Ágil em TI</title>
    <link rel="icon" type="image/png" sizes="16x16" href="assets/images/favicon.png"/>
    <link href="dist/css/style.min.css" rel="stylesheet" />
    <style>
    .auth-box {
        padding: 20px;
        width: 100%;
        max-width: 500px;
        margin: auto;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        border-radius: 8px;
    }
    .form-floating { margin-bottom: 16px; }
    .form-control { height: auto; padding: 12px; }
    .btn { padding: 12px 24px; }
    .checkbox-primary { margin-bottom: 20px; }
    .auth-box .d-flex { padding: 0 20px 20px; }
    .auth-box .btn { width: calc(100% - 40px); }
    </style>
</head>
<body>
<div class="main-wrapper">
    <!-- Preloader -->
    <div class="preloader">
        <svg class="tea lds-ripple" width="37" height="48" viewBox="0 0 37 48" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M27.0819 17H3.02508C1.91076 17 1.01376 17.9059 1.0485 19.0197C1.15761 22.5177 1.49703 29.7374 2.5 34C4.07125 40.6778 7.18553 44.8868 8.44856 46.3845C8.79051 46.79 9.29799 47 9.82843 47H20.0218C20.639 47 21.2193 46.7159 21.5659 46.2052C22.6765 44.5687 25.2312 40.4282 27.5 34C28.9757 29.8188 29.084 22.4043 29.0441 18.9156C29.0319 17.8436 28.1539 17 27.0819 17Z" stroke="#1e88e5" stroke-width="2"></path>
            <path d="M29 23.5C29 23.5 34.5 20.5 35.5 25.4999C36.0986 28.4926 34.2033 31.5383 32 32.8713C29.4555 34.4108 28 34 28 34" stroke="#1e88e5" stroke-width="2"></path>
            <path id="teabag" fill="#1e88e5" fill-rule="evenodd" clip-rule="evenodd" d="M16 25V17H14V25H12C10.3431 25 9 26.3431 9 28V34C9 35.6569 10.3431 37 12 37H18C19.6569 37 21 35.6569 21 34V28C21 26.3431 19.6569 25 18 25H16ZM11 28C11 27.4477 11.4477 27 12 27H18C18.5523 27 19 27.4477 19 28V34C19 34.5523 18.5523 35 18 35H12C11.4477 35 11 34.5523 11 34V28Z"></path>
            <path id="steamL" d="M17 1C17 1 17 4.5 14 6.5C11 8.5 11 12 11 12" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" stroke="#1e88e5"></path>
            <path id="steamR" d="M21 6C21 6 21 8.22727 19 9.5C17 10.7727 17 13 17 13" stroke="#1e88e5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
        </svg>
    </div>
    <!-- Fim Preloader -->

    <div class="auth-wrapper d-flex no-block justify-content-center align-items-center"
         style="background: url(assets/images/big/auth-bg.jpg) no-repeat center center;">
        <div class="auth-box bg-white rounded">
            <div class="logo text-center">
                <span class="db"><img src="assets/images/logocadastro.png" alt="logo" style="width: 95px; height: 42px;" /></span><br>
                <h5 class="font-weight-medium mb-3 mt-1">Cadastrar Usuários</h5>
            </div>
            <div class="row mt-4">
                <div class="col-12">
                    <!-- Mensagens de Erro ou Sucesso -->
                    <?php if (isset($_SESSION['error_message'])): ?>
                        <div class="alert alert-danger" role="alert">
                            <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                        </div>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['success_message'])): ?>
                        <div class="alert alert-success" role="alert">
                            <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                        </div>
                    <?php endif; ?>
                    <!-- Formulário de Cadastro -->
                    <form class="form-horizontal" method="POST" action="process_cadastro.php" autocomplete="off" enctype="multipart/form-data" novalidate>
                        <!-- Honeypot anti-bot -->
                        <input type="text" name="website" style="display:none" tabindex="-1" autocomplete="off">
                        <input type="hidden" name="origin" value="cadastro_usuario">

                        <div class="form-floating mb-3">
                            <input type="text" class="form-control form-input-bg" id="nome" name="nome"
                                   placeholder="Nome Completo" required maxlength="100" autocomplete="off"
                                   pattern="[A-Za-zÀ-ÿ ']{3,100}" title="Apenas letras e espaços." />
                            <label for="nome">Nome</label>
                        </div>
                        <div class="form-floating mb-3">
                            <input type="text" class="form-control form-input-bg" id="cpf" name="cpf"
                                   placeholder="CPF" maxlength="14" required autocomplete="off"
                                   pattern="\d{3}\.\d{3}\.\d{3}-\d{2}" title="Digite um CPF válido (ex: 123.456.789-09)"/>
                            <label for="cpf">CPF</label>
                        </div>
                        <div class="form-floating mb-3">
                            <input type="email" class="form-control form-input-bg" id="email" name="email"
                                   placeholder="Email" required maxlength="80" autocomplete="off" />
                            <label for="email">Email</label>
                        </div>
                        <div class="form-floating mb-3">
                            <input type="password" class="form-control form-input-bg" id="password" name="senha"
                                   placeholder="Senha"
                                   required minlength="8" maxlength="60"
                                   pattern="^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^\da-zA-Z]).{8,}$"
                                   title="Mínimo 8 caracteres, incluindo maiúscula, minúscula, número e símbolo." autocomplete="new-password" />
                            <label for="password">Senha</label>
                        </div>
                        <!--
                        <div class="form-floating mb-3">
                            <input type="file" class="form-control form-input-bg" id="fotoPerfil" name="fotoPerfil" accept="image/*">
                            <label for="fotoPerfil">Foto de Perfil (opcional)</label>
                        </div>
                        -->
                        <div class="checkbox checkbox-primary mb-3">
                            <input id="checkbox-signup" type="checkbox" required/>
                            <label for="checkbox-signup"> Eu concordo com todos os Termos</label>
                        </div>
                        <!-- reCAPTCHA v3: Campo hidden + script -->
                        <input type="hidden" id="g-recaptcha-response" name="g-recaptcha-response">
                        <div class="d-flex align-items-stretch">
                            <button type="submit" class="btn btn-info d-block w-100">Cadastrar</button>
                        </div>
                    </form>
                    <!-- Fim do formulário -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Máscara de CPF -->
<script>
function applyCpfMask(value) {
    return value
        .replace(/\D/g, '')
        .replace(/(\d{3})(\d)/, '$1.$2')
        .replace(/(\d{3})(\d)/, '$1.$2')
        .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
}
document.getElementById('cpf').addEventListener('input', function(e) {
    e.target.value = applyCpfMask(e.target.value);
});
</script>
<!-- reCAPTCHA v3 Google -->
<script src="https://www.google.com/recaptcha/api.js?render=6LetkkUrAAAAAHRGcHqc6D9qZJxiWOYf4PwcvhnB"></script>
<script>
grecaptcha.ready(function() {
    grecaptcha.execute('6LetkkUrAAAAAHRGcHqc6D9qZJxiWOYf4PwcvhnB', {action: 'register'}).then(function(token) {
        document.getElementById('g-recaptcha-response').value = token;
    });
});
</script>
<script src="assets/libs/jquery/dist/jquery.min.js"></script>
<script src="assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
<script type="text/javascript">
    $(".preloader").fadeOut();
</script>
</body>
</html>
