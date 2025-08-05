<?php
require 'db.php'; // Conexão com o banco de dados

$mensagem = ''; // Inicializa a variável de mensagem

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];

    // Verificar se o e-mail existe na base de dados
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE E_mail = ?");
    $stmt->execute([$email]);
    $usuario = $stmt->fetch();

    if ($usuario) {
        $nomeUsuario = $usuario['Nome']; // Obtém o nome do usuário

        // Gerar token de segurança
        $token = bin2hex(random_bytes(50));
        $stmt = $pdo->prepare("UPDATE usuarios SET Token = ?, TokenExpiracao = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE E_mail = ?");
        $stmt->execute([$token, $email]);

        // Preparando o envio do e-mail
        $link = "https://sistemagati.com/redefinir_senha.php?token=$token";
        $to = $email;
        $subject = "Redefinição de Senha";
        $message = "
            <html>
            <head>
                <title>Redefinição de Senha</title>
            </head>
            <body>
                <h2>Olá, $nomeUsuario!</h2>
                <p>Parece que você esqueceu sua senha. Mas não se preocupe, vamos ajudá-lo(a) a criar uma nova!</p>
                <p>Para criar uma nova senha, basta clicar no botão abaixo:</p>
                <a href='$link' style='display: inline-block; background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Redefinir Senha</a>
                <p>Se você não solicitou a redefinição de senha, por favor, desconsidere este e-mail.</p>
            </body>
            </html>
        ";
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: no-reply@sistemagati.com" . "\r\n" .
                    "Reply-To: no-reply@sistemagati.com" . "\r\n" .
                    "X-Mailer: PHP/" . phpversion();

        if (mail($to, $subject, $message, $headers)) {
            $mensagem = "Instruções para redefinir sua senha foram enviadas para seu e-mail.";
        } else {
            $mensagem = "Falha ao enviar e-mail.";
        }
    } else {
        $mensagem = "E-mail não encontrado.";
    }
}
?>




<!DOCTYPE html>
<html dir="ltr">
  <head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <!-- Tell the browser to be responsive to screen width -->
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta
      name="keywords"
      content="wrappixel, admin dashboard, html css dashboard, web dashboard, bootstrap 5 admin, bootstrap 5, css3 dashboard, bootstrap 5 dashboard, material pro admin bootstrap 5 dashboard, frontend, responsive bootstrap 5 admin template, material design, material dashboard bootstrap 5 dashboard template"
    />
    <meta
      name="description"
      content="MaterialPro is powerful and clean admin dashboard template, inpired from Google's Material Design"
    />
    <meta name="robots" content="noindex,nofollow" />
    <title>GATI - Gestão Ágil em TI</title>
    <link
      rel="canonical"
      href="https://www.wrappixel.com/templates/materialpro/"
    />
    <!-- Favicon icon -->
    <link
      rel="icon"
      type="image/png"
      sizes="16x16"
      href="assets/images/favicon.png"
    />
    <!-- Custom CSS -->
    <link href="dist/css/style.min.css" rel="stylesheet" />
    <!-- HTML5 Shim and Respond.js IE8 support of HTML5 elements and media queries -->
    <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
    <!--[if lt IE 9]>
      <script src="https://oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js"></script>
      <script src="https://oss.maxcdn.com/libs/respond.js/1.4.2/respond.min.js"></script>
    <![endif]-->
  </head>

  <body>
    <div class="main-wrapper">
      <!-- -------------------------------------------------------------- -->
      <!-- Preloader - style you can find in spinners.css -->
      <!-- -------------------------------------------------------------- -->
      <div class="preloader">
        <svg
          class="tea lds-ripple"
          width="37"
          height="48"
          viewbox="0 0 37 48"
          fill="none"
          xmlns="http://www.w3.org/2000/svg"
        >
          <path
            d="M27.0819 17H3.02508C1.91076 17 1.01376 17.9059 1.0485 19.0197C1.15761 22.5177 1.49703 29.7374 2.5 34C4.07125 40.6778 7.18553 44.8868 8.44856 46.3845C8.79051 46.79 9.29799 47 9.82843 47H20.0218C20.639 47 21.2193 46.7159 21.5659 46.2052C22.6765 44.5687 25.2312 40.4282 27.5 34C28.9757 29.8188 29.084 22.4043 29.0441 18.9156C29.0319 17.8436 28.1539 17 27.0819 17Z"
            stroke="#1e88e5"
            stroke-width="2"
          ></path>
          <path
            d="M29 23.5C29 23.5 34.5 20.5 35.5 25.4999C36.0986 28.4926 34.2033 31.5383 32 32.8713C29.4555 34.4108 28 34 28 34"
            stroke="#1e88e5"
            stroke-width="2"
          ></path>
          <path
            id="teabag"
            fill="#1e88e5"
            fill-rule="evenodd"
            clip-rule="evenodd"
            d="M16 25V17H14V25H12C10.3431 25 9 26.3431 9 28V34C9 35.6569 10.3431 37 12 37H18C19.6569 37 21 35.6569 21 34V28C21 26.3431 19.6569 25 18 25H16ZM11 28C11 27.4477 11.4477 27 12 27H18C18.5523 27 19 27.4477 19 28V34C19 34.5523 18.5523 35 18 35H12C11.4477 35 11 34.5523 11 34V28Z"
          ></path>
          <path
            id="steamL"
            d="M17 1C17 1 17 4.5 14 6.5C11 8.5 11 12 11 12"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke="#1e88e5"
          ></path>
          <path
            id="steamR"
            d="M21 6C21 6 21 8.22727 19 9.5C17 10.7727 17 13 17 13"
            stroke="#1e88e5"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
          ></path>
        </svg>
      </div>
      <!-- -------------------------------------------------------------- -->
      <!-- Preloader - style you can find in spinners.css -->
      <!-- -------------------------------------------------------------- -->
      <!-- -------------------------------------------------------------- -->
      <!-- Login box.scss -->
      <!-- -------------------------------------------------------------- -->
      <div
        class="
          auth-wrapper
          d-flex
          no-block
          justify-content-center
          align-items-center
        "
        style="
          background: url(assets/images/big/auth-bg.jpg) no-repeat center
            center;">
        <div class="auth-box p-4 bg-white rounded">
    <div>
        <div class="logo text-center">
            <span class="db">
                <img src="assets/images/logocadastro.png" alt="logo" style="width: 95px; height: 42px;">
            </span><br><br>
            <h5 class="font-weight-medium mb-3 mt-1">Recuperar Senha</h5>
        </div>
        <!-- Form -->
        <div class="row">
            <div class="col-12">
                <?php if ($mensagem): ?>
                            <div class="alert alert-info" role="alert">
                                <?php echo $mensagem; ?>
                            </div>
                        <?php endif; ?>                        
                <form class="form-horizontal mt-3" action="esqueceu_senha.php" method="post">
                    <div class="mb-3 row">
                        <div class="col-12">
                            <input
                                class="form-control"
                                type="email"
                                id="email"
                                name="email"
                                required=""
                                placeholder="E-mail"
                            />
                        </div>
                    </div>
                    <div class="text-center">
                        <div class="col-xs-12">
                            <button class="btn d-block w-100 btn-info" type="submit">
                                Enviar
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

      </div>
      <!-- -------------------------------------------------------------- -->
      <!-- Login box.scss -->
      <!-- -------------------------------------------------------------- -->
    </div>
    <!-- -------------------------------------------------------------- -->
    <!-- All Required js -->
    <!-- -------------------------------------------------------------- -->
    <script src="assets/libs/jquery/dist/jquery.min.js"></script>
    <!-- Bootstrap tether Core JavaScript -->
    <script src="assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
    <!-- -------------------------------------------------------------- -->
    <!-- This page plugin js -->
    <!-- -------------------------------------------------------------- -->
    <script>
      // $('[data-bs-toggle="tooltip"]').tooltip();
      $(".preloader").fadeOut();
    </script>
  </body>
</html>



