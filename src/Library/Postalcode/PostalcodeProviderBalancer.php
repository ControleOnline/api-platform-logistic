<?php

namespace ControleOnline\Library\Postalcode;

use ControleOnline\Library\Postalcode\Entity\Address;
use ControleOnline\Library\Postalcode\BrasilApi\BrasilApiServiceProvider;
use ControleOnline\Library\Postalcode\Exception\InvalidParameterException;
use ControleOnline\Library\Postalcode\Exception\PostalcodeNotFoundException;
use ControleOnline\Library\Postalcode\Exception\ProviderRequestException;
use ControleOnline\Library\Postalcode\GoogleMaps\GoogleMapsServiceProvider;
use ControleOnline\Library\Postalcode\Postmon\PostmonServiceProvider;
use ControleOnline\Library\Postalcode\Viacep\ViacepServiceProvider;

class PostalcodeProviderBalancer
{
  /**
   * Execution order. Must change only if you
   * want to change the priority
   */
  private $providers = [
    'viacep'     => ViacepServiceProvider::class,
    'brasilapi'  => BrasilApiServiceProvider::class,
    'postmon'    => PostmonServiceProvider::class,
    'googlemaps' => GoogleMapsServiceProvider::class,
  ];

  private $currentProvider = null;

  public function search(string $postalCode): Address
  {
    $postalCode = preg_replace('/\D+/', '', $postalCode) ?? '';
    if (strlen($postalCode) !== 8) {
      throw new InvalidParameterException('CEP must have exactly 8 digits');
    }

    try {

      if ($this->currentProvider === null) {
        $this->currentProvider = current($this->providers);
        $this->currentProvider = new $this->currentProvider;
      }

      $address = $this->currentProvider->getAddress($postalCode);
      if (!$address instanceof Address) {
        throw new ProviderRequestException('Provider returned an invalid address');
      }

      return $address;
    } catch (\Exception $e) {
      if ($e instanceof ProviderRequestException) {
        $this->setNextProvider();

        return $this->search($postalCode);
      }

      throw $e;
    }
  }

  public function getProviderCodeName(): string
  {
    return key($this->providers);
  }

  private function setNextProvider(): void
  {
    $nextProvider = next($this->providers);

    if ($nextProvider === false) {
      throw new \Exception('Postalcode services are not available');
    }

    $this->currentProvider = new $nextProvider;
  }
}
