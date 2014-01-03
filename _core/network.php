<?php


class Network {
	static $ranges = array();

	static function is_ip_in($ip){
		foreach (static::$ranges as $r){
			if ($r->is_ip_in($ip)){
				return true;
			}
		}
		return false;
	}

	static function init_shaper_structures(){
		$class_offset = 17;
		$ht1_offset = 17;
		$ht2_offset = 257;
		foreach (static::$ranges as & $r){
			$r = new ipv4ShaperRangeCalc($r->ip, $r->mask_len, $class_offset, $ht1_offset, $ht2_offset);
			$amount = $r->amount;
			$ht2_amount = ceil($amount / 256.0);
			$class_offset += $amount;
			$ht1_offset += 1;
			$ht2_offset += $ht2_amount;
		}
	}
	/**
	 *
	 * @param string $ip
	 * @return ipv4ShaperRangeCalc 
	 */
	static function range_by_ip($ip){
		foreach (static::$ranges as & $r){
			if ($r->is_ip_in($ip)){
				return $r;
				break;
			}	
		}
		return false;
	}
	/**
	 * Возвращает ИП адресс для класса
	 * 
	 * @param int $class
	 * @return string ip 
	 */
	static function ip_by_class($class){
		foreach (static::$ranges as & $r){
			$ip = $r->ip_by_class($class); 
			if ($ip){
				return long2ip($ip);
				break;
			}	
		}	
		return false;
	}
	
}

Network::$ranges['89.185.8.0/21'] = new ipv4RangeCalc('89.185.8.0',21);
Network::$ranges['89.185.16.0/21'] = new ipv4RangeCalc('89.185.16.0',21);
Network::$ranges['93.185.222.0/23'] = new ipv4RangeCalc('93.185.222.0',23);
Network::$ranges['93.185.216.0/23'] = new ipv4RangeCalc('93.185.216.0',23);
Network::$ranges['93.185.218.0/23'] = new ipv4RangeCalc('93.185.218.0',23);
Network::$ranges['93.185.220.0/23'] = new ipv4RangeCalc('93.185.220.0',23);
Network::$ranges['5.56.24.0/23'] = new ipv4RangeCalc('5.56.24.0',23);
Network::$ranges['5.56.26.0/23'] = new ipv4RangeCalc('5.56.26.0',23);
Network::$ranges['5.56.28.0/23'] = new ipv4RangeCalc('5.56.28.0',23);
Network::$ranges['5.56.30.0/23'] = new ipv4RangeCalc('5.56.30.0',23);



?>