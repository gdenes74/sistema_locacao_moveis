<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/ConfiguracaoTexto.php';

$page_title = "Desativar Texto Padrão";

if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    $_SESSION['error_message'] = "ID do texto padrão inválido.";
    redirect('views/configuracoes_textos/index.php');
}

$database = new Database();
$db = $database->getConnection();
$configTexto = new ConfiguracaoTexto($db);
$configTexto->id = (int)$_GET['id'];

if (!$configTexto->buscarPorId()) {
    $_SESSION['error_message'] = "Texto padrão não encontrado.";
    redirect('views/configuracoes_textos/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($configTexto->desativar()) {
        $_SESSION['message'] = "Texto padrão desativado com sucesso!";
    } else {
        $_SESSION['error_message'] = "Não foi possível desativar o texto padrão.";
    }
    redirect('views/configuracoes_textos/index.php');
}

include_once __DIR__ . '/../includes/header.php';
?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6"><h1><?php echo htmlspecialchars($page_title); ?></h1></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>views/dashboard/dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>views/configuracoes_textos/index.php">Textos Padrão</a></li>
                        <li class="breadcrumb-item active">Desativar</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="card card-danger">
                <div class="card-header"><h3 class="card-title">Atenção</h3></div>
                <div class="card-body">
                    <p class="lead">Você tem certeza que deseja desativar este texto padrão?</p>
                    <ul>
                        <li><strong>ID:</strong> <?php echo (int)$configTexto->id; ?></li>
                        <li><strong>Chave:</strong> <code><?php echo htmlspecialchars($configTexto->chave); ?></code></li>
                        <li><strong>Título:</strong> <?php echo htmlspecialchars($configTexto->titulo); ?></li>
                    </ul>
                    <p class="text-muted">O texto não será apagado do banco. Ele apenas deixará de ser usado como padrão enquanto estiver inativo.</p>

                    <form method="POST" action="delete.php?id=<?php echo (int)$configTexto->id; ?>">
                        <div class="text-right">
                            <a href="index.php" class="btn btn-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-danger">Confirmar Desativação</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>
</div>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
