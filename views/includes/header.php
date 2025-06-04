<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!defined('BASE_URL')) {
    // Tenta carregar o config.php do diretório esperado
    $config_path = __DIR__ . '/../../config/config.php';
    if (file_exists($config_path)) {
        require_once $config_path;
    } else {
        die('Erro Crítico: BASE_URL não está definida e config.php não encontrado no header.php.');
    }
}

// Lógica para verificar se o usuário está logado
// if (!isset($_SESSION['usuario_id']) && basename($_SERVER['PHP_SELF']) !== 'login.php') {
//    header('Location: ' . BASE_URL . '/views/auth/login.php');
//    exit();
// }

$current_page = basename($_SERVER['PHP_SELF']);
$page_group = ''; // Para determinar o grupo de menu ativo

// Determinar o grupo de menu ativo (exemplo simplificado)
if (strpos($_SERVER['REQUEST_URI'], '/views/orcamentos/') !== false) {
    $page_group = 'orcamentos';
} elseif (strpos($_SERVER['REQUEST_URI'], '/views/clientes/') !== false) {
    $page_group = 'clientes';
} elseif (strpos($_SERVER['REQUEST_URI'], '/views/produtos/') !== false) {
    $page_group = 'produtos';
} elseif (strpos($_SERVER['REQUEST_URI'], '/views/usuarios/') !== false) {
    $page_group = 'usuarios';
} elseif (strpos($_SERVER['REQUEST_URI'], '/views/configuracoes/') !== false) {
    $page_group = 'configuracoes';
} elseif (strpos($_SERVER['REQUEST_URI'], '/views/dashboard/') !== false) {
    $page_group = 'dashboard';
}


$appName = defined('APP_NAME') ? APP_NAME : 'Sistema Toalhas';
$appVersion = defined('APP_VERSION') ? APP_VERSION : '1.0.0';

// Título da página dinâmico (pode ser sobrescrito pela view específica)
$resolved_page_title = isset($page_title) ? htmlspecialchars($page_title) . ' | ' . htmlspecialchars($appName) : htmlspecialchars($appName);

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $resolved_page_title ?></title>

    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- Theme style (AdminLTE) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/admin-lte/3.2.0/css/adminlte.min.css">
    
    <!-- Select2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@ttskch/select2-bootstrap4-theme@x.x.x/dist/select2-bootstrap4.min.css"> <!-- Para tema Bootstrap 4 do Select2 -->

    <!-- Tempusdominus Bootstrap 4 CSS (SOMENTE SE FOR USAR O DATETIMEPICKER DELE PARA OUTROS CAMPOS) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tempusdominus-bootstrap-4/5.39.0/css/tempusdominus-bootstrap-4.min.css">
    
    <!-- ************************************************** -->
    <!-- Bootstrap Datepicker CSS (PARA OS CAMPOS .datepicker) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/css/bootstrap-datepicker.standalone.min.css">
    <!-- ************************************************** -->

    <!-- Toastr CSS (Para notificações) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">

    <!-- SweetAlert2 CSS (Para alertas) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <!-- Estilos customizados globais (se houver) -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/custom_global.css">

    <!-- Estilos específicos da página (definidos pela view como $extra_css) -->
    <?php
    if (isset($extra_css) && is_array($extra_css)) {
        foreach ($extra_css as $css_file) {
            echo '<link rel="stylesheet" href="' . htmlspecialchars($css_file, ENT_QUOTES, 'UTF-8') . '">' . "\n";
        }
    }
    ?>
    <style>
        /* Ajustes para Select2 e Datepicker dentro de modais se necessário */
        .select2-container--open {
            z-index: 9999999 !important; /* Acima do modal */
        }
        .datepicker {
            z-index: 999999 !important; /* Ajuste conforme necessário, deve ser maior que o modal backdrop mas pode ser menor que o modal em si */
        }
        body.modal-open .datepicker { /* Tenta forçar z-index alto quando modal está aberto */
            z-index: 1051 !important; /* Um pouco acima do z-index padrão do modal (1050) */
        }
         .table-responsive {
            overflow-x: auto;
        }
        .form-control-sm {
            height: calc(1.8125rem + 2px); /* Ajuste de altura para AdminLTE */
            padding: .25rem .5rem;
            font-size: .875rem;
            line-height: 1.5;
            border-radius: .2rem;
        }
        /* Para evitar que o ícone do datepicker quebre a linha em colunas pequenas */
        .input-group-append .input-group-text {
            padding: .375rem .5rem; /* Ajuste para caber melhor */
        }
        .table th, .table td {
            vertical-align: middle !important;
        }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

    <!-- Preloader (Opcional, mas comum no AdminLTE) -->
    <div class="preloader flex-column justify-content-center align-items-center" style="background-color: rgba(255,255,255,0.8);">
        <img class="animation__shake" src="<?= BASE_URL ?>/assets/img/logo_toalhas_loader.png" alt="<?= htmlspecialchars($appName) ?> Logo" height="80" width="80">
        <p class="mt-2">Carregando...</p>
    </div>

    <!-- Navbar -->
    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
        <!-- Left navbar links -->
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
            </li>
            <li class="nav-item d-none d-sm-inline-block">
                <a href="<?= BASE_URL ?>/views/dashboard/index.php" class="nav-link">Início</a>
            </li>
            <!-- Outros links da navbar aqui -->
        </ul>

        <!-- Right navbar links -->
        <ul class="navbar-nav ml-auto">
            <!-- Notifications Dropdown Menu -->
            <li class="nav-item dropdown">
                <a class="nav-link" data-toggle="dropdown" href="#">
                    <i class="far fa-bell"></i>
                    <span class="badge badge-warning navbar-badge">0</span> <!-- Atualizar dinamicamente -->
                </a>
                <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                    <span class="dropdown-item dropdown-header">0 Notificações</span>
                    <!-- Conteúdo das notificações aqui -->
                    <div class="dropdown-divider"></div>
                    <a href="#" class="dropdown-item dropdown-footer">Ver todas as notificações</a>
                </div>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-widget="fullscreen" href="#" role="button">
                    <i class="fas fa-expand-arrows-alt"></i>
                </a>
            </li>
            <li class="nav-item dropdown user-menu">
                <a href="#" class="nav-link dropdown-toggle" data-toggle="dropdown">
                    <img src="<?= BASE_URL ?>/assets/img/avatar_placeholder.png" class="user-image img-circle elevation-2" alt="User Image">
                    <span class="d-none d-md-inline"><?= isset($_SESSION['usuario_nome']) ? htmlspecialchars($_SESSION['usuario_nome']) : 'Usuário' ?></span>
                </a>
                <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                    <!-- User image -->
                    <li class="user-header bg-primary">
                        <img src="<?= BASE_URL ?>/assets/img/avatar_placeholder.png" class="img-circle elevation-2" alt="User Image">
                        <p>
                            <?= isset($_SESSION['usuario_nome']) ? htmlspecialchars($_SESSION['usuario_nome']) : 'Usuário' ?>
                            <small>Membro desde <?= isset($_SESSION['usuario_data_cadastro']) ? date('M. Y', strtotime($_SESSION['usuario_data_cadastro'])) : 'N/A' ?></small>
                        </p>
                    </li>
                    <!-- Menu Footer-->
                    <li class="user-footer">
                        <a href="<?= BASE_URL ?>/views/usuarios/profile.php" class="btn btn-default btn-flat">Perfil</a>
                        <a href="<?= BASE_URL ?>/views/auth/logout.php" class="btn btn-default btn-flat float-right">Sair</a>
                    </li>
                </ul>
            </li>
        </ul>
    </nav>
    <!-- /.navbar -->

    <!-- Main Sidebar Container -->
    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <!-- Brand Logo -->
        <a href="<?= BASE_URL ?>/views/dashboard/index.php" class="brand-link">
            <img src="<?= BASE_URL ?>/assets/img/logo_toalhas_icon.png" alt="<?= htmlspecialchars($appName) ?> Logo" class="brand-image img-circle elevation-3" style="opacity: .8">
            <span class="brand-text font-weight-light"><?= htmlspecialchars($appName) ?></span>
        </a>

        <!-- Sidebar -->
        <div class="sidebar">
            <!-- Sidebar user panel (optional) -->
            <div class="user-panel mt-3 pb-3 mb-3 d-flex">
                <div class="image">
                    <img src="<?= BASE_URL ?>/assets/img/avatar_placeholder.png" class="img-circle elevation-2" alt="User Image">
                </div>
                <div class="info">
                    <a href="#" class="d-block"><?= isset($_SESSION['usuario_nome']) ? htmlspecialchars($_SESSION['usuario_nome']) : 'Usuário' ?></a>
                </div>
            </div>

            <!-- SidebarSearch Form (Opcional) -->
            <div class="form-inline mt-2">
                <div class="input-group" data-widget="sidebar-search">
                    <input class="form-control form-control-sidebar" type="search" placeholder="Buscar no Menu" aria-label="Search">
                    <div class="input-group-append">
                        <button class="btn btn-sidebar">
                            <i class="fas fa-search fa-fw"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Sidebar Menu -->
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column nav-child-indent nav-legacy" data-widget="treeview" role="menu" data-accordion="false">
                    <li class="nav-item">
                        <a href="<?= BASE_URL ?>/views/dashboard/index.php" class="nav-link <?= ($page_group == 'dashboard' || $current_page == 'index.php' && $page_group == '') ? 'active' : '' ?>">
                            <i class="nav-icon fas fa-tachometer-alt"></i>
                            <p>Dashboard</p>
                        </a>
                    </li>
                    <li class="nav-item <?= ($page_group == 'orcamentos') ? 'menu-open' : '' ?>">
                        <a href="#" class="nav-link <?= ($page_group == 'orcamentos') ? 'active' : '' ?>">
                            <i class="nav-icon fas fa-file-invoice-dollar"></i>
                            <p>Orçamentos<i class="right fas fa-angle-left"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="<?= BASE_URL ?>/views/orcamentos/index.php" class="nav-link <?= ($current_page == 'index.php' && $page_group == 'orcamentos') ? 'active' : '' ?>">
                                    <i class="far fa-circle nav-icon"></i><p>Listar Orçamentos</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="<?= BASE_URL ?>/views/orcamentos/create.php" class="nav-link <?= ($current_page == 'create.php' && $page_group == 'orcamentos') ? 'active' : '' ?>">
                                    <i class="far fa-circle nav-icon"></i><p>Novo Orçamento</p>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="nav-item <?= ($page_group == 'clientes') ? 'menu-open' : '' ?>">
                        <a href="#" class="nav-link <?= ($page_group == 'clientes') ? 'active' : '' ?>">
                            <i class="nav-icon fas fa-users"></i>
                            <p>Clientes<i class="right fas fa-angle-left"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="<?= BASE_URL ?>/views/clientes/index.php" class="nav-link <?= ($current_page == 'index.php' && $page_group == 'clientes') ? 'active' : '' ?>">
                                    <i class="far fa-circle nav-icon"></i><p>Listar Clientes</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="<?= BASE_URL ?>/views/clientes/create.php" class="nav-link <?= ($current_page == 'create.php' && $page_group == 'clientes') ? 'active' : '' ?>">
                                    <i class="far fa-circle nav-icon"></i><p>Novo Cliente</p>
                                </a>
                            </li>
                        </ul>
                    </li>
                     <li class="nav-item <?= ($page_group == 'produtos') ? 'menu-open' : '' ?>">
                        <a href="#" class="nav-link <?= ($page_group == 'produtos') ? 'active' : '' ?>">
                            <i class="nav-icon fas fa-boxes"></i>
                            <p>Produtos/Itens<i class="right fas fa-angle-left"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="<?= BASE_URL ?>/views/produtos/index.php" class="nav-link <?= ($current_page == 'index.php' && $page_group == 'produtos') ? 'active' : '' ?>">
                                    <i class="far fa-circle nav-icon"></i><p>Listar Produtos</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="<?= BASE_URL ?>/views/produtos/create.php" class="nav-link <?= ($current_page == 'create.php' && $page_group == 'produtos') ? 'active' : '' ?>">
                                    <i class="far fa-circle nav-icon"></i><p>Novo Produto</p>
                                </a>
                            </li>
                             <li class="nav-item">
                                <a href="<?= BASE_URL ?>/views/produtos/categorias.php" class="nav-link <?= ($current_page == 'categorias.php' && $page_group == 'produtos') ? 'active' : '' ?>">
                                    <i class="far fa-circle nav-icon"></i><p>Categorias</p>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="nav-header">ADMINISTRAÇÃO</li>
                    <li class="nav-item <?= ($page_group == 'usuarios') ? 'menu-open' : '' ?>">
                        <a href="#" class="nav-link <?= ($page_group == 'usuarios') ? 'active' : '' ?>">
                            <i class="nav-icon fas fa-user-cog"></i>
                            <p>Usuários<i class="right fas fa-angle-left"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="<?= BASE_URL ?>/views/usuarios/index.php" class="nav-link <?= ($current_page == 'index.php' && $page_group == 'usuarios') ? 'active' : '' ?>">
                                    <i class="far fa-circle nav-icon"></i><p>Listar Usuários</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="<?= BASE_URL ?>/views/usuarios/create.php" class="nav-link <?= ($current_page == 'create.php' && $page_group == 'usuarios') ? 'active' : '' ?>">
                                    <i class="far fa-circle nav-icon"></i><p>Novo Usuário</p>
                                </a>
                            </li>
                        </ul>
                    </li>
                     <li class="nav-item <?= ($page_group == 'configuracoes') ? 'menu-open' : '' ?>">
                        <a href="#" class="nav-link <?= ($page_group == 'configuracoes') ? 'active' : '' ?>">
                            <i class="nav-icon fas fa-cogs"></i>
                            <p>Configurações<i class="right fas fa-angle-left"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="<?= BASE_URL ?>/views/configuracoes/gerais.php" class="nav-link <?= ($current_page == 'gerais.php' && $page_group == 'configuracoes') ? 'active' : '' ?>">
                                    <i class="far fa-circle nav-icon"></i><p>Gerais</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="<?= BASE_URL ?>/views/configuracoes/numeracao.php" class="nav-link <?= ($current_page == 'numeracao.php' && $page_group == 'configuracoes') ? 'active' : '' ?>">
                                    <i class="far fa-circle nav-icon"></i><p>Numeração Sequencial</p>
                                </a>
                            </li>
                            <!-- Adicionar mais links de configurações conforme necessário -->
                        </ul>
                    </li>
                </ul>
            </nav>
            <!-- /.sidebar-menu -->
        </div>
        <!-- /.sidebar -->
    </aside>

    <!-- Content Wrapper. Contains page content -->
    <!-- O fechamento desta tag .content-wrapper será feito no footer.php -->
    <!-- Mas o conteúdo principal da página começa aqui, dentro de um container -->
    <div class="content-wrapper">
        <!-- O conteúdo específico da página será inserido aqui pela view -->
        <!-- Geralmente, as views específicas começam com <section class="content-header"> e <section class="content"> -->

    <!-- Essa estrutura pode variar, mas o .content-wrapper é padrão do AdminLTE -->
    <!-- As views (create.php, index.php, etc.) devem fechar suas próprias tags <section> -->
    <!-- O footer.php fechará o .content-wrapper -->

    <!-- Ajuste para layout: container principal para o conteúdo da página -->
    <!-- Este .main-container pode não ser necessário se o .content-wrapper já fizer o papel -->
    <!-- <div class="container-fluid main-container"> -->
        <!-- <div class="row"> -->
            <!-- <div class="col-md-12"> --> <!-- Ou col-md-9 se houver sidebar lateral de conteúdo -->
                <!-- Conteúdo da Página (incluído pela view) -->