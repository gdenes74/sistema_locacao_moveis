← [Voltar para a documentação](../README.md)

# 10 — ERD Operação

```mermaid
erDiagram
    PRODUTOS ||--o{ ESTOQUE_TEMPORAL : possui
    PRODUTOS ||--o{ MOVIMENTACOES_ESTOQUE : movimenta
    PEDIDOS ||--o{ ITENS_PEDIDO : contem

    LAVANDERIA ||--o{ ITENS_LAVANDERIA : possui
    MANUTENCAO ||--o{ ITENS_MANUTENCAO : possui
    ITENS_PEDIDO ||--o{ OCORRENCIAS_ITENS : pode_gerar

    PEDIDOS ||--o{ ITENS_LAVANDERIA : referencia
    PEDIDOS ||--o{ ITENS_MANUTENCAO : referencia
```

ERD modular de operação. Lavanderia e manutenção estão representadas como estrutura modelada no banco, não como módulos operacionais concluídos.


---

← [Voltar para a documentação](../README.md)
