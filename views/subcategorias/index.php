<?php
// Incluir arquivos essenciais
require_once __DIR__ . '/../../config/config.php'; 
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Subcategoria.php';

// Configurar o título da página
$page_title = "Gerenciar Subcategorias";

// Conexão com o banco
$database = new Database();
$db = $database->getConnection();

// Instanciar Subcategoria
$subcategoria = new Subcategoria($db);

// LÓGICA DE PESQUISA
$searchTerm = '';
if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
    $searchTerm = trim($_GET['search']);
    $stmt = $subcategoria->buscarPorTermo($searchTerm); // Chama o método buscando só no nome da subcategoria
} else {
    $stmt = $subcategoria->listar();
}

$num = 0;
if ($stmt) {
    $num = $stmt->rowCount();
}

// Incluir cabeçalho 
include_once __DIR__ . '/../includes/header.php'; 
 
?>

<!-- Content Wrapper. Contém o conteúdo da página -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><?php echo htmlspecialchars($page_title); ?></h1>
                </div>
                <div class="col-sm-6">
                    <!-- Breadcrumb -->
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/views/dashboard/index.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Subcategorias</li>
                    </ol>
                </div>
            </div>
        </div><!-- /.container-fluid -->
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
            <!-- Mensagens de sessão -->
            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($_SESSION['message']); ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <?php unset($_SESSION['message']); ?>
            <?php endif; ?>
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($_SESSION['error_message']); ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>

            <!-- === FORMULÁRIO DE PESQUISA === -->
            <div class="card card-default mb-4">
                <div class="card-body">
                    <form action="index.php" method="GET" class="form-inline">
                        <div class="form-group mr-2 flex-grow-1">
                            <label for="search" class="sr-only">Pesquisar Subcategoria:</label>
                            <input type="text" class="form-control w-100" id="search" name="search" placeholder="Digite o nome da subcategoria..." value="<?= htmlspecialchars($searchTerm) ?>">
                        </div>
                        <button type="submit" class="btn btn-primary mr-2"><i class="fas fa-search"></i> Pesquisar</button>
                        <?php if (!empty($searchTerm)): ?>
                            <a href="index.php" class="btn btn-secondary"><i class="fas fa-eraser"></i> Limpar</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
            <!-- === FIM DO FORMULÁRIO DE PESQUISA === -->

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <?php
                        if (!empty($searchTerm)) {
                            echo 'Resultados da busca por: "' . htmlspecialchars($searchTerm) . '" (' . $num . ' encontrados)';
                        } else {
                            echo "Lista de Subcategorias Cadastradas (" . $num . " total)";
                        }
                        ?>
                    </h3>
                    <div class="card-tools">
                        <!-- Botão para adicionar nova subcategoria -->
                        <a href="create.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Nova Subcategoria
                        </a>
                    </div>
                </div>
                <!-- /.card-header -->
                <div class="card-body">
                    <?php if ($num > 0): ?>
                        <table id="tabelaSubcategorias" class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nome da Subcategoria</th>
                                    <th>Categoria</th>
                                    <th>Seção</th>
                                    <th>Descrição</th>
                                    <th>Ações</th> 
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
                                    <?php 
                                        extract($row); 
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($id); ?></td>
                                        <td><?php echo htmlspecialchars($nome); ?></td>
                                        <td><?php echo htmlspecialchars($categoria_nome); ?></td> 
                                        <td><?php echo htmlspecialchars($secao_nome); ?></td> 
                                        <td><?php echo htmlspecialchars($descricao); ?></td>
                                        <td>
                                            <!-- Botão Editar -->
                                            <a href="edit.php?id=<?php echo $id; ?>" class="btn btn-info btn-sm" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <!-- Botão Excluir -->
                                            <a href="delete.php?id=<?php echo $id; ?>" class="btn btn-danger btn-sm" title="Excluir" onclick="return confirm('Tem certeza que deseja excluir esta subcategoria? \nATENÇÃO: Isso não será possível se houver produtos vinculados a ela.');">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th>ID</th>
                                    <th>Nome da Subcategoria</th>
                                    <th>Categoria</th>
                                    <th>Seção</th>
                                    <th>Descrição</th>
                                    <th>Ações</th>
                                </tr>
                            </tfoot>
                        </table>
                    <?php else: ?>
                         <div class="alert alert-info">
                            <?php
                            if (!empty($searchTerm)) {
                                echo 'Nenhuma subcategoria encontrada com o termo "' . htmlspecialchars($searchTerm) . '".';
                            } else {
                                echo "Nenhuma subcategoria cadastrada ainda.";
                            }
                            ?>
                        </div>
                    <?php endif; ?>
                </div>
                <!-- /.card-body -->
            </div>
            <!-- /.card -->
        </div><!-- /.container-fluid -->
    </section>
    <!-- /.content -->
</div>
<!-- /.content-wrapper -->

<?php 
// Incluir o rodapé
include_once __DIR__ . '/../includes/footer.php'; 
?>