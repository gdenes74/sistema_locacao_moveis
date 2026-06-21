← [Voltar para a documentação](../README.md)

# 05 — Estoque Temporal

```mermaid
flowchart TD
    A[Produto] --> B[Quantidade Disponível]
    C[Pedido] --> D[Período do Evento]
    D --> E[Reserva Temporal]
    A --> E
    E --> F{Há conflito de datas?}
    F -- Não --> G[Item disponível]
    F -- Sim --> H[Item indisponível no período]
    G --> I[Produção]
```

Representação do conceito de estoque temporal: o item não sai definitivamente do estoque, apenas fica indisponível durante um período.


---

← [Voltar para a documentação](../README.md)
