-- Permite que uma apresentação forrada consuma um de dois grupos físicos.
-- O grupo principal também pode ser alugado diretamente; o adicional é exclusivo.
-- Migração reversível: DROP TABLE produto_estoque_alternativo;
CREATE TABLE IF NOT EXISTS produto_estoque_alternativo (
    produto_pai_id INT NOT NULL PRIMARY KEY,
    produto_principal_id INT NOT NULL,
    produto_adicional_id INT NOT NULL,
    observacoes TEXT NULL,
    CONSTRAINT fk_pea_pai FOREIGN KEY (produto_pai_id) REFERENCES produtos(id) ON DELETE CASCADE,
    CONSTRAINT fk_pea_principal FOREIGN KEY (produto_principal_id) REFERENCES produtos(id),
    CONSTRAINT fk_pea_adicional FOREIGN KEY (produto_adicional_id) REFERENCES produtos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO produto_estoque_alternativo
    (produto_pai_id, produto_principal_id, produto_adicional_id, observacoes)
SELECT pai.id, principal.id, adicional.id,
       'Cubo forrado 0,75 m: 12 em madeira ou forrados + 8 exclusivos de forração. A disponibilidade conjunta é de 20, respeitando locações em madeira.'
FROM produtos pai
JOIN produtos principal ON principal.nome_produto = 'Cubo Madeira 0,50 x 0,50 x 0,75 m'
JOIN produtos adicional ON adicional.nome_produto = 'Cubo 0,50 x 0,50 x 0,75 m Estrutura Exclusiva Forração'
WHERE pai.nome_produto = 'Cubo 0,50 x 0,50 x 0,75 m Forrado Cor A Definir'
  AND NOT EXISTS (SELECT 1 FROM produto_estoque_alternativo e WHERE e.produto_pai_id = pai.id);
