<?php
namespace ControleOnline\Library\Postalcode;

use ControleOnline\Library\Postalcode\Entity\Address;

interface PostalcodeService
{
  public function query(string $postalCode): Address;
}
