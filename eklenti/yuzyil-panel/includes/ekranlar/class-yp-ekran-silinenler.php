<?php
defined( 'ABSPATH' ) || exit;

/**
 * Silinenler: silinen aday, işlem ve ödeme/kasa satırları; geri alma ve kalıcı silme.
 */
final class YP_Ekran_Silinenler extends YP_Ekran {

	public static function islemler() {
		return array(
			'geri_al_aday'     => 'geri_al_aday',
			'kalici_sil_aday'  => 'kalici_sil_aday',
			'geri_al_islem'    => 'geri_al_islem',
			'geri_al_hareket'  => 'geri_al_hareket',
			'kalici_sil_hareket' => 'kalici_sil_hareket',
		);
	}

	public static function goster() {
		global $wpdb;
		$sekme = YP_Guvenlik::secim( 'sekme', array( 'adaylar', 'islemler', 'hareketler' ), 'adaylar', 'get' );
		$a     = YP_Cekirdek::tablo( 'adaylar' );
		$i     = YP_Cekirdek::tablo( 'islemler' );
		$h     = YP_Cekirdek::tablo( 'hareketler' );

		$serit = function () {
			self::serit_grubu_ciz( 'Silinenler', array(
				array( 'Adaylar', array( 'ekran' => 'silinenler', 'sekme' => 'adaylar' ), 'aday-ekle' ),
				array( 'İşlemler', array( 'ekran' => 'silinenler', 'sekme' => 'islemler' ), 'liste' ),
				array( 'Ödeme Satırları', array( 'ekran' => 'silinenler', 'sekme' => 'hareketler' ), 'para' ),
			) );
			self::serit_grubu_ciz( 'Kayıtlar', array(
				array( 'İşlem Günlüğü', array( 'ekran' => 'gunluk' ), 'saat' ),
			) );
			self::serit_yon_grubu();
		};
		$adlar = array( 'adaylar' => 'Adaylar', 'islemler' => 'İşlemler', 'hareketler' => 'Ödeme ve kasa satırları' );
		self::uyg_basla( 'Silinenler', 'silinenler', 'kaydir', $serit, '<span class="vurgu">' . esc_html( $adlar[ $sekme ] ) . '</span><span>Geri alınabilir</span>', 'ust-yok' );
		echo '<nav class="sekmeler">';
		foreach ( array( 'adaylar' => 'Adaylar', 'islemler' => 'İşlemler', 'hareketler' => 'Ödeme ve kasa satırları' ) as $k => $ad ) {
			echo '<a class="' . ( $k === $sekme ? 'aktif' : '' ) . '" href="' . esc_url( YP_Cekirdek::panel_url( array( 'ekran' => 'silinenler', 'sekme' => $k ) ) ) . '">' . esc_html( $ad ) . '</a>';
		}
		echo '</nav><section class="kutu"><div class="tablo-kap"><table class="tablo">';

		if ( 'adaylar' === $sekme ) {
			$liste = $wpdb->get_results( "SELECT * FROM {$a} WHERE silindi = 1 ORDER BY silme_zamani DESC LIMIT 500" ); // phpcs:ignore
			echo '<thead><tr><th>No</th><th>Aday</th><th>Silinme</th><th>Silen</th><th></th></tr></thead><tbody>';
			if ( ! $liste ) {
				self::bos_liste( 'Silinmiş aday yok.', 5 );
			}
			foreach ( $liste as $x ) {
				echo '<tr><td>' . (int) $x->aday_no . '</td><td>' . self::aday_linki( $x->id, $x->adi . ' ' . $x->soyadi ) . '</td><td>' . esc_html( YP_Bicim::tarih_saat( $x->silme_zamani ) ) . '</td><td>' . esc_html( YP_Cekirdek::kullanici_adi( $x->silen ) ) . '</td><td class="islemler-sutun">'; // phpcs:ignore
				self::kucuk_form( 'geri_al_aday', array( 'id' => $x->id ), 'Geri al', 'dugme kucuk' );
				self::kucuk_form( 'kalici_sil_aday', array( 'id' => $x->id ), 'Kalıcı sil', 'dugme kucuk kirmizi', 'Aday, işlemleri, ödenmemiş borçları, notları ve FOTOĞRAFLARI kalıcı olarak silinecek. Alınmış ödemeler kasada isimsiz olarak kalır. Bu işlem geri alınamaz. Emin misiniz?' );
				echo '</td></tr>';
			}
		} elseif ( 'islemler' === $sekme ) {
			$turler = YP_Veri::tanim_adlari( 'islem_turu' );
			$liste  = $wpdb->get_results( "SELECT i.*, a.adi, a.soyadi, a.aday_no FROM {$i} i INNER JOIN {$a} a ON a.id = i.aday_id WHERE i.silindi = 1 ORDER BY i.silme_zamani DESC LIMIT 500" ); // phpcs:ignore
			echo '<thead><tr><th>İşlem tarihi</th><th>Aday</th><th>İşlem</th><th class="sag">Ücret</th><th>Silinme</th><th>Silen</th><th></th></tr></thead><tbody>';
			if ( ! $liste ) {
				self::bos_liste( 'Silinmiş işlem yok.', 7 );
			}
			foreach ( $liste as $x ) {
				echo '<tr><td>' . esc_html( YP_Bicim::tarih( $x->islem_tarihi ) ) . '</td><td>' . self::aday_linki( $x->aday_id, '#' . $x->aday_no . ' ' . $x->adi . ' ' . $x->soyadi ) . '</td><td>' . esc_html( isset( $turler[ (int) $x->islem_turu_id ] ) ? $turler[ (int) $x->islem_turu_id ] : '' ) . '</td>' . self::tutar_hucre( $x->ucret ) . '<td>' . esc_html( YP_Bicim::tarih_saat( $x->silme_zamani ) ) . '</td><td>' . esc_html( YP_Cekirdek::kullanici_adi( $x->silen ) ) . '</td><td>'; // phpcs:ignore
				self::kucuk_form( 'geri_al_islem', array( 'id' => $x->id ), 'Geri al', 'dugme kucuk' );
				echo '</td></tr>';
			}
		} else {
			$liste = $wpdb->get_results( "SELECT h.*, a.adi, a.soyadi, a.aday_no FROM {$h} h LEFT JOIN {$a} a ON a.id = h.aday_id WHERE h.silindi = 1 ORDER BY h.silme_zamani DESC LIMIT 500" ); // phpcs:ignore
			echo '<thead><tr><th>Tarih</th><th>Tür</th><th>Aday / açıklama</th><th>Durum</th><th class="sag">Tutar</th><th>Silinme</th><th>Silen</th><th></th></tr></thead><tbody>';
			if ( ! $liste ) {
				self::bos_liste( 'Silinmiş satır yok.', 8 );
			}
			foreach ( $liste as $x ) {
				$x->referans_adi = null;
				$x->referans_unvani = null;
				$x->kalem_adi = null;
				$metin = $x->aday_no ? '#' . $x->aday_no . ' ' . $x->adi . ' ' . $x->soyadi . ' · ' . $x->borc_tipi : (string) $x->borc_tipi;
				echo '<tr><td>' . esc_html( YP_Bicim::tarih( $x->odeme_tarihi ? $x->odeme_tarihi : $x->vade_tarihi ) ) . '</td><td>' . esc_html( YP_Ekran_Kasa::tur_etiketi( $x ) ) . '</td><td class="yazi-kucuk">' . esc_html( $metin . ( $x->aciklama ? ' · ' . $x->aciklama : '' ) ) . '</td><td>' . self::odeme_rozeti( $x->durum ) . '</td>' . self::tutar_hucre( $x->tutar ) . '<td>' . esc_html( YP_Bicim::tarih_saat( $x->silme_zamani ) ) . '</td><td>' . esc_html( YP_Cekirdek::kullanici_adi( $x->silen ) ) . '</td><td class="islemler-sutun">'; // phpcs:ignore
				self::kucuk_form( 'geri_al_hareket', array( 'id' => $x->id ), 'Geri al', 'dugme kucuk' );
				self::kucuk_form( 'kalici_sil_hareket', array( 'id' => $x->id ), 'Kalıcı sil', 'dugme kucuk kirmizi', 'Satır kalıcı olarak silinecek. Emin misiniz?' );
				echo '</td></tr>';
			}
		}
		echo '</tbody></table></div></section>';
		self::uyg_bitir();
	}

	public static function geri_al_aday() {
		$aday = YP_Veri::aday( YP_Guvenlik::tamsayi( 'id' ) );
		if ( ! $aday || ! $aday->silindi ) {
			self::geri_don( 'Aday bulunamadı.', 'hata' );
		}
		YP_Veri::aday_guncelle( $aday->id, array( 'silindi' => 0, 'silme_zamani' => null, 'silen' => null ) );
		YP_Cekirdek::log( 'aday', $aday->id, 'geri alma', 'Aday silinenlerden geri alındı.' );
		self::yonlendir( array( 'ekran' => 'aday', 'id' => (int) $aday->id ), 'Aday geri alındı.' );
	}

	// KURAL: Kalıcı silmede ödenmiş/iade satırları silinmez, isimsizleştirilir — kasa bakiyeleri geçmişe dönük değişmez.
	public static function kalici_sil_aday() {
		global $wpdb;
		$aday = YP_Veri::aday( YP_Guvenlik::tamsayi( 'id' ) );
		if ( ! $aday || ! $aday->silindi ) {
			self::geri_don( 'Yalnızca silinenlerdeki aday kalıcı silinebilir.', 'hata' );
		}
		$h = YP_Cekirdek::tablo( 'hareketler' );
		// KURAL: Veritabanındaki tüm silme/isimsizleştirme tek transaction'dır; fotoğraf dosyaları ancak başarıyla bittikten sonra silinir.
		self::tek_islemde( function () use ( $wpdb, $h, $aday ) {
			YP_Veri::yazildi_mi( $wpdb->query( $wpdb->prepare( "DELETE FROM {$h} WHERE aday_id = %d AND durum = 'ODENMEDI'", $aday->id ) ) );
			YP_Veri::yazildi_mi( $wpdb->query( $wpdb->prepare(
				"UPDATE {$h} SET aday_id = NULL, islem_id = NULL, aciklama = %s WHERE aday_id = %d",
				'Kalıcı silinen aday #' . (int) $aday->aday_no,
				$aday->id
			) ) );
			YP_Veri::yazildi_mi( $wpdb->delete( YP_Cekirdek::tablo( 'islemler' ), array( 'aday_id' => $aday->id ), array( '%d' ) ) );
			YP_Veri::yazildi_mi( $wpdb->delete( YP_Cekirdek::tablo( 'gorusmeler' ), array( 'aday_id' => $aday->id ), array( '%d' ) ) );
			// KURAL: Kalıcı silinen adayın kişisel bilgi içeren günlük satırları da silinir — yalnızca aday no izi kalır.
			YP_Veri::yazildi_mi( $wpdb->delete( YP_Cekirdek::tablo( 'log' ), array( 'bolum' => 'aday', 'kayit_id' => $aday->id ), array( '%s', '%d' ) ) );
			YP_Veri::yazildi_mi( $wpdb->delete( YP_Cekirdek::tablo( 'adaylar' ), array( 'id' => $aday->id ), array( '%d' ) ) );
			YP_Cekirdek::log( 'silinenler', 0, 'kalıcı silme', 'Aday #' . (int) $aday->aday_no . ' kalıcı olarak silindi.' );
		} );
		YP_Foto::aday_fotolarini_sil( $aday );
		self::geri_don( 'Aday kalıcı olarak silindi.' );
	}

	// KURAL: İşlem geri alınınca onunla aynı anda silinen ödenmemiş borçlar da geri gelir.
	public static function geri_al_islem() {
		global $wpdb;
		$islem = YP_Veri::islem( YP_Guvenlik::tamsayi( 'id' ) );
		if ( ! $islem || ! $islem->silindi ) {
			self::geri_don( 'İşlem bulunamadı.', 'hata' );
		}
		$h = YP_Cekirdek::tablo( 'hareketler' );
		self::tek_islemde( function () use ( $wpdb, $h, $islem ) {
			YP_Veri::yazildi_mi( $wpdb->query( $wpdb->prepare(
				"UPDATE {$h} SET silindi = 0, silme_zamani = NULL, silen = NULL WHERE islem_id = %d AND silindi = 1 AND durum = 'ODENMEDI' AND silme_zamani = %s",
				$islem->id,
				$islem->silme_zamani
			) ) );
			YP_Veri::yazildi_mi( $wpdb->update( YP_Cekirdek::tablo( 'islemler' ), array( 'silindi' => 0, 'silme_zamani' => null, 'silen' => null ), array( 'id' => $islem->id ) ) );
			YP_Cekirdek::log( 'aday', $islem->aday_id, 'geri alma', 'İşlem geri alındı (#' . $islem->id . ').' );
		} );
		self::geri_don( 'İşlem geri alındı.' );
	}

	public static function geri_al_hareket() {
		$s = YP_Veri::hareket( YP_Guvenlik::tamsayi( 'id' ) );
		if ( ! $s || ! $s->silindi ) {
			self::geri_don( 'Satır bulunamadı.', 'hata' );
		}
		self::tek_islemde( function () use ( $s ) {
			YP_Veri::hareket_guncelle( $s->id, array( 'silindi' => 0, 'silme_zamani' => null, 'silen' => null ) );
			YP_Cekirdek::log( $s->aday_id ? 'aday' : 'kasa', $s->aday_id ? $s->aday_id : $s->id, 'geri alma', 'Satır geri alındı: ' . $s->borc_tipi . ' ' . YP_Bicim::tl( $s->tutar ) );
		} );
		self::geri_don( 'Satır geri alındı.' );
	}

	public static function kalici_sil_hareket() {
		global $wpdb;
		$s = YP_Veri::hareket( YP_Guvenlik::tamsayi( 'id' ) );
		if ( ! $s || ! $s->silindi ) {
			self::geri_don( 'Yalnızca silinenlerdeki satır kalıcı silinebilir.', 'hata' );
		}
		self::tek_islemde( function () use ( $wpdb, $s ) {
			YP_Veri::yazildi_mi( $wpdb->delete( YP_Cekirdek::tablo( 'hareketler' ), array( 'id' => $s->id ), array( '%d' ) ) );
			YP_Cekirdek::veri_degisti();
			YP_Cekirdek::log( $s->aday_id ? 'aday' : 'kasa', $s->aday_id ? $s->aday_id : $s->id, 'kalıcı silme', 'Satır kalıcı silindi: ' . $s->borc_tipi . ' ' . YP_Bicim::tl( $s->tutar ) );
		} );
		self::geri_don( 'Satır kalıcı olarak silindi.' );
	}
}
