<?php
define( 'WP_INSTALLING', true );
$_SERVER['HTTP_HOST']   = 'localhost:8765';
$_SERVER['REQUEST_URI'] = '/';
require '/home/user/wordpress/site/wp-load.php';
require ABSPATH . 'wp-admin/includes/upgrade.php';

if ( ! is_blog_installed() ) {
	$sonuc = wp_install( 'Yüzyıl Deneme', 'yonetici', 'deneme@ornek.test', true, '', 'YerelDeneme2026!' );
	echo "WordPress kuruldu, yönetici: yonetici (kullanıcı no {$sonuc['user_id']})\n";
} else {
	echo "WordPress zaten kurulu\n";
}
