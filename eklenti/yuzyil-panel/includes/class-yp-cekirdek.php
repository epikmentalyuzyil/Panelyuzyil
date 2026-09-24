<?php
defined( 'ABSPATH' ) || exit;

/**
 * Ortak yardımcılar: tablo adları, ayarlar, adresler, tarih ve işlem günlüğü.
 */
final class YP_Cekirdek {

	const AYAR_ANAHTARI = 'yp_ayarlar';

	// KURAL: Tüm tablolar wp_yp_ önekiyle adlandırılır — sitenin tablolarıyla çakışmaz.
	public static function tablo( $ad ) {
		global $wpdb;
		return $wpdb->prefix . 'yp_' . $ad;
	}

	public static function varsayilan_ayarlar() {
		return array(
			'slug'                 => 'panel',
			'kurum_adi'            => 'Yüzyıl Psikoteknik Merkezi',
			'kurum_adresi'         => '',
			'kurum_telefonu'       => '',
			// KURAL: Ana menüdeki "Web Sitesi" düğmesinin adresi ayardan gelir — adres değişirse kod değişmez.
			'web_sitesi'           => 'https://www.yuzyilpsikoteknik.com',
			'makbuz_notu'          => 'Bu makbuz bilgi amaçlıdır.',
			'yaklasan_gun'         => 7,
			'gecerlilik_uyari_gun' => 30,
			'sayfa_basi'           => 40,
			'foto_klasoru'         => '',
			'kaldirinca_sil'       => 0,
			'kilit_kullanici'      => 'admin',
			'kilit_hash'           => '',
			'kilit_gun'            => 30,
			// KURAL: SMS abone bilgileri ayarlarda durur — kodda hiçbir abone bilgisi yazmaz.
			'sms_aktif'            => 0,
			'sms_kullanici'        => '',
			'sms_sifre'            => '',
			'sms_baslik'           => '',
			'sms_turkce'           => 0,
			'sms_gunluk_sinir'     => 1000,
		);
	}

	public static function ayarlar() {
		$kayitli = get_option( self::AYAR_ANAHTARI, array() );
		return wp_parse_args( is_array( $kayitli ) ? $kayitli : array(), self::varsayilan_ayarlar() );
	}

	public static function ayar( $anahtar ) {
		$ayarlar = self::ayarlar();
		return isset( $ayarlar[ $anahtar ] ) ? $ayarlar[ $anahtar ] : null;
	}

	public static function ayar_kaydet( array $yeni ) {
		update_option( self::AYAR_ANAHTARI, array_merge( self::ayarlar(), $yeni ), false );
	}

	public static function slug() {
		$slug = sanitize_title( (string) self::ayar( 'slug' ) );
		return '' === $slug ? 'panel' : $slug;
	}

	public static function panel_url( array $args = array() ) {
		return add_query_arg( array_map( 'rawurlencode', $args ), home_url( '/' . self::slug() . '/' ) );
	}

	// KURAL: Tarih ve saat sitenin saat dilimine göre alınır — sunucu saatine göre değil.
	public static function bugun() {
		return current_time( 'Y-m-d' );
	}

	public static function simdi() {
		return current_time( 'mysql' );
	}

	public static function kullanici_id() {
		return get_current_user_id();
	}

	public static function kullanici_adi( $id = null ) {
		$id   = null === $id ? get_current_user_id() : (int) $id;
		$user = $id ? get_userdata( $id ) : false;
		return $user ? $user->display_name : '—';
	}

	// KURAL: Ekleme, değişiklik, tahsilat ve silme işlem günlüğüne yazılır — masaüstündeki LOG tablosunun karşılığı.
	public static function log( $bolum, $kayit_id, $islem, $aciklama = '' ) {
		global $wpdb;
		$wpdb->insert(
			self::tablo( 'log' ),
			array(
				'zaman'          => self::simdi(),
				'kullanici_id'   => self::kullanici_id(),
				'kullanici_adi'  => self::kullanici_adi(),
				'bolum'          => substr( (string) $bolum, 0, 30 ),
				'kayit_id'       => (int) $kayit_id,
				'islem'          => substr( (string) $islem, 0, 30 ),
				'aciklama'       => (string) $aciklama,
			),
			array( '%s', '%d', '%s', '%s', '%d', '%s', '%s' )
		);
		// KURAL: Günlüğe yazılan her işlem veri değişikliği sayılır; özet önbellekleri anında tazelenir.
		self::veri_degisti();
	}

	// KURAL: Veri her değiştiğinde sürüm numarası artar; önbellekler bu numaraya bağlıdır, böylece eski özet asla gösterilmez.
	public static function veri_surumu() {
		$s = get_option( 'yp_veri_surumu' );
		return $s ? $s : '1';
	}

	public static function veri_degisti() {
		update_option( 'yp_veri_surumu', (string) ( (int) self::veri_surumu() + 1 ), false );
	}

	/**
	 * Ağır özet sorgularını kısa süreli önbellekten okur.
	 */
	public static function onbellek( $anahtar, $saniye, callable $uret ) {
		$tam   = 'yp_ob_' . md5( $anahtar . '|' . self::veri_surumu() . '|' . self::bugun() );
		$deger = get_transient( $tam );
		if ( false !== $deger ) {
			return $deger;
		}
		$deger = call_user_func( $uret );
		set_transient( $tam, $deger, $saniye );
		return $deger;
	}

	public static function etkinlestir() {
		require_once YP_DIZIN . 'includes/class-yp-kurulum.php';
		YP_Kurulum::kur();
	}

	// KURAL: Eklenti güncellenince tablolar yalnızca yöneticinin açtığı sayfada yükseltilir — ziyaretçiyi yavaşlatmaz.
	public static function surum_kontrol() {
		if ( get_option( 'yp_db_surum' ) !== YP_DB_SURUM ) {
			self::etkinlestir();
		}
	}
}
