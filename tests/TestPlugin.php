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

    public function testNotificationSending()
    {
        $options = array(
            'notificationEmails' => array(),
            'notificationTime' => 1,
            'resendEmailTime' => 10,
            'lastBotVisit' => time(),
            'notificationData' => array(
                'notificationsSent' => false,
                'lastNotificationTS' => false,
                'botVisitTimeAtNotification' => time()
            )
        );

        \WP_Mock::wpFunction(
            'wp_mail',
            array(
                'called' => count($options['notificationEmails']),
                'args' => array(
                    \WP_Mock\Functions::type('string'),
                    \WP_Mock\Functions::type('string'),
                    \WP_Mock\Functions::type('string')
                ),
                'return' => true
            )
        );

        \WP_Mock::wpFunction(
            'update_option',
            array(
                'called' => 1,
                'args' => array('passive_indexation_check_settings', '*'),
                'return' => true
            )
        );

        $plugin = new PassiveIndexationCheck;
        $output = $plugin->sendNotificationEmails($options);
        $this->assertEquals(false, $output);

        // Test if enough time has passed to send emails, and check if two emails
        // were sent
        $options['notificationEmails'] = array('test@local.com', 'test2@local.com');
        $options['lastBotVisit'] = time() - 24 * 60 * 60 - 5;

        \WP_Mock::wpFunction(
            'wp_mail',
            array(
                'called' => count($options['notificationEmails']),
                'args' => array(
                    \WP_Mock\Functions::type('string'),
                    \WP_Mock\Functions::type('string'),
                    \WP_Mock\Functions::type('string')
                ),
                'return' => true
            )
        );

        $output = $plugin->sendNotificationEmails($options);
        $this->assertEquals(count($output), 2);

        // Test if not enough time has passed to send emails
        $options['lastBotVisit'] = time();

        $output = $plugin->sendNotificationEmails($options);
        $this->assertEquals(false, $output);

        // Test if we send emails again after x days (if the bot hasn't visited the page yet)
        $options['notificationData']['lastNotificationTS'] = time() - 10 * 24 * 60 * 60 - 5;
        $options['notificationData']['notificationsSent'] = true;
        $options['lastBotVisit'] = time() - 1 * 24 * 60 * 60 - 5;
        $options['notificationData']['botVisitTimeAtNotification'] = $options['lastBotVisit'];

        $output = $plugin->sendNotificationEmails($options);
        $this->assertEquals(count($output), 2);

        // Test checking to resend emails after n days if bot times are the same
        $options['notificationData']['lastNotificationTS'] = time() - 9 * 24 * 60 * 60 - 5;
        $options['notificationData']['notificationsSent'] = true;
        $options['lastBotVisit'] = time() - 1 * 24 * 60 * 60 - 5;
        $options['notificationData']['botVisitTimeAtNotification'] = $options['lastBotVisit'];

        $output = $plugin->sendNotificationEmails($options);
        $this->assertEquals($output, false);
    }

    public function testGoogleBotVisit()
    {
        \WP_Mock::wpFunction(
            'get_option',
            array(
                'called' => 1,
                'args' => array(
                    'passive_indexation_check_settings'
                ),
                'return' => array(
                    'notificationEmails' => array('some@some.com', 'boo@boo.com'),
                    'notificationTime' => 1,
                    'resendEmailTime' => 10,
                    'lastBotVisit' => time(),
                    'notificationData' => array(
                        'notificationsSent' => false,
                        'lastNotificationTS' => false,
                        'botVisitTimeAtNotification' => time()
                    )
                )
            )
        );

        \WP_Mock::wpFunction(
            'update_option',
            array(
                'called' => 1,
                'args' => array(
                    'passive_indexation_check_settings', '*'
                ),
                'return' => true
            )
        );
        
        \WP_Mock::wpFunction(
            'add_options_page',
            array(
                'called' => 1,
                'args' => array(
                    'Passive Indexation Check', 'Passive Indexation Check', 'administrator', '*', '*'
                )
            )
        );

        $plugin = new PassiveIndexationCheck;
        $plugin->__construct();

        // Set user agent to Google's user agent and check if the function goes through
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';
        $botVisit = $plugin->checkBotVisit();
        $this->assertGreaterThan(time() - 100, $botVisit);

        unset($_SERVER['HTTP_USER_AGENT']);
        $botVisit = $plugin->checkBotVisit();
        $this->assertEquals(false, $botVisit);

        // Send other agent
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (iPad; U; CPU OS 3_2_1 like Mac OS X; en-us) AppleWebKit/531.21.10 (KHTML, like Gecko) Mobile/7B405';
        $botVisit = $plugin->checkBotVisit();
        $this->assertEquals(false, $botVisit);
    }

    public function testInit()
    {
        \WP_Mock::wpFunction(
            'add_options_page',
            array(
                'called' => 1,
                'args' => array(
                    'Passive Indexation Check', 'Passive Indexation Check', 'administrator', '*', '*'
                )
            )
        );

        $plugin = new PassiveIndexationCheck;

        \WP_Mock::expectActionAdded('do_robots', array($plugin, 'checkBotVisit'));
        \WP_Mock::expectActionAdded('init', array($plugin, 'notificationsHook'));
        $plugin->__construct();
        \WP_Mock::assertHooksAdded();
    }

    public function testUpdateSettingsAjaxNoNonce()
    {
        $plugin = new PassiveIndexationCheck;

        // Check corner case if no nonce is provided
        \WP_Mock::wpFunction(
            'wp_send_json_error',
            array(
                'called' => 1
            )
        );

        $plugin->updateSettings();
    }

    public function testUpdateSettingsInvalidNonce()
    {
        $plugin = new PassiveIndexationCheck;

        // Check corner case if nonce is invalid
        \WP_Mock::wpFunction(
            'wp_verify_nonce',
            array(
                'called' => 1,
                'args' => array(
                    \WP_Mock\Functions::type('string'),
                    'passive_indexation_check_nonce'
                ),
                'return' => false
            )
        );

        \WP_Mock::wpFunction(
            'wp_send_json_error',
            array(
                'called' => 1
            )
        );

        $_POST['nonce'] = 'nonce';
        $_POST['notification_time'] = '1';

        $plugin->updateSettings();
    }

    public function testUpdateSettingsValidNonce()
    {
        $plugin = new PassiveIndexationCheck;

        \WP_Mock::wpFunction(
            'get_option',
            array(
                'called' => 1,
                'args' => array(
                    'passive_indexation_check_settings'
                ),
                'return' => array(
                    'notificationEmails' => array('some@some.com', 'boo@boo.com'),
                    'notificationTime' => 1,
                    'resendEmailTime' => 10,
                    'lastBotVisit' => time(),
                    'notificationData' => array(
                        'notificationsSent' => false,
                        'lastNotificationTS' => false,
                        'botVisitTimeAtNotification' => time()
                    )
                )
            )
        );

        // Check corner case if nonce is correctly verified
        \WP_Mock::wpFunction(
            'wp_verify_nonce',
            array(
                'called' => 1,
                'args' => array(
                    \WP_Mock\Functions::type('string'),
                    'passive_indexation_check_nonce'
                ),
                'return' => true
            )
        );

        \WP_Mock::wpFunction(
            'wp_send_json_success',
            array(
                'called' => 1
            )
        );

        $_POST['nonce'] = 'nonce';
        $_POST['notification_time'] = '1';

        $plugin->updateSettings();
    }

    public function testAddEmailNoNonce()
    {
        $plugin = new PassiveIndexationCheck;

        // Check corner case if no nonce is provided
        \WP_Mock::wpFunction(
            'wp_send_json_error',
            array(
                'called' => 1
            )
        );

        $plugin->addNotifierEmail();
    }

    public function testAddEmailInvalidNonce()
    {
        $plugin = new PassiveIndexationCheck;

        // Check corner case if nonce is invalid
        \WP_Mock::wpFunction(
            'wp_verify_nonce',
            array(
                'called' => 1,
                'args' => array(
                    \WP_Mock\Functions::type('string'),
                    'passive_indexation_check_nonce'
                ),
                'return' => false
            )
        );

        \WP_Mock::wpFunction(
            'wp_send_json_error',
            array(
                'called' => 1
            )
        );

        $_POST['nonce'] = 'nonce';
        $_POST['notification_time'] = '1';

        $plugin->addNotifierEmail();
    }

    public function testAddEmailValidNonceAndNotifier()
    {
        $plugin = new PassiveIndexationCheck;

        \WP_Mock::wpFunction(
            'get_option',
            array(
                'called' => 1,
                'args' => array(
                    'passive_indexation_check_settings'
                ),
                'return' => array(
                    'notificationEmails' => array('some@some.com', 'boo@boo.com'),
                    'notificationTime' => 1,
                    'resendEmailTime' => 10,
                    'lastBotVisit' => time(),
                    'notificationData' => array(
                        'notificationsSent' => false,
                        'lastNotificationTS' => false,
                        'botVisitTimeAtNotification' => time()
                    )
                )
            )
        );

        // Check corner case if nonce is correctly verified
        \WP_Mock::wpFunction(
            'wp_verify_nonce',
            array(
                'called' => 1,
                'args' => array(
                    \WP_Mock\Functions::type('string'),
                    'passive_indexation_check_nonce'
                ),
                'return' => true
            )
        );

        \WP_Mock::wpFunction(
            'wp_send_json_success',
            array(
                'called' => 1
            )
        );

        $_POST['nonce'] = 'nonce';
        $_POST['addedNotifier'] = 'foo@foo.com';

        $plugin->addNotifierEmail();
    }

    public function testAddEmailInValidNotifier()
    {
        $plugin = new PassiveIndexationCheck;

        \WP_Mock::wpFunction(
            'get_option',
            array(
                'called' => 1,
                'args' => array(
                    'passive_indexation_check_settings'
                ),
                'return' => array(
                    'notificationEmails' => array('some@some.com', 'boo@boo.com'),
                    'notificationTime' => 1,
                    'resendEmailTime' => 10,
                    'lastBotVisit' => time(),
                    'notificationData' => array(
                        'notificationsSent' => false,
                        'lastNotificationTS' => false,
                        'botVisitTimeAtNotification' => time()
                    )
                )
            )
        );

        // Check corner case if nonce is correctly verified
        \WP_Mock::wpFunction(
            'wp_verify_nonce',
            array(
                'called' => 1,
                'args' => array(
                    \WP_Mock\Functions::type('string'),
                    'passive_indexation_check_nonce'
                ),
                'return' => true
            )
        );

        \WP_Mock::wpFunction(
            'wp_send_json_error',
            array(
                'called' => 1
            )
        );

        $_POST['nonce'] = 'nonce';
        $_POST['addedNotifier'] = 'foo';

        $plugin->addNotifierEmail();
    }

    public function testDeleteEmailNoNonce()
    {
        $plugin = new PassiveIndexationCheck;

        // Check corner case if no nonce is provided
        \WP_Mock::wpFunction(
            'wp_send_json_error',
            array(
                'called' => 1
            )
        );

        $plugin->deleteNotifierEmail();
    }

    public function testDeleteEmailInvalidNonce()
    {
        $plugin = new PassiveIndexationCheck;

        // Check corner case if nonce is invalid
        \WP_Mock::wpFunction(
            'wp_verify_nonce',
            array(
                'called' => 1,
                'args' => array(
                    \WP_Mock\Functions::type('string'),
                    'passive_indexation_check_nonce'
                ),
                'return' => false
            )
        );

        \WP_Mock::wpFunction(
            'wp_send_json_error',
            array(
                'called' => 1
            )
        );

        $_POST['nonce'] = 'nonce';
        $_POST['notification_time'] = '1';

        $plugin->deleteNotifierEmail();
    }

    public function testDeleteEmailValidNonceAndNotifier()
    {
        $plugin = new PassiveIndexationCheck;

        \WP_Mock::wpFunction(
            'get_option',
            array(
                'called' => 1,
                'args' => array(
                    'passive_indexation_check_settings'
                ),
                'return' => array(
                    'notificationEmails' => array('some@some.com', 'boo@boo.com'),
                    'notificationTime' => 1,
                    'resendEmailTime' => 10,
                    'lastBotVisit' => time(),
                    'notificationData' => array(
                        'notificationsSent' => false,
                        'lastNotificationTS' => false,
                        'botVisitTimeAtNotification' => time()
                    )
                )
            )
        );

        // Check corner case if nonce is correctly verified
        \WP_Mock::wpFunction(
            'wp_verify_nonce',
            array(
                'called' => 1,
                'args' => array(
                    \WP_Mock\Functions::type('string'),
                    'passive_indexation_check_nonce'
                ),
                'return' => true
            )
        );

        \WP_Mock::wpFunction(
            'wp_send_json_success',
            array(
                'called' => 1
            )
        );

        $_POST['nonce'] = 'nonce';
        $_POST['deleteNotifier'] = 'some@some.com';

        $plugin->deleteNotifierEmail();
    }

    public function testDeleteEmailInValidNotifier()
    {
        $plugin = new PassiveIndexationCheck;

        \WP_Mock::wpFunction(
            'get_option',
            array(
                'called' => 1,
                'args' => array(
                    'passive_indexation_check_settings'
                ),
                'return' => array(
                    'notificationEmails' => array('some@some.com', 'boo@boo.com'),
                    'notificationTime' => 1,
                    'resendEmailTime' => 10,
                    'lastBotVisit' => time(),
                    'notificationData' => array(
                        'notificationsSent' => false,
                        'lastNotificationTS' => false,
                        'botVisitTimeAtNotification' => time()
                    )
                )
            )
        );

        // Check corner case if nonce is correctly verified
        \WP_Mock::wpFunction(
            'wp_verify_nonce',
            array(
                'called' => 1,
                'args' => array(
                    \WP_Mock\Functions::type('string'),
                    'passive_indexation_check_nonce'
                ),
                'return' => true
            )
        );

        \WP_Mock::wpFunction(
            'wp_send_json_error',
            array(
                'called' => 1
            )
        );

        $_POST['nonce'] = 'nonce';
        $_POST['addedNotifier'] = 'boo@boo.com';

        $plugin->deleteNotifierEmail();
    }

    public function testActivate()
    {
        $plugin = new PassiveIndexationCheck;
        \WP_Mock::wpFunction(
            'add_option',
            array(
                'called' => 1,
                'args' => array(
                    'passive_indexation_check_settings', '*'
                )
            )
        );
        $plugin->activatePlugin();
    }

    public function testDectivate()
    {
        $plugin = new PassiveIndexationCheck;
        \WP_Mock::wpFunction(
            'delete_option',
            array(
                'called' => 1,
                'args' => array('passive_indexation_check_settings')
            )
        );
        $plugin->deactivatePlugin();
    }
}
