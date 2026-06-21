← [Voltar para a documentação](../README.md)

# 📋 Regras de Negócio

Este documento registra as principais regras de negócio identificadas durante a operação da Mobel Festas e implementadas no Sistema Operacional de Locação para Eventos.

Grande parte dessas regras foi construída a partir da experiência prática da empresa e não de documentação formal pré-existente.

---

## 🎯 Objetivo

O objetivo deste documento é registrar comportamentos e restrições importantes do sistema, facilitando sua manutenção e evolução futura.

---

## 📄 Orçamentos e Pedidos

### Orçamentos

* Um orçamento representa uma proposta comercial.
* Um orçamento pode conter múltiplos produtos e serviços.
* O orçamento não reserva estoque automaticamente.
* O orçamento pode ser alterado durante o processo de negociação.

### Pedidos

* Um pedido representa uma operação confirmada.
* Um pedido normalmente é gerado a partir da aprovação de um orçamento.
* O pedido passa a fazer parte do planejamento operacional.
* O pedido pode possuir diferentes status conforme sua etapa de execução.

---

## 🪑 Produtos

### Cadastro de Produtos

* Produtos podem possuir fotos.
* Produtos podem ser organizados por categorias, seções e subcategorias.
* Produtos podem possuir informações operacionais específicas.

### Produtos Compostos

* Um produto pode ser composto por diversos componentes internos.
* Um kit pode agrupar vários itens em uma única unidade comercial.
* Componentes internos podem ser utilizados apenas para controle operacional.
* A estrutura interna de um produto nem sempre é apresentada ao cliente.

---

## 📦 Controle Temporal de Estoque

O controle temporal é uma das regras centrais do sistema.

Diferentemente de uma venda convencional, os produtos retornam ao estoque após o evento.

Por esse motivo:

* A disponibilidade depende das datas informadas.
* Reservas existentes devem ser consideradas durante a consulta.
* Um mesmo item pode estar disponível em uma data e indisponível em outra.
* O estoque precisa considerar períodos de utilização simultânea.

Essa regra é fundamental para evitar conflitos de reserva.

---

## 🛠️ Produção

Após a confirmação do pedido inicia-se o processo operacional.

A produção deve:

* Considerar os itens reservados para o evento.
* Organizar a separação dos materiais.
* Apoiar a montagem de kits e conjuntos.
* Preparar os itens para entrega.

Dependendo do tipo de produto, podem existir etapas adicionais de preparação.

---

## 🚚 Entrega e Retorno

Após a preparação ocorre a entrega dos itens para o evento.

Quando os produtos retornam à empresa podem ser necessários processos adicionais como:

* Conferência
* Lavanderia
* Manutenção
* Registro de ocorrências

Essas atividades influenciam diretamente a disponibilidade futura dos itens.

---

## 💰 Pagamentos

O sistema prevê registros funcionais de pagamentos vinculados aos pedidos.

Entretanto, o objetivo não é implementar um módulo financeiro corporativo completo.

O foco está apenas nos controles necessários para apoiar a operação de locação.

Não fazem parte do escopo atual:

* Contas a pagar
* Gestão de fornecedores
* Fluxo de caixa completo
* DRE
* Contabilidade

---

## 📌 Considerações

As regras documentadas neste arquivo representam apenas parte do conhecimento operacional existente na empresa.

Novas regras podem ser incorporadas conforme o sistema evolui e novos processos são formalizados.

---

📚 [Voltar para a documentação](../README.md)
