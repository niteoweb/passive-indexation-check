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
        add_action('do_robots', array(&$this, 'checkBotVisit'));
        add_action('init', array(&$this, 'notificationsHook'));
        add_action('admin_init', array(&$this, 'enqueueJSAndCSSFiles'));
        add_action('wp_ajax_passive_indexation_check_update_settings', array(&$this, 'updateSettings'));
        add_action('wp_ajax_passive_indexation_check_delete_email', array(&$this, 'deleteNotifierEmail'));
        add_action('wp_ajax_passive_indexation_check_add_email', array(&$this, 'addNotifierEmail'));
        add_action('admin_menu', array(&$this, 'activateGUI'));
    }

    public function activateGUI()
    {
        add_options_page('Passive Indexation Check', 'Passive Indexation Check', 'administrator', __FILE__, array( &$this, 'loadOptionsGUI'));
    }

    public function loadOptionsGUI()
    {
        $options = get_option('passive_indexation_check_settings');
        $nonce = wp_create_nonce('passive_indexation_check_nonce');

        include_once 'plugindata/view/main_gui.php';
    }

    public function enqueueJSAndCSSFiles()
    {
        wp_enqueue_script('passive-indexation-check-scripts', plugin_dir_url(__FILE__).'plugindata/js/passive_indexation_check.js', array("jquery", "jquery-ui-core"));
        wp_enqueue_script('passive-indexation-check-sweet-alert', plugin_dir_url(__FILE__).'plugindata/js/sweet-alert.min.js', array("jquery", "jquery-ui-core"));
        wp_enqueue_style('passive-indexation-check-sweet-alert-css', plugin_dir_url(__FILE__).'plugindata/css/sweet-alert.css');
    }

    /**
     *
     * Check if the request made for page render is from a bot.
     *
     * @return [boolean] Returns true if visit was made by Google Bot, else false.
     *
     */
    public function checkBotVisit()
    {
        $googleBotVisitTime = false;

        if (!empty($_SERVER['HTTP_USER_AGENT'])) {
            $userAgent = strtolower($_SERVER['HTTP_USER_AGENT']);
            if (strpos($userAgent, 'www.google.com/bot.html') !== false) {
                $options = get_option('passive_indexation_check_settings');
                $googleBotVisitTime = time();
                $options['lastBotVisit'] = $googleBotVisitTime;
                update_option('passive_indexation_check_settings', $options);
            }
        }

        return $googleBotVisitTime;
    }

    /**
     *
     * Check if HTTP request was made by a Google Bot.
     *
     * @return [boolean]    Returns true if page request was done by a Google Bot.
     *
     */
    public function checkIfGoogleBotHeader()
    {
        $googleBotVisit = false;

        if (!empty($_SERVER['HTTP_USER_AGENT'])) {
            $userAgent = strtolower($_SERVER['HTTP_USER_AGENT']);
            if (strpos($userAgent, 'www.google.com/bot.html') !== false) {
                $googleBotVisit = true;
            }
        }

        return $googleBotVisit;

    }

    public function notificationsHook()
    {
        $options = get_option('passive_indexation_check_settings');
        return $this->sendNotificationEmails($options);
    }

    /**
     *
     * Send emails if the Google Bot hasn't visited the page in the specified time.
     *
     * @param  [array]      $options   Plugin options.
     *
     * @return [boolean]               Returns true if emails were sent or false if not.
     *
     */
    public function sendNotificationEmails(&$options)
    {
        $lastVisit = $options['lastBotVisit'];
        $notificationTime = $options['notificationTime'] * 24 * 60 * 60;
        $currentTS = time();

        $sendEmailNotifications = false;

        // Notifications were sent, but lets check if the bot visit time has changed
        if ($options['notificationData']['notificationsSent']) {
            // Bot has visited our page after we sent out the notifications, revert to normal time
            // checking ...
            if ($options['notificationData']['botVisitTimeAtNotification'] < $options['lastBotVisit']) {
                $options['notificationData']['notificationsSent'] = false;
                update_option('passive_indexation_check_settings', $options);
            } else { // Check out if n days have passed since we last sent out the notification ...
                if ($currentTS - $options['notificationData']['lastNotificationTS'] > $options['resendEmailTime'] * 24 * 60 * 60) {
                    $sendEmailNotifications = true;
                }
            }
        } else {
            if ($currentTS - $options['lastBotVisit'] > $notificationTime) {
                $sendEmailNotifications = true;
            }
        }

        if ($sendEmailNotifications) {
            $emails = $options['notificationEmails'];
            $sentEmails = array();
            if (count($emails) > 0) {
                foreach ($emails as $key => $email) {
                    $subject = 'Google Bot visit';
                    $message = 'Your page has not been visited by Google Bot for '
                        . $options['notificationTime'] . ' day(s)';
                    $success = wp_mail($email, $subject, $message);
                    if ($success) {
                        array_push($sentEmails, $email);
                    }
                }
                if (count($sentEmails) > 0) {
                    $options['notificationData']['lastNotificationTS'] = $currentTS;
                    $options['notificationData']['notificationsSent'] = true;
                    $options['notificationData']['botVisitTimeAtNotification'] = $options['lastBotVisit'];
                }
                update_option('passive_indexation_check_settings', $options);
                return $sentEmails;
            }
        }
        return false;
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
        if (isset($_POST['nonce'])) {
            $nonceCheck = wp_verify_nonce($_POST['nonce'], 'passive_indexation_check_nonce');

            if ($nonceCheck) {
                $options = get_option('passive_indexation_check_settings');
                $options['notificationTime'] = $_POST['notification_time'];
                update_option('passive_indexation_check_settings', $options);
                wp_send_json_success($options);
            } else {
                $data = array(
                    'msg' => 'Invalid authentication. Please refresh your page.'
                );
                wp_send_json_error($data);
            }
        } else {
            $data = array(
                'msg' => 'No nonce value was provided.'
            );
            wp_send_json_error($data);
        }
    }

    /**
     *
     * AJAX call for adding notification email.
     *
     */
    public function addNotifierEmail()
    {
        if (isset($_POST['nonce'])) {
            $nonceCheck = wp_verify_nonce($_POST['nonce'], 'passive_indexation_check_nonce');

            if ($nonceCheck) {
                if (!isset($_POST['addedNotifier'])) {
                    $data = array(
                        'msg' => 'Notifier was not sent or invalid data.'
                    );
                    wp_send_json_error($data);
                    return;
                }

                $options = get_option('passive_indexation_check_settings');
                $newNotifier = $_POST['addedNotifier'];

                if (!filter_var($newNotifier, FILTER_VALIDATE_EMAIL)) {
                    $data = array(
                        'msg' => 'Please enter a valid email address.'
                    );
                    wp_send_json_error($data);
                    return;
                }
                if (in_array($newNotifier, $options['notificationEmails'])) {
                    $data = array(
                        'msg' => 'Email already exists.'
                    );
                    wp_send_json_error($data);
                } else {
                    array_push($options['notificationEmails'], $newNotifier);
                    update_option('passive_indexation_check_settings', $options);
                    $options['msg'] = 'Email ' . $newNotifier . ' was successfully added to notifications list.';
                    wp_send_json_success($options);
                }
            } else {
                $data = array(
                    'msg' => 'Invalid authentication. Please refresh your page.'
                );
                wp_send_json_error($data);
            }
        } else {
            $data = array(
                'msg' => 'No nonce value was provided.'
            );
            wp_send_json_error($data);
        }
    }

    /**
     *
     * AJAX call for deleting notification email.
     *
     */
    public function deleteNotifierEmail()
    {
        if (isset($_POST['nonce'])) {
            $nonceCheck = wp_verify_nonce($_POST['nonce'], 'passive_indexation_check_nonce');

            if ($nonceCheck) {
                if (!isset($_POST['deleteNotifier'])) {
                    $data = array(
                        'msg' => 'Notifier was not sent or invalid data.'
                    );
                    wp_send_json_error($data);
                    return;
                }
                $options = get_option('passive_indexation_check_settings');
                $deleteNotifier = $_POST['deleteNotifier'];

                if (($key = array_search($deleteNotifier, $options['notificationEmails'])) !== false) {
                    unset($options['notificationEmails'][$key]);
                    update_option('passive_indexation_check_settings', $options);
                    $options['msg'] = 'Email ' . $deleteNotifier . ' was successfully removed from notifications list.';
                    wp_send_json_success($options);
                } else {
                    $data = array(
                        'msg' => 'Notification email does not exist.'
                    );
                    wp_send_json_error($data);
                }
            } else {
                $data = array(
                    'msg' => 'Invalid authentication. Please refresh your page.'
                );
                wp_send_json_error($data);
            }
        } else {
            $data = array(
                'msg' => 'No nonce value was provided.'
            );
            wp_send_json_error($data);
        }
    }

    public function activatePlugin()
    {
        $options = array(
            'notificationEmails' => array('some@some.com', 'boo@boo.com'),
            'notificationTime' => 1,
            'resendEmailTime' => 10,
            'lastBotVisit' => time(),
            'notificationData' => array(
                'notificationsSent' => false,
                'lastNotificationTS' => false,
                'botVisitTimeAtNotification' => time()
            )
        );
        add_option('passive_indexation_check_settings', $options);
    }

    public function deactivatePlugin()
    {
        delete_option('passive_indexation_check_settings');
    }
}

// Inside WordPress
if (defined('ABSPATH')) {
    $PassiveIndexationCheck_ins = new PassiveIndexationCheck;
    register_activation_hook(__FILE__, array( &$PassiveIndexationCheck_ins, 'activatePlugin' ));
    register_deactivation_hook(__FILE__, array( &$PassiveIndexationCheck_ins, 'deactivatePlugin' ));
}
