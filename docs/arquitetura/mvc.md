# 🧱 Estrutura MVC

![MVC](https://img.shields.io/badge/Pattern-MVC-blue)
![PHP](https://img.shields.io/badge/PHP-Application-777BB4)

## Introdução

O projeto utiliza uma organização inspirada no padrão MVC (Model-View-Controller).

A implementação foi adaptada à realidade e à evolução do sistema, priorizando simplicidade e facilidade de manutenção.

---

## Estrutura Geral

```text
Usuário
   ↓
Views
   ↓
Models
   ↓
Banco de Dados
```

---

## Views

Localização:

```text
/views
```

Responsáveis por:

* Interfaces do sistema
* Formulários
* Listagens
* Impressões
* Interação com usuários

Exemplos:

* Clientes
* Produtos
* Orçamentos
* Pedidos
* Dashboard

---

## Models

Localização:

```text
/models
```

Responsáveis por:

* Comunicação com banco de dados
* Consultas
* Inserções
* Atualizações
* Regras relacionadas às entidades

Principais models:

* Cliente
* Produto
* Orcamento
* Pedido
* Categoria
* Secao
* Subcategoria
* EstoqueMovimentacao

---

## Configuração

Localização:

```text
/config
```

Responsável por:

* Conexão com banco
* Configurações gerais
* Parâmetros do sistema

---

## Utilitários

Localização:

```text
/utils
```

Responsáveis por:

* Funções auxiliares
* Uploads
* Helpers

---

## Recursos Estáticos

Localização:

```text
/assets
```

Contém:

* Imagens
* Uploads
* Logos
* Arquivos estáticos

---

## AJAX

Localização:

```text
/ ajax
```

Utilizado para consultas assíncronas e recursos auxiliares da interface.

---

## Considerações

Embora inspirado em MVC, o projeto foi desenvolvido de forma incremental e pragmática.

O foco principal sempre foi apoiar a operação real da empresa, mantendo o código compreensível, funcional e passível de evolução contínua.
