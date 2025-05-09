<?php
// Inicia a sessão OBRIGATORIAMENTE no topo
session_start();

// Incluir arquivos essenciais
require_once __DIR__ . '/../../config/config.php'; // Para BASE_URL e funções como redirect()
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Produto.php'; // Modelo de Produto

// Verificar se o ID foi passado pela URL
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    // Se o ID não for válido ou não existir, define um erro e redireciona
    $_SESSION['error_message'] = "Erro: ID do produto inválido ou não fornecido.";
    redirect('views/produtos/index.php'); // Redireciona para a lista
}

$produto_id = (int)$_GET['id'];

// Conexão e Instância do Modelo
$database = new Database();
$conn = $database->getConnection();
$produto = new Produto($conn);

// IMPORTANTE: Antes de excluir, buscar o nome do produto para a mensagem
$produto_data = $produto->lerPorId($produto_id);
$nome_produto_para_msg = "Produto ID {$produto_id}"; // Valor padrão caso não encontre
if ($produto_data && isset($produto_data['nome_produto'])) {
    $nome_produto_para_msg = htmlspecialchars($produto_data['nome_produto']);
}

// Tentar excluir o produto usando o método do modelo
if ($produto->excluir($produto_id)) {
    // Exclusão bem-sucedida!
    $_SESSION['message'] = "Produto '{$nome_produto_para_msg}' excluído com sucesso!";
} else {
    // Falha na exclusão. A mensagem de erro específica (ex: 'em uso')
    // já deve ter sido definida dentro do método excluir() na $_SESSION['error_message']
    // Se a sessão de erro não foi definida lá por algum motivo, definimos uma genérica aqui.
    if (!isset($_SESSION['error_message'])) {
         $_SESSION['error_message'] = "Erro ao excluir o produto '{$nome_produto_para_msg}'. Verifique os logs.";
    }
}

// Redirecionar de volta para a lista de produtos em ambos os casos (sucesso ou erro)
redirect('views/produtos/index.php');

?> // Fim do script PHP (não é necessário fechar se for a última coisa no arquivo)