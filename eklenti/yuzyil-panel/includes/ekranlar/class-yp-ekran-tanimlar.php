<?php
defined( 'ABSPATH' ) || exit;

/**
 * Tanımlar (işlem türleri, gider/gelir kalemleri, özel kodlar, evrak), hesaplar ve panel ayarları.
 */
final class YP_Ekran_Tanimlar extends YP_Ekran {

	public static function islemler() {
		return array(
			'tanim_kaydet' => 'tanim_kaydet',
			'tanim_sil'    => 'tanim_sil',
			'hesap_kaydet' => 'hesap_kaydet',
			'hesap_sil'    => 'hesap_sil',
			'ayar_kaydet'  => 'ayar_kaydet',
			'ayar_sms_kaydet' => 'sms_ayar_kaydet',
		);
	}

	/**
	 * Ekranın üç başlığı ve her başlığın altındaki bölümler.
	 * KURAL: Tanımlar, kurum ayarları ve referanslar tek ekranda toplanır; soldaki menü
	 * hangi başlığın altında olduğunuzu her zaman gösterir.
	 */
	private static function gruplar() {
		return array(
			'Tanımlar'         => array_merge( YP_Veri::tanim_turleri(), array( 'hesaplar' => 'Hesaplar (Kasa / Banka)' ) ),
			'Kurum ve Ayarlar' => array(
				'ayarlar'  => 'Kurum Bilgileri',
				'sms'      => 'SMS Ayarları',
				'guvenlik' => 'Panel Şifresi',
			),
			'Referanslar'      => array(
				'referanslar' => 'Referans Listesi',
			),
		);
	}

	private static function sekmeler() {
		$hepsi = array();
		foreach ( self::gruplar() as $bolumler ) {
			$hepsi += $bolumler;
		}
		return $hepsi;
	}

	private static function sekme_grubu( $sekme ) {
		foreach ( self::gruplar() as $baslik => $bolumler ) {
			if ( isset( $bolumler[ $sekme ] ) ) {
				return $baslik;
			}
		}
		return 'Tanımlar';
	}

	public static function goster() {
		$sekme = YP_Guvenlik::secim( 'sekme', array_keys( self::sekmeler() ), 'islem_turu', 'get' );
		$serit = function () use ( $sekme ) {
			self::serit_grubu_ciz( 'Bölümler', array(
				array( 'İşlem Türleri', array( 'ekran' => 'tanimlar', 'sekme' => 'islem_turu' ), 'liste' ),
				array( 'Hesaplar', array( 'ekran' => 'tanimlar', 'sekme' => 'hesaplar' ), 'kasa' ),
				array( 'Kurum Bilgileri', array( 'ekran' => 'tanimlar', 'sekme' => 'ayarlar' ), 'ayar' ),
				array( 'Panel Şifresi', array( 'ekran' => 'tanimlar', 'sekme' => 'guvenlik' ), 'kilit' ),
				array( 'Referanslar', array( 'ekran' => 'tanimlar', 'sekme' => 'referanslar' ), 'referans' ),
			) );
			self::serit_grubu_ciz( 'Kayıtlar', array(
				array( 'İşlem Günlüğü', array( 'ekran' => 'gunluk' ), 'saat' ),
				array( 'Silinenler', array( 'ekran' => 'silinenler' ), 'cop', 'serit-kirmizi' ),
			) );
			self::serit_yon_grubu();
		};
		$sekmeler = self::sekmeler();
		$ad_sagi  = '<span class="vurgu">' . esc_html( self::sekme_grubu( $sekme ) . ' · ' . $sekmeler[ $sekme ] ) . '</span>';
		self::uyg_basla( 'Tanımlar ve Ayarlar', 'tanimlar', 'kaydir', $serit, $ad_sagi, 'ust-yok' );

		echo '<div class="tanim-duzen">';
		echo '<nav class="tanim-yan" aria-label="Bölümler">';
		foreach ( self::gruplar() as $baslik => $bolumler ) {
			echo '<h2>' . esc_html( $baslik ) . '</h2><ul>';
			foreach ( $bolumler as $k => $ad ) {
				$url = YP_Cekirdek::panel_url( array( 'ekran' => 'tanimlar', 'sekme' => $k ) );
				echo '<li><a class="' . ( $k === $sekme ? 'aktif' : '' ) . '" href="' . esc_url( $url ) . '">' . esc_html( $ad ) . '</a></li>';
			}
			echo '</ul>';
		}
		echo '</nav>';

		echo '<div class="tanim-icerik">';
		echo '<h2 class="tanim-baslik">' . esc_html( $sekmeler[ $sekme ] ) . '<span>' . esc_html( self::sekme_grubu( $sekme ) ) . '</span></h2>';
		if ( 'hesaplar' === $sekme ) {
			self::hesaplar();
		} elseif ( 'ayarlar' === $sekme ) {
			self::ayarlar();
		} elseif ( 'sms' === $sekme ) {
			self::sms_ayarlari();
		} elseif ( 'guvenlik' === $sekme ) {
			self::guvenlik();
		} elseif ( 'referanslar' === $sekme ) {
			self::referans_listesi();
		} else {
			self::tanim_listesi( $sekme );
		}
		echo '</div></div>';
		self::uyg_bitir();
	}

	/**
	 * Referans listesi (sürücü kursları vb.) — kart ve ödeme ekranı ayrı kalır, giriş buradadır.
	 * KURAL: Liste ciro/ödeme toplamlarını referans ekranının kendi sorgularıyla alır — sorgu iki yerde yazılmaz.
	 */
	private static function referans_listesi() {
		global $wpdb;
		$r     = YP_Cekirdek::tablo( 'referanslar' );
		$q     = YP_Guvenlik::metin( 'q', 'get', 100 );
		$durum = YP_Guvenlik::secim( 'durum', array( '1', '0', 'tum' ), '1', 'get' );
		$where = array( '1=1' );
		$args  = array();
		if ( 'tum' !== $durum ) {
			$where[] = 'r.aktif = %d';
			$args[]  = (int) $durum;
		}
		if ( '' !== $q ) {
			foreach ( explode( ' ', YP_Bicim::katla( $q ) ) as $k ) {
				if ( '' !== $k ) {
					$where[] = 'r.arama_metni LIKE %s';
					$args[]  = '%' . $wpdb->esc_like( $k ) . '%';
				}
			}
		}
		$sql   = 'SELECT r.*, COALESCE(s.aday_sayisi,0) AS aday_sayisi, COALESCE(s.islem_sayisi,0) AS islem_sayisi,
				COALESCE(s.ciro,0) AS ciro, COALESCE(o.odenen,0) AS odenen, s.son_tarih
			FROM ' . $r . ' r
			LEFT JOIN (' . YP_Ekran_Referanslar::istatistik_alt_sorgusu() . ') s ON s.referans_id = r.id
			LEFT JOIN (' . YP_Ekran_Referanslar::odeme_alt_sorgusu() . ') o ON o.referans_id = r.id
			WHERE ' . implode( ' AND ', $where ) . ' ORDER BY COALESCE(s.ciro,0) DESC, r.ad_soyad LIMIT 200';
		$liste = $wpdb->get_results( $args ? $wpdb->prepare( $sql, $args ) : $sql ); // phpcs:ignore WordPress.DB.PreparedSQL

		echo '<section class="kutu">';
		echo '<p class="soluk">Aday getiren sürücü kursları ve kişiler. Satıra tıklayınca referansın kartı (ciro, işlemler, ödemeler) açılır.</p>';
		echo '<form class="arama-satiri" method="get" action="' . esc_url( YP_Cekirdek::panel_url() ) . '">';
		echo '<input type="hidden" name="ekran" value="tanimlar"><input type="hidden" name="sekme" value="referanslar">';
		echo '<input type="search" name="q" value="' . esc_attr( $q ) . '" placeholder="Ad, firma veya telefon" autocomplete="off">';
		echo '<select name="durum">';
		foreach ( array( '1' => 'Aktif', '0' => 'Pasif', 'tum' => 'Tümü' ) as $k => $v ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $durum, $k, false ) . '>' . esc_html( $v ) . '</option>';
		}
		echo '</select><button type="submit" class="dugme ana kucuk">Listele</button>';
		echo '<a class="dugme yesil kucuk" href="' . esc_url( YP_Cekirdek::panel_url( array( 'ekran' => 'referanslar', 'yeni' => 1 ) ) ) . '">+ Yeni Referans</a>';
		echo '<span class="arac-sayac"><b>' . count( $liste ) . '</b> kayıt</span>';
		echo '</form>';
		echo '<div class="tablo-kap"><table class="tablo yogun"><thead><tr>';
		echo '<th>Referans</th><th>Telefon</th><th class="sag">Aday</th><th class="sag">İşlem</th><th class="sag">Ciro</th><th class="sag">Ödenen</th><th>Son işlem</th>';
		echo '</tr></thead><tbody>';
		if ( ! $liste ) {
			self::bos_liste( 'Referans bulunamadı.', 7 );
		}
		foreach ( $liste as $x ) {
			$url = YP_Cekirdek::panel_url( array( 'ekran' => 'referanslar', 'id' => (int) $x->id ) );
			echo '<tr class="tiklanir" data-git="' . esc_url( $url ) . '">';
			echo '<td><a href="' . esc_url( $url ) . '"><strong>' . esc_html( YP_Veri::referans_adi( $x ) ) . '</strong></a> ' . ( $x->aktif ? '' : self::rozet( 'Pasif', 'gri' ) ) . '</td>'; // phpcs:ignore
			echo '<td>' . esc_html( YP_Bicim::telefon( $x->gsm ? $x->gsm : $x->telefon ) ) . '</td>';
			echo '<td class="sag">' . (int) $x->aday_sayisi . '</td><td class="sag">' . (int) $x->islem_sayisi . '</td>';
			echo self::tutar_hucre( $x->ciro ) . self::tutar_hucre( $x->odenen ); // phpcs:ignore
			echo '<td>' . esc_html( YP_Bicim::tarih( $x->son_tarih ) ) . '</td></tr>';
		}
		echo '</tbody></table></div>';
		echo '<p class="soluk yazi-kucuk">En çok ciro yapan 200 referans listelenir; daha fazlası için arama kutusunu kullanın.</p>';
		echo '</section>';
	}

	private static function aciklamalar() {
		return array(
			'islem_turu'   => 'Yaptığınız psikoteknik işlemleri ve standart ücretlerini girin. "Geçerlilik (ay)" dolu ise rapor tarihine eklenerek geçerlilik bitişi otomatik hesaplanır.',
			'gider_kalemi' => 'Kasadan yapılan ödemelerin "nereye / ne için" başlıkları.',
			'gelir_kalemi' => 'Aday dışı gelirlerin başlıkları.',
			'ozel_kod'     => 'Adayları gruplamak için serbest etiketler (en fazla iki tane seçilebilir).',
			'evrak'        => 'Adaydan alınması gereken evrakların listesi. Aday kartındaki Evrak sekmesinde işaretlenir.',
		);
	}

	private static function tanim_listesi( $tur ) {
		$liste = YP_Veri::tanimlar( $tur, false );
		$ucretli = 'islem_turu' === $tur;
		$acik = self::aciklamalar();
		echo '<section class="kutu"><p class="soluk">' . esc_html( $acik[ $tur ] ) . '</p>';
		echo '<div class="satir-liste ' . ( $ucretli ? 'ucretli-liste' : 'tanim-liste' ) . '">';
		echo '<div class="satir-baslik"><span>Ad</span>' . ( $ucretli ? '<span>Ücret</span><span>Geçerlilik (ay)</span>' : '' ) . '<span>Sıra</span><span>Aktif</span><span></span></div>';
		foreach ( $liste as $t ) {
			self::tanim_satiri( $tur, $t, $ucretli );
		}
		echo '<h3>Yeni ekle</h3>';
		self::tanim_satiri( $tur, null, $ucretli );
		echo '</div></section>';
	}

	private static function tanim_satiri( $tur, $t, $ucretli ) {
		self::form_ac( 'tanim_kaydet', 'satir-form' . ( $t && ! $t->aktif ? ' pasif' : '' ) );
		self::gizli( 'id', $t ? $t->id : 0 );
		self::gizli( 'tur', $tur );
		echo '<input type="text" name="ad" value="' . esc_attr( $t ? $t->ad : '' ) . '" maxlength="150" placeholder="Ad" aria-label="Ad" required>';
		if ( $ucretli ) {
			echo '<input type="text" class="tutar" inputmode="decimal" name="ucret" value="' . esc_attr( $t ? YP_Bicim::tl( $t->ucret, false ) : '' ) . '" placeholder="0,00" aria-label="Ücret">';
			echo '<input type="number" name="gecerlilik_ay" min="0" max="240" value="' . esc_attr( $t ? (int) $t->gecerlilik_ay : 0 ) . '" aria-label="Geçerlilik (ay)">';
		}
		echo '<input type="number" name="sira" min="0" max="999" value="' . esc_attr( $t ? (int) $t->sira : 0 ) . '" aria-label="Sıra">';
		echo '<label class="onay"><input type="checkbox" name="aktif" value="1"' . checked( ! $t || $t->aktif, true, false ) . '> Aktif</label>';
		echo '<span class="satir-dugmeler"><button type="submit" class="dugme kucuk ana">' . ( $t ? 'Kaydet' : 'Ekle' ) . '</button>';
		if ( $t ) {
			echo ' <button type="submit" class="dugme kucuk kirmizi" form="yp-sil-t' . (int) $t->id . '" data-onay="' . esc_attr( 'Tanım silinecek. Kullanılıyorsa silinmez, pasife alınması önerilir. Emin misiniz?' ) . '">Sil</button>';
		}
		echo '</span></form>';
		if ( $t ) {
			self::gizli_form( 'yp-sil-t' . (int) $t->id, 'tanim_sil', array( 'id' => $t->id ) );
		}
	}

	// KURAL: Satırdaki "Sil" düğmesi ayrı, gizli bir forma bağlanır — form içinde form olmaz, düzen tek satırda kalır.
	private static function gizli_form( $form_id, $islem, array $alanlar ) {
		echo '<form id="' . esc_attr( $form_id ) . '" method="post" action="' . esc_url( YP_Cekirdek::panel_url() ) . '" hidden>';
		echo YP_Guvenlik::nonce_alani( $islem ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<input type="hidden" name="_geri" value="' . esc_attr( self::geri_adresi() ) . '">';
		foreach ( $alanlar as $k => $v ) {
			self::gizli( $k, $v );
		}
		echo '</form>';
	}

	private static function hesaplar() {
		echo '<section class="kutu"><p class="soluk">Paranın durduğu yerler. "Açılış bakiyesi", panele geçtiğiniz gün hesapta bulunan tutardır; açılış tarihinden itibaren bakiyeye eklenir.</p><div class="satir-liste hesap-liste">';
		echo '<div class="satir-baslik"><span>Ad</span><span>Tür</span><span>Banka / IBAN</span><span>Açılış bakiyesi</span><span>Açılış tarihi</span><span>Sıra</span><span>Aktif</span><span></span></div>';
		foreach ( YP_Veri::hesaplar( false ) as $h ) {
			self::hesap_satiri( $h );
		}
		echo '<h3>Yeni hesap</h3>';
		self::hesap_satiri( null );
		echo '</div></section>';
	}

	private static function hesap_satiri( $h ) {
		self::form_ac( 'hesap_kaydet', 'satir-form' . ( $h && ! $h->aktif ? ' pasif' : '' ) );
		self::gizli( 'id', $h ? $h->id : 0 );
		echo '<input type="text" name="ad" value="' . esc_attr( $h ? $h->ad : '' ) . '" maxlength="100" placeholder="Örn. Ziraat Bankası" aria-label="Ad" required>';
		echo '<select name="tur" aria-label="Tür">';
		foreach ( YP_Veri::hesap_turleri() as $k => $v ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $h ? $h->tur : 'BANKA', $k, false ) . '>' . esc_html( $v ) . '</option>';
		}
		echo '</select>';
		echo '<input type="text" name="iban" value="' . esc_attr( $h ? $h->iban : '' ) . '" maxlength="40" placeholder="IBAN (isteğe bağlı)" aria-label="IBAN">';
		echo '<input type="text" class="tutar" inputmode="decimal" name="acilis_bakiyesi" value="' . esc_attr( $h ? YP_Bicim::tl( $h->acilis_bakiyesi, false ) : '' ) . '" placeholder="0,00" aria-label="Açılış bakiyesi">';
		echo '<input type="text" class="tarih" data-tarih="1" inputmode="numeric" maxlength="10" name="acilis_tarihi" value="' . esc_attr( $h ? YP_Bicim::tarih( $h->acilis_tarihi ) : YP_Bicim::tarih( YP_Cekirdek::bugun() ) ) . '" placeholder="gg.aa.yyyy" aria-label="Açılış tarihi">';
		echo '<input type="number" name="sira" min="0" max="999" value="' . esc_attr( $h ? (int) $h->sira : 0 ) . '" aria-label="Sıra">';
		echo '<label class="onay"><input type="checkbox" name="aktif" value="1"' . checked( ! $h || $h->aktif, true, false ) . '> Aktif</label>';
		echo '<span class="satir-dugmeler"><button type="submit" class="dugme kucuk ana">' . ( $h ? 'Kaydet' : 'Ekle' ) . '</button>';
		if ( $h ) {
			echo ' <button type="submit" class="dugme kucuk kirmizi" form="yp-sil-h' . (int) $h->id . '" data-onay="' . esc_attr( 'Hesap silinecek. Hareketi varsa silinmez. Emin misiniz?' ) . '">Sil</button>';
		}
		echo '</span></form>';
		if ( $h ) {
			self::gizli_form( 'yp-sil-h' . (int) $h->id, 'hesap_sil', array( 'id' => $h->id ) );
		}
	}

	private static function ayarlar() {
		$a = YP_Cekirdek::ayarlar();
		echo '<section class="kutu">';
		self::form_ac( 'ayar_kaydet' );
		echo '<div class="izgara">';
		self::alan( 'kurum_adi', 'Kurum adı (makbuz başlığı)', $a['kurum_adi'], array( 'maxlength' => 150, 'zorunlu' => true ) );
		self::alan( 'kurum_telefonu', 'Kurum telefonu', $a['kurum_telefonu'], array( 'maxlength' => 50 ) );
		self::alan( 'kurum_adresi', 'Kurum adresi', $a['kurum_adresi'], array( 'maxlength' => 300, 'sinif' => 'genis' ) );
		self::alan( 'web_sitesi', 'Web sitesi adresi (ana menüdeki düğme)', $a['web_sitesi'], array( 'maxlength' => 200, 'tur' => 'url' ) );
		self::alan( 'makbuz_notu', 'Makbuz alt notu', $a['makbuz_notu'], array( 'maxlength' => 300, 'sinif' => 'genis' ) );
		self::alan( 'yaklasan_gun', '"Vadesi yaklaşan" kaç gün', (string) (int) $a['yaklasan_gun'], array( 'tur' => 'number', 'min' => 1, 'max' => 90 ) );
		self::alan( 'gecerlilik_uyari_gun', '"Geçerliliği dolacak" kaç gün', (string) (int) $a['gecerlilik_uyari_gun'], array( 'tur' => 'number', 'min' => 1, 'max' => 365 ) );
		self::alan( 'sayfa_basi', 'Listelerde sayfa başına kayıt', (string) (int) $a['sayfa_basi'], array( 'tur' => 'number', 'min' => 10, 'max' => 100 ) );
		echo '</div><div class="form-dugmeler"><button type="submit" class="dugme ana">Kaydet</button></div></form>';
		echo '<p class="soluk yazi-kucuk">Panel adresi (şu an <strong>/' . esc_html( YP_Cekirdek::slug() ) . '</strong>) WordPress yönetiminde <em>Ayarlar → Yüzyıl Panel</em> sayfasından değiştirilir.</p></section>';
	}

	// KURAL: Panel şifresi yalnızca mevcut şifre doğrulanarak değiştirilir; değişince tüm cihazlarda yeniden giriş istenir.
	private static function guvenlik() {
		echo '<section class="kutu"><h2>Panel giriş bilgileri</h2>';
		echo '<p class="soluk">Panel adresini açan kişi WordPress yöneticisi olsa bile bu şifreyi bilmeden içeri giremez. Şifre veritabanında geri döndürülemez biçimde saklanır.</p>';
		self::form_ac( 'sifre_degistir' );
		echo '<div class="izgara">';
		self::alan( 'kullanici', 'Kullanıcı adı', YP_Kilit::kullanici_adi(), array( 'maxlength' => 60 ) );
		self::alan( 'eski_sifre', 'Mevcut şifre', '', array( 'tur' => 'password', 'zorunlu' => true ) );
		self::alan( 'sifre', 'Yeni şifre (en az ' . YP_Kilit::EN_AZ_UZUNLUK . ' karakter)', '', array( 'tur' => 'password', 'zorunlu' => true ) );
		self::alan( 'sifre2', 'Yeni şifre (tekrar)', '', array( 'tur' => 'password', 'zorunlu' => true ) );
		echo '</div><div class="form-dugmeler"><button type="submit" class="dugme ana">Şifreyi değiştir</button></div></form>';
		echo '<h3>Oturumlar</h3><p class="soluk">"Beni hatırla" işaretlediğiniz cihazlarda panel şifre sormadan açılır. Kaybolan bir cihaz varsa buradan hepsini kapatabilirsiniz.</p>';
		self::form_ac( 'kilit_kapat', 'satir-ici' );
		self::gizli( 'tum_cihazlar', 1 );
		echo '<button type="submit" class="dugme kirmizi" data-onay="Tüm cihazlarda panel kilitlenecek ve şifre yeniden istenecek. Emin misiniz?">Tüm cihazlarda kilitle</button></form>';
		echo '</section>';
	}

	// ---- İşlemler -------------------------------------------------------

	public static function tanim_kaydet() {
		global $wpdb;
		$tur = YP_Guvenlik::secim( 'tur', array_keys( YP_Veri::tanim_turleri() ) );
		$ad  = YP_Guvenlik::metin( 'ad', 'post', 150 );
		if ( '' === $tur || '' === $ad ) {
			self::geri_don( 'Ad boş olamaz.', 'hata' );
		}
		$v = array(
			'tur'           => $tur,
			'ad'            => $ad,
			'ucret'         => max( 0, YP_Guvenlik::tutar( 'ucret' ) ),
			'gecerlilik_ay' => min( 240, YP_Guvenlik::tamsayi( 'gecerlilik_ay' ) ),
			'sira'          => min( 999, YP_Guvenlik::tamsayi( 'sira' ) ),
			'aktif'         => YP_Guvenlik::bayrak( 'aktif' ),
		);
		$id = YP_Guvenlik::tamsayi( 'id' );
		$t  = YP_Cekirdek::tablo( 'tanimlar' );
		if ( $id ) {
			$eski = YP_Veri::tanim( $id );
			if ( ! $eski || $eski->tur !== $tur ) {
				self::geri_don( 'Tanım bulunamadı.', 'hata' );
			}
			$wpdb->update( $t, $v, array( 'id' => $id ) );
			YP_Cekirdek::log( 'tanim', $id, 'güncelleme', $tur . ': ' . $ad );
		} else {
			$wpdb->insert( $t, $v );
			YP_Cekirdek::log( 'tanim', (int) $wpdb->insert_id, 'ekleme', $tur . ': ' . $ad );
		}
		self::geri_don( 'Kaydedildi.' );
	}

	// KURAL: Kullanılan tanım silinmez, pasife alınır — geçmiş kayıtlarda adı boş kalmaz.
	public static function tanim_sil() {
		global $wpdb;
		$t = YP_Veri::tanim( YP_Guvenlik::tamsayi( 'id' ) );
		if ( ! $t ) {
			self::geri_don( 'Tanım bulunamadı.', 'hata' );
		}
		$kullanim = 0;
		if ( 'islem_turu' === $t->tur ) {
			$kullanim = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . YP_Cekirdek::tablo( 'islemler' ) . ' WHERE islem_turu_id = %d', $t->id ) );
		} elseif ( in_array( $t->tur, array( 'gider_kalemi', 'gelir_kalemi' ), true ) ) {
			$kullanim = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . YP_Cekirdek::tablo( 'hareketler' ) . ' WHERE kalem_id = %d', $t->id ) );
		} elseif ( 'ozel_kod' === $t->tur ) {
			$kullanim = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . YP_Cekirdek::tablo( 'adaylar' ) . ' WHERE ozel_kod1_id = %d OR ozel_kod2_id = %d', $t->id, $t->id ) );
		}
		if ( $kullanim > 0 ) {
			self::geri_don( '"' . esc_html( $t->ad ) . '" ' . $kullanim . ' kayıtta kullanılıyor; silinemez. "Aktif" işaretini kaldırarak pasife alabilirsiniz.', 'hata' );
		}
		$wpdb->delete( YP_Cekirdek::tablo( 'tanimlar' ), array( 'id' => $t->id ), array( '%d' ) );
		YP_Cekirdek::log( 'tanim', $t->id, 'silme', $t->tur . ': ' . $t->ad );
		self::geri_don( 'Silindi.' );
	}

	public static function hesap_kaydet() {
		global $wpdb;
		$ad = YP_Guvenlik::metin( 'ad', 'post', 100 );
		if ( '' === $ad ) {
			self::geri_don( 'Hesap adı boş olamaz.', 'hata' );
		}
		$acilis = YP_Guvenlik::tarih( 'acilis_tarihi' );
		$v      = array(
			'ad'              => $ad,
			'tur'             => YP_Guvenlik::secim( 'tur', array_keys( YP_Veri::hesap_turleri() ), 'BANKA' ),
			'iban'            => strtoupper( preg_replace( '/\s+/', '', YP_Guvenlik::metin( 'iban', 'post', 40 ) ) ),
			'acilis_bakiyesi' => YP_Guvenlik::tutar( 'acilis_bakiyesi' ),
			'acilis_tarihi'   => $acilis ? $acilis : YP_Cekirdek::bugun(),
			'sira'            => min( 999, YP_Guvenlik::tamsayi( 'sira' ) ),
			'aktif'           => YP_Guvenlik::bayrak( 'aktif' ),
		);
		$id = YP_Guvenlik::tamsayi( 'id' );
		$t  = YP_Cekirdek::tablo( 'hesaplar' );
		if ( $id && YP_Veri::hesap( $id ) ) {
			$wpdb->update( $t, $v, array( 'id' => $id ) );
			YP_Cekirdek::log( 'kasa', $id, 'hesap', 'Hesap güncellendi: ' . $ad . ' / açılış ' . YP_Bicim::tl( $v['acilis_bakiyesi'] ) );
		} else {
			$wpdb->insert( $t, $v );
			YP_Cekirdek::log( 'kasa', (int) $wpdb->insert_id, 'hesap', 'Yeni hesap: ' . $ad );
		}
		self::geri_don( 'Hesap kaydedildi.' );
	}

	// KURAL: Hareketi olan hesap silinmez, pasife alınır — kasa geçmişi bozulmaz.
	public static function hesap_sil() {
		global $wpdb;
		$h = YP_Veri::hesap( YP_Guvenlik::tamsayi( 'id' ) );
		if ( ! $h ) {
			self::geri_don( 'Hesap bulunamadı.', 'hata' );
		}
		$kullanim = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . YP_Cekirdek::tablo( 'hareketler' ) . ' WHERE hesap_id = %d OR hedef_hesap_id = %d', $h->id, $h->id ) );
		if ( $kullanim > 0 ) {
			self::geri_don( 'Bu hesapta ' . $kullanim . ' hareket var; silinemez. "Aktif" işaretini kaldırarak pasife alabilirsiniz.', 'hata' );
		}
		$wpdb->delete( YP_Cekirdek::tablo( 'hesaplar' ), array( 'id' => $h->id ), array( '%d' ) );
		YP_Cekirdek::log( 'kasa', $h->id, 'hesap', 'Hesap silindi: ' . $h->ad );
		self::geri_don( 'Hesap silindi.' );
	}

	public static function ayar_kaydet() {
		$kurum = YP_Guvenlik::metin( 'kurum_adi', 'post', 150 );
		// KURAL: Web sitesi adresi yalnızca http/https olarak kabul edilir — başka şema (javascript: gibi) yazılamaz.
		$site = esc_url_raw( YP_Guvenlik::metin( 'web_sitesi', 'post', 200 ), array( 'http', 'https' ) );
		YP_Cekirdek::ayar_kaydet( array(
			'kurum_adi'            => '' === $kurum ? 'Yüzyıl Psikoteknik Merkezi' : $kurum,
			'web_sitesi'           => $site,
			'kurum_adresi'         => YP_Guvenlik::metin( 'kurum_adresi', 'post', 300 ),
			'kurum_telefonu'       => YP_Guvenlik::metin( 'kurum_telefonu', 'post', 50 ),
			'makbuz_notu'          => YP_Guvenlik::metin( 'makbuz_notu', 'post', 300 ),
			'yaklasan_gun'         => min( 90, max( 1, YP_Guvenlik::tamsayi( 'yaklasan_gun' ) ) ),
			'gecerlilik_uyari_gun' => min( 365, max( 1, YP_Guvenlik::tamsayi( 'gecerlilik_uyari_gun' ) ) ),
			'sayfa_basi'           => min( 100, max( 10, YP_Guvenlik::tamsayi( 'sayfa_basi' ) ) ),
		) );
		YP_Cekirdek::log( 'tanim', 0, 'ayar', 'Panel ayarları güncellendi.' );
		self::geri_don( 'Ayarlar kaydedildi.' );
	}

	/**
	 * SMS abone bilgileri.
	 * KURAL: Şifre ekranda hiçbir zaman okunaklı yazılmaz; boş bırakılırsa kayıtlı şifre korunur.
	 */
	private static function sms_ayarlari() {
		$a       = YP_Cekirdek::ayarlar();
		$kurulu  = class_exists( 'YP_Sms' ) && YP_Sms::ayarlar_tamam();

		echo '<section class="kutu"><h2>SMS abone bilgileri</h2>';
		echo '<p class="soluk">Toplu mesajlar Netgsm aboneliğiniz üzerinden gönderilir. Buraya girdiğiniz bilgiler yalnızca bu panelde saklanır.</p>';

		self::form_ac( 'ayar_sms_kaydet' );
		echo '<div class="izgara">';
		self::alan( 'sms_kullanici', 'Abone numarası (kullanıcı kodu)', (string) $a['sms_kullanici'], array( 'maxlength' => 40 ) );
		self::alan( 'sms_sifre', 'Şifre' . ( '' !== (string) $a['sms_sifre'] ? ' (kayıtlı — değiştirmek için yazın)' : '' ), '', array( 'tur' => 'password', 'maxlength' => 60 ) );
		self::alan( 'sms_baslik', 'Mesaj başlığı (onaylı gönderici adı)', (string) $a['sms_baslik'], array( 'maxlength' => 20 ) );
		self::alan( 'sms_gunluk_sinir', 'Günlük en çok SMS (0 = sınırsız)', (string) (int) $a['sms_gunluk_sinir'], array( 'tur' => 'number', 'min' => 0, 'max' => 100000 ) );
		echo '</div>';

		echo '<label class="onay-satiri"><input type="checkbox" name="sms_aktif" value="1"' . checked( '1' === (string) $a['sms_aktif'], true, false ) . '> SMS gönderimi açık</label>';
		echo '<label class="onay-satiri"><input type="checkbox" name="sms_turkce" value="1"' . checked( '1' === (string) $a['sms_turkce'], true, false ) . '> Yeni gönderimlerde "Türkçe karakter" kutusu işaretli gelsin</label>';

		echo '<div class="form-dugmeler"><button type="submit" class="dugme ana">Kaydet</button></div></form>';

		echo '<h3>Bağlantı denemesi</h3>';
		echo '<p class="soluk">Bu düğme <strong>mesaj göndermez</strong>; yalnızca abone bilgilerinizin doğru olup olmadığını ve kalan kredinizi sorar.</p>';
		if ( $kurulu ) {
			self::form_ac( 'sms_dene', 'satir-ici' );
			echo '<button type="submit" class="dugme">Bağlantıyı dene</button></form>';
		} else {
			echo '<p class="kirmizi">Önce abone numarası, şifre ve mesaj başlığı girilmeli.</p>';
		}

		echo '<h3>Mesaj başlığı hakkında</h3>';
		echo '<p class="soluk">Mesaj başlığı, SMS\'in kimden geldiğini gösteren addır ve Netgsm tarafından önceden onaylanmış olmalıdır. Onaysız bir başlıkla gönderim reddedilir.</p>';
		echo '</section>';
	}

	public static function sms_ayar_kaydet() {
		$sifre  = YP_Guvenlik::metin( 'sms_sifre', 'post', 60 );
		$kayitli = (string) YP_Cekirdek::ayar( 'sms_sifre' );
		YP_Cekirdek::ayar_kaydet( array(
			'sms_kullanici'    => YP_Guvenlik::metin( 'sms_kullanici', 'post', 40 ),
			// KURAL: Şifre alanı boş gönderilirse eskisi korunur — kaydet'e basmak şifreyi silmez.
			'sms_sifre'        => '' === $sifre ? $kayitli : $sifre,
			'sms_baslik'       => YP_Guvenlik::metin( 'sms_baslik', 'post', 20 ),
			'sms_gunluk_sinir' => min( 100000, max( 0, YP_Guvenlik::tamsayi( 'sms_gunluk_sinir' ) ) ),
			'sms_aktif'        => isset( $_POST['sms_aktif'] ) ? 1 : 0, // phpcs:ignore WordPress.Security.NonceVerification -- nonce yönlendiricide doğrulandı.
			'sms_turkce'       => isset( $_POST['sms_turkce'] ) ? 1 : 0, // phpcs:ignore WordPress.Security.NonceVerification -- nonce yönlendiricide doğrulandı.
		) );
		// KURAL: Günlüğe şifre yazılmaz — yalnızca ayarın değiştiği bilgisi tutulur.
		YP_Cekirdek::log( 'tanim', 0, 'ayar', 'SMS ayarları güncellendi.' );
		self::geri_don( 'SMS ayarları kaydedildi.' );
	}
}
