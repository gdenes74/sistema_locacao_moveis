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

$current_page = basename($_SERVER['PHP_SELF']);
$page_group = '';

// Determina o grupo da página para ativar o menu correto
if (strpos($_SERVER['REQUEST_URI'], '/views/dashboard/') !== false) {
    $page_group = 'dashboard';
} elseif (strpos($_SERVER['REQUEST_URI'], '/views/orcamentos/') !== false) {
    $page_group = 'orcamentos';
} elseif (strpos($_SERVER['REQUEST_URI'], '/views/pedidos/') !== false) {
    $page_group = 'pedidos';
} elseif (strpos($_SERVER['REQUEST_URI'], '/views/clientes/') !== false) {
    $page_group = 'clientes';
} elseif (strpos($_SERVER['REQUEST_URI'], '/views/produtos/') !== false ||
          strpos($_SERVER['REQUEST_URI'], '/views/secoes/') !== false ||
          strpos($_SERVER['REQUEST_URI'], '/views/categorias/') !== false ||
          strpos($_SERVER['REQUEST_URI'], '/views/subcategorias/') !== false) {
    $page_group = 'produtos';
} elseif (strpos($_SERVER['REQUEST_URI'], '/views/usuarios/') !== false) {
    $page_group = 'usuarios';
} elseif (strpos($_SERVER['REQUEST_URI'], '/views/configuracoes/') !== false) {
    $page_group = 'configuracoes';
}

$appName = defined('APP_NAME') ? APP_NAME : 'Sistema Toalhas';
$appVersion = defined('APP_VERSION') ? APP_VERSION : '1.0.0';

$resolved_page_title = isset($page_title) ? htmlspecialchars($page_title) . ' | ' . htmlspecialchars($appName) : htmlspecialchars($appName);

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $resolved_page_title ?></title>

    <link rel="icon" href="<?= BASE_URL ?>/assets/img/logo_toalhas_icon2.png" type="image/png">

    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- Theme style (AdminLTE) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/admin-lte/3.2.0/css/adminlte.min.css">

    <!-- Select2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@ttskch/select2-bootstrap4-theme@1.5.2/dist/select2-bootstrap4.min.css">

    <!-- Tempusdominus Bootstrap 4 CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tempusdominus-bootstrap-4/5.39.0/css/tempusdominus-bootstrap-4.min.css">

    <!-- Bootstrap Datepicker CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/css/bootstrap-datepicker.standalone.min.css">

    <!-- Toastr CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">

    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    <?php
    if (isset($extra_css) && is_array($extra_css)) {
        foreach ($extra_css as $css_file) {
            echo '<link rel="stylesheet" href="' . htmlspecialchars($css_file, ENT_QUOTES, 'UTF-8') . '">' . "\n";
        }
    }
    ?>
    <style>
        .select2-container--open { z-index: 9999999 !important; }
        .datepicker { z-index: 999999 !important; }
        body.modal-open .datepicker { z-index: 1051 !important; }
        .table-responsive { overflow-x: auto; }
        .form-control-sm { height: calc(1.8125rem + 2px); padding: .25rem .5rem; font-size: .875rem; line-height: 1.5; border-radius: .2rem; }
        .input-group-append .input-group-text { padding: .375rem .5rem; }
        .table th, .table td { vertical-align: middle !important; }

        /* INÍCIO: Estilos para aumentar o logo da marca na sidebar */
        .main-sidebar .brand-link .brand-image {
            /* A imagem do usuário no painel é ~34px. Vamos tentar 45px para o logo da marca. */
            max-height: 35px !important; /* Altura máxima */
            height: 35px !important;     /* Força a altura. */
            width: 35px !important;      /* Força a largura. Se sua imagem não for quadrada, ajuste width para 'auto' ou vice-versa para manter proporção */
            object-fit: contain;         /* Garante que a imagem caiba sem distorcer, especialmente se não for quadrada */
            margin-right: 10px !important;/* Espaço entre o logo e o texto "Sistema Controle de Toalhas" */
            
            /* O AdminLTE pode aplicar margens padrões, especialmente para .img-circle.
               Ajuste conforme necessário para centralizar bem. */
            margin-top: 0 !important;
            margin-left: 0 !important; /* O padding-left do .brand-link cuidará do espaçamento inicial */
            /* Se você removeu .img-circle, talvez precise de um margin-left: 0.5rem ou similar */
        }

        .main-sidebar .brand-link {
            /* O container do link precisa acomodar a nova altura e alinhar os itens */
            height: auto !important;          /* Altura se ajusta ao conteúdo */
            /* Ajuste min-height para ser um pouco maior que a altura do seu logo + padding vertical */
            min-height: calc(45px + 1rem) !important; /* Exemplo: 45px logo + 0.5rem padding-top + 0.5rem padding-bottom */
            display: flex !important;         /* Usa flexbox para alinhar itens internos */
            align-items: center !important;   /* Alinha verticalmente o logo e o texto no centro do link */
            padding-top: 0.5rem !important;   /* Padding padrão do AdminLTE, ajuste se necessário */
            padding-bottom: 0.5rem !important;/* Padding padrão do AdminLTE, ajuste se necessário */
            padding-left: 0.8rem !important;  /* Ajuste este para o espaçamento esquerdo do logo. O padrão é 0.5rem + 0.5rem (para .brand-image.img-circle) */
            padding-right: 0.5rem !important; /* Padding padrão */
        }

        /* Opcional: Ajustar o texto da marca se necessário */
        .main-sidebar .brand-link .brand-text {
            font-size: 1.1rem; /* Você pode ajustar o tamanho do texto se desejar */
            /* O margin-left do texto é automaticamente gerenciado pelo margin-right do brand-image e pelo espaçamento do flex */
        }
        /* FIM: Estilos para aumentar o logo da marca na sidebar */

    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed sidebar-collapse" data-lte-preloader-delay="8000">
<div class="wrapper">

    <!-- Preloader -->
    <div class="preloader flex-column justify-content-center align-items-center" style="background-color: rgba(255,255,255,0.8);">
        <img class="animation__shake" src="<?= BASE_URL ?>/assets/img/logo_toalhas_loader.png" alt="<?= htmlspecialchars($appName) ?> Logo" height="180" width="180">
        <p class="mt-2">Carregando...</p>
    </div>

    <!-- Navbar -->
    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
            </li>
            <li class="nav-item d-none d-sm-inline-block">
                <a href="<?= BASE_URL ?>/views/dashboard/index.php" class="nav-link">Início</a>
            </li>
        </ul>
        <ul class="navbar-nav ml-auto">
            <li class="nav-item dropdown">
                <a class="nav-link" data-toggle="dropdown" href="#">
                    <i class="far fa-bell"></i>
                    <span class="badge badge-warning navbar-badge">0</span>
                </a>
                <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                    <span class="dropdown-item dropdown-header">0 Notificações</span>
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
                    <img src="<?= BASE_URL ?>/assets/img/avatar_placeholder2.png" class="user-image img-circle elevation-2" alt="User Image">
                    <span class="d-none d-md-inline"><?= isset($_SESSION['usuario_nome']) ? htmlspecialchars($_SESSION['usuario_nome']) : 'Usuário' ?></span>
                </a>
                <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                    <li class="user-header bg-primary">
                        <img src="<?= BASE_URL ?>/assets/img/avatar_placeholder2.png" class="img-circle elevation-2" alt="User Image">
                        <p>
                            <?= isset($_SESSION['usuario_nome']) ? htmlspecialchars($_SESSION['usuario_nome']) : 'Usuário' ?>
                            <small>Membro desde <?= isset($_SESSION['usuario_data_cadastro']) ? date('M. Y', strtotime($_SESSION['usuario_data_cadastro'])) : 'N/A' ?></small>
                        </p>
                    </li>
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
        <!-- MODIFICAÇÃO IMPORTANTE: Certifique-se que a imagem aqui seja a que você quer exibir maior -->
        <a href="<?= BASE_URL ?>/views/dashboard/index.php" class="brand-link">
            <!-- A imagem do logo é carregada aqui. Verifique se o caminho e o nome do arquivo estão corretos. -->
            <!-- Se o seu logo NÃO for redondo, você pode remover a classe 'img-circle' abaixo. -->
            <img src="<?= BASE_URL ?>/assets/img/logo_toalhas_icon2.png" alt="<?= htmlspecialchars($appName) ?> Logo" class="brand-image img-circle elevation-3" style="opacity: .8">
            <span class="brand-text font-weight-light"><?= htmlspecialchars($appName) ?></span>
        </a>

        <div class="sidebar">
            <div class="user-panel mt-3 pb-3 mb-3 d-flex">
                <div class="image">
                    <img src="<?= BASE_URL ?>/assets/img/avatar_placeholder2.png" class="img-circle elevation-2" alt="User Image">
                </div>
                <div class="info">
                    <a href="#" class="d-block"><?= isset($_SESSION['usuario_nome']) ? htmlspecialchars($_SESSION['usuario_nome']) : 'Usuário' ?></a>
                </div>
            </div>

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

            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column nav-child-indent nav-legacy" data-widget="treeview" role="menu" data-accordion="false">
                    <li class="nav-item">
                        <a href="<?= BASE_URL ?>/views/dashboard/index.php" class="nav-link <?= ($page_group == 'dashboard') ? 'active' : '' ?>">
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
                    <li class="nav-item <?= ($page_group == 'pedidos') ? 'menu-open' : '' ?>">
                        <a href="#" class="nav-link <?= ($page_group == 'pedidos') ? 'active' : '' ?>">
                            <i class="nav-icon fas fa-shopping-cart"></i>
                            <p>Pedidos<i class="right fas fa-angle-left"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="<?= BASE_URL ?>/views/pedidos/index.php" class="nav-link <?= ($current_page == 'index.php' && $page_group == 'pedidos') ? 'active' : '' ?>">
                                    <i class="far fa-circle nav-icon"></i><p>Listar Pedidos</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="<?= BASE_URL ?>/views/pedidos/create.php" class="nav-link <?= ($current_page == 'create.php' && $page_group == 'pedidos') ? 'active' : '' ?>">
                                    <i class="far fa-circle nav-icon"></i><p>Novo Pedido</p>
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
                                <a href="<?= BASE_URL ?>/views/produtos/index.php" class="nav-link <?= ($current_page == 'index.php' && $page_group == 'produtos' && strpos($_SERVER['REQUEST_URI'], '/views/produtos/') !== false) ? 'active' : '' ?>">
                                    <i class="far fa-circle nav-icon"></i><p>Listar Produtos</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="<?= BASE_URL ?>/views/produtos/create.php" class="nav-link <?= ($current_page == 'create.php' && $page_group == 'produtos' && strpos($_SERVER['REQUEST_URI'], '/views/produtos/') !== false) ? 'active' : '' ?>">
                                    <i class="far fa-circle nav-icon"></i><p>Novo Produto</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="<?= BASE_URL ?>/views/secoes/index.php" class="nav-link <?= ($page_group == 'produtos' && strpos($_SERVER['REQUEST_URI'], '/views/secoes/') !== false) ? 'active' : '' ?>">
                                    <i class="far fa-circle nav-icon"></i><p>Seções</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="<?= BASE_URL ?>/views/categorias/index.php" class="nav-link <?= ($page_group == 'produtos' && (strpos($_SERVER['REQUEST_URI'], '/views/categorias/index.php') !== false || strpos($_SERVER['REQUEST_URI'], '/views/categorias.php') !== false)) ? 'active' : '' ?>">
                                    <i class="far fa-circle nav-icon"></i><p>Categorias</p>
                                </a>
                            </li>
                             <li class="nav-item">
                                <a href="<?= BASE_URL ?>/views/subcategorias/index.php" class="nav-link <?= ($page_group == 'produtos' && strpos($_SERVER['REQUEST_URI'], '/views/subcategorias/') !== false) ? 'active' : '' ?>">
                                    <i class="far fa-circle nav-icon"></i><p>Subcategorias</p>
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
                        </ul>
                    </li>
                </ul>
            </nav>
            <!-- /.sidebar-menu -->
        </div>
        <!-- /.sidebar -->
    </aside>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js" integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4=" crossorigin="anonymous"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>

    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">
        <!-- O conteúdo específico da página será inserido aqui pela view -->