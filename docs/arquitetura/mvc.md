← [Voltar para a documentação](../README.md)

# 🧱 Estrutura MVC

![MVC](https://img.shields.io/badge/Pattern-MVC-blue)
![PHP](https://img.shields.io/badge/PHP-Application-777BB4)

## Introdução

O projeto utiliza uma organização inspirada no padrão MVC (Model-View-Controller).

A implementação adotada é simplificada e não possui uma camada Controller formalmente separada, concentrando parte do fluxo da aplicação em arquivos de entrada e nas próprias rotinas dos módulos.

A estrutura foi adaptada às necessidades do sistema e evoluiu de forma incremental ao longo do desenvolvimento.

O objetivo principal sempre foi manter uma organização simples, compreensível e de fácil manutenção.

---

## Visão Geral

```text
Usuário
   ↓
Views
   ↓
Models
   ↓
Banco de Dados
Embora inspirado no padrão MVC tradicional, o sistema utiliza uma abordagem simplificada adequada ao porte e às necessidades atuais da aplicação.

Estrutura do Projeto
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

Cada diretório possui responsabilidades específicas dentro da aplicação.

Views

Localização:

/views

Responsáveis por:

Interfaces do sistema
Formulários
Listagens
Impressões
Interação com usuários

Principais módulos:

Clientes
Produtos
Orçamentos
Pedidos
Dashboard
Configurações
Models

Localização:

/models

Responsáveis por:

Comunicação com o banco de dados
Consultas
Inserções
Atualizações
Regras relacionadas às entidades

Principais models:

Cliente
Produto
Orcamento
Pedido
Categoria
Secao
Subcategoria
EstoqueMovimentacao
ConfiguracaoTexto
NumeracaoSequencial
Configuração

Localização:

/config

Responsável por:

Conexão com banco de dados
Configurações gerais
Parâmetros do sistema
Utilitários

Localização:

/utils

Responsáveis por:

Funções auxiliares
Upload de arquivos
Helpers
Recursos de apoio à aplicação
Recursos Estáticos

Localização:

/assets

Contém:

Imagens
Uploads
Logos
Arquivos estáticos
AJAX

Localização:

/ajax

Utilizado para consultas assíncronas e recursos auxiliares da interface.

Exemplo:

Busca dinâmica de clientes
Papel do index.php

O arquivo index.php atua como ponto de entrada principal da aplicação.

Ele é responsável por receber as requisições e direcionar o fluxo para os módulos correspondentes.

Considerações

Embora inspirado em MVC, o projeto foi desenvolvido de forma pragmática e incremental.

O foco principal sempre foi apoiar a operação real da empresa, mantendo o código compreensível, funcional e passível de evolução contínua.

A estrutura atual representa a evolução natural do sistema ao longo do tempo e poderá ser ajustada conforme novas necessidades surgirem.
📚 [Voltar para a documentação](../README.md)