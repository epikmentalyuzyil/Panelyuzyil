<?php
defined( 'ABSPATH' ) || exit;

/**
 * Tabloları, varsayılan tanımları ve korumalı fotoğraf klasörünü oluşturur.
 */
final class YP_Kurulum {

	public static function kur() {
		self::tablolari_olustur();
		self::eski_yapiyi_temizle();
		self::ayarlari_hazirla();
		self::varsayilanlari_ekle();
		require_once YP_DIZIN . 'includes/class-yp-foto.php';
		YP_Foto::klasoru_hazirla();
		update_option( 'yp_db_surum', YP_DB_SURUM, false );
	}

	/**
	 * Kaldırılan makbuz numarası sisteminin veritabanı kalıntılarını siler (DB sürüm 3).
	 * KURAL: dbDelta sütun silmez; eski kurulumlarda makbuz_no sütunu, dizini ve sayaç tablosu burada kaldırılır.
	 * KURAL: Silme başarısız olsa bile eklenti çalışmaya devam eder — hiçbir kod artık bu alanları kullanmaz.
	 */
	private static function eski_yapiyi_temizle() {
		global $wpdb;
		$h       = YP_Cekirdek::tablo( 'hareketler' );
		$gizli   = $wpdb->suppress_errors( true );
		$sutunlar = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$h}", 0 ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( in_array( 'makbuz_no', $sutunlar, true ) ) {
			// KURAL: Önce dizin, sonra sütun kaldırılır — bazı veritabanları dizinli sütunu doğrudan silmez.
			$wpdb->query( "ALTER TABLE {$h} DROP INDEX makbuz_no" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$h} DROP COLUMN makbuz_no" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		$wpdb->query( 'DROP TABLE IF EXISTS ' . YP_Cekirdek::tablo( 'sayaclar' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->suppress_errors( $gizli );
	}

	// KURAL: Metin sütunları Türkçe karşılaştırma düzeniyle kurulur — İ/ı, Ş/ş arama ve sıralaması doğru çalışır.
	private static function karakter_seti() {
		global $wpdb;
		if ( $wpdb->has_cap( 'utf8mb4' ) ) {
			return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_turkish_ci';
		}
		return 'DEFAULT CHARACTER SET utf8 COLLATE utf8_turkish_ci';
	}

	private static function tablolari_olustur() {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$cs = self::karakter_seti();
		$t  = array(
			'adaylar'    => YP_Cekirdek::tablo( 'adaylar' ),
			'islemler'   => YP_Cekirdek::tablo( 'islemler' ),
			'hareketler' => YP_Cekirdek::tablo( 'hareketler' ),
			'hesaplar'   => YP_Cekirdek::tablo( 'hesaplar' ),
			'referanslar'=> YP_Cekirdek::tablo( 'referanslar' ),
			'gorusmeler' => YP_Cekirdek::tablo( 'gorusmeler' ),
			'tanimlar'   => YP_Cekirdek::tablo( 'tanimlar' ),
			'log'        => YP_Cekirdek::tablo( 'log' ),
			'sms'        => YP_Cekirdek::tablo( 'sms' ),
			'personel'   => YP_Cekirdek::tablo( 'personel' ),
			'personel_odeme' => YP_Cekirdek::tablo( 'personel_odeme' ),
		);

		// KURAL: Tablolar arası bağ id sütunlarıyla kurulur ve kodda korunur — dbDelta yabancı anahtar desteklemez.
		$sql = array();

		$sql[] = "CREATE TABLE {$t['adaylar']} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  aday_no int(10) unsigned NOT NULL,
  kayit_tarihi date DEFAULT NULL,
  tc_no varchar(11) DEFAULT NULL,
  adi varchar(100) NOT NULL DEFAULT '',
  soyadi varchar(100) NOT NULL DEFAULT '',
  baba_adi varchar(100) DEFAULT NULL,
  ana_adi varchar(100) DEFAULT NULL,
  dogum_tarihi date DEFAULT NULL,
  dogum_yeri varchar(100) DEFAULT NULL,
  cinsiyet varchar(10) DEFAULT NULL,
  gsm_1 varchar(20) DEFAULT NULL,
  gsm_2 varchar(20) DEFAULT NULL,
  ev_telefonu varchar(20) DEFAULT NULL,
  e_posta varchar(190) DEFAULT NULL,
  adres varchar(500) DEFAULT NULL,
  ilce varchar(100) DEFAULT NULL,
  il varchar(50) DEFAULT NULL,
  meslek varchar(150) DEFAULT NULL,
  tahsil varchar(100) DEFAULT NULL,
  ehliyet_sinifi varchar(20) DEFAULT NULL,
  ehliyet_no varchar(50) DEFAULT NULL,
  ehliyet_tarihi date DEFAULT NULL,
  ehliyet_il varchar(80) DEFAULT NULL,
  referans_id bigint(20) unsigned DEFAULT NULL,
  ozel_kod1_id bigint(20) unsigned DEFAULT NULL,
  ozel_kod2_id bigint(20) unsigned DEFAULT NULL,
  sari_not varchar(150) DEFAULT NULL,
  ozel_notlar text,
  diger_alan1 varchar(150) DEFAULT NULL,
  diger_alan2 varchar(150) DEFAULT NULL,
  evrak text,
  evrak_tamam tinyint(1) NOT NULL DEFAULT 0,
  foto varchar(100) DEFAULT NULL,
  foto_kucuk varchar(100) DEFAULT NULL,
  arama_metni varchar(700) DEFAULT NULL,
  arsiv tinyint(1) NOT NULL DEFAULT 0,
  kayit_durumu varchar(20) DEFAULT NULL,
  silindi tinyint(1) NOT NULL DEFAULT 0,
  silme_zamani datetime DEFAULT NULL,
  silen bigint(20) unsigned DEFAULT NULL,
  eski_veri longtext,
  olusturan bigint(20) unsigned DEFAULT NULL,
  olusturma datetime DEFAULT NULL,
  guncelleyen bigint(20) unsigned DEFAULT NULL,
  guncelleme datetime DEFAULT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY aday_no (aday_no),
  KEY tc_no (tc_no),
  KEY ad_soyad (soyadi(50),adi(50)),
  KEY gsm_1 (gsm_1),
  KEY referans_id (referans_id),
  KEY kayit_tarihi (kayit_tarihi),
  KEY durum (silindi,arsiv),
  KEY liste (silindi,arsiv,aday_no)
) $cs;";

		$sql[] = "CREATE TABLE {$t['islemler']} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  aday_id bigint(20) unsigned NOT NULL,
  islem_turu_id bigint(20) unsigned DEFAULT NULL,
  islem_tarihi date DEFAULT NULL,
  islem_saati time DEFAULT NULL,
  referans_id bigint(20) unsigned DEFAULT NULL,
  ucret decimal(12,2) NOT NULL DEFAULT 0.00,
  durum varchar(20) NOT NULL DEFAULT 'randevu',
  rapor_no varchar(50) DEFAULT NULL,
  rapor_tarihi date DEFAULT NULL,
  gecerlilik_bitis date DEFAULT NULL,
  aciklama text,
  silindi tinyint(1) NOT NULL DEFAULT 0,
  silme_zamani datetime DEFAULT NULL,
  silen bigint(20) unsigned DEFAULT NULL,
  olusturan bigint(20) unsigned DEFAULT NULL,
  olusturma datetime DEFAULT NULL,
  guncelleyen bigint(20) unsigned DEFAULT NULL,
  guncelleme datetime DEFAULT NULL,
  PRIMARY KEY  (id),
  KEY aday_id (aday_id),
  KEY islem_tarihi (islem_tarihi),
  KEY islem_turu_id (islem_turu_id),
  KEY referans_id (referans_id),
  KEY durum (durum),
  KEY gecerlilik_bitis (gecerlilik_bitis),
  KEY aday_son (aday_id,silindi,islem_tarihi)
) $cs;";

		// KURAL: Borç, taksit, tahsilat, iade, gelir, gider ve transfer tek tabloda tutulur — masaüstü MUHASEBE tablosunun karşılığı.
		$sql[] = "CREATE TABLE {$t['hareketler']} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  kayit_turu varchar(12) NOT NULL DEFAULT 'ADAY',
  aday_id bigint(20) unsigned DEFAULT NULL,
  islem_id bigint(20) unsigned DEFAULT NULL,
  referans_id bigint(20) unsigned DEFAULT NULL,
  hesap_id bigint(20) unsigned DEFAULT NULL,
  hedef_hesap_id bigint(20) unsigned DEFAULT NULL,
  kalem_id bigint(20) unsigned DEFAULT NULL,
  borc_tipi varchar(150) DEFAULT NULL,
  vade_tarihi date DEFAULT NULL,
  tutar decimal(12,2) NOT NULL DEFAULT 0.00,
  durum varchar(12) NOT NULL DEFAULT 'ODENMEDI',
  odeme_turu varchar(20) NOT NULL DEFAULT '',
  odeme_tarihi date DEFAULT NULL,
  aciklama text,
  olusturan bigint(20) unsigned DEFAULT NULL,
  olusturma datetime DEFAULT NULL,
  tahsil_eden bigint(20) unsigned DEFAULT NULL,
  tahsil_zamani datetime DEFAULT NULL,
  silindi tinyint(1) NOT NULL DEFAULT 0,
  silme_zamani datetime DEFAULT NULL,
  silen bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY  (id),
  KEY aday (aday_id,silindi),
  KEY islem_id (islem_id),
  KEY referans_id (referans_id),
  KEY vade (durum,vade_tarihi),
  KEY kasa (hesap_id,odeme_tarihi),
  KEY hedef (hedef_hesap_id,odeme_tarihi),
  KEY odeme_tarihi (odeme_tarihi),
  KEY aday_borc (aday_id,silindi,durum),
  KEY kasa_gun (odeme_tarihi,durum,kayit_turu)
) $cs;";

		$sql[] = "CREATE TABLE {$t['hesaplar']} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  ad varchar(100) NOT NULL DEFAULT '',
  tur varchar(10) NOT NULL DEFAULT 'NAKIT',
  banka_adi varchar(150) DEFAULT NULL,
  iban varchar(40) DEFAULT NULL,
  acilis_bakiyesi decimal(12,2) NOT NULL DEFAULT 0.00,
  acilis_tarihi date DEFAULT NULL,
  sira int(11) NOT NULL DEFAULT 0,
  aktif tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY  (id)
) $cs;";

		$sql[] = "CREATE TABLE {$t['referanslar']} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  unvan varchar(200) DEFAULT NULL,
  ad_soyad varchar(200) NOT NULL DEFAULT '',
  telefon varchar(20) DEFAULT NULL,
  gsm varchar(20) DEFAULT NULL,
  e_posta varchar(190) DEFAULT NULL,
  adres varchar(500) DEFAULT NULL,
  notlar text,
  arama_metni varchar(500) DEFAULT NULL,
  aktif tinyint(1) NOT NULL DEFAULT 1,
  olusturma datetime DEFAULT NULL,
  PRIMARY KEY  (id),
  KEY aktif (aktif)
) $cs;";

		$sql[] = "CREATE TABLE {$t['gorusmeler']} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  aday_id bigint(20) unsigned NOT NULL,
  zaman datetime DEFAULT NULL,
  aciklama text,
  kullanici_id bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY  (id),
  KEY aday_id (aday_id)
) $cs;";

		$sql[] = "CREATE TABLE {$t['tanimlar']} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  tur varchar(30) NOT NULL,
  ad varchar(150) NOT NULL DEFAULT '',
  ucret decimal(12,2) NOT NULL DEFAULT 0.00,
  gecerlilik_ay int(11) NOT NULL DEFAULT 0,
  sira int(11) NOT NULL DEFAULT 0,
  aktif tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY  (id),
  KEY tur (tur,aktif,sira)
) $cs;";


		$sql[] = "CREATE TABLE {$t['log']} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  zaman datetime DEFAULT NULL,
  kullanici_id bigint(20) unsigned DEFAULT NULL,
  kullanici_adi varchar(100) DEFAULT NULL,
  bolum varchar(30) DEFAULT NULL,
  kayit_id bigint(20) unsigned DEFAULT NULL,
  islem varchar(30) DEFAULT NULL,
  aciklama text,
  PRIMARY KEY  (id),
  KEY zaman (zaman),
  KEY kayit (bolum,kayit_id)
) $cs;";

		// KURAL: Gönderilen her SMS satır satır saklanır — kime, ne zaman, hangi metin gittiği sonradan görülebilir.
		$sql[] = "CREATE TABLE {$t['sms']} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  toplu_id varchar(24) NOT NULL DEFAULT '',
  aday_id bigint(20) unsigned DEFAULT NULL,
  ad_soyad varchar(200) NOT NULL DEFAULT '',
  telefon varchar(15) NOT NULL DEFAULT '',
  mesaj text,
  turkce tinyint(1) NOT NULL DEFAULT 0,
  parca tinyint(3) unsigned NOT NULL DEFAULT 1,
  durum varchar(12) NOT NULL DEFAULT 'BEKLIYOR',
  saglayici_kod varchar(10) DEFAULT NULL,
  saglayici_is_no varchar(40) DEFAULT NULL,
  hata varchar(255) DEFAULT NULL,
  olusturan bigint(20) unsigned DEFAULT NULL,
  olusturma datetime DEFAULT NULL,
  PRIMARY KEY  (id),
  KEY toplu_id (toplu_id),
  KEY aday_id (aday_id),
  KEY zaman (olusturma),
  KEY durum_zaman (durum,olusturma)
) $cs;";

		// KURAL: Personel kayıtları aday tablolarından ayrıdır — iki liste birbirine karışmaz.
		$sql[] = "CREATE TABLE {$t['personel']} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  ad varchar(80) NOT NULL DEFAULT '',
  soyad varchar(80) NOT NULL DEFAULT '',
  tc_no varchar(11) DEFAULT NULL,
  dogum_tarihi date DEFAULT NULL,
  gorev varchar(80) DEFAULT NULL,
  baslama_tarihi date DEFAULT NULL,
  ayrilma_tarihi date DEFAULT NULL,
  maas decimal(12,2) NOT NULL DEFAULT 0.00,
  gsm varchar(20) DEFAULT NULL,
  e_posta varchar(190) DEFAULT NULL,
  adres varchar(300) DEFAULT NULL,
  sgk_no varchar(40) DEFAULT NULL,
  iban varchar(34) DEFAULT NULL,
  notlar text,
  aktif tinyint(1) NOT NULL DEFAULT 1,
  silindi tinyint(1) NOT NULL DEFAULT 0,
  silme_zamani datetime DEFAULT NULL,
  silen bigint(20) unsigned DEFAULT NULL,
  olusturan bigint(20) unsigned DEFAULT NULL,
  olusturma datetime DEFAULT NULL,
  guncelleyen bigint(20) unsigned DEFAULT NULL,
  guncelleme datetime DEFAULT NULL,
  PRIMARY KEY  (id),
  KEY ad_soyad (soyad(40),ad(40)),
  KEY durum (silindi,aktif),
  KEY dogum (dogum_tarihi)
) $cs;";

		// KURAL: Maaş, avans, yol ve yemek kartı aynı tabloda "tur" sütunuyla ayrılır.
		$sql[] = "CREATE TABLE {$t['personel_odeme']} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  personel_id bigint(20) unsigned NOT NULL,
  tur varchar(12) NOT NULL DEFAULT 'MAAS',
  donem varchar(7) DEFAULT NULL,
  tutar decimal(12,2) NOT NULL DEFAULT 0.00,
  tarih date DEFAULT NULL,
  hesap_id bigint(20) unsigned DEFAULT NULL,
  hareket_id bigint(20) unsigned DEFAULT NULL,
  aciklama varchar(250) DEFAULT NULL,
  silindi tinyint(1) NOT NULL DEFAULT 0,
  silme_zamani datetime DEFAULT NULL,
  silen bigint(20) unsigned DEFAULT NULL,
  olusturan bigint(20) unsigned DEFAULT NULL,
  olusturma datetime DEFAULT NULL,
  PRIMARY KEY  (id),
  KEY personel (personel_id,silindi),
  KEY tarih (tarih),
  KEY donem (personel_id,tur,donem),
  KEY hareket_id (hareket_id)
) $cs;";

		foreach ( $sql as $s ) {
			dbDelta( $s );
		}
	}

	private static function ayarlari_hazirla() {
		$ayarlar = YP_Cekirdek::ayarlar();
		// KURAL: Fotoğraf klasörünün adı rastgele üretilir — adres tahmin edilemez.
		if ( '' === $ayarlar['foto_klasoru'] ) {
			$ayarlar['foto_klasoru'] = 'yp-korumali-' . strtolower( wp_generate_password( 20, false, false ) );
		}
		update_option( YP_Cekirdek::AYAR_ANAHTARI, $ayarlar, false );
	}

	// KURAL: Varsayılan tanımlar yalnızca liste boşsa eklenir — kullanıcının düzenlemeleri ezilmez.
	private static function varsayilanlari_ekle() {
		global $wpdb;
		$tanimlar = YP_Cekirdek::tablo( 'tanimlar' );
		$hesaplar = YP_Cekirdek::tablo( 'hesaplar' );

		$varsayilan = array(
			'islem_turu'  => array(
				array( 'SRC Psikoteknik', 0, 60 ),
				array( 'Ehliyet İadesi (Ceza Puanı)', 0, 0 ),
				array( 'Ehliyet İadesi (Alkol)', 0, 0 ),
				array( 'İş Makinesi Operatörü', 0, 60 ),
				array( 'Tekrar Değerlendirme', 0, 0 ),
				array( 'Diğer', 0, 0 ),
			),
			'gider_kalemi' => array(
				array( 'Kira' ), array( 'Elektrik / Su / Doğalgaz' ), array( 'Telefon / İnternet' ),
				array( 'Referans Ödemesi' ), array( 'Personel' ), array( 'Vergi / SGK' ),
				array( 'Muhasebe' ), array( 'Lisans / Cihaz / Teknik Destek' ), array( 'Reklam' ),
				array( 'Market / Mutfak' ), array( 'Banka / POS Komisyonu' ), array( 'Diğer Giderler' ),
			),
			'gelir_kalemi' => array( array( 'Diğer Gelirler' ) ),
			'evrak'        => array( array( 'Kimlik Fotokopisi' ), array( 'Ehliyet Fotokopisi' ), array( 'Fotoğraf' ) ),
			'ozel_kod'     => array(),
		);

		foreach ( $varsayilan as $tur => $satirlar ) {
			$var = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tanimlar} WHERE tur = %s", $tur ) );
			if ( $var > 0 ) {
				continue;
			}
			foreach ( $satirlar as $i => $s ) {
				$wpdb->insert(
					$tanimlar,
					array(
						'tur'           => $tur,
						'ad'            => $s[0],
						'ucret'         => isset( $s[1] ) ? $s[1] : 0,
						'gecerlilik_ay' => isset( $s[2] ) ? $s[2] : 0,
						'sira'          => $i + 1,
						'aktif'         => 1,
					),
					array( '%s', '%s', '%f', '%d', '%d', '%d' )
				);
			}
		}

		if ( 0 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$hesaplar}" ) ) {
			$wpdb->insert( $hesaplar, array( 'ad' => 'Nakit Kasa', 'tur' => 'NAKIT', 'sira' => 1, 'aktif' => 1, 'acilis_tarihi' => YP_Cekirdek::bugun() ) );
			$wpdb->insert( $hesaplar, array( 'ad' => 'Banka', 'tur' => 'BANKA', 'sira' => 2, 'aktif' => 1, 'acilis_tarihi' => YP_Cekirdek::bugun() ) );
			$wpdb->insert( $hesaplar, array( 'ad' => 'PTT', 'tur' => 'POSTA', 'sira' => 3, 'aktif' => 1, 'acilis_tarihi' => YP_Cekirdek::bugun() ) );
		}
	}
}
