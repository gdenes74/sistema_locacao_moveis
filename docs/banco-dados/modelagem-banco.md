# 🗄️ Modelagem do Banco de Dados

![Database](https://img.shields.io/badge/Database-MariaDB-003545?logo=mariadb&logoColor=white)
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
O banco é administrado com HeidiSQL e utiliza MySQL/MariaDB.

Grupos Principais de Tabelas
👥 Comercial

Tabelas relacionadas ao atendimento, propostas e pedidos confirmados.

clientes
orcamentos
itens_orcamento
pedidos
itens_pedido

Fluxo principal:

clientes
   ↓
orcamentos
   ↓
pedidos
🪑 Catálogo

Tabelas responsáveis pela organização dos produtos disponíveis para locação ou venda.

produtos
secoes
categorias
subcategorias
fotos
cores_padrao

Essa estrutura permite classificar produtos, organizar fotos e facilitar a consulta dos itens.

🧩 Produtos Compostos

Tabelas utilizadas para representar produtos formados por componentes internos, kits ou conjuntos.

produto_composicao
produto_conjunto_grupos
produto_acabamento

Esse grupo é importante porque alguns produtos possuem uma visão comercial para o cliente, mas dependem de componentes operacionais internos.

📦 Operação e Estoque

Tabelas relacionadas ao controle de disponibilidade, movimentações e processos após o retorno dos itens.

movimentacoes_estoque
estoque_temporal
lavanderia
itens_lavanderia
manutencao
itens_manutencao
ocorrencias_itens

Este grupo representa uma das partes mais importantes do sistema: o controle temporal de estoque.

Um produto pode estar disponível em uma data e indisponível em outra, dependendo das reservas existentes.

⚙️ Sistema e Configurações

Tabelas de apoio utilizadas para usuários, textos padrão, sequências e configurações internas.

usuarios
configuracoes_textos
numeracao_sequencial
numeracao_sequencial_historico
sequencias

Essas tabelas ajudam a manter padronização, rastreabilidade e organização interna do sistema.

Conceito de Estoque Temporal

Em uma operação de locação, o estoque não é reduzido permanentemente como em uma venda tradicional.

O item permanece pertencendo à empresa, mas fica indisponível durante o período do evento.

Por isso, o banco precisa armazenar informações relacionadas a:

período do evento;
reservas existentes;
quantidade disponível;
movimentações;
retorno dos itens;
possíveis ocorrências.
Relação com as Regras de Negócio

A modelagem do banco foi construída a partir das necessidades reais da operação.

Alguns exemplos:

Um cliente pode ter vários orçamentos.
Um orçamento pode ser convertido em pedido.
Um pedido pode conter vários itens.
Um produto pode participar de vários pedidos em períodos diferentes.
Um produto pode ser simples ou composto.
Um item retornado pode gerar lavanderia, manutenção ou ocorrência.
ERD

O ERD completo deve ser mantido em:

docs/diagramas

Preferencialmente em formatos:

PNG
PDF
Mermaid ou PlantUML, se aplicável
Observação

Este modelo de dados continua em evolução conforme novas necessidades operacionais surgem.

A prioridade da modelagem é representar de forma prática e compreensível os processos reais da Mobel Festas.


Esse arquivo agora conecta o banco com o negócio, não fica só uma lista de tabelas.