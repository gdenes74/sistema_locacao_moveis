<?php
// Este arquivo assume que config.php (com BASE_URL e hasAccess) já foi incluído antes.
// Normalmente, o arquivo que inclui este sidebar (ex: header.php ou a própria view) já fez isso.
?>
<aside class="sidebar">
    <nav class="main-nav">
        <ul>
            <li><a href="<?php echo BASE_URL; ?>views/dashboard/dashboard.php">Dashboard</a></li>
            <li><a href="<?php echo BASE_URL; ?>views/clientes/index.php">Clientes</a></li>
            <li><a href="<?php echo BASE_URL; ?>views/produtos/index.php">Produtos</a></li> 
            <li><a href="<?php echo BASE_URL; ?>views/consultas/listar.php">Consultas</a></li>
            <li><a href="<?php echo BASE_URL; ?>views/orcamentos/index.php">Orçamentos</a></li>
            <li><a href="<?php echo BASE_URL; ?>views/pedidos/index.php">Pedidos</a></li>
            <li><a href="<?php echo BASE_URL; ?>views/lavanderia/listar.php">Lavanderia</a></li>
            <li><a href="<?php echo BASE_URL; ?>views/manutencao/listar.php">Manutenção</a></li>
            <li><a href="<?php echo BASE_URL; ?>views/relatorios/listar.php">Relatórios</a></li>
            <li><a href="<?php echo BASE_URL; ?>views/alertas/listar.php">Alertas</a></li>
            
            <?php 
            // Verificação se o usuário logado é 'admin' para mostrar itens específicos
            // Usando a função hasAccess() do seu config.php
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