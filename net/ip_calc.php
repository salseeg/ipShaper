<?php

namespace salseeg\net;

/**
 * Class ipv4RangeCalc
 *
 * Represents network IP ranges
 *
 * It calculate IPs like (ex. 192.168.0.0/24)
 *  - first - network address (192.168.0.0)
 *  - second - assumed to be gateway IP (192.168.0.1)
 *  - other IPs - assumed to be client IPs (192.168.0.2  - 192.168.0.254)
 *  - last - network broadcast (192.168.0.255)
 *
 */
class ipv4RangeCalc {
    
    protected $ip;
    protected $ip_l;
    protected $mask_len;
    protected $amount;
    
    
    /**
     *
     * @param string $ip  -  network base IP
     * @param int $mask_len
     */
    function __construct($ip, $mask_len = 24){
        list($ip, $cidrMaskLen) = self::parseCidr($ip);
        $cidrMaskLen = intval($cidrMaskLen);

        $this->init($ip, $cidrMaskLen ?: $mask_len);
	}

    static function parseCidr($ip){
        $parts = explode('/', $ip, 2);
        if (count($parts) < 2){
            return [$ip, 0];
        }
        return $parts;

    }
    
    protected function init($ip, $mask_len){
		$offset = 32 - $mask_len;
		$this->ip = $ip;
		$this->mask_len = $mask_len;
		$this->ip_l = (ip2long($ip) >> $offset) << $offset ;
		$this->amount = 1 << (32 - $mask_len);
    }

    /**
     *
     * @return string ip
     */
	function get_net_ip(){
		return long2ip($this->ip_l);
	}

    /**
     * @deprecated 
     */
	function get_brodcast_ip(){
        return $this->get_broadcast_ip();    
    }
	function get_broadcast_ip(){
		return long2ip($this->ip_l + $this->amount - 1);
	}


	function get_mask(){
		$fullMask = ip2long('255.255.255.255');
		$offset = 32 - $this->mask_len;
		return long2ip(($fullMask >> $offset) << $offset );
	}
	function get_gate_ip(){
		return long2ip($this->ip_l + 1);
	}

    /**
     * @param array|null $ips
     * @return array
     * @deprecated
     */
	function get_abons_ips(array & $ips = null){
        return $this->getClientIps($ips);
    }

    /**
     * @param array|null $ips
     * @return array
     */
    function getClientIps(array & $ips = null){
		if ($ips !== null){
			foreach(range($this->ip_l + 2, $this->ip_l + $this->amount - 2) as $ipl) {
				$ips[] = long2ip($ipl);
			}
            return $ips;
		}else{
			return array_map("long2ip", range($this->ip_l + 2, $this->ip_l + $this->amount - 2));
		}
	}

	/**
	 * Returns IP range  as a string, like "start_IP - end_IP" or only IP in range 
	 * Возвращает диапазон ИП вида "начальный_ИП - конечный_ИП" или единственный ИП
	 *
	 * @return string
	 */
	function get_abons_ips_as_range(){   
		$ips = $this->get_abons_ips(); // todo: rewrite not using arrays
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

