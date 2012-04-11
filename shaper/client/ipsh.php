#!/usr/bin/php
<?php

require_once dirname(dirname(dirname(__FILE__))).'/_core/config.php';

Network::$ranges['10.0.0.0/24'] = new ipv4RangeCalc('10.0.0.0',24);
Network::$ranges['10.2.0.0/26'] = new ipv4RangeCalc('10.2.0.0',26);

Network::init_shaper_structures();

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
			$this->sync_to_main_db();
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

	function sync_to_main_db(){
		
	}
	static function init(){
		if (!self::$db){
			self::$db = new users_db('users.db');
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
		print "		\n";
			
	}
	/**
	 *  starts shaper tc rules and syncing shaper with db
	 */
	function start(){

		print "
		/sbin/tc qdisc add dev eth1 root handle 1: htb
		/sbin/tc filter add dev eth1 parent 1:0 protocol ip pref 10 u32
		/sbin/tc qdisc add dev eth2 root handle 1: htb
		/sbin/tc filter add dev eth2 parent 1:0 protocol ip pref 10 u32
		";
		foreach(Network::$ranges as $r){
			print_r($r->make_shaper_init_rules());
		}
		print "/sbin/tc filter add dev eth1 parent 1:0 protocol ip pref 30 u32 match u32 0 0 at 0 police mtu 1 action drop\n";
		print "/sbin/tc filter add dev eth2 parent 1:0 protocol ip pref 30 u32 match u32 0 0 at 0 police mtu 1 action drop\n";

		print_r(Network::range_by_ip('89.185.8.130')->make_shaper_speed_rules('89.185.8.130', 5000, 10000));
		print_r(Network::range_by_ip('89.185.23.143')->make_shaper_speed_rules('89.185.23.143', 5000, 10000));
		print_r(Network::range_by_ip('89.185.15.130')->make_shaper_speed_rules('89.185.15.130', 5000, 10000));
		print_r(Network::range_by_ip('10.0.0.1')->make_shaper_speed_rules('10.0.0.1', 5000, 10000));
		print_r(Network::range_by_ip('10.2.0.1')->make_shaper_speed_rules('89.2.0.1', 5000, 10000));


	}
	/**
	 * stoping shaper
	 */
	function stop(){}
	/**
	 * syncing speed with billing and shaper (storing chanfges in db) including speed bonus
	 */
	function sync_speed(){}
	/**
	 * 
	 */
	function override(){}
	function unoverride(){}
	function cleanup_overrides(){}
	function sync_db(){
		users_db::init();
		
	}
//	function  (){}
//	function  (){}
}

$i =  new ips;

?>
