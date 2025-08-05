<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}
// Incluir db.php para conexão com o banco de dados
include 'db.php';

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
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>iNOVA ERP - Usuários</title>
    <link rel="stylesheet" href="style.css">     
</head>
<body>
    <!-- Menu -->
    <?php include 'sidebar.php'; ?>

    <!-- Conteúdo principal -->
    <div class="main-content">
        <h1>Usuários</h1>
        <table class="content-table">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>CPF</th>
                    <th>E-mail</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($usuarios as $usuario): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($usuario['Nome']); ?></td>
                        <td><?php echo formatarCPF($usuario['Cpf']); ?></td>
                        <td><?php echo htmlspecialchars($usuario['E_mail']); ?></td>
                        <td class="action-buttons">
                            <button onclick="loadUserDetails(<?php echo $usuario['Id']; ?>, '<?php echo addslashes($usuario['Nome']); ?>', '<?php echo addslashes($usuario['Cpf']); ?>', '<?php echo addslashes($usuario['E_mail']); ?>', '<?php echo $usuario['PerfilAcesso']; ?>')">Editar</button>

                            <button onclick="deleteUser(<?php echo $usuario['Id']; ?>)">Excluir</button>
                            <?php
            $textoBotao = $usuario['bloqueado'] == 1 ? 'Desbloquear' : 'Bloquear';
            $novoStatus = $usuario['bloqueado'] == 1 ? 2 : 1;
            ?>
            <button onclick="toggleBlock(<?php echo $usuario['Id'] . ", " . $usuario['bloqueado']; ?>)">
                <?php echo $textoBotao; ?>
            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <button onclick="openModal('addModal')">Cadastrar Novo Usuário</button>
    </div>

    <!-- Modal de Cadastro -->
<div id="addModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('addModal')">&times;</span>
        <h2>Cadastrar Novo Usuário</h2>
        <form action="process_cadastro.php" method="post">
            <div class="form-row">
                <label for="nome">Nome:</label>
                <input type="text" id="nome" name="nome" required>
            </div>
            <div class="form-row">
                <label for="cpf">CPF:</label>
                <input type="text" id="cpf" name="cpf" maxlength="14" required oninput="this.value = mascaraCPF(this.value)">
            </div>
            <div class="form-row">
                <label for="email">E-mail:</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="form-row">
                <label for="senha">Senha:</label>
                <input type="password" id="senha" name="senha" required>
            </div>
            <div class="form-row">
                <button type="submit" class="submit-btn">Cadastrar</button>
            </div>
        </form>
    </div>
</div>



<!-- Modal de Edição -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('editModal')">&times;</span>
        <h2>Editar Usuário</h2>
        <form id="editUserForm" onsubmit="event.preventDefault(); submitEditForm();">
            <div class="form-row">
                <label for="editNome">Nome:</label>
                <input type="text" id="editNome" name="nome" required>
            </div>
            <div class="form-row">
                <label for="editCpf">CPF:</label>
                <input type="text" id="editCpf" name="cpf" maxlength="14" required oninput="this.value = mascaraCPF(this.value)">
            </div>
            <div class="form-row">
                <label for="editEmail">E-mail:</label>
                <input type="email" id="editEmail" name="email" required>
            </div>
            <div class="form-row">
                <label for="editPerfil">Perfil:</label>
                <select id="editPerfil" name="perfil">
                    <option value="Admin">Admin</option>
                    <option value="Contratada">Contratada</option>
                </select>
            </div>
            <input type="hidden" id="editUserId" name="id">
            <div class="form-row">
                <button type="submit" class="submit-btn">Salvar Alterações</button>
            </div>
        </form>
    </div>
</div>



<script>
    // Abrir e fechar modais
    function openModal(modalId) {
        document.getElementById(modalId).style.display = 'block';
    }

    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }

    // Deletar usuário
    // Função para deletar usuário
function deleteUser(userId) {
    if (confirm('Tem certeza que deseja excluir este usuário?')) {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'delete_usuario.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onload = function() {
            if (this.status === 200) {
                console.log(this.responseText);
                location.reload(); // Recarrega a página para atualizar a lista
            } else {
                console.error('Erro ao excluir usuário');
            }
        };
        xhr.send('id=' + encodeURIComponent(userId));
    }
}


    // Bloquear/Desbloquear usuário
    function toggleBlock(userId, currentStatus) {
    var newStatus = currentStatus === 1 ? 2 : 1;
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'toggle_block_usuario.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onreadystatechange = function() {
        if (this.readyState === XMLHttpRequest.DONE && this.status === 200) {
            console.log(this.responseText);
            location.reload(); // Recarrega a página para atualizar o status visualmente
        }
    };
    xhr.send('id=' + encodeURIComponent(userId) + '&bloqueado=' + encodeURIComponent(newStatus));
}


    // Submeter formulário de edição
function submitEditForm() {
    var userId = document.getElementById('editUserId').value;
    var nome = document.getElementById('editNome').value;
    var cpf = document.getElementById('editCpf').value;
    var email = document.getElementById('editEmail').value;
    var perfil = document.getElementById('editPerfil').value; // Adicionado

    // Inicializa uma nova requisição AJAX
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'process_edit_usuario.php', true); // Aponta para o arquivo de processamento correto
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onreadystatechange = function() {
        if (this.readyState === XMLHttpRequest.DONE && this.status === 200) {
            // Aqui você pode atualizar a interface ou exibir uma mensagem de sucesso
            console.log(this.responseText);
            closeModal('editModal');
            location.reload(); // Isto recarrega a página para atualizar a lista de usuários
        }
    };
    xhr.send('id=' + encodeURIComponent(userId) + '&nome=' + encodeURIComponent(nome) + '&cpf=' + encodeURIComponent(cpf) + '&email=' + encodeURIComponent(email) + '&perfil=' + encodeURIComponent(perfil));

}

    /// Carregar dados do usuário para o modal de edição
function loadUserDetails(userId, nome, cpf, email, perfilAcesso) {
    document.getElementById('editUserId').value = userId;
    document.getElementById('editNome').value = nome;
    document.getElementById('editCpf').value = cpf;
    document.getElementById('editEmail').value = email;
    
    // Se você estiver usando valores "1" para Admin e "2" para Contratada
    var perfilSelect = document.getElementById('editPerfil');
    perfilSelect.value = perfilAcesso == 1 ? 'Admin' : 'Contratada';

    openModal('editModal');
}

</script>

<script>    
    function mascaraCPF(cpf) {
    cpf = cpf.replace(/\D/g, ""); // Remove tudo o que não é dígito
    cpf = cpf.replace(/(\d{3})(\d)/, "$1.$2"); // Coloca ponto entre o terceiro e o quarto dígitos
    cpf = cpf.replace(/(\d{3})(\d)/, "$1.$2"); // Coloca ponto entre o sexto e o sétimo dígitos
    cpf = cpf.replace(/(\d{3})(\d{1,2})$/, "$1-$2"); // Coloca hífen entre o nono e o décimo dígitos
    cpf = cpf.substring(0, 14); // Limita o tamanho máximo para o padrão de um CPF (incluindo os separadores)
    return cpf;
}
</script>

</body>
</html>
