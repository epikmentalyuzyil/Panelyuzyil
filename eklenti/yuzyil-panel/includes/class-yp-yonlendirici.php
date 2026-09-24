<?php
defined( 'ABSPATH' ) || exit;

/**
 * Panel adresini yakalar, yetkisizlere dokunmaz (doğal 404), yetkiliye paneli açar.
 */
final class YP_Yonlendirici {

	// KURAL: Ekran adı → sınıf eşlemesi burada tutulur — listede olmayan ekran açılamaz.
	private static $ekranlar = array(
		'ana-sayfa'   => 'YP_Ekran_Ana_Sayfa',
		'adaylar'     => 'YP_Ekran_Adaylar',
		'aday'        => 'YP_Ekran_Aday',
		'takip'       => 'YP_Ekran_Takip',
		'referanslar' => 'YP_Ekran_Referanslar',
		'kasa'        => 'YP_Ekran_Kasa',
		'raporlar'    => 'YP_Ekran_Raporlar',
		'tanimlar'    => 'YP_Ekran_Tanimlar',
		'silinenler'  => 'YP_Ekran_Silinenler',
		'gunluk'      => 'YP_Ekran_Gunluk',
		'yazdir'      => 'YP_Ekran_Yazdir',
	);

	public static function baslat() {
		add_action( 'parse_request', array( __CLASS__, 'yakala' ), 0 );
	}

	// KURAL: İstek yolu site ana adresine göre hesaplanır — site alt klasörde kuruluysa da çalışır.
	private static function istek_yolu() {
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$yol  = (string) wp_parse_url( $uri, PHP_URL_PATH );
		$kok  = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		if ( '' !== $kok && 0 === strpos( $yol, $kok ) ) {
			$yol = substr( $yol, strlen( $kok ) );
		}
		return trim( rawurldecode( $yol ), '/' );
	}

	public static function yakala( $wp ) {
		if ( self::istek_yolu() !== YP_Cekirdek::slug() ) {
			return;
		}
		// KURAL: Yetkisiz ziyaretçide hiçbir şey yapılmaz — WordPress kendi 404 sayfasını gösterir, panel belli olmaz.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		self::calistir();
		exit;
	}

	// KURAL: Her istekte yalnızca o ekranın dosyaları yüklenir — gereksiz dosya okuması yapılmaz.
	private static $ekran_dosyalari = array(
		'ana-sayfa'   => array( 'ana-sayfa' ),
		'adaylar'     => array( 'adaylar' ),
		'aday'        => array( 'aday', 'aday-islem' ),
		'takip'       => array( 'takip', 'adaylar' ),
		'referanslar' => array( 'referanslar' ),
		'kasa'        => array( 'kasa' ),
		'raporlar'    => array( 'raporlar', 'adaylar', 'kasa' ),
		// KURAL: Tanımlar, kurum ayarları ve referanslar tek ekranda birleşiktir — referans sorguları da yüklenir.
		'tanimlar'    => array( 'tanimlar', 'referanslar' ),
		'silinenler'  => array( 'silinenler', 'kasa' ),
		'gunluk'      => array( 'gunluk' ),
		// KURAL: Borç bakiye listesi kasa ekranının sorgusunu kullanır — kasa dosyası da yüklenir.
		'yazdir'      => array( 'yazdir', 'kasa' ),
	);

	private static function yukle() {
		$dizin = YP_DIZIN . 'includes/';
		foreach ( array( 'bicim', 'guvenlik', 'veri', 'hesap', 'foto', 'ekran', 'kilit' ) as $ad ) {
			require_once $dizin . 'class-yp-' . $ad . '.php';
		}
	}

	private static function ekran_yukle( $ekran ) {
		if ( ! isset( self::$ekran_dosyalari[ $ekran ] ) ) {
			return;
		}
		// KURAL: Excel motoru yalnızca dışa aktarım yapan ekranlarda yüklenir.
		if ( in_array( $ekran, array( 'raporlar', 'takip' ), true ) ) {
			require_once YP_DIZIN . 'includes/class-yp-excel.php';
		}
		foreach ( self::$ekran_dosyalari[ $ekran ] as $dosya ) {
			$yol = YP_DIZIN . 'includes/ekranlar/class-yp-ekran-' . $dosya . '.php';
			if ( is_readable( $yol ) ) {
				require_once $yol;
			}
		}
	}

	// KURAL: İşlem adının ön eki hangi ekrana ait olduğunu belirler — yalnızca o ekranın dosyası yüklenir.
	private static function islem_ekrani( $islem ) {
		if ( 0 === strpos( $islem, 'kilit_' ) || 'sifre_degistir' === $islem ) {
			return '';
		}
		$onekler = array(
			'kasa_'       => 'kasa',
			'referans_'   => 'referanslar',
			'tanim_'      => 'tanimlar',
			'hesap_'      => 'tanimlar',
			'ayar_'       => 'tanimlar',
			'geri_al_'    => 'silinenler',
			'kalici_sil_' => 'silinenler',
		);
		foreach ( $onekler as $onek => $ekran ) {
			if ( 0 === strpos( $islem, $onek ) ) {
				return $ekran;
			}
		}
		return 'aday';
	}

	private static function basliklar() {
		// KURAL: Panel hiçbir önbelleğe alınmaz — hassas veri önbellek eklentisinde başkasına görünmez.
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow, noarchive, nosnippet', true );
		header( 'X-Frame-Options: DENY' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Referrer-Policy: no-referrer' );
		header( 'Permissions-Policy: camera=(self), microphone=(), geolocation=()' );
		// KURAL: Yalnızca panelin kendi dosyaları çalışır — dış kaynaklı betik yüklenemez.
		header( "Content-Security-Policy: default-src 'self'; img-src 'self' data: blob:; media-src 'self' blob:; style-src 'self' 'unsafe-inline'; script-src 'self'; object-src 'none'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'" );
	}

	private static function calistir() {
		self::yukle();
		YP_Cekirdek::surum_kontrol();

		$varlik = isset( $_GET['varlik'] ) ? sanitize_key( wp_unslash( $_GET['varlik'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( '' !== $varlik ) {
			self::varlik_gonder( $varlik );
			return;
		}

		self::basliklar();
		status_header( 200 );

		// KURAL: Panel kilidi açılmadan hiçbir ekran, fotoğraf veya veri işlemi çalışmaz — yalnızca şifre formu çalışır.
		$kilit_acik = YP_Kilit::acik();

		// KURAL: Veri değiştiren her istek POST olur ve yp_islem alanıyla yönlendirilir.
		if ( 'POST' === ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '' ) && isset( $_POST['yp_islem'] ) ) { // phpcs:ignore
			$islem = sanitize_key( wp_unslash( $_POST['yp_islem'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
			if ( ! $kilit_acik && ! in_array( $islem, array( 'kilit_ac', 'kilit_kur' ), true ) ) {
				YP_Kilit::ekran();
				return;
			}
			self::islem_calistir( $islem );
			return;
		}

		if ( ! $kilit_acik ) {
			YP_Kilit::ekran();
			return;
		}

		$ekran = isset( $_GET['ekran'] ) ? sanitize_key( wp_unslash( $_GET['ekran'] ) ) : 'ana-sayfa'; // phpcs:ignore WordPress.Security.NonceVerification
		if ( 'foto' === $ekran ) {
			YP_Foto::goster( YP_Guvenlik::tamsayi( 'id', 'get' ), YP_Guvenlik::metin( 'b', 'get', 2 ) );
			return;
		}
		// KURAL: Hızlı arama sonuçları JSON döner ve nonce ile korunur.
		if ( 'ara' === $ekran ) {
			self::ekran_yukle( 'adaylar' );
			YP_Ekran_Adaylar::hizli_ara_json();
			return;
		}
		if ( ! isset( self::$ekranlar[ $ekran ] ) ) {
			$ekran = 'ana-sayfa';
		}
		self::ekran_yukle( $ekran );
		if ( ! class_exists( self::$ekranlar[ $ekran ] ) ) {
			$ekran = 'ana-sayfa';
			self::ekran_yukle( $ekran );
		}
		call_user_func( array( self::$ekranlar[ $ekran ], 'goster' ) );
	}

	private static function islem_calistir( $islem ) {
		$isleyici = null;
		$kilit    = YP_Kilit::islemler();
		if ( isset( $kilit[ $islem ] ) ) {
			$isleyici = $kilit[ $islem ];
		} else {
			$ekran = self::islem_ekrani( $islem );
			self::ekran_yukle( $ekran );
			$sinif = isset( self::$ekranlar[ $ekran ] ) ? self::$ekranlar[ $ekran ] : '';
			if ( $sinif && class_exists( $sinif ) && method_exists( $sinif, 'islemler' ) ) {
				$liste = call_user_func( array( $sinif, 'islemler' ) );
				if ( isset( $liste[ $islem ] ) ) {
					$isleyici = is_array( $liste[ $islem ] ) ? $liste[ $islem ] : array( $sinif, $liste[ $islem ] );
				}
			}
		}
		if ( ! $isleyici || ! YP_Guvenlik::dogrula( $islem ) ) {
			// KURAL: Doğrulanamayan istek hiçbir şey değiştirmez ve açıklayıcı hata verir.
			status_header( 403 );
			YP_Ekran::hata_sayfasi( 'Güvenlik doğrulaması başarısız oldu. Sayfayı yenileyip işlemi tekrar deneyin.' );
			return;
		}
		call_user_func( $isleyici );
	}

	// KURAL: Panelin CSS/JS dosyaları da yalnızca yöneticiye, panel adresinden verilir — eklenti klasörü üzerinden yüklenmez.
	private static function varlik_gonder( $varlik ) {
		$dosyalar = array(
			'css'  => array( 'varliklar/panel.css', 'text/css; charset=utf-8' ),
			'js'   => array( 'varliklar/panel.js', 'application/javascript; charset=utf-8' ),
			// KURAL: Kurum logosu da aynı yoldan, yalnızca yetkili kullanıcıya verilir.
			'logo' => array( 'varliklar/minilogo.png', 'image/png' ),
		);
		if ( ! isset( $dosyalar[ $varlik ] ) || ! is_readable( YP_DIZIN . $dosyalar[ $varlik ][0] ) ) {
			status_header( 404 );
			return;
		}
		header( 'Content-Type: ' . $dosyalar[ $varlik ][1] );
		header( 'Cache-Control: private, max-age=31536000' );
		header( 'X-Content-Type-Options: nosniff' );
		readfile( YP_DIZIN . $dosyalar[ $varlik ][0] ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}
}
