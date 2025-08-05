<?php
include 'db.php';

if (isset($_POST['id'])) {
    $id = $_POST['id'];
    
    try {
        // Inicia uma transação para garantir que todas as operações ocorram ou nenhuma
        $pdo->beginTransaction();
        
        // Primeiro, exclui os registros relacionados na tabela setor_bvp_config
        $stmt = $pdo->prepare("DELETE FROM setor_bvp_config WHERE setor_id = ?");
        $stmt->execute([$id]);
        
        // Verifica se há projetos associados a este setor
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM projetos WHERE SetorRelacionadoId = ?");
        $stmt->execute([$id]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("Este setor possui projetos relacionados e não pode ser excluído.");
        }
        
        // Verifica se há usuários associados a este setor
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE SetorId = ?");
        $stmt->execute([$id]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("Este setor possui usuários relacionados e não pode ser excluído.");
        }
        
        // Por fim, exclui o setor
        $stmt = $pdo->prepare("DELETE FROM setores WHERE id = ?");
        $stmt->execute([$id]);
        
        // Confirma a transação
        $pdo->commit();
        
        echo "Setor excluído com sucesso!";
    } catch (PDOException $e) {
        // Reverte a transação em caso de erro
        $pdo->rollBack();
        echo "Erro ao excluir setor: " . $e->getMessage();
    } catch (Exception $e) {
        // Reverte a transação em caso de erro personalizado
        $pdo->rollBack();
        echo "Erro: " . $e->getMessage();
    }
} else {
    echo "ID do setor não fornecido.";
}
?>