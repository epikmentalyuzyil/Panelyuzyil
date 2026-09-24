<?php
defined( 'ABSPATH' ) || exit;

/**
 * Müşteri (aday) kartı ve ödeme ekranı.
 * Yerleşim masaüstü programdaki kursiyer kartıyla aynıdır: üstte simgeli şerit, koyu ad şeridi,
 * solda fotoğraf ve bakiye, ortada bilgiler, sağda genel bilgiler ve işlemler, altta durum çubuğu.
 * KURAL: Ekran pencereye sığar; kaydırma yalnızca not ve liste alanlarının içindedir.
 */
final class YP_Ekran_Aday extends YP_Ekran {

	public static function islemler() {
		$s = 'YP_Aday_Islem';
		return array(
			'aday_kaydet'      => array( $s, 'aday_kaydet' ),
			'aday_arsiv'       => array( $s, 'aday_arsiv' ),
			'aday_sil'         => array( $s, 'aday_sil' ),
			'aday_foto'        => array( $s, 'aday_foto' ),
			'aday_foto_sil'    => array( $s, 'aday_foto_sil' ),
			'evrak_kaydet'     => array( $s, 'evrak_kaydet' ),
			'gorusme_ekle'     => array( $s, 'gorusme_ekle' ),
			'gorusme_sil'      => array( $s, 'gorusme_sil' ),
			'islem_kaydet'     => array( $s, 'islem_kaydet' ),
			'islem_iptal'      => array( $s, 'islem_iptal' ),
			'islem_sil'        => array( $s, 'islem_sil' ),
			'borc_ekle'        => array( $s, 'borc_ekle' ),
			'taksit_plani'     => array( $s, 'taksit_plani' ),
			'tahsil_et'        => array( $s, 'tahsil_et' ),
			'odeme_al'         => array( $s, 'odeme_al' ),
			'iade_et'          => array( $s, 'iade_et' ),
			'hareket_duzenle'  => array( $s, 'hareket_duzenle' ),
			'tahsilat_geri_al' => array( $s, 'tahsilat_geri_al' ),
			'hareket_sil'      => array( $s, 'hareket_sil' ),
		);
	}

	public static function goster() {
		if ( YP_Guvenlik::tamsayi( 'yeni', 'get' ) ) {
			self::kart( null, 'kart' );
			return;
		}
		$aday = YP_Veri::aday( YP_Guvenlik::tamsayi( 'id', 'get' ) );
		if ( ! $aday ) {
			self::yonlendir( array( 'ekran' => 'adaylar' ), 'Aday bulunamadı.', 'hata' );
		}
		// KURAL: Kartın tek alt ekranı ödeme bilgileridir; başka sekme yoktur.
		if ( 'odeme' === YP_Guvenlik::secim( 'sekme', array( 'kart', 'odeme' ), 'kart', 'get' ) ) {
			self::odeme_ekrani( $aday );
			return;
		}
		self::kart( $aday );
	}

	// ---- Ortak parçalar ---------------------------------------------------

	// KURAL: Kartın koyu ad şeridi aynı zamanda araç çubuğudur — ayrıca lacivert üst çubuk açılmaz, yer kaplamaz.
	private static function ad_seridi( $aday, $sag_metin = '' ) {
		echo '<div class="ekran-ad">';
		echo '<a class="ad-menu" href="' . esc_url( YP_Cekirdek::panel_url() ) . '" title="Ana menü">☰</a>';
		echo '<strong>' . esc_html( $aday ? YP_Veri::aday_adi( $aday ) : 'YENİ ADAY' ) . '</strong>';
		echo '<span class="sari-bilgi">' . esc_html( '' !== $sag_metin ? $sag_metin : YP_Bicim::tarih_uzun( YP_Cekirdek::bugun() ) ) . '</span>';
		echo '<span class="arac-sag">' . self::serit_anahtari() . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '</div>';
	}

	/**
	 * Kart ekranının ad şeridi: solda ana menü ve ad, sağda ekranın dört işlemi.
	 * KURAL: Kart ekranında açılır beyaz şerit yoktur; bütün düğmeler bu tek koyu şeritte durur,
	 * bu yüzden şerit oku da çizilmez (ad-sabit sınıfı düğmeleri her durumda görünür tutar).
	 */
	private static function kart_ad_seridi( $aday ) {
		echo '<div class="ekran-ad ad-sabit">';
		echo '<a class="ad-menu" href="' . esc_url( YP_Cekirdek::panel_url() ) . '" title="Ana menü">☰</a>';
		echo '<strong>' . esc_html( $aday ? YP_Veri::aday_adi( $aday ) : 'YENİ ADAY' ) . '</strong>';
		echo '<span class="ad-islem">';
		if ( $aday ) {
			echo '<a class="serit-dugme" href="' . esc_url( YP_Cekirdek::panel_url( array( 'ekran' => 'aday', 'id' => (int) $aday->id, 'sekme' => 'odeme' ) ) ) . '">';
			echo '<span class="serit-ikon">' . self::ikon( 'para', 22 ) . '</span><span>Ödeme Bilgileri</span></a>'; // phpcs:ignore
		}
		echo '<a class="serit-dugme" href="' . esc_url( YP_Cekirdek::panel_url( array( 'ekran' => 'adaylar' ) ) ) . '">';
		echo '<span class="serit-ikon">' . self::ikon( 'arama', 22 ) . '</span><span>Aday Ara</span></a>'; // phpcs:ignore
		echo '<button type="submit" form="kart-formu" class="serit-dugme serit-yesil">';
		echo '<span class="serit-ikon">' . self::ikon( 'kaydet', 22 ) . '</span><span>Kaydet</span></button>'; // phpcs:ignore
		if ( $aday && ! $aday->silindi ) {
			self::form_ac( 'aday_sil', 'satir-ici' );
			self::gizli( 'id', (int) $aday->id );
			echo '<button type="submit" class="serit-dugme serit-kirmizi" data-onay="Aday silinenlere taşınacak. Emin misiniz?">';
			echo '<span class="serit-ikon">' . self::ikon( 'cop', 22 ) . '</span><span>Sil</span></button></form>'; // phpcs:ignore
		}
		echo '</span></div>';
	}

	private static function durum_cubugu( $aday ) {
		echo '<footer class="durum-cubugu">';
		if ( $aday ) {
			echo '<span>KAYIT: ' . esc_html( YP_Cekirdek::kullanici_adi( $aday->olusturan ) . ' — ' . YP_Bicim::tarih_saat( $aday->olusturma ) ) . '</span>';
			if ( $aday->guncelleme ) {
				echo '<span>GÜNCEL: ' . esc_html( YP_Cekirdek::kullanici_adi( $aday->guncelleyen ) . ' — ' . YP_Bicim::tarih_saat( $aday->guncelleme ) ) . '</span>';
			}
			// KURAL: Alt çubuk kaydın seçili durumunu yazar; durum seçilmemişse aktif/arşiv bilgisi gösterilir.
			$durumlar = YP_Veri::kayit_durumlari();
			$durum_ad = isset( $aday->kayit_durumu ) && isset( $durumlar[ $aday->kayit_durumu ] ) ? $durumlar[ $aday->kayit_durumu ] : ( $aday->arsiv ? 'ARŞİVDE' : 'AKTİF' );
			echo '<span class="sag-bilgi">' . esc_html( $durum_ad ) . '</span>';
		} else {
			echo '<span>Yeni kayıt — bilgileri doldurup Kaydet düğmesine basın.</span>';
		}
		echo '</footer>';
	}

	// ---- Kart ekranı ------------------------------------------------------

	/**
	 * Aynı kişinin (aynı TC) diğer aday kartları — yıllar içinde açılmış ayrı kayıtlar.
	 * KURAL: Aynı TC ile ikinci kart açmak engellenmez; diğer kartlar burada listelenip tek tıkla açılır.
	 */
	private static function diger_kartlar( $aday ) {
		global $wpdb;
		if ( ! $aday || '' === (string) $aday->tc_no ) {
			return array();
		}
		$a       = YP_Cekirdek::tablo( 'adaylar' );
		$i       = YP_Cekirdek::tablo( 'islemler' );
		$kartlar = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, aday_no, kayit_tarihi, arsiv FROM {$a} WHERE silindi = 0 AND tc_no = %s AND id <> %d ORDER BY aday_no DESC LIMIT 20", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$aday->tc_no,
			(int) $aday->id
		) );
		if ( ! $kartlar ) {
			return array();
		}
		// KURAL: Her kartın en son işlemi tek sorguda alınır — kart sayısı kadar sorgu atılmaz.
		$idler = array_map( 'intval', wp_list_pluck( $kartlar, 'id' ) );
		$yer   = implode( ',', array_fill( 0, count( $idler ), '%d' ) );
		$son   = array();
		foreach ( $wpdb->get_results( $wpdb->prepare(
			"SELECT aday_id, islem_turu_id, islem_tarihi, rapor_no, ucret, durum FROM {$i}
			WHERE silindi = 0 AND aday_id IN ({$yer}) ORDER BY islem_tarihi DESC, id DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$idler
		) ) as $s ) {
			if ( ! isset( $son[ (int) $s->aday_id ] ) ) {
				$son[ (int) $s->aday_id ] = $s;
			}
		}
		foreach ( $kartlar as $k ) {
			$k->son_islem = isset( $son[ (int) $k->id ] ) ? $son[ (int) $k->id ] : null;
		}
		return $kartlar;
	}

	private static function kart( $aday ) {
		$ozet     = $aday ? YP_Hesap::aday_ozeti( $aday->id ) : array( 'borc' => 0, 'odenen' => 0, 'kalan' => 0, 'geciken' => 0, 'iade' => 0 );
		$saklanan = self::form_al();
		$islemler = $aday ? YP_Veri::aday_islemleri( $aday->id ) : array();
		$turler   = YP_Veri::tanim_adlari( 'islem_turu' );
		// KURAL: Kart, adresteki "islem" numarasıyla açılan hizmeti gösterir; yoksa en son hizmet açıktır.
		// Listeden başka bir hizmete tıklanınca kart o hizmetle yeniden açılır — iki kart aynı anda durmaz.
		$acik_id  = YP_Guvenlik::tamsayi( 'islem', 'get' );
		$son      = $islemler ? $islemler[0] : null;
		foreach ( $islemler as $i ) {
			if ( $acik_id && (int) $i->id === $acik_id ) {
				$son = $i;
			}
		}
		$son_adi  = $son && isset( $turler[ (int) $son->islem_turu_id ] ) ? $turler[ (int) $son->islem_turu_id ] : '';
		// Açık hizmetin düzenleme penceresine gönderilecek bilgileri.
		$son_doldur = ! $son ? '' : wp_json_encode( array(
			'islem_id'          => (int) $son->id,
			'islem_turu_id'     => (string) (int) $son->islem_turu_id,
			'islem_tarihi'      => YP_Bicim::tarih( $son->islem_tarihi ),
			'islem_saati'       => $son->islem_saati ? substr( $son->islem_saati, 0, 5 ) : '',
			'islem_referans_id' => (string) (int) $son->referans_id,
			'ucret'             => YP_Bicim::tl( $son->ucret, false ),
			'durum'             => $son->durum,
			'rapor_no'          => (string) $son->rapor_no,
			'rapor_tarihi'      => YP_Bicim::tarih( $son->rapor_tarihi ),
			'gecerlilik_bitis'  => YP_Bicim::tarih( $son->gecerlilik_bitis ),
			'islem_aciklama'    => (string) $son->aciklama,
		) );

		self::sayfa_basla( $aday ? YP_Veri::aday_adi( $aday ) : 'Yeni Aday', 'adaylar', 'uygulama ust-yok' );
		?>
		<div class="uyg">
			<?php // KURAL: Kart ekranında açılır beyaz şerit yoktur; tüm düğmeler koyu ad şeridindedir. ?>
			<?php self::kart_ad_seridi( $aday ); ?>
			<?php if ( $aday && $aday->silindi ) : ?>
				<div class="uyari uyari-hata">Bu aday silinmiş. Geri almak için <a href="<?php echo esc_url( YP_Cekirdek::panel_url( array( 'ekran' => 'silinenler' ) ) ); ?>">Silinenler</a> ekranını kullanın.</div>
			<?php endif; ?>

			<form class="kart-govde" method="post" action="<?php echo esc_url( YP_Cekirdek::panel_url() ); ?>" id="kart-formu">
				<?php
				echo YP_Guvenlik::nonce_alani( 'aday_kaydet' ); // phpcs:ignore
				self::gizli( 'id', $aday ? $aday->id : 0 );
				self::gizli( '_geri', YP_Cekirdek::panel_url( array( 'ekran' => 'aday', 'id' => $aday ? (int) $aday->id : 0 ) ) );
				// KURAL: Alan değerleri önce hatalı gönderimden saklanan formdan, yoksa kayıttan okunur.
				$d = function ( $alan, $tarih = false ) use ( $aday, $saklanan ) {
					if ( isset( $saklanan[ $alan ] ) ) {
						return $saklanan[ $alan ];
					}
					if ( ! $aday ) {
						return '';
					}
					$v = isset( $aday->$alan ) ? $aday->$alan : '';
					return $tarih ? YP_Bicim::tarih( $v ) : (string) $v;
				};
				?>

				<aside class="kart-sol">
					<div class="kart-foto">
						<?php if ( $aday && $aday->foto ) : ?>
							<img src="<?php echo esc_url( YP_Foto::url( $aday, false ) ); ?>" alt="Aday fotoğrafı">
						<?php else : ?>
							<span>Fotoğraf yok</span>
						<?php endif; ?>
					</div>
					<?php if ( $aday ) : ?>
						<?php // KURAL: Aday numarası fotoğrafın hemen altındadır, fotoğraf düğmesi de onun altındadır. ?>
						<div class="aday-no-kutu"><span>No</span><b><?php echo (int) $aday->aday_no; ?></b></div>
						<button type="button" class="foto-dugme" data-dialog="d-foto">Fotoğraf değiştir</button>
					<?php endif; ?>
					<?php
					// KURAL: Kaydın durumu sol sütunun en altında, küçük ve sade durur — dikkati bilgilerden çalmaz.
					// KURAL: Durum seçilmemiş kayıtlar boş görünür; "Arşiv" seçimi kaydı arşive alır.
					$durum_secili = $aday && isset( $aday->kayit_durumu ) ? (string) $aday->kayit_durumu : '';
					if ( '' === $durum_secili && $aday && $aday->arsiv ) {
						$durum_secili = 'arsiv';
					}
					echo '<label class="durum-secim"><span>Durumu</span><select name="durum_kaydi">';
					echo '<option value="">—</option>';
					foreach ( YP_Veri::kayit_durumlari() as $k => $v ) {
						echo '<option value="' . esc_attr( $k ) . '"' . selected( $durum_secili, $k, false ) . '>' . esc_html( $v ) . '</option>';
					}
					echo '</select></label>';
					?>
				</aside>

				<section class="kart-bolum alan-kimlik">
					<?php // KURAL: Başlık şeridindeki siyah alan fatura tarihi / şirket unvanı içindir; yazılanlar sarı görünür ve şeridi taşmaz. ?>
					<h2>Kimlik Bilgileri
						<input type="text" class="serit-not" name="sari_not" maxlength="150"
							value="<?php echo esc_attr( $d( 'sari_not' ) ); ?>" title="Fatura tarihi ve şirket unvanı">
					</h2>
					<div class="ic">
						<?php
						self::satir_tarih( 'kayit_tarihi', 'Kayıt Tarihi', $aday || $saklanan ? $d( 'kayit_tarihi', true ) : YP_Bicim::tarih( YP_Cekirdek::bugun() ) );
						self::satir_metin( 'tc_no', 'TC Kimlik No', $d( 'tc_no' ), array( 'maxlength' => 11, 'inputmode' => 'numeric', 'veri' => array( 'tc' => 1 ) ) );
						self::satir_metin( 'adi', 'Adı', $d( 'adi' ), array( 'zorunlu' => true, 'maxlength' => 100, 'sinif' => 'buyuk' ) );
						self::satir_metin( 'soyadi', 'Soyadı', $d( 'soyadi' ), array( 'zorunlu' => true, 'maxlength' => 100, 'sinif' => 'buyuk' ) );
						self::satir_tarih( 'dogum_tarihi', 'Doğum Tarihi', $d( 'dogum_tarihi', true ) );
						// KURAL: Genel Bilgiler bölümü kaldırıldığından cinsiyet kimlik satırlarının sonuna alındı — alan kaybolmaz.
						self::satir_secim( 'cinsiyet', 'Cinsiyet', array( 'Erkek' => 'ERKEK', 'Kadın' => 'KADIN' ), $d( 'cinsiyet' ) );
						// KURAL: İletişim bölümü kaldırıldı; iki telefon kimlik satırlarının en altında durur.
						self::satir_metin( 'gsm_1', 'Telefon 1', $d( 'gsm_1' ) ? YP_Bicim::telefon( $d( 'gsm_1' ) ) : '', array( 'tur' => 'tel' ) );
						self::satir_metin( 'gsm_2', 'Telefon 2', $d( 'gsm_2' ) ? YP_Bicim::telefon( $d( 'gsm_2' ) ) : '', array( 'tur' => 'tel' ) );
						?>
					</div>
				</section>

				<?php // KURAL: Ortadaki iki sarı kutu masaüstü karttaki işlem ve referans satırlarının karşılığıdır. ?>
				<div class="alan-islem kart-yigin">
					<section class="kart-bolum">
						<h2>İşlem Bilgileri</h2>
						<div class="ic">
							<?php
							// KURAL: Bu kartın işlem türü referans gibi listeden seçilir; Kaydet'e basınca açık kayda yazılır.
							self::gizli( 'kart_islem_id', $son ? (int) $son->id : 0 );
							self::satir_secim( 'islem_turu_id', '', YP_Veri::tanim_adlari( 'islem_turu' ), $son ? (int) $son->islem_turu_id : '', 'vurgu tek-alan', 'İŞLEM SEÇİN' );
							if ( $son ) {
								echo '<div class="islem-alt">';
								echo '<span>' . esc_html( YP_Bicim::tarih( $son->islem_tarihi ) ) . ( $son->gecerlilik_bitis ? ' · Geçerlilik: ' . esc_html( YP_Bicim::tarih( $son->gecerlilik_bitis ) ) : '' ) . '</span>';
								echo '<button type="button" class="islem-duzenle" data-dialog="d-islem" data-doldur="' . esc_attr( $son_doldur ) . '">ayrıntı</button>';
								echo '</div>';
							}
							?>
						</div>
					</section>
					<section class="kart-bolum">
						<h2>Referans Bilgileri</h2>
						<div class="ic">
							<?php self::satir_secim( 'referans_id', '', YP_Veri::referans_secenekleri( $aday ? (int) $aday->referans_id : 0 ), $d( 'referans_id' ), 'vurgu tek-alan', 'BİREYSEL KAYIT' ); ?>
						</div>
					</section>
				</div>

				<section class="kart-bolum alan-notlar">
					<h2>Aday Notları</h2>
					<div class="ic">
						<textarea class="kart-not" name="ozel_notlar" placeholder="Adayla ilgili notlar…"><?php echo esc_textarea( $d( 'ozel_notlar' ) ); ?></textarea>
					</div>
				</section>

				<?php // KURAL: Bakiye kutusu masaüstündeki gibi sol altta durur; borç kapandığında yeşil "ÖDENDİ" yazar. ?>
				<aside class="alan-bakiye">
					<div class="bakiye-tablo">
						<div><span>Borç</span><b><?php echo esc_html( YP_Bicim::tl( $ozet['borc'], false ) ); ?></b></div>
						<div><span>Ödenen</span><b class="yesil-yazi"><?php echo esc_html( YP_Bicim::tl( $ozet['odenen'], false ) ); ?></b></div>
						<?php if ( $ozet['kalan'] <= 0 && $ozet['borc'] > 0 ) : ?>
							<div><span>Bakiye</span><b class="bakiye-tamam">ÖDENDİ</b></div>
						<?php else : ?>
							<div><span>Bakiye</span><b class="<?php echo $ozet['kalan'] > 0 ? 'kirmizi' : ''; ?>"><?php echo esc_html( YP_Bicim::tl( $ozet['kalan'], false ) ); ?></b></div>
						<?php endif; ?>
					</div>
				</aside>

				<?php
				// KURAL: Bu listede adayın **başka** kayıtları (önceki psikoteknik kartları) durur;
				// açık olan kayıt listeye girmez — satıra tıklanınca o kayıt açılır, bu kart kapanır.
				$digerleri = array();
				foreach ( $islemler as $i ) {
					if ( ! $son || (int) $son->id !== (int) $i->id ) {
						$digerleri[] = $i;
					}
				}
				?>
				<section class="kart-bolum alan-islemler">
					<h2>Adaya Ait Başka Kayıtlar
						<?php if ( $aday ) : ?>
							<button type="button" class="dugme kucuk" data-dialog="d-islem">+ Yeni Kayıt</button>
						<?php endif; ?>
					</h2>
					<div class="ic" style="padding:0">
						<table class="tablo yogun">
							<thead><tr><th>Tarih</th><th>İşlem Adı</th><th>Rapor</th><th class="sag">Ücret</th><th>Durumu</th></tr></thead>
							<tbody>
							<?php
							$diger_kartlar = self::diger_kartlar( $aday );
							if ( ! $digerleri && ! $diger_kartlar ) {
								echo '<tr><td colspan="5" class="bos">' . ( $aday ? 'Adayın başka kaydı yok.' : 'Aday kaydedildikten sonra kayıt açabilirsiniz.' ) . '</td></tr>';
							}
							// KURAL: Aynı TC ile açılmış diğer kartlar da bu listededir; satıra tıklanınca o kart açılır.
							foreach ( $diger_kartlar as $k ) :
								$si   = $k->son_islem;
								$kurl = YP_Cekirdek::panel_url( array( 'ekran' => 'aday', 'id' => (int) $k->id ) );
								?>
								<tr class="tiklanir" data-git="<?php echo esc_url( $kurl ); ?>" title="Bu aday kartını aç">
									<td><?php echo esc_html( YP_Bicim::tarih( $si ? $si->islem_tarihi : $k->kayit_tarihi ) ); ?></td>
									<td><strong><?php echo esc_html( $si && isset( $turler[ (int) $si->islem_turu_id ] ) ? $turler[ (int) $si->islem_turu_id ] : 'Aday kaydı' ); ?></strong></td>
									<td><?php echo esc_html( $si ? $si->rapor_no : '' ); ?></td>
									<td class="sag"><?php echo esc_html( $si ? YP_Bicim::tl( $si->ucret, false ) : '' ); ?></td>
									<?php // KURAL: Ayrı kartın numarası satırın en sağında, küçük ve silik durur — asıl bilgiyi bastırmaz. ?>
									<td><?php echo $si ? self::islem_rozeti( $si->durum ) : self::rozet( $k->arsiv ? 'Arşiv' : 'Aktif', 'gri' ); // phpcs:ignore ?><span class="kart-no">KART #<?php echo (int) $k->aday_no; ?></span></td>
								</tr>
							<?php endforeach; ?>
							<?php
							foreach ( $digerleri as $i ) :
								$url = YP_Cekirdek::panel_url( array( 'ekran' => 'aday', 'id' => (int) $aday->id, 'islem' => (int) $i->id ) );
								?>
								<tr class="<?php echo 'iptal' === $i->durum ? 'soluk-satir ' : ''; ?>tiklanir" data-git="<?php echo esc_url( $url ); ?>" title="Bu kaydın kartını aç">
									<td><?php echo esc_html( YP_Bicim::tarih( $i->islem_tarihi ) ); ?></td>
									<td><strong><?php echo esc_html( isset( $turler[ (int) $i->islem_turu_id ] ) ? $turler[ (int) $i->islem_turu_id ] : '—' ); ?></strong></td>
									<td><?php echo esc_html( $i->rapor_no ); ?></td>
									<td class="sag"><?php echo esc_html( YP_Bicim::tl( $i->ucret, false ) ); ?></td>
									<td><?php echo self::islem_rozeti( $i->durum ); // phpcs:ignore ?></td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</section>

			</form>
			<?php self::durum_cubugu( $aday ); ?>
		</div>
		<?php
		// KURAL: Kart ekranında şerit olmadığı için yalnızca açılabilen pencereler çizilir (fotoğraf, işlem, borç/ödeme);
		// evrak ve görüşme pencerelerinin düğmesi kalmadığından bu ekranda basılmaz.
		if ( $aday ) {
			self::diyaloglar( $aday );
		}
		self::sayfa_bitir();
	}

	// ---- Satır biçimli alanlar --------------------------------------------

	private static function satir_metin( $ad, $etiket, $deger, array $ek = array() ) {
		$tur   = isset( $ek['tur'] ) ? $ek['tur'] : 'text';
		$sinif = isset( $ek['sinif'] ) ? $ek['sinif'] : '';
		$oz    = '';
		foreach ( array( 'maxlength', 'inputmode', 'placeholder' ) as $o ) {
			if ( isset( $ek[ $o ] ) ) {
				$oz .= ' ' . $o . '="' . esc_attr( $ek[ $o ] ) . '"';
			}
		}
		if ( ! empty( $ek['veri'] ) ) {
			foreach ( $ek['veri'] as $k => $v ) {
				$oz .= ' data-' . esc_attr( $k ) . '="' . esc_attr( $v ) . '"';
			}
		}
		$stil = isset( $ek['etiket_genislik'] ) ? ' style="grid-template-columns:' . (int) $ek['etiket_genislik'] . 'px minmax(0,1fr)"' : '';
		echo '<label class="satir-alan ' . esc_attr( $sinif ) . '"' . $stil . '><span>' . esc_html( $etiket ) . ( ! empty( $ek['zorunlu'] ) ? ' <b class="zorunlu">*</b>' : '' ) . '</span>'; // phpcs:ignore
		echo '<input type="' . esc_attr( $tur ) . '" name="' . esc_attr( $ad ) . '" value="' . esc_attr( null === $deger ? '' : $deger ) . '"' . $oz . ( ! empty( $ek['zorunlu'] ) ? ' required' : '' ) . ( ! empty( $ek['sinif'] ) && false !== strpos( $ek['sinif'], 'buyuk' ) ? ' style="text-transform:uppercase"' : '' ) . '></label>'; // phpcs:ignore
	}

	private static function satir_tarih( $ad, $etiket, $deger ) {
		$gorunen = preg_match( '/^\d{4}-\d{2}-\d{2}/', (string) $deger ) ? YP_Bicim::tarih( $deger ) : (string) $deger;
		echo '<label class="satir-alan"><span>' . esc_html( $etiket ) . '</span>';
		echo '<input type="text" class="tarih" data-tarih="1" inputmode="numeric" maxlength="10" placeholder="gg.aa.yyyy" name="' . esc_attr( $ad ) . '" value="' . esc_attr( $gorunen ) . '" autocomplete="off"></label>';
	}

	private static function satir_secim( $ad, $etiket, array $secenekler, $secili, $sinif = '', $bos = 'Seçiniz', $etiket_genislik = 0 ) {
		$stil = $etiket_genislik ? ' style="grid-template-columns:' . (int) $etiket_genislik . 'px minmax(0,1fr)"' : '';
		echo '<label class="satir-alan ' . esc_attr( $sinif ) . '"' . $stil . '><span>' . esc_html( $etiket ) . '</span><select name="' . esc_attr( $ad ) . '">'; // phpcs:ignore
		if ( null !== $bos ) {
			echo '<option value="">' . esc_html( $bos ) . '</option>';
		}
		foreach ( $secenekler as $deger => $yazi ) {
			echo '<option value="' . esc_attr( $deger ) . '"' . selected( (string) $secili, (string) $deger, false ) . '>' . esc_html( is_array( $yazi ) ? $yazi['ad'] : $yazi ) . '</option>';
		}
		echo '</select></label>';
	}

	// ---- Ödeme ekranı -----------------------------------------------------

	private static function odeme_ekrani( $aday ) {
		$ozet       = YP_Hesap::aday_ozeti( $aday->id );
		$hareketler = YP_Veri::aday_hareketleri( $aday->id );
		$hesaplar   = YP_Veri::hesap_adlari();
		$odeme      = YP_Veri::odeme_turleri();
		$bugun      = YP_Cekirdek::bugun();
		$kirilim    = YP_Hesap::aday_kirilimi( $aday->id );

		self::sayfa_basla( YP_Veri::aday_adi( $aday ) . ' — Ödeme', 'adaylar', 'uygulama ust-yok' );
		?>
		<div class="uyg">
			<header class="ekran-serit">
				<?php
				self::serit_grubu_ciz( 'İşlemler', array(
					array( 'Borç Ekle', 'd-borc', 'ekle', 'serit-birincil' ),
					array( 'Ödeme Planı', 'd-taksit', 'takvim' ),
				) );
				self::serit_grubu_ciz( 'Ödeme', array(
					array( 'Ödeme Yap', 'd-odeme-al', 'para', 'serit-yesil serit-birincil' ),
					array( 'İade', 'd-iade', 'geri' ),
				) );
				self::serit_grubu_ciz( 'Kayıt', array(
					array( 'Aday Kartı', array( 'ekran' => 'aday', 'id' => (int) $aday->id ), 'kisi' ),
					array( 'Yeni İşlem', 'd-islem', 'aday-ekle' ),
				) );
				echo '<div class="serit-grup serit-kapat"><div class="serit-dugmeler">';
				echo '<a class="serit-dugme" href="' . esc_url( YP_Cekirdek::panel_url( array( 'ekran' => 'adaylar' ) ) ) . '"><span class="serit-ikon">' . self::ikon( 'kapat', 22 ) . '</span><span>Kapat</span></a>'; // phpcs:ignore
				echo '</div><div class="serit-baslik">Kapat</div></div>';
				?>
			</header>
			<?php self::ad_seridi( $aday, 'BAKİYE: ' . YP_Bicim::tl( $ozet['kalan'] ) ); ?>

			<div class="odeme-govde">
				<div class="odeme-liste">
					<table>
						<thead><tr>
							<th>Borç Tipi</th><th>Vade Tarihi</th><th class="sag">Tutar</th><th>Ödeme Türü</th><th>Ödeme Tarihi</th>
							<th>Makbuz</th><th>Durumu</th><th>Hesap</th><th>Açıklama</th><th></th>
						</tr></thead>
						<tbody>
						<?php
						if ( ! $hareketler ) {
							echo '<tr><td colspan="10" class="bos">Ödeme kartında satır yok. Şeritten "Borç Ekle" ile başlayın.</td></tr>';
						}
						foreach ( $hareketler as $h ) :
							$gecikmis = 'ODENMEDI' === $h->durum && $h->vade_tarihi && $h->vade_tarihi < $bugun;
							$sinif    = 'IADE' === $h->durum ? 'iade' : ( 'ODENMEDI' === $h->durum ? ( $gecikmis ? 'gecikmis' : 'odenmedi' ) : '' );
							?>
							<tr class="<?php echo esc_attr( $sinif ); ?>">
								<td><strong><?php echo esc_html( $h->borc_tipi ); ?></strong></td>
								<td><?php echo esc_html( YP_Bicim::tarih( $h->vade_tarihi ) ); ?></td>
								<td class="sag"><?php echo esc_html( YP_Bicim::tl( $h->tutar, false ) ); ?></td>
								<td><?php echo esc_html( isset( $odeme[ $h->odeme_turu ] ) ? $odeme[ $h->odeme_turu ] : '' ); ?></td>
								<td><?php echo esc_html( YP_Bicim::tarih( $h->odeme_tarihi ) ); ?></td>
								<?php // KURAL: Makbuz numarası yoktur; tahsil edilmiş satırın makbuzu satır kimliğiyle yazdırılır ("kendisi yatırdı" hariç). ?>
								<td><?php echo ( 'ODENDI' === $h->durum && 'KENDISI' !== $h->odeme_turu ) ? '<a href="' . esc_url( self::makbuz_url( array( (int) $h->id ) ) ) . '">Yazdır</a>' : ''; ?></td>
								<td><?php echo esc_html( 'ODENMEDI' === $h->durum ? ( $gecikmis ? 'GECİKMİŞ' : 'ÖDENMEDİ' ) : ( 'IADE' === $h->durum ? 'İADE' : 'ÖDENDİ' ) ); ?></td>
								<td><?php echo esc_html( $h->hesap_id && isset( $hesaplar[ (int) $h->hesap_id ] ) ? $hesaplar[ (int) $h->hesap_id ] : '' ); ?></td>
								<td><?php echo esc_html( $h->aciklama ); ?></td>
								<td class="islemler-sutun">
									<?php if ( 'ODENMEDI' === $h->durum ) : ?>
										<button type="button" class="dugme kucuk yesil" data-dialog="d-tahsil" data-doldur="<?php echo esc_attr( wp_json_encode( array( 'hareket_id' => (int) $h->id, 'tutar' => YP_Bicim::tl( $h->tutar, false ), 'satir_bilgi' => $h->borc_tipi . ' — ' . YP_Bicim::tl( $h->tutar ) . ' (vade ' . YP_Bicim::tarih( $h->vade_tarihi ) . ')' ) ) ); ?>">Tahsil</button>
										<button type="button" class="dugme kucuk" data-dialog="d-duzenle" data-doldur="<?php echo esc_attr( wp_json_encode( array( 'hareket_id' => (int) $h->id, 'tutar' => YP_Bicim::tl( $h->tutar, false ), 'vade_tarihi' => YP_Bicim::tarih( $h->vade_tarihi ), 'borc_tipi' => $h->borc_tipi, 'aciklama' => (string) $h->aciklama ) ) ); ?>">Düzelt</button>
									<?php elseif ( 'ODENDI' === $h->durum ) : ?>
										<?php self::kucuk_form( 'tahsilat_geri_al', array( 'id' => $aday->id, 'hareket_id' => $h->id ), 'Geri al', 'dugme kucuk', 'Tahsilat geri alınacak ve kasadan düşecek. Emin misiniz?' ); ?>
									<?php endif; ?>
									<?php self::kucuk_form( 'hareket_sil', array( 'id' => $aday->id, 'hareket_id' => $h->id ), 'Sil', 'dugme kucuk kirmizi', 'Satır silinecek. Emin misiniz?' ); ?>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>

				<div class="odeme-alt">
					<div class="matris">
						<h3>Bakiye Bilgileri</h3>
						<table>
							<thead><tr><th>&nbsp;</th>
								<?php
								$kalemler = array();
								foreach ( $kirilim as $k ) {
									$kalemler[ $k->tip ] = array( 'borc' => (float) $k->borc, 'odenen' => (float) $k->odenen );
								}
								$kalemler = array_slice( $kalemler, 0, 4, true );
								foreach ( $kalemler as $ad => $x ) {
									echo '<th>' . esc_html( mb_strimwidth( $ad, 0, 18, '…', 'UTF-8' ) ) . '</th>';
								}
								?>
								<th>Toplam</th>
							</tr></thead>
							<tbody>
								<tr><th>Borç</th>
									<?php
									foreach ( $kalemler as $x ) {
										echo '<td>' . esc_html( YP_Bicim::tl( $x['borc'], false ) ) . '</td>';
									}
									?>
									<td><?php echo esc_html( YP_Bicim::tl( $ozet['borc'], false ) ); ?></td>
								</tr>
								<tr><th>Ödenen</th>
									<?php
									foreach ( $kalemler as $x ) {
										echo '<td>' . esc_html( YP_Bicim::tl( $x['odenen'], false ) ) . '</td>';
									}
									?>
									<td><?php echo esc_html( YP_Bicim::tl( $ozet['odenen'], false ) ); ?></td>
								</tr>
								<tr><th>Bakiye</th>
									<?php
									foreach ( $kalemler as $x ) {
										echo '<td>' . esc_html( YP_Bicim::tl( $x['borc'] - $x['odenen'], false ) ) . '</td>';
									}
									?>
									<td class="<?php echo $ozet['kalan'] > 0 ? 'kirmizi' : ''; ?>"><?php echo esc_html( YP_Bicim::tl( $ozet['kalan'], false ) ); ?></td>
								</tr>
							</tbody>
						</table>
					</div>
					<div class="matris">
						<h3>Özet</h3>
						<table>
							<tbody>
								<tr><th>Vadesi geçmiş</th><td class="<?php echo $ozet['geciken'] > 0 ? 'kirmizi' : ''; ?>"><?php echo esc_html( YP_Bicim::tl( $ozet['geciken'], false ) ); ?></td></tr>
								<tr><th>İade edilen</th><td><?php echo esc_html( YP_Bicim::tl( $ozet['iade'], false ) ); ?></td></tr>
								<tr><th>Satır sayısı</th><td><?php echo count( $hareketler ); ?></td></tr>
							</tbody>
						</table>
					</div>
				</div>
			</div>
			<footer class="durum-cubugu">
				<span>Kural: "Kendisi yatırdı" satırları ve iadeler borç/ödenen toplamına girmez; iadeler kasadan çıkış olarak düşer.</span>
				<span class="sag-bilgi"><?php echo esc_html( YP_Veri::aday_adi( $aday ) . ' · Aday No ' . (int) $aday->aday_no ); ?></span>
			</footer>
		</div>
		<?php
		self::diyaloglar( $aday );
		self::sayfa_bitir();
	}

	// ---- Pencereler -------------------------------------------------------

	private static function diyalog_ac( $id, $baslik ) {
		echo '<dialog id="' . esc_attr( $id ) . '" class="pencere"><div class="pencere-baslik"><h2>' . esc_html( $baslik ) . '</h2><button type="button" class="kapat" data-kapat aria-label="Kapat">×</button></div>';
	}

	private static function diyalog_kapat( $dugme ) {
		echo '<div class="form-dugmeler"><button type="submit" class="dugme ana">' . esc_html( $dugme ) . '</button><button type="button" class="dugme" data-kapat>Vazgeç</button></div></form></dialog>';
	}

	private static function islem_alanlari( $aday, $yeni_aday_formu = false ) {
		$secenek = array();
		foreach ( YP_Veri::tanimlar( 'islem_turu', true ) as $t ) {
			$secenek[ (int) $t->id ] = array( 'ad' => $t->ad, 'veri' => array( 'ucret' => YP_Bicim::tl( $t->ucret, false ) ) );
		}
		self::secim( 'islem_turu_id', 'İşlem türü', $secenek, '', array( 'zorunlu' => ! $yeni_aday_formu, 'bos' => $yeni_aday_formu ? 'İşlem açma' : 'Seçiniz', 'veri' => array( 'ucret-doldur' => 1 ) ) );
		self::tarih_alani( 'islem_tarihi', 'İşlem tarihi', YP_Cekirdek::bugun() );
		self::alan( 'islem_saati', 'Saat', '', array( 'tur' => 'time' ) );
		if ( ! $yeni_aday_formu ) {
			self::secim( 'islem_referans_id', 'Referans', YP_Veri::referans_secenekleri( $aday ? (int) $aday->referans_id : 0 ), '', array( 'bos' => 'Yok' ) );
		}
		self::secim( 'durum', 'Durum', YP_Veri::islem_durumlari(), 'randevu', array( 'bos' => null ) );
		self::tutar_alani( 'ucret', 'Ücret', '', array( 'veri' => array( 'ucret-alani' => 1 ) ) );
		echo '<div class="yeni-islem-odeme genis izgara">';
		self::secim( 'odeme_sekli', 'Ödeme', array( 'pesin' => 'Peşin tahsil et', 'borc' => 'Borç olarak yaz', 'taksit' => 'Taksitlendir', 'yok' => 'Ücretsiz / borç yazma' ), 'pesin', array( 'bos' => null, 'veri' => array( 'odeme-sekli' => 1 ) ) );
		self::secim( 'odeme_turu', 'Ödeme türü', YP_Veri::odeme_turleri(), 'NAKIT', array( 'sinif' => 'sekil-pesin', 'veri' => array( 'hesap-oner' => 1 ) ) );
		self::secim( 'hesap_id', 'Hesap', YP_Veri::hesap_adlari( true ), YP_Veri::varsayilan_hesap( 'NAKIT' ), array( 'sinif' => 'sekil-pesin', 'bos' => 'Otomatik' ) );
		self::alan( 'taksit_sayisi', 'Taksit sayısı', '2', array( 'tur' => 'number', 'min' => 1, 'max' => 36, 'sinif' => 'sekil-taksit' ) );
		self::tarih_alani( 'ilk_vade', 'İlk vade', '', array( 'sinif' => 'sekil-taksit sekil-borc' ) );
		echo '</div>';
		if ( ! $yeni_aday_formu ) {
			self::alan( 'rapor_no', 'Rapor no', '', array( 'maxlength' => 50 ) );
			self::tarih_alani( 'rapor_tarihi', 'Rapor tarihi', '' );
			self::tarih_alani( 'gecerlilik_bitis', 'Geçerlilik bitişi', '', array( 'placeholder' => 'boşsa otomatik' ) );
			self::alan( 'islem_aciklama', 'Açıklama', '', array( 'tur' => 'textarea', 'sinif' => 'genis' ) );
		}
	}

	private static function islem_secenekleri( $aday ) {
		$turler = YP_Veri::tanim_adlari( 'islem_turu' );
		$s      = array();
		foreach ( YP_Veri::aday_islemleri( $aday->id ) as $i ) {
			if ( 'iptal' !== $i->durum ) {
				$s[ (int) $i->id ] = YP_Bicim::tarih( $i->islem_tarihi ) . ' — ' . ( isset( $turler[ (int) $i->islem_turu_id ] ) ? $turler[ (int) $i->islem_turu_id ] : 'İşlem' );
			}
		}
		return $s;
	}

	/**
	 * Evrak ve görüşme pencereleri.
	 * KURAL: Kart şeridi sadeleştirilirken bu iki pencerenin düğmesi kaldırıldı; kod ve POST işleyicileri
	 * korunuyor — özellik istenirse tek satırlık bir düğmeyle geri açılır, veri (evrak, gorusmeler) yerinde durur.
	 */
	private static function evrak_diyalogu( $aday ) {
		$liste = YP_Veri::tanimlar( 'evrak', true );
		$durum = json_decode( (string) $aday->evrak, true );
		$durum = is_array( $durum ) ? $durum : array();
		self::diyalog_ac( 'd-evrak', 'Evrak durumu' );
		self::form_ac( 'evrak_kaydet' );
		self::gizli( 'id', $aday->id );
		echo '<p class="soluk">Teslim alınan evrakları işaretleyin. Hepsi işaretliyse kartta "evrak tamam" görünür.</p><div class="onay-listesi">';
		foreach ( $liste as $e ) {
			echo '<label class="onay"><input type="checkbox" name="evrak[]" value="' . (int) $e->id . '"' . checked( ! empty( $durum[ (int) $e->id ] ), true, false ) . '> ' . esc_html( $e->ad ) . '</label>';
		}
		if ( ! $liste ) {
			echo '<p class="soluk">Evrak listesi boş. Tanımlar > Evrak Listesi bölümünden ekleyin.</p>';
		}
		echo '</div>';
		self::diyalog_kapat( 'Kaydet' );
	}

	private static function gorusme_diyalogu( $aday ) {
		global $wpdb;
		$t      = YP_Cekirdek::tablo( 'gorusmeler' );
		$notlar = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE aday_id = %d ORDER BY zaman DESC, id DESC LIMIT 50", $aday->id ) );
		self::diyalog_ac( 'd-gorusme', 'Görüşme notları' );
		self::form_ac( 'gorusme_ekle' );
		self::gizli( 'id', $aday->id );
		self::alan( 'aciklama', 'Yeni not', '', array( 'tur' => 'textarea', 'sinif' => 'genis', 'zorunlu' => true ) );
		echo '<div class="form-dugmeler"><button class="dugme ana" type="submit">Not ekle</button><button type="button" class="dugme" data-kapat>Kapat</button></div></form>';
		echo '<ul class="not-listesi">';
		foreach ( $notlar as $n ) {
			echo '<li><span class="soluk">' . esc_html( YP_Bicim::tarih_saat( $n->zaman ) . ' · ' . YP_Cekirdek::kullanici_adi( $n->kullanici_id ) ) . '</span>';
			self::kucuk_form( 'gorusme_sil', array( 'id' => $aday->id, 'gorusme_id' => $n->id ), 'Sil', 'dugme kucuk kirmizi', 'Not silinecek. Emin misiniz?' );
			echo '<p>' . nl2br( esc_html( $n->aciklama ) ) . '</p></li>';
		}
		echo '</ul></dialog>';
	}

	private static function diyaloglar( $aday ) {
		$hesaplar = YP_Veri::hesap_adlari( true );
		$islemler = self::islem_secenekleri( $aday );
		$turler   = array();
		foreach ( YP_Veri::tanimlar( 'islem_turu', true ) as $t ) {
			$turler[ $t->ad ] = $t->ad;
		}
		$turler['Diğer'] = 'Diğer';

		self::diyalog_ac( 'd-islem', 'İşlem' );
		self::form_ac( 'islem_kaydet' );
		self::gizli( 'id', $aday->id );
		self::gizli( 'islem_id', 0 );
		echo '<div class="izgara" data-islem-formu>';
		self::islem_alanlari( $aday );
		echo '</div><p class="soluk yazi-kucuk yalniz-duzenle">Kayıtlı işlemin ücreti buradan değişmez; tutar değişikliği için Ödeme Bilgileri ekranını kullanın.</p>';
		self::diyalog_kapat( 'Kaydet' );

		self::diyalog_ac( 'd-odeme-al', 'Ödeme Al' );
		self::form_ac( 'odeme_al' );
		self::gizli( 'id', $aday->id );
		echo '<p class="soluk">Tutar en eski vadeli borçtan başlayarak kapatılır.</p><div class="izgara">';
		self::tutar_alani( 'tutar', 'Alınan tutar', '', array( 'zorunlu' => true ) );
		self::secim( 'odeme_turu', 'Ödeme türü', YP_Veri::odeme_turleri(), 'NAKIT', array( 'zorunlu' => true, 'veri' => array( 'hesap-oner' => 1 ) ) );
		self::secim( 'hesap_id', 'Hesap', $hesaplar, YP_Veri::varsayilan_hesap( 'NAKIT' ), array( 'bos' => 'Otomatik' ) );
		self::tarih_alani( 'odeme_tarihi', 'Ödeme tarihi', YP_Cekirdek::bugun() );
		if ( $islemler ) {
			self::secim( 'islem_id', 'Sadece şu işlemin borcu', $islemler, '', array( 'bos' => 'Tüm borçlar' ) );
		}
		self::alan( 'aciklama', 'Açıklama', '', array( 'tur' => 'textarea', 'sinif' => 'genis' ) );
		echo '</div>';
		self::diyalog_kapat( 'Tahsil et' );

		self::diyalog_ac( 'd-tahsil', 'Tahsil Et' );
		self::form_ac( 'tahsil_et' );
		self::gizli( 'id', $aday->id );
		self::gizli( 'hareket_id', 0 );
		echo '<p class="satir-bilgi" data-alan="satir_bilgi"></p><div class="izgara">';
		self::tutar_alani( 'tutar', 'Tahsil edilen tutar', '', array( 'zorunlu' => true ) );
		self::secim( 'odeme_turu', 'Ödeme türü', YP_Veri::odeme_turleri(), 'NAKIT', array( 'zorunlu' => true, 'veri' => array( 'hesap-oner' => 1 ) ) );
		self::secim( 'hesap_id', 'Hesap', $hesaplar, YP_Veri::varsayilan_hesap( 'NAKIT' ), array( 'bos' => 'Otomatik' ) );
		self::tarih_alani( 'odeme_tarihi', 'Ödeme tarihi', YP_Cekirdek::bugun() );
		self::alan( 'aciklama', 'Açıklama', '', array( 'tur' => 'textarea', 'sinif' => 'genis' ) );
		echo '</div><p class="soluk yazi-kucuk">Tutarın bir kısmını girerseniz kalan kısım aynı vadeyle borç olarak kalır.</p>';
		self::diyalog_kapat( 'Tahsil et' );

		self::diyalog_ac( 'd-borc', 'Borç Ekle' );
		self::form_ac( 'borc_ekle' );
		self::gizli( 'id', $aday->id );
		echo '<div class="izgara">';
		self::secim( 'borc_tipi', 'Kalem', $turler, '', array( 'bos' => null ) );
		self::tutar_alani( 'tutar', 'Tutar', '', array( 'zorunlu' => true ) );
		self::tarih_alani( 'vade_tarihi', 'Vade tarihi', YP_Cekirdek::bugun() );
		if ( $islemler ) {
			self::secim( 'islem_id', 'Bağlı işlem', $islemler, '', array( 'bos' => 'Yok' ) );
		}
		self::alan( 'aciklama', 'Açıklama', '', array( 'tur' => 'textarea', 'sinif' => 'genis' ) );
		echo '</div>';
		self::diyalog_kapat( 'Ekle' );

		self::diyalog_ac( 'd-taksit', 'Ödeme Planı Oluştur' );
		self::form_ac( 'taksit_plani' );
		self::gizli( 'id', $aday->id );
		echo '<div class="izgara">';
		self::secim( 'borc_tipi', 'Kalem', $turler, '', array( 'bos' => null ) );
		self::tutar_alani( 'tutar', 'Toplam tutar', '', array( 'zorunlu' => true ) );
		self::alan( 'taksit_sayisi', 'Taksit sayısı', '3', array( 'tur' => 'number', 'min' => 1, 'max' => 36 ) );
		self::alan( 'aralik', 'Kaç ayda bir', '1', array( 'tur' => 'number', 'min' => 1, 'max' => 12 ) );
		self::tarih_alani( 'ilk_vade', 'İlk vade', YP_Cekirdek::bugun() );
		if ( $islemler ) {
			self::secim( 'islem_id', 'Bağlı işlem', $islemler, '', array( 'bos' => 'Yok' ) );
		}
		echo '</div>';
		self::diyalog_kapat( 'Planı oluştur' );

		self::diyalog_ac( 'd-iade', 'İade' );
		self::form_ac( 'iade_et' );
		self::gizli( 'id', $aday->id );
		echo '<p class="soluk">İade edilen para seçilen hesaptan çıkış olarak düşülür.</p><div class="izgara">';
		self::tutar_alani( 'tutar', 'İade tutarı', '', array( 'zorunlu' => true ) );
		self::secim( 'hesap_id', 'Paranın çıktığı hesap', $hesaplar, YP_Veri::varsayilan_hesap( 'NAKIT' ), array( 'zorunlu' => true ) );
		self::tarih_alani( 'odeme_tarihi', 'İade tarihi', YP_Cekirdek::bugun() );
		if ( $islemler ) {
			self::secim( 'islem_id', 'Bağlı işlem', $islemler, '', array( 'bos' => 'Yok' ) );
		}
		self::alan( 'aciklama', 'Açıklama', '', array( 'tur' => 'textarea', 'sinif' => 'genis' ) );
		echo '</div>';
		self::diyalog_kapat( 'İadeyi kaydet' );

		self::diyalog_ac( 'd-duzenle', 'Borç Satırını Düzelt' );
		self::form_ac( 'hareket_duzenle' );
		self::gizli( 'id', $aday->id );
		self::gizli( 'hareket_id', 0 );
		echo '<div class="izgara">';
		self::alan( 'borc_tipi', 'Kalem', '', array( 'maxlength' => 150 ) );
		self::tutar_alani( 'tutar', 'Tutar', '', array( 'zorunlu' => true ) );
		self::tarih_alani( 'vade_tarihi', 'Vade tarihi', '' );
		self::alan( 'aciklama', 'Açıklama', '', array( 'tur' => 'textarea', 'sinif' => 'genis' ) );
		echo '</div>';
		self::diyalog_kapat( 'Kaydet' );

		self::diyalog_ac( 'd-foto', 'Fotoğraf' );
		self::form_ac( 'aday_foto', 'foto-formu', true );
		self::gizli( 'id', $aday->id );
		?>
		<p class="soluk">Dosya seçin (telefonda kamera da açılır) veya bilgisayar kamerasıyla çekin. Fotoğraf otomatik küçültülür.</p>
		<label class="alan genis"><span>Resim dosyası</span><input type="file" name="foto" accept="image/*"></label>
		<div class="kamera" data-kamera hidden>
			<video data-kamera-video playsinline autoplay muted></video>
			<canvas data-kamera-tuval hidden></canvas>
		</div>
		<div class="form-dugmeler">
			<button type="submit" class="dugme ana">Yükle</button>
			<button type="button" class="dugme" data-kamera-ac>Kamerayı aç</button>
			<button type="button" class="dugme yesil" data-kamera-cek hidden>Çek ve kaydet</button>
			<button type="button" class="dugme" data-kapat>Vazgeç</button>
		</div>
		</form>
		<?php
		if ( $aday->foto ) {
			self::kucuk_form( 'aday_foto_sil', array( 'id' => $aday->id ), 'Fotoğrafı sil', 'dugme kucuk kirmizi', 'Fotoğraf kalıcı olarak silinecek. Emin misiniz?' );
		}
		echo '</dialog>';
	}
}
