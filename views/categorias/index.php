<?php
// Incluir arquivos essenciais
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Categoria.php';

// Configurar o título da página
$page_title = "Gerenciar Categorias";

// Conexão com o banco
$database = new Database();
$db = $database->getConnection();

// Instanciar Categoria
$categoria = new Categoria($db);

// LÓGICA DE PESQUISA
$searchTerm = '';
if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
    $searchTerm = trim($_GET['search']);
    $stmt = $categoria->buscarPorTermo($searchTerm);
} else {
    $stmt = $categoria->listar();
}

$num = 0;
if ($stmt) {
    $num = $stmt->rowCount();
}
// FIM DA LÓGICA DE PESQUISA

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
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/views/dashboard/index.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Categorias</li>
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
                    <?= htmlspecialchars($_SESSION['message']) ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <?php unset($_SESSION['message']); ?>
            <?php endif; ?>
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($_SESSION['error_message']) ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>

            <!-- === FORMULÁRIO DE PESQUISA === -->
            <div class="card card-default mb-4">
                <div class="card-body">
                    <form action="index.php" method="GET" class="form-inline">
                        <div class="form-group mr-2 flex-grow-1">
                            <label for="search" class="sr-only">Pesquisar Categoria:</label>
                            <input type="text" class="form-control w-100" id="search" name="search" placeholder="Digite o nome da categoria para pesquisar..." value="<?= htmlspecialchars($searchTerm) ?>">
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
                            echo "Lista de Categorias Cadastradas (" . $num . " total)";
                        }
                        ?>
                    </h3>
                    <div class="card-tools">
                        <a href="create.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Nova Categoria
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <?php if ($num > 0): ?>
                        <table id="tabelaCategorias" class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nome da Categoria</th>
                                    <th>Seção</th> <!-- Coluna para mostrar a seção -->
                                    <th>Descrição</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
                                    <?php extract($row); // Extrai $id, $nome, $secao_id, $secao_nome, $descricao ?>
                                    <tr>
                                        <td><?= htmlspecialchars($id) ?></td>
                                        <td><?= htmlspecialchars($nome) ?></td>
                                        <td><?= htmlspecialchars($secao_nome) ?></td>
                                        <td><?= htmlspecialchars($descricao) ?></td>
                                        <td>
                                            <a href="edit.php?id=<?= $id ?>" class="btn btn-info btn-sm" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="delete.php?id=<?= $id ?>" class="btn btn-danger btn-sm" title="Excluir" onclick="return confirm('Tem certeza que deseja excluir esta categoria? \nATENÇÃO: Isso não será possível se houver subcategorias vinculadas a ela.');">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th>ID</th>
                                    <th>Nome da Categoria</th>
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
                                echo 'Nenhuma categoria encontrada com o termo "' . htmlspecialchars($searchTerm) . '".';
                            } else {
                                echo "Nenhuma categoria cadastrada ainda.";
                            }
                            ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
</div>

<?php
include_once __DIR__ . '/../includes/footer.php';
?>