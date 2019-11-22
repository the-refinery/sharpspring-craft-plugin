<?php

namespace Craft;

use Craft\SharpSpringIntegration\Responses\NativeFormResponse;

use Guzzle\Http\Client;
use Guzzle\Http\Exception\BadResponseException;

class SharpspringIntegration_NativeFormClientService extends BaseApplicationComponent
{
    public function postData($data, $endpointUrl) {
        if(!isset($endpointUrl)) {
            throw new Exception('Native Form Endpoint url is blank. Cannot proceed. Check your config/sharpspringintegration.php file.');
        }

        $client = new Client();

        try {
            $request = $client->get($endpointUrl."/jsonp");

            foreach ($data as $key => $value)  {
                if($value === true) {
                    $value = "True";
                }
                if($value === false) {
                    $value = "False";
                }
                $request->getQuery()->add($key, $value);
            }

            $ssResponse = $request->send();

            $response = new NativeFormResponse((string) $ssResponse->getBody());
        } catch (\Exception $e) {
            SharpspringIntegrationPlugin::log("There was an issue posting to SharpSpring Native Form endpoint:\n\n".$e->getMessage(), LogLevel::Error, true);
            throw $e;
        }

        return $response;
    }
}
