<?php
// Este arquivo assume que config.php (com BASE_URL e hasAccess) já foi incluído antes.
// Normalmente, o arquivo que inclui este sidebar (ex: header.php ou a própria view) já fez isso.
// Caso contrário, descomente a linha abaixo para incluir o config.php, se necessário.
// require_once __DIR__ . '/../../config/config.php';

// Garantir que a sessão esteja iniciada, caso ainda não esteja
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<aside class="sidebar">
    <nav class="main-nav">
        <ul>
            <li><a href="<?php echo BASE_URL; ?>views/dashboard/dashboard.php">Dashboard</a></li>
            <li><a href="<?php echo BASE_URL; ?>views/clientes/index.php">Clientes</a></li>
            <li><a href="<?php echo BASE_URL; ?>views/produtos/index.php">Produtos</a></li>
            <li><a href="<?php echo BASE_URL; ?>views/consultas/listar.php">Consultas</a></li>
            <li><a href="<?php echo BASE_URL; ?>views/orcamentos/listar.php">Orçamentos</a></li>
            <li><a href="<?php echo BASE_URL; ?>views/pedidos/listar.php">Pedidos</a></li>
            <li><a href="<?php echo BASE_URL; ?>views/lavanderia/listar.php">Lavanderia</a></li>
            <li><a href="<?php echo BASE_URL; ?>views/manutencao/listar.php">Manutenção</a></li>
            <li><a href="<?php echo BASE_URL; ?>views/relatorios/listar.php">Relatórios</a></li>
            <li><a href="<?php echo BASE_URL; ?>views/alertas/listar.php">Alertas</a></li>

            <!-- Adicionando Seções, Categorias e Subcategorias conforme solicitado -->
            <li><a href="<?php echo BASE_URL; ?>views/secoes/index.php">Seções</a></li>
            <li><a href="<?php echo BASE_URL; ?>views/categorias/index.php">Categorias</a></li>
            <li><a href="<?php echo BASE_URL; ?>views/subcategorias/index.php">Subcategorias</a></li>

            <?php
            // Verificação se o usuário logado é 'admin' para mostrar itens específicos
            // Usando a função hasAccess() do seu config.php, caso exista
            $isAdmin = function_exists('hasAccess') ? hasAccess('admin') : (isset($_SESSION['user_level']) && $_SESSION['user_level'] == 'admin');

            if ($isAdmin):
            ?>
                <!-- INÍCIO DO BLOCO DE GERENCIAMENTO DA ESTRUTURA -->
                <li><a href="<?php echo BASE_URL; ?>views/secoes/index.php">Gerenciar Seções</a></li>
                <li><a href="<?php echo BASE_URL; ?>views/categorias/index.php">Gerenciar Categorias</a></li>
                <li><a href="<?php echo BASE_URL; ?>views/subcategorias/index.php">Gerenciar Subcategorias</a></li>
                <!-- FIM DO BLOCO DE GERENCIAMENTO DA ESTRUTURA -->

                <li><a href="<?php echo BASE_URL; ?>views/usuarios/listar.php">Usuários</a></li>
            <?php endif; // Fim do if ($isAdmin) ?>
        </ul>
    </nav>
</aside>