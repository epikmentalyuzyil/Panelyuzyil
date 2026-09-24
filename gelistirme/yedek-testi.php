<?php
/**
 * Yedek al → veriyi boz → geri yükle → birebir aynı mı?
 * KURAL: Geri yükleme "çalıştı" demek yetmez; her tablonun her satırı tek tek karşılaştırılır.
 */
$_SERVER['HTTP_HOST'] = 'localhost:8765';
require '/home/user/wordpress/site/wp-load.php';
foreach ( array( 'bicim', 'guvenlik', 'veri', 'hesap', 'foto', 'excel', 'yedek' ) as $sinif ) {
	require_once WP_PLUGIN_DIR . '/yuzyil-panel/includes/class-yp-' . $sinif . '.php';
}
global $wpdb;

$basarisiz = 0;
function kontrol( $ad, $sonuc, $ek = '' ) {
	global $basarisiz;
	echo ( $sonuc ? '  TAMAM  ' : '  HATA   ' ) . $ad . ( '' !== $ek ? ' → ' . $ek : '' ) . "\n";
	if ( ! $sonuc ) {
		$basarisiz++;
	}
}

function parmak_izi() {
	global $wpdb;
	$izler = array();
	foreach ( YP_Yedek::tablolar() as $t ) {
		$ad = YP_Cekirdek::tablo( $t );
		$satirlar = $wpdb->get_results( "SELECT * FROM {$ad} ORDER BY id", ARRAY_A );
		$izler[ $t ] = array(
			'adet' => count( $satirlar ),
			'ozet' => hash( 'sha256', wp_json_encode( $satirlar ) ),
		);
	}
	return $izler;
}

echo "--- 1. Yedek alınıyor ---\n";
$once = parmak_izi();
echo '  mevcut: ';
foreach ( $once as $t => $i ) { echo $t . '=' . $i['adet'] . ' '; }
echo "\n";

$zip_yolu = YP_Yedek::olustur( false );
kontrol( 'yedek dosyası oluştu', ! is_wp_error( $zip_yolu ), is_wp_error( $zip_yolu ) ? $zip_yolu->get_error_message() : round( filesize( $zip_yolu ) / 1024 ) . ' KB' );
if ( is_wp_error( $zip_yolu ) ) { exit( 1 ); }

echo "\n--- 2. Paketin içindekiler ---\n";
$zip = new ZipArchive();
$zip->open( $zip_yolu );
$icerik = array();
for ( $i = 0; $i < $zip->numFiles; $i++ ) {
	$st = $zip->statIndex( $i );
	$icerik[ $st['name'] ] = $st['size'];
}
$zip->close();
foreach ( $icerik as $ad => $boyut ) {
	echo '    ' . str_pad( $ad, 34 ) . ' ' . number_format( $boyut ) . " bayt\n";
}
foreach ( array( 'yedek.json', 'BILGI.txt', 'excel/adaylar.xlsx', 'excel/kasa-hareketleri.xlsx', 'excel/referanslar.xlsx', 'excel/tanimlar-ve-hesaplar.xlsx' ) as $beklenen ) {
	kontrol( $beklenen . ' var ve boş değil', isset( $icerik[ $beklenen ] ) && $icerik[ $beklenen ] > 200 );
}

echo "\n--- 3. Excel dosyaları gerçekten açılabiliyor mu ---\n";
foreach ( array( 'excel/adaylar.xlsx', 'excel/kasa-hareketleri.xlsx', 'excel/referanslar.xlsx', 'excel/tanimlar-ve-hesaplar.xlsx' ) as $x ) {
	$ic = new ZipArchive();
	$gecici = wp_tempnam( 'yp-x' );
	$z = new ZipArchive();
	$z->open( $zip_yolu );
	file_put_contents( $gecici, $z->getFromName( $x ) );
	$z->close();
	$acildi = ( true === $ic->open( $gecici ) );
	$sayfa  = $acildi ? $ic->getFromName( 'xl/worksheets/sheet1.xml' ) : '';
	$satir  = substr_count( (string) $sayfa, '<row ' );
	if ( $acildi ) { $ic->close(); }
	wp_delete_file( $gecici );
	kontrol( $x . ' geçerli bir Excel dosyası', $acildi && $satir > 1, $satir . ' satır' );
}

echo "\n--- 4. Türkçe karakterler Excel'de doğru mu ---\n";
$z = new ZipArchive();
$z->open( $zip_yolu );
$sayfa_xml = '';
$gecici = wp_tempnam( 'yp-x' );
file_put_contents( $gecici, $z->getFromName( 'excel/adaylar.xlsx' ) );
$z->close();
$ic = new ZipArchive();
$ic->open( $gecici );
$sayfa_xml = $ic->getFromName( 'xl/worksheets/sheet1.xml' );
$ic->close();
wp_delete_file( $gecici );
$ornek_ad = $wpdb->get_var( "SELECT soyadi FROM " . YP_Cekirdek::tablo( 'adaylar' ) . " WHERE soyadi LIKE '%ş%' OR soyadi LIKE '%ğ%' OR soyadi LIKE '%ü%' LIMIT 1" );
kontrol( 'Türkçe soyadı Excel içinde aynen duruyor', $ornek_ad && false !== strpos( $sayfa_xml, htmlspecialchars( $ornek_ad, ENT_QUOTES | ENT_XML1, 'UTF-8' ) ), (string) $ornek_ad );

echo "\n--- 5. Yedek okunuyor ve parmak izi doğrulanıyor ---\n";
$paket = YP_Yedek::oku( $zip_yolu );
kontrol( 'yedek okundu ve doğrulandı', ! is_wp_error( $paket ), is_wp_error( $paket ) ? $paket->get_error_message() : 'biçim ' . $paket['bicim'] . ', ' . $paket['sayilar']['adaylar'] . ' aday' );
if ( is_wp_error( $paket ) ) { exit( 1 ); }
kontrol( 'SMS şifresi yedeğe YAZILMAMIŞ', ! isset( $paket['govde']['ayarlar']['sms_sifre'] ) );
kontrol( 'panel giriş şifresi yedeğe YAZILMAMIŞ', ! isset( $paket['govde']['ayarlar']['kilit_hash'] ) );

echo "\n--- 6. Bozuk dosya reddediliyor mu ---\n";
$bozuk = wp_tempnam( 'yp-bozuk' );
copy( $zip_yolu, $bozuk );
$bz = new ZipArchive();
$bz->open( $bozuk );
$ham = $bz->getFromName( 'yedek.json' );
$bz->deleteName( 'yedek.json' );
$bz->addFromString( 'yedek.json', str_replace( '"adi":"', '"adi":"X', $ham ) );
$bz->close();
$bozuk_sonuc = YP_Yedek::oku( $bozuk );
kontrol( 'kurcalanmış yedek reddedildi', is_wp_error( $bozuk_sonuc ), is_wp_error( $bozuk_sonuc ) ? $bozuk_sonuc->get_error_message() : 'KABUL EDİLDİ!' );
wp_delete_file( $bozuk );

$sahte = wp_tempnam( 'yp-sahte' );
$sz = new ZipArchive();
$sz->open( $sahte, ZipArchive::OVERWRITE );
$sz->addFromString( 'merhaba.txt', 'bu bir yedek degil' );
$sz->close();
$sahte_sonuc = YP_Yedek::oku( $sahte );
kontrol( 'yedek olmayan zip reddedildi', is_wp_error( $sahte_sonuc ), is_wp_error( $sahte_sonuc ) ? $sahte_sonuc->get_error_message() : 'KABUL EDİLDİ!' );
wp_delete_file( $sahte );

echo "\n--- 7. Veri bilerek bozuluyor ---\n";
$wpdb->query( 'DELETE FROM ' . YP_Cekirdek::tablo( 'hareketler' ) . ' LIMIT 40' );
$wpdb->query( 'DELETE FROM ' . YP_Cekirdek::tablo( 'adaylar' ) . ' LIMIT 12' );
$wpdb->query( "UPDATE " . YP_Cekirdek::tablo( 'referanslar' ) . " SET unvan = 'BOZULDU' WHERE id = 1" );
$bozuk_iz = parmak_izi();
kontrol( 'veri gerçekten bozuldu', $bozuk_iz['adaylar']['ozet'] !== $once['adaylar']['ozet'],
	'aday ' . $once['adaylar']['adet'] . ' → ' . $bozuk_iz['adaylar']['adet'] . ', hareket ' . $once['hareketler']['adet'] . ' → ' . $bozuk_iz['hareketler']['adet'] );

echo "\n--- 8. Geri yükleniyor ---\n";
$sonuc = YP_Yedek::geri_yukle( $paket );
kontrol( 'geri yükleme tamamlandı', ! is_wp_error( $sonuc ), is_wp_error( $sonuc ) ? $sonuc->get_error_message() : array_sum( $sonuc['yazilan'] ) . ' satır yazıldı' );
if ( is_wp_error( $sonuc ) ) { exit( 1 ); }

echo "\n--- 9. Veri BİREBİR geri geldi mi ---\n";
$sonra = parmak_izi();
foreach ( YP_Yedek::tablolar() as $t ) {
	// KURAL: İşlem günlüğü hariç tutulur — geri yükleme işleminin kendisi günlüğe bir satır yazar, olması gereken budur.
	if ( 'log' === $t ) {
		continue;
	}
	$ayni = $once[ $t ]['ozet'] === $sonra[ $t ]['ozet'];
	kontrol(
		str_pad( $t, 14 ) . ' birebir aynı',
		$ayni,
		$once[ $t ]['adet'] . ' satır' . ( $ayni ? '' : ' — ŞİMDİ ' . $sonra[ $t ]['adet'] )
	);
}
$son_log = $wpdb->get_row( 'SELECT * FROM ' . YP_Cekirdek::tablo( 'log' ) . ' ORDER BY id DESC LIMIT 1' );
kontrol( 'geri yükleme işlem günlüğüne yazıldı', $son_log && 'geri_yukle' === $son_log->islem, $son_log ? $son_log->aciklama : 'kayıt yok' );

echo "\n--- 10. Para hesapları tutuyor mu ---\n";
$alacak = YP_Hesap::genel_alacak();
kontrol( 'genel alacak hesaplanabiliyor', is_array( $alacak ) && $alacak['kalan'] > 0, YP_Bicim::tl( $alacak['kalan'] ) );
$kasa = YP_Hesap::hesap_bakiyesi( YP_Veri::hesap( 1 ) );
kontrol( 'kasa bakiyesi hesaplanabiliyor', is_numeric( $kasa ), YP_Bicim::tl( $kasa ) );

wp_delete_file( $zip_yolu );
echo "\nSONUÇ: " . ( $basarisiz ? $basarisiz . ' DENETİM BAŞARISIZ' : 'tüm denetimler geçti' ) . "\n";
exit( $basarisiz ? 1 : 0 );
