<?php
session_start();
include 'db.php'; // Inclua o banco de dados com segurança.

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $cpf = filter_input(INPUT_POST, 'cpf', FILTER_SANITIZE_STRING);
    $senha = filter_input(INPUT_POST, 'senha', FILTER_SANITIZE_STRING);
    $csrf_token = $_POST['csrf_token'];

    // Verifica o token CSRF
    if (!hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        http_response_code(403);
        exit("Operação não autorizada.");
    }

    // Valida o CPF
    if (!preg_match("/^\d{3}\.\d{3}\.\d{3}\-\d{2}$/", $cpf)) {
        $_SESSION['error_message'] = "CPF inválido.";
        header("Location: login.php");
        exit;
    }

    $stmt = $conn->prepare("SELECT Id, Senha, PerfilAcesso FROM usuarios WHERE Cpf = ?");
    $stmt->bind_param("s", $cpf);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (password_verify($senha, $user['Senha'])) {
            $_SESSION['user_id'] = $user['Id'];
            $_SESSION['perfil_acesso'] = $user['PerfilAcesso'];
            header("Location: dashboard.php");
            exit;
        } else {
            $_SESSION['error_message'] = "Senha incorreta.";
        }
    } else {
        $_SESSION['error_message'] = "Usuário não encontrado.";
    }

    $stmt->close();
    $conn->close();
}

// Gera um token CSRF
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
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
      content="Sua plataforma de Gestão Ágil em TI"
    />
    <meta
      name="description"
      content="Sua plataforma de Gestão Ágil em TI"
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
                                <img src="assets/images/logologin.png" alt="logo" style="width: 225px; height: 98px;" />
                            </span>                            
                        </div>
                        <br>
                        <h2 class="text-white mt-2 fw-light">
                            Sua plataforma de <span class="font-weight-medium" style="line-height: 36px"><b style="background-color: #529efb">Gestão Ágil</b></span> em TI
                        </h2>
                        <p class="fs-4 mt-4" style="color: #ccccccd6">
                            Transforme a gestão de TI com o GATI. Conecte sua equipe, otimize operações e acelere a execução de projetos. Tudo o que você precisa, em um só lugar, para elevar a eficiência ao próximo nível.
                        </p>

                        
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-8 col-xl-9 d-flex align-items-center justify-content-center">
            <div class="row justify-content-center w-100 mt-4 mt-lg-0">
                <div class="col-lg-6 col-xl-3 col-md-7">
                    <div class="card" id="loginform">
                        <div class="card-body">
                            <h2>Acessar o gati</h2>
                            <p class="text-muted fs-4">
                                Novo por aqui?
                                <a href="cadastro_usuario" id="to-register" style="font-weight: bold;">Crie uma conta</a>
                            </p>
                             <!-- Exibição de mensagens de erro ou sucesso -->
                            <?php if (isset($_SESSION['error_message'])): ?>
                                <div class="alert alert-danger" role="alert">
                                    <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                                </div>
                            <?php elseif (isset($_SESSION['success_message'])): ?>
                                <div class="alert alert-success" role="alert">
                                    <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                                </div>
                            <?php endif; ?>
                                        <form class="form-horizontal mt-4 pt-4 needs-validation" novalidate method="post" action="process_login.php">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                <div class="form-floating mb-3">
                    <input type="text" class="form-control form-input-bg" id="cpf" name="cpf" placeholder="CPF" maxlength="14" required autocomplete="off">
                    <label for="cpf">CPF</label>
                    <div class="invalid-feedback">Informe seu CPF</div>
                </div>

                <div class="form-floating mb-3">
                    <input type="password" class="form-control form-input-bg" id="senha" name="senha" placeholder="Senha" required autocomplete="current-password">
                    <label for="senha">Senha</label>
                    <div class="invalid-feedback">Informe sua senha</div>
                </div>
                <div class="d-flex align-items-center mb-3">
                    <div class="ms-auto">
                        <a href="esqueceu_senha" class="fw-bold">Esqueceu a senha?</a>
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
		
		
      <!-- -------------------------------------------------------------- -->
      <!-- Login box.scss -->
      <!-- -------------------------------------------------------------- -->
    </div>
    <!-- -------------------------------------------------------------- -->
    <!-- All Required js -->
    <!-- -------------------------------------------------------------- -->
    <!-- Scripts necessários -->
    <script src="assets/libs/jquery/dist/jquery.min.js"></script>
    <script src="assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            // Esconder o preloader
            $(".preloader").fadeOut();

            // Configurar manipuladores de eventos para alternar entre formulários
            $("#to-recover").on("click", function() {
                $("#loginform").hide();
                $("#recoverform").fadeIn();
                sessionStorage.setItem('formAberto', 'recoverform');
            });

            $("#to-login").on("click", function() {
                $("#recoverform").hide();
                $("#loginform").fadeIn();
                sessionStorage.setItem('formAberto', 'loginform');
            });

            // Aqui você pode adicionar os manipuladores para os outros botões...

            // Verificar se um formulário específico estava aberto e mostrá-lo novamente
            var formAberto = sessionStorage.getItem('formAberto');
            if(formAberto) {
                $('.form-content').hide();
                $('#' + formAberto).show();
            }

            // Validação do formulário
            var forms = document.querySelectorAll('.needs-validation');
            Array.prototype.slice.call(forms).forEach(function(form) {
                form.addEventListener('submit', function(event) {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                }, false);
            });

            // Limpar a sessão ao sair da página para não persistir o estado do formulário
            window.onbeforeunload = function() {
                sessionStorage.removeItem('formAberto');
            };
        });

        // Máscara de CPF no input
    function aplicarMascaraCPF(valor) {
        return valor.replace(/\D/g, '')
            .replace(/(\d{3})(\d)/, '$1.$2')
            .replace(/(\d{3})(\d)/, '$1.$2')
            .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
    }
    document.getElementById('cpf').addEventListener('input', function(e) {
        e.target.value = aplicarMascaraCPF(e.target.value);
    });
</script>


  </body>
</html>
