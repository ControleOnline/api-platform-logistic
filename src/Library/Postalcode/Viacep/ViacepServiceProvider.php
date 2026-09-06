<?php
namespace ControleOnline\Library\Postalcode\Viacep;

use ControleOnline\Library\Postalcode\PostalcodeProvider;
use ControleOnline\Library\Postalcode\PostalcodeService;

class ViacepServiceProvider extends PostalcodeProvider
{
  public function __construct()
  {

  }

  public function getPostalcodeService(): PostalcodeService
  {
    return new ViacepService();
  }
}
