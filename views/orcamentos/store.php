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
    try {
        // Converter valores monetários do formato brasileiro para o formato do banco
        $subtotalLocacao = str_replace(['.', ','], ['', '.'], $_POST['subtotal_locacao']);
        $subtotalVenda = str_replace(['.', ','], ['', '.'], $_POST['subtotal_venda']);
        $taxaDomingoFeriado = str_replace(['.', ','], ['', '.'], $_POST['taxa_domingo_feriado']);
        $taxaMadrugada = str_replace(['.', ','], ['', '.'], $_POST['taxa_madrugada']);
        $taxaHorarioEspecial = str_replace(['.', ','], ['', '.'], $_POST['taxa_horario_especial']);
        $taxaHoraMarcada = str_replace(['.', ','], ['', '.'], $_POST['taxa_hora_marcada']);
        $freteTerreo = str_replace(['.', ','], ['', '.'], $_POST['frete_terreo']);
        $freteElevador = str_replace(['.', ','], ['', '.'], $_POST['frete_elevador']);
        $freteEscadas = str_replace(['.', ','], ['', '.'], $_POST['frete_escadas']);
        $desconto = str_replace(['.', ','], ['', '.'], $_POST['desconto']);
        $valorFinal = str_replace(['.', ','], ['', '.'], $_POST['valor_final']);
        
        // Preencher os dados do orçamento a partir do formulário
        $orcamentoModel->numero = $_POST['numero'];
        $orcamentoModel->codigo = $_POST['codigo'];
        $orcamentoModel->cliente_id = $_POST['cliente_id'];
        $orcamentoModel->data_orcamento = $_POST['data_orcamento'];
        $orcamentoModel->data_validade = $_POST['data_validade'];
        
        // Campos opcionais
        if (!empty($_POST['data_evento'])) {
            $orcamentoModel->data_evento = $_POST['data_evento'];
        }
        
        $orcamentoModel->hora_evento = !empty($_POST['hora_evento']) ? $_POST['hora_evento'] : null;
        $orcamentoModel->local_evento = $_POST['local_evento'] ?? null;
        
        if (!empty($_POST['data_devolucao_prevista'])) {
            $orcamentoModel->data_devolucao_prevista = $_POST['data_devolucao_prevista'];
        }
        
        $orcamentoModel->tipo = $_POST['tipo'];
        $orcamentoModel->status = $_POST['status'] ?? 'pendente';
        $orcamentoModel->subtotal_locacao = $subtotalLocacao;
        $orcamentoModel->valor_total_locacao = $subtotalLocacao; // Mantendo compatibilidade
        $orcamentoModel->subtotal_venda = $subtotalVenda;
        $orcamentoModel->valor_total_venda = $subtotalVenda; // Mantendo compatibilidade
        $orcamentoModel->desconto = $desconto;
        $orcamentoModel->taxa_domingo_feriado = $taxaDomingoFeriado;
        $orcamentoModel->taxa_madrugada = $taxaMadrugada;
        $orcamentoModel->taxa_horario_especial = $taxaHorarioEspecial;
        $orcamentoModel->taxa_hora_marcada = $taxaHoraMarcada;
        $orcamentoModel->frete_elevador = is_numeric($freteElevador) ? $freteElevador : 'confirmar';
        $orcamentoModel->frete_escadas = is_numeric($freteEscadas) ? $freteEscadas : 'confirmar';
        $orcamentoModel->frete_terreo = $freteTerreo;
        $orcamentoModel->valor_final = $valorFinal;
        $orcamentoModel->observacoes = $_POST['observacoes'] ?? null;
        $orcamentoModel->condicoes_pagamento = $_POST['condicoes_pagamento'] ?? null;
        
        // Tentar criar o orçamento
        if ($orcamentoModel->create()) {
            // Salvar itens, se houver
            if (isset($_POST['itens']) && is_array($_POST['itens'])) {
                $itens = [];
                foreach ($_POST['itens'] as $item) {
                    // Converter valores monetários
                    $valorUnitario = str_replace(['.', ','], ['', '.'], $item['valor_unitario']);
                    $valorTotal = str_replace(['.', ','], ['', '.'], $item['valor_total']);
                    
                    $itens[] = [
                        'produto_id' => $item['produto_id'],
                        'quantidade' => intval($item['quantidade']),
                        'tipo' => $item['tipo_item'],
                        'preco_unitario' => $valorUnitario,
                        'desconto' => 0,
                        'preco_final' => $valorTotal,
                        'ajuste_manual' => 0,
                        'motivo_ajuste' => null,
                        'observacoes' => null
                    ];
                }
                $orcamentoModel->salvarItens($orcamentoModel->id, $itens);
            }
            
            $_SESSION['success_message'] = "Orçamento criado com sucesso!";
            header('Location: ' . BASE_URL . '/views/orcamentos/index.php');
            exit;
        } else {
            throw new Exception("Erro ao criar orçamento.");
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Erro ao criar orçamento: " . $e->getMessage();
        header('Location: ' . BASE_URL . '/views/orcamentos/create.php');
        exit;
    }
} else {
    $_SESSION['error_message'] = "Método de requisição inválido.";
    header('Location: ' . BASE_URL . '/views/orcamentos/create.php');
    exit;
}
?>