<?php
defined( 'ABSPATH' ) || exit;

/**
 * Raporlar: aylık gelir-gider özeti, tahsilat raporu, borç/bakiye raporu ve Excel aktarımları.
 */
final class YP_Ekran_Raporlar extends YP_Ekran {

	private static function raporlar() {
		return array(
			'aylik'    => 'Aylık Gelir-Gider Özeti',
			'tahsilat' => 'Tahsilat Raporu',
			'borc'     => 'Borç / Bakiye Raporu',
		);
	}

	public static function goster() {
		$excel = YP_Guvenlik::secim( 'excel', array( 'adaylar', 'kasa', 'aylik', 'tahsilat', 'borc' ), '', 'get' );
		if ( '' !== $excel ) {
			self::excel( $excel );
		}
		$rapor = YP_Guvenlik::secim( 'rapor', array_keys( self::raporlar() ), 'aylik', 'get' );

		$excel_args = array_merge( self::donem_args(), array( 'ekran' => 'raporlar', 'excel' => $rapor ) );
		$serit      = function () use ( $excel_args ) {
			self::serit_grubu_ciz( 'Raporlar', array(
				array( 'Aylık Özet', array( 'ekran' => 'raporlar', 'rapor' => 'aylik' ), 'alacak' ),
				array( 'Tahsilat', array( 'ekran' => 'raporlar', 'rapor' => 'tahsilat' ), 'para' ),
				array( 'Borç / Bakiye', array( 'ekran' => 'raporlar', 'rapor' => 'borc' ), 'rapor' ),
			) );
			self::serit_grubu_ciz( 'Çıktı', array(
				array( 'Excel', $excel_args, 'rapor' ),
				array( 'Yazdır', '', 'yazdir', '', 'data-yazdir' ),
			) );
			self::serit_yon_grubu();
		};
		self::uyg_basla( 'Raporlar', 'raporlar', 'kaydir', $serit, '<span class="vurgu">' . esc_html( self::raporlar()[ $rapor ] ) . '</span>', 'ust-yok' );
		echo '<nav class="sekmeler">';
		foreach ( self::raporlar() as $k => $ad ) {
			echo '<a class="' . ( $k === $rapor ? 'aktif' : '' ) . '" href="' . esc_url( YP_Cekirdek::panel_url( array( 'ekran' => 'raporlar', 'rapor' => $k ) ) ) . '">' . esc_html( $ad ) . '</a>';
		}
		echo '</nav>';

		if ( 'aylik' === $rapor ) {
			self::aylik();
		} elseif ( 'tahsilat' === $rapor ) {
			self::tahsilat();
		} else {
			self::borc();
		}
		self::uyg_bitir();
	}

	private static function donem_args() {
		$args = array();
		foreach ( array( 'bas', 'bit', 'yil', 'rapor' ) as $k ) {
			$d = YP_Guvenlik::metin( $k, 'get', 20 );
			if ( '' !== $d ) {
				$args[ $k ] = $d;
			}
		}
		return $args;
	}

	private static function tarih_araligi( $varsayilan_gun = 30 ) {
		$bas = YP_Guvenlik::tarih( 'bas', 'get' );
		$bit = YP_Guvenlik::tarih( 'bit', 'get' );
		$bas = $bas ? $bas : YP_Bicim::gun_ekle( YP_Cekirdek::bugun(), -$varsayilan_gun );
		$bit = $bit ? $bit : YP_Cekirdek::bugun();
		return $bit < $bas ? array( $bit, $bas ) : array( $bas, $bit );
	}

	// ---- Aylık gelir-gider ------------------------------------------------

	/**
	 * Son 12 ay için tahsilat, gider ve net.
	 * KURAL: Aylık özet tek sorguda hesaplanır ve veri değişene kadar önbellekte tutulur.
	 */
	public static function aylik_veri( $ay_sayisi = 12 ) {
		global $wpdb;
		return YP_Cekirdek::onbellek( 'aylik_' . $ay_sayisi, 10 * MINUTE_IN_SECONDS, function () use ( $wpdb, $ay_sayisi ) {
			$h   = YP_Cekirdek::tablo( 'hareketler' );
			$a   = YP_Cekirdek::tablo( 'adaylar' );
			$i   = YP_Cekirdek::tablo( 'islemler' );
			$k   = YP_Hesap::KASA_KOSULU;
			$bas = YP_Bicim::ay_ekle( substr( YP_Cekirdek::bugun(), 0, 8 ) . '01', -( $ay_sayisi - 1 ) );

			$para = $wpdb->get_results( $wpdb->prepare(
				"SELECT SUBSTRING(h.odeme_tarihi, 1, 7) AS ay,
					COALESCE(SUM(CASE WHEN h.durum = 'ODENDI' AND h.kayit_turu IN ('ADAY','GELIR') THEN h.tutar ELSE 0 END), 0) AS gelir,
					COALESCE(SUM(CASE WHEN h.kayit_turu = 'GIDER' OR h.durum = 'IADE' THEN h.tutar ELSE 0 END), 0) AS gider
				FROM {$h} h WHERE {$k} AND h.odeme_tarihi >= %s GROUP BY SUBSTRING(h.odeme_tarihi, 1, 7)", // phpcs:ignore
				$bas
			) );
			$islem = $wpdb->get_results( $wpdb->prepare(
				"SELECT SUBSTRING(i.islem_tarihi, 1, 7) AS ay, COUNT(*) AS adet
				FROM {$i} i INNER JOIN {$a} a ON a.id = i.aday_id AND a.silindi = 0
				WHERE i.silindi = 0 AND i.durum <> 'iptal' AND i.islem_tarihi >= %s GROUP BY SUBSTRING(i.islem_tarihi, 1, 7)",
				$bas
			) );
			$aylar = array();
			for ( $n = 0; $n < $ay_sayisi; $n++ ) {
				$ay           = substr( YP_Bicim::ay_ekle( $bas, $n ), 0, 7 );
				$aylar[ $ay ] = array( 'ay' => $ay, 'gelir' => 0.0, 'gider' => 0.0, 'islem' => 0 );
			}
			foreach ( $para as $p ) {
				if ( isset( $aylar[ $p->ay ] ) ) {
					$aylar[ $p->ay ]['gelir'] = (float) $p->gelir;
					$aylar[ $p->ay ]['gider'] = (float) $p->gider;
				}
			}
			foreach ( $islem as $x ) {
				if ( isset( $aylar[ $x->ay ] ) ) {
					$aylar[ $x->ay ]['islem'] = (int) $x->adet;
				}
			}
			return array_values( $aylar );
		} );
	}

	private static function aylik() {
		$aylar = self::aylik_veri( 12 );
		$en    = 0.0;
		foreach ( $aylar as $ay ) {
			$en = max( $en, $ay['gelir'], $ay['gider'] );
		}
		$t_gelir = array_sum( wp_list_pluck( $aylar, 'gelir' ) );
		$t_gider = array_sum( wp_list_pluck( $aylar, 'gider' ) );
		?>
		<section class="kartlar">
			<div class="kart"><span>12 aylık tahsilat</span><strong class="yesil-yazi"><?php echo esc_html( YP_Bicim::tl( $t_gelir ) ); ?></strong></div>
			<div class="kart"><span>12 aylık gider</span><strong class="kirmizi"><?php echo esc_html( YP_Bicim::tl( $t_gider ) ); ?></strong></div>
			<div class="kart"><span>Net</span><strong><?php echo esc_html( YP_Bicim::tl( $t_gelir - $t_gider ) ); ?></strong></div>
			<div class="kart"><span>Aylık ortalama net</span><strong><?php echo esc_html( YP_Bicim::tl( ( $t_gelir - $t_gider ) / max( 1, count( $aylar ) ) ) ); ?></strong></div>
		</section>

		<section class="kutu">
			<h2>Aylara göre gelir ve gider</h2>
			<div class="grafik" role="img" aria-label="Aylık gelir gider grafiği">
				<?php foreach ( $aylar as $ay ) : ?>
					<div class="grafik-ay">
						<div class="cubuklar">
							<span class="cubuk gelir" style="height: <?php echo esc_attr( $en > 0 ? round( $ay['gelir'] / $en * 100 ) : 0 ); ?>%" title="<?php echo esc_attr( 'Tahsilat: ' . YP_Bicim::tl( $ay['gelir'] ) ); ?>"></span>
							<span class="cubuk gider" style="height: <?php echo esc_attr( $en > 0 ? round( $ay['gider'] / $en * 100 ) : 0 ); ?>%" title="<?php echo esc_attr( 'Gider: ' . YP_Bicim::tl( $ay['gider'] ) ); ?>"></span>
						</div>
						<div class="etiket"><?php echo esc_html( mb_substr( YP_Bicim::ay_adi( (int) substr( $ay['ay'], 5, 2 ) ), 0, 3, 'UTF-8' ) ); ?><br><span class="soluk"><?php echo esc_html( substr( $ay['ay'], 2, 2 ) ); ?></span></div>
					</div>
				<?php endforeach; ?>
			</div>
			<p class="grafik-gosterge"><span class="kutucuk gelir"></span> Tahsilat <span class="kutucuk gider"></span> Gider</p>

			<div class="tablo-kap"><table class="tablo">
				<thead><tr><th>Ay</th><th class="sag">İşlem</th><th class="sag">Tahsilat</th><th class="sag">Gider</th><th class="sag">Net</th></tr></thead>
				<tbody>
				<?php foreach ( array_reverse( $aylar ) as $ay ) : ?>
					<tr>
						<td><?php echo esc_html( YP_Bicim::ay_adi( (int) substr( $ay['ay'], 5, 2 ) ) . ' ' . substr( $ay['ay'], 0, 4 ) ); ?></td>
						<td class="sag"><?php echo (int) $ay['islem']; ?></td>
						<td class="sag yesil-yazi"><?php echo esc_html( YP_Bicim::tl( $ay['gelir'] ) ); ?></td>
						<td class="sag kirmizi"><?php echo esc_html( YP_Bicim::tl( $ay['gider'] ) ); ?></td>
						<td class="sag"><strong><?php echo esc_html( YP_Bicim::tl( $ay['gelir'] - $ay['gider'] ) ); ?></strong></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
				<tfoot><tr><th>Toplam</th><th class="sag"><?php echo (int) array_sum( wp_list_pluck( $aylar, 'islem' ) ); ?></th><th class="sag"><?php echo esc_html( YP_Bicim::tl( $t_gelir ) ); ?></th><th class="sag"><?php echo esc_html( YP_Bicim::tl( $t_gider ) ); ?></th><th class="sag"><?php echo esc_html( YP_Bicim::tl( $t_gelir - $t_gider ) ); ?></th></tr></tfoot>
			</table></div>
		</section>
		<?php
	}

	// ---- Tahsilat raporu --------------------------------------------------

	public static function tahsilat_veri( $bas, $bit ) {
		global $wpdb;
		$h = YP_Cekirdek::tablo( 'hareketler' );
		$k = YP_Hesap::KASA_KOSULU;
		return array(
			'turlere_gore' => $wpdb->get_results( $wpdb->prepare(
				"SELECT COALESCE(h.borc_tipi, 'Diğer') AS kalem, COUNT(*) AS adet, SUM(h.tutar) AS toplam FROM {$h} h
				WHERE {$k} AND h.durum = 'ODENDI' AND h.kayit_turu IN ('ADAY','GELIR') AND h.odeme_tarihi BETWEEN %s AND %s
				GROUP BY COALESCE(h.borc_tipi, 'Diğer') ORDER BY toplam DESC", // phpcs:ignore
				$bas,
				$bit
			) ),
			'odeme_turu'   => YP_Hesap::gelir_dagilimi( $bas, $bit ),
			'gider'        => YP_Hesap::gider_dagilimi( $bas, $bit ),
		);
	}

	private static function tahsilat() {
		list( $bas, $bit ) = self::tarih_araligi();
		$veri  = self::tahsilat_veri( $bas, $bit );
		$odeme = YP_Veri::odeme_turleri();
		$toplam = 0.0;
		foreach ( $veri['turlere_gore'] as $s ) {
			$toplam += (float) $s->toplam;
		}
		$gider_toplam = 0.0;
		foreach ( $veri['gider'] as $s ) {
			$gider_toplam += (float) $s->toplam;
		}
		self::tarih_formu( $bas, $bit, 'tahsilat' );
		?>
		<section class="kartlar">
			<div class="kart"><span>Tahsilat</span><strong class="yesil-yazi"><?php echo esc_html( YP_Bicim::tl( $toplam ) ); ?></strong></div>
			<div class="kart"><span>Gider</span><strong class="kirmizi"><?php echo esc_html( YP_Bicim::tl( $gider_toplam ) ); ?></strong></div>
			<div class="kart"><span>Net</span><strong><?php echo esc_html( YP_Bicim::tl( $toplam - $gider_toplam ) ); ?></strong></div>
		</section>
		<div class="iki-sutun">
			<section class="kutu">
				<h2>İşlem türüne göre tahsilat</h2>
				<table class="tablo"><thead><tr><th>Kalem</th><th class="sag">Adet</th><th class="sag">Tutar</th></tr></thead><tbody>
				<?php
				if ( ! $veri['turlere_gore'] ) {
					self::bos_liste( 'Kayıt yok.', 3 );
				}
				foreach ( $veri['turlere_gore'] as $s ) {
					echo '<tr><td>' . esc_html( $s->kalem ) . '</td><td class="sag">' . (int) $s->adet . '</td>' . self::tutar_hucre( $s->toplam ) . '</tr>'; // phpcs:ignore
				}
				?>
				</tbody></table>
			</section>
			<section class="kutu">
				<h2>Ödeme türüne göre</h2>
				<table class="tablo"><thead><tr><th>Ödeme türü</th><th class="sag">Adet</th><th class="sag">Tutar</th></tr></thead><tbody>
				<?php
				if ( ! $veri['odeme_turu'] ) {
					self::bos_liste( 'Kayıt yok.', 3 );
				}
				foreach ( $veri['odeme_turu'] as $s ) {
					echo '<tr><td>' . esc_html( isset( $odeme[ $s->odeme_turu ] ) ? $odeme[ $s->odeme_turu ] : $s->odeme_turu ) . '</td><td class="sag">' . (int) $s->adet . '</td>' . self::tutar_hucre( $s->toplam ) . '</tr>'; // phpcs:ignore
				}
				?>
				</tbody></table>
			</section>
		</div>
		<section class="kutu">
			<h2>Gider kalemleri</h2>
			<table class="tablo"><thead><tr><th>Kalem</th><th class="sag">Adet</th><th class="sag">Tutar</th></tr></thead><tbody>
			<?php
			if ( ! $veri['gider'] ) {
				self::bos_liste( 'Kayıt yok.', 3 );
			}
			foreach ( $veri['gider'] as $s ) {
				echo '<tr><td>' . esc_html( $s->kalem ) . '</td><td class="sag">' . (int) $s->adet . '</td>' . self::tutar_hucre( $s->toplam ) . '</tr>'; // phpcs:ignore
			}
			?>
			</tbody></table>
		</section>
		<?php
	}

	// ---- Borç raporu ------------------------------------------------------

	public static function borc_veri( $limit = 500 ) {
		global $wpdb;
		$a   = YP_Cekirdek::tablo( 'adaylar' );
		$alt = YP_Hesap::bakiye_alt_sorgusu();
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT a.id, a.aday_no, a.adi, a.soyadi, a.gsm_1, a.kayit_tarihi, b.borc, b.odenen, b.geciken,
				(b.borc - b.odenen) AS kalan,
				(SELECT COALESCE(NULLIF(r.unvan, ''), r.ad_soyad) FROM " . YP_Cekirdek::tablo( 'referanslar' ) . " r WHERE r.id = a.referans_id) AS referans
			FROM ({$alt}) b INNER JOIN {$a} a ON a.id = b.aday_id AND a.silindi = 0
			WHERE (b.borc - b.odenen) > 0 ORDER BY (b.borc - b.odenen) DESC LIMIT %d", // phpcs:ignore
			$limit
		) );
	}

	private static function borc() {
		// KURAL: Ekranda ilk 150 borçlu gösterilir; genel toplamlar tüm borçlular üzerinden hesaplanır ve veri değişene kadar önbellekte tutulur.
		$veri = YP_Cekirdek::onbellek( 'borc_raporu', 10 * MINUTE_IN_SECONDS, function () {
			return array(
				'satirlar' => self::borc_veri( 150 ),
				'genel'    => YP_Hesap::genel_alacak(),
				'adet'     => self::borclu_adedi(),
			);
		} );
		$satirlar = $veri['satirlar'];
		$toplam   = $veri['genel']['kalan'];
		$geciken  = $veri['genel']['geciken'];
		$adet     = $veri['adet'];
		?>
		<section class="kartlar">
			<div class="kart"><span>Borçlu aday</span><strong><?php echo (int) $adet; ?></strong></div>
			<div class="kart"><span>Toplam kalan borç</span><strong class="kirmizi"><?php echo esc_html( YP_Bicim::tl( $toplam ) ); ?></strong></div>
			<div class="kart kart-uyari"><span>Vadesi geçmiş</span><strong><?php echo esc_html( YP_Bicim::tl( $geciken ) ); ?></strong></div>
		</section>
		<section class="kutu">
			<h2>Borç / bakiye raporu</h2>
			<?php if ( count( $satirlar ) >= 150 ) : ?>
				<p class="uyari uyari-uyari">Ekranda en yüksek borçlu ilk 150 aday gösteriliyor. Tamamı için "Excel'e aktar" düğmesini kullanın.</p>
			<?php endif; ?>
			<div class="tablo-kap"><table class="tablo yogun">
				<thead><tr><th>Aday No</th><th>Ad Soyad</th><th>Telefon</th><th>Referans</th><th>Kayıt</th><th class="sag">Borç</th><th class="sag">Ödenen</th><th class="sag">Kalan</th><th class="sag">Vadesi geçmiş</th></tr></thead>
				<tbody>
				<?php
				if ( ! $satirlar ) {
					self::bos_liste( 'Borcu olan aday yok.', 9 );
				}
				foreach ( $satirlar as $s ) :
					?>
					<tr class="tiklanir" data-git="<?php echo esc_url( YP_Cekirdek::panel_url( array( 'ekran' => 'aday', 'id' => (int) $s->id, 'sekme' => 'odeme' ) ) ); ?>">
						<td><?php echo (int) $s->aday_no; ?></td>
						<td class="ad-sutun"><?php echo esc_html( $s->adi . ' ' . $s->soyadi ); ?></td>
						<td><?php echo esc_html( YP_Bicim::telefon( $s->gsm_1 ) ); ?></td>
						<td><?php echo esc_html( $s->referans ? $s->referans : 'BİREYSEL KAYIT' ); ?></td>
						<td><?php echo esc_html( YP_Bicim::tarih( $s->kayit_tarihi ) ); ?></td>
						<?php
						echo self::tutar_hucre( $s->borc ); // phpcs:ignore
						echo self::tutar_hucre( $s->odenen ); // phpcs:ignore
						?>
						<td class="sag kirmizi"><strong><?php echo esc_html( YP_Bicim::tl( $s->kalan ) ); ?></strong></td>
						<td class="sag"><?php echo $s->geciken > 0 ? esc_html( YP_Bicim::tl( $s->geciken ) ) : '—'; ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
				<tfoot><tr><th colspan="7">Toplam</th><th class="sag"><?php echo esc_html( YP_Bicim::tl( $toplam ) ); ?></th><th class="sag"><?php echo esc_html( YP_Bicim::tl( $geciken ) ); ?></th></tr></tfoot>
			</table></div>
		</section>
		<?php
	}

	public static function borclu_adedi() {
		global $wpdb;
		$a   = YP_Cekirdek::tablo( 'adaylar' );
		$alt = YP_Hesap::bakiye_alt_sorgusu();
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM ({$alt}) b INNER JOIN {$a} a ON a.id = b.aday_id AND a.silindi = 0 WHERE (b.borc - b.odenen) > 0" ); // phpcs:ignore
	}

	private static function tarih_formu( $bas, $bit, $rapor ) {
		?>
		<form class="filtre kutu" method="get" action="<?php echo esc_url( YP_Cekirdek::panel_url() ); ?>">
			<input type="hidden" name="ekran" value="raporlar">
			<input type="hidden" name="rapor" value="<?php echo esc_attr( $rapor ); ?>">
			<?php
			self::tarih_alani( 'bas', 'Başlangıç', $bas );
			self::tarih_alani( 'bit', 'Bitiş', $bit );
			?>
			<div class="filtre-dugmeler"><button type="submit" class="dugme ana">Göster</button></div>
		</form>
		<?php
	}

	// ---- Excel ------------------------------------------------------------

	private static function excel( $tur ) {
		if ( 'adaylar' === $tur ) {
			self::excel_adaylar();
		} elseif ( 'kasa' === $tur ) {
			self::excel_kasa();
		} elseif ( 'aylik' === $tur ) {
			$satirlar = array();
			foreach ( array_reverse( self::aylik_veri( 12 ) ) as $ay ) {
				$satirlar[] = array(
					YP_Bicim::ay_adi( (int) substr( $ay['ay'], 5, 2 ) ) . ' ' . substr( $ay['ay'], 0, 4 ),
					$ay['islem'],
					$ay['gelir'],
					$ay['gider'],
					$ay['gelir'] - $ay['gider'],
				);
			}
			YP_Excel::indir( 'aylik-ozet', array( 'Ay', 'İşlem', 'Tahsilat', 'Gider', 'Net' ), $satirlar, array( 'metin', 'sayi', 'tutar', 'tutar', 'tutar' ), 'Aylık gelir-gider özeti' );
		} elseif ( 'tahsilat' === $tur ) {
			list( $bas, $bit ) = self::tarih_araligi();
			$veri     = self::tahsilat_veri( $bas, $bit );
			$odeme    = YP_Veri::odeme_turleri();
			$satirlar = array();
			foreach ( $veri['turlere_gore'] as $s ) {
				$satirlar[] = array( 'Tahsilat', $s->kalem, (int) $s->adet, (float) $s->toplam );
			}
			foreach ( $veri['odeme_turu'] as $s ) {
				$satirlar[] = array( 'Ödeme türü', isset( $odeme[ $s->odeme_turu ] ) ? $odeme[ $s->odeme_turu ] : $s->odeme_turu, (int) $s->adet, (float) $s->toplam );
			}
			foreach ( $veri['gider'] as $s ) {
				$satirlar[] = array( 'Gider', $s->kalem, (int) $s->adet, (float) $s->toplam );
			}
			YP_Excel::indir( 'tahsilat-raporu', array( 'Bölüm', 'Kalem', 'Adet', 'Tutar' ), $satirlar, array( 'metin', 'metin', 'sayi', 'tutar' ), 'Tahsilat raporu ' . YP_Bicim::tarih( $bas ) . ' – ' . YP_Bicim::tarih( $bit ) );
		} else {
			$satirlar = array();
			foreach ( self::borc_veri( 5000 ) as $s ) {
				$satirlar[] = array(
					(int) $s->aday_no,
					$s->adi . ' ' . $s->soyadi,
					YP_Bicim::telefon( $s->gsm_1 ),
					$s->referans ? $s->referans : 'BİREYSEL KAYIT',
					YP_Bicim::tarih( $s->kayit_tarihi ),
					(float) $s->borc,
					(float) $s->odenen,
					(float) $s->kalan,
					(float) $s->geciken,
				);
			}
			YP_Excel::indir(
				'borc-raporu',
				array( 'Aday No', 'Ad Soyad', 'Telefon', 'Referans', 'Kayıt', 'Borç', 'Ödenen', 'Kalan', 'Vadesi geçmiş' ),
				$satirlar,
				array( 'sayi', 'metin', 'metin', 'metin', 'tarih', 'tutar', 'tutar', 'tutar', 'tutar' ),
				'Borç / bakiye raporu'
			);
		}
	}

	// KURAL: Excel aktarımı ekrandaki filtrelerin aynısını kullanır — listede ne görüyorsanız o iner (en fazla 5000 satır).
	private static function excel_adaylar() {
		global $wpdb;
		$f = YP_Ekran_Adaylar::filtreler();
		list( $parca, $args ) = YP_Ekran_Adaylar::sorgu_parcasi( $f );
		$sql = "SELECT a.id, a.aday_no, a.adi, a.soyadi, a.tc_no, a.gsm_1, a.cinsiyet, a.kayit_tarihi, a.referans_id {$parca}"
			. YP_Ekran_Adaylar::siralama_sql( $f ) . ' LIMIT 5000';
		$adaylar  = $wpdb->get_results( $args ? $wpdb->prepare( $sql, $args ) : $sql ); // phpcs:ignore
		$idler    = array_map( 'intval', wp_list_pluck( $adaylar, 'id' ) );
		$bakiye   = YP_Hesap::aday_bakiyeleri( $idler );
		$son      = YP_Ekran_Adaylar::son_islemler( $idler );
		$referans = YP_Ekran_Adaylar::referans_adlari( array_filter( array_map( 'intval', wp_list_pluck( $adaylar, 'referans_id' ) ) ) );
		$durumlar = YP_Veri::islem_durumlari();

		$satirlar = array();
		foreach ( $adaylar as $ad ) {
			$b  = isset( $bakiye[ (int) $ad->id ] ) ? $bakiye[ (int) $ad->id ] : array( 'borc' => 0, 'odenen' => 0, 'kalan' => 0 );
			$si = isset( $son[ (int) $ad->id ] ) ? $son[ (int) $ad->id ] : null;
			$satirlar[] = array(
				(int) $ad->aday_no,
				YP_Bicim::tarih( $ad->kayit_tarihi ),
				$ad->adi,
				$ad->soyadi,
				(string) $ad->tc_no,
				YP_Bicim::telefon( $ad->gsm_1 ),
				(string) $ad->cinsiyet,
				$si && $si->tur_adi ? $si->tur_adi : '',
				$si && isset( $durumlar[ $si->durum ] ) ? $durumlar[ $si->durum ] : '',
				isset( $referans[ (int) $ad->referans_id ] ) ? $referans[ (int) $ad->referans_id ] : 'BİREYSEL KAYIT',
				$b['borc'],
				$b['odenen'],
				$b['kalan'],
			);
		}
		YP_Excel::indir(
			'aday-listesi',
			array( 'Aday No', 'Kayıt Tarihi', 'Adı', 'Soyadı', 'TC', 'Telefon', 'Cinsiyet', 'Son İşlem', 'Durum', 'Referans', 'Borç', 'Ödenen', 'Kalan' ),
			$satirlar,
			array( 'sayi', 'tarih', 'metin', 'metin', 'metin', 'metin', 'metin', 'metin', 'metin', 'metin', 'tutar', 'tutar', 'tutar' ),
			'Aday listesi'
		);
	}

	private static function excel_kasa() {
		list( $bas, $bit ) = YP_Ekran_Kasa::donem();
		$hesap_id = YP_Guvenlik::tamsayi( 'hesap', 'get' );
		$defter   = YP_Hesap::defter( $bas, $bit, $hesap_id, YP_Guvenlik::secim( 'sekme', array( 'tumu', 'gelir', 'gider', 'adayadi' ), 'tumu', 'get' ) );
		$hesaplar = YP_Veri::hesap_adlari();
		$odeme    = YP_Veri::odeme_turleri();
		$satirlar = array();
		foreach ( $defter['satirlar'] as $s ) {
			$satirlar[] = array(
				YP_Ekran_Kasa::tur_etiketi( $s ),
				YP_Bicim::tarih( $s->odeme_tarihi ),
				$s->kalem_adi ? $s->kalem_adi : $s->borc_tipi,
				isset( $hesaplar[ (int) $s->hesap_id ] ) ? $hesaplar[ (int) $s->hesap_id ] : '',
				isset( $odeme[ $s->odeme_turu ] ) ? $odeme[ $s->odeme_turu ] : '',
				YP_Ekran_Kasa::aciklama_metni( $s, $hesaplar ),
				(float) $s->giris,
				(float) $s->cikis,
			);
		}
		YP_Excel::indir(
			'kasa-hareketleri',
			array( 'İşlem Tipi', 'Tarih', 'İşlem Türü', 'Hesap', 'Ödeme', 'Açıklama', 'Giriş', 'Çıkış' ),
			$satirlar,
			array( 'metin', 'tarih', 'metin', 'metin', 'metin', 'metin', 'tutar', 'tutar' ),
			'Kasa hareketleri ' . YP_Bicim::tarih( $bas ) . ' – ' . YP_Bicim::tarih( $bit )
		);
	}
}
