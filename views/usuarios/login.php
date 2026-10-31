<?php
require_once __DIR__ . '/../../config/config.php';
// Retorno local e limitado às fichas de produção; não aceita URLs externas.
$retorno = (string)($_POST['retorno'] ?? $_GET['retorno'] ?? '');
if (!preg_match('~^views/producao/(?:edit|calculadora_tecido)\.php\?id=[1-9][0-9]*$~D', $retorno)) $retorno = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Usuário e senha fixos para teste
    $usuario = $_POST['usuario'];
    $senha = $_POST['senha'];

    if ($usuario === 'admin' && $senha === 'admin123') {
        $_SESSION['user_id'] = 1; // Simulação de login com ID do usuário
        $_SESSION['user_nome'] = 'Administrador'; // Nome do usuário
        $_SESSION['user_level'] = 'admin'; // Adicione esta linha
        header('Location: ' . ($retorno !== '' ? BASE_URL . '/' . $retorno : '../dashboard/dashboard.php'));
        exit;
    } else {
        $erro = 'Usuário ou senha inválidos!';
    }
}
?>

<?php $page_title = 'Login - Sistema Toalhas'; ?>
<?php include_once '../includes/header.php'; ?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <h2 class="text-center">Login</h2>
        <?php if (isset($erro)): ?>
            <div class="alert alert-danger"><?php echo $erro; ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <input type="hidden" name="retorno" value="<?= htmlspecialchars($retorno, ENT_QUOTES, 'UTF-8') ?>">
            <div class="form-group">
                <label for="usuario">Usuário:</label>
                <input type="text" class="form-control" id="usuario" name="usuario" required>
            </div>
            <div class="form-group">
                <label for="senha">Senha:</label>
                <input type="password" class="form-control" id="senha" name="senha" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Entrar</button>
        </form>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>
