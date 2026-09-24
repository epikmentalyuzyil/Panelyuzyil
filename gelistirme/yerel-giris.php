<?php
// KURAL: Yalnızca localhost'ta çalışır — canlı sitede hiçbir işe yaramaz.
add_action( 'init', function () {
	$yerel = in_array( $_SERVER['REMOTE_ADDR'] ?? '', array( '127.0.0.1', '::1' ), true );
	if ( ! $yerel || empty( $_GET['yerel_giris'] ) ) {
		return;
	}
	$kullanici = get_user_by( 'login', sanitize_user( wp_unslash( $_GET['yerel_giris'] ) ) );
	if ( ! $kullanici ) {
		return;
	}
	wp_set_current_user( $kullanici->ID );
	wp_set_auth_cookie( $kullanici->ID, true );

	// KURAL: Panelin ikinci kilidi de açılır — deneme sırasında her seferinde şifre sorulmasın.
	// Eklenti kodu değiştirilmez; kilidin kendi saklama biçimi birebir taklit edilir.
	$anahtar = wp_generate_password( 43, false, false );
	$ozet    = hash_hmac( 'sha256', $anahtar, wp_salt( 'auth' ) );
	$liste   = get_user_meta( $kullanici->ID, 'yp_kilit_anahtarlari', true );
	$liste   = is_array( $liste ) ? $liste : array();
	$liste[ $ozet ] = time() + DAY_IN_SECONDS;
	update_user_meta( $kullanici->ID, 'yp_kilit_anahtarlari', $liste );
	setcookie(
		'yp_kilit_' . ( defined( 'COOKIEHASH' ) ? COOKIEHASH : 'yp' ),
		$anahtar,
		array(
			'expires'  => time() + DAY_IN_SECONDS,
			'path'     => defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/',
			'secure'   => false,
			'httponly' => true,
			'samesite' => 'Lax',
		)
	);

	wp_safe_redirect( home_url( '/panel/' ) );
	exit;
}, 1 );
