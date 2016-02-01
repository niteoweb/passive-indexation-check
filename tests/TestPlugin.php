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

    public function testShouldEmailBeSent()
    {
        $plugin = new PassiveIndexationCheck;

        // Case 1: Email has not been sent yet and the time has not passed yet
        $emailData = array(
            'sent' => false,
            'lastSentTS' => false
        );

        $currentTS = time();
        $sendTreshold = 1;
        $resendTreshold = 10;
        $lastBotVisit = time() - 0.5 * $sendTreshold * 24 * 60;

        $emailSent = $plugin->shouldEmailBeSent($lastBotVisit, $currentTS, $sendTreshold, $resendTreshold, $emailData);
        $this->assertEquals(false, $emailSent);

        // Case 2: Email has not been sent yet and the time has passed
        $lastBotVisit = $currentTS - 2 * $sendTreshold * 24 * 60;

        $emailSent = $plugin->shouldEmailBeSent($lastBotVisit, $currentTS, $sendTreshold, $resendTreshold, $emailData);
        $this->assertEquals(true, $emailSent);

        // Case 3: Email has been sent, the resend time has passed and the bot time visit has not changed
        $emailData = array(
            'sent' => true,
            'lastSentTS' => $currentTS - 1.1 * $resendTreshold * 24 * 60,
            'botVisitTimeAtNotification' => $currentTS
        );

        $lastBotVisit = $currentTS;

        $emailSent = $plugin->shouldEmailBeSent($lastBotVisit, $currentTS, $sendTreshold, $resendTreshold, $emailData);
        $this->assertEquals(true, $emailSent);

        // Case 4: Email has been sent, the resend time has not passed yet
        $emailData = array(
            'sent' => true,
            'lastSentTS' => $currentTS - 0.5 * $resendTreshold * 24 * 60,
            'botVisitTimeAtNotification' => $currentTS
        );
        $emailSent = $plugin->shouldEmailBeSent($lastBotVisit, $currentTS, $sendTreshold, $resendTreshold, $emailData);
        $this->assertEquals(false, $emailSent);

        // Case 5: Last bot time visit changed and treshold has not passed yet
        $emailData = array(
            'sent' => true,
            'lastSentTS' => $currentTS - 0.5 * $resendTreshold * 24 * 60,
            'botVisitTimeAtNotification' => $currentTS + 1
        );

        $emailSent = $plugin->shouldEmailBeSent($lastBotVisit, $currentTS, $sendTreshold, $resendTreshold, $emailData);
        $this->assertEquals(false, $emailSent);

        // Case 6: Last bot time visit changed and treshold passed
        $emailData = array(
            'sent' => true,
            'lastSentTS' => $currentTS - 0.5 * $resendTreshold * 24 * 60,
            'botVisitTimeAtNotification' => $currentTS + 1
        );

        $lastBotVisit = $currentTS - 2 * $sendTreshold * 24 * 60;
        $emailSent = $plugin->shouldEmailBeSent($lastBotVisit, $currentTS, $sendTreshold, $resendTreshold, $emailData);
        $this->assertEquals(true, $emailSent);

    }

    public function testSendNotificationEmailsTaskEmptyArray()
    {
        \WP_Mock::wpFunction(
            'get_option',
            array(
                'times' => 1,
                'args' => array(
                    'passive_indexation_check_emails'
                ),
                'return' => array(
                )
            )
        );

        \WP_Mock::wpFunction(
            'update_option',
            array(
                'times' => 0,
                'args' => array(
                    'passive_indexation_check_emails'
                ),
                'return' => true
            )
        );

        $plugin = new PassiveIndexationCheck;
        $plugin->sendNotificationEmailsTask();
    }

    public function testSendNotificationEmailsTaskNonEmptyArray()
    {
        \WP_Mock::wpFunction(
            'get_option',
            array(
                'times' => 1,
                'args' => array(
                    'passive_indexation_check_settings'
                ),
                'return' => array(
                    'sendTreshold' => 1,
                    'resendTreshold' => 10,
                    'lastBotVisit' => time(),
                    'version' => 1.0
                )
            )
        );

        \WP_Mock::wpFunction(
            'get_option',
            array(
                'times' => 1,
                'args' => array(
                    'passive_indexation_check_emails'
                ),
                'return' => array(
                    'foo@foo.com' => array(
                        'sent' => false,
                        'lastSentTS' => false,
                        'botVisitTimeAtNotification' => false
                    )
                )
            )
        );

        \WP_Mock::wpFunction(
            'update_option',
            array(
                'times' => 1,
                'args' => array(
                    'passive_indexation_check_emails',
                    '*'
                ),
                'return' => true
            )
        );

        $plugin = new PassiveIndexationCheck;
        $plugin->sendNotificationEmailsTask();
    }

    public function testGoogleBotVisit()
    {
        \WP_Mock::wpFunction(
            'get_option',
            array(
                'times' => 1,
                'args' => array(
                    'passive_indexation_check_settings'
                ),
                'return' => array(
                    'sendTreshold' => 1,
                    'resendTreshold' => 10,
                    'lastBotVisit' => time(),
                    'version' => 1.0
                )
            )
        );

        \WP_Mock::wpFunction(
            'update_option',
            array(
                'times' => 1,
                'args' => array(
                    'passive_indexation_check_settings',
                    '*'
                ),
                'return' => true
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
        $plugin = new PassiveIndexationCheck;

        \WP_Mock::expectActionAdded('do_robots', array($plugin, 'checkBotVisit'));
        \WP_Mock::expectActionAdded('admin_init', array($plugin, 'enqueueJSAndCSSFiles'));
        \WP_Mock::expectActionAdded('wp_ajax_passive_indexation_check_update_settings', array($plugin, 'updateSettings'));
        \WP_Mock::expectActionAdded('wp_ajax_passive_indexation_check_delete_email', array($plugin, 'deleteNotifierEmail'));
        \WP_Mock::expectActionAdded('wp_ajax_passive_indexation_check_add_email', array($plugin, 'addNotifierEmail'));
        \WP_Mock::expectActionAdded('admin_menu', array($plugin, 'activateGUI'));
        \WP_Mock::expectActionAdded('admin_notices', array($plugin, 'emailNoticeGUI'));
        \WP_Mock::expectActionAdded('admin_notices', array($plugin, 'noticeGUI'));
        \WP_Mock::expectActionAdded('passive_indexation_check_send_emails', array($plugin, 'sendNotificationEmailsTask'));

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
                'times' => 1
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
                'times' => 1,
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
                'times' => 1
            )
        );

        $_POST['nonce'] = 'nonce';
        $_POST['send_treshold'] = '1';

        $plugin->updateSettings();
    }

    public function testUpdateSettingsValidNonce()
    {
        $plugin = new PassiveIndexationCheck;

        \WP_Mock::wpFunction(
            'get_option',
            array(
                'times' => 1,
                'args' => array(
                    'passive_indexation_check_settings'
                ),
                'return' => array(
                    'sendTreshold' => 1,
                    'resendTreshold' => 10,
                    'lastBotVisit' => time(),
                    'version' => 1.0
                )
            )
        );

        \WP_Mock::wpFunction(
            'get_option',
            array(
                'times' => 1,
                'args' => array(
                    'passive_indexation_check_emails'
                ),
                'return' => array(
                )
            )
        );

        \WP_Mock::wpFunction(
            'update_option',
            array(
                'times' => 1,
                'args' => array(
                    'passive_indexation_check_settings',
                    '*'
                ),
                'return' => array(
                )
            )
        );

        // Check corner case if nonce is correctly verified
        \WP_Mock::wpFunction(
            'wp_verify_nonce',
            array(
                'times' => 1,
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
                'times' => 1
            )
        );

        $_POST['nonce'] = 'nonce';
        $_POST['send_treshold'] = '1';

        $plugin->updateSettings();
    }

    public function testAddEmailNoNonce()
    {
        $plugin = new PassiveIndexationCheck;

        // Check corner case if no nonce is provided
        \WP_Mock::wpFunction(
            'wp_send_json_error',
            array(
                'times' => 1
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
                'times' => 1,
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
                'times' => 1
            )
        );

        $_POST['nonce'] = 'nonce';

        $plugin->addNotifierEmail();
    }

    public function testAddEmailValidNonceAndNotifier()
    {
        $plugin = new PassiveIndexationCheck;

        \WP_Mock::wpFunction(
            'get_option',
            array(
                'times' => 1,
                'args' => array(
                    'passive_indexation_check_emails'
                ),
                'return' => array(
                )
            )
        );

        // Check corner case if nonce is correctly verified
        \WP_Mock::wpFunction(
            'wp_verify_nonce',
            array(
                'times' => 1,
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
                'times' => 1
            )
        );

        $_POST['nonce'] = 'nonce';
        $_POST['added_notifier'] = 'foo@foo.com';

        $plugin->addNotifierEmail();
    }

    public function testAddEmailInValidNotifier()
    {
        $plugin = new PassiveIndexationCheck;

        // Check corner case if nonce is correctly verified
        \WP_Mock::wpFunction(
            'wp_verify_nonce',
            array(
                'times' => 1,
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
                'times' => 1
            )
        );

        $_POST['nonce'] = 'nonce';
        $_POST['added_notifier'] = 'foo';

        $plugin->addNotifierEmail();
    }

    public function testDeleteEmailNoNonce()
    {
        $plugin = new PassiveIndexationCheck;

        // Check corner case if no nonce is provided
        \WP_Mock::wpFunction(
            'wp_send_json_error',
            array(
                'times' => 1
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
                'times' => 1,
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
                'times' => 1
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
                'times' => 1,
                'args' => array(
                    'passive_indexation_check_emails'
                ),
                'return' => array(
                    'foo@foo.com' => array(
                    )
                )
            )
        );

        // Check corner case if nonce is correctly verified
        \WP_Mock::wpFunction(
            'wp_verify_nonce',
            array(
                'times' => 1,
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
                'times' => 1
            )
        );

        $_POST['nonce'] = 'nonce';
        $_POST['delete_notifier'] = 'foo@foo.com';

        $plugin->deleteNotifierEmail();
    }

    public function testDeleteEmailInValidNotifier()
    {

        $plugin = new PassiveIndexationCheck;

        \WP_Mock::wpFunction(
            'get_option',
            array(
                'times' => 1,
                'args' => array(
                    'passive_indexation_check_emails'
                ),
                'return' => array(
                    'foo@foo.com' => array(
                    )
                )
            )
        );

        // Check corner case if nonce is correctly verified
        \WP_Mock::wpFunction(
            'wp_verify_nonce',
            array(
                'times' => 1,
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
                'times' => 1
            )
        );

        $_POST['nonce'] = 'nonce';
        $_POST['delete_notifier'] = 'invalid@goo.com';

        $plugin->deleteNotifierEmail();
    }

    public function testActivateOptionsPresent()
    {
        $plugin = new PassiveIndexationCheck;

        \WP_Mock::wpFunction(
            'get_option',
            array(
                'times' => 1,
                'args' => array(
                    'passive_indexation_check_settings',
                ),
                'return' => array()
            )
        );

        \WP_Mock::wpFunction(
            'get_option',
            array(
                'times' => 1,
                'args' => array(
                    'passive_indexation_check_emails',
                ),
                'return' => array()
            )
        );

        \WP_Mock::wpFunction(
            'update_option',
            array(
                'times' => 0,
                'args' => array(
                    'passive_indexation_check_settings', '*'
                )
            )
        );

        \WP_Mock::wpFunction(
            'update_option',
            array(
                'times' => 0,
                'args' => array(
                    'passive_indexation_check_emails', '*'
                )
            )
        );

        \WP_Mock::wpFunction(
            'wp_schedule_event',
            array(
                'times' => 1,
                'args' => array(
                    \WP_Mock\Functions::type('long'),
                    \WP_Mock\Functions::type('string'),
                    \WP_Mock\Functions::type('string')
                )
            )
        );

        $plugin->activatePlugin();
    }

    public function testActivateOptionsNotPresent()
    {
        $plugin = new PassiveIndexationCheck;

        \WP_Mock::wpFunction(
            'get_option',
            array(
                'times' => 1,
                'args' => array(
                    'passive_indexation_check_settings',
                ),
                'return' => false
            )
        );

        \WP_Mock::wpFunction(
            'get_option',
            array(
                'times' => 1,
                'args' => array(
                    'passive_indexation_check_emails',
                ),
                'return' => false
            )
        );

        \WP_Mock::wpFunction(
            'update_option',
            array(
                'times' => 1,
                'args' => array(
                    'passive_indexation_check_settings', '*'
                )
            )
        );

        \WP_Mock::wpFunction(
            'update_option',
            array(
                'times' => 1,
                'args' => array(
                    'passive_indexation_check_emails', '*'
                )
            )
        );

        \WP_Mock::wpFunction(
            'wp_schedule_event',
            array(
                'times' => 1,
                'args' => array(
                    \WP_Mock\Functions::type('long'),
                    \WP_Mock\Functions::type('string'),
                    \WP_Mock\Functions::type('string')
                )
            )
        );

        $plugin->activatePlugin();
    }

    public function testDectivate()
    {
        $plugin = new PassiveIndexationCheck;

        \WP_Mock::wpFunction(
            'wp_clear_scheduled_hook',
            array(
                'times' => 1,
                'args' => array('passive_indexation_check_send_emails')
            )
        );

        $plugin->deactivatePlugin();
    }
}
