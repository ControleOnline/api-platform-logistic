<?php

namespace ControleOnline\Library\Postalcode;

use ControleOnline\Library\Postalcode\Entity\Address;
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
    'postmon'    => PostmonServiceProvider::class,
    'googlemaps' => GoogleMapsServiceProvider::class,
  ];

  private $currentProvider = null;

  public function search(string $postalCode): Address
  {
    try {

      if ($this->currentProvider === null) {
        $this->currentProvider = current($this->providers);
        $this->currentProvider = new $this->currentProvider;
      }

      return $this->currentProvider->getAddress($postalCode);
    } catch (\Exception $e) {
      if ($e instanceof ProviderRequestException) {
        $this->setNextProvider();

        return $this->search($postalCode);
      }
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
