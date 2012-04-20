#!/usr/bin/php
<?php

require_once dirname(dirname(dirname(__FILE__))).'/_core/config.php';

//ipv4ShaperRangeCalc::$uplink_iface = 'lo:0';
//ipv4ShaperRangeCalc::$downlink_iface = 'lo:1';



class users_db {
	/**
	 *
	 * @var users_db 
	 */
	static  $db = null;
	/**
	 *
	 * @var SQLite3 
	 */
	var $_db = null;
	
	function __construct($filename){
		$dbh = new SQLite3($filename);
		$this->_db = $dbh;

		if ($this->is_empty()){
			$this->create();
			$this->sync_tariffs();
			$this->sync_abons();
		}
		
	}
	function is_empty(){
		$res = @ $this->_db->querySingle("select count(*) from abons");
		if ($res === false){
			return true;
		}
		$res = @  $this->_db->querySingle("select count(*) from tariffs");
		if ($res === false){
			return true;
		}

		return false;
	}
	function create(){
		$this->_db->exec(
			"create table if not exists 
				tariffs
			(
				id integer primary_key
				, up_speed integer
				, down_speed integer
				, bonus_enabled integer
				, always_enabled integer
			);
			
			"
		);
		$this->_db->exec(
			"create table if not exists 
				abons
			(
				ip integer primary key
				, tariff_id integer
				, foreign key (tariff_id) references tariffs(id)
			);
			
			"
		);
		$this->_db->exec(
			"create table if not exists 
				overrides
			(
				ip integer primary_key
				, up_speed integer
				, down_speed integer
			);
			
			"
		);
	}
	function query_array($sql){
		$ret = array();
		$res = $this->_db->query($sql);
		while ($row = $res->fetchArray(SQLITE3_ASSOC)){
			$ret[] = $row;
		}
		return $ret;
	}
	function sync_abons(){
		$main_abons = unserialize(file_get_contents('http://89.185.8.31/shaper/ips_tariffs.php?php'));
		$main_abons_count = count($main_abons);

		$my_abons = $this->query_array('select * from abons');
		$my_abons_count = count($my_abons);
		if (
			!$main_abons_count
			or
			($my_abons_count < $main_abons_count)
			or
			($my_abons_count / $main_abons_count < 2)
		){
			$my = array();
			foreach ($my_abons as $a){
				$my[$a['ip']] = $a['tariff_id'];
			}

			$main = array();
			foreach ($main_abons as $ip => $tariff_id){
				$main[ip2long($ip)] = $tariff_id;
			}

			//print_r($my);
			//print_r($main);

			$to_add = array_diff_key($main, $my);
			$to_change = array_intersect_key($main, $my);
			$to_delete = array_diff_key($my, $main);

			//print_r($to_add);
			//print_r($to_change);
			//print_r($to_delete);

			if (!empty ($to_add)){
				$this->_db->exec("begin");
				foreach ($to_add as $ipl => $tariff_id){
					$this->_db->exec("insert into abons(ip,tariff_id) values($ipl,$tariff_id)");
				}
				$this->_db->exec("commit");
			}

			$updated = false;
			foreach ($to_change as $ipl => $t){
				if ($my[$ipl] != $main[$ipl]){
					if (!$updated){
						$this->_db->exec('begin');
					}
					$this->_db->exec("update abons set tariff_id = {$main[$ipl]} where ip = $ipl");
					$updated = true;
				}
			}
			if ($updated){
				$this->_db->exec('commit');
			}
			
			if  (!empty ($to_delete)){
				$this->_db->exec("delete from abons where ip in (".implode(',', array_keys($to_delete)).')');
			}
					//$this->_db->exec($sql.implode(',',$rows));
			//print_r($sql.implode(',',$rows));
			
		}
		
	}
	function sync_tariffs(){
		$main_tariffs = unserialize(file_get_contents('http://89.185.8.31/shaper/get_tariffs.php?php'));
		$main_tariffs_count = count($main_tariffs);

		$tariffs = $this->query_array('select * from tariffs');
		$tariffs_count = count($tariffs);


		//print_r($main_tariffs);
		//print_r($tariffs);

		if (
			!$main_tariffs_count
			or
			($tariffs_count < $main_tariffs_count)
			or
			($tariffs_count / $main_tariffs_count < 2)
		){
			$main_index = array();
			$my_index = array();
			foreach ($main_tariffs as $i => $m){
				$main_index[$m['id']] = $i;
			}
			foreach ($tariffs as $i => $m){
				$my_index[$m['id']] = $i;
			}
			//print_r($main_index);
			//print_r($my_index);
			

			$to_add = array_diff_key($main_index, $my_index);
			$to_change = array_intersect_key($main_index, $my_index);
			$to_delete = array_diff_key($my_index, $main_index);

			//print_r($to_add);
			//print_r($to_change);
			//print_r($to_delete);

			if (!empty ($to_add)){
				$this->_db->exec( "begin");
				

				foreach ($to_add as $i){
					$this->_db->exec( " insert 
						into tariffs(
							id
							, down_speed
							, up_speed
							, bonus_enabled
							, always_enabled
						) values (
							{$main_tariffs[$i]['id']}
							, {$main_tariffs[$i]['down_speed']}
							, {$main_tariffs[$i]['up_speed']}
							, {$main_tariffs[$i]['bonus_enabled']}
							, {$main_tariffs[$i]['always_enabled']}
						)
					");
				}
				$this->_db->exec( "commit");
			}

			
			$updated = false;
			foreach ($to_change as $id => $i){
				$main_i = $main_index[$id];
				$my_i = $my_index[$id];
				if (
					($main_tariffs[$main_i]['down_speed'] != $tariffs[$my_i]['down_speed'] )
					or
					($main_tariffs[$main_i]['up_speed'] != $tariffs[$my_i]['up_speed'] )
					or
					($main_tariffs[$main_i]['bonus_enabled'] != $tariffs[$my_i]['bonus_enabled'] )
					or
					($main_tariffs[$main_i]['always_enabled'] != $tariffs[$my_i]['always_enabled'] )
				){
					if (!$updated){
						$this->_db->exec( "begin");
					}	
					$this->_db->exec( " update 
							tariffs
						set
							down_speed = {$main_tariffs[$main_i]['down_speed']}
							, up_speed = {$main_tariffs[$main_i]['up_speed']}
							, bonus_enabled = {$main_tariffs[$main_i]['bonus_enabled']}
							, always_enabled = {$main_tariffs[$main_i]['always_enabled']}
						where
							id = $id
					");
					$updated = true;

				}
			}
			if ($updated){
				$this->_db->exec( "commit");
			}

			if (!empty ($to_delete)){
				$this->_db->exec("delete from tariffs where id = (".implode(',', array_keys($to_delete)));
			}
		}
		
		
		//print_r($main_tariffs);
	}
	function get_speeds(){
		$speeds = $this->query_array(
			" select
				a.ip
				, ifnull(t.up_speed, 0) as up_speed
				, ifnull(t.down_speed, 1) as down_speed
				, ifnull(t.bonus_enabled, 0) as bonus_enabled
				, ifnull(t.always_enabled, 0) as always_enabled
			from
				abons a
				left join tariffs t
				on a.tariff_id = t.id
			"
		);
		return $speeds;
	}
	static function init(){
		if (!self::$db){
			self::$db = new users_db('users.db');
		}
	}
	
	
}


class shaper {
	static function get_current_speed_by_ip($ip){}
	static function get_current_speeds(){
		$cmd = ipv4ShaperRangeCalc::tc." class show dev ".ipv4ShaperRangeCalc::$downlink_iface." | cut -f 3,10 -d ' '";
		$downspeeds = explode("\n", trim(`$cmd`));
		$cmd = ipv4ShaperRangeCalc::tc." class show dev ".ipv4ShaperRangeCalc::$up_iface." | cut -f 3,10 -d ' '";
		$upspeeds = explode("\n", trim(`$cmd`));
			

		$classes = array();
		$ips = array();
		foreach($downspeeds as $s){
			$p = explode(' ', $s);
			$class = explode(':', $p[0]);
			$class = hexdec($class[0]);
			$speed = strtr($p[1], array('bit' => ''));
			$speed = strtr($speed, array('K' => '000'));
			$classes[$class] = $speed;
		}
		foreach($upspeeds as $s){
			$p = explode(' ', $s);
			$class = explode(':', $p[0]);
			$class = hexdec($class[0]);
			$speed = strtr($p[1], array('bit' => ''));
			$speed = strtr($speed, array('K' => '000'));
			$classes[$class] .= '/'.$speed;
		}
		print_r($classes);
		return $ips;
	}
	static function set_speeds($tariff_speeds, $bonus_K = 1){
		$cmds = array();

		$speeds = $tariff_speeds;		
		foreach ($speeds as $s){
			$ip = long2ip($s['ip']);
			Network::range_by_ip($ip)->make_shaper_speed_rules($ip, $s['up_speed']*1000, $s['down_speed']* 1000, $cmds);
		}
		$str = '';
		$offset = strlen(ipv4ShaperRangeCalc::tc) + 1;
		foreach ($cmds as $c){
			//print "$c \n";
			$str .= substr($c,$offset)."\n";
		}
		$fn = tempnam('/tmp/', 'ipsh_');
		file_put_contents($fn, $str);
		$cmd = "tc -b $fn";
		`$cmd`;
		unlink($fn);
	}

	static function init(){
		$cmds = array(
			ipv4ShaperRangeCalc::tc .' qdisc add dev '.ipv4ShaperRangeCalc::$uplink_iface.' root handle 1: htb '
			, ipv4ShaperRangeCalc::tc .' filter add dev '.ipv4ShaperRangeCalc::$uplink_iface.' parent 1:0 protocol ip pref 10 u32 '
			, ipv4ShaperRangeCalc::tc .' qdisc add dev '.ipv4ShaperRangeCalc::$downlink_iface.' root handle 1: htb '
			, ipv4ShaperRangeCalc::tc .' filter add dev '.ipv4ShaperRangeCalc::$downlink_iface.' parent 1:0 protocol ip pref 10 u32 '
		);
		foreach(Network::$ranges as $r){
			$r->make_shaper_init_rules($cmds);
		}

		$my_uplink_ip = '';
		$my_downlink_ip = '';

		$c = "ip a show  dev ".ipv4ShaperRangeCalc::$downlink_iface." | grep 'inet ' | head -n 1";
		$r = trim(`$c`);
		//print_r ($r);
		$p = explode(' ', $r);
		$r = $p[1];
		//print_r ($r);
		$p = explode('/', $r);
		$my_downlink_ip = $p[0];

		$c = "ip a show  dev ".ipv4ShaperRangeCalc::$uplink_iface." | grep 'inet ' | head -n 1";
		$r = trim(`$c`);
		//print_r ($r);
		$p = explode(' ', $r);
		$r = $p[1];
		//print_r ($r);
		$p = explode('/', $r);
		$my_uplink_ip = $p[0];

		
		// myself
		Network::range_by_ip($my_downlink_ip)->make_shaper_speed_rules($my_downlink_ip, 10000, 10000, $cmds);
		Network::range_by_ip($my_uplink_ip)->make_shaper_speed_rules($my_uplink_ip, 10000, 10000,$cmds);

		// servers
		Network::range_by_ip('89.185.8.30')->make_shaper_speed_rules('89.185.8.30', 10000, 10000, $cmds);
		Network::range_by_ip('89.185.8.31')->make_shaper_speed_rules('89.185.8.31', 10000, 10000, $cmds);
		
		$cmds[] =  ipv4ShaperRangeCalc::tc." filter add dev ".ipv4ShaperRangeCalc::$uplink_iface." parent 1:0 protocol ip pref 30 u32 match u32 0 0 at 0 police mtu 1 action drop";
		$cmds[] =  ipv4ShaperRangeCalc::tc." filter add dev ".ipv4ShaperRangeCalc::$downlink_iface." parent 1:0 protocol ip pref 30 u32 match u32 0 0 at 0 police mtu 1 action drop";

		$speeds = users_db::$db->get_speeds();
		foreach ($speeds as $s){
			$ip = long2ip($s['ip']);
			Network::range_by_ip($ip)->make_shaper_speed_rules($ip, $s['up_speed']*1000, $s['down_speed']* 1000, $cmds);
		}
		$str = '';
		$offset = strlen(ipv4ShaperRangeCalc::tc) + 1;
		foreach ($cmds as $c){
			//print "$c \n";
			$str .= substr($c,$offset)."\n";
		}
		$fn = tempnam('/tmp/', 'ipsh_');
		file_put_contents($fn, $str);
		$cmd = "tc -b $fn";
		`$cmd`;
		unlink($fn);


		
	}

	static function stop(){
		$cmds = array(
			ipv4ShaperRangeCalc::tc .' qdisc del dev '.ipv4ShaperRangeCalc::$uplink_iface.' root handle 1: htb '
			, ipv4ShaperRangeCalc::tc .' qdisc del dev '.ipv4ShaperRangeCalc::$downlink_iface.' root handle 1: htb '
		);
		foreach ($cmds as $c){
			$res = trim(`$c`);
		}
		
	}
	
}

class ips {
	function __construct(){
		$args = $_SERVER['argv'];
		array_shift($args);
		$action = array_shift($args);
		$methods = get_class_methods($this);
		if (in_array($action, $methods)){
			$this->$action($args);
		}else{
			$this->help();
		}
	}
	function help(){
		print "	ipsh <action> [arg [arg [..]]]	\n";
		print "		\n";
		print "	action:	\n";
		print "		help	- 	\n";
		print "		start	-  \n";
		print "		stop	-  \n";
		print "		sync_speed\n";
		print "			- \n";
		print "		sync_tariffs\n";
		print "			- \n";
		print "	Supposed crons	\n";
		print "		30 3 * * *  ipsh sync_tariffs\n";
		print "		\n";
		print "		\n";
		print "		\n";
		print "		\n";
			
	}
	/**
	 *  starts shaper tc rules and syncing shaper with db
	 */
	function start(){
		users_db::init();
		Network::init_shaper_structures();

		$speeds = users_db::$db->get_speeds();

		shaper::init();
		shaper::set_speeds($speeds);

		
	}
	/**
	 * stoping shaper
	 */
	function stop(){
		shaper::stop();
	}
	/**
	 * syncing speed with billing and shaper (storing chanfges in db) including speed bonus
	 */
	function sync_speed(){
		users_db::init();
		users_db::$db->sync_abons();

		$speeds = users_db::$db->get_speeds();

	}
	/**
	 * 
	 */
	function override(){}
	function unoverride(){}
	function cleanup_overrides(){}
	function sync_db(){
	}
	function sync_tariffs(){
		users_db::init();
		users_db::$db->sync_tariffs();
	}
	function show($args){
		shaper::get_current_speeds();
		
	}
//	function  (){}
//	function  (){}
}

$i =  new ips;

?>
