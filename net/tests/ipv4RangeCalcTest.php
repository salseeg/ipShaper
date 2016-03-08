<?php
/**
 * Created by PhpStorm.
 * User: salseeg
 * Date: 08.03.16
 * Time: 13:14
 */


namespace salseeg\net\tests;


use salseeg\net\ipv4RangeCalc;

class ipv4RangeCalcTest extends \PHPUnit_Framework_TestCase
{

    function testBasic(){
        $calc = new ipv4RangeCalc('89.185.10.200', 25);

        $netIp = $calc->get_net_ip();
        $this->assertEquals("89.185.10.128", $netIp);
        $this->assertEquals("89.185.10.129", $calc->get_gate_ip());

        $this->assertEquals("255.255.255.128", $calc->get_mask());
        $this->assertEquals("89.185.10.255", $calc->get_broadcast_ip());
        $this->assertEquals("89.185.10.130 - 89.185.10.254", $calc->get_abons_ips_as_range());

    }


}
