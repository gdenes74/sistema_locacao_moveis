<?php
// Incluir arquivos essenciais
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Secao.php';

// Configurar o título da página
$page_title = "Gerenciar Seções";

// Criar conexão com o banco de dados
$database = new Database();
$db = $database->getConnection();

// Instanciar o objeto Secao
$secao = new Secao($db);

// --- LÓGICA DE PESQUISA ---
$searchTerm = ''; // Inicializa a variável do termo de pesquisa
if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
    $searchTerm = trim($_GET['search']);
    $stmt = $secao->buscarPorNome($searchTerm); // Chama o novo método
} else {
    $stmt = $secao->listar(); // Comportamento padrão: listar tudo
}
$num = $stmt->rowCount();
// --- FIM DA LÓGICA DE PESQUISA ---

// Incluir o cabeçalho da página (HTML, CSS, etc.)
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
                        <li class="breadcrumb-item active">Seções</li>
                    </ol>
                </div>
            </div>
        </div><!-- /.container-fluid -->
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
            <!-- Exibir mensagens de sucesso ou erro vindas da sessão -->
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
                            <label for="search" class="sr-only">Pesquisar Seção:</label>
                            <input type="text" class="form-control w-100" id="search" name="search" placeholder="Digite o nome da seção para pesquisar..." value="<?= htmlspecialchars($searchTerm) ?>">
                        </div>
                        <button type="submit" class="btn btn-primary mr-2"><i class="fas fa-search"></i> Pesquisar</button>
                        <?php if (!empty($searchTerm)): // Mostra o botão de limpar apenas se houver uma pesquisa ativa ?>
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
                            // CORRIGIDO AQUI
                            echo 'Resultados da busca por: "' . htmlspecialchars($searchTerm) . '"';
                        } else {
                            echo "Lista de Seções Cadastradas";
                        }
                        ?>
                    </h3>
                    <div class="card-tools">
                        <a href="create.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Nova Seção
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <?php if ($num > 0): ?>
                        <table id="tabelaSecoes" class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nome</th>
                                    <th>Descrição</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
                                    <?php extract($row); ?>
                                    <tr>
                                        <td><?= htmlspecialchars($id) ?></td>
                                        <td><?= htmlspecialchars($nome) ?></td>
                                        <td><?= htmlspecialchars($descricao) ?></td>
                                        <td>
                                            <a href="edit.php?id=<?= $id ?>" class="btn btn-info btn-sm" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="delete.php?id=<?= $id ?>" class="btn btn-danger btn-sm" title="Excluir" onclick="return confirm('Tem certeza que deseja excluir esta seção? \nATENÇÃO: Isso não será possível se houver categorias vinculadas a ela.');">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th>ID</th>
                                    <th>Nome</th>
                                    <th>Descrição</th>
                                    <th>Ações</th>
                                </tr>
                            </tfoot>
                        </table>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <?php
                            if (!empty($searchTerm)) {
                                // CORRIGIDO AQUI
                                echo 'Nenhuma seção encontrada com o termo "' . htmlspecialchars($searchTerm) . '".';
                            } else {
                                echo "Nenhuma seção cadastrada ainda.";
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