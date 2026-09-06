<?php

namespace ControleOnline\Library\Quote\Formula;

use ControleOnline\Library\Quote\Core\AbstractFormula;
use ControleOnline\Library\Quote\Core\DataBag;

class Fixed extends AbstractFormula
{
  public function getTotal(DataBag $tax)
  {
    return $tax->price > $tax->minimumPrice ? $tax->price : $tax->minimumPrice;
  }
}
