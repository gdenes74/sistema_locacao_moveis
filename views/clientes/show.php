<?php
session_start(); // Para mensagens de feedback

// Incluir arquivos essenciais
require_once '../../config/database.php'; // Conexão com o banco
require_once '../../models/Cliente.php';   // Modelo de dados do Cliente
require_once '../../config/config.php';    // Para BASE_URL e outras configurações globais

// Instanciação
$database = new Database();
$db = $database->getConnection();
$cliente = new Cliente($db);

// Verificar se o ID do cliente foi passado pela URL
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    $_SESSION['error_message'] = "Erro: ID do cliente inválido ou não fornecido.";
    header("Location: index.php");
    exit;
}

$cliente_id = (int)$_GET['id'];

// Buscar os dados do cliente
if (!$cliente->getById($cliente_id)) {
    $_SESSION['error_message'] = "Erro: Cliente não encontrado.";
    header("Location: index.php");
    exit;
}

$page_title = "Detalhes do Cliente";

// Inclui o cabeçalho
require_once '../includes/header.php';
?>

<div class="container mt-4">

    <div class="row mb-2">
        <div class="col-sm-6">
            <h3><?php echo htmlspecialchars($page_title); ?></h3>
        </div>
        <div class="col-sm-6 text-end">
            <a href="index.php" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> Voltar para Lista
            </a>
            <a href="edit.php?id=<?= $cliente_id ?>" class="btn btn-warning btn-sm">
                <i class="fas fa-edit"></i> Editar
            </a>
        </div>
    </div>
    <hr>

    <div class="card card-primary">
        <div class="card-header">
            <h3 class="card-title">Informações do Cliente</h3>
        </div>
        <div class="card-body">

            <!-- Linha para Nome e CPF/CNPJ -->
            <div class="row mb-3">
                <div class="col-md-7">
                    <div class="form-group">
                        <label><strong>Nome Completo</strong></label>
                        <p class="form-control-static"><?php echo htmlspecialchars($cliente->nome); ?></p>
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="form-group">
                        <label><strong>CPF/CNPJ</strong></label>
                        <p class="form-control-static"><?php echo htmlspecialchars($cliente->cpf_cnpj ?: '-'); ?></p>
                    </div>
                </div>
            </div>

            <!-- Linha para Telefone e Email -->
            <div class="row mb-3">
                <div class="col-md-6">
                    <div class="form-group">
                        <label><strong>Telefone</strong></label>
                        <p class="form-control-static"><?php echo htmlspecialchars($cliente->telefone ?: '-'); ?></p>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label><strong>Email</strong></label>
                        <p class="form-control-static"><?php echo htmlspecialchars($cliente->email ?: '-'); ?></p>
                    </div>
                </div>
            </div>

            <!-- Linha para Endereço e Cidade -->
            <div class="row mb-3">
                <div class="col-md-8">
                    <div class="form-group">
                        <label><strong>Endereço</strong></label>
                        <p class="form-control-static"><?php echo htmlspecialchars($cliente->endereco ?: '-'); ?></p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label><strong>Cidade</strong></label>
                        <p class="form-control-static"><?php echo htmlspecialchars($cliente->cidade ?: '-'); ?></p>
                    </div>
                </div>
            </div>

            <!-- Campo Observações -->
            <div class="form-group mb-3">
                <label><strong>Observações</strong></label>
                <p class="form-control-static"><?php echo htmlspecialchars($cliente->observacoes ?: '-'); ?></p>
            </div>

            <!-- Campo Data de Cadastro -->
            <div class="form-group mb-3">
                <label><strong>Data de Cadastro</strong></label>
                <p class="form-control-static"><?php echo !empty($cliente->data_cadastro) ? date('d/m/Y H:i', strtotime($cliente->data_cadastro)) : '-'; ?></p>
            </div>

        </div>
        <!-- /.card-body -->

        <div class="card-footer text-end">
            <a href="index.php" class="btn btn-secondary">Voltar</a>
        </div>
    </div>
    <!-- /.card -->

</div> <!-- /.container -->

<?php
// Inclui o rodapé
require_once '../includes/footer.php';
?>