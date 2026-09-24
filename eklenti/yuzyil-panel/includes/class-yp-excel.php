<?php
defined( 'ABSPATH' ) || exit;

/**
 * Excel çıktısı. Harici kütüphane kullanmadan .xlsx üretir; sunucuda zip desteği yoksa Excel'in açacağı CSV'ye düşer.
 */
final class YP_Excel {

	/**
	 * @param string $dosya_adi Uzantısız dosya adı.
	 * @param array  $basliklar Sütun başlıkları.
	 * @param array  $satirlar  Satırlar (her satır sütun sırasına göre dizi).
	 * @param array  $turler    Sütun türleri: metin | sayi | tutar | tarih.
	 * @param string $baslik    Sayfanın üstüne yazılacak açıklama (isteğe bağlı).
	 */
	public static function indir( $dosya_adi, array $basliklar, array $satirlar, array $turler = array(), $baslik = '' ) {
		$ad = sanitize_file_name( $dosya_adi . '-' . gmdate( 'Ymd-Hi' ) );
		if ( class_exists( 'ZipArchive' ) ) {
			self::xlsx( $ad, $basliklar, $satirlar, $turler, $baslik );
		} else {
			self::csv( $ad, $basliklar, $satirlar, $turler, $baslik );
		}
		exit;
	}

	// KURAL: Türkçe Excel'de bozulmasın diye CSV UTF-8 BOM ile ve noktalı virgülle yazılır.
	private static function csv( $ad, array $basliklar, array $satirlar, array $turler, $baslik ) {
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $ad . '.csv"' );
		echo "\xEF\xBB\xBF"; // phpcs:ignore
		$cikti = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( '' !== $baslik ) {
			fputcsv( $cikti, array( $baslik ), ';' );
		}
		fputcsv( $cikti, $basliklar, ';' );
		foreach ( $satirlar as $satir ) {
			$temiz = array();
			foreach ( array_values( $satir ) as $i => $deger ) {
				$tur     = isset( $turler[ $i ] ) ? $turler[ $i ] : 'metin';
				$temiz[] = ( 'tutar' === $tur || 'sayi' === $tur ) ? str_replace( '.', ',', (string) $deger ) : $deger;
			}
			fputcsv( $cikti, $temiz, ';' );
		}
		fclose( $cikti ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}

	/**
	 * Aynı Excel'i indirmek yerine içerik olarak döndürür — yedek paketine konur.
	 * KURAL: Tarayıcıya hiçbir başlık gönderilmez; bu metot yalnızca dosya içeriği üretir.
	 */
	public static function icerik( array $basliklar, array $satirlar, array $turler = array(), $baslik = '' ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return '';
		}
		if ( ! function_exists( 'wp_tempnam' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		$gecici = wp_tempnam( 'yp-xlsx' );
		$zip    = new ZipArchive();
		if ( true !== $zip->open( $gecici, ZipArchive::OVERWRITE ) ) {
			return '';
		}
		$zip->addFromString( '[Content_Types].xml', self::icerik_turleri() );
		$zip->addFromString( '_rels/.rels', self::kok_iliskiler() );
		$zip->addFromString( 'xl/workbook.xml', self::calisma_kitabi() );
		$zip->addFromString( 'xl/_rels/workbook.xml.rels', self::kitap_iliskileri() );
		$zip->addFromString( 'xl/styles.xml', self::stiller() );
		$zip->addFromString( 'xl/worksheets/sheet1.xml', self::sayfa( $basliklar, $satirlar, $turler, $baslik ) );
		$zip->close();

		$icerik = file_get_contents( $gecici ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		wp_delete_file( $gecici );
		return false === $icerik ? '' : $icerik;
	}

	private static function xlsx( $ad, array $basliklar, array $satirlar, array $turler, $baslik ) {
		if ( ! function_exists( 'wp_tempnam' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		$gecici = wp_tempnam( 'yp-excel' );
		$zip    = new ZipArchive();
		if ( true !== $zip->open( $gecici, ZipArchive::OVERWRITE ) ) {
			self::csv( $ad, $basliklar, $satirlar, $turler, $baslik );
			return;
		}
		$zip->addFromString( '[Content_Types].xml', self::icerik_turleri() );
		$zip->addFromString( '_rels/.rels', self::kok_iliskiler() );
		$zip->addFromString( 'xl/workbook.xml', self::calisma_kitabi() );
		$zip->addFromString( 'xl/_rels/workbook.xml.rels', self::kitap_iliskileri() );
		$zip->addFromString( 'xl/styles.xml', self::stiller() );
		$zip->addFromString( 'xl/worksheets/sheet1.xml', self::sayfa( $basliklar, $satirlar, $turler, $baslik ) );
		$zip->close();

		nocache_headers();
		header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
		header( 'Content-Disposition: attachment; filename="' . $ad . '.xlsx"' );
		header( 'Content-Length: ' . filesize( $gecici ) );
		readfile( $gecici ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		wp_delete_file( $gecici );
	}

	private static function kacis( $metin ) {
		return htmlspecialchars( (string) $metin, ENT_QUOTES | ENT_XML1, 'UTF-8' );
	}

	private static function sutun_adi( $sira ) {
		$ad = '';
		while ( $sira > 0 ) {
			$kalan = ( $sira - 1 ) % 26;
			$ad    = chr( 65 + $kalan ) . $ad;
			$sira  = (int) ( ( $sira - $kalan - 1 ) / 26 );
		}
		return $ad;
	}

	// KURAL: Tutar ve sayı hücreleri Excel'de gerçek sayıdır (toplam alınabilir); tarih ve metin düz yazıdır.
	private static function hucre( $sutun, $satir_no, $deger, $tur, $stil = 0 ) {
		$ref = self::sutun_adi( $sutun ) . $satir_no;
		if ( 'tutar' === $tur || 'sayi' === $tur ) {
			$sayi = is_numeric( $deger ) ? (float) $deger : (float) str_replace( array( '.', ',' ), array( '', '.' ), preg_replace( '/[^\d,.\-]/', '', (string) $deger ) );
			$s    = 'tutar' === $tur ? 2 : 0;
			return '<c r="' . $ref . '"' . ( $s ? ' s="' . $s . '"' : '' ) . '><v>' . self::kacis( $sayi ) . '</v></c>';
		}
		return '<c r="' . $ref . '" t="inlineStr"' . ( $stil ? ' s="' . $stil . '"' : '' ) . '><is><t xml:space="preserve">' . self::kacis( $deger ) . '</t></is></c>';
	}

	private static function sayfa( array $basliklar, array $satirlar, array $turler, $baslik ) {
		$xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
		$xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><cols>';
		foreach ( $basliklar as $i => $b ) {
			$genislik = min( 46, max( 12, mb_strlen( (string) $b, 'UTF-8' ) + 4 ) );
			$xml     .= '<col min="' . ( $i + 1 ) . '" max="' . ( $i + 1 ) . '" width="' . $genislik . '" customWidth="1"/>';
		}
		$xml .= '</cols><sheetData>';
		$no   = 1;
		if ( '' !== $baslik ) {
			$xml .= '<row r="1">' . self::hucre( 1, 1, $baslik, 'metin', 1 ) . '</row>';
			$no++;
		}
		$xml .= '<row r="' . $no . '">';
		foreach ( $basliklar as $i => $b ) {
			$xml .= self::hucre( $i + 1, $no, $b, 'metin', 1 );
		}
		$xml .= '</row>';
		foreach ( $satirlar as $satir ) {
			$no++;
			$xml .= '<row r="' . $no . '">';
			foreach ( array_values( $satir ) as $i => $deger ) {
				$xml .= self::hucre( $i + 1, $no, $deger, isset( $turler[ $i ] ) ? $turler[ $i ] : 'metin' );
			}
			$xml .= '</row>';
		}
		return $xml . '</sheetData></worksheet>';
	}

	private static function icerik_turleri() {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
			. '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
			. '<Default Extension="xml" ContentType="application/xml"/>'
			. '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
			. '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
			. '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
			. '</Types>';
	}

	private static function kok_iliskiler() {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
			. '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
			. '</Relationships>';
	}

	private static function calisma_kitabi() {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
			. '<sheets><sheet name="Liste" sheetId="1" r:id="rId1"/></sheets></workbook>';
	}

	private static function kitap_iliskileri() {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
			. '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
			. '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
			. '</Relationships>';
	}

	// KURAL: Stil 1 = kalın başlık, stil 2 = #.##0,00 tutar biçimi.
	private static function stiller() {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
			. '<numFmts count="1"><numFmt numFmtId="164" formatCode="#,##0.00"/></numFmts>'
			. '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
			. '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
			. '<borders count="1"><border/></borders>'
			. '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
			. '<cellXfs count="3">'
			. '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
			. '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
			. '<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
			. '</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
	}
}
