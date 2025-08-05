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
                <?php if (menuPermitido([1, 4, 5, 9])): ?>
                <li class="sidebar-item">
                    <a class="sidebar-link waves-effect waves-dark sidebar-link" href="index" aria-expanded="false">
                        <i class="mdi mdi-chart-pie"></i>
                        <span class="hide-menu">Dashboard</span>
                    </a>
                </li>
                <?php endif; ?>

                <!-- Análise BI -->
                <?php if (menuPermitido([1, 2, 4, 5, 7, 8, 9])): ?>
                <li class="sidebar-item">
                    <a class="sidebar-link waves-effect waves-dark sidebar-link" href="analisebi" aria-expanded="false">
                        <i class="mdi mdi-chart-pie"></i>
                        <span class="hide-menu">Análise BI</span>
                    </a>
                </li>
                <?php endif; ?>

                <li class="nav-small-cap">
                    <i class="mdi mdi-dots-horizontal"></i>
                    <span class="hide-menu">Área Pessoal</span>
                </li>

                <!-- Meu Espaço -->
                <?php if (menuPermitido([1, 4, 9])): ?>
                <li class="sidebar-item">
                    <a class="sidebar-link waves-effect waves-dark sidebar-link" href="meuespaco" aria-expanded="false">
                        <i class="mdi mdi-desktop-mac"></i>
                        <span class="hide-menu">Área de Trabalho</span>
                    </a>
                </li>
                <?php endif; ?>

                <!-- Minhas Tarefas -->
                <?php if (menuPermitido([1, 2, 3, 4, 5, 6, 7, 8, 9])): ?>
                <li class="sidebar-item">
                    <a class="sidebar-link waves-effect waves-dark sidebar-link" href="minhastarefas" aria-expanded="false">
                        <i class="mdi mdi-book"></i>
                        <span class="hide-menu">Minhas Tarefas</span>
                    </a>
                </li>
                <?php endif; ?>

                <li class="nav-small-cap">
                    <i class="mdi mdi-dots-horizontal"></i>
                    <span class="hide-menu">Gestão</span>
                </li>

                <!-- Agenda -->
                <?php if (menuPermitido([1, 3, 4, 5, 6, 9])): ?>
                <li class="sidebar-item">
                    <a class="sidebar-link waves-effect waves-dark sidebar-link" href="agenda" aria-expanded="false">
                        <i class="mdi mdi-account-box"></i>
                        <span class="hide-menu">Agenda</span>
                    </a>
                </li>
                <?php endif; ?>

                <!-- Projetos -->
                <?php if (menuPermitido([1, 2, 4, 5, 7, 8, 9])): ?>
                <li class="sidebar-item">
                    <a class="sidebar-link waves-effect waves-dark sidebar-link" href="projetos" aria-expanded="false">
                        <i class="mdi mdi-briefcase"></i>
                        <span class="hide-menu">Projetos</span>
                    </a>
                </li>
                <?php endif; ?>

                <!-- OKR -->
                <?php if (menuPermitido([1, 2, 4, 5, 7, 8, 9])): ?>
                <li class="sidebar-item">
                    <a class="sidebar-link waves-effect waves-dark sidebar-link" href="okr" aria-expanded="false">
                        <i class="mdi mdi-chart-line"></i>
                        <span class="hide-menu">OKR</span>
                    </a>
                </li>
                <?php endif; ?>

                <!-- Dev BI -->
                <?php if (menuPermitido([1, 5, 9])): ?>
                <li class="sidebar-item">
                    <a class="sidebar-link waves-effect waves-dark sidebar-link" href="bi" aria-expanded="false">
                        <i class="mdi mdi-poll"></i>
                        <span class="hide-menu">Dev BI</span>
                    </a>
                </li>
                <?php endif; ?>

                <!-- Backlog -->
                <?php if (menuPermitido([1, 3, 4])): ?>
                <li class="sidebar-item">
                    <a class="sidebar-link waves-effect waves-dark sidebar-link" href="backlog" aria-expanded="false">
                        <i class="mdi mdi-lightbulb-on"></i>
                        <span class="hide-menu">Backlog</span>
                    </a>
                </li>
                <?php endif; ?>

                <!-- OS -->
                <?php if (menuPermitido([1, 3, 4, 9])): ?>
                <li class="sidebar-item">
                    <a class="sidebar-link waves-effect waves-dark sidebar-link" href="os" aria-expanded="false">
                        <i class="mdi mdi-slack"></i>
                        <span class="hide-menu">Ordem de Serviço</span>
                    </a>
                </li>
                <?php endif; ?>

                <!-- Análise PF -->
                <?php if (menuPermitido([1, 4])): ?>
                <li class="sidebar-item">
                    <a class="sidebar-link waves-effect waves-dark sidebar-link" href="analisepf" aria-expanded="false">
                        <i class="mdi mdi-calculator"></i>
                        <span class="hide-menu">Análise PF</span>
                    </a>
                </li>
                <?php endif; ?>

                <!-- Suporte -->
                <?php if (menuPermitido([1, 3, 4, 6])): ?>
                <li class="sidebar-item">
                    <a class="sidebar-link waves-effect waves-dark sidebar-link" href="suporte" aria-expanded="false">
                        <i class="mdi mdi-headset"></i>
                        <span class="hide-menu">Suporte</span>
                    </a>
                </li>
                <?php endif; ?>

                <!-- Cronograma -->
                <?php if (menuPermitido([1, 3, 4, 9])): ?>
                <li class="sidebar-item">
                    <a class="sidebar-link waves-effect waves-dark sidebar-link" href="cronograma" aria-expanded="false">
                        <i class="mdi mdi-calendar"></i>
                        <span class="hide-menu">Cronograma</span>
                    </a>
                </li>
                <?php endif; ?>

                <!-- Metas -->
                <?php if (menuPermitido([1, 3, 4, 5, 9])): ?>
                <li class="sidebar-item">
                    <a class="sidebar-link waves-effect waves-dark sidebar-link" href="metas" aria-expanded="false">
                        <i class="mdi mdi-target"></i>
                        <span class="hide-menu">Metas</span>
                    </a>
                </li>
                <?php endif; ?>

                <!-- Sprints -->
                <?php if (menuPermitido([1, 3, 4, 9])): ?>
                <li class="sidebar-item">
                    <a class="sidebar-link waves-effect waves-dark sidebar-link" href="sprints" aria-expanded="false">
                        <i class="mdi mdi-timetable"></i>
                        <span class="hide-menu">Sprints</span>
                    </a>
                </li>
                <?php endif; ?>

                <!-- Usuários -->
                <?php if (menuPermitido([1])): ?>
                <li class="sidebar-item">
                    <a class="sidebar-link waves-effect waves-dark sidebar-link" href="usuarios" aria-expanded="false">
                        <i class="mdi mdi-account-box"></i>
                        <span class="hide-menu">Usuários</span>
                    </a>
                </li>
                <?php endif; ?>

                <!-- Contratadas -->
                <?php if (menuPermitido([1, 4, 9])): ?>
                <li class="sidebar-item">
                    <a class="sidebar-link waves-effect waves-dark sidebar-link" href="contratadas" aria-expanded="false">
                        <i class="mdi mdi-book"></i>
                        <span class="hide-menu">Contratadas</span>
                    </a>
                </li>
                <?php endif; ?>

                <!-- Setores -->
                <?php if (menuPermitido([1,9])): ?>
                <li class="sidebar-item">
                    <a class="sidebar-link waves-effect waves-dark sidebar-link" href="setores" aria-expanded="false">
                        <i class="mdi mdi-domain"></i>
                        <span class="hide-menu">Setores</span>
                    </a>
                </li>
                <?php endif; ?>

                <!-- Empenho -->
                <?php if (menuPermitido([1, 4, 9])): ?>
                <li class="sidebar-item">
                    <a class="sidebar-link waves-effect waves-dark sidebar-link" href="empenho" aria-expanded="false">
                        <i class="mdi mdi-currency-usd"></i>
                        <span class="hide-menu">Empenho</span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
        <!-- End Sidebar navigation -->
    </div>
    <!-- End Sidebar scroll-->
</aside>
