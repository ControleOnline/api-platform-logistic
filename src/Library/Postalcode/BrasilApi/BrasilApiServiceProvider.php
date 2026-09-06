<?php

namespace ControleOnline\Library\Postalcode\BrasilApi;

use ControleOnline\Library\Postalcode\PostalcodeProvider;
use ControleOnline\Library\Postalcode\PostalcodeService;

class BrasilApiServiceProvider extends PostalcodeProvider
{
  public function getPostalcodeService(): PostalcodeService
  {
    return new BrasilApiService();
  }
}
