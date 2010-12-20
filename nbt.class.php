<?php
/**
 * Class for reading in NBT-format files.
 * 
 * @author  Justin Martin <frozenfire@thefrozenfire.com>
 * @version 1.0
 *
 * Dependencies:
 *  PHP 4.3+ (5.3+ recommended)
 *  GMP Extension
 */

extension_loaded("gmp") or die("The NBT class requires the GMP extension.");
class NBT {
	public $root = array();
	
	public $verbose = false;
	
	const TAG_END = 0;
	const TAG_BYTE = 1;
	const TAG_SHORT = 2;
	const TAG_INT = 3;
	const TAG_LONG = 4;
	const TAG_FLOAT = 5;
	const TAG_DOUBLE = 6;
	const TAG_BYTE_ARRAY = 7;
	const TAG_STRING = 8;
	const TAG_LIST = 9;
	const TAG_COMPOUND = 10;
	
	public function loadFile($filename) {
		$fp = fopen("compress.zlib://{$filename}", "rb");
		$this->traverseTag($fp, &$this->root);
		return end($this->root);
	}
	
	public function writeFile($filename) {
		$fp = fopen("compress.zlib://{$filename}", "wb");
		foreach($this->root as $rootTag) if(!$this->writeTag($fp, $rootTag)) return false;
		return true;
	}
	
	public function purge() {
		$this->root = array();
	}
	
	protected function traverseTag($fp, $tree) {
		if(feof($fp)) return false;
		$tagType = $this->readType($fp, self::TAG_BYTE); // Read type byte.
		if($tagType == self::TAG_END) {
			return false;
		} else {
			if($this->verbose) $position = ftell($fp);
			$tagName = $this->readType($fp, self::TAG_STRING);
			$tagData = $this->readType($fp, $tagType);
			if($this->verbose) echo "Reading tag \"{$tagName}\" at offset {$position}".PHP_EOL;
			$tree[] = array("type"=>$tagType, "name"=>$tagName, "value"=>$tagData);
			return true;
		}
	}
	
	protected function writeTag($fp, $tag) {
		if($this->verbose) echo "Writing tag \"{$tag["name"]}\" of type {$tag["type"]}".PHP_EOL;
		return $this->writeType($fp, self::TAG_BYTE, $tag["type"]) && $this->writeType($fp, self::TAG_STRING, $tag["name"]) && $this->writeType($fp, $tag["type"], $tag["value"]);
	}
	
	protected function readType($fp, $tagType) {
		switch($tagType) {
			case self::TAG_BYTE: // Signed byte (8 bit)
				list(,$unpacked) = unpack("c", fread($fp, 1));
				return $unpacked;
			case self::TAG_SHORT: // Signed short (16 bit, big endian)
				list(,$unpacked) = unpack("n", fread($fp, 2));
				if($unpacked >= pow(2, 15)) $unpacked -= pow(2, 16); // Convert unsigned short to signed short.
				return $unpacked;
			case self::TAG_INT: // Signed integer (32 bit, big endian)
				list(,$unpacked) = unpack("N", fread($fp, 4));
				if($unpacked >= pow(2, 31)) $unpacked -= pow(2, 32); // Convert unsigned int to signed int
				return $unpacked;
			case self::TAG_LONG: // Signed long (64 bit, big endian)
				list(,$firstHalf) = unpack("N", fread($fp, 4));
				list(,$secondHalf) = unpack("N", fread($fp, 4));
				$value = gmp_add($secondHalf, gmp_mul($firstHalf, "4294967296"));
				if(gmp_cmp($value, gmp_pow(2, 63)) >= 0) $value = gmp_sub($value, gmp_pow(2, 64));
				return gmp_strval($value);
			case self::TAG_FLOAT: // Floating point value (32 bit, big endian, IEEE 754-2008)
				list(,$value) = (pack('d', 1) == "\77\360\0\0\0\0\0\0")?unpack('f', fread($fp, 4)):unpack('f', strrev(fread($fp, 4)));
				return $value;
			case self::TAG_DOUBLE: // Double value (64 bit, big endian, IEEE 754-2008)
				list(,$value) = (pack('d', 1) == "\77\360\0\0\0\0\0\0")?unpack('d', fread($fp, 8)):unpack('d', strrev(fread($fp, 8)));
				return $value;
			case self::TAG_BYTE_ARRAY: // Byte array
				$arrayLength = $this->readType($fp, self::TAG_INT);
				$array = array();
				for($i = 0; $i < $arrayLength; $i++) $array[] = $this->readType($fp, self::TAG_BYTE);
				return $array;
			case self::TAG_STRING: // String
				if(!$stringLength = $this->readType($fp, self::TAG_SHORT)) return "";
				$string = utf8_decode(fread($fp, $stringLength)); // Read in number of bytes specified by string length, and decode from utf8.
				return $string;
			case self::TAG_LIST: // List
				$tagID = $this->readType($fp, self::TAG_BYTE);
				$listLength = $this->readType($fp, self::TAG_INT);
				if($this->verbose) echo "Reading in list of {$listLength} tags of type {$tagID}.".PHP_EOL;
				$list = array("type"=>$tagID, "value"=>array());
				for($i = 0; $i < $listLength; $i++) {
					if(feof($fp)) break;
					$list["value"][] = $this->readType($fp, $tagID);
				}
				return $list;
			case self::TAG_COMPOUND: // Compound
				$tree = array();
				while($this->traverseTag($fp, &$tree));
				return $tree;
		}
	}
	
	protected function writeType($fp, $tagType, $value) {
		switch($tagType) {
			case self::TAG_BYTE: // Signed byte (8 bit)
				return fwrite($fp, pack("c", $value));
			case self::TAG_SHORT: // Signed short (16 bit, big endian)
				if($value < 0) $value += pow(2, 16); // Convert signed short to unsigned short
				return fwrite($fp, pack("n", $value));
			case self::TAG_INT: // Signed integer (32 bit, big endian)
				if($value < 0) $value += pow(2, 32); // Convert signed int to unsigned int
				return fwrite($fp, pack("N", $value));
			case self::TAG_LONG: // Signed long (64 bit, big endian)
				$secondHalf = gmp_mod($value, 2147483647);
				$firstHalf = gmp_sub($value, $secondHalf);
				return fwrite($fp, pack("N", gmp_intval($firstHalf))) && fwrite($fp, pack("N", gmp_intval($secondHalf)));
			case self::TAG_FLOAT: // Floating point value (32 bit, big endian, IEEE 754-2008)
				return fwrite($fp, (pack('d', 1) == "\77\360\0\0\0\0\0\0")?pack('f', $value):strrev(pack('f', $value)));
			case self::TAG_DOUBLE: // Double value (64 bit, big endian, IEEE 754-2008)
				return fwrite($fp, (pack('d', 1) == "\77\360\0\0\0\0\0\0")?pack('d', $value):strrev(pack('d', $value)));
			case self::TAG_BYTE_ARRAY: // Byte array
				return $this->writeType($fp, self::TAG_INT, count($value)) && fwrite($fp, call_user_func_array("pack", array_merge(array("c".count($value)), $value)));
			case self::TAG_STRING: // String
				$value = utf8_encode($value);
				return $this->writeType($fp, self::TAG_SHORT, strlen($value)) && fwrite($fp, $value);
			case self::TAG_LIST: // List
				if($this->verbose) echo "Writing list of ".count($value["value"])." tags of type {$value["type"]}.".PHP_EOL;
				if(!($this->writeType($fp, self::TAG_BYTE, $value["type"]) && $this->writeType($fp, self::TAG_INT, count($value["value"])))) return false;
				foreach($value["value"] as $listItem) if(!$this->writeType($fp, $value["type"], $listItem)) return false;
				return true;
			case self::TAG_COMPOUND: // Compound
				foreach($value as $listItem) if(!$this->writeTag($fp, $listItem)) return false;
				if(!fwrite($fp, "\0")) return false;
				return true;
		}
	}
}
?>
