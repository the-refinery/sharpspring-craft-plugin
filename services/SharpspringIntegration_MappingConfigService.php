<?php

namespace Craft;

class SharpspringIntegration_MappingConfigService extends BaseApplicationComponent
{
	public function setup() {
		$this->setupEntryMappings();
		$this->setupFreeformMappings();
	}


  public function getCustomMapping($mappingKey) {
		$mainMapping = craft()
			->config
			->get(
				"customMappings",
				"sharpspringintegration"
			);

		if(is_null($mainMapping)) {
			throw new Exception("customMapping not found in configuration file.");
		}

		if(is_null($mainMapping[$mappingKey])) {
			throw new Exception("Custom SharpSpring mapping for '{$mappingKey}' not found. Please check integration configuration file for details.");
		}

		$customMapping = $mainMapping[$mappingKey];

		$newMapping = [];

		$newMapping["credentialSet"] = $customMapping["credentialSet"] ?? "*";
		$newMapping["map"] = [];

		foreach($customMapping["map"] as $key => $value) {
			$newMapping["map"][$key] = $value;
		}

		return $newMapping;

	}

	private function setupFreeformMappings() {
		$freeformMappingSetup = craft()
			->config
			->get(
				"freeformSubmissionMappings",
				"sharpspringintegration"
			);

		if(!is_null($freeformMappingSetup)) {
			foreach($freeformMappingSetup as $freeformHandle => $freeformHandleConfig) {
				$event = "freeform_submissions.onBeforeSave";

				if(array_key_exists('event', $freeformHandleConfig)) {
					$event = $freeformHandleConfig['event'];
				}

				craft()->on(
					$event,
					function(Event $event) use ($freeformHandle, $freeformHandleConfig) {
						$submissionModel = $event->params["model"];
						$isNewEntry = $event->params["isNew"];

						if($submissionModel->getForm()->handle == $freeformHandle) {
							$shouldPost = true;
							$fireOnCreate = true;
							$fireOnUpdate = false;

							if(array_key_exists('fireOnCreate', $freeformHandleConfig)) {
								$fireOnCreate = $freeformHandleConfig['fireOnCreate'];
							}

							if(array_key_exists('fireOnUpdate', $freeformHandleConfig)) {
								$fireOnUpdate = $freeformHandleConfig['fireOnUpdate'];
							}

							// Don't post if this is an entry update and we shouldn't fire on update
							if(!$isNewEntry && !$fireOnUpdate) {
								$shouldPost = false;
							}

							if($shouldPost) {
								$credentialSet = "*";
								if(array_key_exists('credentialSet', $freeformHandleConfig)) {
									$credentialSet = $freeformHandleConfig['credentialSet'];
								}

								$sharpSpringData = [];

								if(array_key_exists('map', $freeformHandleConfig)) {
									foreach($freeformHandleConfig['map'] as $entryField => $sharpSpringKey) {
										$fieldType = $submissionModel->getForm()->getLayout()->getFieldByHandle($entryField)->getType();

										switch($fieldType) {
											case "email":
												// NOTE: Freeform stores email addresses as an array of email addresses. Most of the time you only
												// want the first one.
												$values = $submissionModel->__get($entryField)->getValue();
												if(!empty($values)) {
													$sharpSpringData[$sharpSpringKey] = $values[0];
												}
												break;
											case "text":
												$sharpSpringData[$sharpSpringKey] = $submissionModel->__get($entryField)->getValue();
												break;
											case "textarea":
												$sharpSpringData[$sharpSpringKey] = $submissionModel->__get($entryField)->getValue();
												break;
											case "select":
												$sharpSpringData[$sharpSpringKey] = $submissionModel->__get($entryField)->getValue();
												break;
											case "hidden":
												$sharpSpringData[$sharpSpringKey] = $submissionModel->__get($entryField)->getValue();
												break;
											case "checkbox_group":
												$sharpSpringData[$sharpSpringKey] = implode(",", $submissionModel->__get($entryField)->getValue());
												break;
											case "checkbox":
												if($submissionModel->__get($entryField)->getValue()) {
													$value = true;
												} else {
													$value = false;
												}

												$sharpSpringData[$sharpSpringKey] = $value;
												break;
											default:
												SharpspringIntegrationPlugin::log("WARNING: Freeform field '".$entryField."' type '".$fieldType."' for form handle '".$freeformHandle."' is not a known type to process.", LogLevel::Warning, true);
										}
									}
								}

								$publishMethod = $freeformHandleConfig["publishMethod"] ?? "api-lead";

								switch($publishMethod) {
									case "native-form":
										$response = craft()
											->sharpspringIntegration_nativeFormClient
											->postData(
												$sharpSpringData,
												$freeformHandleConfig["nativeFormEndpoint"]
											);
										break;
									case "api-lead":
										$response = craft()
											->sharpspringIntegration_apiClient
											->upsertSingleLead(
												$sharpSpringData,
												$credentialSet,
												null
											);
										break;
									default:
										SharpspringIntegrationPlugin::log("WARNING: API Publishing method '".$publishMethod."' is not a valid publish type.", LogLevel::Warning, true);
								}

								if($response->hasErrors()) {
									SharpspringIntegrationPlugin::log(
										"There was an error posting data to SharpSpring from Freeform '".$freeformHandle."': \n\nRequest:\n========\n\nError:\n=======\n".json_encode($response->getError())."\n\n",
										LogLevel::Error,
										true
									);

									throw new Exception("There was an error posting data to CRM. Please see logs for details.");
								}
							}
						}
					}
				);
			}
		}
	}

	private function setupEntryMappings() {
		$entryMappingSetup = craft()
			->config
			->get(
				"craftEntryMappings",
				"sharpspringintegration"
			);

		if(!is_null($entryMappingSetup)) {
			foreach($entryMappingSetup as $entryTypeHandle => $entryTypeHandleConfig) {
				$event = "entries.onBeforeSaveEntry";

				if(array_key_exists('event', $entryTypeHandleConfig)) {
					$event = $entryTypeHandleConfig['event'];
				}

				craft()->on(
					$event,
					function(Event $event) use ($entryTypeHandle, $entryTypeHandleConfig) {
						$entry = $event->params["entry"];
						$isNewEntry = $event->params["isNewEntry"];

						if($entry->type->name == $entryTypeHandle) {
							$shouldPost = true;
							$fireOnCreate = true;
							$fireOnUpdate = false;

							if(array_key_exists('fireOnCreate', $entryTypeHandleConfig)) {
							   $fireOnCreate = $entryTypeHandleConfig['fireOnCreate'];
							}

							if(array_key_exists('fireOnUpdate', $entryTypeHandleConfig)) {
							   $fireOnUpdate = $entryTypeHandleConfig['fireOnUpdate'];
							}

							// Don't post if this is an entry update and we shouldn't fire on update
							if(!$isNewEntry && !$fireOnUpdate) {
								$shouldPost = false;
							}

							if($shouldPost) {
								$credentialSet = "*";
								if(array_key_exists('credentialSet', $entryTypeHandleConfig)) {
									$credentialSet = $entryTypeHandleConfig['credentialSet'];
								}

								$sharpSpringData = [];

								if(array_key_exists('map', $entryTypeHandleConfig)) {
									foreach($entryTypeHandleConfig['map'] as $entryField => $sharpSpringKey) {
										$fieldType = craft()->fields->getFieldByHandle($entryField)->type;

										switch($fieldType) {
											case "PlainText":
												$sharpSpringData[$sharpSpringKey] = $entry->getFieldValue($entryField);
												break;
											case "Checkboxes":
												//TODO: Figure out how to set up multiple checkboxes via configuration.
												$fieldValue = $entry->getFieldValue($entryField);
												if (array_key_exists(0, $fieldValue)) {
													$sharpSpringData[$sharpSpringKey] = true;
												}
												break;
											case "Number":
												$sharpSpringData[$sharpSpringKey] = $entry->getFieldValue($entryField);
												break;
											case "Dropdown":
												$sharpSpringData[$sharpSpringKey] = $entry->getFieldValue($entryField)->value;
												break;
										}
									}
								}

								$response = craft()
									->sharpspringIntegration_apiClient
									->upsertSingleLead(
										$sharpSpringData,
										$credentialSet,
										null
									);

								if($response->hasErrors()) {
									SharpspringIntegrationPlugin::log(
										"There was an error posting data to SharpSpring from Craft Entry'".$entryTypeHandle."': \n\nRequest:\n========\n".json_encode($sharpSpringData)."\n\nError:\n=======\n".json_encode($response->getError())."\n\n",
										LogLevel::Error,
										true
									);

									throw new Exception("There was an error posting data to CRM. Please see logs for details.");
								}
							}
						}
					}
				);
			}
		}
	}
}