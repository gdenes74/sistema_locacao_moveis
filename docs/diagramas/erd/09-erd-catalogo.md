← [Voltar para a documentação](../README.md)

# 09 — ERD Catálogo

```mermaid
erDiagram
    SECOES ||--o{ CATEGORIAS : possui
    CATEGORIAS ||--o{ SUBCATEGORIAS : possui
    SUBCATEGORIAS ||--o{ PRODUTOS : possui
    PRODUTOS ||--o{ FOTOS : possui
    CORES_PADRAO ||--o{ PRODUTO_ACABAMENTO : referencia
```

ERD modular do catálogo de produtos.


---

← [Voltar para a documentação](../README.md)
