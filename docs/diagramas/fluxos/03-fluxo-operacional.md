← [Voltar para a documentação](../README.md)

# 03 — Fluxo Operacional

```mermaid
flowchart TD
    A[Pedido Confirmado] --> B[Reserva de Estoque]
    B --> C[Produção]
    C --> D[Separação dos Itens]
    D --> E[Entrega]
    E --> F[Evento]
    F --> G[Retorno]
    G --> H[Conferência]

    H -. futuro .-> I[Lavanderia]
    H -. futuro .-> J[Manutenção]
    H -. futuro .-> K[Ocorrências]
```

Fluxo operacional principal após a confirmação do pedido. Lavanderia, manutenção e ocorrências aparecem como processos planejados/modelados, não como módulos concluídos.


---

← [Voltar para a documentação](../README.md)
