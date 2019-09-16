<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    die;
}

$option_name = 'wpmsod_site_id';

delete_site_option($option_name);
