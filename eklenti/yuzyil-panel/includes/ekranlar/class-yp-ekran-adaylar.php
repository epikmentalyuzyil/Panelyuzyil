<?php
defined( 'ABSPATH' ) || exit;

/**
 * Aday listesi: yoğun tablo, solda seçili adayın fotoğrafı, altta kayıt sayacı.
 * KURAL: Liste tek sayfada 25 kayıt gösterir ve bakiyeler tek sorguda toplanır — 10.000 kayıtta da hızlı açılır.
 */
final class YP_Ekran_Adaylar extends YP_Ekran {

	// KURAL: Sıralama yalnızca bu sütunlarla yapılır — kullanıcıdan gelen sütun adı sorguya doğrudan girmez.
	private static function siralamalar() {
		return array(
			'no'    => 'a.aday_no',
			'kayit' => 'a.kayit_tarihi',
			'ad'    => 'a.adi %s, a.soyadi',
			'soyad' => 'a.soyadi %s, a.adi',
			'son'   => 's.son_tarih',
			'kalan' => '(COALESCE(b.borc,0) - COALESCE(b.odenen,0))',
		);
	}

	public static function filtreler() {
		return array(
			'q'           => YP_Guvenlik::metin( 'q', 'get', 100 ),
			'referans'    => YP_Guvenlik::tamsayi( 'referans', 'get' ),
			'islem_turu'  => YP_Guvenlik::tamsayi( 'islem_turu', 'get' ),
			'islem_durum' => YP_Guvenlik::secim( 'islem_durum', array_keys( YP_Veri::islem_durumlari() ), '', 'get' ),
			'odeme'       => YP_Guvenlik::secim( 'odeme', array( 'borclu', 'geciken', 'odendi' ), '', 'get' ),
			'bas'         => YP_Guvenlik::tarih( 'bas', 'get' ),
			'bit'         => YP_Guvenlik::tarih( 'bit', 'get' ),
			'arsiv'       => YP_Guvenlik::secim( 'arsiv', array( '0', '1', 'tum' ), '0', 'get' ),
			'ozel_kod'    => YP_Guvenlik::tamsayi( 'ozel_kod', 'get' ),
			'sirala'      => YP_Guvenlik::secim( 'sirala', array_keys( self::siralamalar() ), 'no', 'get' ),
			'yon'         => YP_Guvenlik::secim( 'yon', array( 'asc', 'desc' ), 'desc', 'get' ),
		);
	}

	/**
	 * Filtrelere göre FROM/WHERE parçası ve argümanlar (liste ve Excel ortak kullanır).
	 */
	// KURAL: Bakiye ve son işlem birleşimleri ağırdır; yalnızca o alana göre süzme/sıralama yapılırsa sorguya eklenir.
	public static function bakiye_gerekli( array $f ) {
		return '' !== $f['odeme'] || 'kalan' === $f['sirala'];
	}

	public static function son_tarih_gerekli( array $f ) {
		return 'son' === $f['sirala'];
	}

	public static function sorgu_parcasi( array $f ) {
		global $wpdb;
		$a   = YP_Cekirdek::tablo( 'adaylar' );
		$i   = YP_Cekirdek::tablo( 'islemler' );

		$from = "FROM {$a} a";
		if ( self::bakiye_gerekli( $f ) ) {
			$from .= ' LEFT JOIN (' . YP_Hesap::bakiye_alt_sorgusu() . ') b ON b.aday_id = a.id';
		}
		if ( self::son_tarih_gerekli( $f ) ) {
			$from .= " LEFT JOIN (SELECT aday_id, MAX(islem_tarihi) AS son_tarih FROM {$i} WHERE silindi = 0 GROUP BY aday_id) s ON s.aday_id = a.id";
		}
		$where = array( 'a.silindi = 0' );
		$args  = array();

		if ( 'tum' !== $f['arsiv'] ) {
			$where[] = 'a.arsiv = %d';
			$args[]  = (int) $f['arsiv'];
		}
		// KURAL: Arama her kelimeyi ayrı arar ve Türkçe harf farkını yok sayar — "sukru yil" "ŞÜKRÜ YILDIZ"ı bulur.
		if ( '' !== $f['q'] ) {
			foreach ( array_slice( explode( ' ', YP_Bicim::katla( $f['q'] ) ), 0, 5 ) as $kelime ) {
				if ( '' === $kelime ) {
					continue;
				}
				// KURAL: Telefon aramasında baştaki 0 atılır — kayıtlı numaralar 0'sız saklanır.
				if ( preg_match( '/^0\d{3,}$/', $kelime ) ) {
					$kelime = substr( $kelime, 1 );
				}
				$where[] = 'a.arama_metni LIKE %s';
				$args[]  = '%' . $wpdb->esc_like( $kelime ) . '%';
			}
		}
		if ( $f['referans'] ) {
			$where[] = 'a.referans_id = %d';
			$args[]  = $f['referans'];
		}
		if ( $f['ozel_kod'] ) {
			$where[] = '(a.ozel_kod1_id = %d OR a.ozel_kod2_id = %d)';
			$args[]  = $f['ozel_kod'];
			$args[]  = $f['ozel_kod'];
		}
		if ( $f['islem_turu'] || '' !== $f['islem_durum'] ) {
			$alt_where = array( 'ix.aday_id = a.id', 'ix.silindi = 0' );
			if ( $f['islem_turu'] ) {
				$alt_where[] = 'ix.islem_turu_id = %d';
				$args[]      = $f['islem_turu'];
			}
			if ( '' !== $f['islem_durum'] ) {
				$alt_where[] = 'ix.durum = %s';
				$args[]      = $f['islem_durum'];
			}
			$where[] = "EXISTS (SELECT 1 FROM {$i} ix WHERE " . implode( ' AND ', $alt_where ) . ')';
		}
		if ( 'borclu' === $f['odeme'] ) {
			$where[] = '(COALESCE(b.borc,0) - COALESCE(b.odenen,0)) > 0';
		} elseif ( 'geciken' === $f['odeme'] ) {
			$where[] = 'COALESCE(b.geciken,0) > 0';
		} elseif ( 'odendi' === $f['odeme'] ) {
			$where[] = 'COALESCE(b.borc,0) > 0 AND (COALESCE(b.borc,0) - COALESCE(b.odenen,0)) <= 0';
		}
		if ( $f['bas'] ) {
			$where[] = 'a.kayit_tarihi >= %s';
			$args[]  = $f['bas'];
		}
		if ( $f['bit'] ) {
			$where[] = 'a.kayit_tarihi <= %s';
			$args[]  = $f['bit'];
		}
		return array( $from . ' WHERE ' . implode( ' AND ', $where ), $args );
	}

	public static function siralama_sql( array $f ) {
		$yon   = 'asc' === $f['yon'] ? 'ASC' : 'DESC';
		$ifade = self::siralamalar()[ $f['sirala'] ];
		$ifade = false !== strpos( $ifade, '%s' ) ? sprintf( $ifade, $yon ) : $ifade;
		return ' ORDER BY ' . $ifade . ' ' . $yon . ', a.id DESC';
	}

	public static function link_args( array $f ) {
		return array_filter( array(
			'ekran'       => 'adaylar',
			'q'           => $f['q'],
			'referans'    => $f['referans'] ? $f['referans'] : '',
			'islem_turu'  => $f['islem_turu'] ? $f['islem_turu'] : '',
			'islem_durum' => $f['islem_durum'],
			'odeme'       => $f['odeme'],
			'bas'         => YP_Bicim::tarih( $f['bas'] ),
			'bit'         => YP_Bicim::tarih( $f['bit'] ),
			'arsiv'       => '0' === $f['arsiv'] ? '' : $f['arsiv'],
			'ozel_kod'    => $f['ozel_kod'] ? $f['ozel_kod'] : '',
			'sirala'      => $f['sirala'],
			'yon'         => $f['yon'],
		), 'strlen' );
	}

	public static function goster() {
		global $wpdb;
		$f          = self::filtreler();
		$sayfa      = self::sayfa_no();
		$sayfa_basi = self::sayfa_basi();
		$t          = YP_Cekirdek::tablo( 'tanimlar' );
		$r          = YP_Cekirdek::tablo( 'referanslar' );

		list( $parca, $args ) = self::sorgu_parcasi( $f );
		$toplam = (int) $wpdb->get_var( $args ? $wpdb->prepare( "SELECT COUNT(*) {$parca}", $args ) : "SELECT COUNT(*) {$parca}" ); // phpcs:ignore
		$sayfa  = min( $sayfa, max( 1, (int) ceil( $toplam / $sayfa_basi ) ) );

		// KURAL: Sayfadaki 25 aday tek sorguyla; bakiye, son işlem ve referans adları yalnızca bu 25 kayıt için üç ek sorguyla gelir.
		$sql = "SELECT a.id, a.aday_no, a.adi, a.soyadi, a.tc_no, a.gsm_1, a.cinsiyet, a.sari_not, a.foto, a.foto_kucuk, a.kayit_tarihi, a.arsiv, a.referans_id
			{$parca}" . self::siralama_sql( $f ) . ' LIMIT %d OFFSET %d';
		$adaylar = $wpdb->get_results( $wpdb->prepare( $sql, array_merge( $args, array( $sayfa_basi, ( $sayfa - 1 ) * $sayfa_basi ) ) ) ); // phpcs:ignore

		$idler    = array_map( 'intval', wp_list_pluck( $adaylar, 'id' ) );
		$bakiye   = YP_Hesap::aday_bakiyeleri( $idler );
		$son      = self::son_islemler( $idler );
		$referans = self::referans_adlari( array_filter( array_map( 'intval', wp_list_pluck( $adaylar, 'referans_id' ) ) ) );

		$link_args = self::link_args( $f );

		// KURAL: Liste ekranı da kart ekranıyla aynı düzendedir — üstte şerit, altında koyu ad şeridi.
		$excel_args = array_merge( $link_args, array( 'ekran' => 'raporlar', 'excel' => 'adaylar' ) );
		$serit      = function () use ( $f, $excel_args ) {
			self::serit_grubu_ciz( 'Aday', array(
				array( 'Yeni Aday', array( 'ekran' => 'aday', 'yeni' => 1 ), 'aday-ekle', 'serit-yesil serit-birincil' ),
				array( 'Tümü', array( 'ekran' => 'adaylar' ), 'liste' ),
			) );
			self::serit_grubu_ciz( 'Hazır Filtreler', array(
				array( 'Borçlular', array( 'ekran' => 'adaylar', 'odeme' => 'borclu' ), 'alacak' ),
				array( 'Gecikenler', array( 'ekran' => 'adaylar', 'odeme' => 'geciken' ), 'uyari', 'serit-kirmizi' ),
				array( 'Ödemesi Biten', array( 'ekran' => 'adaylar', 'odeme' => 'odendi' ), 'onay' ),
				array( 'Arşiv', array( 'ekran' => 'adaylar', 'arsiv' => '1' ), 'kilit' ),
			) );
			self::serit_grubu_ciz( 'Liste', array(
				array( 'Filtre', '#suzgecler', 'arama', 'serit-birincil' ),
				array( 'Excel', $excel_args, 'rapor' ),
				array( 'Takip Listeleri', array( 'ekran' => 'takip' ), 'saat' ),
				// KURAL: Sütunlar sürüklenerek karışırsa tek tıkla varsayılan sıraya dönülür.
				array( 'Sütun Sırası', '', 'geri', '', 'data-sutun-sifirla title="Sütun sırasını varsayılana döndür"' ),
			) );
			self::serit_yon_grubu( 'adaylar' );
			echo '<div class="serit-grup serit-kapat"><div class="serit-dugmeler">';
			self::serit_form_dugmesi( 'Kilitle', 'kilit_kapat', array(), 'kilit' );
			echo '</div><div class="serit-baslik">Kapat</div></div>';
		};
		// KURAL: Liste ekranında lacivert üst çubuk ve koyu ad şeridi yoktur; tek ince araç satırı vardır — satırlar için en çok yer kalır.
		self::uyg_basla( 'Adaylar', 'adaylar', 'sabit', $serit, false, 'ust-yok liste-ekrani' );
		self::filtre_formu( $f, $link_args, $toplam );
		?>
		<div class="liste-govde">
			<aside class="foto-panel" data-foto-panel>
				<div class="cerceve" data-foto-cerceve><span>Satıra gelince<br>fotoğraf burada görünür</span></div>
				<div class="ad" data-foto-ad></div>
				<div class="ayrinti" data-foto-ayrinti></div>
			</aside>
			<div class="kutu">
				<div class="tablo-kap">
				<table class="tablo yogun aday-listesi" data-sutun-tasi="adaylar">
					<thead><tr>
						<?php
						// KURAL: Başlıklar sürüklenerek yer değiştirebilir; sıra tarayıcıda saklanır — herkes kendi düzenini kurar.
						self::siralama_basligi( 'No', 'no', $f, $link_args, 'sag sutun-no' );
						self::siralama_basligi( 'Tarih', 'kayit', $f, $link_args, 'sutun-tarih' );
						?>
						<th class="sutun-tc" data-sutun="tc">TC No</th>
						<?php
						self::siralama_basligi( 'Adı', 'ad', $f, $link_args );
						self::siralama_basligi( 'Soyadı', 'soyad', $f, $link_args );
						?>
						<th class="sag sutun-bakiye" data-sutun="bakiye">Bakiye</th>
						<th class="sutun-islem" data-sutun="islem">İşlem</th>
						<th class="sutun-referans" data-sutun="referans">Referans</th>
						<th class="sutun-gsm" data-sutun="gsm">GSM</th>
						<th class="sutun-cinsiyet" data-sutun="cinsiyet" title="Cinsiyet">XXY</th>
					</tr></thead>
					<tbody>
					<?php
					if ( ! $adaylar ) {
						self::bos_liste( 'Aramanıza uyan aday bulunamadı.', 10 );
					}
					foreach ( $adaylar as $ad ) :
						$b       = isset( $bakiye[ (int) $ad->id ] ) ? $bakiye[ (int) $ad->id ] : array( 'borc' => 0, 'odenen' => 0, 'kalan' => 0, 'geciken' => 0 );
						$kalan   = $b['kalan'];
						$url     = YP_Cekirdek::panel_url( array( 'ekran' => 'aday', 'id' => (int) $ad->id ) );
						?>
						<tr class="tiklanir" data-git="<?php echo esc_url( $url ); ?>"
							data-foto="<?php echo esc_url( YP_Foto::url( $ad, true ) ); ?>"
							data-ad="<?php echo esc_attr( $ad->adi . ' ' . $ad->soyadi ); ?>"
							data-ayrinti="<?php echo esc_attr( 'Aday no: ' . (int) $ad->aday_no . ( $ad->tc_no ? ' · TC: ' . $ad->tc_no : '' ) . ( $ad->sari_not ? ' · ' . $ad->sari_not : '' ) ); ?>">
							<td class="sag sutun-no"><?php echo (int) $ad->aday_no; ?></td>
							<td class="sutun-tarih"><?php echo esc_html( YP_Bicim::tarih( $ad->kayit_tarihi ) ); ?></td>
							<td class="sutun-tc"><?php echo esc_html( $ad->tc_no ? $ad->tc_no : '—' ); ?></td>
							<td class="ad-sutun"><?php echo esc_html( $ad->adi ); ?></td>
							<td class="ad-sutun"><?php echo esc_html( $ad->soyadi ); ?><?php echo $ad->arsiv ? ' <span class="soluk">· arşiv</span>' : ''; // phpcs:ignore ?></td>
							<?php
							// KURAL: Borcu olan satırda tutar yazar (gecikmişse kırmızı); borcu kapanan satırda
							// hücrenin tamamı yeşile boyanır ve "ÖDENDİ" yazar — tamamlanan kayıt bir bakışta ayırt edilir.
							$odendi = ( $kalan <= 0 && $b['borc'] > 0 );
							?>
							<td class="sag sutun-bakiye<?php echo $odendi ? ' hucre-odendi' : ''; ?>">
								<?php
								if ( $kalan > 0 ) {
									$sinif = $b['geciken'] > 0 ? 'kirmizi' : '';
									echo '<span class="' . esc_attr( $sinif ) . '">' . esc_html( YP_Bicim::tl( $kalan, false ) ) . '</span>';
								} elseif ( $odendi ) {
									echo 'ÖDENDİ';
								} else {
									echo '<span class="soluk">—</span>';
								}
								?>
							</td>
							<?php $si = isset( $son[ (int) $ad->id ] ) ? $son[ (int) $ad->id ] : null; ?>
							<td class="sutun-islem"><span class="kirp"><?php echo esc_html( $si && $si->tur_adi ? $si->tur_adi : '—' ); ?></span></td>
							<td class="sutun-referans"><span class="kirp"><?php echo esc_html( isset( $referans[ (int) $ad->referans_id ] ) ? $referans[ (int) $ad->referans_id ] : 'BİREYSEL KAYIT' ); ?></span></td>
							<td class="sutun-gsm"><?php echo esc_html( YP_Bicim::telefon( $ad->gsm_1 ) ); ?></td>
							<?php // KURAL: Cinsiyet listede tek harf gösterilir (K/E); kayıtta tam hâliyle durur. ?>
							<td class="sutun-cinsiyet"><?php echo esc_html( $ad->cinsiyet ? mb_strtoupper( mb_substr( $ad->cinsiyet, 0, 1, 'UTF-8' ), 'UTF-8' ) : '' ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				</div>
				<?php self::kayit_sayaci( $toplam, $sayfa, $sayfa_basi, $link_args ); ?>
			</div>
		</div>
		<?php
		self::uyg_bitir();
	}

	// KURAL: Filtreler varsayılan olarak kapalıdır; yalnızca arama kutusu görünür — ekran sade kalır.
	private static function filtre_formu( array $f, array $link_args, $toplam = 0 ) {
		$acik = $f['referans'] || $f['islem_turu'] || '' !== $f['islem_durum'] || '' !== $f['odeme'] || $f['bas'] || $f['bit'] || '0' !== $f['arsiv'] || $f['ozel_kod'];
		?>
		<form class="kutu arac-satiri" method="get" action="<?php echo esc_url( YP_Cekirdek::panel_url() ); ?>">
			<input type="hidden" name="ekran" value="adaylar">
			<input type="hidden" name="sirala" value="<?php echo esc_attr( $f['sirala'] ); ?>">
			<input type="hidden" name="yon" value="<?php echo esc_attr( $f['yon'] ); ?>">
			<div class="arama-satiri">
				<a class="dugme kucuk" href="<?php echo esc_url( YP_Cekirdek::panel_url() ); ?>" title="Ana menü">☰ Ana menü</a>
				<input type="search" name="q" value="<?php echo esc_attr( $f['q'] ); ?>" placeholder="Ad, soyad, TC, telefon veya aday no" autocomplete="off">
				<button type="submit" class="dugme ana kucuk">Ara</button>
				<?php if ( $link_args && count( $link_args ) > 3 ) : ?>
					<a class="dugme kucuk" href="<?php echo esc_url( YP_Cekirdek::panel_url( array( 'ekran' => 'adaylar' ) ) ); ?>">Temizle</a>
				<?php endif; ?>
				<button type="button" class="dugme kucuk" data-ac="suzgecler">Filtre</button>
				<a class="dugme yesil kucuk" href="<?php echo esc_url( YP_Cekirdek::panel_url( array( 'ekran' => 'aday', 'yeni' => 1 ) ) ); ?>">+ Yeni Aday</a>
				<span class="arac-sayac"><b><?php echo esc_html( number_format( (int) $toplam, 0, ',', '.' ) ); ?></b> kayıt</span>
				<?php echo self::serit_anahtari(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			</div>
			<details class="filtre-katlanir" id="suzgecler"<?php echo $acik ? ' open' : ''; ?>>
				<summary>Filtre</summary>
				<div class="filtre">
					<?php
					self::secim( 'islem_turu', 'İşlem türü', YP_Veri::tanim_adlari( 'islem_turu' ), $f['islem_turu'], array( 'bos' => 'Tümü' ) );
					self::secim( 'islem_durum', 'İşlem durumu', YP_Veri::islem_durumlari(), $f['islem_durum'], array( 'bos' => 'Tümü' ) );
					self::secim( 'referans', 'Referans', YP_Veri::referans_secenekleri(), $f['referans'], array( 'bos' => 'Tümü' ) );
					self::secim( 'odeme', 'Ödeme', array( 'borclu' => 'Borcu olanlar', 'geciken' => 'Ödemesi gecikenler', 'odendi' => 'Borcu kapananlar' ), $f['odeme'], array( 'bos' => 'Tümü' ) );
					$kodlar = YP_Veri::tanim_adlari( 'ozel_kod' );
					if ( $kodlar ) {
						self::secim( 'ozel_kod', 'Özel kod', $kodlar, $f['ozel_kod'], array( 'bos' => 'Tümü' ) );
					}
					self::tarih_alani( 'bas', 'Kayıt (başlangıç)', $f['bas'] );
					self::tarih_alani( 'bit', 'Kayıt (bitiş)', $f['bit'] );
					self::secim( 'arsiv', 'Kayıtlar', array( '0' => 'Aktif', '1' => 'Arşivdekiler', 'tum' => 'Tümü' ), $f['arsiv'], array( 'bos' => null ) );
					?>
					<div class="filtre-dugmeler"><button type="submit" class="dugme ana">Listele</button></div>
				</div>
			</details>
		</form>
		<?php
	}

	// KURAL: Alt sayaç masaüstündeki gibi "Kayıt 26–50 / 320" gösterir ve ilk/son sayfaya tek tıkla gider.
	public static function kayit_sayaci( $toplam, $sayfa, $sayfa_basi, array $args ) {
		$son  = max( 1, (int) ceil( $toplam / $sayfa_basi ) );
		$bas  = $toplam ? ( ( $sayfa - 1 ) * $sayfa_basi ) + 1 : 0;
		$bit  = min( $toplam, $sayfa * $sayfa_basi );
		$url  = function ( $s ) use ( $args ) {
			return esc_url( YP_Cekirdek::panel_url( array_merge( $args, array( 'sayfa' => $s ) ) ) );
		};
		echo '<nav class="kayit-sayaci" aria-label="Sayfalar">';
		echo '<a class="gez ' . ( $sayfa <= 1 ? 'pasif' : '' ) . '" href="' . $url( 1 ) . '" aria-label="İlk sayfa">«</a>';
		echo '<a class="gez ' . ( $sayfa <= 1 ? 'pasif' : '' ) . '" href="' . $url( max( 1, $sayfa - 1 ) ) . '" aria-label="Önceki sayfa">‹</a>';
		echo '<span class="gez">' . (int) $sayfa . ' / ' . (int) $son . '</span>';
		echo '<a class="gez ' . ( $sayfa >= $son ? 'pasif' : '' ) . '" href="' . $url( min( $son, $sayfa + 1 ) ) . '" aria-label="Sonraki sayfa">›</a>';
		echo '<a class="gez ' . ( $sayfa >= $son ? 'pasif' : '' ) . '" href="' . $url( $son ) . '" aria-label="Son sayfa">»</a>';
		echo '<span class="bilgi">Kayıt ' . (int) $bas . '–' . (int) $bit . ' / ' . (int) $toplam . '</span>';
		echo '</nav>';
	}

	/**
	 * Üst çubuktaki hızlı arama: yazarken ilk 8 sonucu JSON olarak döner.
	 * KURAL: En fazla 8 kayıt döner ve yalnızca listede zaten görünen alanlar gönderilir.
	 */
	public static function hizli_ara_json() {
		global $wpdb;
		if ( ! YP_Guvenlik::dogrula( 'ara' ) ) {
			YP_Ekran::json( array( 'sonuc' => array() ), 403 );
		}
		$q = YP_Guvenlik::metin( 'q', 'get', 60 );
		if ( mb_strlen( $q, 'UTF-8' ) < 2 ) {
			YP_Ekran::json( array( 'sonuc' => array() ) );
		}
		$a     = YP_Cekirdek::tablo( 'adaylar' );
		$where = array( 'silindi = 0' );
		$args  = array();
		foreach ( array_slice( explode( ' ', YP_Bicim::katla( $q ) ), 0, 4 ) as $kelime ) {
			if ( '' === $kelime ) {
				continue;
			}
			if ( preg_match( '/^0\d{3,}$/', $kelime ) ) {
				$kelime = substr( $kelime, 1 );
			}
			$where[] = 'arama_metni LIKE %s';
			$args[]  = '%' . $wpdb->esc_like( $kelime ) . '%';
		}
		$args[]  = 8;
		$adaylar = $wpdb->get_results( $wpdb->prepare(
			'SELECT id, aday_no, adi, soyadi, gsm_1, foto, foto_kucuk FROM ' . $a . ' WHERE ' . implode( ' AND ', $where ) . ' ORDER BY aday_no DESC LIMIT %d', // phpcs:ignore
			$args
		) );
		$bakiye = YP_Hesap::aday_bakiyeleri( array_map( 'intval', wp_list_pluck( $adaylar, 'id' ) ) );
		$sonuc  = array();
		foreach ( $adaylar as $ad ) {
			$kalan   = isset( $bakiye[ (int) $ad->id ] ) ? $bakiye[ (int) $ad->id ]['kalan'] : 0;
			$sonuc[] = array(
				'ad'     => $ad->adi . ' ' . $ad->soyadi,
				'alt'    => '#' . (int) $ad->aday_no . ( $ad->gsm_1 ? ' · ' . YP_Bicim::telefon( $ad->gsm_1 ) : '' ),
				'kalan'  => $kalan > 0 ? YP_Bicim::tl( $kalan ) : '',
				'foto'   => YP_Foto::url( $ad, true ),
				'adres'  => YP_Cekirdek::panel_url( array( 'ekran' => 'aday', 'id' => (int) $ad->id ) ),
			);
		}
		YP_Ekran::json( array( 'sonuc' => $sonuc ) );
	}

	// KURAL: Sayfadaki adayların son işlemi tek sorguda alınır (IN listesi) — 25 satır için 25 sorgu atılmaz.
	public static function son_islemler( array $idler ) {
		global $wpdb;
		if ( ! $idler ) {
			return array();
		}
		$i   = YP_Cekirdek::tablo( 'islemler' );
		$t   = YP_Cekirdek::tablo( 'tanimlar' );
		$yer = implode( ',', array_fill( 0, count( $idler ), '%d' ) );
		$satirlar = $wpdb->get_results( $wpdb->prepare(
			"SELECT i.aday_id, i.durum, i.islem_tarihi, t.ad AS tur_adi FROM {$i} i LEFT JOIN {$t} t ON t.id = i.islem_turu_id
			WHERE i.silindi = 0 AND i.aday_id IN ({$yer}) ORDER BY i.islem_tarihi DESC, i.id DESC", // phpcs:ignore
			$idler
		) );
		$sonuc = array();
		foreach ( $satirlar as $s ) {
			if ( ! isset( $sonuc[ (int) $s->aday_id ] ) ) {
				$sonuc[ (int) $s->aday_id ] = $s;
			}
		}
		return $sonuc;
	}

	public static function referans_adlari( array $idler ) {
		global $wpdb;
		$idler = array_unique( $idler );
		if ( ! $idler ) {
			return array();
		}
		$r   = YP_Cekirdek::tablo( 'referanslar' );
		$yer = implode( ',', array_fill( 0, count( $idler ), '%d' ) );
		$satirlar = $wpdb->get_results( $wpdb->prepare( "SELECT id, unvan, ad_soyad FROM {$r} WHERE id IN ({$yer})", $idler ) ); // phpcs:ignore
		$sonuc = array();
		foreach ( $satirlar as $s ) {
			$sonuc[ (int) $s->id ] = $s->unvan ? $s->unvan : $s->ad_soyad;
		}
		return $sonuc;
	}

	private static function siralama_basligi( $metin, $anahtar, array $f, array $args, $sinif = '' ) {
		$yon = ( $f['sirala'] === $anahtar && 'desc' === $f['yon'] ) ? 'asc' : 'desc';
		$ok  = $f['sirala'] === $anahtar ? ( 'desc' === $f['yon'] ? ' ▼' : ' ▲' ) : '';
		$url = YP_Cekirdek::panel_url( array_merge( $args, array( 'sirala' => $anahtar, 'yon' => $yon ) ) );
		// KURAL: Sıralama bağlantısı sürükleme başlatmaz (draggable=false) — sürükleme başlığın kendisinden yapılır.
		echo '<th class="' . esc_attr( $sinif ) . '" data-sutun="' . esc_attr( $anahtar ) . '"><a draggable="false" href="' . esc_url( $url ) . '">' . esc_html( $metin . $ok ) . '</a></th>';
	}

}
