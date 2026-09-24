<?php
defined( 'ABSPATH' ) || exit;

/**
 * Ana menü (hub): üstte gizlenebilir araç şeridi, altında renkli sekme çubuğu, solda kurum alanı,
 * sağda az sayıda büyük karo, altta durum çubuğu.
 * KURAL: Seçimler aynı sekmede açılır; her ekranın üstünde "Ana menü" düğmesi vardır. Orta tuşla tıklayan yeni sekmede açar.
 */
final class YP_Ekran_Ana_Sayfa extends YP_Ekran {

	public static function goster() {
		global $wpdb;
		$a     = YP_Cekirdek::tablo( 'adaylar' );
		$bugun = YP_Cekirdek::bugun();

		// KURAL: Ana menü tek bir sayı gösterir (aktif aday); veri değişene kadar önbellekten okunur — menü anında açılır.
		$aktif_aday = (int) YP_Cekirdek::onbellek( 'anamenu', 5 * MINUTE_IN_SECONDS, function () use ( $wpdb, $a ) {
			return $wpdb->get_var( "SELECT COUNT(*) FROM {$a} WHERE silindi = 0 AND arsiv = 0" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		} );

		self::sayfa_basla( 'Ana Menü', 'ana-sayfa', 'anamenu' );
		?>
		<div class="hub">
			<header class="hub-serit">
				<?php
				self::serit_grubu( 'Aday İşlemleri', array(
					array( 'Yeni Aday Kaydı', array( 'ekran' => 'aday', 'yeni' => 1 ), 'aday-ekle' ),
					array( 'Aday Listesi', array( 'ekran' => 'adaylar' ), 'arama' ),
					array( 'Borçlu Adaylar', array( 'ekran' => 'adaylar', 'odeme' => 'borclu' ), 'rapor' ),
					array( 'Geciken Ödemeler', array( 'ekran' => 'adaylar', 'odeme' => 'geciken' ), 'uyari' ),
				) );
				self::serit_grubu( 'Takip', array(
					array( 'Takip Listeleri', array( 'ekran' => 'takip' ), 'saat' ),
					array( 'Raporlar', array( 'ekran' => 'raporlar' ), 'rapor' ),
				) );
				self::serit_grubu( 'Kasa', array(
					array( 'Kasa', array( 'ekran' => 'kasa' ), 'kasa' ),
				) );
				self::serit_grubu( 'SMS', array(
					array( 'Toplu SMS', array( 'ekran' => 'sms' ), 'sms' ),
					array( 'Gönderim Geçmişi', array( 'ekran' => 'sms', 'sekme' => 'gecmis' ), 'liste' ),
				) );
				self::serit_grubu( 'Yönetim', array(
					array( 'Tanımlar', array( 'ekran' => 'tanimlar' ), 'ayar' ),
					array( 'İşlem Günlüğü', array( 'ekran' => 'gunluk' ), 'liste' ),
					array( 'Silinenler', array( 'ekran' => 'silinenler' ), 'cop' ),
				) );
				echo '<div class="serit-grup serit-kapat">';
				echo '<div class="serit-dugmeler">';
				self::form_ac( 'kilit_kapat', 'satir-ici' );
				echo '<button type="submit" class="serit-dugme"><span class="serit-ikon">' . self::ikon( 'kilit', 30 ) . '</span><span>Kilitle</span></button></form>'; // phpcs:ignore
				echo '</div><div class="serit-baslik">Kapat</div></div>';
				?>
			</header>

			<nav class="hub-sekme">
				<?php
				// KURAL: Sekme çubuğundaki kurum harfine basınca panel kilitlenir ve giriş ekranı açılır — hızlı çıkış yolu.
				self::form_ac( 'kilit_kapat', 'satir-ici marka-kilit' );
				echo '<button type="submit" class="marka-rozet" title="Panel girişine dön">' . esc_html( mb_substr( (string) YP_Cekirdek::ayar( 'kurum_adi' ), 0, 1, 'UTF-8' ) ) . '</button></form>';
				?>
				<?php // KURAL: Kurum sitesi paneli terk eden tek bağlantıdır; yeni sekmede ve referans bilgisi verilmeden açılır. ?>
				<a class="s-mavi" href="<?php echo esc_url( YP_Cekirdek::ayar( 'web_sitesi' ) ); ?>" target="_blank" rel="noopener noreferrer">WEB SİTESİ</a>
				<a class="s-yesil" href="<?php echo esc_url( YP_Cekirdek::panel_url( array( 'ekran' => 'gunluk' ) ) ); ?>">İŞLEM GÜNLÜĞÜ</a>
				<?php // KURAL: Hızlı aday arama ana menüde durur; ekranlarda üst çubuk olmadığı için yer kaplamaz. ?>
				<form class="hizli-ara" method="get" action="<?php echo esc_url( YP_Cekirdek::panel_url() ); ?>" role="search" autocomplete="off">
					<input type="hidden" name="ekran" value="adaylar">
					<input type="search" name="q" placeholder="Aday ara: ad, TC, telefon, aday no…" aria-label="Hızlı aday arama" data-hizli-ara>
					<div class="hizli-sonuc" data-hizli-sonuc hidden></div>
				</form>
				<?php echo self::serit_anahtari(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			</nav>

			<main class="hub-govde">
				<aside class="hub-marka">
					<?php // KURAL: Logo panel adresinden verilir; eklenti klasöründen doğrudan açılamaz. ?>
					<div class="marka-ust">
						<?php if ( is_readable( YP_DIZIN . 'varliklar/minilogo.png' ) ) : // KURAL: Logo dosyası yoksa kırık resim gösterilmez. ?>
							<?php // KURAL: Adres gömülü resim (data:) olabildiği için esc_url değil esc_attr kullanılır — esc_url data: adreslerini siler. ?>
							<img class="hub-logo" src="<?php echo esc_attr( self::logo_url() ); ?>" alt="" width="96" height="96" decoding="async">
						<?php endif; ?>
						<h1><?php echo esc_html( YP_Cekirdek::ayar( 'kurum_adi' ) ); ?></h1>
					</div>
					<p>Otomasyon Sistemi</p>
					<div class="hub-tarih"><?php echo esc_html( YP_Bicim::tarih_uzun( $bugun ) ); ?></div>
				</aside>

				<?php // KURAL: Her karonun sabit bir anahtarı vardır; yerleşim tarayıcıda bu anahtarlarla saklanır. ?>
				<section class="hub-karolar" data-karo-duzen>
					<?php
					// KURAL: Karolarda yalnızca başlık (ve varsa sayı) durur; açıklama satırı yoktur — menü sade kalır.
					self::karo( 'Yeni Aday Kaydı', array( 'ekran' => 'aday', 'yeni' => 1 ), 'yesil', 'aday-ekle', '', '', '', false, 'yeni-aday' );
					self::karo( 'Kasa İşlemleri', array( 'ekran' => 'kasa' ), 'lacivert', 'kasa', '', '', '', false, 'kasa' );
					self::karo( 'Aday Listesi', array( 'ekran' => 'adaylar' ), 'mavi', 'arama', (string) $aktif_aday, '', '', false, 'adaylar' );
					self::karo( 'Raporlar', array( 'ekran' => 'raporlar' ), 'mavi', 'rapor', '', '', '', false, 'raporlar' );
					self::karo( 'SMS Gönderimi', array( 'ekran' => 'sms' ), 'turuncu', 'sms', '', '', '', false, 'sms' );
					self::karo( 'İşlem Günlüğü', array( 'ekran' => 'gunluk' ), 'gri', 'liste', '', '', '', false, 'gunluk' );
					self::karo( 'Silinenler', array( 'ekran' => 'silinenler' ), 'gri', 'cop', '', '', '', false, 'silinenler' );
					self::karo( 'Tanımlar ve Ayarlar', array( 'ekran' => 'tanimlar' ), 'lacivert', 'ayar', '', '', 'karo-sag-alt', false, 'tanimlar' );
					?>
					<div class="karo-duzen-serit">
						<span>Düzen kipi — karoları sürükleyip istediğiniz yere bırakın.</span>
						<button type="button" data-karo-varsayilan>Varsayılan dizilim</button>
						<button type="button" data-karo-bitti>Bitti</button>
					</div>
				</section>
			</main>

			<footer class="hub-durum">
				<span class="d-surum">SÜRÜM: <?php echo esc_html( YP_SURUM ); ?></span>
				<span class="d-kullanici">KULLANICI: <?php echo esc_html( strtoupper( YP_Kilit::kullanici_adi() ) ); ?></span>
				<a class="d-sifre" href="<?php echo esc_url( YP_Cekirdek::panel_url( array( 'ekran' => 'tanimlar', 'sekme' => 'guvenlik' ) ) ); ?>">ŞİFRE DEĞİŞTİR</a>
				<?php
				self::form_ac( 'kilit_kapat', 'satir-ici d-kilit' );
				echo '<button type="submit">PANELİ KİLİTLE</button></form>';
				?>
			</footer>
		</div>
		<?php
		self::sayfa_bitir();
	}

	/**
	 * Logonun adresi. Dosya okunabiliyorsa sayfanın içine gömülür (data URI) — ayrı bir istek,
	 * önbellek ya da güvenlik eklentisi araya girmez; logo her koşulda görünür.
	 * KURAL: Gömme başarısız olursa panel adresindeki logo ucuna düşülür, o da yoksa logo hiç basılmaz.
	 */
	private static function logo_url() {
		$dosya = YP_DIZIN . 'varliklar/minilogo.png';
		if ( is_readable( $dosya ) && filesize( $dosya ) <= 60000 ) {
			$icerik = file_get_contents( $dosya ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			if ( false !== $icerik ) {
				return 'data:image/png;base64,' . base64_encode( $icerik ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
			}
		}
		return self::varlik_url( 'logo', 'minilogo.png' );
	}

	private static function serit_grubu( $baslik, array $dugmeler ) {
		echo '<div class="serit-grup"><div class="serit-dugmeler">';
		foreach ( $dugmeler as $d ) {
			echo '<a class="serit-dugme" href="' . esc_url( YP_Cekirdek::panel_url( $d[1] ) ) . '">';
			echo '<span class="serit-ikon">' . self::ikon( $d[2], 30 ) . '</span><span>' . esc_html( $d[0] ) . '</span></a>'; // phpcs:ignore
		}
		echo '</div><div class="serit-baslik">' . esc_html( $baslik ) . '</div></div>';
	}
}
