<?php
class Pedido
{
    private $conn;
    private $table = 'pedidos';
    private $table_itens = 'itens_pedido';

    // Propriedades do Pedido
    public $id;
    public $numero;
    public $codigo;
    public $cliente_id;
    public $orcamento_id;
    public $data_pedido;
    public $data_validade;
    public $data_evento;
    public $hora_evento;
    public $local_evento;
    public $data_entrega;
    public $hora_entrega;
    public $turno_entrega;
    public $hora_devolucao;
    public $turno_devolucao;
    public $data_devolucao_prevista;
    public $tipo;
    public $situacao_pedido;
    public $valor_total_locacao;
    public $subtotal_locacao;
    public $valor_total_venda;
    public $subtotal_venda;
    public $desconto;
    public $taxa_domingo_feriado;
    public $taxa_madrugada;
    public $taxa_horario_especial;
    public $taxa_hora_marcada;
    public $frete_elevador;
    public $frete_escadas;
    public $frete_terreo;
    public $valor_final;
    public $ajuste_manual;
    public $motivo_ajuste;
    public $valor_sinal;
    public $data_pagamento_sinal;
    public $valor_pago;
    public $data_pagamento_final;
    public $valor_multas;
    public $saldo_calculado;
    public $observacoes;
    public $condicoes_pagamento;
    public $usuario_id;
    public $data_cadastro;

    // Propriedades auxiliares
    public $nome_cliente;
    public $cliente_telefone;
    public $cliente_email;
    public $cliente_cpf_cnpj;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    public function listarTodos($filtros = [], $orderBy = 'p.id DESC')
    {
        $query = "SELECT
                    p.*,
                    c.nome AS nome_cliente,
                    c.telefone AS cliente_telefone,
                    c.email AS cliente_email
                  FROM {$this->table} p
                  LEFT JOIN clientes c ON p.cliente_id = c.id
                  WHERE 1=1";
        $params = [];

        if (!empty($filtros['pesquisar'])) {
            $searchTerm = "%" . $filtros['pesquisar'] . "%";
            $query .= " AND (
                CAST(p.id AS CHAR) LIKE :pesquisar_id OR
                CAST(p.numero AS CHAR) LIKE :pesquisar_num OR
                p.codigo LIKE :pesquisar_cod OR
                c.nome LIKE :pesquisar_cliente
            )";
            $params[':pesquisar_id'] = $searchTerm;
            $params[':pesquisar_num'] = $searchTerm;
            $params[':pesquisar_cod'] = $searchTerm;
            $params[':pesquisar_cliente'] = $searchTerm;
        }

        if (!empty($filtros['cliente_id'])) {
            $query .= " AND p.cliente_id = :cliente_id";
            $params[':cliente_id'] = (int) $filtros['cliente_id'];
        }

        if (!empty($filtros['situacao_pedido'])) {
            $query .= " AND p.situacao_pedido = :situacao_pedido";
            $params[':situacao_pedido'] = $filtros['situacao_pedido'];
        }

        if (!empty($filtros['data_evento_de']) && !empty($filtros['data_evento_ate'])) {
            $query .= " AND p.data_evento BETWEEN :data_evento_de AND :data_evento_ate";
            $params[':data_evento_de'] = $filtros['data_evento_de'];
            $params[':data_evento_ate'] = $filtros['data_evento_ate'];
        }

        if (!empty($filtros['tipo'])) {
            $query .= " AND p.tipo = :tipo";
            $params[':tipo'] = $filtros['tipo'];
        }

        $allowedOrderBy = [
            'p.id DESC', 'p.id ASC', 'p.numero DESC', 'p.numero ASC',
            'p.data_pedido DESC', 'p.data_pedido ASC',
            'p.data_evento DESC', 'p.data_evento ASC',
            'p.valor_final DESC', 'p.valor_final ASC',
            'c.nome ASC', 'c.nome DESC',
            'p.situacao_pedido ASC', 'p.situacao_pedido DESC'
        ];

        $query .= in_array($orderBy, $allowedOrderBy, true)
            ? " ORDER BY {$orderBy}"
            : " ORDER BY p.id DESC";

        try {
            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Erro em Pedido::listarTodos: " . $e->getMessage());
            return false;
        }
    }

    public function create()
    {
        if (empty($this->numero)) {
            error_log("Erro: Propriedade 'numero' não definida em Pedido::create().");
            return false;
        }

        if (empty($this->codigo)) {
            $this->codigo = "PED-" . date('Y') . "-" . str_pad($this->numero, 5, '0', STR_PAD_LEFT);
        }

        try {
            $queryPedido = "INSERT INTO {$this->table}
                (
                    numero, codigo, cliente_id, orcamento_id, data_pedido, data_validade,
                    data_evento, hora_evento, local_evento, data_entrega, hora_entrega,
                    turno_entrega, hora_devolucao, turno_devolucao, data_devolucao_prevista,
                    tipo, situacao_pedido,
                    valor_total_locacao, subtotal_locacao, valor_total_venda, subtotal_venda,
                    desconto, taxa_domingo_feriado, taxa_madrugada, taxa_horario_especial,
                    taxa_hora_marcada, frete_elevador, frete_escadas, frete_terreo, valor_final,
                    ajuste_manual, motivo_ajuste,
                    valor_sinal, data_pagamento_sinal, valor_pago, data_pagamento_final, valor_multas, saldo_calculado,
                    observacoes, condicoes_pagamento, usuario_id
                )
                VALUES
                (
                    :numero, :codigo, :cliente_id, :orcamento_id, :data_pedido, :data_validade,
                    :data_evento, :hora_evento, :local_evento, :data_entrega, :hora_entrega,
                    :turno_entrega, :hora_devolucao, :turno_devolucao, :data_devolucao_prevista,
                    :tipo, :situacao_pedido,
                    :valor_total_locacao, :subtotal_locacao, :valor_total_venda, :subtotal_venda,
                    :desconto, :taxa_domingo_feriado, :taxa_madrugada, :taxa_horario_especial,
                    :taxa_hora_marcada, :frete_elevador, :frete_escadas, :frete_terreo, :valor_final,
                    :ajuste_manual, :motivo_ajuste,
                    :valor_sinal, :data_pagamento_sinal, :valor_pago, :data_pagamento_final, :valor_multas, :saldo_calculado,
                    :observacoes, :condicoes_pagamento, :usuario_id
                )";

            $stmtPedido = $this->conn->prepare($queryPedido);

            $this->cliente_id = !empty($this->cliente_id) ? (int) $this->cliente_id : null;
            $this->orcamento_id = !empty($this->orcamento_id) ? (int) $this->orcamento_id : null;
            $this->data_pedido = !empty($this->data_pedido) ? $this->data_pedido : date('Y-m-d');
            $this->data_validade = !empty($this->data_validade) ? $this->data_validade : null;
            $this->data_evento = !empty($this->data_evento) ? $this->data_evento : null;
            $this->hora_evento = !empty($this->hora_evento) ? $this->hora_evento : null;
            $this->local_evento = !empty($this->local_evento) ? trim($this->local_evento) : null;
            $this->data_entrega = !empty($this->data_entrega) ? $this->data_entrega : null;
            $this->hora_entrega = !empty($this->hora_entrega) ? $this->hora_entrega : null;
            $this->turno_entrega = $this->turno_entrega ?? 'Manhã/Tarde (Horário Comercial)';
            $this->hora_devolucao = !empty($this->hora_devolucao) ? $this->hora_devolucao : null;
            $this->turno_devolucao = $this->turno_devolucao ?? 'Manhã/Tarde (Horário Comercial)';
            $this->data_devolucao_prevista = !empty($this->data_devolucao_prevista) ? $this->data_devolucao_prevista : null;
            $this->tipo = $this->tipo ?? 'locacao';
            $this->situacao_pedido = $this->situacao_pedido ?? 'confirmado';

            $this->valor_total_locacao = (float) ($this->valor_total_locacao ?? 0.00);
            $this->subtotal_locacao = (float) ($this->subtotal_locacao ?? 0.00);
            $this->valor_total_venda = (float) ($this->valor_total_venda ?? 0.00);
            $this->subtotal_venda = (float) ($this->subtotal_venda ?? 0.00);
            $this->desconto = (float) ($this->desconto ?? 0.00);
            $this->taxa_domingo_feriado = (float) ($this->taxa_domingo_feriado ?? 0.00);
            $this->taxa_madrugada = (float) ($this->taxa_madrugada ?? 0.00);
            $this->taxa_horario_especial = (float) ($this->taxa_horario_especial ?? 0.00);
            $this->taxa_hora_marcada = (float) ($this->taxa_hora_marcada ?? 0.00);
            $this->frete_elevador = (float) ($this->frete_elevador ?? 0.00);
            $this->frete_escadas = (float) ($this->frete_escadas ?? 0.00);
            $this->frete_terreo = (float) ($this->frete_terreo ?? 0.00);
            $this->valor_final = (float) ($this->valor_final ?? 0.00);
            $this->ajuste_manual = (bool) ($this->ajuste_manual ?? false);
            $this->motivo_ajuste = !empty($this->motivo_ajuste) ? trim($this->motivo_ajuste) : null;

            $this->valor_sinal = (float) ($this->valor_sinal ?? 0.00);
            $this->data_pagamento_sinal = !empty($this->data_pagamento_sinal) ? $this->data_pagamento_sinal : null;
            $this->valor_pago = (float) ($this->valor_pago ?? 0.00);
            $this->data_pagamento_final = !empty($this->data_pagamento_final) ? $this->data_pagamento_final : null;
            $this->valor_multas = (float) ($this->valor_multas ?? 0.00);
            $this->saldo_calculado = (float) ($this->saldo_calculado ?? 0.00);

            $this->observacoes = !empty($this->observacoes) ? trim($this->observacoes) : null;
            $this->condicoes_pagamento = !empty($this->condicoes_pagamento) ? trim($this->condicoes_pagamento) : null;
            $this->usuario_id = !empty($this->usuario_id)
                ? (int) $this->usuario_id
                : (isset($_SESSION['usuario_id']) ? (int) $_SESSION['usuario_id'] : 1);

            $stmtPedido->bindParam(':numero', $this->numero, PDO::PARAM_INT);
            $stmtPedido->bindParam(':codigo', $this->codigo);
            $stmtPedido->bindValue(':cliente_id', $this->cliente_id, $this->cliente_id === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmtPedido->bindValue(':orcamento_id', $this->orcamento_id, $this->orcamento_id === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmtPedido->bindParam(':data_pedido', $this->data_pedido);
            $stmtPedido->bindValue(':data_validade', $this->data_validade, $this->data_validade === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmtPedido->bindValue(':data_evento', $this->data_evento, $this->data_evento === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmtPedido->bindValue(':hora_evento', $this->hora_evento, $this->hora_evento === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmtPedido->bindValue(':local_evento', $this->local_evento, $this->local_evento === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmtPedido->bindValue(':data_entrega', $this->data_entrega, $this->data_entrega === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmtPedido->bindValue(':hora_entrega', $this->hora_entrega, $this->hora_entrega === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmtPedido->bindParam(':turno_entrega', $this->turno_entrega);
            $stmtPedido->bindValue(':hora_devolucao', $this->hora_devolucao, $this->hora_devolucao === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmtPedido->bindParam(':turno_devolucao', $this->turno_devolucao);
            $stmtPedido->bindValue(':data_devolucao_prevista', $this->data_devolucao_prevista, $this->data_devolucao_prevista === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmtPedido->bindParam(':tipo', $this->tipo);
            $stmtPedido->bindParam(':situacao_pedido', $this->situacao_pedido);

            $stmtPedido->bindParam(':valor_total_locacao', $this->valor_total_locacao);
            $stmtPedido->bindParam(':subtotal_locacao', $this->subtotal_locacao);
            $stmtPedido->bindParam(':valor_total_venda', $this->valor_total_venda);
            $stmtPedido->bindParam(':subtotal_venda', $this->subtotal_venda);
            $stmtPedido->bindParam(':desconto', $this->desconto);
            $stmtPedido->bindParam(':taxa_domingo_feriado', $this->taxa_domingo_feriado);
            $stmtPedido->bindParam(':taxa_madrugada', $this->taxa_madrugada);
            $stmtPedido->bindParam(':taxa_horario_especial', $this->taxa_horario_especial);
            $stmtPedido->bindParam(':taxa_hora_marcada', $this->taxa_hora_marcada);
            $stmtPedido->bindParam(':frete_elevador', $this->frete_elevador);
            $stmtPedido->bindParam(':frete_escadas', $this->frete_escadas);
            $stmtPedido->bindParam(':frete_terreo', $this->frete_terreo);
            $stmtPedido->bindParam(':valor_final', $this->valor_final);
            $stmtPedido->bindParam(':ajuste_manual', $this->ajuste_manual, PDO::PARAM_BOOL);
            $stmtPedido->bindValue(':motivo_ajuste', $this->motivo_ajuste, $this->motivo_ajuste === null ? PDO::PARAM_NULL : PDO::PARAM_STR);

            $stmtPedido->bindParam(':valor_sinal', $this->valor_sinal);
            $stmtPedido->bindValue(':data_pagamento_sinal', $this->data_pagamento_sinal, $this->data_pagamento_sinal === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmtPedido->bindParam(':valor_pago', $this->valor_pago);
            $stmtPedido->bindValue(':data_pagamento_final', $this->data_pagamento_final, $this->data_pagamento_final === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmtPedido->bindParam(':valor_multas', $this->valor_multas);
            $stmtPedido->bindParam(':saldo_calculado', $this->saldo_calculado);

            $stmtPedido->bindValue(':observacoes', $this->observacoes, $this->observacoes === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmtPedido->bindValue(':condicoes_pagamento', $this->condicoes_pagamento, $this->condicoes_pagamento === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmtPedido->bindParam(':usuario_id', $this->usuario_id, PDO::PARAM_INT);

            if (!$stmtPedido->execute()) {
                error_log("Erro ao inserir pedido principal: " . print_r($stmtPedido->errorInfo(), true));
                return false;
            }

            $this->id = (int) $this->conn->lastInsertId();
            return $this->id;
        } catch (PDOException $e) {
            error_log("Exceção PDO em Pedido::create: " . $e->getMessage());
            return false;
        }
    }

    public function criarDePedidoOrcamento($orcamentoId)
    {
        try {
            $queryOrc = "SELECT * FROM orcamentos WHERE id = :orcamento_id";
            $stmtOrc = $this->conn->prepare($queryOrc);
            $stmtOrc->bindParam(':orcamento_id', $orcamentoId, PDO::PARAM_INT);
            $stmtOrc->execute();
            $orcamento = $stmtOrc->fetch(PDO::FETCH_ASSOC);

            if (!$orcamento) {
                throw new Exception("Orçamento não encontrado");
            }

            $this->numero = (int) $orcamento['numero'];
            $this->codigo = 'PED-' . date('Y') . "-" . str_pad($this->numero, 5, '0', STR_PAD_LEFT);
            $this->cliente_id = (int) $orcamento['cliente_id'];
            $this->orcamento_id = (int) $orcamentoId;
            $this->data_pedido = date('Y-m-d');
            $this->data_validade = $orcamento['data_validade'] ?? null;
            $this->data_evento = $orcamento['data_evento'] ?? null;
            $this->hora_evento = $orcamento['hora_evento'] ?? null;
            $this->local_evento = $orcamento['local_evento'] ?? null;
            $this->data_entrega = $orcamento['data_entrega'] ?? null;
            $this->hora_entrega = $orcamento['hora_entrega'] ?? null;
            $this->data_devolucao_prevista = $orcamento['data_devolucao_prevista'] ?? null;
            $this->hora_devolucao = $orcamento['hora_devolucao'] ?? null;
            $this->turno_entrega = $orcamento['turno_entrega'] ?? 'Manhã/Tarde (Horário Comercial)';
            $this->turno_devolucao = $orcamento['turno_devolucao'] ?? 'Manhã/Tarde (Horário Comercial)';
            $this->tipo = $orcamento['tipo'] ?? 'locacao';
            $this->situacao_pedido = 'confirmado';

            $this->valor_total_locacao = (float) ($orcamento['valor_total_locacao'] ?? 0);
            $this->subtotal_locacao = (float) ($orcamento['subtotal_locacao'] ?? 0);
            $this->valor_total_venda = (float) ($orcamento['valor_total_venda'] ?? 0);
            $this->subtotal_venda = (float) ($orcamento['subtotal_venda'] ?? 0);
            $this->desconto = (float) ($orcamento['desconto'] ?? 0);
            $this->taxa_domingo_feriado = (float) ($orcamento['taxa_domingo_feriado'] ?? 0);
            $this->taxa_madrugada = (float) ($orcamento['taxa_madrugada'] ?? 0);
            $this->taxa_horario_especial = (float) ($orcamento['taxa_horario_especial'] ?? 0);
            $this->taxa_hora_marcada = (float) ($orcamento['taxa_hora_marcada'] ?? 0);
            $this->frete_elevador = (float) ($orcamento['frete_elevador'] ?? 0);
            $this->frete_escadas = (float) ($orcamento['frete_escadas'] ?? 0);
            $this->frete_terreo = (float) ($orcamento['frete_terreo'] ?? 0);
            $this->valor_final = (float) ($orcamento['valor_final'] ?? 0);
            $this->ajuste_manual = (bool) ($orcamento['ajuste_manual'] ?? false);
            $this->motivo_ajuste = $orcamento['motivo_ajuste'] ?? null;
            $this->observacoes = $orcamento['observacoes'] ?? null;
            $this->condicoes_pagamento = $orcamento['condicoes_pagamento'] ?? null;
            $this->usuario_id = (int) ($orcamento['usuario_id'] ?? 1);

            $this->valor_sinal = 0.00;
            $this->data_pagamento_sinal = null;
            $this->valor_pago = 0.00;
            $this->data_pagamento_final = null;
            $this->valor_multas = 0.00;
            $this->saldo_calculado = 0.00;

            if (!$this->create()) {
                throw new Exception("Erro ao criar pedido no método create()");
            }

            $this->copiarItensOrcamento($orcamentoId, $this->id);

            if (!$this->recalcularValores($this->id)) {
                throw new Exception("Erro ao recalcular valores do pedido gerado a partir do orçamento");
            }

            return $this->id;
        } catch (Exception $e) {
            error_log("Erro em Pedido::criarDePedidoOrcamento: " . $e->getMessage());
            return false;
        }
    }

    private function copiarItensOrcamento($orcamentoId, $pedidoId)
    {
        $queryItens = "SELECT * FROM itens_orcamento WHERE orcamento_id = :orcamento_id";
        $stmtItens = $this->conn->prepare($queryItens);
        $stmtItens->bindParam(':orcamento_id', $orcamentoId, PDO::PARAM_INT);
        $stmtItens->execute();
        $itens = $stmtItens->fetchAll(PDO::FETCH_ASSOC);

        $queryInsert = "INSERT INTO {$this->table_itens}
            (pedido_id, produto_id, nome_produto_manual, quantidade, tipo,
             preco_unitario, desconto, preco_final, observacoes, tipo_linha, ordem)
            VALUES
            (:pedido_id, :produto_id, :nome_produto_manual, :quantidade, :tipo,
             :preco_unitario, :desconto, :preco_final, :observacoes, :tipo_linha, :ordem)";
        $stmtInsert = $this->conn->prepare($queryInsert);

        foreach ($itens as $item) {
            $stmtInsert->execute([
                ':pedido_id' => $pedidoId,
                ':produto_id' => $item['produto_id'],
                ':nome_produto_manual' => $item['nome_produto_manual'],
                ':quantidade' => $item['quantidade'],
                ':tipo' => $item['tipo'],
                ':preco_unitario' => $item['preco_unitario'],
                ':desconto' => $item['desconto'],
                ':preco_final' => $item['preco_final'],
                ':observacoes' => $item['observacoes'],
                ':tipo_linha' => $item['tipo_linha'],
                ':ordem' => $item['ordem']
            ]);
        }
    }

    public function update()
    {
        if (empty($this->id)) {
            error_log("Erro: Tentativa de atualizar pedido sem ID.");
            return false;
        }

        $query = "UPDATE {$this->table} SET
                    cliente_id = :cliente_id,
                    data_pedido = :data_pedido,
                    data_validade = :data_validade,
                    data_evento = :data_evento,
                    hora_evento = :hora_evento,
                    local_evento = :local_evento,
                    data_entrega = :data_entrega,
                    hora_entrega = :hora_entrega,
                    data_devolucao_prevista = :data_devolucao_prevista,
                    hora_devolucao = :hora_devolucao,
                    turno_entrega = :turno_entrega,
                    turno_devolucao = :turno_devolucao,
                    tipo = :tipo,
                    situacao_pedido = :situacao_pedido,
                    desconto = :desconto,
                    taxa_domingo_feriado = :taxa_domingo_feriado,
                    taxa_madrugada = :taxa_madrugada,
                    taxa_horario_especial = :taxa_horario_especial,
                    taxa_hora_marcada = :taxa_hora_marcada,
                    frete_elevador = :frete_elevador,
                    frete_escadas = :frete_escadas,
                    frete_terreo = :frete_terreo,
                    ajuste_manual = :ajuste_manual,
                    motivo_ajuste = :motivo_ajuste,
                    observacoes = :observacoes,
                    condicoes_pagamento = :condicoes_pagamento,
                    usuario_id = :usuario_id,
                    valor_sinal = :valor_sinal,
                    data_pagamento_sinal = :data_pagamento_sinal,
                    valor_pago = :valor_pago,
                    data_pagamento_final = :data_pagamento_final,
                    valor_multas = :valor_multas
                  WHERE id = :id";

        try {
            $stmt = $this->conn->prepare($query);

            $this->cliente_id = (int) $this->cliente_id;
            $this->data_pedido = !empty($this->data_pedido) ? $this->data_pedido : date('Y-m-d');
            $this->data_validade = !empty($this->data_validade) ? $this->data_validade : null;
            $this->data_evento = !empty($this->data_evento) ? $this->data_evento : null;
            $this->hora_evento = !empty($this->hora_evento) ? $this->hora_evento : null;
            $this->local_evento = !empty($this->local_evento) ? trim($this->local_evento) : null;
            $this->data_entrega = !empty($this->data_entrega) ? $this->data_entrega : null;
            $this->hora_entrega = !empty($this->hora_entrega) ? $this->hora_entrega : null;
            $this->data_devolucao_prevista = !empty($this->data_devolucao_prevista) ? $this->data_devolucao_prevista : null;
            $this->hora_devolucao = !empty($this->hora_devolucao) ? $this->hora_devolucao : null;
            $this->turno_entrega = $this->turno_entrega ?? 'Manhã/Tarde (Horário Comercial)';
            $this->turno_devolucao = $this->turno_devolucao ?? 'Manhã/Tarde (Horário Comercial)';
            $this->tipo = $this->tipo ?? 'locacao';
            $this->situacao_pedido = $this->situacao_pedido ?? 'confirmado';
            $this->desconto = (float) ($this->desconto ?? 0.00);
            $this->taxa_domingo_feriado = (float) ($this->taxa_domingo_feriado ?? 0.00);
            $this->taxa_madrugada = (float) ($this->taxa_madrugada ?? 0.00);
            $this->taxa_horario_especial = (float) ($this->taxa_horario_especial ?? 0.00);
            $this->taxa_hora_marcada = (float) ($this->taxa_hora_marcada ?? 0.00);
            $this->frete_terreo = (float) ($this->frete_terreo ?? 0.00);
            $this->frete_elevador = (float) ($this->frete_elevador ?? 0.00);
            $this->frete_escadas = (float) ($this->frete_escadas ?? 0.00);
            $this->ajuste_manual = (bool) ($this->ajuste_manual ?? false);
            $this->motivo_ajuste = !empty($this->motivo_ajuste) ? trim($this->motivo_ajuste) : null;
            $this->observacoes = !empty($this->observacoes) ? trim($this->observacoes) : null;
            $this->condicoes_pagamento = !empty($this->condicoes_pagamento) ? trim($this->condicoes_pagamento) : null;
            $this->usuario_id = $this->usuario_id ?? (isset($_SESSION['usuario_id']) ? (int) $_SESSION['usuario_id'] : 1);

            $this->valor_sinal = (float) ($this->valor_sinal ?? 0.00);
            $this->data_pagamento_sinal = !empty($this->data_pagamento_sinal) ? $this->data_pagamento_sinal : null;
            $this->valor_pago = (float) ($this->valor_pago ?? 0.00);
            $this->data_pagamento_final = !empty($this->data_pagamento_final) ? $this->data_pagamento_final : null;
            $this->valor_multas = (float) ($this->valor_multas ?? 0.00);

            $stmt->bindParam(':id', $this->id, PDO::PARAM_INT);
            $stmt->bindParam(':cliente_id', $this->cliente_id, PDO::PARAM_INT);
            $stmt->bindParam(':data_pedido', $this->data_pedido);
            $stmt->bindValue(':data_validade', $this->data_validade, $this->data_validade === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':data_evento', $this->data_evento, $this->data_evento === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':hora_evento', $this->hora_evento, $this->hora_evento === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':local_evento', $this->local_evento, $this->local_evento === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':data_entrega', $this->data_entrega, $this->data_entrega === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':hora_entrega', $this->hora_entrega, $this->hora_entrega === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':data_devolucao_prevista', $this->data_devolucao_prevista, $this->data_devolucao_prevista === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':hora_devolucao', $this->hora_devolucao, $this->hora_devolucao === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindParam(':turno_entrega', $this->turno_entrega);
            $stmt->bindParam(':turno_devolucao', $this->turno_devolucao);
            $stmt->bindParam(':tipo', $this->tipo);
            $stmt->bindParam(':situacao_pedido', $this->situacao_pedido);
            $stmt->bindParam(':desconto', $this->desconto);
            $stmt->bindParam(':taxa_domingo_feriado', $this->taxa_domingo_feriado);
            $stmt->bindParam(':taxa_madrugada', $this->taxa_madrugada);
            $stmt->bindParam(':taxa_horario_especial', $this->taxa_horario_especial);
            $stmt->bindParam(':taxa_hora_marcada', $this->taxa_hora_marcada);
            $stmt->bindParam(':frete_elevador', $this->frete_elevador);
            $stmt->bindParam(':frete_escadas', $this->frete_escadas);
            $stmt->bindParam(':frete_terreo', $this->frete_terreo);
            $stmt->bindParam(':ajuste_manual', $this->ajuste_manual, PDO::PARAM_BOOL);
            $stmt->bindValue(':motivo_ajuste', $this->motivo_ajuste, $this->motivo_ajuste === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':observacoes', $this->observacoes, $this->observacoes === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':condicoes_pagamento', $this->condicoes_pagamento, $this->condicoes_pagamento === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindParam(':usuario_id', $this->usuario_id, PDO::PARAM_INT);

            $stmt->bindParam(':valor_sinal', $this->valor_sinal);
            $stmt->bindValue(':data_pagamento_sinal', $this->data_pagamento_sinal, $this->data_pagamento_sinal === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindParam(':valor_pago', $this->valor_pago);
            $stmt->bindValue(':data_pagamento_final', $this->data_pagamento_final, $this->data_pagamento_final === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindParam(':valor_multas', $this->valor_multas);

            if ($stmt->execute()) {
                return true;
            }

            error_log("Erro ao atualizar pedido principal (ID: {$this->id}): " . print_r($stmt->errorInfo(), true));
            return false;
        } catch (PDOException $e) {
            error_log("Exceção PDO em Pedido::update (ID: {$this->id}): " . $e->getMessage());
            return false;
        }
    }

    public function getItens($pedidoId)
    {
        $query = "SELECT
                    ip.*,
                    p.nome_produto AS nome_produto_catalogo,
                    p.codigo AS codigo_produto,
                    p.foto_path,
                    p.descricao_detalhada AS produto_descricao
                  FROM {$this->table_itens} ip
                  LEFT JOIN produtos p ON ip.produto_id = p.id
                  WHERE ip.pedido_id = :pedido_id
                  ORDER BY ip.ordem ASC, ip.id ASC";
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':pedido_id', $pedidoId, PDO::PARAM_INT);
            $stmt->execute();

            $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($itens as &$item) {
                $item['quantidade'] = (int) ($item['quantidade'] ?? 1);
                $item['preco_unitario'] = (float) ($item['preco_unitario'] ?? 0.00);
                $item['desconto'] = (float) ($item['desconto'] ?? 0.00);
                $item['preco_final'] = (float) ($item['preco_final'] ?? 0.00);
                $item['ordem'] = (int) ($item['ordem'] ?? 1);
                $item['tipo_linha'] = !empty($item['tipo_linha']) ? $item['tipo_linha'] : 'PRODUTO';
                $item['tipo'] = !empty($item['tipo']) ? $item['tipo'] : 'locacao';
                $item['observacoes'] = $item['observacoes'] ?? '';
                $item['nome_produto_manual'] = $item['nome_produto_manual'] ?? '';
                $item['nome_produto_catalogo'] = $item['nome_produto_catalogo'] ?? '';
                $item['codigo_produto'] = $item['codigo_produto'] ?? '';
                $item['foto_path'] = $item['foto_path'] ?? '';
            }
            unset($item);

            return $itens;
        } catch (PDOException $e) {
            error_log("Erro em Pedido::getItens (Pedido ID: {$pedidoId}): " . $e->getMessage());
            return false;
        }
    }

    public function updateSituacao($id, $novaSituacao)
    {
        $situacoesPermitidas = [
            'confirmado', 'em_separacao', 'entregue',
            'devolvido_parcial', 'finalizado', 'cancelado'
        ];

        if (!in_array($novaSituacao, $situacoesPermitidas, true)) {
            error_log("Situação inválida fornecida: {$novaSituacao}");
            return false;
        }

        $query = "UPDATE {$this->table} SET situacao_pedido = :situacao WHERE id = :id";
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':situacao', $novaSituacao, PDO::PARAM_STR);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);

            if ($stmt->execute()) {
                error_log("Situação do pedido ID {$id} alterada para: {$novaSituacao}");
                return true;
            }

            error_log("Falha ao atualizar situação do pedido ID {$id}: " . print_r($stmt->errorInfo(), true));
            return false;
        } catch (PDOException $e) {
            error_log("Erro em Pedido::updateSituacao (ID: {$id}): " . $e->getMessage());
            return false;
        }
    }

    public function salvarItens($pedidoId, $itens)
    {
        if (empty($pedidoId) || !is_array($itens)) {
            error_log("Parâmetros inválidos em Pedido::salvarItens - Pedido ID: {$pedidoId}");
            return false;
        }

        try {
            $queryDelete = "DELETE FROM {$this->table_itens} WHERE pedido_id = :pedido_id";
            $stmtDelete = $this->conn->prepare($queryDelete);
            $stmtDelete->bindParam(':pedido_id', $pedidoId, PDO::PARAM_INT);

            if (!$stmtDelete->execute()) {
                error_log("Erro ao deletar itens existentes do pedido ID {$pedidoId}: " . print_r($stmtDelete->errorInfo(), true));
                return false;
            }

            if (empty($itens)) {
                return true;
            }

            $queryInsert = "INSERT INTO {$this->table_itens}
                (pedido_id, produto_id, nome_produto_manual, quantidade, tipo,
                 preco_unitario, desconto, preco_final, observacoes, tipo_linha, ordem)
                VALUES
                (:pedido_id, :produto_id, :nome_produto_manual, :quantidade, :tipo,
                 :preco_unitario, :desconto, :preco_final, :observacoes, :tipo_linha, :ordem)";
            $stmtInsert = $this->conn->prepare($queryInsert);

            foreach ($itens as $index => $item) {
                $produtoId = isset($item['produto_id']) && !empty($item['produto_id']) ? (int) $item['produto_id'] : null;
                $nomeManual = isset($item['nome_produto_manual']) && trim((string) $item['nome_produto_manual']) !== ''
                    ? trim($item['nome_produto_manual'])
                    : null;
                $quantidade = isset($item['quantidade']) ? (int) $item['quantidade'] : 1;
                $tipo = isset($item['tipo']) && !empty($item['tipo']) ? $item['tipo'] : 'locacao';
                $precoUnitario = isset($item['preco_unitario']) ? (float) $item['preco_unitario'] : 0.00;
                $desconto = isset($item['desconto']) ? (float) $item['desconto'] : 0.00;
                $precoFinal = isset($item['preco_final'])
                    ? (float) $item['preco_final']
                    : ($quantidade * ($precoUnitario - $desconto));
                $observacoes = isset($item['observacoes']) && trim((string) $item['observacoes']) !== ''
                    ? trim($item['observacoes'])
                    : null;
                $tipoLinha = isset($item['tipo_linha']) && !empty($item['tipo_linha']) ? $item['tipo_linha'] : 'PRODUTO';
                $ordem = isset($item['ordem']) ? (int) $item['ordem'] : ($index + 1);

                if (!in_array($tipoLinha, ['PRODUTO', 'CABECALHO_SECAO'], true)) {
                    $tipoLinha = 'PRODUTO';
                }

                if ($tipoLinha === 'CABECALHO_SECAO') {
                    $produtoId = null;
                    $nomeManual = $nomeManual ?: 'Título';
                    $quantidade = 0;
                    $precoUnitario = 0.00;
                    $desconto = 0.00;
                    $precoFinal = 0.00;
                    $tipo = null;
                } else {
                    if ($quantidade <= 0) {
                        $quantidade = 1;
                    }

                    if ($produtoId === null) {
                        $nomeManual = $nomeManual ?: 'Item manual';
                    }
                }

                $stmtInsert->bindValue(':pedido_id', $pedidoId, PDO::PARAM_INT);
                $stmtInsert->bindValue(':produto_id', $produtoId, $produtoId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
                $stmtInsert->bindValue(':nome_produto_manual', $nomeManual, $nomeManual === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
                $stmtInsert->bindValue(':quantidade', $quantidade, PDO::PARAM_INT);
                $stmtInsert->bindValue(':tipo', $tipo, $tipo === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
                $stmtInsert->bindValue(':preco_unitario', $precoUnitario, PDO::PARAM_STR);
                $stmtInsert->bindValue(':desconto', $desconto, PDO::PARAM_STR);
                $stmtInsert->bindValue(':preco_final', $precoFinal, PDO::PARAM_STR);
                $stmtInsert->bindValue(':observacoes', $observacoes, $observacoes === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
                $stmtInsert->bindValue(':tipo_linha', $tipoLinha, PDO::PARAM_STR);
                $stmtInsert->bindValue(':ordem', $ordem, PDO::PARAM_INT);

                if (!$stmtInsert->execute()) {
                    error_log("Erro ao inserir item {$index} do pedido ID {$pedidoId}: " . print_r($stmtInsert->errorInfo(), true));
                    return false;
                }
            }

            return true;
        } catch (PDOException $e) {
            error_log("Exceção PDO em Pedido::salvarItens (Pedido ID: {$pedidoId}): " . $e->getMessage());
            return false;
        }
    }

    public function removerTodosItens($pedido_id)
    {
        if (empty($pedido_id)) {
            error_log("ID do pedido não fornecido em removerTodosItens");
            return false;
        }

        $query = "DELETE FROM {$this->table_itens} WHERE pedido_id = :pedido_id";
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':pedido_id', $pedido_id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Erro em Pedido::removerTodosItens (Pedido ID: {$pedido_id}): " . $e->getMessage());
            return false;
        }
    }

    public function gerarProximoNumero()
    {
        $stmt = $this->conn->query("SELECT MAX(numero) FROM numeracao_sequencial");
        $ultimoNumero = $stmt->fetchColumn();
        return ($ultimoNumero < 3000) ? 3000 : $ultimoNumero + 1;
    }

    public function delete($id)
    {
        try {
            $queryItens = "DELETE FROM {$this->table_itens} WHERE pedido_id = :pedido_id";
            $stmtItens = $this->conn->prepare($queryItens);
            $stmtItens->bindParam(':pedido_id', $id, PDO::PARAM_INT);
            $stmtItens->execute();

            $queryPedido = "DELETE FROM {$this->table} WHERE id = :id";
            $stmtPedido = $this->conn->prepare($queryPedido);
            $stmtPedido->bindParam(':id', $id, PDO::PARAM_INT);
            $stmtPedido->execute();

            $queryNum = "DELETE FROM numeracao_sequencial WHERE pedido_id = :pedido_id AND tipo = 'pedido'";
            $stmtNum = $this->conn->prepare($queryNum);
            $stmtNum->bindParam(':pedido_id', $id, PDO::PARAM_INT);
            $stmtNum->execute();

            return true;
        } catch (PDOException $e) {
            error_log("Erro em Pedido::delete (ID: {$id}): " . $e->getMessage());
            return false;
        }
    }

    public function recalcularValores($pedidoId)
    {
        if (empty($pedidoId)) {
            return false;
        }

        try {
            $sqlSubtotal = "SELECT
                                COALESCE(SUM(CASE WHEN tipo = 'locacao' THEN preco_final ELSE 0 END), 0) AS subtotal_locacao,
                                COALESCE(SUM(CASE WHEN tipo = 'venda' THEN preco_final ELSE 0 END), 0) AS subtotal_venda
                            FROM {$this->table_itens}
                            WHERE pedido_id = :pedido_id";
            $stmtSubtotal = $this->conn->prepare($sqlSubtotal);
            $stmtSubtotal->bindParam(':pedido_id', $pedidoId, PDO::PARAM_INT);
            $stmtSubtotal->execute();
            $subtotais = $stmtSubtotal->fetch(PDO::FETCH_ASSOC);

            $subtotalLocacao = (float) ($subtotais['subtotal_locacao'] ?? 0.00);
            $subtotalVenda = (float) ($subtotais['subtotal_venda'] ?? 0.00);

            $sqlValores = "SELECT
                                desconto, taxa_domingo_feriado, taxa_madrugada, taxa_horario_especial,
                                taxa_hora_marcada, frete_terreo, frete_elevador, frete_escadas,
                                valor_sinal, valor_pago, valor_multas
                           FROM {$this->table}
                           WHERE id = :id";
            $stmtValores = $this->conn->prepare($sqlValores);
            $stmtValores->bindParam(':id', $pedidoId, PDO::PARAM_INT);
            $stmtValores->execute();
            $valores = $stmtValores->fetch(PDO::FETCH_ASSOC);

            if (!$valores) {
                return false;
            }

            $somaTaxasFretes =
                (float) ($valores['taxa_domingo_feriado'] ?? 0) +
                (float) ($valores['taxa_madrugada'] ?? 0) +
                (float) ($valores['taxa_horario_especial'] ?? 0) +
                (float) ($valores['taxa_hora_marcada'] ?? 0) +
                (float) ($valores['frete_terreo'] ?? 0) +
                (float) ($valores['frete_elevador'] ?? 0) +
                (float) ($valores['frete_escadas'] ?? 0);

            $descontoGeral = (float) ($valores['desconto'] ?? 0.00);
            $valorFinal = ($subtotalLocacao + $subtotalVenda + $somaTaxasFretes) - $descontoGeral;

            $valorMultas = (float) ($valores['valor_multas'] ?? 0.00);
            $valorSinal = (float) ($valores['valor_sinal'] ?? 0.00);
            $valorPago = (float) ($valores['valor_pago'] ?? 0.00);
            $saldoCalculado = ($valorFinal + $valorMultas) - ($valorSinal + $valorPago);
            $saldoCalculado = max(0, $saldoCalculado);

            $sqlUpdate = "UPDATE {$this->table} SET
                            subtotal_locacao = :subtotal_locacao,
                            valor_total_locacao = :valor_total_locacao,
                            subtotal_venda = :subtotal_venda,
                            valor_total_venda = :valor_total_venda,
                            valor_final = :valor_final,
                            saldo_calculado = :saldo_calculado
                          WHERE id = :id";
            $stmtUpdate = $this->conn->prepare($sqlUpdate);
            $stmtUpdate->bindValue(':subtotal_locacao', $subtotalLocacao);
            $stmtUpdate->bindValue(':valor_total_locacao', $subtotalLocacao);
            $stmtUpdate->bindValue(':subtotal_venda', $subtotalVenda);
            $stmtUpdate->bindValue(':valor_total_venda', $subtotalVenda);
            $stmtUpdate->bindValue(':valor_final', $valorFinal);
            $stmtUpdate->bindValue(':saldo_calculado', $saldoCalculado);
            $stmtUpdate->bindParam(':id', $pedidoId, PDO::PARAM_INT);

            return $stmtUpdate->execute();
        } catch (PDOException $e) {
            error_log("Erro em Pedido::recalcularValores (ID: {$pedidoId}): " . $e->getMessage());
            return false;
        }
    }

    public function getById($id)
    {
        $query = "SELECT
                    p.*,
                    c.nome AS nome_cliente,
                    c.telefone AS cliente_telefone,
                    c.email AS cliente_email,
                    c.cpf_cnpj AS cliente_cpf_cnpj
                  FROM {$this->table} p
                  LEFT JOIN clientes c ON p.cliente_id = c.id
                  WHERE p.id = :id
                  LIMIT 1";
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);

                foreach ($row as $key => $value) {
                    if (property_exists($this, $key)) {
                        if (in_array($key, ['id', 'cliente_id', 'usuario_id', 'numero', 'orcamento_id'], true)) {
                            $this->$key = ($value !== null) ? (int) $value : null;
                        } elseif (in_array($key, [
                            'valor_total_locacao', 'subtotal_locacao', 'valor_total_venda', 'subtotal_venda',
                            'desconto', 'taxa_domingo_feriado', 'taxa_madrugada', 'taxa_horario_especial',
                            'taxa_hora_marcada', 'frete_terreo', 'frete_elevador', 'frete_escadas',
                            'valor_final', 'valor_sinal', 'valor_pago', 'valor_multas', 'saldo_calculado'
                        ], true)) {
                            $this->$key = ($value !== null) ? (float) $value : null;
                        } elseif ($key === 'ajuste_manual') {
                            $this->$key = (bool) $value;
                        } else {
                            $this->$key = $value;
                        }
                    }
                }

                return $row;
            }

            return false;
        } catch (PDOException $e) {
            error_log("Erro em Pedido::getById (ID: {$id}): " . $e->getMessage());
            return false;
        }
    }

    public function obterUltimo()
    {
        $query = "SELECT
                    p.*,
                    c.nome AS nome_cliente
                  FROM {$this->table} p
                  LEFT JOIN clientes c ON p.cliente_id = c.id
                  ORDER BY p.id DESC
                  LIMIT 1";
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            return $stmt;
        } catch (PDOException $e) {
            error_log("Erro em Pedido::obterUltimo: " . $e->getMessage());
            return false;
        }
    }
}
?>
