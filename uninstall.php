<?php
// if uninstall not called from WordPress exit
if( !defined( 'WP_UNINSTALL_PLUGIN' ) )
exit ();
// Delete option from options table
delete_option( 'boj_myplugin_options' );
//remove any additional options and custom tables
function pluginUninstall() {

        global $wpdb;
        $table = $wpdb->prefix."wowguildstats";

        //Delete any options thats stored also?
	//delete_option('wp_yourplugin_version');

	$wpdb->query("DROP TABLE IF EXISTS $table");
	delete_option( 'wgs_guildname' );
	delete_option( 'wgs_realm' );
}
?> 