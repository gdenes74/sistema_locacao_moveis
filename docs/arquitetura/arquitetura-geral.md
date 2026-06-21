← [Voltar para a documentação](../README.md)

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
* Grupos de composição

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

## Estrutura Geral do Projeto

```text
sistema_locacao_moveis/
│
├── ajax/
├── assets/
├── config/
├── docs/
├── models/
├── utils/
├── views/
│
└── index.php
```

A organização segue uma arquitetura MVC simplificada desenvolvida em PHP, com separação entre modelos, interfaces e componentes de apoio.

---

## Stack Tecnológica

O sistema foi desenvolvido utilizando tecnologias amplamente adotadas e de fácil manutenção.

* PHP
* MariaDB / MySQL
* HeidiSQL
* HTML
* CSS
* JavaScript
* Git
* GitHub

---

## Escopo do Sistema

O projeto não busca reproduzir todas as funcionalidades de um ERP corporativo.

Seu objetivo é atender de forma especializada a operação de locação de móveis e itens para eventos da Mobel Festas, concentrando-se nos processos efetivamente utilizados pela empresa.

Entre os principais processos atendidos estão:

* Cadastro de clientes
* Cadastro de produtos
* Orçamentos
* Pedidos
* Controle temporal de estoque
* Produção
* Operação de eventos
* Lavanderia
* Manutenção

---

## Considerações

A arquitetura atual representa a evolução gradual do sistema ao longo do tempo.

Novos módulos e funcionalidades são incorporados conforme surgem necessidades reais observadas na operação da Mobel Festas.

A documentação arquitetural continuará evoluindo juntamente com o sistema.

---

📚 [Voltar para a documentação](../README.md)
