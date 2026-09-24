<?php
defined( 'ABSPATH' ) || exit;

/**
 * Türkçe biçimlendirme: tarih (gg.aa.yyyy), tutar (1.250,00 ₺), metin katlama ve doğrulamalar.
 */
final class YP_Bicim {

	// KURAL: Tarih ekranda her zaman gg.aa.yyyy gösterilir — veritabanında yyyy-aa-gg saklanır.
	public static function tarih( $ymd ) {
		if ( empty( $ymd ) || '0000-00-00' === substr( (string) $ymd, 0, 10 ) ) {
			return '';
		}
		$p = explode( '-', substr( (string) $ymd, 0, 10 ) );
		return 3 === count( $p ) ? $p[2] . '.' . $p[1] . '.' . $p[0] : '';
	}

	// KURAL: Gün başlığı "20 Eylül 2026 Pazar" biçiminde yazılır — masaüstü kasa ekranındaki gibi.
	public static function tarih_uzun( $ymd ) {
		if ( empty( $ymd ) ) {
			return '';
		}
		$gunler = array( 'Pazar', 'Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma', 'Cumartesi' );
		$zaman  = strtotime( $ymd . ' 12:00:00' );
		return (int) gmdate( 'j', $zaman ) . ' ' . self::ay_adi( (int) gmdate( 'n', $zaman ) ) . ' ' . gmdate( 'Y', $zaman ) . ' ' . $gunler[ (int) gmdate( 'w', $zaman ) ];
	}

	public static function tarih_saat( $mysql ) {
		if ( empty( $mysql ) ) {
			return '';
		}
		return self::tarih( substr( $mysql, 0, 10 ) ) . ' ' . substr( (string) $mysql, 11, 5 );
	}

	// KURAL: Tutar 1.250,00 ₺ biçiminde gösterilir — binlik nokta, kuruş virgül.
	public static function tl( $tutar, $simge = true ) {
		$metin = number_format( (float) $tutar, 2, ',', '.' );
		return $simge ? $metin . ' ₺' : $metin;
	}

	// KURAL: Girilen tarih gg.aa.yyyy veya yyyy-aa-gg olabilir; geçersizse boş döner — hatalı tarih kaydedilmez.
	public static function tarih_oku( $girdi ) {
		$girdi = trim( (string) $girdi );
		if ( '' === $girdi ) {
			return '';
		}
		if ( preg_match( '/^(\d{1,2})[.\/-](\d{1,2})[.\/-](\d{4})$/', $girdi, $m ) ) {
			$g = (int) $m[1];
			$a = (int) $m[2];
			$y = (int) $m[3];
		} elseif ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $girdi, $m ) ) {
			$y = (int) $m[1];
			$a = (int) $m[2];
			$g = (int) $m[3];
		} else {
			return '';
		}
		if ( $y < 1900 || $y > 2100 || ! checkdate( $a, $g, $y ) ) {
			return '';
		}
		return sprintf( '%04d-%02d-%02d', $y, $a, $g );
	}

	// KURAL: Tutar "1.250,50", "1250,5" veya "1250.50" yazılabilir; en fazla 2 kuruş hanesi tutulur.
	public static function tutar_oku( $girdi ) {
		$s = preg_replace( '/[^\d,.\-]/', '', (string) $girdi );
		if ( '' === $s ) {
			return 0.0;
		}
		if ( false !== strpos( $s, ',' ) ) {
			$s = str_replace( '.', '', $s );
			$s = str_replace( ',', '.', $s );
		} elseif ( substr_count( $s, '.' ) > 1 ) {
			$s = str_replace( '.', '', $s );
		} elseif ( preg_match( '/\.\d{3}$/', $s ) ) {
			// KURAL: "1.250" gibi yazım binlik ayraç sayılır — Türkçe alışkanlık.
			$s = str_replace( '.', '', $s );
		}
		return round( (float) $s, 2 );
	}

	// KURAL: Arama için Türkçe harfler sadeleştirilir ve küçültülür — "sukru" yazınca "Şükrü" bulunur.
	public static function katla( $metin ) {
		$metin = (string) $metin;
		$tablo = array(
			'İ' => 'i', 'I' => 'i', 'ı' => 'i', 'Ş' => 's', 'ş' => 's', 'Ğ' => 'g', 'ğ' => 'g',
			'Ü' => 'u', 'ü' => 'u', 'Ö' => 'o', 'ö' => 'o', 'Ç' => 'c', 'ç' => 'c',
			'Â' => 'a', 'â' => 'a', 'Î' => 'i', 'î' => 'i', 'Û' => 'u', 'û' => 'u',
		);
		$metin = strtr( $metin, $tablo );
		$metin = function_exists( 'mb_strtolower' ) ? mb_strtolower( $metin, 'UTF-8' ) : strtolower( $metin );
		return trim( preg_replace( '/\s+/', ' ', $metin ) );
	}

	// KURAL: Ad ve soyad Türkçe büyük harfe çevrilir — i→İ, ı→I doğru çevrilir.
	public static function buyuk( $metin ) {
		$metin = strtr( (string) $metin, array( 'i' => 'İ', 'ı' => 'I' ) );
		return function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $metin, 'UTF-8' ) : strtoupper( $metin );
	}

	// KURAL: TC no 11 hane ve resmi algoritmaya uymalı; uymazsa yalnızca uyarı verilir — masaüstü kaydı engellemiyordu.
	public static function tc_gecerli( $tc ) {
		if ( ! preg_match( '/^[1-9]\d{10}$/', (string) $tc ) ) {
			return false;
		}
		$d = array_map( 'intval', str_split( $tc ) );
		$tek  = $d[0] + $d[2] + $d[4] + $d[6] + $d[8];
		$cift = $d[1] + $d[3] + $d[5] + $d[7];
		$h10  = ( ( $tek * 7 ) - $cift ) % 10;
		if ( $h10 < 0 ) {
			$h10 += 10;
		}
		$h11 = array_sum( array_slice( $d, 0, 10 ) ) % 10;
		return $h10 === $d[9] && $h11 === $d[10];
	}

	// KURAL: Telefon yalnızca rakam olarak saklanır, başındaki 0 ve 90 atılır — aramada biçim farkı sorun olmaz.
	public static function telefon_temizle( $tel ) {
		$r = preg_replace( '/\D/', '', (string) $tel );
		if ( 12 === strlen( $r ) && 0 === strpos( $r, '90' ) ) {
			$r = substr( $r, 2 );
		}
		if ( 11 === strlen( $r ) && '0' === $r[0] ) {
			$r = substr( $r, 1 );
		}
		return substr( $r, 0, 20 );
	}

	public static function telefon( $tel ) {
		$r = (string) $tel;
		// KURAL: Telefon gruplarının arası bölünmez boşlukla yazılır — dar tabloda satır kırılmaz.
		if ( 10 === strlen( $r ) ) {
			$b = "\u{00A0}";
			return '0' . substr( $r, 0, 3 ) . $b . substr( $r, 3, 3 ) . $b . substr( $r, 6, 2 ) . $b . substr( $r, 8, 2 );
		}
		return $r;
	}

	// KURAL: Makbuzda tutar yazıyla da yazılır — "Bin iki yüz elli Türk lirası".
	public static function yaziyla( $tutar ) {
		$kurus_toplam = (int) round( abs( (float) $tutar ) * 100 );
		$lira         = intdiv( $kurus_toplam, 100 );
		$kurus        = $kurus_toplam % 100;
		$metin        = ( 0 === $lira ? 'Sıfır' : self::sayi_yazi( $lira ) ) . ' Türk lirası';
		if ( $kurus > 0 ) {
			$metin .= ' ' . self::sayi_yazi( $kurus ) . ' kuruş';
		}
		$metin = trim( preg_replace( '/\s+/', ' ', $metin ) );
		return function_exists( 'mb_strtoupper' ) ? mb_strtoupper( mb_substr( $metin, 0, 1, 'UTF-8' ), 'UTF-8' ) . mb_substr( $metin, 1, null, 'UTF-8' ) : ucfirst( $metin );
	}

	private static function sayi_yazi( $n ) {
		$birler  = array( '', 'bir', 'iki', 'üç', 'dört', 'beş', 'altı', 'yedi', 'sekiz', 'dokuz' );
		$onlar   = array( '', 'on', 'yirmi', 'otuz', 'kırk', 'elli', 'altmış', 'yetmiş', 'seksen', 'doksan' );
		$basamak = array( '', 'bin', 'milyon', 'milyar' );
		$parcalar = array();
		$i        = 0;
		while ( $n > 0 && $i < 4 ) {
			$uc = $n % 1000;
			if ( $uc > 0 ) {
				$y = intdiv( $uc, 100 );
				$o = intdiv( $uc % 100, 10 );
				$b = $uc % 10;
				$s = ( $y > 0 ? ( 1 === $y ? '' : $birler[ $y ] . ' ' ) . 'yüz ' : '' ) . ( $o ? $onlar[ $o ] . ' ' : '' ) . ( $b ? $birler[ $b ] . ' ' : '' );
				// KURAL: "bir bin" denmez, "bin" denir.
				if ( 1 === $i && 1 === $uc ) {
					$s = '';
				}
				$parcalar[] = trim( $s . ' ' . $basamak[ $i ] );
			}
			$n = intdiv( $n, 1000 );
			$i++;
		}
		return implode( ' ', array_reverse( $parcalar ) );
	}

	public static function ay_adi( $ay ) {
		$aylar = array( 1 => 'Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık' );
		return isset( $aylar[ (int) $ay ] ) ? $aylar[ (int) $ay ] : '';
	}

	public static function gun_ekle( $ymd, $gun ) {
		return gmdate( 'Y-m-d', strtotime( $ymd . ' ' . ( $gun >= 0 ? '+' : '' ) . (int) $gun . ' days' ) );
	}

	// KURAL: Ay eklerken ayın son günü aşılırsa ayın son gününe çekilir — 31 Ocak + 1 ay = 28/29 Şubat.
	public static function ay_ekle( $ymd, $ay ) {
		list( $y, $a, $g ) = array_map( 'intval', explode( '-', $ymd ) );
		$a  += (int) $ay;
		$y  += (int) floor( ( $a - 1 ) / 12 );
		$a   = ( ( $a - 1 ) % 12 + 12 ) % 12 + 1;
		$son = (int) gmdate( 't', gmmktime( 0, 0, 0, $a, 1, $y ) );
		return sprintf( '%04d-%02d-%02d', $y, $a, min( $g, $son ) );
	}
}
