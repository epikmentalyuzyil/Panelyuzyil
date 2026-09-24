<?php
/**
 * Eklenti silinirken çalışır.
 * KURAL: Veriler yalnızca ayarlarda "eklenti silinince verileri de sil" işaretliyse silinir; varsayılan: veriler kalır.
 */
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$yp_ayarlar = get_option( 'yp_ayarlar', array() );
if ( empty( $yp_ayarlar['kaldirinca_sil'] ) ) {
	return;
}

global $wpdb;
// KURAL: "sayaclar" artık kullanılmaz (eski makbuz sayacı); yükseltilmemiş eski kurulumda kalmışsa o da silinir.
foreach ( array( 'adaylar', 'islemler', 'hareketler', 'hesaplar', 'referanslar', 'gorusmeler', 'tanimlar', 'sayaclar', 'log' ) as $yp_tablo ) {
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'yp_' . $yp_tablo ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}

// KURAL: Fotoğraf klasörü yalnızca eklentinin kendi rastgele adlı klasörüyse silinir — başka klasöre dokunulmaz.
if ( ! empty( $yp_ayarlar['foto_klasoru'] ) && 0 === strpos( $yp_ayarlar['foto_klasoru'], 'yp-korumali-' ) ) {
	$yp_yukleme = wp_upload_dir( null, false );
	$yp_klasor  = trailingslashit( $yp_yukleme['basedir'] ) . basename( $yp_ayarlar['foto_klasoru'] );
	if ( is_dir( $yp_klasor ) ) {
		foreach ( (array) scandir( $yp_klasor ) as $yp_dosya ) {
			if ( is_file( $yp_klasor . '/' . $yp_dosya ) ) {
				wp_delete_file( $yp_klasor . '/' . $yp_dosya );
			}
		}
		@rmdir( $yp_klasor ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}
}

delete_option( 'yp_ayarlar' );
delete_option( 'yp_db_surum' );
