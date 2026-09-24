<?php
defined( 'ABSPATH' ) || exit;

/**
 * Personel verisi ve hesapları: yaş, kıdem, doğum gününe kalan gün, ödeme toplamları, maaş takvimi.
 * KURAL: Ekran kodu tarih hesabı yapmaz — hepsi burada tek yerde durur.
 */
final class YP_Personel {

	/**
	 * Ödeme türleri. Anahtarlar veritabanına yazılır, değiştirilmez.
	 */
	public static function odeme_turleri() {
		return array(
			'MAAS'    => 'Maaş',
			'AVANS'   => 'Avans',
			'YOL'     => 'Yol ücreti',
			'YEMEK'   => 'Yemek kartı',
			'PRIM'    => 'Prim / ikramiye',
			'KESINTI' => 'Kesinti',
		);
	}

	/**
	 * Kasaya gider olarak işlenebilen türler.
	 * KURAL: Kesinti para çıkışı değildir — kasaya işlenmez.
	 */
	public static function kasaya_giden_turler() {
		return array( 'MAAS', 'AVANS', 'YOL', 'YEMEK', 'PRIM' );
	}

	public static function gorevler() {
		return array( 'Psikolog', 'Psikoteknik Uzmanı', 'Büro Görevlisi', 'Muhasebe', 'Temizlik', 'Şoför', 'Müdür', 'Diğer' );
	}

	// ---- Okuma ----------------------------------------------------------

	public static function personel( $id ) {
		global $wpdb;
		$id = (int) $id;
		if ( ! $id ) {
			return null;
		}
		$t = YP_Cekirdek::tablo( 'personel' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d AND silindi = 0", $id ) ); // phpcs:ignore
	}

	public static function adi( $p ) {
		return $p ? trim( $p->ad . ' ' . $p->soyad ) : '';
	}

	/**
	 * Personel ödemeleri (en yeni üstte).
	 */
	public static function odemeler( $personel_id, $yil = 0 ) {
		global $wpdb;
		$t     = YP_Cekirdek::tablo( 'personel_odeme' );
		$where = 'o.personel_id = %d AND o.silindi = 0';
		$args  = array( (int) $personel_id );
		if ( $yil ) {
			$where .= ' AND YEAR(o.tarih) = %d';
			$args[] = (int) $yil;
		}
		return $wpdb->get_results( $wpdb->prepare( "SELECT o.* FROM {$t} o WHERE {$where} ORDER BY o.tarih DESC, o.id DESC", $args ) ); // phpcs:ignore
	}

	public static function odeme( $id ) {
		global $wpdb;
		$t = YP_Cekirdek::tablo( 'personel_odeme' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d AND silindi = 0", (int) $id ) ); // phpcs:ignore
	}

	/**
	 * Ödemelerin yıla göre bulunduğu yıllar — kart üstündeki yıl seçimi için.
	 */
	public static function odeme_yillari( $personel_id ) {
		global $wpdb;
		$t     = YP_Cekirdek::tablo( 'personel_odeme' );
		$yillar = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT YEAR(tarih) FROM {$t} WHERE personel_id = %d AND silindi = 0 ORDER BY 1 DESC", (int) $personel_id ) ); // phpcs:ignore
		$yillar = array_map( 'intval', (array) $yillar );
		$bu_yil = (int) substr( YP_Cekirdek::bugun(), 0, 4 );
		if ( ! in_array( $bu_yil, $yillar, true ) ) {
			array_unshift( $yillar, $bu_yil );
		}
		return $yillar;
	}

	// ---- Hesaplar -------------------------------------------------------

	/**
	 * Doğum tarihine göre yaş. Tarih yoksa boş döner.
	 */
	public static function yas( $dogum_tarihi ) {
		$d = self::tarih_nesnesi( $dogum_tarihi );
		if ( ! $d ) {
			return null;
		}
		return (int) $d->diff( self::bugun_nesnesi() )->y;
	}

	/**
	 * İşe başlama tarihinden bugüne (ya da ayrılma tarihine) kıdem.
	 * KURAL: "3 yıl 2 ay 14 gün" biçiminde gösterilir — yuvarlanmaz, gün gün hesaplanır.
	 */
	public static function kidem( $baslama_tarihi, $ayrilma_tarihi = '' ) {
		$bas = self::tarih_nesnesi( $baslama_tarihi );
		if ( ! $bas ) {
			return null;
		}
		$bit = self::tarih_nesnesi( $ayrilma_tarihi );
		if ( ! $bit ) {
			$bit = self::bugun_nesnesi();
		}
		if ( $bas > $bit ) {
			return array( 'yil' => 0, 'ay' => 0, 'gun' => 0, 'toplam_gun' => 0, 'metin' => 'Henüz başlamadı' );
		}
		$fark = $bas->diff( $bit );
		$parca = array();
		if ( $fark->y ) {
			$parca[] = $fark->y . ' yıl';
		}
		if ( $fark->m ) {
			$parca[] = $fark->m . ' ay';
		}
		// KURAL: Gün her zaman yazılır — yeni başlayanda "0 yıl 0 ay" yerine "5 gün" görünsün.
		if ( $fark->d || ! $parca ) {
			$parca[] = $fark->d . ' gün';
		}
		return array(
			'yil'        => (int) $fark->y,
			'ay'         => (int) $fark->m,
			'gun'        => (int) $fark->d,
			'toplam_gun' => (int) $fark->days,
			'metin'      => implode( ' ', $parca ),
		);
	}

	/**
	 * Doğum gününe kaç gün kaldı. Bugünse 0 döner.
	 */
	public static function dogum_gunune_kalan( $dogum_tarihi ) {
		$d = self::tarih_nesnesi( $dogum_tarihi );
		if ( ! $d ) {
			return null;
		}
		$bugun = self::bugun_nesnesi();
		$yil   = (int) $bugun->format( 'Y' );
		// KURAL: 29 Şubat doğumlular artık olmayan yılda 28 Şubat'a alınır — tarih kaymaz.
		$gun   = (int) $d->format( 'd' );
		$ay    = (int) $d->format( 'm' );
		$sonraki = self::gunu_kur( $yil, $ay, $gun );
		if ( $sonraki < $bugun ) {
			$sonraki = self::gunu_kur( $yil + 1, $ay, $gun );
		}
		return (int) $bugun->diff( $sonraki )->days;
	}

	private static function gunu_kur( $yil, $ay, $gun ) {
		$son_gun = (int) gmdate( 't', mktime( 0, 0, 0, $ay, 1, $yil ) );
		$gun     = min( $gun, $son_gun );
		return DateTime::createFromFormat( '!Y-m-d', sprintf( '%04d-%02d-%02d', $yil, $ay, $gun ) );
	}

	private static function tarih_nesnesi( $ymd ) {
		$ymd = trim( (string) $ymd );
		if ( '' === $ymd || '0000-00-00' === $ymd ) {
			return null;
		}
		$d = DateTime::createFromFormat( '!Y-m-d', substr( $ymd, 0, 10 ) );
		return $d ? $d : null;
	}

	private static function bugun_nesnesi() {
		return DateTime::createFromFormat( '!Y-m-d', YP_Cekirdek::bugun() );
	}

	// ---- Toplamlar ------------------------------------------------------

	/**
	 * Bir personelin seçili yıldaki ödeme toplamları (türe göre).
	 */
	public static function yil_ozeti( $personel_id, $yil ) {
		global $wpdb;
		$t      = YP_Cekirdek::tablo( 'personel_odeme' );
		$satirlar = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore
			"SELECT tur, COALESCE(SUM(tutar),0) AS toplam, COUNT(*) AS adet
				FROM {$t} WHERE personel_id = %d AND silindi = 0 AND YEAR(tarih) = %d GROUP BY tur",
			(int) $personel_id,
			(int) $yil
		) );
		$ozet = array();
		foreach ( array_keys( self::odeme_turleri() ) as $tur ) {
			$ozet[ $tur ] = array( 'toplam' => 0.0, 'adet' => 0 );
		}
		foreach ( $satirlar as $s ) {
			if ( isset( $ozet[ $s->tur ] ) ) {
				$ozet[ $s->tur ] = array( 'toplam' => (float) $s->toplam, 'adet' => (int) $s->adet );
			}
		}
		return $ozet;
	}

	/**
	 * Seçili yılın maaş takvimi: hangi ay ödendi, ne kadar.
	 * KURAL: Ödeme tarihine değil DÖNEM'e bakılır — Ekim maaşı Kasım'da yatsa da Ekim'e işlenir.
	 */
	public static function maas_takvimi( $personel_id, $yil, $personel = null ) {
		global $wpdb;
		$t = YP_Cekirdek::tablo( 'personel_odeme' );
		$satirlar = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore
			"SELECT donem, COALESCE(SUM(tutar),0) AS toplam, MAX(tarih) AS son_tarih
				FROM {$t} WHERE personel_id = %d AND silindi = 0 AND tur = 'MAAS' AND donem LIKE %s
				GROUP BY donem",
			(int) $personel_id,
			$wpdb->esc_like( (string) (int) $yil ) . '-%'
		) );
		$odenen = array();
		foreach ( $satirlar as $s ) {
			$ay = (int) substr( (string) $s->donem, 5, 2 );
			if ( $ay >= 1 && $ay <= 12 ) {
				$odenen[ $ay ] = array( 'tutar' => (float) $s->toplam, 'tarih' => $s->son_tarih );
			}
		}

		$bas = $personel ? self::tarih_nesnesi( $personel->baslama_tarihi ) : null;
		$ayr = $personel ? self::tarih_nesnesi( $personel->ayrilma_tarihi ) : null;
		$bugun = self::bugun_nesnesi();

		$takvim = array();
		for ( $ay = 1; $ay <= 12; $ay++ ) {
			$ay_basi = self::gunu_kur( (int) $yil, $ay, 1 );
			$ay_sonu = self::gunu_kur( (int) $yil, $ay, 31 );
			// Bu ayda çalışıyor muydu?
			$calisiyordu = true;
			if ( $bas && $ay_sonu < $bas ) {
				$calisiyordu = false;
			}
			if ( $ayr && $ay_basi > $ayr ) {
				$calisiyordu = false;
			}
			$gelecek = $ay_basi > $bugun;

			$takvim[ $ay ] = array(
				'ay'          => $ay,
				'odendi'      => isset( $odenen[ $ay ] ),
				'tutar'       => isset( $odenen[ $ay ] ) ? $odenen[ $ay ]['tutar'] : 0.0,
				'tarih'       => isset( $odenen[ $ay ] ) ? $odenen[ $ay ]['tarih'] : '',
				'calisiyordu' => $calisiyordu,
				'gelecek'     => $gelecek,
			);
		}
		return $takvim;
	}

	/**
	 * Liste ekranı için tüm personel + hesaplanmış sütunlar.
	 */
	public static function liste( $durum = 'aktif', $sirala = 'ad', $yon = 'asc' ) {
		global $wpdb;
		$t     = YP_Cekirdek::tablo( 'personel' );
		$where = array( 'silindi = 0' );
		if ( 'aktif' === $durum ) {
			$where[] = 'aktif = 1';
		} elseif ( 'ayrilan' === $durum ) {
			$where[] = 'aktif = 0';
		}
		$satirlar = $wpdb->get_results( 'SELECT * FROM ' . $t . ' WHERE ' . implode( ' AND ', $where ) ); // phpcs:ignore

		$liste = array();
		foreach ( $satirlar as $p ) {
			$kidem = self::kidem( $p->baslama_tarihi, $p->ayrilma_tarihi );
			$liste[] = array(
				'kayit'       => $p,
				'ad_soyad'    => self::adi( $p ),
				'yas'         => self::yas( $p->dogum_tarihi ),
				'kidem'       => $kidem,
				'kidem_gun'   => $kidem ? $kidem['toplam_gun'] : -1,
				'dogum_kalan' => self::dogum_gunune_kalan( $p->dogum_tarihi ),
				'maas'        => (float) $p->maas,
			);
		}

		$ters = 'desc' === $yon ? -1 : 1;
		usort( $liste, function ( $a, $b ) use ( $sirala, $ters ) {
			switch ( $sirala ) {
				case 'yas':
					$k = self::karsilastir( $a['yas'], $b['yas'] );
					break;
				case 'kidem':
					$k = self::karsilastir( $a['kidem_gun'], $b['kidem_gun'] );
					break;
				case 'maas':
					$k = self::karsilastir( $a['maas'], $b['maas'] );
					break;
				case 'dogum':
					$k = self::karsilastir( $a['dogum_kalan'], $b['dogum_kalan'] );
					break;
				case 'gorev':
					$k = strcoll( (string) $a['kayit']->gorev, (string) $b['kayit']->gorev );
					break;
				default:
					// KURAL: Ad sıralaması Türkçe harf düzenine göre yapılır.
					$k = strcmp( YP_Bicim::katla( $a['ad_soyad'] ), YP_Bicim::katla( $b['ad_soyad'] ) );
			}
			if ( 0 === $k ) {
				$k = strcmp( YP_Bicim::katla( $a['ad_soyad'] ), YP_Bicim::katla( $b['ad_soyad'] ) );
			}
			return $k * $ters;
		} );
		return $liste;
	}

	private static function karsilastir( $a, $b ) {
		// KURAL: Bilgisi girilmemiş (null) kayıt her zaman sona düşer.
		if ( null === $a && null === $b ) {
			return 0;
		}
		if ( null === $a ) {
			return 1;
		}
		if ( null === $b ) {
			return -1;
		}
		return $a === $b ? 0 : ( $a < $b ? -1 : 1 );
	}

	/**
	 * Liste ekranı üst özeti.
	 */
	public static function genel_ozet() {
		global $wpdb;
		$t  = YP_Cekirdek::tablo( 'personel' );
		$o  = YP_Cekirdek::tablo( 'personel_odeme' );
		$ay = YP_Cekirdek::bugun();
		$ay = substr( $ay, 0, 7 );
		return array(
			'aktif'       => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE silindi = 0 AND aktif = 1" ), // phpcs:ignore
			'ayrilan'     => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE silindi = 0 AND aktif = 0" ), // phpcs:ignore
			'maas_toplam' => (float) $wpdb->get_var( "SELECT COALESCE(SUM(maas),0) FROM {$t} WHERE silindi = 0 AND aktif = 1" ), // phpcs:ignore
			'bu_ay_odenen' => (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(tutar),0) FROM {$o} WHERE silindi = 0 AND tur = 'MAAS' AND donem = %s", $ay ) ), // phpcs:ignore
			'donem'       => $ay,
		);
	}
}
