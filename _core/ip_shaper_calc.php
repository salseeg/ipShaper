<?php

class ipv4ShaperRangeCalc extends ipv4RangeCalc {
	const tc = '/sbin/tc';
	const leaf_disc = 'pfifo limit 50';
	const quantum = 'quantum 1500';

	
	static $uplink_iface = 'eth2';
	static $downlink_iface = 'eth1';
	
	

	
	function __construct($ip, $mask_len, $class_offset, $ht1_offset, $ht2_offset) {
		parent::__construct($ip, $mask_len);
		$this->class_offset = $class_offset;
		$this->ht1_offset = $ht1_offset;
		$this->ht2_offset = $ht2_offset;
	}
	function ip_by_class($class){
		if (
			($class >= $this->class_offset )
			and 
			($class < $this->class_offset + $this->amount )
		){
			return $this->ip_l + $class - $class_offset;
		}else{
			return false;
		}
	}
	function make_shaper_init_rules(& $rules){

		if ($this->mask_len < 24){

			$divisor = ceil($this->amount / 256.0);

			// uplink 
			$rules[] = self::tc." filter add dev ".self::$uplink_iface
				." parent 1:0 protocol ip pref 10 handle "
				.dechex($this->ht1_offset).": u32 divisor "
				. $divisor
			;
			$rules[] = self::tc." filter add dev ".self::$uplink_iface
				." parent 1:0 protocol ip pref 10 u32 ht 800:: match ip src "
				. $this->ip.'/'.$this->mask_len.' hashkey mask 0x0000'.dechex($divisor-1).'00 at 12 '
				.'link '.dechex($this->ht1_offset).':'
			;
			// downlink
			$rules[] = self::tc." filter add dev ".self::$downlink_iface
				." parent 1:0 protocol ip pref 10 handle "
				.dechex($this->ht1_offset).": u32 divisor "
				. $divisor
			;
			$rules[] = self::tc." filter add dev ".self::$downlink_iface
				." parent 1:0 protocol ip pref 10 u32 ht 800:: match ip dst "
				. $this->ip.'/'.$this->mask_len.' hashkey mask 0x0000'.dechex($divisor-1).'00 at 16 '
				.'link '.dechex($this->ht1_offset).':'
			;
			//print_r ($rules);
			
			for ($i = 0; $i < $divisor; $i += 1){
				// uplink
				$rules[] = self::tc." filter add dev ".self::$uplink_iface
					." parent 1:0 protocol ip pref 10 handle "
					.dechex($this->ht2_offset + $i).": u32 divisor 256"
				;
				$rules[] = self::tc." filter add dev ".self::$uplink_iface
					. " parent 1:0 protocol ip pref 10 u32 ht ".dechex($this->ht1_offset).":".dechex($i).": match ip src "
					. long2ip($this->ip_l + ($i << 8)).'/24 hashkey mask 0x000000ff at 12 '
					.'link '.dechex($this->ht2_offset + $i).':'
				;
				$rules[] = self::tc." filter add dev ".self::$downlink_iface
					." parent 1:0 protocol ip pref 10 handle "
					.dechex($this->ht2_offset + $i).": u32 divisor 256"
				;
				$rules[] = self::tc." filter add dev ".self::$downlink_iface
					. " parent 1:0 protocol ip pref 10 u32 ht ".dechex($this->ht1_offset).":".dechex($i).": match ip dst "
					. long2ip($this->ip_l + ($i << 8)).'/24 hashkey mask 0x000000ff at 16 '
					.'link '.dechex($this->ht2_offset + $i).':'
				;
			
			}
			//print_r($rules);
		}else{
			$divisor = 1 << (32- $this->mask_len);
			$rules[] = self::tc." filter add dev ".self::$uplink_iface
				." parent 1:0 protocol ip pref 10 handle "
				.dechex($this->ht2_offset).": u32 divisor "
				. $divisor
			;
			
			$rules[] = self::tc." filter add dev ".self::$uplink_iface
				." parent 1:0 protocol ip pref 10 u32 ht 800:: match ip src "
				. $this->ip.'/'.$this->mask_len.' hashkey mask 0x000000'.dechex($divisor-1).' at 12 '
				.'link '.dechex($this->ht2_offset).':'
			;
			$rules[] = self::tc." filter add dev ".self::$downlink_iface
				." parent 1:0 protocol ip pref 10 handle "
				.dechex($this->ht2_offset).": u32 divisor "
				. $divisor
			;
			
			$rules[] = self::tc." filter add dev ".self::$downlink_iface
				." parent 1:0 protocol ip pref 10 u32 ht 800:: match ip dst "
				. $this->ip.'/'.$this->mask_len.' hashkey mask 0x000000'.dechex($divisor-1).' at 16 '
				.'link '.dechex($this->ht2_offset).':'
			;
		}
	}
	function make_shaper_speed_rules($ip, $up_speed, $down_speed, & $rules){
		if (! $this->is_ip_in($ip)){ 
			return false;
		}


		$ip_offset = (ip2long($ip) - $this->ip_l);
		$class = $this->class_offset + $ip_offset;
		$ip_ht2_offset = floor($ip_offset / 256);
		$ip_offset -= $ip_ht2_offset * 256;

		
		// uplink
		$rules[] = self::tc.' class replace dev '.self::$uplink_iface
			.' parent 1: classid 1:'.dechex($class).' htb rate '.($up_speed? ($up_speed.'kbit ') : '1kbit ').self::quantum
		;
		$rules[] = self::tc.' qdisc replace dev '.self::$uplink_iface
			.' parent 1:'.dechex($class).' handle '.dechex($class).':0 '.self::leaf_disc 
		;
		$rules[] = self::tc.' filter replace dev '.self::$uplink_iface
			.' parent 1: pref 20 handle '
			.dechex($this->ht2_offset + $ip_ht2_offset).':'.dechex($ip_offset).':800'
			.' u32 ht '.dechex($this->ht2_offset + $ip_ht2_offset).':'.dechex($ip_offset)
			.': match ip src '.$ip.' flowid 1:'.dechex($class)
		;

		// downlink
		$rules[] = self::tc.' class replace dev '.self::$downlink_iface
			.' parent 1: classid 1:'.dechex($class).' htb rate '.($down_speed?($down_speed.'kbit '):'1kbit ').self::quantum
		;
		$rules[] = self::tc.' qdisc replace dev '.self::$downlink_iface
			.' parent 1:'.dechex($class).' handle '.dechex($class).':0 '.self::leaf_disc 
		;
		$rules[] = self::tc.' filter replace dev '.self::$downlink_iface
			.' parent 1: pref 20 handle '
			.dechex($this->ht2_offset + $ip_ht2_offset).':'.dechex($ip_offset).':800'
			.' u32 ht '.dechex($this->ht2_offset + $ip_ht2_offset).':'.dechex($ip_offset)
			.': match ip dst '.$ip.' flowid 1:'.dechex($class)
		;

		

	}
	
}

?>