<?php
/**
 * SharpSpring Integration plugin for Craft CMS 3.x
 *
 * SharpspringIntegration Service
 *
 * --snip--
 * All of your plugin’s business logic should go in services, including saving data, retrieving data, etc. They
 * provide APIs that your controllers, template variables, and other plugins can interact with.
 *
 * https://craftcms.com/docs/plugins/services
 * --snip--
 *
 * @author    The Refinery
 * @copyright Copyright (c) 2019 The Refinery
 * @link      https://the-refinery.io
 * @package   SharpspringIntegration
 * @since     0.1
 */

namespace sharpspring\SharpSpringIntegration\Services;

use Craft;

use sharpspring\SharpSpringIntegration\Builders\ApiClientBuilder as ApiClientBuilder;
use sharpspring\SharpSpringIntegration\Builders\RequestBuilder as RequestBuilder;

class SharpspringIntegration_ApiClientService extends BaseApplicationComponent
{
    /**
     * This function can literally be anything you want, and you can have as many service functions as you want
     *
     * From any other plugin file, call it like this:
     *
     *     \Craft::$app->sharpspringIntegration->exampleService()
     */
    public function build() {
        return new ApiClientBuilder();
    }

    public function upsertSingleLead($data, $credentialSet, $apiVersion) {
        $getLeadsRequest = (new RequestBuilder())
            ->withApiMethod("getLeads")
            ->pushParam(
                array(
                    "where" => array(
                        "emailAddress" => $data["emailAddress"]
                    )
                )
            );

            $response = \Craft::$app
                ->sharpspringIntegration_apiClient
                ->build()
                ->withCredentialSet($credentialSet)
                ->withRequest($getLeadsRequest)
                ->submit();

            $leadId = null;

            if($response->getResult()["lead"] && count($response->getResult()["lead"]) > 0) {
                $leadId = $response->getResult()["lead"][0]["id"];
            }

            if($leadId) {
                $apiMethod = "updateLeads";
                $leadsParams = array(
                    "objects" => array(
                        array_merge(
                            $data,
                            array("id" => $leadId)
                        )
                    )
                );
            } else {
                $apiMethod = "createLeads";
                $leadsParams = array(
                    "objects" => array(
                        $data
                    )
                );
            }

            $leadsRequest = (new RequestBuilder())
                ->withApiMethod($apiMethod)
                ->pushParam(
                    $leadsParams
                );

            $response = \Craft::$app
                ->sharpspringIntegration_apiClient
                ->build()
                ->withCredentialSet($credentialSet)
                ->withRequest($leadsRequest)
                ->submit();

            return $response;
    }
}
