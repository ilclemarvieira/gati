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
$perfisPermitidos = [1, 2, 4, 9]; // Exemplo: somente Admin, Gestor e Inova podem acessar
verificarPermissao($perfisPermitidos);

$empenhos = $pdo->query("SELECT * FROM empenho")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html dir="ltr" lang="pt">

  <head>
    <?php include 'head.php'?>

    <!-- Adicione os scripts jQuery e jQuery MaskMoney na ordem correta -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-maskmoney/3.0.2/jquery.maskMoney.min.js"></script>

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
            <h3 class="text-themecolor mb-0"><i class="mdi mdi-currency-usd"></i> Empenho</h3>            
          </div>          
        </div>


       
        <div class="container-fluid">
    <button class="btn waves-effect waves-light btn-info" onclick="openModal('addModal')"><i class="ti-plus text"></i> Cadastrar Empenho</button>
    <br><br>


           <div class="row">
        <div class="table-responsive">
            <table class="table table-striped table mb-0" data-tablesaw-mode="columntoggle" id="tablesaw-6204">
                <thead class="thead-light" align="center">
                    <tr style="background-color: #5b5f69">
                        <th scope="col" style="white-space: nowrap;">Valor</th>
                        <th scope="col" style="white-space: nowrap;">Ano</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>

    <?php foreach ($empenhos as $empenho): ?>
            
            <tr>

             <td style="text-align: center; font-weight: 500; text-transform: uppercase; vertical-align: middle">
                            <?php echo 'R$ ' . htmlspecialchars(number_format($empenho['valor'], 2, ',', '.')); ?>
                        </td>
                        <td style="text-align: center; vertical-align: middle">
                            <?php echo htmlspecialchars($empenho['ano']); ?>
                        </td>
                        <td style="text-align: center; vertical-align: middle" class="action-buttons">
                        <div class="d-flex justify-content-center align-items-center">
                            <!-- Botão Editar com ícone de lápis -->
                            <button class="btn btn-sm btn-light-info text-white mx-1 edit-button" data-id="<?php echo $empenho['id']; ?>" data-valor="<?php echo '' . htmlspecialchars(number_format($empenho['valor'], 2, ',', '.')); ?>" data-ano="<?php echo htmlspecialchars($empenho['ano'], ENT_QUOTES); ?>" title="Editar">
                                <i data-feather="edit" class="feather-sm fill-white"></i>
                            </button>

                            <!-- Botão Excluir com ícone de lixeira -->
                            <button onclick="deleteContracted(<?php echo $empenho['id']; ?>)" title="Excluir" class="btn btn-sm btn-light-danger text-white mx-1">
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



<!-- Modal de Cadastro de Empenho -->
<div id="addModal" class="modal" style="display: none;">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 90%;">
        <div class="modal-content bg-dark text-white">
            <div class="modal-header">
                <h2 class="modal-title" id="modalCadastroLabel">
                    <i class="mdi mdi-currency-usd"></i>&nbsp;Cadastrar Empenho
                </h2>
                <span class="close" onclick="closeModal('addModal')">&times;</span>
            </div>
            <form id="addEmpenhoForm" action="process_cadastro_empenho.php" method="post">
                <div class="modal-body">
                    <!-- Campo para a inserção do valor do empenho -->
                    <div class="mb-3">
                        <label for="addValor" class="form-label">Valor:</label>
                        <div class="input-group">
                            <span class="input-group-text">R$</span>
                            <input type="text" class="form-control bg-secondary text-white" id="addValor" name="valor" placeholder="0,00" required>
                        </div>
                    </div>

                    <!-- Campo para a inserção do ano do empenho -->
                    <div class="mb-3">
                        <label for="addAno" class="form-label">Ano:</label>
                        <input type="text" class="form-control bg-secondary text-white" id="addAno" name="ano" pattern="\d{4}" title="Insira um ano válido (4 dígitos)." required>
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









<!-- Modal de Edição de Empenho -->
<div id="editModal" class="modal" style="display: none;">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 90%;">
        <div class="modal-content bg-dark text-white">
            <div class="modal-header">
                <h2 class="modal-title" id="modalEditLabel">
                    <i class="mdi mdi-pencil"></i>&nbsp;Editar Empenho
                </h2>
                <span class="close" onclick="closeModal('editModal')">&times;</span>
            </div>
            <form id="editEmpenhoForm" action="process_edicao_empenho.php" method="post">
                <input type="hidden" id="editId" name="id">
                <div class="modal-body">
                    <!-- Campos para a edição do empenho -->
                    <div class="mb-3">
                    <label for="editValor" class="form-label">Valor:</label>
                    <div class="input-group">
                        <span class="input-group-text">R$</span>
                        <input type="text" class="form-control bg-secondary text-white" id="editValor" name="valor" placeholder="0,00" required>
                    </div>
                </div>

                    <div class="mb-3">
                        <label for="editAno" class="form-label">Ano:</label>
                        <input type="text" class="form-control bg-secondary text-white" id="editAno" name="ano" pattern="\d{4}" title="Insira um ano válido (4 dígitos)." required>
                    </div>

                    <!-- Botão de submissão -->
                    <div class="d-flex justify-content-end mt-4">

                        <button type="submit" class="submit-btn">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-send feather-sm fill-white me-2">
                                <line x1="22" y1="2" x2="11" y2="13"></line>
                                <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                            </svg>
                            Salvar Alterações
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


<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>

<script>
    // Função para abrir o modal de cadastro
    function openModal(modalId) {
        $('#' + modalId).show();
    }

    // Função para fechar o modal
    function closeModal(modalId) {
        $('#' + modalId).hide();
    }

    // Função para submeter o formulário de cadastro
    function submitAddForm() {
        var valor = $('#addValor').val();
        var ano = $('#addAno').val();

        $.ajax({
            url: 'process_cadastro_empenho.php',
            type: 'POST',
            data: {
                valor: valor,
                ano: ano
            },
            success: function(response) {
                closeModal('addModal');
                location.reload();
            },
            error: function() {
                alert("Erro ao cadastrar empenho.");
            }
        });
    }

    // Função para abrir o modal de edição com os dados preenchidos
    function openEditModal(id, valor, ano) {
        $('#editId').val(id);
        $('#editValor').val(valor);
        $('#editAno').val(ano);
        $('#editModal').show();
    }

    // Função para submeter o formulário de edição
    function submitEditForm() {
        var id = $('#editId').val();
        var valor = $('#editValor').val();
        var ano = $('#editAno').val();

        $.ajax({
            url: 'process_edicao_empenho.php',
            type: 'POST',
            data: {
                id: id,
                valor: valor,
                ano: ano
            },
            success: function(response) {
                closeModal('editModal');
                location.reload();
            },
            error: function() {
                alert("Erro ao atualizar empenho.");
            }
        });
    }

    $(document).ready(function() {
        $('.edit-button').click(function() {
            var id = $(this).data('id');
            var valor = $(this).data('valor');
            var ano = $(this).data('ano');
            openEditModal(id, valor, ano);
        });

        // Vincula as funções de submissão aos eventos de submit dos formulários
        $('#addEmpenhoForm').submit(function(event) {
            event.preventDefault();
            submitAddForm();
        });

        $('#editEmpenhoForm').submit(function(event) {
            event.preventDefault();
            submitEditForm();
        });
    });
</script>


<script>
    // Função para excluir um empenho
    function deleteContracted(id) {
        // Confirmar se o usuário realmente deseja excluir o empenho
        if (confirm("Tem certeza de que deseja excluir este empenho?")) {
            // Enviar uma solicitação AJAX para excluir o empenho
            $.ajax({
                url: 'process_delete_empenho.php', // URL para o script PHP que exclui o empenho
                type: 'POST',
                data: { id: id }, // Dados a serem enviados (o ID do empenho a ser excluído)
                success: function(response) {
                    // Se a exclusão for bem-sucedida, recarregar a página para atualizar a lista de empenhos
                    location.reload();
                },
                error: function() {
                    // Se houver algum erro ao excluir o empenho, exibir uma mensagem de erro
                    alert("Erro ao excluir empenho.");
                }
            });
        }
    }
</script>




<script>
document.getElementById('addValor').addEventListener('input', function(e) {
    var value = this.value.replace(/\D/g, ''); // Remove tudo o que não é dígito
    value = parseInt(value, 10); // Converte para inteiro (remove zeros à esquerda e trata como número)

    if (!isNaN(value)) { // Se o valor não é NaN (não é um número), procede
        value = value.toString(); // Converte o valor de volta para string para manipulação
        
        // Trata a parte decimal
        if (value.length > 2) {
            value = value.slice(0, value.length - 2) + ',' + value.slice(value.length - 2);
        } else if (value.length === 2) {
            value = '0,' + value;
        } else if (value.length === 1) {
            value = '0,0' + value;
        }
        
        // Adiciona o separador de milhares
        value = value.replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        
        this.value = value; // Atualiza o valor no campo
    } else {
        this.value = ''; // Limpa o campo se não for um número válido
    }
});
</script>

<script>
document.querySelectorAll('.form-control').forEach(function(input) {
    input.addEventListener('input', function(e) {
        var value = this.value.replace(/\D/g, '');
        value = parseInt(value, 10);

        if (!isNaN(value)) {
            value = value.toString();
            if (value.length > 2) {
                value = value.slice(0, value.length - 2) + ',' + value.slice(value.length - 2);
            } else if (value.length === 2) {
                value = '0,' + value;
            } else if (value.length === 1) {
                value = '0,0' + value;
            }
            
            value = value.replace(/\B(?=(\d{3})+(?!\d))/g, ".");
            
            this.value = value;
        } else {
            this.value = '';
        }
    });
});
</script>

<script>
document.getElementById('addAno').addEventListener('input', function(e) {
    var value = this.value.replace(/\D/g, ''); // Permite apenas números
    if (value.length > 4) {
        this.value = value.slice(0, 4); // Limita a 4 dígitos
    } else {
        this.value = value;
    }
});


document.getElementById('editAno').addEventListener('input', function(e) {
    var value = this.value.replace(/\D/g, ''); // Permite apenas números
    if (value.length > 4) {
        this.value = value.slice(0, 4); // Limita a 4 dígitos
    } else {
        this.value = value;
    }
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
