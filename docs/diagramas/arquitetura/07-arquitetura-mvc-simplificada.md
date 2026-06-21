← [Voltar para a documentação](../README.md)

# 07 — Arquitetura MVC Simplificada

```mermaid
flowchart TD
    A[Usuário] --> B[index.php]
    B --> C[Views]
    C --> D[Models]
    D --> E[(MariaDB / MySQL)]

    B --> F[Utils]
    C --> G[AJAX]
    C --> H[Assets]
    B --> I[Config]

    I --> E
```

Arquitetura inspirada em MVC, sem camada Controller formalmente separada.


---

← [Voltar para a documentação](../README.md)
