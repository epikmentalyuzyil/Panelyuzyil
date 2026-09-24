<?php
/**
 * Plugin Name:       Yüzyıl Panel
 * Description:       Yüzyıl Psikoteknik Merkezi için aday, işlem, ödeme, referans ve kasa takip paneli.
 * Version:           1.6.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Yüzyıl Psikoteknik Merkezi
 * License:           GPL-2.0-or-later
 * Text Domain:       yuzyil-panel
 */

// KURAL: Dosyalar doğrudan açılamaz — yalnızca WordPress üzerinden çalışır.
defined( 'ABSPATH' ) || exit;

define( 'YP_SURUM', '1.6.0' );
define( 'YP_DB_SURUM', '5' );
define( 'YP_DOSYA', __FILE__ );
define( 'YP_DIZIN', plugin_dir_path( __FILE__ ) );

require_once YP_DIZIN . 'includes/class-yp-cekirdek.php';
require_once YP_DIZIN . 'includes/class-yp-yonlendirici.php';

register_activation_hook( __FILE__, array( 'YP_Cekirdek', 'etkinlestir' ) );
// KURAL: Devre dışı bırakınca hiçbir veri silinmez — şart gereği; kanca bilerek boş.

// KURAL: Ziyaretçi sayfalarında yalnızca adres kontrolü çalışır — panel kodu sitenin hızını etkilemez.
YP_Yonlendirici::baslat();

if ( is_admin() ) {
	require_once YP_DIZIN . 'includes/class-yp-ayarlar-sayfasi.php';
	YP_Ayarlar_Sayfasi::baslat();
}
