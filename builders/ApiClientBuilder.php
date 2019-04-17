<?php
namespace Craft\SharpSpringIntegration\Builders;

// use Craft\Craft as Craft;
use Craft;
use Guzzle\Http\Client;
use Guzzle\Http\Exception\BadResponseException;
use Craft\SharpspringIntegrationPlugin;
use Craft\LogLevel;
use Craft\SharpSpringIntegration\Responses\ApiResponse;

class ApiClientBuilder
{
  public $apiVersion = "1.2";
  public $credentialSet = "*";
  public $request;

  public function withApiVersion($version) {
    $this->apiVersion = $version;
    return $this;
  }

  public function withCredentialSet($credentialSet) {
    $this->credentialSet = $credentialSet;
    return $this;
  }

  public function withRequest($request) {
    $this->request = $request;
    return $this;
  }

  public function submit() {
    $allCredentials = Craft\Craft::app()->config->get('credentialSets', 'sharpspringintegration');
    $useCredentials = $allCredentials[$this->credentialSet];

    if(!array_key_exists('accountID', $useCredentials)) {
      throw new \Exception("ERROR: SharpSpring credentialSets for '".$this->credentialSet."' does not have accountID set. Please refer to the documention on how to set these up.");
    }

    if(!array_key_exists('secretKey', $useCredentials)) {
      throw new \Exception("ERROR: SharpSpring credentialSets for '".$this->credentialSet."' does not have secretKey set. Please refer to the documention on how to set these up.");
    }

    $client = new Client();
    $client->setBaseUrl($this->getApiRootUrl());
    $client->setDefaultOption(
      'query',
      [
        'accountID' => $useCredentials['accountID'],
        'secretKey' => $useCredentials['secretKey']
      ]
    );

    try {
      $response = $client->post(
        null,
        null,
        $this->request->toJson()
      )->send();

      $body = $response->getBody(true);
      $data = json_decode($body, true);
    } catch (\Exception $e) {
      SharpspringIntegrationPlugin::log("There was an issue obtaining/parsing data from SharpSpring's API:\n\n".$e->getMessage(), LogLevel::Error, true);
      throw $e;
    }

    return new ApiResponse($data);
  }

  private function getApiRootUrl() {
    return 'https://api.sharpspring.com/pubapi/v'.$this->apiVersion.'/';
  }
}

