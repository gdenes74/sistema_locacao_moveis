<?php
require_once 'C:/xampp/htdocs/sistema-toalhas/config/database.php';

class Pedido {
    private $conn;
    private $table_name = "pedidos";

    public $id;
    public $numero;
    public $codigo;
    public $cliente_id;
    public $orcamento_id;
    public $data_pedido;
    public $data_evento;
    public $hora_evento;
    public $local_evento;
    public $data_entrega;
    public $data_retirada_prevista;
    public $data_retirada_efetiva;
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
    public $valor_sinal;
    public $data_pagamento_sinal;
    public $valor_pago;
    public $data_pagamento_final;
    public $valor_multas;
    public $observacoes;
    public $condicoes_pagamento;
    public $usuario_id;
    public $data_cadastro;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAll() {
        $query = "SELECT id, numero, codigo, cliente_id, data_pedido, data_evento, tipo, status, valor_final, data_cadastro 
                  FROM " . $this->table_name . " 
                  ORDER BY numero DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    public function getById($id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = ? LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $id);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $this->id = $row['id'];
            $this->numero = $row['numero'];
            $this->codigo = $row['codigo'];
            $this->cliente_id = $row['cliente_id'];
            $this->orcamento_id = $row['orcamento_id'];
            $this->data_pedido = $row['data_pedido'];
            $this->data_evento = $row['data_evento'];
            $this->hora_evento = $row['hora_evento'];
            $this->local_evento = $row['local_evento'];
            $this->data_entrega = $row['data_entrega'];
            $this->data_retirada_prevista = $row['data_retirada_prevista'];
            $this->data_retirada_efetiva = $row['data_retirada_efetiva'];
            $this->tipo = $row['tipo'];
            $this->status = $row['status'];
            $this->valor_total_locacao = $row['valor_total_locacao'];
            $this->subtotal_locacao = $row['subtotal_locacao'];
            $this->valor_total_venda = $row['valor_total_venda'];
            $this->subtotal_venda = $row['subtotal_venda'];
            $this->desconto = $row['desconto'];
            $this->taxa_domingo_feriado = $row['taxa_domingo_feriado'];
            $this->taxa_madrugada = $row['taxa_madrugada'];
            $this->taxa_horario_especial = $row['taxa_horario_especial'];
            $this->taxa_hora_marcada = $row['taxa_hora_marcada'];
            $this->frete_elevador = $row['frete_elevador'];
            $this->frete_escadas = $row['frete_escadas'];
            $this->frete_terreo = $row['frete_terreo'];
            $this->valor_final = $row['valor_final'];
            $this->ajuste_manual = $row['ajuste_manual'];
            $this->motivo_ajuste = $row['motivo_ajuste'];
            $this->valor_sinal = $row['valor_sinal'];
            $this->data_pagamento_sinal = $row['data_pagamento_sinal'];
            $this->valor_pago = $row['valor_pago'];
            $this->data_pagamento_final = $row['data_pagamento_final'];
            $this->valor_multas = $row['valor_multas'];
            $this->observacoes = $row['observacoes'];
            $this->condicoes_pagamento = $row['condicoes_pagamento'];
            $this->usuario_id = $row['usuario_id'];
            $this->data_cadastro = $row['data_cadastro'];
            return true;
        }
        return false;
    }

    public function gerarProximoNumero() {
        $stmt = $this->conn->query("SELECT MAX(numero) FROM numeracao_sequencial");
        $ultimoNumero = $stmt->fetchColumn();
        return ($ultimoNumero < 3000) ? 3000 : $ultimoNumero + 1;
    }

    public function create() {
        // Se for conversão de orçamento, mantém o número do orçamento
        if ($this->orcamento_id) {
            $stmtOrc = $this->conn->prepare("SELECT numero FROM orcamentos WHERE id = :orcamento_id");
            $stmtOrc->bindParam(':orcamento_id', $this->orcamento_id);
            $stmtOrc->execute();
            $this->numero = $stmtOrc->fetchColumn();
            $this->codigo = 'PED-' . str_pad($this->numero, 5, '0', STR_PAD_LEFT);
        } else {
            $numero = $this->gerarProximoNumero();
            $this->numero = $numero;
            $this->codigo = 'PED-' . str_pad($numero, 5, '0', STR_PAD_LEFT);
        }

        $query = "INSERT INTO " . $this->table_name . "
                  SET numero=:numero, codigo=:codigo, cliente_id=:cliente_id, orcamento_id=:orcamento_id, 
                      data_pedido=:data_pedido, data_evento=:data_evento, hora_evento=:hora_evento, 
                      local_evento=:local_evento, data_entrega=:data_entrega, data_retirada_prevista=:data_retirada_prevista, 
                      data_retirada_efetiva=:data_retirada_efetiva, tipo=:tipo, status=:status, 
                      valor_total_locacao=:valor_total_locacao, subtotal_locacao=:subtotal_locacao, 
                      valor_total_venda=:valor_total_venda, subtotal_venda=:subtotal_venda, desconto=:desconto, 
                      taxa_domingo_feriado=:taxa_domingo_feriado, taxa_madrugada=:taxa_madrugada, 
                      taxa_horario_especial=:taxa_horario_especial, taxa_hora_marcada=:taxa_hora_marcada, 
                      frete_elevador=:frete_elevador, frete_escadas=:frete_escadas, frete_terreo=:frete_terreo, 
                      valor_final=:valor_final, ajuste_manual=:ajuste_manual, motivo_ajuste=:motivo_ajuste, 
                      valor_sinal=:valor_sinal, data_pagamento_sinal=:data_pagamento_sinal, valor_pago=:valor_pago, 
                      data_pagamento_final=:data_pagamento_final, valor_multas=:valor_multas, 
                      observacoes=:observacoes, condicoes_pagamento=:condicoes_pagamento, usuario_id=:usuario_id";
        $stmt = $this->conn->prepare($query);

        // Sanitização
        $this->numero = htmlspecialchars(strip_tags($this->numero));
        $this->codigo = htmlspecialchars(strip_tags($this->codigo));
        $this->cliente_id = htmlspecialchars(strip_tags($this->cliente_id));
        $this->orcamento_id = $this->orcamento_id ? htmlspecialchars(strip_tags($this->orcamento_id)) : null;
        $this->data_pedido = htmlspecialchars(strip_tags($this->data_pedido));
        $this->data_evento = htmlspecialchars(strip_tags($this->data_evento));
        $this->hora_evento = $this->hora_evento ? htmlspecialchars(strip_tags($this->hora_evento)) : null;
        $this->local_evento = htmlspecialchars(strip_tags($this->local_evento));
        $this->data_entrega = htmlspecialchars(strip_tags($this->data_entrega));
        $this->data_retirada_prevista = htmlspecialchars(strip_tags($this->data_retirada_prevista));
        $this->data_retirada_efetiva = $this->data_retirada_efetiva ? htmlspecialchars(strip_tags($this->data_retirada_efetiva)) : null;
        $this->tipo = htmlspecialchars(strip_tags($this->tipo));
        $this->status = htmlspecialchars(strip_tags($this->status));
        $this->valor_total_locacao = htmlspecialchars(strip_tags($this->valor_total_locacao));
        $this->subtotal_locacao = htmlspecialchars(strip_tags($this->subtotal_locacao));
        $this->valor_total_venda = htmlspecialchars(strip_tags($this->valor_total_venda));
        $this->subtotal_venda = htmlspecialchars(strip_tags($this->subtotal_venda));
        $this->desconto = htmlspecialchars(strip_tags($this->desconto));
        $this->taxa_domingo_feriado = htmlspecialchars(strip_tags($this->taxa_domingo_feriado));
        $this->taxa_madrugada = htmlspecialchars(strip_tags($this->taxa_madrugada));
        $this->taxa_horario_especial = htmlspecialchars(strip_tags($this->taxa_horario_especial));
        $this->taxa_hora_marcada = htmlspecialchars(strip_tags($this->taxa_hora_marcada));
        $this->frete_elevador = htmlspecialchars(strip_tags($this->frete_elevador));
        $this->frete_escadas = htmlspecialchars(strip_tags($this->frete_escadas));
        $this->frete_terreo = htmlspecialchars(strip_tags($this->frete_terreo));
        $this->valor_final = htmlspecialchars(strip_tags($this->valor_final));
        $this->ajuste_manual = htmlspecialchars(strip_tags($this->ajuste_manual));
        $this->motivo_ajuste = $this->motivo_ajuste ? htmlspecialchars(strip_tags($this->motivo_ajuste)) : null;
        $this->valor_sinal = htmlspecialchars(strip_tags($this->valor_sinal));
        $this->data_pagamento_sinal = $this->data_pagamento_sinal ? htmlspecialchars(strip_tags($this->data_pagamento_sinal)) : null;
        $this->valor_pago = htmlspecialchars(strip_tags($this->valor_pago));
        $this->data_pagamento_final = $this->data_pagamento_final ? htmlspecialchars(strip_tags($this->data_pagamento_final)) : null;
        $this->valor_multas = htmlspecialchars(strip_tags($this->valor_multas));
        $this->observacoes = htmlspecialchars(strip_tags($this->observacoes));
        $this->condicoes_pagamento = htmlspecialchars(strip_tags($this->condicoes_pagamento));
        $this->usuario_id = htmlspecialchars(strip_tags($this->usuario_id));

        // Bind dos valores
        $stmt->bindParam(':numero', $this->numero);
        $stmt->bindParam(':codigo', $this->codigo);
        $stmt->bindParam(':cliente_id', $this->cliente_id);
        $stmt->bindParam(':orcamento_id', $this->orcamento_id);
        $stmt->bindParam(':data_pedido', $this->data_pedido);
        $stmt->bindParam(':data_evento', $this->data_evento);
        $stmt->bindParam(':hora_evento', $this->hora_evento);
        $stmt->bindParam(':local_evento', $this->local_evento);
        $stmt->bindParam(':data_entrega', $this->data_entrega);
        $stmt->bindParam(':data_retirada_prevista', $this->data_retirada_prevista);
        $stmt->bindParam(':data_retirada_efetiva', $this->data_retirada_efetiva);
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
        $stmt->bindParam(':ajuste_manual', $this->ajuste_manual);
        $stmt->bindParam(':motivo_ajuste', $this->motivo_ajuste);
        $stmt->bindParam(':valor_sinal', $this->valor_sinal);
        $stmt->bindParam(':data_pagamento_sinal', $this->data_pagamento_sinal);
        $stmt->bindParam(':valor_pago', $this->valor_pago);
        $stmt->bindParam(':data_pagamento_final', $this->data_pagamento_final);
        $stmt->bindParam(':valor_multas', $this->valor_multas);
        $stmt->bindParam(':observacoes', $this->observacoes);
        $stmt->bindParam(':condicoes_pagamento', $this->condicoes_pagamento);
        $stmt->bindParam(':usuario_id', $this->usuario_id);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            // Registra ou atualiza na tabela de numeração sequencial
            if ($this->orcamento_id) {
                $stmtNum = $this->conn->prepare("UPDATE numeracao_sequencial 
                                                SET tipo = 'pedido', pedido_id = :pedido_id, data_conversao = NOW() 
                                                WHERE orcamento_id = :orcamento_id AND tipo = 'orcamento'");
                $stmtNum->bindParam(':pedido_id', $this->id);
                $stmtNum->bindParam(':orcamento_id', $this->orcamento_id);
            } else {
                $stmtNum = $this->conn->prepare("INSERT INTO numeracao_sequencial (numero, tipo, pedido_id, data_atribuicao) 
                                                VALUES (:numero, 'pedido', :pedido_id, NOW())");
                $stmtNum->bindParam(':numero', $this->numero);
                $stmtNum->bindParam(':pedido_id', $this->id);
            }
            $stmtNum->execute();
            return true;
        }
        return false;
    }

    public function update() {
        $query = "UPDATE " . $this->table_name . "
                  SET cliente_id=:cliente_id, orcamento_id=:orcamento_id, data_pedido=:data_pedido, 
                      data_evento=:data_evento, hora_evento=:hora_evento, local_evento=:local_evento, 
                      data_entrega=:data_entrega, data_retirada_prevista=:data_retirada_prevista, 
                      data_retirada_efetiva=:data_retirada_efetiva, tipo=:tipo, status=:status, 
                      valor_total_locacao=:valor_total_locacao, subtotal_locacao=:subtotal_locacao, 
                      valor_total_venda=:valor_total_venda, subtotal_venda=:subtotal_venda, desconto=:desconto, 
                      taxa_domingo_feriado=:taxa_domingo_feriado, taxa_madrugada=:taxa_madrugada, 
                      taxa_horario_especial=:taxa_horario_especial, taxa_hora_marcada=:taxa_hora_marcada, 
                      frete_elevador=:frete_elevador, frete_escadas=:frete_escadas, frete_terreo=:frete_terreo, 
                      valor_final=:valor_final, ajuste_manual=:ajuste_manual, motivo_ajuste=:motivo_ajuste, 
                      valor_sinal=:valor_sinal, data_pagamento_sinal=:data_pagamento_sinal, valor_pago=:valor_pago, 
                      data_pagamento_final=:data_pagamento_final, valor_multas=:valor_multas, 
                      observacoes=:observacoes, condicoes_pagamento=:condicoes_pagamento, usuario_id=:usuario_id
                  WHERE id=:id";
        $stmt = $this->conn->prepare($query);

        // Sanitização
        $this->cliente_id = htmlspecialchars(strip_tags($this->cliente_id));
        $this->orcamento_id = $this->orcamento_id ? htmlspecialchars(strip_tags($this->orcamento_id)) : null;
        $this->data_pedido = htmlspecialchars(strip_tags($this->data_pedido));
        $this->data_evento = htmlspecialchars(strip_tags($this->data_evento));
        $this->hora_evento = $this->hora_evento ? htmlspecialchars(strip_tags($this->hora_evento)) : null;
        $this->local_evento = htmlspecialchars(strip_tags($this->local_evento));
        $this->data_entrega = htmlspecialchars(strip_tags($this->data_entrega));
        $this->data_retirada_prevista = htmlspecialchars(strip_tags($this->data_retirada_prevista));
        $this->data_retirada_efetiva = $this->data_retirada_efetiva ? htmlspecialchars(strip_tags($this->data_retirada_efetiva)) : null;
        $this->tipo = htmlspecialchars(strip_tags($this->tipo));
        $this->status = htmlspecialchars(strip_tags($this->status));
        $this->valor_total_locacao = htmlspecialchars(strip_tags($this->valor_total_locacao));
        $this->subtotal_locacao = htmlspecialchars(strip_tags($this->subtotal_locacao));
        $this->valor_total_venda = htmlspecialchars(strip_tags($this->valor_total_venda));
        $this->subtotal_venda = htmlspecialchars(strip_tags($this->subtotal_venda));
        $this->desconto = htmlspecialchars(strip_tags($this->desconto));
        $this->taxa_domingo_feriado = htmlspecialchars(strip_tags($this->taxa_domingo_feriado));
        $this->taxa_madrugada = htmlspecialchars(strip_tags($this->taxa_madrugada));
        $this->taxa_horario_especial = htmlspecialchars(strip_tags($this->taxa_horario_especial));
        $this->taxa_hora_marcada = htmlspecialchars(strip_tags($this->taxa_hora_marcada));
        $this->frete_elevador = htmlspecialchars(strip_tags($this->frete_elevador));
        $this->frete_escadas = htmlspecialchars(strip_tags($this->frete_escadas));
        $this->frete_terreo = htmlspecialchars(strip_tags($this->frete_terreo));
        $this->valor_final = htmlspecialchars(strip_tags($this->valor_final));
        $this->ajuste_manual = htmlspecialchars(strip_tags($this->ajuste_manual));
        $this->motivo_ajuste = $this->motivo_ajuste ? htmlspecialchars(strip_tags($this->motivo_ajuste)) : null;
        $this->valor_sinal = htmlspecialchars(strip_tags($this->valor_sinal));
        $this->data_pagamento_sinal = $this->data_pagamento_sinal ? htmlspecialchars(strip_tags($this->data_pagamento_sinal)) : null;
        $this->valor_pago = htmlspecialchars(strip_tags($this->valor_pago));
        $this->data_pagamento_final = $this->data_pagamento_final ? htmlspecialchars(strip_tags($this->data_pagamento_final)) : null;
        $this->valor_multas = htmlspecialchars(strip_tags($this->valor_multas));
        $this->observacoes = htmlspecialchars(strip_tags($this->observacoes));
        $this->condicoes_pagamento = htmlspecialchars(strip_tags($this->condicoes_pagamento));
        $this->usuario_id = htmlspecialchars(strip_tags($this->usuario_id));
        $this->id = htmlspecialchars(strip_tags($this->id));

        // Bind dos valores
        $stmt->bindParam(':cliente_id', $this->cliente_id);
        $stmt->bindParam(':orcamento_id', $this->orcamento_id);
        $stmt->bindParam(':data_pedido', $this->data_pedido);
        $stmt->bindParam(':data_evento', $this->data_evento);
        $stmt->bindParam(':hora_evento', $this->hora_evento);
        $stmt->bindParam(':local_evento', $this->local_evento);
        $stmt->bindParam(':data_entrega', $this->data_entrega);
        $stmt->bindParam(':data_retirada_prevista', $this->data_retirada_prevista);
        $stmt->bindParam(':data_retirada_efetiva', $this->data_retirada_efetiva);
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
        $stmt->bindParam(':ajuste_manual', $this->ajuste_manual);
        $stmt->bindParam(':motivo_ajuste', $this->motivo_ajuste);
        $stmt->bindParam(':valor_sinal', $this->valor_sinal);
        $stmt->bindParam(':data_pagamento_sinal', $this->data_pagamento_sinal);
        $stmt->bindParam(':valor_pago', $this->valor_pago);
        $stmt->bindParam(':data_pagamento_final', $this->data_pagamento_final);
        $stmt->bindParam(':valor_multas', $this->valor_multas);
        $stmt->bindParam(':observacoes', $this->observacoes);
        $stmt->bindParam(':condicoes_pagamento', $this->condicoes_pagamento);
        $stmt->bindParam(':usuario_id', $this->usuario_id);
        $stmt->bindParam(':id', $this->id);

        return $stmt->execute();
    }

    public function delete($id) {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $id = htmlspecialchars(strip_tags($id));
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        if ($stmt->execute() && $stmt->rowCount() > 0) {
            // Remove registro da numeração sequencial
            $stmtNum = $this->conn->prepare("DELETE FROM numeracao_sequencial WHERE pedido_id = :pedido_id AND tipo = 'pedido'");
            $stmtNum->bindParam(':pedido_id', $id);
            $stmtNum->execute();
            return true;
        }
        return false;
    }

    public function salvarItens($pedidoId, $itens, $dataEntrega, $dataRetiradaPrevista) {
        if (!empty($itens)) {
            $queryDelete = "DELETE FROM itens_pedido WHERE pedido_id = :pedido_id";
            $stmtDelete = $this->conn->prepare($queryDelete);
            $stmtDelete->bindParam(':pedido_id', $pedidoId);
            $stmtDelete->execute();

            $queryInsert = "INSERT INTO itens_pedido (pedido_id, produto_id, quantidade, quantidade_devolvida, tipo, preco_unitario, desconto, preco_final, ajuste_manual, motivo_ajuste, status, observacoes, data_entrega, data_devolucao_prevista)
                            VALUES (:pedido_id, :produto_id, :quantidade, 0, :tipo, :preco_unitario, :desconto, :preco_final, :ajuste_manual, :motivo_ajuste, 'reservado', :observacoes, :data_entrega, :data_devolucao_prevista)";
            $stmtInsert = $this->conn->prepare($queryInsert);

            foreach ($itens as $item) {
                $stmtInsert->bindParam(':pedido_id', $pedidoId);
                $stmtInsert->bindParam(':produto_id', $item['produto_id']);
                $stmtInsert->bindParam(':quantidade', $item['quantidade']);
                $stmtInsert->bindParam(':tipo', $item['tipo']);
                $stmtInsert->bindParam(':preco_unitario', $item['preco_unitario']);
                $stmtInsert->bindParam(':desconto', $item['desconto']);
                $stmtInsert->bindParam(':preco_final', $item['preco_final']);
                $stmtInsert->bindParam(':ajuste_manual', $item['ajuste_manual']);
                $stmtInsert->bindParam(':motivo_ajuste', $item['motivo_ajuste']);
                $stmtInsert->bindParam(':observacoes', $item['observacoes']);
                $stmtInsert->bindParam(':data_entrega', $dataEntrega);
                $stmtInsert->bindParam(':data_devolucao_prevista', $dataRetiradaPrevista);
                $stmtInsert->execute();
            }
        }
    }

    public function getItens($pedidoId) {
        $query = "SELECT ip.*, p.nome_produto 
                  FROM itens_pedido ip 
                  JOIN produtos p ON ip.produto_id = p.id 
                  WHERE ip.pedido_id = :pedido_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':pedido_id', $pedidoId);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function verificarEstoqueDisponivel($produtoId, $quantidade, $dataEntrega, $dataDevolucao) {
        $query = "SELECT p.quantidade_total - COALESCE(SUM(ip.quantidade), 0) AS disponivel
                  FROM produtos p
                  LEFT JOIN itens_pedido ip ON p.id = ip.produto_id
                  AND (
                      (ip.data_entrega <= :data_devolucao AND ip.data_devolucao_prevista >= :data_entrega)
                      OR (ip.data_entrega <= :data_devolucao AND ip.data_devolucao_efetiva IS NULL)
                  )
                  WHERE p.id = :produto_id
                  GROUP BY p.id, p.quantidade_total";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':produto_id', $produtoId);
        $stmt->bindParam(':data_entrega', $dataEntrega);
        $stmt->bindParam(':data_devolucao', $dataDevolucao);
        $stmt->execute();
        $disponivel = $stmt->fetchColumn();
        return $disponivel >= $quantidade;
    }
}
?>