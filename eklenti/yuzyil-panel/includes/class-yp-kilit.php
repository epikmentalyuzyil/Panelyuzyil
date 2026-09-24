<?php
defined( 'ABSPATH' ) || exit;

/**
 * Panel kilidi: WordPress oturumunun üstüne ikinci bir şifre katmanı.
 * KURAL: Şifre kodda değil, geri döndürülemez özet (hash) olarak veritabanında saklanır.
 */
final class YP_Kilit {

	const COOKIE        = 'yp_kilit';
	const META          = 'yp_kilit_anahtarlari';
	const VARSAYILAN_AD = 'admin';
	const EN_AZ_UZUNLUK = 10;
	const DENEME_HAKKI  = 5;
	const BEKLEME_SN    = 900;
	const SURE_SAAT     = 12;

	public static function kurulu() {
		return '' !== (string) YP_Cekirdek::ayar( 'kilit_hash' );
	}

	public static function kullanici_adi() {
		$ad = (string) YP_Cekirdek::ayar( 'kilit_kullanici' );
		return '' === $ad ? self::VARSAYILAN_AD : $ad;
	}

	private static function cerez_adi() {
		return self::COOKIE . '_' . ( defined( 'COOKIEHASH' ) ? COOKIEHASH : 'yp' );
	}

	// KURAL: Cihaz anahtarı çerezde açık durur ama sunucuda yalnızca özeti saklanır — çerez çalınsa bile şifre ele geçmez.
	private static function ozet( $anahtar ) {
		return hash_hmac( 'sha256', $anahtar, wp_salt( 'auth' ) );
	}

	private static function anahtarlar() {
		$liste = get_user_meta( get_current_user_id(), self::META, true );
		$liste = is_array( $liste ) ? $liste : array();
		$simdi = time();
		foreach ( $liste as $o => $bitis ) {
			if ( (int) $bitis < $simdi ) {
				unset( $liste[ $o ] );
			}
		}
		return $liste;
	}

	public static function acik() {
		if ( ! self::kurulu() ) {
			return false;
		}
		$cerez = isset( $_COOKIE[ self::cerez_adi() ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ self::cerez_adi() ] ) ) : '';
		if ( '' === $cerez ) {
			return false;
		}
		$liste = self::anahtarlar();
		return isset( $liste[ self::ozet( $cerez ) ] );
	}

	private static function anahtar_ver( $hatirla ) {
		$anahtar = wp_generate_password( 43, false, false );
		$sure    = $hatirla ? DAY_IN_SECONDS * max( 1, (int) YP_Cekirdek::ayar( 'kilit_gun' ) ) : HOUR_IN_SECONDS * self::SURE_SAAT;
		$liste   = self::anahtarlar();
		$liste[ self::ozet( $anahtar ) ] = time() + $sure;
		// KURAL: Bir kullanıcıda en fazla 10 cihaz anahtarı tutulur — eskisi düşer.
		if ( count( $liste ) > 10 ) {
			asort( $liste );
			$liste = array_slice( $liste, -10, null, true );
		}
		update_user_meta( get_current_user_id(), self::META, $liste );
		setcookie(
			self::cerez_adi(),
			$anahtar,
			array(
				'expires'  => $hatirla ? time() + $sure : 0,
				'path'     => defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/',
				'domain'   => defined( 'COOKIE_DOMAIN' ) && COOKIE_DOMAIN ? COOKIE_DOMAIN : '',
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
	}

	public static function kilitle( $tum_cihazlar = false ) {
		$cerez = isset( $_COOKIE[ self::cerez_adi() ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ self::cerez_adi() ] ) ) : '';
		if ( $tum_cihazlar ) {
			delete_user_meta( get_current_user_id(), self::META );
		} elseif ( '' !== $cerez ) {
			$liste = self::anahtarlar();
			unset( $liste[ self::ozet( $cerez ) ] );
			update_user_meta( get_current_user_id(), self::META, $liste );
		}
		setcookie( self::cerez_adi(), '', array( 'expires' => time() - 3600, 'path' => defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax' ) );
	}

	// ---- Deneme sınırı --------------------------------------------------

	private static function sayac_anahtari() {
		return 'yp_kilit_deneme_' . get_current_user_id();
	}

	public static function kalan_bekleme() {
		$d = get_transient( self::sayac_anahtari() );
		if ( is_array( $d ) && isset( $d['bitis'] ) && $d['bitis'] > time() ) {
			return (int) $d['bitis'] - time();
		}
		return 0;
	}

	// KURAL: 5 hatalı denemeden sonra 15 dakika beklenir — şifre deneyerek kırma denemesi işe yaramaz.
	private static function hata_isle() {
		$d = get_transient( self::sayac_anahtari() );
		$d = is_array( $d ) ? $d : array( 'adet' => 0, 'bitis' => 0 );
		$d['adet']++;
		if ( $d['adet'] >= self::DENEME_HAKKI ) {
			$d['bitis'] = time() + self::BEKLEME_SN;
			$d['adet']  = 0;
		}
		set_transient( self::sayac_anahtari(), $d, self::BEKLEME_SN * 2 );
	}

	private static function hatalari_temizle() {
		delete_transient( self::sayac_anahtari() );
	}

	// ---- İşlemler -------------------------------------------------------

	public static function islemler() {
		return array(
			'kilit_ac'     => array( __CLASS__, 'ac' ),
			'kilit_kur'    => array( __CLASS__, 'kur' ),
			'kilit_kapat'  => array( __CLASS__, 'kapat' ),
			'sifre_degistir' => array( __CLASS__, 'sifre_degistir' ),
		);
	}

	public static function ac() {
		if ( self::kalan_bekleme() > 0 ) {
			YP_Ekran::yonlendir( array(), 'Çok fazla hatalı deneme yapıldı. Lütfen ' . ceil( self::kalan_bekleme() / 60 ) . ' dakika sonra tekrar deneyin.', 'hata' );
		}
		$ad    = YP_Guvenlik::metin( 'kullanici', 'post', 60 );
		$sifre = isset( $_POST['sifre'] ) ? (string) wp_unslash( $_POST['sifre'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput,WordPress.Security.NonceVerification -- nonce yönlendiricide doğrulandı; şifre olduğu gibi karşılaştırılır.
		$dogru = hash_equals( YP_Bicim::katla( self::kullanici_adi() ), YP_Bicim::katla( $ad ) ) && wp_check_password( $sifre, (string) YP_Cekirdek::ayar( 'kilit_hash' ) );
		if ( ! $dogru ) {
			self::hata_isle();
			YP_Cekirdek::log( 'guvenlik', 0, 'hatalı giriş', 'Panel kilidinde hatalı kullanıcı adı veya şifre.' );
			YP_Ekran::yonlendir( array(), 'Kullanıcı adı veya şifre hatalı.', 'hata' );
		}
		self::hatalari_temizle();
		self::anahtar_ver( YP_Guvenlik::bayrak( 'hatirla' ) );
		YP_Cekirdek::log( 'guvenlik', 0, 'giriş', 'Panel kilidi açıldı.' );
		YP_Ekran::yonlendir( array(), 'Hoş geldiniz.' );
	}

	public static function kur() {
		if ( self::kurulu() ) {
			YP_Ekran::yonlendir( array(), 'Şifre zaten belirlenmiş.', 'hata' );
		}
		$ad  = YP_Guvenlik::metin( 'kullanici', 'post', 60 );
		$s1  = isset( $_POST['sifre'] ) ? (string) wp_unslash( $_POST['sifre'] ) : ''; // phpcs:ignore
		$s2  = isset( $_POST['sifre2'] ) ? (string) wp_unslash( $_POST['sifre2'] ) : ''; // phpcs:ignore
		$hata = self::sifre_hatasi( $s1, $s2 );
		if ( '' !== $hata ) {
			YP_Ekran::yonlendir( array(), esc_html( $hata ), 'hata' );
		}
		YP_Cekirdek::ayar_kaydet( array(
			'kilit_kullanici' => '' === $ad ? self::VARSAYILAN_AD : $ad,
			'kilit_hash'      => wp_hash_password( $s1 ),
		) );
		self::anahtar_ver( true );
		YP_Cekirdek::log( 'guvenlik', 0, 'şifre', 'Panel şifresi oluşturuldu.' );
		YP_Ekran::yonlendir( array(), 'Panel şifreniz oluşturuldu. Bundan sonra panele bu şifreyle gireceksiniz.' );
	}

	// KURAL: Şifre en az 10 karakter olmalı ve "admin", "123456" gibi kolay tahmin edilen bir değer olmamalı.
	public static function sifre_hatasi( $s1, $s2 ) {
		$kolay = array( 'admin', 'password', 'parola', 'sifre', '123456', '1234567890', 'admin123', 'qwerty', 'yuzyil', 'psikoteknik' );
		if ( strlen( $s1 ) < self::EN_AZ_UZUNLUK ) {
			return 'Şifre en az ' . self::EN_AZ_UZUNLUK . ' karakter olmalı.';
		}
		if ( $s1 !== $s2 ) {
			return 'İki şifre birbirini tutmuyor.';
		}
		if ( in_array( YP_Bicim::katla( $s1 ), $kolay, true ) ) {
			return 'Bu şifre çok kolay tahmin ediliyor; başka bir şifre seçin.';
		}
		return '';
	}

	public static function kapat() {
		self::kilitle( YP_Guvenlik::bayrak( 'tum_cihazlar' ) );
		YP_Cekirdek::log( 'guvenlik', 0, 'kilit', 'Panel kilitlendi.' );
		YP_Ekran::yonlendir( array(), 'Panel kilitlendi.' );
	}

	public static function sifre_degistir() {
		$eski = isset( $_POST['eski_sifre'] ) ? (string) wp_unslash( $_POST['eski_sifre'] ) : ''; // phpcs:ignore
		$s1   = isset( $_POST['sifre'] ) ? (string) wp_unslash( $_POST['sifre'] ) : ''; // phpcs:ignore
		$s2   = isset( $_POST['sifre2'] ) ? (string) wp_unslash( $_POST['sifre2'] ) : ''; // phpcs:ignore
		if ( ! wp_check_password( $eski, (string) YP_Cekirdek::ayar( 'kilit_hash' ) ) ) {
			YP_Ekran::geri_don( 'Mevcut şifre hatalı.', 'hata' );
		}
		$hata = self::sifre_hatasi( $s1, $s2 );
		if ( '' !== $hata ) {
			YP_Ekran::geri_don( esc_html( $hata ), 'hata' );
		}
		$ad = YP_Guvenlik::metin( 'kullanici', 'post', 60 );
		YP_Cekirdek::ayar_kaydet( array(
			'kilit_kullanici' => '' === $ad ? self::kullanici_adi() : $ad,
			'kilit_hash'      => wp_hash_password( $s1 ),
		) );
		// KURAL: Şifre değişince tüm cihazlardaki "beni hatırla" kayıtları düşer — eski cihaz yeniden şifre ister.
		delete_user_meta( get_current_user_id(), self::META );
		self::anahtar_ver( true );
		YP_Cekirdek::log( 'guvenlik', 0, 'şifre', 'Panel şifresi değiştirildi; tüm cihazlarda yeniden giriş istenecek.' );
		YP_Ekran::geri_don( 'Şifreniz değiştirildi.' );
	}

	// ---- Ekran ----------------------------------------------------------

	public static function ekran() {
		$kurulum = ! self::kurulu();
		$bekleme = self::kalan_bekleme();
		YP_Ekran::sayfa_basla( $kurulum ? 'Panel Şifresi' : 'Giriş', '', 'kilit-sayfa' );
		?>
		<div class="kilit-kap">
			<form class="kilit-kart" method="post" action="<?php echo esc_url( YP_Cekirdek::panel_url() ); ?>" autocomplete="off">
				<?php echo YP_Guvenlik::nonce_alani( $kurulum ? 'kilit_kur' : 'kilit_ac' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<div class="kilit-baslik">
					<h1><?php echo esc_html( YP_Cekirdek::ayar( 'kurum_adi' ) ); ?></h1>
					<span><?php echo $kurulum ? 'Panel şifresi oluşturun' : 'Panel Girişi'; ?></span>
				</div>
				<div class="kilit-govde">
					<?php if ( $kurulum ) : ?>
						<p class="kilit-not">Paneli ilk kez açıyorsunuz. Yalnızca size ait bir şifre belirleyin; bundan sonra panel bu şifreyi soracak.</p>
					<?php endif; ?>
					<label class="kilit-alan">
						<span class="kilit-ikon" aria-hidden="true">
							<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="8" r="3.4"/><path d="M4.5 20c0-3.6 3.4-6 7.5-6s7.5 2.4 7.5 6"/></svg>
						</span>
						<input type="text" name="kullanici" value="<?php echo esc_attr( $kurulum ? self::VARSAYILAN_AD : '' ); ?>" placeholder="Kullanıcı adı" autocomplete="username" required maxlength="60" autofocus>
					</label>
					<label class="kilit-alan">
						<span class="kilit-ikon" aria-hidden="true">
							<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><rect x="4.5" y="10" width="15" height="10" rx="2.5"/><path d="M8 10V7.5a4 4 0 0 1 8 0V10"/></svg>
						</span>
						<input type="password" name="sifre" placeholder="<?php echo $kurulum ? 'Yeni şifre (en az 10 karakter)' : 'Şifre'; ?>" autocomplete="<?php echo $kurulum ? 'new-password' : 'current-password'; ?>" required>
					</label>
					<?php if ( $kurulum ) : ?>
						<label class="kilit-alan">
							<span class="kilit-ikon" aria-hidden="true">
								<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>
							</span>
							<input type="password" name="sifre2" placeholder="Yeni şifre (tekrar)" autocomplete="new-password" required>
						</label>
					<?php endif; ?>
					<div class="kilit-alt">
						<?php if ( ! $kurulum ) : ?>
							<label class="anahtar">
								<input type="checkbox" name="hatirla" value="1" checked>
								<span class="anahtar-govde" aria-hidden="true"></span>
								<span class="anahtar-yazi">Beni hatırla</span>
							</label>
						<?php else : ?>
							<span></span>
						<?php endif; ?>
						<button type="submit" class="kilit-dugme"<?php echo $bekleme > 0 ? ' disabled' : ''; ?>><?php echo $kurulum ? 'Şifreyi kaydet' : 'Giriş'; ?></button>
					</div>
					<?php if ( $bekleme > 0 ) : ?>
						<p class="kilit-not kilit-uyari">Çok fazla hatalı deneme yapıldı. <?php echo (int) ceil( $bekleme / 60 ); ?> dakika sonra tekrar deneyebilirsiniz.</p>
					<?php endif; ?>
				</div>
			</form>
			<?php // KURAL: Site adresi ayardan değil WordPress'ten alınır — alan adı değişse de bağlantı doğru kalır. ?>
			<a class="kilit-site" href="<?php echo esc_url( home_url( '/' ) ); ?>">
				<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 10.5 12 4l8 6.5V19a1.5 1.5 0 0 1-1.5 1.5h-13A1.5 1.5 0 0 1 4 19z"/><path d="M9.8 20.5v-6h4.4v6"/></svg>
				<span>Site ana sayfası</span>
			</a>
			<p class="kilit-dipnot">Bu ekran yalnızca yetkili hesaplara görünür.</p>
		</div>
		<?php
		YP_Ekran::sayfa_bitir();
	}
}
