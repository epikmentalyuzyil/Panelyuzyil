<?php
defined( 'ABSPATH' ) || exit;

/**
 * Aday kartının veri değiştiren işlemleri. Yönlendirici her çağrıdan önce yetki ve nonce doğrular.
 */
final class YP_Aday_Islem extends YP_Ekran {

	// ---- Yardımcılar ----------------------------------------------------

	private static function aday_veya_don( $id ) {
		$aday = YP_Veri::aday( $id );
		if ( ! $aday ) {
			self::yonlendir( array( 'ekran' => 'adaylar' ), 'Aday bulunamadı.', 'hata' );
		}
		return $aday;
	}

	/**
	 * Aday kartındaki "İşlem Bilgileri" seçimini kayda yazar.
	 * KURAL: Kartta açık bir kayıt varsa yalnızca onun işlem türü değişir (ücret ve ödemeler el değmeden kalır);
	 * açık kayıt yoksa ve bir tür seçildiyse adaya yeni bir kayıt açılır.
	 */
	private static function kart_islemi_yaz( $aday ) {
		global $wpdb;
		if ( ! $aday || ! isset( $_POST['kart_islem_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$tur_id   = YP_Guvenlik::tamsayi( 'islem_turu_id' );
		$islem_id = YP_Guvenlik::tamsayi( 'kart_islem_id' );
		$tur      = YP_Veri::tanim( $tur_id );
		if ( ! $tur || 'islem_turu' !== $tur->tur ) {
			return;
		}
		if ( $islem_id ) {
			$islem = YP_Veri::islem( $islem_id );
			if ( ! $islem || (int) $islem->aday_id !== (int) $aday->id || (int) $islem->islem_turu_id === (int) $tur->id ) {
				return;
			}
			$wpdb->update( YP_Cekirdek::tablo( 'islemler' ), array(
				'islem_turu_id' => $tur->id,
				'guncelleyen'   => YP_Cekirdek::kullanici_id(),
				'guncelleme'    => YP_Cekirdek::simdi(),
			), array( 'id' => $islem->id ) );
			YP_Cekirdek::veri_degisti();
			self::log( $aday->id, 'işlem', 'Kart işlem türü değişti: ' . $tur->ad . ' (#' . $islem->id . ')' );
			self::mesaj( 'İşlem türü güncellendi: ' . esc_html( $tur->ad ) );
			return;
		}
		// Açık kayıt yok: seçilen türde yeni kayıt açılır (ücret ve ödeme sonradan girilir).
		$sonuc = self::islem_olustur( $aday );
		if ( is_string( $sonuc ) ) {
			self::mesaj( 'Kayıt açılamadı: ' . esc_html( $sonuc ), 'hata' );
			return;
		}
		foreach ( $sonuc['mesajlar'] as $m ) {
			self::mesaj( $m );
		}
	}

	private static function log( $aday_id, $islem, $aciklama ) {
		YP_Cekirdek::log( 'aday', $aday_id, $islem, $aciklama );
	}

	private static function aday_url( $id, $sekme = '' ) {
		$a = array( 'ekran' => 'aday', 'id' => (int) $id );
		if ( '' !== $sekme ) {
			$a['sekme'] = $sekme;
		}
		return $a;
	}

	// KURAL: Tahsilat için ödeme türü zorunludur; "kendisi yatırdı" dışında hesap da zorunludur.
	private static function odeme_bilgisi_oku() {
		$odeme_turu = YP_Guvenlik::secim( 'odeme_turu', array_keys( YP_Veri::odeme_turleri() ) );
		$hesap_id   = YP_Guvenlik::tamsayi( 'hesap_id' );
		if ( '' !== $odeme_turu && 'KENDISI' !== $odeme_turu && ! $hesap_id ) {
			$hesap_id = YP_Veri::varsayilan_hesap( $odeme_turu );
		}
		if ( $hesap_id && ! YP_Veri::hesap( $hesap_id ) ) {
			$hesap_id = 0;
		}
		$tarih = YP_Guvenlik::tarih( 'odeme_tarihi' );
		return array(
			'odeme_turu' => $odeme_turu,
			'hesap_id'   => 'KENDISI' === $odeme_turu ? 0 : $hesap_id,
			'tarih'      => $tarih ? $tarih : YP_Cekirdek::bugun(),
		);
	}

	private static function odeme_bilgisi_hatasi( array $o ) {
		if ( '' === $o['odeme_turu'] ) {
			return 'Ödeme türünü seçin.';
		}
		if ( 'KENDISI' !== $o['odeme_turu'] && ! $o['hesap_id'] ) {
			return 'Paranın gireceği hesabı seçin (Tanımlar > Hesaplar bölümünden hesap ekleyebilirsiniz).';
		}
		return '';
	}

	/**
	 * Bir borç satırını tahsil eder. Kısmi ödemede satır ikiye bölünür.
	 * KURAL: Transaction içinde çağrılır; yazma başarısız olursa tüm tahsilat geri alınır.
	 *
	 * @return int Tahsil edilen (ÖDENDİ olan) satırın kimliği — makbuz bu kimlikle yazdırılır.
	 */
	private static function satiri_tahsil_et( $satir, $tutar, array $o, $aciklama = '' ) {
		$tutar = round( (float) $tutar, 2 );
		$kalan = round( (float) $satir->tutar - $tutar, 2 );
		// KURAL: Kısmi ödemede satır ödenen tutar kadar ÖDENDİ olur, kalan aynı vadeyle yeni ÖDENMEDİ satır açılır — toplam borç değişmez.
		if ( $kalan > 0 ) {
			YP_Veri::hareket_ekle( array(
				'kayit_turu'  => 'ADAY',
				'aday_id'     => $satir->aday_id,
				'islem_id'    => $satir->islem_id,
				'borc_tipi'   => $satir->borc_tipi,
				'vade_tarihi' => $satir->vade_tarihi,
				'tutar'       => $kalan,
				'durum'       => 'ODENMEDI',
				'aciklama'    => 'Kısmi ödeme sonrası kalan',
			) );
		}
		$ek = trim( (string) $aciklama );
		YP_Veri::hareket_guncelle( $satir->id, array(
			'tutar'         => $tutar,
			'durum'         => 'ODENDI',
			'odeme_turu'    => $o['odeme_turu'],
			'odeme_tarihi'  => $o['tarih'],
			'hesap_id'      => $o['hesap_id'] ? $o['hesap_id'] : null,
			'tahsil_eden'   => YP_Cekirdek::kullanici_id(),
			'tahsil_zamani' => YP_Cekirdek::simdi(),
			'aciklama'      => '' !== $ek ? $ek : $satir->aciklama,
		) );
		return (int) $satir->id;
	}

	// KURAL: "Kendisi yatırdı" için makbuz yazdırılmaz — kuruma para girmez.
	private static function makbuz_mesaji( array $idler, $odeme_turu ) {
		if ( ! $idler || 'KENDISI' === $odeme_turu ) {
			return '';
		}
		return ' <a href="' . esc_url( self::makbuz_url( $idler ) ) . '">Makbuzu yazdır</a>';
	}

	// ---- Aday kaydı -----------------------------------------------------

	public static function aday_kaydet() {
		$id     = YP_Guvenlik::tamsayi( 'id' );
		$hatalar = array();
		$tarih   = function ( $alan, $etiket ) use ( &$hatalar ) {
			$ham = YP_Guvenlik::metin( $alan, 'post', 20 );
			$t   = YP_Bicim::tarih_oku( $ham );
			if ( '' !== $ham && '' === $t ) {
				$hatalar[] = $etiket . ' geçersiz (gg.aa.yyyy yazın).';
			}
			return '' === $t ? null : $t;
		};
		$bos_null = function ( $d ) {
			return '' === $d ? null : $d;
		};

		$v = array(
			'tc_no'          => $bos_null( preg_replace( '/\D/', '', YP_Guvenlik::metin( 'tc_no', 'post', 20 ) ) ),
			'adi'            => YP_Bicim::buyuk( YP_Guvenlik::metin( 'adi', 'post', 100 ) ),
			'soyadi'         => YP_Bicim::buyuk( YP_Guvenlik::metin( 'soyadi', 'post', 100 ) ),
			'baba_adi'       => $bos_null( YP_Bicim::buyuk( YP_Guvenlik::metin( 'baba_adi', 'post', 100 ) ) ),
			'ana_adi'        => $bos_null( YP_Bicim::buyuk( YP_Guvenlik::metin( 'ana_adi', 'post', 100 ) ) ),
			'dogum_tarihi'   => $tarih( 'dogum_tarihi', 'Doğum tarihi' ),
			'dogum_yeri'     => $bos_null( YP_Bicim::buyuk( YP_Guvenlik::metin( 'dogum_yeri', 'post', 100 ) ) ),
			'cinsiyet'       => $bos_null( YP_Guvenlik::secim( 'cinsiyet', array( 'Erkek', 'Kadın' ) ) ),
			'gsm_1'          => $bos_null( YP_Bicim::telefon_temizle( YP_Guvenlik::metin( 'gsm_1', 'post', 30 ) ) ),
			'gsm_2'          => $bos_null( YP_Bicim::telefon_temizle( YP_Guvenlik::metin( 'gsm_2', 'post', 30 ) ) ),
			'ev_telefonu'    => $bos_null( YP_Bicim::telefon_temizle( YP_Guvenlik::metin( 'ev_telefonu', 'post', 30 ) ) ),
			'e_posta'        => $bos_null( sanitize_email( YP_Guvenlik::metin( 'e_posta', 'post', 190 ) ) ),
			'adres'          => $bos_null( YP_Guvenlik::metin( 'adres', 'post', 500 ) ),
			'ilce'           => $bos_null( YP_Guvenlik::metin( 'ilce', 'post', 100 ) ),
			'il'             => $bos_null( YP_Guvenlik::secim( 'il', array_keys( YP_Veri::iller() ) ) ),
			'meslek'         => $bos_null( YP_Guvenlik::metin( 'meslek', 'post', 150 ) ),
			'tahsil'         => $bos_null( YP_Guvenlik::metin( 'tahsil', 'post', 100 ) ),
			'ehliyet_sinifi' => $bos_null( YP_Guvenlik::secim( 'ehliyet_sinifi', array_keys( YP_Veri::ehliyet_siniflari() ) ) ),
			'ehliyet_no'     => $bos_null( YP_Guvenlik::metin( 'ehliyet_no', 'post', 50 ) ),
			'ehliyet_tarihi' => $tarih( 'ehliyet_tarihi', 'Ehliyet veriliş tarihi' ),
			'ehliyet_il'     => $bos_null( YP_Guvenlik::secim( 'ehliyet_il', array_keys( YP_Veri::iller() ) ) ),
			'kayit_tarihi'   => $tarih( 'kayit_tarihi', 'Kayıt tarihi' ),
			'referans_id'    => YP_Guvenlik::tamsayi( 'referans_id' ) ? YP_Guvenlik::tamsayi( 'referans_id' ) : null,
			'ozel_kod1_id'   => YP_Guvenlik::tamsayi( 'ozel_kod1_id' ) ? YP_Guvenlik::tamsayi( 'ozel_kod1_id' ) : null,
			'ozel_kod2_id'   => YP_Guvenlik::tamsayi( 'ozel_kod2_id' ) ? YP_Guvenlik::tamsayi( 'ozel_kod2_id' ) : null,
			'sari_not'       => $bos_null( YP_Guvenlik::metin( 'sari_not', 'post', 150 ) ),
			'ozel_notlar'    => $bos_null( YP_Guvenlik::uzun_metin( 'ozel_notlar' ) ),
			'diger_alan1'    => $bos_null( YP_Guvenlik::metin( 'diger_alan1', 'post', 150 ) ),
			'diger_alan2'    => $bos_null( YP_Guvenlik::metin( 'diger_alan2', 'post', 150 ) ),
		);

		// KURAL: Karttan kaldırılmış bir alan (e-posta, adres, meslek gibi) kayıtta duruyorsa silinmez —
		// yalnızca formdan gerçekten gönderilen sütunlar yazılır.
		// KURAL: Ad, soyad, TC ve kayıt tarihi her koşulda denetlenir; bunlar listeden çıkarılmaz.
		$hep_yazilan = array( 'adi', 'soyadi', 'tc_no', 'kayit_tarihi' );
		foreach ( array_keys( $v ) as $alan ) {
			if ( ! isset( $_POST[ $alan ] ) && ! in_array( $alan, $hep_yazilan, true ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- nonce yönlendiricide doğrulandı.
				unset( $v[ $alan ] );
			}
		}

		// KURAL: Ad ve soyad zorunludur; TC girilmişse 11 hane olmalıdır.
		if ( '' === $v['adi'] ) {
			$hatalar[] = 'Adı zorunludur.';
		}
		if ( '' === $v['soyadi'] ) {
			$hatalar[] = 'Soyadı zorunludur.';
		}
		if ( null !== $v['tc_no'] && 11 !== strlen( $v['tc_no'] ) ) {
			$hatalar[] = 'TC kimlik no 11 haneli olmalıdır.';
		}
		if ( $hatalar ) {
			self::form_sakla( array_map( 'sanitize_text_field', wp_unslash( array_filter( $_POST, 'is_string' ) ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
			self::geri_don( implode( '<br>', array_map( 'esc_html', $hatalar ) ), 'hata' );
		}
		if ( null === $v['kayit_tarihi'] ) {
			$v['kayit_tarihi'] = YP_Cekirdek::bugun();
		}

		// KURAL: Karttaki "Durumu" kutusu kaydın durumunu yazar; "Arşiv" seçilirse kayıt arşive alınır,
		// diğer durumlarda (tamamlandı / iade / yarıda kaldı) kayıt aktif kalır.
		if ( isset( $_POST['durum_kaydi'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$secim               = YP_Guvenlik::secim( 'durum_kaydi', array_keys( YP_Veri::kayit_durumlari() ), '' );
			$v['kayit_durumu']   = '' === $secim ? null : $secim;
			$v['arsiv']          = 'arsiv' === $secim ? 1 : 0;
		}

		if ( $id ) {
			$aday = self::aday_veya_don( $id );
			$v['guncelleyen'] = YP_Cekirdek::kullanici_id();
			$v['guncelleme']  = YP_Cekirdek::simdi();
			YP_Veri::aday_guncelle( $id, $v );
			self::log( $id, 'güncelleme', 'Aday bilgileri güncellendi.' );
			self::mesaj( 'Aday bilgileri kaydedildi.' );
			self::kart_islemi_yaz( $aday ? $aday : YP_Veri::aday( $id ) );
		} else {
			$v['olusturan'] = YP_Cekirdek::kullanici_id();
			$v['olusturma'] = YP_Cekirdek::simdi();
			$id             = YP_Veri::aday_ekle( $v );
			if ( ! $id ) {
				self::form_sakla( array_map( 'sanitize_text_field', wp_unslash( array_filter( $_POST, 'is_string' ) ) ) ); // phpcs:ignore
				self::geri_don( 'Aday kaydedilemedi. Lütfen tekrar deneyin.', 'hata' );
			}
			$aday = YP_Veri::aday( $id );
			self::log( $id, 'ekleme', 'Yeni aday: #' . $aday->aday_no . ' ' . YP_Veri::aday_adi( $aday ) );
			self::mesaj( 'Aday kaydedildi. Aday no: <strong>' . (int) $aday->aday_no . '</strong>' );

			// KURAL: Yeni aday formunda işlem seçildiyse işlem ve ödemesi aynı anda açılır — günlük akış tek ekranda biter.
			if ( YP_Guvenlik::tamsayi( 'islem_turu_id' ) ) {
				$sonuc = self::islem_olustur( $aday );
				if ( is_string( $sonuc ) ) {
					self::mesaj( 'Aday kaydedildi ancak işlem açılamadı: ' . esc_html( $sonuc ), 'hata' );
				} else {
					foreach ( $sonuc['mesajlar'] as $m ) {
						self::mesaj( $m );
					}
				}
			}
		}

		// KURAL: TC algoritmaya uymuyorsa uyarılır. Aynı TC ile ikinci bir kart açmak normaldir (yıllar sonra
		// yeni bir psikoteknik kaydı) — uyarı verilmez; diğer kartlar "Adaya Ait Başka Kayıtlar" bölümünde listelenir.
		if ( null !== $v['tc_no'] && ! YP_Bicim::tc_gecerli( $v['tc_no'] ) ) {
			self::mesaj( 'Uyarı: TC kimlik no doğrulama kuralına uymuyor, kontrol edin.', 'uyari' );
		}
		self::yonlendir( self::aday_url( $id ) );
	}

	public static function aday_arsiv() {
		$aday  = self::aday_veya_don( YP_Guvenlik::tamsayi( 'id' ) );
		$yeni  = $aday->arsiv ? 0 : 1;
		YP_Veri::aday_guncelle( $aday->id, array( 'arsiv' => $yeni ) );
		self::log( $aday->id, 'arşiv', $yeni ? 'Arşive alındı.' : 'Arşivden çıkarıldı.' );
		self::yonlendir( self::aday_url( $aday->id ), $yeni ? 'Aday arşive alındı.' : 'Aday arşivden çıkarıldı.' );
	}

	// KURAL: "Sil" adayı Silinenler'e taşır, geri alınabilir; fotoğraf ve kayıtlar "Kalıcı sil"e kadar durur — onaylanan karar.
	public static function aday_sil() {
		$aday = self::aday_veya_don( YP_Guvenlik::tamsayi( 'id' ) );
		YP_Veri::aday_guncelle( $aday->id, array(
			'silindi'      => 1,
			'silme_zamani' => YP_Cekirdek::simdi(),
			'silen'        => YP_Cekirdek::kullanici_id(),
		) );
		self::log( $aday->id, 'silme', 'Aday silinenlere taşındı: #' . $aday->aday_no . ' ' . YP_Veri::aday_adi( $aday ) );
		self::yonlendir( array( 'ekran' => 'adaylar' ), 'Aday silindi. Geri almak için Silinenler ekranını kullanın.' );
	}

	public static function aday_foto() {
		$aday   = self::aday_veya_don( YP_Guvenlik::tamsayi( 'id' ) );
		$ajax   = YP_Guvenlik::bayrak( 'ajax' );
		$dosya  = isset( $_FILES['foto'] ) ? $_FILES['foto'] : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- YP_Foto::yukle içinde doğrulanır.
		$sonuc  = YP_Foto::yukle( $aday->id, $dosya );
		if ( is_wp_error( $sonuc ) ) {
			if ( $ajax ) {
				self::json( array( 'ok' => false, 'mesaj' => $sonuc->get_error_message() ), 400 );
			}
			self::geri_don( esc_html( $sonuc->get_error_message() ), 'hata' );
		}
		self::log( $aday->id, 'fotoğraf', 'Fotoğraf yüklendi.' );
		if ( $ajax ) {
			self::mesaj( 'Fotoğraf kaydedildi.' );
			self::json( array( 'ok' => true ) );
		}
		self::geri_don( 'Fotoğraf kaydedildi.' );
	}

	public static function aday_foto_sil() {
		$aday = self::aday_veya_don( YP_Guvenlik::tamsayi( 'id' ) );
		YP_Foto::aday_fotolarini_sil( $aday );
		YP_Veri::aday_guncelle( $aday->id, array( 'foto' => null, 'foto_kucuk' => null ) );
		self::log( $aday->id, 'fotoğraf', 'Fotoğraf silindi.' );
		self::geri_don( 'Fotoğraf silindi.' );
	}

	// KURAL: Evrak tamam sayılması için aktif evrak listesindeki her kalem işaretli olmalıdır.
	public static function evrak_kaydet() {
		$aday     = self::aday_veya_don( YP_Guvenlik::tamsayi( 'id' ) );
		$isaretli = YP_Guvenlik::tamsayi_dizisi( 'evrak' );
		$liste    = YP_Veri::tanimlar( 'evrak', true );
		$json     = array();
		$tamam    = 1;
		foreach ( $liste as $e ) {
			$var = in_array( (int) $e->id, $isaretli, true );
			$json[ (int) $e->id ] = $var ? 1 : 0;
			if ( ! $var ) {
				$tamam = 0;
			}
		}
		YP_Veri::aday_guncelle( $aday->id, array( 'evrak' => wp_json_encode( $json ), 'evrak_tamam' => $tamam ) );
		self::log( $aday->id, 'evrak', $tamam ? 'Evraklar tamamlandı.' : 'Evrak durumu güncellendi.' );
		self::yonlendir( self::aday_url( $aday->id, 'evrak' ), 'Evrak durumu kaydedildi.' );
	}

	public static function gorusme_ekle() {
		global $wpdb;
		$aday = self::aday_veya_don( YP_Guvenlik::tamsayi( 'id' ) );
		$not  = YP_Guvenlik::uzun_metin( 'aciklama' );
		if ( '' === trim( $not ) ) {
			self::yonlendir( self::aday_url( $aday->id, 'gorusmeler' ), 'Not boş olamaz.', 'hata' );
		}
		$wpdb->insert( YP_Cekirdek::tablo( 'gorusmeler' ), array(
			'aday_id'      => $aday->id,
			'zaman'        => YP_Cekirdek::simdi(),
			'aciklama'     => $not,
			'kullanici_id' => YP_Cekirdek::kullanici_id(),
		) );
		self::log( $aday->id, 'görüşme', 'Görüşme notu eklendi.' );
		self::yonlendir( self::aday_url( $aday->id, 'gorusmeler' ), 'Not eklendi.' );
	}

	public static function gorusme_sil() {
		global $wpdb;
		$aday = self::aday_veya_don( YP_Guvenlik::tamsayi( 'id' ) );
		$wpdb->delete( YP_Cekirdek::tablo( 'gorusmeler' ), array( 'id' => YP_Guvenlik::tamsayi( 'gorusme_id' ), 'aday_id' => $aday->id ), array( '%d', '%d' ) );
		self::log( $aday->id, 'görüşme', 'Görüşme notu silindi.' );
		self::yonlendir( self::aday_url( $aday->id, 'gorusmeler' ), 'Not silindi.' );
	}

	// ---- İşlemler -------------------------------------------------------

	/**
	 * Formdan işlem ve ödemesini oluşturur.
	 * KURAL: İşlem, borç satırları ve peşin tahsilat tek transaction'dır — biri yazılamazsa hiçbiri kalmaz.
	 *
	 * @return array|string array( 'id' => işlem id, 'mesajlar' => array ) veya hata metni.
	 */
	private static function islem_olustur( $aday ) {
		$tur = YP_Veri::tanim( YP_Guvenlik::tamsayi( 'islem_turu_id' ) );
		if ( ! $tur || 'islem_turu' !== $tur->tur ) {
			return 'İşlem türünü seçin.';
		}
		$islem_tarihi = YP_Guvenlik::tarih( 'islem_tarihi' );
		$islem_tarihi = $islem_tarihi ? $islem_tarihi : YP_Cekirdek::bugun();
		$ucret        = YP_Guvenlik::tutar( 'ucret' );
		$sekil        = YP_Guvenlik::secim( 'odeme_sekli', array( 'pesin', 'borc', 'taksit', 'yok' ), 'borc' );
		$rapor_tarihi = YP_Guvenlik::tarih( 'rapor_tarihi' );
		$gecerlilik   = YP_Guvenlik::tarih( 'gecerlilik_bitis' );
		// KURAL: Geçerlilik bitişi boşsa rapor tarihi + işlem türündeki geçerlilik süresi (ay) ile otomatik hesaplanır.
		if ( ! $gecerlilik && $rapor_tarihi && (int) $tur->gecerlilik_ay > 0 ) {
			$gecerlilik = YP_Bicim::ay_ekle( $rapor_tarihi, (int) $tur->gecerlilik_ay );
		}
		$referans_id = isset( $_POST['islem_referans_id'] ) ? YP_Guvenlik::tamsayi( 'islem_referans_id' ) : (int) $aday->referans_id; // phpcs:ignore WordPress.Security.NonceVerification
		$saat        = YP_Guvenlik::metin( 'islem_saati', 'post', 5 );

		$o = self::odeme_bilgisi_oku();
		if ( 'pesin' === $sekil && $ucret > 0 ) {
			$hata = self::odeme_bilgisi_hatasi( $o );
			if ( '' !== $hata ) {
				return $hata;
			}
		}
		if ( $ucret < 0 ) {
			return 'Ücret eksi olamaz.';
		}
		if ( ! YP_Guvenlik::tarih( 'odeme_tarihi' ) ) {
			$o['tarih'] = $islem_tarihi;
		}
		$ilk_vade = YP_Guvenlik::tarih( 'ilk_vade' );
		$ilk_vade = $ilk_vade ? $ilk_vade : $islem_tarihi;
		$adet     = min( 36, max( 1, YP_Guvenlik::tamsayi( 'taksit_sayisi' ) ) );
		$islem    = array(
			'aday_id'          => $aday->id,
			'islem_turu_id'    => $tur->id,
			'islem_tarihi'     => $islem_tarihi,
			'islem_saati'      => preg_match( '/^\d{2}:\d{2}$/', $saat ) ? $saat . ':00' : null,
			'referans_id'      => $referans_id ? $referans_id : null,
			'ucret'            => $ucret,
			'durum'            => YP_Guvenlik::secim( 'durum', array_keys( YP_Veri::islem_durumlari() ), 'randevu' ),
			'rapor_no'         => YP_Guvenlik::metin( 'rapor_no', 'post', 50 ),
			'rapor_tarihi'     => $rapor_tarihi ? $rapor_tarihi : null,
			'gecerlilik_bitis' => $gecerlilik ? $gecerlilik : null,
			'aciklama'         => YP_Guvenlik::uzun_metin( 'islem_aciklama' ),
			'olusturan'        => YP_Cekirdek::kullanici_id(),
			'olusturma'        => YP_Cekirdek::simdi(),
		);

		// KURAL: Mesajlar transaction bittikten sonra yazılır — geri alınan işlem için "tahsil edildi" gösterilmez.
		try {
			return YP_Veri::tek_islemde( function () use ( $aday, $tur, $islem, $ucret, $sekil, $o, $ilk_vade, $islem_tarihi, $adet ) {
				global $wpdb;
				$mesajlar = array();
				YP_Veri::yazildi_mi( $wpdb->insert( YP_Cekirdek::tablo( 'islemler' ), $islem ) );
				$islem_id = (int) $wpdb->insert_id;
				if ( ! $islem_id ) {
					throw new YP_Veri_Hatasi( 'İşlem kaydedilemedi.' );
				}
				self::log( $aday->id, 'işlem', 'İşlem açıldı: ' . $tur->ad . ' / ' . YP_Bicim::tl( $ucret ) );

				// KURAL: İşlem ücreti borç satırı olarak yazılır (borç tipi = işlem türü adı) — masaüstünde ücret MUHASEBE satırıydı.
				if ( $ucret > 0 && 'yok' !== $sekil ) {
					if ( 'taksit' === $sekil ) {
						foreach ( YP_Hesap::taksitlere_bol( $ucret, $adet ) as $k => $p ) {
							YP_Veri::hareket_ekle( array(
								'kayit_turu'  => 'ADAY',
								'aday_id'     => $aday->id,
								'islem_id'    => $islem_id,
								'borc_tipi'   => $tur->ad,
								'vade_tarihi' => YP_Bicim::ay_ekle( $ilk_vade, $k ),
								'tutar'       => $p,
								'durum'       => 'ODENMEDI',
								'aciklama'    => ( $k + 1 ) . '. taksit / ' . $adet,
							) );
						}
						$mesajlar[] = $adet . ' taksit oluşturuldu.';
					} else {
						$satir_id = YP_Veri::hareket_ekle( array(
							'kayit_turu'  => 'ADAY',
							'aday_id'     => $aday->id,
							'islem_id'    => $islem_id,
							'borc_tipi'   => $tur->ad,
							'vade_tarihi' => 'pesin' === $sekil ? $islem_tarihi : $ilk_vade,
							'tutar'       => $ucret,
							'durum'       => 'ODENMEDI',
						) );
						if ( 'pesin' === $sekil ) {
							$odenen_id = self::satiri_tahsil_et( YP_Veri::hareket( $satir_id ), $ucret, $o );
							self::log( $aday->id, 'tahsilat', 'Peşin tahsilat: ' . YP_Bicim::tl( $ucret ) );
							$mesajlar[] = 'Ücret tahsil edildi.' . self::makbuz_mesaji( array( $odenen_id ), $o['odeme_turu'] );
						}
					}
				}
				return array( 'id' => $islem_id, 'mesajlar' => $mesajlar );
			} );
		} catch ( YP_Veri_Hatasi $e ) {
			return 'İşlem kaydedilemedi; hiçbir değişiklik yapılmadı.';
		}
	}

	public static function islem_kaydet() {
		global $wpdb;
		$aday     = self::aday_veya_don( YP_Guvenlik::tamsayi( 'id' ) );
		$islem_id = YP_Guvenlik::tamsayi( 'islem_id' );

		if ( ! $islem_id ) {
			$sonuc = self::islem_olustur( $aday );
			if ( is_string( $sonuc ) ) {
				self::yonlendir( self::aday_url( $aday->id ), esc_html( $sonuc ), 'hata' );
			}
			foreach ( $sonuc['mesajlar'] as $m ) {
				self::mesaj( $m );
			}
			self::yonlendir( self::aday_url( $aday->id ), 'İşlem kaydedildi.' );
		}

		$islem = YP_Veri::islem( $islem_id );
		if ( ! $islem || (int) $islem->aday_id !== (int) $aday->id ) {
			self::yonlendir( self::aday_url( $aday->id ), 'İşlem bulunamadı.', 'hata' );
		}
		$tur          = YP_Veri::tanim( YP_Guvenlik::tamsayi( 'islem_turu_id' ) );
		$rapor_tarihi = YP_Guvenlik::tarih( 'rapor_tarihi' );
		$gecerlilik   = YP_Guvenlik::tarih( 'gecerlilik_bitis' );
		if ( ! $gecerlilik && $rapor_tarihi && $tur && (int) $tur->gecerlilik_ay > 0 ) {
			$gecerlilik = YP_Bicim::ay_ekle( $rapor_tarihi, (int) $tur->gecerlilik_ay );
		}
		$saat = YP_Guvenlik::metin( 'islem_saati', 'post', 5 );
		// KURAL: Kayıtlı işlemde ücret buradan değişmez — borç, ödeme kartından eklenir/düzeltilir; tutarlar tutarlı kalır.
		$wpdb->update( YP_Cekirdek::tablo( 'islemler' ), array(
			'islem_turu_id'    => $tur && 'islem_turu' === $tur->tur ? $tur->id : $islem->islem_turu_id,
			'islem_tarihi'     => YP_Guvenlik::tarih( 'islem_tarihi' ) ? YP_Guvenlik::tarih( 'islem_tarihi' ) : $islem->islem_tarihi,
			'islem_saati'      => preg_match( '/^\d{2}:\d{2}$/', $saat ) ? $saat . ':00' : null,
			'referans_id'      => YP_Guvenlik::tamsayi( 'islem_referans_id' ) ? YP_Guvenlik::tamsayi( 'islem_referans_id' ) : null,
			'durum'            => YP_Guvenlik::secim( 'durum', array_keys( YP_Veri::islem_durumlari() ), $islem->durum ),
			'rapor_no'         => YP_Guvenlik::metin( 'rapor_no', 'post', 50 ),
			'rapor_tarihi'     => $rapor_tarihi ? $rapor_tarihi : null,
			'gecerlilik_bitis' => $gecerlilik ? $gecerlilik : null,
			'aciklama'         => YP_Guvenlik::uzun_metin( 'islem_aciklama' ),
			'guncelleyen'      => YP_Cekirdek::kullanici_id(),
			'guncelleme'       => YP_Cekirdek::simdi(),
		), array( 'id' => $islem->id ) );
		self::log( $aday->id, 'işlem', 'İşlem güncellendi (#' . $islem->id . ').' );
		self::yonlendir( self::aday_url( $aday->id ), 'İşlem güncellendi.' );
	}

	private static function islem_odenen( $islem_id ) {
		global $wpdb;
		$t = YP_Cekirdek::tablo( 'hareketler' );
		return (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(tutar),0) FROM {$t} WHERE islem_id = %d AND silindi = 0 AND durum IN ('ODENDI','IADE')", $islem_id ) );
	}

	// KURAL: İşlemle birlikte silinen borçlar işlemle aynı silme zamanını alır — işlem geri alınınca yalnızca onlar geri gelir.
	private static function islem_odenmemisleri_sil( $islem_id, $zaman ) {
		global $wpdb;
		$t = YP_Cekirdek::tablo( 'hareketler' );
		YP_Veri::yazildi_mi( $wpdb->query( $wpdb->prepare(
			"UPDATE {$t} SET silindi = 1, silme_zamani = %s, silen = %d WHERE islem_id = %d AND silindi = 0 AND durum = 'ODENMEDI'",
			$zaman,
			YP_Cekirdek::kullanici_id(),
			$islem_id
		) ) );
		YP_Cekirdek::veri_degisti();
	}

	// KURAL: İşlem iptal edilince ödenmemiş borçları silinenlere taşınır; alınmış ödeme varsa iade için uyarılır.
	public static function islem_iptal() {
		global $wpdb;
		$aday  = self::aday_veya_don( YP_Guvenlik::tamsayi( 'id' ) );
		$islem = YP_Veri::islem( YP_Guvenlik::tamsayi( 'islem_id' ) );
		if ( ! $islem || (int) $islem->aday_id !== (int) $aday->id ) {
			self::yonlendir( self::aday_url( $aday->id ), 'İşlem bulunamadı.', 'hata' );
		}
		$zaman = YP_Cekirdek::simdi();
		// KURAL: İşlemin iptali ve ödenmemiş borçlarının silinmesi birlikte yazılır ya da hiç yazılmaz.
		self::tek_islemde( function () use ( $wpdb, $islem, $aday, $zaman ) {
			YP_Veri::yazildi_mi( $wpdb->update( YP_Cekirdek::tablo( 'islemler' ), array( 'durum' => 'iptal', 'guncelleyen' => YP_Cekirdek::kullanici_id(), 'guncelleme' => $zaman ), array( 'id' => $islem->id ) ) );
			self::islem_odenmemisleri_sil( $islem->id, $zaman );
			self::log( $aday->id, 'işlem', 'İşlem iptal edildi (#' . $islem->id . ').' );
		}, self::aday_url( $aday->id ) );
		$odenen = self::islem_odenen( $islem->id );
		if ( $odenen > 0 ) {
			self::mesaj( 'Bu işlem için ' . esc_html( YP_Bicim::tl( $odenen ) ) . ' tahsil edilmiş. Para geri verilecekse Ödeme Kartı\'ndan "İade" yapın.', 'uyari' );
		}
		self::yonlendir( self::aday_url( $aday->id ), 'İşlem iptal edildi.' );
	}

	// KURAL: Ödeme alınmış işlem silinemez (önce tahsilat geri alınmalı) — kasa geçmişi bozulmaz.
	public static function islem_sil() {
		global $wpdb;
		$aday  = self::aday_veya_don( YP_Guvenlik::tamsayi( 'id' ) );
		$islem = YP_Veri::islem( YP_Guvenlik::tamsayi( 'islem_id' ) );
		if ( ! $islem || (int) $islem->aday_id !== (int) $aday->id ) {
			self::yonlendir( self::aday_url( $aday->id ), 'İşlem bulunamadı.', 'hata' );
		}
		if ( self::islem_odenen( $islem->id ) > 0 ) {
			self::yonlendir( self::aday_url( $aday->id ), 'Bu işleme ödeme alınmış; silinemez. İşlemi iptal edebilir veya önce tahsilatı geri alabilirsiniz.', 'hata' );
		}
		$zaman = YP_Cekirdek::simdi();
		self::tek_islemde( function () use ( $wpdb, $islem, $aday, $zaman ) {
			YP_Veri::yazildi_mi( $wpdb->update( YP_Cekirdek::tablo( 'islemler' ), array( 'silindi' => 1, 'silme_zamani' => $zaman, 'silen' => YP_Cekirdek::kullanici_id() ), array( 'id' => $islem->id ) ) );
			self::islem_odenmemisleri_sil( $islem->id, $zaman );
			self::log( $aday->id, 'işlem', 'İşlem silindi (#' . $islem->id . ').' );
		}, self::aday_url( $aday->id ) );
		self::yonlendir( self::aday_url( $aday->id ), 'İşlem silindi.' );
	}

	// ---- Ödeme kartı ----------------------------------------------------

	private static function islem_secimi( $aday ) {
		$islem_id = YP_Guvenlik::tamsayi( 'islem_id' );
		if ( ! $islem_id ) {
			return null;
		}
		$islem = YP_Veri::islem( $islem_id );
		return ( $islem && (int) $islem->aday_id === (int) $aday->id ) ? $islem : null;
	}

	private static function borc_tipi_oku( $islem ) {
		$tip = YP_Guvenlik::metin( 'borc_tipi', 'post', 150 );
		if ( '' === $tip && $islem ) {
			$tur = YP_Veri::tanim( $islem->islem_turu_id );
			$tip = $tur ? $tur->ad : '';
		}
		return '' === $tip ? 'Diğer' : $tip;
	}

	public static function borc_ekle() {
		$aday  = self::aday_veya_don( YP_Guvenlik::tamsayi( 'id' ) );
		$islem = self::islem_secimi( $aday );
		$tutar = YP_Guvenlik::tutar( 'tutar' );
		$vade  = YP_Guvenlik::tarih( 'vade_tarihi' );
		if ( $tutar <= 0 ) {
			self::yonlendir( self::aday_url( $aday->id, 'odeme' ), 'Tutar sıfırdan büyük olmalı.', 'hata' );
		}
		$tip      = self::borc_tipi_oku( $islem );
		$aciklama = YP_Guvenlik::uzun_metin( 'aciklama' );
		self::tek_islemde( function () use ( $aday, $islem, $tip, $vade, $tutar, $aciklama ) {
			YP_Veri::hareket_ekle( array(
				'kayit_turu'  => 'ADAY',
				'aday_id'     => $aday->id,
				'islem_id'    => $islem ? $islem->id : null,
				'borc_tipi'   => $tip,
				'vade_tarihi' => $vade ? $vade : YP_Cekirdek::bugun(),
				'tutar'       => $tutar,
				'durum'       => 'ODENMEDI',
				'aciklama'    => $aciklama,
			) );
			self::log( $aday->id, 'borç', 'Borç eklendi: ' . $tip . ' ' . YP_Bicim::tl( $tutar ) );
		}, self::aday_url( $aday->id, 'odeme' ) );
		self::yonlendir( self::aday_url( $aday->id, 'odeme' ), 'Borç eklendi.' );
	}

	// KURAL: Taksit sayısı 1–36 arasıdır; taksitler ilk vadeden itibaren seçilen ay aralığıyla dizilir.
	public static function taksit_plani() {
		$aday   = self::aday_veya_don( YP_Guvenlik::tamsayi( 'id' ) );
		$islem  = self::islem_secimi( $aday );
		$toplam = YP_Guvenlik::tutar( 'tutar' );
		$adet   = min( 36, max( 1, YP_Guvenlik::tamsayi( 'taksit_sayisi' ) ) );
		$aralik = min( 12, max( 1, YP_Guvenlik::tamsayi( 'aralik' ) ) );
		$ilk    = YP_Guvenlik::tarih( 'ilk_vade' );
		$ilk    = $ilk ? $ilk : YP_Cekirdek::bugun();
		if ( $toplam <= 0 ) {
			self::yonlendir( self::aday_url( $aday->id, 'odeme' ), 'Toplam tutar sıfırdan büyük olmalı.', 'hata' );
		}
		$tip = self::borc_tipi_oku( $islem );
		// KURAL: Taksit planının tüm taksitleri birlikte yazılır — yarım plan oluşmaz.
		self::tek_islemde( function () use ( $aday, $islem, $tip, $toplam, $adet, $aralik, $ilk ) {
			foreach ( YP_Hesap::taksitlere_bol( $toplam, $adet ) as $k => $p ) {
				YP_Veri::hareket_ekle( array(
					'kayit_turu'  => 'ADAY',
					'aday_id'     => $aday->id,
					'islem_id'    => $islem ? $islem->id : null,
					'borc_tipi'   => $tip,
					'vade_tarihi' => YP_Bicim::ay_ekle( $ilk, $k * $aralik ),
					'tutar'       => $p,
					'durum'       => 'ODENMEDI',
					'aciklama'    => ( $k + 1 ) . '. taksit / ' . $adet,
				) );
			}
			self::log( $aday->id, 'borç', 'Taksit planı: ' . $adet . ' x ' . $tip . ', toplam ' . YP_Bicim::tl( $toplam ) );
		}, self::aday_url( $aday->id, 'odeme' ) );
		self::yonlendir( self::aday_url( $aday->id, 'odeme' ), $adet . ' taksit oluşturuldu.' );
	}

	public static function tahsil_et() {
		$aday  = self::aday_veya_don( YP_Guvenlik::tamsayi( 'id' ) );
		$satir = YP_Veri::hareket( YP_Guvenlik::tamsayi( 'hareket_id' ) );
		if ( ! $satir || (int) $satir->aday_id !== (int) $aday->id || 'ODENMEDI' !== $satir->durum || $satir->silindi ) {
			self::yonlendir( self::aday_url( $aday->id, 'odeme' ), 'Tahsil edilecek borç satırı bulunamadı.', 'hata' );
		}
		$o     = self::odeme_bilgisi_oku();
		$hata  = self::odeme_bilgisi_hatasi( $o );
		$tutar = YP_Guvenlik::tutar( 'tutar' );
		$tutar = $tutar > 0 ? $tutar : (float) $satir->tutar;
		// KURAL: Tek satırda tahsilat satır tutarını aşamaz — fazlası için "Ödeme Al" kullanılır.
		if ( '' === $hata && $tutar > (float) $satir->tutar + 0.001 ) {
			$hata = 'Tutar bu satırın borcundan (' . YP_Bicim::tl( $satir->tutar ) . ') fazla olamaz. Birden fazla taksiti kapatmak için "Ödeme Al" kullanın.';
		}
		if ( '' !== $hata ) {
			self::yonlendir( self::aday_url( $aday->id, 'odeme' ), esc_html( $hata ), 'hata' );
		}
		$aciklama = YP_Guvenlik::uzun_metin( 'aciklama' );
		// KURAL: Kısmi ödemede satırın bölünmesi ve tahsilat tek transaction'dır; satır kilitlenip yeniden denetlenir.
		$odenen_id = self::tek_islemde( function () use ( $satir, $tutar, $o, $aciklama, $aday ) {
			$guncel = YP_Veri::hareket_kilitli( $satir->id );
			if ( ! $guncel || 'ODENMEDI' !== $guncel->durum || $guncel->silindi || $tutar > (float) $guncel->tutar + 0.001 ) {
				throw new YP_Islem_Engeli( 'Bu borç satırı bu arada değişmiş (tahsil edilmiş veya silinmiş olabilir).' );
			}
			$id = self::satiri_tahsil_et( $guncel, $tutar, $o, $aciklama );
			self::log( $aday->id, 'tahsilat', 'Tahsilat: ' . YP_Bicim::tl( $tutar ) . ' (' . $guncel->borc_tipi . ')' );
			return $id;
		}, self::aday_url( $aday->id, 'odeme' ) );
		self::yonlendir( self::aday_url( $aday->id, 'odeme' ), 'Tahsilat kaydedildi.' . self::makbuz_mesaji( array( $odenen_id ), $o['odeme_turu'] ) );
	}

	/**
	 * Adayın (ve seçiliyse işlemin) ödenmemiş borç satırları, en eski vadeden başlayarak.
	 *
	 * @param bool $kilitle Transaction içinde satırları kilitleyerek okur.
	 */
	private static function odenmemis_satirlar( $aday, $islem, $kilitle = false ) {
		global $wpdb;
		$t   = YP_Cekirdek::tablo( 'hareketler' );
		$sql = "SELECT * FROM {$t} WHERE aday_id = %d AND silindi = 0 AND kayit_turu = 'ADAY' AND durum = 'ODENMEDI'";
		$arg = array( $aday->id );
		if ( $islem ) {
			$sql  .= ' AND islem_id = %d';
			$arg[] = $islem->id;
		}
		$sql .= ' ORDER BY vade_tarihi, id' . ( $kilitle ? YP_Veri::kilit_eki() : '' );
		return $wpdb->get_results( $wpdb->prepare( $sql, $arg ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	private static function satir_toplami( array $satirlar ) {
		return round( array_sum( array_map( function ( $s ) {
			return (float) $s->tutar;
		}, $satirlar ) ), 2 );
	}

	// KURAL: "Ödeme Al" tutarı en eski vadeli borçtan başlayarak dağıtır; tahsil edilen satırlar tek makbuzda yazdırılır.
	public static function odeme_al() {
		$aday  = self::aday_veya_don( YP_Guvenlik::tamsayi( 'id' ) );
		$islem = self::islem_secimi( $aday );
		$o     = self::odeme_bilgisi_oku();
		$hata  = self::odeme_bilgisi_hatasi( $o );
		$tutar = YP_Guvenlik::tutar( 'tutar' );
		if ( '' === $hata && $tutar <= 0 ) {
			$hata = 'Tutar sıfırdan büyük olmalı.';
		}
		$borc = self::satir_toplami( self::odenmemis_satirlar( $aday, $islem ) );
		// KURAL: Alınan tutar ödenmemiş borç toplamını aşamaz — fazla para kayda geçmez.
		if ( '' === $hata && $tutar > $borc + 0.001 ) {
			$hata = 'Tutar ödenmemiş borçtan (' . YP_Bicim::tl( $borc ) . ') fazla olamaz.';
		}
		if ( '' !== $hata ) {
			self::yonlendir( self::aday_url( $aday->id, 'odeme' ), esc_html( $hata ), 'hata' );
		}
		$aciklama = YP_Guvenlik::uzun_metin( 'aciklama' );
		// KURAL: Birden çok satırın tahsili ve bölünmesi tek transaction'dır — ödeme ya tamamen kaydedilir ya hiç kaydedilmez.
		$idler = self::tek_islemde( function () use ( $aday, $islem, $o, $tutar, $aciklama ) {
			$satirlar = self::odenmemis_satirlar( $aday, $islem, true );
			if ( $tutar > self::satir_toplami( $satirlar ) + 0.001 ) {
				throw new YP_Islem_Engeli( 'Ödenmemiş borç bu arada değişmiş; tutarı kontrol edip tekrar deneyin.' );
			}
			$idler = array();
			$kalan = $tutar;
			foreach ( $satirlar as $s ) {
				if ( $kalan <= 0.001 ) {
					break;
				}
				$pay     = min( $kalan, (float) $s->tutar );
				$idler[] = self::satiri_tahsil_et( $s, $pay, $o, $aciklama );
				$kalan   = round( $kalan - $pay, 2 );
			}
			self::log( $aday->id, 'tahsilat', 'Ödeme alındı: ' . YP_Bicim::tl( $tutar ) );
			return $idler;
		}, self::aday_url( $aday->id, 'odeme' ) );
		self::yonlendir( self::aday_url( $aday->id, 'odeme' ), 'Ödeme kaydedildi.' . self::makbuz_mesaji( $idler, $o['odeme_turu'] ) );
	}

	// KURAL: İade, alınan net tutarı (ödenen − önceki iadeler) aşamaz ve seçilen hesaptan çıkış olarak yazılır.
	// KURAL: İşlem seçildiyse sınır o işlemin kendi tahsilatlarıdır; seçilmediyse adayın tüm tahsilatları.
	public static function iade_et() {
		$aday     = self::aday_veya_don( YP_Guvenlik::tamsayi( 'id' ) );
		$islem    = self::islem_secimi( $aday );
		$tutar    = YP_Guvenlik::tutar( 'tutar' );
		$hesap    = YP_Veri::hesap( YP_Guvenlik::tamsayi( 'hesap_id' ) );
		$tarih    = YP_Guvenlik::tarih( 'odeme_tarihi' );
		$islem_id = $islem ? (int) $islem->id : 0;
		$net      = YP_Hesap::iade_edilebilir( $aday->id, $islem_id );
		$kapsam   = $islem ? 'bu işlem için alınan net tutarı' : 'adaydan alınan net tutarı';
		$hata     = '';
		if ( $tutar <= 0 ) {
			$hata = 'Tutar sıfırdan büyük olmalı.';
		} elseif ( ! $hesap ) {
			$hata = 'Paranın çıkacağı hesabı seçin.';
		} elseif ( $tutar > $net + 0.001 ) {
			$hata = 'İade tutarı ' . $kapsam . ' (' . YP_Bicim::tl( $net ) . ') aşamaz.';
		}
		if ( '' !== $hata ) {
			self::yonlendir( self::aday_url( $aday->id, 'odeme' ), esc_html( $hata ), 'hata' );
		}
		$tur_harita = array( 'NAKIT' => 'NAKIT', 'BANKA' => 'HAVALE', 'POSTA' => 'PTT' );
		$aciklama   = YP_Guvenlik::uzun_metin( 'aciklama' );
		self::tek_islemde( function () use ( $aday, $islem_id, $hesap, $tutar, $tarih, $aciklama, $tur_harita, $kapsam ) {
			// KURAL: Sınır transaction içinde kilitli okumayla yeniden denetlenir — çift gönderim fazla iade yazamaz.
			$net = YP_Hesap::iade_edilebilir( $aday->id, $islem_id, true );
			if ( $tutar > $net + 0.001 ) {
				throw new YP_Islem_Engeli( 'İade tutarı ' . $kapsam . ' (' . YP_Bicim::tl( $net ) . ') aşıyor.' );
			}
			YP_Veri::hareket_ekle( array(
				'kayit_turu'    => 'ADAY',
				'aday_id'       => $aday->id,
				'islem_id'      => $islem_id ? $islem_id : null,
				'hesap_id'      => $hesap->id,
				'borc_tipi'     => 'İade',
				'tutar'         => $tutar,
				'durum'         => 'IADE',
				'odeme_turu'    => isset( $tur_harita[ $hesap->tur ] ) ? $tur_harita[ $hesap->tur ] : 'NAKIT',
				'odeme_tarihi'  => $tarih ? $tarih : YP_Cekirdek::bugun(),
				'aciklama'      => $aciklama,
				'tahsil_eden'   => YP_Cekirdek::kullanici_id(),
				'tahsil_zamani' => YP_Cekirdek::simdi(),
			) );
			self::log( $aday->id, 'iade', 'İade: ' . YP_Bicim::tl( $tutar ) . ' (' . $hesap->ad . ')' . ( $islem_id ? ' / işlem #' . $islem_id : '' ) );
		}, self::aday_url( $aday->id, 'odeme' ) );
		self::yonlendir( self::aday_url( $aday->id, 'odeme' ), 'İade kaydedildi.' );
	}

	private static function aday_satiri( $aday ) {
		$satir = YP_Veri::hareket( YP_Guvenlik::tamsayi( 'hareket_id' ) );
		if ( ! $satir || (int) $satir->aday_id !== (int) $aday->id || $satir->silindi ) {
			self::yonlendir( self::aday_url( $aday->id, 'odeme' ), 'Satır bulunamadı.', 'hata' );
		}
		return $satir;
	}

	// KURAL: Yalnızca ödenmemiş borç satırının vadesi, tutarı ve kalemi düzeltilebilir.
	public static function hareket_duzenle() {
		$aday  = self::aday_veya_don( YP_Guvenlik::tamsayi( 'id' ) );
		$satir = self::aday_satiri( $aday );
		if ( 'ODENMEDI' !== $satir->durum ) {
			self::yonlendir( self::aday_url( $aday->id, 'odeme' ), 'Ödenmiş satır düzenlenemez; önce tahsilatı geri alın.', 'hata' );
		}
		$tutar = YP_Guvenlik::tutar( 'tutar' );
		$vade  = YP_Guvenlik::tarih( 'vade_tarihi' );
		if ( $tutar <= 0 ) {
			self::yonlendir( self::aday_url( $aday->id, 'odeme' ), 'Tutar sıfırdan büyük olmalı.', 'hata' );
		}
		$borc_tipi = self::borc_tipi_oku( null );
		$aciklama  = YP_Guvenlik::uzun_metin( 'aciklama' );
		self::tek_islemde( function () use ( $satir, $tutar, $vade, $borc_tipi, $aciklama, $aday ) {
			$guncel = YP_Veri::hareket_kilitli( $satir->id );
			if ( ! $guncel || 'ODENMEDI' !== $guncel->durum || $guncel->silindi ) {
				throw new YP_Islem_Engeli( 'Bu borç satırı bu arada değişmiş (tahsil edilmiş veya silinmiş olabilir).' );
			}
			YP_Veri::hareket_guncelle( $guncel->id, array(
				'tutar'       => $tutar,
				'vade_tarihi' => $vade ? $vade : $guncel->vade_tarihi,
				'borc_tipi'   => $borc_tipi,
				'aciklama'    => $aciklama,
			) );
			self::log( $aday->id, 'borç', 'Borç satırı düzeltildi (#' . $guncel->id . '): ' . YP_Bicim::tl( $guncel->tutar ) . ' → ' . YP_Bicim::tl( $tutar ) );
		}, self::aday_url( $aday->id, 'odeme' ) );
		self::yonlendir( self::aday_url( $aday->id, 'odeme' ), 'Borç satırı güncellendi.' );
	}

	// KURAL: Tahsilat geri alınınca satır yeniden ÖDENMEDİ olur, ödeme bilgileri temizlenir, günlüğe yazılır.
	public static function tahsilat_geri_al() {
		$aday  = self::aday_veya_don( YP_Guvenlik::tamsayi( 'id' ) );
		$satir = self::aday_satiri( $aday );
		if ( 'ODENDI' !== $satir->durum ) {
			self::yonlendir( self::aday_url( $aday->id, 'odeme' ), 'Bu satır tahsil edilmemiş.', 'hata' );
		}
		self::tek_islemde( function () use ( $satir, $aday ) {
			$guncel = YP_Veri::hareket_kilitli( $satir->id );
			if ( ! $guncel || 'ODENDI' !== $guncel->durum || $guncel->silindi ) {
				throw new YP_Islem_Engeli( 'Bu satırın tahsilatı bu arada değişmiş.' );
			}
			YP_Veri::hareket_guncelle( $guncel->id, array(
				'durum'         => 'ODENMEDI',
				'odeme_turu'    => '',
				'odeme_tarihi'  => null,
				'hesap_id'      => null,
				'tahsil_eden'   => null,
				'tahsil_zamani' => null,
			) );
			self::log( $aday->id, 'tahsilat', 'Tahsilat geri alındı: ' . YP_Bicim::tl( $guncel->tutar ) );
		}, self::aday_url( $aday->id, 'odeme' ) );
		self::yonlendir( self::aday_url( $aday->id, 'odeme' ), 'Tahsilat geri alındı.' );
	}

	// KURAL: Satır silme Silinenler'e taşır, geri alınabilir — masaüstü KAYIT_DURUMU=1.
	public static function hareket_sil() {
		$aday  = self::aday_veya_don( YP_Guvenlik::tamsayi( 'id' ) );
		$satir = self::aday_satiri( $aday );
		self::tek_islemde( function () use ( $satir, $aday ) {
			YP_Veri::hareket_guncelle( $satir->id, array(
				'silindi'      => 1,
				'silme_zamani' => YP_Cekirdek::simdi(),
				'silen'        => YP_Cekirdek::kullanici_id(),
			) );
			self::log( $aday->id, 'silme', 'Ödeme kartı satırı silindi: ' . $satir->borc_tipi . ' ' . YP_Bicim::tl( $satir->tutar ) . ' (' . $satir->durum . ')' );
		}, self::aday_url( $aday->id, 'odeme' ) );
		self::yonlendir( self::aday_url( $aday->id, 'odeme' ), 'Satır silindi. Silinenler ekranından geri alınabilir.' );
	}
}
