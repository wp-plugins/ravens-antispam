<?php
/*
Raven's Antispam Uninstallator
@see http://www.santosj.name/general/wordpress-27-plugin-uninstall-methods/
@todo Test if it works at all
*/

if(!defined('ABSPATH') && !defined('WP_UNINSTALL_PLUGIN'))
	exit;

delete_option('ras_always_visible');
delete_option('ras_own_template_code');
delete_option('ras_template_code');