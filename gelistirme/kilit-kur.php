<?php
$_SERVER['HTTP_HOST'] = 'localhost:8765';
require '/home/user/wordpress/site/wp-load.php';
require_once WP_PLUGIN_DIR . '/yuzyil-panel/includes/class-yp-bicim.php';
$ayarlar = get_option( 'yp_ayarlar', array() );
$ayarlar = is_array( $ayarlar ) ? $ayarlar : array();
$ayarlar['kilit_kullanici'] = 'yonetici';
$ayarlar['kilit_hash']      = wp_hash_password( 'YerelDeneme2026!' );
$ayarlar['kurum_adi']       = 'Yüzyıl Psikoteknik Merkezi';
update_option( 'yp_ayarlar', $ayarlar );
echo "panel kilidi kuruldu (deneme şifresi yerelde saklı)\n";
