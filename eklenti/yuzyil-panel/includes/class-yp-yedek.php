<?php
defined( 'ABSPATH' ) || exit;

/**
 * Yedek alma ve geri yükleme.
 *
 * Yedek tek bir sıkıştırılmış dosyadır ve iki şey taşır:
 *  1) Okunabilir Excel dosyaları — aday listesi, kasa geçmişi, referanslar, tanımlar.
 *  2) yedek.json — bütün tabloların satır satır tam kopyası; geri yükleme YALNIZCA bundan yapılır.
 *
 * KURAL: Geri yükleme Excel'den değil yedek.json'dan yapılır — Excel okunmak içindir, veri kaybı olmaz.
 */
final class YP_Yedek {

	// KURAL: Yedek biçimi değişirse bu numara artar; eski panel yeni yedeği yanlışlıkla yüklemeye çalışmaz.
	const BICIM = 1;

	const VERI_DOSYASI = 'yedek.json';
	const BILGI_DOSYASI = 'BILGI.txt';

	/**
	 * Yedeğe giren tablolar. Sıra önemlidir: geri yüklemede önce tanımlar, sonra onlara bağlı kayıtlar yazılır.
	 */
	public static function tablolar() {
		return array( 'tanimlar', 'hesaplar', 'referanslar', 'adaylar', 'islemler', 'hareketler', 'gorusmeler', 'sms', 'log' );
	}

	public static function tablo_adlari() {
		return array(
			'tanimlar'    => 'Tanımlar',
			'hesaplar'    => 'Hesaplar',
			'referanslar' => 'Referanslar',
			'adaylar'     => 'Adaylar',
			'islemler'    => 'İşlemler',
			'hareketler'  => 'Borç, ödeme ve kasa hareketleri',
			'gorusmeler'  => 'Görüşme notları',
			'sms'         => 'SMS gönderimleri',
			'log'         => 'İşlem günlüğü',
		);
	}

	// ---- Yedek alma -----------------------------------------------------

	/**
	 * Yedeği geçici bir dosyaya kurar ve yolunu döndürür. Hata olursa WP_Error döner.
	 */
	public static function olustur( $fotograflar = false ) {
		global $wpdb;

		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'zip_yok', 'Sunucuda sıkıştırma desteği (ZipArchive) kapalı. Barındırma firmanızdan açılmasını isteyin.' );
		}
		if ( ! function_exists( 'wp_tempnam' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$veri    = array();
		$sayilar = array();
		foreach ( self::tablolar() as $tablo ) {
			$ad = YP_Cekirdek::tablo( $tablo );
			// KURAL: Satırlar olduğu gibi alınır — silinmiş kayıtlar da yedeğe girer, geri yükleme birebir olsun.
			$satirlar = $wpdb->get_results( "SELECT * FROM {$ad}", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$veri[ $tablo ]    = is_array( $satirlar ) ? $satirlar : array();
			$sayilar[ $tablo ] = count( $veri[ $tablo ] );
		}

		// KURAL: Ayarlar da yedeğe girer ama SMS şifresi ASLA yazılmaz — yedek dosyası başkasının eline geçebilir.
		$ayarlar = YP_Cekirdek::ayarlar();
		unset( $ayarlar['sms_sifre'], $ayarlar['kilit_hash'] );

		$govde = array(
			'tablolar' => $veri,
			'ayarlar'  => $ayarlar,
		);
		$govde_json = wp_json_encode( $govde, JSON_UNESCAPED_UNICODE );
		if ( false === $govde_json ) {
			return new WP_Error( 'json_hata', 'Veri yedek biçimine çevrilemedi.' );
		}

		$paket = array(
			'bicim'     => self::BICIM,
			'surum'     => YP_SURUM,
			'db_surum'  => YP_DB_SURUM,
			'tarih'     => current_time( 'mysql' ),
			'site'      => home_url( '/' ),
			'kurum'     => (string) YP_Cekirdek::ayar( 'kurum_adi' ),
			'sayilar'   => $sayilar,
			// KURAL: Özet (parmak izi) dosya bozulursa geri yüklemeyi durdurur.
			'ozet'      => hash( 'sha256', $govde_json ),
			'govde'     => $govde,
		);
		$paket_json = wp_json_encode( $paket, JSON_UNESCAPED_UNICODE );
		if ( false === $paket_json ) {
			return new WP_Error( 'json_hata', 'Yedek dosyası yazılamadı.' );
		}

		$gecici = wp_tempnam( 'yp-yedek' );
		$zip    = new ZipArchive();
		if ( true !== $zip->open( $gecici, ZipArchive::OVERWRITE ) ) {
			return new WP_Error( 'zip_acilmadi', 'Yedek dosyası oluşturulamadı.' );
		}

		$zip->addFromString( self::VERI_DOSYASI, $paket_json );
		$zip->addFromString( self::BILGI_DOSYASI, self::bilgi_metni( $paket ) );

		foreach ( self::excel_dosyalari( $veri ) as $yol => $tablo ) {
			$zip->addFromString( $yol, $tablo );
		}

		$foto_sayisi = 0;
		if ( $fotograflar ) {
			$foto_sayisi = self::fotograflari_ekle( $zip, $veri['adaylar'] );
		}

		$zip->close();

		if ( ! file_exists( $gecici ) || filesize( $gecici ) < 100 ) {
			return new WP_Error( 'zip_bos', 'Yedek dosyası boş çıktı.' );
		}

		YP_Cekirdek::log( 'yedek', 0, 'yedek_al', sprintf( '%d aday, %d hareket%s', $sayilar['adaylar'], $sayilar['hareketler'], $foto_sayisi ? ', ' . $foto_sayisi . ' fotoğraf' : '' ) );
		return $gecici;
	}

	public static function dosya_adi() {
		return sanitize_file_name( 'yuzyil-panel-yedek-' . current_time( 'Y-m-d-Hi' ) ) . '.zip';
	}

	/**
	 * Yedeği tarayıcıya indirir.
	 */
	public static function indir( $fotograflar = false ) {
		$yol = self::olustur( $fotograflar );
		if ( is_wp_error( $yol ) ) {
			return $yol;
		}
		nocache_headers();
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . self::dosya_adi() . '"' );
		header( 'Content-Length: ' . filesize( $yol ) );
		readfile( $yol ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		wp_delete_file( $yol );
		exit;
	}

	private static function bilgi_metni( array $paket ) {
		$s  = "YÜZYIL PANEL — VERİ YEDEĞİ\r\n";
		$s .= str_repeat( '=', 40 ) . "\r\n\r\n";
		$s .= 'Kurum        : ' . $paket['kurum'] . "\r\n";
		$s .= 'Yedek tarihi : ' . YP_Bicim::tarih_saat( $paket['tarih'] ) . "\r\n";
		$s .= 'Panel sürümü : ' . $paket['surum'] . "\r\n";
		$s .= 'Veri sürümü  : ' . $paket['db_surum'] . "\r\n\r\n";
		$s .= "İÇİNDEKİ KAYIT SAYILARI\r\n";
		$adlar = self::tablo_adlari();
		foreach ( $paket['sayilar'] as $tablo => $adet ) {
			$s .= '  ' . str_pad( isset( $adlar[ $tablo ] ) ? $adlar[ $tablo ] : $tablo, 34 ) . ' : ' . $adet . "\r\n";
		}
		$s .= "\r\nDOSYALAR\r\n";
		$s .= "  yedek.json    Geri yükleme bu dosyadan yapılır. SİLMEYİN, DEĞİŞTİRMEYİN.\r\n";
		$s .= "  excel\\        Okumak ve saklamak için hazırlanmış listeler.\r\n";
		$s .= "\r\nGERİ YÜKLEME\r\n";
		$s .= "  Panel > Yedekleme ekranından bu dosyayı seçip geri yükleyin.\r\n";
		$s .= "  Geri yükleme mevcut verinin TAMAMINI siler ve yerine bu yedeği yazar.\r\n";
		$s .= "\r\nNOT: SMS şifresi ve panel giriş şifresi güvenlik gereği yedeğe yazılmaz;\r\n";
		$s .= "geri yükledikten sonra bunları yeniden girmeniz gerekir.\r\n";
		return $s;
	}

	// ---- Excel dosyaları ------------------------------------------------

	/**
	 * Okunabilir Excel dosyalarını üretir: yol => içerik.
	 */
	private static function excel_dosyalari( array $veri ) {
		$dosyalar = array();

		$dosyalar['excel/adaylar.xlsx'] = YP_Excel::icerik(
			self::aday_basliklari(),
			self::aday_satirlari( $veri ),
			self::aday_turleri(),
			'Aday Listesi — ' . YP_Bicim::tarih( YP_Cekirdek::bugun() )
		);

		$dosyalar['excel/kasa-hareketleri.xlsx'] = YP_Excel::icerik(
			array( 'Tarih', 'Tür', 'Aday No', 'Adı Soyadı', 'Referans', 'Kalem', 'Hesap', 'Hedef Hesap', 'Borç Tipi', 'Vade', 'Tutar', 'Ödeme Türü', 'Durum', 'Açıklama', 'Silindi' ),
			self::kasa_satirlari( $veri ),
			array( 'metin', 'metin', 'sayi', 'metin', 'metin', 'metin', 'metin', 'metin', 'metin', 'metin', 'tutar', 'metin', 'metin', 'metin', 'metin' ),
			'Kasa ve Ödeme Hareketleri — ' . YP_Bicim::tarih( YP_Cekirdek::bugun() )
		);

		$dosyalar['excel/referanslar.xlsx'] = YP_Excel::icerik(
			array( 'No', 'Unvan', 'Yetkili', 'Telefon', 'GSM', 'E-Posta', 'Adres', 'Notlar', 'Durum' ),
			self::referans_satirlari( $veri ),
			array( 'sayi', 'metin', 'metin', 'metin', 'metin', 'metin', 'metin', 'metin', 'metin' ),
			'Referanslar — ' . YP_Bicim::tarih( YP_Cekirdek::bugun() )
		);

		$dosyalar['excel/tanimlar-ve-hesaplar.xlsx'] = YP_Excel::icerik(
			array( 'Grup', 'No', 'Adı', 'Sıra', 'Durum', 'Ek Bilgi' ),
			self::tanim_satirlari( $veri ),
			array( 'metin', 'sayi', 'metin', 'sayi', 'metin', 'metin' ),
			'Tanımlar, Gruplar ve Hesaplar — ' . YP_Bicim::tarih( YP_Cekirdek::bugun() )
		);

		return $dosyalar;
	}

	private static function aday_basliklari() {
		return array(
			'Aday No', 'Kayıt Tarihi', 'TC No', 'Adı', 'Soyadı', 'Baba Adı', 'Ana Adı', 'Doğum Tarihi', 'Doğum Yeri',
			'Cinsiyet', 'GSM 1', 'GSM 2', 'Ev Telefonu', 'E-Posta', 'İl', 'İlçe', 'Adres', 'Meslek', 'Öğrenim',
			'Ehliyet Sınıfı', 'Ehliyet No', 'Ehliyet Tarihi', 'Ehliyet İli', 'Referans', 'Özel Kod 1', 'Özel Kod 2',
			'Sarı Not', 'Notlar', 'Evrak Tamam', 'Kayıt Durumu', 'Arşiv', 'Silindi', 'Toplam Borç', 'Ödenen', 'Kalan Bakiye',
		);
	}

	private static function aday_turleri() {
		$t = array_fill( 0, 35, 'metin' );
		$t[0] = 'sayi';
		$t[32] = 'tutar';
		$t[33] = 'tutar';
		$t[34] = 'tutar';
		return $t;
	}

	private static function aday_satirlari( array $veri ) {
		$referans = self::anahtarla( $veri['referanslar'], 'id', 'unvan' );
		$tanim    = self::anahtarla( $veri['tanimlar'], 'id', 'ad' );

		// Aday başına borç/ödenen toplamı yedekteki hareketlerden hesaplanır.
		$borc   = array();
		$odenen = array();
		foreach ( $veri['hareketler'] as $h ) {
			if ( 'ADAY' !== $h['kayit_turu'] || 1 === (int) $h['silindi'] || 'KENDISI' === $h['odeme_turu'] || ! $h['aday_id'] ) {
				continue;
			}
			$id = (int) $h['aday_id'];
			if ( in_array( $h['durum'], array( 'ODENDI', 'ODENMEDI' ), true ) ) {
				$borc[ $id ] = ( isset( $borc[ $id ] ) ? $borc[ $id ] : 0 ) + (float) $h['tutar'];
			}
			if ( 'ODENDI' === $h['durum'] ) {
				$odenen[ $id ] = ( isset( $odenen[ $id ] ) ? $odenen[ $id ] : 0 ) + (float) $h['tutar'];
			}
		}

		$satirlar = array();
		foreach ( $veri['adaylar'] as $a ) {
			$id = (int) $a['id'];
			$b  = isset( $borc[ $id ] ) ? $borc[ $id ] : 0;
			$o  = isset( $odenen[ $id ] ) ? $odenen[ $id ] : 0;
			$satirlar[] = array(
				$a['aday_no'],
				YP_Bicim::tarih( $a['kayit_tarihi'] ),
				(string) $a['tc_no'],
				$a['adi'],
				$a['soyadi'],
				$a['baba_adi'],
				$a['ana_adi'],
				YP_Bicim::tarih( $a['dogum_tarihi'] ),
				$a['dogum_yeri'],
				$a['cinsiyet'],
				$a['gsm_1'],
				$a['gsm_2'],
				$a['ev_telefonu'],
				$a['e_posta'],
				$a['il'],
				$a['ilce'],
				$a['adres'],
				$a['meslek'],
				$a['tahsil'],
				$a['ehliyet_sinifi'],
				$a['ehliyet_no'],
				YP_Bicim::tarih( $a['ehliyet_tarihi'] ),
				$a['ehliyet_il'],
				self::bak( $referans, $a['referans_id'] ),
				self::bak( $tanim, $a['ozel_kod1_id'] ),
				self::bak( $tanim, $a['ozel_kod2_id'] ),
				$a['sari_not'],
				$a['ozel_notlar'],
				( (int) $a['evrak_tamam'] ? 'Evet' : 'Hayır' ),
				$a['kayit_durumu'],
				( (int) $a['arsiv'] ? 'Evet' : 'Hayır' ),
				( (int) $a['silindi'] ? 'Evet' : 'Hayır' ),
				$b,
				$o,
				round( $b - $o, 2 ),
			);
		}
		return $satirlar;
	}

	private static function kasa_satirlari( array $veri ) {
		$hesap    = self::anahtarla( $veri['hesaplar'], 'id', 'ad' );
		$tanim    = self::anahtarla( $veri['tanimlar'], 'id', 'ad' );
		$referans = self::anahtarla( $veri['referanslar'], 'id', 'unvan' );
		$aday     = array();
		foreach ( $veri['adaylar'] as $a ) {
			$aday[ (int) $a['id'] ] = array( 'no' => $a['aday_no'], 'ad' => trim( $a['adi'] . ' ' . $a['soyadi'] ) );
		}

		$turler = array(
			'ADAY'     => 'Aday borç/ödeme',
			'GELIR'    => 'Gelir',
			'GIDER'    => 'Gider',
			'TRANSFER' => 'Hesaplar arası transfer',
		);

		$satirlar = array();
		foreach ( $veri['hareketler'] as $h ) {
			$id = (int) $h['aday_id'];
			$satirlar[] = array(
				YP_Bicim::tarih( $h['odeme_tarihi'] ? $h['odeme_tarihi'] : $h['vade_tarihi'] ),
				isset( $turler[ $h['kayit_turu'] ] ) ? $turler[ $h['kayit_turu'] ] : $h['kayit_turu'],
				isset( $aday[ $id ] ) ? $aday[ $id ]['no'] : '',
				isset( $aday[ $id ] ) ? $aday[ $id ]['ad'] : '',
				self::bak( $referans, $h['referans_id'] ),
				self::bak( $tanim, $h['kalem_id'] ),
				self::bak( $hesap, $h['hesap_id'] ),
				self::bak( $hesap, $h['hedef_hesap_id'] ),
				$h['borc_tipi'],
				YP_Bicim::tarih( $h['vade_tarihi'] ),
				(float) $h['tutar'],
				$h['odeme_turu'],
				$h['durum'],
				$h['aciklama'],
				( (int) $h['silindi'] ? 'Evet' : 'Hayır' ),
			);
		}
		return $satirlar;
	}

	private static function referans_satirlari( array $veri ) {
		$satirlar = array();
		foreach ( $veri['referanslar'] as $r ) {
			$satirlar[] = array(
				$r['id'],
				$r['unvan'],
				$r['ad_soyad'],
				$r['telefon'],
				$r['gsm'],
				$r['e_posta'],
				$r['adres'],
				$r['notlar'],
				( (int) $r['aktif'] ? 'Aktif' : 'Pasif' ),
			);
		}
		return $satirlar;
	}

	private static function tanim_satirlari( array $veri ) {
		$gruplar  = YP_Veri::tanim_turleri();
		$satirlar = array();
		foreach ( $veri['tanimlar'] as $t ) {
			$satirlar[] = array(
				isset( $gruplar[ $t['tur'] ] ) ? $gruplar[ $t['tur'] ] : $t['tur'],
				$t['id'],
				$t['ad'],
				$t['sira'],
				( (int) $t['aktif'] ? 'Aktif' : 'Pasif' ),
				'',
			);
		}
		foreach ( $veri['hesaplar'] as $h ) {
			$satirlar[] = array(
				'Hesaplar (Kasa / Banka)',
				$h['id'],
				$h['ad'],
				$h['sira'],
				( (int) $h['aktif'] ? 'Aktif' : 'Pasif' ),
				trim( $h['tur'] . ' ' . $h['banka_adi'] . ' ' . $h['iban'] ),
			);
		}
		return $satirlar;
	}

	private static function anahtarla( array $satirlar, $anahtar, $deger ) {
		$sonuc = array();
		foreach ( $satirlar as $s ) {
			$sonuc[ (int) $s[ $anahtar ] ] = (string) $s[ $deger ];
		}
		return $sonuc;
	}

	private static function bak( array $cizelge, $id ) {
		$id = (int) $id;
		return isset( $cizelge[ $id ] ) ? $cizelge[ $id ] : '';
	}

	/**
	 * Aday fotoğraflarını yedeğe ekler.
	 */
	private static function fotograflari_ekle( ZipArchive $zip, array $adaylar ) {
		$klasor = YP_Foto::klasor();
		if ( ! is_dir( $klasor ) ) {
			return 0;
		}
		$sayi = 0;
		foreach ( $adaylar as $a ) {
			foreach ( array( 'foto', 'foto_kucuk' ) as $alan ) {
				$dosya = (string) $a[ $alan ];
				if ( '' === $dosya ) {
					continue;
				}
				// KURAL: Yalnızca korumalı klasörün içindeki dosya okunur — yedek başka dizine uzanamaz.
				$yol = $klasor . '/' . basename( $dosya );
				if ( is_readable( $yol ) && is_file( $yol ) ) {
					$zip->addFile( $yol, 'fotograflar/' . basename( $dosya ) );
					$sayi++;
				}
			}
		}
		return $sayi;
	}

	// ---- Yedeği okuma ---------------------------------------------------

	/**
	 * Yedek dosyasını açar, doğrular ve içindekileri döndürür.
	 * Hata olursa WP_Error döner; hiçbir şey değiştirilmez.
	 */
	public static function oku( $zip_yolu ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'zip_yok', 'Sunucuda sıkıştırma desteği (ZipArchive) kapalı.' );
		}
		$zip = new ZipArchive();
		if ( true !== $zip->open( $zip_yolu ) ) {
			return new WP_Error( 'acilmadi', 'Dosya açılamadı. Yüzyıl Panel yedeği olduğundan emin olun.' );
		}
		$json = $zip->getFromName( self::VERI_DOSYASI );
		$zip->close();

		if ( false === $json || '' === $json ) {
			return new WP_Error( 'veri_yok', 'Bu dosyanın içinde ' . self::VERI_DOSYASI . ' yok. Yüzyıl Panel yedeği değil ya da dosya bozulmuş.' );
		}
		$paket = json_decode( $json, true );
		if ( ! is_array( $paket ) || ! isset( $paket['bicim'], $paket['govde'], $paket['ozet'] ) ) {
			return new WP_Error( 'bozuk', 'Yedek dosyası okunamadı; içeriği bozulmuş olabilir.' );
		}
		if ( (int) $paket['bicim'] > self::BICIM ) {
			return new WP_Error( 'yeni_bicim', 'Bu yedek panelin daha yeni bir sürümüyle alınmış. Önce paneli güncelleyin.' );
		}
		$govde_json = wp_json_encode( $paket['govde'], JSON_UNESCAPED_UNICODE );
		if ( ! hash_equals( (string) $paket['ozet'], hash( 'sha256', (string) $govde_json ) ) ) {
			return new WP_Error( 'ozet_tutmadi', 'Yedek dosyasının parmak izi tutmuyor — dosya bozulmuş ya da değiştirilmiş. Güvenlik gereği geri yükleme yapılmadı.' );
		}
		if ( ! isset( $paket['govde']['tablolar'] ) || ! is_array( $paket['govde']['tablolar'] ) ) {
			return new WP_Error( 'tablo_yok', 'Yedekte tablo verisi bulunamadı.' );
		}
		return $paket;
	}

	/**
	 * Şu anki veritabanındaki kayıt sayıları — geri yükleme öncesi karşılaştırma için.
	 */
	public static function mevcut_sayilar() {
		global $wpdb;
		$sayilar = array();
		foreach ( self::tablolar() as $tablo ) {
			$ad               = YP_Cekirdek::tablo( $tablo );
			$sayilar[ $tablo ] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$ad}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		return $sayilar;
	}

	// ---- Geri yükleme ---------------------------------------------------

	/**
	 * Yedeği geri yükler: mevcut veriyi siler, yedekteki satırları yazar.
	 * Tek bir transaction içinde çalışır; herhangi bir adım hata verirse hiçbir şey değişmez.
	 */
	public static function geri_yukle( array $paket ) {
		global $wpdb;

		$tablolar = $paket['govde']['tablolar'];
		$atlanan  = array();
		$yazilan  = array();

		try {
			YP_Veri::tek_islemde( function () use ( $wpdb, $tablolar, &$atlanan, &$yazilan ) {
				foreach ( YP_Yedek::tablolar() as $tablo ) {
					$ad = YP_Cekirdek::tablo( $tablo );

					// KURAL: Yedekte olmayan tablo atlanır — eski bir yedek yeni tabloyu silmez.
					if ( ! isset( $tablolar[ $tablo ] ) ) {
						continue;
					}

					$sutunlar = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$ad}", 0 ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					if ( ! $sutunlar ) {
						throw new YP_Veri_Hatasi( $ad . ' tablosu bulunamadı.' );
					}

					// KURAL: Silme ve yazma tek transaction içindedir; biri bile başarısız olursa hepsi geri alınır.
					YP_Veri::yazildi_mi( $wpdb->query( "DELETE FROM {$ad}" ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

					$adet = 0;
					foreach ( $tablolar[ $tablo ] as $satir ) {
						if ( ! is_array( $satir ) ) {
							continue;
						}
						// KURAL: Yalnızca bu paneldeki tabloda GERÇEKTEN olan sütunlar yazılır —
						// yedek eski ya da yeni sürümden gelse bile geri yükleme çalışır.
						$yazilacak = array();
						foreach ( $satir as $sutun => $deger ) {
							if ( in_array( $sutun, $sutunlar, true ) ) {
								$yazilacak[ $sutun ] = $deger;
							} elseif ( ! isset( $atlanan[ $tablo ][ $sutun ] ) ) {
								$atlanan[ $tablo ][ $sutun ] = true;
							}
						}
						if ( ! $yazilacak ) {
							continue;
						}
						YP_Veri::yazildi_mi( $wpdb->insert( $ad, $yazilacak ) );
						$adet++;
					}
					$yazilan[ $tablo ] = $adet;
				}
				return true;
			} );
		} catch ( Throwable $e ) {
			// KURAL: Hata olursa transaction geri alınmıştır — veri yedek öncesi hâliyle durur.
			return new WP_Error( 'geri_yukleme', 'Geri yükleme yapılamadı, hiçbir kayıt değiştirilmedi. Sebep: ' . $e->getMessage() );
		}

		// KURAL: Ayarlar veriden sonra yazılır; şifreler yedekte olmadığı için mevcut şifreler korunur.
		if ( isset( $paket['govde']['ayarlar'] ) && is_array( $paket['govde']['ayarlar'] ) ) {
			$ayarlar = $paket['govde']['ayarlar'];
			unset( $ayarlar['sms_sifre'], $ayarlar['kilit_hash'], $ayarlar['kilit_kullanici'], $ayarlar['slug'], $ayarlar['foto_klasoru'] );
			YP_Cekirdek::ayar_kaydet( $ayarlar );
		}

		YP_Cekirdek::veri_degisti();
		YP_Cekirdek::log( 'yedek', 0, 'geri_yukle', sprintf( 'Yedek geri yüklendi (%s). %d aday, %d hareket.', $paket['tarih'], isset( $yazilan['adaylar'] ) ? $yazilan['adaylar'] : 0, isset( $yazilan['hareketler'] ) ? $yazilan['hareketler'] : 0 ) );

		return array( 'yazilan' => $yazilan, 'atlanan' => $atlanan );
	}
}
