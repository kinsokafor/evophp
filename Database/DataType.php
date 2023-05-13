<?php 

namespace EvoPhp\Database;

use EvoPhp\Api\Operations;
use EvoPhp\Api\stdClass;

/**
 * summary
 */
Trait DataType
{
    /**
     * summary
     */
    public function __construct()
    {
        
    }

    public function evaluateData($value) {
    	$output = new stdClass();
    	switch (gettype($value)) {
            case "boolean":
                    $output->value = $value ? 1 : 0;
                    $output->realType = "boolean";
                    $output->valueType = "i";
                    $output->field = "int";
                break;

            case "integer":
                    $output->value = $value;
                    $output->realType = "int";
                    $output->valueType = "i";
                    $output->field = "int";
                break;

            case "double":
            case "float":
            		$output->value = $value;
                    $output->realType = "double";
                    $output->valueType = "d";
                    $output->field = "double";
                break;

            case "array":
            		$output->value = Operations::serialize($value);
                    $output->realType = "array";
                    $output->valueType = "s";
                    $output->field = false;
                break;

            case "object":
                    $value = (array) $value;
                    $output->value = Operations::serialize($value);
                    $output->realType = "object";
                    $output->valueType = "s";
                    $output->field = false;
                break;

            case "blob":
            		$output->value = $value;
                    $output->realType = "blob";
                    $output->valueType = "b";
                    $output->field = "blob";
                break;

            default:
            		$output->value = $value;
                    $output->realType = "string";
                    $output->valueType = "s";
                    $output->field = false;
                break;
        }
        return $output;
    }
}