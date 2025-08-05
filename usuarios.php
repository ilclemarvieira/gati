<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}
include 'db.php';

$perfilAcesso = $_SESSION['PerfilAcesso'] ?? null;
function verificarPermissao($perfisPermitidos) {
    global $perfilAcesso;
    if (!in_array($perfilAcesso, $perfisPermitidos)) {
        $paginaRedirecionamento = $_SERVER['HTTP_REFERER'] ?? 'index.php';
        header('Location: ' . $paginaRedirecionamento);
        exit;
    }
}
$perfisPermitidos = [1]; // Somente perfil 1 (Admin)
verificarPermissao($perfisPermitidos);

// Gera token CSRF se não existir
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Formata CPF
function formatarCPF($cpf) {
    $cpf = preg_replace("/\D/", '', $cpf);
    if (strlen($cpf) === 11) {
        return substr($cpf, 0, 3) . '.' .
               substr($cpf, 3, 3) . '.' .
               substr($cpf, 6, 3) . '-' .
               substr($cpf, 9, 2);
    }
    return $cpf;
}

// Busca todas as empresas
$stmt = $pdo->prepare("SELECT Id, Nome FROM contratadas ORDER BY Nome");
$stmt->execute();
$empresas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Busca todos os setores
$setores = $pdo->query("SELECT id, nome_do_setor FROM setores ORDER BY nome_do_setor")->fetchAll(PDO::FETCH_ASSOC);

// Busca todos os usuários com empresa e setor
$usuarios = $pdo->query("
    SELECT
      u.*,
      c.Nome            AS NomeEmpresa,
      s.id              AS SetorId,
      s.nome_do_setor   AS NomeSetor
    FROM usuarios AS u
    LEFT JOIN contratadas AS c ON u.EmpresaId = c.Id
    LEFT JOIN setores     AS s ON u.SetorId    = s.id
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html dir="ltr" lang="pt">

  <head>
    <?php include 'head.php'?>

    <style>
    .modal-content {
        /* Estilos para o conteúdo do modal */
    }

    .os-details p {
        /* Estilos para os parágrafos dos detalhes da OS */
    }

    .anexo-container {
        margin-top: 20px;
       
    }

    .anexo-container img, .anexo-container iframe {
        max-width: 100%; /* Garantir que o anexo não ultrapasse a largura do modal */
        height: auto;
    }
  .highlighted {
    background-color: #ffff0026;
}

/* Estado normal das linhas destacadas */
.content-table tbody tr.highlighted {
    background-color: #ffff0026;
}

/* Estado de hover para todas as linhas */
.content-table tbody tr:hover {
    background-color: #f5f5f5; /* ou qualquer outra cor que deseja para o hover */
}

/* Estado de hover especificamente para linhas destacadas */
.content-table tbody tr.highlighted:hover {
    background-color: #ffff0026 !important; /* Mantém a cor de fundo amarela mesmo no hover */
}

.filter-container {
    display: flex;
    flex-direction: row;
    flex-wrap: wrap;
    gap: 15px; /* Aumentar o espaçamento */
    align-items: center;
    background-color: #272b34; /* Fundo escuro para o container */
    padding: 20px; /* Aumentar o padding */
    border-radius: 5px;
    margin-bottom: 20px; /* Espaçamento abaixo do filtro */

}

.filter-container select, .filter-container input[type="text"] {
    flex-grow: 1; /* Permitir que os campos cresçam */
    padding: 12px; /* Aumentar o padding */
    border-radius: 4px;
    border: 1px solid #666;
    background-color: #323743; /* Fundo escuro para os campos */
    color: #a1aab2; /* Cor do texto */
    font-size: 14px;
    margin: 0px 5px;
}

.filter-container select:focus, .filter-container input[type="text"]:focus {
    border-color: #007bff; /* Cor da borda ao focar */
}

.filter-container button {
    padding: 12px 15px; /* Ajustar o padding */
    border-radius: 4px;
    border: 1px solid #4a5f7c; /* Borda sutil */
    background-color: #4a5f7c; /* Cor de fundo do botão */
    color: #e6e6e6; /* Cor do texto */
    cursor: pointer;
    text-transform: uppercase; /* Estilo do texto */
    font-weight: bold;
    transition: background-color 0.3s, border-color 0.3s, color 0.3s; /* Efeito de transição suave */
    margin: 0px 5px;
}

.filter-container button:hover {
    background-color: #627d9a; /* Cor de fundo do botão ao passar o mouse */
    border-color: #627d9a; /* Cor da borda ao passar o mouse */
    color: #ffffff; /* Cor do texto ao passar o mouse */
}

/* Estilos responsivos para telas menores */
@media (max-width: 768px) {
    .filter-container {
        flex-direction: column; /* Campos em coluna para telas menores */
    }

    .filter-container select, 
    .filter-container input[type="text"], 
    .filter-container button {
        flex-basis: 100%; /* Ocupar toda a largura */
        margin-bottom: 10px; /* Adiciona um espaço abaixo de cada campo */
    }

    /* Último item não deve ter margin-bottom para não adicionar espaço extra após o último elemento */
    .filter-container button {
        margin-bottom: 0; 
    }
}

.filter-container input[type="text"] {
    /* Outras propriedades existentes... */
    font-size: 15px; /* Aumenta o tamanho da fonte */
    color: #a1aab2; /* Cor da fonte mais clara */
    padding: 12px; /* Aumenta o padding para dar mais espaço dentro do campo */
    width: 270px; /* Ajustado para ocupar 100% da largura do container */
    margin: 10px 0px;
}

/* Para garantir que o campo não fique muito grande em telas menores */
@media (max-width: 768px) {
    .filter-container input[type="text"] {
        font-size: 14px; /* Tamanho da fonte um pouco menor em telas pequenas */
    }
}




/* Estilos para os ícones e espaçamento */
.round-lg {
  width: 40px; /* Ajusta o tamanho da circunferência */
  height: 40px; /* Ajusta o tamanho da circunferência */
  display: flex;
  align-items: center;
  justify-content: center;
  margin-right: 15px; /* Espaçamento entre o ícone e o texto */
}
.fa {
  font-size: 14px; /* Reduz o tamanho do ícone */
}
 .card {
    cursor: pointer; /* Adiciona o cursor de mão */
  }

.card-body {
  display: flex;
  align-items: center; /* Centraliza verticalmente o conteúdo */
}
.card-title {
  margin-bottom: 0; /* Remove a margem padrão do título */
  font-size: 15px;
  Font-weight:bold;
}
.card-text {
  margin-top: 4px; /* Espaçamento entre o título e o texto */
  font-size: 16px;
}
.text-muted {
  display: block; /* Faz com que o texto ocupe sua própria linha */
}



.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0,0,0,0.4);
    padding-top: 10px;
}

.modal-content {
    background-color: #fff;
    margin: 2% auto;
    padding: 20px;
    border-radius: 8px;
    width: 50%; /* Ajuste a largura conforme necessário */
    box-shadow: 0 4px 10px rgba(0,0,10,10.15);
}


/* Modal Header Styling */
.modal-content h2 {
    color: #333;
    font-size: 24px;
    font-weight: 500;
    border-bottom: 1px solid #e3e3e354;
    padding-bottom: 10px;
    margin-bottom: 10px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.close {
    color: #aaaaaa;
    float: right;
    font-size: 30px;
    padding-bottom: 10px;
    margin-bottom: 10px;
    font-weight: bold;
    cursor: pointer;
}

.close:hover,
.close:focus {
    color: #000;
    text-decoration: none;
    cursor: pointer;
}

.form-row {
    display: flex;
    flex-direction: column;
    margin-bottom: 15px;
}

.form-row label {
    font-weight: 600;
    margin-bottom: 5px;
}

.form-row input[type=text],
.form-row input[type=date],
.form-row select,
.form-row textarea {
    width: 100%;
    padding: 15px;
    margin-bottom: 15px;
    border: 1px solid #ccc;
    border-radius: 4px;
    box-sizing: border-box;
    transition: border-color 0.3s;
}

.form-row input[type=text]:focus,
.form-row input[type=date]:focus,
.form-row select:focus,
.form-row textarea:focus {
    border-color: #007bff;
    outline: none;
}

.submit-btn {
    width: auto;
    padding: 10px 20px;
    font-weight: 500;
    background-color: rgba(252,75,108,.5)!important;
    color: white;
    border: rgba(252,75,108,.5)!important;
    border-radius: 40px;
    cursor: pointer;
    transition: background-color 0.2s;
    align-self: flex-end;
}

.submit-btn:hover {
    background-color: #0056b3;
}

/* Responsividade para telas menores */
@media screen and (max-width: 768px) {
    .modal-content {
        width: 90%;
        padding: 20px;
    }

    .form-row input[type=text],
    .form-row input[type=date],
    .form-row select,
    .form-row textarea {
        padding: 10px;
    }
}


/* Details Paragraph Styling */
.os-details p {
    padding: 2px 0;
    border-bottom: 1px solid #eeeeee0d;
    display: flex;
    color: #fff;
    justify-content: space-between; /* Align the label and value on opposite ends */
}

.os-details b {
    font-weight: 500;
    color: #333;
    margin-right: 15px; /* Spacing between label and value */
}

/* Anexo Container Styling */
.anexo-container {
    margin-top: 20px;
}

/* Flex Container for Two Column Layout */
.flex-container {
    display: flex;
    justify-content: space-between;
    flex-wrap: wrap; /* Allow wrapping for smaller screens */
}

/* Flex Item Styling */
.flex-item {
    flex-basis: 48%; /* Two items per row */
    margin-bottom: 10px; /* Spacing between items */
}

/* Responsive adjustments */
@media screen and (max-width: 768px) {
    .flex-item {
        flex-basis: 100%; /* Stack items on top of each other on small screens */
    }
}

/* Style the form rows */
.form-row {
    display: flex;
    flex-direction: column;
    margin-bottom: 15px;
}

.form-row label {
    font-weight: 600;
    margin-bottom: 5px;
}

.form-row input[type=text],
.form-row input[type=date],
.form-row select,
.form-row textarea {
    width: 100%;
    padding: 15px;
    margin-bottom: 15px;
    border: 1px solid #ccc;
    border-radius: 4px;
    box-sizing: border-box;
    transition: border-color 0.3s;
}

.form-row input[type=text]:focus,
.form-row input[type=date]:focus,
.form-row select:focus,
.form-row textarea:focus {
    border-color: #007bff;
    outline: none;
}


.readonly {
        background-color: #b3c0c7 !important; /* Cor de fundo cinza para indicar que está desativado */
        color: #272b34; !important; /* Cor do texto para indicar que está desativado */
        font-weight: bold;
        cursor: not-allowed !important; /* Cursor de não permitido para indicar que o campo é somente leitura */
    }



/* Responsive layout - when the screen is less than 600px wide, make the modal-content full width */
@media screen and (max-width: 600px) {
    .modal-content {
        width: 95%;
    }
}

/* Estilo para linhas finalizadas */
.finalizada {
    text-decoration: line-through;
    opacity: 0.5; /* Opcional: para tornar o texto mais claro indicando que está concluído */
}

.linha-taxada {
    text-decoration: line-through;
    opacity: 0.7; /* Opcional para tornar a linha mais clara */
}

</style> 
   
  </head>

  <body>    
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
    <!-- Main wrapper - style you can find in pages.scss -->
    <!-- -------------------------------------------------------------- -->
    <div id="main-wrapper">
      <!-- -------------------------------------------------------------- -->
      <!-- Topbar header - style you can find in pages.scss -->
      <!-- -------------------------------------------------------------- -->
      <header class="topbar">
        <?php include 'header.php'?>   
      </header> 

       <?php include 'sidebar.php'?> 

      <div class="page-wrapper">
       
        <div class="row page-titles">
          <div class="col-md-5 col-12 align-self-center">
            <h3 class="text-themecolor mb-0"><i class="mdi mdi-account-box"></i> Usuários</h3>            
          </div>          
        </div>


       
        <div class="container-fluid">     

<!-- Botão para abrir o modal de adicionar nova OS -->
        <button class="btn waves-effect waves-light btn-info" onclick="openModal('addModal')"><i class="ti-plus text"></i> Cadastrar Usuário</button>

        <br><br>


        <div class="row">
            <div class="table-responsive">
              <table class="table table-striped table mb-0" data-tablesaw-mode="columntoggle" id="tablesaw-6204">
                <thead class="thead-light" align="center">
                  <tr style="background-color: #5b5f69">
                    <th>Nome</th>
                    <th>CPF</th>
                    <th>E-mail</th>
                    <th>Perfil</th>
                    <th>Empresa</th>
                    <th>Setor</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody style="font-size:12.5px; font-weight: 400;">
                <?php foreach ($usuarios as $usuario): ?>
                  <tr>
                    <td class="text-center text-uppercase" style="font-weight:500;">
                      <?= htmlspecialchars($usuario['Nome']) ?>
                    </td>
                    <td class="text-center" style="font-weight:500;">
                      <?= formatarCPF($usuario['Cpf']) ?>
                    </td>
                    <td class="text-center">
                      <?= htmlspecialchars($usuario['E_mail']) ?>
                    </td>
                    <td class="text-center">
                    <?php
                    switch ($usuario['PerfilAcesso']):
                        case 1: echo '<span class="badge bg-danger">Admin</span>';      break;
                        case 7: echo '<span class="badge bg-secondary">Diretor</span>'; break;
                        case 9: echo '<span class="badge bg-secondary">Sub Diretor</span>'; break;
                        case 2: echo '<span class="badge bg-success">Gestor</span>';    break;
                        case 3: echo '<span class="badge bg-warning">Contratada</span>';break;
                        case 4: echo '<span class="badge bg-primary">Inova</span>';     break;
                        case 5: echo '<span class="badge bg-info">Bi</span>';           break;
                        case 6: echo '<span class="badge bg-dark">Suporte</span>';      break;
                        case 8: echo '<span class="badge bg-dark">Dtic</span>';      break;
                        default: echo '<span class="badge bg-light text-dark">Indefinido</span>';
                    endswitch;
                    ?>
                    </td>
                    <td class="text-center">
                      <?= htmlspecialchars($usuario['NomeEmpresa'] ?? 'Nenhuma') ?>
                    </td>
                    <td class="text-center">
                      <?= htmlspecialchars($usuario['NomeSetor'] ?? 'Nenhum') ?>
                    </td>
                    <td class="text-center action-buttons">
                      <div class="d-flex justify-content-center align-items-center">
                        <!-- Editar -->
                        <button
                          onclick="loadUserDetails(
                            <?= $usuario['Id'] ?>,
                            '<?= addslashes($usuario['Nome']) ?>',
                            '<?= addslashes($usuario['Cpf']) ?>',
                            '<?= addslashes($usuario['E_mail']) ?>',
                            <?= $usuario['PerfilAcesso'] ?>,
                            <?= $usuario['EmpresaId']  ?? 'null' ?>,
                            <?= $usuario['SetorId']    ?? 'null' ?>
                          )"
                          title="Editar"
                          class="btn btn-sm btn-light-info text-white mx-1">
                          <i data-feather="edit" class="feather-sm fill-white"></i>
                        </button>
                        <!-- Bloquear/Desbloquear -->
                        <?php if ($usuario['bloqueado'] == 1): ?>
                          <button onclick="toggleBlock(<?= $usuario['Id'] ?>,2)"
                                  title="Bloqueado"
                                  class="btn btn-sm btn-light-danger text-white mx-1">
                            <i data-feather="lock" class="feather-sm fill-white"></i>
                          </button>
                        <?php else: ?>
                          <button onclick="toggleBlock(<?= $usuario['Id'] ?>,1)"
                                  title="Desbloqueado"
                                  class="btn btn-sm btn-light-info text-white mx-1">
                            <i data-feather="unlock" class="feather-sm fill-white"></i>
                          </button>
                        <?php endif; ?>
                        <!-- Excluir -->
                        <button onclick="deleteUser(<?= $usuario['Id'] ?>)"
                                title="Excluir"
                                class="btn btn-sm btn-light-danger text-white mx-1">
                          <i data-feather="trash-2" class="feather-sm fill-white"></i>
                        </button>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>


        <!-- Modal de Cadastro -->
<div id="addModal" class="modal" style="display: none;">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 90%;">
        <div class="modal-content bg-dark text-white">
            <div class="modal-header">
                <h2 class="modal-title" id="modalCadastroLabel">
                    <i class="mdi mdi-account-plus"></i>&nbsp;Cadastrar Usuário
                </h2>
                <span class="close" onclick="closeModal('addModal')">&times;</span>
            </div>
            <form id="formCadastroUsuario" action="process_cadastro.php" method="post">
                <!-- Honeypot anti-bot -->
                <input type="text" name="website" style="display:none" tabindex="-1" autocomplete="off">
                <input type="hidden" name="origin" value="usuarios">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <!-- Campo hidden para reCAPTCHA (será preenchido via JavaScript) -->
                <input type="hidden" id="g-recaptcha-response" name="g-recaptcha-response">
                
                <div class="modal-body">
                    <!-- Campos para a inserção de dados do usuário -->
                    <div class="mb-3">
                        <label for="nome" class="form-label">Nome:</label>
                        <input type="text" class="form-control bg-secondary text-white" id="nome" name="nome" required maxlength="100" pattern="[A-Za-zÀ-ÿ ']{3,100}" title="Apenas letras e espaços.">
                    </div>
                    <div class="mb-3">
                        <label for="cpf" class="form-label">CPF:</label>
                        <input type="text" class="form-control bg-secondary text-white" id="cpf" name="cpf" maxlength="14" required oninput="this.value = mascaraCPF(this.value)" pattern="\d{3}\.\d{3}\.\d{3}-\d{2}" title="Digite um CPF válido (ex: 123.456.789-09)">
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">E-mail:</label>
                        <input type="email" class="form-control bg-secondary text-white" id="email" name="email" required maxlength="80">
                    </div>
                    <div class="mb-3">
                        <label for="senha" class="form-label">Senha:</label>
                        <input type="password" class="form-control bg-secondary text-white" id="senha" name="senha" required minlength="8" maxlength="60" pattern="^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^\da-zA-Z]).{8,}$" title="Mínimo 8 caracteres, incluindo maiúscula, minúscula, número e símbolo.">
                    </div>
                    <div class="mb-3">
                        <label for="empresaId" class="form-label">Empresa Vinculada:</label>
                        <select id="empresaId" name="empresaId" class="form-select bg-secondary text-white">
                            <option value="">Nenhuma</option>
                            <?php foreach ($empresas as $empresa): ?>
                                <option value="<?= $empresa['Id'] ?>"><?= htmlspecialchars($empresa['Nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="setorId" class="form-label">Setor Vinculado:</label>
                        <select id="setorId" name="setorId" class="form-select bg-secondary text-white">
                            <option value="">Nenhum</option>
                            <?php foreach ($setores as $setor): ?>
                                <option value="<?= $setor['id'] ?>"><?= htmlspecialchars($setor['nome_do_setor']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Botão de submissão -->
                    <div class="d-flex justify-content-end mt-4">
                        <button type="submit" class="btn btn-primary font-weight-medium rounded-pill px-4">
                            <div class="d-flex align-items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-send feather-sm fill-white me-2">
                                    <line x1="22" y1="2" x2="11" y2="13"></line>
                                    <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                                </svg>
                                Cadastrar
                            </div>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>




 <!-- Modal de Edição -->
 <div id="editModal" class="modal" style="display: none;">
            <div class="modal-dialog modal-dialog-centered" style="max-width: 90%;">
              <div class="modal-content bg-dark text-white">
                <div class="modal-header">
                  <h2 class="modal-title"><i class="mdi mdi-account-edit"></i>&nbsp;Editar Usuário</h2>
                  <span class="close" onclick="closeModal('editModal')">&times;</span>
                </div>
                <form id="editUserForm" action="process_edit_usuario.php" method="post"
                      onsubmit="event.preventDefault(); submitEditForm();">
                  <div class="modal-body">
                    <input type="hidden" id="editUserId" name="id">
                    <div class="mb-3">
                      <label for="editNome" class="form-label">Nome:</label>
                      <input type="text" id="editNome" name="nome" class="form-control bg-secondary text-white" required>
                    </div>
                    <div class="mb-3">
                      <label for="editCpf" class="form-label">CPF:</label>
                      <input type="text" id="editCpf" name="cpf" class="form-control bg-secondary text-white"
                             maxlength="14" required oninput="this.value = mascaraCPF(this.value)">
                    </div>
                    <div class="mb-3">
                      <label for="editEmail" class="form-label">E-mail:</label>
                      <input type="email" id="editEmail" name="email" class="form-control bg-secondary text-white" required>
                    </div>
                    <div class="mb-3">
                      <label for="editSenha" class="form-label">Nova Senha (deixe em branco para manter a atual):</label>
                      <input type="password" id="editSenha" name="senha" class="form-control bg-secondary text-white" minlength="8" maxlength="60" pattern="^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^\da-zA-Z]).{8,}$" title="Mínimo 8 caracteres, incluindo maiúscula, minúscula, número e símbolo.">
                      <small class="form-text text-muted">Se deixar em branco, a senha atual será mantida.</small>
                    </div>
                    <div class="mb-3">
                    <label for="editPerfil" class="form-label">Perfil:</label>
                    <select id="editPerfil" name="perfil" class="form-select bg-secondary text-white" required>
                    <option value="">— selecione —</option>
                    <option value="1">Admin</option>
                    <option value="7">Diretor</option>
                    <option value="9">Sub Diretor</option>
                    <option value="2">Gestor</option>
                    <option value="3">Contratada</option>
                    <option value="4">Inova</option>
                    <option value="5">Bi</option>
                    <option value="6">Suporte</option>
                    <option value="8">Dtic</option>
                    </select>

                    </div>
                    <div class="mb-3">
                      <label for="editEmpresaId" class="form-label">Empresa Vinculada:</label>
                      <select id="editEmpresaId" name="empresaId" class="form-select bg-secondary text-white">
                        <option value="">Nenhuma</option>
                        <?php foreach ($empresas as $empresa): ?>
                          <option value="<?= $empresa['Id'] ?>"><?= htmlspecialchars($empresa['Nome']) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="mb-3">
                      <label for="editSetor" class="form-label">Setor Vinculado:</label>
                      <select id="editSetor" name="setorId" class="form-select bg-secondary text-white">
                        <option value="">Nenhum</option>
                        <?php foreach ($setores as $setor): ?>
                          <option value="<?= $setor['id'] ?>"><?= htmlspecialchars($setor['nome_do_setor']) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="d-flex justify-content-end mt-4">
                      <button type="submit" class="submit-btn">
                        <i class="feather feather-send me-2"></i> Salvar Alterações
                      </button>
                    </div>
                  </div>
                </form>
              </div>
            </div>
          </div>



</div>  



        <?php include 'footer.php'?>        
      </div>      
    </div>

    <div class="chat-windows"></div>


   <!-- --------------------------------------------------------------
     Scripts de interação (abertura de modais, CRUD, etc.)
-------------------------------------------------------------- -->
<script>
  // Abrir e fechar modais
  function openModal(modalId) {
    document.getElementById(modalId).style.display = 'block';
  }
  function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
  }

  // Deletar usuário
  function deleteUser(userId) {
    if (!confirm('Tem certeza que deseja excluir este usuário?')) return;
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'delete_usuario.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function() {
      if (xhr.status === 200) {
        location.reload();
      } else {
        alert('Erro ao excluir usuário.');
      }
    };
    xhr.send('id=' + encodeURIComponent(userId));
  }

  // Bloquear/Desbloquear usuário
  function toggleBlock(userId, newStatus) {
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'toggle_block_usuario.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function() {
      if (xhr.status === 200) {
        alert(xhr.responseText);
        location.reload();
      } else {
        alert('Erro ao alterar o estado de bloqueio.');
      }
    };
    xhr.send('id=' + encodeURIComponent(userId) + '&bloqueado=' + encodeURIComponent(newStatus));
  }

  // Carrega dados no modal de edição, incluindo setor
  function loadUserDetails(userId, nome, cpf, email, perfilAcesso, empresaId, setorId) {
  document.getElementById('editUserId').value    = userId;
  document.getElementById('editNome').value      = nome;
  document.getElementById('editCpf').value       = cpf;
  document.getElementById('editEmail').value     = email;
  document.getElementById('editSenha').value     = ''; // Limpa o campo de senha

  // <-- aqui, basta colocar o número que veio do banco:
  document.getElementById('editPerfil').value    = perfilAcesso;

  document.getElementById('editEmpresaId').value = empresaId || '';
  document.getElementById('editSetor').value     = setorId   || '';
  openModal('editModal');
}


  // Submeter formulário de edição (incluindo setor e senha)
  function submitEditForm() {
    var userId    = document.getElementById('editUserId').value;
    var nome      = document.getElementById('editNome').value;
    var cpf       = document.getElementById('editCpf').value;
    var email     = document.getElementById('editEmail').value;
    var senha     = document.getElementById('editSenha').value;
    var perfil    = document.getElementById('editPerfil').value;
    var empresaId = document.getElementById('editEmpresaId').value;
    var setorId   = document.getElementById('editSetor').value;

    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'process_edit_usuario.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function() {
  if (xhr.status === 200) {
    try {
      const res = JSON.parse(xhr.responseText);
      alert(res.message);
      if (res.success) {
        closeModal('editModal');
        location.reload();
      }
    } catch(e) {
      // Se por algum motivo não vier JSON, cai aqui:
      alert(xhr.responseText);
    }
  } else {
    alert('Erro ao atualizar usuário.');
  }
};

    var params = [
      'id='        + encodeURIComponent(userId),
      'nome='      + encodeURIComponent(nome),
      'cpf='       + encodeURIComponent(cpf),
      'email='     + encodeURIComponent(email),
      'senha='     + encodeURIComponent(senha),
      'perfil='    + encodeURIComponent(perfil),
      'empresaId=' + encodeURIComponent(empresaId),
      'setorId='   + encodeURIComponent(setorId)
    ].join('&');
    xhr.send(params);
  }
</script>

<!-- Máscara de CPF -->
<script>
  function mascaraCPF(cpf) {
    cpf = cpf.replace(/\D/g, '');
    cpf = cpf.replace(/(\d{3})(\d)/, '$1.$2');
    cpf = cpf.replace(/(\d{3})(\d)/, '$1.$2');
    cpf = cpf.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
    return cpf.substring(0, 14);
  }
</script>


<!-- jQuery para cadastro via AJAX -->
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
<script>
  $(function() {
    $('#formCadastroUsuario').submit(function(e) {
      e.preventDefault();
      $.ajax({
        type: $(this).attr('method'),
        url:  $(this).attr('action'),
        data: $(this).serialize(),
        dataType: 'json',
        success: function(response) {
          alert(response.message);
          if (response.success) {
            closeModal('addModal');
            window.location.reload();
          }
        },
        error: function(xhr, status, error) {
          console.log('Erro AJAX:', error);
          console.log('Response:', xhr.responseText);
          alert('Erro ao cadastrar usuário. Verifique o console para mais detalhes.');
        }
      });
    });
  });
</script>

   
    <script src="assets/libs/jquery/dist/jquery.min.js"></script>
    <!-- Bootstrap tether Core JavaScript -->
    <script src="assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
    <!-- apps -->
    <script src="dist/js/app.min.js"></script>
    <script src="dist/js/app.init.dark.js"></script>
    <script src="dist/js/app-style-switcher.js"></script>
    <!-- slimscrollbar scrollbar JavaScript -->
    <script src="assets/libs/perfect-scrollbar/dist/perfect-scrollbar.jquery.min.js"></script>
    <script src="assets/extra-libs/sparkline/sparkline.js"></script>
    <!--Wave Effects -->
    <script src="dist/js/waves.js"></script>
    <!--Menu sidebar -->
    <script src="dist/js/sidebarmenu.js"></script>
    <!--Custom JavaScript -->
    <script src="dist/js/feather.min.js"></script>
    <script src="dist/js/custom.min.js"></script>
  </body>
</html>