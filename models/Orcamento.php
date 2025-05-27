<?php
class Orcamento {
    private $conn;
    private $table = 'orcamentos';
    private $tableNumeracao = 'numeracao_sequencial'; // Tabela para controle de numeração

    // Propriedades do Orçamento
    public $id;
    public $numero; // INT - Sequencial único global (vem de numeracao_sequencial)
    public $codigo; // VARCHAR - Ex: ORC-2024-123
    public $cliente_id;
    public $data_orcamento;
    public $data_validade;
    public $data_evento;
    public $hora_evento;
    public $local_evento;
    public $data_devolucao_prevista;
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
    public $ajuste_manual;
    public $motivo_ajuste;
    public $observacoes;
    public $condicoes_pagamento;
    public $usuario_id;
    public $data_cadastro;

    public $nome_cliente;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function listarTodos($filtros = [], $orderBy = 'o.id DESC') {
        $query = "SELECT
                    o.id, o.numero, o.codigo, o.cliente_id, o.data_orcamento, o.data_validade,
                    o.data_evento, o.hora_evento, o.local_evento, o.data_devolucao_prevista,
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
            $query .= " AND (CAST(o.numero AS CHAR) LIKE :pesquisar_num OR o.codigo LIKE :pesquisar_cod OR c.nome LIKE :pesquisar_cliente)";
            $params[':pesquisar_num'] = "%" . $filtros['pesquisar'] . "%";
            $params[':pesquisar_cod'] = "%" . $filtros['pesquisar'] . "%";
            $params[':pesquisar_cliente'] = "%" . $filtros['pesquisar'] . "%";
        }
        if (!empty($filtros['cliente_id'])) {
            $query .= " AND o.cliente_id = :cliente_id";
            $params[':cliente_id'] = $filtros['cliente_id'];
        }
        if (!empty($filtros['status'])) {
            $query .= " AND o.status = :status";
            $params[':status'] = $filtros['status'];
        }

        if (!empty($orderBy)) {
            $allowedOrderBy = ['o.id DESC', 'o.id ASC', 'o.data_orcamento DESC', 'o.data_orcamento ASC', 'o.numero DESC', 'o.numero ASC', 'o.valor_final DESC', 'o.valor_final ASC', 'c.nome ASC', 'c.nome DESC'];
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
            error_log("Erro em Orcamento::listarTodos (Versao 2): " . $e->getMessage());
            return false;
        }
    }

    public function getById($id) {
        $query = "SELECT
                    o.*, 
                    c.nome AS nome_cliente
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
                        if (in_array($key, ['id', 'cliente_id', 'usuario_id', 'numero'])) {
                            $this->$key = (int)$value;
                        } elseif (in_array($key, ['valor_total_locacao', 'subtotal_locacao', 'valor_total_venda', 'subtotal_venda', 'desconto', 'taxa_domingo_feriado', 'taxa_madrugada', 'taxa_horario_especial', 'taxa_hora_marcada', 'frete_terreo', 'valor_final'])) {
                            $this->$key = (float)$value;
                        } elseif ($key === 'ajuste_manual') {
                            $this->$key = (bool)$value;
                        }
                    }
                }
                $this->nome_cliente = $row['nome_cliente'] ?? null;
                return $row; 
            }
            return false;
        } catch (PDOException $e) {
            error_log("Erro em Orcamento::getById (Versao 2): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Gera o próximo NÚMERO (INT) sequencial global, consultando a tabela numeracao_sequencial.
     * @return int|false O próximo número ou false em caso de erro.
     */
    private function gerarProximoNumeroDocumentoGlobal() {
        // Busca o maior 'numero' (INT) na tabela de numeração com lock para evitar concorrência
        $query = "SELECT MAX(numero) as max_numero FROM {$this->tableNumeracao} FOR UPDATE";
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $proximo_numero = 1; // Começa em 1 se não houver nenhum
            if ($row && $row['max_numero'] !== null) {
                $proximo_numero = (int)$row['max_numero'] + 1;
            }
            return $proximo_numero;
        } catch (PDOException $e) {
            error_log("Erro em gerarProximoNumeroDocumentoGlobal (Versao 2): " . $e->getMessage());
            return false; 
        }
    }

    public function create() {
        // 1. Gerar o próximo NÚMERO global (INT)
        $novoNumeroGlobal = $this->gerarProximoNumeroDocumentoGlobal();
        if ($novoNumeroGlobal === false) {
            error_log("Falha ao gerar número global do documento para o orçamento (Versao 2).");
            return false; 
        }
        $this->numero = $novoNumeroGlobal;

        // 2. Gerar o CÓDIGO (ex: ORC-ANOATUAL-NUMEROGLOBAL)
        $this->codigo = "ORC-" . date('Y') . "-" . $this->numero;

        // Iniciar transação
        try {
            $this->conn->beginTransaction();

            // 3. Inserir na tabela 'orcamentos'
            $queryOrcamento = "INSERT INTO {$this->table}
                        (numero, codigo, cliente_id, data_orcamento, data_validade, data_evento, hora_evento, local_evento,
                        data_devolucao_prevista, tipo, status, valor_total_locacao, subtotal_locacao, valor_total_venda,
                        subtotal_venda, desconto, taxa_domingo_feriado, taxa_madrugada, taxa_horario_especial,
                        taxa_hora_marcada, frete_elevador, frete_escadas, frete_terreo, valor_final, ajuste_manual,
                        motivo_ajuste, observacoes, condicoes_pagamento, usuario_id)
                    VALUES
                        (:numero, :codigo, :cliente_id, :data_orcamento, :data_validade, :data_evento, :hora_evento, :local_evento,
                        :data_devolucao_prevista, :tipo, :status, :valor_total_locacao, :subtotal_locacao, :valor_total_venda,
                        :subtotal_venda, :desconto, :taxa_domingo_feriado, :taxa_madrugada, :taxa_horario_especial,
                        :taxa_hora_marcada, :frete_elevador, :frete_escadas, :frete_terreo, :valor_final, :ajuste_manual,
                        :motivo_ajuste, :observacoes, :condicoes_pagamento, :usuario_id)";
            
            $stmtOrcamento = $this->conn->prepare($queryOrcamento);

            $this->data_orcamento = !empty($this->data_orcamento) ? date('Y-m-d', strtotime(str_replace('/', '-', $this->data_orcamento))) : date('Y-m-d');
            $this->data_validade = !empty($this->data_validade) ? date('Y-m-d', strtotime(str_replace('/', '-', $this->data_validade))) : date('Y-m-d', strtotime('+30 days', strtotime($this->data_orcamento)));
            $this->data_evento = !empty($this->data_evento) ? date('Y-m-d', strtotime(str_replace('/', '-', $this->data_evento))) : null;
            $this->data_devolucao_prevista = !empty($this->data_devolucao_prevista) ? date('Y-m-d', strtotime(str_replace('/', '-', $this->data_devolucao_prevista))) : null;
            $this->hora_evento = !empty($this->hora_evento) ? $this->hora_evento : null;
            $this->status = $this->status ?? 'pendente';
            $this->tipo = $this->tipo ?? 'locacao';
            $this->ajuste_manual = isset($this->ajuste_manual) ? (bool)$this->ajuste_manual : false;
            $this->usuario_id = $this->usuario_id ?? (isset($_SESSION['usuario_id']) ? $_SESSION['usuario_id'] : 1);

            $stmtOrcamento->bindParam(':numero', $this->numero, PDO::PARAM_INT);
            $stmtOrcamento->bindParam(':codigo', $this->codigo);
            $stmtOrcamento->bindParam(':cliente_id', $this->cliente_id, PDO::PARAM_INT);
            $stmtOrcamento->bindParam(':data_orcamento', $this->data_orcamento);
            $stmtOrcamento->bindParam(':data_validade', $this->data_validade);
            $stmtOrcamento->bindParam(':data_evento', $this->data_evento);
            $stmtOrcamento->bindParam(':hora_evento', $this->hora_evento);
            $stmtOrcamento->bindParam(':local_evento', $this->local_evento);
            $stmtOrcamento->bindParam(':data_devolucao_prevista', $this->data_devolucao_prevista);
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
            $stmtOrcamento->bindParam(':valor_final', $this->valor_final);
            $stmtOrcamento->bindParam(':ajuste_manual', $this->ajuste_manual, PDO::PARAM_BOOL);
            $stmtOrcamento->bindParam(':motivo_ajuste', $this->motivo_ajuste);
            $stmtOrcamento->bindParam(':observacoes', $this->observacoes);
            $stmtOrcamento->bindParam(':condicoes_pagamento', $this->condicoes_pagamento);
            $stmtOrcamento->bindParam(':usuario_id', $this->usuario_id, PDO::PARAM_INT);

            if (!$stmtOrcamento->execute()) {
                $this->conn->rollBack();
                error_log("Erro ao inserir orçamento (Versao 2): " . print_r($stmtOrcamento->errorInfo(), true));
                return false;
            }
            $this->id = $this->conn->lastInsertId(); 

            // 4. Inserir na tabela 'numeracao_sequencial'
            $queryNumeracao = "INSERT INTO {$this->tableNumeracao} (numero, tipo, orcamento_id, data_atribuicao)
                               VALUES (:numero, 'orcamento', :orcamento_id, NOW())";
            $stmtNumeracao = $this->conn->prepare($queryNumeracao);
            $stmtNumeracao->bindParam(':numero', $this->numero, PDO::PARAM_INT); // O mesmo número global
            $stmtNumeracao->bindParam(':orcamento_id', $this->id, PDO::PARAM_INT);
            
            if (!$stmtNumeracao->execute()) {
                $this->conn->rollBack();
                error_log("Erro ao inserir na numeracao_sequencial (Versao 2): " . print_r($stmtNumeracao->errorInfo(), true));
                if ($stmtNumeracao->errorCode() == '23000') { 
                    error_log("Violação de chave única ao inserir na numeracao_sequencial para o número: " . $this->numero . " (Versao 2)");
                }
                return false;
            }

            $this->conn->commit();
            return $this->id; 

        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) { 
                $this->conn->rollBack();
            }
            error_log("Exceção PDO em Orcamento::create (Versao 2): " . $e->getMessage());
            return false;
        }
    }
    
    public function update() {
        // O 'numero' e 'codigo' não devem ser atualizados após a criação.
        $query = "UPDATE {$this->table} SET
                    cliente_id = :cliente_id,
                    data_orcamento = :data_orcamento,
                    data_validade = :data_validade,
                    data_evento = :data_evento,
                    hora_evento = :hora_evento,
                    local_evento = :local_evento,
                    data_devolucao_prevista = :data_devolucao_prevista,
                    tipo = :tipo,
                    status = :status,
                    valor_total_locacao = :valor_total_locacao,
                    subtotal_locacao = :subtotal_locacao,
                    valor_total_venda = :valor_total_venda,
                    subtotal_venda = :subtotal_venda,
                    desconto = :desconto,
                    taxa_domingo_feriado = :taxa_domingo_feriado,
                    taxa_madrugada = :taxa_madrugada,
                    taxa_horario_especial = :taxa_horario_especial,
                    taxa_hora_marcada = :taxa_hora_marcada,
                    frete_elevador = :frete_elevador,
                    frete_escadas = :frete_escadas,
                    frete_terreo = :frete_terreo,
                    valor_final = :valor_final,
                    ajuste_manual = :ajuste_manual,
                    motivo_ajuste = :motivo_ajuste,
                    observacoes = :observacoes,
                    condicoes_pagamento = :condicoes_pagamento,
                    usuario_id = :usuario_id
                WHERE id = :id";

        try {
            $stmt = $this->conn->prepare($query);

            $this->data_orcamento = !empty($this->data_orcamento) ? date('Y-m-d', strtotime(str_replace('/', '-', $this->data_orcamento))) : null;
            $this->data_validade = !empty($this->data_validade) ? date('Y-m-d', strtotime(str_replace('/', '-', $this->data_validade))) : null;
            $this->data_evento = !empty($this->data_evento) ? date('Y-m-d', strtotime(str_replace('/', '-', $this->data_evento))) : null;
            $this->data_devolucao_prevista = !empty($this->data_devolucao_prevista) ? date('Y-m-d', strtotime(str_replace('/', '-', $this->data_devolucao_prevista))) : null;
            $this->hora_evento = !empty($this->hora_evento) ? $this->hora_evento : null;
            $this->ajuste_manual = isset($this->ajuste_manual) ? (bool)$this->ajuste_manual : false;
            $this->usuario_id = $this->usuario_id ?? (isset($_SESSION['usuario_id']) ? $_SESSION['usuario_id'] : 1);
            
            $stmt->bindParam(':id', $this->id, PDO::PARAM_INT);
            $stmt->bindParam(':cliente_id', $this->cliente_id, PDO::PARAM_INT);
            $stmt->bindParam(':data_orcamento', $this->data_orcamento);
            $stmt->bindParam(':data_validade', $this->data_validade);
            $stmt->bindParam(':data_evento', $this->data_evento);
            $stmt->bindParam(':hora_evento', $this->hora_evento);
            $stmt->bindParam(':local_evento', $this->local_evento);
            $stmt->bindParam(':data_devolucao_prevista', $this->data_devolucao_prevista);
            $stmt->bindParam(':tipo', $this->tipo);
            $stmt->bindParam(':status', $this->status);
            $stmt->bindParam(':valor_total_locacao', $this->valor_total_locacao);
            $stmt->bindParam(':subtotal_locacao', $this->subtotal_locacao);
            $stmt->bindParam(':valor_total_venda', $this->valor_total_venda);
            $stmt->bindParam(':subtotal_venda', $this->subtotal_venda);
            $stmt->bindParam(':desconto', $this->desconto);
            $stmt->bindParam(':taxa_domingo_feriado', $this->taxa_domingo_feriado);
            $stmt->bindParam(':taxa_madrugada', $this->taxa_madrugada);
            $stmt->bindParam(':taxa_horario_especial', $this->taxa_horario_especial);
            $stmt->bindParam(':taxa_hora_marcada', $this->taxa_hora_marcada);
            $stmt->bindParam(':frete_elevador', $this->frete_elevador);
            $stmt->bindParam(':frete_escadas', $this->frete_escadas);
            $stmt->bindParam(':frete_terreo', $this->frete_terreo);
            $stmt->bindParam(':valor_final', $this->valor_final);
            $stmt->bindParam(':ajuste_manual', $this->ajuste_manual, PDO::PARAM_BOOL);
            $stmt->bindParam(':motivo_ajuste', $this->motivo_ajuste);
            $stmt->bindParam(':observacoes', $this->observacoes);
            $stmt->bindParam(':condicoes_pagamento', $this->condicoes_pagamento);
            $stmt->bindParam(':usuario_id', $this->usuario_id, PDO::PARAM_INT);

            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Erro em Orcamento::update (Versao 2): " . $e->getMessage());
            return false;
        }
    }

    public function delete($id) {
        $this->deletarTodosItens($id); 

        $query = "DELETE FROM {$this->table} WHERE id = :id";
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Erro em Orcamento::delete (Versao 2): " . $e->getMessage());
            return false;
        }
    }

    public function salvarItens($orcamento_id, $itens) {
        $this->deletarTodosItens($orcamento_id);

        $query = "INSERT INTO itens_orcamento
                    (orcamento_id, produto_id, quantidade, tipo, preco_unitario, desconto, preco_final, ajuste_manual, motivo_ajuste, observacoes)
                  VALUES
                    (:orcamento_id, :produto_id, :quantidade, :tipo, :preco_unitario, :desconto, :preco_final, :ajuste_manual, :motivo_ajuste, :observacoes)";
        
        try {
            $stmt = $this->conn->prepare($query);
            foreach ($itens as $item) {
                $item['ajuste_manual'] = isset($item['ajuste_manual']) ? (bool)$item['ajuste_manual'] : false;
                $item['tipo'] = $item['tipo'] ?? 'locacao';

                $stmt->bindParam(':orcamento_id', $orcamento_id, PDO::PARAM_INT);
                $stmt->bindParam(':produto_id', $item['produto_id'], PDO::PARAM_INT);
                $stmt->bindParam(':quantidade', $item['quantidade'], PDO::PARAM_INT);
                $stmt->bindParam(':tipo', $item['tipo']); 
                $stmt->bindParam(':preco_unitario', $item['preco_unitario']);
                $stmt->bindParam(':desconto', $item['desconto']);
                $stmt->bindParam(':preco_final', $item['preco_final']);
                $stmt->bindParam(':ajuste_manual', $item['ajuste_manual'], PDO::PARAM_BOOL);
                $stmt->bindParam(':motivo_ajuste', $item['motivo_ajuste']);
                $stmt->bindParam(':observacoes', $item['observacoes']);
                
                if (!$stmt->execute()) {
                    error_log("Erro ao inserir item de orçamento (Versao 2): " . print_r($stmt->errorInfo(), true));
                    return false; // Interrompe se um item falhar
                }
            }
            return true;
        } catch (PDOException $e) {
            error_log("Erro em Orcamento::salvarItens (Versao 2): " . $e->getMessage());
            return false;
        }
    }

    public function deletarTodosItens($orcamento_id) {
        $query = "DELETE FROM itens_orcamento WHERE orcamento_id = :orcamento_id";
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':orcamento_id', $orcamento_id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Erro em Orcamento::deletarTodosItens (Versao 2): " . $e->getMessage());
            return false;
        }
    }

    public function getItens($orcamento_id) {
        $query = "SELECT io.*, p.nome_produto, p.codigo AS codigo_produto
                  FROM itens_orcamento io
                  LEFT JOIN produtos p ON io.produto_id = p.id
                  WHERE io.orcamento_id = :orcamento_id
                  ORDER BY io.id ASC";
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':orcamento_id', $orcamento_id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro em Orcamento::getItens (Versao 2): " . $e->getMessage());
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
            return $stmt->rowCount(); 
        } catch (PDOException $e) {
            error_log("Erro em Orcamento::verificarEAtualizarExpirados (Versao 2): " . $e->getMessage());
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
            error_log("Erro em Orcamento::updateStatus (Versao 2): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Recalcula os valores totais do orçamento com base nos itens associados.
     * @param int $orcamento_id ID do orçamento
     * @return bool Retorna true se os valores foram atualizados com sucesso, false caso contrário.
     */
    public function recalcularValores($orcamento_id) {
        $itens = $this->getItens($orcamento_id);
        $subtotal_locacao = 0.0;
        $subtotal_venda = 0.0;

        if (!empty($itens)) {
            foreach ($itens as $item) {
                if ($item['tipo'] === 'locacao') {
                    $subtotal_locacao += (float)$item['preco_final'];
                } else if ($item['tipo'] === 'venda') {
                    $subtotal_venda += (float)$item['preco_final'];
                }
            }
        }

        // Atualizar valores no objeto
        $this->subtotal_locacao = $subtotal_locacao;
        $this->subtotal_venda = $subtotal_venda;
        $this->valor_total_locacao = $subtotal_locacao;
        $this->valor_total_venda = $subtotal_venda;

        // Calcular valor final considerando descontos e taxas
        $total_taxas = (float)$this->taxa_domingo_feriado + (float)$this->taxa_madrugada + 
                       (float)$this->taxa_horario_especial + (float)$this->taxa_hora_marcada;
        $total_frete = (float)$this->frete_terreo;
        if (is_numeric($this->frete_elevador)) {
            $total_frete += (float)$this->frete_elevador;
        }
        if (is_numeric($this->frete_escadas)) {
            $total_frete += (float)$this->frete_escadas;
        }
        $this->valor_final = ($subtotal_locacao + $subtotal_venda + $total_taxas + $total_frete) - (float)$this->desconto;

        // Atualizar no banco de dados
        $query = "UPDATE {$this->table} SET 
                  subtotal_locacao = :subtotal_locacao, 
                  valor_total_locacao = :valor_total_locacao, 
                  subtotal_venda = :subtotal_venda, 
                  valor_total_venda = :valor_total_venda, 
                  valor_final = :valor_final 
                  WHERE id = :id";
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':subtotal_locacao', $this->subtotal_locacao);
            $stmt->bindParam(':valor_total_locacao', $this->valor_total_locacao);
            $stmt->bindParam(':subtotal_venda', $this->subtotal_venda);
            $stmt->bindParam(':valor_total_venda', $this->valor_total_venda);
            $stmt->bindParam(':valor_final', $this->valor_final);
            $stmt->bindParam(':id', $orcamento_id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Erro em Orcamento::recalcularValores: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Gera o próximo número sequencial para um novo orçamento
     * @return string Próximo número de orçamento
     */
    public function gerarProximoNumero() {
        $query = "SELECT MAX(CAST(numero AS UNSIGNED)) as ultimo_numero FROM orcamentos";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $ultimoNumero = $result['ultimo_numero'] ?? 0;
        
        return sprintf('%06d', $ultimoNumero + 1);
    }

    /**
     * Gera o próximo código para um novo orçamento no formato ORC-AAAAMM-XXXX
     * @return string Próximo código de orçamento
     */
    public function gerarProximoCodigo() {
        $anoMes = date('Ym'); // Formato AAAAMM
        
        $query = "SELECT MAX(SUBSTRING_INDEX(codigo, '-', -1)) as ultimo_sequencial 
                  FROM orcamentos 
                  WHERE codigo LIKE :prefixo";
        
        $prefixo = "ORC-{$anoMes}-%";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':prefixo', $prefixo);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $ultimoSequencial = $result['ultimo_sequencial'] ?? 0;
        
        $proximoSequencial = sprintf('%04d', intval($ultimoSequencial) + 1);
        
        return "ORC-{$anoMes}-{$proximoSequencial}";
    }

    /**
     * Obtém o último orçamento cadastrado
     * @return PDOStatement|false Retorna um statement com o último orçamento
     */
    public function obterUltimo() {
        $query = "SELECT o.*, c.nome as nome_cliente 
                  FROM orcamentos o
                  LEFT JOIN clientes c ON o.cliente_id = c.id
                  ORDER BY o.id DESC
                  LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        return $stmt;
    }
}
?>