<?php
defined( 'ABSPATH' ) || exit;

/**
 * Kasa: gün odaklı para takibi. Düzen masaüstü kasa ekranıyla aynıdır:
 * üstte gün gezinmesi, ortada işlem listesi, sağda "dünden devir / bugün / devreden" özeti.
 */
final class YP_Ekran_Kasa extends YP_Ekran {

	public static function islemler() {
		return array(
			'kasa_gider'    => 'gider_ekle',
			'kasa_gelir'    => 'gelir_ekle',
			'kasa_kayit'    => 'kayit_ekle',
			'kasa_duzenle'  => 'kayit_duzenle',
			'kasa_transfer' => 'transfer',
			'kasa_sil'      => 'sil',
		);
	}

	private static function sekmeler() {
		return array(
			'tumu'  => array( 'Tüm İşlemler', 'sekme-tumu' ),
			'gelir' => array( 'Gelir', 'sekme-gelir' ),
			'gider' => array( 'Gider', 'sekme-gider' ),
		);
	}

	public static function donem() {
		$bas = YP_Guvenlik::tarih( 'bas', 'get' );
		$bit = YP_Guvenlik::tarih( 'bit', 'get' );
		// KURAL: Tarih seçilmezse bugün gösterilir — kasa günlük çalışır (masaüstündeki gibi).
		$bas = $bas ? $bas : YP_Cekirdek::bugun();
		$bit = $bit ? $bit : $bas;
		if ( $bit < $bas ) {
			$gecici = $bas;
			$bas    = $bit;
			$bit    = $gecici;
		}
		return array( $bas, $bit );
	}

	public static function tur_etiketi( $s ) {
		if ( 'TRANSFER' === $s->kayit_turu ) {
			return 'Transfer';
		}
		if ( 'IADE' === $s->durum ) {
			return 'İade';
		}
		if ( 'KENDISI' === $s->odeme_turu ) {
			return 'Aday adına';
		}
		$e = array( 'ADAY' => 'Tahsilat', 'GELIR' => 'Gelir', 'GIDER' => 'Gider' );
		return isset( $e[ $s->kayit_turu ] ) ? $e[ $s->kayit_turu ] : $s->kayit_turu;
	}

	public static function aciklama_metni( $s, array $hesaplar ) {
		$p = array();
		if ( 'TRANSFER' === $s->kayit_turu ) {
			$p[] = ( isset( $hesaplar[ (int) $s->hesap_id ] ) ? $hesaplar[ (int) $s->hesap_id ] : '?' ) . ' → ' . ( isset( $hesaplar[ (int) $s->hedef_hesap_id ] ) ? $hesaplar[ (int) $s->hedef_hesap_id ] : '?' );
		}
		if ( ! empty( $s->aday_no ) ) {
			$p[] = '#' . $s->aday_no . ' ' . $s->adi . ' ' . $s->soyadi;
		}
		if ( ! empty( $s->referans_adi ) ) {
			$p[] = 'Referans: ' . ( $s->referans_unvani ? $s->referans_unvani : $s->referans_adi );
		}
		if ( $s->aciklama ) {
			$p[] = $s->aciklama;
		}
		return implode( ' · ', $p );
	}

	public static function goster() {
		list( $bas, $bit ) = self::donem();
		$tek_gun  = $bas === $bit;
		$sekme    = YP_Guvenlik::secim( 'sekme', array_keys( self::sekmeler() ), 'tumu', 'get' );
		$hesap_id = YP_Guvenlik::tamsayi( 'hesap', 'get' );
		$hesaplar = YP_Veri::hesap_adlari();
		if ( $hesap_id && ! isset( $hesaplar[ $hesap_id ] ) ) {
			$hesap_id = 0;
		}
		$defter = YP_Hesap::defter( $bas, $bit, $hesap_id, $sekme );
		$ozet   = YP_Hesap::tur_ozeti( $bas, $bit );
		$odeme  = YP_Veri::odeme_turleri();

		$temel = array( 'ekran' => 'kasa', 'bas' => YP_Bicim::tarih( $bas ), 'bit' => YP_Bicim::tarih( $bit ) );
		if ( $hesap_id ) {
			$temel['hesap'] = $hesap_id;
		}

		$excel_args = array_merge( $temel, array( 'excel' => 'kasa', 'ekran' => 'raporlar', 'sekme' => $sekme ) );
		$onceki     = array_merge( $temel, array( 'bas' => YP_Bicim::tarih( YP_Bicim::gun_ekle( $bas, -1 ) ), 'bit' => YP_Bicim::tarih( YP_Bicim::gun_ekle( $bas, -1 ) ) ) );
		$sonraki    = array_merge( $temel, array( 'bas' => YP_Bicim::tarih( YP_Bicim::gun_ekle( $bit, 1 ) ), 'bit' => YP_Bicim::tarih( YP_Bicim::gun_ekle( $bit, 1 ) ) ) );

		// KURAL: Kasa ekranında açılır şerit, araç çubuğu ve tarih/hesap paneli yoktur;
		// bütün işlemler en üstteki tek ince şeritten yapılır.
		self::sayfa_basla( 'Kasa', 'kasa', 'uygulama ust-yok' );
		echo '<div class="uyg">';
		self::ust_serit( $onceki, $sonraki, $bas, $bit, $tek_gun, $hesap_id, $hesaplar );
		?>
		<div class="kasa-govde">
			<section class="kutu kasa-liste">
				<div class="tablo-kap">
				<table class="tablo yogun">
					<thead><tr>
						<th>İşlem Tipi</th><th>Tarih</th><th>İşlem Türü</th><th class="sag">Tutar</th><th class="yazdirma-gizle">Makbuz</th>
						<th>Hesap</th><th>Açıklama</th><th>Durumu</th><th>Kullanıcı</th><th class="yazdirma-gizle"></th>
					</tr></thead>
					<tbody>
					<?php
					if ( ! $defter['satirlar'] ) {
						self::bos_liste( 'Bu tarihte hareket yok.', 10 );
					}
					$top_giris = 0.0;
					$top_cikis = 0.0;
					foreach ( $defter['satirlar'] as $s ) :
						$top_giris += $s->giris;
						$top_cikis += $s->cikis;
						$kendisi    = 'KENDISI' === $s->odeme_turu;
						?>
						<?php
						// KURAL: Satıra tıklayınca seçilir; üst şeritteki "Düzenle" ve "Sil" seçili satırda çalışır.
						// Düzenleme yalnızca gelir/gider satırlarında açılır; aday tahsilatı ve transfer buradan değişmez.
						$secilebilir = 'ADAY' !== $s->kayit_turu;
						$duzeltilir  = in_array( $s->kayit_turu, array( 'GELIR', 'GIDER' ), true ) && 'IADE' !== $s->durum;
						$doldur      = ! $duzeltilir ? '' : wp_json_encode( array(
							'hareket_id'     => (int) $s->id,
							'kasa_turu'      => 'GIDER' === $s->kayit_turu ? 'gider' : 'gelir',
							'hesap_id'       => (string) (int) $s->hesap_id,
							'tutar'          => YP_Bicim::tl( $s->tutar, false ),
							'odeme_tarihi'   => YP_Bicim::tarih( $s->odeme_tarihi ),
							'gelir_kalem_id' => 'GELIR' === $s->kayit_turu ? (string) (int) $s->kalem_id : '',
							'gider_kalem_id' => 'GIDER' === $s->kayit_turu ? (string) (int) $s->kalem_id : '',
							'referans_id'    => (string) (int) $s->referans_id,
							'aciklama'       => (string) $s->aciklama,
						) );
						?>
						<tr class="<?php echo $kendisi ? 'soluk-satir' : ''; ?><?php echo $secilebilir ? ' secilebilir' : ''; ?>"<?php echo $secilebilir ? ' data-kasa-satir="' . (int) $s->id . '"' : ''; ?><?php echo $duzeltilir ? ' data-doldur="' . esc_attr( $doldur ) . '"' : ''; ?>>
							<td><?php echo esc_html( self::tur_etiketi( $s ) ); ?></td>
							<td><?php echo esc_html( YP_Bicim::tarih( $s->odeme_tarihi ) ); ?></td>
							<td><?php echo esc_html( $s->kalem_adi ? $s->kalem_adi : $s->borc_tipi ); ?></td>
							<?php
							// KURAL: Transfer "tüm hesaplar" görünümünde toplamı değiştirmez; tutarı nötr renkte ⇄ ile gösterilir.
							$devir = 'TRANSFER' === $s->kayit_turu && $s->giris <= 0 && $s->cikis <= 0;
							?>
							<td class="sag <?php echo $s->cikis > 0 ? 'kirmizi' : ( $devir ? 'soluk' : '' ); ?>">
								<?php echo esc_html( $devir ? '⇄ ' . YP_Bicim::tl( $s->tutar, false ) : ( $s->cikis > 0 ? '−' : '' ) . YP_Bicim::tl( $s->cikis > 0 ? $s->cikis : $s->giris, false ) ); ?>
							</td>
							<?php // KURAL: Aday tahsilatının makbuzu satır kimliğiyle yazdırılır; kasaya girmeyen ödemede makbuz yoktur. ?>
							<td class="yazdirma-gizle"><?php echo ( 'ADAY' === $s->kayit_turu && 'ODENDI' === $s->durum && ! $kendisi && $s->aday_id ) ? '<a href="' . esc_url( self::makbuz_url( array( (int) $s->id ) ) ) . '">Yazdır</a>' : ''; ?></td>
							<td><?php echo esc_html( isset( $hesaplar[ (int) $s->hesap_id ] ) ? $hesaplar[ (int) $s->hesap_id ] : '—' ); ?></td>
							<td class="aciklama-sutun">
								<?php
								$metin = self::aciklama_metni( $s, $hesaplar );
								echo $s->aday_id ? self::aday_linki( $s->aday_id, $metin, 'odeme' ) : esc_html( $metin ); // phpcs:ignore
								?>
							</td>
							<td><?php echo $kendisi ? self::rozet( 'Kasaya girmez', 'gri' ) : self::odeme_rozeti( $s->durum ); // phpcs:ignore ?></td>
							<td class="yazi-kucuk"><?php echo esc_html( YP_Cekirdek::kullanici_adi( $s->tahsil_eden ? $s->tahsil_eden : $s->olusturan ) ); ?></td>
							<td class="yazdirma-gizle">
								<?php
								if ( 'ADAY' !== $s->kayit_turu ) {
									self::kucuk_form( 'kasa_sil', array( 'hareket_id' => $s->id ), 'Sil', 'dugme kucuk kirmizi', 'Kayıt silinecek ve hesap bakiyesi değişecek. Emin misiniz?' );
								}
								?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				</div>
				<?php // KURAL: Liste süzgeçleri toplam şeridinin hemen üstündedir; sade yazılardır, kutu çizilmez. ?>
				<nav class="kasa-sekmeler">
					<?php
					foreach ( self::sekmeler() as $k => $s ) {
						$url = YP_Cekirdek::panel_url( array_merge( $temel, array( 'sekme' => $k ) ) );
						echo '<a class="' . esc_attr( $s[1] ) . ( $k === $sekme ? ' aktif' : '' ) . '" href="' . esc_url( $url ) . '">' . esc_html( $s[0] ) . '</a>';
					}
					?>
				</nav>
				<?php // KURAL: Gün toplamı tablonun altındaki ince şerittedir; net tutar listenin en sağına yaslanır. ?>
				<div class="kasa-toplam">
					<span class="kt-ad">Toplam</span>
					<span class="yesil-yazi"><?php echo esc_html( YP_Bicim::tl( $top_giris ) ); ?></span>
					<span class="kirmizi">−<?php echo esc_html( YP_Bicim::tl( $top_cikis ) ); ?></span>
					<strong class="kt-net">Net: <?php echo esc_html( YP_Bicim::tl( $top_giris - $top_cikis ) ); ?></strong>
				</div>
			</section>

			<aside class="kasa-ozet">
				<nav class="ozet-sekmeler" role="tablist">
					<button type="button" class="aktif" data-ozet="kasa">Kasa</button>
					<button type="button" data-ozet="gelir">Gelir</button>
					<button type="button" data-ozet="gider">Gider</button>
				</nav>

				<div class="ozet-blok" data-ozet-icerik="kasa">
					<?php
					self::ozet_kutusu( 'Dünden devir', array(
						'Devir Nakit'      => $ozet['NAKIT']['devreden'],
						'Devir Bankalar'   => $ozet['BANKA']['devreden'],
						'Devir Posta/PTT'  => $ozet['POSTA']['devreden'],
					), 'Devreden toplam', $ozet['NAKIT']['devreden'] + $ozet['BANKA']['devreden'] + $ozet['POSTA']['devreden'] );

					self::ozet_kutusu( $tek_gun ? 'Bugün' : 'Seçilen aralık', array(
						'Nakit (+)'      => $ozet['NAKIT']['giren'],
						'Bankalar (+)'   => $ozet['BANKA']['giren'],
						'Posta/PTT (+)'  => $ozet['POSTA']['giren'],
						'Çıkışlar (−)'   => -( $ozet['NAKIT']['cikan'] + $ozet['BANKA']['cikan'] + $ozet['POSTA']['cikan'] ),
					), 'Gün net', ( $ozet['NAKIT']['giren'] + $ozet['BANKA']['giren'] + $ozet['POSTA']['giren'] ) - ( $ozet['NAKIT']['cikan'] + $ozet['BANKA']['cikan'] + $ozet['POSTA']['cikan'] ) );

					?>
					<div class="ozet-kutu">
						<h3>KASA</h3>
						<?php if ( $hesap_id ) : ?>
							<div class="ozet-satir"><a href="<?php echo esc_url( YP_Cekirdek::panel_url( array( 'ekran' => 'kasa', 'bas' => YP_Bicim::tarih( $bas ), 'bit' => YP_Bicim::tarih( $bit ) ) ) ); ?>">← Tüm hesaplar</a></div>
						<?php endif; ?>
						<?php foreach ( YP_Hesap::hesap_bakiyeleri() as $hb ) : ?>
							<div class="ozet-satir">
								<span><a href="<?php echo esc_url( YP_Cekirdek::panel_url( array_merge( $temel, array( 'hesap' => (int) $hb['hesap']->id ) ) ) ); ?>"><?php echo esc_html( $hb['hesap']->ad ); ?></a></span>
								<b><?php echo esc_html( YP_Bicim::tl( $hb['bakiye'], false ) ); ?></b>
							</div>
						<?php endforeach; ?>
					</div>
				</div>

				<div class="ozet-blok" data-ozet-icerik="gelir" hidden>
					<div class="ozet-kutu">
						<h3>Gelirler (ödeme türüne göre)</h3>
						<?php
						$gd = YP_Hesap::gelir_dagilimi( $bas, $bit );
						if ( ! $gd ) {
							echo '<p class="soluk yazi-kucuk">Bu tarihte gelir yok.</p>';
						}
						foreach ( $gd as $g ) {
							echo '<div class="ozet-satir"><span>' . esc_html( isset( $odeme[ $g->odeme_turu ] ) ? $odeme[ $g->odeme_turu ] : $g->odeme_turu ) . ' <small class="soluk">' . (int) $g->adet . '</small></span><b>' . esc_html( YP_Bicim::tl( $g->toplam, false ) ) . '</b></div>';
						}
						?>
					</div>
				</div>

				<div class="ozet-blok" data-ozet-icerik="gider" hidden>
					<div class="ozet-kutu">
						<h3>Giderler (kaleme göre)</h3>
						<?php
						$gd = YP_Hesap::gider_dagilimi( $bas, $bit );
						if ( ! $gd ) {
							echo '<p class="soluk yazi-kucuk">Bu tarihte gider yok.</p>';
						}
						foreach ( $gd as $g ) {
							echo '<div class="ozet-satir"><span>' . esc_html( $g->kalem ) . ' <small class="soluk">' . (int) $g->adet . '</small></span><b class="kirmizi">' . esc_html( YP_Bicim::tl( $g->toplam, false ) ) . '</b></div>';
						}
						?>
					</div>
				</div>
			</aside>
		</div>
		<?php
		echo '</div>';
		self::diyaloglar( $bas, $bit, $hesap_id, $excel_args );
		self::sayfa_bitir();
	}

	/**
	 * Kasanın tek üst şeridi: solda ana menü, ortada işlem düğmeleri, sağda gösterilen gün.
	 * KURAL: Şerit incedir ve düğmeler küçüktür; ekranın kalanı listeye ve özete kalır.
	 */
	private static function ust_serit( array $onceki, array $sonraki, $bas, $bit, $tek_gun, $hesap_id, array $hesaplar ) {
		echo '<div class="kasa-serit">';
		echo '<a class="ks-menu" href="' . esc_url( YP_Cekirdek::panel_url() ) . '" title="Ana menü">☰</a>';
		echo '<strong class="ks-ad">KASA</strong>';
		echo '<span class="ks-dugmeler">';
		self::serit_dugme( 'Yeni Gelir / Gider', 'ekle', array( 'dialog' => 'd-kasa-kayit' ) );
		self::serit_dugme( 'Para Transferi', 'transfer', array( 'dialog' => 'd-transfer' ) );
		// KURAL: Kaydet düğmesi açık pencerenin formunu gönderir; pencere yokken çalışmaz (pasiftir).
		self::serit_dugme( 'Kaydet', 'kaydet', array( 'veri' => 'data-kasa-kaydet disabled', 'sinif' => 'ks-yesil' ) );
		// KURAL: Düzenle, listede seçili gelir/gider satırını silmeden düzeltir (tür, hesap, tutar, tarih, kalem, açıklama).
		self::serit_dugme( 'Düzenle', 'duzenle', array( 'veri' => 'data-kasa-duzenle' ) );
		self::serit_dugme( 'Sil', 'cop', array( 'veri' => 'data-kasa-sil', 'sinif' => 'ks-kirmizi' ) );
		self::serit_dugme( 'Tarih Aralığı', 'takvim', array( 'dialog' => 'd-tarih' ) );
		self::serit_dugme( 'Önceki Gün', 'onceki', array( 'adres' => $onceki ) );
		self::serit_dugme( 'Sonraki Gün', 'sonraki', array( 'adres' => $sonraki ) );
		self::serit_dugme( 'Kasa Raporları', 'rapor', array( 'dialog' => 'd-kasa-rapor' ) );
		echo '</span>';
		echo '<span class="ks-bilgi">' . esc_html( $tek_gun ? YP_Bicim::tarih_uzun( $bas ) : YP_Bicim::tarih( $bas ) . ' – ' . YP_Bicim::tarih( $bit ) );
		echo ' · ' . esc_html( $hesap_id && isset( $hesaplar[ $hesap_id ] ) ? $hesaplar[ $hesap_id ] : 'Tüm hesaplar' ) . '</span>';
		echo '</div>';
		// KURAL: Seçili satırın kimliği bu gizli formla gönderilir — "Sil" düğmesi ayrı bir satır düğmesine ihtiyaç duymaz.
		self::form_ac( 'kasa_sil', 'gizli-form', false );
		echo '<input type="hidden" name="hareket_id" value="0" data-kasa-sil-id></form>';
	}

	// KURAL: Şerit düğmeleri tek biçimdedir: simge + kısa yazı; bağlantı ya da düğme olarak çizilir.
	private static function serit_dugme( $etiket, $ikon, array $ek = array() ) {
		$ic    = '<span class="ks-ikon">' . self::ikon( $ikon, 20 ) . '</span><span>' . esc_html( $etiket ) . '</span>';
		$sinif = 'ks-dugme' . ( isset( $ek['sinif'] ) ? ' ' . $ek['sinif'] : '' );
		if ( isset( $ek['adres'] ) ) {
			echo '<a class="' . esc_attr( $sinif ) . '" href="' . esc_url( YP_Cekirdek::panel_url( $ek['adres'] ) ) . '">' . $ic . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput
			return;
		}
		$oz = isset( $ek['dialog'] ) ? ' data-dialog="' . esc_attr( $ek['dialog'] ) . '"' : '';
		$oz .= isset( $ek['veri'] ) ? ' ' . $ek['veri'] : '';
		echo '<button type="button" class="' . esc_attr( $sinif ) . '"' . $oz . '>' . $ic . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	private static function ozet_kutusu( $baslik, array $satirlar, $toplam_adi, $toplam ) {
		echo '<div class="ozet-kutu"><h3>' . esc_html( $baslik ) . '</h3>';
		foreach ( $satirlar as $ad => $tutar ) {
			echo '<div class="ozet-satir"><span>' . esc_html( $ad ) . '</span><b class="' . ( $tutar < 0 ? 'kirmizi' : '' ) . '">' . esc_html( YP_Bicim::tl( $tutar, false ) ) . '</b></div>';
		}
		echo '<div class="ozet-satir ozet-toplam"><span>' . esc_html( $toplam_adi ) . '</span><b>' . esc_html( YP_Bicim::tl( $toplam, false ) ) . '</b></div></div>';
	}

	private static function diyaloglar( $bas, $bit, $hesap_id, array $excel_args ) {
		$hesaplar = YP_Veri::hesap_adlari( true );
		$nakit    = YP_Veri::varsayilan_hesap( 'NAKIT' );

		// KURAL: Gelir ve gider tek pencerede girilir; üstteki "Tür" seçimi gerekli alanları açıp kapatır.
		echo '<dialog id="d-kasa-kayit" class="pencere"><div class="pencere-baslik"><h2>Yeni gelir / gider</h2><button type="button" class="kapat" data-kapat aria-label="Kapat">×</button></div>';
		self::form_ac( 'kasa_kayit' );
		echo '<div class="izgara">';
		self::secim( 'kasa_turu', 'Tür', array( 'gelir' => 'GELİR (para girişi)', 'gider' => 'GİDER (para çıkışı)' ), 'gelir', array( 'bos' => null, 'veri' => array( 'kasa-turu' => 1 ) ) );
		self::secim( 'hesap_id', 'Hesap', $hesaplar, $nakit, array( 'zorunlu' => true ) );
		self::tutar_alani( 'tutar', 'Tutar', '', array( 'zorunlu' => true ) );
		self::tarih_alani( 'odeme_tarihi', 'Tarih', YP_Cekirdek::bugun() );
		self::secim( 'gelir_kalem_id', 'Gelir kalemi', YP_Veri::tanim_adlari( 'gelir_kalemi', true ), '', array( 'bos' => 'Diğer', 'sinif' => 'tur-gelir' ) );
		self::secim( 'gider_kalem_id', 'Gider kalemi', YP_Veri::tanim_adlari( 'gider_kalemi', true ), '', array( 'sinif' => 'tur-gider' ) );
		self::secim( 'referans_id', 'Referansa ödemeyse', YP_Veri::referans_secenekleri(), '', array( 'bos' => 'Değil', 'sinif' => 'tur-gider' ) );
		self::alan( 'aciklama', 'Açıklama', '', array( 'tur' => 'textarea', 'sinif' => 'genis' ) );
		echo '</div><p class="soluk yazi-kucuk">Aday tahsilatları buraya girilmez; onlar adayın Ödeme Bilgileri ekranından alınır.</p>';
		echo '<div class="form-dugmeler"><button type="submit" class="dugme ana">Kaydet</button><button type="button" class="dugme" data-kapat>Vazgeç</button></div></form></dialog>';

		// KURAL: Hatalı girilen gelir/gider satırı silinmeden düzeltilir; alanlar seçili satırdan doldurulur.
		echo '<dialog id="d-kasa-duzenle" class="pencere"><div class="pencere-baslik"><h2>Kaydı düzelt</h2><button type="button" class="kapat" data-kapat aria-label="Kapat">×</button></div>';
		self::form_ac( 'kasa_duzenle' );
		self::gizli( 'hareket_id', 0 );
		echo '<div class="izgara">';
		self::secim( 'kasa_turu', 'İşlem tipi', array( 'gelir' => 'GELİR (para girişi)', 'gider' => 'GİDER (para çıkışı)' ), 'gelir', array( 'bos' => null, 'veri' => array( 'kasa-turu' => 1 ) ) );
		self::secim( 'hesap_id', 'Hesap', $hesaplar, $nakit, array( 'zorunlu' => true ) );
		self::tutar_alani( 'tutar', 'Tutar', '', array( 'zorunlu' => true ) );
		self::tarih_alani( 'odeme_tarihi', 'Tarih', YP_Cekirdek::bugun() );
		self::secim( 'gelir_kalem_id', 'Gelir kalemi', YP_Veri::tanim_adlari( 'gelir_kalemi', true ), '', array( 'bos' => 'Diğer', 'sinif' => 'tur-gelir' ) );
		self::secim( 'gider_kalem_id', 'Gider kalemi', YP_Veri::tanim_adlari( 'gider_kalemi', true ), '', array( 'sinif' => 'tur-gider' ) );
		self::secim( 'referans_id', 'Referansa ödemeyse', YP_Veri::referans_secenekleri(), '', array( 'bos' => 'Değil', 'sinif' => 'tur-gider' ) );
		self::alan( 'aciklama', 'Açıklama', '', array( 'tur' => 'textarea', 'sinif' => 'genis' ) );
		echo '</div><div class="form-dugmeler"><button type="submit" class="dugme ana">Değişikliği kaydet</button><button type="button" class="dugme" data-kapat>Vazgeç</button></div></form></dialog>';

		// KURAL: Tarih aralığı tarayıcının kendi mini takviminden seçilir; elle de yazılabilir.
		echo '<dialog id="d-tarih" class="pencere dar"><div class="pencere-baslik"><h2>Tarih aralığı</h2><button type="button" class="kapat" data-kapat aria-label="Kapat">×</button></div>';
		echo '<form method="get" action="' . esc_url( YP_Cekirdek::panel_url() ) . '"><input type="hidden" name="ekran" value="kasa">';
		echo '<div class="izgara">';
		echo '<label class="alan"><span>Başlangıç</span><input type="date" name="bas" value="' . esc_attr( $bas ) . '" required></label>';
		echo '<label class="alan"><span>Bitiş</span><input type="date" name="bit" value="' . esc_attr( $bit ) . '" required></label>';
		self::secim( 'hesap', 'Hesap', YP_Veri::hesap_adlari(), $hesap_id, array( 'bos' => 'Tüm hesaplar' ) );
		echo '</div><div class="form-dugmeler"><button type="submit" class="dugme ana">Göster</button>';
		echo '<a class="dugme" href="' . esc_url( YP_Cekirdek::panel_url( array( 'ekran' => 'kasa' ) ) ) . '">Bugün</a>';
		echo '<button type="button" class="dugme" data-kapat>Vazgeç</button></div></form></dialog>';

		self::rapor_diyalogu( $bas, $bit, $excel_args );

		echo '<dialog id="d-transfer" class="pencere"><div class="pencere-baslik"><h2>Hesaplar arası transfer</h2><button type="button" class="kapat" data-kapat aria-label="Kapat">×</button></div>';
		self::form_ac( 'kasa_transfer' );
		echo '<p class="soluk">Örnek: kasadaki nakdi bankaya yatırmak. Toplam para değişmez.</p><div class="izgara">';
		self::secim( 'hesap_id', 'Nereden', $hesaplar, $nakit, array( 'zorunlu' => true ) );
		self::secim( 'hedef_hesap_id', 'Nereye', $hesaplar, YP_Veri::varsayilan_hesap( 'HAVALE' ), array( 'zorunlu' => true ) );
		self::tutar_alani( 'tutar', 'Tutar', '', array( 'zorunlu' => true ) );
		self::tarih_alani( 'odeme_tarihi', 'Tarih', YP_Cekirdek::bugun() );
		self::alan( 'aciklama', 'Açıklama', '', array( 'tur' => 'textarea', 'sinif' => 'genis' ) );
		echo '</div><div class="form-dugmeler"><button type="submit" class="dugme ana">Kaydet</button><button type="button" class="dugme" data-kapat>Vazgeç</button></div></form></dialog>';
	}

	/**
	 * Bize borcu olan sürücü kurslarını (referansları) ve borç toplamlarını verir.
	 * KURAL: Bir kursun borcu, o kursa bağlı adayların kapanmamış bakiyelerinin toplamıdır.
	 */
	public static function borclu_kurslar( $bas = '', $bit = '' ) {
		// KURAL: Bu toplam bütün adayları tarar; kasa ekranı her açılışında yeniden hesaplanmasın diye
		// veri değişene kadar (sürüm anahtarlı) önbellekte tutulur.
		return (array) YP_Cekirdek::onbellek( 'borclu_kurs_' . md5( $bas . '|' . $bit ), 10 * MINUTE_IN_SECONDS, function () use ( $bas, $bit ) {
			return self::borclu_kurslari_hesapla( $bas, $bit );
		} );
	}

	private static function borclu_kurslari_hesapla( $bas, $bit ) {
		global $wpdb;
		$a     = YP_Cekirdek::tablo( 'adaylar' );
		$r     = YP_Cekirdek::tablo( 'referanslar' );
		$where = array( 'a.silindi = 0', 'a.referans_id IS NOT NULL' );
		$args  = array();
		if ( $bas ) {
			$where[] = 'a.kayit_tarihi >= %s';
			$args[]  = $bas;
		}
		if ( $bit ) {
			$where[] = 'a.kayit_tarihi <= %s';
			$args[]  = $bit;
		}
		$sql = "SELECT r.id, r.unvan, r.ad_soyad, COUNT(a.id) AS aday_sayisi,
				SUM(COALESCE(b.borc, 0) - COALESCE(b.odenen, 0)) AS bakiye
			FROM {$a} a
			INNER JOIN {$r} r ON r.id = a.referans_id
			LEFT JOIN (" . YP_Hesap::bakiye_alt_sorgusu() . ') b ON b.aday_id = a.id
			WHERE ' . implode( ' AND ', $where ) . ' GROUP BY r.id, r.unvan, r.ad_soyad
			HAVING SUM(COALESCE(b.borc, 0) - COALESCE(b.odenen, 0)) > 0
			ORDER BY bakiye DESC';
		return $wpdb->get_results( $args ? $wpdb->prepare( $sql, $args ) : $sql ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/**
	 * "Kasa Raporları" penceresi: borçlu sürücü kurslarını seçip tarih aralığıyla borç listesi çıkarır.
	 * KURAL: Liste yeni sekmede yazdırılabilir sayfa olarak açılır — ilgili merkeze olduğu gibi iletilir.
	 */
	private static function rapor_diyalogu( $bas, $bit, array $excel_args ) {
		$kurslar = self::borclu_kurslar();
		echo '<dialog id="d-kasa-rapor" class="pencere"><div class="pencere-baslik"><h2>Kasa raporları</h2><button type="button" class="kapat" data-kapat aria-label="Kapat">×</button></div>';
		echo '<form method="get" action="' . esc_url( YP_Cekirdek::panel_url() ) . '" target="_blank">';
		echo '<input type="hidden" name="ekran" value="yazdir"><input type="hidden" name="tur" value="kurs_borc">';
		echo '<h3 class="rapor-baslik">Sürücü kursu borç bakiye listesi</h3>';
		echo '<p class="soluk yazi-kucuk">Listeye girecek kursları işaretleyin, isterseniz kayıt tarihi aralığı verin.</p>';
		echo '<div class="izgara">';
		echo '<label class="alan"><span>Kayıt tarihi (başlangıç)</span><input type="date" name="bas"></label>';
		echo '<label class="alan"><span>Kayıt tarihi (bitiş)</span><input type="date" name="bit"></label>';
		echo '</div>';
		echo '<div class="kurs-listesi">';
		if ( ! $kurslar ) {
			echo '<p class="soluk">Şu anda borcu görünen sürücü kursu yok.</p>';
		}
		foreach ( $kurslar as $k ) {
			$ad = $k->unvan ? $k->unvan : $k->ad_soyad;
			echo '<label class="onay"><input type="checkbox" name="ref[]" value="' . (int) $k->id . '"> <span>' . esc_html( $ad ) . '</span>';
			echo '<b>' . esc_html( YP_Bicim::tl( $k->bakiye, false ) ) . '</b></label>';
		}
		echo '</div>';
		echo '<div class="form-dugmeler"><button type="submit" class="dugme ana">Borç listesi hazırla</button>';
		echo '<a class="dugme" href="' . esc_url( YP_Cekirdek::panel_url( $excel_args ) ) . '">Kasa hareketleri (Excel)</a>';
		echo '<button type="button" class="dugme" data-yazdir>Ekranı yazdır</button>';
		echo '<button type="button" class="dugme" data-kapat>Kapat</button></div></form></dialog>';
	}

	// ---- İşlemler -------------------------------------------------------

	// KURAL: Tek pencereden gelen kayıt, seçilen türe göre mevcut gelir/gider işleyicisine yönlendirilir.
	public static function kayit_ekle() {
		$gider = 'gider' === YP_Guvenlik::secim( 'kasa_turu', array( 'gelir', 'gider' ), 'gelir' );
		$kalem = YP_Guvenlik::tamsayi( $gider ? 'gider_kalem_id' : 'gelir_kalem_id' );
		if ( $gider ) {
			self::gider_ekle( $kalem );
			return;
		}
		self::gelir_ekle( $kalem );
	}

	private static function ortak_oku() {
		$tutar = YP_Guvenlik::tutar( 'tutar' );
		$hesap = YP_Veri::hesap( YP_Guvenlik::tamsayi( 'hesap_id' ) );
		$tarih = YP_Guvenlik::tarih( 'odeme_tarihi' );
		if ( $tutar <= 0 ) {
			self::geri_don( 'Tutar sıfırdan büyük olmalı.', 'hata' );
		}
		if ( ! $hesap ) {
			self::geri_don( 'Hesap seçin.', 'hata' );
		}
		return array( $tutar, $hesap, $tarih ? $tarih : YP_Cekirdek::bugun() );
	}

	private static function odeme_turu_hesaptan( $hesap ) {
		$h = array( 'NAKIT' => 'NAKIT', 'BANKA' => 'HAVALE', 'POSTA' => 'PTT' );
		return isset( $h[ $hesap->tur ] ) ? $h[ $hesap->tur ] : 'NAKIT';
	}

	/**
	 * Gelir/gider satırını silmeden düzeltir: tür, hesap, tutar, tarih, kalem, referans ve açıklama.
	 * KURAL: Para yazan bir işlem olduğu için tek transaction içinde yapılır; satır önce kilitlenip
	 * hâlâ düzenlenebilir olduğu doğrulanır (aday tahsilatı, transfer ve iade buradan değiştirilmez).
	 */
	public static function kayit_duzenle() {
		$id = YP_Guvenlik::tamsayi( 'hareket_id' );
		$s  = YP_Veri::hareket( $id );
		if ( ! $s || $s->silindi || ! in_array( $s->kayit_turu, array( 'GELIR', 'GIDER' ), true ) || 'IADE' === $s->durum ) {
			self::geri_don( 'Bu kayıt buradan düzenlenemez. Aday tahsilatları adayın ödeme ekranından, transferler silinip yeniden girilerek düzeltilir.', 'hata' );
		}
		$tur   = YP_Guvenlik::secim( 'kasa_turu', array( 'gelir', 'gider' ), 'gelir' );
		$tutar = YP_Guvenlik::tutar( 'tutar' );
		$hesap = YP_Veri::hesap( YP_Guvenlik::tamsayi( 'hesap_id' ) );
		$tarih = YP_Guvenlik::tarih( 'odeme_tarihi' );
		if ( $tutar <= 0 ) {
			self::geri_don( 'Tutar sıfırdan büyük olmalı.', 'hata' );
		}
		if ( ! $hesap ) {
			self::geri_don( 'Hesap seçin.', 'hata' );
		}
		$kalem_turu = 'gider' === $tur ? 'gider_kalemi' : 'gelir_kalemi';
		$kalem      = YP_Veri::tanim( YP_Guvenlik::tamsayi( 'gider' === $tur ? 'gider_kalem_id' : 'gelir_kalem_id' ) );
		$kalem      = ( $kalem && $kalem_turu === $kalem->tur ) ? $kalem : null;
		if ( 'gider' === $tur && ! $kalem ) {
			self::geri_don( 'Gider kalemini seçin.', 'hata' );
		}
		$ref      = 'gider' === $tur ? YP_Veri::referans( YP_Guvenlik::tamsayi( 'referans_id' ) ) : null;
		$aciklama = YP_Guvenlik::uzun_metin( 'aciklama' );

		self::tek_islemde( function () use ( $s, $tur, $tutar, $hesap, $tarih, $kalem, $ref, $aciklama ) {
			$kilitli = YP_Veri::hareket_kilitli( $s->id );
			if ( ! $kilitli || $kilitli->silindi || ! in_array( $kilitli->kayit_turu, array( 'GELIR', 'GIDER' ), true ) ) {
				throw new YP_Islem_Engeli( 'Kayıt bu arada değişmiş; sayfayı yenileyip tekrar deneyin.' );
			}
			YP_Veri::hareket_guncelle( $s->id, array(
				'kayit_turu'   => 'gider' === $tur ? 'GIDER' : 'GELIR',
				'hesap_id'     => $hesap->id,
				'kalem_id'     => $kalem ? $kalem->id : null,
				'referans_id'  => $ref ? $ref->id : null,
				'borc_tipi'    => $kalem ? $kalem->ad : 'Diğer gelir',
				'tutar'        => $tutar,
				'odeme_turu'   => self::odeme_turu_hesaptan( $hesap ),
				'odeme_tarihi' => $tarih ? $tarih : $s->odeme_tarihi,
				'aciklama'     => $aciklama,
			) );
			YP_Cekirdek::log( 'kasa', $s->id, 'düzeltme', self::tur_etiketi( $s ) . ' düzeltildi: ' . YP_Bicim::tl( $s->tutar ) . ' → ' . YP_Bicim::tl( $tutar ) );
		} );
		self::geri_don( 'Kayıt düzeltildi.' );
	}

	// KURAL: Gider, seçilen hesaptan ÖDENDİ çıkış olarak yazılır; kalem zorunludur — "nereye ödendi" her zaman bilinir.
	public static function gider_ekle( $kalem_id = 0 ) {
		list( $tutar, $hesap, $tarih ) = self::ortak_oku();
		$kalem = YP_Veri::tanim( $kalem_id ? (int) $kalem_id : YP_Guvenlik::tamsayi( 'kalem_id' ) );
		if ( ! $kalem || 'gider_kalemi' !== $kalem->tur ) {
			self::geri_don( 'Gider kalemini seçin.', 'hata' );
		}
		$ref      = YP_Veri::referans( YP_Guvenlik::tamsayi( 'referans_id' ) );
		$aciklama = YP_Guvenlik::uzun_metin( 'aciklama' );
		// KURAL: Gider satırı ve günlük kayıtları birlikte yazılır ya da hiç yazılmaz.
		self::tek_islemde( function () use ( $hesap, $kalem, $ref, $tutar, $tarih, $aciklama ) {
			$id = YP_Veri::hareket_ekle( array(
				'kayit_turu'    => 'GIDER',
				'hesap_id'      => $hesap->id,
				'kalem_id'      => $kalem->id,
				'referans_id'   => $ref ? $ref->id : null,
				'borc_tipi'     => $kalem->ad,
				'tutar'         => $tutar,
				'durum'         => 'ODENDI',
				'odeme_turu'    => self::odeme_turu_hesaptan( $hesap ),
				'odeme_tarihi'  => $tarih,
				'aciklama'      => $aciklama,
				'tahsil_eden'   => YP_Cekirdek::kullanici_id(),
				'tahsil_zamani' => YP_Cekirdek::simdi(),
			) );
			YP_Cekirdek::log( 'kasa', $id, 'gider', $kalem->ad . ' ' . YP_Bicim::tl( $tutar ) . ' (' . $hesap->ad . ')' );
			if ( $ref ) {
				YP_Cekirdek::log( 'referans', $ref->id, 'ödeme', 'Referansa ödeme (kasadan): ' . YP_Bicim::tl( $tutar ) );
			}
		} );
		self::geri_don( 'Gider kaydedildi.' );
	}

	public static function gelir_ekle( $kalem_id = 0 ) {
		list( $tutar, $hesap, $tarih ) = self::ortak_oku();
		$kalem = YP_Veri::tanim( $kalem_id ? (int) $kalem_id : YP_Guvenlik::tamsayi( 'kalem_id' ) );
		$kalem = ( $kalem && 'gelir_kalemi' === $kalem->tur ) ? $kalem : null;
		$aciklama = YP_Guvenlik::uzun_metin( 'aciklama' );
		self::tek_islemde( function () use ( $hesap, $kalem, $tutar, $tarih, $aciklama ) {
			$id = YP_Veri::hareket_ekle( array(
				'kayit_turu'    => 'GELIR',
				'hesap_id'      => $hesap->id,
				'kalem_id'      => $kalem ? $kalem->id : null,
				'borc_tipi'     => $kalem ? $kalem->ad : 'Diğer gelir',
				'tutar'         => $tutar,
				'durum'         => 'ODENDI',
				'odeme_turu'    => self::odeme_turu_hesaptan( $hesap ),
				'odeme_tarihi'  => $tarih,
				'aciklama'      => $aciklama,
				'tahsil_eden'   => YP_Cekirdek::kullanici_id(),
				'tahsil_zamani' => YP_Cekirdek::simdi(),
			) );
			YP_Cekirdek::log( 'kasa', $id, 'gelir', ( $kalem ? $kalem->ad : 'Diğer gelir' ) . ' ' . YP_Bicim::tl( $tutar ) . ' (' . $hesap->ad . ')' );
		} );
		self::geri_don( 'Gelir kaydedildi.' );
	}

	// KURAL: Transferde kaynak ve hedef hesap farklı olmalıdır.
	public static function transfer() {
		list( $tutar, $hesap, $tarih ) = self::ortak_oku();
		$hedef = YP_Veri::hesap( YP_Guvenlik::tamsayi( 'hedef_hesap_id' ) );
		if ( ! $hedef || (int) $hedef->id === (int) $hesap->id ) {
			self::geri_don( 'Hedef hesap, kaynak hesaptan farklı olmalı.', 'hata' );
		}
		$aciklama = YP_Guvenlik::uzun_metin( 'aciklama' );
		self::tek_islemde( function () use ( $hesap, $hedef, $tutar, $tarih, $aciklama ) {
			$id = YP_Veri::hareket_ekle( array(
				'kayit_turu'     => 'TRANSFER',
				'hesap_id'       => $hesap->id,
				'hedef_hesap_id' => $hedef->id,
				'borc_tipi'      => 'Transfer',
				'tutar'          => $tutar,
				'durum'          => 'ODENDI',
				'odeme_turu'     => 'HAVALE',
				'odeme_tarihi'   => $tarih,
				'aciklama'       => $aciklama,
				'tahsil_eden'    => YP_Cekirdek::kullanici_id(),
				'tahsil_zamani'  => YP_Cekirdek::simdi(),
			) );
			YP_Cekirdek::log( 'kasa', $id, 'transfer', $hesap->ad . ' → ' . $hedef->ad . ' ' . YP_Bicim::tl( $tutar ) );
		} );
		self::geri_don( 'Transfer kaydedildi.' );
	}

	// KURAL: Kasadan yalnızca gelir/gider/transfer silinir; aday tahsilatı adayın ödeme kartından yönetilir.
	public static function sil() {
		$s = YP_Veri::hareket( YP_Guvenlik::tamsayi( 'hareket_id' ) );
		if ( ! $s || 'ADAY' === $s->kayit_turu || $s->silindi ) {
			self::geri_don( 'Kayıt bulunamadı veya buradan silinemez.', 'hata' );
		}
		self::tek_islemde( function () use ( $s ) {
			YP_Veri::hareket_guncelle( $s->id, array( 'silindi' => 1, 'silme_zamani' => YP_Cekirdek::simdi(), 'silen' => YP_Cekirdek::kullanici_id() ) );
			YP_Cekirdek::log( 'kasa', $s->id, 'silme', self::tur_etiketi( $s ) . ' silindi: ' . YP_Bicim::tl( $s->tutar ) );
		} );
		self::geri_don( 'Kayıt silindi. Silinenler ekranından geri alınabilir.' );
	}
}
