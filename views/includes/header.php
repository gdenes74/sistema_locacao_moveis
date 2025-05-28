<?php
// views/includes/header.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Tenta incluir config.php se BASE_URL não estiver definida
if (!defined('BASE_URL')) {
    if (file_exists(__DIR__ . '/../../config/config.php')) {
        require_once __DIR__ . '/../../config/config.php';
    } else {
        die('Erro Crítico: BASE_URL não está definida. Verifique a inclusão de config/config.php no header.php ou na view principal.');
    }
}

$page_title = $page_title ?? (defined('APP_NAME') ? APP_NAME : 'Sistema Toalhas');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <title><?php echo htmlspecialchars($page_title); ?></title>

    <!-- Bootstrap CSS (v4.6.2) via CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">

    <!-- Font Awesome CSS via CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

    <!-- jQuery UI CSS via CDN (Opcional) -->
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">

    <!-- Select2 CSS via CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <!-- Tema do Select2 para Bootstrap 4 (Opcional, mas recomendado) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@ttskch/select2-bootstrap4-theme@latest/dist/select2-bootstrap4.min.css"> <!-- Use @latest ou uma versão específica -->

    <!-- Toastr CSS (Para notificações) via CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">

    <!-- SweetAlert2 CSS (Para alertas) via CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    <!-- ********************************************************* -->
    <!-- *** ADICIONAR jQuery AQUI, ANTES DE QUALQUER OUTRO JS *** -->
    <!-- ********************************************************* -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- ********************************************************* -->

    <!-- Seus Estilos Customizados Inline -->
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
        }
        .navbar-custom {
            background-color: #343a40;
        }
        .main-container {
            padding-top: 20px;
            padding-bottom: 20px;
        }
        .content-card {
            background-color: #ffffff;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-top: 15px;
        }
        .table thead th {
            background-color: #e9ecef;
            color: #495057;
            border-color: #dee2e6;
        }
        .suggestions-dropdown {
            position: absolute;
            background-color: white;
            border: 1px solid #ced4da;
            border-top: none;
            z-index: 1000; 
            width: 100%;
            max-height: 200px;
            overflow-y: auto;
            display: none; 
            left: 0; 
        }
        .suggestions-dropdown .cliente-suggestion {
            padding: 8px 12px; 
            cursor: pointer;
            border-bottom: 1px solid #eee;
            font-size: 0.9rem; 
        }
        .suggestions-dropdown .cliente-suggestion:hover {
            background-color: #f0f0f0; 
        }
        .custom-sidebar {
            background-color: #f1f1f1;
            padding: 15px;
            min-height: 80vh;
        }
        .custom-sidebar ul {
            list-style-type: none;
            padding: 0;
        }
        .custom-sidebar ul li a {
            display: block;
            padding: 8px 10px;
            text-decoration: none;
            color: #333;
        }
        .custom-sidebar ul li a:hover {
            background-color: #ddd;
        }
        #loading-produtos {
            text-align: center;
            padding: 20px;
        }
    </style>
</head>
<body>

    <!-- Exemplo de uma Navbar simples do Bootstrap -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
        <a class="navbar-brand" href="<?php echo BASE_URL; ?>"><?php echo htmlspecialchars($page_title); ?></a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav mr-auto">
                <li class="nav-item"><a class="nav-link" href="<?php echo BASE_URL; ?>views/dashboard/dashboard.php">Dashboard</a></li>
                <!-- Adicione outros links principais da navbar aqui se desejar -->
            </ul>
            <ul class="navbar-nav">
                <?php if (function_exists('isLoggedIn') && isLoggedIn()): ?>
                    <li class="nav-item">
                        <span class="navbar-text mr-3">
                            Olá, <?php echo htmlspecialchars($_SESSION['user_nome'] ?? 'Usuário'); ?>
                        </span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo BASE_URL; ?>views/usuarios/logout.php">Sair <i class="fas fa-sign-out-alt"></i></a>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo BASE_URL; ?>views/usuarios/login.php">Login</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>

    <div class="container-fluid main-container">
        <div class="row">
            <?php
            // $show_sidebar = $show_sidebar ?? true; 
            // if ($show_sidebar && file_exists(__DIR__ . '/sidebar.php')):
            ?>
                <!-- <div class="col-md-3"> -->
                    <?php // include __DIR__ . '/sidebar.php'; ?>
                <!-- </div> -->
                <!-- <div class="col-md-9"> -->
            <?php // else: ?>
                <div class="col-md-12">
            <?php // endif; ?>
                    <?php 
                    // Tenta incluir alert_messages.php, mas não quebra se não existir
                    if (file_exists(__DIR__ . '/alert_messages.php')) {
                        include_once __DIR__ . '/alert_messages.php';
                    }
                    ?>
                    <!-- O conteúdo específico da página começa aqui -->