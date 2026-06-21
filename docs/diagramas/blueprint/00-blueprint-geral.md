← [Voltar para a documentação](../README.md)

# 00 — Blueprint Geral

```mermaid
flowchart TD
    A[Cliente] --> B[Orçamento]
    B --> C[Pedido Confirmado]
    C --> D[Reserva no Estoque Temporal]
    D --> E[Produção]
    E --> F[Evento]
    F --> G[Retorno]
    G --> H[Conferência]

    P[Catálogo de Produtos] --> B
    PC[Produtos Compostos] --> B
    PC --> E
    DOC[Documentos e Impressões] --> B
    DOC --> C
    CFG[Configurações e Textos Padrão] --> DOC

    FUT[Planejado: Lavanderia, Manutenção e Ocorrências] -. modelado no banco .-> H
```

Este diagrama apresenta a visão geral do sistema sem tratá-lo como ERP completo. O foco é o ciclo operacional de locação para eventos.


---

← [Voltar para a documentação](../README.md)
