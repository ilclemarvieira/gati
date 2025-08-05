<?php
// okr

// -------------------------------------------------
// Configurações iniciais: timezone, sessão, cookie
// -------------------------------------------------
date_default_timezone_set('America/Sao_Paulo');
ini_set('session.gc_maxlifetime', 21600);
session_start();
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

// -------------------------------------------------
// Verificação de login
// -------------------------------------------------
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

// -------------------------------------------------
// Inclusão da conexão com o banco de dados
// -------------------------------------------------
include 'db.php';

// -------------------------------------------------
// Dados do usuário logado
// -------------------------------------------------
$perfilAcesso    = $_SESSION['PerfilAcesso'] ?? null;
$usuarioLogadoId = $_SESSION['usuario_id'] ?? null;

// -------------------------------------------------
// Obter o setor do usuário logado
// -------------------------------------------------
$usuarioSetorId = null;
if ($usuarioLogadoId) {
    $stmtSetor = $pdo->prepare("SELECT SetorId FROM usuarios WHERE Id = :id");
    $stmtSetor->execute([':id' => $usuarioLogadoId]);
    $usuarioSetorId = $stmtSetor->fetchColumn();
}

// -------------------------------------------------
// Função para verificação de acesso
// -------------------------------------------------
function verificarPermissao($perfisPermitidos) {
    global $perfilAcesso;
    if (!in_array($perfilAcesso, $perfisPermitidos)) {
        $paginaRedirecionamento = $_SERVER['HTTP_REFERER'] ?? 'index.php';
        header('Location: ' . $paginaRedirecionamento);
        exit;
    }
}
verificarPermissao([1,2,4,5,7,8,9]);

// -------------------------------------------------
// Carrega dados auxiliares para os selects
// -------------------------------------------------
$usuarios      = $pdo->query("SELECT Id, Nome FROM usuarios ORDER BY Nome ASC")->fetchAll(PDO::FETCH_ASSOC);

// Perfis de acesso completo podem ver todos os setores
$isAdminOrDirector = in_array($perfilAcesso, [1, 7, 9]); // Admin, diretor, sub-diretor

// Carrega setores de acordo com o nível de acesso
if ($isAdminOrDirector) {
    // Administrador, diretor e subdiretor veem todos os setores
    $setores = $pdo->query("SELECT id, nome_do_setor FROM setores ORDER BY nome_do_setor ASC")->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Outros perfis só veem seu próprio setor
    if ($usuarioSetorId) {
        $stmtSetores = $pdo->prepare("SELECT id, nome_do_setor FROM setores WHERE id = :setorId ORDER BY nome_do_setor ASC");
        $stmtSetores->execute([':setorId' => $usuarioSetorId]);
        $setores = $stmtSetores->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $setores = [];
    }
}

$listaProjetos = $pdo->query("SELECT Id, NomeProjeto FROM projetos ORDER BY NomeProjeto ASC")->fetchAll(PDO::FETCH_ASSOC);

$anosQuery     = $pdo->query("SELECT DISTINCT YEAR(DataCriacao) AS ano FROM projetos ORDER BY ano DESC");
$anosDisponiveis = $anosQuery->fetchAll(PDO::FETCH_ASSOC);

// -------------------------------------------------
// Filtros (com valores padrão e regras de perfil)
$anoFiltro    = isset($_GET['ano'])    && $_GET['ano']    !== '' ? $_GET['ano']    : date('Y');
$setorFiltro  = $_GET['setor']        ?? '';
$prazoFiltro  = $_GET['prazo']        ?? '';
$statusFiltro = $_GET['status']       ?? '';

// Para perfis restritos (Gestor, Inova, BI, DTic), forçar seu próprio setor como filtro
$perfilComSetorRestrito = in_array($perfilAcesso, [2, 4, 5, 8]); // Gestor, Inova, BI, DTic

// Se for um perfil administrativo, deixar o filtro aberto
// Caso contrário, forçar o setor do usuário
if (!$isAdminOrDirector && empty($setorFiltro) && $usuarioSetorId) {
    $setorFiltro = $usuarioSetorId;
}

$prazoFiltro = $_GET['prazo'] ?? '';

// -------------------------------------------------
// Paginação
// -------------------------------------------------
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) {
    $page = 1;
}
$validLimits = [15, 30, 60, 100];
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 15;
if (!in_array($limit, $validLimits)) {
    $limit = 15;
}
$offset = ($page - 1) * $limit;

// -------------------------------------------------
// Função para obter contadores de OKR (Total, Finalizados, Bloqueados)
// de acordo com os filtros
// -------------------------------------------------
function getOKRCount($pdo, $status, $anoFiltro, $setorFiltro, $prazoFiltro, $perfilAcesso, $usuarioLogadoId) {
    $sql = "
        SELECT COUNT(*) as total
          FROM projetos p
         WHERE p.EnviadoFunil = 1
    ";
    $params = [];
    
    // Perfis Inova, BI e DTic só podem ver projetos em que são responsáveis ou estão envolvidos
    if (in_array($perfilAcesso, [4, 5, 8])) {
        $sql .= " AND (p.ResponsavelId = :usuario_id OR FIND_IN_SET(:usuario_id_env, p.UsuariosEnvolvidos))";
        $params[':usuario_id'] = $usuarioLogadoId;
        $params[':usuario_id_env'] = $usuarioLogadoId;
    }
    // Perfil de Gestor só vê projetos do seu setor
    elseif ($perfilAcesso == 2 && !empty($setorFiltro)) {
        $sql .= " AND p.SetorRelacionadoId = :setor_gestor";
        $params[':setor_gestor'] = $setorFiltro;
    }
    
    if (!empty($anoFiltro)) {
        $sql .= " AND YEAR(p.DataCriacao) = :ano";
        $params[':ano'] = $anoFiltro;
    }
    if (!empty($setorFiltro)) {
        $sql .= " AND p.SetorRelacionadoId = :setor";
        $params[':setor'] = $setorFiltro;
    }
    if (!empty($prazoFiltro)) {
        $sql .= " AND p.Prazo = :prazo";
        $params[':prazo'] = $prazoFiltro;
    }
    if (!empty($status)) {
        $sql .= " AND p.Status = :status";
        $params[':status'] = $status;
    }
    
    // CORREÇÃO AQUI - Remova o bloco where e substitua por este:
    if (!empty($statusFiltro)) {
        $sql .= " AND p.Status = :statusFiltro";
        $params[':statusFiltro'] = $statusFiltro;
    }
    // Não colocamos mais a restrição de status padrão, para permitir todos os status

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->execute();
    return $stmt->fetchColumn();
}

// -------------------------------------------------
// Contadores nos cards
// -------------------------------------------------
$totalOKR        = getOKRCount($pdo, null,        $anoFiltro, $setorFiltro, $prazoFiltro, $perfilAcesso, $usuarioLogadoId);
$okrsFinalizados = getOKRCount($pdo, 'concluido', $anoFiltro, $setorFiltro, $prazoFiltro, $perfilAcesso, $usuarioLogadoId);
$okrsBloqueados  = getOKRCount($pdo, 'bloqueado', $anoFiltro, $setorFiltro, $prazoFiltro, $perfilAcesso, $usuarioLogadoId);

// -------------------------------------------------
// Processamento via AJAX e formulários
// -------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ----------------------------------------------------------------------
    // Obter detalhes do projeto
    // ----------------------------------------------------------------------
    if (isset($_POST['action']) && $_POST['action'] === 'get_projeto_details') {
        header('Content-Type: application/json');
        $projetoId = (int) ($_POST['projetoId'] ?? 0);
        if ($projetoId > 0) {
            $stmt = $pdo->prepare("
                SELECT p.*, u.Nome AS NomeResponsavel, s.nome_do_setor 
                  FROM projetos p
             LEFT JOIN usuarios u ON p.ResponsavelId = u.Id
             LEFT JOIN setores s ON p.SetorRelacionadoId = s.id
                 WHERE p.Id = :id
            ");
            $stmt->execute([':id' => $projetoId]);
            $projeto = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($projeto) {
                // Verificação de permissão para ver este projeto
                $podeVerProjeto = false;
                
                // Perfis administrativos podem ver qualquer projeto
                if (in_array($perfilAcesso, [1, 2, 7, 9])) {
                    $podeVerProjeto = true;
                }
                // Gestor pode ver projetos do seu setor
                elseif ($perfilAcesso == 2 && $projeto['SetorRelacionadoId'] == $usuarioSetorId) {
                    $podeVerProjeto = true;
                }
                // Perfis restritos só veem projetos em que são responsáveis ou estão envolvidos
                elseif (in_array($perfilAcesso, [4, 5, 8])) {
                    $envolvidosArray = explode(',', $projeto['UsuariosEnvolvidos'] ?? '');
                    if ($projeto['ResponsavelId'] == $usuarioLogadoId || in_array($usuarioLogadoId, $envolvidosArray)) {
                        $podeVerProjeto = true;
                    }
                }
                
                if (!$podeVerProjeto) {
                    echo json_encode(['success' => false, 'message' => 'Você não tem permissão para visualizar este projeto.']);
                    exit;
                }
                
                // Outros Setores
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

                // Usuários Envolvidos
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

                // Dependências
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
        echo json_encode(['success' => false, 'message' => 'Projeto não encontrado.']);
        exit;
    }

    // ----------------------------------------------------------------------
    // Listar Subtarefas (igual "projetos.php")
    // ----------------------------------------------------------------------
    if (isset($_POST['action']) && $_POST['action'] === 'listar_subtarefas') {
        header('Content-Type: application/json');
        $projetoId = (int)$_POST['projetoId'];
        if ($projetoId <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID de projeto inválido.']);
            exit;
        }
        
        // Verificação de permissão para ver este projeto
        $podeVerProjeto = false;
        $stmtProjeto = $pdo->prepare("SELECT ResponsavelId, SetorRelacionadoId, UsuariosEnvolvidos FROM projetos WHERE Id = :id");
        $stmtProjeto->execute([':id' => $projetoId]);
        $projeto = $stmtProjeto->fetch(PDO::FETCH_ASSOC);
        
        if ($projeto) {
            // Perfis administrativos podem ver qualquer projeto
            if (in_array($perfilAcesso, [1, 2, 7, 9])) {
                $podeVerProjeto = true;
            }
            // Gestor pode ver projetos do seu setor
            elseif ($perfilAcesso == 2 && $projeto['SetorRelacionadoId'] == $usuarioSetorId) {
                $podeVerProjeto = true;
            }
            // Perfis restritos só veem projetos em que são responsáveis ou estão envolvidos
            elseif (in_array($perfilAcesso, [4, 5, 8])) {
                $envolvidosArray = explode(',', $projeto['UsuariosEnvolvidos'] ?? '');
                if ($projeto['ResponsavelId'] == $usuarioLogadoId || in_array($usuarioLogadoId, $envolvidosArray)) {
                    $podeVerProjeto = true;
                }
            }
        }
        
        if (!$podeVerProjeto) {
            echo json_encode(['success' => false, 'message' => 'Você não tem permissão para visualizar este projeto.']);
            exit;
        }
        
        $sqlSub = "
            SELECT s.id, 
                   s.projeto_id,
                   s.parent_subtarefa_id,
                   s.criador_id,
                   s.nome_subtarefa,
                   s.descricao,
                   s.data_cadastro,
                   s.concluida,
                   s.is_key_result,
                   s.concluido_por,
                   s.data_conclusao,
                   s.ordem,
                   u.Nome  AS nomeCriador,
                   uc.Nome AS nomeConcluido
              FROM subtarefas_projetos s
         LEFT JOIN usuarios u  ON s.criador_id    = u.Id
         LEFT JOIN usuarios uc ON s.concluido_por = uc.Id
             WHERE s.projeto_id = :projId
          ORDER BY s.ordem ASC, s.id ASC
        ";
        $stmtSub = $pdo->prepare($sqlSub);
        $stmtSub->execute([':projId' => $projetoId]);
        $raw = $stmtSub->fetchAll(PDO::FETCH_ASSOC);

        // Montar hierarquia:
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

     // ----------------------------------------------------------------------
    // Criar Subtarefa (igual "projetos.php")
    // ----------------------------------------------------------------------
    if (isset($_POST['action']) && $_POST['action'] === 'criar_subtarefa') {
        header('Content-Type: application/json');
        try {
            $projetoId = (int)$_POST['projetoId'];
            $nome_subtarefa = trim($_POST['nome_subtarefa'] ?? '');
            $descricao = trim($_POST['descricao'] ?? '');
            $parentId  = isset($_POST['parent_subtarefa_id']) && $_POST['parent_subtarefa_id'] !== '' 
                         ? (int)$_POST['parent_subtarefa_id'] 
                         : null;

            if ($projetoId <= 0 || empty($nome_subtarefa)) {
                throw new Exception("Dados insuficientes para criar subtarefa.");
            }
            
            // Verificação de permissão para modificar este projeto
            $podeEditarProjeto = false;
            $stmtProjeto = $pdo->prepare("SELECT ResponsavelId, SetorRelacionadoId, UsuariosEnvolvidos FROM projetos WHERE Id = :id");
            $stmtProjeto->execute([':id' => $projetoId]);
            $projeto = $stmtProjeto->fetch(PDO::FETCH_ASSOC);
            
            if ($projeto) {
                // Perfis administrativos podem editar qualquer projeto
                if (in_array($perfilAcesso, [1, 2, 9])) {
                    $podeEditarProjeto = true;
                }
                // Gestor pode editar projetos do seu setor
                elseif ($perfilAcesso == 2 && $projeto['SetorRelacionadoId'] == $usuarioSetorId) {
                    $podeEditarProjeto = true;
                }
                // Perfis restritos só editam projetos em que são responsáveis ou estão envolvidos
                elseif (in_array($perfilAcesso, [4, 5, 8])) {
                    $envolvidosArray = explode(',', $projeto['UsuariosEnvolvidos'] ?? '');
                    if ($projeto['ResponsavelId'] == $usuarioLogadoId || in_array($usuarioLogadoId, $envolvidosArray)) {
                        $podeEditarProjeto = true;
                    }
                }
            }
            
            if (!$podeEditarProjeto) {
                throw new Exception("Você não tem permissão para modificar este projeto.");
            }

            // Descobrir a nova ordem
            if ($parentId === null) {
                $sqlMax = "SELECT COALESCE(MAX(ordem),0) AS maxOrd FROM subtarefas_projetos WHERE projeto_id = :proj AND parent_subtarefa_id IS NULL";
                $stmtMax = $pdo->prepare($sqlMax);
                $stmtMax->execute([':proj' => $projetoId]);
            } else {
                $sqlMax = "SELECT COALESCE(MAX(ordem),0) AS maxOrd FROM subtarefas_projetos WHERE projeto_id = :proj AND parent_subtarefa_id = :parent";
                $stmtMax = $pdo->prepare($sqlMax);
                $stmtMax->execute([':proj' => $projetoId, ':parent' => $parentId]);
            }
            $maxRow = $stmtMax->fetch(PDO::FETCH_ASSOC);
            $novaOrdem = (int)$maxRow['maxOrd'] + 1;

            $sqlIns = "
                INSERT INTO subtarefas_projetos 
                (projeto_id, criador_id, nome_subtarefa, descricao, data_cadastro, concluida, parent_subtarefa_id, ordem, is_key_result)
                VALUES (:projId, :criadorId, :nome, :descricao, NOW(), 0, :parentId, :ordem, 0)
            ";

            $stmtIns = $pdo->prepare($sqlIns);
            $stmtIns->execute([
                ':projId' => $projetoId,
                ':criadorId' => $usuarioLogadoId,
                ':nome' => $nome_subtarefa,
                ':descricao' => $descricao,
                ':parentId' => $parentId,
                ':ordem' => $novaOrdem
            ]);

            echo json_encode(['success' => true, 'message' => 'Subtarefa criada com sucesso!']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // ----------------------------------------------------------------------
    // Reordenar Subtarefas (arrastar/soltar)
    // Bloqueia se subtarefa for key_result=1 e new parent != null
    // ----------------------------------------------------------------------
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
            // Preparado para leitura do key_result
            $checkKeyResult = $pdo->prepare("
                SELECT id, is_key_result, nome_subtarefa
                  FROM subtarefas_projetos
                 WHERE id = :id
            ");

            // UPDATE para ordem e parent
            $upd = $pdo->prepare("
                UPDATE subtarefas_projetos
                   SET ordem = :ordem,
                       parent_subtarefa_id = :parentId
                 WHERE id = :id
            ");

            // Flag para erro de Key Result
            $krError = false;
            $krErrorName = '';

            foreach ($listaDecod as $item) {
                $subtarefaId = (int)($item['id'] ?? 0);
                $novaOrdem   = (int)($item['ordem'] ?? 0);
                $parentId = (isset($item['parentId']) && $item['parentId'] !== null && $item['parentId'] !== '') ? (int)$item['parentId'] : null;


                if ($subtarefaId > 0) {
                    // Verifica se a subtarefa é Key Result
                    $checkKeyResult->execute([':id' => $subtarefaId]);
                    $row = $checkKeyResult->fetch(PDO::FETCH_ASSOC);

                    // Se Key Result e tentando mover para dentro (parent não nulo)
                    if ($row && $row['is_key_result'] == 1 && $parentId !== null) {
                        $krError = true;
                        if (!$krErrorName) {
                            $krErrorName = $row['nome_subtarefa'];
                        }
                    }

                    // Executa update mesmo que haja erro (rollback depois, se necessário)
                    $upd->execute([
                        ':ordem'    => $novaOrdem,
                        ':parentId' => $parentId,
                        ':id'       => $subtarefaId
                    ]);
                }
            }

            if ($krError) {
                $pdo->rollBack();
                echo json_encode([
                    'success' => false,
                    'message' => "A tarefa \"{$krErrorName}\" é um Key Result e não pode se tornar subtarefa!"
                ]);
                exit;
            }

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Ordem atualizada com sucesso!']);

        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // ----------------------------------------------------------------------
    // Toggle Subtarefa (Concluir/Desconcluir) (igual "projetos.php")
    // ----------------------------------------------------------------------
    if (isset($_POST['action']) && $_POST['action'] === 'toggle_subtarefa') {
        header('Content-Type: application/json');
        $subtarefaId = (int) ($_POST['subtarefaId'] ?? 0);
        $concluida   = (int) ($_POST['concluida'] ?? 0);

        if ($subtarefaId <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID inválido para alteração de status.']);
            exit;
        }

        if ($concluida === 1) {
            // Marcar como concluída
            $stmt = $pdo->prepare("
                UPDATE subtarefas_projetos
                   SET concluida = 1,
                       concluido_por = :usuario_id,
                       data_conclusao = NOW()
                 WHERE id = :id
            ");
            $stmt->execute([
                ':usuario_id' => $usuarioLogadoId,
                ':id'         => $subtarefaId,
            ]);
        } else {
            // Desmarcar
            $stmt = $pdo->prepare("
                UPDATE subtarefas_projetos
                   SET concluida = 0,
                       concluido_por = NULL,
                       data_conclusao = NULL
                 WHERE id = :id
            ");
            $stmt->execute([
                ':id' => $subtarefaId
            ]);
        }

        echo json_encode(['success' => true, 'message' => 'Status atualizado com sucesso!']);
        exit;
    }

    // ----------------------------------------------------------------------
    // Toggle Key Result (igual "projetos.php", com exceção do local)
    // ----------------------------------------------------------------------
    if (isset($_POST['action']) && $_POST['action'] === 'toggle_key_result') {
        header('Content-Type: application/json');
        try {
            $subtarefaId = (int) ($_POST['subtarefaId'] ?? 0);
            $isKeyResult = (int) ($_POST['is_key_result'] ?? 0); // 0 ou 1
            if ($subtarefaId <= 0) {
                throw new Exception("ID de subtarefa inválido.");
            }
            // Verifica se é tarefa raiz (sem pai)
            $check = $pdo->prepare("SELECT parent_subtarefa_id, criador_id FROM subtarefas_projetos WHERE id = :id");
            $check->execute([':id' => $subtarefaId]);
            $row = $check->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                throw new Exception("Subtarefa não encontrada.");
            }
            if ($row['parent_subtarefa_id'] !== null && $isKeyResult == 1) {
                throw new Exception("Apenas tarefas raiz podem ser marcadas como Key Result.");
            }
            if ((int)$row['criador_id'] !== (int)$usuarioLogadoId) {
                throw new Exception("Você não tem permissão para alterar esta tarefa.");
            }

            $upd = $pdo->prepare("UPDATE subtarefas_projetos SET is_key_result = :keyResult WHERE id = :id");
            $upd->execute([':keyResult' => $isKeyResult, ':id' => $subtarefaId]);

            echo json_encode(['success' => true, 'message' => 'Estado Key Result atualizado com sucesso.']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // ----------------------------------------------------------------------
    // Editar Subtarefa (igual "projetos.php")
    // ----------------------------------------------------------------------
    if (isset($_POST['action']) && $_POST['action'] === 'editar_subtarefa') {
        header('Content-Type: application/json');
        $subtarefaId = (int) ($_POST['subtarefaId'] ?? 0);
        $nome_subtarefa = trim($_POST['nome_subtarefa'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');
        if ($subtarefaId <= 0 || empty($nome_subtarefa)) {
            echo json_encode(['success' => false, 'message' => 'Dados inválidos para edição da subtarefa.']);
            exit;
        }

        // Verifica se o criador é o usuário logado e se não é Key Result
        $check = $pdo->prepare("SELECT criador_id, is_key_result FROM subtarefas_projetos WHERE id = :id");
        $check->execute([':id' => $subtarefaId]);
        $row = $check->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            echo json_encode(['success' => false, 'message' => 'Subtarefa não encontrada.']);
            exit;
        }
        if ((int)$row['criador_id'] !== (int)$usuarioLogadoId) {
            echo json_encode(['success' => false, 'message' => 'Você não tem permissão para editar esta subtarefa.']);
            exit;
        }
        if ($row['is_key_result'] == 1) {
            echo json_encode(['success' => false, 'message' => 'Esta subtarefa é um Key Result e não pode ser editada.']);
            exit;
        }

        $stmt = $pdo->prepare("
            UPDATE subtarefas_projetos
               SET nome_subtarefa = :nome_subtarefa,
                   descricao      = :descricao
             WHERE id = :id
        ");
        $stmt->execute([
            ':nome_subtarefa' => $nome_subtarefa,
            ':descricao'      => $descricao,
            ':id'             => $subtarefaId
        ]);
        echo json_encode(['success' => true, 'message' => 'Tarefa atualizada com sucesso!']);
        exit;
    }

    // ----------------------------------------------------------------------
    // Excluir Subtarefa (igual "projetos.php")
    // ----------------------------------------------------------------------
    if (isset($_POST['action']) && $_POST['action'] === 'excluir_subtarefa') {
        header('Content-Type: application/json');
        try {
            $subtarefaId = (int) ($_POST['subtarefaId'] ?? 0);
            if ($subtarefaId <= 0) {
                throw new Exception("ID inválido para exclusão.");
            }
            // Verifica criador e se não é KeyResult
            $check = $pdo->prepare("SELECT criador_id, is_key_result FROM subtarefas_projetos WHERE id = :id");
            $check->execute([':id' => $subtarefaId]);
            $row = $check->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                throw new Exception("Subtarefa não encontrada.");
            }
            if ((int)$row['criador_id'] !== (int)$usuarioLogadoId) {
                throw new Exception("Você não tem permissão para excluir esta subtarefa.");
            }
            if ($row['is_key_result'] == 1) {
                throw new Exception("Esta subtarefa é um Key Result e não pode ser excluída.");
            }

            // Excluir recursivamente
            $pdo->beginTransaction();
            $stack = [$subtarefaId];
            while(!empty($stack)) {
                $current = array_pop($stack);
                // Buscar filhas
                $filhasStmt = $pdo->prepare("SELECT id FROM subtarefas_projetos WHERE parent_subtarefa_id = :pid");
                $filhasStmt->execute([':pid' => $current]);
                $filhas = $filhasStmt->fetchAll(PDO::FETCH_COLUMN);
                foreach($filhas as $f) {
                    $stack[] = $f;
                }
                $del = $pdo->prepare("DELETE FROM subtarefas_projetos WHERE id = :id");
                $del->execute([':id'=>$current]);
            }
            $pdo->commit();

            echo json_encode(['success' => true, 'message' => 'Tarefa excluída (e subtarefas) com sucesso!']);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // ----------------------------------------------------------------------
    // Listar Anexos
    // ----------------------------------------------------------------------
    if (isset($_POST['action']) && $_POST['action'] === 'listar_anexos') {
        header('Content-Type: application/json');
        $projetoId = (int) ($_POST['projetoId'] ?? 0);
        if ($projetoId <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID inválido para anexos.']);
            exit;
        }
        $stmt = $pdo->prepare("
            SELECT a.*, u.Nome AS nomeUsuario
              FROM anexos_projetos a
         LEFT JOIN usuarios u ON a.usuario_id = u.Id
             WHERE a.projeto_id = :projeto_id
          ORDER BY a.id DESC
        ");
        $stmt->execute([':projeto_id' => $projetoId]);
        $anexos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'anexos' => $anexos]);
        exit;
    }

    // ----------------------------------------------------------------------
    // Upload de Anexo
    // ----------------------------------------------------------------------
    if (isset($_POST['action']) && $_POST['action'] === 'upload_anexo') {
        header('Content-Type: application/json');
        $projetoId = (int) ($_POST['projetoId'] ?? 0);
        if ($projetoId <= 0 || !isset($_FILES['arquivo'])) {
            echo json_encode(['success' => false, 'message' => 'Dados inválidos para upload.']);
            exit;
        }
        $uploadDir = 'uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $arquivo = $_FILES['arquivo'];
        $nomeArquivo = basename($arquivo['name']);
        $targetFile = $uploadDir . uniqid() . '_' . $nomeArquivo;
        if (move_uploaded_file($arquivo['tmp_name'], $targetFile)) {
            $stmt = $pdo->prepare("
                INSERT INTO anexos_projetos (projeto_id, usuario_id, nome_arquivo, caminho_arquivo, data_upload)
                VALUES (:projeto_id, :usuario_id, :nome_arquivo, :caminho_arquivo, NOW())
            ");
            $stmt->execute([
                ':projeto_id'      => $projetoId,
                ':usuario_id'      => $usuarioLogadoId,
                ':nome_arquivo'    => $nomeArquivo,
                ':caminho_arquivo' => $targetFile
            ]);
            echo json_encode(['success' => true, 'message' => 'Anexo enviado com sucesso!']);
            exit;
        } else {
            echo json_encode(['success' => false, 'message' => 'Falha no upload do anexo.']);
            exit;
        }
    }

    // ----------------------------------------------------------------------
    // Editar Anexo (renomear)
    // ----------------------------------------------------------------------
    if (isset($_POST['action']) && $_POST['action'] === 'editar_anexo') {
        header('Content-Type: application/json');
        $anexoId  = (int) ($_POST['anexoId'] ?? 0);
        $novo_nome = trim($_POST['novo_nome'] ?? '');
        if ($anexoId <= 0 || empty($novo_nome)) {
            echo json_encode(['success' => false, 'message' => 'Dados inválidos para editar anexo.']);
            exit;
        }
        $stmt = $pdo->prepare("
            UPDATE anexos_projetos
               SET nome_arquivo = :novo_nome
             WHERE id = :anexoId
               AND usuario_id = :usuario_id
        ");
        $stmt->execute([
            ':novo_nome'  => $novo_nome,
            ':anexoId'    => $anexoId,
            ':usuario_id' => $usuarioLogadoId
        ]);
        echo json_encode(['success' => true, 'message' => 'Anexo atualizado com sucesso!']);
        exit;
    }

    // ----------------------------------------------------------------------
    // Excluir Anexo
    // ----------------------------------------------------------------------
    if (isset($_POST['action']) && $_POST['action'] === 'excluir_anexo') {
        header('Content-Type: application/json');
        $anexoId = (int) ($_POST['anexoId'] ?? 0);
        if ($anexoId <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID inválido para exclusão de anexo.']);
            exit;
        }
        // Remover arquivo fisicamente
        $stmt = $pdo->prepare("
            SELECT caminho_arquivo
              FROM anexos_projetos
             WHERE id = :anexoId
               AND usuario_id = :usuario_id
        ");
        $stmt->execute([
            ':anexoId'    => $anexoId,
            ':usuario_id' => $usuarioLogadoId
        ]);
        $anexo = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($anexo && file_exists($anexo['caminho_arquivo'])) {
            unlink($anexo['caminho_arquivo']);
        }
        // Excluir do BD
        $stmt = $pdo->prepare("
            DELETE FROM anexos_projetos
             WHERE id = :anexoId
               AND usuario_id = :usuario_id
        ");
        $stmt->execute([
            ':anexoId'    => $anexoId,
            ':usuario_id' => $usuarioLogadoId
        ]);
        echo json_encode(['success' => true, 'message' => 'Anexo excluído com sucesso!']);
        exit;
    }

    // ----------------------------------------------------------------------
    // Listar Atividades (comentários)
    // ----------------------------------------------------------------------
    if (isset($_POST['action']) && $_POST['action'] === 'listar_atividades') {
        header('Content-Type: application/json');
        $projetoId = (int) ($_POST['projetoId'] ?? 0);
        if ($projetoId <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID inválido para atividades.']);
            exit;
        }
        $stmt = $pdo->prepare("
            SELECT a.*, u.Nome AS nomeUsuario
              FROM atividades_projetos a
         LEFT JOIN usuarios u ON a.usuario_id = u.Id
             WHERE a.projeto_id = :projeto_id
          ORDER BY a.id DESC
        ");
        $stmt->execute([':projeto_id' => $projetoId]);
        $atividades = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'atividades' => $atividades]);
        exit;
    }

    // ----------------------------------------------------------------------
    // Adicionar Atividade
    // ----------------------------------------------------------------------
    if (isset($_POST['action']) && $_POST['action'] === 'adicionar_atividade') {
        header('Content-Type: application/json');
        $projetoId = (int) ($_POST['projetoId'] ?? 0);
        $comentario = trim($_POST['comentario'] ?? '');
        if ($projetoId <= 0 || empty($comentario)) {
            echo json_encode(['success' => false, 'message' => 'Dados inválidos para adicionar comentário.']);
            exit;
        }
        $stmt = $pdo->prepare("
            INSERT INTO atividades_projetos (projeto_id, comentario, usuario_id, data_hora)
            VALUES (:projeto_id, :comentario, :usuario_id, NOW())
        ");
        $stmt->execute([
            ':projeto_id' => $projetoId,
            ':comentario' => $comentario,
            ':usuario_id' => $usuarioLogadoId,
        ]);
        echo json_encode(['success' => true, 'message' => 'Comentário adicionado com sucesso!']);
        exit;
    }

    // ----------------------------------------------------------------------
    // Editar Atividade
    // ----------------------------------------------------------------------
    if (isset($_POST['action']) && $_POST['action'] === 'editar_atividade') {
        header('Content-Type: application/json');
        $atividadeId = (int) ($_POST['atividadeId'] ?? 0);
        $novo_texto  = trim($_POST['novo_texto'] ?? '');
        if ($atividadeId <= 0 || empty($novo_texto)) {
            echo json_encode(['success' => false, 'message' => 'Dados inválidos para editar atividade.']);
            exit;
        }
        $stmt = $pdo->prepare("
            UPDATE atividades_projetos
               SET comentario = :comentario
             WHERE id = :id
               AND usuario_id = :usuario_id
        ");
        $stmt->execute([
            ':comentario' => $novo_texto,
            ':id'         => $atividadeId,
            ':usuario_id' => $usuarioLogadoId,
        ]);
        echo json_encode(['success' => true, 'message' => 'Atividade atualizada com sucesso!']);
        exit;
    }

    // ----------------------------------------------------------------------
    // Excluir Atividade
    // ----------------------------------------------------------------------
    if (isset($_POST['action']) && $_POST['action'] === 'excluir_atividade') {
        header('Content-Type: application/json');
        $atividadeId = (int) ($_POST['atividadeId'] ?? 0);
        if ($atividadeId <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID inválido para exclusão de atividade.']);
            exit;
        }
        $stmt = $pdo->prepare("
            DELETE FROM atividades_projetos
             WHERE id = :id
               AND usuario_id = :usuario_id
        ");
        $stmt->execute([
            ':id'         => $atividadeId,
            ':usuario_id' => $usuarioLogadoId,
        ]);
        echo json_encode(['success' => true, 'message' => 'Atividade excluída com sucesso!']);
        exit;
    }

    // ----------------------------------------------------------------------
    // Ações de projeto (criar, editar, excluir, finalizar, bloquear, etc.)
    // ----------------------------------------------------------------------
    if (isset($_POST['acao'])) {
        $acao = $_POST['acao'];
        try {
            // Criar Projeto
            if ($acao === 'criar') {
                $setorRelacionadoId = $_POST['SetorRelacionadoId'] ?? null;
                $nomeProjeto = trim($_POST['NomeProjeto']) ?? '';
                $dataCriacao = $_POST['DataCriacao'] ?? date('Y-m-d');
                $prioridade = $_POST['Prioridade'] ?? '';
                $responsavelId = $_POST['ResponsavelId'] ?? null;
                $enviadoFunil = 1;
                $prazo = $_POST['Prazo'] ?? 'curto';
                $outroSetorEnvolvido = !empty($_POST['OutroSetorEnvolvido']) ? implode(',', $_POST['OutroSetorEnvolvido']) : '';
                $usuariosEnvolvidos = !empty($_POST['UsuariosEnvolvidos']) ? implode(',', $_POST['UsuariosEnvolvidos']) : '';
                $dependenciasProjetos = !empty($_POST['DependenciasProjetos']) ? implode(',', $_POST['DependenciasProjetos']) : '';
                $alineamentoEstrategico = $_POST['AlinhamentoEstrategico'] ?? 0;
                $vulnerabilidadeCiberseg = $_POST['VulnerabilidadeCiberseguranca'] ?? 0;
                $lpd_dpo_criptografado = $_POST['LPD_DPO_Criptografado'] ?? 0;
                $impactoOperacional = $_POST['ImpactoOperacional'] ?? 0;
                $impactoAdministrativo = $_POST['ImpactoAdministrativo'] ?? 0;
                $impactoFinanceiro = $_POST['ImpactoFinanceiro'] ?? 0;
                if(empty($nomeProjeto) || empty($setorRelacionadoId) || empty($responsavelId)) {
                    throw new Exception("Preencha todos os campos obrigatórios.");
                }
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
                        ImpactoFinanceiro
                    ) VALUES (
                        :SetorRelacionadoId, :NomeProjeto, :DataCriacao, :Prioridade, :ResponsavelId, :EnviadoFunil,
                        :Prazo, :OutroSetorEnvolvido, :UsuariosEnvolvidos, :DependenciasProjetos, :AlinhamentoEstrategico,
                        :VulnerabilidadeCiberseguranca, :LPD_DPO_Criptografado, :ImpactoOperacional, :ImpactoAdministrativo, :ImpactoFinanceiro
                    )
                ";
                $stmt = $pdo->prepare($sqlInsert);
                $stmt->execute([
                    ':SetorRelacionadoId' => $setorRelacionadoId,
                    ':NomeProjeto' => $nomeProjeto,
                    ':DataCriacao' => $dataCriacao,
                    ':Prioridade' => $prioridade,
                    ':ResponsavelId' => $responsavelId,
                    ':EnviadoFunil' => $enviadoFunil,
                    ':Prazo' => $prazo,
                    ':OutroSetorEnvolvido' => $outroSetorEnvolvido,
                    ':UsuariosEnvolvidos' => $usuariosEnvolvidos,
                    ':DependenciasProjetos' => $dependenciasProjetos,
                    ':AlinhamentoEstrategico' => $alineamentoEstrategico,
                    ':VulnerabilidadeCiberseguranca' => $vulnerabilidadeCiberseg,
                    ':LPD_DPO_Criptografado' => $lpd_dpo_criptografado,
                    ':ImpactoOperacional' => $impactoOperacional,
                    ':ImpactoAdministrativo' => $impactoAdministrativo,
                    ':ImpactoFinanceiro' => $impactoFinanceiro
                ]);
                $_SESSION['message'] = "Projeto OKR cadastrado com sucesso!";
                header('Location: okr');
                exit;
            }

            // Marcar OKRs (exemplo de priorização)
            if ($acao === 'marcarOKRs') {
                $qtd = (int) ($_POST['quantidadeOKR'] ?? 0);
                if ($qtd < 1) { throw new Exception("A quantidade deve ser pelo menos 1."); }
                $_SESSION['ultimaQuantidadeOKR'] = $qtd;
                $stmtAll = $pdo->query("
                    SELECT Id,
                    (AlinhamentoEstrategico +
                     VulnerabilidadeCiberseguranca +
                     LPD_DPO_Criptografado +
                     ImpactoOperacional +
                     ImpactoAdministrativo +
                     ImpactoFinanceiro) AS ValorOKR
                      FROM projetos
                  ORDER BY ValorOKR DESC
                ");
                $allProjects = $stmtAll->fetchAll(PDO::FETCH_ASSOC);
                $totalAll = count($allProjects);
                if ($qtd > $totalAll) { $qtd = $totalAll; }
                $topIDs  = array_column(array_slice($allProjects, 0, $qtd), 'Id');
                $restIDs = array_column(array_slice($allProjects, $qtd), 'Id');
                $pdo->beginTransaction();
                try {
                    if (!empty($topIDs)) {
                        $placeHolders = rtrim(str_repeat('?,', count($topIDs)), ',');
                        $sqlTop = "UPDATE projetos SET EnviadoFunil = 1 WHERE Id IN ($placeHolders)";
                        $stmtTop = $pdo->prepare($sqlTop);
                        $stmtTop->execute($topIDs);
                    }
                    if (!empty($restIDs)) {
                        $placeHolders = rtrim(str_repeat('?,', count($restIDs)), ',');
                        $sqlRest = "UPDATE projetos SET EnviadoFunil = 0 WHERE Id IN ($placeHolders)";
                        $stmtRest = $pdo->prepare($sqlRest);
                        $stmtRest->execute($restIDs);
                    }
                    $pdo->commit();
                    $_SESSION['message'] = "Projetos marcados como OKR com sucesso!";
                } catch (Exception $e) {
                    $pdo->rollBack();
                    throw $e;
                }
                header('Location: okr');
                exit;
            }

            // Excluir Projeto
            if ($acao === 'excluir') {
                $idExcluir = (int) ($_POST['projeto_id'] ?? 0);
                if ($idExcluir > 0) {
                    $stmtDel = $pdo->prepare("DELETE FROM projetos WHERE Id = :id");
                    $stmtDel->bindValue(':id', $idExcluir, PDO::PARAM_INT);
                    $stmtDel->execute();
                    $_SESSION['message'] = "Projeto excluído com sucesso!";
                }
                header('Location: okr');
                exit;
            }

            // Editar Projeto
            if ($acao === 'editar') {
                $projetoId = (int) ($_POST['projeto_id'] ?? 0);
                $setorRelacionadoId = $_POST['SetorRelacionadoId'] ?? null;
                $nomeProjeto = trim($_POST['NomeProjeto']) ?? '';
                $dataCriacao = $_POST['DataCriacao'] ?? date('Y-m-d');
                $prioridade = $_POST['Prioridade'] ?? '';
                $responsavelId = $_POST['ResponsavelId'] ?? null;
                $enviadoFunil = $_POST['EnviadoFunil'] ?? 1;
                $prazo = $_POST['Prazo'] ?? 'curto';
                $outroSetorEnvolvido = !empty($_POST['OutroSetorEnvolvido']) ? implode(',', $_POST['OutroSetorEnvolvido']) : '';
                $usuariosEnvolvidos = !empty($_POST['UsuariosEnvolvidos']) ? implode(',', $_POST['UsuariosEnvolvidos']) : '';
                $dependenciasProjetos = !empty($_POST['DependenciasProjetos']) ? implode(',', $_POST['DependenciasProjetos']) : '';
                $alineamentoEstrategico = $_POST['AlinhamentoEstrategico'] ?? 0;
                $vulnerabilidadeCiberseguranca = $_POST['VulnerabilidadeCiberseguranca'] ?? 0;
                $lpd_dpo_criptografado = $_POST['LPD_DPO_Criptografado'] ?? 0;
                $impactoOperacional = $_POST['ImpactoOperacional'] ?? 0;
                $impactoAdministrativo = $_POST['ImpactoAdministrativo'] ?? 0;
                $impactoFinanceiro = $_POST['ImpactoFinanceiro'] ?? 0;
                if(empty($projetoId) || empty($nomeProjeto) || empty($setorRelacionadoId) || empty($responsavelId)) {
                    throw new Exception("Preencha todos os campos obrigatórios.");
                }
                $sqlUpdate = "
                    UPDATE projetos SET
                        SetorRelacionadoId = :SetorRelacionadoId,
                        NomeProjeto = :NomeProjeto,
                        DataCriacao = :DataCriacao,
                        Prioridade = :Prioridade,
                        ResponsavelId = :ResponsavelId,
                        EnviadoFunil = :EnviadoFunil,
                        Prazo = :Prazo,
                        OutroSetorEnvolvido = :OutroSetorEnvolvido,
                        UsuariosEnvolvidos = :UsuariosEnvolvidos,
                        DependenciasProjetos = :DependenciasProjetos,
                        AlinhamentoEstrategico = :AlinhamentoEstrategico,
                        VulnerabilidadeCiberseguranca = :VulnerabilidadeCiberseguranca,
                        LPD_DPO_Criptografado = :LPD_DPO_Criptografado,
                        ImpactoOperacional = :ImpactoOperacional,
                        ImpactoAdministrativo = :ImpactoAdministrativo,
                        ImpactoFinanceiro = :ImpactoFinanceiro
                    WHERE Id = :Id
                ";
                $stmt = $pdo->prepare($sqlUpdate);
                $stmt->execute([
                    ':SetorRelacionadoId' => $setorRelacionadoId,
                    ':NomeProjeto' => $nomeProjeto,
                    ':DataCriacao' => $dataCriacao,
                    ':Prioridade' => $prioridade,
                    ':ResponsavelId' => $responsavelId,
                    ':EnviadoFunil' => $enviadoFunil,
                    ':Prazo' => $prazo,
                    ':OutroSetorEnvolvido' => $outroSetorEnvolvido,
                    ':UsuariosEnvolvidos' => $usuariosEnvolvidos,
                    ':DependenciasProjetos' => $dependenciasProjetos,
                    ':AlinhamentoEstrategico' => $alineamentoEstrategico,
                    ':VulnerabilidadeCiberseguranca' => $vulnerabilidadeCiberseguranca,
                    ':LPD_DPO_Criptografado' => $lpd_dpo_criptografado,
                    ':ImpactoOperacional' => $impactoOperacional,
                    ':ImpactoAdministrativo' => $impactoAdministrativo,
                    ':ImpactoFinanceiro' => $impactoFinanceiro,
                    ':Id' => $projetoId
                ]);
                echo json_encode(['success' => true, 'message' => "Projeto atualizado com sucesso!"]);
                exit;
            }

            // Finalizar Projeto
            if ($acao === 'finalizar') {
                $projetoId = (int) ($_POST['projeto_id'] ?? 0);
                $licoes = trim($_POST['licoes'] ?? '');
                if ($projetoId <= 0) {
                    throw new Exception("Projeto inválido.");
                }
                // Atualiza status
                $stmtFinalizar = $pdo->prepare("UPDATE projetos SET Status = 'concluido' WHERE Id = :id");
                $stmtFinalizar->execute([':id' => $projetoId]);

                // Calcula tempo de execução
                $stmtData = $pdo->prepare("SELECT DataCriacao FROM projetos WHERE Id = :id");
                $stmtData->execute([':id' => $projetoId]);
                $projData = $stmtData->fetch(PDO::FETCH_ASSOC);
                $dataCriacao = $projData['DataCriacao'];
                $date1 = new DateTime($dataCriacao);
                $date2 = new DateTime();
                $diff = $date1->diff($date2);
                $dias = $diff->days;

                // Insere em okr_relatorios, se existente
                $stmtRelatorio = $pdo->prepare("
                    INSERT INTO okr_relatorios (projeto_id, data_finalizacao, tempo_execucao, licoes_aprendidas, motivo_bloqueio)
                    VALUES (:projeto_id, NOW(), :tempo, :licoes, '')
                ");
                $stmtRelatorio->execute([
                    ':projeto_id' => $projetoId,
                    ':tempo' => $dias,
                    ':licoes' => $licoes
                ]);
                echo json_encode(['success' => true, 'message' => "Projeto finalizado com sucesso!"]);
                exit;
            }

            // Bloquear projeto
            if ($acao === 'bloquear') {
                $projetoId = (int) ($_POST['projeto_id'] ?? 0);
                $motivo = trim($_POST['motivo'] ?? '');
                if ($projetoId <= 0) { throw new Exception("Projeto inválido."); }
                if (empty($motivo)) { throw new Exception("Informe o motivo do bloqueio."); }
            
                $stmtBloquear = $pdo->prepare("
                    UPDATE projetos
                       SET Status = 'bloqueado',
                           motivo_bloqueio = :motivo,
                           DataBloqueio = NOW()
                     WHERE Id = :id
                ");
                $stmtBloquear->execute([
                    ':id'    => $projetoId,
                    ':motivo'=> $motivo
                ]);
                echo json_encode(['success' => true, 'message' => "Projeto bloqueado com sucesso!"]);
                exit;
            }

            // Reabrir projeto
            if ($acao === 'reabrir') {
                $projetoId = (int) ($_POST['projeto_id'] ?? 0);
                if ($projetoId <= 0) { throw new Exception("Projeto inválido."); }
            
                // Atualiza status para "andamento" e libera OKR
                $stmt = $pdo->prepare("
                    UPDATE projetos
                       SET Status = 'andamento',
                           motivo_bloqueio = NULL,
                           DisponivelOKR = 1,
                           dtliberadookr = IF(dtliberadookr IS NULL, NOW(), dtliberadookr)
                     WHERE Id = :id
                ");
                $stmt->execute([':id' => $projetoId]);
                echo json_encode(['success' => true, 'message' => "Projeto reaberto com sucesso!"]);
                exit;
            }

            // Desbloquear projeto
            if ($acao === 'desbloquear') {
                $projetoId = (int) ($_POST['projeto_id'] ?? 0);
                if ($projetoId <= 0) { throw new Exception("Projeto inválido."); }

                // Ao desbloquear, Status -> andamento e DisponivelOKR = 1
                $stmt = $pdo->prepare("
                    UPDATE projetos
                       SET Status = 'andamento',
                           motivo_bloqueio = NULL,
                           DataBloqueio = NULL,
                           DisponivelOKR = 1
                     WHERE Id = :id
                ");
                $stmt->execute([':id' => $projetoId]);
                echo json_encode(['success' => true, 'message' => "Projeto desbloqueado com sucesso!"]);
                exit;
            }

        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => "Ocorreu um erro: " . $e->getMessage()]);
            exit;
        }
    }
}

// ----------------------------------------------------------------------
// Listar Justificativas do Andamento do Projeto
// ----------------------------------------------------------------------
if (isset($_POST['action']) && $_POST['action'] === 'listar_justificativas') {
    header('Content-Type: application/json');
    $projetoId = (int) ($_POST['projetoId'] ?? 0);
    if ($projetoId <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID de projeto inválido.']);
        exit;
    }
    $stmt = $pdo->prepare("
        SELECT j.*, u.Nome AS nomeUsuario
          FROM justificativas_andamento j
     LEFT JOIN usuarios u ON j.usuario_id = u.Id
         WHERE j.projeto_id = :projetoId
         ORDER BY j.data_justificativa DESC
    ");
    $stmt->execute([':projetoId' => $projetoId]);
    $justificativas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'justificativas' => $justificativas]);
    exit;
}

// ----------------------------------------------------------------------
// Adicionar Justificativa do Andamento do Projeto
// ----------------------------------------------------------------------
if (isset($_POST['action']) && $_POST['action'] === 'adicionar_justificativa') {
    header('Content-Type: application/json');
    try {
        $projetoId = (int) ($_POST['projetoId'] ?? 0);
        $justificativa = trim($_POST['justificativa'] ?? '');
        $origem = trim($_POST['origem'] ?? 'avulsa'); // Padrão avulsa
        
        // Dados da semana específica, se fornecidos
        $semana = isset($_POST['semana']) ? $_POST['semana'] : null;
        $dataQuinta = isset($_POST['dataQuinta']) ? $_POST['dataQuinta'] : null;
        
        if ($projetoId <= 0 || empty($justificativa)) {
            throw new Exception("Dados insuficientes para adicionar a justificativa.");
        }
        
        // Sempre usar a data e hora atuais para o registro da justificativa
        $dataJustificativa = 'NOW()';
        
        // Não modificamos mais a data para a quinta-feira, usamos sempre a data atual
        // A variável $dataQuinta ainda é importante para rastrear qual quinta-feira está sendo justificada
        // mas o timestamp do registro será sempre o momento atual
        
        // Query com a data personalizada
        $sql = "
            INSERT INTO justificativas_andamento (projeto_id, usuario_id, justificativa, data_justificativa, origem)
            VALUES (:projetoId, :usuarioId, :justificativa, {$dataJustificativa}, :origem)
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':projetoId'    => $projetoId,
            ':usuarioId'    => $usuarioLogadoId,
            ':justificativa'=> $justificativa,
            ':origem'       => $origem
        ]);
        
        // CORREÇÃO 2: Verificar se esta justificativa satisfaz alguma justificativa pendente
        // mesmo quando feita diretamente pelo modal de detalhes do projeto
        if ($semana === null && $dataQuinta === null) {
            // Foi adicionada diretamente do modal de detalhes (não veio do modal de pendentes)
            // Verificar se satisfaz alguma quinta-feira pendente para este projeto
            $quintasFeiras = obterQuintasFeirasVerificacao();
            
            // Verificar se este projeto tem pendências
            $stmtCheckPendente = $pdo->prepare("
                SELECT p.Id, j.data_justificativa 
                  FROM projetos p
             LEFT JOIN justificativas_andamento j ON 
                        p.Id = j.projeto_id AND 
                        DATE(j.data_justificativa) BETWEEN ? AND ?
                 WHERE p.Id = ? 
                   AND (p.Status = 'andamento' OR p.Status = 'bloqueado')
                   AND p.EnviadoFunil = 1
            ");
            
            // Para cada quinta-feira verificada
            foreach ($quintasFeiras as $index => $dataQuinta) {
                $hoje = date('Y-m-d');
                $stmtCheckPendente->execute([$dataQuinta, $hoje, $projetoId]);
                $existePendencia = ($stmtCheckPendente->rowCount() === 0);
                
                if ($existePendencia) {
                    // Encontramos uma quinta-feira pendente
                    // Adicionar dados para retorno ao frontend
                    $response = [
                        'success' => true, 
                        'message' => 'Justificativa adicionada com sucesso!',
                        'satisfazQuintaFeira' => true,
                        'semana' => $index,
                        'dataQuinta' => $dataQuinta
                    ];
                    echo json_encode($response);
                    exit;
                }
            }
        }
        
        echo json_encode(['success' => true, 'message' => 'Justificativa adicionada com sucesso!']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}


// ----------------------------------------------------------------------
// Verificar se deve exibir modal de justificativa (apenas às quintas-feiras)
// ----------------------------------------------------------------------

/**
 * Retorna um array com as datas das quintas-feiras para verificação de justificativas
 * @param int $semanas Número de semanas a serem verificadas retroativamente
 * @return array Array de datas das quintas-feiras no formato Y-m-d
 */
function obterQuintasFeirasVerificacao($semanas = 8): array {
    $hoje = new DateTime('today', new DateTimeZone('America/Sao_Paulo'));
    $quintasFeiras = [];
    
    // Encontra a quinta-feira desta semana
    $quintaAtual = clone $hoje;
    $diaSemana = (int)$quintaAtual->format('N'); // 1=segunda ... 7=domingo
    
    if ($diaSemana < 4) { // Se for seg, ter, qua
        $quintaAtual->modify('next Thursday');
    } elseif ($diaSemana > 4) { // Se for sex, sab, dom
        $quintaAtual->modify('next Thursday');
        $quintaAtual->modify('-1 week');
    }
    // Se for quinta ($diaSemana == 4), não precisa modificar
    
    // Verifica se hoje é quinta e adiciona a quinta atual
    if ($diaSemana == 4) {
        $quintasFeiras[] = $quintaAtual->format('Y-m-d');
    }
    
    // Adiciona as quintas-feiras anteriores
    $quinta = clone $quintaAtual;
    for ($i = 0; $i < $semanas; $i++) {
        $quinta->modify('-1 week');
        $quintasFeiras[] = $quinta->format('Y-m-d');
    }
    
    return $quintasFeiras;
}

/**
 * Verifica projetos pendentes de justificativa para todas as quintas-feiras recentes
 */
function getProjetosPendentesJustificativa($pdo, $usuarioId, $perfilAcesso, $usuarioSetorId) {
    $hoje = (new DateTime('now', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');
    $ehQuintaFeira = (int)(new DateTime('now', new DateTimeZone('America/Sao_Paulo')))->format('N') === 4;
    
    // Obter lista de quintas-feiras para verificação (atual, se for quinta, + anteriores)
    $quintasFeiras = obterQuintasFeirasVerificacao();
    if (empty($quintasFeiras)) {
        return []; // Se não houver quintas-feiras para verificar, retorna vazio
    }
    
    // Construir a condição para todas as quintas-feiras de uma vez
    $placeholdersData = implode(',', array_fill(0, count($quintasFeiras), '?'));
    
    // SQL base modificado para lidar com projetos recém-adicionados ao OKR
    $sql = "
      SELECT DISTINCT p.Id, p.NomeProjeto, p.ResponsavelId, p.SetorRelacionadoId, 
             p.dtliberadookr, u.Nome as NomeResponsavel, s.nome_do_setor as NomeSetor
        FROM projetos p
        LEFT JOIN usuarios u ON p.ResponsavelId = u.Id
        LEFT JOIN setores s ON p.SetorRelacionadoId = s.id
       WHERE (p.Status = 'andamento' OR p.Status = 'bloqueado')
         AND p.EnviadoFunil = 1
         AND p.Id IN (
             SELECT DISTINCT projeto_id 
               FROM (
                   SELECT p.Id as projeto_id
                     FROM projetos p
                    WHERE (p.Status = 'andamento' OR p.Status = 'bloqueado')
                      AND p.EnviadoFunil = 1 
                      AND NOT EXISTS (
                          SELECT 1
                            FROM justificativas_andamento j
                           WHERE j.projeto_id = p.Id
                             AND DATE(j.data_justificativa) >= ?
                      )
                      /* NOVA CONDIÇÃO: Não incluir projetos liberados hoje, exceto se hoje for quinta-feira */
                      AND (
                          p.dtliberadookr IS NULL OR 
                          DATE(p.dtliberadookr) < ? OR 
                          (DATE(p.dtliberadookr) = ? AND ?)
                      )
               ) as subquery
         )
    ";
    
    $params = [$quintasFeiras[0]]; // A primeira quinta-feira (mais recente)
    $params[] = $hoje; // Para a condição: DATE(p.dtliberadookr) < ?
    $params[] = $hoje; // Para a condição: DATE(p.dtliberadookr) = ?
    $params[] = $ehQuintaFeira; // Booleano: TRUE se hoje for quinta-feira, FALSE caso contrário
    
    // Condição diferente baseada no perfil
    if (!in_array($perfilAcesso, [1, 7, 9])) { // Se não for admin, diretor ou sub-diretor
        if ($perfilAcesso == 2) { // Perfil de gestor
            // Gestor vê todos os projetos do seu setor
            $sql .= " AND p.SetorRelacionadoId = ?";
            $params[] = $usuarioSetorId;
        } else {
            // Outros perfis veem apenas projetos em que são responsáveis ou estão envolvidos
            $sql .= " AND (
                    p.ResponsavelId = ?
                    OR FIND_IN_SET(?, p.UsuariosEnvolvidos)
                )";
            $params[] = $usuarioId;
            $params[] = $usuarioId;
        }
    }
    
    // Ordenação
    if (in_array($perfilAcesso, [1, 7, 9])) {
        $sql .= " ORDER BY s.nome_do_setor ASC, p.NomeProjeto ASC, p.Id ASC";
    } else {
        $sql .= " ORDER BY p.NomeProjeto ASC, p.Id ASC";
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Adicionar campos necessários para o UI que são usados no JavaScript
    foreach ($resultados as &$resultado) {
        // Adiciona apenas a quinta-feira mais recente (primeira da lista)
        $resultado['DataQuintaFeira'] = $quintasFeiras[0];
        $resultado['NumeroSemana'] = 0;
    }
    
    return $resultados;
}


// Carregar projetos pendentes com base no perfil
$projetosPendentes = getProjetosPendentesJustificativa($pdo, $usuarioLogadoId, $perfilAcesso, $usuarioSetorId);
$totalPendentes = count($projetosPendentes);


// -------------------------------------------------
// Obter total de registros p/ paginação
// -------------------------------------------------
$countSql = "
    SELECT COUNT(*) as totalReg
      FROM projetos p
 LEFT JOIN usuarios u ON p.ResponsavelId = u.Id
     WHERE p.EnviadoFunil = 1
";
$countParams = [];

// Status filter dinâmico para count
if (isset($statusFiltro) && $statusFiltro !== '') {
    $countSql .= " AND p.Status = :statusFiltro";
    $countParams[':statusFiltro'] = $statusFiltro;
}

// Restrições por perfil:
// Admin, Diretor, Sub-diretor veem todos
if (!in_array($perfilAcesso, [1, 2, 9])) {
    // Gestor vê apenas do seu setor
    if ($perfilAcesso == 7) {
        $countSql .= " AND p.SetorRelacionadoId = :setor_gestor";
        $countParams[':setor_gestor'] = $usuarioSetorId;
    }
    // Inova, BI e DTic só veem projetos em que são responsáveis ou estão envolvidos
    elseif (in_array($perfilAcesso, [4, 5, 8])) {
        $countSql .= " AND (p.ResponsavelId = :usuario_id OR FIND_IN_SET(:usuario_id_env, p.UsuariosEnvolvidos))";
        $countParams[':usuario_id'] = $usuarioLogadoId;
        $countParams[':usuario_id_env'] = $usuarioLogadoId;
    }
}

// Filtros aplicados pelo usuário
if (!empty($anoFiltro)) {
    $countSql .= " AND YEAR(p.DataCriacao) = :ano";
    $countParams[':ano'] = $anoFiltro;
}
if (!empty($setorFiltro)) {
    $countSql .= " AND p.SetorRelacionadoId = :setor";
    $countParams[':setor'] = $setorFiltro;
}
if (!empty($prazoFiltro)) {
    $countSql .= " AND p.Prazo = :prazo";
    $countParams[':prazo'] = $prazoFiltro;
}

$stmtCount = $pdo->prepare($countSql);
foreach ($countParams as $k => $v) {
    $stmtCount->bindValue($k, $v);
}
$stmtCount->execute();
$totalRows = $stmtCount->fetchColumn();

$totalPages = $totalRows > 0 ? ceil($totalRows / $limit) : 1;
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $limit;
}

// -------------------------------------------------
// Query principal
// -------------------------------------------------
$sql = "
    SELECT p.*,
           (p.AlinhamentoEstrategico + p.VulnerabilidadeCiberseguranca + 
            p.LPD_DPO_Criptografado + p.ImpactoOperacional + 
            p.ImpactoAdministrativo + p.ImpactoFinanceiro) AS ValorOKR,
           u.Nome AS NomeResponsavel
      FROM projetos p
 LEFT JOIN usuarios u ON p.ResponsavelId = u.Id
     WHERE 1=1
";
$params = [];

// status filter dinâmico
if (isset($statusFiltro) && $statusFiltro !== '') {
    $sql .= " AND p.Status = :statusFiltro";
    $params[':statusFiltro'] = $statusFiltro;
    // Só exige EnviadoFunil = 1 para andamento ou bloqueado
    if ($statusFiltro !== 'concluido') {
        $sql .= " AND p.EnviadoFunil = 1";
    }
} else {
    // Se não filtrou status, exibe só os disponíveis para OKR
    $sql .= " AND p.EnviadoFunil = 1 AND (p.Status = 'andamento' OR p.Status = 'bloqueado')";
}

// Restrições por perfil:
// Admin, Diretor, Sub-diretor veem todos
if (!in_array($perfilAcesso, [1, 2, 9])) {
    // Gestor vê apenas do seu setor
    if ($perfilAcesso == 7) {
        $sql .= " AND p.SetorRelacionadoId = :setor_gestor";
        $params[':setor_gestor'] = $usuarioSetorId;
    }
    // Inova, BI e DTic só veem projetos em que são responsáveis ou estão envolvidos
    elseif (in_array($perfilAcesso, [4, 5, 8])) {
        $sql .= " AND (p.ResponsavelId = :usuario_id OR FIND_IN_SET(:usuario_id_env, p.UsuariosEnvolvidos))";
        $params[':usuario_id'] = $usuarioLogadoId;
        $params[':usuario_id_env'] = $usuarioLogadoId;
    }
}

// Aplicar filtros
if (!empty($anoFiltro)) {
    $sql .= " AND YEAR(p.DataCriacao) = :ano";
    $params[':ano'] = $anoFiltro;
}
if (!empty($setorFiltro)) {
    $sql .= " AND p.SetorRelacionadoId = :setor";
    $params[':setor'] = $setorFiltro;
}
if (!empty($prazoFiltro)) {
    $sql .= " AND p.Prazo = :prazo";
    $params[':prazo'] = $prazoFiltro;
}
$sql .= " ORDER BY ValorOKR DESC LIMIT :offset, :limit";

$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
$stmt->execute();
$projetos = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'remover_okr') {
    $projetoId = (int)($_POST['projeto_id'] ?? 0);
    if (in_array($perfilAcesso, [1, 2, 9]) && $projetoId > 0) {
        $stmt = $pdo->prepare("UPDATE projetos SET EnviadoFunil = 0 WHERE Id = ?");
        $stmt->execute([$projetoId]);
        $_SESSION['message'] = "Projeto removido do OKR com sucesso!";
    } else {
        $_SESSION['message'] = "Ação não permitida!";
    }
    header("Location: okr.php");
    exit;
}


?>

<!DOCTYPE html>
<html dir="ltr" lang="pt">
<head>
    <?php include 'head.php'; ?>
    <!-- Estilos customizados do seu layout -->
    <style>
/* ======================================================
   SISTEMA OKR - ESTILO PREMIUM COMPLETO
   ====================================================== */

   :root {
    --primary: #007bff;
    --primary-light: #3a95ff;
    --primary-dark: #0056b3;
    --success: #28a745;
    --success-light: #48c767;
    --success-dark: #1e7e34;
    --danger: #dc3545;
    --danger-light: #e25663;
    --danger-dark: #bd2130;
    --warning: #ffc107;
    --info: #17a2b8;
    --dark: #1f1f1f;
    --darker: #151515;
    --card-bg: #23272a;
    --card-shadow: 0 8px 24px rgba(0, 0, 0, 0.25);
    --card-hover-shadow: 0 12px 30px rgba(0, 0, 0, 0.4);
    --border-radius: 12px;
    --text-primary: #eaeaea;
    --text-secondary: #a1aab2;
    --border-color: #3c4147;
}

body {
    background-color: var(--dark);
    color: var(--text-primary);
    font-family: 'Inter', sans-serif;
    margin: 0;
    padding: 0;
}

/* Preloader */
.preloader {
    position: fixed;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    z-index: 9999;
    background: var(--dark) url('assets/images/preloader.gif') no-repeat center center;
}

/* Analytics Cards - Design Premium */
.analytics-card {
    background: linear-gradient(145deg, var(--card-bg), #2a2e32);
    border-radius: var(--border-radius);
    box-shadow: var(--card-shadow);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    position: relative;
    overflow: hidden;
    margin-bottom: 25px;
    border: none;
}

.analytics-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--card-hover-shadow);
}

.analytics-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 5px;
    background: linear-gradient(90deg, var(--primary), var(--primary-light));
}

.analytics-card.success::before {
    background: linear-gradient(90deg, var(--success), var(--success-light));
}

.analytics-card.danger::before {
    background: linear-gradient(90deg, var(--danger), var(--danger-light));
}

.analytics-card .card-body {
    padding: 25px;
}

.analytics-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 20px;
    background: linear-gradient(135deg, rgba(255,255,255,0.1), rgba(255,255,255,0.05));
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
}

.analytics-icon i {
    font-size: 24px;
    color: white;
}

.analytics-data h4 {
    font-size: 16px;
    font-weight: 600;
    color: var(--text-secondary);
    margin-bottom: 5px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.analytics-data p {
    font-size: 28px;
    font-weight: 700;
    margin: 0;
    color: white;
}

/* Chart Container */
.chart-container {
    background: var(--card-bg);
    border-radius: var(--border-radius);
    box-shadow: var(--card-shadow);
    padding: 25px;
    margin-bottom: 30px;
    border: 1px solid rgba(255,255,255,0.05);
}

.chart-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.chart-title {
    font-size: 18px;
    font-weight: 600;
    color: white;
}

/* Filter Section */
.filter-section {
    background: var(--card-bg);
    border-radius: var(--border-radius);
    padding: 20px;
    margin-bottom: 30px;
    box-shadow: var(--card-shadow);
    border: 1px solid rgba(255,255,255,0.05);
}

.filter-section select {
    background-color: rgba(0,0,0,0.2);
    color: var(--text-primary);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 10px 15px;
    font-size: 14px;
    transition: all 0.2s ease;
    font-weight: 500;
}

.filter-section select:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
    outline: none;
}

.filter-section select option {
    background-color: #2b2b2b;
}

.filter-title {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-secondary);
    margin-bottom: 10px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* OKR Table - Design Premium */
.okr-table {
    background: transparent;
    border-collapse: separate;
    border-spacing: 0 8px;
    width: 100%;
}

.okr-table thead th {
    background-color: transparent;
    color: var(--text-secondary);
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 15px;
    border: none;
}

.okr-table tbody tr {
    background: var(--card-bg);
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    border-radius: var(--border-radius);
}

.okr-table tbody tr:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
}

.okr-table tbody td {
    padding: 15px;
    border: none;
    font-size: 14px;
    vertical-align: middle;
}

.okr-table tbody tr td:first-child {
    border-top-left-radius: var(--border-radius);
    border-bottom-left-radius: var(--border-radius);
}

.okr-table tbody tr td:last-child {
    border-top-right-radius: var(--border-radius);
    border-bottom-right-radius: var(--border-radius);
}

/* Project Title Cell */
.project-title {
    font-weight: 600;
    color: white;
    font-size: 14px;
    max-width: 250px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* Progress Circle */
.progress-circle {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: #2c2c2c;
    position: relative;
    margin: 0 auto;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 14px;
    color: white;
    box-shadow: inset 0 0 10px rgba(0,0,0,0.3);
}

.progress-circle::before {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: 50%;
    padding: 3px;
    background: conic-gradient(var(--primary) var(--progress), transparent 0);
    -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
    mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
    -webkit-mask-composite: xor;
    mask-composite: exclude;
}

/* Status Badges */
.status-badge {
    padding: 5px 12px;
    border-radius: 50px;
    font-size: 12px;
    font-weight: 600;
    display: inline-block;
}

.status-in-progress {
    background-color: rgba(255, 193, 7, 0.15);
    color: var(--warning);
    border: 1px solid rgba(255, 193, 7, 0.2);
}

.status-completed {
    background-color: rgba(40, 167, 69, 0.15);
    color: var(--success);
    border: 1px solid rgba(40, 167, 69, 0.2);
}

.status-blocked {
    background-color: rgba(220, 53, 69, 0.15);
    color: var(--danger);
    border: 1px solid rgba(220, 53, 69, 0.2);
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 8px;
    justify-content: center;
}

.btn-action {
    width: 34px;
    height: 34px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: rgba(0,0,0,0.15);
    border: 1px solid rgba(255,255,255,0.08);
    color: var(--text-secondary);
    transition: all 0.2s ease;
}

.btn-action:hover {
    background: rgba(255,255,255,0.1);
    color: white;
}

.btn-action.view:hover { color: var(--info); border-color: var(--info); }
.btn-action.complete:hover { color: var(--success); border-color: var(--success); }
.btn-action.block:hover { color: var(--danger); border-color: var(--danger); }
.btn-action.report:hover { color: var(--warning); border-color: var(--warning); }
.btn-action.reopen:hover { color: var(--primary); border-color: var(--primary); }

/* OKR Score Column */
.okr-score {
    font-weight: 700;
    font-size: 16px;
    color: var(--primary);
    text-align: center;
    background: rgba(0, 123, 255, 0.1);
    padding: 5px 10px;
    border-radius: 8px;
}

/* Pagination Styles */
.pagination-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 30px;
    padding: 10px 0;
}

.pagination {
    display: flex;
    list-style: none;
    padding: 0;
    margin: 0;
    gap: 5px;
}

.pagination li a,
.pagination li span {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 8px;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.08);
    color: var(--text-secondary);
    font-weight: 500;
    text-decoration: none;
    transition: all 0.2s ease;
}

.pagination li a:hover {
    background: rgba(255,255,255,0.1);
    color: white;
}

.pagination li.active span {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

.pagination li.disabled span {
    opacity: 0.5;
    cursor: not-allowed;
}

.items-per-page {
    display: flex;
    align-items: center;
    gap: 10px;
}

.items-per-page label {
    font-size: 14px;
    color: var(--text-secondary);
}

.items-per-page select {
    background-color: rgba(0,0,0,0.2);
    color: var(--text-primary);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 8px 12px;
    font-size: 14px;
}

/* Alert System for Pending Justifications */
.alert-justificativa {
    background: linear-gradient(145deg, #de4c5a, #2e3240);
    border-left: 5px solid var(--info);
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
    margin-bottom: 30px;
    position: relative;
    overflow: hidden;
}

.alert-justificativa::after {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 200px;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(23, 162, 184, 0.05));
    z-index: -1; /* Alterado de 1 para -1 */
}


.alert-justificativa h5 {
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 10px;
    color: white;
}

.alert-justificativa p {
    color: var(--text-secondary);
    margin-bottom: 0;
}

.btn-justificar {
    background: var(--info);
    color: white;
    border: none;
    border-radius: 8px;
    padding: 8px 16px;
    font-weight: 600;
    transition: all 0.2s ease;
    box-shadow: 0 4px 10px rgba(23, 162, 184, 0.3);
}

.btn-justificar:hover {
    background: #1391a5;
    transform: translateY(-2px);
}

.pulse-animation {
    animation: pulse 1.5s infinite;
}

@keyframes pulse {
    0% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.05); opacity: 0.8; }
    100% { transform: scale(1); opacity: 1; }
}

/* Breadcrumbs and Page Title */
.page-title-area {
    display: flex;
    align-items: center;
    padding: 15px 0;
    margin-bottom: 20px;
}

.page-title {
    font-size: 24px;
    font-weight: 700;
    color: white;
    margin: 0;
    display: flex;
    align-items: center;
}

.page-title i {
    font-size: 28px;
    margin-right: 10px;
    background: linear-gradient(45deg, var(--primary), var(--info));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

/* Header Button */
.header-btn {
    padding: 10px 18px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.header-btn-primary {
    background: var(--primary);
    color: white;
    border: none;
    box-shadow: 0 4px 10px rgba(0, 123, 255, 0.3);
}

.header-btn-primary:hover {
    background: var(--primary-dark);
    color: white;
    transform: translateY(-2px);
}

.header-btn i {
    font-size: 16px;
}

/* Modals Design */
.modal-content {
    background-color: #2e2e2e;
    color: #eaeaea;
    border: none;
    border-radius: 12px;
    overflow: hidden;
}

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

.modern-modal .modal-body {
    padding: 20px;
}

.modern-modal .modal-footer {
    background: #25292c;
    border-top: 1px solid #3c4147;
    padding: 15px 20px;
}

.btn-close {
    color: white;
    opacity: 0.8;
    transition: opacity 0.2s;
}

.btn-close:hover {
    opacity: 1;
}

/* Sidebar das abas no modal */
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

/* Project Details in Modal */
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

/* BVP Grid in Modal */
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

.bvp-total-circle {
    margin: 0;
    position: relative;
    width: 100px;
    height: 100px;
}

.bvp-total-circle svg {
    width: 100%;
    height: 100%;
    transform: rotate(-90deg);
}

.bvp-total-circle .circle-bg {
    fill: none;
    stroke: #3c3c3c;
    stroke-width: 4;
}

.bvp-total-circle .circle-progress {
    fill: none;
    stroke-width: 4;
    stroke-linecap: round;
    transition: stroke-dasharray 0.5s ease;
}

.bvp-total-circle.low .circle-progress { stroke: #ff4d4d; }
.bvp-total-circle.medium .circle-progress { stroke: #ffcc00; }
.bvp-total-circle.high .circle-progress { stroke: #00b7ff; }

.bvp-total-circle span {
    position: absolute; 
    top: 50%; 
    left: 50%; 
    transform: translate(-50%, -50%);
    font-size: 20px;
    color: #eaeaea;
    font-weight: bold;
}
/* Estilos aprimorados para tarefas */
.task-item {
    background: #2e3240;
    border: 1px solid #52555d;
    border-radius: 6px;
    padding: 15px;
    margin-bottom: 15px;
    transition: background 0.3s ease;
    position: relative;
}

.task-item:hover {
    background: #353943;
}

/* Novo wrapper para garantir o espaço para o badge de Key Result */
.task-content-wrapper {
    padding-top: isKeyResult ? 28px : 0;
}

/* Layout principal da tarefa */
.task-main {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 10px;
    width: 100%;
}

/* Área do título da tarefa com quebra de linha */
.task-title-area {
    display: flex;
    align-items: flex-start;
    flex: 1;
    min-width: 0;
    margin-right: 10px; /* Espaço para os botões */
}

/* Estilo do título com quebra de linha */
.task-title {
    word-wrap: break-word;
    word-break: break-word;
    overflow-wrap: break-word;
    hyphens: auto;
}

/* Container dos botões de ação */
.task-buttons {
    display: flex;
    white-space: nowrap;
    flex-shrink: 0;
    align-items: center;
}

/* Badge de Key Result melhorado */
.key-result-badge {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    background: rgba(253, 216, 53, 0.2);
    color: #fdd835;
    font-size: 0.85rem;
    font-weight: 600;
    padding: 4px 10px;
    border-radius: 6px 6px 0 0;
    text-align: center;
    z-index: 2;
}

.key-result-badge i {
    margin-right: 5px;
}

/* Estilos para tarefa marcada como Key Result */
.task-item.key-result {
    background-color: rgba(255, 249, 196, 0.07);
    border: 1px solid rgba(253, 216, 53, 0.17);
    border-top: 2px solid #fdd835;
}

/* Preservar quebras de linha nas descrições */
.task-description-formatted {
    white-space: pre-line; /* Esta é a chave! Preserva quebras de linha */
    word-wrap: break-word;
    word-break: break-word;
    overflow-wrap: break-word;
    line-height: 1.4;
    font-size: 0.9rem;
    color: #b8c2cc;
}

/* Melhorar a aparência da descrição */
.task-description {
    margin-top: 10px;
    padding-top: 8px;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

/* Tarefa concluída */
.tarefa-concluida {
    text-decoration: line-through;
    opacity: 0.7;
}

/* Responsividade para mobile */
@media (max-width: 576px) {
    .task-main {
        flex-direction: column;
        align-items: stretch;
    }
    
    .task-title-area {
        margin-right: 0;
        margin-bottom: 10px;
    }
    
    .task-buttons {
        align-self: flex-end;
    }
}

/* Custom styles para o drag and drop */
.ui-state-highlight {
    background: #444;
    border: 1px dashed #999;
    height: 2.5em;
    line-height: 1.2em;
    margin-bottom: 15px;
}

.folder-icon-highlight {
    color: orange !important;
}

/* Task Container in Modal */
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

.nested-list {
    list-style: none;
    padding-left: 0;
}

.subtask-item {
    margin-left: 20px;
    border-left: 2px solid #555;
    padding-left: 10px;
}

.drag-handle {
    cursor: move;
    margin-right: 8px;
}

  /* Novo CSS para ajustar o layout das tarefas */
    .task-header {
        width: 100%;
    }
    
    .task-main {
        width: 100%;
        gap: 5px;
    }
    
  /* Estilos para o sistema de recolher/expandir descrição */
.task-content {
    max-height: 1000px;
    overflow: hidden;
    transition: max-height 0.3s ease;
}

.task-content.collapsed {
    max-height: 0;
}

.toggle-description-btn {
    background: none;
    border: none;
    color: #a1aab2;
    font-size: 0.8rem;
    padding: 4px 0;
    margin-top: 4px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    transition: color 0.2s ease;
}

.toggle-description-btn:hover {
    color: #00b7ff;
}

.toggle-description-btn i {
    transition: transform 0.3s ease;
    margin-right: 5px;
}

.toggle-description-btn.expanded i {
    transform: rotate(180deg);
}

/* Estilos para Key Result */
.task-item.key-result {
    border-left: 2px solid #ffd700;
    background-color: rgba(255, 215, 0, 0.03);
}

.key-result-indicator {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-left: 6px;
    font-size: 0.75rem;
    color: #ffd700;
    vertical-align: middle;
}

.key-result-indicator i {
    font-size: 0.75rem;
}

/* Estilos gerais da tarefa */
.task-left {
    display: flex;
    flex-direction: row;
    align-items: flex-start;
    width: 100%;
}

.task-text {
    flex: 1;
    min-width: 0;
    width: 100%;
}

.task-title {
    word-wrap: break-word;
    word-break: break-word;
    overflow-wrap: break-word;
    hyphens: auto;
    display: inline-block;
}


.tarefa-concluida {
    text-decoration: line-through;
    opacity: 0.7;
}

/* Responsividade para descrição no mobile */
@media (max-width: 576px) {
    .task-description {
        width: 100%;
    }
    
    .d-flex.justify-content-between {
        flex-direction: column;
        align-items: flex-start;
    }

    .d-flex.flex-nowrap.align-items-start {
        margin-top: 10px;
        margin-left: 30px;
    }
}

.task-item.key-result {
    background-color: #fff9c412;
    border: 1px solid #fdd8352b;
    position: relative;
}

/* Estilos para garantir que os botões fiquem alinhados corretamente */
    .task-item .task-title {
        word-wrap: break-word;
        word-break: break-word;
        overflow-wrap: break-word;
        hyphens: auto;
    }
    
    /* Mantenha os botões de ação juntos e impede que quebrem para a próxima linha */
    .task-item .d-flex.flex-nowrap {
        white-space: nowrap;
        flex-shrink: 0;
    }
    
    /* Evita que o texto da tarefa empurre os botões para fora */
    .task-item .d-flex.justify-content-between {
        width: 100%;
    }

.key-result-icon {
    position: absolute;
    top: 6px;
    right: 8px;
    color: #fdd835;
    font-size: 1.2rem;
    cursor: default;
}

.key-result-icon:hover {
    color: #ffeb3b;
}

/* Attachment Container in Modal */
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

/* Activity List in Modal */
.activity-list {
    max-height: 350px;
    overflow-y: auto;
    margin-bottom: 20px;
}

.activity-form {
    background: #2e3240;
    padding: 15px;
    border-radius: 8px;
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

/* Dependencies Container in Modal */
.dependencies-container {
    max-height: 400px;
    overflow-y: auto;
    padding-right: 10px;
}

/* Form Fields in Modal */
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

.form-control:focus, 
.form-select:focus {
    border-color: #007bff;
    box-shadow: 0 0 5px rgba(0, 123, 255, 0.5);
    outline: none;
}

.form-text { 
    font-size: 0.875rem; 
    color: #7a8290; 
}

/* Buttons in Modal */
.btn {
    font-weight: 500;
    border-radius: 6px;
    padding: 8px 16px;
    transition: all 0.2s ease;
}

.btn-primary {
    background-color: var(--primary);
    border-color: var(--primary);
}

.btn-primary:hover {
    background-color: var(--primary-dark);
    border-color: var(--primary-dark);
    transform: translateY(-2px);
}

.btn-secondary {
    background-color: #454d55;
    border-color: #454d55;
}

.btn-secondary:hover {
    background-color: #5a6268;
    border-color: #5a6268;
    transform: translateY(-2px);
}

.btn-success {
    background-color: var(--success);
    border-color: var(--success);
}

.btn-success:hover {
    background-color: var(--success-dark);
    border-color: var(--success-dark);
    transform: translateY(-2px);
}

.btn-danger {
    background-color: var(--danger);
    border-color: var(--danger);
}

.btn-danger:hover {
    background-color: var(--danger-dark);
    border-color: var(--danger-dark);
    transform: translateY(-2px);
}

.btn-info {
    background-color: var(--info);
    border-color: var(--info);
}

.btn-info:hover {
    background-color: #138496;
    border-color: #138496;
    transform: translateY(-2px);
}

/* Lista de projetos pendentes */
#listaPendenteJustificativa a {
    background: #2e3240;
    color: #eaeaea;
    border: 1px solid #3c4147;
    margin: 5px 0;
    border-radius: 8px;
    transition: all 0.3s ease;
}

#listaPendenteJustificativa a:hover {
    background: #353943;
    transform: translateX(5px);
}

.justificar-projeto {
    background: var(--info);
    color: white;
    border-radius: 6px;
    font-size: 13px;
    border: none;
    transition: all 0.2s ease;
}

.justificar-projeto:hover {
    background: #138496;
    transform: scale(1.05);
}

/* Custom Scrollbar para todo o sistema */
::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

::-webkit-scrollbar-track {
    background: rgba(255,255,255,0.05);
    border-radius: 10px;
}

::-webkit-scrollbar-thumb {
    background: rgba(255,255,255,0.1);
    border-radius: 10px;
}

::-webkit-scrollbar-thumb:hover {
    background: rgba(255,255,255,0.2);
}

/* Feedback visual do Sortable */
.ui-state-highlight {
    background: #444;
    border: 1px dashed #999;
    height: 2.5em;
    line-height: 1.2em;
    margin-bottom: 15px;
}

.folder-icon-highlight {
    color: orange !important;
}

/* Responsividade para dispositivos móveis */
@media (max-width: 767.98px) {
    .analytics-icon {
        width: 45px;
        height: 45px;
    }
    
    .analytics-icon i {
        font-size: 18px;
    }
    
    .analytics-data h4 {
        font-size: 14px;
    }
    
    .analytics-data p {
        font-size: 22px;
    }
    
    .progress-circle {
        width: 45px;
        height: 45px;
        font-size: 12px;
    }
    
    .project-title {
        max-width: 150px;
    }
    
    .btn-action {
        width: 30px;
        height: 30px;
    }
    
    .okr-score {
        font-size: 14px;
    }
    
    .header-btn {
        padding: 8px 15px;
        font-size: 13px;
    }
}
/* Adicionar ao bloco de estilos */
#modalProjetosPendentes .modal-body {
    max-height: 70vh;
    overflow-y: auto;
}

#listaPendenteJustificativa {
    margin-top: 10px;
}

#listaPendenteJustificativa .list-group-item:last-child {
    border-radius: 0 0 8px 8px;
}

.fade-out {
    transition: all 0.5s;
    opacity: 0;
    transform: translateX(-20px);
}

.setor-header {
    position: sticky;
    top: 0;
    z-index: 1;
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
}

/* Estilo para contadores */
.contador-setor {
    font-size: 0.9rem;
    padding: 3px 8px;
    border-radius: 50px;
    background: rgba(0, 183, 255, 0.2);
    color: #00b7ff;
    margin-left: 8px;
}

/* Estilos limpos e discretos para Key Result */
.task-item.key-result {
    border-left: 2px solid #ffd700;
    background-color: rgba(255, 215, 0, 0.03);
}

.key-result-indicator {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-left: 6px;
    font-size: 0.75rem;
    color: #ffd700;
    vertical-align: middle;
}

.key-result-indicator i {
    font-size: 0.75rem;
}

/* Estilos gerais para tarefa */
.task-item {
    background: #2e3240;
    border: 1px solid #52555d;
    border-radius: 6px;
    padding: 15px;
    margin-bottom: 15px;
    transition: background 0.3s ease;
    position: relative;
}

.task-item:hover {
    background: #353943;
}

.task-title {
    word-wrap: break-word;
    word-break: break-word;
    overflow-wrap: break-word;
    hyphens: auto;
}

.task-description {
    margin-top: 10px;
    padding-top: 8px;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.tarefa-concluida {
    text-decoration: line-through;
    opacity: 0.7;
}

/* Responsividade */
@media (max-width: 576px) {
    .d-flex.justify-content-between {
        flex-direction: column;
        align-items: flex-start;
    }

    .d-flex.flex-nowrap.align-items-start {
        margin-top: 10px;
        margin-left: 30px;
    }
}
    </style>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<div class="preloader"></div>
<div id="main-wrapper">
    <header class="topbar">
        <?php include 'header.php'; ?>
    </header>
    <?php include 'sidebar.php'; ?>

    <div class="page-wrapper">
        <!-- Título e breadcrumbs com design premium -->
        <div class="row page-titles">
            <div class="container-fluid">
                <h3 class="text-themecolor mb-0"><i class="mdi mdi-chart-line"></i> OKR - Gestão Ágil</h3>
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
            
            <!-- Sistema de Alertas de Justificativa -->
            <div id="alertaJustificativa" class="alert-justificativa" style="display: none;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5>
                            <i class="fa fa-exclamation-triangle me-2 text-warning pulse-animation"></i>
                            <span id="alertaJustificativaTexto">Atenção!</span>
                        </h5>
                        <p id="alertaJustificativaDetalhes"></p>
                    </div>
                    <div class="ms-3">
                        <button type="button" id="btnJustificarPendentes" class="btn-justificar mb-2 d-block">
                            <i class="fa fa-check-circle me-1"></i> Registrar Agora
                        </button>
                        
                    </div>
                </div>
            </div>
            
            <!-- Cards de métricas com design premium -->
            <div class="row">
                <div class="col-lg-4 col-md-6">
                    <div class="analytics-card">
                        <div class="card-body d-flex align-items-center">
                            <div class="analytics-icon" style="background: linear-gradient(45deg, var(--primary), #3a8eff);">
                                <i class="fa fa-bullseye"></i>
                            </div>
                            <div class="analytics-data">
                                <h4>Total de OKRs</h4>
                                <p><?php echo $totalOKR; ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="analytics-card success">
                        <div class="card-body d-flex align-items-center">
                            <div class="analytics-icon" style="background: linear-gradient(45deg, var(--success), #4fd368);">
                                <i class="fa fa-check"></i>
                            </div>
                            <div class="analytics-data">
                                <h4>OKRs Finalizados</h4>
                                <p><?php echo $okrsFinalizados; ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="analytics-card danger">
                        <div class="card-body d-flex align-items-center">
                            <div class="analytics-icon" style="background: linear-gradient(45deg, var(--danger), #e05c69);">
                                <i class="fa fa-lock"></i>
                            </div>
                            <div class="analytics-data">
                                <h4>OKRs Bloqueados</h4>
                                <p><?php echo $okrsBloqueados; ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
   
            
            <!-- Filtros redesenhados -->
<div class="filter-section">
  <form method="GET" action="okr" id="filtroForm" class="row gx-3 gy-2 align-items-end">
    
    <div class="col-md-3">
      <label for="filtroAno" class="filter-title">Ano</label>
      <select name="ano" id="filtroAno" class="form-select">
        <option value="">Todos os Anos</option>
        <?php foreach($anosDisponiveis as $linhaAno):
          $year = $linhaAno['ano'];
          $selected = ($anoFiltro == $year) ? 'selected' : '';
        ?>
        <option value="<?php echo $year; ?>" <?php echo $selected; ?>>
          <?php echo $year; ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>
    
    <div class="col-md-3">
      <label for="filtroSetor" class="filter-title">Setor</label>
      <select name="setor" id="filtroSetor" class="form-select">
        <option value="">Todos Setores</option>
        <?php foreach($setores as $s):
          $selected = ($setorFiltro==$s['id']) ? 'selected' : '';
        ?>
        <option value="<?php echo $s['id']; ?>" <?php echo $selected; ?>>
          <?php echo htmlspecialchars($s['nome_do_setor']); ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>
    
    <div class="col-md-3">
      <label for="filtroPrazo" class="filter-title">Prazo</label>
      <select name="prazo" id="filtroPrazo" class="form-select">
        <option value="">Todos Prazos</option>
        <option value="curto"  <?php echo ($prazoFiltro=='curto'?'selected':''); ?>>Curto</option>
        <option value="medio"  <?php echo ($prazoFiltro=='medio'?'selected':''); ?>>Médio</option>
        <option value="longo"  <?php echo ($prazoFiltro=='longo'?'selected':''); ?>>Longo</option>
      </select>
    </div>
    
    <div class="col-md-3">
      <label for="filtroStatus" class="filter-title">Status</label>
      <select name="status" id="filtroStatus" class="form-select">
        <option value="">Todos Status</option>
        <option value="andamento"  <?php echo ($statusFiltro=='andamento'?'selected':''); ?>>Em Andamento</option>
        <option value="bloqueado"  <?php echo ($statusFiltro=='bloqueado'?'selected':''); ?>>Bloqueado</option>
        <option value="concluido" <?php if($statusFiltro=='concluido') echo 'selected'; ?>>Concluído</option>
        </select>
    </div>
    
  </form>
</div>

            
            <!-- Botão de Voltar redesenhado -->
            <div class="d-flex justify-content-end mb-4">
                <button class="header-btn header-btn-primary" onclick="location.href='projetos.php'">
                    <i class="fa fa-arrow-left"></i> Voltar para Projetos
                </button>
            </div>
            
            <!-- Tabela de Projetos OKR sofisticada -->
            <div class="table-responsive">
    <table class="okr-table" id="okrTable">
        <thead>
            <tr>
                <th>ID</th>
                <th>Projeto</th>
                <th>Setor</th>
                <th>Responsável</th>
                <th>Score</th>
                <th>Progresso</th>
                <th>Em OKR</th>
                <th>Bloqueado</th>
                <th>Status</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!empty($projetos)): ?>
            <?php foreach ($projetos as $proj):
                // Progresso das subtarefas
                $stmtCount = $pdo->prepare("
                    SELECT COUNT(*) as total, SUM(concluida) as concluidas
                      FROM subtarefas_projetos
                     WHERE projeto_id = :pid
                ");
                $stmtCount->execute([':pid'=>$proj['Id']]);
                $count = $stmtCount->fetch(PDO::FETCH_ASSOC);
                $totalTasks     = $count['total'] ?: 0;
                $completedTasks = $count['concluidas'] ?: 0;
                $progress       = $totalTasks > 0 ? round(($completedTasks/$totalTasks)*100) : 0;

                // Dias OKR
                $daysOKR = 0;
                $dtOkr = $proj['dtliberadookr'] ?? null;
                if (!empty($dtOkr) && $dtOkr != '0000-00-00 00:00:00') {
                    $date1 = new DateTime($dtOkr);
                    $date2 = new DateTime();
                    $diff  = $date1->diff($date2);
                    $daysOKR = $diff->days;
                }
                $textoOKR = $daysOKR . " dia(s)";

                // Bloqueio
                $bloqueadoDias = "—";
                if (strtolower($proj['Status'])=='bloqueado' && !empty($proj['DataBloqueio']) && $proj['DataBloqueio']!='0000-00-00 00:00:00') {
                    $dbDate   = new DateTime($proj['DataBloqueio']);
                    $diffBlq  = $dbDate->diff(new DateTime());
                    $bloqueadoDias = $diffBlq->days . " dia(s)";
                }

                // Situação / Classe da linha
                $statusBadge = '';
                if (strtolower($proj['Status']) == 'andamento') {
                    $statusBadge = '<span class="status-badge status-in-progress"><i class="fa fa-clock me-1"></i> Em Andamento</span>';
                } elseif (strtolower($proj['Status']) == 'bloqueado') {
                    $statusBadge = '<span class="status-badge status-blocked"><i class="fa fa-lock me-1"></i> Bloqueado</span>';
                } elseif (strtolower($proj['Status']) == 'concluido') {
                    $statusBadge = '<span class="status-badge status-completed"><i class="fa fa-check-circle me-1"></i> Concluído</span>';
                }
                
                // Nome do setor
                $nomeSetor = 'Setor '.$proj['SetorRelacionadoId'];
                foreach($setores as $s) {
                    if($s['id']==$proj['SetorRelacionadoId']) {
                        $nomeSetor = $s['nome_do_setor'];
                        break;
                    }
                }
                $temPermissaoGestao = in_array($perfilAcesso, [1, 2, 9]);
            ?>
            <tr>
                <td class="text-center"><?php echo $proj['Id']; ?></td>
                <td>
                    <div class="project-title"><?php echo htmlspecialchars($proj['NomeProjeto']); ?></div>
                </td>
                <td><?php echo htmlspecialchars($nomeSetor); ?></td>
                <td><?php echo htmlspecialchars($proj['NomeResponsavel']); ?></td>
                <td>
                    <div class="okr-score"><?php echo $proj['ValorOKR']; ?></div>
                </td>
                <td>
                    <!-- Gauge modernizado -->
                    <div class="progress-circle" style="--progress: <?php echo $progress; ?>%">
                        <?php echo $progress; ?>%
                    </div>
                </td>
                <td class="text-center"><?php echo $textoOKR; ?></td>
                <td class="text-center"><?php echo $bloqueadoDias; ?></td>
                <td class="text-center"><?php echo $statusBadge; ?></td>
                <td>
                <div class="action-buttons">
                    <!-- Botão Detalhar (sempre presente) -->
                    <button class="btn-action view" data-bs-toggle="tooltip" title="Detalhar"
                            onclick="openDetalhesModal(<?php echo $proj['Id']; ?>)">
                        <i class="fa fa-eye"></i>
                    </button>
                   <!-- Botão Remover OKR (apenas para perfis 1,2,9 e projetos não concluídos) -->
                    <?php if ($temPermissaoGestao && strtolower($proj['Status']) !== 'concluido'): ?>
                        <form action="okr.php" method="post" style="display:inline;" data-bs-toggle="tooltip" title="Remover OKR" onsubmit="return confirm('Remover este projeto do OKR?');">
                            <input type="hidden" name="acao" value="remover_okr">
                            <input type="hidden" name="projeto_id" value="<?php echo $proj['Id']; ?>">
                            <button type="submit" class="btn-action btn-remover-okr" title="Remover OKR">
                                <i class="fa fa-times-circle"></i>
                            </button>
                        </form>
                    <?php endif; ?>
                    <!-- Demais botões conforme status -->
                    <?php if ($proj['Status'] === 'concluido'): ?>
                        <button class="btn-action report" data-bs-toggle="tooltip" title="PDF Projeto"
                                onclick="gerarRelatorioPDF(<?php echo $proj['Id']; ?>)">
                            <i class="fa fa-file-pdf"></i>
                        </button>
                        <?php if ($temPermissaoGestao): ?>
                        <button class="btn-action reopen" data-bs-toggle="tooltip" title="Reabrir"
                                onclick="reabrirProjeto(<?php echo $proj['Id']; ?>)">
                            <i class="fa fa-undo"></i>
                        </button>
                        <button class="btn-action block" data-bs-toggle="tooltip" title="Bloquear"
                                onclick="openBloqueiarModal(<?php echo $proj['Id']; ?>)">
                            <i class="fa fa-ban"></i>
                        </button>
                        <?php endif; ?>
                    <?php elseif ($proj['Status'] === 'bloqueado'): ?>
                        <?php if ($temPermissaoGestao): ?>
                        <button class="btn-action complete" data-bs-toggle="tooltip" title="Finalizar"
                                onclick="openFinalizarModal(<?php echo $proj['Id']; ?>)">
                            <i class="fa fa-flag-checkered"></i>
                        </button>
                        <button class="btn-action reopen" data-bs-toggle="tooltip" title="Desbloquear"
                                onclick="desbloquearProjeto(<?php echo $proj['Id']; ?>)">
                            <i class="fa fa-unlock"></i>
                        </button>
                        <?php endif; ?>
                    <?php else: ?>
                        <?php if ($temPermissaoGestao): ?>
                        <button class="btn-action complete" data-bs-toggle="tooltip" title="Finalizar"
                                onclick="openFinalizarModal(<?php echo $proj['Id']; ?>)">
                            <i class="fa fa-flag-checkered"></i>
                        </button>
                        <button class="btn-action block" data-bs-toggle="tooltip" title="Bloquear"
                                onclick="openBloqueiarModal(<?php echo $proj['Id']; ?>)">
                            <i class="fa fa-ban"></i>
                        </button>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="10" class="text-center py-4">
                    <i class="fa fa-info-circle me-2"></i> Nenhum OKR encontrado com os filtros atuais.
                </td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

            
            <!-- Paginação redesenhada -->
            <?php if ($totalPages > 1): ?>
            <div class="pagination-container">
                <ul class="pagination">
                    <?php
                    function buildQueryUrl($extraParams=[]) {
                        $query = $_GET;
                        unset($query['page'], $query['limit']);
                        $query = array_merge($query, $extraParams);
                        return 'okr?' . http_build_query($query);
                    }

                    if ($page > 1) {
                        echo '<li><a href="'. buildQueryUrl(['page' => 1, 'limit' => $limit]) .'"><i class="fa fa-angle-double-left"></i></a></li>';
                    } else {
                        echo '<li class="disabled"><span><i class="fa fa-angle-double-left"></i></span></li>';
                    }
                    if ($page > 1) {
                        echo '<li><a href="'. buildQueryUrl(['page' => $page-1, 'limit' => $limit]) .'"><i class="fa fa-angle-left"></i></a></li>';
                    } else {
                        echo '<li class="disabled"><span><i class="fa fa-angle-left"></i></span></li>';
                    }

                    $maxLinks = 5;
                    $start = max(1, $page - floor($maxLinks/2));
                    $end = min($totalPages, $start + $maxLinks - 1);

                    for ($i = $start; $i <= $end; $i++) {
                        if ($i == $page) {
                            echo '<li class="active"><span>'.$i.'</span></li>';
                        } else {
                            echo '<li><a href="'. buildQueryUrl(['page' => $i, 'limit' => $limit]) .'">'.$i.'</a></li>';
                        }
                    }

                    if ($page < $totalPages) {
                        echo '<li><a href="'. buildQueryUrl(['page' => $page+1, 'limit' => $limit]) .'"><i class="fa fa-angle-right"></i></a></li>';
                    } else {
                        echo '<li class="disabled"><span><i class="fa fa-angle-right"></i></span></li>';
                    }

                    if ($page < $totalPages) {
                        echo '<li><a href="'. buildQueryUrl(['page' => $totalPages, 'limit' => $limit]) .'"><i class="fa fa-angle-double-right"></i></a></li>';
                    } else {
                        echo '<li class="disabled"><span><i class="fa fa-angle-double-right"></i></span></li>';
                    }
                    ?>
                </ul>
                
                <!-- Seletor de itens por página -->
                <div class="items-per-page">
                    <label for="itemsPerPage">Itens por página:</label>
                    <select id="itemsPerPage" class="form-select form-select-sm">
                        <?php foreach ($validLimits as $val): ?>
                            <option value="<?php echo $val; ?>" <?php echo $limit == $val ? 'selected' : ''; ?>><?php echo $val; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <?php endif; ?>

            <!-- MODAL DETALHES DO PROJETO -->
<div class="modal" id="modalDetalhesProjeto">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content modern-modal">
            <div class="modal-header d-flex justify-content-between align-items-center">
                <h5 class="modal-title"><i class="fa fa-info-circle me-2"></i> Detalhes do Projeto (OKR)</h5>
                <button type="button" class="btn-close btn-close-white" onclick="hideModal('modalDetalhesProjeto')"></button>
            </div>
            <div class="modal-body p-0">
                <div class="row g-0">
                    <!-- Sidebar com Navegação das Abas -->
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
                                <a class="nav-link" id="andamento-tab" data-bs-toggle="pill" href="#andamento" role="tab">
                                    <i class="fa fa-info-circle me-2"></i> Andamento
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="dependencias-tab" data-bs-toggle="pill" href="#dependencias" role="tab">
                                    <i class="fa fa-link me-2"></i> Projetos Vinculados
                                </a>
                            </li>
                        </ul>
                    </div>

                    <!-- Conteúdo das Abas -->
                    <div class="col-md-9 tab-content p-4">
                        <!-- ABA: Visão Geral -->
                        <div class="tab-pane fade show active" id="overview" role="tabpanel">
                            <div id="projectDetails" class="project-details"></div>
                        </div>
                        <!-- ABA: Orçamento Macro -->
                        <div class="tab-pane fade" id="orcamento" role="tabpanel">
                            <div id="orcamentoDetails" class="orcamento-details"></div>
                        </div>
                        <!-- ABA: Tarefas -->
                        <div class="tab-pane fade" id="tarefas" role="tabpanel">
                            <div class="mb-3">
                                <div class="progress" style="height: 20px;">
                                    <div class="progress-bar progress-bar-striped bg-success"
                                         id="projectProgressBar"
                                         role="progressbar" style="width: 0%;" aria-valuenow="0"
                                         aria-valuemin="0" aria-valuemax="100">0%
                                    </div>
                                </div>
                            </div>
                            <div id="listaTarefas" class="task-container"></div>
                            <form id="formNovaTarefa" onsubmit="return false;" class="mt-4 task-form">
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
                                <button type="submit" class="btn btn-success w-100">
                                    <i class="fa fa-plus me-2"></i> Adicionar Tarefa
                                </button>
                            </form>
                        </div>
                        <!-- ABA: Anexos -->
                        <div class="tab-pane fade" id="anexos" role="tabpanel">
                            <div id="listaAnexos" class="attachment-container"></div>
                            <hr class="my-4">
                            <form id="formUploadAnexo" onsubmit="return false;" enctype="multipart/form-data" class="attachment-form">
                                <input type="hidden" name="projetoId" id="anexosProjetoId">
                                <div class="mb-3">
                                    <label for="arquivoAnexo" class="form-label">Novo Anexo</label>
                                    <input type="file" class="form-control" id="arquivoAnexo" name="arquivo" required>
                                </div>
                                <button type="submit" class="btn btn-success">
                                    <i class="fa fa-upload me-2"></i> Enviar Anexo
                                </button>
                            </form>
                        </div>
                        <!-- ABA: Comentários -->
                        <div class="tab-pane fade" id="atividades" role="tabpanel">
                            <div id="listaAtividades" class="activity-list"></div>
                            <form id="formNovaAtividade" onsubmit="return false;" class="activity-form mt-4">
                                <input type="hidden" name="projetoId" id="atividadesProjetoId">
                                <div class="mb-3">
                                    <label for="textoAtividade" class="form-label">Novo Comentário</label>
                                    <textarea class="form-control" id="textoAtividade" name="comentario" rows="3" required></textarea>
                                </div>
                                <button type="submit" class="btn btn-info w-100">
                                    <i class="fa fa-comment me-2"></i> Adicionar Comentário
                                </button>
                            </form>
                        </div>
                        <!-- ABA: Andamento (Justificativas) -->
                        <div class="tab-pane fade" id="andamento" role="tabpanel">
                            <div id="listaJustificativas" class="activity-list"></div>
                            <form id="formNovaJustificativa" onsubmit="return false;" class="activity-form mt-4">
                                <input type="hidden" name="projetoId" id="justificativasProjetoId">
                                <div class="mb-3">
                                    <label for="textoJustificativa" class="form-label">Novo Registro de Andamento</label>
                                    <textarea class="form-control" id="textoJustificativa" name="justificativa" rows="3" required></textarea>
                                </div>
                                <button type="submit" class="btn btn-info w-100">
                                    <i class="fa fa-upload me-2"></i> Enviar
                                </button>
                            </form>
                        </div>
                        <!-- ABA: Dependências -->
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

            <!-- Modal: Editar Tarefa -->
            <div class="modal" id="modalEditarTarefa">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content modern-modal">
                        <div class="modal-header">
                            <h5 class="modal-title"><i class="fa fa-edit me-2"></i> Editar Tarefa</h5>
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
                            <button type="button" class="btn btn-secondary" onclick="hideModal('modalEditarTarefa')">
                                <i class="fa fa-times me-1"></i> Cancelar
                            </button>
                            <button type="button" class="btn btn-primary" onclick="salvarEdicaoTarefa()">
                                <i class="fa fa-save me-1"></i> Salvar
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal: Finalizar Projeto -->
            <div class="modal fade" id="modalFinalizarProjeto" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content modern-modal">
                        <div class="modal-header">
                            <h5 class="modal-title"><i class="fa fa-flag-checkered me-2"></i> Finalizar Projeto OKR</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Progresso Atual: <span id="finalizarProgressoLabel">0%</span></label>
                                <div class="progress">
                                    <div id="finalizarProgressBar" class="progress-bar bg-success" role="progressbar"
                                         style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
                                </div>
                            </div>
                            <form method="POST" id="formFinalizarProjeto">
                                <input type="hidden" name="acao" value="finalizar">
                                <input type="hidden" name="projeto_id" id="finalizarProjetoId" value="">
                                <div class="mb-3">
                                    <label for="licoes" class="form-label">Lições Aprendidas e Melhorias</label>
                                    <textarea name="licoes" id="licoes" class="form-control" rows="3"></textarea>
                                </div>
                                <div class="mb-3">
                                    <p class="small text-muted">O tempo de execução é calculado a partir da data de criação do projeto.</p>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                        <i class="fa fa-times me-1"></i> Cancelar
                                    </button>
                                    <button type="submit" class="btn btn-success">
                                        <i class="fa fa-check me-1"></i> Finalizar Projeto
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal: Bloquear Projeto -->
            <div class="modal fade" id="modalBloqueiarProjeto" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content modern-modal">
                        <div class="modal-header">
                            <h5 class="modal-title"><i class="fa fa-ban me-2"></i> Bloquear Projeto OKR</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Progresso Atual: <span id="bloquearProgressoLabel">0%</span></label>
                                <div class="progress">
                                    <div id="bloquearProgressBar" class="progress-bar bg-danger" role="progressbar"
                                         style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
                                </div>
                            </div>
                            <form method="POST" id="formBloquearProjeto">
                                <input type="hidden" name="acao" value="bloquear">
                                <input type="hidden" name="projeto_id" id="bloquearProjetoId" value="">
                                <div class="mb-3">
                                    <label for="motivo" class="form-label">Motivo de Bloqueio</label>
                                    <textarea name="motivo" id="motivo_bloqueio" class="form-control" rows="3" required></textarea>
                                </div>
                                <div class="mb-3">
                                    <p class="small text-muted">Informe o motivo para que o projeto seja bloqueado.</p>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                        <i class="fa fa-times me-1"></i> Cancelar
                                    </button>
                                    <button type="submit" class="btn btn-danger">
                                        <i class="fa fa-ban me-1"></i> Bloquear Projeto
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Lista de Projetos Pendentes -->
            <div class="modal" id="modalProjetosPendentes">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content modern-modal">
                        <div class="modal-header">
                            <h5 class="modal-title"><i class="fa fa-exclamation-triangle me-2 text-info"></i> Projetos Pendentes de Registro de Andamento</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                        </div>
                        <div class="modal-body">
                            <p class="text-light">Os seguintes projetos precisam de registro de andamento semanal:</p>
                            <div class="list-group" id="listaPendenteJustificativa" style="max-height: 60vh; overflow-y: auto;">
                                <!-- Lista de projetos será inserida aqui via JavaScript -->
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal: Visualizar Motivo de Bloqueio -->
            <div class="modal fade" id="modalMotivoBloqueio" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content modern-modal">
                        <div class="modal-header">
                            <h5 class="modal-title"><i class="fa fa-info-circle me-2"></i> Motivo do Bloqueio</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                        </div>
                        <div class="modal-body">
                            <p id="motivoBloqueioText">Carregando...</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class="fa fa-times me-1"></i> Fechar
                            </button>
                        </div>
                    </div>
                </div>
            </div>

        </div><!-- container-fluid -->
        <?php include 'footer.php'; ?>
    </div> <!-- page-wrapper -->
</div> <!-- main-wrapper -->

<!-- SCRIPTS PRINCIPAIS -->
<script src="assets/libs/jquery/dist/jquery.min.js"></script>
<script src="assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
<script src="dist/js/app.min.js"></script>
<script src="dist/js/app.init.dark.js"></script>
<script src="dist/js/app-style-switcher.js"></script>
<script src="assets/libs/perfect-scrollbar/dist/perfect-scrollbar.jquery.min.js"></script>
<script src="assets/extra-libs/sparkline/sparkline.js"></script>
<script src="dist/js/waves.js"></script>
<script src="dist/js/sidebarmenu.js"></script>
<script src="dist/js/feather.min.js"></script>
<script src="dist/js/custom.min.js"></script>

<!-- jQuery UI para sortable -->
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>

<script>
// ---------------------------------------------------------
// Configurações iniciais e animações
// ---------------------------------------------------------
document.addEventListener('DOMContentLoaded', function() {
    // Remover loader ao carregar a página
    document.querySelector('.preloader').style.display = 'none';
    
    // Inicializar tooltips do Bootstrap
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));

    // Inicializar modais Bootstrap
    const modais = document.querySelectorAll('.modal');
    modais.forEach(modal => {
        const modalBS = new bootstrap.Modal(modal);
        modal._modalBS = modalBS;
    });
    
    // Inicializar filtros de busca
    initFiltros();
    
    // Inicializar eventos dos formulários
    initFormHandlers();
    
    // Se estiver na página OKR, inicializa o gráfico
    if (document.getElementById('okrBarChart')) {
        initChart();
    }
    
    // CRUCIAL: Remover listeners duplicados de justificativas 
    removeJustificativaListeners();
    setupJustificativaSystem();
    setupFormJustificativa();
});

// Remover listeners duplicados para evitar cadastro duplo
function removeJustificativaListeners() {
    // Remove qualquer listener jQuery existente (elimina a duplicidade)
    $(document).off('submit', '#formNovaJustificativa');
    
    // Se necessário, clona e substitui o elemento para limpar todos os listeners
    const formNovaJustificativa = document.getElementById('formNovaJustificativa');
    if (formNovaJustificativa) {
        const newForm = formNovaJustificativa.cloneNode(true);
        formNovaJustificativa.parentNode.replaceChild(newForm, formNovaJustificativa);
    }
}

// Função para readicionar o event listener de justificativa
function setupFormJustificativa() {
    // Attach event listener ao formulário de justificativa
    $(document).off('submit', '#formNovaJustificativa');
    $(document).on('submit', '#formNovaJustificativa', function(e) {
        e.preventDefault();
        
        const projetoId = document.getElementById('justificativasProjetoId').value;
        const justificativa = document.getElementById('textoJustificativa').value.trim();
        
        // Verificar se temos dados de semana específica
        const semanaInput = document.getElementById('semanaJustificativa');
        const dataQuintaInput = document.getElementById('dataQuintaJustificativa');
        
        const semana = semanaInput ? semanaInput.value : null;
        const dataQuinta = dataQuintaInput ? dataQuintaInput.value : null;
        
        if (!justificativa) {
            alert('Escreva uma justificativa antes de adicionar.');
            return;
        }
        
        const params = {
            action: 'adicionar_justificativa',
            projetoId: projetoId,
            justificativa: justificativa,
            origem: 'avulsa'
        };
        
        // Adicionar dados da semana se disponíveis
        if (semana !== null && dataQuinta !== null) {
            params.semana = semana;
            params.dataQuinta = dataQuinta;
        }
        
        // Bloquear o botão durante o processamento
        const submitButton = $(this).find('button[type="submit"]');
        submitButton.prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-2"></i> Enviando...');
        
        fetch('okr', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams(params)
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById('textoJustificativa').value = '';
                listarJustificativas(projetoId);
                
                // Caso 1: Justificativa feita pelo modal de pendentes (com semana e dataQuinta)
                if (semana !== null && dataQuinta !== null && typeof window.removerProjetoPendente === 'function') {
                    window.removerProjetoPendente(projetoId, semana, dataQuinta);
                } 
                // Caso 2: Justificativa feita diretamente, mas o servidor identificou uma quinta-feira pendente
                else if (data.satisfazQuintaFeira && typeof window.removerProjetoPendente === 'function') {
                    window.removerProjetoPendente(projetoId, data.semana, data.dataQuinta);
                }
                // Caso 3: Verificar todas as quintas pendentes manualmente
                else if (typeof window.removerProjetoPendente === 'function') {
                    // Buscar semana e dataQuinta no elemento DOM
                    const pendingItems = document.querySelectorAll(`.justificar-projeto[data-id="${projetoId}"]`);
                    if (pendingItems && pendingItems.length > 0) {
                        const firstPending = pendingItems[0];
                        const pendingSemana = firstPending.getAttribute('data-semana');
                        const pendingDataQuinta = firstPending.getAttribute('data-quinta');
                        
                        if (pendingSemana && pendingDataQuinta) {
                            window.removerProjetoPendente(projetoId, pendingSemana, pendingDataQuinta);
                        } else {
                            // Remover o projeto inteiro se não conseguirmos identificar a semana específica
                            window.removerProjetoPendente(projetoId);
                        }
                    }
                }
                
                // Remover os campos ocultos
                if (semanaInput) semanaInput.remove();
                if (dataQuintaInput) dataQuintaInput.remove();
                
                // Exibir mensagem de sucesso
                alert('Registro de andamento adicionado com sucesso!');
            } else {
                alert(data.message || 'Erro ao adicionar registro de andamento.');
            }
            
            // Desbloquear o botão após o processamento
            submitButton.prop('disabled', false).html('<i class="fa fa-upload me-2"></i> Enviar');
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Erro ao comunicar com o servidor.');
            
            // Desbloquear o botão em caso de erro
            submitButton.prop('disabled', false).html('<i class="fa fa-upload me-2"></i> Enviar');
        });
    });
}



// Inicializar filtros de busca
function initFiltros() {
    ['filtroAno','filtroSetor','filtroPrazo','filtroStatus'].forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('change', function() {
                document.getElementById('filtroForm').submit();
            });
        }
    });
    
    // Inicializar selector de itens por página
    const itemsPerPage = document.getElementById('itemsPerPage');
    if (itemsPerPage) {
        itemsPerPage.addEventListener('change', function() {
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('limit', this.value);
            urlParams.set('page', 1); // Volta para primeira página
            window.location.href = 'okr?' + urlParams.toString();
        });
    }
}

// Inicializar handlers de formulários
function initFormHandlers() {
    // Form de Nova Tarefa
    const formNovaTarefa = document.getElementById('formNovaTarefa');
    if (formNovaTarefa) {
        formNovaTarefa.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const projetoId = document.getElementById('tarefasProjetoId').value;
            const nome = document.getElementById('nomeTarefa').value.trim();
            const descricao = document.getElementById('descricaoTarefa').value;
            const parentId = document.getElementById('novaTarefaParentId').value;
            
            if (!projetoId || !nome) {
                alert('Preencha o nome da tarefa.');
                return;
            }
            
            fetch('okr', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({
                    action: 'criar_subtarefa',
                    projetoId: projetoId,
                    nome_subtarefa: nome,
                    descricao: descricao,
                    parent_subtarefa_id: parentId
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('nomeTarefa').value = '';
                    document.getElementById('descricaoTarefa').value = '';
                    document.getElementById('novaTarefaParentId').value = '';
                    document.querySelectorAll('.folder-icon-highlight').forEach(el => 
                        el.classList.remove('folder-icon-highlight')
                    );
                    listarTarefas(projetoId);
                } else {
                    alert(data.message || 'Erro ao criar tarefa.');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao comunicar com o servidor.');
            });
        });
    }
    
    // Form de Upload de Anexo
    const formUploadAnexo = document.getElementById('formUploadAnexo');
    if (formUploadAnexo) {
        formUploadAnexo.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const projetoId = document.getElementById('anexosProjetoId').value;
            const fileInput = document.getElementById('arquivoAnexo');
            
            if (!fileInput.files.length) {
                alert('Selecione um arquivo para upload.');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'upload_anexo');
            formData.append('projetoId', projetoId);
            formData.append('arquivo', fileInput.files[0]);
            
            fetch('okr', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    fileInput.value = '';
                    listarAnexos(projetoId);
                } else {
                    alert(data.message || 'Erro ao enviar anexo.');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao comunicar com o servidor.');
            });
        });
    }
    
    // Form de Nova Atividade (Comentário)
    const formNovaAtividade = document.getElementById('formNovaAtividade');
    if (formNovaAtividade) {
        formNovaAtividade.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const projetoId = document.getElementById('atividadesProjetoId').value;
            const comentario = document.getElementById('textoAtividade').value.trim();
            
            if (!comentario) {
                alert('Escreva um comentário antes de adicionar.');
                return;
            }
            
            fetch('okr', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=adicionar_atividade&projetoId=${projetoId}&comentario=${encodeURIComponent(comentario)}`
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('textoAtividade').value = '';
                    listarAtividades(projetoId);
                } else {
                    alert(data.message || 'Erro ao adicionar comentário.');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao comunicar com o servidor.');
            });
        });
    }
    
    // Form de Finalizar Projeto
    const formFinalizarProjeto = document.getElementById('formFinalizarProjeto');
    if (formFinalizarProjeto) {
        formFinalizarProjeto.addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (!confirm('Tem certeza que deseja finalizar este projeto?')) {
                return;
            }
            
            const formData = new FormData(this);
            
            fetch('okr', {
                method: 'POST',
                body: new URLSearchParams(formData)
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert(data.message || 'Projeto finalizado com sucesso!');
                    hideModal('modalFinalizarProjeto');
                    window.location.reload();
                } else {
                    alert(data.message || 'Erro ao finalizar projeto.');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao comunicar com o servidor.');
            });
        });
    }
    
    // Form de Bloquear Projeto
    const formBloquearProjeto = document.getElementById('formBloquearProjeto');
    if (formBloquearProjeto) {
        formBloquearProjeto.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const motivo = document.getElementById('motivo_bloqueio').value.trim();
            if (!motivo) {
                alert('Informe o motivo do bloqueio.');
                return;
            }
            
            if (!confirm('Tem certeza que deseja bloquear este projeto?')) {
                return;
            }
            
            const formData = new FormData(this);
            
            fetch('okr', {
                method: 'POST',
                body: new URLSearchParams(formData)
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert(data.message || 'Projeto bloqueado com sucesso!');
                    hideModal('modalBloqueiarProjeto');
                    window.location.reload();
                } else {
                    alert(data.message || 'Erro ao bloquear projeto.');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao comunicar com o servidor.');
            });
        });
    }
}

// Inicializar gráfico
function initChart() {
    const ctx = document.getElementById('okrBarChart').getContext('2d');
    
    // Configure Chart.js com tema escuro
    Chart.defaults.color = 'rgba(255, 255, 255, 0.7)';
    Chart.defaults.borderColor = 'rgba(255, 255, 255, 0.1)';
    
    const totalOKR = <?php echo $totalOKR; ?>;
    const okrsFinalizados = <?php echo $okrsFinalizados; ?>;
    const okrsBloqueados = <?php echo $okrsBloqueados; ?>;
    const okrsAndamento = totalOKR - (okrsFinalizados + okrsBloqueados);
    
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Finalizados', 'Bloqueados', 'Em Andamento'],
            datasets: [{
                label: 'Quantidade',
                data: [okrsFinalizados, okrsBloqueados, okrsAndamento],
                backgroundColor: [
                    'rgba(46, 204, 113, 0.8)',
                    'rgba(231, 76, 60, 0.8)',
                    'rgba(52, 152, 219, 0.8)'
                ],
                borderColor: [
                    'rgba(46, 204, 113, 1)',
                    'rgba(231, 76, 60, 1)',
                    'rgba(52, 152, 219, 1)'
                ],
                borderWidth: 2,
                borderRadius: 8,
                maxBarThickness: 50
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { 
                    display: false 
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    titleFont: {
                        size: 14,
                        weight: 'bold'
                    },
                    bodyFont: {
                        size: 13
                    },
                    padding: 12,
                    cornerRadius: 8
                }
            },
            scales: {
                x: { 
                    grid: { 
                        display: false,
                        drawBorder: false 
                    },
                    ticks: { 
                        font: {
                            size: 13
                        }
                    }
                },
                y: { 
                    beginAtZero: true,
                    ticks: { 
                        font: {
                            size: 12
                        },
                        stepSize: 1,
                        precision: 0
                    },
                    grid: { 
                        color: 'rgba(255, 255, 255, 0.05)',
                        drawBorder: false
                    }
                }
            }
        }
    });
}

// ---------------------------------------------------------
// Funções para manipulação dos modais
// ---------------------------------------------------------
function showModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        if (modal._modalBS) {
            modal._modalBS.show();
        } else {
            const bsModal = new bootstrap.Modal(modal);
            modal._modalBS = bsModal;
            bsModal.show();
        }
    }
}

function hideModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal && modal._modalBS) {
        modal._modalBS.hide();
    }
}

// ---------------------------------------------------------
// Funções de visualização e operações com projetos
// ---------------------------------------------------------
function viewMotivoBloqueio(projetoId) {
    fetch('okr', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=get_projeto_details&projetoId=${projetoId}`
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            const projeto = d.projeto;
            const motivo = projeto.motivo_bloqueio || 'Motivo não informado.';
            document.getElementById('motivoBloqueioText').textContent = motivo;
            showModal('modalMotivoBloqueio');
        } else {
            alert(d.message || 'Erro ao obter informações do projeto.');
        }
    })
    .catch(e => {
        console.error('Erro:', e);
        alert('Erro ao comunicar com o servidor.');
    });
}

function openFinalizarModal(projetoId) {
    document.getElementById('finalizarProjetoId').value = projetoId;
    
    fetch('okr', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=listar_subtarefas&projetoId=${projetoId}`
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            const tarefas = d.subtarefas;
            let total = 0, concluidas = 0;
            function contarTarefas(lista) {
                lista.forEach(tarefa => {
                    total++;
                    if (tarefa.concluida == 1) concluidas++;
                    if (tarefa.children && tarefa.children.length > 0) {
                        contarTarefas(tarefa.children);
                    }
                });
            }
            contarTarefas(tarefas);
            const percentual = total > 0 ? Math.round((concluidas / total) * 100) : 0;
            const progressBar = document.getElementById('finalizarProgressBar');
            const progressLabel = document.getElementById('finalizarProgressoLabel');
            progressBar.style.width = percentual + '%';
            progressBar.textContent = percentual + '%';
            progressLabel.textContent = percentual + '%';
        }
    })
    .catch(e => console.error('Erro:', e));
    
    showModal('modalFinalizarProjeto');
}

function openBloqueiarModal(projetoId) {
    document.getElementById('bloquearProjetoId').value = projetoId;
    
    fetch('okr', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=listar_subtarefas&projetoId=${projetoId}`
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            const tarefas = d.subtarefas;
            let total = 0, concluidas = 0;
            function contarTarefas(lista) {
                lista.forEach(tarefa => {
                    total++;
                    if (tarefa.concluida == 1) concluidas++;
                    if (tarefa.children && tarefa.children.length > 0) {
                        contarTarefas(tarefa.children);
                    }
                });
            }
            contarTarefas(tarefas);
            const percentual = total > 0 ? Math.round((concluidas / total) * 100) : 0;
            const progressBar = document.getElementById('bloquearProgressBar');
            const progressLabel = document.getElementById('bloquearProgressoLabel');
            progressBar.style.width = percentual + '%';
            progressBar.textContent = percentual + '%';
            progressLabel.textContent = percentual + '%';
        }
    })
    .catch(e => console.error('Erro:', e));
    
    showModal('modalBloqueiarProjeto');
}

function reabrirProjeto(projetoId) {
    if (!confirm('Deseja reabrir este projeto?')) return;
    
    fetch('okr', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `acao=reabrir&projeto_id=${projetoId}`
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            alert(d.message || 'Projeto reaberto com sucesso!');
            window.location.reload();
        } else {
            alert(d.message || 'Erro ao reabrir projeto.');
        }
    })
    .catch(e => {
        console.error('Erro:', e);
        alert('Erro ao comunicar com o servidor.');
    });
}

function desbloquearProjeto(projetoId) {
    if (!confirm('Deseja desbloquear este projeto?')) return;
    
    fetch('okr', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `acao=desbloquear&projeto_id=${projetoId}`
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            alert(d.message || 'Projeto desbloqueado com sucesso!');
            window.location.reload();
        } else {
            alert(d.message || 'Erro ao desbloquear projeto.');
        }
    })
    .catch(e => {
        console.error('Erro:', e);
        alert('Erro ao comunicar com o servidor.');
    });
}

function gerarRelatorioPDF(projetoId) {
    window.open('relatorio_pdf.php?id=' + projetoId, '_blank');
}

// ---------------------------------------------------------
// Funções para o modal de detalhes (principal)
// ---------------------------------------------------------
function openDetalhesModal(projetoId) {
    document.getElementById('tarefasProjetoId').value = projetoId;
    document.getElementById('novaTarefaParentId').value = '';
    document.getElementById('anexosProjetoId').value = projetoId;
    document.getElementById('atividadesProjetoId').value = projetoId;
    document.getElementById('justificativasProjetoId').value = projetoId;
    document.getElementById('nomeTarefa').value = '';
    document.getElementById('descricaoTarefa').value = '';
    document.getElementById('arquivoAnexo').value = '';
    document.getElementById('textoAtividade').value = '';
    document.getElementById('textoJustificativa').value = '';

    listarTarefas(projetoId);
    listarAnexos(projetoId);
    listarAtividades(projetoId);
    listarJustificativas(projetoId);

    fetch('okr', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=get_projeto_details&projetoId=${projetoId}`
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            const proj = d.projeto;
            const cont = document.getElementById('projectDetails');
            const bvpTotal = (
                parseInt(proj.AlinhamentoEstrategico) +
                parseInt(proj.VulnerabilidadeCiberseguranca) +
                parseInt(proj.LPD_DPO_Criptografado) +
                parseInt(proj.ImpactoOperacional) +
                parseInt(proj.ImpactoAdministrativo) +
                parseInt(proj.ImpactoFinanceiro)
            );
            const bvpClass = bvpTotal >= 20 ? 'high' : (bvpTotal >= 10 ? 'medium' : 'low');

            let statusBadge = '';
            if (proj.Status === 'andamento') {
                statusBadge = '<span class="status-badge status-in-progress"><i class="fa fa-clock me-1"></i> Em Andamento</span>';
            } else if (proj.Status === 'bloqueado') {
                statusBadge = '<span class="status-badge status-blocked"><i class="fa fa-lock me-1"></i> Bloqueado</span>';
            } else if (proj.Status === 'concluido') {
                statusBadge = '<span class="status-badge status-completed"><i class="fa fa-check-circle me-1"></i> Concluído</span>';
            }

            // Ajusta Qualificação
            const qualString = proj.qualificacao
                ? proj.qualificacao.split(',').map(q => q.trim()).join(', ')
                : 'Nenhuma';

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
                        (proj.sigilo_projeto === 'Sigiloso' && proj.motivo_sigilo && proj.motivo_sigilo.trim() !== '')
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
                            <div class="detail-item" style="white-space: pre-wrap;">
                                <i class="fa fa-file-text"></i>
                                <span>Orçamento Macro: <strong>${escapeHtml(proj.orcamento_macro)}</strong></span>
                            </div>
                        ` : ''}
                        <div class="detail-item">
                            <i class="fa fa-paperclip"></i>
                            <span>Arquivos do Orçamento: <span id="arquivosOrcamentoResumo">Carregando...</span></span>
                        </div>
                    ` : ''}
                    
                    <!-- EVITA INDENTAÇÃO + WHITE-SPACE: PRE-WRAP -->
                    <div class="detail-item" style="white-space: pre-wrap;"><i class="fa fa-align-left"></i><span>Descrição do Projeto: <strong>${escapeHtml(proj.descricao_projeto || 'Sem descrição')}</strong></span></div>
                </div>

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
            cont.innerHTML = html;

            // Carrega arquivos do orçamento macro na visão geral
            carregarArquivosOrcamentoVisaoGeralOKR(projetoId);
            
            // Carrega arquivos do orçamento macro na aba específica
            carregarOrcamentoMacroDetalhadoOKR(projetoId);

            if (proj.DependenciasProjetos) {
                const deps = proj.DependenciasProjetos.split(',');
                loadDependencias(deps);
            } else {
                document.getElementById('listaDependencias').innerHTML = '<p class="text-muted">Sem projetos vinculados.</p>';
            }
        }
    })
    .catch(e => console.error(e));

    const footer = document.getElementById('modalDetalhesFooter');
    footer.innerHTML = `
        <button type="button" class="btn btn-secondary" onclick="hideModal('modalDetalhesProjeto')">
            <i class="fa fa-times me-2"></i> Fechar
        </button>
    `;
    showModal('modalDetalhesProjeto');
}

// ---------------------------------------------------------
// Funções para Tarefas
// ---------------------------------------------------------
function listarTarefas(projetoId) {
    fetch('okr', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=listar_subtarefas&projetoId=${projetoId}`
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            montarListaTarefas(d.subtarefas);
            updateProgressBar(d.subtarefas);
            initNestedSortables();
        } else {
            document.getElementById('listaTarefas').innerHTML = 
                '<p class="text-danger">' + (d.message || 'Erro ao listar tarefas.') + '</p>';
        }
    })
    .catch(e => {
        console.error('Erro:', e);
        document.getElementById('listaTarefas').innerHTML = 
            '<p class="text-danger">Erro ao comunicar com o servidor.</p>';
    });
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
    list.forEach(st => {
        const conclClass = (st.concluida == 1 ? 'tarefa-concluida' : '');
        const checked = (st.concluida == 1 ? 'checked' : '');
        const isKeyResult = (st.is_key_result == 1);
        const keyResultClass = isKeyResult ? 'key-result' : '';
        
        const contentClass = 'task-content collapsed';
        
        // CORREÇÃO: Armazenar descrição original para edição
        let actionButtons = '';
        if (!isKeyResult) {
            actionButtons = `
                <button type="button" class="btn btn-sm btn-outline-primary ms-1"
                        title="Editar Tarefa"
                        data-task-id="${st.id}"
                        data-task-title="${escapeHtml(st.nome_subtarefa)}"
                        data-task-description="${escapeHtml(st.descricao || '')}"
                        onclick="editarTarefa(this)">
                    <i class="fa fa-edit"></i>
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger ms-1"
                        title="Excluir Tarefa"
                        onclick="excluirTarefa(${st.id})">
                    <i class="fa fa-trash"></i>
                </button>
            `;
        }
        
        const addSubtaskButton = `<button type="button" class="btn btn-sm btn-outline-secondary ms-1"
                                        title="Adicionar Subtarefa"
                                        onclick="abrirModalNovaMicro(${st.id}, this)">
                                        + Subtarefa
                                   </button>`;
        
        let concluidoInfo = '';
        if (st.concluida == 1 && st.nomeConcluido) {
            concluidoInfo = `<small class="text-danger d-block concluido-info">
                                Concluído por: ${escapeHtml(st.nomeConcluido)} em ${formatDatePt(st.data_conclusao)}
                             </small>`;
        }
        
        const toggleButton = st.descricao ? 
            `<button type="button" class="toggle-description-btn" onclick="toggleDescription(this)">
                <i class="fa fa-chevron-down"></i> Mostrar detalhes
            </button>` : '';
        
        html += `
            <li data-id="${st.id}" data-parent="${st.parent_subtarefa_id || ''}" class="${keyResultClass}">
                <div class="task-item ${keyResultClass} position-relative">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="d-flex align-items-start task-left" style="flex: 1; min-width: 0; padding-right: 8px;">
                            <span class="drag-handle me-2"><i class="fa fa-bars"></i></span>
                            <input type="checkbox" data-id="${st.id}" onchange="toggleTarefaStatus(this)" ${checked} class="form-check-input me-2">
                            <div class="task-text">
                                <span class="${conclClass} fw-semibold task-title">
                                    ${escapeHtml(st.nome_subtarefa)}
                                    ${isKeyResult ? '<span class="key-result-indicator" title="Key Result"><i class="fa fa-key"></i></span>' : ''}
                                </span>
                                <div class="small text-muted mt-1">
                                    [${formatDatePt(st.data_cadastro)} - Criado por: ${escapeHtml(st.nomeCriador || 'N/D')}]
                                </div>
                                ${concluidoInfo}
                                <div class="${contentClass}">
                                    ${st.descricao ? `<div class="task-description task-description-formatted">${escapeHtml(st.descricao)}</div>` : ''}
                                </div>
                                ${toggleButton}
                            </div>
                        </div>
                        <div class="d-flex flex-nowrap align-items-start ms-2">
                            ${addSubtaskButton}
                            ${actionButtons}
                        </div>
                    </div>
                </div>
                <ul class="nested-list subtask-item">
                    ${st.children && st.children.length > 0 ? gerarHtmlSubtarefas(st.children) : ''}
                </ul>
            </li>
        `;
    });
    return html;
}

function editarTarefa(button) {
    // Obtém dados dos atributos data-* do botão (agora com descrição correta)
    const taskId = button.getAttribute('data-task-id');
    const taskTitle = button.getAttribute('data-task-title');
    const taskDescription = button.getAttribute('data-task-description') || '';
    
    // Agora chamamos a função original com os dados recuperados
    abrirModalEditarTarefa(taskId, taskTitle, taskDescription);
}

function updateProgressBar(tasks) {
    const { total, completed } = countTasks(tasks);
    const percentual = total > 0 ? Math.round((completed / total) * 100) : 0;
    
    const progressBar = document.getElementById('projectProgressBar');
    if (progressBar) {
        progressBar.style.width = percentual + '%';
        progressBar.textContent = percentual + '%';
        progressBar.setAttribute('aria-valuenow', percentual);
    }
}

function countTasks(tasks) {
    let total = 0, completed = 0;
    function countRec(items) {
        items.forEach(item => {
            total++;
            if (item.concluida == 1) completed++;
            if (item.children && item.children.length > 0) {
                countRec(item.children);
            }
        });
    }
    countRec(tasks);
    return { total, completed };
}

function atualizarKeyResultBotao() {
    // Para itens no nível raiz, garantir que o botão esteja presente.
    $('#rootSubtarefas > li').each(function() {
        var $li = $(this);
        var taskId = $li.data('id');
        // Se o botão de Key Result não estiver presente, adiciona-o.
        if ($li.find('.task-item .btn-outline-warning').length === 0) {
            var btnHtml = `<button type="button" class="btn btn-sm btn-outline-warning ms-1"
                                title="Marcar como Key Result"
                                onclick="toggleKeyResult(${taskId}, 1)">
                                <i class="fa fa-key"></i>
                           </button>`;
            $li.find('.task-item .ms-auto').append(btnHtml);
        }
    });
    
    // Para subtarefas, remover o botão se existir.
    $('#rootSubtarefas li').not('#rootSubtarefas > li').each(function() {
        $(this).find('.task-item .btn-outline-warning').remove();
    });
}

function initNestedSortables() {
    $('.nested-list').sortable({
        connectWith: '.nested-list',
        handle: '.drag-handle',
        placeholder: 'ui-state-highlight',
        update: function(e, ui) {
            montarEstruturaHierarquica();
        },
        stop: function(e, ui) {
            montarEstruturaHierarquica();
        },
        receive: function(e, ui) {
            montarEstruturaHierarquica();
        }
    }).disableSelection();
}

function montarEstruturaHierarquica() {
    const lista = [];
    function parseUl(ulElem, parentId) {
        $(ulElem).children('li').each(function(index, liElem) {
            const liId = $(liElem).data('id') || '';
            lista.push({
                id: liId,
                parentId: parentId, // se não houver pai, ficará null
                ordem: index + 1
            });
            const ulFilho = $(liElem).children('ul.nested-list');
            if (ulFilho.length > 0) {
                parseUl(ulFilho, liId);
            }
        });
    }
    parseUl($('#rootSubtarefas'), null);
    if (lista.length === 0) return;
    
    const params = new URLSearchParams();
    params.append('action', 'reordenar_subtarefas');
    params.append('lista', JSON.stringify(lista));
    
    fetch('okr', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: params
    })
    .then(r => r.json())
    .then(d => {
        console.log('Resposta da reordenação:', d);
        if (!d.success) {
            console.warn(d.message || 'Erro ao reordenar subtarefas.');
            const projetoId = document.getElementById('tarefasProjetoId').value;
            listarTarefas(projetoId);
        }
    })
    .catch(e => {
        console.error('Erro na reordenação:', e);
        const projetoId = document.getElementById('tarefasProjetoId').value;
        listarTarefas(projetoId);
    });
}

function abrirModalNovaMicro(parentId, iconElem) {
    const already = iconElem.classList.contains('folder-icon-highlight');
    document.querySelectorAll('.folder-icon-highlight').forEach(el =>
        el.classList.remove('folder-icon-highlight')
    );
    if (!already) {
        iconElem.classList.add('folder-icon-highlight');
        document.getElementById('novaTarefaParentId').value = parentId;
    } else {
        document.getElementById('novaTarefaParentId').value = '';
    }
    document.getElementById('nomeTarefa').focus();
}

function toggleTarefaStatus(checkbox) {
    const subtarefaId = checkbox.getAttribute('data-id');
    const concluida = checkbox.checked ? 1 : 0;
    fetch('okr', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=toggle_subtarefa&subtarefaId=${subtarefaId}&concluida=${concluida}`
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            const projetoId = document.getElementById('tarefasProjetoId').value;
            listarTarefas(projetoId);
        } else {
            alert(d.message || 'Erro ao atualizar tarefa.');
            checkbox.checked = !checkbox.checked;
        }
    })
    .catch(e => {
        console.error('Erro:', e);
        alert('Erro ao comunicar com o servidor.');
        checkbox.checked = !checkbox.checked;
    });
}

function toggleKeyResult(subtarefaId, newState) {
    if (!confirm(`Deseja ${newState === 1 ? 'marcar' : 'remover'} esta tarefa ${newState === 1 ? 'como' : 'de'} Key Result?`)) {
        return;
    }
    fetch('okr', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=toggle_key_result&subtarefaId=${subtarefaId}&is_key_result=${newState}`
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            const projetoId = document.getElementById('tarefasProjetoId').value;
            listarTarefas(projetoId);
        } else {
            alert(d.message || 'Erro ao atualizar Key Result.');
        }
    })
    .catch(e => {
        console.error('Erro:', e);
        alert('Erro ao comunicar com o servidor.');
    });
}

function abrirModalEditarTarefa(id, nome, descricao) {
    document.getElementById('editarTarefaId').value = id;
    document.getElementById('editarNomeTarefa').value = nome;
    document.getElementById('editarDescricaoTarefa').value = descricao;
    showModal('modalEditarTarefa');
}

function salvarEdicaoTarefa() {
    const id = document.getElementById('editarTarefaId').value;
    const nome = document.getElementById('editarNomeTarefa').value.trim();
    const descricao = document.getElementById('editarDescricaoTarefa').value;
    if (!nome) {
        alert('Informe o nome da tarefa.');
        return;
    }
    fetch('okr', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=editar_subtarefa&subtarefaId=${id}&nome_subtarefa=${encodeURIComponent(nome)}&descricao=${encodeURIComponent(descricao)}`
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            hideModal('modalEditarTarefa');
            const projetoId = document.getElementById('tarefasProjetoId').value;
            listarTarefas(projetoId);
        } else {
            alert(d.message || 'Erro ao editar tarefa.');
        }
    })
    .catch(e => {
        console.error('Erro:', e);
        alert('Erro ao comunicar com o servidor.');
    });
}

function excluirTarefa(id) {
    if (!confirm('Deseja excluir esta tarefa e suas subtarefas?')) {
        return;
    }
    fetch('okr', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=excluir_subtarefa&subtarefaId=${id}`
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            const projetoId = document.getElementById('tarefasProjetoId').value;
            listarTarefas(projetoId);
        } else {
            alert(d.message || 'Erro ao excluir tarefa.');
        }
    })
    .catch(e => {
        console.error('Erro:', e);
        alert('Erro ao comunicar com o servidor.');
    });
}

// ---------------------------------------------------------
// Funções para Anexos
// ---------------------------------------------------------
function listarAnexos(projetoId) {
    fetch('okr', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=listar_anexos&projetoId=${projetoId}`
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            montarAnexos(d.anexos);
        } else {
            document.getElementById('listaAnexos').innerHTML = 
                '<p class="text-danger">' + (d.message || 'Erro ao listar anexos.') + '</p>';
        }
    })
    .catch(e => {
        console.error('Erro:', e);
        document.getElementById('listaAnexos').innerHTML = 
            '<p class="text-danger">Erro ao comunicar com o servidor.</p>';
    });
}

function montarAnexos(anexos) {
    const container = document.getElementById('listaAnexos');
    if (!container) return;
    
    if (!anexos || anexos.length === 0) {
        container.innerHTML = '<p>Nenhum anexo adicionado.</p>';
        return;
    }
    let html = '<ul class="list-unstyled">';
    anexos.forEach(anexo => {
        html += `
            <li class="mb-2">
                <a href="${escapeHtml(anexo.caminho_arquivo)}" target="_blank" class="text-info fw-bold">
                    ${escapeHtml(anexo.nome_arquivo)}
                </a>
                <small class="d-block text-muted">
                    [Enviado por: ${escapeHtml(anexo.nomeUsuario || 'N/D')} em ${formatDatePt(anexo.data_upload)}]
                </small>
                <button type="button" class="btn btn-sm btn-outline-primary ms-2"
                        onclick="renomearAnexo(${anexo.id}, '${escapeHtml(anexo.nome_arquivo)}')">
                    <i class="fa fa-edit"></i>
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger ms-2"
                        onclick="excluirAnexo(${anexo.id})">
                    <i class="fa fa-trash"></i>
                </button>
            </li>`;
    });
    html += '</ul>';
    container.innerHTML = html;
}

function renomearAnexo(anexoId, nomeAtual) {
    const novoNome = prompt('Novo nome do arquivo:', nomeAtual);
    if (novoNome === null || novoNome.trim() === '') return;
    fetch('okr', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=editar_anexo&anexoId=${anexoId}&novo_nome=${encodeURIComponent(novoNome)}`
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            const projetoId = document.getElementById('anexosProjetoId').value;
            listarAnexos(projetoId);
        } else {
            alert(d.message || 'Erro ao renomear anexo.');
        }
    })
    .catch(e => {
        console.error('Erro:', e);
        alert('Erro ao comunicar com o servidor.');
    });
}

function excluirAnexo(anexoId) {
    if (!confirm('Deseja excluir este anexo?')) return;
    fetch('okr', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=excluir_anexo&anexoId=${anexoId}`
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            const projetoId = document.getElementById('anexosProjetoId').value;
            listarAnexos(projetoId);
        } else {
            alert(d.message || 'Erro ao excluir anexo.');
        }
    })
    .catch(e => {
        console.error('Erro:', e);
        alert('Erro ao comunicar com o servidor.');
    });
}

// ---------------------------------------------------------
// Funções para Atividades (Comentários)
// ---------------------------------------------------------
function listarAtividades(projetoId) {
    fetch('okr', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=listar_atividades&projetoId=${projetoId}`
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            montarAtividades(d.atividades);
        } else {
            document.getElementById('listaAtividades').innerHTML = 
                '<p class="text-danger">' + (d.message || 'Erro ao listar comentários.') + '</p>';
        }
    })
    .catch(e => {
        console.error('Erro:', e);
        document.getElementById('listaAtividades').innerHTML = 
            '<p class="text-danger">Erro ao comunicar com o servidor.</p>';
    });
}

function montarAtividades(atividades) {
    const container = document.getElementById('listaAtividades');
    if (!container) return;
    
    if (!atividades || atividades.length === 0) {
        container.innerHTML = '<p class="text-muted">Nenhum comentário.</p>';
        return;
    }
    let html = '';
    atividades.forEach(atividade => {
        const botoes = `<button class="btn btn-sm btn-outline-danger" onclick="excluirAtividade(${atividade.id})">
                <i class="fa fa-trash"></i>
            </button>`;
        html += `
            <div class="activity-item">
                <div class="activity-header">
                    <span class="activity-author">${escapeHtml(atividade.nomeUsuario || 'Usuário')}</span>
                    <div class="activity-actions">${botoes}</div>
                </div>
                <div class="activity-meta">Em: ${formatDatePt(atividade.data_hora)}</div>
                <div class="activity-content">${escapeHtml(atividade.comentario)}</div>
            </div>`;
    });
    container.innerHTML = html;
}

function excluirAtividade(id) {
    if (!confirm('Deseja excluir este comentário?')) return;
    fetch('okr', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=excluir_atividade&atividadeId=${id}`
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            const projetoId = document.getElementById('atividadesProjetoId').value;
            listarAtividades(projetoId);
        } else {
            alert(d.message || 'Erro ao excluir comentário.');
        }
    })
    .catch(e => {
        console.error('Erro:', e);
        alert('Erro ao comunicar com o servidor.');
    });
}

// ---------------------------------------------------------
// Funções para Justificativas de Andamento - OTIMIZADAS 🚀
// ---------------------------------------------------------
function listarJustificativas(projetoId) {
    fetch('okr', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=listar_justificativas&projetoId=${projetoId}`
    })
    .then(r => r.json())
    .then(d => {
        const container = document.getElementById('listaJustificativas');
        if (!container) return;
        
        if (d.success) {
            if (d.justificativas && d.justificativas.length > 0) {
                let html = '';
                d.justificativas.forEach(just => {
                    html += `
                        <div class="activity-item">
                            <div class="activity-header">
                                <span class="activity-author">${escapeHtml(just.nomeUsuario || 'Usuário')}</span>
                            </div>
                            <div class="activity-meta">Em: ${formatDatePt(just.data_justificativa)}</div>
                            <div class="activity-content">${escapeHtml(just.justificativa)}</div>
                        </div>
                    `;
                });
                container.innerHTML = html;
            } else {
                container.innerHTML = '<p class="text-muted">Nenhum registro cadastrado.</p>';
            }
        } else {
            container.innerHTML = '<p class="text-danger">' + (d.message || 'Erro ao listar registros.') + '</p>';
        }
    })
    .catch(e => {
        console.error('Erro:', e);
        const container = document.getElementById('listaJustificativas');
        if (container) {
            container.innerHTML = '<p class="text-danger">Erro ao comunicar com o servidor.</p>';
        }
    });
}

// Função para abrir o modal de detalhes e focar na aba de andamento
function openDetalhesModalComAbaAndamento(projetoId, semana = null, dataQuinta = null) {
    // Primeiro abrimos o modal normalmente
    openDetalhesModal(projetoId);
    
    // Depois ativamos a aba de andamento
    setTimeout(() => {
        const andamentoTab = document.getElementById('andamento-tab');
        if (andamentoTab) {
            andamentoTab.click();
            
            // Se temos informações da semana específica, adicionar ao campo de justificativa
            const textoJustificativa = document.getElementById('textoJustificativa');
            if (textoJustificativa && semana !== null && dataQuinta !== null) {
                const data = new Date(dataQuinta);
                const dataFormatada = data.toLocaleDateString('pt-BR', { 
                    day: '2-digit', 
                    month: '2-digit', 
                    year: 'numeric' 
                });
                
                // Adicionar informações da semana específica
                
                // Adicionar dados ocultos para identificar a semana ao salvar
                const semanaInput = document.createElement('input');
                semanaInput.type = 'hidden';
                semanaInput.id = 'semanaJustificativa';
                semanaInput.value = semana;
                
                const dataQuintaInput = document.createElement('input');
                dataQuintaInput.type = 'hidden';
                dataQuintaInput.id = 'dataQuintaJustificativa';
                dataQuintaInput.value = dataQuinta;
                
                const form = document.getElementById('formNovaJustificativa');
                if (form) {
                    form.appendChild(semanaInput);
                    form.appendChild(dataQuintaInput);
                }
            }
            
            if (textoJustificativa) {
                textoJustificativa.focus();
            }
        }
    }, 300);
}

function setupJustificativaSystem() {
    // Dados de projetos pendentes (carregados do PHP)
    let projetosPendentes = <?php echo json_encode($projetosPendentes); ?>;
    const perfilAcesso = <?php echo json_encode($perfilAcesso); ?>;
    
    console.log('Projetos pendentes originais:', projetosPendentes);
    
    // Verificar se hoje é quinta-feira (dia 4 da semana em JavaScript, onde 0=domingo)
    const hoje = new Date();
    const diaSemana = hoje.getDay(); // 0=domingo, 1=segunda, ... 4=quinta
    const eQuintaFeira = diaSemana === 4;
    
    console.log('Dia da semana:', diaSemana, 'É quinta-feira:', eQuintaFeira);
    
    // Criamos uma cópia dos projetos originais antes do filtro para comparação
    let projetosAntesFiltro = [...projetosPendentes];
    
    /**
     * NOVA LÓGICA MAIS ROBUSTA:
     * 1. Não filtramos em quinta-feira
     * 2. Verificamos a data de liberação corretamente
     * 3. Adicionamos logs para debugging
     * 4. Tratamos datas inválidas
     */
    if (!eQuintaFeira) {
        console.log('Aplicando filtro (não é quinta-feira)');
        
        // Filtragem mais robusta
        projetosPendentes = projetosPendentes.filter(projeto => {
            // Criar ID para facilitar log
            const projetoId = projeto.Id;
            const projetoNome = projeto.NomeProjeto || 'Sem nome';
            
            // Se não tiver dtliberadookr, mantemos (comportamento seguro)
            if (!projeto.dtliberadookr) {
                console.log(`Projeto #${projetoId} [${projetoNome}] mantido: sem data de liberação OKR`);
                return true;
            }
            
            // Verificar se a data é válida
            const dataLiberacao = new Date(projeto.dtliberadookr);
            if (isNaN(dataLiberacao.getTime())) {
                console.log(`Projeto #${projetoId} [${projetoNome}] mantido: data inválida "${projeto.dtliberadookr}"`);
                return true;
            }
            
            // Padronizar para comparação (remover horas/minutos/segundos)
            const dataLiberacaoNormalizada = new Date(dataLiberacao.getFullYear(), dataLiberacao.getMonth(), dataLiberacao.getDate());
            const hojeNormalizada = new Date(hoje.getFullYear(), hoje.getMonth(), hoje.getDate());
            
            // Calcular diferença em dias
            const diffTime = hojeNormalizada - dataLiberacaoNormalizada;
            const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24));
            
            // Logar para debug
            console.log(`Projeto #${projetoId} [${projetoNome}]:`);
            console.log(`  - Data liberação: ${dataLiberacaoNormalizada.toISOString().split('T')[0]}`);
            console.log(`  - Hoje: ${hojeNormalizada.toISOString().split('T')[0]}`);
            console.log(`  - Diferença em dias: ${diffDays}`);
            
            // Se foi liberado hoje ou no futuro (0 ou menos dias), não incluir na lista
            const manter = diffDays > 0;
            console.log(`  - ${manter ? 'MANTIDO' : 'REMOVIDO'} da lista de pendentes`);
            
            return manter;
        });
        
        // Mostrar projetos que foram filtrados
        const projetosRemovidos = projetosAntesFiltro.filter(p1 => 
            !projetosPendentes.some(p2 => p2.Id === p1.Id)
        );
        
        console.log('Projetos removidos pelo filtro:', projetosRemovidos);
        console.log('Projetos após filtro:', projetosPendentes);
    }
    
    let totalPendentes = projetosPendentes.length;
    
    // Identificar se existem pendências de quintas anteriores
    const hojeTimestamp = hoje.getTime();
    const temPendenciasAnteriores = projetosPendentes.some(p => {
        if (!p.DataQuintaFeira) return false;
        const dataQuinta = new Date(p.DataQuintaFeira);
        // Se a data da quinta for anterior a hoje, é uma pendência anterior
        return dataQuinta.getTime() < hojeTimestamp;
    });

    // Deve mostrar as notificações APENAS se:
    // - For quinta-feira, OU
    // - Existirem pendências de quintas-feiras anteriores
    const deveMostrarNotificacoes = eQuintaFeira || temPendenciasAnteriores;

    console.log('Mostrar notificações:', deveMostrarNotificacoes, 
                'Tem pendências anteriores:', temPendenciasAnteriores,
                'Total pendentes:', totalPendentes);

    // Se não deve mostrar notificações, limpar dados e ocultar elementos
    if (!deveMostrarNotificacoes) {
        const alertaEl = document.getElementById('alertaJustificativa');
        if (alertaEl) {
            alertaEl.style.display = 'none';
        }
        // Limpar pendências já que não serão exibidas
        totalPendentes = 0;
        return; // Sair da função, não processando mais nada
    }
    
    // Inicialização do gerenciador de estado global
    window.justificativaState = {
        pendentes: [...projetosPendentes], // cópia para não modificar o original
        projetosPorId: {},
        
        // Inicializa os dados
        init: function() {
            this.agruparPorId();
            return this;
        },
        
        // Agrupa projetos por ID para facilitar manipulação
        agruparPorId: function() {
            this.projetosPorId = {};
            this.pendentes.forEach(projeto => {
                if (!this.projetosPorId[projeto.Id]) {
                    this.projetosPorId[projeto.Id] = [];
                }
                this.projetosPorId[projeto.Id].push(projeto);
            });
            return this;
        },
        
        // Retorna a contagem total de pendências
        get totalPendentes() {
            return this.pendentes.length;
        },
        
        // Retorna a contagem de projetos únicos
        get projetosUnicos() {
            return Object.keys(this.projetosPorId).length;
        },
        
        // Remove um projeto específico ou todos os projetos com determinado ID
        removerProjeto: function(projetoId, semana = null, dataQuinta = null) {
            if (semana !== null && dataQuinta !== null) {
                // Remove apenas o registro específico
                this.pendentes = this.pendentes.filter(
                    p => !(p.Id == projetoId && p.NumeroSemana == semana && p.DataQuintaFeira == dataQuinta)
                );
            } else {
                // Remove todos os registros do projeto
                this.pendentes = this.pendentes.filter(p => p.Id != projetoId);
            }
            
            // Reagrupa por ID após a remoção
            this.agruparPorId();
            
            // Atualiza a UI
            this.atualizarUI();
            return this;
        },
        
        // Atualiza toda a interface do usuário
        atualizarUI: function() {
            // Atualiza o alerta principal
            atualizarAlertaPendentes();
            
            // Se o modal estiver aberto, atualiza seu conteúdo
            const modalEl = document.getElementById('modalProjetosPendentes');
            if (modalEl && $(modalEl).hasClass('show')) {
                preencherModalPendentes();
            }
            return this;
        }
    }.init(); // Inicializa imediatamente
    
    // Agrupar projetos por ID (para considerar semanas diferentes do mesmo projeto)
    const projetosPorId = {};
    projetosPendentes.forEach(projeto => {
        if (!projetosPorId[projeto.Id]) {
            projetosPorId[projeto.Id] = [];
        }
        projetosPorId[projeto.Id].push(projeto);
    });
    
    // Elementos da UI
    const alertaEl = document.getElementById('alertaJustificativa');
    const textoAlertaEl = document.getElementById('alertaJustificativaTexto');
    const detalhesAlertaEl = document.getElementById('alertaJustificativaDetalhes');
    const btnJustificar = document.getElementById('btnJustificarPendentes');
    const modalPendentes = document.getElementById('modalProjetosPendentes');
    
    // Função para atualizar a exibição do alerta baseado nos projetos pendentes
    function atualizarAlertaPendentes() {
        if (!alertaEl) return;
        
        // Usa o estado global para obter o total atualizado
        const totalPendentes = window.justificativaState.totalPendentes;
        
        if (totalPendentes > 0) {
            if (textoAlertaEl) {
                // Mensagem personalizada com base no perfil
                if (perfilAcesso == 7 || perfilAcesso == 9) {
                    textoAlertaEl.textContent = 
                        `Atenção! Existem ${totalPendentes} registro${totalPendentes > 1 ? 's' : ''} pendente${totalPendentes > 1 ? 's' : ''} em todos os setores.`;
                } else if (perfilAcesso == 2) {
                    textoAlertaEl.textContent = 
                        `Atenção! Seu setor tem ${totalPendentes} registro${totalPendentes > 1 ? 's' : ''} pendente${totalPendentes > 1 ? 's' : ''}.`;
                } else {
                    textoAlertaEl.textContent = 
                        `Atenção! Você tem ${totalPendentes} registro${totalPendentes > 1 ? 's' : ''} pendente${totalPendentes > 1 ? 's' : ''}.`;
                }
            }
            if (detalhesAlertaEl) {
                if (perfilAcesso == 7 || perfilAcesso == 9) {
                    detalhesAlertaEl.textContent = 
                        "Projetos não registrados em quintas-feiras anteriores. Verifique a distribuição por setor.";
                } else {
                    detalhesAlertaEl.textContent = 
                        "Projetos não registrados em quintas-feiras anteriores. Atualize seus projetos para manter o time informado.";
                }
            }
            alertaEl.style.display = 'block';
        } else {
            // Se não houver projetos pendentes, esconde o alerta
            alertaEl.style.display = 'none';
        }
    }
    
    // Função auxiliar para criar um item de projeto
    function criarItemProjeto(container, projeto, perfilAcesso) {
        // Informações adicionais baseadas no perfil
        let infoAdicional = '';
        
        if (perfilAcesso == 7 || perfilAcesso == 9) {
            // Para diretores, mostrar responsável e detalhes completos
            infoAdicional = `
                <div class="mt-1">
                    <span class="badge bg-light text-dark me-1"><i class="fa fa-user"></i> ${escapeHtml(projeto.NomeResponsavel || 'Sem responsável')}</span>
                    ${projeto.NomeSetor ? `<span class="badge bg-success"><i class="fa fa-building"></i> ${escapeHtml(projeto.NomeSetor)}</span>` : ''}
                </div>
            `;
        } else if (perfilAcesso == 2) {
            // Para gestores, mostrar apenas o responsável
            infoAdicional = `<small class="text-info d-block">Responsável: ${escapeHtml(projeto.NomeResponsavel || 'Não definido')}</small>`;
        }
        
        // Determinar se deve mostrar o botão Registrar
        const mostrarBotaoRegistrar = perfilAcesso != 7 && perfilAcesso != 9;
        
        const itemEl = document.createElement('a');
        itemEl.href = '#';
        itemEl.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-start projeto-item';
        itemEl.id = `projeto-pendente-${projeto.Id}`;
        itemEl.style.background = '#2e3240';
        itemEl.style.color = '#eaeaea';
        itemEl.style.border = '1px solid #3c4147';
        itemEl.style.marginBottom = '2px';
        itemEl.style.borderRadius = (perfilAcesso == 7 || perfilAcesso == 9) ? '0' : '8px';
        itemEl.setAttribute('data-id', projeto.Id);
        
        itemEl.innerHTML = `
            <div>
                <div class="d-flex align-items-center">
                    <strong>${escapeHtml(projeto.NomeProjeto)}</strong>
                </div>
                ${infoAdicional}
            </div>
            ${mostrarBotaoRegistrar ? `
            <button class="btn btn-info btn-sm justificar-projeto" data-id="${projeto.Id}" data-semana="${projeto.NumeroSemana}" data-quinta="${projeto.DataQuintaFeira}" data-nome="${escapeHtml(projeto.NomeProjeto)}">
                <i class="fa fa-comment me-1"></i> Registrar
            </button>
            ` : ''}
        `;
        
        container.appendChild(itemEl);
        
        // Se for o último item do grupo para diretores, adicionar borda arredondada na parte inferior
        if (perfilAcesso == 7 || perfilAcesso == 9) {
            const nextEl = container.nextElementSibling;
            if (!nextEl || nextEl.classList.contains('list-group-item-dark')) {
                itemEl.style.borderRadius = '0 0 8px 8px';
                itemEl.style.marginBottom = '15px';
            }
        }
    }
    
    // Função principal para preencher o modal de projetos pendentes
    function preencherModalPendentes() {
        const listaPendentes = document.getElementById('listaPendenteJustificativa');
        if (!listaPendentes) return;

        // Define se deve exibir o botão de "Registrar" (para perfis que não são diretor/subdiretor)
        const mostrarBotaoRegistrar = !(perfilAcesso === 7 || perfilAcesso === 9);

        // Limpa a lista atual
        listaPendentes.innerHTML = '';

        // Obtém dados atualizados do estado global
        const pendentesAtuais = window.justificativaState.pendentes;
        const totalPendentes = window.justificativaState.totalPendentes;
        
        console.log('Preenchendo modal com', totalPendentes, 'projetos pendentes');

        // Se não houver pendentes, mostra mensagem
        if (totalPendentes === 0) {
            listaPendentes.innerHTML =
                '<div class="text-center p-4 text-muted">Não há projetos pendentes de justificativa.</div>';
            return;
        }

        // Para diretores e subdiretores (perfis 7 e 9), agrupa por setor
        if (perfilAcesso === 7 || perfilAcesso === 9) {
            // Criar objeto para agrupar projetos por setor (evitando duplicações)
            const projetosPorSetor = {};
            pendentesAtuais.forEach(proj => {
                const setor = proj.NomeSetor || 'Setor não especificado';
                if (!projetosPorSetor[setor]) projetosPorSetor[setor] = {};
                // Usar o ID do projeto como chave para evitar duplicidades
                if (!projetosPorSetor[setor][proj.Id]) {
                    projetosPorSetor[setor][proj.Id] = proj;
                }
            });

            for (const [setor, projetos] of Object.entries(projetosPorSetor)) {
                // Cabeçalho de setor
                const headerEl = document.createElement('div');
                headerEl.className = 'fw-bold setor-header';
                headerEl.style.color = '#3dbb7c';
                headerEl.style.marginTop = '15px';
                headerEl.style.backgroundColor = '#23272a';
                headerEl.style.padding = '8px';
                headerEl.innerHTML = `
                    <i class="fa fa-building me-2"></i>
                    ${escapeHtml(setor)}
                    <span class="badge bg-danger ms-2">${Object.keys(projetos).length} pendente${Object.keys(projetos).length > 1 ? 's' : ''}</span>
                `;
                listaPendentes.appendChild(headerEl);

                // Lista de cada projeto (sem duplicação)
                Object.values(projetos).forEach(proj => {
                    criarItemProjeto(listaPendentes, proj, perfilAcesso);
                });
            }
        } else {
            // Para outros perfis, garantir que cada projeto é único
            const projetosUnicos = {};
            pendentesAtuais.forEach(proj => {
                // Usar o ID do projeto como chave para evitar duplicidades
                if (!projetosUnicos[proj.Id]) {
                    projetosUnicos[proj.Id] = proj;
                }
            });

            // Criar container para cada projeto único
            Object.values(projetosUnicos).forEach(projeto => {
                // Container do projeto
                const projetoContainer = document.createElement('div');
                projetoContainer.className = 'projeto-pendente-container mb-3';
                projetoContainer.id = `projeto-pendente-${projeto.Id}`;
                projetoContainer.style.backgroundColor = '#2a2e34';
                projetoContainer.style.border = '1px solid #3c4147';
                projetoContainer.style.borderRadius = '8px';
                projetoContainer.style.padding = '10px';
                projetoContainer.style.transition = 'all 0.5s ease';

                // Título do projeto
                const tituloEl = document.createElement('div');
                tituloEl.className = 'fw-bold mb-2';
                tituloEl.innerHTML = `<i class="fa fa-project-diagram me-2"></i> ${escapeHtml(projeto.NomeProjeto)}`;
                projetoContainer.appendChild(tituloEl);

                // Informações adicionais sobre o responsável
                if (projeto.NomeResponsavel) {
                    const infoEl = document.createElement('div');
                    infoEl.className = 'small text-muted mb-2';
                    infoEl.innerHTML = `<i class="fa fa-user me-1"></i> Responsável: ${escapeHtml(projeto.NomeResponsavel)}`;
                    projetoContainer.appendChild(infoEl);
                }

                // Botão único de registrar
                const acaoContainer = document.createElement('div');
                acaoContainer.className = 'text-end';
                
                if (mostrarBotaoRegistrar) {
                    const btnRegistrar = document.createElement('button');
                    btnRegistrar.className = 'btn btn-info btn-sm justificar-projeto';
                    btnRegistrar.dataset.id = projeto.Id;
                    btnRegistrar.dataset.semana = projeto.NumeroSemana;
                    btnRegistrar.dataset.quinta = projeto.DataQuintaFeira;
                    btnRegistrar.innerHTML = `<i class="fa fa-comment me-1"></i> Registrar Andamento`;
                    acaoContainer.appendChild(btnRegistrar);
                }
                
                projetoContainer.appendChild(acaoContainer);
                listaPendentes.appendChild(projetoContainer);
            });
        }

        // Agora reaplica o listener nos botões de "Registrar"
        document.querySelectorAll('.justificar-projeto').forEach(btn => {
            btn.addEventListener('click', e => {
                e.preventDefault();
                const id = btn.dataset.id;
                const semana = btn.dataset.semana;
                const dataQuinta = btn.dataset.quinta;
                hideModal('modalProjetosPendentes');
                openDetalhesModalComAbaAndamento(id, semana, dataQuinta);
            });
        });
    }
    
    // VERIFICAÇÃO ÚNICA: Exibir alerta APENAS se houver projetos pendentes
    if (projetosPendentes && projetosPendentes.length > 0 && alertaEl) {
        atualizarAlertaPendentes();
    } else if (alertaEl) {
        // Se não houver projetos pendentes, garantimos que o alerta fique oculto
        alertaEl.style.display = 'none';
    }
    
    // Botão para mostrar modal de projetos pendentes
    if (btnJustificar) {
        btnJustificar.addEventListener('click', function() {
            // Preencher o modal com os dados atualizados antes de abri-lo
            preencherModalPendentes();
            showModal('modalProjetosPendentes');
        });
        
        // Para diretores, o título do modal é diferente
        if (perfilAcesso == 7 || perfilAcesso == 9) {
            const modalTitle = document.querySelector('#modalProjetosPendentes .modal-title');
            if (modalTitle) {
                modalTitle.innerHTML = '<i class="fa fa-exclamation-triangle me-2 text-info"></i> Registro Pendentes por Setor';
            }
        }
    }
    
    // Adicionar evento para atualizar a lista ao abrir o modal
    if (modalPendentes) {
        modalPendentes.addEventListener('show.bs.modal', preencherModalPendentes);
    }
    
    // Função melhorada para remover um projeto da lista de pendentes
    window.removerProjetoPendente = function(projetoId, semana = null, dataQuinta = null) {
        // Remover do estado global
        window.justificativaState.removerProjeto(projetoId, semana, dataQuinta);
        
        // Animação de remoção na UI se o elemento existir
        if (semana !== null && dataQuinta !== null) {
            // Buscar elementos específicos da semana (caso existam)
            const itens = document.querySelectorAll(`.projeto-item[data-id="${projetoId}"], #projeto-pendente-${projetoId}`);
            
            if (itens.length > 0) {
                itens.forEach(item => {
                    // Animar saída
                    item.style.opacity = '0';
                    item.style.transform = 'translateX(-20px)';
                    
                    // Remover após animação
                    setTimeout(() => {
                        if (item.parentNode) {
                            item.parentNode.removeChild(item);
                        }
                        
                        // Verificar se ficou vazio
                        const listaPendentes = document.getElementById('listaPendenteJustificativa');
                        if (listaPendentes && listaPendentes.querySelectorAll('.projeto-item, .projeto-pendente-container').length === 0) {
                            listaPendentes.innerHTML = '<div class="text-center p-4 text-muted">Não há projetos pendentes de justificativa.</div>';
                        }
                    }, 500);
                });
            }
        } else {
            // Remover todos os elementos do projeto
            const itens = document.querySelectorAll(`.projeto-item[data-id="${projetoId}"], #projeto-pendente-${projetoId}`);
            
            if (itens.length > 0) {
                itens.forEach(item => {
                    // Animar saída
                    item.style.opacity = '0';
                    item.style.transform = 'translateX(-20px)';
                    
                    // Remover após animação
                    setTimeout(() => {
                        if (item.parentNode) {
                            item.parentNode.removeChild(item);
                        }
                        
                        // Verificar se ficou vazio
                        const listaPendentes = document.getElementById('listaPendenteJustificativa');
                        if (listaPendentes && listaPendentes.querySelectorAll('.projeto-item, .projeto-pendente-container').length === 0) {
                            listaPendentes.innerHTML = '<div class="text-center p-4 text-muted">Não há projetos pendentes de justificativa.</div>';
                        }
                    }, 500);
                });
            }
        }
    };
    
    // Ajustar função setupFormJustificativa para atualizar corretamente o estado
    const originalSetupFormJustificativa = window.setupFormJustificativa;
    window.setupFormJustificativa = function() {
        // Remover listeners duplicados
        $(document).off('submit', '#formNovaJustificativa');
        
        // Adicionar novo listener otimizado
        $(document).on('submit', '#formNovaJustificativa', function(e) {
            e.preventDefault();
            
            const projetoId = document.getElementById('justificativasProjetoId').value;
            const justificativa = document.getElementById('textoJustificativa').value.trim();
            
            // Verificar se temos dados de semana específica
            const semanaInput = document.getElementById('semanaJustificativa');
            const dataQuintaInput = document.getElementById('dataQuintaJustificativa');
            
            const semana = semanaInput ? semanaInput.value : null;
            const dataQuinta = dataQuintaInput ? dataQuintaInput.value : null;
            
            if (!justificativa) {
                alert('Escreva uma justificativa antes de adicionar.');
                return;
            }
            
            const params = {
                action: 'adicionar_justificativa',
                projetoId: projetoId,
                justificativa: justificativa,
                origem: 'avulsa'
            };
            
            // Adicionar dados da semana se disponíveis
            if (semana !== null && dataQuinta !== null) {
                params.semana = semana;
                params.dataQuinta = dataQuinta;
            }
            
            // Bloquear o botão durante o processamento
            const submitButton = $(this).find('button[type="submit"]');
            submitButton.prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-2"></i> Enviando...');
            
            fetch('okr', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams(params)
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('textoJustificativa').value = '';
                    listarJustificativas(projetoId);
                    
                    // PONTO CRÍTICO: Remover o projeto/semana pendente após sucesso
                    // Caso 1: Justificativa feita pelo modal de pendentes (com semana e dataQuinta)
                    if (semana !== null && dataQuinta !== null) {
                        window.removerProjetoPendente(projetoId, semana, dataQuinta);
                    } 
                    // Caso 2: Justificativa feita diretamente, mas o servidor identificou uma quinta-feira pendente
                    else if (data.satisfazQuintaFeira) {
                        window.removerProjetoPendente(projetoId, data.semana, data.dataQuinta);
                    }
                    // Caso 3: Verificar todas as quintas pendentes manualmente
                    else {
                        // Primeiro buscar nos botões pendentes
                        const pendingItems = document.querySelectorAll(`.justificar-projeto[data-id="${projetoId}"]`);
                        if (pendingItems && pendingItems.length > 0) {
                            const firstPending = pendingItems[0];
                            const pendingSemana = firstPending.getAttribute('data-semana');
                            const pendingDataQuinta = firstPending.getAttribute('data-quinta');
                            
                            if (pendingSemana && pendingDataQuinta) {
                                window.removerProjetoPendente(projetoId, pendingSemana, pendingDataQuinta);
                            } else {
                                // Remover o projeto inteiro se não conseguirmos identificar a semana específica
                                window.removerProjetoPendente(projetoId);
                            }
                        } else {
                            // Verificar se o projeto está no estado global e remover
                            if (window.justificativaState.projetosPorId[projetoId]) {
                                window.removerProjetoPendente(projetoId);
                            }
                        }
                    }
                    
                    // Remover os campos ocultos
                    if (semanaInput) semanaInput.remove();
                    if (dataQuintaInput) dataQuintaInput.remove();
                    
                    // Exibir mensagem de sucesso
                    alert('Registro de andamento adicionado com sucesso!');
                } else {
                    alert(data.message || 'Erro ao adicionar registro de andamento.');
                }
                
                // Desbloquear o botão após o processamento
                submitButton.prop('disabled', false).html('<i class="fa fa-upload me-2"></i> Enviar');
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao comunicar com o servidor.');
                
                // Desbloquear o botão em caso de erro
                submitButton.prop('disabled', false).html('<i class="fa fa-upload me-2"></i> Enviar');
            });
        });
    };
    
    // Chamar o setup do formulário
    if (typeof window.setupFormJustificativa === 'function') {
        window.setupFormJustificativa();
    }
    
    // Adicionar estilos CSS para as animações
    if (!document.getElementById('justificativaStyles')) {
        const style = document.createElement('style');
        style.id = 'justificativaStyles';
        style.textContent = `
            .fade-out {
                opacity: 0 !important;
                transform: translateX(-20px) !important;
            }
            
            .projeto-pendente-container, .projeto-item {
                transition: all 0.5s ease;
            }
            
            #alertaJustificativa {
                transition: all 0.3s ease;
            }
        `;
        document.head.appendChild(style);
    }
}

// Função auxiliar para criar item de projeto
function criarItemProjeto(container, projeto, perfilAcesso) {
    // Informações adicionais baseadas no perfil
    let infoAdicional = '';
    
    if (perfilAcesso == 7 || perfilAcesso == 9) {
        // Para diretores, mostrar responsável e detalhes completos
        infoAdicional = `
            <div class="mt-1">
                <span class="badge bg-light text-dark me-1"><i class="fa fa-user"></i> ${escapeHtml(projeto.NomeResponsavel || 'Sem responsável')}</span>
                ${projeto.NomeSetor ? `<span class="badge bg-success"><i class="fa fa-building"></i> ${escapeHtml(projeto.NomeSetor)}</span>` : ''}
            </div>
        `;
    } else if (perfilAcesso == 2) {
        // Para gestores, mostrar apenas o responsável
        infoAdicional = `<small class="text-info d-block">Responsável: ${escapeHtml(projeto.NomeResponsavel || 'Não definido')}</small>`;
    }
    
    // Determinar se deve mostrar o botão Registrar
    const mostrarBotaoRegistrar = perfilAcesso != 7;
    
    const itemEl = document.createElement('a');
    itemEl.href = '#';
    itemEl.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-start';
    itemEl.style.background = '#2e3240';
    itemEl.style.color = '#eaeaea';
    itemEl.style.border = '1px solid #3c4147';
    itemEl.style.marginBottom = '2px';
    itemEl.style.borderRadius = (perfilAcesso == 7 || perfilAcesso == 9) ? '0' : '8px';
    itemEl.setAttribute('data-id', projeto.Id);
    
    itemEl.innerHTML = `
        <div>
            <div class="d-flex align-items-center">
                <strong>${escapeHtml(projeto.NomeProjeto)}</strong> 
                
            </div>
            ${infoAdicional}
        </div>
        ${mostrarBotaoRegistrar ? `
        <button class="btn btn-info btn-sm justificar-projeto" data-id="${projeto.Id}" data-nome="${escapeHtml(projeto.NomeProjeto)}">
            <i class="fa fa-comment me-1"></i> Registrar
        </button>
        ` : ''}
    `;
    
    container.appendChild(itemEl);
    
    // Se for o último item do grupo para diretores, adicionar borda arredondada na parte inferior
    if (perfilAcesso == 7 || perfilAcesso == 9) {
        const nextEl = container.nextElementSibling;
        if (!nextEl || nextEl.classList.contains('list-group-item-dark')) {
            itemEl.style.borderRadius = '0 0 8px 8px';
            itemEl.style.marginBottom = '15px';
        }
    }
}

// ---------------------------------------------------------
// Funções para carregar dependências
// ---------------------------------------------------------
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
        return fetch('okr', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=listar_subtarefas&projetoId=${depId}`
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                let tasks = data.subtarefas;
                let { total, completed } = countTasks(tasks);
                let perc = total > 0 ? Math.round((completed / total) * 100) : 0;
                let projectName = 'Projeto ' + depId;
                const found = window.listaProjetosJS?.find(x => x.Id == depId);
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
                    </div>`;
            });
            container.innerHTML = html || '<p class="text-muted">Sem dados de dependências.</p>';
        })
        .catch(error => {
            console.error('Erro geral ao processar dependências:', error);
            container.innerHTML = '<p class="text-danger">Erro ao carregar dependências.</p>';
        });
}

// ---------------------------------------------------------
// Funções utilitárias
// ---------------------------------------------------------
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

// Variável global para projetos disponíveis (usado em dependências)
window.listaProjetosJS = <?php echo json_encode($listaProjetos, JSON_UNESCAPED_UNICODE); ?>;

// Função para formatar valor monetário
function formatMoney(value) {
    if (!value) return '0,00';
    return parseFloat(value).toLocaleString('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
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

// Função para carregar resumo dos arquivos na visão geral (OKR)
function carregarArquivosOrcamentoVisaoGeralOKR(projetoId) {
    fetch('projetos', {  // Note que fazemos fetch para 'projetos' para buscar os arquivos
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

// Função para carregar orçamento detalhado na aba específica (OKR)
function carregarOrcamentoMacroDetalhadoOKR(projetoId) {
    fetch('okr', {
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
            carregarArquivosOrcamentoDetalhadosOKR(projetoId);
        }
    })
    .catch(e => console.error('Erro ao carregar orçamento detalhado:', e));
}

// Função para exibir arquivos na aba orçamento detalhada (OKR)
function carregarArquivosOrcamentoDetalhadosOKR(projetoId) {
    fetch('projetos', {  // Note que fazemos fetch para 'projetos' para buscar os arquivos
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

// Adicione esta função ao seu arquivo JavaScript
function toggleDescription(button) {
    // Encontrar o content container mais próximo
    const container = button.previousElementSibling;
    
    // Toggle da classe collapsed
    container.classList.toggle('collapsed');
    
    // Atualizar estado do botão
    if (container.classList.contains('collapsed')) {
        button.innerHTML = '<i class="fa fa-chevron-down"></i> Mostrar detalhes';
        button.classList.remove('expanded');
    } else {
        button.innerHTML = '<i class="fa fa-chevron-up"></i> Ocultar detalhes';
        button.classList.add('expanded');
    }
}
</script>

</body>
</html>