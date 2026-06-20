# 🏗️ Arquitetura Geral

![PHP](https://img.shields.io/badge/PHP-8%2B-777BB4?logo=php\&logoColor=white)
![MariaDB](https://img.shields.io/badge/MariaDB-003545?logo=mariadb\&logoColor=white)
![Architecture](https://img.shields.io/badge/Architecture-MVC-blue)

## Visão Geral

O Sistema Operacional de Locação para Eventos da Mobel Festas foi desenvolvido para apoiar processos reais da operação da empresa.

Diferentemente de sistemas genéricos, sua arquitetura evoluiu gradualmente a partir das necessidades observadas no dia a dia da locação de móveis e itens para eventos.

O objetivo principal não é reproduzir um ERP tradicional, mas representar digitalmente os fluxos operacionais da empresa.

---

## Fluxo Principal

```text
Cliente
   ↓
Orçamento
   ↓
Pedido
   ↓
Produção
   ↓
Evento
   ↓
Retorno
```

A maior parte das funcionalidades do sistema está organizada em torno desse fluxo.

---

## Principais Módulos

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
* Grupos

### 🖨️ Documentação

* Impressões comerciais
* Impressões operacionais
* Textos padrão

### 🧺 Evolução Contínua

* Lavanderia
* Manutenção
* Ocorrências
* Melhorias operacionais

---

## Princípios do Projeto

O sistema foi desenvolvido seguindo alguns princípios simples:

* Resolver problemas reais da operação
* Manter baixo custo de implantação
* Utilizar tecnologias amplamente disponíveis
* Facilitar manutenção e evolução contínua
* Preservar simplicidade sempre que possível

---

## Tecnologia

A solução utiliza:

* PHP
* MariaDB / MySQL
* HeidiSQL
* HTML
* CSS
* JavaScript

---

## Considerações

A arquitetura atual representa a evolução gradual do sistema ao longo do tempo.

Novos módulos e funcionalidades são incorporados conforme surgem necessidades reais da operação da Mobel Festas.
