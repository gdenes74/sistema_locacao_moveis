<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Orcamento.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$database = new Database();
$conn = $database->getConnection();
$orcamentoModel = new Orcamento($conn);

// Buscar filtros, se houver (exemplo simples, pode ser expandido)
$filtros = [];
if (isset($_GET['pesquisar']) && !empty($_GET['pesquisar'])) {
    $filtros['pesquisar'] = $_GET['pesquisar'];
}
if (isset($_GET['status']) && !empty($_GET['status'])) {
    $filtros['status'] = $_GET['status'];
}

// Buscar todos os orçamentos usando a função listarTodos
$stmt = $orcamentoModel->listarTodos($filtros);
$orcamentos = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

$successMessage = $_SESSION['success_message'] ?? null;
$errorMessage = $_SESSION['error_message'] ?? null;
unset($_SESSION['success_message'], $_SESSION['error_message']);
?>
<?php include_once __DIR__ . '/../includes/header.php'; ?>

<div class="content-wrapper">
    <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>
    
    <section class="content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h1>Orçamentos</h1>
                </div>
                <div class="col-sm-6 text-right">
                    <a href="create.php" class="btn btn-success">
                        <i class="fas fa-plus"></i> Novo Orçamento
                    </a>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <?php if ($successMessage): ?>
                <div class="alert alert-success">
                    <?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>
            <?php if ($errorMessage): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Lista de Orçamentos</h3>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <form method="GET" action="index.php" class="form-inline">
                                <div class="input-group">
                                    <input type="text" name="pesquisar" class="form-control" placeholder="Pesquisar por cliente ou número..." value="<?= isset($_GET['pesquisar']) ? htmlspecialchars($_GET['pesquisar']) : '' ?>">
                                    <div class="input-group-append">
                                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Buscar</button>
                                    </div>
                                </div>
                                <select name="status" class="form-control ml-2">
                                    <option value="">Todos os Status</option>
                                    <option value="pendente" <?= isset($_GET['status']) && $_GET['status'] === 'pendente' ? 'selected' : '' ?>>Pendente</option>
                                    <option value="aprovado" <?= isset($_GET['status']) && $_GET['status'] === 'aprovado' ? 'selected' : '' ?>>Aprovado</option>
                                    <option value="recusado" <?= isset($_GET['status']) && $_GET['status'] === 'recusado' ? 'selected' : '' ?>>Recusado</option>
                                    <option value="expirado" <?= isset($_GET['status']) && $_GET['status'] === 'expirado' ? 'selected' : '' ?>>Expirado</option>
                                </select>
                            </form>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table id="orcamentosTable" class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>Número</th>
                                    <th>Código</th>
                                    <th>Cliente</th>
                                    <th>Data do Orçamento</th>
                                    <th>Status</th>
                                    <th>Valor Final (R$)</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($orcamentos)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center">Nenhum orçamento encontrado.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($orcamentos as $orcamento): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($orcamento['numero']) ?></td>
                                            <td><?= htmlspecialchars($orcamento['codigo']) ?></td>
                                            <td><?= htmlspecialchars($orcamento['nome_cliente']) ?></td>
                                            <td><?= date('d/m/Y', strtotime($orcamento['data_orcamento'])) ?></td>
                                            <td>
                                                <span class="badge badge-pill badge-<?= $this->getBadgeClass($orcamento['status']) ?>">
                                                    <?= htmlspecialchars(ucfirst($orcamento['status'])) ?>
                                                </span>
                                            </td>
                                            <td class="text-right"><?= number_format($orcamento['valor_final'], 2, ',', '.') ?></td>
                                            <td class="text-center">
                                                <a href="show.php?id=<?= $orcamento['id'] ?>" class="btn btn-info btn-xs m-1" title="Ver Detalhes"><i class="fas fa-eye fa-fw"></i></a>
                                                <a href="edit.php?id=<?= $orcamento['id'] ?>" class="btn btn-warning btn-xs m-1" title="Editar"><i class="fas fa-edit fa-fw"></i></a>
                                                <a href="delete.php?id=<?= $orcamento['id'] ?>" class="btn btn-danger btn-xs m-1" title="Excluir" onclick="return confirm('Tem certeza que deseja excluir o orçamento #<?= $orcamento['numero'] ?>? Esta ação não pode ser desfeita.');"><i class="fas fa-trash fa-fw"></i></a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer clearfix py-2">
                        <span class="float-left text-muted small">Exibindo <?= count($orcamentos); ?> orçamento(s)</span>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>

<script>
$(document).ready(function() {
    $('#orcamentosTable').DataTable({
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.10.25/i18n/Portuguese-Brasil.json"
        },
        "pageLength": 10,
        "order": [[0, "desc"]]
    });
});
</script>