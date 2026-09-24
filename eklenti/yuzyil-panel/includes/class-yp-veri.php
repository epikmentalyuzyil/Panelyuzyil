<?php
defined( 'ABSPATH' ) || exit;

/**
 * Tablolara erişim. Tüm sorgular $wpdb->prepare ile hazırlanır.
 */
final class YP_Veri {

	// ---- Sabit listeler -------------------------------------------------

	// KURAL: İşlem durumları sabittir — masaüstünde olmayan psikoteknik akışı: randevu → test → rapor → teslim.
	public static function islem_durumlari() {
		return array(
			'randevu' => 'Randevu',
			'test'    => 'Test Yapıldı',
			'rapor'   => 'Rapor Verildi',
			'teslim'  => 'Teslim Edildi',
			'iptal'   => 'İptal',
		);
	}

	/**
	 * Aday kartının kayıt durumu (kartın sol altındaki kutu).
	 * KURAL: "Arşiv" seçimi kaydı arşive alır (listede varsayılan olarak görünmez); diğer üçü kaydı aktif bırakır.
	 */
	public static function kayit_durumlari() {
		return array(
			'arsiv'      => 'ARŞİV',
			'tamamlandi' => 'TAMAMLANDI',
			'iade'       => 'İADE',
			'yarida'     => 'YARIDA KALDI',
		);
	}

	// KURAL: Ödeme durumları ÖDENDİ / ÖDENMEDİ / İADE ile sınırlıdır — takip/avukat kullanılmıyordu, çıkarıldı.
	public static function odeme_durumlari() {
		return array(
			'ODENMEDI' => 'Ödenmedi',
			'ODENDI'   => 'Ödendi',
			'IADE'     => 'İade',
		);
	}

	// KURAL: "Kendisi yatırdı" tahsilatı borca, ödenene ve kasaya girmez — masaüstü kuralı aynen korunur.
	public static function odeme_turleri() {
		return array(
			'NAKIT'   => 'Nakit',
			'POS'     => 'Banka / POS',
			'HAVALE'  => 'Banka / Havale',
			'PTT'     => 'Posta / PTT',
			'KENDISI' => 'Kendisi Yatırdı',
		);
	}

	public static function hesap_turleri() {
		return array(
			'NAKIT' => 'Nakit Kasa',
			'BANKA' => 'Banka Hesabı',
			'POSTA' => 'Posta / PTT',
		);
	}

	// KURAL: Ödeme türüne göre önerilen hesap türü — kullanıcı tahsilatta değiştirebilir.
	public static function odeme_hesap_turu( $odeme_turu ) {
		$harita = array( 'NAKIT' => 'NAKIT', 'POS' => 'BANKA', 'HAVALE' => 'BANKA', 'PTT' => 'POSTA' );
		return isset( $harita[ $odeme_turu ] ) ? $harita[ $odeme_turu ] : '';
	}

	public static function iller() {
		$iller = array( 'Adana', 'Adıyaman', 'Afyonkarahisar', 'Ağrı', 'Aksaray', 'Amasya', 'Ankara', 'Antalya', 'Ardahan', 'Artvin', 'Aydın', 'Balıkesir', 'Bartın', 'Batman', 'Bayburt', 'Bilecik', 'Bingöl', 'Bitlis', 'Bolu', 'Burdur', 'Bursa', 'Çanakkale', 'Çankırı', 'Çorum', 'Denizli', 'Diyarbakır', 'Düzce', 'Edirne', 'Elazığ', 'Erzincan', 'Erzurum', 'Eskişehir', 'Gaziantep', 'Giresun', 'Gümüşhane', 'Hakkari', 'Hatay', 'Iğdır', 'Isparta', 'İstanbul', 'İzmir', 'Kahramanmaraş', 'Karabük', 'Karaman', 'Kars', 'Kastamonu', 'Kayseri', 'Kırıkkale', 'Kırklareli', 'Kırşehir', 'Kilis', 'Kocaeli', 'Konya', 'Kütahya', 'Malatya', 'Manisa', 'Mardin', 'Mersin', 'Muğla', 'Muş', 'Nevşehir', 'Niğde', 'Ordu', 'Osmaniye', 'Rize', 'Sakarya', 'Samsun', 'Siirt', 'Sinop', 'Sivas', 'Şanlıurfa', 'Şırnak', 'Tekirdağ', 'Tokat', 'Trabzon', 'Tunceli', 'Uşak', 'Van', 'Yalova', 'Yozgat', 'Zonguldak' );
		return array_combine( $iller, $iller );
	}

	public static function ehliyet_siniflari() {
		$s = array( 'M', 'A1', 'A2', 'A', 'B1', 'B', 'BE', 'C1', 'C1E', 'C', 'CE', 'D1', 'D1E', 'D', 'DE', 'F', 'G' );
		return array_combine( $s, $s );
	}

	public static function tanim_turleri() {
		return array(
			'islem_turu'   => 'İşlem Türleri',
			'gider_kalemi' => 'Gider Kalemleri',
			'gelir_kalemi' => 'Gelir Kalemleri',
			'ozel_kod'     => 'Özel Kodlar',
			'evrak'        => 'Evrak Listesi',
		);
	}

	// ---- Tanımlar ve hesaplar -------------------------------------------

	// KURAL: Tanım ve hesap listeleri istek başına bir kez okunur; aynı sayfada tekrar tekrar sorgulanmaz.
	private static $bellek = array();

	public static function bellek_temizle() {
		self::$bellek = array();
	}

	public static function tanimlar( $tur, $sadece_aktif = true ) {
		global $wpdb;
		$anahtar = 'tanim_' . $tur . ( $sadece_aktif ? '_a' : '' );
		if ( isset( self::$bellek[ $anahtar ] ) ) {
			return self::$bellek[ $anahtar ];
		}
		$t   = YP_Cekirdek::tablo( 'tanimlar' );
		$sql = "SELECT * FROM {$t} WHERE tur = %s" . ( $sadece_aktif ? ' AND aktif = 1' : '' ) . ' ORDER BY sira, ad';
		self::$bellek[ $anahtar ] = $wpdb->get_results( $wpdb->prepare( $sql, $tur ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return self::$bellek[ $anahtar ];
	}

	public static function tanim( $id ) {
		global $wpdb;
		$id = (int) $id;
		if ( ! $id ) {
			return null;
		}
		if ( isset( self::$bellek[ 'tanim_id_' . $id ] ) ) {
			return self::$bellek[ 'tanim_id_' . $id ];
		}
		$t = YP_Cekirdek::tablo( 'tanimlar' );
		self::$bellek[ 'tanim_id_' . $id ] = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", $id ) );
		return self::$bellek[ 'tanim_id_' . $id ];
	}

	public static function tanim_adlari( $tur, $sadece_aktif = false ) {
		$sonuc = array();
		foreach ( self::tanimlar( $tur, $sadece_aktif ) as $s ) {
			$sonuc[ (int) $s->id ] = $s->ad;
		}
		return $sonuc;
	}

	public static function hesaplar( $sadece_aktif = true ) {
		global $wpdb;
		$anahtar = $sadece_aktif ? 'hesap_aktif' : 'hesap_tum';
		if ( isset( self::$bellek[ $anahtar ] ) ) {
			return self::$bellek[ $anahtar ];
		}
		$t = YP_Cekirdek::tablo( 'hesaplar' );
		self::$bellek[ $anahtar ] = $wpdb->get_results( "SELECT * FROM {$t}" . ( $sadece_aktif ? ' WHERE aktif = 1' : '' ) . ' ORDER BY sira, ad' ); // phpcs:ignore
		return self::$bellek[ $anahtar ];
	}

	public static function hesap( $id ) {
		$id = (int) $id;
		foreach ( self::hesaplar( false ) as $h ) {
			if ( (int) $h->id === $id ) {
				return $h;
			}
		}
		return null;
	}

	// KURAL: Hesap seçilmezse ödeme türüne uyan ilk aktif hesap kullanılır (Nakit→Nakit Kasa, POS/Havale→Banka, PTT→Posta).
	public static function varsayilan_hesap( $odeme_turu ) {
		$tur = self::odeme_hesap_turu( $odeme_turu );
		foreach ( self::hesaplar( true ) as $h ) {
			if ( $h->tur === $tur ) {
				return (int) $h->id;
			}
		}
		return 0;
	}

	public static function hesap_adlari( $sadece_aktif = false ) {
		$sonuc = array();
		foreach ( self::hesaplar( $sadece_aktif ) as $h ) {
			$sonuc[ (int) $h->id ] = $h->ad;
		}
		return $sonuc;
	}

	// ---- Referanslar ----------------------------------------------------

	public static function referans( $id ) {
		global $wpdb;
		$t = YP_Cekirdek::tablo( 'referanslar' );
		return $id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", $id ) ) : null;
	}

	public static function referans_adi( $r ) {
		if ( ! $r ) {
			return '';
		}
		return $r->unvan ? $r->unvan . ' (' . $r->ad_soyad . ')' : $r->ad_soyad;
	}

	public static function referans_secenekleri( $dahil_id = 0 ) {
		global $wpdb;
		$anahtar = 'ref_sec_' . (int) $dahil_id;
		if ( isset( self::$bellek[ $anahtar ] ) ) {
			return self::$bellek[ $anahtar ];
		}
		$t     = YP_Cekirdek::tablo( 'referanslar' );
		$liste = $wpdb->get_results( $wpdb->prepare( "SELECT id, unvan, ad_soyad FROM {$t} WHERE aktif = 1 OR id = %d ORDER BY ad_soyad", $dahil_id ) );
		$sonuc = array();
		foreach ( $liste as $r ) {
			$sonuc[ (int) $r->id ] = self::referans_adi( $r );
		}
		self::$bellek[ $anahtar ] = $sonuc;
		return $sonuc;
	}

	// ---- Adaylar --------------------------------------------------------

	public static function aday( $id ) {
		global $wpdb;
		$t = YP_Cekirdek::tablo( 'adaylar' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", $id ) );
	}

	public static function aday_adi( $a ) {
		return $a ? trim( $a->adi . ' ' . $a->soyadi ) : '';
	}

	// KURAL: Hızlı arama metni ad, soyad, TC, telefon, aday no ve baba adından üretilir; her kayıtta yenilenir.
	public static function aday_arama_metni( array $v ) {
		$parcalar = array(
			isset( $v['adi'] ) ? $v['adi'] : '',
			isset( $v['soyadi'] ) ? $v['soyadi'] : '',
			isset( $v['baba_adi'] ) ? $v['baba_adi'] : '',
			isset( $v['tc_no'] ) ? $v['tc_no'] : '',
			isset( $v['gsm_1'] ) ? $v['gsm_1'] : '',
			isset( $v['gsm_2'] ) ? $v['gsm_2'] : '',
			isset( $v['aday_no'] ) ? '#' . $v['aday_no'] : '',
		);
		return substr( YP_Bicim::katla( implode( ' ', $parcalar ) ), 0, 700 );
	}

	// KURAL: Aday no en büyük + 1 verilir ve benzersizdir; çakışırsa bir sonraki denenir — masaüstü KURSIYER_MAX_ID.
	public static function aday_ekle( array $veri ) {
		global $wpdb;
		$t = YP_Cekirdek::tablo( 'adaylar' );
		for ( $deneme = 0; $deneme < 5; $deneme++ ) {
			$veri['aday_no']     = (int) $wpdb->get_var( "SELECT COALESCE(MAX(aday_no), 0) FROM {$t}" ) + 1 + $deneme; // phpcs:ignore
			$veri['arama_metni'] = self::aday_arama_metni( $veri );
			$wpdb->suppress_errors( true );
			$ok = $wpdb->insert( $t, $veri );
			$wpdb->suppress_errors( false );
			if ( $ok ) {
				YP_Cekirdek::veri_degisti();
				return (int) $wpdb->insert_id;
			}
		}
		return 0;
	}

	public static function aday_guncelle( $id, array $veri ) {
		global $wpdb;
		$t   = YP_Cekirdek::tablo( 'adaylar' );
		$eski = self::aday( $id );
		if ( ! $eski ) {
			return false;
		}
		$birlesik            = array_merge( (array) $eski, $veri );
		$veri['arama_metni'] = self::aday_arama_metni( $birlesik );
		$ok                  = $wpdb->update( $t, $veri, array( 'id' => (int) $id ) );
		YP_Cekirdek::veri_degisti();
		return false !== $ok;
	}

	// ---- İşlemler -------------------------------------------------------

	public static function islem( $id ) {
		global $wpdb;
		$t = YP_Cekirdek::tablo( 'islemler' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", $id ) );
	}

	public static function aday_islemleri( $aday_id, $silinmis = false ) {
		global $wpdb;
		$t = YP_Cekirdek::tablo( 'islemler' );
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE aday_id = %d AND silindi = %d ORDER BY islem_tarihi DESC, id DESC", $aday_id, $silinmis ? 1 : 0 ) );
	}

	// ---- Hareketler -----------------------------------------------------

	public static function hareket( $id ) {
		global $wpdb;
		$t = YP_Cekirdek::tablo( 'hareketler' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", $id ) );
	}

	/**
	 * Satırı transaction içinde kilitleyerek okur (MySQL: SELECT … FOR UPDATE).
	 * KURAL: Aynı borç iki sekmeden aynı anda tahsil edilemez — ikinci işlem ilkinin bitmesini bekler ve güncel satırı görür.
	 */
	public static function hareket_kilitli( $id ) {
		global $wpdb;
		$t = YP_Cekirdek::tablo( 'hareketler' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d" . self::kilit_eki(), $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	// KURAL: Satır kilidi yalnızca MySQL/MariaDB'de eklenir; SQLite (yerel deneme) tüm veritabanını zaten kilitler.
	public static function kilit_eki() {
		return ( defined( 'DB_ENGINE' ) && 'sqlite' === DB_ENGINE ) ? '' : ' FOR UPDATE';
	}

	public static function aday_hareketleri( $aday_id ) {
		global $wpdb;
		$t = YP_Cekirdek::tablo( 'hareketler' );
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE aday_id = %d AND silindi = 0 ORDER BY COALESCE(vade_tarihi, odeme_tarihi), id", $aday_id ) );
	}

	public static function hareket_ekle( array $veri ) {
		global $wpdb;
		$veri = array_merge(
			array(
				'olusturan' => YP_Cekirdek::kullanici_id(),
				'olusturma' => YP_Cekirdek::simdi(),
			),
			$veri
		);
		$ok = $wpdb->insert( YP_Cekirdek::tablo( 'hareketler' ), $veri );
		self::yazildi_mi( $ok );
		YP_Cekirdek::veri_degisti();
		return $ok ? (int) $wpdb->insert_id : 0;
	}

	public static function hareket_guncelle( $id, array $veri ) {
		global $wpdb;
		$ok = $wpdb->update( YP_Cekirdek::tablo( 'hareketler' ), $veri, array( 'id' => (int) $id ) );
		self::yazildi_mi( $ok );
		YP_Cekirdek::veri_degisti();
		return false !== $ok;
	}

	// ---- Transaction ----------------------------------------------------

	/** @var int İç içe tek_islemde çağrılarında yalnızca en dıştaki transaction açar/kapatır. */
	private static $derinlik = 0;

	/**
	 * Birden çok yazma yapan para işlemini tek transaction içinde çalıştırır.
	 * KURAL: İş hata fırlatırsa ya da bir yazma başarısız olursa ROLLBACK yapılır — yarım kayıt kalmaz.
	 * KURAL: İş içinde yönlendirme/exit yapılmaz; yarıda kesilen istekte açık transaction kapanışta geri alınır.
	 *
	 * @throws YP_Veri_Hatasi Yazma başarısız olursa.
	 */
	public static function tek_islemde( callable $is ) {
		global $wpdb;
		if ( self::$derinlik > 0 ) {
			return call_user_func( $is );
		}
		static $kapanis_kayitli = false;
		if ( ! $kapanis_kayitli ) {
			register_shutdown_function( array( __CLASS__, 'acik_islemi_geri_al' ) );
			$kapanis_kayitli = true;
		}
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			throw new YP_Veri_Hatasi( 'Transaction başlatılamadı.' );
		}
		self::$derinlik = 1;
		try {
			$sonuc = call_user_func( $is );
			if ( false === $wpdb->query( 'COMMIT' ) ) {
				throw new YP_Veri_Hatasi( 'Transaction onaylanamadı.' );
			}
			self::$derinlik = 0;
			return $sonuc;
		} catch ( Throwable $e ) {
			$wpdb->query( 'ROLLBACK' );
			self::$derinlik = 0;
			// KURAL: Geri alınan işlemin önbellek sürümü de tazelenir — hiçbir ekran yarım değeri göstermez.
			YP_Cekirdek::veri_degisti();
			throw $e;
		}
	}

	// KURAL: İstek, açık bir transaction varken biterse (beklenmeyen exit) değişiklikler geri alınır.
	public static function acik_islemi_geri_al() {
		global $wpdb;
		if ( self::$derinlik > 0 && $wpdb ) {
			$wpdb->query( 'ROLLBACK' );
			self::$derinlik = 0;
		}
	}

	/**
	 * Transaction içindeki bir yazmanın sonucunu denetler.
	 * KURAL: Transaction dışında davranış değişmez (false döner); içindeyse hata fırlatılır ve her şey geri alınır.
	 *
	 * @param int|bool $sonuc $wpdb->insert/update/delete/query dönüşü.
	 * @throws YP_Veri_Hatasi
	 */
	public static function yazildi_mi( $sonuc ) {
		if ( false === $sonuc && self::$derinlik > 0 ) {
			global $wpdb;
			throw new YP_Veri_Hatasi( 'Veritabanına yazılamadı: ' . $wpdb->last_error );
		}
		return $sonuc;
	}
}

/**
 * Para işlemi sırasında veritabanı yazması başarısız olduğunda fırlatılır.
 */
class YP_Veri_Hatasi extends RuntimeException {}

/**
 * Transaction içinde yeniden yapılan denetim işlemi durdurduğunda fırlatılır (örn. satır bu arada tahsil edilmiş).
 * KURAL: Mesajı kullanıcıya gösterilecek Türkçe metindir; işlem yine tamamen geri alınır.
 */
class YP_Islem_Engeli extends YP_Veri_Hatasi {}
