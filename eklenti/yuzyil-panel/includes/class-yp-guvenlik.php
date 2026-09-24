<?php
defined( 'ABSPATH' ) || exit;

/**
 * Yetki, nonce ve girdi temizleme.
 */
final class YP_Guvenlik {

	// KURAL: Panele yalnızca "Yönetici" (manage_options) girer — şart gereği.
	public static function yetkili() {
		return is_user_logged_in() && current_user_can( 'manage_options' );
	}

	public static function nonce_alani( $islem ) {
		return '<input type="hidden" name="yp_islem" value="' . esc_attr( $islem ) . '">'
			. wp_nonce_field( 'yp_' . $islem, '_yp_nonce', false, false );
	}

	public static function nonce( $islem ) {
		return wp_create_nonce( 'yp_' . $islem );
	}

	// KURAL: Her veri değiştiren istekte hem yetki hem nonce doğrulanır; biri tutmazsa işlem yapılmaz.
	public static function dogrula( $islem ) {
		$nonce = isset( $_REQUEST['_yp_nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_yp_nonce'] ) ) : '';
		return self::yetkili() && wp_verify_nonce( $nonce, 'yp_' . $islem );
	}

	private static function ham( $anahtar, $kaynak ) {
		$dizi = 'get' === $kaynak ? $_GET : $_POST; // phpcs:ignore WordPress.Security.NonceVerification -- nonce dogrula() ile yapılır.
		return isset( $dizi[ $anahtar ] ) ? wp_unslash( $dizi[ $anahtar ] ) : null;
	}

	public static function metin( $anahtar, $kaynak = 'post', $uzunluk = 255 ) {
		$d = self::ham( $anahtar, $kaynak );
		if ( is_array( $d ) || null === $d ) {
			return '';
		}
		$d = sanitize_text_field( $d );
		return function_exists( 'mb_substr' ) ? mb_substr( $d, 0, $uzunluk, 'UTF-8' ) : substr( $d, 0, $uzunluk );
	}

	public static function uzun_metin( $anahtar, $kaynak = 'post' ) {
		$d = self::ham( $anahtar, $kaynak );
		return ( is_array( $d ) || null === $d ) ? '' : sanitize_textarea_field( $d );
	}

	public static function tamsayi( $anahtar, $kaynak = 'post' ) {
		$d = self::ham( $anahtar, $kaynak );
		return ( is_array( $d ) || null === $d ) ? 0 : absint( $d );
	}

	public static function tarih( $anahtar, $kaynak = 'post' ) {
		return YP_Bicim::tarih_oku( self::metin( $anahtar, $kaynak, 20 ) );
	}

	public static function tutar( $anahtar, $kaynak = 'post' ) {
		return YP_Bicim::tutar_oku( self::metin( $anahtar, $kaynak, 30 ) );
	}

	// KURAL: Seçim alanları yalnızca izin verilen değerlerden birini alabilir — listedışı değer varsayılana döner.
	public static function secim( $anahtar, array $izinli, $varsayilan = '', $kaynak = 'post' ) {
		$d = self::metin( $anahtar, $kaynak, 60 );
		return in_array( $d, $izinli, true ) ? $d : $varsayilan;
	}

	public static function bayrak( $anahtar, $kaynak = 'post' ) {
		$d = self::ham( $anahtar, $kaynak );
		return ( null !== $d && ! is_array( $d ) && '' !== $d && '0' !== $d ) ? 1 : 0;
	}

	public static function tamsayi_dizisi( $anahtar, $kaynak = 'post' ) {
		$d = self::ham( $anahtar, $kaynak );
		return is_array( $d ) ? array_values( array_filter( array_map( 'absint', $d ) ) ) : array();
	}
}
