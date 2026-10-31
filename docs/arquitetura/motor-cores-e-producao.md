# Motor de cores, produtos compostos e produção

Estado documentado em 30/10/2026. A aplicação roda localmente em PHP/MariaDB
e ainda está em desenvolvimento. Este documento descreve o fluxo do código;
cadastros e fotos aprovadas são dados da instalação, não arquivos deste Git.

## Separação das regras

| Caso | Produto comercial | Recurso físico que limita a disponibilidade | Foto na linha |
| --- | --- | --- | --- |
| Produto com capa removível | Variante composta por cor | Estrutura, assento/colchão e capa cadastrada | Variante ou prévia aprovada |
| Forração sob demanda | Composto `Cor A Definir` | Estrutura compartilhada | Prévia contextual do mestre e da amostra |
| Cor nova de capa | Composto ainda sem capa daquela cor | Componentes existentes; capa a produzir | Serviço até haver foto/capa aprovadas |

`SIMPLES` e `COMPONENTE` representam patrimônio físico. `COMPOSTO` representa
uma opção comercial e `SERVICO` não acrescenta estoque. A cor de uma forração
sob demanda não cria capa física nem uma segunda estrutura.

## Encadeamento

```mermaid
flowchart LR
  A[Produto e composição] --> B[Família e vínculos editáveis]
  B --> C[Mestre, máscara e amostra aprovados]
  C --> D[Orçamento ou pedido: cor e prévia contextual]
  A --> E[Consulta temporal dos componentes]
  D --> E
  E --> F[Pedido confirmado]
  F --> G[Pendência de produção]
  G --> H[Ficha e cálculo de tecido]
  H --> I[Produção e conferência física]
  I --> J[Atualização manual do estoque]
```

### Cadastro e publicação visual

- `views/produtos/configurar_motor.php` e `models/MotorCores.php`: configuração
  de família, produto genérico, acabamento, preço e vínculos editáveis.
- `views/produtos/cores_imagens.php`, `novo_mestre.php` e
  `preparar_fotos.php`: seleção do produto, mestre, máscara e amostras.
- `assets/js/motor_recoloracao.js`: prévia da cor apenas nas áreas marcadas.
- `views/produtos/aprovar_previa_motor.php` e `publicar_foto_motor.php`:
  revisão e publicação como ações separadas. Imagem derivada não substitui
  `produtos.foto_path` durante a simples prévia.

### Uso comercial

- `assets/js/forracao_orcamento_pedido.js`: escolha contextual de cores para
  forração sem capa física.
- `assets/js/variantes_capa_orcamento_pedido.js`: sugestão e seleção de
  variantes com capa.
- `views/orcamentos/` e `views/pedidos/`: criação, edição, visualização e
  impressão carregam as regras de cor. A observação permanece parte do item.
- `assets/js/resumo_disponibilidade_componentes.js`: apresenta a falta por
  componente em vez de um número artificial para tecido sob demanda.

### Produção

- `models/ProducaoPendente.php`: cria e sincroniza fichas, calcula faltas,
  mantém status e histórico. Sincronizar novamente não deve duplicar fichas.
- `views/producao/index.php`, `create.php` e `edit.php`: lista, cadastro
  manual e acompanhamento. Status `PRODUZIDA` não altera o estoque.
- `models/ReferenciaCorteProducao.php`: busca ficha técnica do produto,
  componente ou serviço e localiza a foto mestre aprovada.
- `models/PlanoCorteTecido.php`: interpreta medidas de corte explícitas e
  calcula trechos por largura útil e quantidade.
- `views/producao/calculadora_tecido.php` e `ficha_impressao.php` compartilham
  `plano_corte_profissional.php` para tela e impressão.
- `views/producao/plano_consolidado.php`: soma consumos calculados por material
  e cor; não otimiza retalhos de fichas diferentes.

## Reproduzibilidade e segurança

As configurações de banco são locais e ignoradas pelo Git. As fotos em
`assets/uploads/` e os backups também ficam fora do repositório. A migração
aditiva de pendências está em `tests/instalar_producao_pendente.php`; executá-la
exige backup identificável e banco separado da produção. Parte do esquema do
motor de cores foi criada na instalação local e ainda precisa de migração
versionada para uma implantação limpa.

Não execute os scripts de integração em uma base sem backup: alguns criam
registros de teste dentro de transações. A checagem de sintaxe e o teste JS de
recoloração podem rodar sem modificar o banco.

## Validação pendente

Conferir, por família, cadastro, mestre aprovado, amostras, disponibilidade,
edição de orçamento/pedido e impressão. Os produtos que só têm consumo linear
de tecido não fornecem um desenho físico do molde até que suas peças de corte
sejam especificadas na ficha. A calculadora é uma referência de planejamento:
sentido do tecido, emendas, folgas e cortes finais exigem conferência humana.
