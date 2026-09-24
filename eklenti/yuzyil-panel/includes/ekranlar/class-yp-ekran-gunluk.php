<?php
defined( 'ABSPATH' ) || exit;

/**
 * İşlem günlüğü: kim, ne zaman, neyi ekledi/değiştirdi/tahsil etti/sildi.
 */
final class YP_Ekran_Gunluk extends YP_Ekran {

	public static function goster() {
		global $wpdb;
		$t      = YP_Cekirdek::tablo( 'log' );
		$bolumler = array( 'aday' => 'Aday', 'kasa' => 'Kasa', 'referans' => 'Referans', 'tanim' => 'Tanımlar', 'silinenler' => 'Silinenler' );
		$bolum  = YP_Guvenlik::secim( 'bolum', array_keys( $bolumler ), '', 'get' );
		$bas    = YP_Guvenlik::tarih( 'bas', 'get' );
		$bit    = YP_Guvenlik::tarih( 'bit', 'get' );
		$sayfa  = self::sayfa_no();
		$sb     = 50;
		$where  = array( '1=1' );
		$args   = array();
		if ( '' !== $bolum ) {
			$where[] = 'bolum = %s';
			$args[]  = $bolum;
		}
		if ( $bas ) {
			$where[] = 'zaman >= %s';
			$args[]  = $bas . ' 00:00:00';
		}
		if ( $bit ) {
			$where[] = 'zaman <= %s';
			$args[]  = $bit . ' 23:59:59';
		}
		$w      = implode( ' AND ', $where );
		$toplam = (int) $wpdb->get_var( $args ? $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE {$w}", $args ) : "SELECT COUNT(*) FROM {$t} WHERE {$w}" ); // phpcs:ignore
		$liste  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE {$w} ORDER BY zaman DESC, id DESC LIMIT %d OFFSET %d", array_merge( $args, array( $sb, ( $sayfa - 1 ) * $sb ) ) ) ); // phpcs:ignore

		$serit = function () {
			self::serit_grubu_ciz( 'Kayıtlar', array(
				array( 'Tümü', array( 'ekran' => 'gunluk' ), 'liste' ),
				array( 'Silinenler', array( 'ekran' => 'silinenler' ), 'cop', 'serit-kirmizi' ),
				array( 'Tanımlar', array( 'ekran' => 'tanimlar' ), 'ayar' ),
			) );
			self::serit_yon_grubu();
		};
		self::uyg_basla( 'İşlem Günlüğü', 'gunluk', 'kaydir', $serit, '<span class="vurgu"><b>' . esc_html( number_format( $toplam, 0, ',', '.' ) ) . '</b> kayıt</span>', 'ust-yok' );
		?>
		<form class="filtre kutu" method="get" action="<?php echo esc_url( YP_Cekirdek::panel_url() ); ?>">
			<input type="hidden" name="ekran" value="gunluk">
			<?php
			self::secim( 'bolum', 'Bölüm', $bolumler, $bolum, array( 'bos' => 'Tümü' ) );
			self::tarih_alani( 'bas', 'Başlangıç', $bas );
			self::tarih_alani( 'bit', 'Bitiş', $bit );
			?>
			<div class="filtre-dugmeler"><button type="submit" class="dugme ana">Listele</button></div>
		</form>
		<section class="kutu"><div class="tablo-kap"><table class="tablo">
			<thead><tr><th>Zaman</th><th>Kullanıcı</th><th>Bölüm</th><th>İşlem</th><th>Açıklama</th></tr></thead><tbody>
			<?php
			if ( ! $liste ) {
				self::bos_liste( 'Kayıt yok.', 5 );
			}
			foreach ( $liste as $l ) {
				$bolum_adi = isset( $bolumler[ $l->bolum ] ) ? $bolumler[ $l->bolum ] : $l->bolum;
				$aciklama  = 'aday' === $l->bolum && $l->kayit_id ? self::aday_linki( $l->kayit_id, $l->aciklama, 'gecmis' ) : esc_html( $l->aciklama );
				echo '<tr><td>' . esc_html( YP_Bicim::tarih_saat( $l->zaman ) ) . '</td><td>' . esc_html( $l->kullanici_adi ) . '</td><td>' . esc_html( $bolum_adi ) . '</td><td>' . esc_html( $l->islem ) . '</td><td class="yazi-kucuk">' . $aciklama . '</td></tr>'; // phpcs:ignore
			}
			?>
		</tbody></table></div>
		<?php self::sayfalama( $toplam, $sayfa, $sb, array_filter( array( 'ekran' => 'gunluk', 'bolum' => $bolum, 'bas' => YP_Bicim::tarih( $bas ), 'bit' => YP_Bicim::tarih( $bit ) ), 'strlen' ) ); ?>
		</section>
		<?php
		self::uyg_bitir();
	}
}
