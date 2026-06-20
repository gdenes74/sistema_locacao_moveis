# Regras de Negócio

Este documento registra regras importantes do sistema.

## Orçamentos e pedidos

- Um orçamento representa uma proposta comercial.
- Um orçamento aprovado pode ser convertido em pedido.
- O pedido representa uma operação confirmada.
- O pedido pode ter status próprios conforme a etapa operacional.

## Produtos

- Produtos podem ser cadastrados com fotos, categorias e subcategorias.
- Produtos podem ter componentes internos.
- Produtos podem compor kits ou conjuntos.
- Nem todo item interno precisa aparecer da mesma forma para o cliente.

## Estoque temporal

- O estoque deve considerar o período do evento.
- A disponibilidade de um item depende de reservas já existentes no mesmo intervalo.
- Um item pode estar disponível em uma data e indisponível em outra.

## Produção

- A produção deve considerar os itens do pedido.
- A separação precisa apoiar a preparação do evento.
- Alguns itens exigem conferência, manutenção ou lavanderia após o retorno.

## Pagamentos

- O sistema prevê registros funcionais de pagamentos vinculados aos pedidos.
- O objetivo não é criar um módulo financeiro completo com contas a pagar, fornecedores ou DRE.
