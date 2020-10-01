<?php

namespace App\Traits;

use Exception;
use GuzzleHttp\Client;

trait ConsumesExternalService
{

    //  send a request to any ServiceLocatorTrait

    //  return a string with the response


    public function performRequest($method, $requestUrl, $formParams = [], $headers = [])
    {

        $client = new Client([
            'base_uri' => $this->baseUri,
        ]);

        if (isset($this->secret)) {
            $headers['Authorization'] = 'Bearer '.$this->secret;
        }

        try {
            $response = $client->request($method, $requestUrl, ['form_params' => $formParams, 'headers' => $headers]);
            return $response->getBody()->getContents();
        } catch (ClientException $e) {
            return $e->getResponse()->getBody()->getContents();
        } catch (GuzzleException $e) {
            Log::error("GuzzleException: " . $e->getMessage());
        } catch (Exception $e) {
            Log::error("Exception: " . $e->getMessage());
        }
    }

}
