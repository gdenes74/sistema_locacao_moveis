<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Orcamento.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$database = new Database();
$conn = $database->getConnection();
$orcamento = new Orcamento($conn); // Instancia o modelo Orcamento

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
        $_SESSION['error_message'] = "ID de orçamento inválido para atualização.";
        header('Location: index.php');
        exit;
    }

    try {
        $orcamento->id = $_POST['id'];
        
        // --- Carregar dados existentes para manter o número e código ---
        // É importante carregar para pegar o 'numero' e 'codigo' que não vêm do form de edição
        $dadosAtuais = $orcamento->getById($orcamento->id); // getById preenche as propriedades do objeto $orcamento
        if (!$dadosAtuais) {
            $_SESSION['error_message'] = "Orçamento não encontrado para atualização (ID: {$_POST['id']}).";
            header('Location: index.php');
            exit;
        }
        // O 'numero' e 'codigo' já estarão em $orcamento->numero e $orcamento->codigo
        // após a chamada de getById acima. Não precisamos reatribuí-los do POST.

        $orcamento->cliente_id = $_POST['cliente_id'];
        
        // --- Tratamento de datas do formato DD/MM/YYYY para YYYY-MM-DD ---
        $data_orcamento_formatada = DateTime::createFromFormat('d/m/Y', $_POST['data_orcamento']);
        $orcamento->data_orcamento = $data_orcamento_formatada ? $data_orcamento_formatada->format('Y-m-d') : null;

        // data_validade (deve vir do formulário no formato Y-m-d, possivelmente de um campo hidden)
        // Se você estiver usando o datepicker para data_validade e ele envia d/m/Y, precisará converter.
        // Assumindo que 'data_validade' no POST já está em Y-m-d ou será tratada no edit.php para ser.
        $orcamento->data_validade = $_POST['data_validade'] ?? null;
        
        // **** NOVOS CAMPOS: data_entrega e hora_entrega ****
        $data_entrega_formatada = !empty($_POST['data_entrega']) ? DateTime::createFromFormat('d/m/Y', $_POST['data_entrega']) : null;
        $orcamento->data_entrega = $data_entrega_formatada ? $data_entrega_formatada->format('Y-m-d') : null;
        $orcamento->hora_entrega = !empty($_POST['hora_entrega']) ? $_POST['hora_entrega'] : null;
        // **** FIM NOVOS CAMPOS ****

        $data_evento_formatada = !empty($_POST['data_evento']) ? DateTime::createFromFormat('d/m/Y', $_POST['data_evento']) : null;
        $orcamento->data_evento = $data_evento_formatada ? $data_evento_formatada->format('Y-m-d') : null;

        $data_devolucao_prevista_formatada = !empty($_POST['data_devolucao_prevista']) ? DateTime::createFromFormat('d/m/Y', $_POST['data_devolucao_prevista']) : null;
        $orcamento->data_devolucao_prevista = $data_devolucao_prevista_formatada ? $data_devolucao_prevista_formatada->format('Y-m-d') : null;

        $orcamento->hora_evento = !empty($_POST['hora_evento']) ? $_POST['hora_evento'] : null;
        $orcamento->local_evento = !empty($_POST['local_evento']) ? trim($_POST['local_evento']) : ''; // Adicionado trim
        $orcamento->hora_devolucao = !empty($_POST['hora_devolucao']) ? $_POST['hora_devolucao'] : null;
        
        // O turno_entrega agora está associado à data_entrega/hora_entrega
        $orcamento->turno_entrega = $_POST['turno_entrega'] ?? 'Manhã/Tarde (Horário Comercial)'; // Valor padrão
        $orcamento->turno_devolucao = $_POST['turno_devolucao'] ?? 'Manhã/Tarde (Horário Comercial)'; // Valor padrão
        $orcamento->tipo = $_POST['tipo'] ?? 'locacao'; // Valor padrão
        $orcamento->status = $_POST['status'] ?? 'pendente'; // Valor padrão
        
        $orcamento->desconto = isset($_POST['desconto_total']) ? (float)str_replace(['R$', '.', ','], ['', '', '.'], $_POST['desconto_total']) : 0.0;
        $orcamento->taxa_domingo_feriado = isset($_POST['taxa_domingo_feriado']) ? (float)str_replace(['R$', '.', ','], ['', '', '.'], $_POST['taxa_domingo_feriado']) : 0.0;
        $orcamento->taxa_madrugada = isset($_POST['taxa_madrugada']) ? (float)str_replace(['R$', '.', ','], ['', '', '.'], $_POST['taxa_madrugada']) : 0.0;
        $orcamento->taxa_horario_especial = isset($_POST['taxa_horario_especial']) ? (float)str_replace(['R$', '.', ','], ['', '', '.'], $_POST['taxa_horario_especial']) : 0.0;
        $orcamento->taxa_hora_marcada = isset($_POST['taxa_hora_marcada']) ? (float)str_replace(['R$', '.', ','], ['', '', '.'], $_POST['taxa_hora_marcada']) : 0.0;
        $orcamento->frete_terreo = isset($_POST['frete_terreo']) ? (float)str_replace(['R$', '.', ','], ['', '', '.'], $_POST['frete_terreo']) : 0.0;
        $orcamento->frete_elevador = $_POST['frete_elevador'] ?? '';
        $orcamento->frete_escadas = $_POST['frete_escadas'] ?? '';

        $orcamento->ajuste_manual = isset($_POST['ajuste_manual']) ? 1 : 0;
        $orcamento->motivo_ajuste = $_POST['motivo_ajuste'] ?? '';

        $orcamento->observacoes = $_POST['observacoes'] ?? '';
        $orcamento->condicoes_pagamento = $_POST['condicoes_pagamento'] ?? '';
        $orcamento->usuario_id = $_SESSION['usuario_id'] ?? 1;

        // O método update() na classe Orcamento já foi modificado para incluir data_entrega e hora_entrega.
        if ($orcamento->update()) {
            $itens = [];
            if (isset($_POST['produto_id']) && is_array($_POST['produto_id'])) {
                foreach ($_POST['produto_id'] as $index => $produto_id) {
                    if (!empty($produto_id)) {
                        $quantidade = isset($_POST['quantidade'][$index]) ? (int)$_POST['quantidade'][$index] : 1; // Cast para int
                        $preco_unitario = isset($_POST['valor_unitario'][$index]) ? (float)str_replace(['R$', '.', ','], ['', '', '.'], $_POST['valor_unitario'][$index]) : 0.0;
                        $desconto_item = isset($_POST['desconto_item'][$index]) ? (float)str_replace(['R$', '.', ','], ['', '', '.'], $_POST['desconto_item'][$index]) : 0.0;
                        $preco_final = ($quantidade * $preco_unitario) - $desconto_item;

                        $itens[] = [
                            'produto_id' => $produto_id,
                            'quantidade' => $quantidade,
                            'tipo' => $_POST['tipo_item'][$index] ?? 'locacao', // Assumindo que você terá um campo tipo_item[]
                            'preco_unitario' => $preco_unitario,
                            'desconto' => $desconto_item,
                            'preco_final' => $preco_final,
                            'ajuste_manual' => false, 
                            'motivo_ajuste' => '',
                            'observacoes' => $_POST['observacoes_item'][$index] ?? '' // Assumindo que você terá um campo observacoes_item[]
                        ];
                    }
                }
            }
            
            if ($orcamento->salvarItens($orcamento->id, $itens)) {
                $orcamento->recalcularValores($orcamento->id); // Passar o ID do orçamento
                $_SESSION['success_message'] = "Orçamento #" . htmlspecialchars($orcamento->numero) . " atualizado com sucesso!"; // Usar $orcamento->numero
                header('Location: index.php');
                exit;
            } else {
                // Mesmo se salvarItens falhar, o orçamento principal foi atualizado.
                // Pode ser melhor logar o erro e informar o usuário.
                $_SESSION['error_message'] = "Orçamento principal atualizado, mas houve um erro ao salvar os itens. Verifique os logs.";
                error_log("Erro em update.php: Falha ao executar salvarItens para o orçamento ID {$orcamento->id}.");
                header('Location: edit.php?id=' . $orcamento->id); // Voltar para edição
                exit;
            }
        } else {
            $_SESSION['error_message'] = "Erro ao atualizar o orçamento principal. Verifique os logs.";
            error_log("Erro em update.php: Falha ao executar orcamento->update() para o orçamento ID {$orcamento->id}. Erro PDO: " . print_r($conn->errorInfo(), true));
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Ocorreu um erro inesperado: " . $e->getMessage();
        error_log("Exceção em update.php para orçamento ID {$_POST['id']}: " . $e->getMessage());
    }
    
    header('Location: edit.php?id=' . $_POST['id']);
    exit;

} else {
    header('Location: index.php');
    exit;
}
?>