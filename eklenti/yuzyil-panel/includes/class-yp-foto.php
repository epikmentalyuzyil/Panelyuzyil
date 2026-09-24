<?php
defined( 'ABSPATH' ) || exit;

/**
 * Aday fotoğrafları: korumalı klasör, küçültme/sıkıştırma, yetkili gösterim, silme.
 */
final class YP_Foto {

	const EN_BUYUK_KENAR = 800;
	const KUCUK_EN       = 120;
	const KUCUK_BOY      = 156;
	const HEDEF_BOYUT    = 200 * 1024;
	const EN_BUYUK_DOSYA = 15 * 1024 * 1024;

	// KURAL: wp-config.php'de YP_FOTO_DIZINI tanımlıysa fotoğraflar oraya (web kökü dışına) yazılır — Nginx için en güvenli yol.
	public static function klasor() {
		if ( defined( 'YP_FOTO_DIZINI' ) && YP_FOTO_DIZINI ) {
			return untrailingslashit( YP_FOTO_DIZINI );
		}
		$yukleme = wp_upload_dir( null, false );
		return untrailingslashit( $yukleme['basedir'] ) . '/' . sanitize_file_name( (string) YP_Cekirdek::ayar( 'foto_klasoru' ) );
	}

	public static function klasor_url() {
		if ( defined( 'YP_FOTO_DIZINI' ) && YP_FOTO_DIZINI ) {
			return '';
		}
		$yukleme = wp_upload_dir( null, false );
		return untrailingslashit( $yukleme['baseurl'] ) . '/' . sanitize_file_name( (string) YP_Cekirdek::ayar( 'foto_klasoru' ) );
	}

	// KURAL: Klasörde doğrudan erişimi kapatan .htaccess, web.config ve boş index.php bulunur; eksikse yeniden yazılır.
	public static function klasoru_hazirla() {
		$k = self::klasor();
		if ( '' === (string) YP_Cekirdek::ayar( 'foto_klasoru' ) && ! defined( 'YP_FOTO_DIZINI' ) ) {
			return;
		}
		wp_mkdir_p( $k );
		$dosyalar = array(
			'.htaccess'  => "# Yuzyil Panel: dogrudan erisim kapali\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\nOptions -Indexes\n",
			'web.config' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration><system.webServer><authorization><deny users=\"*\" /></authorization></system.webServer></configuration>\n",
			'index.php'  => "<?php\n// Sessiz.\n",
		);
		foreach ( $dosyalar as $ad => $icerik ) {
			if ( ! file_exists( $k . '/' . $ad ) ) {
				file_put_contents( $k . '/' . $ad, $icerik ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			}
		}
	}

	private static function yol( $dosya ) {
		$dosya = sanitize_file_name( (string) $dosya );
		return '' === $dosya ? '' : self::klasor() . '/' . $dosya;
	}

	/**
	 * Yüklenen dosyayı işler ve adaya bağlar. $dosya: $_FILES dizisindeki tek kayıt.
	 *
	 * @return true|WP_Error
	 */
	public static function yukle( $aday_id, $dosya ) {
		$aday = YP_Veri::aday( $aday_id );
		if ( ! $aday ) {
			return new WP_Error( 'yok', 'Aday bulunamadı.' );
		}
		// KURAL: Sunucu boyut sınırı aşılırsa sınır MB olarak söylenir — kullanıcı neyi değiştireceğini bilir.
		if ( ! empty( $dosya['error'] ) && in_array( (int) $dosya['error'], array( UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE ), true ) ) {
			return new WP_Error( 'boyut', 'Dosya sunucunun kabul ettiği boyuttan büyük (en fazla ' . size_format( wp_max_upload_size() ) . ').' );
		}
		if ( empty( $dosya['tmp_name'] ) || ! empty( $dosya['error'] ) || ! is_uploaded_file( $dosya['tmp_name'] ) ) {
			return new WP_Error( 'yukleme', empty( $dosya['name'] ) ? 'Önce bir resim dosyası seçin.' : 'Dosya yüklenemedi. Tekrar deneyin.' );
		}
		if ( (int) $dosya['size'] > self::EN_BUYUK_DOSYA ) {
			return new WP_Error( 'boyut', 'Dosya çok büyük (en fazla 15 MB).' );
		}
		// KURAL: Yalnızca gerçek resim dosyası kabul edilir — uzantı ve içerik birlikte kontrol edilir.
		$kontrol = wp_check_filetype_and_ext( $dosya['tmp_name'], sanitize_file_name( $dosya['name'] ), array(
			'jpg|jpeg|jpe' => 'image/jpeg',
			'png'          => 'image/png',
			'webp'         => 'image/webp',
			'gif'          => 'image/gif',
		) );
		if ( empty( $kontrol['type'] ) || false === @getimagesize( $dosya['tmp_name'] ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
			return new WP_Error( 'tur', 'Yalnızca JPG, PNG, WEBP veya GIF resim yüklenebilir.' );
		}

		return self::dosyadan_kaydet( $aday, $dosya['tmp_name'] );
	}

	/**
	 * Diskteki bir resmi işleyip adaya bağlar (yükleme ve ileride veri aktarımı ortak kullanır).
	 *
	 * @return true|WP_Error
	 */
	public static function dosyadan_kaydet( $aday, $kaynak ) {
		$aday_id = (int) $aday->id;
		self::klasoru_hazirla();
		$rastgele = strtolower( wp_generate_password( 16, false, false ) );
		$buyuk    = (int) $aday_id . '-' . $rastgele . '.jpg';
		$kucuk    = (int) $aday_id . '-' . $rastgele . '-k.jpg';

		$sonuc = self::isle( $kaynak, self::yol( $buyuk ), self::yol( $kucuk ) );
		if ( is_wp_error( $sonuc ) ) {
			return $sonuc;
		}

		// KURAL: Yeni fotoğraf kaydedildikten sonra eskisi diskten silinir — yarım kalan yüklemede eski fotoğraf kaybolmaz.
		$eski_b = $aday->foto;
		$eski_k = $aday->foto_kucuk;
		YP_Veri::aday_guncelle( $aday_id, array( 'foto' => $buyuk, 'foto_kucuk' => $kucuk ) );
		self::dosya_sil( $eski_b );
		self::dosya_sil( $eski_k );
		return true;
	}

	// KURAL: Fotoğrafın en/boy için alt sınırı yoktur; yalnızca 800 px'ten büyükse küçültülür (büyütülmez, kırpılmaz).
	// KURAL: JPEG'e çevrilir ve ~200 KB altına sıkıştırılır; EXIF (konum vb.) atılır.
	private static function isle( $kaynak, $hedef_buyuk, $hedef_kucuk ) {
		$editor = wp_get_image_editor( $kaynak );
		if ( is_wp_error( $editor ) ) {
			return new WP_Error( 'editor', 'Sunucuda resim işleme desteği (GD/Imagick) bulunamadı.' );
		}
		if ( method_exists( $editor, 'maybe_exif_rotate' ) ) {
			$editor->maybe_exif_rotate();
		}
		$olcu  = $editor->get_size();
		$kucuk = $olcu && max( (int) $olcu['width'], (int) $olcu['height'] ) <= self::EN_BUYUK_KENAR;
		$editor->resize( self::EN_BUYUK_KENAR, self::EN_BUYUK_KENAR, false );
		// KURAL: Önceden hazırlanmış küçük fotoğraf yüksek kaliteyle tek kez kaydedilir — gereksiz kalite kaybı olmaz.
		$kaliteler = $kucuk ? array( 90, 82, 72, 62, 50 ) : array( 82, 72, 62, 50 );
		foreach ( $kaliteler as $kalite ) {
			$editor->set_quality( $kalite );
			$kayit = $editor->save( $hedef_buyuk, 'image/jpeg' );
			if ( is_wp_error( $kayit ) ) {
				return new WP_Error( 'kayit', 'Fotoğraf kaydedilemedi: klasör yazılabilir değil.' );
			}
			clearstatcache( true, $hedef_buyuk );
			if ( filesize( $hedef_buyuk ) <= self::HEDEF_BOYUT ) {
				break;
			}
		}

		$k = wp_get_image_editor( $hedef_buyuk );
		if ( is_wp_error( $k ) ) {
			return $k;
		}
		// KURAL: Liste önizlemesi orantılı küçültülür — kırpılmaz, küçük fotoğraf büyütülmez.
		$k->resize( self::KUCUK_EN, self::KUCUK_BOY, false );
		$k->set_quality( 80 );
		$kayit = $k->save( $hedef_kucuk, 'image/jpeg' );
		return is_wp_error( $kayit ) ? $kayit : true;
	}

	public static function dosya_sil( $dosya ) {
		$yol = self::yol( $dosya );
		if ( '' !== $yol && is_file( $yol ) ) {
			wp_delete_file( $yol );
		}
	}

	// KURAL: Aday kalıcı silinince fotoğrafları da diskten silinir — şart gereği.
	public static function aday_fotolarini_sil( $aday ) {
		if ( $aday ) {
			self::dosya_sil( $aday->foto );
			self::dosya_sil( $aday->foto_kucuk );
		}
	}

	public static function url( $aday, $kucuk = true ) {
		if ( ! $aday || empty( $aday->foto ) ) {
			return '';
		}
		// KURAL: Fotoğraf adresi dosya adına göre değişir — tarayıcı eski fotoğrafı önbellekten göstermez.
		return YP_Cekirdek::panel_url( array(
			'ekran' => 'foto',
			'id'    => (int) $aday->id,
			'b'     => $kucuk ? 'k' : 'b',
			'v'     => substr( md5( (string) $aday->foto ), 0, 8 ),
		) );
	}

	// KURAL: Fotoğraf yalnızca yetki kontrolünden geçen panel isteğiyle gösterilir — yönlendirici bu noktaya yalnızca yöneticiyi getirir.
	public static function goster( $aday_id, $boyut ) {
		if ( ! YP_Guvenlik::yetkili() ) {
			status_header( 404 );
			exit;
		}
		$aday  = YP_Veri::aday( $aday_id );
		$dosya = $aday ? ( 'b' === $boyut ? $aday->foto : $aday->foto_kucuk ) : '';
		$yol   = self::yol( $dosya );
		if ( '' === $yol || ! is_file( $yol ) ) {
			status_header( 404 );
			exit;
		}
		header_remove( 'Pragma' );
		header( 'Content-Type: image/jpeg' );
		header( 'Content-Length: ' . filesize( $yol ) );
		header( 'Cache-Control: private, max-age=86400' );
		header( 'Expires: ' . gmdate( 'D, d M Y H:i:s', time() + 86400 ) . ' GMT' );
		readfile( $yol ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}

	/**
	 * Klasörün dışarıdan açılıp açılmadığını test eder (ayar sayfasındaki düğme).
	 *
	 * @return string korunuyor | acik | bilinmiyor | disarida
	 */
	public static function koruma_testi() {
		if ( defined( 'YP_FOTO_DIZINI' ) && YP_FOTO_DIZINI ) {
			return 'disarida';
		}
		self::klasoru_hazirla();
		$ad = 'yp-test-' . strtolower( wp_generate_password( 10, false, false ) ) . '.txt';
		file_put_contents( self::klasor() . '/' . $ad, 'test' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$yanit = wp_remote_get( self::klasor_url() . '/' . $ad, array( 'timeout' => 10, 'sslverify' => false ) );
		wp_delete_file( self::klasor() . '/' . $ad );
		if ( is_wp_error( $yanit ) ) {
			return 'bilinmiyor';
		}
		$kod = (int) wp_remote_retrieve_response_code( $yanit );
		return ( 200 === $kod && 'test' === trim( wp_remote_retrieve_body( $yanit ) ) ) ? 'acik' : 'korunuyor';
	}
}
