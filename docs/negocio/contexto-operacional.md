# 🎪 Contexto Operacional

![Business](https://img.shields.io/badge/Business-Event%20Rental-success)
![Operation](https://img.shields.io/badge/Focus-Operational%20Control-blue)
![Real Process](https://img.shields.io/badge/Based%20on-Real%20Operation-purple)

## Objetivo

Este documento apresenta o contexto operacional que motivou o desenvolvimento do Sistema Operacional de Locação para Eventos da Mobel Festas.

Compreender o funcionamento da operação é fundamental para entender as decisões arquiteturais e as regras de negócio implementadas no sistema.

---

## O Negócio

A Mobel Festas atua na locação de móveis, toalhas e itens para eventos.

Diferentemente de uma venda tradicional, a locação envolve controle de datas, disponibilidade, logística, produção, entrega, retorno e conferência dos itens.

O mesmo produto pode participar de diversos eventos ao longo do ano, desde que esteja disponível no período solicitado.

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

## Controle Temporal

Uma das características mais importantes do negócio é o controle temporal de estoque.

Ao contrário de uma venda comum, um produto não deixa de existir após ser utilizado.

Ele apenas fica indisponível durante determinado período.

Por esse motivo, a disponibilidade de um item depende das reservas existentes para as datas informadas.

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
