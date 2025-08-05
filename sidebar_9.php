<?php
// Verifica se existe um usuário logado e o perfil de acesso na sessão
$perfilAcesso = $_SESSION['PerfilAcesso'] ?? null;

function menuPermitido($perfis) {
    global $perfilAcesso;
    return in_array($perfilAcesso, $perfis);
}
?>

<aside class="left-sidebar">
    <!-- Sidebar scroll-->
    <div class="scroll-sidebar">
        <!-- Sidebar navigation-->
        <nav class="sidebar-nav">
            <ul id="sidebarnav">
                <!-- Dashboard -->
                <?php if (menuPermitido([1, 2, 4, 5])): ?>
                <li class="sidebar-item">
                    <a class="sidebar-link waves-effect waves-dark sidebar-link" href="index.php" aria-expanded="false">
                        <i class="mdi mdi-gauge"></i><span class="hide-menu">Dashboard</span>
                    </a>
                </li>
                <?php endif; ?>

                 <li class="nav-small-cap">
                <i class="mdi mdi-dots-horizontal"></i>
                <span class="hide-menu">Área Pessoal</span>
              </li>

                <!-- Minhas Tarefas -->
                <?php if (menuPermitido([1, 2, 3, 4, 5])): ?>
                <li class="sidebar-item">
                    <a class="sidebar-link waves-effect waves-dark sidebar-link" href="minhastarefas.php" aria-expanded="false">
                        <i class="mdi mdi-pencil"></i><span class="hide-menu">Minhas Tarefas</span>
                    </a>
                </li>
                <?php endif; ?>


                <li class="nav-small-cap">
                <i class="mdi mdi-dots-horizontal"></i>
                <span class="hide-menu">Gestão</span>
              </li>

                <!-- Dev BI -->
                <?php if (menuPermitido([1, 2, 5])): ?>
                <li class="sidebar-item">
                    <a class="sidebar-link waves-effect waves-dark sidebar-link" href="backlogbi.php" aria-expanded="false">
                        <i class="mdi mdi-view-dashboard"></i><span class="hide-menu">Dev BI</span>
                    </a>
                </li>
                <?php endif; ?>



                <!-- Backlog -->
                <?php if (menuPermitido([1, 2, 3, 4])): ?>
                <li class="sidebar-item">
                    <a class="sidebar-link waves-effect waves-dark sidebar-link" href="backlog.php" aria-expanded="false">
                        <i class="mdi mdi-monitor"></i><span class="hide-menu">Backlog</span>
                    </a>
                </li>
                <?php endif; ?>


                <!-- OS -->
                <?php if (menuPermitido([1, 2, 3, 4])): ?>
                <li class="sidebar-item">
                    <a class="sidebar-link waves-effect waves-dark sidebar-link" href="os.php" aria-expanded="false">
                        <i class="mdi mdi-arrange-bring-forward"></i><span class="hide-menu">Ordem de Serviço</span>
                    </a>
                </li>
                <?php endif; ?>



                <!-- Suporte -->
                <?php if (menuPermitido([1, 2, 3, 4])): ?>
                <li class="sidebar-item">
                    <a class="sidebar-link waves-effect waves-dark sidebar-link" href="suporte.php" aria-expanded="false">
                        <i class="mdi mdi-bulletin-board"></i><span class="hide-menu">Suporte</span>
                    </a>
                </li>
                <?php endif; ?>


                <!-- Interações -->
                <?php if (menuPermitido([1, 2, 4])): ?>
                <li class="sidebar-item">
                    <a class="sidebar-link waves-effect waves-dark sidebar-link" href="interacao.php" aria-expanded="false">
                        <i class="mdi mdi-comment-processing-outline"></i><span class="hide-menu">Interações</span>
                    </a>
                </li>
                <?php endif; ?>


                <!-- Reuniões 
                <?php if (menuPermitido([1, 2, 4, 5])): ?>
                <li class="sidebar-item">
                    <a class="sidebar-link waves-effect waves-dark sidebar-link" href="reunioes.php" aria-expanded="false">
                        <i class="mdi mdi-calendar"></i><span class="hide-menu">Reuniões</span>
                    </a>
                </li>
                <?php endif; ?>-->
           

              <?php if (menuPermitido([1, 2, 4])): ?>
              <li class="nav-small-cap">
                <i class="mdi mdi-dots-horizontal"></i>
                <span class="hide-menu">Cadastro</span>
              </li>
              <?php endif; ?>


              <!-- Usuários (Apenas Admin) -->
                <?php if (menuPermitido([1])): ?>
                <li class="sidebar-item">
                    <a class="sidebar-link waves-effect waves-dark sidebar-link" href="usuarios.php" aria-expanded="false">
                        <i class="mdi mdi-account-box"></i><span class="hide-menu">Usuários</span>
                    </a>
                </li>
                <?php endif; ?>


              <!-- Contratadas (Disponível para Admin, Gestor, Inova) -->
                <?php if (menuPermitido([1, 2, 4])): ?>
                <li class="sidebar-item">
                    <a class="sidebar-link waves-effect waves-dark sidebar-link" href="contratadas.php" aria-expanded="false">
                        <i class="mdi mdi-book"></i><span class="hide-menu">Contratadas</span>
                    </a>
                </li>
                <?php endif; ?>

                <!-- Empenho (Disponível para Admin, Gestor, Inova) -->
                <?php if (menuPermitido([1, 2, 4])): ?>
                <li class="sidebar-item">
                    <a class="sidebar-link waves-effect waves-dark sidebar-link" href="empenho.php" aria-expanded="false">
                        <i class="mdi mdi-currency-usd"></i><span class="hide-menu">Empenho</span>
                    </a>
                </li>
                <?php endif; ?>              

            </ul>
          </nav>
          <!-- End Sidebar navigation -->
        </div>
        <!-- End Sidebar scroll-->
      
        
        <!-- End Bottom points-->
      </aside>


      