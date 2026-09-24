<?php
/**
 * KURAL: Buradaki isim, TC, telefon ve tutarların tamamı uydurmadır — gerçek müşteri verisi kullanılmaz.
 * Yalnızca yerel deneme sitesini ekran taraması için doldurur.
 */
$_SERVER['HTTP_HOST'] = 'localhost:8765';
require '/home/user/wordpress/site/wp-load.php';

// KURAL: Panel sınıfları yalnızca panel adresinde yüklenir — komut satırında elle çağrılır.
foreach ( array( 'bicim', 'guvenlik', 'veri', 'hesap' ) as $sinif ) {
	require_once WP_PLUGIN_DIR . '/yuzyil-panel/includes/class-yp-' . $sinif . '.php';
}
global $wpdb;

mt_srand( 20260924 ); // aynı veri tekrar üretilebilsin

$T = function ( $ad ) { return YP_Cekirdek::tablo( $ad ); };

// Temizlik: betik tekrar çalıştırılabilsin.
foreach ( array( 'hareketler', 'islemler', 'gorusmeler', 'adaylar', 'referanslar', 'log' ) as $t ) {
	$wpdb->query( 'TRUNCATE TABLE ' . $T( $t ) );
}
$wpdb->query( $wpdb->prepare( 'UPDATE ' . $T( 'hesaplar' ) . ' SET acilis_bakiyesi = %f, acilis_tarihi = %s WHERE id = 1', 4500.00, '2026-01-02' ) );
$wpdb->query( $wpdb->prepare( 'UPDATE ' . $T( 'hesaplar' ) . ' SET acilis_bakiyesi = %f, acilis_tarihi = %s WHERE id = 2', 18000.00, '2026-01-02' ) );

// ---- Referanslar: uydurma sürücü kursları -------------------------------

$kurslar = array(
	array( 'Yıldıztepe Sürücü Kursu', 'Necla Armağan', '0532 000 00 11' ),
	array( 'Akpınar Sürücü Kursu', 'Kerem Doğanay', '0532 000 00 12' ),
	array( 'Gölbaşı Sürücü Kursu', 'Sevim Ertekin', '0532 000 00 13' ),
	array( 'Menekşe Sürücü Kursu', 'Orhan Kılıçdemir', '0532 000 00 14' ),
	array( 'Çamlıyayla Sürücü Kursu', 'Fadime Uysalan', '0532 000 00 15' ),
	array( 'Şahinkaya Sürücü Kursu', 'Bülent Özmenteş', '0532 000 00 16' ),
	array( 'Deniz Yıldızı Sürücü Kursu', 'Gülsüm Arıkbaş', '0532 000 00 17' ),
	array( 'İpekyolu Sürücü Kursu', 'Tuncay Beşikçi', '0532 000 00 18' ),
);
$referans_idler = array();
foreach ( $kurslar as $k ) {
	$wpdb->insert(
		$T( 'referanslar' ),
		array(
			'unvan'       => $k[0],
			'ad_soyad'    => $k[1],
			'gsm'         => $k[2],
			'telefon'     => '0312 000 00 ' . mt_rand( 10, 99 ),
			'e_posta'     => '',
			'adres'       => 'Uydurma Mahallesi ' . mt_rand( 1, 40 ) . '. Sokak No ' . mt_rand( 1, 60 ),
			'notlar'      => '',
			'arama_metni' => YP_Bicim::katla( $k[0] . ' ' . $k[1] ),
			'aktif'       => 1,
			'olusturma'   => current_time( 'mysql' ),
		)
	);
	$referans_idler[] = (int) $wpdb->insert_id;
}
echo 'referans: ' . count( $referans_idler ) . "\n";

// ---- Uydurma kişi adları -----------------------------------------------

$adlar = array(
	'Ahmet', 'Mehmet', 'Mustafa', 'Hüseyin', 'Ali', 'İbrahim', 'Osman', 'Yusuf', 'Ramazan', 'Hasan',
	'Fatma', 'Ayşe', 'Emine', 'Hatice', 'Zeynep', 'Elif', 'Meryem', 'Şerife', 'Havva', 'Zeliha',
	'Sıdıka', 'Çağatay', 'Ömer', 'Gökhan', 'Işıl', 'Ülkü', 'Şaban', 'Özlem', 'İlknur', 'Buğra',
);
$soyadlar = array(
	'Yıldırım', 'Şahinoğlu', 'Çetinkaya', 'Güneşli', 'Karabulut', 'Öztürkmen', 'Aydınlı', 'Demirsoy',
	'Kurtuluş', 'Işıkçı', 'Ünalmış', 'Çakırbeyli', 'Görgülü', 'Sarıçiçek', 'Taşdemirci', 'Ağaoğlu',
	'İnceoğlu', 'Üstündağ', 'Bozkurtlu', 'Şimşekli',
);
$iller = array( 'Ankara', 'İstanbul', 'İzmir', 'Konya', 'Kayseri', 'Eskişehir', 'Çankırı', 'Kırıkkale' );

// KURAL: Uydurma TC numarası da olsa denetimden geçen bir sayı üretilir — ekranda uyarı çıkmasın.
function uydurma_tc() {
	do {
		$d = array( mt_rand( 1, 9 ) );
		for ( $i = 1; $i < 9; $i++ ) {
			$d[] = mt_rand( 0, 9 );
		}
		$tek  = $d[0] + $d[2] + $d[4] + $d[6] + $d[8];
		$cift = $d[1] + $d[3] + $d[5] + $d[7];
		$d10  = ( ( $tek * 7 ) - $cift ) % 10;
		if ( $d10 < 0 ) {
			$d10 += 10;
		}
		$d[]  = $d10;
		$d11  = array_sum( $d ) % 10;
		$d[]  = $d11;
	} while ( count( $d ) !== 11 );
	return implode( '', $d );
}

$islem_turleri = $wpdb->get_col( 'SELECT id FROM ' . $T( 'tanimlar' ) . " WHERE tur = 'islem_turu' AND aktif = 1" );
$gider_kalem   = $wpdb->get_col( 'SELECT id FROM ' . $T( 'tanimlar' ) . " WHERE tur = 'gider_kalemi' AND aktif = 1" );
$gelir_kalem   = $wpdb->get_col( 'SELECT id FROM ' . $T( 'tanimlar' ) . " WHERE tur = 'gelir_kalemi' AND aktif = 1" );

$bugun     = new DateTime( current_time( 'Y-m-d' ) );
$kullanici = 1;
$aday_no   = 1000;
$sayac     = array( 'aday' => 0, 'islem' => 0, 'hareket' => 0, 'gorusme' => 0 );

// Aynı TC ile iki kart açılan durum da denenir.
$paylasilan_tc = uydurma_tc();

for ( $i = 0; $i < 64; $i++ ) {
	$ad     = $adlar[ array_rand( $adlar ) ];
	$soyad  = $soyadlar[ array_rand( $soyadlar ) ];
	$gun    = mt_rand( 0, 300 );
	$kayit  = ( clone $bugun )->modify( "-{$gun} days" );
	$tc     = ( $i === 5 || $i === 6 ) ? $paylasilan_tc : uydurma_tc();
	$erkek  = mt_rand( 0, 1 ) === 1;
	$arsiv  = ( $i % 17 === 0 ) ? 1 : 0;
	$silik  = ( $i === 61 || $i === 62 ) ? 1 : 0;

	$veri = array(
		'aday_no'       => ++$aday_no,
		'kayit_tarihi'  => $kayit->format( 'Y-m-d' ),
		'tc_no'         => $tc,
		'adi'           => $ad,
		'soyadi'        => $soyad,
		'dogum_tarihi'  => sprintf( '%04d-%02d-%02d', mt_rand( 1962, 2005 ), mt_rand( 1, 12 ), mt_rand( 1, 28 ) ),
		'cinsiyet'      => $erkek ? 'Erkek' : 'Kadın',
		'gsm_1'         => '05' . mt_rand( 30, 59 ) . ' ' . mt_rand( 100, 999 ) . ' ' . mt_rand( 10, 99 ) . ' ' . mt_rand( 10, 99 ),
		'gsm_2'         => mt_rand( 0, 3 ) === 0 ? '05' . mt_rand( 30, 59 ) . ' ' . mt_rand( 100, 999 ) . ' ' . mt_rand( 10, 99 ) . ' ' . mt_rand( 10, 99 ) : '',
		'e_posta'       => '',
		'il'            => $iller[ array_rand( $iller ) ],
		'referans_id'   => $referans_idler[ array_rand( $referans_idler ) ],
		'ozel_notlar'   => mt_rand( 0, 4 ) === 0 ? 'Randevusunu telefonla teyit etti.' : '',
		'sari_not'      => mt_rand( 0, 6 ) === 0 ? 'Fatura ' . $kayit->format( 'd.m.Y' ) . ' tarihli' : '',
		'arsiv'         => $arsiv,
		'kayit_durumu'  => $arsiv ? 'ARŞİV' : ( mt_rand( 0, 8 ) === 0 ? 'TAMAMLANDI' : '' ),
		'silindi'       => $silik,
		'silme_zamani'  => $silik ? current_time( 'mysql' ) : null,
		'silen'         => $silik ? $kullanici : null,
		'olusturan'     => $kullanici,
		'olusturma'     => $kayit->format( 'Y-m-d' ) . ' 09:' . sprintf( '%02d', mt_rand( 0, 59 ) ) . ':00',
	);
	$veri['arama_metni'] = YP_Veri::aday_arama_metni( $veri );
	$wpdb->insert( $T( 'adaylar' ), $veri );
	$aday_id = (int) $wpdb->insert_id;
	$sayac['aday']++;

	// Her adayın 1-2 işlemi olsun.
	$islem_adet = mt_rand( 1, 2 );
	for ( $j = 0; $j < $islem_adet; $j++ ) {
		$itarih = ( clone $kayit )->modify( '+' . ( $j * mt_rand( 40, 200 ) ) . ' days' );
		if ( $itarih > $bugun ) {
			$itarih = clone $bugun;
		}
		$ucret  = (float) ( array( 750, 900, 1100, 1250, 1500, 1800 )[ mt_rand( 0, 5 ) ] );
		$durumlar = array_keys( YP_Veri::islem_durumlari() );
		$durum  = $durumlar[ mt_rand( 0, count( $durumlar ) - 1 ) ];
		$wpdb->insert(
			$T( 'islemler' ),
			array(
				'aday_id'           => $aday_id,
				'islem_turu_id'     => (int) $islem_turleri[ array_rand( $islem_turleri ) ],
				'islem_tarihi'      => $itarih->format( 'Y-m-d' ),
				'islem_saati'       => sprintf( '%02d:%02d:00', mt_rand( 9, 17 ), array( 0, 15, 30, 45 )[ mt_rand( 0, 3 ) ] ),
				'referans_id'       => $veri['referans_id'],
				'ucret'             => $ucret,
				'durum'             => $durum,
				'rapor_no'          => mt_rand( 0, 2 ) ? 'R-' . mt_rand( 10000, 99999 ) : '',
				'rapor_tarihi'      => mt_rand( 0, 2 ) ? $itarih->format( 'Y-m-d' ) : null,
				'gecerlilik_bitis'  => ( clone $itarih )->modify( '+5 years' )->format( 'Y-m-d' ),
				'aciklama'          => '',
				'olusturan'         => $kullanici,
				'olusturma'         => $itarih->format( 'Y-m-d H:i:s' ),
			)
		);
		$islem_id = (int) $wpdb->insert_id;
		$sayac['islem']++;

		// Borç satırı + ödeme durumu: tamamı ödenmiş / kısmi / hiç ödenmemiş.
		$hal = mt_rand( 1, 10 );
		if ( $hal <= 5 ) {
			$parcalar = array( $ucret );           // tamamı ödendi
			$odendi   = array( true );
		} elseif ( $hal <= 8 ) {
			$pesin    = round( $ucret * 0.4, 2 );  // kısmi
			$parcalar = array( $pesin, $ucret - $pesin );
			$odendi   = array( true, false );
		} else {
			$parcalar = array( $ucret );           // hiç ödenmedi
			$odendi   = array( false );
		}

		foreach ( $parcalar as $p => $tutar ) {
			$odendi_mi = $odendi[ $p ];
			$otarih    = ( clone $itarih )->modify( '+' . ( $p * 30 ) . ' days' );
			$oturu     = array( 'NAKIT', 'KREDI_KARTI', 'HAVALE' )[ mt_rand( 0, 2 ) ];
			$hesap     = 'NAKIT' === $oturu ? 1 : 2;
			$wpdb->insert(
				$T( 'hareketler' ),
				array(
					'kayit_turu'    => 'ADAY',
					'aday_id'       => $aday_id,
					'islem_id'      => $islem_id,
					'referans_id'   => $veri['referans_id'],
					'hesap_id'      => $odendi_mi ? $hesap : null,
					'borc_tipi'     => 1 === count( $parcalar ) ? 'İşlem ücreti' : ( 0 === $p ? 'Peşin' : 'Kalan taksit' ),
					'vade_tarihi'   => $otarih->format( 'Y-m-d' ),
					'tutar'         => $tutar,
					'durum'         => $odendi_mi ? 'ODENDI' : 'ODENMEDI',
					'odeme_turu'    => $odendi_mi ? $oturu : '',
					'odeme_tarihi'  => $odendi_mi ? ( $otarih > $bugun ? $bugun->format( 'Y-m-d' ) : $otarih->format( 'Y-m-d' ) ) : null,
					'aciklama'      => '',
					'olusturan'     => $kullanici,
					'olusturma'     => $itarih->format( 'Y-m-d H:i:s' ),
					'tahsil_eden'   => $odendi_mi ? $kullanici : null,
					'tahsil_zamani' => $odendi_mi ? $otarih->format( 'Y-m-d H:i:s' ) : null,
				)
			);
			$sayac['hareket']++;
		}
	}

	// Birkaç adaya görüşme notu.
	if ( mt_rand( 0, 3 ) === 0 ) {
		$wpdb->insert(
			$T( 'gorusmeler' ),
			array(
				'aday_id'      => $aday_id,
				'zaman'        => ( clone $kayit )->modify( '+' . mt_rand( 1, 20 ) . ' days' )->format( 'Y-m-d H:i:s' ),
				'aciklama'     => array( 'Aradı, randevu sordu.', 'Rapor teslim edildi.', 'Kalan ödeme için arandı.', 'Evrak eksiği bildirildi.' )[ mt_rand( 0, 3 ) ],
				'kullanici_id' => $kullanici,
			)
		);
		$sayac['gorusme']++;
	}
}

// ---- Kasa: son 45 günün gelir/gider hareketleri ------------------------

$kasa = 0;
for ( $g = 45; $g >= 0; $g-- ) {
	$tarih = ( clone $bugun )->modify( "-{$g} days" );
	if ( mt_rand( 0, 5 ) === 0 ) {
		continue; // bazı günler boş
	}
	$gider_adet = mt_rand( 0, 2 );
	for ( $k = 0; $k < $gider_adet; $k++ ) {
		$wpdb->insert(
			$T( 'hareketler' ),
			array(
				'kayit_turu'   => 'GIDER',
				'hesap_id'     => mt_rand( 1, 2 ),
				'kalem_id'     => (int) $gider_kalem[ array_rand( $gider_kalem ) ],
				'tutar'        => (float) mt_rand( 80, 900 ),
				'durum'        => 'ODENDI',
				'odeme_turu'   => 'NAKIT',
				'odeme_tarihi' => $tarih->format( 'Y-m-d' ),
				'aciklama'     => '',
				'olusturan'    => $kullanici,
				'olusturma'    => $tarih->format( 'Y-m-d H:i:s' ),
				'tahsil_eden'  => $kullanici,
				'tahsil_zamani'=> $tarih->format( 'Y-m-d H:i:s' ),
			)
		);
		$kasa++;
	}
	if ( mt_rand( 0, 3 ) === 0 ) {
		$wpdb->insert(
			$T( 'hareketler' ),
			array(
				'kayit_turu'   => 'GELIR',
				'hesap_id'     => mt_rand( 1, 2 ),
				'kalem_id'     => (int) $gelir_kalem[ array_rand( $gelir_kalem ) ],
				'tutar'        => (float) mt_rand( 200, 1500 ),
				'durum'        => 'ODENDI',
				'odeme_turu'   => 'NAKIT',
				'odeme_tarihi' => $tarih->format( 'Y-m-d' ),
				'aciklama'     => 'Kurum dışı gelir',
				'olusturan'    => $kullanici,
				'olusturma'    => $tarih->format( 'Y-m-d H:i:s' ),
				'tahsil_eden'  => $kullanici,
				'tahsil_zamani'=> $tarih->format( 'Y-m-d H:i:s' ),
			)
		);
		$kasa++;
	}
}
// Bir para transferi (nakit kasadan bankaya).
$wpdb->insert(
	$T( 'hareketler' ),
	array(
		'kayit_turu'      => 'TRANSFER',
		'hesap_id'        => 1,
		'hedef_hesap_id'  => 2,
		'tutar'           => 5000.00,
		'durum'           => 'ODENDI',
		'odeme_turu'      => 'NAKIT',
		'odeme_tarihi'    => ( clone $bugun )->modify( '-3 days' )->format( 'Y-m-d' ),
		'aciklama'        => 'Günlük tahsilat bankaya yatırıldı',
		'olusturan'       => $kullanici,
		'olusturma'       => current_time( 'mysql' ),
		'tahsil_eden'     => $kullanici,
		'tahsil_zamani'   => current_time( 'mysql' ),
	)
);
$kasa++;

YP_Cekirdek::veri_degisti();

echo "aday: {$sayac['aday']} · işlem: {$sayac['islem']} · aday hareketi: {$sayac['hareket']} · görüşme: {$sayac['gorusme']} · kasa hareketi: {$kasa}\n";
$alacak = YP_Hesap::genel_alacak();
echo 'genel alacak: ' . YP_Bicim::tl( $alacak['kalan'] ) . ' (geciken ' . YP_Bicim::tl( $alacak['geciken'] ) . ")\n";
echo 'nakit kasa bakiyesi: ' . YP_Bicim::tl( YP_Hesap::hesap_bakiyesi( YP_Veri::hesap( 1 ) ) ) . "\n";
