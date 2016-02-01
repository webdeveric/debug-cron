<?php
/*
Plugin Name: Debug Cron
Plugin Group: Utilities
Version: 0.2.0
Description: Check for WordPress wp-cron issues
Author: Eric King
Author URI: http://webdeveric.com/
*/

namespace webdeveric\DebugCron;

defined('ABSPATH') || exit;

if (! is_admin()) {
    return;
}

if (! class_exists('Composer\Autoload\ClassLoader', false)) {
    require_once 'src/DebugCron.php';
}

add_action('plugins_loaded', function () {

    new DebugCron;

}, PHP_INT_MAX, 0);
