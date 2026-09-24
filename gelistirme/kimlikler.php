<?php
$_SERVER['HTTP_HOST'] = 'localhost:8765';
require '/home/user/wordpress/site/wp-load.php';
global $wpdb;
$aday      = (int) $wpdb->get_var( "SELECT id FROM wp_yp_adaylar WHERE silindi = 0 AND arsiv = 0 ORDER BY aday_no DESC LIMIT 1" );
$borclu    = (int) $wpdb->get_var( "SELECT aday_id FROM wp_yp_hareketler WHERE kayit_turu='ADAY' AND durum='ODENMEDI' AND silindi=0 ORDER BY id LIMIT 1" );
$referans  = (int) $wpdb->get_var( "SELECT id FROM wp_yp_referanslar ORDER BY id LIMIT 1" );
// KURAL: Makbuz tek adayın satırlarıyla açılır — farklı adayların satırları birleştirilemez.
$m_aday    = (int) $wpdb->get_var( "SELECT aday_id FROM wp_yp_hareketler WHERE kayit_turu='ADAY' AND durum='ODENDI' AND silindi=0 GROUP BY aday_id HAVING COUNT(*) > 1 ORDER BY aday_id LIMIT 1" );
$makbuz    = $m_aday ? $wpdb->get_col( $wpdb->prepare( "SELECT id FROM wp_yp_hareketler WHERE aday_id = %d AND kayit_turu='ADAY' AND durum='ODENDI' AND silindi=0 ORDER BY id LIMIT 2", $m_aday ) )
                     : $wpdb->get_col( "SELECT id FROM wp_yp_hareketler WHERE kayit_turu='ADAY' AND durum='ODENDI' AND silindi=0 ORDER BY id LIMIT 1" );
echo json_encode( array(
	'aday'     => $aday,
	'borclu'   => $borclu,
	'referans' => $referans,
	'makbuz'   => implode( ',', $makbuz ),
), JSON_UNESCAPED_UNICODE ) . "\n";
