<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Pedido.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar se é uma requisição POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// ✅ CORRIGIDO: Verificar parâmetros corretos
if (!isset($_POST['pedido_id']) || !isset($_POST['nova_situacao'])) {
    echo json_encode(['success' => false, 'message' => 'Parâmetros obrigatórios não fornecidos']);
    exit;
}

$pedido_id = (int)$_POST['pedido_id'];
$nova_situacao = trim($_POST['nova_situacao']); // ✅ CORRIGIDO

// ✅ CORRIGIDO: Situações válidas conforme banco
$situacoes_validas = ['confirmado', 'em_separacao', 'entregue', 'devolvido_parcial', 'finalizado', 'cancelado'];
if (!in_array($nova_situacao, $situacoes_validas)) {
    echo json_encode(['success' => false, 'message' => 'Situação inválida']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    $pedidoModel = new Pedido($db);
    
    // ✅ CORRIGIDO: Usar método correto
    if ($pedidoModel->updateSituacao($pedido_id, $nova_situacao)) {
        echo json_encode([
            'success' => true, 
            'message' => 'Situação atualizada com sucesso!',
            'nova_situacao' => $nova_situacao // ✅ CORRIGIDO
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao atualizar situação']);
    }
    
} catch (Exception $e) {
    error_log("Erro ao atualizar situação do pedido: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}
?>