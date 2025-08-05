<?php
// ==========================
// Definindo e aumentando sessão
// ==========================
date_default_timezone_set('America/Sao_Paulo');
ini_set('session.gc_maxlifetime', 21600); // 6 horas
session_start();

if (!isset($_SESSION['ultimaQuantidadeOKR']) || !is_array($_SESSION['ultimaQuantidadeOKR'])) {
    $_SESSION['ultimaQuantidadeOKR'] = [];
}

$params = session_get_cookie_params();
setcookie(
    session_name(),
    $_COOKIE[session_name()] ?? '',
    time() + 21600,
    $params["path"],
    $params["domain"],
    isset($_SERVER['HTTPS']),
    true
);

// ==========================
// Verificação de login
// ==========================
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

// ==========================
// Incluindo conexão e outros
// ==========================
require 'db.php';           // cria $pdo
$perfilAcesso    = $_SESSION['PerfilAcesso'] ?? null;
$usuarioLogadoId = $_SESSION['usuario_id'];

/* -------------------------------------------------
 * BVP por setor – helpers
 * -------------------------------------------------*/
function getBvpLimit(PDO $pdo, int $setorId): int
{
    static $cache = [];
    if (!isset($cache[$setorId])) {
        $st = $pdo->prepare("SELECT quantidade FROM setor_bvp_config WHERE setor_id = ?");
        $st->execute([$setorId]);
        $cache[$setorId] = (int) $st->fetchColumn();     // 0 se nenhum registro
    }
    return $cache[$setorId];
}

function setBvpLimit(PDO $pdo, int $setorId, int $qtd): void
{
    $sql = "INSERT INTO setor_bvp_config (setor_id, quantidade)
            VALUES(:s, :q)
            ON DUPLICATE KEY UPDATE quantidade = VALUES(quantidade)";
    $pdo->prepare($sql)->execute([':s'=>$setorId, ':q'=>$qtd]);
}


$usuarioLogadoId = $_SESSION['usuario_id'] ?? null;

// ==========================
// Verificação de permissão
// ==========================
function verificarPermissao($perfisPermitidos) {
    global $perfilAcesso;
    if (!in_array($perfilAcesso, $perfisPermitidos)) {
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
        exit;
    }
}
$perfisPermitidos = [1, 2, 4, 5, 7, 8, 9]; // +9 = Sub Diretor
 verificarPermissao($perfisPermitidos);

// ==========================
// Filtragem por perfil
// ==========================
// Vamos buscar o setor do usuário (só faz sentido para gestores e os demais)
$userSetorId = null;
if (in_array($perfilAcesso, [2, 4, 5, 8])) {
    $stmtSetor = $pdo->prepare("SELECT SetorId FROM usuarios WHERE Id = ?");
    $stmtSetor->execute([$usuarioLogadoId]);
    $userSetorId = (int) $stmtSetor->fetchColumn();
}

// Monta a cláusula WHERE adicional de acordo com o perfil
$filtroPerfil = "";
switch ($perfilAcesso) {
    case 1:  // Admin
    case 7:  // Diretor
    case 9:  // Sub Diretor
        // Sem filtro — veem tudo
        break;

    case 2:  // Gestor
        // Veem todos os projetos do seu setor
        $filtroPerfil = " AND p.SetorRelacionadoId = {$userSetorId}";
        break;

    case 4:  // Inova
    case 5:  // Bi
    case 8:  // Dtic
        // Só veem projetos DO SEU SETOR **e** vinculados a eles
        $filtroPerfil = "
          AND p.SetorRelacionadoId = {$userSetorId}
          AND (
            p.ResponsavelId = {$usuarioLogadoId}
            OR FIND_IN_SET({$usuarioLogadoId}, p.UsuariosEnvolvidos) > 0
          )
        ";
        break;

    default:
        // Qualquer outro perfil (ex.: Contratada=3, Suporte=6) não vê nada
        $filtroPerfil = " AND 0";
}

// ==========================
// Obtenção de dados auxiliares
// ==========================
$usuarios      = $pdo->query("SELECT Id, Nome, SetorId FROM usuarios ORDER BY Nome ASC")->fetchAll(PDO::FETCH_ASSOC);
$setores       = $pdo->query("SELECT id, nome_do_setor FROM setores ORDER BY nome_do_setor ASC")->fetchAll(PDO::FETCH_ASSOC);
$listaProjetos = $pdo->query("SELECT Id, NomeProjeto FROM projetos ORDER BY NomeProjeto ASC")->fetchAll(PDO::FETCH_ASSOC);

$anosQuery       = $pdo->query("SELECT DISTINCT YEAR(DataCriacao) AS ano FROM projetos ORDER BY ano DESC");
$anosDisponiveis = $anosQuery->fetchAll(PDO::FETCH_ASSOC);

// ==========================
// Função de atualização da Fila OKR por setor
// ==========================
function atualizarFilaOKR(PDO $pdo, int $setorId, int $qtdManual = 0): void
{
    $desiredOKR = $qtdManual > 0 ? $qtdManual : getBvpLimit($pdo, $setorId);
    if ($desiredOKR <= 0) return;

    /* 1) zera concluídos  ------------------------------- */
   $pdo->prepare("
        UPDATE projetos
           SET EnviadoFunil  = 0,
               DisponivelOKR = 0
         WHERE SetorRelacionadoId = :setor
           AND Status = 'concluido'
    ")->execute([':setor'=>$setorId]);

    /* 2) quantos já estão em OKR ------------------------ */
    $cur = $pdo->prepare("
        SELECT COUNT(*) FROM projetos
         WHERE SetorRelacionadoId = :s
           AND EnviadoFunil = 1
           AND Status = 'andamento'
    ");   $cur->execute([':s'=>$setorId]);
    $slots = max(0, $desiredOKR - (int)$cur->fetchColumn());

    /* 3) zera DisponivelOKR dos não‑selecionados -------- */
    $pdo->prepare("
        UPDATE projetos
           SET DisponivelOKR = 0
         WHERE SetorRelacionadoId = :s
           AND Status = 'andamento'
           AND EnviadoFunil = 0
    ")->execute([':s'=>$setorId]);

    /* 4) libera top‑N ----------------------------------- */
    if ($slots>0) {
        $top = $pdo->prepare("
            SELECT Id FROM (
              SELECT Id,
                     ( AlinhamentoEstrategico
                     + VulnerabilidadeCiberseguranca
                     + LPD_DPO_Criptografado
                     + ImpactoOperacional
                     + ImpactoAdministrativo
                     + ImpactoFinanceiro ) AS ValorOKR
                FROM projetos
               WHERE SetorRelacionadoId = :s
                 AND Status = 'andamento'
                 AND EnviadoFunil = 0
               ORDER BY ValorOKR DESC
               LIMIT :n
            ) t
        ");
        $top->bindValue(':s', $setorId, PDO::PARAM_INT);
        $top->bindValue(':n', $slots,  PDO::PARAM_INT);
        $top->execute();
        if ($ids = $top->fetchAll(PDO::FETCH_COLUMN)) {
            $pdo->query("UPDATE projetos
                            SET DisponivelOKR = 1
                          WHERE Id IN (" . implode(',',$ids) . ")");
        }
    }
}


/* ============================================================
 *  ATUALIZA A FILA logo que a página carrega
 * ========================================================== */
$setores = $pdo->query("SELECT id,nome_do_setor FROM setores")->fetchAll(PDO::FETCH_ASSOC);

if (in_array($perfilAcesso,[1,9])) {               // Admin/Sub‑Diretor → todos
    foreach ($setores as $sx) {
        atualizarFilaOKR($pdo,(int)$sx['id']);
    }
} elseif ($perfilAcesso && $perfilAcesso!=1) {     // demais → só próprio setor
    atualizarFilaOKR($pdo,$userSetorId ?? 0);
}


// Se for Admin/Sub-Diretor, percorre todos os setores
if (in_array($perfilAcesso, [1,9])) {
    foreach ($setores as $s) {
        $q = $_SESSION['ultimaQuantidadeOKR'][$s['id']] ?? 0;
        if ($q > 0) {
            atualizarFilaOKR($pdo, (int)$s['id'], $q);
        }
    }
} else {
    // Gestor só atualiza o seu setor
    $q = $_SESSION['ultimaQuantidadeOKR'][$userSetorId] ?? 0;
    if ($q > 0) {
        atualizarFilaOKR($pdo, $userSetorId, $q);
    }
}



// ==========================
// Tratamentos de formulário (POST)
// ==========================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // (1) Detalhes do Projeto (AJAX)
    if (isset($_POST['action']) && $_POST['action'] === 'get_projeto_details') {
        header('Content-Type: application/json');
        $projetoId = (int)($_POST['projetoId'] ?? 0);
        if ($projetoId > 0) {
            // Busca projeto + Responsável + Setor
            $stmt = $pdo->prepare("
                SELECT p.*,
                       u.Nome AS NomeResponsavel,
                       s.nome_do_setor
                  FROM projetos p
             LEFT JOIN usuarios u ON p.ResponsavelId = u.Id
             LEFT JOIN setores s ON p.SetorRelacionadoId = s.id
                 WHERE p.Id = :id
            ");
            $stmt->execute([':id' => $projetoId]);
            $projeto = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($projeto) {
                // Montando lista de nomes para 'OutroSetorEnvolvido'
                global $setores, $usuarios, $listaProjetos;
                $outroSetores = !empty($projeto['OutroSetorEnvolvido']) ? explode(',', $projeto['OutroSetorEnvolvido']) : [];
                $outroSetoresNomes = [];
                foreach ($outroSetores as $setId) {
                    foreach ($setores as $s) {
                        if ($s['id'] == $setId) {
                            $outroSetoresNomes[] = $s['nome_do_setor'];
                            break;
                        }
                    }
                }
                $projeto['OutroSetorEnvolvidoNomes'] = implode(', ', $outroSetoresNomes);

                // Montando lista de nomes para 'UsuariosEnvolvidos'
                $usuariosEnvolvidos = !empty($projeto['UsuariosEnvolvidos']) ? explode(',', $projeto['UsuariosEnvolvidos']) : [];
                $usuariosEnvolvidosNomes = [];
                foreach ($usuariosEnvolvidos as $usrId) {
                    foreach ($usuarios as $u) {
                        if ($u['Id'] == $usrId) {
                            $usuariosEnvolvidosNomes[] = $u['Nome'];
                            break;
                        }
                    }
                }
                $projeto['UsuariosEnvolvidosNomes'] = implode(', ', $usuariosEnvolvidosNomes);

                // Montando lista de nomes para 'DependenciasProjetos'
                $dependencias = !empty($projeto['DependenciasProjetos']) ? explode(',', $projeto['DependenciasProjetos']) : [];
                $dependenciasNomes = [];
                foreach ($dependencias as $depId) {
                    foreach ($listaProjetos as $lp) {
                        if ($lp['Id'] == $depId) {
                            $dependenciasNomes[] = $lp['NomeProjeto'];
                            break;
                        }
                    }
                }
                $projeto['DependenciasProjetosNomes'] = implode(', ', $dependenciasNomes);

                echo json_encode(['success' => true, 'projeto' => $projeto]);
                exit;
            }
        }
        // No PHP (na função de excluir subtarefa)
echo json_encode(['success' => true, 'message' => 'Tarefa excluída.']);
        exit;
    }

    // Puxar o setor do usuário para quem não for Admin/Diretor
$userSetorId   = null;
$userSetorName = '';
if (!in_array($perfilAcesso, [1,7])) {
    $stmt = $pdo->prepare("SELECT SetorId FROM usuarios WHERE Id = ?");
    $stmt->execute([$usuarioLogadoId]);
    $userSetorId = (int)$stmt->fetchColumn();
    // Acha o nome
    foreach ($setores as $s) {
        if ($s['id'] === $userSetorId) {
            $userSetorName = $s['nome_do_setor'];
            break;
        }
    }
}

// Remover arquivo do orçamento macro (AJAX)
if (isset($_POST['action']) && $_POST['action'] === 'remover_arquivo_orcamento') {
    header('Content-Type: application/json');
    $projetoId = (int)$_POST['projetoId'];
    $nomeArquivo = $_POST['nomeArquivo'] ?? '';
    
    if ($projetoId <= 0 || empty($nomeArquivo)) {
        echo json_encode(['success' => false, 'message' => 'Parâmetros inválidos.']);
        exit;
    }
    
    try {
        // Remove do banco de dados
        $stmt = $pdo->prepare("DELETE FROM projetos_arquivos WHERE projeto_id = :pid AND arquivo_nome = :nome AND tipo_arquivo = 'orcamento_macro'");
        $stmt->execute([':pid' => $projetoId, ':nome' => $nomeArquivo]);
        
        // Remove arquivo físico
        $caminhoArquivo = 'uploads/' . $nomeArquivo;
        if (file_exists($caminhoArquivo)) {
            @unlink($caminhoArquivo);
        }
        
        echo json_encode(['success' => true, 'message' => 'Arquivo removido com sucesso.']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erro ao remover arquivo: ' . $e->getMessage()]);
    }
    exit;
}


    // (2) Listar subtarefas (AJAX)
    if (isset($_POST['action']) && $_POST['action'] === 'listar_subtarefas') {
        header('Content-Type: application/json');
        $projetoId = (int)$_POST['projetoId'];
        if ($projetoId <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID de projeto inválido.']);
            exit;
        }
        $sqlSub = "
            SELECT s.*, u.Nome AS nomeCriador, uc.Nome AS nomeConcluido
            FROM subtarefas_projetos s
            LEFT JOIN usuarios u ON s.criador_id = u.Id
            LEFT JOIN usuarios uc ON s.concluido_por = uc.Id
            WHERE s.projeto_id = :projId
            ORDER BY s.ordem ASC, s.id ASC
        ";
        $stmtSub = $pdo->prepare($sqlSub);
        $stmtSub->execute([':projId' => $projetoId]);
        $raw = $stmtSub->fetchAll(PDO::FETCH_ASSOC);

        // Montar hierarquia
        $byId = [];
        foreach ($raw as $r) {
            $r['children'] = [];
            $byId[$r['id']] = $r;
        }
        $hierarquia = [];
        foreach ($byId as $id => $row) {
            $pai = $row['parent_subtarefa_id'];
            if ($pai && isset($byId[$pai])) {
                $byId[$pai]['children'][] = &$byId[$id];
            } else {
                $hierarquia[] = &$byId[$id];
            }
        }
        echo json_encode(['success' => true, 'subtarefas' => $hierarquia]);
        exit;
    }

    // (3) Criar subtarefa (AJAX)
    if (isset($_POST['action']) && $_POST['action'] === 'criar_subtarefa') {
        header('Content-Type: application/json');
        try {
            $projetoId = (int)$_POST['projetoId'];
            $nomeSubtarefa = trim($_POST['nome_subtarefa'] ?? '');
            $descricao = trim($_POST['descricao'] ?? '');
            $parentSubtarefaId = isset($_POST['parent_subtarefa_id']) && $_POST['parent_subtarefa_id'] !== '' ? (int)$_POST['parent_subtarefa_id'] : null;

            if ($projetoId <= 0 || empty($nomeSubtarefa)) {
                throw new Exception("Dados insuficientes para criar subtarefa.");
            }
            global $usuarioLogadoId;

            // Descobrir ordem
            if ($parentSubtarefaId === null) {
                $sqlMax = "SELECT COALESCE(MAX(ordem),0) AS maxOrd FROM subtarefas_projetos WHERE projeto_id = :proj AND parent_subtarefa_id IS NULL";
                $stmtMax = $pdo->prepare($sqlMax);
                $stmtMax->execute([':proj' => $projetoId]);
            } else {
                $sqlMax = "SELECT COALESCE(MAX(ordem),0) AS maxOrd FROM subtarefas_projetos WHERE projeto_id = :proj AND parent_subtarefa_id = :parent";
                $stmtMax = $pdo->prepare($sqlMax);
                $stmtMax->execute([':proj' => $projetoId, ':parent' => $parentSubtarefaId]);
            }
            $maxRow = $stmtMax->fetch(PDO::FETCH_ASSOC);
            $novaOrdem = (int)$maxRow['maxOrd'] + 1;

            $sqlIns = "
                INSERT INTO subtarefas_projetos 
                (projeto_id, criador_id, nome_subtarefa, descricao, data_cadastro, concluida, parent_subtarefa_id, ordem)
                VALUES (:projId, :criadorId, :nome, :descricao, :dataCadastro, 0, :parentId, :ordem)
            ";
            $stmtIns = $pdo->prepare($sqlIns);
            $stmtIns->execute([
                ':projId'       => $projetoId,
                ':criadorId'    => $usuarioLogadoId,
                ':nome'         => $nomeSubtarefa,
                ':descricao'    => $descricao,
                ':dataCadastro' => date('Y-m-d'),
                ':parentId'     => $parentSubtarefaId,
                ':ordem'        => $novaOrdem
            ]);

            echo json_encode(['success' => true, 'message' => 'Subtarefa criada com sucesso!']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // (4) Reordenar subtarefas (AJAX)
    if (isset($_POST['action']) && $_POST['action'] === 'reordenar_subtarefas') {
        header('Content-Type: application/json');
        $listaParam = $_POST['lista'] ?? '[]';
        $listaDecod = json_decode($listaParam, true);
        if (!is_array($listaDecod)) {
            echo json_encode(['success' => false, 'message' => 'Dados de reordenação inválidos.']);
            exit;
        }
        $pdo->beginTransaction();
        try {
            // Verificar se algum Key Result está sendo movido como subtarefa
            $checkKeyResult = $pdo->prepare("SELECT id, is_key_result, nome_subtarefa FROM subtarefas_projetos WHERE id = :id");
            $upd = $pdo->prepare("UPDATE subtarefas_projetos SET ordem = :ordem, parent_subtarefa_id = :parentId WHERE id = :id");
            
            foreach ($listaDecod as $item) {
                $subtarefaId = (int)($item['id'] ?? 0);
                $novaOrdem   = (int)($item['ordem'] ?? 0);
                $parentId    = $item['parentId'] !== '' ? $item['parentId'] : null;

                if ($subtarefaId > 0) {
                    // Verifica se a subtarefa é um Key Result
                    $checkKeyResult->execute([':id' => $subtarefaId]);
                    $row = $checkKeyResult->fetch(PDO::FETCH_ASSOC);
                    if ($row && $row['is_key_result'] == 1 && $parentId !== null) {
                        throw new Exception("A tarefa \"{$row['nome_subtarefa']}\" é um Key Result e não pode se tornar uma subtarefa.");
                    }
                    $upd->execute([':ordem' => $novaOrdem, ':parentId' => $parentId, ':id' => $subtarefaId]);
                }
            }
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Ordem atualizada com sucesso!']);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // (5) Atualizar status da subtarefa (AJAX)
    if (isset($_POST['action']) && $_POST['action'] === 'toggle_subtarefa') {
        header('Content-Type: application/json');
        try {
            $subtarefaId = (int)$_POST['subtarefaId'];
            $concluida   = (int)$_POST['concluida'];
            if ($subtarefaId <= 0) {
                throw new Exception("ID de subtarefa inválido.");
            }
            global $usuarioLogadoId;
            if ($concluida === 1) {
                $upd = $pdo->prepare("UPDATE subtarefas_projetos SET concluida = 1, data_conclusao = :dtc, concluido_por = :usr WHERE id = :id");
                $upd->execute([':dtc' => date('Y-m-d H:i:s'), ':usr' => $usuarioLogadoId, ':id' => $subtarefaId]);
            } else {
                $upd = $pdo->prepare("UPDATE subtarefas_projetos SET concluida = 0, data_conclusao = NULL, concluido_por = NULL WHERE id = :id");
                $upd->execute([':id' => $subtarefaId]);
            }
            echo json_encode(['success' => true, 'message' => 'Status da subtarefa atualizado.']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // (6) Editar subtarefa (AJAX)
    if (isset($_POST['action']) && $_POST['action'] === 'editar_subtarefa') {
        header('Content-Type: application/json');
        try {
            global $usuarioLogadoId;
            $subtarefaId = (int)$_POST['subtarefaId'];
            $nome        = trim($_POST['nome_subtarefa'] ?? '');
            $desc        = trim($_POST['descricao'] ?? '');
    
            if ($subtarefaId <= 0 || empty($nome)) {
                throw new Exception("ID ou nome de subtarefa inválido.");
            }
            
            // Apenas verifica se é Key Result (não verifica mais o criador)
            $check = $pdo->prepare("SELECT is_key_result FROM subtarefas_projetos WHERE id = :id");
            $check->execute([':id' => $subtarefaId]);
            $row = $check->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                throw new Exception("Subtarefa não encontrada.");
            }
            
            // Mantém apenas a restrição de Key Result
            if ($row['is_key_result'] == 1) {
                throw new Exception("Esta subtarefa é um Key Result e não pode ser editada.");
            }
    
            // Faz update
            $upd = $pdo->prepare("UPDATE subtarefas_projetos SET nome_subtarefa = :nome, descricao = :desc WHERE id = :id");
            $upd->execute([':nome' => $nome, ':desc' => $desc, ':id' => $subtarefaId]);
    
            echo json_encode(['success' => true, 'message' => 'Subtarefa editada com sucesso.']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // (7) Excluir subtarefa (AJAX)
    if (isset($_POST['action']) && $_POST['action'] === 'excluir_subtarefa') {
        header('Content-Type: application/json');
        try {
            global $usuarioLogadoId;
            $subtarefaId = (int)$_POST['subtarefaId'];
            if ($subtarefaId <= 0) {
                throw new Exception("ID de subtarefa inválido.");
            }
            
            // Apenas verifica se é Key Result (não verifica mais o criador)
            $check = $pdo->prepare("SELECT is_key_result FROM subtarefas_projetos WHERE id = :id");
            $check->execute([':id' => $subtarefaId]);
            $row = $check->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                throw new Exception("Subtarefa não encontrada.");
            }
            
            // Mantém apenas a restrição de Key Result
            if ($row['is_key_result'] == 1) {
                throw new Exception("Esta subtarefa é um Key Result e não pode ser excluída.");
            }
    
            // Exclui ela e filhas (recursivo)
            $pdo->beginTransaction();
            $stack = [$subtarefaId];
            while (!empty($stack)) {
                $current = array_pop($stack);
                $filhasStmt = $pdo->prepare("SELECT id FROM subtarefas_projetos WHERE parent_subtarefa_id = :pid");
                $filhasStmt->execute([':pid' => $current]);
                $filhas = $filhasStmt->fetchAll(PDO::FETCH_COLUMN);
    
                foreach ($filhas as $f) {
                    $stack[] = $f;
                }
                $del = $pdo->prepare("DELETE FROM subtarefas_projetos WHERE id = :id");
                $del->execute([':id' => $current]);
            }
            $pdo->commit();
    
            echo json_encode(['success' => true, 'message' => 'Subtarefa (e filhas) excluída(s) com sucesso.']);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // (8) Listar anexos (AJAX)
    if (isset($_POST['action']) && $_POST['action'] === 'listar_anexos') {
        header('Content-Type: application/json');
        $projetoId = (int)$_POST['projetoId'];
        if ($projetoId <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID de projeto inválido.']);
            exit;
        }
        $stmt = $pdo->prepare("
            SELECT a.*, u.Nome AS nomeUsuario
              FROM anexos_projetos a
         LEFT JOIN usuarios u ON a.usuario_id = u.Id
             WHERE a.projeto_id = :pid
          ORDER BY a.id DESC
        ");
        $stmt->execute([':pid' => $projetoId]);
        $anexos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'anexos' => $anexos]);
        exit;
    }

    // (9) Upload de anexo (AJAX)
    if (isset($_POST['action']) && $_POST['action'] === 'upload_anexo') {
        header('Content-Type: application/json');
        try {
            $projetoId = (int)$_POST['projetoId'];
            if ($projetoId <= 0) {
                throw new Exception("Projeto inválido para upload de anexo.");
            }
            if (!isset($_FILES['arquivo']) || $_FILES['arquivo']['error'] != 0) {
                throw new Exception("Erro no upload do arquivo.");
            }
            $uploadDir = 'uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $tmpName  = $_FILES['arquivo']['tmp_name'];
            $fileName = basename($_FILES['arquivo']['name']);
            $destino  = $uploadDir . uniqid() . '_' . $fileName;
            if (!move_uploaded_file($tmpName, $destino)) {
                throw new Exception("Falha ao mover o arquivo para o servidor.");
            }
            global $usuarioLogadoId;
            $dataUpload = date('Y-m-d H:i:s');

            $sql = "INSERT INTO anexos_projetos (projeto_id, usuario_id, nome_arquivo, caminho_arquivo, data_upload)
                    VALUES (:projId, :usrId, :nomeArq, :caminhoArq, :dataUpl)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':projId' => $projetoId,
                ':usrId' => $usuarioLogadoId,
                ':nomeArq' => $fileName,
                ':caminhoArq' => $destino,
                ':dataUpl' => $dataUpload
            ]);

            echo json_encode(['success' => true, 'message' => 'Anexo enviado com sucesso!']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // (10) Editar anexo (renomear) (AJAX)
    if (isset($_POST['action']) && $_POST['action'] === 'editar_anexos') {
        header('Content-Type: application/json');
        try {
            global $usuarioLogadoId;
            $anexoId  = (int)$_POST['anexoId'];
            $novoNome = trim($_POST['novo_nome'] ?? '');
            if ($anexoId <= 0 || empty($novoNome)) {
                throw new Exception("ID de anexo ou novo nome inválido.");
            }
            // Verifica se anexo é do user
            $check = $pdo->prepare("SELECT usuario_id FROM anexos_projetos WHERE id = :id");
            $check->execute([':id' => $anexoId]);
            $row = $check->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                throw new Exception("Anexo não encontrado.");
            }
            if ((int)$row['usuario_id'] !== (int)$usuarioLogadoId) {
                throw new Exception("Você não tem permissão para editar este anexo.");
            }
            // Atualiza
            $upd = $pdo->prepare("UPDATE anexos_projetos SET nome_arquivo = :novo WHERE id = :id");
            $upd->execute([':novo' => $novoNome, ':id' => $anexoId]);

            echo json_encode(['success' => true, 'message' => 'Anexo renomeado com sucesso.']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // (11) Excluir anexo (AJAX)
    if (isset($_POST['action']) && $_POST['action'] === 'excluir_anexo') {
        header('Content-Type: application/json');
        try {
            global $usuarioLogadoId;
            $anexoId = (int)$_POST['anexoId'];
            if ($anexoId <= 0) {
                throw new Exception("ID de anexo inválido.");
            }
            // Verifica se anexo é do user
            $check = $pdo->prepare("SELECT usuario_id, caminho_arquivo FROM anexos_projetos WHERE id = :id");
            $check->execute([':id' => $anexoId]);
            $row = $check->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                throw new Exception("Anexo não encontrado.");
            }
            if ((int)$row['usuario_id'] !== (int)$usuarioLogadoId) {
                throw new Exception("Você não tem permissão para excluir este anexo.");
            }
            // Exclui do DB e do disco
            $del = $pdo->prepare("DELETE FROM anexos_projetos WHERE id = :id");
            $del->execute([':id' => $anexoId]);

            $file = $row['caminho_arquivo'];
            if (file_exists($file)) {
                @unlink($file);
            }
            echo json_encode(['success' => true, 'message' => 'Anexo excluído com sucesso.']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // (12) Listar atividades (comentários) (AJAX)
    if (isset($_POST['action']) && $_POST['action'] === 'listar_atividades') {
        header('Content-Type: application/json');
        $projetoId = (int)$_POST['projetoId'];
        if ($projetoId <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID de projeto inválido.']);
            exit;
        }
        $stmt = $pdo->prepare("
            SELECT a.*, u.Nome AS nomeUsuario
              FROM atividades_projetos a
         LEFT JOIN usuarios u ON a.usuario_id = u.Id
             WHERE a.projeto_id = :pid
          ORDER BY a.id DESC
        ");
        $stmt->execute([':pid' => $projetoId]);
        $atividades = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'atividades' => $atividades]);
        exit;
    }

    // (13) Adicionar atividade (AJAX)
    if (isset($_POST['action']) && $_POST['action'] === 'adicionar_atividade') {
        header('Content-Type: application/json');
        try {
            $projetoId  = (int)$_POST['projetoId'];
            $comentario = trim($_POST['comentario'] ?? '');
            if ($projetoId <= 0 || empty($comentario)) {
                throw new Exception("Projeto ou texto do comentário inválido.");
            }
            global $usuarioLogadoId;
            $agora = date('Y-m-d H:i:s');

            $sql = "INSERT INTO atividades_projetos (projeto_id, usuario_id, comentario, data_hora)
                    VALUES (:projId, :usrId, :coment, :dataH)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':projId' => $projetoId,
                ':usrId'  => $usuarioLogadoId,
                ':coment' => $comentario,
                ':dataH'  => $agora
            ]);
            echo json_encode(['success' => true, 'message' => 'Comentário adicionado com sucesso!']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // (14) Editar atividade (AJAX) - (se quiser habilitar)
    if (isset($_POST['action']) && $_POST['action'] === 'editar_atividade') {
        header('Content-Type: application/json');
        try {
            global $usuarioLogadoId;
            $atividadeId = (int)$_POST['atividadeId'];
            $texto       = trim($_POST['novo_texto'] ?? '');
            if ($atividadeId <= 0 || empty($texto)) {
                throw new Exception("ID de atividade ou novo texto inválido.");
            }
            $check = $pdo->prepare("SELECT usuario_id FROM atividades_projetos WHERE id = :id");
            $check->execute([':id' => $atividadeId]);
            $row = $check->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                throw new Exception("Atividade não encontrada.");
            }
            if ((int)$row['usuario_id'] !== (int)$usuarioLogadoId) {
                throw new Exception("Você não tem permissão para editar esta atividade.");
            }
            $upd = $pdo->prepare("UPDATE atividades_projetos SET comentario = :txt WHERE id = :id");
            $upd->execute([':txt' => $texto, ':id' => $atividadeId]);

            echo json_encode(['success' => true, 'message' => 'Atividade atualizada com sucesso.']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // (15) Excluir atividade (AJAX)
    if (isset($_POST['action']) && $_POST['action'] === 'excluir_atividade') {
        header('Content-Type: application/json');
        try {
            global $usuarioLogadoId;
            $atividadeId = (int)$_POST['atividadeId'];
            if ($atividadeId <= 0) {
                throw new Exception("ID de atividade inválido.");
            }
            $check = $pdo->prepare("SELECT usuario_id FROM atividades_projetos WHERE id = :id");
            $check->execute([':id' => $atividadeId]);
            $row = $check->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                throw new Exception("Atividade não encontrada.");
            }
            if ((int)$row['usuario_id'] !== (int)$usuarioLogadoId) {
                throw new Exception("Você não tem permissão para excluir esta atividade.");
            }
            $del = $pdo->prepare("DELETE FROM atividades_projetos WHERE id = :id");
            $del->execute([':id' => $atividadeId]);

            echo json_encode(['success' => true, 'message' => 'Atividade excluída com sucesso.']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

  // (16) Alternar Key Result (AJAX)
if (isset($_POST['action']) && $_POST['action'] === 'toggle_key_result') {
    header('Content-Type: application/json');

    try {
        $subtarefaId = (int) ($_POST['subtarefaId'] ?? 0);
        $isKeyResult = (int) ($_POST['is_key_result'] ?? 0); // 0 ou 1

        if ($subtarefaId <= 0) {
            throw new Exception("ID de subtarefa inválido.");
        }

        // Buscar o projeto_id associado à subtarefa
        $stmtProjeto = $pdo->prepare("
            SELECT sp.projeto_id, sp.parent_subtarefa_id
            FROM subtarefas_projetos sp
            WHERE sp.id = :id
        ");
        $stmtProjeto->execute([':id' => $subtarefaId]);
        $subtarefaInfo = $stmtProjeto->fetch(PDO::FETCH_ASSOC);

        if (!$subtarefaInfo) {
            throw new Exception("Subtarefa não encontrada.");
        }

        // Verificar se é uma tarefa raiz (sem pai)
        if ($subtarefaInfo['parent_subtarefa_id'] !== null) {
            throw new Exception("Apenas tarefas raiz podem ser marcadas como Key Result.");
        }

        // Buscar informações do projeto para verificar permissões
        $stmtProjetoInfo = $pdo->prepare("
            SELECT ResponsavelId, UsuariosEnvolvidos
            FROM projetos
            WHERE Id = :projeto_id
        ");
        $stmtProjetoInfo->execute([':projeto_id' => $subtarefaInfo['projeto_id']]);
        $projetoInfo = $stmtProjetoInfo->fetch(PDO::FETCH_ASSOC);

        if (!$projetoInfo) {
            throw new Exception("Projeto não encontrado.");
        }

        // Verificar permissões
        $temPermissao = false;

        // Perfis com permissão (Admin, Gestor, Sub Diretor)
        $perfisPermitidos = [1, 2, 9];
        if (in_array($perfilAcesso, $perfisPermitidos)) {
            $temPermissao = true;
        } 
        // Verificar se o usuário é o responsável pelo projeto
        else if ((int)$projetoInfo['ResponsavelId'] === $usuarioLogadoId) {
            $temPermissao = true;
        } 
        // Verificar se o usuário está entre os usuários envolvidos
        else if (!empty($projetoInfo['UsuariosEnvolvidos'])) {
            $usuariosEnvolvidos = explode(',', $projetoInfo['UsuariosEnvolvidos']);
            if (in_array($usuarioLogadoId, $usuariosEnvolvidos)) {
                $temPermissao = true;
            }
        }

        // Se não tem permissão, retorna erro
        if (!$temPermissao) {
            throw new Exception("Você não tem permissão para alterar Key Results.");
        }

        // Atualiza o flag de Key Result
        $upd = $pdo->prepare("
            UPDATE subtarefas_projetos
            SET is_key_result = :keyResult
            WHERE id = :id
        ");
        $upd->execute([
            ':keyResult' => $isKeyResult,
            ':id'        => $subtarefaId
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Estado Key Result atualizado com sucesso.'
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    exit;
}


// (17) Liberar OKR
if (isset($_POST['action']) && $_POST['action'] === 'liberar_okr') {
    header('Content-Type: application/json');
    try {
        // 1) Projeto válido?
        $projetoId = isset($_POST['projetoId'])
                   ? (int) $_POST['projetoId']
                   : 0;
        if ($projetoId <= 0) {
            throw new Exception("Projeto inválido para liberar OKR.");
        }

        // 2) Determina qual setor estamos liberando
        // Para o Admin (perfil 1), vamos obter o setor do próprio projeto
        if ($perfilAcesso == 1) {
            // Para Admin, obter o setor do próprio projeto
            $stmtSetor = $pdo->prepare("SELECT SetorRelacionadoId FROM projetos WHERE Id = :projetoId");
            $stmtSetor->execute([':projetoId' => $projetoId]);
            $setorAlvo = (int) $stmtSetor->fetchColumn();
            
            if ($setorAlvo <= 0) {
                // Se ainda não tiver setor, verificar se foi passado por POST
                $setorAlvo = isset($_POST['setorOKR']) ? (int) $_POST['setorOKR'] : 0;
            }
        } 
        // Para outros perfis com permissão (Gestor e Sub Diretor)
        else if (in_array($perfilAcesso, array(2, 9), true)) {
            $setorAlvo = isset($_POST['setorOKR'])
                       ? (int) $_POST['setorOKR']
                       : ($userSetorId ?? 0);
        } 
        // Demais perfis só podem liberar projetos do próprio setor
        else {
            $setorAlvo = $userSetorId ?? 0;
        }
        
        // Validação adicional: garantir que o setor seja válido
        if ($setorAlvo <= 0) {
            throw new Exception("Setor não definido ou inválido para liberação de OKR.");
        }

        // Verificar se o projeto atende aos requisitos para liberação
        $checkSql = "SELECT Status, DisponivelOKR, SetorRelacionadoId FROM projetos WHERE Id = :id";
        $stmtCheck = $pdo->prepare($checkSql);
        $stmtCheck->execute([':id' => $projetoId]);
        $projetoInfo = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if (!$projetoInfo) {
            throw new Exception("Projeto não encontrado.");
        }

        if ($projetoInfo['Status'] !== 'andamento') {
            throw new Exception("O projeto precisa estar em andamento para ser liberado para OKR.");
        }

        if ($projetoInfo['DisponivelOKR'] != 1) {
            throw new Exception("O projeto não está disponível para OKR.");
        }

        // Se não for Admin/Sub-Diretor, verificar setor
        if (!in_array($perfilAcesso, [1, 9]) && $projetoInfo['SetorRelacionadoId'] != $setorAlvo) {
            throw new Exception("Você não tem permissão para liberar este projeto para OKR.");
        }

        // 3) Quantidade desejada de OKR para esse setor (fallback = 1)
        if (isset($_SESSION['ultimaQuantidadeOKR'][$setorAlvo])) {
            $desiredOKR = (int) $_SESSION['ultimaQuantidadeOKR'][$setorAlvo];
        } else {
            $desiredOKR = getBvpLimit($pdo, $setorAlvo);
            if ($desiredOKR <= 0) {
                $desiredOKR = 1;
            }
        }

        // 4) Conta quantos já estão em OKR neste setor
        $stmtCount = $pdo->prepare("
            SELECT COUNT(*) 
              FROM projetos 
             WHERE SetorRelacionadoId = :setor
               AND EnviadoFunil = 1 
               AND Status = 'andamento'
        ");
        $stmtCount->execute([':setor' => $setorAlvo]);
        $currentOKR = (int) $stmtCount->fetchColumn();

        if ($currentOKR >= $desiredOKR) {
            throw new Exception("O limite de projetos em OKR já foi atingido para este setor.");
        }

        // 5) Realiza a liberação para OKR
        $stmtUpdate = $pdo->prepare("
            UPDATE projetos
               SET EnviadoFunil = 1,
                   dtliberadookr = NOW()
             WHERE Id = :id
        ");
        $stmtUpdate->execute([':id' => $projetoId]);

        echo json_encode([
            'success' => true,
            'message' => "Projeto liberado para OKR com sucesso!",
            'projetoId' => $projetoId
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    exit;
}



// Listar arquivos do orçamento macro (AJAX)
if (isset($_POST['action']) && $_POST['action'] === 'listar_arquivos_orcamento') {
    header('Content-Type: application/json');
    $projetoId = (int)$_POST['projetoId'];
    if ($projetoId <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID de projeto inválido.']);
        exit;
    }
    
    $stmt = $pdo->prepare("
        SELECT arquivo_nome, arquivo_original, data_upload
        FROM projetos_arquivos 
        WHERE projeto_id = :pid AND tipo_arquivo = 'orcamento_macro'
        ORDER BY data_upload DESC
    ");
    $stmt->execute([':pid' => $projetoId]);
    $arquivos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'arquivos' => $arquivos]);
    exit;
}

// (17) Formulários criar/editar/excluir projeto e marcar BVP
if (isset($_POST['acao'])) {
    $acao = $_POST['acao'];
    try {
        // Criar
        if ($acao === 'criar') {
            // Campos obrigatórios e existentes
            $setorRelacionadoId      = $_POST['SetorRelacionadoId'] ?? null;
            $nomeProjeto             = trim($_POST['NomeProjeto'] ?? '');
            $dataCriacao             = $_POST['DataCriacao'] ?? date('Y-m-d');
            $prioridade              = $_POST['Prioridade'] ?? '';
            $responsavelId           = $_POST['ResponsavelId'] ?? null;
            $prazo                   = $_POST['Prazo'] ?? 'Curto';
            $outroSetorEnvolvido     = !empty($_POST['OutroSetorEnvolvido'])
                                       ? implode(',', $_POST['OutroSetorEnvolvido'])
                                       : '';
            $usuariosEnvolvidos      = !empty($_POST['UsuariosEnvolvidos'])
                                       ? implode(',', $_POST['UsuariosEnvolvidos'])
                                       : '';
            $dependenciasProjetos    = !empty($_POST['DependenciasProjetos'])
                                       ? implode(',', $_POST['DependenciasProjetos'])
                                       : '';
            $alineamentoEstrategico  = (int)($_POST['AlinhamentoEstrategico'] ?? 0);
            $vulnerabilidadeCiberseg = (int)($_POST['VulnerabilidadeCiberseguranca'] ?? 0);
            $lpd_dpo_criptografado   = (int)($_POST['LPD_DPO_Criptografado'] ?? 0);
            $impactoOperacional      = (int)($_POST['ImpactoOperacional'] ?? 0);
            $impactoAdministrativo   = (int)($_POST['ImpactoAdministrativo'] ?? 0);
            $impactoFinanceiro       = (int)($_POST['ImpactoFinanceiro'] ?? 0);

            // Campos novos: tipo, sigilo, motivo, qualificação, descrição, orçamento macro e valor estimado
            $tipo_projeto    = $_POST['tipo_projeto'] ?? '';
            $sigilo_projeto  = $_POST['sigilo_projeto'] ?? 'Público';
            $motivo_sigilo   = trim($_POST['motivo_sigilo'] ?? '');
            $qualificacao    = !empty($_POST['qualificacao'])
                               ? implode(',', $_POST['qualificacao'])
                               : '';
            $descricao_projeto = trim($_POST['descricao_projeto'] ?? '');

            // Orçamento Macro (texto) e Valor Estimado (decimal)
            $orcamentoMacroRaw = trim($_POST['orcamento_macro'] ?? '');
            $orcamento_macro   = $orcamentoMacroRaw; // já em texto livre

            $valorEstimadoRaw  = $_POST['valor_estimado'] ?? null;
            $valorEstimado     = null;
            if ($valorEstimadoRaw !== null && $valorEstimadoRaw !== '') {
                // Normaliza vírgula para ponto, se enviado no formato "1.234,56"
                $valorNormalizado = str_replace(',', '.', $valorEstimadoRaw);
                if (is_numeric($valorNormalizado)) {
                    $valorEstimado = floatval($valorNormalizado);
                }
            }

            // Verificação de campos obrigatórios
            if (empty($nomeProjeto) || empty($setorRelacionadoId) || empty($responsavelId)) {
                throw new Exception("Por favor, preencha todos os campos obrigatórios.");
            }

            // Monta o INSERT incluindo columns orcamento_macro e valor_estimado
            $sqlInsert = "
                INSERT INTO projetos (
                    SetorRelacionadoId,
                    NomeProjeto,
                    DataCriacao,
                    Prioridade,
                    ResponsavelId,
                    EnviadoFunil,
                    Prazo,
                    OutroSetorEnvolvido,
                    UsuariosEnvolvidos,
                    DependenciasProjetos,
                    AlinhamentoEstrategico,
                    VulnerabilidadeCiberseguranca,
                    LPD_DPO_Criptografado,
                    ImpactoOperacional,
                    ImpactoAdministrativo,
                    ImpactoFinanceiro,
                    tipo_projeto,
                    sigilo_projeto,
                    motivo_sigilo,
                    qualificacao,
                    descricao_projeto,
                    orcamento_macro,
                    valor_estimado,
                    DisponivelOKR,
                    Status,
                    DataCadastro
                ) VALUES (
                    :SetorRelacionadoId,
                    :NomeProjeto,
                    :DataCriacao,
                    :Prioridade,
                    :ResponsavelId,
                    0,
                    :Prazo,
                    :OutroSetorEnvolvido,
                    :UsuariosEnvolvidos,
                    :DependenciasProjetos,
                    :AlinhamentoEstrategico,
                    :VulnerabilidadeCiberseguranca,
                    :LPD_DPO_Criptografado,
                    :ImpactoOperacional,
                    :ImpactoAdministrativo,
                    :ImpactoFinanceiro,
                    :tipo_projeto,
                    :sigilo_projeto,
                    :motivo_sigilo,
                    :qualificacao,
                    :descricao_projeto,
                    :orcamento_macro,
                    :valor_estimado,
                    0,
                    'backlog',
                    NOW()
                )
            ";

            $stmt = $pdo->prepare($sqlInsert);
            $stmt->execute([
                ':SetorRelacionadoId'            => $setorRelacionadoId,
                ':NomeProjeto'                   => $nomeProjeto,
                ':DataCriacao'                   => $dataCriacao,
                ':Prioridade'                    => $prioridade,
                ':ResponsavelId'                 => $responsavelId,
                ':Prazo'                         => $prazo,
                ':OutroSetorEnvolvido'           => $outroSetorEnvolvido,
                ':UsuariosEnvolvidos'            => $usuariosEnvolvidos,
                ':DependenciasProjetos'          => $dependenciasProjetos,
                ':AlinhamentoEstrategico'        => $alineamentoEstrategico,
                ':VulnerabilidadeCiberseguranca'  => $vulnerabilidadeCiberseg,
                ':LPD_DPO_Criptografado'         => $lpd_dpo_criptografado,
                ':ImpactoOperacional'            => $impactoOperacional,
                ':ImpactoAdministrativo'         => $impactoAdministrativo,
                ':ImpactoFinanceiro'             => $impactoFinanceiro,
                ':tipo_projeto'                  => $tipo_projeto,
                ':sigilo_projeto'                => $sigilo_projeto,
                ':motivo_sigilo'                 => $motivo_sigilo,
                ':qualificacao'                  => $qualificacao,
                ':descricao_projeto'             => $descricao_projeto,
                ':orcamento_macro'               => $orcamento_macro,
                ':valor_estimado'                => $valorEstimado
            ]);

            // Pega o ID do projeto recém criado
            $novoProjetoId = $pdo->lastInsertId();

            // Processa arquivos do orçamento macro
            if (isset($_FILES['orcamento_macro_files']) && !empty($_FILES['orcamento_macro_files']['name'][0])) {
                $uploadDir = 'uploads/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $totalFiles = count($_FILES['orcamento_macro_files']['name']);
                for ($i = 0; $i < $totalFiles; $i++) {
                    if ($_FILES['orcamento_macro_files']['error'][$i] === UPLOAD_ERR_OK) {
                        $nomeOriginal = $_FILES['orcamento_macro_files']['name'][$i];
                        $nomeArquivo = uniqid() . '_' . $nomeOriginal;
                        $caminhoDestino = $uploadDir . $nomeArquivo;
                        
                        if (move_uploaded_file($_FILES['orcamento_macro_files']['tmp_name'][$i], $caminhoDestino)) {
                            // Salva na tabela projetos_arquivos
                            $sqlArquivo = "INSERT INTO projetos_arquivos (projeto_id, arquivo_nome, arquivo_original, tipo_arquivo, data_upload) 
                                           VALUES (:projeto_id, :arquivo_nome, :arquivo_original, :tipo_arquivo, NOW())";
                            $stmtArquivo = $pdo->prepare($sqlArquivo);
                            $stmtArquivo->execute([
                                ':projeto_id' => $novoProjetoId,
                                ':arquivo_nome' => $nomeArquivo,
                                ':arquivo_original' => $nomeOriginal,
                                ':tipo_arquivo' => 'orcamento_macro'
                            ]);
                        }
                    }
                }
            }

            // Atualiza fila OKR para o setor inserido
            $setorId = isset($_POST['SetorRelacionadoId'])
                       ? (int)$_POST['SetorRelacionadoId']
                       : null;
            if ($setorId === null) {
                die('SetorRelacionadoId não informado para atualizar fila OKR!');
            }
            atualizarFilaOKR($pdo, $setorId);

            // Mensagem de sucesso e redirecionamento
            $_SESSION['message'] = "Projeto cadastrado com sucesso!";
            header('Location: projetos');
            exit;
        }

        if ($acao === 'autorizar') {
            header('Content-Type: application/json');
            try {
                $projetoId = (int)($_POST['projeto_id'] ?? 0);
                if (!$projetoId) throw new Exception("Projeto inválido.");
                
                // Apenas perfis 1,2,4,5,8,9 podem autorizar
                if (!in_array($perfilAcesso, [1,2,4,5,8,9])) throw new Exception("Sem permissão.");
        
                // Atualiza o status de backlog para andamento
                $stmt = $pdo->prepare("UPDATE projetos SET Status = 'andamento' WHERE Id = :id AND Status = 'backlog'");
                $stmt->execute([':id' => $projetoId]);
                if ($stmt->rowCount() > 0) {
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Não foi possível autorizar.']);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
        }

        if (isset($_POST['acao']) && $_POST['acao'] === 'autorizar') {
            header('Content-Type: application/json');
            try {
                $projetoId = (int)($_POST['projeto_id'] ?? 0);
                if (!$projetoId) throw new Exception("Projeto inválido.");
                if (!in_array($perfilAcesso, [1,2,4,5,8,9])) throw new Exception("Sem permissão.");
                $stmt = $pdo->prepare("UPDATE projetos SET Status = 'andamento' WHERE Id = :id AND Status = 'backlog'");
                $stmt->execute([':id' => $projetoId]);
                if ($stmt->rowCount() > 0) {
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Não foi possível autorizar.']);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
        }

        if (isset($_POST['acao']) && $_POST['acao'] === 'voltar_backlog') {
            header('Content-Type: application/json');
            try {
                $projetoId = (int)($_POST['projeto_id'] ?? 0);
                // Perfis permitidos
                if (!in_array($perfilAcesso, [1,2,4,5,8,9])) throw new Exception("Sem permissão.");
                if (!$projetoId) throw new Exception("Projeto inválido.");
                // Só permite voltar projetos em andamento
                $stmt = $pdo->prepare("UPDATE projetos SET Status = 'backlog' WHERE Id = :id AND Status = 'andamento'");
                $stmt->execute([':id' => $projetoId]);
                if ($stmt->rowCount() > 0) {
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Não foi possível mover para backlog.']);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
        }

        // Marcar top X OKRs
        if ($acao === 'marcarOKRs') {
            // pega quantidade e setor
            $qtd = (int)($_POST['quantidadeOKR'] ?? 0);
            if ($qtd < 1) throw new Exception("...");
            if (in_array($perfilAcesso, [1,2,9])) {
                $setorAlvo = (int)($_POST['setorOKR'] ?? 0);
                if (!$setorAlvo) throw new Exception("Selecione um setor.");
            } else {
                $setorAlvo = $userSetorId;
            }
            $_SESSION['ultimaQuantidadeOKR'][$setorAlvo] = $qtd;
            setBvpLimit($pdo, $setorAlvo, $qtd);
            atualizarFilaOKR($pdo, $setorAlvo); // <-- Aqui estava o erro
            $_SESSION['message'] = "{$qtd} projetos liberados para OKR neste setor!";
            header('Location: projetos');
            exit;
        }

        // Excluir
        if ($acao === 'excluir') {
            $idExcluir = (int)($_POST['projeto_id'] ?? 0);
            if ($idExcluir > 0) {
                // Busca o setor relacionado do projeto antes de excluir
                $stmtSetor = $pdo->prepare("SELECT SetorRelacionadoId FROM projetos WHERE Id = :id");
                $stmtSetor->bindValue(':id', $idExcluir, PDO::PARAM_INT);
                $stmtSetor->execute();
                $projeto = $stmtSetor->fetch(PDO::FETCH_ASSOC);

                $setorId = isset($projeto['SetorRelacionadoId']) ? (int)$projeto['SetorRelacionadoId'] : null;

                // Remove arquivos relacionados ao projeto
                $stmtArquivos = $pdo->prepare("SELECT arquivo_nome FROM projetos_arquivos WHERE projeto_id = :id");
                $stmtArquivos->execute([':id' => $idExcluir]);
                $arquivos = $stmtArquivos->fetchAll(PDO::FETCH_ASSOC);
                
                // Exclui arquivos físicos
                foreach ($arquivos as $arquivo) {
                    $caminhoArquivo = 'uploads/' . $arquivo['arquivo_nome'];
                    if (file_exists($caminhoArquivo)) {
                        @unlink($caminhoArquivo);
                    }
                }
                
                // Remove registros de arquivos
                $stmtDelArquivos = $pdo->prepare("DELETE FROM projetos_arquivos WHERE projeto_id = :id");
                $stmtDelArquivos->execute([':id' => $idExcluir]);

                // Remove registro do projeto
                $stmtDel = $pdo->prepare("DELETE FROM projetos WHERE Id = :id");
                $stmtDel->bindValue(':id', $idExcluir, PDO::PARAM_INT);
                $stmtDel->execute();

                // Atualiza fila OKR apenas se conseguiu identificar o setor
                if ($setorId !== null) {
                    atualizarFilaOKR($pdo, $setorId);
                }
                $_SESSION['message'] = "Projeto excluído com sucesso!";
            }
            header('Location: projetos');
            exit;
        }

        // Editar
        if ($acao === 'editar') {
            $projetoId               = (int)($_POST['projeto_id'] ?? 0);
            $setorRelacionadoId      = $_POST['SetorRelacionadoId'] ?? null;
            $nomeProjeto             = trim($_POST['NomeProjeto'] ?? '');
            $dataCriacao             = $_POST['DataCriacao'] ?? date('Y-m-d');
            $prioridade              = $_POST['Prioridade'] ?? '';
            $responsavelId           = $_POST['ResponsavelId'] ?? null;
            $prazo                   = $_POST['Prazo'] ?? 'Curto';

            $outroSetorEnvolvido     = !empty($_POST['OutroSetorEnvolvido'])    ? implode(',', $_POST['OutroSetorEnvolvido'])    : '';
            $usuariosEnvolvidos      = !empty($_POST['UsuariosEnvolvidos'])     ? implode(',', $_POST['UsuariosEnvolvidos'])     : '';
            $dependenciasProjetos    = !empty($_POST['DependenciasProjetos'])   ? implode(',', $_POST['DependenciasProjetos'])   : '';

            $alineamentoEstrategico  = (int)($_POST['AlinhamentoEstrategico']       ?? 0);
            $vulnerabilidadeCiberseg = (int)($_POST['VulnerabilidadeCiberseguranca'] ?? 0);
            $lpd_dpo_criptografado   = (int)($_POST['LPD_DPO_Criptografado']        ?? 0);
            $impactoOperacional      = (int)($_POST['ImpactoOperacional']           ?? 0);
            $impactoAdministrativo   = (int)($_POST['ImpactoAdministrativo']        ?? 0);
            $impactoFinanceiro       = (int)($_POST['ImpactoFinanceiro']            ?? 0);

            // NOVOS CAMPOS
            $tipo_projeto      = $_POST['tipo_projeto']    ?? '';
            $sigilo_projeto    = $_POST['sigilo_projeto']  ?? 'Público';
            $motivo_sigilo     = trim($_POST['motivo_sigilo'] ?? '');
            $qualificacao      = !empty($_POST['qualificacao']) ? implode(',', $_POST['qualificacao']) : '';
            $descricao_projeto = trim($_POST['descricao_projeto'] ?? '');

            $orcamento_macro   = trim($_POST['orcamento_macro'] ?? '');
            $valor_estimado    = $_POST['valor_estimado'] !== '' ? str_replace(',', '.', $_POST['valor_estimado']) : null;

            if (!$projetoId || !$setorRelacionadoId || !$responsavelId || $nomeProjeto==='') {
                throw new Exception("Campos obrigatórios não preenchidos.");
            }

            $sql = "
                UPDATE projetos SET
                    SetorRelacionadoId            = :SetorRelacionadoId,
                    NomeProjeto                   = :NomeProjeto,
                    DataCriacao                   = :DataCriacao,
                    Prioridade                    = :Prioridade,
                    ResponsavelId                 = :ResponsavelId,
                    Prazo                         = :Prazo,
                    OutroSetorEnvolvido           = :OutroSetorEnvolvido,
                    UsuariosEnvolvidos            = :UsuariosEnvolvidos,
                    DependenciasProjetos          = :DependenciasProjetos,
                    AlinhamentoEstrategico        = :AlinhamentoEstrategico,
                    VulnerabilidadeCiberseguranca = :VulnerabilidadeCiberseguranca,
                    LPD_DPO_Criptografado         = :LPD_DPO_Criptografado,
                    ImpactoOperacional            = :ImpactoOperacional,
                    ImpactoAdministrativo         = :ImpactoAdministrativo,
                    ImpactoFinanceiro             = :ImpactoFinanceiro,
                    tipo_projeto                  = :tipo_projeto,
                    sigilo_projeto                = :sigilo_projeto,
                    motivo_sigilo                 = :motivo_sigilo,
                    qualificacao                  = :qualificacao,
                    descricao_projeto             = :descricao_projeto,
                    orcamento_macro               = :orcamento_macro,
                    valor_estimado                = :valor_estimado
                WHERE Id = :Id
            ";
            $pdo->prepare($sql)->execute([
                ':SetorRelacionadoId'            => $setorRelacionadoId,
                ':NomeProjeto'                   => $nomeProjeto,
                ':DataCriacao'                   => $dataCriacao,
                ':Prioridade'                    => $prioridade,
                ':ResponsavelId'                 => $responsavelId,
                ':Prazo'                         => $prazo,
                ':OutroSetorEnvolvido'           => $outroSetorEnvolvido,
                ':UsuariosEnvolvidos'            => $usuariosEnvolvidos,
                ':DependenciasProjetos'          => $dependenciasProjetos,
                ':AlinhamentoEstrategico'        => $alineamentoEstrategico,
                ':VulnerabilidadeCiberseguranca' => $vulnerabilidadeCiberseg,
                ':LPD_DPO_Criptografado'         => $lpd_dpo_criptografado,
                ':ImpactoOperacional'            => $impactoOperacional,
                ':ImpactoAdministrativo'         => $impactoAdministrativo,
                ':ImpactoFinanceiro'             => $impactoFinanceiro,
                ':tipo_projeto'                  => $tipo_projeto,
                ':sigilo_projeto'                => $sigilo_projeto,
                ':motivo_sigilo'                 => $motivo_sigilo,
                ':qualificacao'                  => $qualificacao,
                ':descricao_projeto'             => $descricao_projeto,
                ':orcamento_macro'               => $orcamento_macro,
                ':valor_estimado'                => $valor_estimado,
                ':Id'                            => $projetoId
            ]);

            // Processa novos arquivos do orçamento macro (se enviados)
            if (isset($_FILES['orcamento_macro_files']) && !empty($_FILES['orcamento_macro_files']['name'][0])) {
                $uploadDir = 'uploads/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $totalFiles = count($_FILES['orcamento_macro_files']['name']);
                for ($i = 0; $i < $totalFiles; $i++) {
                    if ($_FILES['orcamento_macro_files']['error'][$i] === UPLOAD_ERR_OK) {
                        $nomeOriginal = $_FILES['orcamento_macro_files']['name'][$i];
                        $nomeArquivo = uniqid() . '_' . $nomeOriginal;
                        $caminhoDestino = $uploadDir . $nomeArquivo;
                        
                        if (move_uploaded_file($_FILES['orcamento_macro_files']['tmp_name'][$i], $caminhoDestino)) {
                            // Salva na tabela projetos_arquivos
                            $sqlArquivo = "INSERT INTO projetos_arquivos (projeto_id, arquivo_nome, arquivo_original, tipo_arquivo, data_upload) 
                                           VALUES (:projeto_id, :arquivo_nome, :arquivo_original, :tipo_arquivo, NOW())";
                            $stmtArquivo = $pdo->prepare($sqlArquivo);
                            $stmtArquivo->execute([
                                ':projeto_id' => $projetoId,
                                ':arquivo_nome' => $nomeArquivo,
                                ':arquivo_original' => $nomeOriginal,
                                ':tipo_arquivo' => 'orcamento_macro'
                            ]);
                        }
                    }
                }
            }

            atualizarFilaOKR($pdo, (int)$setorRelacionadoId);

            /* ---------- resposta ---------- */
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {          // veio por fetch-AJAX
                echo json_encode(['success'=>true]);
                exit;
            }
            $_SESSION['message'] = "Projeto atualizado com sucesso!";
            header('Location: projetos');
            exit;
        }
    } catch (Exception $e) {
        $_SESSION['message'] = "Ocorreu um erro: " . $e->getMessage();
        header('Location: projetos');
        exit;
    }
}

} // Fim $_SERVER['REQUEST_METHOD'] === 'POST'


// ==========================
// Aplica filtros (GET) e monta a listagem com paginação
// ==========================
$anoFiltro   = $_GET['ano']   ?? '';
$setorFiltro = $_GET['setor'] ?? '';
$prazoFiltro = $_GET['prazo'] ?? '';


// Define o nome do setor do usuário (para exibir no filtro)
$userSetorName = '';
if (!in_array($perfilAcesso, [1,7]) && $userSetorId) {
    foreach ($setores as $s) {
        if ((int)$s['id'] === (int)$userSetorId) {
            $userSetorName = $s['nome_do_setor'];
            break;
        }
    }
}


// Parâmetros de paginação
$itemsPorPagina = isset($_GET['items_per_page']) ? (int)$_GET['items_per_page'] : 50; // Default: 10 itens
$paginaAtual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1; // Default: página 1
if ($paginaAtual < 1) $paginaAtual = 1;

// Primeiro conta o total para saber quantas páginas existem
$sqlCount = "
    SELECT COUNT(*) AS total
      FROM projetos p
     WHERE 1=1
       {$filtroPerfil}
";
$paramsCount = [];
if (!empty($anoFiltro)) {
    $sqlCount      .= " AND YEAR(p.DataCriacao) = :ano";
    $paramsCount[':ano'] = $anoFiltro;
}
if (!empty($setorFiltro)) {
    $sqlCount      .= " AND p.SetorRelacionadoId = :setor";
    $paramsCount[':setor'] = $setorFiltro;
}
if (!empty($prazoFiltro)) {
    $sqlCount      .= " AND p.Prazo = :prazo";
    $paramsCount[':prazo'] = $prazoFiltro;
}

$stmtCount = $pdo->prepare($sqlCount);
foreach ($paramsCount as $k => $v) {
    $stmtCount->bindValue($k, $v);
}
$stmtCount->execute();
$totalRegistros = (int)$stmtCount->fetchColumn();

// ==========================
// Estatísticas dinâmicas (após definir $filtroPerfil e $paramsCount)
// ==========================

// Determinar o setor atual para estatísticas com base no perfil
if (!empty($setorFiltro)) {
    // Se um filtro foi explicitamente aplicado, respeita-o
    $setorEstatisticas = (int)$setorFiltro;
} else {
    // Caso contrário, define com base no perfil
    if (in_array($perfilAcesso, [1, 7, 9])) {
        // Admin, Diretor e Sub-Diretor veem todos os setores por padrão
        $setorEstatisticas = 0;
    } else {
        // Demais perfis veem apenas seu próprio setor
        $setorEstatisticas = $userSetorId;
    }
}

// Obter o nome do setor para exibição
$setorNomeEstatisticas = "TODOS SETORES";
if ($setorEstatisticas > 0) {
    foreach ($setores as $s) {
        if ((int)$s['id'] === $setorEstatisticas) {
            $setorNomeEstatisticas = htmlspecialchars($s['nome_do_setor']);
            break;
        }
    }
}

// Construir o queryBase especificamente para as estatísticas
// Isso garante que mesmo sem filtro GET, as estatísticas respeitem o setor do usuário
$queryBaseEstatisticas = "
    FROM projetos p
    WHERE 1=1
    {$filtroPerfil}
";

$paramsEstatisticas = [];

// Se for perfil diferente de Admin/Diretor/Sub-Diretor e não tiver filtro explícito
// adiciona filtro do setor do usuário nas estatísticas
if (!in_array($perfilAcesso, [1, 7, 9]) && empty($setorFiltro) && $userSetorId) {
    $queryBaseEstatisticas .= " AND p.SetorRelacionadoId = :setorEstat";
    $paramsEstatisticas[':setorEstat'] = $userSetorId;
}

// Se houver um filtro explícito de setor, usa ele
if (!empty($setorFiltro)) {
    $queryBaseEstatisticas .= " AND p.SetorRelacionadoId = :setorEstat";
    $paramsEstatisticas[':setorEstat'] = $setorFiltro;
}

// Adiciona outros filtros GET
if (!empty($anoFiltro)) {
    $queryBaseEstatisticas .= " AND YEAR(p.DataCriacao) = :anoEstat";
    $paramsEstatisticas[':anoEstat'] = $anoFiltro;
}
if (!empty($prazoFiltro)) {
    $queryBaseEstatisticas .= " AND p.Prazo = :prazoEstat";
    $paramsEstatisticas[':prazoEstat'] = $prazoFiltro;
}

// (1) Total de projetos
$stmt = $pdo->prepare("SELECT COUNT(*) AS total {$queryBaseEstatisticas}");
foreach ($paramsEstatisticas as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->execute();
$totalProjetos = (int)$stmt->fetchColumn();

// (2) Liberados para OKR
$stmt = $pdo->prepare("SELECT COUNT(*) {$queryBaseEstatisticas} AND DisponivelOKR = 1 AND EnviadoFunil = 0 AND Status = 'andamento'");
foreach ($paramsEstatisticas as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->execute();
$totalLiberados = (int)$stmt->fetchColumn();

// (3) Em OKR
$stmt = $pdo->prepare("SELECT COUNT(*) {$queryBaseEstatisticas} AND EnviadoFunil = 1 AND Status = 'andamento'");
foreach ($paramsEstatisticas as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->execute();
$totalEmOKR = (int)$stmt->fetchColumn();

// (4) Bloqueados
$stmt = $pdo->prepare("SELECT COUNT(*) {$queryBaseEstatisticas} AND Status = 'bloqueado'");
foreach ($paramsEstatisticas as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->execute();
$totalBloqueados = (int)$stmt->fetchColumn();

// Calcular total de vagas BVP configuradas
$totalVagasBVP = calcularTotalVagasBVP($pdo, $setorEstatisticas);

// Cálculos finais
$vagasAbertas = max(0, $totalVagasBVP - $totalEmOKR);
$porcentagemUsada = $totalVagasBVP > 0 ? round(($totalEmOKR / $totalVagasBVP) * 100) : 0;

// O código para a paginação permanece o mesmo
$totalPaginas = ceil($totalRegistros / $itemsPorPagina);
if ($paginaAtual > $totalPaginas && $totalPaginas > 0) {
    $paginaAtual = $totalPaginas;
}

$offset = ($paginaAtual - 1) * $itemsPorPagina;

// O SQL para a listagem de projetos permanece o mesmo
$sql = "
    SELECT
      p.*,
      (p.AlinhamentoEstrategico
       + p.VulnerabilidadeCiberseguranca
       + p.LPD_DPO_Criptografado
       + p.ImpactoOperacional
       + p.ImpactoAdministrativo
       + p.ImpactoFinanceiro
      ) AS ValorOKR,
      u.Nome AS NomeResponsavel
    FROM projetos p
    LEFT JOIN usuarios u ON p.ResponsavelId = u.Id
    WHERE 1=1
      {$filtroPerfil}
";
$params = [];
if (!empty($anoFiltro)) {
    $sql     .= " AND YEAR(p.DataCriacao) = :ano";
    $params[':ano'] = $anoFiltro;
}
if (!empty($setorFiltro)) {
    $sql     .= " AND p.SetorRelacionadoId = :setor";
    $params[':setor'] = $setorFiltro;
}
if (!empty($prazoFiltro)) {
    $sql     .= " AND p.Prazo = :prazo";
    $params[':prazo'] = $prazoFiltro;
}

$sql .= " ORDER BY ValorOKR DESC
          LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':limit',  $itemsPorPagina, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset,         PDO::PARAM_INT);
$stmt->execute();
$projetos = $stmt->fetchAll(PDO::FETCH_ASSOC);
// IDs dos projetos da página atual
$idsProjetos = array_column($projetos, 'Id');
if (!empty($idsProjetos)) {
    $idsStr = implode(',', array_map('intval', $idsProjetos)); // segurança
} else {
    $idsStr = '';
}

// Busca os totais de keyresults e tarefas de todos os projetos da página
$totaisPorProjeto = [];
if ($idsStr) {
    $sqlTotais = "
        SELECT 
            projeto_id, 
            SUM(is_key_result = 1) AS total_keyresult, 
            COUNT(*) AS total_tarefas
        FROM subtarefas_projetos
        WHERE projeto_id IN ($idsStr)
        GROUP BY projeto_id
    ";
    $stmtTotais = $pdo->query($sqlTotais);
    foreach ($stmtTotais->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $totaisPorProjeto[$row['projeto_id']] = [
            'total_keyresult' => (int)$row['total_keyresult'],
            'total_tarefas'   => (int)$row['total_tarefas'],
        ];
    }
}

// Injeta os totais em cada projeto
foreach ($projetos as &$proj) {
    $projId = $proj['Id'];
    $proj['total_keyresult'] = $totaisPorProjeto[$projId]['total_keyresult'] ?? 0;
    $proj['total_tarefas']   = $totaisPorProjeto[$projId]['total_tarefas']   ?? 0;
}
unset($proj);




// ==========================
// Função para calcular total de vagas BVP (CORRIGIDA)
// ==========================
function calcularTotalVagasBVP(PDO $pdo, $setorId = null) {
    if ($setorId > 0) {
        // Para um setor específico
        $stmt = $pdo->prepare("SELECT quantidade FROM setor_bvp_config WHERE setor_id = ?");
        $stmt->execute([$setorId]);
        $quantidade = $stmt->fetchColumn();
        
        // Se encontrou uma configuração, usa o valor exato (que pode ser 0)
        if ($quantidade !== false) {
            return (int)$quantidade;
        }
        
        // Se não encontrou na tabela, tenta pegar da sessão
        if (isset($_SESSION['ultimaQuantidadeOKR'][$setorId])) {
            return (int)$_SESSION['ultimaQuantidadeOKR'][$setorId];
        }
        
        // Se não encontrou em lugar nenhum, retorna 0
        return 0;
    } else {
        // Para todos os setores
        $stmt = $pdo->query("SELECT SUM(quantidade) FROM setor_bvp_config");
        $total = (int)$stmt->fetchColumn();
        
        // Retorna o valor exato da soma (que pode ser 0)
        return $total;
    }
}

// Busca todos os projetos (já vindos do banco)
$projetosPorAno = []; // Ex: [2025 => [10,12,13,14], 2026 => [15,17,19] ...]
foreach ($projetos as $proj) {
    $ano = date('Y', strtotime($proj['DataCriacao']));
    if (!isset($projetosPorAno[$ano])) {
        $projetosPorAno[$ano] = [];
    }
    $projetosPorAno[$ano][] = $proj['Id'];
}

// Ordena os IDs de cada ano para garantir a ordem correta (menor para maior Id)
foreach ($projetosPorAno as &$ids) {
    sort($ids, SORT_NUMERIC);
}
unset($ids);

// Agora crie um array que armazena o número sequencial para cada projeto, no ano dele
$projetoSequencial = [];
foreach ($projetos as $proj) {
    $ano = date('Y', strtotime($proj['DataCriacao']));
    $sequencial = array_search($proj['Id'], $projetosPorAno[$ano]) + 1; // +1 para começar em 1
    $projetoSequencial[$proj['Id']] = sprintf('%02d-%s', $sequencial, $ano); // 01-2025, 02-2025, etc
}



?>

<!DOCTYPE html>
<html dir="ltr" lang="pt">
<head>
    <?php include 'head.php'; // SEU head ?>
    <style>
/* ===================================
   Estilos gerais e do tema escuro
=================================== */
body {
    background-color: #1f1f1f;
    color: #eaeaea;
    font-family: 'Roboto', sans-serif;
    margin: 0;
    padding: 0;
}

.preloader {
    position: fixed;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    z-index: 9999;
    background: #1f1f1f url('assets/images/preloader.gif') no-repeat center center;
}

/* Cards Genéricos */
.card {
    background-color: #23272a;
    border: none;
    border-radius: 15px;
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.5);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.card:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.8);
}

.card-body {
    padding: 25px;
}

/* ===================================
   Cards de Estatística (Topo da página)
=================================== */
.stats-card {
    background: linear-gradient(145deg, #1d2126, #212527);
    border-radius: 20px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.25);
    position: relative;
    overflow: hidden;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 20px;
    min-height: 140px;
    transition: transform 0.3s ease, box-shadow 0.3s ease, background 0.3s ease;
    cursor: pointer;
}

.stats-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 12px 36px rgba(0, 0, 0, 0.35);
    background: linear-gradient(145deg, #2e3235, #25292b);
}

.stats-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 4px;
    background: linear-gradient(90deg, rgba(0, 123, 255, 0), #007bff, rgba(0, 123, 255, 0));
    opacity: 0;
    transition: opacity 0.3s ease;
}

.stats-card:hover::before {
    opacity: 1;
}

.task-item.key-result {
    background-color:rgba(39, 165, 39, 0.08);
    border-radius: 5px;
}

.stats-icon-circle {
    position: relative;
    width: 80px;
    height: 80px;
    flex-shrink: 0;
    transition: transform 0.3s ease, filter 0.3s ease;
}

.stats-card:hover .stats-icon-circle {
    transform: scale(1.08);
    filter: brightness(1.2);
}

.stats-icon-circle svg {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    transform: rotate(-90deg);
}

.stats-icon-circle .circle-bg {
    fill: none;
    stroke: #3c4147;
    stroke-width: 3;
    transition: stroke 0.3s ease;
}

.stats-icon-circle .circle-progress {
    fill: none;
    stroke-width: 3;
    stroke-linecap: round;
    transition: stroke-dasharray 0.5s ease, stroke 0.3s ease;
}

.stats-icon-circle .circle-progress.bg-gradient-primary { stroke: #00b7ff; }
.stats-icon-circle .circle-progress.bg-gradient-success { stroke: #34c759; }
.stats-icon-circle .circle-progress.bg-gradient-info { stroke: #17c1e8; }
.stats-icon-circle .circle-progress.bg-gradient-danger { stroke: #ff4d6d; }

.stats-icon-circle i {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 32px;
    color: #fff;
    transition: color 0.3s ease;
}

.stats-card:hover .stats-icon-circle i {
    color: #00b7ff;
}

.stats-content {
    flex-grow: 1;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.stats-title {
    font-size: 0.95rem;
    font-weight: 600;
    color: #b8c1cc;
    text-transform: uppercase;
    letter-spacing: 1.2px;
    margin-bottom: 8px;
    transition: color 0.3s ease;
}

.stats-card:hover .stats-title {
    color: #fff;
}

.stats-number {
    font-size: 2.4rem;
    font-weight: 700;
    color: #fff;
    line-height: 1.1;
    transition: color 0.3s ease, transform 0.3s ease;
}

.stats-card:hover .stats-number {
    color: #00b7ff;
    transform: scale(1.05);
}

.stats-subtitle {
    font-size: 0.9rem;
    color: #8a929e;
    margin-top: 6px;
    font-style: italic;
    transition: color 0.3s ease;
}

.stats-card:hover .stats-subtitle {
    color: #a1aab2;
}

@media (max-width: 768px) {
    .stats-card {
        flex-direction: column;
        text-align: center;
        padding: 15px;
        min-height: 160px;
    }

    .stats-icon-circle {
        width: 60px;
        height: 60px;
        margin-bottom: 10px;
    }

    .stats-icon-circle i {
        font-size: 24px;
    }

    .stats-number {
        font-size: 2rem;
    }

    .stats-title {
        font-size: 0.85rem;
    }

    .stats-subtitle {
        font-size: 0.8rem;
    }
}

/* Filtro */
.filter-section {
    border: 1px solid #3c4147;
    padding: 10px;
    border-radius: 5px;
    margin-bottom: 20px;
}

.filter-section select {
    background-color: #323743;
    color: #a1aab2;
    border: 1px solid #666;
    border-radius: 4px;
    padding: 8px;
    width: 100%;
}

select[multiple].form-select {
    min-height: 250px;
}

/* Tabelas */
.table-wrapper {
    background: #25292c;
    border-radius: 12px;
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.4);
    overflow: hidden;
    margin-top: 20px;
    padding: 10px;
}

table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0 5px;
    background: #1d2126;
    animation: fadeIn 0.5s ease-in;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

th {
    background: linear-gradient(135deg, #1d2126, #1d2126);
    color: #fff;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
    padding: 15px;
    border-bottom: 2px solid #007bff;
    text-align: center;
    position: sticky;
    top: 0;
    z-index: 10;
}

td {
    padding: 12px;
    text-align: center;
    vertical-align: middle;
    background: #2b2f32;
    border-radius: 8px;
    transition: transform 0.2s ease, background 0.3s ease;
}

tr:hover td {
    background: #353943;
    transform: translateY(-2px);
}

/* Botões */
.btn-table {
    padding: 8px 14px;
    border-radius: 50%;
    font-size: 1rem;
    transition: all 0.3s ease;
    border: 2px solid transparent;
    background: #323743;
    color: #fff;
}

.btn-table:hover {
    transform: scale(1.1);
}

.btn-outline-primary {
    border-color: #007bff;
    color: #007bff;
}

.btn-outline-primary:hover {
    background: #007bff;
    color: #fff;
    box-shadow: 0 4px 15px rgba(0, 123, 255, 0.4);
}

.btn-outline-danger {
    border-color: #dc3545;
    color: #dc3545;
}

.btn-outline-danger:hover {
    background: #dc3545;
    color: #fff;
    box-shadow: 0 4px 15px rgba(220, 53, 69, 0.4);
}

.btn-outline-info {
    border-color: #17a2b8;
    color: #17a2b8;
}

.btn-outline-info:hover {
    background: #17a2b8;
    color: #fff;
    box-shadow: 0 4px 15px rgba(23, 162, 184, 0.4);
}

.btn-liberar-okr {
    cursor: pointer;
    transition: transform 0.2s ease, background 0.3s ease;
}

.btn-liberar-okr:hover {
    transform: scale(1.05);
    background: #0056b3;
}

.rounded-pill {
    border-radius: 50px !important;
}

/* Modais (Base) */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0, 0, 0, 0.7);
    z-index: 9999;
}

.modal.show {
    display: block;
}

.modal-dialog {
    margin: 2rem auto;
    max-width: 90%;
}

.modal-content {
    background-color: #2e2e2e;
    color: #eaeaea;
    border: none;
    border-radius: 8px;
}

.modal-header {
    padding: 1rem;
    border-bottom: 1px solid #555;
}

.modal-body {
    padding: 8px;
}

.modal-footer {
    border-top: 1px solid #555;
    padding: 15px;
}

.btn-close {
    cursor: pointer;
    filter: brightness(1.5);
}

/* Modal Moderno - Detalhes do Projeto */
.modern-modal {
    background: #25292c;
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
    overflow: hidden;
    animation: slideIn 0.3s ease-out;
}

@keyframes slideIn {
    from { transform: translateY(-50px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

.modern-modal .modal-header {
    background: linear-gradient(135deg, #2e3240, #25292c);
    border-bottom: 1px solid #3c4147;
    padding: 15px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modern-modal .modal-title {
    font-size: 1.4rem;
    font-weight: 600;
    color: #fff;
}

.sidebar-nav {
    padding: 20px 0;
    border-right: 1px solid #3c4147;
    text-align: left;
}

.sidebar-nav .nav-link {
    color: #a1aab2;
    padding: 12px 20px;
    border-radius: 0;
    transition: all 0.3s ease;
    font-size: 1rem;
    text-align: left;
    display: flex;
    align-items: center;
}

.sidebar-nav .nav-link:hover {
    background: #353943;
    color: #fff;
}

.sidebar-nav .nav-link.active {
    background: #007bff;
    color: #fff;
    box-shadow: inset 0 0 10px rgba(0, 0, 0, 0.2);
}

/* Conteúdo das Abas no Modal */
.tab-content {
    background: #1d2126;
    min-height: 400px;
    padding: 20px;
}

/* Formulários */
.form-label {
    font-weight: 600;
    color: #b0b8c1;
}

.form-control, .form-select {
    background: #323743;
    border: 1px solid #666;
    color: #a1aab2;
    border-radius: 4px;
    padding: 8px;
}

.form-control:focus, .form-select:focus {
    border-color: #007bff;
    box-shadow: 0 0 5px rgba(0, 123, 255, 0.5);
}

.form-text {
    font-size: 0.875rem;
    color: #7a8290;
}

fieldset {
    border: 1px solid #555;
    border-radius: 5px;
    padding: 15px;
    margin-bottom: 20px;
}

fieldset legend {
    width: auto;
    padding: 0 10px;
    font-size: 1.1rem;
    color: #9fb497;
}

/* Sessões e Títulos */
.section-title {
    color: #9fb497;
    font-size: 1.2rem;
    font-weight: bold;
    margin-top: 20px;
    margin-bottom: 10px;
}

.header-buttons {
    display: flex;
    gap: 10px;
    margin-bottom: 15px;
}

/* Gráfico Circular (BVP) */
.bvp-circle, .bvp-total-circle {
    position: relative;
    width: 60px;
    height: 60px;
    margin: 0 auto;
}

.bvp-total-circle {
    width: 100px;
    height: 100px;
}

.bvp-circle svg, .bvp-total-circle svg {
    width: 100%;
    height: 100%;
    transform: rotate(-90deg);
}

.bvp-circle .circle-bg, .bvp-total-circle .circle-bg {
    fill: none;
    stroke: #3c3c3c;
    stroke-width: 4;
}

.bvp-circle .circle-progress, .bvp-total-circle .circle-progress {
    fill: none;
    stroke-width: 4;
    stroke-linecap: round;
    transition: stroke-dasharray 0.5s ease;
}

.bvp-circle.low .circle-progress, .bvp-total-circle.low .circle-progress {
    stroke: #ff4d4d;
}

.bvp-circle.medium .circle-progress, .bvp-total-circle.medium .circle-progress {
    stroke: #ffcc00;
}

.bvp-circle.high .circle-progress, .bvp-total-circle.high .circle-progress {
    stroke: #00b7ff;
}

.bvp-circle span, .bvp-total-circle span {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 14px;
    color: #eaeaea;
    font-weight: bold;
}

.bvp-total-circle span {
    font-size: 20px;
}

/* Tarefas (Subtarefas) */
.nested-list {
    list-style: none;
    padding-left: 0;
}

.nested-list.subtask-item:empty {
    min-height: 10px;
    padding: 0;
    margin: 0;
}

.subtask-item {
    margin-left: 20px;
    border-left: 2px solid #555;
    padding-left: 10px;
}

.drag-handle {
    cursor: move;
    margin-right: 8px;
    color: #ccc;
}

.folder-icon-highlight {
    color: orange !important;
}

.ui-state-highlight {
    background: #444;
    border: 1px dashed #999;
    height: 2.5em;
    line-height: 1.2em;
}

.tarefa-concluida {
    text-decoration: line-through;
    opacity: 0.7;
}

.task-item {
    background: #2e3240;
    border: 1px solid #52555d;
    border-radius: 6px;
    padding: 15px;
    margin-bottom: 15px;
    transition: background 0.3s ease;
}

.task-item:hover {
    background: #353943;
}

.task-description {
    font-size: 0.95rem;
    color: #eaeaea;
    margin-top: 5px;
    white-space: pre-wrap;
}

.task-container {
    max-height: 700px;
    overflow-y: auto;
    padding-right: 10px;
}

.task-form {
    background: #2e3240;
    padding: 15px;
    border-radius: 8px;
}

/* Comentários/Atividades */
.activity-list {
    max-height: 350px;
    overflow-y: auto;
    margin-bottom: 20px;
}

.activity-item {
    background: #2e3240;
    border: 1px solid #52555d;
    border-radius: 6px;
    padding: 15px;
    margin-bottom: 15px;
    transition: background 0.3s ease;
}

.activity-item:hover {
    background: #353943;
}

.activity-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 8px;
}

.activity-author {
    font-size: 1rem;
    font-weight: 500;
    color: #00b7ff;
}

.activity-meta {
    font-size: 0.85rem;
    color: #a1aab2;
}

.activity-content {
    font-size: 0.95rem;
    color: #eaeaea;
    margin-top: 5px;
    white-space: pre-wrap;
}

.activity-actions {
    display: flex;
    gap: 8px;
}

.activity-form {
    background: #2e3240;
    padding: 15px;
    border-radius: 8px;
}

/* Detalhes do Projeto (Modal) */
.project-details {
    background: transparent;
    padding: 0;
}

.project-details .section-title {
    font-size: 1.5rem;
    color: #00b7ff;
    border-bottom: 2px solid #007bff;
    padding-bottom: 8px;
    margin-bottom: 20px;
}

.project-details h4.section-title {
    font-size: 1.2rem;
    color: #9fb497;
}

.project-details .detail-item {
    background: #2e3240;
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 10px;
    transition: transform 0.2s ease;
    display: flex;
    align-items: center;
}

.project-details .detail-item:hover {
    transform: translateX(5px);
    background: #353943;
}

.project-details .detail-item i {
    font-size: 1.2rem;
    color: #00b7ff;
    width: 24px;
}

.project-details .detail-item strong {
    color: #fff;
    margin-left: 8px;
    font-weight: 600;
}

/* Pontuação BVP (Detalhes do Projeto) */
.bvp-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 10px;
    margin-top: 15px;
}

.bvp-item {
    background: #323743;
    padding: 15px;
    border-radius: 8px;
    transition: background 0.3s ease;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.bvp-item:hover {
    background: #3c4147;
}

.bvp-item .bvp-label {
    font-size: 0.95rem;
    color: #a1aab2;
    font-weight: 500;
    text-align: left;
    flex: 1;
}

.bvp-item .bvp-value {
    font-size: 1.2rem;
    color: #00b7ff;
    font-weight: 700;
    text-align: right;
    padding-left: 10px;
    background: rgba(0, 123, 255, 0.1);
    border-radius: 4px;
    padding: 4px 8px;
}

.bvp-total {
    background: linear-gradient(135deg, #323743, #2e3240);
    padding: 20px;
    border-radius: 12px;
    text-align: center;
    margin-top: 20px;
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 15px;
}

.bvp-total .bvp-total-circle {
    margin: 0;
}

/* Anexos */
.attachment-container {
    max-height: 400px;
    overflow-y: auto;
    padding-right: 10px;
}

.attachment-form {
    background: #2e3240;
    padding: 15px;
    border-radius: 8px;
}

/* Dependências */
.dependencies-container {
    max-height: 400px;
    overflow-y: auto;
    padding-right: 10px;
}

/* Badges */
.badge {
    font-size: 12px;
    font-weight: 500;
    padding: 6px 12px;
    border-radius: 20px;
}

.badge i {
    margin-right: 4px;
}

/* Responsividade */
@media (max-width: 768px) {
    .sidebar-nav {
        display: none;
    }
    .tab-content {
        width: 100%;
    }
    .modern-modal .modal-header {
        flex-direction: column;
        align-items: flex-start;
    }
    .modern-modal .modal-title {
        margin-bottom: 10px;
    }
    .bvp-grid {
        grid-template-columns: 1fr;
    }
    .stats-card {
        flex-direction: column;
        text-align: center;
        padding: 15px;
        gap: 10px;
    }
    .stats-icon-circle {
        margin-bottom: 10px;
    }
    .stats-content {
        padding: 0;
    }
    .table-wrapper {
        padding: 5px;
    }
    td {
        padding: 8px;
        font-size: 0.9rem;
    }
    .btn-table {
        padding: 6px 10px;
    }
}

.btn-light-info {
    background-color: #17a2b8;
    border-color: #17a2b8;
    color: #fff;
}

.btn-light-info:hover {
    background-color: #138496;
    border-color: #117a8b;
}

/* Estilos adicionais para os cards de estatísticas */
.stats-card .stats-number {
    text-align: center;
}

.stats-card .stats-subtitle {
    margin-top: 5px;
}

.stats-card .stats-subtitle .d-flex {
    font-size: 0.9rem;
    color: #d0d0d0;
    justify-content: center;
}

/* Estilos adicionais para o card VAGAS BVP */
.stats-card .badge.rounded-pill {
    padding: 4px 8px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
}

.stats-card .stats-subtitle .d-flex span {
    white-space: nowrap;
}

/* Deixando todos os títulos em maiúsculas para consistência */
.stats-card .stats-title {
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
}
.btn-danger.okr-btn-custom:hover, .btn-danger.okr-btn-custom:focus {
    background: #b22222 !important; /* vermelho escuro */
    color: #fff !important;
    box-shadow: 0 0 0 0.15rem #ffb3b3;
    transform: translateY(-2px) scale(1.04);
    font-size:12px;
}
</style>
</head>
<body>
<div class="preloader"></div>
<div id="main-wrapper">
    <header class="topbar">
        <?php include 'header.php'; // Top Bar ?>
    </header>

    <?php include 'sidebar.php'; // Sidebar ?>

    <div class="page-wrapper">
        <div class="row page-titles">
            <div class="col-md-5 col-12 align-self-center">
                <h3 class="text-themecolor mb-0"><i class="mdi mdi-briefcase"></i> Projetos</h3>
            </div>
        </div>
        <div class="container-fluid">

            <!-- Mensagem de Sessão -->
            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['message']; unset($_SESSION['message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

           <!-- ====================== CARDS DE ESTATÍSTICAS (ATUALIZADOS) ====================== -->
           <div class="row g-3 mb-4 align-items-stretch">

<!-- Card: Total de Projetos -->
<div class="col-12 col-sm-6 col-lg-3 d-flex">
    <div class="stats-card flex-fill">
        <div class="stats-icon-circle">
            <svg viewBox="0 0 36 36">
                <path class="circle-bg"
                      d="M18 2.0845
                         a 15.9155 15.9155 0 0 1 0 31.831
                         a 15.9155 15.9155 0 0 1 0 -31.831"/>
                <?php $pctTotal = 100; ?>
                <path class="circle-progress bg-gradient-primary"
                      stroke-dasharray="<?= $pctTotal ?>, 100"
                      d="M18 2.0845
                         a 15.9155 15.9155 0 0 1 0 31.831
                         a 15.9155 15.9155 0 0 1 0 -31.831"/>
            </svg>
            <i class="fa fa-tasks"></i>
        </div>
        <div class="stats-content">
            <div class="stats-title">PROJETOS</div>
            <div class="stats-number" data-count="<?= $totalProjetos ?>"><?= $totalProjetos ?></div>
            <div class="stats-subtitle">Total cadastrado</div>
        </div>
    </div>
</div>

<!-- Card: VAGAS BVP (Redesenhado conforme exemplo) -->
<div class="col-12 col-sm-6 col-lg-3 d-flex">
    <div class="stats-card flex-fill" style="background: linear-gradient(145deg, #1a1d22, #212527);">
        <div class="stats-icon-circle">
            <svg viewBox="0 0 36 36">
                <path class="circle-bg"
                      d="M18 2.0845
                         a 15.9155 15.9155 0 0 1 0 31.831
                         a 15.9155 15.9155 0 0 1 0 -31.831"/>
                <path class="circle-progress"
                      style="stroke: #00b7ff;"
                      stroke-dasharray="<?= $porcentagemUsada ?>, 100"
                      d="M18 2.0845
                         a 15.9155 15.9155 0 0 1 0 31.831
                         a 15.9155 15.9155 0 0 1 0 -31.831"/>
            </svg>
            <i class="fa fa-bullseye" style="color: #fff;"></i>
        </div>
        <div class="stats-content">
            <div class="d-flex justify-content-between align-items-center">
                <div class="stats-title text-uppercase fw-bold" style="letter-spacing: 0.5px;">VAGAS BVP</div>
                <span class="badge rounded-pill" style="background-color: #0973cf; padding: 5px 10px; font-size: 0.7rem;"><?= $setorNomeEstatisticas ?></span>
            </div>
            
            <div class="stats-number text-end" data-count="<?= $totalVagasBVP ?>" style="font-size: 2.6rem; font-weight: 700; margin-right: 10px;">
                <?= $totalVagasBVP ?>
            </div>
            
            <div class="stats-subtitle" style="margin-top: 8px;">
                <div class="d-flex justify-content-between">
                   
                    <span style="color: #34c759b3;">
                        <i class="fa fa-clipboard-check me-1"></i> Vagas abertas: <?= $vagasAbertas ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Card: Em OKR -->
<div class="col-12 col-sm-6 col-lg-3 d-flex">
    <div class="stats-card flex-fill">
        <div class="stats-icon-circle">
            <svg viewBox="0 0 36 36">
                <path class="circle-bg"
                      d="M18 2.0845
                         a 15.9155 15.9155 0 0 1 0 31.831
                         a 15.9155 15.9155 0 0 1 0 -31.831"/>
                <?php $pctOKR = $totalProjetos > 0 ? round(($totalEmOKR / $totalProjetos) * 100) : 0; ?>
                <path class="circle-progress bg-gradient-success"
                      stroke-dasharray="<?= $pctOKR ?>, 100"
                      d="M18 2.0845
                         a 15.9155 15.9155 0 0 1 0 31.831
                         a 15.9155 15.9155 0 0 1 0 -31.831"/>
            </svg>
            <i class="fa fa-check-circle"></i>
        </div>
        <div class="stats-content">
            <div class="stats-title">EM OKR</div>
            <div class="stats-number" data-count="<?= $totalEmOKR ?>"><?= $totalEmOKR ?></div>
            <div class="stats-subtitle">Em andamento</div>
        </div>
    </div>
</div>

<!-- Card: Bloqueados -->
<div class="col-12 col-sm-6 col-lg-3 d-flex">
    <div class="stats-card flex-fill">
        <div class="stats-icon-circle">
            <svg viewBox="0 0 36 36">
                <path class="circle-bg"
                      d="M18 2.0845
                         a 15.9155 15.9155 0 0 1 0 31.831
                         a 15.9155 15.9155 0 0 1 0 -31.831"/>
                <?php $pctBloq = $totalProjetos > 0 ? round(($totalBloqueados / $totalProjetos) * 100) : 0; ?>
                <path class="circle-progress bg-gradient-danger"
                      stroke-dasharray="<?= $pctBloq ?>, 100"
                      d="M18 2.0845
                         a 15.9155 15.9155 0 0 1 0 31.831
                         a 15.9155 15.9155 0 0 1 0 -31.831"/>
            </svg>
            <i class="fa fa-lock"></i>
        </div>
        <div class="stats-content">
            <div class="stats-title">BLOQUEADOS</div>
            <div class="stats-number" data-count="<?= $totalBloqueados ?>"><?= $totalBloqueados ?></div>
            <div class="stats-subtitle">Aguardando liberação</div>
        </div>
    </div>
</div>

</div>


<!-- Filtros responsivos -->
<div class="filter-section mb-4">
    <form method="GET" action="projetos" id="filtroForm" class="row g-3">
        <!-- Ano -->
        <div class="col-md-4 col-sm-6">
            <select name="ano" id="filtroAno" class="form-select">
                <option value="">Todos os Anos</option>
                <?php foreach ($anosDisponiveis as $linhaAno): 
                    $year     = $linhaAno['ano'];
                    $selected = ($anoFiltro == $year) ? 'selected' : '';
                ?>
                <option value="<?= $year ?>" <?= $selected ?>><?= $year ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Setor -->
        <div class="col-md-4 col-sm-6">
        <?php if (in_array($perfilAcesso, [1,7,9])): // Admin, Diretor e Sub Diretor ?>
                <select name="setor" id="filtroSetor" class="form-select">
                    <option value="">Todos Setores</option>
                    <?php foreach ($setores as $s):
                        $sel = ($setorFiltro == $s['id']) ? 'selected' : '';
                    ?>
                    <option value="<?= $s['id'] ?>" <?= $sel ?>>
                        <?= htmlspecialchars($s['nome_do_setor']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php else: // Gestor, Inova, Bi, Dtic etc. só o próprio setor ?>
                <select name="setor" id="filtroSetor" class="form-select" readonly>
                    <option value="<?= $userSetorId ?>" selected>
                        <?= htmlspecialchars($userSetorName) ?>
                    </option>
                </select>
            <?php endif; ?>
        </div>

        <!-- Prazo -->
        <div class="col-md-4 col-sm-12">
            <select name="prazo" id="filtroPrazo" class="form-select">
                <option value="">Todos Prazos</option>
                <option value="Curto"  <?= $prazoFiltro == 'Curto'  ? 'selected' : '' ?>>Curto (Até 3 meses)</option>
                <option value="Médio"  <?= $prazoFiltro == 'Médio'  ? 'selected' : '' ?>>Médio (3–6 meses)</option>
                <option value="Longo"  <?= $prazoFiltro == 'Longo'  ? 'selected' : '' ?>>Longo (Acima de 6 meses)</option>
            </select>
        </div>
    </form>
</div>


<!-- Botões de ações responsivos -->
<div class="header-buttons d-flex flex-wrap gap-2 mb-3">
    <button type="button" class="btn btn-primary" onclick="showNewProjectModal()">
        <i class="fa fa-plus me-1"></i> Novo Projeto
    </button>

    <?php if (in_array($perfilAcesso, [1, 2, 9])): // Admin, Gestor e Sub Diretor ?>
        <button class="btn btn-secondary" onclick="showModal('modalProjetosDisponiveis')">
            <i class="fa fa-clipboard-check me-1"></i> Liberar Vagas BVP
        </button>
    <?php endif; ?>
</div>

            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle" id="projetosTable">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Projeto</th>
                            <th>Setor</th>
                            <th>Responsável</th>
                            <th>BVP</th>
                            <th>KeyResult</th>
                            <th>Tarefas</th>
                            <th>OKR / Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($projetos)): ?>
                        <?php foreach ($projetos as $proj): ?>
                            <?php
                            $bvpClass = '';
                            if ($proj['ValorOKR'] >= 20) {
                                $bvpClass = 'high';
                            } elseif ($proj['ValorOKR'] >= 10) {
                                $bvpClass = 'medium';
                            } else {
                                $bvpClass = 'low';
                            }
                            ?>
                            <tr>
                            <?php
                                $idProjeto = $proj['Id'];
                                $anoProjeto = date('Y', strtotime($proj['DataCriacao']));
                                $codigoProjeto = $idProjeto . '-' . $anoProjeto;
                                ?>
                                <td style="text-align: center; vertical-align: middle;">
                                    <span class="badge bg-info">
                                        <?php echo $codigoProjeto; ?>
                                    </span>
                                </td>
                                <td class="text-uppercase fw-semibold"><?php echo htmlspecialchars($proj['NomeProjeto']); ?></td>
                                <td>
                                    <?php
                                    $nomeSetor = 'Setor ' . $proj['SetorRelacionadoId'];
                                    foreach ($setores as $s) {
                                        if ($s['id'] == $proj['SetorRelacionadoId']) {
                                            $nomeSetor = htmlspecialchars($s['nome_do_setor']) ?? $nomeSetor;
                                            break;
                                        }
                                    }
                                    echo $nomeSetor;
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($proj['NomeResponsavel']); ?></td>
                                <td>
                                    <div class="bvp-circle <?php echo $bvpClass; ?>" data-bvp="<?php echo $proj['ValorOKR']; ?>">
                                        <svg viewBox="0 0 36 36">
                                            <path class="circle-bg"
                                                  d="M18 2.0845
                                                     a 15.9155 15.9155 0 0 1 0 31.831
                                                     a 15.9155 15.9155 0 0 1 0 -31.831"/>
                                            <path class="circle-progress"
                                                  stroke-dasharray="<?php echo ($proj['ValorOKR'] / 30) * 100; ?>, 100"
                                                  d="M18 2.0845
                                                     a 15.9155 15.9155 0 0 1 0 31.831
                                                     a 15.9155 15.9155 0 0 1 0 -31.831"/>
                                        </svg>
                                        <span><?php echo $proj['ValorOKR']; ?></span>
                                    </div>
                                </td>
                                <td style="text-align:center;">
                                    <span class="badge bg-warning text-dark" title="Total de Key Results">
                                        <i class="fa fa-key me-1"></i>
                                        <?php echo (int)($proj['total_keyresult'] ?? 0); ?>
                                    </span>
                                </td>
                                <td style="text-align:center;">
                                    <span class="badge bg-info text-dark" title="Total de Tarefas">
                                        <i class="fa fa-tasks me-1"></i>
                                        <?php echo (int)($proj['total_tarefas'] ?? 0); ?>
                                    </span>
                                </td>
                                <td>
                                <?php
                                if ($proj['Status'] === 'backlog') {
                                    echo '<span class="badge bg-secondary" title="Backlog"><i class="fa fa-list"></i> Backlog</span>';
                                } elseif ($proj['Status'] === 'bloqueado') {
                                    echo '<span class="badge bg-danger" title="Bloqueado"><i class="fa fa-lock"></i> Bloqueado</span>';
                                } elseif ($proj['Status'] === 'concluido') {
                                    echo '<span class="badge bg-success" title="Concluído"><i class="fa fa-check"></i> Concluído</span>';
                                } elseif ($proj['Status'] === 'andamento') {
                                    if ($proj['EnviadoFunil'] == 1) {
                                        echo '<span class="badge bg-info" title="Em OKR"><i class="fa fa-bullseye"></i> Em OKR</span>';
                                    } elseif ($proj['DisponivelOKR'] == 1 && $proj['EnviadoFunil'] == 0) {
                                        echo '<span class="badge bg-primary" title="Disponível p/ OKR"><i class="fa fa-hourglass-half"></i> Disp. OKR</span>';
                                    } else {
                                        echo '<span class="badge bg-warning text-dark" title="Aguardando"><i class="fa fa-clock"></i> Aguardando</span>';
                                    }
                                } else {
                                    echo '<span class="badge bg-light text-dark" title="Sem status"><i class="fa fa-question-circle"></i> Sem status</span>';
                                }
                                ?>
                            </td>
                                <td>
                                <div class="d-flex align-items-center justify-content-center gap-1 flex-nowrap">
    <?php if ($proj['EnviadoFunil'] == 1): ?>
        <!-- Botão OKR estilizado -->
        <a href="okr?id=<?php echo $proj['Id']; ?>"
                    class="btn btn-danger d-flex align-items-center justify-content-center px-3 py-2 shadow rounded-pill gap-2"
                    style="font-weight:600; letter-spacing:0.5px; font-size:12px; transition: all .2s;"
                    title="Acessar OKR">
                        <i class="fa fa-bullseye" style="font-size:1.4em;"></i>
                        <span>OKR</span>
                    </a>
    <?php else: ?>
        <!-- Botão Detalhes sempre visível -->
        <button type="button"
                class="btn btn-sm btn-light-info text-white"
                data-projeto-id="<?php echo $proj['Id']; ?>"
                data-disponivel-okr="<?php echo $proj['DisponivelOKR']; ?>"
                data-enviado-funil="<?php echo $proj['EnviadoFunil']; ?>"
                data-status="<?php echo $proj['Status']; ?>"
                title="Detalhes"
                onclick="openDetalhesModal(<?php echo $proj['Id']; ?>, <?php echo $proj['DisponivelOKR']; ?>, <?php echo $proj['EnviadoFunil']; ?>, '<?php echo $proj['Status']; ?>')">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                stroke-linejoin="round" class="feather feather-eye feather-sm fill-white">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                <circle cx="12" cy="12" r="3"></circle>
            </svg>
        </button>

        <?php if ($proj['Status'] === 'concluido'): ?>
            <!-- Botão PDF (só aparece quando concluído) -->
            <a href="relatorio_pdf?id=<?php echo $proj['Id']; ?>"
               class="btn btn-sm btn-light-info text-white"
               title="Gerar Relatório PDF" target="_blank" style="font-weight:600;">
                <i class="fa fa-file-pdf" style="font-size:17px;"></i>
            </a>
        <?php endif; ?>

        <?php if ($proj['Status'] !== 'concluido'): ?>

            <!-- Botão Editar com verificação de permissão -->
            <?php 
            $podeEditar = false;
            if (in_array($perfilAcesso, [1, 2, 9])) {
                $podeEditar = true;
            } 
            else if ($proj['ResponsavelId'] == $usuarioLogadoId) {
                $podeEditar = true;
            }
            if ($podeEditar): 
            ?>
            <button type="button"
                    class="btn btn-sm btn-light-info text-white"
                    data-projeto-id="<?php echo $proj['Id']; ?>"
                    title="Editar"
                    onclick="showEditModal(<?php echo $proj['Id']; ?>)">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18"
                    viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                    class="feather feather-edit feather-sm fill-white">
                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14
                            a2 2 0 0 0 2-2v-7"></path>
                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1
                            1-4 9.5-9.5z"></path>
                </svg>
            </button>
            <?php else: ?>
            <button type="button"
                    class="btn btn-sm btn-light-info text-white opacity-50"
                    title="Sem permissão para editar"
                    disabled>
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18"
                    viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                    class="feather feather-edit feather-sm fill-white">
                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14
                            a2 2 0 0 0 2-2v-7"></path>
                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1
                            1-4 9.5-9.5z"></path>
                </svg>
            </button>
            <?php endif; ?>

            <!-- Botão Excluir com verificação de permissão -->
            <?php 
            $podeExcluir = false;
            if (in_array($perfilAcesso, [1, 2, 9])) {
                $podeExcluir = true;
            } 
            else if ($proj['ResponsavelId'] == $usuarioLogadoId) {
                $podeExcluir = true;
            }
            if ($podeExcluir): 
            ?>
            <form method="POST" style="margin:0;" onsubmit="return confirm('Deseja excluir este projeto?');">
                <input type="hidden" name="acao" value="excluir">
                <input type="hidden" name="projeto_id" value="<?php echo $proj['Id']; ?>">
                <button type="submit"
                        class="btn btn-sm btn-light-danger text-white"
                        title="Excluir">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18"
                        viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        class="feather feather-trash-2 feather-sm fill-white">
                        <polyline points="3 6 5 6 21 6"></polyline>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6
                                m3 0V4a2 2 0 0 1 2-2h4
                                a2 2 0 0 1 2 2v2"></path>
                        <line x1="10" y1="11" x2="10" y2="17"></line>
                        <line x1="14" y1="11" x2="14" y2="17"></line>
                    </svg>
                </button>
            </form>
            <?php else: ?>
            <button type="button"
                    class="btn btn-sm btn-light-danger text-white opacity-50"
                    title="Sem permissão para excluir"
                    disabled>
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18"
                    viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                    class="feather feather-trash-2 feather-sm fill-white">
                    <polyline points="3 6 5 6 21 6"></polyline>
                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6
                            m3 0V4a2 2 0 0 1 2-2h4
                            a2 2 0 0 1 2 2v2"></path>
                    <line x1="10" y1="11" x2="10" y2="17"></line>
                    <line x1="14" y1="11" x2="14" y2="17"></line>
                </svg>
            </button>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>
                        </td>

                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7">Nenhum projeto encontrado.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="pagination-container mt-4">
    <div class="row g-2">
        <div class="col-md-6 col-sm-12 mb-2">
            <div class="items-per-page-selector d-flex align-items-center">
                <label for="itemsPerPage" class="me-2">Itens por página:</label>
                <select id="itemsPerPage" class="form-select form-select-sm" style="width: 80px;">
                    <option value="10" <?php echo $itemsPorPagina == 10 ? 'selected' : ''; ?>>10</option>
                    <option value="25" <?php echo $itemsPorPagina == 25 ? 'selected' : ''; ?>>25</option>
                    <option value="50" <?php echo $itemsPorPagina == 50 ? 'selected' : ''; ?>>50</option>
                    <option value="100" <?php echo $itemsPorPagina == 100 ? 'selected' : ''; ?>>100</option>
                </select>
            </div>
        </div>
        <div class="col-md-6 col-sm-12">
            <nav aria-label="Navegação de páginas">
                <ul class="pagination pagination-sm flex-wrap justify-content-md-end justify-content-center mb-0">
                    <?php if ($paginaAtual > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="javascript:void(0)" onclick="mudarPagina(1)" aria-label="Primeira">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="javascript:void(0)" onclick="mudarPagina(<?php echo $paginaAtual - 1; ?>)" aria-label="Anterior">
                                <span aria-hidden="true">&lt;</span>
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="page-item disabled">
                            <a class="page-link" href="#" aria-label="Primeira">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                        <li class="page-item disabled">
                            <a class="page-link" href="#" aria-label="Anterior">
                                <span aria-hidden="true">&lt;</span>
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php
                    // Determinando quais botões de página mostrar
                    $startPage = max(1, $paginaAtual - 1);
                    $endPage = min($totalPaginas, $paginaAtual + 1);

                    // Ajusta para mostrar sempre até 3 números em mobile
                    if ($endPage - $startPage < 2 && $endPage < $totalPaginas) {
                        $endPage = min($totalPaginas, $startPage + 2);
                    }
                    if ($endPage - $startPage < 2 && $startPage > 1) {
                        $startPage = max(1, $endPage - 2);
                    }

                    for ($i = $startPage; $i <= $endPage; $i++): ?>
                        <li class="page-item <?php echo $i == $paginaAtual ? 'active' : ''; ?>">
                            <a class="page-link" href="javascript:void(0)" onclick="mudarPagina(<?php echo $i; ?>)"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>

                    <?php if ($paginaAtual < $totalPaginas): ?>
                        <li class="page-item">
                            <a class="page-link" href="javascript:void(0)" onclick="mudarPagina(<?php echo $paginaAtual + 1; ?>)" aria-label="Próxima">
                                <span aria-hidden="true">&gt;</span>
                            </a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="javascript:void(0)" onclick="mudarPagina(<?php echo $totalPaginas; ?>)" aria-label="Última">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="page-item disabled">
                            <a class="page-link" href="#" aria-label="Próxima">
                                <span aria-hidden="true">&gt;</span>
                            </a>
                        </li>
                        <li class="page-item disabled">
                            <a class="page-link" href="#" aria-label="Última">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </div>
</div>

<script>
// variáveis disponíveis para o JS
const PERFIL_ACESSO = <?= (int)$perfilAcesso ?>;
const USER_SETOR_ID = <?= json_encode($userSetorId) ?>;    // null se não for gestor/inova/BI/DTIC
const USER_ID       = <?= (int)$usuarioLogadoId ?>;

// Adicione esta variável global para armazenar o status do projeto atual
let PROJETO_STATUS_ATUAL = null;
</script>

<!-- ================== MODAL NOVO PROJETO ================== -->
<div class="modal fade" id="modalNovoProjeto" tabindex="-1" aria-labelledby="modalNovoProjetoLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modern-modal">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalNovoProjetoLabel">
          <i class="fa fa-plus me-2"></i> Novo Projeto
        </h5>
        <button type="button" class="btn-close btn-close-white" onclick="hideModal('modalNovoProjeto')"></button>
      </div>

      <!-- Formulário com enctype para upload de arquivos -->
      <form id="formNovoProjeto"
            onsubmit="return false;"
            class="tab-content"
            enctype="multipart/form-data"
            novalidate>
        <div class="modal-body p-0 d-flex">

          <!-- LADO ESQUERDO: NAVEGAÇÃO POR ABAS -->
          <div class="sidebar-nav col-3 border-end">
            <div class="nav flex-column nav-pills me-3" role="tablist" aria-orientation="vertical">
              <button class="nav-link active"
                      id="btn-tab-dados"
                      data-bs-toggle="pill"
                      data-bs-target="#novo-dados"
                      type="button"
                      role="tab"
                      aria-controls="novo-dados"
                      aria-selected="true">
                <i class="fa fa-info-circle me-2"></i> Dados Básicos
              </button>

              <button class="nav-link"
                      id="btn-tab-complementares"
                      data-bs-toggle="pill"
                      data-bs-target="#novo-complementares"
                      type="button"
                      role="tab"
                      aria-controls="novo-complementares"
                      aria-selected="false">
                <i class="fa fa-users me-2"></i> Complementares
              </button>

              <!-- Nova aba: Orçamento Macro -->
              <button class="nav-link"
                      id="btn-tab-orcamento"
                      data-bs-toggle="pill"
                      data-bs-target="#novo-orcamento"
                      type="button"
                      role="tab"
                      aria-controls="novo-orcamento"
                      aria-selected="false">
                <i class="fa fa-calculator me-2"></i> Orçamento Macro
              </button>

              <button class="nav-link"
                      id="btn-tab-avaliacoes"
                      data-bs-toggle="pill"
                      data-bs-target="#novo-avaliacoes"
                      type="button"
                      role="tab"
                      aria-controls="novo-avaliacoes"
                      aria-selected="false">
                <i class="fa fa-star me-2"></i> Avaliações Técnicas
              </button>
            </div>
          </div>

          <!-- LADO DIREITO: CONTEÚDO DE CADA ABA -->
          <div class="col-9 p-4">
            <div class="tab-content" id="v-pills-tabContent">

              <!-- ================= ABA 1: DADOS BÁSICOS ================= -->
              <div class="tab-pane fade show active" id="novo-dados" role="tabpanel" aria-labelledby="btn-tab-dados">
                <div class="row g-3">
                  <!-- Setor Relacionado -->
                  <div class="col-md-6">
                    <label for="novoSetorRelacionadoId" class="form-label">Setor Relacionado</label>
                    <select name="SetorRelacionadoId"
                            id="novoSetorRelacionadoId"
                            class="form-select"
                            required>
                      <!-- opções preenchidas dinamicamente -->
                    </select>
                  </div>

                  <!-- Responsável -->
                  <div class="col-md-6">
                    <label for="novoResponsavelId" class="form-label">Responsável</label>
                    <select name="ResponsavelId"
                            id="novoResponsavelId"
                            class="form-select"
                            required>
                      <!-- opções preenchidas dinamicamente -->
                    </select>
                  </div>

                  <!-- Nome do Projeto -->
                  <div class="col-12">
                    <label for="novoNomeProjeto" class="form-label">Nome do Projeto</label>
                    <input type="text"
                           name="NomeProjeto"
                           id="novoNomeProjeto"
                           class="form-control"
                           required
                           placeholder="Digite o nome do projeto">
                  </div>

                  <!-- Data de Criação -->
                  <div class="col-md-4">
                    <label for="novoDataCriacao" class="form-label">Data de Criação</label>
                    <input type="date"
                           name="DataCriacao"
                           id="novoDataCriacao"
                           class="form-control"
                           required>
                  </div>

                  <!-- Prioridade -->
                  <div class="col-md-4">
                    <label for="novoPrioridade" class="form-label">Prioridade</label>
                    <select name="Prioridade"
                            id="novoPrioridade"
                            class="form-select"
                            required>
                      <option value="Baixa">Baixa</option>
                      <option value="Média" selected>Média</option>
                      <option value="Alta">Alta</option>
                      <option value="Urgente">Urgente</option>
                    </select>
                  </div>

                  <!-- Prazo -->
                  <div class="col-md-4">
                    <label for="novoPrazo" class="form-label">Prazo</label>
                    <select name="Prazo"
                            id="novoPrazo"
                            class="form-select"
                            required>
                      <option value="Curto">Curto (Até 3 meses)</option>
                      <option value="Médio" selected>Médio (Entre 3 a 6 meses)</option>
                      <option value="Longo">Longo (Acima de 6 meses)</option>
                    </select>
                  </div>

                  <!-- Tipo do Projeto -->
                  <div class="col-md-6">
                    <label for="novoTipoProjeto" class="form-label">Tipo do Projeto</label>
                    <select name="tipo_projeto"
                            id="novoTipoProjeto"
                            class="form-select"
                            required>
                      <option value="" disabled selected>Selecione</option>
                      <option value="Estudo ou plano">Estudo ou plano</option>
                      <option value="Desenvolvimento de sistemas">Desenvolvimento de sistemas</option>
                      <option value="Capacitação">Capacitação</option>
                      <option value="Gestão de processo">Gestão de processo</option>
                      <option value="Serviços e contratações">Serviços e contratações</option>
                      <option value="Manutenção">Manutenção</option>
                      <option value="Outros">Outros</option>
                    </select>
                  </div>

                  <!-- Sigilo do Projeto -->
                  <div class="col-md-6">
                    <label for="novoSigiloProjeto" class="form-label">Sigilo do Projeto</label>
                    <select name="sigilo_projeto"
                            id="novoSigiloProjeto"
                            class="form-select"
                            required
                            onchange="document.getElementById('divNovoMotivoSigilo').style.display = this.value === 'Sigiloso' ? 'block' : 'none'">
                      <option value="Público" selected>Público</option>
                      <option value="Sigiloso">Sigiloso</option>
                    </select>
                  </div>

                  <!-- Motivo do Sigilo (visível apenas se Sigiloso) -->
                  <div class="col-12" id="divNovoMotivoSigilo" style="display: none;">
                    <label for="novoMotivoSigilo" class="form-label">Motivo do Sigilo</label>
                    <textarea name="motivo_sigilo"
                              id="novoMotivoSigilo"
                              class="form-control"
                              rows="2"
                              placeholder="Explique brevemente o motivo do sigilo"></textarea>
                  </div>

                  <!-- Qualificação (checkboxes) -->
                  <div class="col-12">
                    <label class="form-label">Qualificação</label>
                    <div class="row">
                      <div class="col-md-6">
                        <div class="form-check">
                          <input class="form-check-input"
                                 type="checkbox"
                                 name="qualificacao[]"
                                 value="Exigência Legal interna ou externa"
                                 id="novoQualificacao1">
                          <label class="form-check-label" for="novoQualificacao1">
                            Exigência Legal interna ou externa
                          </label>
                        </div>
                        <div class="form-check">
                          <input class="form-check-input"
                                 type="checkbox"
                                 name="qualificacao[]"
                                 value="Padronização de regras de negócios"
                                 id="novoQualificacao2">
                          <label class="form-check-label" for="novoQualificacao2">
                            Padronização de regras de negócios
                          </label>
                        </div>
                        <div class="form-check">
                          <input class="form-check-input"
                                 type="checkbox"
                                 name="qualificacao[]"
                                 value="Ocasionando prejuízos a PMSC"
                                 id="novoQualificacao3">
                          <label class="form-check-label" for="novoQualificacao3">
                            Ocasionando prejuízos a PMSC
                          </label>
                        </div>
                        <div class="form-check">
                          <input class="form-check-input"
                                 type="checkbox"
                                 name="qualificacao[]"
                                 value="Impacto para o usuário externo"
                                 id="novoQualificacao4">
                          <label class="form-check-label" for="novoQualificacao4">
                            Impacto para o usuário externo
                          </label>
                        </div>
                      </div>
                      <div class="col-md-6">
                        <div class="form-check">
                          <input class="form-check-input"
                                 type="checkbox"
                                 name="qualificacao[]"
                                 value="Melhoria de processos/procedimentos"
                                 id="novoQualificacao5">
                          <label class="form-check-label" for="novoQualificacao5">
                            Melhoria de processos/procedimentos
                          </label>
                        </div>
                        <div class="form-check">
                          <input class="form-check-input"
                                 type="checkbox"
                                 name="qualificacao[]"
                                 value="Automação de atividade/tarefa"
                                 id="novoQualificacao6">
                          <label class="form-check-label" for="novoQualificacao6">
                            Automação de atividade/tarefa
                          </label>
                        </div>
                        <div class="form-check">
                          <input class="form-check-input"
                                 type="checkbox"
                                 name="qualificacao[]"
                                 value="Mitigação de riscos"
                                 id="novoQualificacao7">
                          <label class="form-check-label" for="novoQualificacao7">
                            Mitigação de riscos
                          </label>
                        </div>
                        <div class="form-check">
                          <input class="form-check-input"
                                 type="checkbox"
                                 name="qualificacao[]"
                                 value="Estratégia de mercado/atuação de riscos"
                                 id="novoQualificacao8">
                          <label class="form-check-label" for="novoQualificacao8">
                            Estratégia de mercado/atuação de riscos
                          </label>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Descrição do Projeto -->
                  <div class="col-12">
                    <label for="novoDescricaoProjeto" class="form-label">Descrição do Projeto</label>
                    <textarea name="descricao_projeto"
                              id="novoDescricaoProjeto"
                              class="form-control"
                              rows="4"
                              required
                              placeholder="Descreva o objeto do projeto proposto, qual a ideia"></textarea>
                  </div>
                </div>

                <!-- Botões de navegação: Próximo para Complementares -->
                <div class="mt-4 d-flex justify-content-end">
                  <button type="button"
                          class="btn btn-secondary me-2"
                          onclick="hideModal('modalNovoProjeto')">
                    <i class="fa fa-times me-2"></i> Cancelar
                  </button>
                  <button type="button"
                          class="btn btn-primary"
                          onclick="showTab('novo-complementares')">
                    Próximo <i class="fa fa-arrow-right ms-2"></i>
                  </button>
                </div>
              </div>
              <!-- =============== FIM ABA 1 ============== -->


              <!-- ================= ABA 2: COMPLEMENTARES ================= -->
              <div class="tab-pane fade" id="novo-complementares" role="tabpanel" aria-labelledby="btn-tab-complementares">
                <div class="row g-3">
                  <!-- Setores Envolvidos -->
                  <div class="col-12">
                    <label for="novoOutroSetorEnvolvido" class="form-label">Setores Envolvidos</label>
                    <select name="OutroSetorEnvolvido[]"
                            id="novoOutroSetorEnvolvido"
                            class="form-select"
                            multiple
                            size="5">
                      <!-- opções preenchidas dinamicamente -->
                    </select>
                    <div class="form-text">Use CTRL/CMD para selecionar múltiplos.</div>
                  </div>

                  <!-- Usuários Envolvidos -->
                  <div class="col-12">
                    <label for="novoUsuariosEnvolvidos" class="form-label">Usuários Envolvidos</label>
                    <select name="UsuariosEnvolvidos[]"
                            id="novoUsuariosEnvolvidos"
                            class="form-select"
                            multiple
                            size="5">
                      <!-- opções preenchidas dinamicamente -->
                    </select>
                    <div class="form-text">Use CTRL/CMD para selecionar múltiplos.</div>
                  </div>

                  <!-- Outros Projetos Vinculados -->
                  <div class="col-12">
                    <label for="novoDependenciasProjetos" class="form-label">Outros Projetos Vinculados</label>
                    <select name="DependenciasProjetos[]"
                            id="novoDependenciasProjetos"
                            class="form-select"
                            multiple
                            size="5">
                      <!-- opções preenchidas dinamicamente -->
                    </select>
                    <div class="form-text">Use CTRL/CMD para selecionar múltiplos.</div>
                  </div>
                </div>

                <!-- Botões de navegação: Voltar e Próximo -->
                <div class="mt-4 d-flex justify-content-between">
                  <button type="button"
                          class="btn btn-secondary"
                          onclick="showTab('novo-dados')">
                    <i class="fa fa-arrow-left me-2"></i> Voltar
                  </button>
                  <div>
                    <button type="button"
                            class="btn btn-secondary me-2"
                            onclick="hideModal('modalNovoProjeto')">
                      <i class="fa fa-times me-2"></i> Cancelar
                    </button>
                    <button type="button"
                            class="btn btn-primary"
                            onclick="showTab('novo-orcamento')">
                      Próximo <i class="fa fa-arrow-right ms-2"></i>
                    </button>
                  </div>
                </div>
              </div>
              <!-- =============== FIM ABA 2 ============== -->


              <!-- ================ ABA 3: ORÇAMENTO MACRO ================ -->
              <div class="tab-pane fade" id="novo-orcamento" role="tabpanel" aria-labelledby="btn-tab-orcamento">
                <div class="row g-3">

                  <!-- Valor Estimado -->
                  <div class="col-md-6">
                    <label for="valor_estimado" class="form-label">Valor Estimado (R$)</label>
                    <input type="text"
                            name="valor_estimado"
                            id="valor_estimado"
                            class="form-control money-mask"
                            placeholder="R$ 0,00">
                    </div>

                  <!-- Descrição do Orçamento Macro -->
                  <div class="col-12">
                    <label for="orcamento_macro_desc" class="form-label">Descrição do Orçamento Macro</label>
                    <textarea name="orcamento_macro"
                              id="orcamento_macro_desc"
                              class="form-control"
                              rows="4"
                              placeholder="Explique as premissas e estimativas gerais do orçamento macro"></textarea>
                  </div>

                  <!-- Upload de arquivos do Orçamento Macro -->
                  <div class="col-12">
                    <label for="orcamento_macro_files" class="form-label">Anexar arquivos do Orçamento Macro</label>
                     <input type="file"
                        name="orcamento_macro_files[]"
                        id="orcamento_macro_files"
                        class="form-control"
                        multiple
                        accept=".pdf,.xlsx,.xls,.docx,.pptx,.zip,.rar">
                    <div class="form-text">
                      Você pode anexar planilhas, relatórios ou documentos que subsidiem o orçamento macro.
                    </div>
                  </div>
                </div>

                <!-- Botões de navegação: Voltar e Próximo -->
                <div class="mt-4 d-flex justify-content-between">
                  <button type="button"
                          class="btn btn-secondary"
                          onclick="showTab('novo-complementares')">
                    <i class="fa fa-arrow-left me-2"></i> Voltar
                  </button>
                  <div>
                    <button type="button"
                            class="btn btn-secondary me-2"
                            onclick="hideModal('modalNovoProjeto')">
                      <i class="fa fa-times me-2"></i> Cancelar
                    </button>
                    <button type="button"
                            class="btn btn-primary"
                            onclick="showTab('novo-avaliacoes')">
                      Próximo <i class="fa fa-arrow-right ms-2"></i>
                    </button>
                  </div>
                </div>
              </div>
              <!-- =============== FIM ABA 3 ============== -->


              <!-- ================== ABA 4: AVALIAÇÕES ================== -->
              <div class="tab-pane fade" id="novo-avaliacoes" role="tabpanel" aria-labelledby="btn-tab-avaliacoes">
                <div class="row g-3">
                  <!-- Alinhamento Estratégico -->
                  <div class="col-md-4">
                    <label for="novoAlinhamentoEstrategico" class="form-label">Alinhamento Estratégico</label>
                    <input type="number"
                           name="AlinhamentoEstrategico"
                           id="novoAlinhamentoEstrategico"
                           class="form-control"
                           min="0"
                           max="5"
                           required
                           oninput="limitRange(this)">
                  </div>

                  <!-- Vulnerabilidade Cibersegurança -->
                  <div class="col-md-4">
                    <label for="novoVulnerabilidadeCiberseguranca" class="form-label">Vulnerabilidade</label>
                    <input type="number"
                           name="VulnerabilidadeCiberseguranca"
                           id="novoVulnerabilidadeCiberseguranca"
                           class="form-control"
                           min="0"
                           max="5"
                           required
                           oninput="limitRange(this)">
                  </div>

                  <!-- LGPD - DPO Criptografado -->
                  <div class="col-md-4">
                    <label for="novoLPD_DPO_Criptografado" class="form-label">LGPD - DPO</label>
                    <input type="number"
                           name="LPD_DPO_Criptografado"
                           id="novoLPD_DPO_Criptografado"
                           class="form-control"
                           min="0"
                           max="5"
                           required
                           oninput="limitRange(this)">
                  </div>

                  <!-- Impacto Operacional -->
                  <div class="col-md-4">
                    <label for="novoImpactoOperacional" class="form-label">Impacto Operacional</label>
                    <input type="number"
                           name="ImpactoOperacional"
                           id="novoImpactoOperacional"
                           class="form-control"
                           min="0"
                           max="5"
                           required
                           oninput="limitRange(this)">
                  </div>

                  <!-- Impacto Administrativo -->
                  <div class="col-md-4">
                    <label for="novoImpactoAdministrativo" class="form-label">Impacto Administrativo</label>
                    <input type="number"
                           name="ImpactoAdministrativo"
                           id="novoImpactoAdministrativo"
                           class="form-control"
                           min="0"
                           max="5"
                           required
                           oninput="limitRange(this)">
                  </div>

                  <!-- Impacto Financeiro -->
                  <div class="col-md-4">
                    <label for="novoImpactoFinanceiro" class="form-label">Impacto Financeiro</label>
                    <input type="number"
                           name="ImpactoFinanceiro"
                           id="novoImpactoFinanceiro"
                           class="form-control"
                           min="0"
                           max="5"
                           required
                           oninput="limitRange(this)">
                  </div>
                </div>

                <!-- Botões de navegação: Voltar e Criar Projeto -->
                <div class="mt-4 d-flex justify-content-between">
                  <button type="button"
                          class="btn btn-secondary"
                          onclick="showTab('novo-orcamento')">
                    <i class="fa fa-arrow-left me-2"></i> Voltar
                  </button>
                  <div>
                    <button type="button"
                            class="btn btn-secondary me-2"
                            onclick="hideModal('modalNovoProjeto')">
                      <i class="fa fa-times me-2"></i> Cancelar
                    </button>
                    <button type="button"
                            class="btn btn-primary"
                            onclick="submitProjectForm('formNovoProjeto', 'criar')">
                      <i class="fa fa-save me-2"></i> Criar Projeto
                    </button>
                  </div>
                </div>
              </div>
              <!-- =============== FIM ABA 4 ============== -->

            </div>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ==================== SCRIPTS AUXILIARES ==================== -->
<script>
  // Exibe a aba com o ID passado (ex.: 'novo-dados', 'novo-complementares', 'novo-orcamento', 'novo-avaliacoes')
  function showTab(tabId) {
    // Desmarca todas as abas e tab-panes
    document.querySelectorAll('.nav-link').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.tab-pane').forEach(pane => pane.classList.remove('show', 'active'));

    // Ativa o botão de navegação correspondente
    const btn = document.querySelector(`[data-bs-target="#${tabId}"]`);
    if (btn) {
      btn.classList.add('active');
    }

    // Exibe o conteúdo da aba correspondente
    const pane = document.getElementById(tabId);
    if (pane) {
      pane.classList.add('show', 'active');
    }
  }

  // Oculta o modal (usa Bootstrap Modal via JavaScript ou função customizada)
  function hideModal(modalId) {
    const modalEl = document.getElementById(modalId);
    if (modalEl) {
      // Se estiver usando Bootstrap 5:
      const modalObj = bootstrap.Modal.getInstance(modalEl);
      if (modalObj) {
        modalObj.hide();
      }
    }
  }

  // Limita valores dos campos numéricos de 0 a 5
  function limitRange(input) {
    if (input.value !== '') {
      const val = parseInt(input.value, 10);
      if (val < parseInt(input.min, 10)) input.value = input.min;
      if (val > parseInt(input.max, 10)) input.value = input.max;
    }
  }

  // Função de envio do formulário (pode chamar AJAX ou submeter diretamente)
  function submitProjectForm(formId, action) {
    const form = document.getElementById(formId);
    if (!form.checkValidity()) {
      form.classList.add('was-validated');
      return;
    }
    // Exemplo de envio via AJAX ou redirecionamento:
    // form.action = 'projetos.php?acao=' + action;
    // form.method = 'POST';
    // form.submit();

    // Se estiver usando AJAX, implemente aqui o envio.
    console.log('Enviando formulário:', formId, 'ação:', action);
  }
</script>



<!-- ================== MODAL EDITAR PROJETO ================== -->
<div class="modal fade" id="modalEditarProjeto" tabindex="-1" aria-labelledby="modalEditarProjetoLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modern-modal">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalEditarProjetoLabel">
          <i class="fa fa-edit me-2"></i> Editar Projeto
        </h5>
        <button type="button" class="btn-close btn-close-white" onclick="hideModal('modalEditarProjeto')"></button>
      </div>

      <div class="modal-body p-0 d-flex">
        <!-- LADO ESQUERDO: NAVEGAÇÃO POR ABAS -->
        <div class="sidebar-nav col-3 border-end">
          <div class="nav flex-column nav-pills me-3" role="tablist" aria-orientation="vertical">
            <button class="nav-link active"
                    id="btn-tab-editar-dados"
                    data-bs-toggle="pill"
                    data-bs-target="#editar-dados"
                    type="button"
                    role="tab"
                    aria-controls="editar-dados"
                    aria-selected="true">
              <i class="fa fa-info-circle me-2"></i> Dados Básicos
            </button>
            <button class="nav-link"
                    id="btn-tab-editar-complementares"
                    data-bs-toggle="pill"
                    data-bs-target="#editar-complementares"
                    type="button"
                    role="tab"
                    aria-controls="editar-complementares"
                    aria-selected="false">
              <i class="fa fa-users me-2"></i> Complementares
            </button>
            <!-- Nova aba: Orçamento Macro -->
            <button class="nav-link"
                    id="btn-tab-editar-orcamento"
                    data-bs-toggle="pill"
                    data-bs-target="#editar-orcamento"
                    type="button"
                    role="tab"
                    aria-controls="editar-orcamento"
                    aria-selected="false">
              <i class="fa fa-calculator me-2"></i> Orçamento Macro
            </button>
            <button class="nav-link"
                    id="btn-tab-editar-avaliacoes"
                    data-bs-toggle="pill"
                    data-bs-target="#editar-avaliacoes"
                    type="button"
                    role="tab"
                    aria-controls="editar-avaliacoes"
                    aria-selected="false">
              <i class="fa fa-star me-2"></i> Avaliações Técnicas
            </button>
          </div>
        </div>

        <!-- LADO DIREITO: CONTEÚDO DE CADA ABA -->
        <form id="formEditarProjeto"
              onsubmit="return false;"
              class="tab-content col-9 p-4"
              enctype="multipart/form-data"
              novalidate>
          <input type="hidden" id="editarProjetoId" name="projeto_id">

          <!-- ================= ABA: DADOS BÁSICOS ================= -->
          <div class="tab-pane fade show active" id="editar-dados" role="tabpanel" aria-labelledby="btn-tab-editar-dados">
            <div class="row g-3">
              <div class="col-md-6">
                <label for="editarSetorRelacionadoId" class="form-label">Setor Relacionado</label>
                <select name="SetorRelacionadoId"
                        id="editarSetorRelacionadoId"
                        class="form-select"
                        required>
                  <!-- opções preenchidas dinamicamente -->
                </select>
              </div>
              <div class="col-md-6">
                <label for="editarResponsavelId" class="form-label">Responsável</label>
                <select name="ResponsavelId"
                        id="editarResponsavelId"
                        class="form-select"
                        required>
                  <!-- opções preenchidas dinamicamente -->
                </select>
              </div>
              <div class="col-12">
                <label for="editarNomeProjeto" class="form-label">Nome do Projeto</label>
                <input type="text"
                       name="NomeProjeto"
                       id="editarNomeProjeto"
                       class="form-control"
                       required
                       placeholder="Digite o nome do projeto">
              </div>
              <div class="col-md-4">
                <label for="editarDataCriacao" class="form-label">Data de Criação</label>
                <input type="date"
                       name="DataCriacao"
                       id="editarDataCriacao"
                       class="form-control"
                       required>
              </div>
              <div class="col-md-4">
                <label for="editarPrioridade" class="form-label">Prioridade</label>
                <select name="Prioridade"
                        id="editarPrioridade"
                        class="form-select"
                        required>
                  <option value="Baixa">Baixa</option>
                  <option value="Média">Média</option>
                  <option value="Alta">Alta</option>
                  <option value="Urgente">Urgente</option>
                </select>
              </div>
              <div class="col-md-4">
                <label for="editarPrazo" class="form-label">Prazo</label>
                <select name="Prazo"
                        id="editarPrazo"
                        class="form-select"
                        required>
                  <option value="Curto">Curto (Até 3 meses)</option>
                  <option value="Médio">Médio (Entre 3 a 6 meses)</option>
                  <option value="Longo">Longo (Acima de 6 meses)</option>
                </select>
              </div>
              <!-- NOVOS CAMPOS PARA EDIÇÃO -->
              <div class="col-md-6">
                <label for="editarTipoProjeto" class="form-label">Tipo do Projeto</label>
                <select name="tipo_projeto"
                        id="editarTipoProjeto"
                        class="form-select"
                        required>
                  <option value="" disabled>Selecione</option>
                  <option value="Estudo ou plano">Estudo ou plano</option>
                  <option value="Desenvolvimento de sistemas">Desenvolvimento de sistemas</option>
                  <option value="Capacitação">Capacitação</option>
                  <option value="Gestão de processo">Gestão de processo</option>
                  <option value="Serviços e contratações">Serviços e contratações</option>
                  <option value="Manutenção">Manutenção</option>
                  <option value="Outros">Outros</option>
                </select>
              </div>
              <div class="col-md-6">
                <label for="editarSigiloProjeto" class="form-label">Sigilo do Projeto</label>
                <select name="sigilo_projeto"
                        id="editarSigiloProjeto"
                        class="form-select"
                        required
                        onchange="document.getElementById('divEditarMotivoSigilo').style.display = this.value === 'Sigiloso' ? 'block' : 'none'">
                  <option value="Público">Público</option>
                  <option value="Sigiloso">Sigiloso</option>
                </select>
              </div>
              <div class="col-12" id="divEditarMotivoSigilo" style="display: none;">
                <label for="editarMotivoSigilo" class="form-label">Motivo do Sigilo</label>
                <textarea name="motivo_sigilo"
                          id="editarMotivoSigilo"
                          class="form-control"
                          rows="2"
                          placeholder="Explique brevemente o motivo do sigilo"></textarea>
              </div>
              <div class="col-12">
                <label class="form-label">Qualificação</label>
                <div class="row">
                  <div class="col-md-6">
                    <div class="form-check">
                      <input class="form-check-input"
                             type="checkbox"
                             name="qualificacao[]"
                             value="Exigência Legal interna ou externa"
                             id="editarQualificacao1">
                      <label class="form-check-label" for="editarQualificacao1">
                        Exigência Legal interna ou externa
                      </label>
                    </div>
                    <div class="form-check">
                      <input class="form-check-input"
                             type="checkbox"
                             name="qualificacao[]"
                             value="Padronização de regras de negócios"
                             id="editarQualificacao2">
                      <label class="form-check-label" for="editarQualificacao2">
                        Padronização de regras de negócios
                      </label>
                    </div>
                    <div class="form-check">
                      <input class="form-check-input"
                             type="checkbox"
                             name="qualificacao[]"
                             value="Ocasionando prejuízos a PMSC"
                             id="editarQualificacao3">
                      <label class="form-check-label" for="editarQualificacao3">
                        Ocasionando prejuízos a PMSC
                      </label>
                    </div>
                    <div class="form-check">
                      <input class="form-check-input"
                             type="checkbox"
                             name="qualificacao[]"
                             value="Impacto para o usuário externo"
                             id="editarQualificacao4">
                      <label class="form-check-label" for="editarQualificacao4">
                        Impacto para o usuário externo
                      </label>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-check">
                      <input class="form-check-input"
                             type="checkbox"
                             name="qualificacao[]"
                             value="Melhoria de processos/procedimentos"
                             id="editarQualificacao5">
                      <label class="form-check-label" for="editarQualificacao5">
                        Melhoria de processos/procedimentos
                      </label>
                    </div>
                    <div class="form-check">
                      <input class="form-check-input"
                             type="checkbox"
                             name="qualificacao[]"
                             value="Automação de atividade/tarefa"
                             id="editarQualificacao6">
                      <label class="form-check-label" for="editarQualificacao6">
                        Automação de atividade/tarefa
                      </label>
                    </div>
                    <div class="form-check">
                      <input class="form-check-input"
                             type="checkbox"
                             name="qualificacao[]"
                             value="Mitigação de riscos"
                             id="editarQualificacao7">
                      <label class="form-check-label" for="editarQualificacao7">
                        Mitigação de riscos
                      </label>
                    </div>
                    <div class="form-check">
                      <input class="form-check-input"
                             type="checkbox"
                             name="qualificacao[]"
                             value="Estratégia de mercado/atuação de riscos"
                             id="editarQualificacao8">
                      <label class="form-check-label" for="editarQualificacao8">
                        Estratégia de mercado/atuação de riscos
                      </label>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-12">
                <label for="editarDescricaoProjeto" class="form-label">Descrição do Projeto</label>
                <textarea name="descricao_projeto"
                          id="editarDescricaoProjeto"
                          class="form-control"
                          rows="4"
                          required
                          placeholder="Descreva o objeto do projeto proposto, qual a ideia"></textarea>
              </div>
            </div>
          </div>
          <!-- =============== FIM ABA DADOS BÁSICOS ============== -->

          <!-- ================= ABA: COMPLEMENTARES ================= -->
          <div class="tab-pane fade" id="editar-complementares" role="tabpanel" aria-labelledby="btn-tab-editar-complementares">
            <div class="row g-3">
              <div class="col-12">
                <label for="editarOutroSetorEnvolvido" class="form-label">Setores Envolvidos</label>
                <select name="OutroSetorEnvolvido[]"
                        id="editarOutroSetorEnvolvido"
                        class="form-select"
                        multiple>
                  <!-- opções preenchidas dinamicamente -->
                </select>
                <div class="form-text">Use CTRL/CMD para selecionar múltiplos.</div>
              </div>
              <div class="col-12">
                <label for="editarUsuariosEnvolvidos" class="form-label">Usuários Envolvidos</label>
                <select name="UsuariosEnvolvidos[]"
                        id="editarUsuariosEnvolvidos"
                        class="form-select"
                        multiple>
                  <!-- opções preenchidas dinamicamente -->
                </select>
                <div class="form-text">Use CTRL/CMD para selecionar múltiplos.</div>
              </div>
              <div class="col-12">
                <label for="editarDependenciasProjetos" class="form-label">Outros Projetos Vinculados</label>
                <select name="DependenciasProjetos[]"
                        id="editarDependenciasProjetos"
                        class="form-select"
                        multiple>
                  <!-- opções preenchidas dinamicamente -->
                </select>
                <div class="form-text">Use CTRL/CMD para selecionar múltiplos.</div>
              </div>
            </div>
          </div>
          <!-- =============== FIM ABA COMPLEMENTARES ============== -->

          <!-- ================ ABA: ORÇAMENTO MACRO ================ -->
          <div class="tab-pane fade" id="editar-orcamento" role="tabpanel" aria-labelledby="btn-tab-editar-orcamento">
            <div class="row g-3">
              <!-- Valor Estimado -->
              <div class="col-md-6">
                <label for="editarValorEstimado" class="form-label">Valor Estimado (R$)</label>
                <input type="text"
                        name="valor_estimado"
                        id="editarValorEstimado"
                        class="form-control money-mask" 
                        placeholder="R$ 0,00">
                </div>
              <!-- Descrição do Orçamento Macro -->
              <div class="col-12">
                <label for="editarOrcamentoMacro" class="form-label">Descrição do Orçamento Macro</label>
                <textarea name="orcamento_macro"
                          id="editarOrcamentoMacro"
                          class="form-control"
                          rows="4"
                          placeholder="Explique as premissas e estimativas gerais do orçamento macro"></textarea>
              </div>

              <!-- SEÇÃO DE ARQUIVOS EXISTENTES -->
              <div class="col-12">
                <label class="form-label">Arquivos do Orçamento Macro</label>
                <div id="arquivosOrcamentoExistentes" class="mb-3">
                  <!-- Arquivos existentes serão carregados aqui via JavaScript -->
                </div>
              </div>

              <!-- Upload de novos arquivos do Orçamento Macro -->
              <div class="col-12">
                <label for="editarOrcamentoMacroFiles" class="form-label">Adicionar novos arquivos</label>
                <input type="file"
                       name="orcamento_macro_files[]"
                       id="editarOrcamentoMacroFiles"
                       class="form-control"
                       multiple
                       accept=".pdf,.xlsx,.xls,.docx,.pptx,.zip,.rar">
                <div class="form-text">
                  Você pode anexar planilhas, relatórios ou documentos que subsidiem o orçamento macro.
                </div>
              </div>
            </div>
          </div>
          <!-- =============== FIM ABA ORÇAMENTO MACRO ============== -->

          <!-- ================= ABA: AVALIAÇÕES ================= -->
          <div class="tab-pane fade" id="editar-avaliacoes" role="tabpanel" aria-labelledby="btn-tab-editar-avaliacoes">
            <div class="row g-3">
              <div class="col-md-4">
                <label for="editarAlinhamentoEstrategico" class="form-label">Alinhamento Estratégico</label>
                <input type="number"
                       name="AlinhamentoEstrategico"
                       id="editarAlinhamentoEstrategico"
                       class="form-control"
                       min="0"
                       max="5"
                       required
                       oninput="limitRange(this)">
              </div>
              <div class="col-md-4">
                <label for="editarVulnerabilidadeCiberseguranca" class="form-label">Vulnerabilidade</label>
                <input type="number"
                       name="VulnerabilidadeCiberseguranca"
                       id="editarVulnerabilidadeCiberseguranca"
                       class="form-control"
                       min="0"
                       max="5"
                       required
                       oninput="limitRange(this)">
              </div>
              <div class="col-md-4">
                <label for="editarLPD_DPO_Criptografado" class="form-label">LGPD - DPO</label>
                <input type="number"
                       name="LPD_DPO_Criptografado"
                       id="editarLPD_DPO_Criptografado"
                       class="form-control"
                       min="0"
                       max="5"
                       required
                       oninput="limitRange(this)">
              </div>
              <div class="col-md-4">
                <label for="editarImpactoOperacional" class="form-label">Impacto Operacional</label>
                <input type="number"
                       name="ImpactoOperacional"
                       id="editarImpactoOperacional"
                       class="form-control"
                       min="0"
                       max="5"
                       required
                       oninput="limitRange(this)">
              </div>
              <div class="col-md-4">
                <label for="editarImpactoAdministrativo" class="form-label">Impacto Administrativo</label>
                <input type="number"
                       name="ImpactoAdministrativo"
                       id="editarImpactoAdministrativo"
                       class="form-control"
                       min="0"
                       max="5"
                       required
                       oninput="limitRange(this)">
              </div>
              <div class="col-md-4">
                <label for="editarImpactoFinanceiro" class="form-label">Impacto Financeiro</label>
                <input type="number"
                       name="ImpactoFinanceiro"
                       id="editarImpactoFinanceiro"
                       class="form-control"
                       min="0"
                       max="5"
                       required
                       oninput="limitRange(this)">
              </div>
            </div>
          </div>
          <!-- =============== FIM ABA AVALIAÇÕES ============== -->
        </form>
      </div>

      <!-- BOTÕES DE RODAPÉ DO MODAL -->
      <div class="modal-footer">
        <button type="button"
                class="btn btn-secondary"
                onclick="hideModal('modalEditarProjeto')">
          <i class="fa fa-times me-2"></i> Cancelar
        </button>
        <button type="button"
                class="btn btn-primary"
                onclick="submitProjectForm('formEditarProjeto', 'editar')">
          <i class="fa fa-save me-2"></i> Salvar Alterações
        </button>
      </div>
    </div>
  </div>
</div>

<!-- CSS para os arquivos -->
<style>
.arquivo-orcamento-item {
    background: #2e3240;
    border: 1px solid #52555d;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    transition: background 0.3s ease;
}

.arquivo-orcamento-item:hover {
    background: #353943;
}

.arquivo-info {
    display: flex;
    align-items: center;
    flex-grow: 1;
}

.arquivo-icone {
    font-size: 2rem;
    margin-right: 15px;
    color: #007bff;
}

.arquivo-detalhes h6 {
    margin: 0;
    color: #fff;
    font-weight: 600;
}

.arquivo-detalhes small {
    color: #a1aab2;
    font-size: 0.85rem;
}

.arquivo-acoes {
    display: flex;
    gap: 8px;
}

.arquivo-acoes .btn {
    padding: 6px 12px;
    font-size: 0.875rem;
}

.arquivo-orcamento-empty {
    text-align: center;
    color: #a1aab2;
    font-style: italic;
    padding: 20px;
    background: #2e3240;
    border-radius: 8px;
    border: 2px dashed #52555d;
}
</style>

<script>
// Função para obter ícone baseado na extensão do arquivo
function getFileIcon(fileName) {
    const extension = fileName.split('.').pop().toLowerCase();
    const iconMap = {
        'pdf': 'fa-file-pdf-o',
        'xlsx': 'fa-file-excel-o',
        'xls': 'fa-file-excel-o',
        'docx': 'fa-file-word-o',
        'doc': 'fa-file-word-o',
        'pptx': 'fa-file-powerpoint-o',
        'ppt': 'fa-file-powerpoint-o',
        'zip': 'fa-file-archive-o',
        'rar': 'fa-file-archive-o',
        'jpg': 'fa-file-image-o',
        'jpeg': 'fa-file-image-o',
        'png': 'fa-file-image-o',
        'gif': 'fa-file-image-o'
    };
    return iconMap[extension] || 'fa-file-o';
}

// Função para carregar arquivos do orçamento macro no modal de edição
function carregarArquivosOrcamentoEdicao(projetoId) {
    fetch('projetos', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'listar_arquivos_orcamento', projetoId })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            mostrarArquivosOrcamentoEdicao(data.arquivos);
        }
    })
    .catch(e => console.error('Erro ao carregar arquivos:', e));
}

// Função para exibir arquivos na seção de edição
function mostrarArquivosOrcamentoEdicao(arquivos) {
    const container = document.getElementById('arquivosOrcamentoExistentes');
    
    if (!arquivos || arquivos.length === 0) {
        container.innerHTML = `
            <div class="arquivo-orcamento-empty">
                <i class="fa fa-folder-open-o fa-2x mb-2"></i>
                <p class="mb-0">Nenhum arquivo anexado ao orçamento macro</p>
            </div>
        `;
        return;
    }

    let html = '';
    arquivos.forEach((arquivo, index) => {
        const icone = getFileIcon(arquivo.arquivo_original);
        html += `
            <div class="arquivo-orcamento-item" data-arquivo-id="${index}">
                <div class="arquivo-info">
                    <div class="arquivo-icone">
                        <i class="fa ${icone}"></i>
                    </div>
                    <div class="arquivo-detalhes">
                        <h6>${escapeHtml(arquivo.arquivo_original)}</h6>
                        <small>Enviado em ${formatDatePt(arquivo.data_upload)}</small>
                    </div>
                </div>
                <div class="arquivo-acoes">
                    <a href="uploads/${arquivo.arquivo_nome}" 
                       target="_blank" 
                       class="btn btn-sm btn-outline-info"
                       title="Visualizar/Baixar">
                        <i class="fa fa-eye"></i>
                    </a>
                    <button type="button" 
                            class="btn btn-sm btn-outline-danger"
                            title="Remover arquivo"
                            onclick="removerArquivoOrcamento('${arquivo.arquivo_nome}', ${index})">
                        <i class="fa fa-trash"></i>
                    </button>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

// Função para remover arquivo do orçamento macro
function removerArquivoOrcamento(nomeArquivo, index) {
    if (!confirm('Deseja realmente remover este arquivo do orçamento macro?')) {
        return;
    }

    const projetoId = document.getElementById('editarProjetoId').value;
    
    fetch('projetos', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ 
            action: 'remover_arquivo_orcamento', 
            projetoId,
            nomeArquivo 
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            // Remove o item da interface
            const item = document.querySelector(`[data-arquivo-id="${index}"]`);
            if (item) {
                item.remove();
            }
            
            // Recarrega a lista para atualizar
            carregarArquivosOrcamentoEdicao(projetoId);
            
            alert('Arquivo removido com sucesso!');
        } else {
            alert(data.message || 'Erro ao remover arquivo.');
        }
    })
    .catch(e => {
        console.error('Erro ao remover arquivo:', e);
        alert('Erro ao remover arquivo.');
    });
}

// Modificar a função showEditModal para carregar os arquivos também
const originalShowEditModal = window.showEditModal;
window.showEditModal = function(projetoId) {
    // Chama a função original
    originalShowEditModal(projetoId);
    
    // Carrega os arquivos do orçamento macro após um pequeno delay
    setTimeout(() => {
        carregarArquivosOrcamentoEdicao(projetoId);
    }, 500);
};
</script>



<!-- ================== MODAL TOP BVP (marcar OKRs) ================== -->
<div class="modal" id="modalProjetosDisponiveis">
  <div class="modal-dialog modal-dialog-centered" style="max-width:500px;">
    <div class="modal-content">
      <div class="modal-header text-light">
        <h5 class="modal-title"><i class="fa fa-clipboard-check"></i> BVP Disponíveis</h5>
        <button type="button" class="btn-close btn-close-white" onclick="hideModal('modalProjetosDisponiveis')"></button>
      </div>
      <div class="modal-body">
        <form method="POST" action="projetos" id="formMarcarOKRs">
          <input type="hidden" name="acao" value="marcarOKRs">

          <!-- 1) Seletor de setor -->
          <div class="mb-3">
            <label for="setorOKR" class="form-label">Setor</label>
<?php if (in_array($perfilAcesso, [1,9])): // Admin/Sub-Diretor ?>
            <select name="setorOKR" id="setorOKR" class="form-select" required onchange="atualizarQuantidadeBVP(this.value)">
             
<?php foreach($setores as $s): 
    // Busca a quantidade configurada para este setor
    $stmtQtd = $pdo->prepare("SELECT quantidade FROM setor_bvp_config WHERE setor_id = ?");
    $stmtQtd->execute([$s['id']]);
    $qtdConfig = (int)$stmtQtd->fetchColumn();
    
    // Se não tiver na tabela, verifica na sessão
    if ($qtdConfig <= 0 && isset($_SESSION['ultimaQuantidadeOKR'][$s['id']])) {
        $qtdConfig = (int)$_SESSION['ultimaQuantidadeOKR'][$s['id']];
    }
?>
              <option value="<?= $s['id'] ?>" data-qtd="<?= $qtdConfig ?>"><?= htmlspecialchars($s['nome_do_setor']) ?></option>
<?php endforeach; ?>
            </select>
<?php else: // Gestor só vê o próprio setor 
    // Busca a quantidade configurada para o setor do gestor
    $stmtQtd = $pdo->prepare("SELECT quantidade FROM setor_bvp_config WHERE setor_id = ?");
    $stmtQtd->execute([$userSetorId]);
    $qtdConfig = (int)$stmtQtd->fetchColumn();
    
    // Se não tiver na tabela, verifica na sessão
    if ($qtdConfig <= 0 && isset($_SESSION['ultimaQuantidadeOKR'][$userSetorId])) {
        $qtdConfig = (int)$_SESSION['ultimaQuantidadeOKR'][$userSetorId];
    }
?>
            <select name="setorOKR" id="setorOKR" class="form-select" readonly>
              <option value="<?= $userSetorId ?>" data-qtd="<?= $qtdConfig ?>" selected><?= htmlspecialchars($userSetorName) ?></option>
            </select>
<?php endif; ?>
          </div>

          <!-- 2) Quantidade de vagas -->
          <div class="mb-3">
            <label for="quantidadeOKR" class="form-label">Projetos Disponíveis para OKR</label>
            <input type="number" name="quantidadeOKR" id="quantidadeOKR"
                   class="form-control" min="0"
                   value="<?= $qtdConfig ?>"
                   required>
            <div class="form-text">
              Defina quantos projetos (ainda não em OKR) devem ficar disponíveis para este setor.
            </div>
          </div>

          <button type="submit" class="btn btn-primary w-100">
            <i class="fa fa-save"></i> Salvar
          </button>
        </form>
      </div>
    </div>
  </div>
</div>


<!-- ================== MODAL DETALHES DO PROJETO ================== -->
<div class="modal" id="modalDetalhesProjeto">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content modern-modal">
            <div class="modal-header d-flex justify-content-between align-items-center">
                <h5 class="modal-title"><i class="fa fa-info-circle me-2"></i> Detalhes do Projeto</h5>
                <button type="button" class="btn-close btn-close-white" onclick="hideModal('modalDetalhesProjeto')"></button>
            </div>
            <div class="modal-body p-0">
                <div class="row g-0">
                    <!-- Sidebar com Navegação -->
                    <div class="col-md-3 sidebar-nav">
                        <ul class="nav flex-column nav-pills" id="projectTabs" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" id="overview-tab" data-bs-toggle="pill" href="#overview" role="tab">
                                    <i class="fa fa-home me-2"></i> Visão Geral
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="orcamento-tab" data-bs-toggle="pill" href="#orcamento" role="tab">
                                    <i class="fa fa-calculator me-2"></i> Orçamento Macro
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="tarefas-tab" data-bs-toggle="pill" href="#tarefas" role="tab">
                                    <i class="fa fa-tasks me-2"></i> Tarefas
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="anexos-tab" data-bs-toggle="pill" href="#anexos" role="tab">
                                    <i class="fa fa-paperclip me-2"></i> Anexos
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="atividades-tab" data-bs-toggle="pill" href="#atividades" role="tab">
                                    <i class="fa fa-comments me-2"></i> Comentários
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="dependencias-tab" data-bs-toggle="pill" href="#dependencias" role="tab">
                                    <i class="fa fa-link me-2"></i> Outros Projetos Vinculados
                                </a>
                            </li>
                        </ul>
                    </div>
                    <!-- Conteúdo das Abas -->
                    <div class="col-md-9 tab-content p-4">
                        <!-- Visão Geral -->
                        <div class="tab-pane fade show active" id="overview" role="tabpanel">
                            <div id="projectDetails" class="project-details"></div>
                        </div>
                        <!-- Orçamento Macro -->
                        <div class="tab-pane fade" id="orcamento" role="tabpanel">
                            <div id="orcamentoDetails" class="orcamento-details"></div>
                        </div>
                        <!-- Tarefas -->
                        <div class="tab-pane fade" id="tarefas" role="tabpanel">
                            <div class="mb-3">
                                <div class="progress" style="height: 20px;">
                                    <div class="progress-bar progress-bar-striped bg-success" id="projectProgressBar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
                                </div>
                            </div>
                            <div id="listaTarefas" class="task-container"></div>
                            <div id="aviso-tarefa-concluida" class="alert alert-info mt-2" style="display:none;">
                                Projeto concluído. Não é possível adicionar tarefas.
                            </div>
                            <form id="formNovaTarefa" onsubmit="return false;" class="mt-4 task-form form-bloqueavel">
                                <input type="hidden" name="projetoId" id="tarefasProjetoId">
                                <input type="hidden" name="parent_subtarefa_id" id="novaTarefaParentId">
                                <div class="mb-3">
                                    <label for="nomeTarefa" class="form-label">Nova Tarefa</label>
                                    <input type="text" class="form-control" id="nomeTarefa" name="nome_subtarefa" required>
                                </div>
                                <div class="mb-3">
                                    <label for="descricaoTarefa" class="form-label">Descrição (opcional)</label>
                                    <textarea class="form-control" id="descricaoTarefa" name="descricao" rows="2"></textarea>
                                </div>
                                <button type="submit" class="btn btn-success w-100"><i class="fa fa-plus me-2"></i> Adicionar Tarefa</button>
                            </form>
                        </div>
                        <!-- Anexos -->
                        <div class="tab-pane fade" id="anexos" role="tabpanel">
                            <div id="listaAnexos" class="attachment-container"></div>
                            <hr class="my-4">
                            <div id="aviso-anexo-concluido" class="alert alert-info mt-2" style="display:none;">
                                Projeto concluído. Não é possível adicionar anexos.
                            </div>
                            <form id="formUploadAnexo" onsubmit="return false;" enctype="multipart/form-data" class="attachment-form form-bloqueavel">
                                <input type="hidden" name="projetoId" id="anexosProjetoId">
                                <div class="mb-3">
                                    <label for="arquivoAnexo" class="form-label">Novo Anexo</label>
                                    <input type="file" class="form-control" id="arquivoAnexo" name="arquivo" required>
                                </div>
                                <button type="submit" class="btn btn-success"><i class="fa fa-upload me-2"></i> Enviar Anexo</button>
                            </form>
                        </div>
                        <!-- Atividades -->
                        <div class="tab-pane fade" id="atividades" role="tabpanel">
                            <div id="listaAtividades" class="activity-list"></div>
                            <div id="aviso-comentario-concluido" class="alert alert-info mt-2" style="display:none;">
                                Projeto concluído. Não é possível adicionar comentários.
                            </div>
                            <form id="formNovaAtividade" onsubmit="return false;" class="activity-form mt-4 form-bloqueavel">
                                <input type="hidden" name="projetoId" id="atividadesProjetoId">
                                <div class="mb-3">
                                    <label for="textoAtividade" class="form-label">Novo Comentário</label>
                                    <textarea class="form-control" id="textoAtividade" name="comentario" rows="3" required></textarea>
                                </div>
                                <button type="submit" class="btn btn-info w-100"><i class="fa fa-comment me-2"></i> Adicionar Comentário</button>
                            </form>
                        </div>
                        <!-- Dependências -->
                        <div class="tab-pane fade" id="dependencias" role="tabpanel">
                            <div id="listaDependencias" class="dependencies-container"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer" id="modalDetalhesFooter">
                <button type="button" class="btn btn-secondary" onclick="hideModal('modalDetalhesProjeto')">
                    <i class="fa fa-times me-2"></i> Fechar
                </button>
            </div>
        </div>
    </div>
</div>


<!-- ================== MODAL EDITAR TAREFA (Subtarefas) ================== -->
<div class="modal" id="modalEditarTarefa">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header text-light">
                <h5 class="modal-title"><i class="fa fa-edit"></i> Editar Tarefa</h5>
                <button type="button" class="btn-close btn-close-white" onclick="hideModal('modalEditarTarefa')"></button>
            </div>
            <div class="modal-body">
                <form id="formEditarTarefa" onsubmit="return false;">
                    <input type="hidden" id="editarTarefaId" name="subtarefaId">
                    <div class="mb-3">
                        <label for="editarNomeTarefa" class="form-label">Nome da Tarefa</label>
                        <input type="text" class="form-control" id="editarNomeTarefa" name="nome_subtarefa" required>
                    </div>
                    <div class="mb-3">
                        <label for="editarDescricaoTarefa" class="form-label">Descrição</label>
                        <textarea class="form-control" id="editarDescricaoTarefa" name="descricao" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary"
                        onclick="hideModal('modalEditarTarefa')">
                    <i class="fa fa-times me-2"></i> Cancelar
                </button>
                <button type="button" class="btn btn-primary"
                        onclick="salvarEdicaoTarefa()">
                    <i class="fa fa-save me-2"></i> Salvar Alterações
                </button>
            </div>
        </div>
    </div>
</div>
<!-- Fim Modais -->

        </div> <!-- container-fluid -->

        <?php include 'footer.php'; // Rodapé ?>
    </div> <!-- page-wrapper -->
</div> <!-- main-wrapper -->

<div class="chat-windows"></div>

<!-- ================== SCRIPTS GERAIS ================== -->
<script src="assets/libs/jquery/dist/jquery.min.js"></script>
<!-- REMOVIDO: <script src="assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js"></script> -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="dist/js/app.min.js"></script>
<script src="dist/js/app.init.dark.js"></script>
<script src="dist/js/app-style-switcher.js"></script>
<script src="assets/libs/perfect-scrollbar/dist/perfect-scrollbar.jquery.min.js"></script>
<script src="assets/extra-libs/sparkline/sparkline.js"></script>
<script src="dist/js/waves.js"></script>
<script src="dist/js/sidebarmenu.js"></script>
<script src="dist/js/feather.min.js"></script>
<script src="dist/js/custom.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>



<script>
window.PERFIL_ACESSO = <?php echo (int)($perfilAcesso ?? 0); ?>;
</script>


<script>
    window.PROJETO_STATUS_ATUAL = '';

    // Remove preloader ao carregar
    window.onload = function() {
        document.querySelector('.preloader').style.display = 'none';
    };

    function showModal(modalId) {
    const el = document.getElementById(modalId);
    if (!el) return;
    // Cria/pega a instância do Bootstrap Modal e manda mostrar
    const modal = bootstrap.Modal.getOrCreateInstance(el);
    modal.show();
    }

    function hideModal(modalId) {
  const el = document.getElementById(modalId);
  if (!el) return;
  // Pega a instância existente e manda ocultar
  const modal = bootstrap.Modal.getInstance(el);
  if (modal) {
    modal.hide();
  }
}

    // Ao mudar um filtro, enviamos o formulário manualmente
  ['filtroAno','filtroSetor','filtroPrazo'].forEach(id => {
    const el = document.getElementById(id);
    if (el) {
      el.addEventListener('change', () => {
        filtrarProjetos(); 
      });
    }
  });

  function filtrarProjetos(event) {
    // Se vier de um submit do form, evitar que recarregue a página automaticamente
    if (event) event.preventDefault();

    // Pega os valores dos selects
    const ano   = document.getElementById('filtroAno').value.trim();
    const setor = document.getElementById('filtroSetor').value.trim();
    const prazo = document.getElementById('filtroPrazo').value.trim();

    // Monta array apenas com parâmetros não vazios
    const params = [];
    if (ano)   params.push('ano='   + encodeURIComponent(ano));
    if (setor) params.push('setor=' + encodeURIComponent(setor));
    if (prazo) params.push('prazo=' + encodeURIComponent(prazo));

    // Se não houver parâmetros, redireciona só para projetos sem query
    if (params.length === 0) {
      window.location.href = 'projetos';
    } else {
      // Se houver parâmetros, junta em ?chave=valor&chave=valor...
      const queryString = '?' + params.join('&');
      window.location.href = 'projetos' + queryString;
    }
  }

    // Animação nos números do topo
    document.addEventListener('DOMContentLoaded', function() {
        const numbers = document.querySelectorAll('.stats-number');
        numbers.forEach(number => {
            const finalValue = parseInt(number.getAttribute('data-count'));
            let currentValue = 0;
            const duration = 1000;
            const increment = finalValue / (duration / 60);

            const animate = () => {
                currentValue += increment;
                if (currentValue >= finalValue) {
                    number.textContent = finalValue;
                } else {
                    number.textContent = Math.round(currentValue);
                    requestAnimationFrame(animate);
                }
            };

            if (finalValue > 0) {
                requestAnimationFrame(animate);
            } else {
                number.textContent = '0';
            }
        });
    });

    // Exibir campo "Motivo Sigilo" somente quando for "Sigiloso"
    document.addEventListener('DOMContentLoaded', function() {
        const novoSigilo = document.getElementById('novoSigiloProjeto');
        const divNovoMotivo = document.getElementById('divNovoMotivoSigilo');
        if (novoSigilo) {
            novoSigilo.addEventListener('change', function() {
                if (this.value === 'Sigiloso') {
                    divNovoMotivo.style.display = 'block';
                } else {
                    divNovoMotivo.style.display = 'none';
                    document.getElementById('novoMotivoSigilo').value = '';
                }
            });
        }
        const editarSigilo = document.getElementById('editarSigiloProjeto');
        const divEditarMotivo = document.getElementById('divEditarMotivoSigilo');
        if (editarSigilo) {
            editarSigilo.addEventListener('change', function() {
                if (this.value === 'Sigiloso') {
                    divEditarMotivo.style.display = 'block';
                } else {
                    divEditarMotivo.style.display = 'none';
                    document.getElementById('editarMotivoSigilo').value = '';
                }
            });
        }
    });

    function openDetalhesModal(projetoId, disponivelOKR, enviadoFunil, statusProj) {
    // Armazena o status do projeto atual
    PROJETO_STATUS_ATUAL = statusProj;
    // 1) Zera campos
    document.getElementById('tarefasProjetoId').value = projetoId;
    document.getElementById('novaTarefaParentId').value = '';
    document.getElementById('anexosProjetoId').value = projetoId;
    document.getElementById('atividadesProjetoId').value = projetoId;
    document.getElementById('nomeTarefa').value = '';
    document.getElementById('descricaoTarefa').value = '';
    document.getElementById('arquivoAnexo').value = '';
    document.getElementById('textoAtividade').value = '';

    // 2) Carrega Tarefas, Anexos e Atividades
    listarTarefas(projetoId);
    listarAnexos(projetoId);
    listarAtividades(projetoId);

    // Variáveis para verificação do responsável
    let projetoResponsavelId = 0;
    let usuariosEnvolvidos = [];

    // 3) Buscar detalhes
    fetch('projetos', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=get_projeto_details&projetoId=' + projetoId
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            const proj = d.projeto;
            const cont = document.getElementById('projectDetails');

            // Armazenar o ID do responsável para uso posterior
            projetoResponsavelId = parseInt(proj.ResponsavelId) || 0;
            
            // Armazenar usuários envolvidos para uso posterior (para a regra de KeyResult)
            if (proj.UsuariosEnvolvidos) {
                usuariosEnvolvidos = proj.UsuariosEnvolvidos.split(',').map(id => parseInt(id.trim())).filter(id => id > 0);
            }
            
            // Armazenar em campos ocultos para ser usado em outras funções (como gerarHtmlSubtarefas)
            const hiddenFields = `
                <input type="hidden" id="projetoResponsavelId" value="${projetoResponsavelId}">
                <input type="hidden" id="projetoUsuariosEnvolvidos" value="${proj.UsuariosEnvolvidos || ''}">
            `;

            // Calcula BVP total
            const bvpTotal = (
                parseInt(proj.AlinhamentoEstrategico) +
                parseInt(proj.VulnerabilidadeCiberseguranca) +
                parseInt(proj.LPD_DPO_Criptografado) +
                parseInt(proj.ImpactoOperacional) +
                parseInt(proj.ImpactoAdministrativo) +
                parseInt(proj.ImpactoFinanceiro)
            );
            const bvpClass = bvpTotal >= 20 ? 'high' : (bvpTotal >= 10 ? 'medium' : 'low');

            // Badge do status
            let statusBadge = '';
            if (proj.Status === 'backlog') {
                statusBadge = '<span class="badge bg-secondary"><i class="fa fa-list me-1"></i> Backlog</span>';
            } else if (proj.Status === 'andamento') {
                // Se quiser detalhar o andamento igual na tabela principal (OKR etc), pode fazer aqui
                if (proj.EnviadoFunil == 1) {
                    statusBadge = '<span class="badge bg-info"><i class="fa fa-bullseye me-1"></i> Em OKR</span>';
                } else if (proj.DisponivelOKR == 1 && proj.EnviadoFunil == 0) {
                    statusBadge = '<span class="badge bg-primary"><i class="fa fa-hourglass-half me-1"></i> Disp. OKR</span>';
                } else {
                    statusBadge = '<span class="badge bg-warning"><i class="fa fa-clock me-1"></i> Em Andamento</span>';
                }
            } else if (proj.Status === 'bloqueado') {
                statusBadge = '<span class="badge bg-danger"><i class="fa fa-lock me-1"></i> Bloqueado</span>';
            } else if (proj.Status === 'concluido') {
                statusBadge = '<span class="badge bg-success"><i class="fa fa-check-circle me-1"></i> Concluído</span>';
            } else {
                statusBadge = '<span class="badge bg-light text-dark"><i class="fa fa-question-circle me-1"></i> Sem Status</span>';
            }

            // Ajusta Qualificação
            const qualString = proj.qualificacao
                ? proj.qualificacao.split(',').map(q => q.trim()).join(', ')
                : 'Nenhuma';

            // Monta HTML (observando white-space no div da Descrição)
            let html = `
                <h3 class="section-title">${escapeHtml(proj.NomeProjeto)} ${statusBadge}</h3>
                <div class="section">
                    <div class="detail-item">
                        <i class="fa fa-building"></i>
                        <span>Setor Principal: <strong>${escapeHtml(proj.nome_do_setor)}</strong></span>
                    </div>
                    <div class="detail-item">
                        <i class="fa fa-user"></i>
                        <span>Responsável: <strong>${escapeHtml(proj.NomeResponsavel)}</strong></span>
                    </div>
                    <div class="detail-item">
                        <i class="fa fa-calendar"></i>
                        <span>Data de Criação: <strong>${formatDatePt(proj.DataCriacao)}</strong></span>
                    </div>
                    <div class="detail-item">
                        <i class="fa fa-exclamation-circle"></i>
                        <span>Prioridade: <strong>${escapeHtml(proj.Prioridade)}</strong></span>
                    </div>
                    <div class="detail-item">
                        <i class="fa fa-clock"></i>
                        <span>Prazo: <strong>${escapeHtml(proj.Prazo)}</strong></span>
                    </div>
                </div>
                <div class="section mt-4">
                    <h4 class="section-title">Equipe e Dependências</h4>
                    <div class="detail-item">
                        <span><i class="fa fa-users"></i> Setores Envolvidos:
                            <strong>${proj.OutroSetorEnvolvidoNomes || 'Nenhum'}</strong>
                        </span>
                    </div>
                    <div class="detail-item">
                        <span><i class="fa fa-user-plus"></i> Usuários Envolvidos:
                            <strong>${proj.UsuariosEnvolvidosNomes || 'Nenhum'}</strong>
                        </span>
                    </div>
                </div>

                <div class="section mt-4">
                    <h4 class="section-title">Informações Complementares</h4>
                    <div class="detail-item">
                        <i class="fa fa-tag"></i>
                        <span>Tipo do Projeto:
                            <strong>${escapeHtml(proj.tipo_projeto || 'Não definido')}</strong>
                        </span>
                    </div>
                    <div class="detail-item">
                        <i class="fa fa-lock"></i>
                        <span>Sigilo:
                            <strong>${escapeHtml(proj.sigilo_projeto || 'Público')}</strong>
                        </span>
                    </div>
                    ${
                        (proj.sigilo_projeto === 'Sigiloso' && proj.motivo_sigilo.trim() !== '')
                        ? `
                            <div class="detail-item">
                                <i class="fa fa-info-circle"></i>
                                <span>Motivo do Sigilo:
                                    <strong>${escapeHtml(proj.motivo_sigilo)}</strong>
                                </span>
                            </div>
                          `
                        : ''
                    }
                    <div class="detail-item">
                        <i class="fa fa-check"></i>
                        <span>Qualificação:
                            <strong>${escapeHtml(qualString)}</strong>
                        </span>
                    </div>
                    
                    <!-- SEÇÃO ORÇAMENTO MACRO -->
                    ${(proj.valor_estimado || proj.orcamento_macro) ? `
                        <div class="detail-item">
                            <i class="fa fa-calculator"></i>
                            <span>Valor Estimado:
                                <strong>${proj.valor_estimado ? 'R$ ' + formatMoney(proj.valor_estimado) : 'Não informado'}</strong>
                            </span>
                        </div>
                        ${proj.orcamento_macro ? `
                            <div class="detail-item">
                                <i class="fa fa-file"></i>
                                <span>Orçamento Macro: <strong>${escapeHtml(proj.orcamento_macro)}</strong></span>
                            </div>
                        ` : ''}
                        <div class="detail-item">
                            <i class="fa fa-paperclip"></i>
                            <span>Arquivos do Orçamento: <span id="arquivosOrcamentoResumo">Carregando...</span></span>
                        </div>
                    ` : ''}
                    
                    <!-- EVITA INDENTAÇÃO + WHITE-SPACE: PRE-WRAP -->
                    <div class="detail-item">
    <i class="fa fa-align-left"></i>
    <span>Descrição do Projeto:</span>
        </div>
        ${proj.descricao_projeto ? `
            <div style="background: #2e3240; padding: 15px; border-radius: 8px; margin-top: 10px; border-left: 4px solid #007bff;">
                ${escapeHtml(proj.descricao_projeto)}
            </div>
        ` : `
            <div style="background: #2e3240; padding: 15px; border-radius: 8px; margin-top: 10px; text-align: center; color: #a1aab2; font-style: italic;">
                Sem descrição informada
            </div>
        `}

                <div class="section mt-4">
                    <h4 class="section-title">Pontuação BVP</h4>
                    <div class="bvp-grid">
                        <div class="bvp-item">
                            <span class="bvp-label">Alinhamento Estratégico</span>
                            <span class="bvp-value">${proj.AlinhamentoEstrategico}</span>
                        </div>
                        <div class="bvp-item">
                            <span class="bvp-label">Vulnerabilidade Cibersegurança</span>
                            <span class="bvp-value">${proj.VulnerabilidadeCiberseguranca}</span>
                        </div>
                        <div class="bvp-item">
                            <span class="bvp-label">LGPD - DPO</span>
                            <span class="bvp-value">${proj.LPD_DPO_Criptografado}</span>
                        </div>
                        <div class="bvp-item">
                            <span class="bvp-label">Impacto Operacional</span>
                            <span class="bvp-value">${proj.ImpactoOperacional}</span>
                        </div>
                        <div class="bvp-item">
                            <span class="bvp-label">Impacto Administrativo</span>
                            <span class="bvp-value">${proj.ImpactoAdministrativo}</span>
                        </div>
                        <div class="bvp-item">
                            <span class="bvp-label">Impacto Financeiro</span>
                            <span class="bvp-value">${proj.ImpactoFinanceiro}</span>
                        </div>
                    </div>
                    <div class="bvp-total mt-4">
                        <div class="bvp-total-circle ${bvpClass}" data-bvp="${bvpTotal}">
                            <svg viewBox="0 0 36 36">
                                <path class="circle-bg"
                                      d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831
                                         a 15.9155 15.9155 0 0 1 0 -31.831"/>
                                <path class="circle-progress"
                                      stroke-dasharray="${(bvpTotal / 30) * 100}, 100"
                                      d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831
                                         a 15.9155 15.9155 0 0 1 0 -31.831"/>
                            </svg>
                            <span>${bvpTotal}/30</span>
                        </div>
                    </div>
                </div>                
            `;

            // Insere no container com os campos ocultos
            cont.innerHTML = hiddenFields + html;

            // Carrega arquivos do orçamento macro na visão geral
            carregarArquivosOrcamentoVisaoGeral(projetoId);
            
            // Carrega arquivos do orçamento macro na aba específica
            carregarOrcamentoMacroDetalhado(projetoId);

            // Dependências
            if (proj.DependenciasProjetos) {
                const deps = proj.DependenciasProjetos.split(',');
                loadDependencias(deps);
            } else {
                document.getElementById('listaDependencias').innerHTML = '<p class="text-muted">Sem projetos vinculados.</p>';
            }
            
            // NOVA REGRA: Verificar aqui mesmo se o usuário atual é o responsável pelo projeto
            // para mostrar o botão "Liberar OKR"
            const isResponsavel = (parseInt(USER_ID) === projetoResponsavelId);
            const isPerfil129 = [1, 2, 9].includes(parseInt(window.PERFIL_ACESSO));
            
            // 4) Ajusta rodapé do modal
            const footer = document.getElementById('modalDetalhesFooter');
            footer.innerHTML = `
                <button type="button" class="btn btn-secondary" onclick="hideModal('modalDetalhesProjeto')">
                    <i class="fa fa-times me-2"></i> Fechar
                </button>
            `;

            // Perfis permitidos para autorizar ou voltar para backlog
            const perfisBacklog = [1, 2, 4, 5, 8, 9];

            // Botão "Autorizar Projeto" (quando está em backlog)
            if (perfisBacklog.includes(Number(window.PERFIL_ACESSO)) && statusProj === 'backlog') {
                footer.innerHTML += `
                    <button type="button" class="btn btn-success ms-2" id="btnAutorizarProjeto">
                        <i class="fa fa-thumbs-up me-2"></i> Autorizar Projeto
                    </button>
                `;
                setTimeout(function() {
                    document.getElementById('btnAutorizarProjeto').onclick = function() {
                        autorizarProjeto(projetoId);
                    };
                }, 50);
            }

            // Botão "Voltar p/ Backlog" (quando está em andamento)
            if (perfisBacklog.includes(Number(window.PERFIL_ACESSO)) && statusProj === 'andamento') {
                footer.innerHTML += `
                    <button type="button" class="btn btn-warning ms-2" id="btnVoltarBacklog">
                        <i class="fa fa-undo me-2"></i> Voltar p/ Backlog
                    </button>
                `;
                setTimeout(function() {
                    document.getElementById('btnVoltarBacklog').onclick = function() {
                        voltarBacklog(projetoId);
                    };
                }, 50);
            }

            // NOVA REGRA: Verificar se o usuário é responsável pelo projeto OU pertence aos perfis 1, 2, 9
            // Botão "Liberar OKR" - Admin (1), Gestor (2), Sub Diretor (9) e RESPONSÁVEL podem liberar
            if (statusProj === 'andamento' && disponivelOKR == 1 && enviadoFunil == 0 && 
                (isPerfil129 || isResponsavel)) {
                footer.innerHTML += `
                    <button type="button" class="btn btn-success ms-2" onclick="liberarOKR(${projetoId})">
                        <i class="fa fa-bullseye me-2"></i> Liberar OKR
                    </button>
                `;
            }
            
            // Botão "Visualizar OKR" - para projetos já em OKR, bloqueados ou concluídos
            if ((statusProj === 'andamento' && enviadoFunil == 1)
                || statusProj === 'bloqueado'
                || statusProj === 'concluido') {
                footer.innerHTML += `
                    <button type="button" class="btn btn-outline-success ms-2" onclick="location.href='okr?id=${projetoId}'">
                        <i class="fa fa-bullseye me-2"></i> Visualizar OKR
                    </button>
                `;
            }
        }
    })
    .catch(e => console.error(e));

    // 5) Bloqueio dos formulários conforme status do projeto
    if (statusProj === 'concluido') {
        $('#formNovaTarefa').hide();
        $('#aviso-tarefa-concluida').show();

        $('#formUploadAnexo').hide();
        $('#aviso-anexo-concluido').show();

        $('#formNovaAtividade').hide();
        $('#aviso-comentario-concluido').show();
    } else {
        $('#formNovaTarefa').show();
        $('#aviso-tarefa-concluida').hide();

        $('#formUploadAnexo').show();
        $('#aviso-anexo-concluido').hide();

        $('#formNovaAtividade').show();
        $('#aviso-comentario-concluido').hide();
    }

    // 6) Exibir modal
    showModal('modalDetalhesProjeto');
}





    function formatDatePt(dateStr) {
        if (!dateStr || dateStr.startsWith('0000-00-00')) return 'N/D';
        let [data, hora] = dateStr.split(' ');
        let [yyyy, mm, dd] = data.split('-');
        return hora ? `${dd}/${mm}/${yyyy} ${hora}` : `${dd}/${mm}/${yyyy}`;
    }
    function escapeHtml(txt) {
        if (!txt) return '';
        return txt
          .replace(/&/g, "&amp;")
          .replace(/"/g, "&quot;")
          .replace(/'/g, "&#039;")
          .replace(/</g, "&lt;")
          .replace(/>/g, "&gt;");
    }

    // Dependências
    window.listaProjetosJS = <?php echo json_encode($listaProjetos, JSON_UNESCAPED_UNICODE); ?>;
    function loadDependencias(deps) {
        const container = document.getElementById('listaDependencias');
        if (!container) return;
        container.innerHTML = '';

        const uniqueDeps = [...new Set(deps)];
        if (!uniqueDeps || uniqueDeps.length === 0) {
            container.innerHTML = '<p class="text-muted">Sem projetos vinculados.</p>';
            return;
        }

        const dependencyData = [];
        const processDependency = (depId) => {
            return fetch('projetos', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'listar_subtarefas', projetoId: depId })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    let tasks = data.subtarefas;
                    let { total, completed } = countTasks(tasks);
                    let perc = total > 0 ? Math.round((completed / total) * 100) : 0;
                    let projectName = 'Projeto ' + depId;
                    const found = window.listaProjetosJS.find(x => x.Id == depId);
                    if (found) projectName = found.NomeProjeto;
                    dependencyData.push({
                        depId: depId,
                        projectName: projectName,
                        perc: perc,
                        total: total,
                        completed: completed
                    });
                } else {
                    console.warn(`Erro ao carregar dependência #${depId}: ${data.message || 'Erro desconhecido'}`);
                    dependencyData.push({
                        depId: depId,
                        projectName: 'Erro ao carregar (#' + depId + ')',
                        perc: 0,
                        total: 0,
                        completed: 0
                    });
                }
            })
            .catch(e => {
                console.error(`Erro ao carregar dependência #${depId}:`, e);
                dependencyData.push({
                    depId: depId,
                    projectName: 'Erro ao carregar (#' + depId + ')',
                    perc: 0,
                    total: 0,
                    completed: 0
                });
            });
        };

        const promises = uniqueDeps.map(depId => processDependency(depId));
        Promise.all(promises)
            .then(() => {
                let html = '';
                dependencyData.forEach(dep => {
                    html += `
                        <div style="margin-bottom: 10px;">
                            <strong>${escapeHtml(dep.projectName)}</strong><br>
                            <div class="progress" style="height: 20px;">
                                <div class="progress-bar progress-bar-striped progress-bar-animated"
                                     role="progressbar"
                                     style="width: ${dep.perc}%;"
                                     aria-valuenow="${dep.perc}" aria-valuemin="0" aria-valuemax="100">
                                    ${dep.perc}%
                                </div>
                            </div>
                        </div>
                    `;
                });
                container.innerHTML = html || '<p class="text-muted">Sem dados de dependências.</p>';
            })
            .catch(error => {
                console.error('Erro geral ao processar dependências:', error);
                container.innerHTML = '<p class="text-danger">Erro ao carregar dependências.</p>';
            });
    }
    function countTasks(tasks) {
        let total = 0, completed = 0;
        tasks.forEach(t => {
            total++;
            if (t.concluida == 1) completed++;
            if (t.children && t.children.length > 0) {
                const c = countTasks(t.children);
                total += c.total;
                completed += c.completed;
            }
        });
        return { total, completed };
    }

    function liberarOKR(projetoId) {
    if (!confirm('Deseja liberar este projeto para OKR?')) return;
    
    // Obter o ID do projeto e o setor (se existir)
    let setorOKR = document.getElementById('setorOKR') ? document.getElementById('setorOKR').value : '';
    
    // Preparar os dados para envio
    let formData = new URLSearchParams();
    formData.append('action', 'liberar_okr');
    formData.append('projetoId', projetoId);
    if (setorOKR) {
        formData.append('setorOKR', setorOKR);
    }
    
    // Enviar requisição
    fetch('projetos', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: formData
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            alert(d.message);
            window.location.reload();
        } else {
            alert(d.message || 'Erro ao liberar OKR.');
        }
    })
    .catch(e => {
        console.error('Erro ao liberar OKR:', e);
        alert('Erro ao liberar OKR. Verifique o console para mais detalhes.');
    });
}

   function filtrarUsuariosPorSetores(prefix) {
    const usuarios = <?= json_encode($usuarios, JSON_UNESCAPED_UNICODE) ?>;
    const setorSelect = document.getElementById(`${prefix}OutroSetorEnvolvido`);
    const usuariosSelect = document.getElementById(`${prefix}UsuariosEnvolvidos`);
    
    // Obtém todos os setores selecionados
    const setoresSelecionados = Array.from(setorSelect.selectedOptions).map(opt => opt.value);
    
    // Se nenhum setor estiver selecionado, limpa a lista de usuários
    if (setoresSelecionados.length === 0) {
        usuariosSelect.innerHTML = '<option disabled>Selecione um setor primeiro</option>';
        return;
    }
    
    // Filtra os usuários que pertencem aos setores selecionados
    const usuariosFiltrados = usuarios.filter(u => 
        u.SetorId && setoresSelecionados.includes(u.SetorId.toString())
    );
    
    // Atualiza o select de usuários
    usuariosSelect.innerHTML = '';
    
    if (usuariosFiltrados.length === 0) {
        usuariosSelect.innerHTML = '<option disabled>Nenhum usuário encontrado nos setores selecionados</option>';
    } else {
        // Adiciona os usuários filtrados ao select
        usuariosFiltrados.forEach(u => {
            usuariosSelect.add(new Option(u.Nome, u.Id));
        });
    }
}

// Modificar a função populateSelects existente
function populateSelects(prefix) {
    // dados vindos do PHP
    const setores  = <?= json_encode($setores, JSON_UNESCAPED_UNICODE) ?>;
    const usuarios = <?= json_encode($usuarios, JSON_UNESCAPED_UNICODE) ?>;

    // 1) Filtrar Setores
    let setoresFiltrados;
    if ([1,7,9].includes(PERFIL_ACESSO)) {
        // Admin, Diretor, Sub Diretor veem todos
        setoresFiltrados = setores;
    } else {
        // todos os outros só veem o seu próprio setor
        setoresFiltrados = setores.filter(s => s.id === USER_SETOR_ID);
    }

    const setorSelect = document.getElementById(`${prefix}SetorRelacionadoId`);
    setorSelect.innerHTML = '';
    setoresFiltrados.forEach(s => {
        setorSelect.add(new Option(s.nome_do_setor, s.id));
    });

    // 2) Filtrar Usuários para Responsável
    let usuariosFiltrados;
    if ([1,7,9].includes(PERFIL_ACESSO)) {
        // Admin, Diretor, Sub Diretor veem todos
        usuariosFiltrados = usuarios;
    } else if (PERFIL_ACESSO === 2) {
        // Gestor vê só sua equipe (mesmo setor)
        usuariosFiltrados = usuarios.filter(u => u.SetorId === USER_SETOR_ID);
    } else {
        // Demais perfis só podem selecionar a si próprios
        usuariosFiltrados = usuarios.filter(u => u.Id === USER_ID);
    }

    const respSelect = document.getElementById(`${prefix}ResponsavelId`);
    respSelect.innerHTML = '';
    usuariosFiltrados.forEach(u => {
        respSelect.add(new Option(u.Nome, u.Id));
    });
    
    // Adiciona listener para atualizar usuários envolvidos quando o responsável muda
    respSelect.addEventListener('change', function() {
        atualizarUsuariosEnvolvidos(prefix, this.value);
    });

    // Para o select de setores
    const outroSetor = document.getElementById(`${prefix}OutroSetorEnvolvido`);
    outroSetor.innerHTML = setores.map(s =>
        `<option value="${s.id}">${escapeHtml(s.nome_do_setor)}</option>`
    ).join('');
    
    // Adiciona event listener para o select de "Setores Envolvidos"
    outroSetor.addEventListener('change', function() {
        filtrarUsuariosPorSetores(prefix);
    });

    // Para o select de projetos dependentes
    const dependProj = document.getElementById(`${prefix}DependenciasProjetos`);
    dependProj.innerHTML = window.listaProjetosJS.map(p =>
        `<option value="${p.Id}">${escapeHtml(p.NomeProjeto)}</option>`
    ).join('');
    
    // Inicializa o select de usuários envolvidos com mensagem instrucional
    const usuariosSelect = document.getElementById(`${prefix}UsuariosEnvolvidos`);
    usuariosSelect.innerHTML = '<option disabled>Selecione um ou mais setores acima</option>';
}

// Atualiza a função de atualizarUsuariosEnvolvidos para não interferir na filtragem por setor
function atualizarUsuariosEnvolvidos(prefix, responsavelId) {
    // Se não tiver setores selecionados, não alteramos a lista filtrada
    const setorSelect = document.getElementById(`${prefix}OutroSetorEnvolvido`);
    const setoresSelecionados = Array.from(setorSelect.selectedOptions).map(opt => opt.value);
    
    // Se já tem setores selecionados, filtramos por eles, caso contrário não alteramos
    if (setoresSelecionados.length > 0) {
        filtrarUsuariosPorSetores(prefix);
    }
}


    // Botão Novo Projeto
    function showNewProjectModal() {
    populateSelects('novo');
    document.getElementById('novoDataCriacao').value = new Date().toISOString().split('T')[0];
    document.getElementById('novoPrioridade').value = 'Média';
    document.getElementById('novoPrazo').value = 'Curto';
    
    // Define o usuário logado como responsável padrão
    document.getElementById('novoResponsavelId').value = USER_ID;
    
    // Atualiza a lista de usuários envolvidos para excluir o responsável
    atualizarUsuariosEnvolvidos('novo', USER_ID);
    
    [
        'AlinhamentoEstrategico',
        'VulnerabilidadeCiberseguranca',
        'LPD_DPO_Criptografado',
        'ImpactoOperacional',
        'ImpactoAdministrativo',
        'ImpactoFinanceiro'
    ].forEach(id => {
        document.getElementById(`novo${id}`).value = 0;
    });
    
    ['novoOutroSetorEnvolvido', 'novoUsuariosEnvolvidos', 'novoDependenciasProjetos'].forEach(id => {
        const select = document.getElementById(id);
        Array.from(select.options).forEach(option => option.selected = false);
    });
    
    showModal('modalNovoProjeto');
}

// Botão Editar
function showEditModal(projetoId) {
    // 1) Preenche selects de setor e responsável
    populateSelects('editar');
    // 2) Atribui ID do projeto ao input hidden
    document.getElementById('editarProjetoId').value = projetoId;

    // 3) Monta os parâmetros em URLSearchParams e faz a requisição
    const params = new URLSearchParams();
    params.append('action', 'get_projeto_details');
    params.append('projetoId', projetoId);

    fetch('projetos', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: params.toString()
    })
    .then(response => response.json().catch(() => {
        throw new Error('Resposta inválida do servidor.');
    }))
    .then(data => {
        if (!data.success) {
            alert(data.message || 'Erro ao carregar detalhes do projeto.');
            return;
        }
        const p = data.projeto;

        // ==== DADOS BÁSICOS ====
        const setorEl       = document.getElementById('editarSetorRelacionadoId');
        const respEl        = document.getElementById('editarResponsavelId');
        const nomeEl        = document.getElementById('editarNomeProjeto');
        const dataCriacaoEl = document.getElementById('editarDataCriacao');
        const priEl         = document.getElementById('editarPrioridade');
        const prazoEl       = document.getElementById('editarPrazo');

        if (setorEl) setorEl.value = p.SetorRelacionadoId;
        if (respEl) {
            respEl.value = p.ResponsavelId;
            // Atualiza lista de usuários envolvidos conforme responsável
            atualizarUsuariosEnvolvidos('editar', p.ResponsavelId);
        }
        if (nomeEl)        nomeEl.value        = p.NomeProjeto;
        if (dataCriacaoEl) dataCriacaoEl.value = p.DataCriacao.split(' ')[0];
        if (priEl)         priEl.value         = p.Prioridade;
        if (prazoEl)       prazoEl.value       = p.Prazo;

        // ==== COMPLEMENTARES ====
        ['OutroSetorEnvolvido', 'UsuariosEnvolvidos', 'DependenciasProjetos'].forEach(field => {
            const valores = p[field] ? p[field].split(',') : [];
            const select  = document.getElementById(`editar${field}`);
            if (select) {
                Array.from(select.options).forEach(opt => {
                    opt.selected = valores.includes(opt.value);
                });
            }
        });

        // ==== NOVOS CAMPOS ====
        const tipoProjEl   = document.getElementById('editarTipoProjeto');
        const sigiloEl     = document.getElementById('editarSigiloProjeto');
        const motivoEl     = document.getElementById('editarMotivoSigilo');
        const divMotivoSig = document.getElementById('divEditarMotivoSigilo');

        if (tipoProjEl) tipoProjEl.value = p.tipo_projeto || '';
        if (sigiloEl) {
            sigiloEl.value = p.sigilo_projeto || 'Público';
            if (p.sigilo_projeto === 'Sigiloso' && divMotivoSig) {
                divMotivoSig.style.display = 'block';
            } else if (divMotivoSig) {
                divMotivoSig.style.display = 'none';
            }
        }
        if (motivoEl) motivoEl.value = p.motivo_sigilo || '';

        // Qualificação (checkboxes)
        if (p.qualificacao) {
            const quals = p.qualificacao.split(',');
            for (let i = 1; i <= 8; i++) {
                const cb = document.getElementById(`editarQualificacao${i}`);
                if (cb) {
                    cb.checked = quals.includes(cb.value);
                }
            }
        } else {
            for (let i = 1; i <= 8; i++) {
                const cb = document.getElementById(`editarQualificacao${i}`);
                if (cb) cb.checked = false;
            }
        }

        // Descrição do Projeto
        const descEl = document.getElementById('editarDescricaoProjeto');
        if (descEl) descEl.value = p.descricao_projeto || '';

        // ==== AVALIAÇÕES ====
        const alinhEl = document.getElementById('editarAlinhamentoEstrategico');
        const vulEl   = document.getElementById('editarVulnerabilidadeCiberseguranca');
        const lpdEl   = document.getElementById('editarLPD_DPO_Criptografado');
        const opEl    = document.getElementById('editarImpactoOperacional');
        const admEl   = document.getElementById('editarImpactoAdministrativo');
        const finEl   = document.getElementById('editarImpactoFinanceiro');

        if (alinhEl) alinhEl.value = p.AlinhamentoEstrategico;
        if (vulEl)   vulEl.value   = p.VulnerabilidadeCiberseguranca;
        if (lpdEl)   lpdEl.value   = p.LPD_DPO_Criptografado;
        if (opEl)    opEl.value    = p.ImpactoOperacional;
        if (admEl)   admEl.value   = p.ImpactoAdministrativo;
        if (finEl)   finEl.value   = p.ImpactoFinanceiro;

        // ==== ABA ORÇAMENTO MACRO ====
        const valorEstEl  = document.getElementById('editarValorEstimado');
        const orcamentoEl = document.getElementById('editarOrcamentoMacro');
        if (valorEstEl) {
    if (p.valor_estimado && p.valor_estimado !== null && p.valor_estimado !== '') {
        const valorNumerico = parseFloat(p.valor_estimado);
        if (!isNaN(valorNumerico)) {
            // Formatar para o padrão brasileiro R$ X.XXX,XX
            const valorFormatado = 'R$ ' + valorNumerico.toLocaleString('pt-BR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
            valorEstEl.value = valorFormatado;
        } else {
            valorEstEl.value = '';
        }
    } else {
        valorEstEl.value = '';
    }
}
        if (orcamentoEl) orcamentoEl.value = p.orcamento_macro || '';

        // 4) Exibe o modal de edição usando o Bootstrap 5
        const modalEl = document.getElementById('modalEditarProjeto');
        const modal   = new bootstrap.Modal(modalEl);
        modal.show();

        // 5) CARREGA OS ARQUIVOS DO ORÇAMENTO MACRO APÓS O MODAL ABRIR
        setTimeout(() => {
            carregarArquivosOrcamentoEdicao(projetoId);
        }, 500);
    })
    .catch(err => {
        console.error('Erro ao carregar projeto:', err);
        alert('Erro ao carregar detalhes do projeto.');
    });
}



    // Salvar projeto
    function submitProjectForm(formId, action) {
    const form = document.getElementById(formId);
    const formData = new FormData(form);
    formData.append('acao', action);

    // Validação dos campos obrigatórios
    const requiredFields = ['NomeProjeto', 'SetorRelacionadoId', 'ResponsavelId', 'DataCriacao', 'Prioridade', 'Prazo'];
    for (let field of requiredFields) {
        if (!formData.get(field)) {
            alert(`Por favor, preencha o campo ${field.replace(/([A-Z])/g, ' $1').trim()} na aba "Dados Básicos"!`);
            return;
        }
    }

    if (formId === 'formNovoProjeto') {
        const outroSetor = formData.getAll('OutroSetorEnvolvido[]');
        const usuarios = formData.getAll('UsuariosEnvolvidos[]');
        const deps = formData.getAll('DependenciasProjetos[]');
        if (outroSetor.length === 0 && usuarios.length === 0 && deps.length === 0) {
            alert("Por favor, selecione ao menos um item na aba 'Complementares' (Setores, Usuários ou Dependências).");
            return;
        }
    }

    fetch('projetos', {
        method: 'POST',
        body: formData
    })
    .then((resp) => {
        if (resp.redirected) {
            window.location.href = resp.url;
            return;
        }
        return resp.text().then(txt => {
            try {
                // Tenta converter para JSON
                const data = JSON.parse(txt);
                return data;
            } catch (e) {
                // Resposta não é JSON
                if (txt.trim() === '') return {success: true};
                return {success: false, message: txt};
            }
        });
    })
    .then((data) => {
        if (!data) return;

        if (data.success) {
            // Fecha modal e recarrega página
            hideModal(formId === 'formNovoProjeto' ? 'modalNovoProjeto' : 'modalEditarProjeto');
            setTimeout(() => location.reload(), 350);
        } else {
            alert(data.message || 'Erro ao salvar o projeto.');
            console.error('Erro ao salvar projeto:', data.message);
        }
    })
    .catch((err) => {
        console.error('Falha geral ao salvar projeto:', err);
        alert('Erro inesperado. Veja o console para detalhes.');
    });
}



    // Tarefas
    function listarTarefas(projetoId) {
    fetch('projetos', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action:'listar_subtarefas', projetoId })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            montarListaTarefas(data.subtarefas);
            updateProgressBar(data.subtarefas);
            
            // Aguardar um momento para o DOM ser atualizado antes de inicializar o sortable
            setTimeout(() => {
                initNestedSortables();
            }, 100);
        } else {
            alert(data.message || 'Erro ao listar tarefas.');
        }
    })
    .catch(e => console.error(e));
}

    function montarListaTarefas(subtarefas) {
        const cont = document.getElementById('listaTarefas');
        if (!subtarefas || subtarefas.length === 0) {
            cont.innerHTML = '<p>Nenhuma tarefa cadastrada.</p>';
            return;
        }
        let html = '<ul class="nested-list" id="rootSubtarefas">';
        html += gerarHtmlSubtarefas(subtarefas);
        html += '</ul>';
        cont.innerHTML = html;
    }

    function gerarHtmlSubtarefas(list) {
    let html = '';
    const perfilAcesso = <?php echo (int)$perfilAcesso; ?>;
    const usuarioLogadoId = <?php echo (int)$usuarioLogadoId; ?>;
    
    // Precisamos obter estas informações do projeto atual
    const projetoResponsavelId = parseInt(document.getElementById('projetoResponsavelId')?.value || '0');
    const projetoUsuariosEnvolvidos = (document.getElementById('projetoUsuariosEnvolvidos')?.value || '').split(',').map(id => parseInt(id.trim())).filter(id => id > 0);
    
    list.forEach(st => {
        const conclClass = (st.concluida == 1 ? 'tarefa-concluida' : '');
        const checked = (st.concluida == 1 ? 'checked' : '');
        const isKeyResult = (st.is_key_result == 1);
        const keyResultClass = isKeyResult ? 'key-result' : '';
        
        // Verifica se o projeto está concluído
        const projetoConcluido = PROJETO_STATUS_ATUAL === 'concluido';

        let editIcons = '';
        let keyResultButton = '';

        // Só mostra botões se o projeto NÃO estiver concluído
        if (!projetoConcluido) {
            // NOVA REGRA: Botão Key Result - para perfis 1, 2, 9, responsável pelo projeto e usuários envolvidos em tarefas raiz
            const isPerfil129 = [1, 2, 9].includes(perfilAcesso);
            const isResponsavel = (usuarioLogadoId === projetoResponsavelId);
            const isUsuarioEnvolvido = projetoUsuariosEnvolvidos.includes(usuarioLogadoId);
            
            if ((isPerfil129 || isResponsavel || isUsuarioEnvolvido) && !st.parent_subtarefa_id) {
                keyResultButton = `
                    <button type="button" class="btn btn-sm btn-outline-warning ms-1"
                            title="${isKeyResult ? 'Remover' : 'Marcar como'} Key Result"
                            onclick="toggleKeyResult(${st.id}, ${isKeyResult ? 0 : 1})">
                        <i class="fa fa-key"></i>
                    </button>
                `;
            }

            // Botões de editar e excluir - PARA TODOS OS USUÁRIOS (exceto Key Results)
            if (!isKeyResult) {
                editIcons = `
                    <button type="button" class="btn btn-sm btn-outline-primary ms-1"
                            title="Editar Tarefa"
                            onclick="abrirModalEditarTarefa(${st.id}, '${escapeHtml(st.nome_subtarefa)}', \`${escapeHtml(st.descricao || '')}\`)">
                        <i class="fa fa-edit"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-danger ms-1"
                            title="Excluir Tarefa"
                            onclick="excluirTarefa(${st.id})">
                        <i class="fa fa-trash"></i>
                    </button>
                `;
            }
        }

        // Botão adicionar subtarefa - disponível se projeto não estiver concluído
        const addSubtaskButton = !projetoConcluido ? `
            <button type="button" class="btn btn-sm btn-outline-secondary ms-2"
                    title="Adicionar Subtarefa"
                    onclick="abrirModalNovaMicro(${st.id}, this)">
                + Subtarefa
            </button>
        ` : '';

        let concluidoInfo = '';
        if (st.concluida == 1 && st.nomeConcluido) {
            concluidoInfo = `<small class="text-danger d-block">Concluído por: ${escapeHtml(st.nomeConcluido)} em ${formatDatePt(st.data_conclusao)}</small>`;
        }

        // Desabilita o checkbox se o projeto estiver concluído
        const checkboxDisabled = projetoConcluido ? 'disabled' : '';

        html += `
            <li data-id="${st.id}" data-parent="${st.parent_subtarefa_id || ''}" class="${keyResultClass}">
                <div class="task-item ${keyResultClass}">
                    <div class="d-flex align-items-center">
                        <span class="drag-handle me-2"><i class="fa fa-bars"></i></span>
                        <input type="checkbox" data-id="${st.id}" onchange="toggleTarefaStatus(this)" ${checked} ${checkboxDisabled} class="form-check-input me-2">
                        <span class="${conclClass} fw-semibold task-title">${escapeHtml(st.nome_subtarefa)}</span>
                        ${addSubtaskButton}
                        <div class="ms-auto">
                            ${keyResultButton}
                            ${editIcons}
                        </div>
                    </div>
                    <div class="small text-muted">
                        [${formatDatePt(st.data_cadastro)} - Criado por: ${escapeHtml(st.nomeCriador || 'N/D')}]
                    </div>
                    ${concluidoInfo}
                    ${st.descricao ? `<div class="task-description">${escapeHtml(st.descricao)}</div>` : ''}
                </div>
                <ul class="nested-list subtask-item">${st.children && st.children.length > 0 ? gerarHtmlSubtarefas(st.children) : ''}</ul>
            </li>`;
    });
    return html;
}

function initNestedSortables() {
    // Se o projeto estiver concluído, não permite reordenação
    if (PROJETO_STATUS_ATUAL === 'concluido') {
        // Remove a funcionalidade de arrastar das listas
        $('.nested-list').sortable("destroy");
        // Remove o cursor de mover dos handles
        $('.drag-handle').css('cursor', 'default');
        return;
    }
    
    // Destruir instâncias anteriores para evitar conflitos
    $('.nested-list').each(function() {
        if ($(this).hasClass('ui-sortable')) {
            $(this).sortable('destroy');
        }
    });
    
    // Inicializar sortable em todas as listas aninhadas
    $('.nested-list').sortable({
        connectWith: '.nested-list',
        handle: '.drag-handle',
        placeholder: 'ui-state-highlight',
        tolerance: 'pointer',
        items: '> li',
        forcePlaceholderSize: true,
        helper: 'clone',
        opacity: 0.6,
        revert: 200,
        
        receive: function(e, ui) {
            // Verifica se é um Key Result sendo movido para dentro de outra tarefa
            const isSubtask = ui.item.closest('ul.nested-list').parent().is('li');
            if (isSubtask && ui.item.hasClass('key-result')) {
                const taskName = ui.item.find('.task-title').text() || 'Tarefa sem nome';
                alert(`A tarefa "${taskName}" é um Key Result e não pode se tornar uma subtarefa.`);
                $(this).sortable('cancel');
                return false;
            }
        },
        
        update: function(e, ui) {
            // Só executa quando o item é solto (não durante o arrasto)
            if (this === ui.item.parent()[0]) {
                montarEstruturaHierarquica();
            }
        }
    }).disableSelection();
}

function montarEstruturaHierarquica() {
    const lista = [];
    
    function parseUl(ulElem, parentId) {
        $(ulElem).children('li').each(function(index, liElem) {
            const liId = $(liElem).data('id');
            if (liId) {
                lista.push({ 
                    id: liId, 
                    parentId: parentId || null, 
                    ordem: (index + 1) 
                });
                
                const ulFilho = $(liElem).children('ul.nested-list');
                if (ulFilho.length > 0) {
                    parseUl(ulFilho, liId);
                }
            }
        });
    }
    
    parseUl($('#rootSubtarefas'), null);

    // Evitar chamadas vazias
    if (lista.length === 0) return;

    const fd = new URLSearchParams();
    fd.set('action', 'reordenar_subtarefas');
    fd.set('lista', JSON.stringify(lista));
    
    fetch('projetos', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: fd
    })
    .then(r => r.json())
    .then(d => {
        if (!d.success) {
            alert(d.message || 'Erro ao reordenar subtarefas.');
            // Recarregar a lista em caso de erro
            const projetoId = document.getElementById('tarefasProjetoId').value;
            listarTarefas(projetoId);
        }
    })
    .catch(e => {
        console.error('Erro ao salvar ordem:', e);
        // Recarregar a lista em caso de erro
        const projetoId = document.getElementById('tarefasProjetoId').value;
        listarTarefas(projetoId);
    });
}

// Certifique-se de que o jQuery UI está carregado antes de inicializar
$(document).ready(function() {
    // Verificar se jQuery UI está disponível
    if (typeof $.fn.sortable === 'undefined') {
        console.error('jQuery UI Sortable não está carregado!');
        // Você pode adicionar um fallback aqui ou carregar o jQuery UI dinamicamente
    }
});

// Adicione esta verificação na função listarTarefas para garantir que o sortable seja inicializado:
// (Adicione no final da função listarTarefas, após chamar initNestedSortables())

function listarTarefas(projetoId) {
    fetch('projetos', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action:'listar_subtarefas', projetoId })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            montarListaTarefas(data.subtarefas);
            updateProgressBar(data.subtarefas);
            
            // Aguardar um momento para o DOM ser atualizado antes de inicializar o sortable
            setTimeout(() => {
                initNestedSortables();
            }, 100);
        } else {
            alert(data.message || 'Erro ao listar tarefas.');
        }
    })
    .catch(e => console.error(e));
}

    function updateProgressBar(tasks) {
        const { total, completed } = countTasks(tasks);
        const perc = total > 0 ? Math.round((completed / total) * 100) : 0;
        const pb = document.getElementById('projectProgressBar');
        pb.style.width = perc + '%';
        pb.setAttribute('aria-valuenow', perc);
        pb.textContent = perc + '%';
    }

    function abrirModalNovaMicro(parentId, iconElem) {
        const already = iconElem.classList.contains('folder-icon-highlight');
        document.querySelectorAll('.folder-icon-highlight').forEach(el => el.classList.remove('folder-icon-highlight'));
        if (!already) {
            iconElem.classList.add('folder-icon-highlight');
            document.getElementById('novaTarefaParentId').value = parentId;
        } else {
            document.getElementById('novaTarefaParentId').value = '';
        }
        document.getElementById('nomeTarefa').focus();
    }

    function toggleTarefaStatus(checkbox) {
    // Verifica se o projeto está concluído
    if (PROJETO_STATUS_ATUAL === 'concluido') {
        alert('Não é possível alterar tarefas em projetos concluídos.');
        checkbox.checked = !checkbox.checked; // Reverte o estado
        return;
    }
    
    const subId = checkbox.dataset.id;
    const st = checkbox.checked ? 1 : 0;
    const fd = new URLSearchParams();
    fd.set('action', 'toggle_subtarefa');
    fd.set('subtarefaId', subId);
    fd.set('concluida', st);
    fetch('projetos', { method:'POST', body: fd })
    .then(r => r.json())
    .then(d => {
        if (!d.success) {
            alert(d.message || 'Erro ao atualizar tarefa.');
            checkbox.checked = !st;
        } else {
            listarTarefas(document.getElementById('tarefasProjetoId').value);
        }
    })
    .catch(e => {
        console.error(e);
        checkbox.checked = !st;
    });
}

    document.getElementById('formNovaTarefa').addEventListener('submit', function(e) {
        e.preventDefault();
        const pid = document.getElementById('tarefasProjetoId').value;
        const par = document.getElementById('novaTarefaParentId').value;
        const nome = document.getElementById('nomeTarefa').value.trim();
        const desc = document.getElementById('descricaoTarefa').value;
        if (!pid || !nome) {
            alert('Preencha o nome da tarefa!');
            return;
        }
        const fd = new URLSearchParams();
        fd.set('action','criar_subtarefa');
        fd.set('projetoId', pid);
        fd.set('parent_subtarefa_id', par);
        fd.set('nome_subtarefa', nome);
        fd.set('descricao', desc);

        fetch('projetos', { method:'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                document.querySelectorAll('.folder-icon-highlight').forEach(i => i.classList.remove('folder-icon-highlight'));
                document.getElementById('nomeTarefa').value = '';
                document.getElementById('descricaoTarefa').value = '';
                document.getElementById('novaTarefaParentId').value = '';
                listarTarefas(pid);
            } else {
                alert(d.message || 'Erro ao criar tarefa.');
            }
        })
        .catch(e => console.error(e));
    });

    function abrirModalEditarTarefa(id, nome, desc) {
        document.getElementById('editarTarefaId').value = id;
        document.getElementById('editarNomeTarefa').value = nome;
        document.getElementById('editarDescricaoTarefa').value = desc;
        showModal('modalEditarTarefa');
    }
    function salvarEdicaoTarefa() {
        const id = document.getElementById('editarTarefaId').value;
        const novoNome = document.getElementById('editarNomeTarefa').value.trim();
        const novaDesc = document.getElementById('editarDescricaoTarefa').value;
        if (!novoNome) {
            alert('Preencha o nome da tarefa!');
            return;
        }
        const fd = new URLSearchParams();
        fd.set('action','editar_subtarefa');
        fd.set('subtarefaId', id);
        fd.set('nome_subtarefa', novoNome);
        fd.set('descricao', novaDesc);
        fetch('projetos', { method:'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                alert(d.message);
                hideModal('modalEditarTarefa');
                listarTarefas(document.getElementById('tarefasProjetoId').value);
            } else {
                alert(d.message || 'Erro ao editar tarefa.');
            }
        })
        .catch(e => console.error(e));
    }
    

    function excluirTarefa(id) {
    // Removido o confirm() para não pedir confirmação
    const fd = new URLSearchParams();
    fd.set('action','excluir_subtarefa');
    fd.set('subtarefaId', id);
    fetch('projetos', { method:'POST', body: fd })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            // Removido o alert() para não mostrar mensagem de sucesso
            listarTarefas(document.getElementById('tarefasProjetoId').value);
        } else {
            // Mantido apenas o alerta para erros
            alert(d.message || 'Erro ao excluir tarefa.');
        }
    })
    .catch(e => console.error(e));
}

    function toggleKeyResult(subtarefaId, newState) {
        if (!confirm(`Deseja ${newState === 0 ? 'remover de' : 'marcar como'} Key Result esta tarefa?`)) return;
        const fd = new URLSearchParams();
        fd.set('action','toggle_key_result');
        fd.set('subtarefaId', subtarefaId);
        fd.set('is_key_result', newState);

        fetch('projetos', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: fd
        })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                alert(d.message);
                listarTarefas(document.getElementById('tarefasProjetoId').value);
            } else {
                alert(d.message || 'Erro ao atualizar Key Result.');
            }
        })
        .catch(e => {
            console.error(e);
            alert('Erro ao processar a solicitação.');
        });
    }

    // Anexos
    function listarAnexos(projetoId) {
        const fd = new URLSearchParams();
        fd.set('action','listar_anexos');
        fd.set('projetoId', projetoId);
        fetch('projetos', { method:'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                montarListaAnexos(d.anexos);
            } else {
                alert(d.message || 'Erro ao listar anexos.');
            }
        })
        .catch(e => console.error(e));
    }
    function montarListaAnexos(anexos) {
    const c = document.getElementById('listaAnexos');
    if (!anexos || anexos.length === 0) {
        c.innerHTML = '<p>Nenhum anexo adicionado.</p>';
        return;
    }
    
    // Verifica se o projeto está concluído
    const projetoConcluido = PROJETO_STATUS_ATUAL === 'concluido';
    
    let html = '<ul class="list-unstyled">';
    anexos.forEach(a => {
        let icons = '';
        // Só mostra ícones se o projeto NÃO estiver concluído
        if (!projetoConcluido && parseInt(a.usuario_id) === <?php echo (int)$usuarioLogadoId; ?>) {
            icons = `
                <i class="fa fa-pencil icon-btn ms-2" title="Renomear"
                   onclick="renomearAnexo(${a.id}, '${escapeHtml(a.nome_arquivo)}')"></i>
                <i class="fa fa-trash icon-btn ms-2" title="Excluir"
                   onclick="excluirAnexo(${a.id})"></i>
            `;
        }
        html += `
            <li class="mb-2">
                <a href="${a.caminho_arquivo}" target="_blank" class="text-decoration-underline text-info fw-bold">
                    ${escapeHtml(a.nome_arquivo)}
                </a>
                <small class="d-block text-muted">
                    [Enviado por: ${escapeHtml(a.nomeUsuario || 'N/D')} em ${formatDatePt(a.data_upload)}]
                </small>
                ${icons}
            </li>
        `;
    });
    html += '</ul>';
    c.innerHTML = html;
}

    document.getElementById('formUploadAnexo').addEventListener('submit', function(e) {
        e.preventDefault();
        const pid = document.getElementById('anexosProjetoId').value;
        if (!pid) { alert('Projeto inválido para upload.'); return; }
        const fileInput = document.getElementById('arquivoAnexo');
        if (fileInput.files.length === 0) {
            alert('Selecione um arquivo!');
            return;
        }
        const formData = new FormData();
        formData.append('action','upload_anexo');
        formData.append('projetoId', pid);
        formData.append('arquivo', fileInput.files[0]);

        fetch('projetos', { method:'POST', body: formData })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                alert(d.message);
                listarAnexos(pid);
                fileInput.value = '';
            } else {
                alert(d.message || 'Erro ao enviar anexo.');
            }
        })
        .catch(e => console.error(e));
    });

    function renomearAnexo(anexoId, nomeAtual) {
        const novoNome = prompt("Novo nome do Anexo:", nomeAtual);
        if (novoNome === null) return;
        const fd = new URLSearchParams();
        fd.set('action','editar_anexos');
        fd.set('anexoId', anexoId);
        fd.set('novo_nome', novoNome);
        fetch('projetos', { method:'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                alert(d.message);
                listarAnexos(document.getElementById('anexosProjetoId').value);
            } else {
                alert(d.message || 'Erro ao renomear anexo.');
            }
        })
        .catch(e => console.error(e));
    }
    function excluirAnexo(anexoId) {
        if (!confirm("Deseja excluir este anexo?")) return;
        const fd = new URLSearchParams();
        fd.set('action','excluir_anexo');
        fd.set('anexoId', anexoId);
        fetch('projetos', { method:'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                alert(d.message);
                listarAnexos(document.getElementById('anexosProjetoId').value);
            } else {
                alert(d.message || 'Erro ao excluir anexo.');
            }
        })
        .catch(e => console.error(e));
    }

    // Atividades
    function listarAtividades(projetoId) {
        const fd = new URLSearchParams();
        fd.set('action','listar_atividades');
        fd.set('projetoId', projetoId);
        fetch('projetos', { method:'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                montarListaAtividades(d.atividades);
            } else {
                alert(d.message || 'Erro ao listar comentários.');
            }
        })
        .catch(e => console.error(e));
    }

    function montarListaAtividades(atividades) {
    const container = document.getElementById('listaAtividades');
    if (!atividades || atividades.length === 0) {
        container.innerHTML = '<p class="text-muted">Nenhum comentário ainda.</p>';
        return;
    }
    
    // Verifica se o projeto está concluído
    const projetoConcluido = PROJETO_STATUS_ATUAL === 'concluido';
    
    let html = '';
    atividades.forEach(at => {
        let editIcons = '';
        // Só mostra botão excluir se o projeto NÃO estiver concluído
        if (!projetoConcluido && parseInt(at.usuario_id) === <?php echo (int)$usuarioLogadoId; ?>) {
            editIcons = `
                <button type="button" class="btn btn-sm btn-outline-danger"
                        title="Excluir"
                        onclick="excluirAtividade(${at.id})">
                    <i class="fa fa-trash"></i>
                </button>
            `;
        }
        html += `
            <div class="activity-item">
                <div class="activity-header">
                    <span class="activity-author">${escapeHtml(at.nomeUsuario || 'N/D')}</span>
                    <div class="activity-actions">
                        ${editIcons}
                    </div>
                </div>
                <div class="activity-meta">Comentado em ${formatDatePt(at.data_hora)}</div>
                <div class="activity-content">${escapeHtml(at.comentario)}</div>
            </div>
        `;
    });
    container.innerHTML = html;
}

    document.getElementById('formNovaAtividade').addEventListener('submit', function(e) {
        e.preventDefault();
        const projetoId = document.getElementById('atividadesProjetoId').value;
        const comentario = document.getElementById('textoAtividade').value;
        if (!projetoId || !comentario.trim()) {
            alert('Escreva algo antes de comentar!');
            return;
        }
        const fd = new URLSearchParams();
        fd.set('action','adicionar_atividade');
        fd.set('projetoId', projetoId);
        fd.set('comentario', comentario);

        fetch('projetos', { method:'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                listarAtividades(projetoId);
                document.getElementById('textoAtividade').value = '';
            } else {
                alert(d.message || 'Erro ao adicionar comentário.');
            }
        })
        .catch(e => console.error(e));
    });

    function excluirAtividade(id) {
        if (!confirm("Deseja excluir este comentário?")) return;
        const fd = new URLSearchParams();
        fd.set('action','excluir_atividade');
        fd.set('atividadeId', id);
        fetch('projetos', { method:'POST', body:fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                listarAtividades(document.getElementById('atividadesProjetoId').value);
            } else {
                alert(d.message || 'Erro ao excluir comentário.');
            }
        })
        .catch(e => console.error(e));
    }

    function limitRange(el) {
    let val = parseFloat(el.value) || 0;
    if (val < 0) val = 0;
    if (val > 5) val = 5;
    // Atualiza valor
    el.value = val;
}


// Função para mudar de página
function mudarPagina(pagina) {
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('pagina', pagina);
    window.location.href = 'projetos?' + urlParams.toString();
}

// Event listener para o select de itens por página
document.getElementById('itemsPerPage').addEventListener('change', function() {
    const itemsPerPage = this.value;
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('items_per_page', itemsPerPage);
    urlParams.set('pagina', 1); // Volta para a primeira página ao mudar o número de itens
    window.location.href = 'projetos?' + urlParams.toString();
});

// Adicionar CSS para estilizar o paginador
document.addEventListener('DOMContentLoaded', function() {
    const style = document.createElement('style');
    style.textContent = `
        .pagination-container {
            margin-top: 20px;
            margin-bottom: 20px;
        }
        .pagination .page-link {
            background-color: #2e3240;
            border-color: #3c4147;
            color: #eaeaea;
            transition: all 0.3s ease;
        }
        .pagination .page-item.active .page-link {
            background-color: #007bff;
            border-color: #007bff;
            color: #fff;
            font-weight: bold;
        }
        .pagination .page-item.disabled .page-link {
            background-color: #23272a;
            border-color: #3c4147;
            color: #666;
        }
        .pagination .page-link:hover:not(.disabled) {
            background-color: #353943;
            border-color: #007bff;
            color: #fff;
        }
        .items-per-page-selector {
            color: #eaeaea;
        }
        .items-per-page-selector .form-select {
            background-color: #2e3240;
            border-color: #3c4147;
            color: #eaeaea;
            transition: all 0.3s ease;
        }
        .items-per-page-selector .form-select:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.25rem rgba(0, 123, 255, 0.25);
        }
    `;
    document.head.appendChild(style);

    // Mostrando informações de registros encontrados
    const tableWrapper = document.querySelector('.table-responsive');
    if (tableWrapper) {
        const infoElement = document.createElement('div');
        infoElement.className = 'text-muted mb-2';
        
    }
});

// Após o carregamento da página, se houver query string, substituímos o URL sem parâmetros
window.addEventListener('load', function(){
    if(window.location.search){
      history.replaceState(null, "", window.location.pathname);
    }
  });

  // Opcional: Se desejar que o formulário submeta automaticamente ao alterar os filtros
  ['filtroAno','filtroSetor','filtroPrazo'].forEach(id => {
    const el = document.getElementById(id);
    if (el) {
      el.addEventListener('change', function(){
        document.getElementById('filtroForm').submit();
      });
    }
  });


  // Função para atualizar a quantidade de BVP quando o setor é alterado
function atualizarQuantidadeBVP(setorId) {
    if (!setorId) {
        document.getElementById('quantidadeOKR').value = '0';
        return;
    }
    
    // Busca o option selecionado para pegar o atributo data-qtd
    const option = document.querySelector(`#setorOKR option[value="${setorId}"]`);
    if (option) {
        const qtd = option.getAttribute('data-qtd') || '0';
        document.getElementById('quantidadeOKR').value = qtd;
    } else {
        document.getElementById('quantidadeOKR').value = '0';
    }
}

// Executa quando o modal é exibido para garantir o valor correto
document.addEventListener('DOMContentLoaded', function() {
    const btnModalBVP = document.querySelector('.btn[onclick="showModal(\'modalProjetosDisponiveis\')"]');
    if (btnModalBVP) {
        btnModalBVP.addEventListener('click', function() {
            setTimeout(() => {
                const setorOKR = document.getElementById('setorOKR');
                if (setorOKR && setorOKR.value) {
                    atualizarQuantidadeBVP(setorOKR.value);
                }
            }, 100);
        });
    }
});

</script>

<script>
// Função para trocar as abas do modal
function showTab(tabId) {
    var tabTrigger = document.querySelector('[data-bs-target="#' + tabId + '"]');
    if(tabTrigger){
        var tab = new bootstrap.Tab(tabTrigger);
        tab.show();
    }
}

// Avança de Dados Básicos para Complementares
document.addEventListener('DOMContentLoaded', function () {
    var btnNextToComplementares = document.getElementById('btnNextToComplementares');
    if(btnNextToComplementares){
        btnNextToComplementares.onclick = function() {
            showTab('novo-complementares');
        };
    }

    // Avança de Complementares para Avaliações
    var btnNextToAvaliacoes = document.getElementById('btnNextToAvaliacoes');
    if(btnNextToAvaliacoes){
        btnNextToAvaliacoes.onclick = function() {
            showTab('novo-avaliacoes');
        };
    }
});

// Limita os valores de 0 a 5
function limitRange(input) {
    if (input.value < 0) input.value = 0;
    if (input.value > 5) input.value = 5;
}

// Supondo que proj.Status, proj.Id, e uma variável global com o perfil
let footer = document.getElementById('modalDetalhesFooter');

// Sempre reseta o footer para só o botão fechar
footer.innerHTML = `
    <button type="button" class="btn btn-secondary" onclick="hideModal('modalDetalhesProjeto')">
        <i class="fa fa-times me-2"></i> Fechar
    </button>
`;

// Permissões: Admin(1), Gestor(2), Subdiretor(9)
if (["1", "2", "9"].includes(String(window.PERFIL_USUARIO)) && proj.Status === 'backlog') {
    // Botão para autorizar projeto
    footer.innerHTML += `
        <button type="button" class="btn btn-success ms-2" id="btnAutorizarProjeto">
            <i class="fa fa-thumbs-up me-2"></i> Autorizar Projeto
        </button>
    `;
    // Adiciona listener após inserir no DOM
    setTimeout(function() {
        document.getElementById('btnAutorizarProjeto').onclick = function() {
            autorizarProjeto(proj.Id);
        };
    }, 50);
}

function autorizarProjeto(projetoId) {
    if (!confirm("Tem certeza que deseja autorizar este projeto?")) return;
    fetch('projetos', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: new URLSearchParams({
            acao: 'autorizar',
            projeto_id: projetoId
        })
    })
    .then(r => r.json())
    .then(resp => {
        if (resp.success) {
            location.reload();
        } else {
            alert(resp.message || 'Erro ao autorizar.');
        }
    })
    .catch(() => alert('Erro de conexão ao autorizar projeto!'));
}

function voltarBacklog(projetoId) {
    if (!confirm("Tem certeza que deseja mover este projeto para o backlog?\nIsso pode pausar o fluxo atual.")) return;
    fetch('projetos', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: new URLSearchParams({
            acao: 'voltar_backlog',
            projeto_id: projetoId
        })
    })
    .then(r => r.json())
    .then(resp => {
        if (resp.success) {
            
            location.reload();
        } else {
            alert(resp.message || 'Erro ao mover para o backlog.');
        }
    })
    .catch(() => alert('Erro de conexão ao mover para o backlog!'));
}

</script>

<script>
// Função para carregar e exibir arquivos do orçamento macro
function carregarArquivosOrcamentoEdicao(projetoId) {
    console.log('Carregando arquivos para projeto:', projetoId); // Debug
    
    fetch('projetos', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ 
            action: 'listar_arquivos_orcamento', 
            projetoId: projetoId 
        })
    })
    .then(r => r.json())
    .then(data => {
        console.log('Resposta dos arquivos:', data); // Debug
        if (data.success) {
            mostrarArquivosOrcamentoEdicao(data.arquivos);
        } else {
            console.error('Erro ao carregar arquivos:', data.message);
        }
    })
    .catch(e => {
        console.error('Erro ao carregar arquivos:', e);
        mostrarArquivosOrcamentoEdicao([]); // Mostra vazio em caso de erro
    });
}

// Função para exibir os arquivos na interface
function mostrarArquivosOrcamentoEdicao(arquivos) {
    const container = document.getElementById('arquivosOrcamentoExistentes');
    if (!container) {
        console.error('Container arquivosOrcamentoExistentes não encontrado!');
        return;
    }
    
    console.log('Exibindo arquivos:', arquivos); // Debug
    
    if (!arquivos || arquivos.length === 0) {
        container.innerHTML = `
            <div style="text-align: center; color: #a1aab2; font-style: italic; padding: 20px; background: #2e3240; border-radius: 8px; border: 2px dashed #52555d;">
                <i class="fa fa-folder-open fa-2x mb-2"></i>
                <p class="mb-0">Nenhum arquivo anexado ao orçamento macro</p>
            </div>
        `;
        return;
    }

    let html = '';
    arquivos.forEach((arquivo, index) => {
        const icone = getFileIcon(arquivo.arquivo_original);
        html += `
            <div style="background: #2e3240; border: 1px solid #52555d; border-radius: 8px; padding: 15px; margin-bottom: 10px; display: flex; align-items: center; justify-content: space-between;">
                <div style="display: flex; align-items: center; flex-grow: 1;">
                    <div style="font-size: 2rem; margin-right: 15px; color: #007bff;">
                        <i class="fa ${icone}"></i>
                    </div>
                    <div>
                        <h6 style="margin: 0; color: #fff; font-weight: 600;">${escapeHtml(arquivo.arquivo_original)}</h6>
                        <small style="color: #a1aab2; font-size: 0.85rem;">Enviado em ${formatDatePt(arquivo.data_upload)}</small>
                    </div>
                </div>
                <div style="display: flex; gap: 8px;">
                    <a href="uploads/${arquivo.arquivo_nome}" 
                       target="_blank" 
                       class="btn btn-sm btn-outline-info"
                       title="Visualizar/Baixar">
                        <i class="fa fa-eye"></i> Ver
                    </a>
                    <button type="button" 
                            class="btn btn-sm btn-outline-danger"
                            title="Remover arquivo"
                            onclick="removerArquivoOrcamento('${arquivo.arquivo_nome}', '${arquivo.arquivo_original}')">
                        <i class="fa fa-trash"></i>
                    </button>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

// Função para obter ícone baseado na extensão do arquivo
function getFileIcon(fileName) {
    const extension = fileName.split('.').pop().toLowerCase();
    const iconMap = {
        'pdf': 'fa-file-pdf-o',
        'xlsx': 'fa-file-excel-o',
        'xls': 'fa-file-excel-o',
        'docx': 'fa-file-word-o',
        'doc': 'fa-file-word-o',
        'pptx': 'fa-file-powerpoint-o',
        'ppt': 'fa-file-powerpoint-o',
        'zip': 'fa-file-archive-o',
        'rar': 'fa-file-archive-o',
        'jpg': 'fa-file-image-o',
        'jpeg': 'fa-file-image-o',
        'png': 'fa-file-image-o',
        'gif': 'fa-file-image-o'
    };
    return iconMap[extension] || 'fa-file-o';
}

// Função para remover arquivo
function removerArquivoOrcamento(nomeArquivo, nomeOriginal) {
    if (!confirm(`Deseja realmente remover o arquivo "${nomeOriginal}"?`)) {
        return;
    }

    const projetoId = document.getElementById('editarProjetoId').value;
    
    fetch('projetos', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ 
            action: 'remover_arquivo_orcamento', 
            projetoId: projetoId,
            nomeArquivo: nomeArquivo 
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('Arquivo removido com sucesso!');
            // Recarrega a lista
            carregarArquivosOrcamentoEdicao(projetoId);
        } else {
            alert(data.message || 'Erro ao remover arquivo.');
        }
    })
    .catch(e => {
        console.error('Erro ao remover arquivo:', e);
        alert('Erro ao remover arquivo.');
    });
}

// Teste manual - você pode chamar esta função no console do navegador para testar
function testarCarregamentoArquivos() {
    const projetoId = document.getElementById('editarProjetoId').value;
    if (projetoId) {
        carregarArquivosOrcamentoEdicao(projetoId);
    } else {
        console.log('Nenhum projeto selecionado');
    }
}

// Função para formatar valor monetário
function formatMoney(value) {
    if (!value) return '0,00';
    return parseFloat(value).toLocaleString('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

// Função para carregar resumo dos arquivos na visão geral
function carregarArquivosOrcamentoVisaoGeral(projetoId) {
    fetch('projetos', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'listar_arquivos_orcamento', projetoId })
    })
    .then(r => r.json())
    .then(data => {
        const container = document.getElementById('arquivosOrcamentoResumo');
        if (!container) return;
        
        if (data.success && data.arquivos && data.arquivos.length > 0) {
            let html = '';
            data.arquivos.forEach((arquivo, index) => {
                if (index > 0) html += ', ';
                html += `<a href="uploads/${arquivo.arquivo_nome}" target="_blank" class="text-info text-decoration-underline">${escapeHtml(arquivo.arquivo_original)}</a>`;
            });
            container.innerHTML = html;
        } else {
            container.innerHTML = '<span class="text-muted">Nenhum arquivo anexado</span>';
        }
    })
    .catch(e => {
        const container = document.getElementById('arquivosOrcamentoResumo');
        if (container) {
            container.innerHTML = '<span class="text-danger">Erro ao carregar</span>';
        }
    });
}

// Função para carregar orçamento detalhado na aba específica
function carregarOrcamentoMacroDetalhado(projetoId) {
    fetch('projetos', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'get_projeto_details', projetoId })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const proj = data.projeto;
            const container = document.getElementById('orcamentoDetails');
            if (!container) return;
            
            let html = `
                <h3 class="section-title">
                    <i class="fa fa-calculator me-2"></i>Orçamento Macro
                </h3>
                
                <div class="section">
                    <div class="detail-item">
                        <i class="fa fa-money"></i>
                        <span>Valor Estimado: 
                            <strong>${proj.valor_estimado ? 'R$ ' + formatMoney(proj.valor_estimado) : 'Não informado'}</strong>
                        </span>
                    </div>
                    
                    ${proj.orcamento_macro ? `
                        <div class="detail-item">
                            <i class="fa fa-file-text"></i>
                            <span>Descrição:</span>
                        </div>
                        <div style="background: #2e3240; padding: 15px; border-radius: 8px; margin-top: 10px;">
                            ${escapeHtml(proj.orcamento_macro)}
                        </div>
                    ` : `
                        <div class="detail-item">
                            <i class="fa fa-file-text"></i>
                            <span>Descrição: <strong class="text-muted">Não informada</strong></span>
                        </div>
                    `}
                </div>
                
                <div class="section mt-4">
                    <h4 class="section-title">Arquivos Anexados</h4>
                    <div id="arquivosOrcamentoDetalhados">Carregando arquivos...</div>
                </div>
            `;
            
            container.innerHTML = html;
            
            // Carrega arquivos detalhados
            carregarArquivosOrcamentoDetalhados(projetoId);
        }
    })
    .catch(e => console.error('Erro ao carregar orçamento detalhado:', e));
}

// Função para exibir arquivos na aba orçamento detalhada
function carregarArquivosOrcamentoDetalhados(projetoId) {
    fetch('projetos', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'listar_arquivos_orcamento', projetoId })
    })
    .then(r => r.json())
    .then(data => {
        const container = document.getElementById('arquivosOrcamentoDetalhados');
        if (!container) return;
        
        if (data.success && data.arquivos && data.arquivos.length > 0) {
            let html = '';
            data.arquivos.forEach(arquivo => {
                const icone = getFileIcon(arquivo.arquivo_original);
                html += `
                    <div style="background: #2e3240; border: 1px solid #52555d; border-radius: 8px; padding: 15px; margin-bottom: 10px; display: flex; align-items: center; justify-content: space-between;">
                        <div style="display: flex; align-items: center; flex-grow: 1;">
                            <div style="font-size: 2rem; margin-right: 15px; color: #007bff;">
                                <i class="fa ${icone}"></i>
                            </div>
                            <div>
                                <h6 style="margin: 0; color: #fff; font-weight: 600;">${escapeHtml(arquivo.arquivo_original)}</h6>
                                <small style="color: #a1aab2; font-size: 0.85rem;">Enviado em ${formatDatePt(arquivo.data_upload)}</small>
                            </div>
                        </div>
                        <div>
                            <a href="uploads/${arquivo.arquivo_nome}" 
                               target="_blank" 
                               class="btn btn-sm btn-outline-info"
                               title="Visualizar/Baixar">
                                <i class="fa fa-eye"></i> Visualizar
                            </a>
                        </div>
                    </div>
                `;
            });
            container.innerHTML = html;
        } else {
            container.innerHTML = `
                <div style="text-align: center; color: #a1aab2; font-style: italic; padding: 20px; background: #2e3240; border-radius: 8px; border: 2px dashed #52555d;">
                    <i class="fa fa-folder-open fa-2x mb-2"></i>
                    <p class="mb-0">Nenhum arquivo anexado ao orçamento macro</p>
                </div>
            `;
        }
    })
    .catch(e => {
        const container = document.getElementById('arquivosOrcamentoDetalhados');
        if (container) {
            container.innerHTML = '<div class="text-danger">Erro ao carregar arquivos</div>';
        }
    });
}

// Função para aplicar máscara de moeda em Real (R$ 0.000,00)
function aplicarMascaraMoeda() {
  const camposMoeda = document.querySelectorAll('.money-mask');
  
  camposMoeda.forEach(function(campo) {
    // Formata o valor inicial se existir
    if (campo.value && !campo.value.includes('R$')) {
      campo.value = formatarMoeda(campo.value);
    }
    
    // Quando o campo recebe foco
    campo.addEventListener('focus', function(e) {
      // Se estiver vazio, inicializa com R$
      if (!this.value) {
        this.value = 'R$ ';
      } 
      // Posiciona o cursor no final
      const length = this.value.length;
      this.setSelectionRange(length, length);
    });
    
    // Durante a digitação
    campo.addEventListener('input', function(e) {
      let valor = this.value;
      
      // Mantém o "R$ " no início
      if (!valor.startsWith('R$ ')) {
        valor = 'R$ ' + valor.replace('R$', '');
      }
      
      // Remove tudo que não é dígito, exceto R$ no início
      let numerico = valor.replace('R$ ', '').replace(/\D/g, '');
      
      if (numerico.length > 0) {
        // Converte para decimal (considerando os centavos)
        numerico = (parseInt(numerico) / 100).toFixed(2);
        
        // Formata para o padrão brasileiro
        const formatado = numerico.replace('.', ',');
        
        // Adiciona separadores de milhar
        let partes = formatado.split(',');
        partes[0] = partes[0].replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        
        this.value = 'R$ ' + partes.join(',');
      } else {
        this.value = 'R$ ';
      }
    });
    
    // Quando o campo perde o foco
    campo.addEventListener('blur', function(e) {
      if (this.value === 'R$ ' || this.value === 'R$') {
        this.value = '';
      } else if (this.value) {
        // Garantir a formatação correta ao perder o foco
        let numerico = extrairNumero(this.value);
        this.value = formatarMoeda(numerico);
      }
    });
  });
}

// Função para formatar um valor como moeda brasileira
function formatarMoeda(valor) {
  // Se for string, converte para número
  let numero = typeof valor === 'string' ? extrairNumero(valor) : valor;
  
  // Se não for um número válido, retorna vazio
  if (isNaN(numero) || numero === 0) return '';
  
  // Formata para Real brasileiro
  return 'R$ ' + numero.toLocaleString('pt-BR', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  });
}

// Extrai apenas o valor numérico de uma string formatada
function extrairNumero(texto) {
  // Remove R$ e outros caracteres não numéricos, preservando o ponto decimal
  let limpo = texto.replace(/R\$\s?/g, '').replace(/\./g, '').replace(',', '.');
  return parseFloat(limpo);
}

// Inicializa a máscara quando o DOM estiver carregado
document.addEventListener('DOMContentLoaded', function() {
  aplicarMascaraMoeda();
});

// Modifica a função submitProjectForm para tratar valores monetários
const originalSubmitProjectForm = window.submitProjectForm || function(){};
window.submitProjectForm = function(formId, action) {
  const form = document.getElementById(formId);
  
  // Processa os campos de moeda antes do envio
  const camposMoeda = form.querySelectorAll('.money-mask');
  camposMoeda.forEach(function(campo) {
    if (campo.value) {
      // Extrai o valor numérico (sem formatação)
      const valorNumerico = extrairNumero(campo.value);
      
      // Atualiza o valor do campo para envio (mantém no formato que o PHP espera)
      campo.value = valorNumerico;
    }
  });
  
  // Chama a função original
  return originalSubmitProjectForm(formId, action);
};

// Aplicar a máscara sempre que um modal for aberto
document.addEventListener('shown.bs.modal', function(event) {
  setTimeout(aplicarMascaraMoeda, 100); // Pequeno delay para garantir que os elementos estão visíveis
});

// Se estiver usando jQuery para os modais
$(document).ready(function() {
  $('#modalNovoProjeto, #modalEditarProjeto').on('shown.bs.modal', function() {
    setTimeout(aplicarMascaraMoeda, 100);
  });
});
</script>

</body>
</html>