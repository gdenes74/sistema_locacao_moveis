← [Voltar para a documentação](../README.md)

# 🎪 Contexto Operacional

![Business](https://img.shields.io/badge/Business-Event%20Rental-success)
![Operation](https://img.shields.io/badge/Focus-Operational%20Control-blue)
![Real Process](https://img.shields.io/badge/Based%20on-Real%20Operation-purple)

## Objetivo

Este documento apresenta o contexto operacional que motivou o desenvolvimento do Sistema Operacional de Locação para Eventos da Mobel Festas.

Compreender o funcionamento da operação é fundamental para entender as decisões arquiteturais, a modelagem de dados e as regras de negócio implementadas no sistema.

---

## O Negócio

A Mobel Festas atua na locação de móveis, toalhas e itens para eventos.

Diferentemente de uma venda tradicional, a locação envolve controle de datas, disponibilidade, logística, produção, entrega, retorno e conferência dos itens.

O mesmo produto pode participar de diversos eventos ao longo do ano, desde que esteja disponível no período solicitado.

---

## Sistema Operacional de Locação para Eventos

O projeto foi concebido como um Sistema Operacional de Locação para Eventos.

Seu objetivo não é reproduzir todas as funcionalidades de um ERP corporativo tradicional.

O foco está nos processos efetivamente utilizados pela operação da empresa, concentrando-se no ciclo completo da locação de produtos para eventos.

Essa abordagem permite que o sistema evolua diretamente a partir das necessidades observadas no dia a dia da operação.

---

## Fluxo Operacional

```text
Cliente
   ↓
Orçamento
   ↓
Pedido
   ↓
Reserva de Estoque
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

Cada etapa possui necessidades específicas de controle e acompanhamento.

---

## Domínios Operacionais

Os principais domínios atendidos atualmente pelo sistema são:

### 👥 Comercial

* Clientes
* Orçamentos
* Pedidos

### 🪑 Catálogo

* Produtos
* Categorias
* Seções
* Subcategorias
* Fotos

### 📦 Operação

* Controle temporal de estoque
* Reservas
* Disponibilidade

### 🧩 Produtos Compostos

* Kits
* Componentes
* Acabamentos
* Grupos de composição

### 🛠️ Pós-Evento

* Conferência
* Lavanderia
* Manutenção
* Ocorrências

---

## Controle Temporal

Uma das características mais importantes do negócio é o controle temporal de estoque.

Ao contrário de uma venda convencional, um produto não deixa de existir após ser utilizado.

Ele apenas permanece indisponível durante determinado período.

Por esse motivo, a disponibilidade de um item depende das reservas existentes para as datas informadas.

Esse conceito é uma das principais regras de negócio implementadas no sistema.

---

## Produtos Compostos

Nem todos os produtos são itens simples.

Existem produtos compostos por diversos componentes internos.

Além disso, alguns kits possuem estrutura própria de montagem e preparação.

Isso exige uma separação entre a visão comercial apresentada ao cliente e a visão operacional utilizada internamente.

---

## Produção

Após a confirmação do pedido, inicia-se o processo operacional de preparação dos itens.

A produção é responsável por organizar, separar e preparar os materiais necessários para cada evento.

Dependendo da natureza do pedido, essa etapa pode envolver montagem de kits, conferência de componentes e preparação logística.

---

## Retorno dos Itens

Após a realização do evento, os itens retornam para a empresa.

Nesse momento podem ocorrer:

* Conferência
* Lavanderia
* Manutenção
* Registro de ocorrências

Esses processos influenciam diretamente a disponibilidade futura dos produtos.

---

## Papel do Sistema

O sistema foi criado para apoiar todas essas etapas, transformando processos operacionais em informações estruturadas e acessíveis.

Seu objetivo principal é aumentar a organização, reduzir controles paralelos e fornecer maior visibilidade sobre a operação da empresa.

---

## Considerações

A compreensão do contexto operacional é fundamental para entender as decisões arquiteturais, as regras de negócio e a modelagem de dados adotadas no projeto.

Grande parte das funcionalidades existentes foi desenvolvida para representar processos reais observados na operação da Mobel Festas.

---

📚 [Voltar para a documentação](../README.md)
