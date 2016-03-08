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

    function testCreating(){
        $c = new ipv4RangeCalc('192.168.0.1', 25);

        $this->assertAttributeEquals(25, 'mask_len', $c);
        $this->assertAttributeEquals(128, 'amount', $c);
        $this->assertAttributeEquals('192.168.0.1', 'ip', $c);
        $this->assertAttributeEquals(ip2long('192.168.0.0'), 'ip_l', $c);
    }

    function testCreatingCidr(){
        $c = new ipv4RangeCalc('192.168.0.1/25');

        $this->assertAttributeEquals(25, 'mask_len', $c);
        $this->assertAttributeEquals(128, 'amount', $c);
        $this->assertAttributeEquals('192.168.0.1', 'ip', $c);
        $this->assertAttributeEquals(ip2long('192.168.0.0'), 'ip_l', $c);
    }

    function testClientRange(){
        $c = new ipv4RangeCalc('192.168.0.1/29');

        $ips = $c->get_client_ips();
        $this->assertEquals([
            '192.168.0.2',
            '192.168.0.3',
            '192.168.0.4',
            '192.168.0.5',
            '192.168.0.6',
        ], $ips);
    }

    function testClientIpRange(){
        $c = new ipv4RangeCalc('192.168.0.1/29');

        $this->assertEquals('192.168.0.2 - 192.168.0.6', $c->get_abons_ips_as_range());
        $this->assertEquals('192.168.0.2 - 192.168.0.6', $c->get_client_ips_as_range());

        $c = new ipv4RangeCalc('192.168.0.1/30');

        $this->assertEquals('192.168.0.2', $c->get_abons_ips_as_range());
        $this->assertEquals('192.168.0.2', $c->get_client_ips_as_range());

    }

    function testBench(){
        $i = 100000; //00000;

        while ($i >0){
            $c = new ipv4RangeCalc(implode('.', [
                rand(0, 254),
                rand(0, 254),
                rand(0, 254),
                rand(0, 254),
            ]), rand(13, 24));
//            $c->get_abons_ips_as_range();
            $c->get_client_ips_as_range();

            $i -= 1;
        }


    }


}
