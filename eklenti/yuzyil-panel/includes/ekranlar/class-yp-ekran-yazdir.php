<?php
defined( 'ABSPATH' ) || exit;

/**
 * Yazdırılabilir sayfalar: tahsilat makbuzu. Tarayıcının "Yazdır / PDF olarak kaydet" özelliği kullanılır.
 * KURAL: Makbuz numarası yoktur; makbuz, yazdırılacak tahsilat satırlarının kimlikleriyle açılır.
 */
final class YP_Ekran_Yazdir extends YP_Ekran {

	// KURAL: Bir makbuzda en fazla bu kadar satır olabilir — adres uzunluğu ve sorgu sınırlı kalır.
	const EN_COK_SATIR = 50;

	public static function goster() {
		$tur = YP_Guvenlik::secim( 'tur', array( 'makbuz', 'kurs_borc' ), 'makbuz', 'get' );
		if ( 'kurs_borc' === $tur ) {
			self::kurs_borc();
			return;
		}
		self::makbuz( self::id_listesi( YP_Guvenlik::metin( 'id', 'get', 600 ) ) );
	}

	/**
	 * Sürücü kursu (referans) borç bakiye listesi — ilgili merkeze iletilmek üzere yazdırılır.
	 * KURAL: Listeye yalnızca kapanmamış bakiyesi olan adaylar girer; seçilen kurs sayısı sınırlıdır.
	 */
	private static function kurs_borc() {
		global $wpdb;
		// KURAL: Adresten gelen kurs listesi yalnızca pozitif tam sayılardan oluşur; dizi/metin gibi
		// beklenmeyen değerler atılır, tekrarlar teke iner ve en çok 100 kurs alınır.
		$secilen = array();
		$ham     = isset( $_GET['ref'] ) ? (array) $_GET['ref'] : array(); // phpcs:ignore WordPress.Security.NonceVerification
		foreach ( $ham as $x ) {
			if ( ! is_scalar( $x ) ) {
				continue;
			}
			$n = (int) $x;
			if ( $n > 0 && ! in_array( $n, $secilen, true ) ) {
				$secilen[] = $n;
			}
		}
		$secilen = array_slice( $secilen, 0, 100 );
		$bas     = YP_Guvenlik::tarih( 'bas', 'get' );
		$bit     = YP_Guvenlik::tarih( 'bit', 'get' );
		if ( ! $secilen ) {
			self::hata_sayfasi( 'Liste için en az bir sürücü kursu seçin.' );
			return;
		}

		$a     = YP_Cekirdek::tablo( 'adaylar' );
		$yer   = implode( ',', array_fill( 0, count( $secilen ), '%d' ) );
		$where = array( 'a.silindi = 0', "a.referans_id IN ({$yer})" );
		$args  = $secilen;
		if ( $bas ) {
			$where[] = 'a.kayit_tarihi >= %s';
			$args[]  = $bas;
		}
		if ( $bit ) {
			$where[] = 'a.kayit_tarihi <= %s';
			$args[]  = $bit;
		}
		$satirlar = $wpdb->get_results( $wpdb->prepare(
			"SELECT a.id, a.aday_no, a.adi, a.soyadi, a.kayit_tarihi, a.referans_id,
				COALESCE(b.borc, 0) AS borc, COALESCE(b.odenen, 0) AS odenen,
				COALESCE(b.borc, 0) - COALESCE(b.odenen, 0) AS kalan
			FROM {$a} a LEFT JOIN (" . YP_Hesap::bakiye_alt_sorgusu() . ') b ON b.aday_id = a.id
			WHERE ' . implode( ' AND ', $where ) . ' AND (COALESCE(b.borc, 0) - COALESCE(b.odenen, 0)) > 0
			ORDER BY a.referans_id, a.aday_no', // phpcs:ignore WordPress.DB.PreparedSQL
			$args
		) );

		$kurslar = array();
		foreach ( YP_Ekran_Kasa::borclu_kurslar() as $k ) {
			$kurslar[ (int) $k->id ] = $k->unvan ? $k->unvan : $k->ad_soyad;
		}
		$gruplar = array();
		$toplam  = 0.0;
		foreach ( $satirlar as $s ) {
			$gruplar[ (int) $s->referans_id ][] = $s;
			$toplam                            += (float) $s->kalan;
		}
		$ayar = YP_Cekirdek::ayarlar();

		self::sayfa_basla( 'Borç Bakiye Listesi', '', 'yazdir' );
		?>
		<div class="yazdir-dugmeler">
			<button type="button" class="dugme ana" data-yazdir>Yazdır</button>
			<a class="dugme" href="<?php echo esc_url( YP_Cekirdek::panel_url( array( 'ekran' => 'kasa' ) ) ); ?>">Kasaya dön</a>
		</div>
		<article class="makbuz">
			<header>
				<div>
					<h1><?php echo esc_html( $ayar['kurum_adi'] ); ?></h1>
					<p><?php echo esc_html( $ayar['kurum_adresi'] ); ?><?php echo $ayar['kurum_telefonu'] ? '<br>Tel: ' . esc_html( $ayar['kurum_telefonu'] ) : ''; ?></p>
				</div>
				<div class="makbuz-baslik">
					<strong>BORÇ BAKİYE LİSTESİ</strong>
					<span><?php echo esc_html( ( $bas || $bit ) ? YP_Bicim::tarih( $bas ) . ' – ' . YP_Bicim::tarih( $bit ) : 'Tüm kayıtlar' ); ?></span>
					<span>Döküm: <?php echo esc_html( YP_Bicim::tarih( YP_Cekirdek::bugun() ) ); ?></span>
				</div>
			</header>
			<?php if ( ! $gruplar ) : ?>
				<p>Seçilen kurslarda kapanmamış bakiye bulunmuyor.</p>
			<?php endif; ?>
			<?php foreach ( $gruplar as $ref_id => $liste ) : ?>
				<?php $ara_toplam = 0.0; ?>
				<h2 class="kurs-basligi"><?php echo esc_html( isset( $kurslar[ $ref_id ] ) ? $kurslar[ $ref_id ] : 'Kurs #' . (int) $ref_id ); ?></h2>
				<table class="tablo">
					<thead><tr><th>Aday No</th><th>Adı Soyadı</th><th>Kayıt</th><th class="sag">Borç</th><th class="sag">Ödenen</th><th class="sag">Bakiye</th></tr></thead>
					<tbody>
					<?php foreach ( $liste as $s ) : ?>
						<?php $ara_toplam += (float) $s->kalan; ?>
						<tr>
							<td><?php echo (int) $s->aday_no; ?></td>
							<td><?php echo esc_html( $s->adi . ' ' . $s->soyadi ); ?></td>
							<td><?php echo esc_html( YP_Bicim::tarih( $s->kayit_tarihi ) ); ?></td>
							<?php echo self::tutar_hucre( $s->borc ); // phpcs:ignore ?>
							<?php echo self::tutar_hucre( $s->odenen ); // phpcs:ignore ?>
							<?php echo self::tutar_hucre( $s->kalan ); // phpcs:ignore ?>
						</tr>
					<?php endforeach; ?>
					</tbody>
					<tfoot><tr><th colspan="5">Ara toplam (<?php echo count( $liste ); ?> aday)</th><?php echo self::tutar_hucre( $ara_toplam ); // phpcs:ignore ?></tr></tfoot>
				</table>
			<?php endforeach; ?>
			<?php if ( $gruplar ) : ?>
				<p class="yaziyla">Genel toplam: <strong><?php echo esc_html( YP_Bicim::tl( $toplam ) ); ?></strong> — yalnız <?php echo esc_html( YP_Bicim::yaziyla( $toplam ) ); ?></p>
			<?php endif; ?>
			<footer><?php echo esc_html( $ayar['makbuz_notu'] ); ?></footer>
		</article>
		<?php
		self::sayfa_bitir();
	}

	// KURAL: Adresteki kimlik listesi yalnızca pozitif tam sayılardan oluşur; tekrarlar atılır.
	private static function id_listesi( $ham ) {
		$idler = array();
		foreach ( explode( ',', (string) $ham ) as $parca ) {
			$n = (int) trim( $parca );
			if ( $n > 0 && ! in_array( $n, $idler, true ) ) {
				$idler[] = $n;
			}
		}
		return array_slice( $idler, 0, self::EN_COK_SATIR );
	}

	private static function makbuz( array $idler ) {
		global $wpdb;
		$h        = YP_Cekirdek::tablo( 'hareketler' );
		$satirlar = array();
		if ( $idler ) {
			$yer      = implode( ',', array_fill( 0, count( $idler ), '%d' ) );
			$satirlar = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$h} WHERE id IN ({$yer}) AND silindi = 0 AND durum = 'ODENDI' AND kayit_turu = 'ADAY' ORDER BY vade_tarihi, id", $idler ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		// KURAL: Makbuzdaki satırların hepsi aynı adaya ait olmalıdır — farklı adayların tahsilatı tek makbuzda birleşmez.
		$adaylar = array_unique( array_map( function ( $s ) {
			return (int) $s->aday_id;
		}, $satirlar ) );
		if ( ! $satirlar || 1 !== count( $adaylar ) ) {
			self::hata_sayfasi( 'Makbuz bulunamadı (satır silinmiş veya tahsilatı geri alınmış olabilir).' );
			return;
		}
		$ilk      = $satirlar[0];
		$aday     = $ilk->aday_id ? YP_Veri::aday( $ilk->aday_id ) : null;
		$hesap    = $ilk->hesap_id ? YP_Veri::hesap( $ilk->hesap_id ) : null;
		$odeme    = YP_Veri::odeme_turleri();
		$ayar     = YP_Cekirdek::ayarlar();
		$toplam   = array_sum( array_map( function ( $s ) {
			return (float) $s->tutar;
		}, $satirlar ) );
		$otomatik = YP_Guvenlik::tamsayi( 'otomatik', 'get' );

		self::sayfa_basla( 'Tahsilat Makbuzu', '', 'yazdir' );
		?>
		<div class="yazdir-dugmeler">
			<button type="button" class="dugme ana" data-yazdir <?php echo $otomatik ? 'data-otomatik' : ''; ?>>Yazdır</button>
			<?php if ( $aday ) : ?><a class="dugme" href="<?php echo esc_url( YP_Cekirdek::panel_url( array( 'ekran' => 'aday', 'id' => (int) $aday->id, 'sekme' => 'odeme' ) ) ); ?>">Aday kartına dön</a><?php endif; ?>
		</div>
		<article class="makbuz">
			<header>
				<div>
					<h1><?php echo esc_html( $ayar['kurum_adi'] ); ?></h1>
					<p><?php echo esc_html( $ayar['kurum_adresi'] ); ?><?php echo $ayar['kurum_telefonu'] ? '<br>Tel: ' . esc_html( $ayar['kurum_telefonu'] ) : ''; ?></p>
				</div>
				<div class="makbuz-baslik">
					<strong>TAHSİLAT MAKBUZU</strong>
					<span>Tarih: <?php echo esc_html( YP_Bicim::tarih( $ilk->odeme_tarihi ) ); ?></span>
				</div>
			</header>
			<dl class="makbuz-bilgi">
				<?php if ( $aday ) : ?>
					<div><dt>Sayın</dt><dd><?php echo esc_html( YP_Veri::aday_adi( $aday ) ); ?> (Aday no: <?php echo (int) $aday->aday_no; ?>)</dd></div>
					<?php if ( $aday->tc_no ) : ?><div><dt>TC kimlik no</dt><dd><?php echo esc_html( $aday->tc_no ); ?></dd></div><?php endif; ?>
				<?php endif; ?>
				<div><dt>Ödeme şekli</dt><dd><?php echo esc_html( ( isset( $odeme[ $ilk->odeme_turu ] ) ? $odeme[ $ilk->odeme_turu ] : '' ) . ( $hesap ? ' — ' . $hesap->ad : '' ) ); ?></dd></div>
			</dl>
			<table class="tablo">
				<thead><tr><th>Açıklama</th><th>Vade</th><th class="sag">Tutar</th></tr></thead>
				<tbody>
				<?php foreach ( $satirlar as $s ) : ?>
					<tr><td><?php echo esc_html( $s->borc_tipi . ( $s->aciklama ? ' — ' . $s->aciklama : '' ) ); ?></td><td><?php echo esc_html( YP_Bicim::tarih( $s->vade_tarihi ) ); ?></td><?php echo self::tutar_hucre( $s->tutar ); // phpcs:ignore ?></tr>
				<?php endforeach; ?>
				</tbody>
				<tfoot><tr><th colspan="2">Toplam</th><?php echo self::tutar_hucre( $toplam ); // phpcs:ignore ?></tr></tfoot>
			</table>
			<p class="yaziyla">Yalnız: <strong><?php echo esc_html( YP_Bicim::yaziyla( $toplam ) ); ?></strong></p>
			<?php if ( $aday ) : ?>
				<?php $ozet = YP_Hesap::aday_ozeti( $aday->id ); ?>
				<p class="soluk">Kalan borç: <?php echo esc_html( YP_Bicim::tl( $ozet['kalan'] ) ); ?></p>
			<?php endif; ?>
			<div class="imzalar">
				<div>Tahsil eden<br><strong><?php echo esc_html( YP_Cekirdek::kullanici_adi( $ilk->tahsil_eden ) ); ?></strong></div>
				<div>Ödeyen<br><strong><?php echo esc_html( $aday ? YP_Veri::aday_adi( $aday ) : '' ); ?></strong></div>
			</div>
			<footer><?php echo esc_html( $ayar['makbuz_notu'] ); ?></footer>
		</article>
		<?php
		self::sayfa_bitir();
	}
}
