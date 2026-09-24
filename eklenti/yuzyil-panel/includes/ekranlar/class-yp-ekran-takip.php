<?php
defined( 'ABSPATH' ) || exit;

/**
 * Takip listeleri: geciken ve yaklaşan ödemeler, geçerliliği dolacak raporlar, eksik evrak, doğum günü, çift kayıt.
 * KURAL: Her liste tek sorgudur ve Excel'e aktarılabilir.
 */
final class YP_Ekran_Takip extends YP_Ekran {

	public static function listeler() {
		return array(
			'geciken'     => 'Geciken Ödemeler',
			'bugun'       => 'Bugün Vadesi Gelenler',
			'yaklasan'    => 'Vadesi Yaklaşanlar',
			'gecerlilik'  => 'Geçerliliği Dolacak Raporlar',
			'evrak'       => 'Eksik Evrak',
			'dogumgunu'   => 'Doğum Günleri',
			'cift'        => 'Çift Kayıt Şüphesi',
		);
	}

	// KURAL: Ekranda en fazla 150 satır gösterilir; tamamı Excel'e aktarılır — tarayıcı binlerce satırla yavaşlamaz.
	const EKRAN_SINIRI = 150;

	public static function goster() {
		$liste = YP_Guvenlik::secim( 'liste', array_keys( self::listeler() ), 'geciken', 'get' );
		$excel = 'excel' === YP_Guvenlik::metin( 'cikti', 'get', 10 );
		$veri  = self::veri( $liste, $excel ? 5000 : self::EKRAN_SINIRI );

		if ( $excel ) {
			YP_Excel::indir( 'takip-' . $liste, $veri['basliklar'], self::excel_satirlari( $veri ), $veri['turler'], self::listeler()[ $liste ] );
		}

		$serit = function () use ( $liste ) {
			self::serit_grubu_ciz( 'Listeler', array(
				array( 'Gecikenler', array( 'ekran' => 'takip', 'liste' => 'geciken' ), 'uyari', 'serit-kirmizi' ),
				array( 'Yaklaşanlar', array( 'ekran' => 'takip', 'liste' => 'yaklasan' ), 'saat' ),
				array( 'Eksik Evrak', array( 'ekran' => 'takip', 'liste' => 'evrak' ), 'rapor' ),
			) );
			self::serit_grubu_ciz( 'Çıktı', array(
				array( 'Excel', array( 'ekran' => 'takip', 'liste' => $liste, 'cikti' => 'excel' ), 'rapor' ),
				array( 'Yazdır', '', 'yazdir', '', 'data-yazdir' ),
			) );
			self::serit_yon_grubu();
		};
		$ad_sagi = '<span class="vurgu">' . esc_html( self::listeler()[ $liste ] ) . '</span>';
		if ( '' !== $veri['toplam_metni'] ) {
			$ad_sagi .= '<span>' . esc_html( $veri['toplam_metni'] ) . '</span>';
		}
		self::uyg_basla( 'Takip Listeleri', 'takip', 'sabit', $serit, $ad_sagi, 'ust-yok' );

		echo '<nav class="sekmeler">';
		foreach ( self::listeler() as $k => $ad ) {
			$url = YP_Cekirdek::panel_url( array( 'ekran' => 'takip', 'liste' => $k ) );
			echo '<a class="' . ( $k === $liste ? 'aktif' : '' ) . '" href="' . esc_url( $url ) . '">' . esc_html( $ad ) . '</a>';
		}
		echo '</nav>';

		// KURAL: Liste adı ve toplamı üstteki araç çubuğunda yazar; burada yalnızca tek satırlık açıklama kalır — satırlara yer açılır.
		echo '<section class="kutu"><p class="kutu-not">' . esc_html( $veri['aciklama'] );
		if ( count( $veri['satirlar'] ) >= self::EKRAN_SINIRI ) {
			echo ' <b>Ekranda ilk ' . (int) self::EKRAN_SINIRI . ' kayıt görünüyor; tamamı için Excel\'e aktarın.</b>';
		}
		echo '</p>';
		echo '<div class="tablo-kap kaydir-alan"><table class="tablo yogun"><thead><tr>';
		foreach ( $veri['basliklar'] as $i => $b ) {
			$sinif = isset( $veri['turler'][ $i ] ) && in_array( $veri['turler'][ $i ], array( 'tutar', 'sayi' ), true ) ? ' class="sag"' : '';
			echo '<th' . $sinif . '>' . esc_html( $b ) . '</th>'; // phpcs:ignore
		}
		echo '</tr></thead><tbody>';
		if ( ! $veri['satirlar'] ) {
			self::bos_liste( 'Bu listede kayıt yok.', count( $veri['basliklar'] ) );
		}
		foreach ( $veri['satirlar'] as $satir ) {
			echo '<tr class="tiklanir" data-git="' . esc_url( YP_Cekirdek::panel_url( array( 'ekran' => 'aday', 'id' => (int) $satir['aday_id'], 'sekme' => $satir['sekme'] ) ) ) . '">';
			foreach ( $satir['hucreler'] as $i => $hucre ) {
				$tur   = isset( $veri['turler'][ $i ] ) ? $veri['turler'][ $i ] : 'metin';
				$sinif = in_array( $tur, array( 'tutar', 'sayi' ), true ) ? 'sag' : '';
				if ( isset( $satir['vurgu'][ $i ] ) ) {
					$sinif .= ' ' . $satir['vurgu'][ $i ];
				}
				echo '<td class="' . esc_attr( $sinif ) . '">' . ( 'tutar' === $tur ? esc_html( YP_Bicim::tl( $hucre ) ) : esc_html( $hucre ) ) . '</td>';
			}
			echo '</tr>';
		}
		echo '</tbody></table></div></section>';
		self::uyg_bitir();
	}

	private static function excel_satirlari( array $veri ) {
		$satirlar = array();
		foreach ( $veri['satirlar'] as $s ) {
			$satirlar[] = $s['hucreler'];
		}
		return $satirlar;
	}

	/**
	 * Seçilen listenin başlıkları, satırları ve toplam metni.
	 */
	private static function veri( $liste, $sinir = 150 ) {
		global $wpdb;
		$a     = YP_Cekirdek::tablo( 'adaylar' );
		$h     = YP_Cekirdek::tablo( 'hareketler' );
		$i     = YP_Cekirdek::tablo( 'islemler' );
		$t     = YP_Cekirdek::tablo( 'tanimlar' );
		$bugun = YP_Cekirdek::bugun();
		$bk    = YP_Hesap::BORC_KOSULU;

		if ( in_array( $liste, array( 'geciken', 'bugun', 'yaklasan' ), true ) ) {
			if ( 'geciken' === $liste ) {
				$kosul     = 'h.vade_tarihi < %s';
				$arg       = array( $bugun );
				$aciklama  = 'Vadesi geçmiş, henüz ödenmemiş borçlar.';
			} elseif ( 'bugun' === $liste ) {
				$kosul    = 'h.vade_tarihi = %s';
				$arg      = array( $bugun );
				$aciklama = 'Bugün vadesi gelen ödemeler.';
			} else {
				$gun      = max( 1, (int) YP_Cekirdek::ayar( 'yaklasan_gun' ) );
				$kosul    = 'h.vade_tarihi > %s AND h.vade_tarihi <= %s';
				$arg      = array( $bugun, YP_Bicim::gun_ekle( $bugun, $gun ) );
				$aciklama = 'Önümüzdeki ' . $gun . ' gün içinde vadesi gelecek ödemeler.';
			}
			// KURAL: Ödeme listeleri satır bazındadır (hangi taksit) ve silinmiş aday/satır gösterilmez.
			$ozet = $wpdb->get_row( $wpdb->prepare(
				"SELECT COUNT(*) AS adet, COALESCE(SUM(h.tutar), 0) AS toplam
				FROM {$h} h INNER JOIN {$a} a ON a.id = h.aday_id AND a.silindi = 0
				WHERE {$bk} AND h.durum = 'ODENMEDI' AND {$kosul}", // phpcs:ignore
				$arg
			) );
			$satirlar = $wpdb->get_results( $wpdb->prepare(
				"SELECT h.id, h.aday_id, h.vade_tarihi, h.tutar, h.borc_tipi, a.aday_no, a.adi, a.soyadi, a.gsm_1
				FROM {$h} h INNER JOIN {$a} a ON a.id = h.aday_id AND a.silindi = 0
				WHERE {$bk} AND h.durum = 'ODENMEDI' AND {$kosul}
				ORDER BY h.vade_tarihi, a.soyadi, a.adi LIMIT %d", // phpcs:ignore
				array_merge( $arg, array( (int) $sinir ) )
			) );
			$cikti  = array();
			$toplam = (float) $ozet->toplam;
			foreach ( $satirlar as $s ) {
				$gecikme = (int) round( ( strtotime( $bugun ) - strtotime( $s->vade_tarihi ) ) / 86400 );
				$cikti[] = array(
					'aday_id'  => $s->aday_id,
					'sekme'    => 'odeme',
					'hucreler' => array(
						(int) $s->aday_no,
						$s->adi . ' ' . $s->soyadi,
						YP_Bicim::telefon( $s->gsm_1 ),
						$s->borc_tipi,
						YP_Bicim::tarih( $s->vade_tarihi ),
						'geciken' === $liste ? $gecikme . ' gün' : ( 'yaklasan' === $liste ? abs( $gecikme ) . ' gün sonra' : 'bugün' ),
						(float) $s->tutar,
					),
					'vurgu'    => 'geciken' === $liste ? array( 6 => 'kirmizi' ) : array(),
				);
			}
			return array(
				'basliklar'    => array( 'Aday No', 'Ad Soyad', 'Telefon', 'Kalem', 'Vade', 'Durum', 'Tutar' ),
				'turler'       => array( 'sayi', 'metin', 'metin', 'metin', 'tarih', 'metin', 'tutar' ),
				'satirlar'     => $cikti,
				'aciklama'     => $aciklama,
				'toplam_metni' => $cikti ? (int) $ozet->adet . ' satır · Toplam ' . YP_Bicim::tl( $toplam ) : '',
			);
		}

		if ( 'gecerlilik' === $liste ) {
			$gun  = max( 1, (int) YP_Cekirdek::ayar( 'gecerlilik_uyari_gun' ) );
			$bit  = YP_Bicim::gun_ekle( $bugun, $gun );
			$satirlar = $wpdb->get_results( $wpdb->prepare(
				"SELECT i.id, i.aday_id, i.gecerlilik_bitis, i.rapor_no, i.rapor_tarihi, a.aday_no, a.adi, a.soyadi, a.gsm_1, tn.ad AS tur_adi
				FROM {$i} i INNER JOIN {$a} a ON a.id = i.aday_id AND a.silindi = 0
				LEFT JOIN {$t} tn ON tn.id = i.islem_turu_id
				WHERE i.silindi = 0 AND i.durum <> 'iptal' AND i.gecerlilik_bitis IS NOT NULL AND i.gecerlilik_bitis <= %s
				ORDER BY i.gecerlilik_bitis LIMIT %d",
				$bit,
				(int) $sinir
			) );
			$cikti = array();
			foreach ( $satirlar as $s ) {
				$kalan   = (int) round( ( strtotime( $s->gecerlilik_bitis ) - strtotime( $bugun ) ) / 86400 );
				$cikti[] = array(
					'aday_id'  => $s->aday_id,
					'sekme'    => '',
					'hucreler' => array(
						(int) $s->aday_no,
						$s->adi . ' ' . $s->soyadi,
						YP_Bicim::telefon( $s->gsm_1 ),
						$s->tur_adi ? $s->tur_adi : '',
						(string) $s->rapor_no,
						YP_Bicim::tarih( $s->gecerlilik_bitis ),
						$kalan < 0 ? abs( $kalan ) . ' gün önce doldu' : $kalan . ' gün kaldı',
					),
					'vurgu'    => $kalan < 0 ? array( 5 => 'kirmizi', 6 => 'kirmizi' ) : array(),
				);
			}
			return array(
				'basliklar'    => array( 'Aday No', 'Ad Soyad', 'Telefon', 'İşlem', 'Rapor No', 'Geçerlilik Bitişi', 'Durum' ),
				'turler'       => array( 'sayi', 'metin', 'metin', 'metin', 'metin', 'tarih', 'metin' ),
				'satirlar'     => $cikti,
				'aciklama'     => 'Raporunun geçerliliği ' . $gun . ' gün içinde dolacak veya dolmuş adaylar — yeniden aramak için.',
				'toplam_metni' => $cikti ? count( $cikti ) . ' kayıt' : '',
			);
		}

		if ( 'evrak' === $liste ) {
			$satirlar = $wpdb->get_results(
				"SELECT a.id, a.aday_no, a.adi, a.soyadi, a.gsm_1, a.kayit_tarihi, a.evrak
				FROM {$a} a WHERE a.silindi = 0 AND a.arsiv = 0 AND a.evrak_tamam = 0
				ORDER BY a.kayit_tarihi DESC LIMIT " . (int) $sinir // phpcs:ignore
			);
			$evraklar = YP_Veri::tanimlar( 'evrak', true );
			$cikti    = array();
			foreach ( $satirlar as $s ) {
				$durum  = json_decode( (string) $s->evrak, true );
				$durum  = is_array( $durum ) ? $durum : array();
				$eksik  = array();
				foreach ( $evraklar as $e ) {
					if ( empty( $durum[ (int) $e->id ] ) ) {
						$eksik[] = $e->ad;
					}
				}
				$cikti[] = array(
					'aday_id'  => $s->id,
					'sekme'    => 'evrak',
					'hucreler' => array(
						(int) $s->aday_no,
						$s->adi . ' ' . $s->soyadi,
						YP_Bicim::telefon( $s->gsm_1 ),
						YP_Bicim::tarih( $s->kayit_tarihi ),
						$eksik ? implode( ', ', $eksik ) : 'İşaretlenmemiş',
					),
					'vurgu'    => array(),
				);
			}
			return array(
				'basliklar'    => array( 'Aday No', 'Ad Soyad', 'Telefon', 'Kayıt', 'Eksik Evrak' ),
				'turler'       => array( 'sayi', 'metin', 'metin', 'tarih', 'metin' ),
				'satirlar'     => $cikti,
				'aciklama'     => 'Evrakları tamamlanmamış aktif adaylar.',
				'toplam_metni' => $cikti ? count( $cikti ) . ' aday' : '',
			);
		}

		if ( 'dogumgunu' === $liste ) {
			// KURAL: Doğum günü listesi bugünden başlayarak 30 günü kapsar; yıl dikkate alınmaz.
			$satirlar = $wpdb->get_results(
				"SELECT a.id, a.aday_no, a.adi, a.soyadi, a.gsm_1, a.dogum_tarihi FROM {$a} a
				WHERE a.silindi = 0 AND a.arsiv = 0 AND a.dogum_tarihi IS NOT NULL LIMIT 20000" // phpcs:ignore
			);
			$cikti = array();
			foreach ( $satirlar as $s ) {
				$ay_gun = substr( $s->dogum_tarihi, 5, 5 );
				$bu_yil = substr( $bugun, 0, 4 ) . '-' . $ay_gun;
				$fark   = (int) round( ( strtotime( $bu_yil ) - strtotime( $bugun ) ) / 86400 );
				if ( $fark < 0 ) {
					$fark = (int) round( ( strtotime( ( (int) substr( $bugun, 0, 4 ) + 1 ) . '-' . $ay_gun ) - strtotime( $bugun ) ) / 86400 );
				}
				if ( $fark > 30 ) {
					continue;
				}
				$yas     = (int) substr( $bugun, 0, 4 ) - (int) substr( $s->dogum_tarihi, 0, 4 );
				$cikti[] = array(
					'sira'     => $fark,
					'aday_id'  => $s->id,
					'sekme'    => '',
					'hucreler' => array(
						(int) $s->aday_no,
						$s->adi . ' ' . $s->soyadi,
						YP_Bicim::telefon( $s->gsm_1 ),
						YP_Bicim::tarih( $s->dogum_tarihi ),
						$yas,
						0 === $fark ? 'Bugün' : $fark . ' gün sonra',
					),
					'vurgu'    => 0 === $fark ? array( 5 => 'kirmizi' ) : array(),
				);
			}
			usort( $cikti, function ( $x, $y ) {
				return $x['sira'] - $y['sira'];
			} );
			$cikti = array_slice( $cikti, 0, (int) $sinir );
			return array(
				'basliklar'    => array( 'Aday No', 'Ad Soyad', 'Telefon', 'Doğum Tarihi', 'Yaş', 'Ne Zaman' ),
				'turler'       => array( 'sayi', 'metin', 'metin', 'tarih', 'sayi', 'metin' ),
				'satirlar'     => $cikti,
				'aciklama'     => 'Önümüzdeki 30 gün içinde doğum günü olan adaylar.',
				'toplam_metni' => $cikti ? count( $cikti ) . ' aday' : '',
			);
		}

		// Çift kayıt şüphesi: aynı TC veya aynı telefon birden fazla adayda.
		$cift = $wpdb->get_results(
			"SELECT a.id, a.aday_no, a.adi, a.soyadi, a.tc_no, a.gsm_1, a.kayit_tarihi, 'TC' AS neden
			FROM {$a} a WHERE a.silindi = 0 AND a.tc_no IS NOT NULL AND a.tc_no <> ''
				AND a.tc_no IN (SELECT tc_no FROM {$a} WHERE silindi = 0 AND tc_no IS NOT NULL AND tc_no <> '' GROUP BY tc_no HAVING COUNT(*) > 1)
			UNION ALL
			SELECT a.id, a.aday_no, a.adi, a.soyadi, a.tc_no, a.gsm_1, a.kayit_tarihi, 'Telefon' AS neden
			FROM {$a} a WHERE a.silindi = 0 AND a.gsm_1 IS NOT NULL AND a.gsm_1 <> ''
				AND a.gsm_1 IN (SELECT gsm_1 FROM {$a} WHERE silindi = 0 AND gsm_1 IS NOT NULL AND gsm_1 <> '' GROUP BY gsm_1 HAVING COUNT(*) > 1)
			ORDER BY tc_no, gsm_1, aday_no LIMIT " . (int) $sinir // phpcs:ignore
		);
		$cikti = array();
		foreach ( $cift as $s ) {
			$cikti[] = array(
				'aday_id'  => $s->id,
				'sekme'    => '',
				'hucreler' => array(
					(int) $s->aday_no,
					$s->adi . ' ' . $s->soyadi,
					(string) $s->tc_no,
					YP_Bicim::telefon( $s->gsm_1 ),
					YP_Bicim::tarih( $s->kayit_tarihi ),
					$s->neden . ' aynı',
				),
				'vurgu'    => array(),
			);
		}
		return array(
			'basliklar'    => array( 'Aday No', 'Ad Soyad', 'TC', 'Telefon', 'Kayıt', 'Neden' ),
			'turler'       => array( 'sayi', 'metin', 'metin', 'metin', 'tarih', 'metin' ),
			'satirlar'     => $cikti,
			'aciklama'     => 'Aynı TC veya aynı telefon numarasıyla birden fazla kayıt.',
			'toplam_metni' => $cikti ? count( $cikti ) . ' kayıt' : '',
		);
	}
}
