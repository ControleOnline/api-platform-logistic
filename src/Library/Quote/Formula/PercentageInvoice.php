<?php

namespace ControleOnline\Library\Quote\Formula;

use ControleOnline\Library\Quote\Core\AbstractFormula;
use ControleOnline\Library\Quote\Core\DataBag;

class PercentageInvoice extends AbstractFormula
{
  public function getTotal(DataBag $tax)
  {
    $total = ($tax->parent()->params->productTotalPrice / 100) * $tax->price;

    return $total > $tax->minimumPrice ? $total : $tax->minimumPrice;
  }
}
