<?php
// @author xiaofeng
// @date 2015-10-11
// @update 2015-10-12 01:15

// todo 支持multi-type { "type": ["number", "string"] }
// todo dbdict 字段字典
// tittle description

error_reporting(E_ALL);
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

define('DOMAIN', 'http://www.foo.com/bar.json');
$schema = JsonSchema::encode(json_decode($json, true), [
	'city' => '城市'
]);
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

	public static function encode($value, array $dict = []) {
		$schema = self::encodeHelper($value, null, function($key) use($dict) {
			return isset($dict[$key]) ? $dict[$key] : "";
		});
		$schema = ['$schema' => "http://json-schema.org/draft-04/schema#"] + $schema;
		if(isset($conf['domain']) && is_string($conf['domain'])) {
			$schema = ['id' => $domain] + $schema;
		}
		$schema["title"] = '';
		$schema["description"] = '';
		return $schema;
	}

	private static function encodeHelper($value, $key = null, callable $getTitle = null) {
		static $_getTitle;
		if($getTitle !== null) {
			$_getTitle = $getTitle;
		}

		$schema = [];

		// metadata
		if($_getTitle !== null) {
			$schema["title"] = $_getTitle($key);
			$schema["description"] = $_getTitle($key);
		}
		// $schema["default"] = $value;
		// $schema["enum"] = [];


		$value = self::sanitize($value);
		$type = self::getType($value);
		$schema["type"] = $type;
		switch($type) {
			case self::BOOLEAN:
				break;
			case self::INTEGER:
				// $schema["multipleOf"] = ;
				// $schema["minimum"] = ;
				// $schema["maximum"] = ;
				// $schema["exclusiveMinimum"] = true;
				// $schema["exclusiveMaximum"] = true;
				break;
			case self::NUMBER:
				// $schema["multipleOf"] = ;
				// $schema["minimum"] = ;
				// $schema["maximum"] = ;
				// $schema["exclusiveMinimum"] = true;
				// $schema["exclusiveMaximum"] = true;
				break;
			case self::STRING:
				$schema["minLength"] = 0;
				$schema["maxLength"] = 255;
				$schema["pattern"] = '^.*$';
				// "date-time" "email" "hostname" "ipv4" "ipv6" "uri"
				// $schema["format"] = '';
				break;
			case self::OBJECT:
				foreach($value as $property => $val) {
					$schema["properties"][$property] = self::encodeHelper($val, $property);
					$schema["dependencies"][$property] = []; // Property dependencies
					// $schema["dependencies"][$property] = '$schema'; // Schema dependencies
					$schema["required"][] = $property;
				}
				// $schema["minProperties"] =
				// $schema["maxProperties"] =

				$schema["additionalProperties"] = true;
				// $schema["additionalProperties"] = '$schema'; // 附件属性本身是schema

				// $schema["patternProperties"] = [
				// 	'regex' => '$schema',
				// 	...
				// ];
				break;
			case self::ARRAY:
				// 区分tuple 还是 list


				// Tuple 不同类型固定长度
				foreach($value as $item) {
					$schema["items"][] = self::encodeHelper($item, $key);
				}
				$schema["additionalItems"] = false;

				// 只有一个元素无法判断是tuple还是list， 默认item好了
				$hash = array_map(['JsonSchemaUtil', 'arrayHash'], $schema["items"]);
				$isList = count(array_keys(array_flip($hash))) === 1;

				if($isList) {
					// List 相同类型不定长度
					$schema["items"] = self::encodeHelper($value[0], $key);
					// $schema["minItems"]
					// $schema["maxItems"]
					$schema["additionalItems"] = true;
					$schema["uniqueItems"] = false;
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

	// @see http://stackoverflow.com/questions/2254220/php-best-way-to-md5-multi-dimensional-array
	public static function arrayHash(array $arr) {
		array_multisort($arr);
		return md5(json_encode($arr));
	}
}
