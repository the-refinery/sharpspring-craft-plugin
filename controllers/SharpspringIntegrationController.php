<?php
/**
 * SharpSpring Integration plugin for Craft CMS
 *
 * SharpspringIntegration Controller
 *
 * --snip--
 * Generally speaking, controllers are the middlemen between the front end of the CP/website and your plugin’s
 * services. They contain action methods which handle individual tasks.
 *
 * A common pattern used throughout Craft involves a controller action gathering post data, saving it on a model,
 * passing the model off to a service, and then responding to the request appropriately depending on the service
 * method’s response.
 *
 * Action methods begin with the prefix “action”, followed by a description of what the method does (for example,
 * actionSaveIngredient()).
 *
 * https://craftcms.com/docs/plugins/controllers
 * --snip--
 *
 * @author    The Refinery
 * @copyright Copyright (c) 2019 The Refinery
 * @link      https://the-refinery.io
 * @package   SharpspringIntegration
 * @since     3.0
 */

namespace sharpspring\SharpSpringIntegration\Controllers;

class SharpspringIntegrationController extends BaseController
{

    /**
     * @var    bool|array Allows anonymous access to this controller's actions.
     * @access protected
     */
    protected $allowAnonymous = array(
        'actionIndex',
        'actionPushAsync',
    );

    /**
     * Handle a request going to our plugin's index action URL, e.g.: actions/sharpspringIntegration
     */
    public function actionIndex()
    {
    }

    public function actionPushAsync()
    {
        $this->requirePostRequest();
        $data = json_decode(\Craft::$app->request->getRawBody(), true);

        // Require a "mapping" key in the JSON body
        if(!array_key_exists("mapping", $data))
        {
            HeaderHelper::setHeader(array('status' => 400));
            $this->returnJson(
                array(
                    "status" => "error",
                    "errors" => array(
                        array("detail" => "Key 'mapping' must be supplied.")
                    )
                )
            );
        }

        // Always look in the custom mappings for any AJAX-based calls
        $config = \Craft::$app
			->config
			->get(
				"customMappings",
				"sharpspringintegration"
            );

        if(!$config)
        {
            HeaderHelper::setHeader(array('status' => 400));
            $this->returnJson(
                array(
                    "status" => "error",
                    "errors" => array(
                        array(
                            "detail" =>
                                "Configuration not found. Please check if the appropriate integration files are set up properly."
                        )
                    )
                )
            );
        }

        // If custom mapping key is not found in configuration, send back an error
        if(!array_key_exists($data["mapping"], $config))
        {
            HeaderHelper::setHeader(array('status' => 400));
            $this->returnJson(
                array(
                    "status" => "error",
                    "errors" => array(
                        array(
                            "detail" =>
                                "Mapping for '{$data["mapping"]}' not found. Please check if the appropriate integration files are set up properly."
                        )
                    )
                )
            );
        }

        $configMapping = \Craft::$app
            ->sharpspringIntegration_mappingConfig
            ->getCustomMapping($data["mapping"]);

        $sharpSpringData = [];

        // Always read from the custom mappings.
        // Example of incoming async request (read via POST body as a JSON String):
        // {
        //     "mapping": "someCustomMapping",
        //     "data": [
        //          {
        //             "email": "someone@somewhere.com",
        //             "iWantToSubscribe": true
        //          },
        //     ]
        // }

        if(array_key_exists("data", $data)) {
            // First iteration of this plugin only expects one data point.
            // It should later include the ability to push multiple data points in one call.
            foreach($data["data"][0] as $key => $value) {
                if(array_key_exists($key, $configMapping["map"])) {
                    $sharpSpringData[$configMapping["map"][$key]] = $value;
                } else {
                    SharpspringIntegrationPlugin::log(
                        "WARNING: incoming async data key #{$key} does not have an associated mapping for configuration {$data['mapping']}",
                        LogLevel::Warning,
                        true
                    );
                }
            }

            try {
                $response = \Craft::$app
                    ->sharpspringIntegration_apiClient
                    ->upsertSingleLead(
                        $sharpSpringData,
                        $configMapping["credentialSet"],
                        null
                    );

                if($response->hasErrors()) {
                    SharpspringIntegrationPlugin::log(
                        "There was an issue posting to sharpspring using custom mapping '{$data['mapping']}': \n\nRequest:\n========\n".json_encode($sharpSpringData)."\n\nError:\n=======\n".json_encode($response->getError())."\n\n",
                        LogLevel::Error,
                        true
                    );
                    HeaderHelper::setHeader(array('status' => 400));
                    $this->returnJson(
                        array(
                            "status" => "error",
                            "errors" => array(
                                array(
                                    "detail" =>
                                        "There was an error with your submission"
                                )
                            )
                        )
                    );
                }
            } catch(\Guzzle\Http\Exception\ClientErrorResponseException $e) {
                SharpspringIntegrationPlugin::log(
                    "There was a client response issue posting to sharpspring:\n========\n{$e->getMessage()}",
                    LogLevel::Error,
                    true
                );
                HeaderHelper::setHeader(array('status' => 500));
                $this->returnJson(
                    array(
                        "status" => "error",
                        "errors" => array(
                            array(
                                "detail" =>
                                    "An unexpected error has occurred while submitting data to SharpSpring. Please check configurations and try again."
                            )
                        )
                    )
                );
            } catch(\Exception $e) {
                SharpspringIntegrationPlugin::log(
                    "There was an unexpected issue posting to sharpspring:\n========\n{$e->getMessage()}",
                    LogLevel::Error,
                    true
                );
                HeaderHelper::setHeader(array('status' => 500));
                $this->returnJson(
                    array(
                        "status" => "error",
                        "errors" => array(
                            array(
                                "detail" =>
                                    "An unexpected error has occurred while submitting data to SharpSpring. Please check configurations and try again."
                            )
                        )
                    )
                );
            }

            $this->returnJson(
                array(
                    "status" => "success",
                    "message" => "Data has been successfully sent."
                )
            );
        } else {
            // Log an error if the incoming data was empty
            SharpspringIntegrationPlugin::log(
                "WARNING: incoming async payload does not have any data to push (using mapping {$data['mapping']})",
                LogLevel::Warning,
                true
            );

            HeaderHelper::setHeader(array('status' => 400));
            $this->returnJson(
                array(
                    "status" => "error",
                    "errors" => array(
                        array(
                            "detail" =>
                                "Incoming data was empty."
                        )
                    )
                )
            );
        }
    }
}