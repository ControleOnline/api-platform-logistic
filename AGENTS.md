## Escopo
- Modulo logistico da API.
- Cobre informacoes logisticas ligadas ao pedido, especialmente `OrderLogistic`.

## Quando usar
- Prompts sobre dados logisticos de pedido, entrega, expedicao e metadados logisticos operacionais.

## Limites
- O pedido continua sendo de `orders`.
- Informacoes de frete, roteamento ou marketplace devem ser tratadas aqui apenas quando forem logisticas de fato.
- O contrato unico de consulta e solicitacao da logistica do pedido pertence a este modulo e orquestra couriers da loja e providers integrados.
- A cotacao precisa validar endereco de coleta e entrega antes de acionar qualquer provider: o destino precisa existir de fato e nao pode ser igual ao ponto de coleta.
