<?php

namespace App\Services;

use App\Traits\ConsumesExternalService;


class GatewayService
{

  use ConsumesExternalService;

  public $baseUri;

  public $secret;


  public function __construct()
  {
    $this->baseUri = config('services.gateway.base_uri');
    $this->secret = config('services.bidbonds.secret');
  }

    /**
     * Get company cost from the gateway
     */

  public function getCompanyCost()
  {
    return $this->performRequest('GET', '/v1/api/company/get-cost');
  }

  
}
