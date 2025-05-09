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

// Variáveis para repopular o formulário
$nome = '';
$telefone = '';
$email = '';
$cpf_cnpj = '';
$endereco = '';
$cidade = '';
$observacoes = '';

// --- Processamento do Formulário (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Coleta e sanitização dos dados
    $cliente->nome = filter_input(INPUT_POST, 'nome', FILTER_SANITIZE_SPECIAL_CHARS);
    $cliente->telefone = filter_input(INPUT_POST, 'telefone', FILTER_SANITIZE_SPECIAL_CHARS) ?: null;
    $cliente->email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL) ?: null;
    $cliente->cpf_cnpj = filter_input(INPUT_POST, 'cpf_cnpj', FILTER_SANITIZE_SPECIAL_CHARS) ?: null;
    $cliente->endereco = filter_input(INPUT_POST, 'endereco', FILTER_SANITIZE_SPECIAL_CHARS) ?: null;
    $cliente->cidade = filter_input(INPUT_POST, 'cidade', FILTER_SANITIZE_SPECIAL_CHARS) ?: null;
    $cliente->observacoes = filter_input(INPUT_POST, 'observacoes', FILTER_SANITIZE_SPECIAL_CHARS) ?: null;

    // Guarda os valores para repopular
    $nome = $cliente->nome;
    $telefone = $cliente->telefone;
    $email = $cliente->email;
    $cpf_cnpj = $cliente->cpf_cnpj;
    $endereco = $cliente->endereco;
    $cidade = $cliente->cidade;
    $observacoes = $cliente->observacoes;

    // Validação
    if (empty($cliente->nome)) {
        $_SESSION['error_message'] = "O campo 'Nome' do cliente é obrigatório.";
    } elseif ($cliente->email === false && !empty($_POST['email'])) {
        $_SESSION['error_message'] = "O formato do Email informado é inválido.";
        $email = $_POST['email'];
    } else {
        // Tenta criar
        if ($cliente->create()) {
            $_SESSION['success_message'] = "Cliente '{$cliente->nome}' cadastrado com sucesso!";
            header("Location: index.php");
            exit;
        } else {
            $_SESSION['error_message'] = "Não foi possível cadastrar o cliente.";
        }
    }
}

$page_title = "Adicionar Novo Cliente";

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
        </div>
    </div>
    <hr>

    <?php
    // Exibe mensagens de feedback
    if (isset($_SESSION['message'])) {
        echo "<div class='alert alert-success alert-dismissible fade show' role='alert'>" . htmlspecialchars($_SESSION['message']) . "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>";
        unset($_SESSION['message']);
    }
    if (isset($_SESSION['error_message'])) {
        echo "<div class='alert alert-danger alert-dismissible fade show' role='alert'>" . htmlspecialchars($_SESSION['error_message']) . "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>";
        unset($_SESSION['error_message']);
    }
    ?>

    <div class="card card-primary">
        <div class="card-header">
            <h3 class="card-title">Informações do Cliente</h3>
        </div>
        <form method="POST" action="create.php">
            <div class="card-body">

                <!-- Linha para Nome e CPF/CNPJ -->
                <div class="row mb-3">
                    <div class="col-md-7"> <!-- Ajustado para 7 colunas -->
                        <div class="form-group">
                            <label for="nome">Nome Completo <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nome" name="nome" required placeholder="Digite o nome do cliente" value="<?php echo htmlspecialchars($nome); ?>">
                        </div>
                    </div>
                    <div class="col-md-5"> <!-- Ajustado para 5 colunas -->
                        <div class="form-group">
                            <label for="cpf_cnpj">CPF/CNPJ</label>
                            <input type="text" class="form-control" id="cpf_cnpj" name="cpf_cnpj" placeholder="Digite o CPF ou CNPJ (opcional)" value="<?php echo htmlspecialchars($cpf_cnpj); ?>">
                            <!-- Poderia adicionar máscara JS aqui depois -->
                        </div>
                    </div>
                </div>

                <!-- Linha para Telefone e Email -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="telefone">Telefone</label>
                            <input type="tel" class="form-control" id="telefone" name="telefone" placeholder="(00) 00000-0000" value="<?php echo htmlspecialchars($telefone); ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" class="form-control" id="email" name="email" placeholder="cliente@email.com" value="<?php echo htmlspecialchars($email); ?>">
                        </div>
                    </div>
                </div>

                <!-- Linha para Endereço e Cidade -->
                <div class="row mb-3">
                    <div class="col-md-8">
                        <div class="form-group">
                            <label for="endereco">Endereço</label>
                            <input type="text" class="form-control" id="endereco" name="endereco" placeholder="Rua Exemplo, 123 - Bairro Modelo" value="<?php echo htmlspecialchars($endereco); ?>">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="cidade">Cidade</label>
                            <input type="text" class="form-control" id="cidade" name="cidade" placeholder="Nome da Cidade" value="<?php echo htmlspecialchars($cidade); ?>">
                        </div>
                    </div>
                </div>

                <!-- Campo Observações -->
                <div class="form-group mb-3">
                    <label for="observacoes">Observações</label>
                    <textarea class="form-control" id="observacoes" name="observacoes" rows="4" placeholder="Digite aqui informações adicionais..."><?php echo htmlspecialchars($observacoes); ?></textarea>
                </div>

            </div>
            <!-- /.card-body -->

            <div class="card-footer text-end">
                <a href="index.php" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Salvar Cliente</button>
            </div>
        </form>
    </div>
    <!-- /.card -->

</div> <!-- /.container -->

<?php
// Inclui o rodapé
require_once '../includes/footer.php';
?>

<!-- Scripts JS opcionais aqui (ex: máscara para CPF/CNPJ e Telefone) -->
<!--
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
<script>
$(document).ready(function(){
    $('#telefone').mask('(00) 00000-0000');
    var CpfCnpjMaskBehavior = function (val) {
      return val.replace(/\D/g, '').length <= 11 ? '000.000.000-009' : '00.000.000/0000-00';
    },
    cpfCnpjpOptions = {
      onKeyPress: function(val, e, field, options) {
          field.mask(CpfCnpjMaskBehavior.apply({}, arguments), options);
        }
    };
    $('#cpf_cnpj').mask(CpfCnpjMaskBehavior, cpfCnpjpOptions);
});
</script>
-->