<?php
namespace Niteoweb\PassiveIndexationCheck;

/**
 * Plugin Name: Passive Indexation Check
 * Description: Spider Blocker will check if googlebot is visiting the blog.
 * Version:     1.0.0
 * Runtime:     5.3+
 * Author:      Easy Blog Networks
 * Author URI:  www.easyblognetworks.com
 */

class PassiveIndexationCheck
{


    public function __construct()
    {

    }


    public function activatePlugin()
    {

    }


    public function deactivatePlugin()
    {

    }
}

// Inside WordPress
if (defined('ABSPATH')) {
    $PassiveIndexationCheck_ins = new PassiveIndexationCheck;
    register_activation_hook(__FILE__, array( &$PassiveIndexationCheck_ins, 'activatePlugin' ));
    register_deactivation_hook(__FILE__, array( &$PassiveIndexationCheck_ins, 'deactivatePlugin' ));
}
