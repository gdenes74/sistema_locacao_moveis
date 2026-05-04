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

    /**
     * Mantido por compatibilidade com telas antigas.
     * Agora considera também produto COMPOSTO e produto sem controle de estoque.
     */
    public function verificarEstoqueSimples($produto_id, $quantidade_solicitada) {
        try {
            $produto = $this->obterProduto((int) $produto_id);
            if (!$produto) {
                return false;
            }

            if ($this->produtoSemControleEstoque($produto)) {
                return true;
            }

            $estoqueTotal = $this->obterEstoqueTotal((int) $produto_id);
            return $estoqueTotal >= max(0, (int) $quantidade_solicitada);
        } catch (Exception $e) {
            error_log("Erro em EstoqueMovimentacao::verificarEstoqueSimples: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Mantido por compatibilidade com telas antigas.
     * Para produto composto, retorna o menor limite possível considerando:
     * - estoque próprio do produto pai, quando controla_estoque = 1;
     * - componentes obrigatórios em produto_composicao;
     * - componentes/serviços sem estoque não limitam.
     */
    public function obterEstoqueTotal($produto_id) {
        try {
            $produto = $this->obterProduto((int) $produto_id);
            if (!$produto) {
                return 0;
            }

            if ($this->produtoSemControleEstoque($produto) && !$this->produtoEhComposto($produto)) {
                return 999999;
            }

            if ($this->produtoEhComposto($produto)) {
                return $this->obterEstoqueTotalComposto($produto);
            }

            return (int) ($produto['quantidade_total'] ?? 0);
        } catch (Exception $e) {
            error_log("Erro em EstoqueMovimentacao::obterEstoqueTotal: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Consulta disponibilidade temporal em tempo real (Rota A),
     * usando produtos + pedidos + itens_pedido, sem depender da tabela estoque_temporal.
     *
     * Evolução: suporta produto COMPOSTO, preservando o retorno antigo para o front.
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
        ?int $ignorarPedidoId = null,
        array $itensContextoAtual = []
    ): array {
        return $this->consultarDisponibilidadeInterna(
            $produtoId,
            $dataInicio,
            $horaInicio,
            $turnoInicio,
            $dataFim,
            $horaFim,
            $turnoFim,
            $quantidadeSolicitada,
            $ignorarPedidoId,
            [],
            $itensContextoAtual
        );
    }

    private function consultarDisponibilidadeInterna(
        int $produtoId,
        ?string $dataInicio,
        ?string $horaInicio = null,
        ?string $turnoInicio = null,
        ?string $dataFim = null,
        ?string $horaFim = null,
        ?string $turnoFim = null,
        int $quantidadeSolicitada = 0,
        ?int $ignorarPedidoId = null,
        array $pilhaProdutos = [],
        array $itensContextoAtual = []
    ): array {
        $produto = $this->obterProduto($produtoId);

        if (!$produto) {
            return $this->montarResultadoBase(null, $produtoId, $quantidadeSolicitada, [
                'success' => false,
                'nivel_alerta' => 'indisponivel',
                'alertas' => ['Produto não encontrado.'],
                'disponivel' => false,
            ]);
        }

        if (in_array($produtoId, $pilhaProdutos, true)) {
            return $this->montarResultadoBase($produto, $produtoId, $quantidadeSolicitada, [
                'success' => false,
                'nivel_alerta' => 'indisponivel',
                'alertas' => ['Composição circular detectada neste produto.'],
                'disponivel' => false,
            ]);
        }

        $pilhaProdutos[] = $produtoId;

        if ($this->produtoEhComposto($produto)) {
            return $this->consultarDisponibilidadeProdutoComposto(
                $produto,
                $dataInicio,
                $horaInicio,
                $turnoInicio,
                $dataFim,
                $horaFim,
                $turnoFim,
                $quantidadeSolicitada,
                $ignorarPedidoId,
                $pilhaProdutos,
                $itensContextoAtual
            );
        }

        return $this->consultarDisponibilidadeProdutoIndividual(
            $produto,
            $dataInicio,
            $horaInicio,
            $turnoInicio,
            $dataFim,
            $horaFim,
            $turnoFim,
            $quantidadeSolicitada,
            $ignorarPedidoId
        );
    }

    private function consultarDisponibilidadeProdutoIndividual(
        array $produto,
        ?string $dataInicio,
        ?string $horaInicio = null,
        ?string $turnoInicio = null,
        ?string $dataFim = null,
        ?string $horaFim = null,
        ?string $turnoFim = null,
        int $quantidadeSolicitada = 0,
        ?int $ignorarPedidoId = null
    ): array {
        $produtoId = (int) $produto['id'];
        $resultadoBase = $this->montarResultadoBase($produto, $produtoId, $quantidadeSolicitada);

        if ((int) ($produto['disponivel_locacao'] ?? 1) !== 1) {
            $resultadoBase['alertas'][] = 'Produto marcado como indisponível para locação.';
            $resultadoBase['nivel_alerta'] = 'indisponivel';
        }

        if ($this->produtoSemControleEstoque($produto)) {
            $resultadoBase['estoque_total'] = 999999;
            $resultadoBase['estoque_disponivel'] = 999999;
            $resultadoBase['livre_periodo'] = 999999;
            $resultadoBase['livre_apos_orcamento'] = 999999;
            $resultadoBase['faltante_orcamento'] = 0;
            $resultadoBase['disponivel'] = $resultadoBase['nivel_alerta'] !== 'indisponivel';
            $resultadoBase['alertas'][] = 'Produto/serviço sem controle de estoque.';
            return $resultadoBase;
        }

        // Se ainda não há período informado, devolve estoque simples + alerta orientativo.
        if (empty($dataInicio) || empty($dataFim)) {
            $resultadoBase['disponivel'] = $resultadoBase['estoque_total'] >= $resultadoBase['quantidade_solicitada']
                && $resultadoBase['nivel_alerta'] !== 'indisponivel';
            $resultadoBase['estoque_disponivel'] = $resultadoBase['estoque_total'];
            $resultadoBase['livre_periodo'] = $resultadoBase['estoque_total'];
            $resultadoBase['livre_apos_orcamento'] = $resultadoBase['estoque_total'] - $resultadoBase['quantidade_solicitada'];
            $resultadoBase['faltante_orcamento'] = max(0, $resultadoBase['quantidade_solicitada'] - $resultadoBase['estoque_total']);
            if (!$resultadoBase['disponivel'] && $resultadoBase['nivel_alerta'] !== 'indisponivel') {
                $resultadoBase['nivel_alerta'] = 'indisponivel';
                $resultadoBase['alertas'][] = 'Quantidade maior que o estoque total do produto.';
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
                $comprometido += (float) $itemAgenda['quantidade'];
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

        $resultadoBase['comprometido_periodo'] = $this->normalizarNumeroEstoque($comprometido);
        $resultadoBase['livre_periodo'] = $this->normalizarNumeroEstoque($livre);
        $resultadoBase['livre_apos_orcamento'] = $this->normalizarNumeroEstoque($livreAposOrcamento);
        $resultadoBase['faltante_orcamento'] = $this->normalizarNumeroEstoque(max(0, 0 - $livreAposOrcamento));
        $resultadoBase['estoque_disponivel'] = $this->normalizarNumeroEstoque($livre);
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

        return $resultadoBase;
    }

    private function consultarDisponibilidadeProdutoComposto(
        array $produto,
        ?string $dataInicio,
        ?string $horaInicio = null,
        ?string $turnoInicio = null,
        ?string $dataFim = null,
        ?string $horaFim = null,
        ?string $turnoFim = null,
        int $quantidadeSolicitada = 0,
        ?int $ignorarPedidoId = null,
        array $pilhaProdutos = [],
        array $itensContextoAtual = []
    ): array {
        $produtoId = (int) $produto['id'];
        $resultadoBase = $this->montarResultadoBase($produto, $produtoId, $quantidadeSolicitada);
        $resultadoBase['produto_composto'] = true;
        $resultadoBase['componentes'] = [];

        if ((int) ($produto['disponivel_locacao'] ?? 1) !== 1) {
            $resultadoBase['alertas'][] = 'Produto composto marcado como indisponível para locação.';
            $resultadoBase['nivel_alerta'] = 'indisponivel';
        }

        $limitadores = [];

        // O produto pai também pode ser limitador físico.
        // Ex.: sofá de madeira com braços tem 4 estruturas próprias e ainda pode consumir capas/colchões.
        if (!$this->produtoSemControleEstoque($produto)) {
            $limitadores[] = [
                'tipo_limitador' => 'PRODUTO_PAI',
                'produto' => $produto,
                'quantidade_por_unidade' => 1.0,
                'obrigatorio' => 1,
                'observacoes_composicao' => 'Estoque próprio do produto principal.',
            ];
        }

        $componentes = $this->obterComponentesProdutoComposto($produtoId);
        foreach ($componentes as $componente) {
            $produtoFilho = $this->obterProduto((int) $componente['produto_filho_id']);
            if (!$produtoFilho) {
                $resultadoBase['alertas'][] = 'Componente não encontrado na composição do produto.';
                $resultadoBase['nivel_alerta'] = 'indisponivel';
                continue;
            }

            $limitadores[] = [
                'tipo_limitador' => 'COMPONENTE',
                'produto' => $produtoFilho,
                'quantidade_por_unidade' => max(0.0001, (float) $componente['quantidade']),
                'obrigatorio' => (int) ($componente['obrigatorio'] ?? 1),
                'observacoes_composicao' => $componente['observacoes'] ?? null,
            ];
        }

        if (empty($limitadores)) {
            return array_merge($resultadoBase, [
                'disponivel' => false,
                'nivel_alerta' => 'indisponivel',
                'alertas' => array_merge($resultadoBase['alertas'], [
                    'Produto composto sem estoque próprio e sem componentes cadastrados.'
                ]),
                'estoque_total' => 0,
                'estoque_disponivel' => 0,
                'livre_periodo' => 0,
                'livre_apos_orcamento' => 0 - $resultadoBase['quantidade_solicitada'],
                'faltante_orcamento' => $resultadoBase['quantidade_solicitada'],
            ]);
        }

        $menorEstoqueTotalPorComposto = null;
        $menorLivrePorComposto = null;
        $menorLivreAposPedidoPorComposto = null;
        $comprometidoConsolidado = 0;
        $conflitosConsolidados = [];
        $agendaConsolidada = [];
        $ultimoRetorno = null;
        $proximaSaida = null;
        $componentesResumo = [];
        $maiorFaltantePorComponente = 0;
        $disponivel = $resultadoBase['nivel_alerta'] !== 'indisponivel';

        foreach ($limitadores as $limitador) {
            $produtoLimitador = $limitador['produto'];
            $produtoLimitadorId = (int) $produtoLimitador['id'];
            $qtdPorUnidade = (float) $limitador['quantidade_por_unidade'];
            $qtdNecessaria = (int) ceil(max(0, $quantidadeSolicitada) * $qtdPorUnidade);
            $obrigatorio = (int) $limitador['obrigatorio'] === 1;

            // Quando o front envia o contexto inteiro da tela, este limitador passa a considerar
            // todos os produtos do orçamento/pedido atual que consomem o mesmo componente.
            // Ex.: Pufe Forrado Azul + Pufe Forrado Verde consomem a mesma Pufe Estrutura.
            $qtdNecessariaContexto = $this->calcularConsumoLimitadorNoContexto(
                $produtoLimitadorId,
                $itensContextoAtual
            );

            if ($qtdNecessariaContexto > 0) {
                $qtdNecessaria = (int) ceil($qtdNecessariaContexto);
            }

            if ($this->produtoSemControleEstoque($produtoLimitador)) {
                $resumoSemEstoque = [
                    'tipo_limitador' => $limitador['tipo_limitador'],
                    'produto_id' => $produtoLimitadorId,
                    'produto_nome' => $produtoLimitador['nome_produto'] ?? '',
                    'tipo_produto' => $produtoLimitador['tipo_produto'] ?? 'SIMPLES',
                    'controla_estoque' => false,
                    'quantidade_por_unidade' => $qtdPorUnidade,
                    'quantidade_necessaria' => $qtdNecessaria,
                    'obrigatorio' => $obrigatorio,
                    'estoque_total' => 999999,
                    'comprometido_periodo' => 0,
                    'livre_periodo' => 999999,
                    'estoque_disponivel' => 999999,
                    'livre_apos_orcamento' => 999999,
                    'faltante_orcamento' => 0,
                    'disponivel' => true,
                    'nivel_alerta' => 'ok',
                    'alertas' => ['Componente/serviço sem controle de estoque.'],
                    'conflitos' => [],
                    'observacoes_composicao' => $limitador['observacoes_composicao'],
                ];
                $componentesResumo[] = $resumoSemEstoque;
                continue;
            }

            if ($this->produtoEhComposto($produtoLimitador) && $limitador['tipo_limitador'] !== 'PRODUTO_PAI') {
                $resultadoLimitador = $this->consultarDisponibilidadeInterna(
                    $produtoLimitadorId,
                    $dataInicio,
                    $horaInicio,
                    $turnoInicio,
                    $dataFim,
                    $horaFim,
                    $turnoFim,
                    $qtdNecessaria,
                    $ignorarPedidoId,
                    $pilhaProdutos,
                    $itensContextoAtual
                );
            } else {
                $resultadoLimitador = $this->consultarDisponibilidadeProdutoIndividual(
                    $produtoLimitador,
                    $dataInicio,
                    $horaInicio,
                    $turnoInicio,
                    $dataFim,
                    $horaFim,
                    $turnoFim,
                    $qtdNecessaria,
                    $ignorarPedidoId
                );
            }

            $estoqueTotalComposto = (int) floor(((float) ($resultadoLimitador['estoque_total'] ?? 0)) / $qtdPorUnidade);
            $livreComposto = (int) floor(((float) ($resultadoLimitador['livre_periodo'] ?? 0)) / $qtdPorUnidade);
            $livreAposComposto = (int) floor(((float) ($resultadoLimitador['livre_apos_orcamento'] ?? 0)) / $qtdPorUnidade);

            $menorEstoqueTotalPorComposto = $menorEstoqueTotalPorComposto === null
                ? $estoqueTotalComposto
                : min($menorEstoqueTotalPorComposto, $estoqueTotalComposto);

            $menorLivrePorComposto = $menorLivrePorComposto === null
                ? $livreComposto
                : min($menorLivrePorComposto, $livreComposto);

            $menorLivreAposPedidoPorComposto = $menorLivreAposPedidoPorComposto === null
                ? $livreAposComposto
                : min($menorLivreAposPedidoPorComposto, $livreAposComposto);

            $comprometidoConsolidado += (float) ($resultadoLimitador['comprometido_periodo'] ?? 0);

            foreach (($resultadoLimitador['conflitos'] ?? []) as $conflito) {
                $conflito['componente_id'] = $produtoLimitadorId;
                $conflito['componente_nome'] = $produtoLimitador['nome_produto'] ?? '';
                $conflitosConsolidados[] = $conflito;
            }

            foreach (($resultadoLimitador['agenda'] ?? []) as $agendaItem) {
                $agendaItem['componente_id'] = $produtoLimitadorId;
                $agendaItem['componente_nome'] = $produtoLimitador['nome_produto'] ?? '';
                $agendaConsolidada[] = $agendaItem;
            }

            if (!empty($resultadoLimitador['ultimo_retorno'])) {
                if ($ultimoRetorno === null || new DateTime($ultimoRetorno['fim_dt']) < new DateTime($resultadoLimitador['ultimo_retorno']['fim_dt'])) {
                    $ultimoRetorno = $resultadoLimitador['ultimo_retorno'];
                    $ultimoRetorno['componente_id'] = $produtoLimitadorId;
                    $ultimoRetorno['componente_nome'] = $produtoLimitador['nome_produto'] ?? '';
                }
            }

            if (!empty($resultadoLimitador['proxima_saida'])) {
                if ($proximaSaida === null || new DateTime($proximaSaida['inicio_dt']) > new DateTime($resultadoLimitador['proxima_saida']['inicio_dt'])) {
                    $proximaSaida = $resultadoLimitador['proxima_saida'];
                    $proximaSaida['componente_id'] = $produtoLimitadorId;
                    $proximaSaida['componente_nome'] = $produtoLimitador['nome_produto'] ?? '';
                }
            }

            $faltanteComponente = (float) ($resultadoLimitador['faltante_orcamento'] ?? 0);
            if ($qtdPorUnidade > 0) {
                $maiorFaltantePorComponente = max($maiorFaltantePorComponente, (int) ceil($faltanteComponente / $qtdPorUnidade));
            }

            $componenteDisponivel = (bool) ($resultadoLimitador['disponivel'] ?? false);
            if ($obrigatorio && !$componenteDisponivel) {
                $disponivel = false;
                $resultadoBase['alertas'][] = 'Componente indisponível: ' . ($produtoLimitador['nome_produto'] ?? 'componente');
            }

            $componentesResumo[] = [
                'tipo_limitador' => $limitador['tipo_limitador'],
                'produto_id' => $produtoLimitadorId,
                'produto_nome' => $produtoLimitador['nome_produto'] ?? '',
                'tipo_produto' => $produtoLimitador['tipo_produto'] ?? 'SIMPLES',
                'controla_estoque' => true,
                'quantidade_por_unidade' => $qtdPorUnidade,
                'quantidade_necessaria' => $qtdNecessaria,
                'reservado_orcamento_atual' => $resultadoLimitador['reservado_orcamento_atual'] ?? $qtdNecessaria,
                'obrigatorio' => $obrigatorio,
                'estoque_total' => $resultadoLimitador['estoque_total'] ?? 0,
                'comprometido_periodo' => $resultadoLimitador['comprometido_periodo'] ?? 0,
                'livre_periodo' => $resultadoLimitador['livre_periodo'] ?? 0,
                'estoque_disponivel' => $resultadoLimitador['estoque_disponivel'] ?? 0,
                'livre_apos_orcamento' => $resultadoLimitador['livre_apos_orcamento'] ?? 0,
                'faltante_orcamento' => $resultadoLimitador['faltante_orcamento'] ?? 0,
                'disponivel' => $componenteDisponivel,
                'nivel_alerta' => $resultadoLimitador['nivel_alerta'] ?? 'ok',
                'alertas' => $resultadoLimitador['alertas'] ?? [],
                'conflitos' => $resultadoLimitador['conflitos'] ?? [],
                'observacoes_composicao' => $limitador['observacoes_composicao'],
            ];
        }

        $estoqueTotalConsolidado = $menorEstoqueTotalPorComposto ?? 0;
        $livreConsolidado = $menorLivrePorComposto ?? 0;
        $livreAposConsolidado = $menorLivreAposPedidoPorComposto ?? ($livreConsolidado - $resultadoBase['quantidade_solicitada']);
        $faltante = max(
            0,
            $resultadoBase['quantidade_solicitada'] - $livreConsolidado,
            $maiorFaltantePorComponente
        );

        if ($resultadoBase['quantidade_solicitada'] > $livreConsolidado) {
            $disponivel = false;
        }

        // Para produto composto, o número principal não pode somar estrutura + capa.
        // Ex.: Pufe Amarelo não deve mostrar 60 estruturas + 15 capas = 75.
        // O painel principal deve refletir o gargalo consolidado do produto final.
        $comprometidoConsolidado = max(0, $estoqueTotalConsolidado - $livreConsolidado);

        // Evita listar o mesmo item de pedido duas vezes quando ele aparece pelo componente comum
        // e também pela capa/cor específica do produto composto consultado.
        $conflitosConsolidados = $this->deduplicarRegistrosPorItemPedido($conflitosConsolidados);
        $agendaConsolidada = $this->deduplicarRegistrosPorItemPedido($agendaConsolidada);

        $resultadoBase['estoque_total'] = $estoqueTotalConsolidado;
        $resultadoBase['estoque_disponivel'] = $livreConsolidado;
        $resultadoBase['livre_periodo'] = $livreConsolidado;
        $resultadoBase['livre_apos_orcamento'] = $livreAposConsolidado;
        $resultadoBase['faltante_orcamento'] = $faltante;
        $resultadoBase['comprometido_periodo'] = $this->normalizarNumeroEstoque($comprometidoConsolidado);
        $resultadoBase['disponivel'] = $disponivel;
        $resultadoBase['componentes'] = $componentesResumo;
        $resultadoBase['conflitos'] = $conflitosConsolidados;
        $resultadoBase['agenda'] = $agendaConsolidada;
        $resultadoBase['ultimo_retorno'] = $ultimoRetorno;
        $resultadoBase['proxima_saida'] = $proximaSaida;
        $resultadoBase['consulta_periodo_valida'] = !(empty($dataInicio) || empty($dataFim));

        if (!$disponivel) {
            $resultadoBase['nivel_alerta'] = 'indisponivel';
            if ($faltante > 0) {
                $resultadoBase['alertas'][] = 'Quantidade insuficiente para o produto composto. Faltante: ' . $faltante . '.';
            }
        } elseif (!empty($conflitosConsolidados)) {
            $resultadoBase['nivel_alerta'] = 'atencao';
        }

        return $resultadoBase;
    }

    /**
     * Retorna a agenda do produto dentro de uma janela.
     *
     * Importante: para componentes físicos, também considera pedidos feitos por produtos pais compostos.
     * Ex.: consultar "Capa Puff Azul" deve enxergar pedidos de "Puff Forrado Azul" que consomem essa capa.
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
                    (ip.quantidade * 1) AS quantidade,
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
                    c.nome AS nome_cliente,
                    'DIRETO' AS origem_consumo,
                    ip.produto_id AS produto_origem_id,
                    prod.nome_produto AS produto_origem_nome
                FROM itens_pedido ip
                INNER JOIN pedidos p ON p.id = ip.pedido_id
                INNER JOIN clientes c ON c.id = p.cliente_id
                INNER JOIN produtos prod ON prod.id = ip.produto_id
                WHERE
                    ip.produto_id = :produto_id_direto
                    AND ip.tipo_linha = 'PRODUTO'
                    AND ip.tipo = 'locacao'
                    AND p.situacao_pedido = 'confirmado'
                    AND p.data_entrega IS NOT NULL
                    AND p.data_devolucao_prevista IS NOT NULL
                    AND p.data_entrega <= :fim_data_direto
                    AND p.data_devolucao_prevista >= :inicio_data_direto
            ";

            if (!empty($ignorarPedidoId)) {
                $query .= " AND p.id <> :ignorar_pedido_id_direto";
            }

            $query .= "
                UNION ALL

                SELECT
                    ip.id AS item_pedido_id,
                    ip.pedido_id,
                    (ip.quantidade * pc.quantidade) AS quantidade,
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
                    c.nome AS nome_cliente,
                    'COMPOSTO' AS origem_consumo,
                    ip.produto_id AS produto_origem_id,
                    prod.nome_produto AS produto_origem_nome
                FROM itens_pedido ip
                INNER JOIN produto_composicao pc ON pc.produto_pai_id = ip.produto_id
                INNER JOIN pedidos p ON p.id = ip.pedido_id
                INNER JOIN clientes c ON c.id = p.cliente_id
                INNER JOIN produtos prod ON prod.id = ip.produto_id
                WHERE
                    pc.produto_filho_id = :produto_id_composicao
                    AND ip.tipo_linha = 'PRODUTO'
                    AND ip.tipo = 'locacao'
                    AND p.situacao_pedido = 'confirmado'
                    AND p.data_entrega IS NOT NULL
                    AND p.data_devolucao_prevista IS NOT NULL
                    AND p.data_entrega <= :fim_data_composicao
                    AND p.data_devolucao_prevista >= :inicio_data_composicao
            ";

            if (!empty($ignorarPedidoId)) {
                $query .= " AND p.id <> :ignorar_pedido_id_composicao";
            }

            $query .= " ORDER BY data_entrega ASC, hora_entrega ASC, pedido_id ASC";

            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(':produto_id_direto', $produtoId, PDO::PARAM_INT);
            $stmt->bindValue(':inicio_data_direto', $inicioJanela->format('Y-m-d'));
            $stmt->bindValue(':fim_data_direto', $fimJanela->format('Y-m-d'));
            $stmt->bindValue(':produto_id_composicao', $produtoId, PDO::PARAM_INT);
            $stmt->bindValue(':inicio_data_composicao', $inicioJanela->format('Y-m-d'));
            $stmt->bindValue(':fim_data_composicao', $fimJanela->format('Y-m-d'));

            if (!empty($ignorarPedidoId)) {
                $stmt->bindValue(':ignorar_pedido_id_direto', $ignorarPedidoId, PDO::PARAM_INT);
                $stmt->bindValue(':ignorar_pedido_id_composicao', $ignorarPedidoId, PDO::PARAM_INT);
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
                    'quantidade' => $this->normalizarNumeroEstoque((float) $row['quantidade']),
                    'situacao_pedido' => $row['situacao_pedido'],
                    'observacoes_item' => $row['observacoes_item'],
                    'inicio_dt' => $inicio->format('Y-m-d H:i:s'),
                    'fim_dt' => $fim->format('Y-m-d H:i:s'),
                    'inicio_formatado' => $this->formatarDataHoraBr($inicio),
                    'fim_formatado' => $this->formatarDataHoraBr($fim),
                    'origem_consumo' => $row['origem_consumo'] ?? 'DIRETO',
                    'produto_origem_id' => isset($row['produto_origem_id']) ? (int) $row['produto_origem_id'] : null,
                    'produto_origem_nome' => $row['produto_origem_nome'] ?? null,
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

    private function deduplicarRegistrosPorItemPedido(array $registros): array {
        if (empty($registros)) {
            return [];
        }

        $vistos = [];
        $deduplicados = [];

        foreach ($registros as $registro) {
            if (!is_array($registro)) {
                continue;
            }

            $itemPedidoId = isset($registro['item_pedido_id']) ? (int) $registro['item_pedido_id'] : 0;
            $pedidoId = isset($registro['pedido_id']) ? (int) $registro['pedido_id'] : 0;
            $produtoOrigemId = isset($registro['produto_origem_id']) ? (int) $registro['produto_origem_id'] : 0;
            $inicio = (string) ($registro['inicio_dt'] ?? '');
            $fim = (string) ($registro['fim_dt'] ?? '');

            if ($itemPedidoId > 0) {
                $chave = 'item:' . $itemPedidoId;
            } else {
                $chave = 'pedido:' . $pedidoId . '|produto:' . $produtoOrigemId . '|inicio:' . $inicio . '|fim:' . $fim;
            }

            if (isset($vistos[$chave])) {
                continue;
            }

            $vistos[$chave] = true;
            $deduplicados[] = $registro;
        }

        return $deduplicados;
    }

    private function montarResultadoBase(?array $produto, int $produtoId, int $quantidadeSolicitada = 0, array $sobrescrever = []): array {
        $estoqueTotal = $produto ? (int) ($produto['quantidade_total'] ?? 0) : 0;
        $quantidadeSolicitada = max(0, (int) $quantidadeSolicitada);

        $resultado = [
            'success' => true,
            'produto_id' => $produtoId,
            'produto_nome' => $produto['nome_produto'] ?? '',
            'tipo_produto' => $produto['tipo_produto'] ?? 'SIMPLES',
            'controla_estoque' => $produto ? (int) ($produto['controla_estoque'] ?? 1) : 1,
            'produto_composto' => $produto ? $this->produtoEhComposto($produto) : false,
            'estoque_total' => $estoqueTotal,
            'estoque_disponivel' => $estoqueTotal,
            'comprometido_periodo' => 0,
            'livre_periodo' => $estoqueTotal,
            'quantidade_solicitada' => $quantidadeSolicitada,
            'reservado_orcamento_atual' => $quantidadeSolicitada,
            'livre_apos_orcamento' => $estoqueTotal - $quantidadeSolicitada,
            'faltante_orcamento' => max(0, $quantidadeSolicitada - $estoqueTotal),
            'disponivel' => false,
            'consulta_periodo_valida' => false,
            'nivel_alerta' => 'ok',
            'alertas' => [],
            'conflitos' => [],
            'agenda' => [],
            'componentes' => [],
            'ultimo_retorno' => null,
            'proxima_saida' => null,
            'observacoes_produto' => $produto['observacoes'] ?? null,
        ];

        return array_merge($resultado, $sobrescrever);
    }

    private function obterProduto(int $produtoId): ?array {
        try {
            $query = "
                SELECT
                    id,
                    nome_produto,
                    quantidade_total,
                    observacoes,
                    disponivel_locacao,
                    tipo_produto,
                    controla_estoque
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

    private function obterComponentesProdutoComposto(int $produtoPaiId): array {
        try {
            $query = "
                SELECT
                    pc.id,
                    pc.produto_pai_id,
                    pc.produto_filho_id,
                    pc.quantidade,
                    pc.obrigatorio,
                    pc.observacoes,
                    pf.nome_produto AS produto_filho_nome,
                    pf.tipo_produto AS produto_filho_tipo,
                    pf.controla_estoque AS produto_filho_controla_estoque,
                    pf.quantidade_total AS produto_filho_quantidade_total
                FROM produto_composicao pc
                INNER JOIN produtos pf ON pf.id = pc.produto_filho_id
                WHERE pc.produto_pai_id = :produto_pai_id
                ORDER BY pc.id ASC
            ";
            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(':produto_pai_id', $produtoPaiId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Erro em EstoqueMovimentacao::obterComponentesProdutoComposto: " . $e->getMessage());
            return [];
        }
    }

    private function obterEstoqueTotalComposto(array $produto): int {
        $limitadores = [];

        if (!$this->produtoSemControleEstoque($produto)) {
            $limitadores[] = [
                'produto' => $produto,
                'quantidade_por_unidade' => 1.0,
            ];
        }

        foreach ($this->obterComponentesProdutoComposto((int) $produto['id']) as $componente) {
            $produtoFilho = $this->obterProduto((int) $componente['produto_filho_id']);
            if (!$produtoFilho || $this->produtoSemControleEstoque($produtoFilho)) {
                continue;
            }

            $limitadores[] = [
                'produto' => $produtoFilho,
                'quantidade_por_unidade' => max(0.0001, (float) $componente['quantidade']),
            ];
        }

        if (empty($limitadores)) {
            return 0;
        }

        $menor = null;
        foreach ($limitadores as $limitador) {
            $estoque = (int) ($limitador['produto']['quantidade_total'] ?? 0);
            $qtdPorUnidade = (float) $limitador['quantidade_por_unidade'];
            $possivel = (int) floor($estoque / $qtdPorUnidade);
            $menor = $menor === null ? $possivel : min($menor, $possivel);
        }

        return (int) ($menor ?? 0);
    }

    private function normalizarItensContextoAtual(array $itensContextoAtual): array {
        $itens = [];

        foreach ($itensContextoAtual as $item) {
            if (!is_array($item)) {
                continue;
            }

            $produtoId = isset($item['produto_id']) ? (int) $item['produto_id'] : 0;
            $quantidade = isset($item['quantidade']) ? (float) $item['quantidade'] : 0;

            if ($produtoId <= 0 || $quantidade <= 0) {
                continue;
            }

            if (!isset($itens[$produtoId])) {
                $itens[$produtoId] = 0.0;
            }

            $itens[$produtoId] += $quantidade;
        }

        $normalizados = [];
        foreach ($itens as $produtoId => $quantidade) {
            $normalizados[] = [
                'produto_id' => (int) $produtoId,
                'quantidade' => (float) $quantidade,
            ];
        }

        return $normalizados;
    }

    private function calcularConsumoLimitadorNoContexto(int $produtoLimitadorId, array $itensContextoAtual): float {
        $itens = $this->normalizarItensContextoAtual($itensContextoAtual);

        if (empty($itens)) {
            return 0.0;
        }

        $total = 0.0;

        foreach ($itens as $item) {
            $produtoOrigemId = (int) $item['produto_id'];
            $quantidadeOrigem = (float) $item['quantidade'];

            $consumos = $this->expandirConsumoEstoqueProduto(
                $produtoOrigemId,
                $quantidadeOrigem,
                []
            );

            if (isset($consumos[$produtoLimitadorId])) {
                $total += (float) $consumos[$produtoLimitadorId];
            }
        }

        return $total;
    }

    private function expandirConsumoEstoqueProduto(int $produtoId, float $quantidade, array $pilha = []): array {
        if ($produtoId <= 0 || $quantidade <= 0) {
            return [];
        }

        if (in_array($produtoId, $pilha, true)) {
            return [];
        }

        $pilha[] = $produtoId;
        $produto = $this->obterProduto($produtoId);

        if (!$produto) {
            return [];
        }

        $consumos = [];

        if (!$this->produtoSemControleEstoque($produto)) {
            $consumos[$produtoId] = ($consumos[$produtoId] ?? 0.0) + $quantidade;
        }

        if ($this->produtoEhComposto($produto)) {
            $componentes = $this->obterComponentesProdutoComposto($produtoId);

            foreach ($componentes as $componente) {
                $filhoId = (int) ($componente['produto_filho_id'] ?? 0);
                $qtdPorUnidade = max(0.0001, (float) ($componente['quantidade'] ?? 1));
                $qtdFilho = $quantidade * $qtdPorUnidade;

                $consumosFilho = $this->expandirConsumoEstoqueProduto(
                    $filhoId,
                    $qtdFilho,
                    $pilha
                );

                foreach ($consumosFilho as $idConsumo => $qtdConsumo) {
                    $consumos[$idConsumo] = ($consumos[$idConsumo] ?? 0.0) + (float) $qtdConsumo;
                }
            }
        }

        return $consumos;
    }

    private function produtoEhComposto(array $produto): bool {
        return strtoupper((string) ($produto['tipo_produto'] ?? 'SIMPLES')) === 'COMPOSTO';
    }

    private function produtoSemControleEstoque(array $produto): bool {
        return (int) ($produto['controla_estoque'] ?? 1) !== 1;
    }

    private function normalizarNumeroEstoque($valor) {
        $valor = (float) $valor;
        if (abs($valor - round($valor)) < 0.00001) {
            return (int) round($valor);
        }
        return round($valor, 2);
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
