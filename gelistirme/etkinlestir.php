<?php
$_SERVER['HTTP_HOST']   = 'localhost:8765';
$_SERVER['REQUEST_URI'] = '/';
require '/home/user/wordpress/site/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$sonuc = activate_plugin( 'yuzyil-panel/yuzyil-panel.php' );
if ( is_wp_error( $sonuc ) ) {
	echo "HATA: " . $sonuc->get_error_message() . "\n";
	exit( 1 );
}
echo is_plugin_active( 'yuzyil-panel/yuzyil-panel.php' ) ? "eklenti etkin\n" : "eklenti etkin değil\n";
global $wpdb;
$tablolar = $wpdb->get_col( "SHOW TABLES LIKE '{$wpdb->prefix}yp_%'" );
echo "kurulan tablolar (" . count( $tablolar ) . "): " . implode( ', ', array_map( function ( $t ) { return str_replace( 'wp_yp_', '', $t ); }, $tablolar ) ) . "\n";
