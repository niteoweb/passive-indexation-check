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

    protected $optionsKey = 'passive_indexation_check_settings';
    protected $emailsKey = 'passive_indexation_check_emails';

    private $options;
    private $emails = array();

    public function __construct()
    {
        add_action('do_robots', array(&$this, 'checkBotVisit'));
        add_action('admin_init', array(&$this, 'enqueueJSAndCSSFiles'));
        add_action('wp_ajax_passive_indexation_check_update_settings', array(&$this, 'updateSettings'));
        add_action('wp_ajax_passive_indexation_check_delete_email', array(&$this, 'deleteNotifierEmail'));
        add_action('wp_ajax_passive_indexation_check_add_email', array(&$this, 'addNotifierEmail'));
        add_action('admin_menu', array(&$this, 'activateGUI'));
        add_action('admin_notices', array(&$this, 'emailNoticeGUI'));
        add_action('admin_notices', array(&$this, 'noticeGUI'));
        add_action('passive_indexation_check_send_emails', array(&$this, 'sendNotificationEmailsTask'));

        $options = array(
            'sendTreshold' => 1,
            'resendTreshold' => 10,
            'lastBotVisit' => time(),
            'version' => 1.0
        );
        $this->options = $options;
    }

    public function noticeGUI()
    {
        include_once 'view/notice_gui.html';
    }

    public function emailNoticeGUI()
    {
        $emails = get_option($this->emailsKey);
        if (count($emails) == 0) {
            $pluginUrl = sprintf('%s/options-general.php?page=passive-indexation-check/index.php', get_admin_url());
            include_once 'view/email_notice_gui.html';
        }
    }

    public function activateGUI()
    {
        add_options_page('Passive Indexation Check', 'Passive Indexation Check', 'administrator', __FILE__, array(&$this, 'loadOptionsGUI'));
    }

    public function loadOptionsGUI()
    {
        $options = get_option($this->optionsKey);
        $emails = get_option($this->emailsKey);
        $nonce = wp_create_nonce('passive_indexation_check_nonce');

        include_once 'view/main_gui.html';
    }

    public function enqueueJSAndCSSFiles()
    {
        wp_enqueue_script('passive-indexation-check-scripts', plugin_dir_url(__FILE__).'js/passive_indexation_check.js', array("jquery", "jquery-ui-core"));
    }

    /**
     *
     * Check if the request made for page render is from a bot.
     *
     * @return [false | long]   Returns false if bot has not visited page or time of visit.
     *
     */
    public function checkBotVisit()
    {
        $googleBotVisitTime = false;
        $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : false;

        if ($userAgent) {
            $userAgent = strtolower($userAgent);
            if (strpos($userAgent, 'www.google.com/bot.html') !== false) {
                $options = get_option($this->optionsKey);
                $googleBotVisitTime = time();
                $options['lastBotVisit'] = $googleBotVisitTime;
                update_option($this->optionsKey, $options);
            }
        }

        return $googleBotVisitTime;
    }

    /**
     *
     * Check if an email notification will be sent.
     *
     * @param  [long]       $lastBotVisit   Last Google Bot time visit.
     * @param  [long]       $currentTS      Current timestamp.
     * @param  [integer]    $sendTreshold   Send treshold in days (e.g. 2).
     * @param  [integer]    $resendTreshold Resend treshold in days (e.g. 10).
     * @param  [array]      $emailData      Email data array that contains the following:
     *                                      {
     *                                          'sent'                          [true | false]
     *                                          'lastSentTS'                    [long]
     *                                          'botVisitTimeAtNotification'    [long]
     *                                      }
     *
     * @return [boolean]                    Returns true if the email will be sent.
     *
     */
    public function shouldEmailBeSent($lastBotVisit, $currentTS, $sendTreshold, $resendTreshold, $emailData)
    {
        $tresholdPassed = ($currentTS - $lastBotVisit > $sendTreshold * 24 * 60) ? true : false;

        if (!$emailData['sent'] && !$emailData['lastSentTS']) {
            if ($tresholdPassed) {
                return true;
            }
            return false;
        }

        // Resend email if 10 days have passed and the last Google Bot visit time was the same as
        // it was at the first sent email
        $resendTresholdPassed = ($currentTS - $emailData['lastSentTS'] > $resendTreshold * 24 * 60) ? true : false;

        if ($resendTresholdPassed && $lastBotVisit == $emailData['botVisitTimeAtNotification']) {
            return true;
        }
        if ($lastBotVisit != $emailData['botVisitTimeAtNotification'] && $tresholdPassed) {
            return true;
        }
        return false;
    }

    /**
     *
     * Send emails if the Google Bot hasn't visited the page in the specified time.
     *
     */
    public function sendNotificationEmailsTask()
    {
        $emails = get_option($this->emailsKey);

        if (count($emails) == 0) {
            return;
        } else {
            $currentTS = time();

            $options = get_option($this->optionsKey);
            $lastBotVisit = $options['lastBotVisit'];
            $sendTreshold = $options['sendTreshold'];
            $resendTreshold = $options['resendTreshold'];

            $subject = 'Google Bot visit';
            $message = sprintf('Your page has not been visited by Google Bot for %s day(s).', $sendTreshold);

            foreach ($emails as $email => $emailData) {
                if ($this->shouldEmailBeSent($lastBotVisit, $currentTS, $sendTreshold, $resendTreshold, $emailData)) {
                    $emailSent = wp_mail($email, $subject, $message);
                    if ($emailSent) {
                        $emails[$email]['lastSentTS'] = $currentTS;
                        $emails[$email]['botVisitTimeAtNotification'] = $lastBotVisit;
                        $emails[$email]['sent'] = true;
                    }
                }
            }
            update_option($this->emailsKey, $emails);
        }
    }

    /**
     *
     * AJAX call for updating plugin settings.
     *
     * @return [JSON] Returns JSON text response with new options and success code.
     *
     */
    public function updateSettings()
    {
        $response = array();

        if (isset($_POST['nonce'])) {
            $nonceCheck = wp_verify_nonce($_POST['nonce'], 'passive_indexation_check_nonce');

            if ($nonceCheck) {
                $options = get_option($this->optionsKey);
                $options['sendTreshold'] = $_POST['send_treshold'];
                update_option($this->optionsKey, $options);
                $response['options'] = $options;
                $response['emails'] = get_option($this->emailsKey);
                $response['msg'] = 'Settings were successfully updated.';
                wp_send_json_success($response);
            } else {
                $response['msg'] = 'Invalid authentication. Please refresh your page.';
                wp_send_json_error($response);
            }
        } else {
            $response['msg'] = 'No nonce value was provided.';
            wp_send_json_error($response);
        }
    }

    /**
     *
     * AJAX call for adding notification email.
     *
     */
    public function addNotifierEmail()
    {
        $response = array();

        if (isset($_POST['nonce'])) {
            $nonceCheck = wp_verify_nonce($_POST['nonce'], 'passive_indexation_check_nonce');

            if ($nonceCheck) {
                if (!isset($_POST['added_notifier'])) {
                    $response['msg'] = 'Notifier was not sent or invalid data.';
                    wp_send_json_error($response);
                    return;
                }

                $newNotifier = $_POST['added_notifier'];

                if (!filter_var($newNotifier, FILTER_VALIDATE_EMAIL)) {
                    $response['msg'] = 'Please enter a valid email address.';
                    wp_send_json_error($response);
                    return;
                }

                $emails = get_option($this->emailsKey);

                if (array_key_exists($newNotifier, $emails)) {
                    $response['msg'] = 'Email already exists.';
                    wp_send_json_error($response);
                } else {
                    $emails[$newNotifier] = array(
                        'sent' => false,
                        'lastSentTS' => false,
                        'botVisitTimeAtNotification' => false
                    );
                    update_option($this->emailsKey, $emails);
                    $response['emails'] = $emails;
                    $response['msg'] = sprintf('Email %s was successfully added to notifications list.', $newNotifier);
                    wp_send_json_success($response);
                }
            } else {
                $response['msg'] = 'Invalid authentication. Please refresh your page.';
                wp_send_json_error($response);
            }
        } else {
            $response['msg'] = 'No nonce value was provided.';
            wp_send_json_error($response);
        }
    }

    /**
     *
     * AJAX call for deleting notification email.
     *
     */
    public function deleteNotifierEmail()
    {
        $response = array();

        if (isset($_POST['nonce'])) {
            $nonceCheck = wp_verify_nonce($_POST['nonce'], 'passive_indexation_check_nonce');

            if ($nonceCheck) {
                if (!isset($_POST['delete_notifier'])) {
                    echo 'no data';
                    $response['msg'] = 'Notifier was not sent or invalid data.';
                    wp_send_json_error($response);
                    return;
                }

                $deleteNotifier = $_POST['delete_notifier'];
                $emails = get_option($this->emailsKey);

                if (array_key_exists($deleteNotifier, $emails)) {
                    unset($emails[$deleteNotifier]);
                    update_option($this->emailsKey, $emails);
                    $response['msg'] = sprintf('Email %s was successfully removed from notifications list.', $deleteNotifier);
                    $response['emails'] = $emails;
                    wp_send_json_success($response);
                } else {
                    $response['msg'] = 'Notification email does not exist.';
                    wp_send_json_error($response);
                }
            } else {
                $response['msg'] = 'Invalid authentication. Please refresh your page.';
                wp_send_json_error($response);
            }
        } else {
            $response['msg'] = 'No nonce value was provided.';
            wp_send_json_error($response);
        }
    }

    public function activatePlugin()
    {
        if (get_option($this->optionsKey) === false) {
            update_option($this->optionsKey, $this->options);
        }
        if (get_option($this->emailsKey) === false) {
            update_option($this->emailsKey, $this->emails);
        }
        wp_schedule_event(time(), 'daily', 'passive_indexation_check_send_emails');
    }

    public function deactivatePlugin()
    {
        wp_clear_scheduled_hook('passive_indexation_check_send_emails');
    }
}

// Inside WordPress
if (defined('ABSPATH')) {
    $PassiveIndexationCheck_ins = new PassiveIndexationCheck;
    register_activation_hook(__FILE__, array(&$PassiveIndexationCheck_ins, 'activatePlugin'));
    register_deactivation_hook(__FILE__, array(&$PassiveIndexationCheck_ins, 'deactivatePlugin'));
}
