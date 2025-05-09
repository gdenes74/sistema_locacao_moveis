<?php include_once __DIR__ . '/../includes/header.php'; ?>

<div class="container mt-5">
    <h1 class="mb-4 text-center">Dashboard - Sistema Toalhas</h1>
    
    <div class="row">
        <!-- Card de Produtos -->
        <div class="col-md-4 col-sm-6 mb-4">
            <div class="card shadow-sm card-custom bg-light-blue border-0">
                <div class="card-body text-center">
                    <h4 class="card-title text-primary"><i class="fas fa-box"></i> Produtos</h4>
                    <p class="card-text text-secondary">Gerencie os produtos cadastrados no sistema.</p>
                    <a href="../produtos/index.php" class="btn btn-outline-primary btn-sm"><i class="fas fa-list"></i> Listar Produtos</a>
                    <a href="../produtos/create.php" class="btn btn-outline-primary btn-sm"><i class="fas fa-plus"></i> Cadastrar Produto</a>
                </div>
            </div>
        </div>

        <!-- Card de Clientes -->
        <div class="col-md-4 col-sm-6 mb-4">
            <div class="card shadow-sm card-custom bg-light-green border-0">
                <div class="card-body text-center">
                    <h4 class="card-title text-success"><i class="fas fa-users"></i> Clientes</h4>
                    <p class="card-text text-secondary">Gerencie os clientes cadastrados.</p>
                    <a href="../clientes/index.php" class="btn btn-outline-success btn-sm"><i class="fas fa-list"></i> Listar Clientes</a>
                    <a href="../clientes/create.php" class="btn btn-outline-success btn-sm"><i class="fas fa-plus"></i> Cadastrar Cliente</a>
                </div>
            </div>
        </div>

        <!-- Card de Relatórios -->
        <div class="col-md-4 col-sm-6 mb-4">
            <div class="card shadow-sm card-custom bg-light-yellow border-0">
                <div class="card-body text-center">
                    <h4 class="card-title text-warning"><i class="fas fa-chart-line"></i> Relatórios</h4>
                    <p class="card-text text-secondary">Visualize relatórios gerenciais.</p>
                    <a href="#" class="btn btn-outline-warning btn-sm disabled"><i class="fas fa-clock"></i> Em breve</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>