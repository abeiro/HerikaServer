<?php

if (!function_exists('herikaNormalizeFunctionResponseArguments')) {
    function herikaNormalizeFunctionResponseArguments(array $properties, array $parsedResponse): array
    {
        if (count($properties) !== 1) {
            return $parsedResponse;
        }

        $parameterName = array_key_first($properties);
        if (
            !is_string($parameterName)
            || $parameterName === 'target'
            || array_key_exists($parameterName, $parsedResponse)
            || !array_key_exists('target', $parsedResponse)
        ) {
            return $parsedResponse;
        }

        // Dialogue JSON exposes one generic target field even when the action
        // schema gives its sole argument a more specific name.
        $parsedResponse[$parameterName] = $parsedResponse['target'];
        return $parsedResponse;
    }
}
