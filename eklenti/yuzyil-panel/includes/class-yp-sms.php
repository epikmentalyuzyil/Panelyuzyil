<?php
defined( 'ABSPATH' ) || exit;

/**
 * SMS gönderimi (Netgsm). Numara temizleme, Türkçe karakter seçeneği, SMS adedi hesabı
 * ve sağlayıcıya gönderim burada toplanır.
 * KURAL: Ekran kodu sağlayıcıyı tanımaz — sağlayıcı değişirse yalnızca bu dosya değişir.
 */
final class YP_Sms {

	// KURAL: Türkçe karakterli mesaj 70, karaktersiz mesaj 160 haneye sığar — sınırlar burada tek yerde durur.
	const HANE_TURKCE      = 70;
	const HANE_TURKCE_COK  = 67;
	const HANE_DUZ         = 160;
	const HANE_DUZ_COK     = 153;

	// KURAL: Tek gönderimde en fazla bu kadar numara işlenir — yanlışlıkla devasa gönderim yapılamaz.
	const EN_COK_ALICI = 500;

	const UC_GONDER = 'https://api.netgsm.com.tr/sms/send/xml';
	const UC_BAKIYE = 'https://api.netgsm.com.tr/balance/list/get';

	// ---- Ayarlar --------------------------------------------------------

	public static function ayar( $anahtar ) {
		return (string) YP_Cekirdek::ayar( $anahtar );
	}

	public static function acik() {
		return '1' === (string) YP_Cekirdek::ayar( 'sms_aktif' );
	}

	/**
	 * Gönderim için gereken üç bilgi de girilmiş mi?
	 */
	public static function ayarlar_tamam() {
		return '' !== self::ayar( 'sms_kullanici' ) && '' !== self::ayar( 'sms_sifre' ) && '' !== self::ayar( 'sms_baslik' );
	}

	public static function eksik_ayar_metni() {
		$eksik = array();
		if ( '' === self::ayar( 'sms_kullanici' ) ) {
			$eksik[] = 'abone numarası';
		}
		if ( '' === self::ayar( 'sms_sifre' ) ) {
			$eksik[] = 'şifre';
		}
		if ( '' === self::ayar( 'sms_baslik' ) ) {
			$eksik[] = 'mesaj başlığı';
		}
		return implode( ', ', $eksik );
	}

	// ---- Numara ---------------------------------------------------------

	/**
	 * Girilen telefonu 10 haneli cep numarasına çevirir (5xxxxxxxxx); çeviremezse boş döner.
	 * KURAL: Yalnızca cep numarasına SMS gider — sabit hat, eksik ya da bozuk numara sessizce elenmez, ayrıca gösterilir.
	 */
	public static function numara_duzelt( $ham ) {
		$rakam = preg_replace( '/\D+/', '', (string) $ham );
		if ( '' === $rakam ) {
			return '';
		}
		// Ülke kodu ve baştaki sıfır ayıklanır: 90 5xx…, 0 5xx…, 5xx…
		if ( 12 === strlen( $rakam ) && 0 === strpos( $rakam, '90' ) ) {
			$rakam = substr( $rakam, 2 );
		} elseif ( 13 === strlen( $rakam ) && 0 === strpos( $rakam, '090' ) ) {
			$rakam = substr( $rakam, 3 );
		}
		if ( 11 === strlen( $rakam ) && '0' === $rakam[0] ) {
			$rakam = substr( $rakam, 1 );
		}
		if ( 10 !== strlen( $rakam ) || '5' !== $rakam[0] ) {
			return '';
		}
		return $rakam;
	}

	public static function numara_yaz( $on_hane ) {
		$n = self::numara_duzelt( $on_hane );
		if ( '' === $n ) {
			return '';
		}
		return '0' . substr( $n, 0, 3 ) . ' ' . substr( $n, 3, 3 ) . ' ' . substr( $n, 6, 2 ) . ' ' . substr( $n, 8, 2 );
	}

	// ---- Metin ----------------------------------------------------------

	/**
	 * Türkçe harfleri en yakın düz karşılığına çevirir.
	 * KURAL: Türkçe kutusu işaretli değilse mesaj bu hâliyle gider — böylece 160 haneye sığar ve tek SMS ücreti yazar.
	 */
	public static function turkcesiz( $metin ) {
		$cizelge = array(
			'ç' => 'c', 'Ç' => 'C', 'ğ' => 'g', 'Ğ' => 'G', 'ı' => 'i', 'I' => 'I', 'İ' => 'I',
			'ö' => 'o', 'Ö' => 'O', 'ş' => 's', 'Ş' => 'S', 'ü' => 'u', 'Ü' => 'U', 'â' => 'a',
			'Â' => 'A', 'î' => 'i', 'Î' => 'I', 'û' => 'u', 'Û' => 'U',
		);
		return strtr( (string) $metin, $cizelge );
	}

	/**
	 * Gönderilecek metnin son hâli.
	 */
	public static function hazirla( $metin, $turkce ) {
		$metin = trim( (string) $metin );
		return $turkce ? $metin : self::turkcesiz( $metin );
	}

	public static function uzunluk( $metin ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( (string) $metin, 'UTF-8' ) : strlen( (string) $metin );
	}

	/**
	 * Bir alıcıya kaç SMS gideceği (kredi hesabı).
	 */
	public static function parca_sayisi( $metin, $turkce ) {
		$uzunluk = self::uzunluk( self::hazirla( $metin, $turkce ) );
		if ( 0 === $uzunluk ) {
			return 0;
		}
		$tek = $turkce ? self::HANE_TURKCE : self::HANE_DUZ;
		$cok = $turkce ? self::HANE_TURKCE_COK : self::HANE_DUZ_COK;
		return $uzunluk <= $tek ? 1 : (int) ceil( $uzunluk / $cok );
	}

	public static function tek_hane_siniri( $turkce ) {
		return $turkce ? self::HANE_TURKCE : self::HANE_DUZ;
	}

	// ---- Gönderim -------------------------------------------------------

	/**
	 * Numara listesine aynı mesajı gönderir.
	 * Dönen dizi: basarili (bool), kod, mesaj, is_no.
	 * KURAL: Ağ hatası da sağlayıcı hatası da aynı biçimde döner — çağıran taraf tek bir yol izler.
	 */
	public static function gonder( array $numaralar, $metin, $turkce ) {
		if ( ! self::ayarlar_tamam() ) {
			return self::sonuc( false, 'AYAR', 'SMS ayarları eksik: ' . self::eksik_ayar_metni() . '.' );
		}
		$numaralar = array_values( array_unique( array_filter( array_map( array( __CLASS__, 'numara_duzelt' ), $numaralar ) ) ) );
		if ( ! $numaralar ) {
			return self::sonuc( false, 'NUMARA', 'Gönderilecek geçerli cep numarası yok.' );
		}
		if ( count( $numaralar ) > self::EN_COK_ALICI ) {
			return self::sonuc( false, 'SINIR', 'Tek seferde en fazla ' . self::EN_COK_ALICI . ' alıcıya gönderilebilir.' );
		}
		$metin = self::hazirla( $metin, $turkce );
		if ( '' === $metin ) {
			return self::sonuc( false, 'BOS', 'Mesaj metni boş olamaz.' );
		}

		$yanit = wp_remote_post(
			self::UC_GONDER,
			array(
				'timeout'     => 25,
				'redirection' => 0,
				'headers'     => array( 'Content-Type' => 'text/xml; charset=utf-8' ),
				'body'        => self::xml( $numaralar, $metin, $turkce ),
			)
		);

		if ( is_wp_error( $yanit ) ) {
			// KURAL: Ağ hatasında mesajın gidip gitmediği bilinemez — kayıt "hata" yazılır, kullanıcı uyarılır.
			return self::sonuc( false, 'AG', 'Sağlayıcıya ulaşılamadı: ' . $yanit->get_error_message() );
		}
		$govde = trim( (string) wp_remote_retrieve_body( $yanit ) );
		$kod   = strtok( $govde, ' ' );
		$kod   = false === $kod ? '' : trim( $kod );

		// KURAL: Netgsm başarıda "00 <işno>" ya da "01 <işno>" döner; diğer kodlar hatadır.
		if ( in_array( $kod, array( '00', '01', '02' ), true ) ) {
			$parca = explode( ' ', $govde );
			return self::sonuc( true, $kod, 'Gönderim sağlayıcıya iletildi.', isset( $parca[1] ) ? trim( $parca[1] ) : '' );
		}
		return self::sonuc( false, '' === $kod ? 'YANIT' : $kod, self::hata_metni( $kod, $govde ) );
	}

	/**
	 * Gönderim yapmadan ayarları dener: kredi sorgusu.
	 * KURAL: Deneme için SMS atılmaz — yalnızca okuma yapan kredi ucu çağrılır.
	 */
	public static function baglanti_dene() {
		if ( ! self::ayarlar_tamam() ) {
			return self::sonuc( false, 'AYAR', 'Önce abone numarası, şifre ve mesaj başlığı girilmeli.' );
		}
		$adres = add_query_arg(
			array(
				'usercode' => self::ayar( 'sms_kullanici' ),
				'password' => self::ayar( 'sms_sifre' ),
				'stip'     => '2',
			),
			self::UC_BAKIYE
		);
		$yanit = wp_remote_get( $adres, array( 'timeout' => 20, 'redirection' => 0 ) );
		if ( is_wp_error( $yanit ) ) {
			return self::sonuc( false, 'AG', 'Sağlayıcıya ulaşılamadı: ' . $yanit->get_error_message() );
		}
		$govde = trim( (string) wp_remote_retrieve_body( $yanit ) );
		$kod   = trim( (string) strtok( $govde, ' ' ) );
		if ( '00' === $kod ) {
			$parca  = explode( ' ', $govde );
			$kredi  = isset( $parca[1] ) ? trim( $parca[1] ) : '';
			return self::sonuc( true, '00', '' !== $kredi ? 'Bağlantı başarılı. Kalan kredi: ' . $kredi : 'Bağlantı başarılı.' );
		}
		return self::sonuc( false, '' === $kod ? 'YANIT' : $kod, self::hata_metni( $kod, $govde ) );
	}

	private static function sonuc( $basarili, $kod, $mesaj, $is_no = '' ) {
		return array( 'basarili' => (bool) $basarili, 'kod' => (string) $kod, 'mesaj' => (string) $mesaj, 'is_no' => (string) $is_no );
	}

	/**
	 * Netgsm 1:n XML gövdesi — tek mesaj, çok numara.
	 */
	private static function xml( array $numaralar, $metin, $turkce ) {
		$x  = '<?xml version="1.0" encoding="UTF-8"?>';
		$x .= '<mainbody>';
		$x .= '<header>';
		$x .= '<company dil="TR">Netgsm</company>';
		$x .= '<usercode>' . self::kacis( self::ayar( 'sms_kullanici' ) ) . '</usercode>';
		$x .= '<password>' . self::kacis( self::ayar( 'sms_sifre' ) ) . '</password>';
		$x .= '<type>1:n</type>';
		$x .= '<msgheader>' . self::kacis( self::ayar( 'sms_baslik' ) ) . '</msgheader>';
		// KURAL: Türkçe harf istendiğinde sağlayıcıya TR kodlaması bildirilir; yoksa harfler bozuk gider.
		if ( $turkce ) {
			$x .= '<encoding>TR</encoding>';
		}
		$x .= '</header>';
		$x .= '<body>';
		$x .= '<msg><![CDATA[' . str_replace( ']]>', ']]]]><![CDATA[>', $metin ) . ']]></msg>';
		foreach ( $numaralar as $no ) {
			$x .= '<no>' . self::kacis( $no ) . '</no>';
		}
		$x .= '</body>';
		$x .= '</mainbody>';
		return $x;
	}

	private static function kacis( $metin ) {
		return htmlspecialchars( (string) $metin, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * Sağlayıcı hata kodunu anlaşılır Türkçeye çevirir.
	 */
	public static function hata_metni( $kod, $govde = '' ) {
		$cizelge = array(
			'20' => 'Mesaj metni çok uzun ya da standart dışı karakter içeriyor.',
			'30' => 'Abone numarası veya şifre yanlış; ya da API erişim izni kapalı.',
			'40' => 'Mesaj başlığı sistemde tanımlı değil. Netgsm panelinden onaylı bir başlık girin.',
			'50' => 'Abonelik, IYS kontrollü gönderime uygun değil.',
			'51' => 'IYS marka bilgisi eksik.',
			'60' => 'Gönderim için tanımlı bir görev bulunamadı.',
			'70' => 'Gönderilen bilgilerde eksik ya da hatalı alan var.',
			'80' => 'Gönderim sınırı aşıldı.',
			'85' => 'Aynı numaraya kısa sürede çok fazla gönderim yapıldı.',
			'100' => 'Sistem hatası.',
			'101' => 'Sağlayıcı tarafında beklenmeyen bir hata oluştu.',
		);
		if ( isset( $cizelge[ $kod ] ) ) {
			return $cizelge[ $kod ] . ' (kod ' . $kod . ')';
		}
		$ek = '' !== $govde ? ' Sağlayıcı yanıtı: ' . mb_substr( $govde, 0, 120, 'UTF-8' ) : '';
		return 'Gönderim yapılamadı.' . $ek;
	}
}
