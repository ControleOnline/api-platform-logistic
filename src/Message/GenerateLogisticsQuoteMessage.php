<?php

namespace ControleOnline\Message;

class GenerateLogisticsQuoteMessage
{
    public function __construct(
        public int $quoteOrderId
    ) {
    }
}
