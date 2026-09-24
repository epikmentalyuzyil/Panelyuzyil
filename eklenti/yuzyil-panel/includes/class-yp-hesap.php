<?php
defined( 'ABSPATH' ) || exit;

/**
 * Bakiye ve kasa hesapları. Kurallar masaüstü saklı yordamlarından (SP_Z_PROX_ODEME_BAKIYE_KURSIYER,
 * SP_Z_PROX_MUHASEBE_DEVIRLI) alınmıştır.
 */
final class YP_Hesap {

	// KURAL: Borca giren satır = silinmemiş, aday satırı, ÖDENDİ/ÖDENMEDİ, "kendisi yatırdı" değil — masaüstü TOPLAM_BORC.
	const BORC_KOSULU = "h.silindi = 0 AND h.kayit_turu = 'ADAY' AND h.odeme_turu <> 'KENDISI'";

	/**
	 * İade edilebilecek net tahsilat: ödenen − önceki iadeler.
	 * KURAL: İşlem seçiliyse yalnızca o işlemin kendi tahsilat ve iadeleri sayılır; değilse adayın tümü.
	 *
	 * @param bool $kilitle Transaction içinde satırları kilitleyerek okur — aynı anda iki iade aşım yaratamaz.
	 */
	public static function iade_edilebilir( $aday_id, $islem_id = 0, $kilitle = false ) {
		global $wpdb;
		$t   = YP_Cekirdek::tablo( 'hareketler' );
		$k   = self::BORC_KOSULU;
		$sql = "SELECT
				COALESCE(SUM(CASE WHEN h.durum = 'ODENDI' THEN h.tutar ELSE 0 END), 0) AS odenen,
				COALESCE(SUM(CASE WHEN h.durum = 'IADE' THEN h.tutar ELSE 0 END), 0) AS iade
			FROM {$t} h WHERE {$k} AND h.aday_id = %d";
		$arg = array( (int) $aday_id );
		if ( $islem_id ) {
			$sql  .= ' AND h.islem_id = %d';
			$arg[] = (int) $islem_id;
		}
		$satir = $wpdb->get_row( $wpdb->prepare( $sql . ( $kilitle ? YP_Veri::kilit_eki() : '' ), $arg ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $satir ? round( (float) $satir->odenen - (float) $satir->iade, 2 ) : 0.0;
	}

	/**
	 * Bir adayın bakiye özeti.
	 */
	public static function aday_ozeti( $aday_id ) {
		global $wpdb;
		$t     = YP_Cekirdek::tablo( 'hareketler' );
		$bugun = YP_Cekirdek::bugun();
		$k     = self::BORC_KOSULU;
		$satir = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COALESCE(SUM(CASE WHEN h.durum IN ('ODENDI','ODENMEDI') THEN h.tutar ELSE 0 END), 0) AS borc,
					COALESCE(SUM(CASE WHEN h.durum = 'ODENDI' THEN h.tutar ELSE 0 END), 0) AS odenen,
					COALESCE(SUM(CASE WHEN h.durum = 'IADE' THEN h.tutar ELSE 0 END), 0) AS iade,
					COALESCE(SUM(CASE WHEN h.durum = 'ODENMEDI' AND h.vade_tarihi < %s THEN h.tutar ELSE 0 END), 0) AS geciken
				FROM {$t} h WHERE {$k} AND h.aday_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$bugun,
				$aday_id
			)
		);
		$borc   = (float) $satir->borc;
		$odenen = (float) $satir->odenen;
		// KURAL: Kalan borç = toplam borç − toplam ödenen; iade ayrıca gösterilir, kalanı değiştirmez — masaüstü kuralı.
		return array(
			'borc'    => $borc,
			'odenen'  => $odenen,
			'kalan'   => round( $borc - $odenen, 2 ),
			'iade'    => (float) $satir->iade,
			'geciken' => (float) $satir->geciken,
		);
	}

	// KURAL: Masaüstündeki taksit/harç/diğer kırılımı psikoteknikte işlem türüne göre yapılır.
	public static function aday_kirilimi( $aday_id ) {
		global $wpdb;
		$t = YP_Cekirdek::tablo( 'hareketler' );
		$k = self::BORC_KOSULU;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT COALESCE(h.borc_tipi, 'Diğer') AS tip,
					SUM(CASE WHEN h.durum IN ('ODENDI','ODENMEDI') THEN h.tutar ELSE 0 END) AS borc,
					SUM(CASE WHEN h.durum = 'ODENDI' THEN h.tutar ELSE 0 END) AS odenen
				FROM {$t} h WHERE {$k} AND h.aday_id = %d GROUP BY COALESCE(h.borc_tipi, 'Diğer') ORDER BY tip", // phpcs:ignore
				$aday_id
			)
		);
	}

	/**
	 * Verilen adayların bakiyelerini tek sorguda getirir (liste sayfası için).
	 * KURAL: Sayfadaki 25 aday için tek sorgu atılır; tüm tabloyu toplayan ağır sorgu çalıştırılmaz.
	 *
	 * @return array aday_id => array( borc, odenen, kalan, geciken )
	 */
	public static function aday_bakiyeleri( array $idler ) {
		global $wpdb;
		$idler = array_values( array_unique( array_map( 'intval', $idler ) ) );
		if ( ! $idler ) {
			return array();
		}
		$t   = YP_Cekirdek::tablo( 'hareketler' );
		$k   = self::BORC_KOSULU;
		$yer = implode( ',', array_fill( 0, count( $idler ), '%d' ) );
		$sql = "SELECT h.aday_id,
				COALESCE(SUM(CASE WHEN h.durum IN ('ODENDI','ODENMEDI') THEN h.tutar ELSE 0 END), 0) AS borc,
				COALESCE(SUM(CASE WHEN h.durum = 'ODENDI' THEN h.tutar ELSE 0 END), 0) AS odenen,
				COALESCE(SUM(CASE WHEN h.durum = 'ODENMEDI' AND h.vade_tarihi < %s THEN h.tutar ELSE 0 END), 0) AS geciken
			FROM {$t} h WHERE {$k} AND h.aday_id IN ({$yer}) GROUP BY h.aday_id";
		$satirlar = $wpdb->get_results( $wpdb->prepare( $sql, array_merge( array( YP_Cekirdek::bugun() ), $idler ) ) ); // phpcs:ignore
		$sonuc = array();
		foreach ( $satirlar as $s ) {
			$sonuc[ (int) $s->aday_id ] = array(
				'borc'    => (float) $s->borc,
				'odenen'  => (float) $s->odenen,
				'kalan'   => round( (float) $s->borc - (float) $s->odenen, 2 ),
				'geciken' => (float) $s->geciken,
			);
		}
		return $sonuc;
	}

	/**
	 * Aday listesi için bakiye alt sorgusu (aday_id, borc, odenen).
	 */
	public static function bakiye_alt_sorgusu() {
		$t = YP_Cekirdek::tablo( 'hareketler' );
		$k = self::BORC_KOSULU;
		return "SELECT h.aday_id,
				SUM(CASE WHEN h.durum IN ('ODENDI','ODENMEDI') THEN h.tutar ELSE 0 END) AS borc,
				SUM(CASE WHEN h.durum = 'ODENDI' THEN h.tutar ELSE 0 END) AS odenen,
				SUM(CASE WHEN h.durum = 'ODENMEDI' AND h.vade_tarihi < '" . esc_sql( YP_Cekirdek::bugun() ) . "' THEN h.tutar ELSE 0 END) AS geciken
			FROM {$t} h WHERE {$k} AND h.aday_id IS NOT NULL GROUP BY h.aday_id";
	}

	// ---- Kasa -----------------------------------------------------------

	// KURAL: Kasaya giren hareket = silinmemiş, ÖDENDİ veya İADE, tutarı 0 değil, "kendisi yatırdı" değil — masaüstü DEVIRLI.
	const KASA_KOSULU = "h.silindi = 0 AND h.durum IN ('ODENDI','IADE') AND h.odeme_turu <> 'KENDISI' AND h.tutar <> 0 AND h.odeme_tarihi IS NOT NULL";

	// KURAL: Açılış bakiyesi, açılış tarihinden itibaren hesaba eklenir.
	private static function acilis( $hesap, $tarih ) {
		if ( null === $tarih || empty( $hesap->acilis_tarihi ) || $tarih >= $hesap->acilis_tarihi ) {
			return (float) $hesap->acilis_bakiyesi;
		}
		return 0.0;
	}

	/**
	 * Tüm hesapların giriş/çıkış toplamlarını TEK sorguda getirir.
	 * KURAL: Hesap başına ayrı sorgu atılmaz; kaynak ve hedef toplamları iki gruplu sorguda toplanır — kasa ekranı hızlı açılır.
	 *
	 * @return array hesap_id => array( 'giren' => float, 'cikan' => float )
	 */
	private static $toplam_bellegi = array();

	private static function hareket_toplamlari( $bas = null, $bit = null ) {
		// KURAL: Aynı istekte aynı tarih aralığı ikinci kez sorulursa veritabanına tekrar gidilmez.
		$anahtar = ( null === $bas ? '-' : $bas ) . '|' . ( null === $bit ? '-' : $bit );
		if ( isset( self::$toplam_bellegi[ $anahtar ] ) ) {
			return self::$toplam_bellegi[ $anahtar ];
		}
		// KURAL: Devreden bakiye (geçmişin tamamı) veri değişene kadar önbellekte tutulur — her sayfa açılışında tüm tablo toplanmaz.
		if ( null === $bas ) {
			$hazir = YP_Cekirdek::onbellek( 'kasa_devir_' . $anahtar, 10 * MINUTE_IN_SECONDS, function () use ( $bas, $bit ) {
				return self::hareket_toplamlari_hesapla( $bas, $bit );
			} );
			self::$toplam_bellegi[ $anahtar ] = $hazir;
			return $hazir;
		}
		$sonuc = self::hareket_toplamlari_hesapla( $bas, $bit );
		self::$toplam_bellegi[ $anahtar ] = $sonuc;
		return $sonuc;
	}

	private static function hareket_toplamlari_hesapla( $bas, $bit ) {
		global $wpdb;
		$t   = YP_Cekirdek::tablo( 'hareketler' );
		$k   = self::KASA_KOSULU;
		$arg = array();
		$tarih_kosulu = '';
		if ( null !== $bas ) {
			$tarih_kosulu .= ' AND h.odeme_tarihi >= %s';
			$arg[]         = $bas;
		}
		if ( null !== $bit ) {
			$tarih_kosulu .= ' AND h.odeme_tarihi <= %s';
			$arg[]         = $bit;
		}
		$sonuc = array();

		// 1) Hareketin kendi hesabı: gider, iade ve transfer çıkıştır; tahsilat ve gelir giriştir.
		$sql1 = "SELECT h.hesap_id,
				COALESCE(SUM(CASE WHEN h.durum = 'IADE' OR h.kayit_turu IN ('GIDER','TRANSFER') THEN 0 ELSE h.tutar END), 0) AS giren,
				COALESCE(SUM(CASE WHEN h.durum = 'IADE' OR h.kayit_turu IN ('GIDER','TRANSFER') THEN h.tutar ELSE 0 END), 0) AS cikan
			FROM {$t} h WHERE {$k} AND h.hesap_id IS NOT NULL{$tarih_kosulu} GROUP BY h.hesap_id";
		foreach ( $wpdb->get_results( $arg ? $wpdb->prepare( $sql1, $arg ) : $sql1 ) as $s ) { // phpcs:ignore
			$sonuc[ (int) $s->hesap_id ] = array( 'giren' => (float) $s->giren, 'cikan' => (float) $s->cikan );
		}

		// 2) Transferin hedef hesabı: giriştir.
		$sql2 = "SELECT h.hedef_hesap_id, COALESCE(SUM(h.tutar), 0) AS giren FROM {$t} h
			WHERE {$k} AND h.kayit_turu = 'TRANSFER' AND h.hedef_hesap_id IS NOT NULL{$tarih_kosulu} GROUP BY h.hedef_hesap_id";
		foreach ( $wpdb->get_results( $arg ? $wpdb->prepare( $sql2, $arg ) : $sql2 ) as $s ) { // phpcs:ignore
			$id = (int) $s->hedef_hesap_id;
			if ( ! isset( $sonuc[ $id ] ) ) {
				$sonuc[ $id ] = array( 'giren' => 0.0, 'cikan' => 0.0 );
			}
			$sonuc[ $id ]['giren'] += (float) $s->giren;
		}
		return $sonuc;
	}

	/**
	 * Hesabın verilen güne kadar (dahil) bakiyesi; tarih yoksa tüm hareketler.
	 */
	public static function hesap_bakiyesi( $hesap, $tarih = null ) {
		$toplam = self::hareket_toplamlari( null, $tarih );
		$id     = (int) $hesap->id;
		$net    = isset( $toplam[ $id ] ) ? $toplam[ $id ]['giren'] - $toplam[ $id ]['cikan'] : 0.0;
		return round( self::acilis( $hesap, $tarih ) + $net, 2 );
	}

	public static function hesap_bakiyeleri( $tarih = null ) {
		$toplam = self::hareket_toplamlari( null, $tarih );
		$sonuc  = array();
		foreach ( YP_Veri::hesaplar( true ) as $h ) {
			$id  = (int) $h->id;
			$net = isset( $toplam[ $id ] ) ? $toplam[ $id ]['giren'] - $toplam[ $id ]['cikan'] : 0.0;
			$sonuc[] = array(
				'hesap'  => $h,
				'bakiye' => round( self::acilis( $h, $tarih ) + $net, 2 ),
			);
		}
		return $sonuc;
	}

	/**
	 * Tarih aralığında her hesap için devreden, giren, çıkan, kalan — masaüstü gün sonu/devir raporu.
	 * KURAL: Dönem ve devreden toplamları ikişer sorguda (toplam 4) alınır; hesap sayısı arttıkça sorgu sayısı artmaz.
	 */
	public static function donem_ozeti( $bas, $bit ) {
		$donem    = self::hareket_toplamlari( $bas, $bit );
		$devir    = self::hareket_toplamlari( null, YP_Bicim::gun_ekle( $bas, -1 ) );
		$onceki   = YP_Bicim::gun_ekle( $bas, -1 );
		$sonuc    = array();
		foreach ( YP_Veri::hesaplar( true ) as $h ) {
			$id       = (int) $h->id;
			$d_net    = isset( $devir[ $id ] ) ? $devir[ $id ]['giren'] - $devir[ $id ]['cikan'] : 0.0;
			$devreden = round( self::acilis( $h, $onceki ) + $d_net, 2 );
			// KURAL: Açılış tarihi dönemin içindeyse açılış bakiyesi girişe eklenir.
			$acilis_ici = ( ! empty( $h->acilis_tarihi ) && $h->acilis_tarihi >= $bas && $h->acilis_tarihi <= $bit ) ? (float) $h->acilis_bakiyesi : 0.0;
			$giren      = ( isset( $donem[ $id ] ) ? $donem[ $id ]['giren'] : 0.0 ) + $acilis_ici;
			$cikan      = isset( $donem[ $id ] ) ? $donem[ $id ]['cikan'] : 0.0;
			$sonuc[]    = array(
				'hesap'    => $h,
				'devreden' => $devreden,
				'giren'    => round( $giren, 2 ),
				'cikan'    => round( $cikan, 2 ),
				'kalan'    => round( $devreden + $giren - $cikan, 2 ),
			);
		}
		return $sonuc;
	}

	/**
	 * Hesap türüne göre (Nakit / Bankalar / Posta) devreden, giren, çıkan ve gün sonu toplamları.
	 * KURAL: Masaüstü kasa ekranındaki "dünden devir / bugün / devreden" bloklarının karşılığıdır.
	 */
	public static function tur_ozeti( $bas, $bit ) {
		$turler = array( 'NAKIT' => 'Nakit', 'BANKA' => 'Bankalar', 'POSTA' => 'Posta / PTT' );
		$sonuc  = array();
		foreach ( $turler as $kod => $ad ) {
			$sonuc[ $kod ] = array( 'ad' => $ad, 'devreden' => 0.0, 'giren' => 0.0, 'cikan' => 0.0, 'kalan' => 0.0, 'hesaplar' => array() );
		}
		foreach ( self::donem_ozeti( $bas, $bit ) as $o ) {
			$kod = isset( $sonuc[ $o['hesap']->tur ] ) ? $o['hesap']->tur : 'NAKIT';
			foreach ( array( 'devreden', 'giren', 'cikan', 'kalan' ) as $alan ) {
				$sonuc[ $kod ][ $alan ] += $o[ $alan ];
			}
			$sonuc[ $kod ]['hesaplar'][] = $o;
		}
		return $sonuc;
	}

	/**
	 * Kasa defteri satırları. Hesap seçiliyse yürüyen bakiye hesaplanır.
	 */
	public static function defter( $bas, $bit, $hesap_id = 0, $sekme = 'tumu' ) {
		global $wpdb;
		$t   = YP_Cekirdek::tablo( 'hareketler' );
		$a   = YP_Cekirdek::tablo( 'adaylar' );
		$r   = YP_Cekirdek::tablo( 'referanslar' );
		$tn  = YP_Cekirdek::tablo( 'tanimlar' );
		$k   = self::KASA_KOSULU;
		// KURAL: Sekmeler kasa listesini süzer; "aday adına" sekmesi kasaya girmeyen (kendisi yatırdı) ödemeleri gösterir.
		if ( 'gelir' === $sekme ) {
			$k .= " AND h.durum = 'ODENDI' AND h.kayit_turu IN ('ADAY','GELIR')";
		} elseif ( 'gider' === $sekme ) {
			$k .= " AND (h.kayit_turu = 'GIDER' OR h.durum = 'IADE')";
		} elseif ( 'adayadi' === $sekme ) {
			$k = "h.silindi = 0 AND h.durum = 'ODENDI' AND h.odeme_turu = 'KENDISI' AND h.odeme_tarihi IS NOT NULL";
		}
		$sql = "SELECT h.*, a.adi, a.soyadi, a.aday_no, r.ad_soyad AS referans_adi, r.unvan AS referans_unvani, tn.ad AS kalem_adi
			FROM {$t} h
			LEFT JOIN {$a} a ON a.id = h.aday_id
			LEFT JOIN {$r} r ON r.id = h.referans_id
			LEFT JOIN {$tn} tn ON tn.id = h.kalem_id
			WHERE {$k} AND h.odeme_tarihi BETWEEN %s AND %s";
		$arg = array( $bas, $bit );
		if ( $hesap_id ) {
			$sql  .= " AND (h.hesap_id = %d OR (h.kayit_turu = 'TRANSFER' AND h.hedef_hesap_id = %d))";
			$arg[] = $hesap_id;
			$arg[] = $hesap_id;
		}
		$sql   .= ' ORDER BY h.odeme_tarihi, h.id';
		$satirlar = $wpdb->get_results( $wpdb->prepare( $sql, $arg ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		// KURAL: Satır satır yürüyen bakiye hesaplanmaz — kasa listesinde böyle bir sütun yoktur,
		// hesaplanması hesap süzgecinde fazladan bir sorgu demekti.
		foreach ( $satirlar as $s ) {
			$s->giris = 0.0;
			$s->cikis = 0.0;
			if ( 'TRANSFER' === $s->kayit_turu ) {
				if ( $hesap_id && (int) $s->hedef_hesap_id === (int) $hesap_id ) {
					$s->giris = (float) $s->tutar;
				} elseif ( $hesap_id ) {
					$s->cikis = (float) $s->tutar;
				}
				// KURAL: "Tüm hesaplar" görünümünde transfer toplam parayı değiştirmez — giriş/çıkış boş kalır.
			} elseif ( 'IADE' === $s->durum || 'GIDER' === $s->kayit_turu ) {
				$s->cikis = (float) $s->tutar;
			} else {
				$s->giris = (float) $s->tutar;
			}
		}
		return array( 'satirlar' => $satirlar );
	}

	// KURAL: Gelirler ödeme türüne göre, giderler kaleme göre dağıtılır — masaüstü kasa raporu dağılımı.
	public static function gelir_dagilimi( $bas, $bit ) {
		global $wpdb;
		$t = YP_Cekirdek::tablo( 'hareketler' );
		$k = self::KASA_KOSULU;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT h.odeme_turu, SUM(h.tutar) AS toplam, COUNT(*) AS adet FROM {$t} h
				WHERE {$k} AND h.durum = 'ODENDI' AND h.kayit_turu IN ('ADAY','GELIR') AND h.odeme_tarihi BETWEEN %s AND %s
				GROUP BY h.odeme_turu ORDER BY toplam DESC", // phpcs:ignore
				$bas,
				$bit
			)
		);
	}

	public static function gider_dagilimi( $bas, $bit ) {
		global $wpdb;
		$t  = YP_Cekirdek::tablo( 'hareketler' );
		$tn = YP_Cekirdek::tablo( 'tanimlar' );
		$k  = self::KASA_KOSULU;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT COALESCE(tn.ad, CASE WHEN h.durum = 'IADE' THEN 'İadeler' ELSE 'Diğer' END) AS kalem, SUM(h.tutar) AS toplam, COUNT(*) AS adet
				FROM {$t} h LEFT JOIN {$tn} tn ON tn.id = h.kalem_id
				WHERE {$k} AND (h.kayit_turu = 'GIDER' OR h.durum = 'IADE') AND h.odeme_tarihi BETWEEN %s AND %s
				GROUP BY COALESCE(tn.ad, CASE WHEN h.durum = 'IADE' THEN 'İadeler' ELSE 'Diğer' END) ORDER BY toplam DESC", // phpcs:ignore
				$bas,
				$bit
			)
		);
	}

	// ---- Genel toplamlar ------------------------------------------------

	// KURAL: Toplam ve geciken alacak yalnızca silinmemiş adaylardan hesaplanır — masaüstü KURSIYER_DURUMU<>6.
	public static function genel_alacak() {
		global $wpdb;
		$a     = YP_Cekirdek::tablo( 'adaylar' );
		$alt   = self::bakiye_alt_sorgusu();
		$satir = $wpdb->get_row( "SELECT COALESCE(SUM(b.borc - b.odenen), 0) AS kalan, COALESCE(SUM(b.geciken), 0) AS geciken FROM ({$alt}) b INNER JOIN {$a} a ON a.id = b.aday_id AND a.silindi = 0" ); // phpcs:ignore
		return array(
			'kalan'   => (float) $satir->kalan,
			'geciken' => (float) $satir->geciken,
		);
	}

	// KURAL: Taksit tutarları kuruşa kadar eşit bölünür, artan kuruş son taksite eklenir — toplam birebir tutar.
	public static function taksitlere_bol( $toplam, $adet ) {
		$adet   = max( 1, (int) $adet );
		$kurus  = (int) round( $toplam * 100 );
		$parca  = intdiv( $kurus, $adet );
		$sonuc  = array_fill( 0, $adet, $parca / 100 );
		$sonuc[ $adet - 1 ] = ( $kurus - $parca * ( $adet - 1 ) ) / 100;
		return $sonuc;
	}
}
