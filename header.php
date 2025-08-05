<?php

include 'db.php';

// Verifique se o usuário está logado e obtenha o ID do usuário da sessão
if (isset($_SESSION['usuario_id'])) {
    $usuarioId = $_SESSION['usuario_id'];

    // Prepare a consulta SQL para buscar o nome e o e-mail do usuário
    $stmt = $pdo->prepare("SELECT Nome, E_mail FROM usuarios WHERE Id = ?");
    $stmt->execute([$usuarioId]);

    // Fetch the user data
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    // Se o usuário for encontrado, armazene o nome e o e-mail nas variáveis
    if ($usuario) {
        $nomeUsuarioLogado = $usuario['Nome'];
        $emailUsuarioLogado = $usuario['E_mail'];
    } else {
        // Se o usuário não for encontrado, use valores padrão
        $nomeUsuarioLogado = 'Usuário Não Encontrado';
        $emailUsuarioLogado = 'N/A';
    }
} else {
    // Se o usuário não estiver logado, redirecione para a página de login ou use valores padrão
    header('Location: login');
    exit;
    // Ou usar valores padrão
    // $nomeUsuarioLogado = 'Convidado';
    // $emailUsuarioLogado = 'N/A';
}


$usuarioId = $_SESSION['usuario_id'];
$stmt = $pdo->prepare("SELECT Id, Nome, E_mail, FotoPerfil FROM usuarios WHERE Id = ?");
$stmt->execute([$usuarioId]);
$usuarioLogado = $stmt->fetch(PDO::FETCH_ASSOC);

// Definindo o caminho relativo para a pasta de imagens
$imagePath = 'img/perfil/';
$defaultImage = 'default_image.jpg';

// Caminho completo para a imagem do perfil do usuário ou imagem padrão
$profileImageSrc = (!empty($usuarioLogado['FotoPerfil']) && file_exists($imagePath . $usuarioLogado['FotoPerfil']))
    ? $imagePath . $usuarioLogado['FotoPerfil']
    : $imagePath . $defaultImage;


?>

        <nav class="navbar top-navbar navbar-expand-md navbar-dark">
          <div class="navbar-header">
            <!-- This is for the sidebar toggle which is visible on mobile only -->
            <a
              class="nav-toggler waves-effect waves-light d-block d-md-none"
              href="javascript:void(0)"
              ><i class="ti-menu ti-close"></i
            ></a>
            <!-- ============================================================== -->
            <!-- Logo -->
            <!-- ============================================================== -->
            <a class="navbar-brand" href="index">
              <!-- Logo icon -->
              <b class="logo-icon">
                
                <img
                  src="assets/images/logo-icon.png"
                  alt="homepage"
                  class="dark-logo"
                />
                <!-- Light Logo icon -->
                <img
                  src="assets/images/logo-light-icon.png"
                  alt="homepage"
                  class="light-logo"
                />
              </b>
              <!--End Logo icon -->
              <!-- Logo text -->
              <span class="logo-text">
                <!-- dark Logo text -->
                <img
                  src="assets/images/logo-text.png"
                  alt="homepage"
                  class="dark-logo"
                />
                <!-- Light Logo text -->
                <img
                  src="assets/images/logo-light-text.png"
                  class="light-logo"
                  alt="homepage"
                />
              </span>
            </a>            
            <a
              class="topbartoggler d-block d-md-none waves-effect waves-light"
              href="javascript:void(0)"
              data-bs-toggle="collapse"
              data-bs-target="#navbarSupportedContent"
              aria-controls="navbarSupportedContent"
              aria-expanded="false"
              aria-label="Toggle navigation"
              ><i class="ti-more"></i
            ></a>
          </div>
          
          <div class="navbar-collapse collapse" id="navbarSupportedContent">
            
            <ul class="navbar-nav me-auto">
              <!-- This is  -->
              <li class="nav-item">
                <a
                  class="
                    nav-link
                    sidebartoggler
                    d-none d-md-block
                    waves-effect waves-dark
                  "
                  href="javascript:void(0)"
                  ><i class="ti-menu"></i
                ></a>
              </li>              
            </ul>            
            <ul class="navbar-nav">              
              <li class="nav-item dropdown">
                <a
                  class="nav-link dropdown-toggle waves-effect waves-dark"
                  href="#"
                  data-bs-toggle="dropdown"
                  aria-haspopup="true"
                  aria-expanded="false"
                >
                  <img
                    src="<?php echo $profileImageSrc; ?>"
                    alt="user"
                    width="30"
                    class="profile-pic rounded-circle"
                  />
                </a>
                <div
                  class="
                    dropdown-menu dropdown-menu-end
                    user-dd
                    animated
                    flipInY
                  "
                >
                  <div
                    class="
                      d-flex
                      no-block
                      align-items-center
                      p-3
                      bg-info
                      text-white
                      mb-2
                    "
                  >
                    <div class="">
                      <img
                        src="<?php echo $profileImageSrc; ?>"
                        alt="user"
                        class="rounded-circle"
                        width="60"
                      />
                    </div>
                    <div class="ms-2">
                        <h4 class="mb-0 text-white"><?php echo htmlspecialchars($nomeUsuarioLogado); ?></h4>
                        <p class="mb-0"><?php echo htmlspecialchars($emailUsuarioLogado); ?></p>
                    </div>
                  </div>
                  <a class="dropdown-item" href="perfil"
                    ><i
                      data-feather="user"
                      class="feather-sm text-info me-1 ms-1"
                    ></i>
                    Meu perfil</a
                  >
                 
                                   
                  <div class="dropdown-divider"></div>
                  <a class="dropdown-item" href="sair"
                    ><i
                      data-feather="log-out"
                      class="feather-sm text-danger me-1 ms-1"
                    ></i>
                    Logout</a
                  >
                  
                </div>
              </li>
              <!-- ============================================================== -->
              <!-- Language -->
              <!-- ============================================================== -->
              <li class="nav-item dropdown">
                <a
                  class="
                    nav-link
                    dropdown-toggle
                    text-muted
                    waves-effect waves-dark
                  "
                  href=""
                  data-bs-toggle="dropdown"
                  aria-haspopup="true"
                  aria-expanded="false"
                >
                  <i class="flag-icon flag-icon-br"></i
                ></a>
                
              </li>
            </ul>
          </div>
        </nav>
   