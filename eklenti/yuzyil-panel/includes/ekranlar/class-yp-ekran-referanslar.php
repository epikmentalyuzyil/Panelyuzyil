<?php
defined( 'ABSPATH' ) || exit;

/**
 * Referans kartları: kimden gelindiği, getirilen işlemler, ciro, referansa yapılan ödemeler.
 */
final class YP_Ekran_Referanslar extends YP_Ekran {

	public static function islemler() {
		return array(
			'referans_kaydet' => 'kaydet',
			'referans_durum'  => 'durum_degistir',
			'referans_sil'    => 'sil',
			'referans_odeme'  => 'odeme_yap',
		);
	}

	public static function goster() {
		if ( YP_Guvenlik::tamsayi( 'yeni', 'get' ) ) {
			$serit = function () {
				self::serit_grubu_ciz( 'Referans', array(
					array( 'Referans Listesi', array( 'ekran' => 'referanslar' ), 'referans' ),
				) );
				self::serit_yon_grubu();
			};
			self::uyg_basla( 'Yeni Referans', 'referanslar', 'kaydir', $serit, '', 'ust-yok' );
			echo '<section class="kutu">';
			self::form( null );
			echo '</section>';
			self::uyg_bitir();
			return;
		}
		$id = YP_Guvenlik::tamsayi( 'id', 'get' );
		if ( $id ) {
			self::kart( $id );
			return;
		}
		// KURAL: Referans listesi artık "Tanımlar ve Ayarlar" ekranındadır — eski adres oraya götürür, liste iki yerde durmaz.
		self::yonlendir( array( 'ekran' => 'tanimlar', 'sekme' => 'referanslar' ) );
	}

	// KURAL: Referans ciro ve adetleri işlemdeki referansa göre hesaplanır; iptal edilen işlemler sayılmaz.
	// KURAL: Bu iki alt sorgu "Tanımlar ve Ayarlar" ekranındaki referans listesinde de kullanılır — sorgu iki yerde yazılmaz.
	public static function istatistik_alt_sorgusu() {
		$i = YP_Cekirdek::tablo( 'islemler' );
		$a = YP_Cekirdek::tablo( 'adaylar' );
		return "SELECT i.referans_id, COUNT(*) AS islem_sayisi, COUNT(DISTINCT i.aday_id) AS aday_sayisi, SUM(i.ucret) AS ciro, MAX(i.islem_tarihi) AS son_tarih
			FROM {$i} i INNER JOIN {$a} a ON a.id = i.aday_id AND a.silindi = 0
			WHERE i.silindi = 0 AND i.durum <> 'iptal' AND i.referans_id IS NOT NULL GROUP BY i.referans_id";
	}

	public static function odeme_alt_sorgusu() {
		$h = YP_Cekirdek::tablo( 'hareketler' );
		return "SELECT referans_id, SUM(tutar) AS odenen FROM {$h} WHERE silindi = 0 AND kayit_turu = 'GIDER' AND durum = 'ODENDI' AND referans_id IS NOT NULL GROUP BY referans_id";
	}

	// KURAL: Referans listesi "Tanımlar ve Ayarlar" ekranındadır; bu ekran yalnızca kart ve yeni kayıt formunu çizer.

	private static function form( $r ) {
		self::form_ac( 'referans_kaydet' );
		self::gizli( 'id', $r ? $r->id : 0 );
		echo '<div class="izgara">';
		self::alan( 'ad_soyad', 'Ad soyad', $r ? $r->ad_soyad : '', array( 'zorunlu' => true, 'maxlength' => 200 ) );
		self::alan( 'unvan', 'Firma / unvan', $r ? $r->unvan : '', array( 'maxlength' => 200 ) );
		self::alan( 'gsm', 'Cep telefonu', $r ? YP_Bicim::telefon( $r->gsm ) : '', array( 'tur' => 'tel', 'inputmode' => 'tel' ) );
		self::alan( 'telefon', 'Sabit telefon', $r ? YP_Bicim::telefon( $r->telefon ) : '', array( 'tur' => 'tel', 'inputmode' => 'tel' ) );
		self::alan( 'e_posta', 'E-posta', $r ? $r->e_posta : '', array( 'tur' => 'email' ) );
		self::alan( 'adres', 'Adres', $r ? $r->adres : '', array( 'maxlength' => 500, 'sinif' => 'genis' ) );
		self::alan( 'notlar', 'Notlar', $r ? $r->notlar : '', array( 'tur' => 'textarea', 'sinif' => 'genis' ) );
		echo '</div><div class="form-dugmeler"><button type="submit" class="dugme ana">Kaydet</button></div></form>';
	}

	private static function kart( $id ) {
		global $wpdb;
		$r = YP_Veri::referans( $id );
		if ( ! $r ) {
			self::yonlendir( array( 'ekran' => 'referanslar' ), 'Referans bulunamadı.', 'hata' );
		}
		$bas = YP_Guvenlik::tarih( 'bas', 'get' );
		$bit = YP_Guvenlik::tarih( 'bit', 'get' );
		$bas = $bas ? $bas : substr( YP_Cekirdek::bugun(), 0, 4 ) . '-01-01';
		$bit = $bit ? $bit : YP_Cekirdek::bugun();

		$i  = YP_Cekirdek::tablo( 'islemler' );
		$a  = YP_Cekirdek::tablo( 'adaylar' );
		$h  = YP_Cekirdek::tablo( 'hareketler' );
		$islemler = $wpdb->get_results( $wpdb->prepare(
			"SELECT i.*, a.adi, a.soyadi, a.aday_no FROM {$i} i INNER JOIN {$a} a ON a.id = i.aday_id AND a.silindi = 0
			WHERE i.silindi = 0 AND i.referans_id = %d AND i.islem_tarihi BETWEEN %s AND %s ORDER BY i.islem_tarihi DESC, i.id DESC LIMIT 500",
			$r->id, $bas, $bit
		) );
		$aylik = $wpdb->get_results( $wpdb->prepare(
			"SELECT SUBSTRING(i.islem_tarihi, 1, 7) AS ay, COUNT(*) AS adet, SUM(i.ucret) AS ciro FROM {$i} i INNER JOIN {$a} a ON a.id = i.aday_id AND a.silindi = 0
			WHERE i.silindi = 0 AND i.durum <> 'iptal' AND i.referans_id = %d AND i.islem_tarihi BETWEEN %s AND %s GROUP BY SUBSTRING(i.islem_tarihi, 1, 7) ORDER BY ay DESC",
			$r->id, $bas, $bit
		) );
		$odemeler = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$h} WHERE silindi = 0 AND kayit_turu = 'GIDER' AND referans_id = %d AND odeme_tarihi BETWEEN %s AND %s ORDER BY odeme_tarihi DESC, id DESC",
			$r->id, $bas, $bit
		) );
		$turler   = YP_Veri::tanim_adlari( 'islem_turu' );
		$hesaplar = YP_Veri::hesap_adlari();
		$ciro     = 0.0;
		$adet     = 0;
		foreach ( $aylik as $m ) {
			$ciro += (float) $m->ciro;
			$adet += (int) $m->adet;
		}
		$odenen = array_sum( array_map( function ( $o ) {
			return (float) $o->tutar;
		}, $odemeler ) );

		$serit = function () use ( $r ) {
			self::serit_grubu_ciz( 'Referans', array(
				array( 'Ödeme Yap', 'd-ref-odeme', 'para', 'serit-yesil serit-birincil' ),
				array( 'Düzenle', 'd-ref-duzenle', 'duzenle' ),
				array( 'Adayları', array( 'ekran' => 'adaylar', 'referans' => (int) $r->id ), 'arama' ),
			) );
			self::serit_grubu_ciz( 'Liste', array(
				array( 'Referans Listesi', array( 'ekran' => 'referanslar' ), 'referans' ),
			) );
			self::serit_yon_grubu();
		};
		$ad_sagi = '<span class="vurgu">Ciro: <b>' . esc_html( YP_Bicim::tl( $ciro ) ) . '</b></span><span>Ödenen: ' . esc_html( YP_Bicim::tl( $odenen ) ) . '</span>';
		self::uyg_basla( YP_Veri::referans_adi( $r ), 'referanslar', 'kaydir', $serit, $ad_sagi, 'ust-yok' );
		if ( ! $r->aktif ) {
			echo '<div class="uyari uyari-uyari">Bu referans pasif; yeni aday formunda listelenmez.</div>';
		}
		?>
		<form class="filtre kutu" method="get" action="<?php echo esc_url( YP_Cekirdek::panel_url() ); ?>">
			<input type="hidden" name="ekran" value="referanslar"><input type="hidden" name="id" value="<?php echo (int) $r->id; ?>">
			<?php self::tarih_alani( 'bas', 'Başlangıç', $bas ); ?>
			<?php self::tarih_alani( 'bit', 'Bitiş', $bit ); ?>
			<div class="filtre-dugmeler"><button type="submit" class="dugme ana">Göster</button></div>
		</form>
		<section class="kartlar">
			<div class="kart"><span>Getirdiği işlem</span><strong><?php echo (int) $adet; ?></strong></div>
			<div class="kart"><span>Ciro</span><strong><?php echo esc_html( YP_Bicim::tl( $ciro ) ); ?></strong></div>
			<div class="kart"><span>Referansa ödenen</span><strong><?php echo esc_html( YP_Bicim::tl( $odenen ) ); ?></strong></div>
			<div class="kart"><span>Telefon</span><strong class="yazi-orta"><?php echo esc_html( YP_Bicim::telefon( $r->gsm ? $r->gsm : $r->telefon ) ); ?></strong></div>
		</section>
		<div class="iki-sutun">
			<section class="kutu">
				<h2>Aylara göre</h2>
				<table class="tablo"><thead><tr><th>Ay</th><th class="sag">İşlem</th><th class="sag">Ciro</th></tr></thead><tbody>
				<?php
				if ( ! $aylik ) {
					self::bos_liste( 'Bu tarih aralığında işlem yok.', 3 );
				}
				foreach ( $aylik as $m ) {
					echo '<tr><td>' . esc_html( YP_Bicim::ay_adi( (int) substr( $m->ay, 5, 2 ) ) . ' ' . substr( $m->ay, 0, 4 ) ) . '</td><td class="sag">' . (int) $m->adet . '</td>' . self::tutar_hucre( $m->ciro ) . '</tr>'; // phpcs:ignore
				}
				?>
				</tbody></table>
			</section>
			<section class="kutu">
				<h2>Referansa yapılan ödemeler</h2>
				<table class="tablo"><thead><tr><th>Tarih</th><th>Hesap</th><th>Açıklama</th><th class="sag">Tutar</th></tr></thead><tbody>
				<?php
				if ( ! $odemeler ) {
					self::bos_liste( 'Ödeme yok.', 4 );
				}
				foreach ( $odemeler as $o ) {
					echo '<tr><td>' . esc_html( YP_Bicim::tarih( $o->odeme_tarihi ) ) . '</td><td>' . esc_html( isset( $hesaplar[ (int) $o->hesap_id ] ) ? $hesaplar[ (int) $o->hesap_id ] : '' ) . '</td><td class="yazi-kucuk">' . esc_html( $o->aciklama ) . '</td>' . self::tutar_hucre( $o->tutar ) . '</tr>'; // phpcs:ignore
				}
				?>
				</tbody></table>
			</section>
		</div>
		<section class="kutu">
			<h2>Getirdiği adaylar ve işlemler</h2>
			<div class="tablo-kap"><table class="tablo">
				<thead><tr><th>Tarih</th><th>No</th><th>Aday</th><th>İşlem</th><th>Durum</th><th class="sag">Ücret</th></tr></thead><tbody>
				<?php
				if ( ! $islemler ) {
					self::bos_liste( 'Bu tarih aralığında işlem yok.', 6 );
				}
				foreach ( $islemler as $x ) :
					?>
					<tr>
						<td><?php echo esc_html( YP_Bicim::tarih( $x->islem_tarihi ) ); ?></td>
						<td><?php echo (int) $x->aday_no; ?></td>
						<td><?php echo self::aday_linki( $x->aday_id, $x->adi . ' ' . $x->soyadi ); // phpcs:ignore ?></td>
						<td><?php echo esc_html( isset( $turler[ (int) $x->islem_turu_id ] ) ? $turler[ (int) $x->islem_turu_id ] : '' ); ?></td>
						<td><?php echo self::islem_rozeti( $x->durum ); // phpcs:ignore ?></td>
						<?php echo self::tutar_hucre( $x->ucret ); // phpcs:ignore ?>
					</tr>
				<?php endforeach; ?>
			</tbody></table></div>
		</section>
		<div class="dugme-grubu">
			<?php
			self::kucuk_form( 'referans_durum', array( 'id' => $r->id ), $r->aktif ? 'Pasife al' : 'Aktif yap', 'dugme' );
			self::kucuk_form( 'referans_sil', array( 'id' => $r->id ), 'Sil', 'dugme kirmizi', 'Referans silinecek. Emin misiniz?' );
			?>
		</div>

		<dialog id="d-ref-duzenle" class="pencere"><div class="pencere-baslik"><h2>Referansı düzenle</h2><button type="button" class="kapat" data-kapat aria-label="Kapat">×</button></div>
			<?php self::form( $r ); ?>
		</dialog>
		<dialog id="d-ref-odeme" class="pencere"><div class="pencere-baslik"><h2>Referansa ödeme</h2><button type="button" class="kapat" data-kapat aria-label="Kapat">×</button></div>
			<?php
			self::form_ac( 'referans_odeme' );
			self::gizli( 'id', $r->id );
			echo '<p class="soluk">Ödeme seçilen hesaptan gider olarak düşülür ve bu kartta görünür.</p><div class="izgara">';
			self::tutar_alani( 'tutar', 'Tutar', '', array( 'zorunlu' => true ) );
			self::secim( 'hesap_id', 'Hesap', YP_Veri::hesap_adlari( true ), YP_Veri::varsayilan_hesap( 'NAKIT' ), array( 'zorunlu' => true ) );
			self::tarih_alani( 'odeme_tarihi', 'Tarih', YP_Cekirdek::bugun() );
			self::secim( 'kalem_id', 'Gider kalemi', YP_Veri::tanim_adlari( 'gider_kalemi', true ), self::referans_kalemi(), array( 'bos' => 'Yok' ) );
			self::alan( 'aciklama', 'Açıklama', '', array( 'tur' => 'textarea', 'sinif' => 'genis' ) );
			echo '</div><div class="form-dugmeler"><button type="submit" class="dugme ana">Kaydet</button><button type="button" class="dugme" data-kapat>Vazgeç</button></div></form>';
			?>
		</dialog>
		<?php
		self::uyg_bitir();
	}

	private static function referans_kalemi() {
		foreach ( YP_Veri::tanimlar( 'gider_kalemi', true ) as $t ) {
			if ( false !== strpos( YP_Bicim::katla( $t->ad ), 'referans' ) ) {
				return (int) $t->id;
			}
		}
		return 0;
	}

	// ---- İşlemler -------------------------------------------------------

	public static function kaydet() {
		global $wpdb;
		$id = YP_Guvenlik::tamsayi( 'id' );
		$v  = array(
			'ad_soyad' => YP_Guvenlik::metin( 'ad_soyad', 'post', 200 ),
			'unvan'    => YP_Guvenlik::metin( 'unvan', 'post', 200 ),
			'gsm'      => YP_Bicim::telefon_temizle( YP_Guvenlik::metin( 'gsm', 'post', 30 ) ),
			'telefon'  => YP_Bicim::telefon_temizle( YP_Guvenlik::metin( 'telefon', 'post', 30 ) ),
			'e_posta'  => sanitize_email( YP_Guvenlik::metin( 'e_posta', 'post', 190 ) ),
			'adres'    => YP_Guvenlik::metin( 'adres', 'post', 500 ),
			'notlar'   => YP_Guvenlik::uzun_metin( 'notlar' ),
		);
		// KURAL: Referansın ad soyadı zorunludur — masaüstünde de REF_ADI_SOYADI boş geçilemiyordu.
		if ( '' === $v['ad_soyad'] ) {
			self::geri_don( 'Ad soyad zorunludur.', 'hata' );
		}
		$v['arama_metni'] = substr( YP_Bicim::katla( implode( ' ', array( $v['ad_soyad'], $v['unvan'], $v['gsm'], $v['telefon'] ) ) ), 0, 500 );
		$t = YP_Cekirdek::tablo( 'referanslar' );
		if ( $id && YP_Veri::referans( $id ) ) {
			$wpdb->update( $t, $v, array( 'id' => $id ) );
			YP_Cekirdek::log( 'referans', $id, 'güncelleme', 'Referans güncellendi: ' . $v['ad_soyad'] );
			self::yonlendir( array( 'ekran' => 'referanslar', 'id' => $id ), 'Referans kaydedildi.' );
		}
		$v['aktif']     = 1;
		$v['olusturma'] = YP_Cekirdek::simdi();
		$wpdb->insert( $t, $v );
		$id = (int) $wpdb->insert_id;
		YP_Cekirdek::log( 'referans', $id, 'ekleme', 'Yeni referans: ' . $v['ad_soyad'] );
		self::yonlendir( array( 'ekran' => 'referanslar', 'id' => $id ), 'Referans eklendi.' );
	}

	public static function durum_degistir() {
		global $wpdb;
		$r = YP_Veri::referans( YP_Guvenlik::tamsayi( 'id' ) );
		if ( ! $r ) {
			self::yonlendir( array( 'ekran' => 'referanslar' ), 'Referans bulunamadı.', 'hata' );
		}
		$wpdb->update( YP_Cekirdek::tablo( 'referanslar' ), array( 'aktif' => $r->aktif ? 0 : 1 ), array( 'id' => $r->id ) );
		YP_Cekirdek::log( 'referans', $r->id, 'durum', $r->aktif ? 'Pasife alındı.' : 'Aktif yapıldı.' );
		self::yonlendir( array( 'ekran' => 'referanslar', 'id' => $r->id ), 'Referans durumu değişti.' );
	}

	// KURAL: Adaya, işleme veya ödemeye bağlı referans silinmez, pasife alınır — geçmiş raporlar bozulmaz.
	public static function sil() {
		global $wpdb;
		$r = YP_Veri::referans( YP_Guvenlik::tamsayi( 'id' ) );
		if ( ! $r ) {
			self::yonlendir( array( 'ekran' => 'referanslar' ), 'Referans bulunamadı.', 'hata' );
		}
		$kullanim = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . YP_Cekirdek::tablo( 'adaylar' ) . ' WHERE referans_id = %d', $r->id ) )
			+ (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . YP_Cekirdek::tablo( 'islemler' ) . ' WHERE referans_id = %d', $r->id ) )
			+ (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . YP_Cekirdek::tablo( 'hareketler' ) . ' WHERE referans_id = %d', $r->id ) );
		if ( $kullanim > 0 ) {
			self::yonlendir( array( 'ekran' => 'referanslar', 'id' => $r->id ), 'Bu referansa bağlı kayıtlar var; silinemez. Bunun yerine "Pasife al" kullanın.', 'hata' );
		}
		$wpdb->delete( YP_Cekirdek::tablo( 'referanslar' ), array( 'id' => $r->id ), array( '%d' ) );
		YP_Cekirdek::log( 'referans', $r->id, 'silme', 'Referans silindi: ' . $r->ad_soyad );
		self::yonlendir( array( 'ekran' => 'referanslar' ), 'Referans silindi.' );
	}

	public static function odeme_yap() {
		$r     = YP_Veri::referans( YP_Guvenlik::tamsayi( 'id' ) );
		$tutar = YP_Guvenlik::tutar( 'tutar' );
		$hesap = YP_Veri::hesap( YP_Guvenlik::tamsayi( 'hesap_id' ) );
		$tarih = YP_Guvenlik::tarih( 'odeme_tarihi' );
		if ( ! $r ) {
			self::yonlendir( array( 'ekran' => 'referanslar' ), 'Referans bulunamadı.', 'hata' );
		}
		if ( $tutar <= 0 || ! $hesap ) {
			self::yonlendir( array( 'ekran' => 'referanslar', 'id' => $r->id ), 'Tutar ve hesap zorunludur.', 'hata' );
		}
		$kalem      = YP_Veri::tanim( YP_Guvenlik::tamsayi( 'kalem_id' ) );
		$tur_harita = array( 'NAKIT' => 'NAKIT', 'BANKA' => 'HAVALE', 'POSTA' => 'PTT' );
		$aciklama   = YP_Guvenlik::uzun_metin( 'aciklama' );
		$hedef      = array( 'ekran' => 'referanslar', 'id' => $r->id );
		// KURAL: Referans ödemesinin kasa satırı ve günlük kaydı birlikte yazılır ya da hiç yazılmaz.
		self::tek_islemde( function () use ( $r, $hesap, $kalem, $tutar, $tarih, $aciklama, $tur_harita ) {
			YP_Veri::hareket_ekle( array(
				'kayit_turu'    => 'GIDER',
				'referans_id'   => $r->id,
				'hesap_id'      => $hesap->id,
				'kalem_id'      => ( $kalem && 'gider_kalemi' === $kalem->tur ) ? $kalem->id : null,
				'borc_tipi'     => 'Referans ödemesi',
				'tutar'         => $tutar,
				'durum'         => 'ODENDI',
				'odeme_turu'    => isset( $tur_harita[ $hesap->tur ] ) ? $tur_harita[ $hesap->tur ] : 'NAKIT',
				'odeme_tarihi'  => $tarih ? $tarih : YP_Cekirdek::bugun(),
				'aciklama'      => $aciklama,
				'tahsil_eden'   => YP_Cekirdek::kullanici_id(),
				'tahsil_zamani' => YP_Cekirdek::simdi(),
			) );
			YP_Cekirdek::log( 'referans', $r->id, 'ödeme', 'Referansa ödeme: ' . YP_Bicim::tl( $tutar ) . ' (' . $hesap->ad . ')' );
		}, $hedef );
		self::yonlendir( $hedef, 'Ödeme kaydedildi ve kasadan düşüldü.' );
	}
}
