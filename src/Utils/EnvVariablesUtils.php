<?php

namespace Polymind\Aws;

use \GuzzleHttp\json_encode;

class EnvVariablesUtils {

	public static function formatEnvVariables($envVariables) {
		$formattedEnvVariables = '';

		if (is_string($envVariables)) {
			$formattedEnvVariables = str_replace('key', 'name', $envVariables);
		} else {
			if (is_array($envVariables)) {
				$decodedEnvArray = json_encode($envVariables);
				$formattedEnvVariables = str_replace('key', 'name', $decodedEnvArray);
			} else {
				throw new \Exception('The environment variables provided do not have a valid format.');
			}
		}

		return $formattedEnvVariables;
	}
}
