<?php
defined( 'ABSPATH' ) || exit;

/**
 * Ortak sayfa iskeleti, mesajlar, yönlendirme ve form yardımcıları.
 * KURAL: Buradaki her çıktı esc_html / esc_attr / esc_url ile yazdırılır.
 */
class YP_Ekran {

	// KURAL: Sol sabit menü yoktur; her ekran ana menüden yeni sekmede açılır ve üstte yalnızca ince bir şerit bulunur.

	// ---- Mesajlar ve yönlendirme ----------------------------------------

	// KURAL: İşlem sonucu mesajı bir sonraki sayfada bir kez gösterilir — form tekrar gönderilmesin diye yönlendirme yapılır.
	public static function mesaj( $metin, $tur = 'basari' ) {
		$anahtar          = 'yp_mesaj_' . get_current_user_id();
		$liste            = get_transient( $anahtar );
		$liste            = is_array( $liste ) ? $liste : array();
		$liste[]          = array( 'tur' => $tur, 'metin' => $metin );
		set_transient( $anahtar, $liste, 120 );
	}

	protected static function mesajlari_al() {
		$anahtar = 'yp_mesaj_' . get_current_user_id();
		$liste   = get_transient( $anahtar );
		delete_transient( $anahtar );
		return is_array( $liste ) ? $liste : array();
	}

	public static function yonlendir( array $args = array(), $mesaj = '', $tur = 'basari' ) {
		if ( '' !== $mesaj ) {
			self::mesaj( $mesaj, $tur );
		}
		wp_safe_redirect( YP_Cekirdek::panel_url( $args ) );
		exit;
	}

	// KURAL: Geri dönüş adresi yalnızca panel adresi olabilir — dışarıya yönlendirme yapılmaz.
	public static function geri_don( $mesaj = '', $tur = 'basari' ) {
		if ( '' !== $mesaj ) {
			self::mesaj( $mesaj, $tur );
		}
		$geri = isset( $_POST['_geri'] ) ? esc_url_raw( wp_unslash( $_POST['_geri'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$kok  = YP_Cekirdek::panel_url();
		if ( '' === $geri || 0 !== strpos( $geri, $kok ) ) {
			$geri = $kok;
		}
		wp_safe_redirect( $geri );
		exit;
	}

	// KURAL: Hatalı gönderilen formun değerleri 5 dakika saklanır — kullanıcı yazdıklarını kaybetmez.
	public static function form_sakla( array $degerler ) {
		set_transient( 'yp_form_' . get_current_user_id(), $degerler, 300 );
	}

	public static function form_al() {
		$anahtar = 'yp_form_' . get_current_user_id();
		$v       = get_transient( $anahtar );
		delete_transient( $anahtar );
		return is_array( $v ) ? $v : array();
	}

	/**
	 * Tahsilat makbuzu adresi: ?ekran=yazdir&tur=makbuz&id=12,15
	 * KURAL: Makbuz numarası yoktur; makbuz tahsil edilen satırların kimlikleriyle açılır.
	 */
	public static function makbuz_url( array $idler ) {
		return YP_Cekirdek::panel_url( array( 'ekran' => 'yazdir', 'tur' => 'makbuz', 'id' => implode( ',', array_map( 'intval', $idler ) ) ) );
	}

	/**
	 * Para hareketi yazan işi tek transaction içinde çalıştırır.
	 * KURAL: Herhangi bir yazma başarısız olursa hiçbir değişiklik kalmaz; kullanıcı hata mesajıyla geri döner.
	 *
	 * @param callable   $is          Yazmaları yapan iş (içinde yönlendirme/exit yapılmaz).
	 * @param array|null $hata_hedefi Hata olursa gidilecek panel adresi; null ise geri dönülür.
	 */
	protected static function tek_islemde( callable $is, $hata_hedefi = null ) {
		try {
			return YP_Veri::tek_islemde( $is );
		} catch ( YP_Veri_Hatasi $e ) {
			$mesaj = $e instanceof YP_Islem_Engeli
				? esc_html( $e->getMessage() ) . ' Hiçbir değişiklik kaydedilmedi.'
				: 'İşlem tamamlanamadı; hiçbir değişiklik kaydedilmedi. Lütfen tekrar deneyin.';
			if ( is_array( $hata_hedefi ) ) {
				self::yonlendir( $hata_hedefi, $mesaj, 'hata' );
			}
			self::geri_don( $mesaj, 'hata' );
		}
	}

	public static function json( $veri, $kod = 200 ) {
		status_header( $kod );
		header( 'Content-Type: application/json; charset=utf-8' );
		echo wp_json_encode( $veri );
		exit;
	}

	// ---- Sayfa iskeleti -------------------------------------------------

	// KURAL: CSS/JS adresi dosyanın değişim zamanını taşır — eklenti güncellenince tarayıcı eski dosyayı kullanmaz.
	protected static function varlik_url( $tur, $dosya ) {
		$zaman = @filemtime( YP_DIZIN . 'varliklar/' . $dosya ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		return YP_Cekirdek::panel_url( array( 'varlik' => $tur, 'v' => YP_SURUM . '-' . (int) $zaman ) );
	}

	public static function sayfa_basla( $baslik, $aktif = '', $govde_sinifi = '' ) {
		$css = self::varlik_url( 'css', 'panel.css' );
		// KURAL: "uygulama" sınıfı hem html hem body'ye verilir — sayfa kaymaz, program penceresi gibi davranır.
		$uyg = false !== strpos( $govde_sinifi, 'uygulama' ) || in_array( $govde_sinifi, array( 'anamenu' ), true );
		?><!DOCTYPE html>
<html lang="tr"<?php echo $uyg ? ' class="uygulama"' : ''; ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow, noarchive">
<meta name="referrer" content="no-referrer">
<title><?php echo esc_html( $baslik . ' · ' . YP_Cekirdek::ayar( 'kurum_adi' ) ); ?></title>
<link rel="stylesheet" href="<?php echo esc_url( $css ); ?>">
</head>
<body class="<?php echo esc_attr( trim( $govde_sinifi . ( $uyg && false === strpos( $govde_sinifi, 'uygulama' ) ? ' uygulama' : '' ) ) ); ?>" data-panel="<?php echo esc_url( YP_Cekirdek::panel_url() ); ?>" data-ara-nonce="<?php echo esc_attr( YP_Guvenlik::nonce( 'ara' ) ); ?>">
<?php
// KURAL: "ust-yok" verilen ekranlar kendi ince araç satırını çizer — lacivert çubuk yer kaplamaz.
if ( ! in_array( $govde_sinifi, array( 'yazdir', 'kilit-sayfa', 'anamenu' ), true ) && false === strpos( $govde_sinifi, 'ust-yok' ) ) : ?>
<header class="ust">
	<a class="ana-menu-dugme" href="<?php echo esc_url( YP_Cekirdek::panel_url() ); ?>" title="Ana menü">☰ <span>Ana menü</span></a>
	<span class="sayfa-adi"><?php echo esc_html( $baslik ); ?></span>
	<form class="hizli-ara" method="get" action="<?php echo esc_url( YP_Cekirdek::panel_url() ); ?>" role="search" autocomplete="off">
		<input type="hidden" name="ekran" value="adaylar">
		<input type="search" name="q" placeholder="Aday ara: ad, TC, telefon, aday no…" aria-label="Hızlı aday arama" data-hizli-ara>
		<div class="hizli-sonuc" data-hizli-sonuc hidden></div>
	</form>
	<span class="kullanici"><?php echo esc_html( YP_Cekirdek::kullanici_adi() ); ?></span>
	<?php echo self::serit_anahtari(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
	<?php echo self::kilit_dugmesi(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
</header>
<?php endif; ?>
<main class="icerik">
	<?php foreach ( self::mesajlari_al() as $m ) : ?>
		<div class="uyari uyari-<?php echo esc_attr( $m['tur'] ); ?>" role="status"><?php echo wp_kses( $m['metin'], array( 'a' => array( 'href' => array() ), 'strong' => array(), 'br' => array() ) ); ?></div>
	<?php endforeach; ?>
		<?php
	}

	/**
	 * Ekranın en üstündeki tek satırlık araç çubuğu: ana menü, ekran adı, özet bilgi,
	 * (JS ile) birincil düğmeler, şerit oku ve kilit.
	 * KURAL: Lacivert üst çubuk ve koyu ad şeridi yerine bu tek ince satır kullanılır — içeriğe en çok yer kalır.
	 */
	public static function arac_cubugu( $baslik, $bilgi = '' ) {
		echo '<div class="arac-cubugu">';
		echo '<a class="dugme kucuk" href="' . esc_url( YP_Cekirdek::panel_url() ) . '" title="Ana menü">☰ Ana menü</a>';
		echo '<strong class="arac-ad">' . esc_html( $baslik ) . '</strong>';
		if ( '' !== $bilgi ) {
			echo '<span class="arac-bilgi">' . $bilgi . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput -- çağıran taraf kaçışlı HTML verir.
		}
		echo '<span class="ad-islem" data-birincil-yuva></span>';
		echo '<span class="arac-sag">' . self::serit_anahtari() . self::kilit_dugmesi() . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '</div>';
	}

	// KURAL: Araç şeridi kapalı başlar; bu ok düğmesi onu aşağı açar, tekrar basınca kapatır — ekran içeriğe kalır.
	public static function serit_anahtari() {
		return '<button type="button" class="serit-anahtar" data-serit-anahtar aria-expanded="false"'
			. ' title="Araç şeridini aç/kapat" aria-label="Araç şeridini aç/kapat">' . self::ikon( 'asagi', 16 ) . '</button>';
	}

	// KURAL: "Kilitle" paneli kapatır, WordPress oturumu açık kalır — ortak bilgisayarda ekranı hızlıca kapatmak için.
	protected static function kilit_dugmesi() {
		ob_start();
		self::form_ac( 'kilit_kapat', 'satir-ici' );
		echo '<button type="submit" class="dugme kucuk kilitle-dugme">Paneli kilitle</button></form>';
		return ob_get_clean();
	}

	public static function sayfa_bitir() {
		$js = self::varlik_url( 'js', 'panel.js' );
		?>
</main>
<script src="<?php echo esc_url( $js ); ?>"></script>
</body>
</html>
		<?php
	}

	/**
	 * Program penceresi düzeninde ekran açar.
	 * KURAL: Sayfa kaymaz; içerik tek bir kaydırma alanında ya da sabit bölümlerde durur.
	 *
	 * @param string $tur 'kaydir' → içerik tek parça kayar, 'sabit' → hiç kaymaz (tablolar kendi içinde kayar).
	 */
	public static function uyg_basla( $baslik, $aktif = '', $tur = 'kaydir', $serit = null, $ad_sagi = '', $govde_ek = '' ) {
		self::sayfa_basla( $baslik, $aktif, trim( 'uygulama ' . $govde_ek ) );
		echo '<div class="uyg">';
		// KURAL: Her ekran aynı düzendedir — üstte gizlenebilir şerit, altında tek satırlık ince araç çubuğu, sonra gövde.
		if ( is_callable( $serit ) ) {
			echo '<header class="ekran-serit">';
			call_user_func( $serit );
			echo '</header>';
			// KURAL: $ad_sagi false verilirse çubuk çizilmez — ekran kendi araç satırını kurar (aday listesi gibi).
			if ( false !== $ad_sagi ) {
				if ( false !== strpos( $govde_ek, 'ust-yok' ) ) {
					self::arac_cubugu( $baslik, $ad_sagi );
				} else {
					echo '<div class="ekran-ad"><strong>' . esc_html( $baslik ) . '</strong>';
					echo '<span class="ad-bilgi">' . $ad_sagi . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput -- çağıran taraf kaçışlı HTML verir.
					echo '</div>';
				}
			}
		}
		echo '<div class="uyg-govde ' . ( 'kaydir' === $tur ? 'kaydir' : '' ) . '">';
	}

	/**
	 * Ekranların ortak "Yönlendirmeler" grubu: Ana menü + Adaylar + Kasa.
	 * KURAL: Her ekrandan ana menüye ve en çok kullanılan iki ekrana tek tıkla dönülür.
	 */
	public static function serit_yon_grubu( $haric = '' ) {
		$dugmeler = array( array( 'Ana Menü', array(), 'ev' ) );
		if ( 'adaylar' !== $haric ) {
			$dugmeler[] = array( 'Adaylar', array( 'ekran' => 'adaylar' ), 'arama' );
		}
		if ( 'kasa' !== $haric ) {
			$dugmeler[] = array( 'Kasa', array( 'ekran' => 'kasa' ), 'kasa' );
		}
		self::serit_grubu_ciz( 'Yönlendirmeler', $dugmeler, 'serit-kapat' );
	}

	public static function uyg_bitir() {
		echo '</div></div>';
		self::sayfa_bitir();
	}

	public static function hata_sayfasi( $mesaj ) {
		self::sayfa_basla( 'Hata' );
		echo '<div class="uyari uyari-hata">' . esc_html( $mesaj ) . '</div>';
		echo '<p><a class="dugme" href="' . esc_url( YP_Cekirdek::panel_url() ) . '">Ana sayfaya dön</a></p>';
		self::sayfa_bitir();
	}

	// ---- Form yardımcıları ----------------------------------------------

	public static function form_ac( $islem, $ek_sinif = '', $dosya = false ) {
		echo '<form method="post" action="' . esc_url( YP_Cekirdek::panel_url() ) . '" class="' . esc_attr( $ek_sinif ) . '"' . ( $dosya ? ' enctype="multipart/form-data"' : '' ) . '>';
		echo YP_Guvenlik::nonce_alani( $islem ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<input type="hidden" name="_geri" value="' . esc_attr( self::geri_adresi() ) . '">';
	}

	// KURAL: Geri adresi mevcut panel sayfasıdır — işlemden sonra kullanıcı aynı yere döner.
	protected static function geri_adresi() {
		$args = array();
		foreach ( $_GET as $k => $v ) { // phpcs:ignore WordPress.Security.NonceVerification
			if ( is_string( $v ) ) {
				$args[ sanitize_key( $k ) ] = sanitize_text_field( wp_unslash( $v ) );
			}
		}
		return YP_Cekirdek::panel_url( $args );
	}

	public static function gizli( $ad, $deger ) {
		echo '<input type="hidden" name="' . esc_attr( $ad ) . '" value="' . esc_attr( $deger ) . '">';
	}

	public static function alan( $ad, $etiket, $deger = '', array $ek = array() ) {
		// KURAL: Boş (NULL) veritabanı değeri boş metin olarak yazılır — PHP 8.1+ uyarısı oluşmaz.
		$deger    = null === $deger ? '' : (string) $deger;
		$tur      = isset( $ek['tur'] ) ? $ek['tur'] : 'text';
		$sinif    = isset( $ek['sinif'] ) ? $ek['sinif'] : '';
		$zorunlu  = ! empty( $ek['zorunlu'] );
		$ozellik  = '';
		foreach ( array( 'placeholder', 'maxlength', 'inputmode', 'pattern', 'autocomplete', 'min', 'max', 'step' ) as $o ) {
			if ( isset( $ek[ $o ] ) ) {
				$ozellik .= ' ' . $o . '="' . esc_attr( $ek[ $o ] ) . '"';
			}
		}
		if ( ! empty( $ek['veri'] ) ) {
			foreach ( $ek['veri'] as $k => $v ) {
				$ozellik .= ' data-' . esc_attr( $k ) . '="' . esc_attr( $v ) . '"';
			}
		}
		$id = 'a-' . sanitize_html_class( $ad ) . '-' . wp_rand( 100, 999999 );
		echo '<label class="alan ' . esc_attr( $sinif ) . '" for="' . esc_attr( $id ) . '"><span>' . esc_html( $etiket ) . ( $zorunlu ? ' <b class="zorunlu">*</b>' : '' ) . '</span>';
		if ( 'textarea' === $tur ) {
			echo '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $ad ) . '" rows="3"' . $ozellik . ( $zorunlu ? ' required' : '' ) . '>' . esc_textarea( $deger ) . '</textarea>'; // phpcs:ignore
		} else {
			echo '<input id="' . esc_attr( $id ) . '" type="' . esc_attr( $tur ) . '" name="' . esc_attr( $ad ) . '" value="' . esc_attr( $deger ) . '"' . $ozellik . ( $zorunlu ? ' required' : '' ) . '>'; // phpcs:ignore
		}
		echo '</label>';
	}

	// KURAL: Tarih alanı gg.aa.yyyy yazımıyla çalışır — tarayıcı dilinden bağımsız hep aynı görünür.
	public static function tarih_alani( $ad, $etiket, $ymd = '', array $ek = array() ) {
		$ek['sinif']       = trim( ( isset( $ek['sinif'] ) ? $ek['sinif'] : '' ) . ' tarih' );
		$ek['placeholder'] = 'gg.aa.yyyy';
		$ek['inputmode']   = 'numeric';
		$ek['maxlength']   = 10;
		$ek['autocomplete'] = 'off';
		$ek['veri']        = array( 'tarih' => '1' );
		// KURAL: yyyy-aa-gg gelirse gg.aa.yyyy'ye çevrilir; başka yazım (hatalı form tekrar gösterimi) olduğu gibi kalır.
		$gorunen = preg_match( '/^\d{4}-\d{2}-\d{2}/', (string) $ymd ) ? YP_Bicim::tarih( $ymd ) : (string) $ymd;
		self::alan( $ad, $etiket, $gorunen, $ek );
	}

	public static function tutar_alani( $ad, $etiket, $tutar = '', array $ek = array() ) {
		$ek['sinif']     = trim( ( isset( $ek['sinif'] ) ? $ek['sinif'] : '' ) . ' tutar' );
		$ek['inputmode'] = 'decimal';
		$ek['placeholder'] = '0,00';
		$ek['autocomplete'] = 'off';
		self::alan( $ad, $etiket, ( '' === $tutar || null === $tutar ) ? '' : YP_Bicim::tl( $tutar, false ), $ek );
	}

	public static function secim( $ad, $etiket, array $secenekler, $secili = '', array $ek = array() ) {
		$bos     = array_key_exists( 'bos', $ek ) ? $ek['bos'] : 'Seçiniz';
		$zorunlu = ! empty( $ek['zorunlu'] );
		$sinif   = isset( $ek['sinif'] ) ? $ek['sinif'] : '';
		$ozellik = '';
		if ( ! empty( $ek['veri'] ) ) {
			foreach ( $ek['veri'] as $k => $v ) {
				$ozellik .= ' data-' . esc_attr( $k ) . '="' . esc_attr( $v ) . '"';
			}
		}
		$id = 'a-' . sanitize_html_class( $ad ) . '-' . wp_rand( 100, 999999 );
		echo '<label class="alan ' . esc_attr( $sinif ) . '" for="' . esc_attr( $id ) . '"><span>' . esc_html( $etiket ) . ( $zorunlu ? ' <b class="zorunlu">*</b>' : '' ) . '</span>';
		echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $ad ) . '"' . ( $zorunlu ? ' required' : '' ) . $ozellik . '>'; // phpcs:ignore
		if ( null !== $bos ) {
			echo '<option value="">' . esc_html( $bos ) . '</option>';
		}
		foreach ( $secenekler as $deger => $yazi ) {
			if ( is_array( $yazi ) ) {
				$veri = '';
				foreach ( $yazi['veri'] as $k => $v ) {
					$veri .= ' data-' . esc_attr( $k ) . '="' . esc_attr( $v ) . '"';
				}
				echo '<option value="' . esc_attr( $deger ) . '"' . selected( (string) $secili, (string) $deger, false ) . $veri . '>' . esc_html( $yazi['ad'] ) . '</option>'; // phpcs:ignore
			} else {
				echo '<option value="' . esc_attr( $deger ) . '"' . selected( (string) $secili, (string) $deger, false ) . '>' . esc_html( $yazi ) . '</option>';
			}
		}
		echo '</select></label>';
	}

	// KURAL: Silme gibi geri dönüşü zor işlemler onay penceresi ister.
	public static function kucuk_form( $islem, array $alanlar, $dugme, $sinif = 'dugme kucuk', $onay = '' ) {
		self::form_ac( $islem, 'satir-ici' );
		foreach ( $alanlar as $k => $v ) {
			self::gizli( $k, $v );
		}
		echo '<button type="submit" class="' . esc_attr( $sinif ) . '"' . ( '' !== $onay ? ' data-onay="' . esc_attr( $onay ) . '"' : '' ) . '>' . esc_html( $dugme ) . '</button></form>';
	}

	// ---- Liste yardımcıları ---------------------------------------------

	public static function sayfalama( $toplam, $sayfa, $sayfa_basi, array $args ) {
		$son = max( 1, (int) ceil( $toplam / $sayfa_basi ) );
		if ( $son <= 1 ) {
			return;
		}
		echo '<nav class="sayfalama" aria-label="Sayfalar">';
		$goster = array_unique( array_filter( array( 1, $sayfa - 2, $sayfa - 1, $sayfa, $sayfa + 1, $sayfa + 2, $son ), function ( $s ) use ( $son ) {
			return $s >= 1 && $s <= $son;
		} ) );
		sort( $goster );
		$onceki = 0;
		foreach ( $goster as $s ) {
			if ( $onceki && $s > $onceki + 1 ) {
				echo '<span class="bosluk">…</span>';
			}
			$url = YP_Cekirdek::panel_url( array_merge( $args, array( 'sayfa' => $s ) ) );
			echo $s === $sayfa ? '<span class="aktif">' . (int) $s . '</span>' : '<a href="' . esc_url( $url ) . '">' . (int) $s . '</a>';
			$onceki = $s;
		}
		echo '<span class="toplam">' . (int) $toplam . ' kayıt</span></nav>';
	}

	/**
	 * Program şeridi (ribbon) grubu: simgeli düğmeler + altında grup başlığı.
	 * $dugmeler: her biri array( etiket, args|'', ikon, sinif|'', islem|'' )
	 * KURAL: Şeritteki her düğme ya bağlantıdır ya da tek alanlı bir formdur — ayrı menüye gerek kalmaz.
	 */
	public static function serit_grubu_ciz( $baslik, array $dugmeler, $ek_sinif = '' ) {
		echo '<div class="serit-grup ' . esc_attr( $ek_sinif ) . '"><div class="serit-dugmeler">';
		foreach ( $dugmeler as $d ) {
			$etiket = $d[0];
			$hedef  = $d[1];
			$ikon   = $d[2];
			$sinif  = isset( $d[3] ) ? $d[3] : '';
			if ( is_array( $hedef ) ) {
				echo '<a class="serit-dugme ' . esc_attr( $sinif ) . '" href="' . esc_url( YP_Cekirdek::panel_url( $hedef ) ) . '">';
			} elseif ( 0 === strpos( (string) $hedef, '#' ) ) {
				// KURAL: "#" ile başlayan hedef, sayfadaki bir bölümü açıp kapatır (süzgeç paneli gibi) — yeni sayfa açılmaz.
				echo '<button type="button" class="serit-dugme ' . esc_attr( $sinif ) . '" data-ac="' . esc_attr( substr( $hedef, 1 ) ) . '">';
			} else {
				// KURAL: 5. eleman verilirse düğmeye ek nitelik yazılır (data-yazdir gibi) — çağıran taraf güvenli metin verir.
				$oz = isset( $d[4] ) ? ' ' . $d[4] : '';
				echo '<button type="button" class="serit-dugme ' . esc_attr( $sinif ) . '"' . ( '' !== $hedef ? ' data-dialog="' . esc_attr( $hedef ) . '"' : '' ) . $oz . '>'; // phpcs:ignore WordPress.Security.EscapeOutput
			}
			echo '<span class="serit-ikon">' . self::ikon( $ikon, 22 ) . '</span><span>' . esc_html( $etiket ) . '</span>'; // phpcs:ignore
			echo is_array( $hedef ) ? '</a>' : '</button>';
		}
		echo '</div><div class="serit-baslik">' . esc_html( $baslik ) . '</div></div>';
	}

	/**
	 * Şeritte form gönderen düğme (Kaydet, Sil gibi).
	 */
	public static function serit_form_dugmesi( $etiket, $islem, array $alanlar, $ikon, $sinif = '', $onay = '' ) {
		self::form_ac( $islem, 'satir-ici' );
		foreach ( $alanlar as $k => $v ) {
			self::gizli( $k, $v );
		}
		echo '<button type="submit" class="serit-dugme ' . esc_attr( $sinif ) . '"' . ( '' !== $onay ? ' data-onay="' . esc_attr( $onay ) . '"' : '' ) . '>';
		echo '<span class="serit-ikon">' . self::ikon( $ikon, 22 ) . '</span><span>' . esc_html( $etiket ) . '</span></button></form>'; // phpcs:ignore
	}

	// KURAL: Simgeler dosya değil, sayfa içi SVG'dir — ek istek olmaz, her ölçekte net görünür.
	public static function ikon( $ad, $boyut = 24 ) {
		$yollar = array(
			'aday-ekle' => '<circle cx="9" cy="8" r="3.6"/><path d="M2.5 20c0-3.4 2.9-5.6 6.5-5.6 1.5 0 2.9.4 4 1.1"/><path d="M17.5 14v6M14.5 17h6"/>',
			'arama'     => '<circle cx="11" cy="11" r="6.2"/><path d="M15.6 15.6 21 21"/>',
			'kasa'      => '<rect x="3" y="7" width="18" height="12" rx="2"/><path d="M3 11h18M7 15h3"/>',
			'para'      => '<circle cx="12" cy="12" r="8.2"/><path d="M12 7.5v9M9.6 9.6h4.2a1.9 1.9 0 0 1 0 3.8h-3.6a1.9 1.9 0 0 0 0 3.8h4.2"/>',
			'uyari'     => '<path d="M12 3.8 21 19.5H3z"/><path d="M12 9.8v4.4M12 16.8h.01"/>',
			'alacak'    => '<path d="M4 19V9M10 19V5M16 19v-6M22 19H2"/>',
			'referans'  => '<circle cx="8.5" cy="9" r="3.2"/><circle cx="17" cy="10.5" r="2.6"/><path d="M2.6 19c0-3.1 2.6-5.1 5.9-5.1s5.9 2 5.9 5.1M15.2 14.2c3 .1 5.2 1.9 5.2 4.8"/>',
			'ayar'      => '<circle cx="12" cy="12" r="3"/><path d="M19.4 14a1.6 1.6 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.6 1.6 0 0 0-1.8-.3 1.6 1.6 0 0 0-1 1.5V20a2 2 0 1 1-4 0v-.1A1.6 1.6 0 0 0 9 18.4a1.6 1.6 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.6 1.6 0 0 0 .3-1.8 1.6 1.6 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1A1.6 1.6 0 0 0 4.6 9a1.6 1.6 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.6 1.6 0 0 0 1.8.3H9a1.6 1.6 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.6 1.6 0 0 0 1 1.5 1.6 1.6 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.6 1.6 0 0 0-.3 1.8V9a1.6 1.6 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.6 1.6 0 0 0-1.5 1z"/>',
			'cop'       => '<path d="M4 7h16M9.5 7V4.8h5V7M6.5 7l1 12.2h9L17.5 7M10.5 10.5v6M13.5 10.5v6"/>',
			'liste'     => '<path d="M8 6.5h12M8 12h12M8 17.5h12M4 6.5h.01M4 12h.01M4 17.5h.01"/>',
			'rapor'     => '<path d="M6 3.5h8l4 4V20a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V4.5a1 1 0 0 1 1-1z"/><path d="M14 3.5V8h4M8.5 13h7M8.5 16.5h5"/>',
			'saat'      => '<circle cx="12" cy="12" r="8.4"/><path d="M12 7.4V12l3 1.8"/>',
			'kilit'     => '<rect x="4.5" y="10" width="15" height="10" rx="2.5"/><path d="M8 10V7.5a4 4 0 0 1 8 0V10"/>',
			'kaydet'    => '<path d="M5 4.5h10.5L19.5 8.5V18a1.5 1.5 0 0 1-1.5 1.5H5A1.5 1.5 0 0 1 3.5 18V6A1.5 1.5 0 0 1 5 4.5z"/><path d="M8 4.5v5h7v-5M8 19.5v-5h8v5"/>',
			'kapat'     => '<path d="M6 6l12 12M18 6L6 18"/>',
			'onceki'    => '<path d="M14.5 5.5 8 12l6.5 6.5"/>',
			'sonraki'   => '<path d="M9.5 5.5 16 12l-6.5 6.5"/>',
			'yazdir'    => '<path d="M7 9V4.5h10V9"/><rect x="4" y="9" width="16" height="7" rx="1.6"/><path d="M7 14h10v5.5H7z"/>',
			'kamera'    => '<path d="M4.5 8h3l1.4-2h6.2L16.5 8h3A1.5 1.5 0 0 1 21 9.5v8A1.5 1.5 0 0 1 19.5 19h-15A1.5 1.5 0 0 1 3 17.5v-8A1.5 1.5 0 0 1 4.5 8z"/><circle cx="12" cy="13" r="3.2"/>',
			'geri'      => '<path d="M9.5 6.5 4.5 11.5l5 5"/><path d="M4.5 11.5h9a6 6 0 0 1 0 12H9"/>',
			'ekle'      => '<path d="M12 5v14M5 12h14"/>',
			'duzenle'   => '<path d="M15.6 4.9 19 8.3 8.4 18.9l-4.2.8.8-4.2z"/>',
			'takvim'    => '<rect x="3.5" y="5.5" width="17" height="15" rx="2"/><path d="M3.5 10h17M8 3.5v4M16 3.5v4"/>',
			'ev'        => '<path d="M4 10.5 12 4l8 6.5V19a1.5 1.5 0 0 1-1.5 1.5h-13A1.5 1.5 0 0 1 4 19z"/><path d="M9.8 20.5v-6h4.4v6"/>',
			'asagi'     => '<path d="M6 9.5 12 15.5l6-6"/>',
			'onay'      => '<circle cx="12" cy="12" r="8.4"/><path d="m8.2 12.2 2.7 2.7 5-5.4"/>',
			'transfer'  => '<path d="M4 8.5h13M14 5.5l3 3-3 3"/><path d="M20 15.5H7M10 12.5l-3 3 3 3"/>',
			'gider'     => '<circle cx="12" cy="12" r="8.2"/><path d="M8 12h8"/>',
			'gelir'     => '<circle cx="12" cy="12" r="8.2"/><path d="M12 8v8M8 12h8"/>',
		);
		$ic = isset( $yollar[ $ad ] ) ? $yollar[ $ad ] : $yollar['liste'];
		return '<svg viewBox="0 0 24 24" width="' . (int) $boyut . '" height="' . (int) $boyut . '" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $ic . '</svg>';
	}

	/**
	 * Ana menü karosu.
	 */
	// KURAL: Karolar normal bağlantıdır — tek tık aynı sekmede açar, farenin orta tuşu tarayıcının kendi kuralıyla yeni sekmede açar.
	// KURAL: $anahtar verilen karo taşınabilir; yerleşim bu anahtarla saklanır — başlık değişse de karonun yeri korunur.
	public static function karo( $baslik, array $args, $renk, $ikon, $deger = '', $alt = '', $sinif = '', $yeni_sekme = false, $anahtar = '' ) {
		$hedef = $yeni_sekme ? ' target="_blank" rel="noopener"' : '';
		$veri  = '' !== $anahtar ? ' data-karo="' . esc_attr( $anahtar ) . '"' : '';
		echo '<a class="karo karo-' . esc_attr( $renk ) . ' ' . esc_attr( $sinif ) . '" href="' . esc_url( YP_Cekirdek::panel_url( $args ) ) . '"' . $hedef . $veri . '>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<span><b>' . esc_html( $baslik ) . '</b>' . ( '' !== $alt ? '<small>' . esc_html( $alt ) . '</small>' : '' ) . '</span>';
		if ( '' !== $deger ) {
			echo '<span class="karo-deger">' . esc_html( $deger ) . '</span>';
		}
		echo '<span class="karo-ikon">' . self::ikon( $ikon, 62 ) . '</span></a>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	public static function rozet( $metin, $tur = '' ) {
		return '<span class="rozet ' . esc_attr( $tur ) . '">' . esc_html( $metin ) . '</span>';
	}

	public static function odeme_rozeti( $durum ) {
		$d = YP_Veri::odeme_durumlari();
		$c = array( 'ODENDI' => 'yesil', 'ODENMEDI' => 'turuncu', 'IADE' => 'gri' );
		return self::rozet( isset( $d[ $durum ] ) ? $d[ $durum ] : $durum, isset( $c[ $durum ] ) ? $c[ $durum ] : '' );
	}

	public static function islem_rozeti( $durum ) {
		$d = YP_Veri::islem_durumlari();
		$c = array( 'randevu' => 'mavi', 'test' => 'turuncu', 'rapor' => 'mor', 'teslim' => 'yesil', 'iptal' => 'gri' );
		return self::rozet( isset( $d[ $durum ] ) ? $d[ $durum ] : $durum, isset( $c[ $durum ] ) ? $c[ $durum ] : '' );
	}

	public static function tutar_hucre( $tutar, $vurgu = false ) {
		$sinif = 'sag' . ( $vurgu && $tutar > 0 ? ' kirmizi' : '' );
		return '<td class="' . $sinif . '">' . esc_html( YP_Bicim::tl( $tutar ) ) . '</td>';
	}

	public static function bos_liste( $metin, $sutun ) {
		echo '<tr><td colspan="' . (int) $sutun . '" class="bos">' . esc_html( $metin ) . '</td></tr>';
	}

	public static function sayfa_no() {
		return max( 1, YP_Guvenlik::tamsayi( 'sayfa', 'get' ) );
	}

	public static function sayfa_basi() {
		$n = (int) YP_Cekirdek::ayar( 'sayfa_basi' );
		return $n > 0 ? min( $n, 200 ) : 25;
	}

	public static function aday_linki( $id, $metin, $sekme = '' ) {
		$args = array( 'ekran' => 'aday', 'id' => (int) $id );
		if ( '' !== $sekme ) {
			$args['sekme'] = $sekme;
		}
		return '<a href="' . esc_url( YP_Cekirdek::panel_url( $args ) ) . '">' . esc_html( $metin ) . '</a>';
	}
}
