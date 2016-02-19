<?php

namespace webdeveric\DebugCron;

class DebugCron
{
    /**
     * @var array
     */
    protected $messages;

    public function __construct()
    {
        add_action('admin_notices', array($this, 'adminNotices'));
        $this->messages = array();
        $this->check();
    }

    /**
     * Add a message
     *
     * @param string $msg
     * @param string $type
     * @return void
     */
    public function addMessage($msg, $type = 'updated')
    {
        if (! isset($this->messages[ $type ])) {
            $this->messages[ $type ] = array();
        }
        $this->messages[ $type ][] = $msg;
    }

    /**
     * Add an error message
     *
     * @param string $msg
     * @return void
     */
    public function addErrorMessage($msg)
    {
        if (is_wp_error($msg)) {
            $msg = $msg->get_error_message();
        }
        $this->addMessage($msg, 'error');
    }

    /**
     * Get the messages formatted as HTML
     *
     * @return string
     */
    protected function getMessagesHTML()
    {
        $html = '';

        foreach ($this->messages as $class_name => $messages) {
            $html .= '<div class="' . esc_attr($class_name) . '">';

            foreach ($messages as $message) {
                $html .= '<p>' . $message . '</p>';
            }

            $html .= '</div>';
        }

        return $html;
    }

    /**
     * Display the messages in the admin
     *
     * @return void
     */
    public function adminNotices()
    {
        if (empty($this->messages)) {
            return;
        }

        echo $this->getMessagesHTML();

        $this->messages = array();
    }

    /**
     * Check for wp cron issues
     *
     * @return void
     */
    public function check()
    {
        $host          = parse_url(site_url(), PHP_URL_HOST);
        $dns           = dns_get_record($host, DNS_A);
        $dns_ip        = $dns !== false ? $dns[ 0 ]['ip'] : null;
        $ipv4          = gethostbyname($host);
        $ipv4_filtered = filter_var($ipv4, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);

        $cron_url = add_query_arg(
            'doing_wp_cron',
            sprintf('%.22F', microtime(true)),
            site_url('wp-cron.php')
        );

        $cron_args = array(
            'timeout'   => 0.01,
            'blocking'  => false,
            'sslverify' => apply_filters('https_local_ssl_verify', false)
        );

        $response = wp_remote_post($cron_url, $cron_args);

        if (defined('DISABLE_WP_CRON') && constant('DISABLE_WP_CRON') == true) {
            $this->addMessage('You are not using <code>wp_cron</code> since you have <code>DISABLE_WP_CRON</code> set to true. You should check the cron jobs on your server to make sure they are set correctly.');
        }

        if (is_wp_error($response)) {
            $this->addErrorMessage(sprintf('Your server has a problem reaching <strong><a href="%1$s" target="_blank">%1$s</a></strong>.', $cron_url));

            $codes    = $response->get_error_codes();
            $messages = $response->get_error_messages();

            foreach ($messages as $key => $msg) {
                $this->addErrorMessage(sprintf('<strong>%s</strong>: %s', $codes[ $key ], $msg));
            }
        } else {
            if (isset($response, $response['response'], $response['response']['code'])) {
                if ($response['response']['code'] == 200) {
                    $this->addMessage(sprintf('Your server is able to reach <strong>%s</strong> without any problems.', $cron_url));
                } else {
                    $this->addErrorMessage(sprintf('Your server has a problem reaching <strong>%s</strong>. Error Code: <strong>%d</strong>', $cron_url, $response['response']['code']));
                }
            } else {
                $this->addMessage(sprintf('Your server has a problem reaching <strong>%s</strong>. <xmp>%s</xmp>', $cron_url, print_r($response, true)));
            }
        }

        switch (true) {
            case $ipv4_filtered === false:

                $this->addErrorMessage(
                    sprintf(
                        'Your server resolves <strong>%s</strong> to <strong>%s</strong>, which is a private or reserved IP.<br />
                        Please check your virtual host configuration to make sure this will not be a problem.',
                        $host,
                        $ipv4
                    )
                );

                break;
            case isset($dns_ip, $ipv4) && $dns_ip != $ipv4 && $ipv4_filtered !== false:

                $this->addErrorMessage(
                    sprintf(
                        'Your server resolves <strong>%s</strong> to <strong>%s</strong>, but a DNS lookup has determined the IP should be <strong>%s</strong>.<br />
                         Please check your virtual host configuration &amp; server hosts file to make sure this will not be a problem.',
                        $host,
                        $ipv4,
                        $dns_ip
                    )
                );

                break;
            case isset($dns_ip, $ipv4) && $dns_ip == $ipv4 && $ipv4_filtered !== false && $ipv4 == $ipv4_filtered:
            default:

                $this->addMessage(sprintf('Your server does not have any issues with DNS/hosts file for <strong>%s</strong>.', $host));
        }
    }
}
