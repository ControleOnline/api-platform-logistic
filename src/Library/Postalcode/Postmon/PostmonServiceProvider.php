<?php
namespace ControleOnline\Library\Postalcode\Postmon;

use ControleOnline\Library\Postalcode\PostalcodeProvider;
use ControleOnline\Library\Postalcode\PostalcodeService;

class PostmonServiceProvider extends PostalcodeProvider
{
  public function __construct()
  {

  }

  public function getPostalcodeService(): PostalcodeService
  {
    return new PostmonService();
  }
}
