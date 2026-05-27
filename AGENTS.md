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

## Marketplace providers
- `QuoteLogisticsService` e `OrderLogisticsService` devem resolver providers de marketplace por contrato/registry, nao por concatenacao de nome de classe.
- Leituras de estado salvo e snapshot devem vir de contratos compartilhados do marketplace; o service de logistica nao deve conhecer classes concretas para esse despacho.
- Quando novas consultas de apoio forem necessarias, a leitura persistente deve continuar em repositorio ou resolver dedicado; service de logistica nao deve abrigar SQL novo.
