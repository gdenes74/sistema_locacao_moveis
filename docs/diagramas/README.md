# 📊 Diagramas

![Blueprint](https://img.shields.io/badge/Blueprint-System%20Architecture-blue)
![Documentation](https://img.shields.io/badge/Documentation-Visual-success)
![Project](https://img.shields.io/badge/Project-Mobel%20Festas-purple)

## Objetivo

Esta pasta concentra os diagramas visuais do Sistema Operacional de Locação para Eventos da Mobel Festas.

Os diagramas complementam a documentação textual e ajudam a compreender:

* Fluxos operacionais
* Regras de negócio
* Estrutura do banco de dados
* Arquitetura do sistema
* Evolução do projeto

---

## Estratégia de Documentação Visual

Em vez de criar um único diagrama gigante e difícil de interpretar, a documentação foi dividida em diagramas menores e especializados.

Essa abordagem facilita:

* Leitura
* Impressão
* Manutenção
* Evolução da documentação

---

## Diagramas Planejados

### 01 — Visão Geral

```text
01-visao-geral.png
```

Apresenta os principais módulos e o fluxo geral do sistema.

---

### 02 — Fluxo Comercial

```text
02-fluxo-comercial.png
```

Fluxo:

```text
Cliente
   ↓
Orçamento
   ↓
Pedido
```

---

### 03 — Fluxo Operacional

```text
03-fluxo-operacional.png
```

Fluxo:

```text
Pedido
   ↓
Produção
   ↓
Entrega
   ↓
Evento
   ↓
Retorno
   ↓
Conferência
```

---

### 04 — Catálogo de Produtos

```text
04-catalogo-produtos.png
```

Relacionamentos entre:

* Produtos
* Seções
* Categorias
* Subcategorias
* Fotos

---

### 05 — Estoque Temporal

```text
05-estoque-temporal.png
```

Representação visual do controle de disponibilidade por período.

---

### 06 — Produtos Compostos

```text
06-produtos-compostos.png
```

Relacionamentos entre:

* Produtos
* Kits
* Componentes
* Acabamentos
* Grupos

---

### 07 — Roadmap

```text
07-roadmap.png
```

Representação visual da evolução planejada do sistema.

---

### 08 — ERD Comercial

```text
08-erd-comercial.png
```

Tabelas relacionadas a:

* Clientes
* Orçamentos
* Pedidos

---

### 09 — ERD Catálogo

```text
09-erd-catalogo.png
```

Tabelas relacionadas a:

* Produtos
* Categorias
* Fotos

---

### 10 — ERD Operação

```text
10-erd-operacao.png
```

Tabelas relacionadas a:

* Estoque temporal
* Movimentações
* Lavanderia
* Manutenção
* Ocorrências

---

## Padrão Recomendado

Sempre que possível:

* Utilizar fontes legíveis para impressão.
* Evitar excesso de elementos em um único diagrama.
* Dividir diagramas por assunto.
* Manter versões PNG e PDF.
* Atualizar os diagramas conforme a evolução do sistema.

---

## Observação

Os diagramas representam uma visão complementar da documentação.

Para detalhes completos sobre regras, arquitetura e contexto operacional, consultar os documentos presentes nas demais pastas da estrutura `/docs`.
