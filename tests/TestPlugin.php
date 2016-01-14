<?php
namespace Niteoweb\PassiveIndexationCheck\Tests;

    use Niteoweb\PassiveIndexationCheck\PassiveIndexationCheck;

class TestPlugin extends \PHPUnit_Framework_TestCase
{

    public function setUp()
    {
        \WP_Mock::setUsePatchwork(true);
        \WP_Mock::setUp();
    }

    public function tearDown()
    {
        \WP_Mock::tearDown();
    }

    public function testPlugin()
    {
            $plugin = new PassiveIndexationCheck;
            $plugin->__construct();
    }

    public function testActivate()
    {
            $plugin = new PassiveIndexationCheck;
            $plugin->activatePlugin();
    }

    public function testDectivate()
    {
            $plugin = new PassiveIndexationCheck;
            $plugin->deactivatePlugin();
    }
}
