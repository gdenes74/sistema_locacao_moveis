← [Voltar para a documentação](../README.md)

# 🗄️ Modelagem do Banco de Dados

![Database](https://img.shields.io/badge/Database-MariaDB-003545?logo=mariadb\&logoColor=white)
![Tool](https://img.shields.io/badge/Tool-HeidiSQL-orange)
![Modeling](https://img.shields.io/badge/Focus-Data%20Modeling-blue)

## Visão Geral

O banco de dados do Sistema Operacional de Locação para Eventos foi modelado para representar os principais processos da Mobel Festas.

A estrutura reflete o fluxo real da operação:

```text
Cliente
   ↓
Orçamento
   ↓
Pedido
   ↓
Estoque
   ↓
Produção
   ↓
Retorno
```

O banco é administrado através do HeidiSQL e utiliza MariaDB/MySQL.

---

## 👥 Comercial

Tabelas relacionadas ao atendimento, propostas comerciais e pedidos confirmados.

```text
clientes
orcamentos
itens_orcamento
pedidos
itens_pedido
```

Fluxo principal:

```text
clientes
   ↓
orcamentos
   ↓
pedidos
```

---

## 🪑 Catálogo

Tabelas responsáveis pela organização dos produtos disponíveis para locação.

```text
produtos
secoes
categorias
subcategorias
fotos
cores_padrao
```

Essa estrutura permite classificar produtos, organizar fotos e facilitar consultas operacionais.

---

## 🧩 Produtos Compostos

Tabelas utilizadas para representar produtos compostos, kits e conjuntos.

```text
produto_composicao
produto_conjunto_grupos
produto_acabamento
```

Esse grupo é importante porque alguns produtos possuem uma visão comercial para o cliente, mas dependem de componentes operacionais internos.

---

## 📦 Operação e Estoque

Tabelas relacionadas ao controle de disponibilidade, movimentações e processos pós-evento.

```text
movimentacoes_estoque
estoque_temporal
lavanderia
itens_lavanderia
manutencao
itens_manutencao
ocorrencias_itens
```

Este grupo representa uma das partes mais importantes do sistema: o controle temporal de estoque.

Um produto pode estar disponível em uma data e indisponível em outra, dependendo das reservas existentes.

---

## ⚙️ Sistema e Configurações

Tabelas de apoio utilizadas para usuários, textos padrão, sequências e configurações internas.

```text
usuarios
configuracoes_textos
numeracao_sequencial
numeracao_sequencial_historico
sequencias
```

Essas tabelas ajudam a manter padronização, rastreabilidade e organização interna do sistema.

---

## ⏳ Conceito de Estoque Temporal

Em uma operação de locação, o estoque não é reduzido permanentemente como ocorre em uma venda tradicional.

O item continua pertencendo à empresa, mas permanece indisponível durante o período em que está reservado para um evento.

Por esse motivo, o sistema precisa controlar:

* Período do evento
* Reservas existentes
* Quantidade disponível
* Movimentações
* Retorno dos itens
* Ocorrências operacionais

---

## 🔗 Relação com as Regras de Negócio

A modelagem do banco foi construída a partir das necessidades reais da operação.

Exemplos:

* Um cliente pode possuir vários orçamentos.
* Um orçamento pode ser convertido em pedido.
* Um pedido pode conter vários itens.
* Um produto pode participar de vários pedidos em períodos diferentes.
* Um produto pode ser simples ou composto.
* Um item retornado pode gerar lavanderia, manutenção ou ocorrência.

---

## 📊 ERD

O diagrama entidade-relacionamento (ERD) deve ser mantido em:

```text
docs/diagramas
```

Preferencialmente em formatos:

* PNG
* PDF
* Mermaid
* PlantUML (quando aplicável)

---

## 📌 Observação

O modelo de dados continua evoluindo conforme novas necessidades operacionais surgem.

A prioridade da modelagem é representar de forma prática e compreensível os processos reais da Mobel Festas.

---

📚 [Voltar para a documentação](../README.md)
