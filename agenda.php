<?php
// Definindo o tempo máximo da sessão para 6 horas (21600 segundos)
ini_set('session.gc_maxlifetime', 21600);

// Iniciando a sessão
session_start();

// Lembre-se de configurar o cookie de sessão para ter a mesma duração
$params = session_get_cookie_params();
setcookie(session_name(), $_COOKIE[session_name()], time() + 21600,
    $params["path"], $params["domain"],
    $params["secure"], $params["httponly"]
);


if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}
// Incluir db.php para conexão com o banco de dados
include 'db.php';

$perfilAcesso = $_SESSION['PerfilAcesso'] ?? null;

function verificarPermissao($perfisPermitidos) {
    global $perfilAcesso;
    if (!in_array($perfilAcesso, $perfisPermitidos)) {
        // Redireciona para a página anterior ou para uma página padrão
        $paginaRedirecionamento = $_SERVER['HTTP_REFERER'] ?? 'index.php'; // Define 'index.php' como fallback
        header('Location: ' . $paginaRedirecionamento);
        exit;
    }
}

// No início de cada página restrita, chame verificarPermissao com os perfis permitidos
$perfisPermitidos = [1,2,3,4,5,6,9]; // Exemplo: somente Admin, Gestor e Inova podem acessar
verificarPermissao($perfisPermitidos);

// Consulta para buscar todos os usuários
$usuarios = $pdo->query("SELECT * FROM usuarios")->fetchAll();

function formatarCPF($cpf) {
    $cpf = preg_replace("/\D/", '', $cpf);
    if (strlen($cpf) === 11) {
        return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
    }
    return $cpf;
}


?>

<!DOCTYPE html>
<html dir="ltr" lang="pt">

<head>
  <?php include 'head.php'?>
<link href="assets/libs/fullcalendar/dist/fullcalendar.min.css" rel="stylesheet" />

<!-- Inclusão do jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Inclusão do Popper.js (necessário para Bootstrap 4, opcional para Bootstrap 5) -->
<script src="https://unpkg.com/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
<link
      rel="stylesheet"
      type="text/css"
      href="assets/libs/quill/dist/quill.snow.css"
    />

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
    flex-wrap: wrap;
    align-items: center;
    gap: 20px; /* Aumente o valor do gap para dar mais espaço entre os elementos */
    padding: 10px;
    border: 1px solid #3c4147;
    border-radius: 5px;
    margin-bottom: 20px;
}

.filter-container select, 
.filter-container button {
    height: 38px;
    border: 1px solid #5b5f69;
    background-color: #272b34;
    color: #b2b9b2;
    padding: 0 15px;
    font-size: 14px;
    margin-right: 10px; /* Espaçamento à direita para todos os selects */
}

.filter-container button {
    white-space: nowrap; /* Evita a quebra de linha dentro do botão */
    text-align: center; /* Alinha o texto do botão ao centro */
}



.filter-container select {
    flex-grow: 1; /* Permite que os selects cresçam para preencher o espaço */
}

/* Media query para telas pequenas */
@media (max-width: 768px) {
    .filter-container {
        flex-direction: column; /* Os elementos são empilhados verticalmente */
    }

    .filter-container select, 
    .filter-container button {
        width: 100%; /* Os elementos ocupam toda a largura disponível */
    }
    
    .filter-container button {
        order: 1; /* O botão é movido para cima */
        margin-top: 10px; /* Espaçamento acima do botão */
    }
}





/* Estilos para os ícones e espaçamento */
.round-lg {
  width: 50px; /* Tamanho do ícone */
  height: 50px; /* Tamanho do ícone */
  border-radius: 25px; /* Círculo perfeito */
  display: flex;
  align-items: center;
  justify-content: center;
  margin-right: 15px; /* Espaçamento entre o ícone e o texto */
}

/* Ícones */
.fa {
  color: #b2b9aa; /* Ícones brancos para melhor contraste */
  font-size: 21px; /* Ícones maiores */
}

.card {
  background-color: #23272a; /* Fundo escuro, mas ligeiramente mais claro que o fundo da página para se destacar */
  color: #ffffff; /* Texto branco para contraste */
  border: none; /* Sem bordas */
  border-radius: 15px; /* Bordas mais arredondadas */
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.5); /* Sombra mais intensa para um "pop" 3D */
  transition: transform 0.3s ease, box-shadow 0.3s ease; /* Transição suave para efeitos */
  overflow: hidden; /* Garante que todo o conteúdo fique contido dentro das bordas arredondadas */
  cursor: pointer; /* Cursor de mão */
}

.card:hover {
  transform: translateY(-5px); /* Move o card para cima ao passar o mouse */
  box-shadow: 0 12px 40px rgba(0, 0, 0, 0.8); /* Sombra mais intensa no hover */
}

.card-body {
  display: flex;
  align-items: center;
  justify-content: space-between; /* Espaçamento igual entre itens */
  padding: 25px; /* Mais espaço interno */
}

.card-title {
  font-size: 20px; /* Título maior */
  font-weight: 600; /* Peso da fonte aumentado */
}

.card-text {
  font-size: 18px; /* Texto maior */
  margin-top: 5px; /* Ajuste no espaçamento */
}

/* Cores personalizadas para ícones com base no tipo */
.bg-info { background-color: #17a2b8!important; }
.bg-high { background-color: #d9534f!important; } /* cor mais vibrante para alta prioridade */
.bg-medium { background-color: #f0ad4e!important; } /* cor mais vibrante para média prioridade */
.bg-low { background-color: #5bc0de!important; } /* cor mais vibrante para baixa prioridade */


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
    background-color: #2A2D34; /* Cor de fundo para modal */
    color: #FFFFFF; /* Cor do texto para modal */
    border-radius: 8px; /* Bordas arredondadas para modal */
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
    color: #CCCCCC;
    float: right;
    font-size: 30px;
    padding-bottom: 10px;
    margin-bottom: 10px;
    font-weight: bold;
    cursor: pointer;
}

.close:hover,
.close:focus {
    color: #FFFFFF;
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

.linha-resolvida {
    text-decoration: line-through;
    opacity: 0.7; /* Opcional para tornar a linha mais clara */
}

.linha-resolvida .action-buttons button,
.linha-resolvida .action-buttons .dropdown-menu a {
    opacity: 1 !important; /* Força a opacidade a 1 (100% visível) */
}


/* Estilo personalizado para os checkboxes de prioridade */
.priority-check {
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
    position: relative;
    cursor: pointer;
    width: 20px;
    height: 20px;
    background-color: #323743;
    border: 1px solid rgba(255,255,255,.2);
    border-radius: 4px;
    vertical-align: middle;
    transition: background-color 0.2s, border-color 0.2s;
}

/* Quando o checkbox de prioridade estiver marcado */
.priority-check:checked {
    background-color: #198754; /* Um azul claro para distinção */
    border-color: #198754;
}

/* Estilo para o ícone de verificação quando o checkbox de prioridade está marcado */
.priority-check:checked::after {
    content: '\2713'; /* Código Unicode para o ícone de verificação */
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    color: white; /* Ícone de verificação branco */
    font-size: 14px; /* Tamanho do ícone de verificação */
}

/* Hover efeito para o checkbox de prioridade */
.priority-check:hover:not(:checked) {
    border-color: #198754; /* Muda a cor da borda ao passar o mouse para um azul claro */
}

/* Foco no checkbox de prioridade */
.priority-check:focus {
    outline: none; /* Remove o contorno padrão */
    box-shadow: 0 0 0 0.25rem rgba(13, 202, 240, 0.25); /* Adiciona um sombreado azul claro para indicar foco */
}

/* Estilo para desabilitar o checkbox de prioridade */
.priority-check:disabled {
    background-color: #e9ecef; /* Fundo cinza claro para o checkbox desabilitado */
    border-color: #ced4da; /* Borda cinza */
    cursor: not-allowed; /* Cursor de não permitido */
}

/* Estilo para o label associado ao checkbox de prioridade para melhor acessibilidade */
.priority-check + label {
    margin-left: 8px; /* Espaço entre o checkbox de prioridade e o label */
    cursor: pointer; /* Muda o cursor para indicar que o label é clicável */
}

/* Garanta que o label acione o estado de foco do checkbox de prioridade quando clicado */
.priority-check + label:hover {
    color: #0dcaf0; /* Muda a cor do texto ao passar o mouse para um azul claro */
}

/* Estilos para o botão Filtrar */
.btn-filtrar {
  background-color: #4a4e69; /* Cor de fundo do botão */
  color: #ffffff; /* Cor do texto */
  border: 1px solid #6c7293; /* Cor da borda */
  border-radius: 0px; /* Bordas arredondadas */
  padding: 10px 20px; /* Espaçamento interno */
  font-size: 16px; /* Tamanho do texto */
  transition: all 0.3s; /* Transição suave para hover */
  display: flex; /* Usa flexbox para alinhar ícone e texto */
  align-items: center; /* Centraliza itens verticalmente */
  justify-content: center; /* Centraliza itens horizontalmente */
  box-shadow: 0 2px 4px 0 rgba(0, 0, 0, 0.2); /* Sombra suave */
}

.btn-filtrar i {
  margin-right: 5px; /* Espaço entre ícone e texto */
}

/* Efeito ao passar o mouse */
.btn-filtrar:hover {
  color: #b2b9aa;
  background-color: #5b5f69; /* Cor de fundo mais clara ao hover */
  border-color: #5b5f69; /* Cor da borda mais clara ao hover */
  box-shadow: 0 4px 8px 0 rgba(0, 0, 0, 0.3); /* Sombra mais pronunciada ao hover */
  transform: translateY(-2px); /* Leve elevação do botão ao hover */
}

/* Efeito ao clicar no botão */
.btn-filtrar:active {
  transform: translateY(1px); /* Pressionando o botão para baixo */
  box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.2); /* Sombra mais plana quando pressionado */
}

.linha-atrasada {
    position: relative;
    border-left: 7px solid #cb8a00; /* Barra lateral para destacar */
    background-image: linear-gradient(to right, rgb(94 176 112 / 0%) 0%, rgb(81 91 98 / 11%) 10%, rgb(100 212 114 / 6%) 50%, rgb(80 148 120 / 0%) 90%, rgb(80 90 97 / 0%) 100%);
    animation: blink-animation 2s infinite; /* Animação da barra lateral */
}

@keyframes blink-animation {
    0% { border-left-color: #198754; }
    50% { border-left-color: #4f5660; }
    100% { border-left-color: #198754; }
}


.modal {
    z-index: 1050; /* Valor padrão do Bootstrap, mas pode ser aumentado se necessário */
}

.fc-today {
    background-color: #fffa90 !important; /* A cor amarela de destaque que você escolheu */
    font-weight: 600; /* Opcional: para tornar o texto do dia atual mais grosso */
    color: #fff !important; /* Opcional: para mudar a cor do texto para preto ou outra cor de contraste alto */
    font-size:20px;
}

    .fc-event .event-categoria {
        font-size: 0.85em; /* Tamanho menor do que o título */
        color: #fff; /* Cor branca */
        opacity: 0.7; /* Ligeiramente transparente */
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
            <h3 class="text-themecolor mb-0"><i class="mdi mdi-account-box"></i> Agenda Inova</h3>            
          </div>          
        </div>


       
      <div class="container-fluid">
        <button id="addEventButton" class="btn btn-success mb-2">Adicionar Novo Evento</button>



        <div id="calendar"></div>

<!-- Modal para Adicionar Novo Evento -->
<!-- Modal para Adicionar Novo Evento -->
<div id="eventModal" class="modal" style="display: none;">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 90%;">
        <div class="modal-content bg-dark text-white">
            <div class="modal-header">
                <h2 class="modal-title" id="modalTitle"><i class="mdi mdi-calendar-plus"></i>&nbsp;Adicionar Novo Evento</h2>                
                <span class="close" onclick="closeModal()">×</span>
            </div>
            <form id="eventForm">
                <div class="modal-body">
                    <!-- Campo para o Título do Evento -->
                    <div class="mb-3">
                        <label for="title" class="form-label">Título do Evento</label>
                        <input type="text" class="form-control bg-secondary text-white" id="title" name="title" required>
                    </div>

                    <!-- Campo para Hora de Início -->
                    <div class="mb-3">
                        <label for="start_time" class="form-label">Hora de Início</label>
                        <input type="time" class="form-control bg-secondary text-white" id="start_time" name="start_time" required>
                    </div>

                    <!-- Campo para a Descrição com CKEditor -->
                    <div class="mb-3">
                        <label for="description" class="form-label">Descrição</label>
                        <textarea class="form-control" id="description" name="description"></textarea>
                    </div>

                    <script src="https://cdn.ckeditor.com/4.16.0/standard/ckeditor.js"></script>

                    <!-- Campo para Data de Início -->
                    <div class="mb-3">
                        <label for="start_date" class="form-label">Data de Início</label>
                        <input type="date" class="form-control bg-secondary text-white" id="start_date" name="start_date" required>
                    </div>

                    <!-- Campo para Data de Fim -->
                    <div class="mb-3">
                        <label for="end_date" class="form-label">Data de Fim</label>
                        <input type="date" class="form-control bg-secondary text-white" id="end_date" name="end_date">
                    </div>

                    <!-- Campo para Categoria -->
                    <div class="mb-3">
                        <label for="category" class="form-label">Categoria</label>
                        <select class="form-control bg-secondary text-white" id="category" name="category">
                            <option value="Reunião presencial">Reunião presencial</option>
                            <option value="Reunião online">Reunião online</option>
                            <option value="Reunião de planejamento">Reunião de planejamento</option>
                            <option value="Evento">Evento</option>
                            <option value="Curso">Curso</option>
                            <option value="Tipo de operação">Tipo de operação</option>
                            <option value="Treinamento">Treinamento</option>
                            <option value="Happy Hour">Happy hour</option>
                            <option value="Confraternização">Confraternização</option>
                            <option value="Aniversário">Aniversário</option>
                            <option value="Lançamento de produto">Lançamento de produto</option>
                        </select>
                    </div>
                    
                    <!-- Campo para Link -->
                    <div class="mb-3">
                        <label for="link" class="form-label">Link do Evento</label>
                        <input type="url" class="form-control bg-secondary text-white" id="link" name="link" placeholder="https://">
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary font-weight-medium rounded-pill px-4" id="submitButton">
                        <div class="d-flex align-items-center">
                            <i class="mdi mdi-content-save"></i>&nbsp;Salvar
                        </div>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>






</div>



    <!-- Modal de Edição de Evento -->
<div id="editEventModal" class="modal" style="display: none;">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 90%;">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="editModalTitle"><i class="mdi mdi-pencil"></i>&nbsp;Editar Evento</h2>
                <span class="close" onclick="$('#editEventModal').modal('hide')">&times;</span>            
            </div>
            <div align="right" class="mt-2">
                <!-- Botão com ícone de link -->
                <button type="button" id="btnAbrirLink" class="btn btn-outline-info" title="Abrir Link" style="display: none;">
        <i class="fas fa-link"></i>
    </button>
                <!-- Botão com ícone de imprimir -->
                <button type="button" class="btn btn-outline-secondary" onclick="imprimirAta()" title="Imprimir Ata da Reunião">
                    <i class="fas fa-print"></i>
                </button>
                <!-- Botão com ícone de lixeira -->
                <button type="button" class="btn btn-outline-danger" onclick="excluirReuniao()" title="Excluir Reunião">
                    <i class="fas fa-trash"></i>
                </button>
            </div>

            <br>
            <form id="editEventForm">
                <div class="modal-body">
                    <!-- Campo oculto para ID do Evento -->
                    <input type="hidden" id="editEventId" name="eventId">


                    <!-- Título do Evento -->
                    <div class="mb-3">
                        <label for="editTitle" class="form-label">Título</label>
                        <input type="text" class="form-control bg-secondary text-white" id="editTitle" name="title" required>
                    </div>

                    <!-- Hora de Início -->
                    <div class="mb-3">
                        <label for="editStartTime" class="form-label">Hora de Início</label>
                        <input type="time" class="form-control" id="editStartTime" name="start_time" required>
                    </div>

                    <!-- Descrição -->
                    <div class="mb-3">
                        <label for="editDescription" class="form-label">Descrição</label>
                        <textarea class="form-control" id="editDescription" name="description"></textarea>
                    </div>

                    <!-- Data de Início -->
                    <div class="mb-3">
                        <label for="editStartDate" class="form-label">Data de Início</label>
                        <input type="date" class="form-control" id="editStartDate" name="start_date" required>
                    </div>

                    <!-- Data de Fim -->
                    <div class="mb-3">
                        <label for="editEndDate" class="form-label">Data de Fim</label>
                        <input type="date" class="form-control" id="editEndDate" name="end_date">
                    </div>

                    <!-- Categoria no modal de edição -->
                    <div class="mb-3">
                        <label for="editCategory" class="form-label">Categoria</label>
                        <select class="form-control" id="editCategory" name="category">
                            <option value="Reunião presencial">Reunião presencial</option>
                            <option value="Reunião online">Reunião online</option>
                            <option value="Reunião de planejamento">Reunião de planejamento</option>
                            <option value="Evento">Evento</option>
                            <option value="Curso">Curso</option>
                            <option value="Tipo de operação">Tipo de operação</option>
                            <option value="Treinamento">Treinamento</option>
                            <option value="Happy Hour">Happy hour</option>
                            <option value="Confraternização">Confraternização</option>
                            <option value="Aniversário">Aniversário</option>
                            <option value="Lançamento de produto">Lançamento de produto</option>
                        </select>
                    </div>

                    <!-- Link do Evento -->
                    <div class="mb-3">
                        <label for="editLink" class="form-label">Link do Evento</label>
                        <input type="url" class="form-control" id="editLink" name="link" placeholder="https://">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Atualizar</button>
                </div>
            </form>
        </div>
    </div>
</div>


<script>
// Certifique-se de adicionar este script para lidar com o fechamento do modal
$('#editEventModal .close').click(function() {
    $('#editEventModal').modal('hide');
});
</script>



        <?php include 'footer.php'?>        
      </div>      
    </div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('description')) {
        CKEDITOR.replace('description', {
            height: 200,
            toolbar: [
                { name: 'clipboard', items: ['Cut', 'Copy', 'Paste', 'Undo', 'Redo'] },
                { name: 'styles', items: ['Styles', 'Format'] },
                { name: 'basicstyles', items: ['Bold', 'Italic', 'Underline', 'Strike', 'Subscript', 'Superscript', 'RemoveFormat'] },
                { name: 'paragraph', items: ['NumberedList', 'BulletedList', '-', 'Outdent', 'Indent', '-', 'Blockquote'] },
                { name: 'links', items: ['Link', 'Unlink'] },
                { name: 'tools', items: ['Maximize', 'ShowBlocks'] }
            ]
        });
    }

    var form = document.getElementById('eventForm');
    if (form) {
        form.addEventListener('submit', function(event) {
            event.preventDefault();
            for (var instance in CKEDITOR.instances) {
                CKEDITOR.instances[instance].updateElement();
            }
            var formData = new FormData(this);
            var submitButton = document.getElementById('submitButton');
            submitButton.disabled = true; // Desativar o botão de envio para evitar cliques múltiplos

            $.ajax({
                url: 'save_event.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    alert('Evento salvo com sucesso!');
                    console.log(response);
                    $('#calendar').fullCalendar('refetchEvents');
                    $('#eventModal').modal('hide');
                    submitButton.disabled = false; // Reativar o botão após a submissão
                },
                error: function(xhr, status, error) {
                    console.error('Erro ao salvar o evento:', error);
                    alert('Erro ao salvar o evento: ' + error);
                    submitButton.disabled = false; // Reativar o botão em caso de erro
                }
            });
        });
    }
});
</script>


     <script>
$(document).ready(function() {
    $('#calendar').fullCalendar({
        header: {
            left: 'prev,next today',
            center: 'title',
            right: 'month,agendaWeek,agendaDay'
        },
        selectable: true,
        selectHelper: true,
        editable: true,
        eventLimit: true,
        locale: 'pt-br',
        firstDay: 1, // A semana começa na segunda-feira
        timeFormat: 'HH:mm', // Formato de 24 horas com minutos

        // Função para customizar a renderização do evento
        eventRender: function(event, element) {
            // Verifique se há uma categoria definida para o evento
            if (event.categoria) {
                // Crie um novo elemento para mostrar a categoria
                var categoriaSpan = $('<span class="event-categoria"></span>').text(event.categoria);
                // Adicione o novo elemento abaixo do título do evento
                element.find('.fc-title').append('<br/>').append(categoriaSpan);
            }
        },

        dayRender: function(date, cell) {
            if (date.format('YYYY-MM-DD') === moment().format('YYYY-MM-DD')) {
                cell.css("background-color", "#fffa90"); // Cor para o dia atual
            }
        },

        events: 'fetch_events.php',

       eventClick: function(calEvent) {
    // Preenche o formulário de edição com os dados do evento
    $('#editEventId').val(calEvent.id);
    $('#editTitle').val(calEvent.title);
    $('#editStartTime').val(moment(calEvent.start).format('HH:mm'));
    $('#editStartDate').val(moment(calEvent.start).format('YYYY-MM-DD'));
    $('#editEndDate').val(calEvent.end ? moment(calEvent.end).format('YYYY-MM-DD') : '');
    $('#editDescription').val(calEvent.description || '');
    $('#editLink').val(calEvent.link || '');
    $('#editCategory').val(calEvent.categoria || '').change();

    // Cria ou atualiza o elemento com o nome do usuário
    var criadoPorHtml = `<div class="modal-body"><div class="col-md-12"><span class="badge bg-info">Criado por: <b>${calEvent.usuarioNome}</b></span></div></div>`;
    var $criadoPorDiv = $('#eventoCriadoPor');

    if ($criadoPorDiv.length === 0) {
        // Se o elemento ainda não existe, cria e insere no modal
        $criadoPorDiv = $(criadoPorHtml).attr('id', 'eventoCriadoPor');
        // Insere o elemento criado antes do formulário de edição dentro do modal
        $criadoPorDiv.insertBefore('#editEventForm .modal-body');
    } else {
        // Se já existe, apenas atualiza o HTML
        $criadoPorDiv.html(criadoPorHtml);
    }

    // Verifica se há um link associado ao evento e mostra o botão de link
    if (calEvent.link) {
        $('#btnAbrirLink').show().off('click').on('click', function() {
            abrirLink(calEvent.link);
        });
    } else {
        $('#btnAbrirLink').hide();
    }


    // Abre o modal de edição
    $('#editEventModal').modal('show');
},

eventDrop: function(event) {
        // Formatar as datas para YYYY-MM-DD
        var newStartDate = moment(event.start).format('YYYY-MM-DD');
        var newEndDate = (event.end ? moment(event.end).format('YYYY-MM-DD') : newStartDate);
        
        // Enviar as novas datas para o servidor via AJAX
        $.ajax({
            url: 'update_event_date.php', // Script PHP para atualizar a data
            type: 'POST',
            data: {
                eventId: event.id,
                newStartDate: newStartDate,
                newEndDate: newEndDate
            },
            success: function(response) {
                alert('Evento atualizado com sucesso!');
            },
            error: function() {
                alert('Erro ao atualizar evento.');
            }
        });
    }

    });

    $('#editEventModal').on('hidden.bs.modal', function () {
    // Remove o nome do usuário quando o modal for fechado
    $('#eventoCriadoPor').remove();
});


   // Função atualizada para abrir o link fornecido
function abrirLink(url) {
    window.open(url, '_blank');
}


    // Botão para abrir o modal de adição de evento
    $('#addEventButton').click(function() {
        $('#eventForm')[0].reset();
        $('#eventModal').modal('show');
    });

   

    // Submissão do formulário de editar evento
    $('#editEventForm').on('submit', function(e) {
        e.preventDefault();
        var formData = new FormData(this);

        $.ajax({
            url: 'update_event.php',
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            success: function(response) {
                $('#editEventModal').modal('hide');
                alert('Evento atualizado com sucesso!');
                $('#calendar').fullCalendar('refetchEvents');
            },
            error: function(xhr, status, error) { 
                alert("Erro ao atualizar o evento: " + error);
            }
        });
    });
});

</script>

<script> 
function closeModal() {
    $('#eventModal').modal('hide'); // Para Bootstrap 4 e 5
}

</script>

<script>
    function imprimirAta() {
    var eventoId = $('#editEventId').val();
    window.open('gerarAtaReuniao.php?id=' + eventoId, '_blank');
}
</script>





    <script src="assets/libs/jquery/dist/jquery.min.js"></script>
    <script src="assets/libs/moment/min/moment.min.js"></script>
    <script src="assets/libs/fullcalendar/dist/fullcalendar.min.js"></script> 
    <script src="assets/libs/fullcalendar/dist/locale/pt-br.js"></script> 

    <script src="assets/extra-libs/taskboard/js/jquery-ui.min.js"></script>
    <!-- Bootstrap tether Core JavaScript -->
    <script src="assets/libs/popper.js/popper.min.js"></script>
    <script src="assets/libs/bootstrap/dist/js/bootstrap.min.js"></script>
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
    <!--This page JavaScript -->     
    <script src="dist/js/pages/calendar/cal-init.js"></script>
    <script src="assets/libs/ckeditor/ckeditor.js"></script>
    <script src="assets/libs/ckeditor/samples/js/sample.js"></script>

    <script src="assets/libs/quill/dist/quill.min.js"></script>
    <!-- Initialize Quill editor -->




   
<script>
      //default
      initSample();

     // Turn off automatic editor creation first.
CKEDITOR.disableAutoInline = true;

// Initialize the CKEditor as inline editor with the sourcedialog plugin
CKEDITOR.inline("editor2", {
    extraPlugins: "sourcedialog",
    removePlugins: "sourcearea"
});

// Initialize CKEditor to auto-grow with minimum height of 400px
const editor1 = CKEDITOR.replace("editor1", {
    extraPlugins: 'autoGrow', // Add the autoGrow plugin
    autoGrow_minHeight: 400, // Minimum height when empty
    autoGrow_maxHeight: 800, // Maximum height it can grow to
    autoGrow_bottomSpace: 50, // Extra space below the editor
    removePlugins: 'resize', // Disable manual resize handle
    extraAllowedContent: "div", // Allow <div> tags in content
    height: 400 // Initial height
});

// Listen to instanceReady event to set up initial configurations
editor1.on("instanceReady", function () {
    // Output self-closing tags in HTML4 style
    this.dataProcessor.writer.selfClosingEnd = ">";

    // Configure line breaks for various HTML elements
    const dtd = CKEDITOR.dtd;
    for (const e in CKEDITOR.tools.extend({}, dtd.$nonBodyContent, dtd.$block, dtd.$listItem, dtd.$tableContent)) {
        this.dataProcessor.writer.setRules(e, {
            indent: true,
            breakBeforeOpen: true,
            breakAfterOpen: true,
            breakBeforeClose: true,
            breakAfterClose: true
        });
    }
    // Start in source mode (optional)
    // this.setMode("source");
});

    </script>

    <script> 
function excluirReuniao() {
    // Confirmação antes de prosseguir com a exclusão
    var confirmar = confirm("Tem certeza que deseja excluir este evento?");
    if (confirmar) {
        var eventId = $('#editEventId').val(); // Pega o ID do evento do campo oculto

        $.ajax({
            url: 'delete_event.php', // O script PHP para executar a exclusão
            type: 'POST',
            data: {id: eventId},
            success: function(response) {
                // Verifica a resposta do servidor
                if(response === 'success') {
                    alert('Evento excluído com sucesso!');
                    $('#editEventModal').modal('hide'); // Fecha o modal
                    $('#calendar').fullCalendar('refetchEvents'); // Atualiza os eventos no calendário
                } else {
                    // Lidar com falha na exclusão
                    alert('Falha ao excluir o evento. Por favor, tente novamente.');
                }
            },
            error: function() {
                alert('Erro ao excluir o evento.');
            }
        });
    }
}

    </script>


   <script>
$(document).ready(function() {
    var urlParams = new URLSearchParams(window.location.search);
    var eventoId = urlParams.get('evento_id');
    
    if (eventoId) {
        $.ajax({
            url: 'fetch_event_info.php',
            type: 'GET',
            data: { id: eventoId },
            success: function(evento) {
                if (evento) {
                    $('#editEventId').val(evento.id);
                    $('#editTitle').val(evento.titulo);
                    $('#editStartTime').val(moment(evento.horario_inicio, 'HH:mm:ss').format('HH:mm'));
                    $('#editStartDate').val(moment(evento.data_inicio).format('YYYY-MM-DD'));
                    $('#editEndDate').val(evento.data_fim ? moment(evento.data_fim).format('YYYY-MM-DD') : '');
                    $('#editDescription').val(evento.descricao);
                    $('#editCategory').val(evento.categoria);
                    $('#editLink').val(evento.link);

                    var criadoPorHtml = `<div class="modal-body"><div class="col-md-12"><span class="badge bg-info">Criado por: <b>${evento.usuarioNome}</b></span></div></div>`;
                    var $criadoPorDiv = $('#eventoCriadoPor');
                    if (!$criadoPorDiv.length) {
                        $(criadoPorHtml).attr('id', 'eventoCriadoPor').insertBefore('#editEventForm .modal-body');
                    } else {
                        $criadoPorDiv.html(criadoPorHtml);
                    }

                    $('#editEventModal').modal('show');
                } else {
                    alert('Não foram encontradas informações para o evento.');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                alert('Erro ao buscar informações do evento: ' + textStatus);
            }
        });
    }
});

</script>



  </body>
</html>