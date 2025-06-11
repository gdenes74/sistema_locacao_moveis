<?php
class Orcamento {
    private $conn;
    private $table = 'orcamentos'; // Tabela principal de orçamentos
    private $table_itens = 'itens_orcamento'; // Tabela de itens do orçamento

    // Propriedades do Orçamento (Cabeçalho)
    public $id;
    public $numero;
    public $codigo;
    public $cliente_id;
    public $data_orcamento;
    public $data_validade;
    public $data_entrega;
    public $hora_entrega;
    public $data_evento;
    public $hora_evento;
    public $local_evento;
    public $data_devolucao_prevista;
    public $hora_devolucao;
    public $turno_entrega;
    public $turno_devolucao;
    public $tipo;
    public $status;
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
    public $ajuste_manual; // Para o cabeçalho do orçamento
    public $motivo_ajuste; // Para o cabeçalho do orçamento
    public $observacoes;   // Observações gerais do orçamento (cabeçalho)
    public $condicoes_pagamento;
    public $usuario_id;
    public $data_cadastro;

    // Propriedades para dados do cliente (para joins)
    public $nome_cliente;
    public $cliente_telefone;
    public $cliente_email;
    public $cliente_cpf_cnpj;
    public $cliente_endereco;
    public $cliente_cidade;
    public $cliente_observacoes; // Observações do cadastro do cliente

    public function __construct($db) {
        $this->conn = $db;
    }

    public function listarTodos($filtros = [], $orderBy = 'o.id DESC') {
        $query = "SELECT
                    o.id, o.numero, o.codigo, o.cliente_id, o.data_orcamento, o.data_validade,
                    o.data_entrega, o.hora_entrega, 
                    o.data_evento, o.hora_evento, o.local_evento, o.data_devolucao_prevista, o.hora_devolucao,
                    o.turno_entrega, o.turno_devolucao,
                    o.tipo, o.status, o.valor_total_locacao, o.subtotal_locacao,
                    o.valor_total_venda, o.subtotal_venda, o.desconto, o.taxa_domingo_feriado,
                    o.taxa_madrugada, o.taxa_horario_especial, o.taxa_hora_marcada,
                    o.frete_elevador, o.frete_escadas, o.frete_terreo, o.valor_final,
                    o.ajuste_manual, o.motivo_ajuste, o.observacoes, o.condicoes_pagamento,
                    o.usuario_id, o.data_cadastro,
                    c.nome AS nome_cliente
                FROM
                    {$this->table} o
                LEFT JOIN
                    clientes c ON o.cliente_id = c.id
                WHERE 1=1";
        $params = [];

        if (!empty($filtros['pesquisar'])) {
            $searchTerm = "%" . $filtros['pesquisar'] . "%";
            $query .= " AND (CAST(o.numero AS CHAR) LIKE :pesquisar_num OR o.codigo LIKE :pesquisar_cod OR c.nome LIKE :pesquisar_cliente)";
            $params[':pesquisar_num'] = $searchTerm;
            $params[':pesquisar_cod'] = $searchTerm;
            $params[':pesquisar_cliente'] = $searchTerm;
        }
        if (!empty($filtros['cliente_id']) && filter_var($filtros['cliente_id'], FILTER_VALIDATE_INT)) {
            $query .= " AND o.cliente_id = :cliente_id";
            $params[':cliente_id'] = (int)$filtros['cliente_id'];
        }
        if (!empty($filtros['status'])) {
            $query .= " AND o.status = :status";
            $params[':status'] = $filtros['status'];
        }
        if (!empty($filtros['data_entrega_de']) && !empty($filtros['data_entrega_ate'])) {
            $query .= " AND o.data_entrega BETWEEN :data_entrega_de AND :data_entrega_ate";
            $params[':data_entrega_de'] = $filtros['data_entrega_de'];
            $params[':data_entrega_ate'] = $filtros['data_entrega_ate'];
        }

        if (!empty($orderBy)) {
            $allowedOrderBy = ['o.id DESC', 'o.id ASC', 'o.data_orcamento DESC', 'o.data_orcamento ASC', 'o.numero DESC', 'o.numero ASC', 'o.valor_final DESC', 'o.valor_final ASC', 'c.nome ASC', 'c.nome DESC', 'o.data_entrega DESC', 'o.data_entrega ASC'];
            if (in_array($orderBy, $allowedOrderBy)) {
                $query .= " ORDER BY " . $orderBy;
            } else {
                $query .= " ORDER BY o.id DESC";
            }
        }

        try {
            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Erro em Orcamento::listarTodos: " . $e->getMessage() . " Query: " . $query . " Params: " . print_r($params, true));
            return false;
        }
    }

    public function getById($id) {
        $query = "SELECT
                    o.*,
                    c.nome AS nome_cliente,
                    c.telefone AS cliente_telefone,
                    c.email AS cliente_email,
                    c.cpf_cnpj AS cliente_cpf_cnpj,
                    c.endereco AS cliente_endereco,
                    c.cidade AS cliente_cidade,
                    c.observacoes AS cliente_observacoes 
                FROM
                    {$this->table} o
                LEFT JOIN
                    clientes c ON o.cliente_id = c.id
                WHERE
                    o.id = :id
                LIMIT 1";
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                foreach ($row as $key => $value) {
                    if (property_exists($this, $key)) {
                        $this->$key = $value;
                        // Conversões de tipo
                        if (in_array($key, ['id', 'cliente_id', 'usuario_id', 'numero'])) {
                            $this->$key = (int)$value;
                        } elseif (in_array($key, ['valor_total_locacao', 'subtotal_locacao', 'valor_total_venda', 'subtotal_venda', 'desconto', 'taxa_domingo_feriado', 'taxa_madrugada', 'taxa_horario_especial', 'taxa_hora_marcada', 'frete_terreo', 'valor_final'])) {
                            $this->$key = (float)$value;
                        } elseif ($key === 'ajuste_manual') {
                            $this->$key = (bool)$value;
                        }
                    }
                }
                return $row; // Retorna o array para uso direto se necessário, mas as propriedades do objeto são preenchidas.
            }
            return false;
        } catch (PDOException $e) {
            error_log("Erro em Orcamento::getById (ID: {$id}): " . $e->getMessage());
            return false;
        }
    }

    public function create() {
        if (empty($this->numero)) {
            error_log("Erro: Propriedade 'numero' não definida em Orcamento::create(). Este valor deve ser gerado antes.");
            return false;
        }
        $this->codigo = "ORC-" . date('Y') . "-" . str_pad($this->numero, 5, '0', STR_PAD_LEFT); // Exemplo de formatação do código

        try {
            $this->conn->beginTransaction();

            $queryOrcamento = "INSERT INTO {$this->table}
                        (numero, codigo, cliente_id, data_orcamento, data_validade, 
                         data_entrega, hora_entrega, data_evento, hora_evento, local_evento,
                         data_devolucao_prevista, hora_devolucao, turno_entrega, turno_devolucao,
                         tipo, status, valor_total_locacao, subtotal_locacao, valor_total_venda,
                         subtotal_venda, desconto, taxa_domingo_feriado, taxa_madrugada, taxa_horario_especial,
                         taxa_hora_marcada, frete_elevador, frete_escadas, frete_terreo, valor_final, ajuste_manual,
                         motivo_ajuste, observacoes, condicoes_pagamento, usuario_id)
                    VALUES
                        (:numero, :codigo, :cliente_id, :data_orcamento, :data_validade,
                         :data_entrega, :hora_entrega, :data_evento, :hora_evento, :local_evento,
                         :data_devolucao_prevista, :hora_devolucao, :turno_entrega, :turno_devolucao,
                         :tipo, :status, :valor_total_locacao, :subtotal_locacao, :valor_total_venda,
                         :subtotal_venda, :desconto, :taxa_domingo_feriado, :taxa_madrugada, :taxa_horario_especial,
                         :taxa_hora_marcada, :frete_elevador, :frete_escadas, :frete_terreo, :valor_final, :ajuste_manual,
                         :motivo_ajuste, :observacoes, :condicoes_pagamento, :usuario_id)";

            $stmtOrcamento = $this->conn->prepare($queryOrcamento);

            // Sanitize e prepare os dados do objeto ANTES de fazer bind
            $this->data_orcamento = !empty($this->data_orcamento) ? $this->data_orcamento : date('Y-m-d');
            $this->data_validade = !empty($this->data_validade) ? $this->data_validade : date('Y-m-d', strtotime('+7 days')); // Exemplo de validade padrão
            
            $this->data_entrega = !empty($this->data_entrega) ? $this->data_entrega : null;
            $this->hora_entrega = !empty($this->hora_entrega) ? $this->hora_entrega : null;
            $this->data_evento = !empty($this->data_evento) ? $this->data_evento : null;
            $this->hora_evento = !empty($this->hora_evento) ? $this->hora_evento : null;
            $this->data_devolucao_prevista = !empty($this->data_devolucao_prevista) ? $this->data_devolucao_prevista : null;
            $this->hora_devolucao = !empty($this->hora_devolucao) ? $this->hora_devolucao : null;

            $this->local_evento = !empty($this->local_evento) ? trim($this->local_evento) : null;
            $this->turno_entrega = $this->turno_entrega ?? 'Manhã/Tarde (Horário Comercial)';
            $this->turno_devolucao = $this->turno_devolucao ?? 'Manhã/Tarde (Horário Comercial)';
            $this->tipo = $this->tipo ?? 'locacao';
            $this->status = $this->status ?? 'pendente';

            // Valores numéricos que devem ser 0.00 se não preenchidos
            $this->valor_total_locacao = (float)($this->valor_total_locacao ?? 0.00);
            $this->subtotal_locacao = (float)($this->subtotal_locacao ?? 0.00);
            $this->valor_total_venda = (float)($this->valor_total_venda ?? 0.00);
            $this->subtotal_venda = (float)($this->subtotal_venda ?? 0.00);
            $this->desconto = (float)($this->desconto ?? 0.00);
            $this->taxa_domingo_feriado = (float)($this->taxa_domingo_feriado ?? 0.00);
            $this->taxa_madrugada = (float)($this->taxa_madrugada ?? 0.00);
            $this->taxa_horario_especial = (float)($this->taxa_horario_especial ?? 0.00);
            $this->taxa_hora_marcada = (float)($this->taxa_hora_marcada ?? 0.00);
            $this->frete_terreo = (float)($this->frete_terreo ?? 0.00);
            $this->valor_final = (float)($this->valor_final ?? 0.00); // Será recalculado

            $this->frete_elevador = !empty($this->frete_elevador) ? trim($this->frete_elevador) : null;
            $this->frete_escadas = !empty($this->frete_escadas) ? trim($this->frete_escadas) : null;
            
            $this->ajuste_manual = (bool)($this->ajuste_manual ?? false);
            $this->motivo_ajuste = !empty($this->motivo_ajuste) ? trim($this->motivo_ajuste) : null;
            $this->observacoes = !empty($this->observacoes) ? trim($this->observacoes) : null;
            $this->condicoes_pagamento = !empty($this->condicoes_pagamento) ? trim($this->condicoes_pagamento) : null;
            $this->usuario_id = $this->usuario_id ?? (isset($_SESSION['usuario_id']) ? (int)$_SESSION['usuario_id'] : 1);


            // Bind dos parâmetros
            $stmtOrcamento->bindParam(':numero', $this->numero, PDO::PARAM_INT);
            $stmtOrcamento->bindParam(':codigo', $this->codigo);
            $stmtOrcamento->bindParam(':cliente_id', $this->cliente_id, PDO::PARAM_INT);
            $stmtOrcamento->bindParam(':data_orcamento', $this->data_orcamento);
            $stmtOrcamento->bindParam(':data_validade', $this->data_validade);
            $stmtOrcamento->bindParam(':data_entrega', $this->data_entrega);
            $stmtOrcamento->bindParam(':hora_entrega', $this->hora_entrega);
            $stmtOrcamento->bindParam(':data_evento', $this->data_evento);
            $stmtOrcamento->bindParam(':hora_evento', $this->hora_evento);
            $stmtOrcamento->bindParam(':local_evento', $this->local_evento);
            $stmtOrcamento->bindParam(':data_devolucao_prevista', $this->data_devolucao_prevista);
            $stmtOrcamento->bindParam(':hora_devolucao', $this->hora_devolucao);
            $stmtOrcamento->bindParam(':turno_entrega', $this->turno_entrega);
            $stmtOrcamento->bindParam(':turno_devolucao', $this->turno_devolucao);
            $stmtOrcamento->bindParam(':tipo', $this->tipo);
            $stmtOrcamento->bindParam(':status', $this->status);
            $stmtOrcamento->bindParam(':valor_total_locacao', $this->valor_total_locacao);
            $stmtOrcamento->bindParam(':subtotal_locacao', $this->subtotal_locacao);
            $stmtOrcamento->bindParam(':valor_total_venda', $this->valor_total_venda);
            $stmtOrcamento->bindParam(':subtotal_venda', $this->subtotal_venda);
            $stmtOrcamento->bindParam(':desconto', $this->desconto);
            $stmtOrcamento->bindParam(':taxa_domingo_feriado', $this->taxa_domingo_feriado);
            $stmtOrcamento->bindParam(':taxa_madrugada', $this->taxa_madrugada);
            $stmtOrcamento->bindParam(':taxa_horario_especial', $this->taxa_horario_especial);
            $stmtOrcamento->bindParam(':taxa_hora_marcada', $this->taxa_hora_marcada);
            $stmtOrcamento->bindParam(':frete_elevador', $this->frete_elevador);
            $stmtOrcamento->bindParam(':frete_escadas', $this->frete_escadas);
            $stmtOrcamento->bindParam(':frete_terreo', $this->frete_terreo);
            $stmtOrcamento->bindParam(':valor_final', $this->valor_final); // Será recalculado após salvar itens
            $stmtOrcamento->bindParam(':ajuste_manual', $this->ajuste_manual, PDO::PARAM_BOOL);
            $stmtOrcamento->bindParam(':motivo_ajuste', $this->motivo_ajuste);
            $stmtOrcamento->bindParam(':observacoes', $this->observacoes);
            $stmtOrcamento->bindParam(':condicoes_pagamento', $this->condicoes_pagamento);
            $stmtOrcamento->bindParam(':usuario_id', $this->usuario_id, PDO::PARAM_INT);

            if (!$stmtOrcamento->execute()) {
                $this->conn->rollBack();
                error_log("Erro ao inserir orçamento principal: " . print_r($stmtOrcamento->errorInfo(), true));
                return false;
            }
            $this->id = $this->conn->lastInsertId();

            $this->conn->commit(); // Commita o orçamento principal
            return $this->id; // Retorna o ID para salvar os itens

        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log("Exceção PDO em Orcamento::create: " . $e->getMessage());
            return false;
        }
    }

    public function update() {
        // Similar ao create, mas com UPDATE e WHERE id = :id
        // Certifique-se de que todas as colunas que podem ser atualizadas estão aqui.
        $query = "UPDATE {$this->table} SET
                    cliente_id = :cliente_id, data_orcamento = :data_orcamento, data_validade = :data_validade,
                    data_entrega = :data_entrega, hora_entrega = :hora_entrega, data_evento = :data_evento,
                    hora_evento = :hora_evento, local_evento = :local_evento, data_devolucao_prevista = :data_devolucao_prevista,
                    hora_devolucao = :hora_devolucao, turno_entrega = :turno_entrega, turno_devolucao = :turno_devolucao,
                    tipo = :tipo, status = :status, 
                    /* valores financeiros são atualizados por recalcularValores, mas podem ser setados aqui se necessário antes do recálculo */
                    desconto = :desconto, taxa_domingo_feriado = :taxa_domingo_feriado,
                    taxa_madrugada = :taxa_madrugada, taxa_horario_especial = :taxa_horario_especial,
                    taxa_hora_marcada = :taxa_hora_marcada, frete_elevador = :frete_elevador,
                    frete_escadas = :frete_escadas, frete_terreo = :frete_terreo, 
                    ajuste_manual = :ajuste_manual, motivo_ajuste = :motivo_ajuste, observacoes = :observacoes,
                    condicoes_pagamento = :condicoes_pagamento, usuario_id = :usuario_id
                    /* NÃO ATUALIZE numero ou codigo aqui, a menos que seja uma regra de negócio específica */
                WHERE id = :id";

        try {
            $stmt = $this->conn->prepare($query);

            // Sanitize e prepare os dados do objeto
            $this->data_orcamento = !empty($this->data_orcamento) ? $this->data_orcamento : date('Y-m-d');
            $this->data_validade = !empty($this->data_validade) ? $this->data_validade : date('Y-m-d', strtotime('+7 days'));
            $this->data_entrega = !empty($this->data_entrega) ? $this->data_entrega : null;
            $this->hora_entrega = !empty($this->hora_entrega) ? $this->hora_entrega : null;
            // ... (sanitização para outros campos como no create)

            // Bind dos parâmetros (similar ao create, mas com :id)
            $stmt->bindParam(':id', $this->id, PDO::PARAM_INT);
            $stmt->bindParam(':cliente_id', $this->cliente_id, PDO::PARAM_INT);
            $stmt->bindParam(':data_orcamento', $this->data_orcamento);
            $stmt->bindParam(':data_validade', $this->data_validade);
            $stmt->bindParam(':data_entrega', $this->data_entrega);
            $stmt->bindParam(':hora_entrega', $this->hora_entrega);
            $stmt->bindParam(':data_evento', $this->data_evento);
            $stmt->bindParam(':hora_evento', $this->hora_evento);
            $stmt->bindParam(':local_evento', $this->local_evento);
            $stmt->bindParam(':data_devolucao_prevista', $this->data_devolucao_prevista);
            $stmt->bindParam(':hora_devolucao', $this->hora_devolucao);
            $stmt->bindParam(':turno_entrega', $this->turno_entrega);
            $stmt->bindParam(':turno_devolucao', $this->turno_devolucao);
            $stmt->bindParam(':tipo', $this->tipo);
            $stmt->bindParam(':status', $this->status);
            $stmt->bindParam(':desconto', $this->desconto);
            $stmt->bindParam(':taxa_domingo_feriado', $this->taxa_domingo_feriado);
            $stmt->bindParam(':taxa_madrugada', $this->taxa_madrugada);
            $stmt->bindParam(':taxa_horario_especial', $this->taxa_horario_especial);
            $stmt->bindParam(':taxa_hora_marcada', $this->taxa_hora_marcada);
            $stmt->bindParam(':frete_elevador', $this->frete_elevador);
            $stmt->bindParam(':frete_escadas', $this->frete_escadas);
            $stmt->bindParam(':frete_terreo', $this->frete_terreo);
            $stmt->bindParam(':ajuste_manual', $this->ajuste_manual, PDO::PARAM_BOOL);
            $stmt->bindParam(':motivo_ajuste', $this->motivo_ajuste);
            $stmt->bindParam(':observacoes', $this->observacoes);
            $stmt->bindParam(':condicoes_pagamento', $this->condicoes_pagamento);
            $stmt->bindParam(':usuario_id', $this->usuario_id, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                return true; // Retorna true se a atualização do cabeçalho for bem-sucedida
            } else {
                error_log("Erro ao atualizar orçamento principal (ID: {$this->id}): " . print_r($stmt->errorInfo(), true));
                return false;
            }

        } catch (PDOException $e) {
            error_log("Exceção PDO em Orcamento::update (ID: {$this->id}): " . $e->getMessage());
            return false;
        }
    }

    public function delete($id) {
        // ON DELETE CASCADE na FK de itens_orcamento cuidará dos itens.
        $query = "DELETE FROM {$this->table} WHERE id = :id";
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Erro em Orcamento::delete (ID: {$id}): " . $e->getMessage());
            return false;
        }
    }

    public function salvarItens($orcamento_id, $itens) {
        $inTransaction = $this->conn->inTransaction();
        if (!$inTransaction) {
            $this->conn->beginTransaction();
        }

        try {
            // Deletar itens existentes para este orçamento antes de inserir os novos (estratégia de substituição)
            $this->deletarTodosItens($orcamento_id);

            // --- MODIFICADO: Query para incluir nome_produto_manual ---
            $query = "INSERT INTO {$this->table_itens}
                        (orcamento_id, produto_id, nome_produto_manual, quantidade, tipo, preco_unitario, desconto, preco_final, ajuste_manual, motivo_ajuste, observacoes)
                      VALUES
                        (:orcamento_id, :produto_id, :nome_produto_manual, :quantidade, :tipo, :preco_unitario, :desconto, :preco_final, :ajuste_manual, :motivo_ajuste, :observacoes)";

            $stmt = $this->conn->prepare($query);

            foreach ($itens as $item) {
                // Sanitizar e preparar dados do item
                $produto_id_item = isset($item['produto_id']) && !empty($item['produto_id']) ? (int)$item['produto_id'] : null;
                $nome_produto_manual_item = isset($item['nome_produto_manual']) && !empty(trim($item['nome_produto_manual'])) ? trim($item['nome_produto_manual']) : null;
                
                // Se produto_id existe, nome_produto_manual deve ser null para evitar confusão,
                // a menos que a regra seja permitir ambos. Por ora, prioriza produto_id.
                if ($produto_id_item !== null) {
                    $nome_produto_manual_item = null;
                }

                $quantidade_item = isset($item['quantidade']) ? (int)$item['quantidade'] : 1;
                if ($quantidade_item <= 0) $quantidade_item = 1;

                $tipo_item = $item['tipo'] ?? 'locacao';
                $preco_unitario_item = (float)($item['preco_unitario'] ?? 0.00);
                $desconto_item_val = (float)($item['desconto'] ?? 0.00);
                $preco_final_item = (float)($item['preco_final'] ?? 0.00); // Idealmente, recalcular aqui também
                
                $ajuste_manual_item = isset($item['ajuste_manual']) ? (bool)$item['ajuste_manual'] : false;
                $motivo_ajuste_item = isset($item['motivo_ajuste']) ? trim($item['motivo_ajuste']) : null;
                $observacoes_item = isset($item['observacoes']) ? trim($item['observacoes']) : null;


                $stmt->bindParam(':orcamento_id', $orcamento_id, PDO::PARAM_INT);
                $stmt->bindParam(':produto_id', $produto_id_item, $produto_id_item === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
                // --- MODIFICADO: Bind para nome_produto_manual ---
                $stmt->bindParam(':nome_produto_manual', $nome_produto_manual_item, $nome_produto_manual_item === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
                
                $stmt->bindParam(':quantidade', $quantidade_item, PDO::PARAM_INT);
                $stmt->bindParam(':tipo', $tipo_item);
                $stmt->bindParam(':preco_unitario', $preco_unitario_item);
                $stmt->bindParam(':desconto', $desconto_item_val);
                $stmt->bindParam(':preco_final', $preco_final_item);
                $stmt->bindParam(':ajuste_manual', $ajuste_manual_item, PDO::PARAM_BOOL);
                $stmt->bindParam(':motivo_ajuste', $motivo_ajuste_item);
                $stmt->bindParam(':observacoes', $observacoes_item); // Observação específica do item

                if (!$stmt->execute()) {
                    error_log("Erro ao inserir item de orçamento (Orcamento ID: {$orcamento_id}): " . print_r($stmt->errorInfo(), true) . " Item Data: " . print_r($item, true));
                    if (!$inTransaction) $this->conn->rollBack();
                    return false;
                }
            }

            if (!$inTransaction) $this->conn->commit();
            return true;
        } catch (PDOException $e) {
            error_log("Exceção PDO em Orcamento::salvarItens (Orcamento ID: {$orcamento_id}): " . $e->getMessage());
            if (!$inTransaction) $this->conn->rollBack();
            return false;
        }
    }

    public function deletarTodosItens($orcamento_id) {
        $query = "DELETE FROM {$this->table_itens} WHERE orcamento_id = :orcamento_id";
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':orcamento_id', $orcamento_id, PDO::PARAM_INT);
            return $stmt->execute(); // Retorna true ou false
        } catch (PDOException $e) {
            error_log("Erro em Orcamento::deletarTodosItens (Orcamento ID: {$orcamento_id}): " . $e->getMessage());
            return false;
        }
    }

    public function getItens($orcamento_id) {
        // --- MODIFICADO: Query para buscar nome_produto_manual se existir ---
        // A query original já buscava io.*, então se nome_produto_manual existir na tabela, será retornado.
        // Apenas adicionamos explicitamente p.nome_produto para o caso de produto_id não ser nulo.
        $query = "SELECT 
                    io.*, 
                    p.nome_produto AS nome_produto_catalogo,  -- Nome do produto do catálogo
                    p.codigo AS codigo_produto
                  FROM {$this->table_itens} io
                  LEFT JOIN produtos p ON io.produto_id = p.id
                  WHERE io.orcamento_id = :orcamento_id
                  ORDER BY io.id ASC";
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':orcamento_id', $orcamento_id, PDO::PARAM_INT);
            $stmt->execute();
            $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Adicionar lógica para usar nome_produto_manual se nome_produto_catalogo for nulo
            // Isso é mais para a camada de visualização, mas pode ser útil aqui.
            // No entanto, a visualização (show.php) é o melhor lugar para decidir qual nome exibir.
            // Por agora, retornamos os dados como estão do banco.

            return $itens;
        } catch (PDOException $e) {
            error_log("Erro em Orcamento::getItens (Orcamento ID: {$orcamento_id}): " . $e->getMessage());
            return false;
        }
    }

    public function verificarEAtualizarExpirados() {
        $query = "UPDATE {$this->table}
                  SET status = 'expirado'
                  WHERE data_validade < CURDATE() AND status = 'pendente'";
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            return $stmt->rowCount(); // Retorna o número de orçamentos atualizados
        } catch (PDOException $e) {
            error_log("Erro em Orcamento::verificarEAtualizarExpirados: " . $e->getMessage());
            return 0;
        }
    }

    public function updateStatus($id, $novoStatus) {
        $query = "UPDATE {$this->table} SET status = :status WHERE id = :id";
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':status', $novoStatus);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Erro em Orcamento::updateStatus (ID: {$id}): " . $e->getMessage());
            return false;
        }
    }

    public function recalcularValores($orcamento_id) {
        $dadosOrcamentoAtual = $this->getById($orcamento_id);
        if (!$dadosOrcamentoAtual) {
            error_log("Erro em Orcamento::recalcularValores: Não foi possível carregar o orçamento ID {$orcamento_id} para recálculo.");
            return false;
        }

        $itens = $this->getItens($orcamento_id);
        $subtotal_locacao_calc = 0.0;
        $subtotal_venda_calc = 0.0;

        if ($itens !== false && !empty($itens)) {
            foreach ($itens as $item) {
                $precoFinalItem = (float)($item['preco_final'] ?? 0.00);
                if (isset($item['tipo']) && $item['tipo'] === 'venda') {
                    $subtotal_venda_calc += $precoFinalItem;
                } else {
                    $subtotal_locacao_calc += $precoFinalItem;
                }
            }
        }

        // As propriedades do objeto $this já foram preenchidas por getById()
        // com os valores das taxas, descontos, fretes que vieram do formulário ou do banco.
        $this->subtotal_locacao = $subtotal_locacao_calc;
        $this->valor_total_locacao = $subtotal_locacao_calc; // Simplificação, pode ter outra lógica
        $this->subtotal_venda = $subtotal_venda_calc;
        $this->valor_total_venda = $subtotal_venda_calc;   // Simplificação

        $total_taxas = (float)($this->taxa_domingo_feriado ?? 0) +
                       (float)($this->taxa_madrugada ?? 0) +
                       (float)($this->taxa_horario_especial ?? 0) +
                       (float)($this->taxa_hora_marcada ?? 0);
        $total_frete = (float)($this->frete_terreo ?? 0);
        // Adicionar frete_elevador e frete_escadas se forem numéricos e desejado
        // Ex: $total_frete += is_numeric($this->frete_elevador) ? (float)$this->frete_elevador : 0;

        $this->valor_final = ($this->subtotal_locacao + $this->subtotal_venda + $total_taxas + $total_frete) - (float)($this->desconto ?? 0);

        // Query para atualizar apenas os valores calculados e as taxas/fretes que podem ter sido editados no form
        $query = "UPDATE {$this->table} SET
                  subtotal_locacao = :subtotal_locacao, valor_total_locacao = :valor_total_locacao,
                  subtotal_venda = :subtotal_venda, valor_total_venda = :valor_total_venda,
                  valor_final = :valor_final,
                  desconto = :desconto, taxa_domingo_feriado = :taxa_domingo_feriado,
                  taxa_madrugada = :taxa_madrugada, taxa_horario_especial = :taxa_horario_especial,
                  taxa_hora_marcada = :taxa_hora_marcada, frete_terreo = :frete_terreo,
                  frete_elevador = :frete_elevador, frete_escadas = :frete_escadas
                  WHERE id = :id";
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':subtotal_locacao', $this->subtotal_locacao);
            $stmt->bindParam(':valor_total_locacao', $this->valor_total_locacao);
            $stmt->bindParam(':subtotal_venda', $this->subtotal_venda);
            $stmt->bindParam(':valor_total_venda', $this->valor_total_venda);
            $stmt->bindParam(':valor_final', $this->valor_final);
            
            $stmt->bindParam(':desconto', $this->desconto); // Usa o valor de $this (que veio do form/getById)
            $stmt->bindParam(':taxa_domingo_feriado', $this->taxa_domingo_feriado);
            $stmt->bindParam(':taxa_madrugada', $this->taxa_madrugada);
            $stmt->bindParam(':taxa_horario_especial', $this->taxa_horario_especial);
            $stmt->bindParam(':taxa_hora_marcada', $this->taxa_hora_marcada);
            $stmt->bindParam(':frete_terreo', $this->frete_terreo);
            $stmt->bindParam(':frete_elevador', $this->frete_elevador); 
            $stmt->bindParam(':frete_escadas', $this->frete_escadas);   

            $stmt->bindParam(':id', $orcamento_id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Erro em Orcamento::recalcularValores (update) (ID: {$orcamento_id}): " . $e->getMessage());
            return false;
        }
    }

    public function obterUltimo() {
        $query = "SELECT o.*, c.nome as nome_cliente
                  FROM {$this->table} o
                  LEFT JOIN clientes c ON o.cliente_id = c.id
                  ORDER BY o.id DESC
                  LIMIT 1";
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            return $stmt; 
        } catch (PDOException $e) {
            error_log("Erro em Orcamento::obterUltimo: " . $e->getMessage());
            return false;
        }
    }
}
?>