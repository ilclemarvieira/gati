<?php
session_start();
include 'db.php';
include 'autenticacao.php';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];
    $stmt = $conn->prepare("SELECT email, user_type, nome, bloqueado, password FROM user WHERE email = ?"); // Otimizado para buscar apenas colunas necessárias
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows == 1) {
        $row = $result->fetch_assoc();
        if ($row['bloqueado'] == 1) {
            $error = "Usuário bloqueado temporariamente, favor fazer contato com o suporte Chat Bizz: chatbizz@trendsads.net";
        } elseif (password_verify($password, $row['password'])) {
            $_SESSION['email'] = $email;
            $_SESSION['user_type'] = $row['user_type'];
			$_SESSION['nome'] = $row['nome'];
            
            if ($row['user_type'] === 'admin') {
                header("Location: admin.php");
            } elseif ($row['user_type'] === 'cliente') {
                header("Location: cliente.php");
            }
            
            exit();
        } else {
            $error = "Senha incorreta";
        }
    } else {
        $error = "Usuário não encontrado";
    }
    $stmt->close();
    $conn->close();
}
?>
<!DOCTYPE html>
<html dir="ltr">
  <head>
    <meta charset="utf-8" />
    <!-- TODO: Implementar cacheamento para arquivos estáticos -->
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <!-- Tell the browser to be responsive to screen width -->
    <!-- TODO: Otimizar o carregamento de CSS e JavaScript (minificar, carregar de forma assíncrona, etc.) -->
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta
      name="keywords"
      content="Potencialize sua Comunicação com Chat Bizz e Tenha seu Próprio Chat GPT Personalizado!"
    />
    <meta
      name="description"
      content="Potencialize sua Comunicação com Chat Bizz e Tenha seu Próprio Chat GPT Personalizado!"
    />
    <meta name="robots" content="noindex,nofollow" />
    <title>GATI - Gestão Ágil em TI</title>
    <!-- Favicon icon -->
    <!-- TODO: Implementar carregamento progressivo de imagens (lazy loading) se necessário -->
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
    <script>
      window.onload = function () {
        var url = "https://chatbizz.com.br";
        var description =
          "Potencialize sua Comunicação com Chat Bizz e Tenha seu Próprio Chat GPT Personalizado!";
        var logo = "assets/images/logo-light-icon.png";
        var title = "Chat Bizz";
        var img = "assets/images/logo-light-icon.png";
        var preview =
          '<!DOCTYPE html><html><head><meta charset="utf-8" />
    <!-- TODO: Implementar cacheamento para arquivos estáticos --><meta http-equiv="X-UA-Compatible" content="IE=edge" /><meta name="viewport" content="width=device-width, initial-scale=1" /><title>' +
          title +
          '</title><meta name="description" content="' +
          description +
          '" /><meta property="og:title" content="' +
          title +
          '" /><meta property="og:description" content="' +
          description +
          '" /><meta property="og:image" content="' +
          img +
          '" /><meta property="og:url" content="' +
          url +
          '" /><meta property="og:type" content="website" /></head><body></body></html>';
        var parser = new DOMParser();
        var htmlDoc = parser.parseFromString(preview, "text/html");
        var head = htmlDoc.head;
        var meta = document.createElement("meta");
        meta.setAttribute("property", "og:image");
        meta.setAttribute("content", logo);
        head.appendChild(meta);
        var titleTag = htmlDoc.getElementsByTagName("title")[0];
        titleTag.innerHTML = title;
        var body = htmlDoc.body;
        var script = document.createElement("script");
        script.innerHTML =
          'window.location.href = "' + url + '";';
        body.appendChild(script);
        var styles = htmlDoc.createElement("style");
        styles.innerHTML = "body { display: none; }";
        head.appendChild(styles);
        document.documentElement.appendChild(htmlDoc.documentElement);
        setTimeout(function () {
          document.body.style.display = "block";
       
 }, 2000);
      };
    </script>
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
      <div class="row auth-wrapper gx-0">
        <div class="col-lg-4 col-xl-3 auth-box-2 on-sidebar" style="background-color: #000">
            <div class="h-100 d-flex align-items-center justify-content-center">
                <div class="row justify-content-center text-center">
                    <div class="col-md-7 col-lg-12 col-xl-9">
                        <div>
                            <span class="db">
                                <img src="assets/images/logo-light-icon.png" alt="logo" style="width: 70px; height: 48px;" />
                            </span>
                            <span class="db">
                                <img src="assets/images/logo-light-text.png" alt="logo" style="width: 140px; height: 29px;" />
                            </span>
                        </div>
                        <h2 class="text-white mt-2 fw-light">
                            Faça login no GATI - Gestão Ágil em TI
                        </h2>
                        <p class="fs-4 mt-4" style="color: #ccccccd6">
                            Acesso ao sistema de gestão e acompanhamento de projetos de TI.
                        </p>
                        <!-- O botão abaixo provavelmente abrirá um chat ou algo semelhante, ajuste conforme necessário -->
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-8 col-xl-9 d-flex align-items-center justify-content-center">
            <div class="row justify-content-center w-100 mt-4 mt-lg-0">
                <div class="col-lg-6 col-xl-3 col-md-7">
                    <div class="card" id="loginform">
                        <div class="card-body">
                            <h2>Acessar GATI</h2>
                            <p class="text-muted fs-4">
                                Novo por aqui?
                                <a href="cadastro_usuario.php" id="to-register" style="font-weight: bold;">Crie uma conta</a>
                            </p>
                            <?php if (isset($error)) : ?>
                            <p class="error"><?php echo $error; ?></p>
                            <?php endif; ?>
                            <form class="form-horizontal mt-4 pt-4 needs-validation" novalidate method="post" action="process_login.php">
                                <div class="form-floating mb-3">
                                    <input type="text" class="form-control form-input-bg" id="cpf" name="cpf" placeholder="CPF" required>
                                    <label for="cpf">CPF</label>
                                    <div class="invalid-feedback">Informe seu CPF</div>
                                </div>
                                <div class="form-floating mb-3">
                                    <input type="password" class="form-control form-input-bg" id="senha" name="senha" placeholder="Senha" required>              
                                    <label for="senha">Senha</label>
                                    <div class="invalid-feedback">Informe sua senha</div>
                                </div>
                                <div class="d-flex align-items-center mb-3">
                                    <div class="ms-auto">
                                        <a href="esqueceu_senha.php" class="fw-bold">Esqueceu a senha?</a>
                                    </div>
                                </div>
                                <div class="d-flex align-items-stretch button-group mt-4 pt-2">
                                    <button type="submit" class="btn btn-info btn-lg px-4" value="Entrar">
                                        Acessar
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
		
		<button class="btn btn-info btn-lg px-4 abrir-chat" style="position: fixed; bottom: 20px; right: 20px; z-index: 9999; border-radius: 50px;">
    <i class="fas fa-comments"></i> CHAT BIZZ
</button>
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
    <script>
      $(".preloader").fadeOut();
      // ---------------------------
      // Login and Recover Password
      // ---------------------------
      $("#to-recover").on("click", function () {
        $("#loginform").hide();
        $("#recoverform").fadeIn();
      });
      $("#to-login").on("click", function () {
        $("#loginform").fadeIn();
        $("#recoverform").hide();
      });
      $("#to-register").on("click", function () {
        $("#loginform").hide();
        $("#registerform").fadeIn();
      });
      $("#to-login2").on("click", function () {
        $("#loginform").fadeIn();
        $("#registerform").hide();
      });
      // Example starter JavaScript for disabling form submissions if there are invalid fields
      (function () {
        "use strict";
        // Fetch all the forms we want to apply custom Bootstrap validation styles to
        var forms = document.querySelectorAll(".needs-validation");
        // Loop over them and prevent submission
        Array.prototype.slice.call(forms).forEach(function (form) {
          form.addEventListener(
            "submit",
            function (event) {
              if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
              }
              form.classList.add("was-validated");
            },
            false
          );
        });
      })();
    </script>
	  
	  
	<script>
$(document).ready(function() {
    $('.abrir-chat').on('click', function(e) {
        e.preventDefault();
        abrirChat();
    });
});
function abrirChat() {
    var chatUrl = 'https://chatbizz.com.br/?chatlink=chatbizz';
    var chatBoxContent = 
        '<div class="chat-header" style="background-color: #272b34; color: #fff; padding: 0.5rem; position: relative;">' +
            '<h5 class="modal-title" style="color: #ccc; margin: 0;"><i class="fas fa-comments"></i> Assistente Virtual - Chat Bizz</h5>' +
            '<button type="button" class="btn-close" style="position: absolute; right: 0.5rem; top: 38%; transform: translateY(-50%); color: #ccc; background: none; border: none; font-size: 1.5rem;" onclick="$(\'#chatBox\').hide();"><i class="fas fa-times"></i></button>' +
        '</div>' +
        '<div class="chat-body p-0" style="height: calc(90% - 2.5rem);">' +
            '<iframe src="' + chatUrl + '" style="width: 100%; height: 100%; border: none;"></iframe>' +
        '</div>';
    var chatBox = $(
        '<div id="chatBox" style="position: fixed; bottom: 1rem; right: 1rem; max-width: 600px; width: 100%; z-index: 9999; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3); border-radius: 10px; background-color: #272b34;">' + chatBoxContent + '</div>'
    );
    if (!$('#chatBox').length) {
        $('body').append(chatBox);
    } else {
        $('#chatBox').show();
    }
    
    ajustarChat();
}
// Função para ajustar o chat
function ajustarChat() {
    var alturaJanela = $(window).height();
    var alturaChat = alturaJanela * 0.9; // 90% da altura da janela
    $('#chatBox').css('height', alturaChat + 'px');
    $('.chat-body').css('height', (alturaChat - $('.chat-header').outerHeight(true)) + 'px');
    if ($(window).width() <= 768) {
        $('#chatBox').css({
            'bottom': '0',
            'right': '0',
            'transform': 'none',
            'width': '100%',
            'max-width': '100%'
        });
    } else {
        $('#chatBox').css({
            'bottom': '1rem',
            'right': '1rem',
            'transform': 'none',
            'width': '100%',
            'max-width': '600px'
        });
    }
}
// Ajustar o chat quando a janela for redimensionada
$(window).on('resize', ajustarChat);
</script>
  </body>
</html>
