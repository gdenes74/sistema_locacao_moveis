<?php
/**
 * Migração aditiva e idempotente. Não altera estoque, pedidos, orçamentos ou produtos.
 * Executar uma única vez pela linha de comando: C:\xampp\php\php.exe tests\instalar_producao_pendente.php
 * Reversão: DROP TABLE producao_pendencias_historico; DROP TABLE producao_pendencias;
 */
require_once __DIR__ . '/../config/database.php';

$db = (new Database())->getConnection();
$db->exec("CREATE TABLE IF NOT EXISTS producao_pendencias (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    origem_tipo VARCHAR(20) NOT NULL DEFAULT 'MANUAL',
    origem_id INT UNSIGNED NULL,
    pedido_id INT UNSIGNED NULL,
    orcamento_id INT UNSIGNED NULL,
    cliente_id INT UNSIGNED NULL,
    produto_id INT UNSIGNED NULL,
    componente_id INT UNSIGNED NULL,
    descricao_item VARCHAR(255) NULL,
    cor VARCHAR(120) NULL,
    quantidade_solicitada DECIMAL(10,2) NOT NULL DEFAULT 0,
    quantidade_faltante DECIMAL(10,2) NOT NULL DEFAULT 0,
    data_evento DATE NULL,
    data_entrega DATE NULL,
    observacoes TEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'PENDENTE',
    criado_por INT UNSIGNED NULL,
    atualizado_por INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_producao_status_data (status, data_entrega, data_evento),
    KEY idx_producao_origem (origem_tipo, origem_id),
    KEY idx_producao_pedido (pedido_id),
    KEY idx_producao_orcamento (orcamento_id),
    KEY idx_producao_cliente (cliente_id),
    KEY idx_producao_produto (produto_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$db->exec("CREATE TABLE IF NOT EXISTS producao_pendencias_historico (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    pendencia_id INT UNSIGNED NOT NULL,
    acao VARCHAR(40) NOT NULL,
    status_anterior VARCHAR(20) NULL,
    status_novo VARCHAR(20) NULL,
    observacao TEXT NULL,
    usuario_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_producao_historico_pendencia (pendencia_id),
    CONSTRAINT fk_producao_historico_pendencia FOREIGN KEY (pendencia_id)
        REFERENCES producao_pendencias(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

echo "Produção Pendente instalada/confirmada. Nenhum estoque foi alterado.\n";
