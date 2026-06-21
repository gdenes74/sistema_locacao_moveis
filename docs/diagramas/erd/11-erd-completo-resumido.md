← [Voltar para a documentação](../README.md)

# 11 — ERD Completo Resumido

```mermaid
erDiagram
    CLIENTES ||--o{ ORCAMENTOS : possui
    CLIENTES ||--o{ PEDIDOS : possui
    ORCAMENTOS ||--o{ ITENS_ORCAMENTO : contem
    ORCAMENTOS ||--o| PEDIDOS : converte
    PEDIDOS ||--o{ ITENS_PEDIDO : contem

    SECOES ||--o{ CATEGORIAS : possui
    CATEGORIAS ||--o{ SUBCATEGORIAS : possui
    SUBCATEGORIAS ||--o{ PRODUTOS : possui
    PRODUTOS ||--o{ FOTOS : possui

    PRODUTOS ||--o{ ITENS_ORCAMENTO : usado_em
    PRODUTOS ||--o{ ITENS_PEDIDO : usado_em
    PRODUTOS ||--o{ ESTOQUE_TEMPORAL : reservado_em
    PRODUTOS ||--o{ MOVIMENTACOES_ESTOQUE : movimenta

    PRODUTOS ||--o{ PRODUTO_COMPOSICAO : pai
    PRODUTOS ||--o{ PRODUTO_ACABAMENTO : acabamento
```

Visão resumida do ERD completo para GitHub. O ERD detalhado pode ser dividido por domínio para melhorar a leitura.


---

← [Voltar para a documentação](../README.md)
