<?php
// @author xiaofeng
// @date 2015-10-11

JsonSchemaUtil::test();
JsonSchema::test();

$json = <<<JSON
{
  "address": {
    "streetAddress": "21 2nd Street",
    "city": [
      {
        "location": "home",
        "code": 44
      },
      {
        "location": "home",
        "code": 44
      }
    ]
  }
}
JSON;

$schema = JsonSchema::encode(json_decode($json, true));
var_dump(json_encode($schema, JSON_PRETTY_PRINT));

class JsonSchema {
	const UNKNOWN = "unknown";
	const BOOLEAN = "boolean";
	const INTEGER = "integer";
	const STRING  = "string";
	const OBJECT  = "object";
	const ARRAY   = "array";
	const NUMBER  = "number";
	const NULL    = "null";

	public static function test() {
		assert(JsonSchema::isArray([]) === JsonSchema::UNKNOWN);
		assert(JsonSchema::isArray([1,2,3]) === true);
		assert(JsonSchema::isArray([1,2,3, "foo"=>"bar"]) === false);
		assert(JsonSchema::isObject(['x'=>1, 'y'=>null]) === true);
		assert(JsonSchema::isObject(['x'=>1, 'y'=>null, 1]) === false);
		assert(JsonSchema::getType(true) === "boolean");
		assert(JsonSchema::getType(1) === "integer");
		assert(JsonSchema::getType("hello") === "string");
		assert(JsonSchema::getType(new stdClass) === "object");
		assert(JsonSchema::getType(3.14) === "number");
		assert(JsonSchema::getType(null) === "null");
		assert(JsonSchema::getType([1,2,3,4]) === "array");
		assert(JsonSchema::getType(['name'=>'xiao', 'age'=>25]) === "object");
	}

	private static function isArray($value) {
		if(!$value) {
			return self::UNKNOWN;
		}
		return is_array($value) && array_keys($value) === range(0, count($value) - 1);
	}

	private static function isObject($value) {
		if(!$value || !is_array($value)) {
			return self::UNKNOWN;
		}
		return is_array($value)
			&& call_user_func_array(JsonSchemaUtil::all('is_string'), array_keys($value));
	}

	private static function getType($value) {
		$type = gettype($value);
		switch($type) {
			case "boolean":
			case "integer":
			case "string":
			case "object":
				return $type;
			case "double":
				return "number";
			case "NULL":
				return "null";
			case "array":
				if(self::isArray($value)) {
					return "array";
				} else if(self::isObject($value)) {
					return "object";
				} else {
					return self::UNKNOWN;
				}
			case "resource":
			case "unknown type":
				return self::UNKNOWN;
		}
	}

	private static function sanitize($value) {
		switch(true) {
			case is_scalar($value) || $value === null:
				return $value;
			case $value instanceof DateTime:
				return $value->format(DateTime::ISO8601);
			case is_array($value):
				foreach ($value as $key => $val) {
					$value[$key] = self::sanitize($val);
				}
				return $value;
			case is_object($value):
				return get_object_vars($value);
			default:
				return (string)$value;
		}
	}

	public static function encode($value) {
		$schema = [];
		// $schema = ["$schema" => "http://json-schema.org/draft-04/schema#"];
		$value = self::sanitize($value);
		$type = self::getType($value);
		$schema["type"] = $type;
		switch($type) {
			case self::BOOLEAN:
				break;
			case self::INTEGER:
				break;
			case self::NUMBER:
				break;
			case self::STRING:
				break;
			case self::OBJECT:
				foreach($value as $property => $val) {
					$schema["properties"][$property] = self::encode($val);
					$schema["required"][] = $property;
				}
				break;
			case self::ARRAY:
				foreach($value as $item) {
					$schema["items"][] = self::encode($item);
				}
				break;
			case self::NULL:
			case self::UNKNOWN:
			default:
				break;
		}
		return $schema;
	}

}

class JsonSchemaUtil {
	public static function test() {
		$x1y2 = JsonSchemaUtil::all(function($x, $y) { return $x === 1 && $y === 2; });
		assert($x1y2([1, 2], [1, 2]) === true);
		assert($x1y2([1, 2], [1, 1]) === false);
	}

	public static function all(callable $func) {
		return function() use($func) {
			$args = func_get_args();
			if(!$args) {
			    return false;
			}
			foreach($args as $arg) {
				if(!is_array($arg)) {
					$arg = [$arg];
				}
			    if(call_user_func_array($func, $arg) !== true) {
			        return false;
				}
			}
			return true;
		};
	}
}
