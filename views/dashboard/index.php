<?php
// views/dashboard/index.php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

$page_title = "Dashboard";

$database = new Database();
$db = $database->getConnection();

function dashCount(PDO $db, string $sql, array $params = []): int
{
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    } catch (Exception $e) {
        error_log('[dashboard] dashCount: ' . $e->getMessage());
        return 0;
    }
}

function dashRows(PDO $db, string $sql, array $params = []): array
{
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log('[dashboard] dashRows: ' . $e->getMessage());
        return [];
    }
}

function dashMoeda(float|int|string|null $valor): string
{
    return 'R$ ' . number_format((float)($valor ?? 0), 2, ',', '.');
}

function dashData(?string $data): string
{
    if (empty($data) || $data === '0000-00-00') {
        return '-';
    }
    try {
        return (new DateTime($data))->format('d/m/Y');
    } catch (Exception $e) {
        return '-';
    }
}

function dashStatusLabel(?string $status = null): string
{
    $status = $status ?: 'pendente';
    $labels = [
        'pendente' => 'Pendente',
        'aprovado' => 'Aprovado',
        'convertido' => 'Convertido',
        'cancelado' => 'Cancelado',
        'confirmado' => 'Confirmado',
        'em_separacao' => 'Em separação',
        'entregue' => 'Entregue',
        'devolvido_parcial' => 'Devolvido parcial',
        'finalizado' => 'Finalizado',
    ];
    return $labels[$status] ?? ucfirst(str_replace('_', ' ', $status));
}

$hoje = date('Y-m-d');
$emSeteDias = date('Y-m-d', strtotime('+7 days'));
$mesInicio = date('Y-m-01');
$mesFim = date('Y-m-t');

$totais = [
    'clientes' => dashCount($db, "SELECT COUNT(*) FROM clientes"),
    'produtos' => dashCount($db, "SELECT COUNT(*) FROM produtos"),
    'orcamentos_pendentes' => dashCount($db, "SELECT COUNT(*) FROM orcamentos WHERE COALESCE(status, status_orcamento, '') IN ('', 'pendente')"),
    'pedidos_confirmados' => dashCount($db, "SELECT COUNT(*) FROM pedidos WHERE situacao_pedido = 'confirmado'"),
    'pedidos_hoje_evento' => dashCount($db, "SELECT COUNT(*) FROM pedidos WHERE data_evento = :hoje AND situacao_pedido <> 'cancelado'", [':hoje' => $hoje]),
    'entregas_hoje' => dashCount($db, "SELECT COUNT(*) FROM pedidos WHERE data_entrega = :hoje AND situacao_pedido <> 'cancelado'", [':hoje' => $hoje]),
    'coletas_hoje' => dashCount($db, "SELECT COUNT(*) FROM pedidos WHERE data_devolucao_prevista = :hoje AND situacao_pedido <> 'cancelado'", [':hoje' => $hoje]),
    'saldo_aberto' => 0,
    'valor_mes' => 0,
];

try {
    $stmt = $db->prepare("SELECT COALESCE(SUM(saldo_calculado), 0) FROM pedidos WHERE situacao_pedido <> 'cancelado'");
    $stmt->execute();
    $totais['saldo_aberto'] = (float)$stmt->fetchColumn();
} catch (Exception $e) {
    error_log('[dashboard] saldo_aberto: ' . $e->getMessage());
}

try {
    $stmt = $db->prepare("SELECT COALESCE(SUM(valor_final), 0) FROM pedidos WHERE data_evento BETWEEN :ini AND :fim AND situacao_pedido <> 'cancelado'");
    $stmt->execute([':ini' => $mesInicio, ':fim' => $mesFim]);
    $totais['valor_mes'] = (float)$stmt->fetchColumn();
} catch (Exception $e) {
    error_log('[dashboard] valor_mes: ' . $e->getMessage());
}

$pedidosProximos = dashRows($db, "
    SELECT p.id, p.numero, p.codigo, p.data_evento, p.data_entrega, p.data_devolucao_prevista,
           p.valor_final, p.saldo_calculado, p.situacao_pedido, c.nome AS nome_cliente
    FROM pedidos p
    LEFT JOIN clientes c ON c.id = p.cliente_id
    WHERE p.situacao_pedido <> 'cancelado'
      AND (
          p.data_evento BETWEEN :hoje1 AND :em_sete1
          OR p.data_entrega BETWEEN :hoje2 AND :em_sete2
          OR p.data_devolucao_prevista BETWEEN :hoje3 AND :em_sete3
      )
    ORDER BY COALESCE(p.data_entrega, p.data_evento, p.data_devolucao_prevista) ASC, p.id DESC
    LIMIT 8
", [
    ':hoje1' => $hoje, ':em_sete1' => $emSeteDias,
    ':hoje2' => $hoje, ':em_sete2' => $emSeteDias,
    ':hoje3' => $hoje, ':em_sete3' => $emSeteDias,
]);

$orcamentosRecentes = dashRows($db, "
    SELECT o.id, o.numero, o.codigo, o.data_orcamento, o.data_evento, o.valor_final,
           COALESCE(o.status, o.status_orcamento, 'pendente') AS status_atual,
           c.nome AS nome_cliente
    FROM orcamentos o
    LEFT JOIN clientes c ON c.id = o.cliente_id
    ORDER BY o.id DESC
    LIMIT 6
");

include_once __DIR__ . '/../includes/header.php';
?>

<style>
.dashboard-hero {
    border-radius: 18px;
    padding: 24px 26px;
    background: linear-gradient(135deg, #0b2d4d 0%, #14507d 50%, #0f766e 100%);
    color: #fff;
    box-shadow: 0 14px 35px rgba(0,0,0,.16);
    position: relative;
    overflow: hidden;
}
.dashboard-hero:after {
    content: "";
    position: absolute;
    right: -60px;
    top: -60px;
    width: 210px;
    height: 210px;
    border-radius: 50%;
    background: rgba(255,255,255,.10);
}
.dashboard-hero h1 { font-weight: 800; letter-spacing: -.4px; }
.dashboard-hero .lead { opacity: .92; max-width: 780px; }
.dash-action a { margin-right: 8px; margin-bottom: 8px; }
.metric-card {
    border: 0;
    border-radius: 16px;
    box-shadow: 0 9px 24px rgba(15, 23, 42, .08);
    overflow: hidden;
    height: 100%;
}
.metric-card .card-body { padding: 18px; }
.metric-icon {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 1.2rem;
    margin-bottom: 12px;
}
.metric-number { font-size: 1.65rem; font-weight: 800; line-height: 1; }
.metric-label { color: #64748b; font-size: .92rem; margin-top: 4px; }
.portal-card {
    border: 0;
    border-radius: 16px;
    box-shadow: 0 8px 22px rgba(15, 23, 42, .07);
}
.portal-card .card-header {
    border-bottom: 1px solid rgba(0,0,0,.06);
    background: #fff;
    border-radius: 16px 16px 0 0;
    font-weight: 700;
}
.quick-card {
    display: block;
    padding: 18px;
    border-radius: 15px;
    background: #fff;
    border: 1px solid #edf2f7;
    color: #1f2937;
    height: 100%;
    transition: all .15s ease-in-out;
}
.quick-card:hover { transform: translateY(-2px); box-shadow: 0 10px 24px rgba(0,0,0,.08); color: #0b5ed7; }
.quick-card i { font-size: 1.45rem; margin-bottom: 10px; }
.table-dashboard td, .table-dashboard th { font-size: .92rem; }
.badge-soft { padding: .4rem .55rem; border-radius: 999px; }
</style>

<section class="content-header pb-0">
    <div class="container-fluid">
        <div class="dashboard-hero mb-4">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1 class="mb-2">Portal MOBEL</h1>
                    <p class="lead mb-3">Visão rápida da operação: pedidos, eventos, entregas, coletas, clientes, produtos e textos padrão do sistema.</p>
                    <div class="dash-action">
                        <a href="<?= BASE_URL ?>/views/orcamentos/create.php" class="btn btn-warning btn-sm font-weight-bold"><i class="fas fa-plus mr-1"></i> Novo Orçamento</a>
                        <a href="<?= BASE_URL ?>/views/pedidos/create.php" class="btn btn-light btn-sm font-weight-bold"><i class="fas fa-shopping-cart mr-1"></i> Novo Pedido</a>
                        <a href="<?= BASE_URL ?>/views/configuracoes_textos/index.php" class="btn btn-outline-light btn-sm font-weight-bold"><i class="fas fa-align-left mr-1"></i> Textos Padrão</a>
                    </div>
                </div>
                <div class="col-lg-4 text-lg-right mt-3 mt-lg-0">
                    <div class="h5 mb-1"><?= date('d/m/Y') ?></div>
                    <div class="text-white-50">Painel de entrada do sistema de locação</div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card metric-card">
                    <div class="card-body">
                        <div class="metric-icon bg-primary"><i class="fas fa-users"></i></div>
                        <div class="metric-number"><?= (int)$totais['clientes'] ?></div>
                        <div class="metric-label">Clientes cadastrados</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card metric-card">
                    <div class="card-body">
                        <div class="metric-icon bg-success"><i class="fas fa-boxes"></i></div>
                        <div class="metric-number"><?= (int)$totais['produtos'] ?></div>
                        <div class="metric-label">Produtos / itens</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card metric-card">
                    <div class="card-body">
                        <div class="metric-icon bg-info"><i class="fas fa-file-invoice-dollar"></i></div>
                        <div class="metric-number"><?= (int)$totais['orcamentos_pendentes'] ?></div>
                        <div class="metric-label">Orçamentos pendentes</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card metric-card">
                    <div class="card-body">
                        <div class="metric-icon bg-warning"><i class="fas fa-calendar-check"></i></div>
                        <div class="metric-number"><?= (int)$totais['pedidos_confirmados'] ?></div>
                        <div class="metric-label">Pedidos confirmados</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card metric-card">
                    <div class="card-body">
                        <div class="metric-icon bg-danger"><i class="fas fa-truck-loading"></i></div>
                        <div class="metric-number"><?= (int)$totais['entregas_hoje'] ?></div>
                        <div class="metric-label">Entregas hoje</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card metric-card">
                    <div class="card-body">
                        <div class="metric-icon bg-secondary"><i class="fas fa-undo-alt"></i></div>
                        <div class="metric-number"><?= (int)$totais['coletas_hoje'] ?></div>
                        <div class="metric-label">Coletas hoje</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card metric-card">
                    <div class="card-body">
                        <div class="metric-icon bg-teal"><i class="fas fa-coins"></i></div>
                        <div class="metric-number" style="font-size:1.25rem;"><?= dashMoeda($totais['valor_mes']) ?></div>
                        <div class="metric-label">Eventos do mês</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card metric-card">
                    <div class="card-body">
                        <div class="metric-icon bg-dark"><i class="fas fa-cash-register"></i></div>
                        <div class="metric-number" style="font-size:1.25rem;"><?= dashMoeda($totais['saldo_aberto']) ?></div>
                        <div class="metric-label">Saldo aberto em pedidos</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8 mb-4">
                <div class="card portal-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-calendar-alt mr-1"></i> Próximos 7 dias</span>
                        <a href="<?= BASE_URL ?>/views/pedidos/index.php" class="btn btn-sm btn-outline-primary ml-auto">Ver pedidos</a>
                    </div>
                    <div class="card-body p-0">
                        <?php if (!empty($pedidosProximos)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover table-dashboard mb-0">
                                    <thead>
                                        <tr>
                                            <th>Pedido</th>
                                            <th>Cliente</th>
                                            <th>Evento</th>
                                            <th>Entrega</th>
                                            <th>Coleta</th>
                                            <th>Total</th>
                                            <th>Status</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pedidosProximos as $p): ?>
                                            <tr>
                                                <td><strong>#<?= htmlspecialchars($p['numero'] ?? $p['id']) ?></strong><br><small><?= htmlspecialchars($p['codigo'] ?? '') ?></small></td>
                                                <td><?= htmlspecialchars($p['nome_cliente'] ?? 'Cliente não informado') ?></td>
                                                <td><?= dashData($p['data_evento'] ?? null) ?></td>
                                                <td><?= dashData($p['data_entrega'] ?? null) ?></td>
                                                <td><?= dashData($p['data_devolucao_prevista'] ?? null) ?></td>
                                                <td><strong><?= dashMoeda($p['valor_final'] ?? 0) ?></strong><br><small>Saldo: <?= dashMoeda($p['saldo_calculado'] ?? 0) ?></small></td>
                                                <td><span class="badge badge-primary badge-soft"><?= htmlspecialchars(dashStatusLabel($p['situacao_pedido'] ?? '')) ?></span></td>
                                                <td class="text-right"><a href="<?= BASE_URL ?>/views/pedidos/show.php?id=<?= (int)$p['id'] ?>" class="btn btn-sm btn-info"><i class="fas fa-eye"></i></a></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="p-4 text-center text-muted">Nenhum pedido com evento, entrega ou coleta nos próximos 7 dias.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 mb-4">
                <div class="card portal-card mb-4">
                    <div class="card-header"><i class="fas fa-bolt mr-1"></i> Atalhos rápidos</div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-6 mb-3"><a class="quick-card" href="<?= BASE_URL ?>/views/orcamentos/create.php"><i class="fas fa-file-invoice-dollar text-primary"></i><br><strong>Novo orçamento</strong></a></div>
                            <div class="col-6 mb-3"><a class="quick-card" href="<?= BASE_URL ?>/views/pedidos/create.php"><i class="fas fa-shopping-cart text-success"></i><br><strong>Novo pedido</strong></a></div>
                            <div class="col-6 mb-3"><a class="quick-card" href="<?= BASE_URL ?>/views/clientes/create.php"><i class="fas fa-user-plus text-info"></i><br><strong>Novo cliente</strong></a></div>
                            <div class="col-6 mb-3"><a class="quick-card" href="<?= BASE_URL ?>/views/configuracoes_textos/index.php"><i class="fas fa-align-left text-warning"></i><br><strong>Textos padrão</strong></a></div>
                        </div>
                    </div>
                </div>

                <div class="card portal-card">
                    <div class="card-header"><i class="fas fa-history mr-1"></i> Orçamentos recentes</div>
                    <div class="card-body p-0">
                        <?php if (!empty($orcamentosRecentes)): ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($orcamentosRecentes as $o): ?>
                                    <a href="<?= BASE_URL ?>/views/orcamentos/show.php?id=<?= (int)$o['id'] ?>" class="list-group-item list-group-item-action">
                                        <div class="d-flex justify-content-between">
                                            <strong>#<?= htmlspecialchars($o['numero'] ?? $o['id']) ?></strong>
                                            <span class="badge badge-light"><?= htmlspecialchars(dashStatusLabel($o['status_atual'] ?? '')) ?></span>
                                        </div>
                                        <div><?= htmlspecialchars($o['nome_cliente'] ?? 'Cliente não informado') ?></div>
                                        <small class="text-muted">Evento: <?= dashData($o['data_evento'] ?? null) ?> · <?= dashMoeda($o['valor_final'] ?? 0) ?></small>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="p-3 text-muted">Nenhum orçamento recente encontrado.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
