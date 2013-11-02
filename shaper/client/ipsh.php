#!/usr/bin/php
<?php

require_once dirname(dirname(dirname(__FILE__))).'/_core/config.php';

// ipv4ShaperRangeCalc::$uplink_iface = 'test1';
// ipv4ShaperRangeCalc::$downlink_iface = 'test2';


class conf {
	static $servers = array();
	static $sources = array();
	static $bypass = array(
	    	'up' => array()
	    	, 'down' => array()
	);
}


conf::$servers['89.185.8.51'] = array('up' => 20*1000*1000, 'down' => 10*1000*1000);
conf::$servers['89.185.8.52'] = array('up' => 20*1000*1000, 'down' => 10*1000*1000);
conf::$servers['89.185.8.53'] = array('up' => 20*1000*1000, 'down' => 10*1000*1000);

conf::$servers['89.185.8.30'] = array('up' => 20*1000*1000, 'down' => 10*1000*1000);
conf::$servers['89.185.8.31'] = array('up' => 10*1000*1000, 'down' => 10*1000*1000);
conf::$servers['89.185.8.44'] = array('up' => 30*1000*1000, 'down' => 30*1000*1000);

conf::$bypass['up'][] = '93.185.219.128/26';	//wifi abon
conf::$bypass['up'][] = '89.185.8.74/32';	//server output
conf::$bypass['down'][] = '89.185.8.54/32';	//server output
conf::$bypass['down'][] = '224.0.0.5/32';	//ospf
conf::$bypass['down'][] = '224.0.0.6/32';	//ospf
conf::$bypass['down'][] = '172.17.0.0/16';	//access vlan
conf::$bypass['down'][] = '5.56.24.125/32';	//kassa
conf::$bypass['down'][] = '5.56.26.200/32';	//kassa
conf::$bypass['down'][] = '89.185.12.249/32';	//kassa
conf::$bypass['down'][] = '93.185.217.100/32';	//kassa
conf::$bypass['down'][] = '93.185.219.6/32';	//kassa
conf::$bypass['down'][] = '89.185.8.51/32';



conf::$sources['abons'] = 'http://89.185.8.31/shaper/ips_tariffs.php?php';
conf::$sources['tariffs'] = 'http://89.185.8.31/shaper/get_tariffs.php?php';







/**
 * 
 */
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
	/**
	 *	Проверяет пустая ли база
	 * 
	 * @return bool  
	 */
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
	/**
	 * 	Создает структуру базы
	 */
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
				, expires integer
			);
			
			"
		);
	}
	function query_array($sql, $index = false){
		$ret = array();
		$res = $this->_db->query($sql);
		while ($row = $res->fetchArray(SQLITE3_ASSOC)){
			if ($index){
				$ret[$row[$index]] = $row;
			}else{
				$ret[] = $row;
			}
		}
		return $ret;
	}
	/**
	 *
	 * @return array [ip] => array( ip => long, up_speed => bits, down_speed => bits, expires => unixtime )
	 */
	function get_overrides(){
		$overrides = $this->query_array(
			" select
				*
			from
				overrides
			"
			, 'ip'
		);
		$keys = array_keys($overrides);
		$keys = array_map('long2ip', $keys);
		return array_combine($keys, array_values($overrides));
	
	}
	function set_override($ip, $up, $down, $expires){
		$ip = ip2long($ip);
		$this->_db->exec("insert into overrides(ip, up_speed, down_speed, expires) values($ip,$up,$down,$expires)");
	}
	function delete_override($ip){
		$ip = ip2long($ip);
		$this->_db->exec("delete from overrides where ip = $ip ");
	}
	function clean_expired_overrides($time = false){
		if (!$time){
			$time = time();
		}
		$this->_db->exec("delete from overrides where expires < $time ");
	}
	
	/**
	 * 
	 */
	function sync_abons(){
		$main_abons = unserialize(file_get_contents(conf::$sources['abons']));
		$main_abons_count = count($main_abons);

		$my_abons = $this->query_array('select * from abons');
		$my_abons_count = count($my_abons);
		if (
			$main_abons_count
			and 
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
	/**
	 * 
	 */
	function sync_tariffs(){
		$main_tariffs = unserialize(file_get_contents(conf::$sources['tariffs']));
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
	/**
	 *
	 * @return array [ip] => array(ip => long, up_speed => bits, down_speed => bits, bonus_enabled => bool, always_enabled => bool )
	 */
	function get_speeds(){
		$speeds = $this->query_array(
			" select
				a.ip
				, ifnull(t.up_speed, 0) as up_speed
				, ifnull(t.down_speed, 10000) as down_speed
				, ifnull(t.bonus_enabled, 0) as bonus_enabled
				, ifnull(t.always_enabled, 0) as always_enabled
			from
				abons a
				left join tariffs t
				on a.tariff_id = t.id
			"
			, 'ip'
		);
		$keys = array_keys($speeds);
		$keys = array_map('long2ip', $keys);
		return array_combine($keys, array_values($speeds));
	}
	/**
	 * 
	 */
	static function init(){
		if (!self::$db){
			self::$db = new users_db('users.db');
		}
	}
	
	
}

/**
 *  интерфейс работы с шейпером и таблицами(ipset) фаервола
 */
class shaper {
	static function exec_batch($cmds){
		// Выполнение на шейпере
		$str = '';
		$offset = strlen(ipv4ShaperRangeCalc::tc) + 1;
		foreach ($cmds as $c){
			//print "$c \n";
			$str .= substr($c,$offset)."\n";
		}
		$fn = tempnam('/tmp/', 'ipsh_');
		file_put_contents($fn, $str);
		$cmd = ipv4ShaperRangeCalc::tc." -b $fn";
		$res = trim(`$cmd 2>&1`);
		if (!$res){
			unlink($fn);	
		}else{
			print $res."\n";
		}
	}
	static function get_current_speed_by_ip($ip){
		$range = Network::range_by_ip($ip);
		$class = $range->class_offset + ip2long($ip) - $range->ip_l;
		$classid = '1:'.dechex($class);
		
		$cmd = ipv4ShaperRangeCalc::tc." class show dev ".ipv4ShaperRangeCalc::$downlink_iface." classid $classid | cut -f 10 -d ' '";
		$downspeed = strtr(trim(`$cmd`), array('K' => '000', 'bit' => ''));
		$cmd = ipv4ShaperRangeCalc::tc." class show dev ".ipv4ShaperRangeCalc::$uplink_iface." classid $classid | cut -f 10 -d ' '";
		$upspeed = strtr(trim(`$cmd`), array('K' => '000', 'bit' =>''));

		return array('up' => $upspeed, 'down' => $downspeed);

		
	}
	/**
	 *	Возвращает массив текущих скорорстей на шейпере
	 * 
	 * @return array [ip] => array( 'up' => bits, 'down' => bits)
	 */
	static function get_current_speeds(){
		$cmd = ipv4ShaperRangeCalc::tc." class show dev ".ipv4ShaperRangeCalc::$downlink_iface." | cut -f 3,10 -d ' '";
		$downspeeds = explode("\n", trim(`$cmd`));
		$cmd = ipv4ShaperRangeCalc::tc." class show dev ".ipv4ShaperRangeCalc::$uplink_iface." | cut -f 3,10 -d ' '";
		$upspeeds = explode("\n", trim(`$cmd`));
			

		$classes = array();
		$ips = array();
		foreach($downspeeds as $s){
			$p = explode(' ', $s);
			$class = explode(':', $p[0]);
			$class = hexdec($class[1]);
			$speed = strtr($p[1], array('bit' => ''));
			$speed = strtr($speed, array('K' => '000'));
			$classes[Network::ip_by_class($class)] = array('down' => $speed);
		}
		foreach($upspeeds as $s){
			$p = explode(' ', $s);
			$class = explode(':', $p[0]);
			$class = hexdec($class[1]);
			$speed = strtr($p[1], array('bit' => ''));
			$speed = strtr($speed, array('K' => '000'));
			$classes[Network::ip_by_class($class)]['up'] = $speed;
		}
		return $classes;
	}
	static function set_speeds($tariff_speeds, $bonus_K = 1){
		$cmds = array();
		$current_speeds = self::get_current_speeds();
		$speeds = $tariff_speeds;	

		// Пересчет бонусов
		
		if ($bonus_K != 1){
			$hours = date('G');
			$maxK = ($hours < 9)? 5 : 2;
			foreach ($speeds as $ip => & $s){
				if ($s['bonus_enabled']){
					$s['up_speed'] = min( 100000000 
						, min($s['up_speed'] * $maxK
							, max($s['up_speed'], @ $current_speeds[$ip]['up'] * $bonus_K)
						)
					);
					$s['down_speed'] = min( 100000000 
						, min($s['down_speed'] * $maxK
							, max($s['down_speed'], @ $current_speeds[$ip]['down'] * $bonus_K)
						)
					);
				}
			}
		}

		// todo: overrides here
		$overrides = users_db::$db->get_overrides();
		foreach ($overrides as $ip => $o){
			$speeds[$ip]['up_speed'] = $o['up_speed'];
			$speeds[$ip]['down_speed'] = $o['down_speed'];
		}

		// Синхронизация тарифны+бонусы+вручную с шейпером

		$curr_ips = array_keys($current_speeds);
		$needed_ips = array_keys($speeds);

		$to_add = array_diff($needed_ips, $curr_ips);
		$to_check = array_intersect($curr_ips, $needed_ips);
		$to_delete = array_diff($curr_ips, $needed_ips);

		// Добавление правил
		foreach ($to_add as $ip){
			$s = $speeds[$ip];
			$range = Network::range_by_ip($ip);
			if ($range){
				$range->make_shaper_speed_rules($ip, $s['up_speed'], $s['down_speed'], $cmds);
			}else{
				// throw new Exception('Unknown range ip : '.$ip);
			}
		}

		// Изменения правил
		foreach ($to_check as $ip){
			$s = $speeds[$ip];
			$up_diff = abs($current_speeds[$ip]['up'] - $s['up_speed'] );
			$down_diff = abs($current_speeds[$ip]['down'] - $s['down_speed']);

			if (($up_diff > 1000)
				or ($down_diff > 1000)
			){
				Network::range_by_ip($ip)->make_shaper_speed_rules($ip, $s['up_speed'], $s['down_speed'], $cmds);
			}
		}

		
		//  Удаление правил
		foreach ($to_delete as $ip){
			$range = Network::range_by_ip($ip);
			if ($range){
				$range->make_shaper_speed_rules($ip, 0, 64000, $cmds);
			}
		}

		self::exec_batch($cmds);

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


		// servers
		foreach (conf::$servers as $ip => $speed){
			Network::range_by_ip($ip)->make_shaper_speed_rules($ip, $speed['up'], $speed['down'], $cmds);
		}	

		// my own trafic (marked on output) not works
		//$cmds[] =  ipv4ShaperRangeCalc::tc." filter add dev ".ipv4ShaperRangeCalc::$uplink_iface." parent 1:0 protocol ip pref 25 handle 1 fw  police rate 30000000 burst 1500 action ok";
		//$cmds[] =  ipv4ShaperRangeCalc::tc." filter add dev ".ipv4ShaperRangeCalc::$downlink_iface." parent 1:0 protocol ip pref 25 handle 1 fw  police rate 30000000 burst 1500 action ok";
		
		$cmds[] =  ipv4ShaperRangeCalc::tc." class replace dev ".ipv4ShaperRangeCalc::$uplink_iface." parent 1: classid 1:8 htb rate 500Mbit ".ipv4ShaperRangeCalc::quantum;
		$cmds[] =  ipv4ShaperRangeCalc::tc." qdisc replace dev ".ipv4ShaperRangeCalc::$uplink_iface." parent 1:8 handle 8: ".ipv4ShaperRangeCalc::leaf_disc;
		$cmds[] =  ipv4ShaperRangeCalc::tc." class replace dev ".ipv4ShaperRangeCalc::$downlink_iface." parent 1: classid 1:8 htb rate 500Mbit ".ipv4ShaperRangeCalc::quantum;
		$cmds[] =  ipv4ShaperRangeCalc::tc." qdisc replace dev ".ipv4ShaperRangeCalc::$downlink_iface." parent 1:8 handle 8: ".ipv4ShaperRangeCalc::leaf_disc;
		
		foreach (conf::$bypass['up'] as $ip){
			$cmds[] =  ipv4ShaperRangeCalc::tc." filter add dev ".ipv4ShaperRangeCalc::$uplink_iface
				." parent 1:0 protocol ip pref 25 u32 match ip src $ip flowid 1:8";
		}
		foreach (conf::$bypass['down'] as $ip){
			$cmds[] =  ipv4ShaperRangeCalc::tc." filter add dev ".ipv4ShaperRangeCalc::$downlink_iface
				." parent 1:0 protocol ip pref 25 u32 match ip src $ip flowid 1:8";
		}
		
		// droping rest
		$cmds[] =  ipv4ShaperRangeCalc::tc." filter add dev ".ipv4ShaperRangeCalc::$uplink_iface." parent 1:0 protocol ip pref 30 u32 match u32 0 0 at 0 police mtu 1 action drop";
		$cmds[] =  ipv4ShaperRangeCalc::tc." filter add dev ".ipv4ShaperRangeCalc::$downlink_iface." parent 1:0 protocol ip pref 30 u32 match u32 0 0 at 0 police mtu 1 action drop";

		//$speeds = users_db::$db->get_speeds();
		//foreach ($speeds as $s){
		//	$ip = long2ip($s['ip']);
		//	Network::range_by_ip($ip)->make_shaper_speed_rules($ip, $s['up_speed'], $s['down_speed'], $cmds);
		//}

		self::exec_batch($cmds);

		
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
			Network::init_shaper_structures();
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
		print "		start	- запуск шейпера, настройка правил, синхронизация с базой  \n";
		print "		stop	- остановка шейпера, удаление правил \n";
		print "		sync_speed\n";
		print "			- синхронизация cкоростей абонентов с базой и бонусным коэфициентом \n";
		print "		sync_tariffs\n";
		print "			- синхронизация тарифов с базой\n";
		print "		show [ip]\n";
		print "			- показать текущие скорости абонентов, или абонента при указанитт ip\n";
		print "		override [ip down_speed up_speed hours]\n";
		print "			- принудительно устанавливает для ip скорость down_speed/up_speed кбит/с \n";
		print "			  на hours часов. При отсутсвии аргументовот ображает текущие\n";
		print "			   принудительно установленные скорости.\n";
		print "		unoverride ip\n";
		print "			- удаляет принудительно установленную скорость\n";
		print "		cleanup_overrides\n";
		print "			- удаляет закончившиеся принудительно установленные скорости\n";
		print "	Supposed crons	\n";
		print "		30 3 * * *	ipsh sync_tariffs\n";
		print "		*/3 * * * *	ipsh sync_speed\n";
		print "		50 */2 * * *	ipsh cleanup_overrides\n";
		print "		\n";
		print "		\n";
			
	}
	/**
	 *  starts shaper tc rules and syncing shaper with db
	 */
	function start(){
		users_db::init();

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

		shaper::set_speeds($speeds);

	}
	/**
	 * 
	 */
	function override($args){
		users_db::init();
		if ($args and count($args) == 4){
			$ip = $args[0];
			$down_speed = $args[1] * 1000;
			$up_speed = $args[2] * 1000;
			$time = time() + $args[3] * 3600;
			users_db::$db->set_override($ip, $up_speed, $down_speed, $time);
			$this->sync_speed();
		}else{
			$overrides = users_db::$db->get_overrides();

			foreach ($overrides as $ip => $s){
				$up = strtr($s['up_speed'].' ', array('000000 ' => ' Mbit', '000 ' => ' Kbit', ' ' => ' bit'));
				$down = strtr($s['down_speed'].' ', array('000000 ' => ' Mbit', '000 ' => ' Kbit', ' ' => ' bit'));
				$time = date("Y-m-d H:i:s", $s['expires']);

				print "$ip\t = \t $down \t $up \t$time\n";
			}
			if (!count($overrides)){
				print "No overrides found\n";
			}

		}
		
	}
	function unoverride($args){
		if ($args and count($args) == 1){
			users_db::init();
			$ip = $args[0];
			users_db::$db->delete_override($ip);
			$this->sync_speed();
		}else{
			$this->help();
		}
	}
	function cleanup_overrides(){
		users_db::init();
		users_db::$db->clean_expired_overrides();
		$this->sync_speed();
	}
	function sync_tariffs(){
		users_db::init();
		users_db::$db->sync_tariffs();
	}
	function show($args){
		Network::init_shaper_structures();

		if ($args and count($args) == 1){
			$ip = $args[0];
			$s = shaper::get_current_speed_by_ip($ip);
			$up = strtr($s['up'].' ', array('000000 ' => ' Mbit', '000 ' => ' Kbit', ' ' => ' bit'));
			$down = strtr($s['down'].' ', array('000000 ' => ' Mbit', '000 ' => ' Kbit', ' ' => ' bit'));
			
			print "$ip\t = \t $down \t $up \n";
			
		}else{
			$speeds = shaper::get_current_speeds();

			foreach ($speeds as $ip => $s){
				$up = strtr($s['up'].' ', array('000000 ' => ' Mbit', '000 ' => ' Kbit', ' ' => ' bit'));
				$down = strtr($s['down'].' ', array('000000 ' => ' Mbit', '000 ' => ' Kbit', ' ' => ' bit'));

				print "$ip\t = \t $down \t $up \n";
			}
		}
		
	}
//	function  (){}
//	function  (){}
}

$i =  new ips;

?>
