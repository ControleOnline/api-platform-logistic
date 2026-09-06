<?php

namespace ControleOnline\Library\Postalcode\BrasilApi;

use ControleOnline\Library\Postalcode\Entity\Address;
use ControleOnline\Library\Postalcode\Exception\InvalidParameterException;
use ControleOnline\Library\Postalcode\Exception\ProviderRequestException;
use ControleOnline\Library\Postalcode\PostalcodeService;
use GuzzleHttp\Client;

/**
 * BrasilAPI CEP lookup — https://brasilapi.com.br/docs#tag/CEP
 */
class BrasilApiService implements PostalcodeService
{
  private string $endpoint = 'https://brasilapi.com.br/api/cep/v1';

  public function query(string $postalCode): Address
  {
    if (!$this->isCEP($postalCode)) {
      throw new InvalidParameterException('CEP string is not valid. Acceptable format: 16058741');
    }

    $result = $this->search($postalCode);

    return (new Address)
      ->setCountry('Brasil')
      ->setState((string) ($result->state ?? ''))
      ->setUF((string) ($result->state ?? ''))
      ->setCity((string) ($result->city ?? ''))
      ->setDistrict((string) ($result->neighborhood ?? ''))
      ->setStreet((string) ($result->street ?? ''))
      ->setNumber('')
      ->setPostalCode($postalCode)
      ->setComplement('')
      ->setProvider('brasilapi');
  }

  private function search(string $cep): object
  {
    try {
      $client = new Client([
        'verify' => false,
        'timeout' => 8,
        'connect_timeout' => 4,
      ]);
      $response = $client->request('GET', sprintf('%s/%s', $this->endpoint, $cep));
      $result = json_decode((string) $response->getBody());

      if (!is_object($result)) {
        throw new ProviderRequestException('BrasilAPI response format error');
      }

      if (!empty($result->message) && empty($result->cep) && empty($result->city)) {
        throw new ProviderRequestException('CEP not found');
      }

      if (empty($result->city) && empty($result->street) && empty($result->cep)) {
        throw new ProviderRequestException('BrasilAPI response format error');
      }

      return $result;
    } catch (ProviderRequestException $e) {
      throw $e;
    } catch (\Exception $e) {
      throw new ProviderRequestException($e->getMessage());
    }
  }

  private function isCEP(string $input): bool
  {
    return preg_match('/^\d{8}$/', $input) === 1;
  }
}
