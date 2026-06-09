<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/ConfiguracaoTexto.php';

$page_title = "Novo Texto Padrão";
$error = null;

$database = new Database();
$db = $database->getConnection();
$configTexto = new ConfiguracaoTexto($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $configTexto->chave = $_POST['chave'] ?? '';
    $configTexto->titulo = $_POST['titulo'] ?? '';
    $configTexto->conteudo = $_POST['conteudo'] ?? '';
    $configTexto->ativo = isset($_POST['ativo']) ? 1 : 0;
    $configTexto->usuario_id = $_SESSION['usuario_id'] ?? ($_SESSION['user_id'] ?? 1);

    if (trim((string)$configTexto->chave) === '') {
        $error = "A chave é obrigatória.";
    } elseif (trim((string)$configTexto->titulo) === '') {
        $error = "O título é obrigatório.";
    } elseif ($configTexto->criar()) {
        $_SESSION['message'] = "Texto padrão criado com sucesso!";
        redirect('views/configuracoes_textos/index.php');
    } else {
        $error = "Não foi possível criar o texto. Verifique se a chave já existe.";
    }
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
                        <li class="breadcrumb-item active">Novo</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="card card-primary">
                <div class="card-header"><h3 class="card-title">Cadastro de Texto Padrão</h3></div>
                <form method="POST" action="create.php">
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?php echo htmlspecialchars($error); ?>
                                <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                            </div>
                        <?php endif; ?>

                        <div class="form-group">
                            <label for="chave">Chave <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="chave" name="chave" required placeholder="ex.: observacoes_gerais_padrao" value="<?php echo htmlspecialchars($_POST['chave'] ?? ''); ?>">
                            <small class="form-text text-muted">Use letras minúsculas, números e underline. Ex.: pix_padrao.</small>
                        </div>

                        <div class="form-group">
                            <label for="titulo">Título <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="titulo" name="titulo" required value="<?php echo htmlspecialchars($_POST['titulo'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label for="conteudo">Conteúdo</label>
                            <textarea class="form-control" id="conteudo" name="conteudo" rows="12" style="font-family: Consolas, monospace;"><?php echo htmlspecialchars($_POST['conteudo'] ?? ''); ?></textarea>
                        </div>

                        <div class="form-group">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="ativo" name="ativo" value="1" <?php echo (!isset($_POST['ativo']) || !empty($_POST['ativo'])) ? 'checked' : ''; ?>>
                                <label class="custom-control-label" for="ativo">Texto ativo</label>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer text-right">
                        <a href="index.php" class="btn btn-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Salvar Texto</button>
                    </div>
                </form>
            </div>
        </div>
    </section>
</div>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
