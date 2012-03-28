<?php


class Network {
	static $ranges = array();

	static function is_ip_in($ip){
		foreach (self::$ranges as $r){
			if ($r->is_ip_in($ip)){
				return true;
			}
		}
		return false;
	}
}

Network::$ranges['89.185.8.0/21'] = new ipv4RangeCalc('89.185.8.0',21);
Network::$ranges['89.185.16.0/21'] = new ipv4RangeCalc('89.185.16.0',21);
Network::$ranges['93.185.222.0/23'] = new ipv4RangeCalc('93.185.222.0',23);
Network::$ranges['93.185.216.0/23'] = new ipv4RangeCalc('93.185.216.0',23);
Network::$ranges['93.185.218.0/23'] = new ipv4RangeCalc('89.185.218.0',23);


?>