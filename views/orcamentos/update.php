<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Orcamento.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$database = new Database();
$conn = $database->getConnection();
$orcamento = new Orcamento($conn);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
        $_SESSION['error_message'] = "ID de orçamento inválido para atualização.";
        header('Location: index.php');
        exit;
    }

    try {
        $orcamento->id = $_POST['id'];
        $orcamento->cliente_id = $_POST['cliente_id'];
        
        // --- Tratamento de datas do formato DD/MM/YYYY para YYYY-MM-DD ---
        // data_orcamento
        $data_orcamento_formatada = DateTime::createFromFormat('d/m/Y', $_POST['data_orcamento']);
        $orcamento->data_orcamento = $data_orcamento_formatada ? $data_orcamento_formatada->format('Y-m-d') : null;

        // data_validade (já vem do campo hidden no edit.php no formato YYYY-MM-DD)
        // No edit.php, o campo hidden #data_validade_hidden já armazena a data no formato YYYY-MM-DD
        $orcamento->data_validade = $_POST['data_validade'] ?? null; 
        
        // data_evento
        $data_evento_formatada = !empty($_POST['data_evento']) ? DateTime::createFromFormat('d/m/Y', $_POST['data_evento']) : null;
        $orcamento->data_evento = $data_evento_formatada ? $data_evento_formatada->format('Y-m-d') : null;

        // data_devolucao_prevista
        $data_devolucao_prevista_formatada = !empty($_POST['data_devolucao_prevista']) ? DateTime::createFromFormat('d/m/Y', $_POST['data_devolucao_prevista']) : null;
        $orcamento->data_devolucao_prevista = $data_devolucao_prevista_formatada ? $data_devolucao_prevista_formatada->format('Y-m-d') : null;
        // --- Fim do tratamento de datas ---

        $orcamento->hora_evento = !empty($_POST['hora_evento']) ? $_POST['hora_evento'] : null;
        $orcamento->local_evento = !empty($_POST['local_evento']) ? $_POST['local_evento'] : '';
        $orcamento->hora_devolucao = !empty($_POST['hora_devolucao']) ? $_POST['hora_devolucao'] : null;
        $orcamento->turno_entrega = $_POST['turno_entrega'] ?? null;
        $orcamento->turno_devolucao = $_POST['turno_devolucao'] ?? null;
        $orcamento->tipo = $_POST['tipo'] ?? null;
        $orcamento->status = $_POST['status'] ?? null;
        
        // --- Novos campos de Valores e Taxas ---
        // Converte valores monetários de 'R$ X.XXX,XX' para float 'X.XXX.XX'
        $orcamento->desconto = isset($_POST['desconto_total']) ? (float)str_replace(['R$', '.', ','], ['', '', '.'], $_POST['desconto_total']) : 0.0;
        $orcamento->taxa_domingo_feriado = isset($_POST['taxa_domingo_feriado']) ? (float)str_replace(['R$', '.', ','], ['', '', '.'], $_POST['taxa_domingo_feriado']) : 0.0;
        $orcamento->taxa_madrugada = isset($_POST['taxa_madrugada']) ? (float)str_replace(['R$', '.', ','], ['', '', '.'], $_POST['taxa_madrugada']) : 0.0;
        $orcamento->taxa_horario_especial = isset($_POST['taxa_horario_especial']) ? (float)str_replace(['R$', '.', ','], ['', '', '.'], $_POST['taxa_horario_especial']) : 0.0;
        $orcamento->taxa_hora_marcada = isset($_POST['taxa_hora_marcada']) ? (float)str_replace(['R$', '.', ','], ['', '', '.'], $_POST['taxa_hora_marcada']) : 0.0;
        $orcamento->frete_terreo = isset($_POST['frete_terreo']) ? (float)str_replace(['R$', '.', ','], ['', '', '.'], $_POST['frete_terreo']) : 0.0;
        $orcamento->frete_elevador = $_POST['frete_elevador'] ?? '';
        $orcamento->frete_escadas = $_POST['frete_escadas'] ?? '';

        // --- Ajuste Manual de Valores ---
        $orcamento->ajuste_manual = isset($_POST['ajuste_manual']) ? 1 : 0;
        $orcamento->motivo_ajuste = $_POST['motivo_ajuste'] ?? '';

        $orcamento->observacoes = $_POST['observacoes'] ?? '';
        $orcamento->condicoes_pagamento = $_POST['condicoes_pagamento'] ?? '';
        $orcamento->usuario_id = $_SESSION['usuario_id'] ?? 1; // Ou outra lógica para obter o ID do usuário logado

        // Tenta atualizar o orçamento principal
        if ($orcamento->update()) {
            // Se a atualização do orçamento principal foi bem-sucedida, atualiza os itens
            $itens = [];
            if (isset($_POST['produto_id']) && is_array($_POST['produto_id'])) {
                foreach ($_POST['produto_id'] as $index => $produto_id) {
                    // Valida se o produto_id não está vazio, para evitar salvar linhas em branco
                    if (!empty($produto_id)) {
                        $quantidade = isset($_POST['quantidade'][$index]) ? $_POST['quantidade'][$index] : 1;
                        $preco_unitario = isset($_POST['valor_unitario'][$index]) ? (float)str_replace(['R$', '.', ','], ['', '', '.'], $_POST['valor_unitario'][$index]) : 0.0;
                        $desconto_item = isset($_POST['desconto_item'][$index]) ? (float)str_replace(['R$', '.', ','], ['', '', '.'], $_POST['desconto_item'][$index]) : 0.0;
                        $preco_final = ($quantidade * $preco_unitario) - $desconto_item;

                        $itens[] = [
                            'produto_id' => $produto_id,
                            'quantidade' => $quantidade,
                            'tipo' => 'locacao', // Ou recupere o tipo se você tiver um campo para ele no formulário
                            'preco_unitario' => $preco_unitario,
                            'desconto' => $desconto_item,
                            'preco_final' => $preco_final,
                            'ajuste_manual' => false, // O ajuste manual é do orçamento total
                            'motivo_ajuste' => '',
                            'observacoes' => ''
                        ];
                    }
                }
            }
            
            // Salva (deleta e reinsere) os itens do orçamento
            if ($orcamento->salvarItens($orcamento->id, $itens)) {
                // Recalcula os valores finais do orçamento (subtotais, total geral, etc.)
                $orcamento->recalcularValores($orcamento->id);
                $_SESSION['success_message'] = "Orçamento #" . htmlspecialchars($orcamento->numero) . " atualizado com sucesso!";
                header('Location: index.php');
                exit;
            } else {
                $_SESSION['error_message'] = "Erro ao salvar os itens do orçamento. Orçamento principal atualizado.";
            }
        } else {
            $_SESSION['error_message'] = "Erro ao atualizar o orçamento.";
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Ocorreu um erro inesperado: " . $e->getMessage();
        error_log("Erro no update.php: " . $e->getMessage()); // Loga o erro para depuração
    }
    
    // Redireciona de volta para a página de edição em caso de erro
    header('Location: edit.php?id=' . $_POST['id']);
    exit;

} else {
    // Se não for uma requisição POST, redireciona para a lista de orçamentos
    header('Location: index.php');
    exit;
}
?>