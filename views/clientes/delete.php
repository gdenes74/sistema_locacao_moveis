<?php
// Inicia a sessão obrigatoriamente no topo
session_start();

// Incluir arquivos essenciais
require_once __DIR__ . '/../../config/config.php'; // Para BASE_URL e funções utilitárias
require_once __DIR__ . '/../../config/database.php'; // Conexão com o banco de dados
require_once __DIR__ . '/../../models/Cliente.php'; // Modelo de Cliente

// Verificar se o ID do cliente foi passado pela URL
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    $_SESSION['error_message'] = "Erro: ID do cliente inválido ou não fornecido.";
    // Redirecionar para a página de listagem de clientes
    header("Location: " . BASE_URL . "views/clientes/index.php");
    exit;
}

$cliente_id = (int)$_GET['id'];

// Instanciar conexão e o modelo Cliente
$database = new Database();
$conn = $database->getConnection();
$cliente = new Cliente($conn);

// Antes de excluir, buscar o nome do cliente para exibir na mensagem de sucesso/erro
$cliente_data = $cliente->lerPorId($cliente_id);
$nome_cliente_para_msg = "Cliente ID {$cliente_id}"; // Valor padrão
if ($cliente_data && isset($cliente_data['nome'])) {
    $nome_cliente_para_msg = htmlspecialchars($cliente_data['nome']);
}

// Tentar excluir o cliente
if ($cliente->excluir($cliente_id)) {
    // Exclusão foi bem-sucedida
    $_SESSION['success_message'] = "Cliente '{$nome_cliente_para_msg}' excluído com sucesso!";
} else {
    // Falha na exclusão
    $_SESSION['error_message'] = "Erro ao excluir o cliente '{$nome_cliente_para_msg}'. Verifique os logs ou dependências.";
}

// Redirecionar de volta para a listagem de clientes
header("Location: " . BASE_URL . "views/clientes/index.php");
exit;

?>