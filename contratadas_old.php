<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}
include 'db.php';

$contratadas = $pdo->query("SELECT * FROM contratadas")->fetchAll();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>iNOVA ERP - Contratadas</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <h1>Contratadas</h1>
        <table class="content-table">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>E-mail</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($contratadas as $contratada): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($contratada['Nome']); ?></td>
                        <td><?php echo htmlspecialchars($contratada['E_mail']); ?></td>
                        <td class="action-buttons">
                            <button onclick="loadEditModal(<?php echo $contratada['Id'] . ', \'' . addslashes($contratada['Nome']) . '\', \'' . addslashes($contratada['E_mail']) . '\''; ?>)">Editar</button>
                            <button onclick="deleteContracted(<?php echo $contratada['Id']; ?>)">Excluir</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <button onclick="openModal('addModal')">Cadastrar Nova Contratada</button>
    </div>

   <!-- Modal de Cadastro -->
<div id="addModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('addModal')">&times;</span>
        <h2>Cadastrar Contratada</h2>
        <form id="addContratadaForm" onsubmit="event.preventDefault(); submitAddForm();">
            <div class="form-row">
                <label for="addNome">Nome:</label>
                <input type="text" id="addNome" name="nome" required>
            </div>
            <div class="form-row">
                <label for="addEmail">E-mail:</label>
                <input type="email" id="addEmail" name="email" required>
            </div>
            <div class="form-row">
                <button type="submit" class="submit-btn">Cadastrar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal de Edição Atualizado -->
<div id="editModal" class="modal" style="display: none;">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 90%;">
        <div class="modal-content bg-dark text-white">
            <div class="modal-header">
                <h2 class="modal-title" id="modalEditLabel">
                    <i class="mdi mdi-account-edit"></i>&nbsp;Editar Contratada
                </h2>
                <span class="close" onclick="closeModal('editModal')">&times;</span>
            </div>
            <form id="editContratadaForm" action="process_edicao.php" method="post" onsubmit="event.preventDefault(); submitEditForm();">
                <input type="hidden" id="editId" name="id">
                <div class="modal-body">
                    <!-- Campos para a edição de dados -->
                    <div class="mb-3">
                        <label for="editNome" class="form-label">Nome:</label>
                        <input type="text" class="form-control bg-secondary text-white" id="editNome" name="nome" required>
                    </div>
                    <div class="mb-3">
                        <label for="editEmail" class="form-label">E-mail:</label>
                        <input type="email" class="form-control bg-secondary text-white" id="editEmail" name="email" required>
                    </div>
                    <!-- Botão de submissão -->
                    <div class="d-flex justify-content-end mt-4">
                        <button type="submit" class="btn btn-primary font-weight-medium rounded-pill px-4">
                            <div class="d-flex align-items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-save feather-sm fill-white me-2">
                                    <polyline points="19 21 12 16 5 21"></polyline>
                                    <polyline points="12 3 12 16"></polyline>
                                </svg>
                                Salvar Alterações
                            </div>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>


<script>
    function openModal(modalId) {
        document.getElementById(modalId).style.display = 'block';
    }

    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }

    function submitAddForm() {
        var nome = document.getElementById('addNome').value;
        var email = document.getElementById('addEmail').value;
        
        fetch('process_cadastro_contratada.php', {
            method: 'POST',
            body: new URLSearchParams(`nome=${nome}&email=${email}`)
        })
        .then(response => response.text())
        .then(data => {
            alert(data);
            closeModal('addModal');
            location.reload();
        });
    }

    function submitEditForm() {
        var id = document.getElementById('editId').value;
        var nome = document.getElementById('editNome').value;
        var email = document.getElementById('editEmail').value;
        
        fetch('process_edit_contratada.php', {
            method: 'POST',
            body: new URLSearchParams(`id=${id}&nome=${nome}&email=${email}`)
        })
        .then(response => response.text())
        .then(data => {
            alert(data);
            closeModal('editModal');
            location.reload();
        });
    }

        function loadEditModal(id, nome, email) {
        document.getElementById('editId').value = id;
        document.getElementById('editNome').value = nome;
        document.getElementById('editEmail').value = email;
        openModal('editModal');
    }

    function deleteContracted(id) {
        if (confirm('Tem certeza que deseja excluir esta contratada?')) {
            fetch('delete_contratada.php', {
                method: 'POST',
                body: new URLSearchParams(`id=${id}`)
            })
            .then(response => response.text())
            .then(data => {
                alert(data);
                location.reload();
            });
        }
    }
</script>



</body>
</html>
