<?php

class ipv4RangeCalc {
/**
 *
 * @param string $ip 
 * @param int $mask_len 
 */	
    function __construct($ip, $mask_len){
		$offset = 32 - $mask_len;
		$this->ip = $ip;
		$this->mask_len = $mask_len;
		$this->ip_l = (ip2long($ip) >> $offset) << $offset ;
		$this->amount = pow(2, 32 - $mask_len);
	}

        /** 
         *
         * @return string ip
         */
	function get_net_ip(){
		return long2ip($this->ip_l);
	}
	function get_brodcast_ip(){
		return long2ip($this->ip_l + $this->amount - 1);
	}
	function get_mask(){
		$ffff = ip2long('255.255.255.255');
		$offset = 32 - $this->mask_len;
		return long2ip(($ffff >> $offset) << $offset );
	}
	function get_gate_ip(){
		return long2ip($this->ip_l + 1);
	}
	function get_abons_ips(array & $ips = null){
		if ($ips !== null){
			foreach(range($this->ip_l + 2, $this->ip_l + $this->amount - 2) as $ipl) {
				$ips[] = long2ip($ipl);
			} 
		}else{
			return array_map("long2ip", range($this->ip_l + 2, $this->ip_l + $this->amount - 2));
		}
	}

	/**
	 * Возвращает диапазон ИП вида "начальный_ИП - конечный_ИП" или единственный ИП
	 *
	 * @return string
	 */
	function get_abons_ips_as_range(){
		$ips = $this->get_abons_ips();
		$first_ip = array_shift($ips);
		$last_ip = array_pop($ips);
		return $last_ip
			? ($first_ip." - ".$last_ip)
			: $first_ip
		;
	}
	function is_ip_in($ip){
		$ipl = ip2long($ip);
		return (($ipl >= $this->ip_l) and ($ipl <= ($this->ip_l + $this->amount - 1)));
	}
}

//$calc = new ipv4RangeCalc('89.185.10.200', 25);
//print_r($calc);
//print "net 		: ".$calc->get_net_ip()."\n";
//print "mask 		: ".$calc->get_mask()."\n";
//print "broadcast	: ".$calc->get_brodcast_ip()."\n";
//print "gate		: ".$calc->get_gate_ip()."\n";
//print_r($calc->get_abons_ips());
?>