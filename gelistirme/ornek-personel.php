<?php
// KURAL: İsim, TC, maaş ve tarihlerin tamamı uydurmadır — gerçek personel verisi kullanılmaz.
$_SERVER['HTTP_HOST'] = 'localhost:8765';
require '/home/user/wordpress/site/wp-load.php';
foreach ( array( 'bicim', 'guvenlik', 'veri', 'hesap', 'personel' ) as $s ) {
	require_once WP_PLUGIN_DIR . '/yuzyil-panel/includes/class-yp-' . $s . '.php';
}
global $wpdb;
mt_srand( 4242 );

$p = YP_Cekirdek::tablo( 'personel' );
$o = YP_Cekirdek::tablo( 'personel_odeme' );
$wpdb->query( "TRUNCATE TABLE {$p}" );
$wpdb->query( "TRUNCATE TABLE {$o}" );

$bugun = new DateTime( YP_Cekirdek::bugun() );

$kisiler = array(
	// ad, soyad, görev, doğum, başlama, maaş, ayrıldı mı
	array( 'Nurten', 'Akyıldız', 'Psikolog', '1988-03-14', '2019-02-11', 62000, false ),
	array( 'Serkan', 'Çelikbaş', 'Psikoteknik Uzmanı', '1991-11-02', '2021-06-01', 54000, false ),
	array( 'Elif', 'Tunçdemir', 'Büro Görevlisi', '1996-09-28', '2023-09-18', 38000, false ),
	array( 'Kadir', 'Öztürkoğlu', 'Muhasebe', '1979-05-07', '2016-01-04', 71000, false ),
	array( 'Şule', 'Bayraktaroğlu', 'Büro Görevlisi', '1999-10-03', '2025-03-10', 36500, false ),
	array( 'Hayati', 'Güngörmüş', 'Şoför', '1972-12-21', '2018-07-23', 41000, false ),
	array( 'Bediha', 'Yalçınkaya', 'Temizlik', '1984-01-30', '2022-11-14', 29500, false ),
	array( 'Tolga', 'Şenocak', 'Psikoteknik Uzmanı', '1993-06-17', '2020-04-06', 52000, true ),
);

$hesap_nakit = 1;
$hesap_banka = 2;
$eklenen = 0;
$odeme   = 0;

foreach ( $kisiler as $k ) {
	list( $ad, $soyad, $gorev, $dogum, $baslama, $maas, $ayrildi ) = $k;
	$ayrilma = $ayrildi ? '2026-05-31' : null;
	$wpdb->insert( $p, array(
		'ad'             => $ad,
		'soyad'          => $soyad,
		'gorev'          => $gorev,
		'tc_no'          => (string) mt_rand( 10000000000, 99999999999 ),
		'dogum_tarihi'   => $dogum,
		'baslama_tarihi' => $baslama,
		'ayrilma_tarihi' => $ayrilma,
		'maas'           => $maas,
		'gsm'            => '05' . mt_rand( 30, 59 ) . ' ' . mt_rand( 100, 999 ) . ' ' . mt_rand( 10, 99 ) . ' ' . mt_rand( 10, 99 ),
		'sgk_no'         => 'SGK-' . mt_rand( 100000, 999999 ),
		'iban'           => 'TR' . mt_rand( 10, 99 ) . str_repeat( (string) mt_rand( 1, 9 ), 4 ) . mt_rand( 100000000000000, 999999999999999 ),
		'adres'          => 'Uydurma Mahallesi ' . mt_rand( 1, 30 ) . '. Sokak No ' . mt_rand( 1, 40 ),
		'notlar'         => mt_rand( 0, 3 ) === 0 ? 'Yıllık izni Temmuz ayında kullanır.' : '',
		'aktif'          => $ayrildi ? 0 : 1,
		'olusturan'      => 1,
		'olusturma'      => YP_Cekirdek::simdi(),
	) );
	$pid = (int) $wpdb->insert_id;
	$eklenen++;

	// Bu yılın maaşları: çalıştığı aylar ödenmiş, son bir iki ay bilerek boş bırakılmış.
	$yil    = (int) $bugun->format( 'Y' );
	$bu_ay  = (int) $bugun->format( 'm' );
	$bas_dt = new DateTime( $baslama );
	$son_ay = $ayrildi ? 5 : max( 1, $bu_ay - 1 );

	for ( $ay = 1; $ay <= $son_ay; $ay++ ) {
		$ay_sonu = new DateTime( sprintf( '%04d-%02d-01', $yil, $ay ) );
		$ay_sonu->modify( 'last day of this month' );
		if ( $ay_sonu < $bas_dt ) {
			continue;
		}
		// Bir ayı bilerek atla — kontrol takvimi işe yarasın.
		if ( ! $ayrildi && $ay === max( 1, $bu_ay - 3 ) && 0 === $pid % 3 ) {
			continue;
		}
		$odeme_gunu = new DateTime( sprintf( '%04d-%02d-%02d', $yil, $ay, min( 28, mt_rand( 1, 5 ) ) ) );
		$odeme_gunu->modify( '+1 month' );
		$wpdb->insert( $o, array(
			'personel_id' => $pid,
			'tur'         => 'MAAS',
			'donem'       => sprintf( '%04d-%02d', $yil, $ay ),
			'tutar'       => $maas,
			'tarih'       => $odeme_gunu->format( 'Y-m-d' ),
			'hesap_id'    => $hesap_banka,
			'aciklama'    => '',
			'olusturan'   => 1,
			'olusturma'   => YP_Cekirdek::simdi(),
		) );
		$odeme++;
	}

	// Avans, yol, yemek kartı
	foreach ( array( 'AVANS' => 3, 'YOL' => 6, 'YEMEK' => 6 ) as $tur => $adet ) {
		for ( $i = 0; $i < $adet; $i++ ) {
			$ay = mt_rand( 1, max( 1, $son_ay ) );
			$tutar = 'AVANS' === $tur ? mt_rand( 3, 12 ) * 1000 : ( 'YOL' === $tur ? mt_rand( 1200, 2600 ) : mt_rand( 1800, 3200 ) );
			$wpdb->insert( $o, array(
				'personel_id' => $pid,
				'tur'         => $tur,
				'donem'       => '',
				'tutar'       => $tutar,
				'tarih'       => sprintf( '%04d-%02d-%02d', $yil, $ay, mt_rand( 1, 28 ) ),
				'hesap_id'    => 'AVANS' === $tur ? $hesap_nakit : $hesap_banka,
				'aciklama'    => 'YEMEK' === $tur ? 'Yemek kartı yüklemesi' : ( 'YOL' === $tur ? 'Aylık yol ücreti' : '' ),
				'olusturan'   => 1,
				'olusturma'   => YP_Cekirdek::simdi(),
			) );
			$odeme++;
		}
	}
}

YP_Cekirdek::veri_degisti();
echo "personel: {$eklenen} · ödeme kaydı: {$odeme}\n";
$ozet = YP_Personel::genel_ozet();
echo 'aktif: ' . $ozet['aktif'] . ' · ayrılan: ' . $ozet['ayrilan'] . ' · aylık maaş toplamı: ' . YP_Bicim::tl( $ozet['maas_toplam'] ) . "\n";
