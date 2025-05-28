<?php
// views/includes/sidebar.php
if (!defined('BASE_URL')) {
    if (file_exists(__DIR__ . '/../../config/config.php')) {
        require_once __DIR__ . '/../../config/config.php';
    } else {
        die('Erro Crítico: BASE_URL não está definida no sidebar.php.');
    }
}
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isAdmin = function_exists('hasAccess') ? hasAccess('admin') : (isset($_SESSION['user_level']) && $_SESSION['user_level'] == 'admin');
?>
<div class="custom-sidebar"> <!-- Use a classe definida no CSS do header -->
    <h5>Navegação</h5>
    <ul class="nav flex-column">
        <li class="nav-item"><a class="nav-link" href="<?php echo BASE_URL; ?>views/dashboard/dashboard.php">Dashboard</a></li>
        <li class="nav-item"><a class="nav-link" href="<?php echo BASE_URL; ?>views/clientes/index.php">Clientes</a></li>
        <li class="nav-item"><a class="nav-link" href="<?php echo BASE_URL; ?>views/produtos/index.php">Produtos</a></li>
        <li class="nav-item"><a class="nav-link" href="<?php echo BASE_URL; ?>views/orcamentos/index.php">Orçamentos</a></li>
        <li class="nav-item"><a class="nav-link" href="<?php echo BASE_URL; ?>views/pedidos/index.php">Pedidos</a></li>
        <!-- Adicione outros links -->
        <li class="nav-item"><a class="nav-link" href="<?php echo BASE_URL; ?>views/secoes/index.php">Seções</a></li>
        <li class="nav-item"><a class="nav-link" href="<?php echo BASE_URL; ?>views/categorias/index.php">Categorias</a></li>
        <li class="nav-item"><a class="nav-link" href="<?php echo BASE_URL; ?>views/subcategorias/index.php">Subcategorias</a></li>

        <?php if ($isAdmin): ?>
            <h6 class="mt-3">Admin</h6>
            <li class="nav-item"><a class="nav-link" href="<?php echo BASE_URL; ?>views/usuarios/index.php">Usuários</a></li> <!-- Supondo que index.php lista usuários -->
        <?php endif; ?>
    </ul>
</div>