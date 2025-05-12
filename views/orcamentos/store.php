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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Preencher os dados do orçamento a partir do formulário
    $orcamentoModel->cliente_id = $_POST['cliente_id'];
    $orcamentoModel->consulta_id = isset($_POST['consulta_id']) ? $_POST['consulta_id'] : null;
    $orcamentoModel->data_orcamento = date('Y-m-d', strtotime(str_replace('/', '-', $_POST['data_orcamento'])));
    $orcamentoModel->data_validade = date('Y-m-d', strtotime(str_replace('/', '-', $_POST['data_validade'])));
    $orcamentoModel->data_evento = date('Y-m-d', strtotime(str_replace('/', '-', $_POST['data_evento'])));
    $orcamentoModel->hora_evento = $_POST['hora_evento'] ?: null;
    $orcamentoModel->local_evento = $_POST['local_evento'];
    $orcamentoModel->data_devolucao_prevista = date('Y-m-d', strtotime(str_replace('/', '-', $_POST['data_devolucao_prevista'])));
    $orcamentoModel->tipo = $_POST['tipo'];
    $orcamentoModel->status = 'pendente';
    $orcamentoModel->subtotal_locacao = floatval($_POST['subtotal_locacao']);
    $orcamentoModel->valor_total_locacao = floatval($_POST['subtotal_locacao']);
    $orcamentoModel->subtotal_venda = floatval($_POST['subtotal_venda']);
    $orcamentoModel->valor_total_venda = floatval($_POST['subtotal_venda']);
    $orcamentoModel->desconto = floatval($_POST['desconto']);
    $orcamentoModel->taxa_domingo_feriado = floatval($_POST['taxa_domingo_feriado']);
    $orcamentoModel->taxa_madrugada = floatval($_POST['taxa_madrugada']);
    $orcamentoModel->taxa_horario_especial = floatval($_POST['taxa_horario_especial']);
    $orcamentoModel->taxa_hora_marcada = floatval($_POST['taxa_hora_marcada']);
    $orcamentoModel->frete_elevador = $_POST['frete_elevador'] ?: 'confirmar';
    $orcamentoModel->frete_escadas = $_POST['frete_escadas'] ?: 'confirmar';
    $orcamentoModel->frete_terreo = floatval($_POST['frete_terreo']);
    $orcamentoModel->valor_final = floatval($_POST['valor_final']);
    $orcamentoModel->ajuste_manual = isset($_POST['ajuste_manual']) ? 1 : 0;
    $orcamentoModel->motivo_ajuste = $_POST['motivo_ajuste'] ?: null;
    $orcamentoModel->observacoes = $_POST['observacoes'];
    $orcamentoModel->condicoes_pagamento = $_POST['condicoes_pagamento'];
    $orcamentoModel->usuario_id = isset($_SESSION['usuario_id']) ? $_SESSION['usuario_id'] : 1; // Ajuste conforme autenticação

    // Tentar criar o orçamento
    if ($orcamentoModel->create()) {
        // Salvar itens, se houver
        if (isset($_POST['itens']) && is_array($_POST['itens'])) {
            $itens = [];
            foreach ($_POST['itens'] as $item) {
                $itens[] = [
                    'produto_id' => $item['produto_id'],
                    'quantidade' => intval($item['quantidade']),
                    'tipo' => $item['tipo'],
                    'preco_unitario' => floatval($item['preco_unitario']),
                    'desconto' => floatval($item['desconto']),
                    'preco_final' => floatval($item['preco_final']),
                    'ajuste_manual' => isset($item['ajuste_manual']) ? 1 : 0,
                    'motivo_ajuste' => $item['motivo_ajuste'] ?: null,
                    'observacoes' => $item['observacoes'] ?: null
                ];
            }
            $orcamentoModel->salvarItens($orcamentoModel->id, $itens);
        }
        $_SESSION['success_message'] = "Orçamento criado com sucesso!";
        header('Location: index.php');
        exit;
    } else {
        $_SESSION['error_message'] = "Erro ao criar orçamento.";
        header('Location: create.php');
        exit;
    }
} else {
    $_SESSION['error_message'] = "Método de requisição inválido.";
    header('Location: create.php');
    exit;
}
?>