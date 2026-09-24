<?php
// KURAL: Yerleşik PHP sunucusu güzel adresleri (/panel/) kendi başına çözemez — WordPress'e devredilir.
$yol  = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
$kok  = __DIR__ . '/site';
$tam  = realpath( $kok . $yol );

if ( $tam && strpos( $tam, realpath( $kok ) ) === 0 && is_file( $tam ) ) {
	return false; // gerçek dosya: sunucu kendisi versin
}
if ( $yol !== '/' && is_dir( $kok . $yol ) && is_file( rtrim( $kok . $yol, '/' ) . '/index.php' ) ) {
	require rtrim( $kok . $yol, '/' ) . '/index.php';
	return true;
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
require $kok . '/index.php';
return true;
