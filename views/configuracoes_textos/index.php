<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/ConfiguracaoTexto.php';

$page_title = "Textos Padrão do Sistema";

$database = new Database();
$db = $database->getConnection();
$configTexto = new ConfiguracaoTexto($db);

$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$filtroAtivo = isset($_GET['ativo']) ? $_GET['ativo'] : '';

$stmt = $configTexto->listarTodos([
    'pesquisar' => $searchTerm,
    'ativo' => $filtroAtivo,
]);

$num = $stmt ? $stmt->rowCount() : 0;

include_once __DIR__ . '/../includes/header.php';
?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><?php echo htmlspecialchars($page_title); ?></h1>
                    <small>Cadastre e edite os textos usados como padrão em orçamentos, pedidos e impressões.</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>views/dashboard/dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Textos Padrão</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($_SESSION['message']); unset($_SESSION['message']); ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
            <?php endif; ?>

            <div class="card card-default mb-4">
                <div class="card-body">
                    <form action="index.php" method="GET" class="form-inline">
                        <div class="form-group mr-2 flex-grow-1">
                            <label for="search" class="sr-only">Pesquisar</label>
                            <input type="text" class="form-control w-100" id="search" name="search" placeholder="Pesquisar por chave, título ou conteúdo..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                        </div>
                        <div class="form-group mr-2">
                            <label for="ativo" class="sr-only">Status</label>
                            <select name="ativo" id="ativo" class="form-control">
                                <option value="" <?php echo $filtroAtivo === '' ? 'selected' : ''; ?>>Todos</option>
                                <option value="1" <?php echo $filtroAtivo === '1' ? 'selected' : ''; ?>>Ativos</option>
                                <option value="0" <?php echo $filtroAtivo === '0' ? 'selected' : ''; ?>>Inativos</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary mr-2"><i class="fas fa-search"></i> Pesquisar</button>
                        <?php if ($searchTerm !== '' || $filtroAtivo !== ''): ?>
                            <a href="index.php" class="btn btn-secondary"><i class="fas fa-eraser"></i> Limpar</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Textos cadastrados (<?php echo (int)$num; ?>)</h3>
                    <div class="card-tools">
                        <a href="create.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Novo Texto
                        </a>
                    </div>
                </div>

                <div class="card-body">
                    <?php if ($stmt && $num > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th style="width: 70px;">ID</th>
                                        <th style="width: 230px;">Chave</th>
                                        <th style="width: 260px;">Título</th>
                                        <th>Prévia</th>
                                        <th style="width: 90px;">Status</th>
                                        <th style="width: 115px;">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
                                        <?php
                                            $previa = trim(strip_tags((string)$row['conteudo']));
                                            if (mb_strlen($previa, 'UTF-8') > 180) {
                                                $previa = mb_substr($previa, 0, 180, 'UTF-8') . '...';
                                            }
                                        ?>
                                        <tr>
                                            <td><?php echo (int)$row['id']; ?></td>
                                            <td><code><?php echo htmlspecialchars($row['chave']); ?></code></td>
                                            <td><?php echo htmlspecialchars($row['titulo']); ?></td>
                                            <td style="white-space: pre-line;"><?php echo htmlspecialchars($previa); ?></td>
                                            <td>
                                                <?php if ((int)$row['ativo'] === 1): ?>
                                                    <span class="badge badge-success">Ativo</span>
                                                <?php else: ?>
                                                    <span class="badge badge-secondary">Inativo</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="edit.php?id=<?php echo (int)$row['id']; ?>" class="btn btn-info btn-sm" title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <?php if ((int)$row['ativo'] === 1): ?>
                                                    <a href="delete.php?id=<?php echo (int)$row['id']; ?>" class="btn btn-danger btn-sm" title="Desativar">
                                                        <i class="fas fa-ban"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">Nenhum texto padrão encontrado.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
</div>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
