<?php
namespace ControleOnline\Library\Postalcode\GoogleMaps;

use ControleOnline\Library\Postalcode\PostalcodeProvider;
use ControleOnline\Library\Postalcode\PostalcodeService;

class GoogleMapsServiceProvider extends PostalcodeProvider
{
  public function __construct()
  {
  }

  public function getPostalcodeService(): PostalcodeService
  {
    return new GoogleMapsService();
  }
}
