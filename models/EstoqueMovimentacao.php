<?php
class EstoqueMovimentacao {
    private $conn;
    private $table_name = "movimentacoes_estoque";

    // Propriedades
    public $id;
    public $produto_id;
    public $tipo_movimentacao;
    public $quantidade;
    public $referencia_id;
    public $referencia_tipo;
    public $observacoes;
    public $usuario_id;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Alias mantido por compatibilidade.
     */
    public function verificarDisponibilidade(
        $produto_id,
        $data_inicio,
        $data_fim,
        $quantidade_solicitada = 0,
        $hora_inicio = null,
        $turno_inicio = null,
        $hora_fim = null,
        $turno_fim = null,
        $ignorar_pedido_id = null
    ) {
        return $this->consultarDisponibilidadePeriodo(
            $produto_id,
            $data_inicio,
            $hora_inicio,
            $turno_inicio,
            $data_fim,
            $hora_fim,
            $turno_fim,
            $quantidade_solicitada,
            $ignorar_pedido_id
        );
    }

    public function verificarEstoqueSimples($produto_id, $quantidade_solicitada) {
        try {
            $query = "SELECT quantidade_total FROM produtos WHERE id = :produto_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':produto_id', $produto_id);
            $stmt->execute();

            $produto = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$produto) {
                return false;
            }

            return (int) $produto['quantidade_total'] >= (int) $quantidade_solicitada;
        } catch (Exception $e) {
            error_log("Erro em EstoqueMovimentacao::verificarEstoqueSimples: " . $e->getMessage());
            return false;
        }
    }

    public function obterEstoqueTotal($produto_id) {
        try {
            $query = "SELECT quantidade_total FROM produtos WHERE id = :produto_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':produto_id', $produto_id);
            $stmt->execute();

            $produto = $stmt->fetch(PDO::FETCH_ASSOC);
            return $produto ? (int) $produto['quantidade_total'] : 0;
        } catch (Exception $e) {
            error_log("Erro em EstoqueMovimentacao::obterEstoqueTotal: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Consulta disponibilidade temporal em tempo real (Rota A),
     * usando produtos + pedidos + itens_pedido, sem depender da tabela estoque_temporal.
     */
    public function consultarDisponibilidadePeriodo(
        int $produtoId,
        ?string $dataInicio,
        ?string $horaInicio = null,
        ?string $turnoInicio = null,
        ?string $dataFim = null,
        ?string $horaFim = null,
        ?string $turnoFim = null,
        int $quantidadeSolicitada = 0,
        ?int $ignorarPedidoId = null
    ): array {
        $produto = $this->obterProduto($produtoId);

        $resultadoBase = [
            'success' => true,
            'produto_id' => $produtoId,
            'produto_nome' => $produto['nome_produto'] ?? '',
            'estoque_total' => isset($produto['quantidade_total']) ? (int) $produto['quantidade_total'] : 0,
            'estoque_disponivel' => isset($produto['quantidade_total']) ? (int) $produto['quantidade_total'] : 0,
            'comprometido_periodo' => 0,
            'livre_periodo' => isset($produto['quantidade_total']) ? (int) $produto['quantidade_total'] : 0,
            'quantidade_solicitada' => max(0, (int) $quantidadeSolicitada),
            'reservado_orcamento_atual' => max(0, (int) $quantidadeSolicitada),
            'livre_apos_orcamento' => isset($produto['quantidade_total']) ? ((int) $produto['quantidade_total'] - max(0, (int) $quantidadeSolicitada)) : 0,
            'faltante_orcamento' => 0,
            'disponivel' => false,
            'consulta_periodo_valida' => false,
            'nivel_alerta' => 'ok',
            'alertas' => [],
            'conflitos' => [],
            'agenda' => [],
            'ultimo_retorno' => null,
            'proxima_saida' => null,
            'observacoes_produto' => $produto['observacoes'] ?? null,
        ];

        if (!$produto) {
            return array_merge($resultadoBase, [
                'success' => false,
                'nivel_alerta' => 'indisponivel',
                'alertas' => ['Produto não encontrado.'],
                'disponivel' => false,
            ]);
        }

        if ((int) ($produto['disponivel_locacao'] ?? 1) !== 1) {
            $resultadoBase['alertas'][] = 'Produto marcado como indisponível para locação.';
            $resultadoBase['nivel_alerta'] = 'indisponivel';
        }


        // Se ainda não há período informado, devolve estoque simples + alerta orientativo.
        if (empty($dataInicio) || empty($dataFim)) {
            $resultadoBase['disponivel'] = $resultadoBase['estoque_total'] >= $resultadoBase['quantidade_solicitada']
                && $resultadoBase['nivel_alerta'] !== 'indisponivel';
            $resultadoBase['estoque_disponivel'] = $resultadoBase['estoque_total'];
            $resultadoBase['livre_periodo'] = $resultadoBase['estoque_total'];
            $resultadoBase['livre_apos_orcamento'] = $resultadoBase['estoque_total'] - $resultadoBase['quantidade_solicitada'];
            $resultadoBase['faltante_orcamento'] = max(0, $resultadoBase['quantidade_solicitada'] - $resultadoBase['estoque_total']);
            if (!empty($produto['observacoes'])) {
                $resultadoBase['observacoes_produto'] = trim($produto['observacoes']);
            }
            return $resultadoBase;
        }

        $inicioConsulta = $this->resolverDataHora($dataInicio, $horaInicio, $turnoInicio, 'inicio');
        $fimConsulta = $this->resolverDataHora($dataFim, $horaFim, $turnoFim, 'fim');

        if (!$inicioConsulta || !$fimConsulta) {
            $resultadoBase['alertas'][] = 'Não foi possível interpretar corretamente as datas/horários da consulta.';
            $resultadoBase['nivel_alerta'] = 'indisponivel';
            return $resultadoBase;
        }

        if ($inicioConsulta > $fimConsulta) {
            $resultadoBase['alertas'][] = 'A data/hora de devolução deve ser posterior à data/hora de entrega.';
            $resultadoBase['nivel_alerta'] = 'indisponivel';
            return $resultadoBase;
        }

        $resultadoBase['consulta_periodo_valida'] = true;

        $janelaAgendaInicio = (clone $inicioConsulta)->modify('-15 days');
        $janelaAgendaFim = (clone $fimConsulta)->modify('+15 days');

        $agenda = $this->obterAgendaProduto(
            $produtoId,
            $janelaAgendaInicio->format('Y-m-d H:i:s'),
            $janelaAgendaFim->format('Y-m-d H:i:s'),
            $ignorarPedidoId
        );

        $comprometido = 0;
        $conflitos = [];
        $ultimoRetorno = null;
        $proximaSaida = null;

        foreach ($agenda as $itemAgenda) {
            if (empty($itemAgenda['inicio_dt']) || empty($itemAgenda['fim_dt'])) {
                continue;
            }

            $inicioAgenda = new DateTime($itemAgenda['inicio_dt']);
            $fimAgenda = new DateTime($itemAgenda['fim_dt']);

            if ($this->intervalosSeSobrepoem($inicioConsulta, $fimConsulta, $inicioAgenda, $fimAgenda)) {
                $comprometido += (int) $itemAgenda['quantidade'];
                $conflitos[] = $itemAgenda;
            }

            if ($fimAgenda <= $inicioConsulta) {
                if ($ultimoRetorno === null || new DateTime($ultimoRetorno['fim_dt']) < $fimAgenda) {
                    $ultimoRetorno = $itemAgenda;
                }
            }

            if ($inicioAgenda >= $fimConsulta) {
                if ($proximaSaida === null || new DateTime($proximaSaida['inicio_dt']) > $inicioAgenda) {
                    $proximaSaida = $itemAgenda;
                }
            }
        }

        $estoqueTotal = (int) $resultadoBase['estoque_total'];
        $livre = $estoqueTotal - $comprometido;
        $livreAposOrcamento = $livre - $resultadoBase['quantidade_solicitada'];
        $disponivel = $livreAposOrcamento >= 0 && $resultadoBase['nivel_alerta'] !== 'indisponivel';

        $resultadoBase['comprometido_periodo'] = $comprometido;
        $resultadoBase['livre_periodo'] = $livre;
        $resultadoBase['livre_apos_orcamento'] = $livreAposOrcamento;
        $resultadoBase['faltante_orcamento'] = max(0, 0 - $livreAposOrcamento);
        $resultadoBase['estoque_disponivel'] = $livre;
        $resultadoBase['disponivel'] = $disponivel;
        $resultadoBase['conflitos'] = $conflitos;
        $resultadoBase['agenda'] = $agenda;
        $resultadoBase['ultimo_retorno'] = $ultimoRetorno;
        $resultadoBase['proxima_saida'] = $proximaSaida;

        if (!$disponivel) {
            $resultadoBase['nivel_alerta'] = 'indisponivel';
            $resultadoBase['alertas'][] = 'Quantidade insuficiente para o período consultado.';
        } elseif ($comprometido > 0) {
            $resultadoBase['nivel_alerta'] = 'atencao';
        }

        if ($ultimoRetorno) {
            $fimUltimo = new DateTime($ultimoRetorno['fim_dt']);
            $intervaloHoras = ($inicioConsulta->getTimestamp() - $fimUltimo->getTimestamp()) / 3600;
            if ($intervaloHoras >= 0 && $intervaloHoras <= 12) {
                $resultadoBase['alertas'][] = 'Atenção: há reuso apertado próximo ao início deste período.';
                if ($resultadoBase['nivel_alerta'] === 'ok') {
                    $resultadoBase['nivel_alerta'] = 'atencao';
                }
            }
        }

        if ($proximaSaida) {
            $inicioProximo = new DateTime($proximaSaida['inicio_dt']);
            $intervaloHoras = ($inicioProximo->getTimestamp() - $fimConsulta->getTimestamp()) / 3600;
            if ($intervaloHoras >= 0 && $intervaloHoras <= 24) {
                $resultadoBase['alertas'][] = 'Atenção: há nova saída próxima após este período.';
                if ($resultadoBase['nivel_alerta'] === 'ok') {
                    $resultadoBase['nivel_alerta'] = 'atencao';
                }
            }
        }

        if (!empty($produto['observacoes'])) {
            $resultadoBase['observacoes_produto'] = trim($produto['observacoes']);
        }

        return $resultadoBase;
    }

    /**
     * Retorna a agenda do produto dentro de uma janela.
     */
    public function obterAgendaProduto(
        int $produtoId,
        string $dataJanelaInicio,
        string $dataJanelaFim,
        ?int $ignorarPedidoId = null
    ): array {
        try {
            $inicioJanela = $this->normalizarDateTimeLivre($dataJanelaInicio, 'inicio');
            $fimJanela = $this->normalizarDateTimeLivre($dataJanelaFim, 'fim');

            if (!$inicioJanela || !$fimJanela) {
                return [];
            }

            $query = "
                SELECT
                    ip.id AS item_pedido_id,
                    ip.pedido_id,
                    ip.quantidade,
                    ip.observacoes AS observacoes_item,
                    p.numero,
                    p.codigo,
                    p.cliente_id,
                    p.data_entrega,
                    p.hora_entrega,
                    p.turno_entrega,
                    p.data_devolucao_prevista,
                    p.hora_devolucao,
                    p.turno_devolucao,
                    p.situacao_pedido,
                    c.nome AS nome_cliente
                FROM itens_pedido ip
                INNER JOIN pedidos p ON p.id = ip.pedido_id
                INNER JOIN clientes c ON c.id = p.cliente_id
                WHERE
                    ip.produto_id = :produto_id
                    AND ip.tipo_linha = 'PRODUTO'
                    AND ip.tipo = 'locacao'
                    AND p.situacao_pedido = 'confirmado'
                    AND p.data_entrega IS NOT NULL
                    AND p.data_devolucao_prevista IS NOT NULL
                    AND p.data_entrega <= :fim_data
                    AND p.data_devolucao_prevista >= :inicio_data
            ";

            if (!empty($ignorarPedidoId)) {
                $query .= " AND p.id <> :ignorar_pedido_id";
            }

            $query .= " ORDER BY p.data_entrega ASC, p.hora_entrega ASC, p.id ASC";

            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(':produto_id', $produtoId, PDO::PARAM_INT);
            $stmt->bindValue(':inicio_data', $inicioJanela->format('Y-m-d'));
            $stmt->bindValue(':fim_data', $fimJanela->format('Y-m-d'));

            if (!empty($ignorarPedidoId)) {
                $stmt->bindValue(':ignorar_pedido_id', $ignorarPedidoId, PDO::PARAM_INT);
            }

            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $agenda = [];
            foreach ($rows as $row) {
                $inicio = $this->resolverDataHora(
                    $row['data_entrega'] ?? null,
                    $row['hora_entrega'] ?? null,
                    $row['turno_entrega'] ?? null,
                    'inicio'
                );
                $fim = $this->resolverDataHora(
                    $row['data_devolucao_prevista'] ?? null,
                    $row['hora_devolucao'] ?? null,
                    $row['turno_devolucao'] ?? null,
                    'fim'
                );

                if (!$inicio || !$fim) {
                    continue;
                }

                if (!$this->intervalosSeSobrepoem($inicioJanela, $fimJanela, $inicio, $fim)) {
                    continue;
                }

                $agenda[] = [
                    'item_pedido_id' => (int) $row['item_pedido_id'],
                    'pedido_id' => (int) $row['pedido_id'],
                    'pedido_numero' => (int) $row['numero'],
                    'pedido_codigo' => $row['codigo'],
                    'cliente_id' => (int) $row['cliente_id'],
                    'cliente' => $row['nome_cliente'],
                    'quantidade' => (int) $row['quantidade'],
                    'situacao_pedido' => $row['situacao_pedido'],
                    'observacoes_item' => $row['observacoes_item'],
                    'inicio_dt' => $inicio->format('Y-m-d H:i:s'),
                    'fim_dt' => $fim->format('Y-m-d H:i:s'),
                    'inicio_formatado' => $this->formatarDataHoraBr($inicio),
                    'fim_formatado' => $this->formatarDataHoraBr($fim),
                ];
            }

            usort($agenda, function ($a, $b) {
                return strcmp($a['inicio_dt'], $b['inicio_dt']);
            });

            return $agenda;
        } catch (Exception $e) {
            error_log("Erro em EstoqueMovimentacao::obterAgendaProduto: " . $e->getMessage());
            return [];
        }
    }

    private function obterProduto(int $produtoId): ?array {
        try {
            $query = "
                SELECT
                    id,
                    nome_produto,
                    quantidade_total,
                    observacoes,
                    disponivel_locacao
                FROM produtos
                WHERE id = :produto_id
                LIMIT 1
            ";
            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(':produto_id', $produtoId, PDO::PARAM_INT);
            $stmt->execute();

            $produto = $stmt->fetch(PDO::FETCH_ASSOC);
            return $produto ?: null;
        } catch (Exception $e) {
            error_log("Erro em EstoqueMovimentacao::obterProduto: " . $e->getMessage());
            return null;
        }
    }

    private function intervalosSeSobrepoem(DateTime $inicioA, DateTime $fimA, DateTime $inicioB, DateTime $fimB): bool {
        return $inicioA < $fimB && $fimA > $inicioB;
    }

    private function normalizarData(?string $data): ?string {
        if (empty($data)) {
            return null;
        }

        $data = trim($data);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
            return $data;
        }

        $dt = DateTime::createFromFormat('d/m/Y', $data);
        if ($dt instanceof DateTime) {
            return $dt->format('Y-m-d');
        }

        return null;
    }

    private function normalizarHora(?string $hora): ?string {
        if (empty($hora)) {
            return null;
        }

        $hora = trim($hora);

        if (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $hora)) {
            return strlen($hora) === 5 ? $hora . ':00' : $hora;
        }

        return null;
    }

    private function resolverDataHora(?string $data, ?string $hora, ?string $turno, string $tipo): ?DateTime {
        $dataNormalizada = $this->normalizarData($data);
        if (!$dataNormalizada) {
            return null;
        }

        $horaNormalizada = $this->normalizarHora($hora);
        if (!$horaNormalizada) {
            $horaNormalizada = $this->obterHoraPadraoPorTurno($turno, $tipo);
        }

        return DateTime::createFromFormat('Y-m-d H:i:s', $dataNormalizada . ' ' . $horaNormalizada) ?: null;
    }

    private function normalizarDateTimeLivre(?string $valor, string $tipo): ?DateTime {
        if (empty($valor)) {
            return null;
        }

        $valor = trim($valor);

        if (preg_match('/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}$/', $valor)) {
            return DateTime::createFromFormat('Y-m-d H:i:s', $valor) ?: null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
            return $this->resolverDataHora($valor, null, null, $tipo);
        }

        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $valor)) {
            return $this->resolverDataHora($valor, null, null, $tipo);
        }

        return null;
    }

    private function obterHoraPadraoPorTurno(?string $turno, string $tipo): string {
        $turno = trim((string) $turno);

        if ($tipo === 'inicio') {
            switch ($turno) {
                case 'Manhã (Horário Comercial)':
                    return '08:00:00';
                case 'Tarde (Horário Comercial)':
                    return '13:00:00';
                case 'Noite (A Combinar)':
                    return '18:00:00';
                case 'Horário Específico':
                    return '08:00:00';
                case 'Manhã/Tarde (Horário Comercial)':
                default:
                    return '08:00:00';
            }
        }

        switch ($turno) {
            case 'Manhã (Horário Comercial)':
                return '12:00:00';
            case 'Tarde (Horário Comercial)':
                return '18:00:00';
            case 'Noite (A Combinar)':
                return '22:00:00';
            case 'Horário Específico':
                return '18:00:00';
            case 'Manhã/Tarde (Horário Comercial)':
            default:
                return '18:00:00';
        }
    }

    private function formatarDataHoraBr(DateTime $dataHora): string {
        return $dataHora->format('d/m/Y H:i');
    }
}
?>