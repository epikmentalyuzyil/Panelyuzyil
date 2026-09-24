<?php
defined( 'ABSPATH' ) || exit;

/**
 * wp-admin > Ayarlar > Yüzyıl Panel: panel adresi, kaldırma seçeneği, fotoğraf klasörü koruma testi.
 */
final class YP_Ayarlar_Sayfasi {

	public static function baslat() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_yp_ayarlar', array( __CLASS__, 'kaydet' ) );
		add_action( 'admin_init', array( 'YP_Cekirdek', 'surum_kontrol' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( YP_DOSYA ), array( __CLASS__, 'eklenti_linkleri' ) );
	}

	public static function menu() {
		add_options_page( 'Yüzyıl Panel', 'Yüzyıl Panel', 'manage_options', 'yuzyil-panel', array( __CLASS__, 'sayfa' ) );
	}

	public static function eklenti_linkleri( $linkler ) {
		array_unshift( $linkler, '<a href="' . esc_url( admin_url( 'options-general.php?page=yuzyil-panel' ) ) . '">Ayarlar</a>' );
		return $linkler;
	}

	// KURAL: Panel adresi sistem adresleriyle ve mevcut sayfa/yazı adresleriyle çakışamaz.
	private static function slug_hatasi( $slug ) {
		$yasak = array( 'wp-admin', 'wp-login', 'wp-login-php', 'admin', 'login', 'wp-content', 'wp-includes', 'wp-json', 'feed', 'sitemap', 'wp-sitemap', 'xmlrpc', 'page', 'category', 'tag', 'author', 'search', 'comments', 'embed' );
		if ( '' === $slug || strlen( $slug ) < 3 ) {
			return 'Adres en az 3 karakter olmalı.';
		}
		if ( in_array( $slug, $yasak, true ) ) {
			return 'Bu adres WordPress tarafından kullanılıyor; başka bir adres seçin.';
		}
		if ( get_page_by_path( $slug, OBJECT, array( 'page', 'post' ) ) ) {
			return 'Sitenizde bu adreste bir sayfa veya yazı var; başka bir adres seçin.';
		}
		return '';
	}

	public static function kaydet() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Yetkiniz yok.', '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'yp_ayarlar' );
		$donus = admin_url( 'options-general.php?page=yuzyil-panel' );

		// KURAL: Panel şifresi unutulursa WordPress yöneticisi buradan sıfırlar; şifre görüntülenmez, yalnızca silinir ve yeniden belirlenir.
		if ( isset( $_POST['sifre_sifirla'] ) ) {
			YP_Cekirdek::ayar_kaydet( array( 'kilit_hash' => '' ) );
			delete_metadata( 'user', 0, 'yp_kilit_anahtarlari', '', true );
			set_transient( 'yp_ayar_sifre_sifirlandi', 1, 60 );
			wp_safe_redirect( $donus );
			exit;
		}

		if ( isset( $_POST['koruma_testi'] ) ) {
			require_once YP_DIZIN . 'includes/class-yp-foto.php';
			set_transient( 'yp_koruma_sonucu', YP_Foto::koruma_testi(), 300 );
			wp_safe_redirect( $donus );
			exit;
		}

		$slug  = sanitize_title( isset( $_POST['slug'] ) ? wp_unslash( $_POST['slug'] ) : '' );
		$hata  = $slug === YP_Cekirdek::slug() ? '' : self::slug_hatasi( $slug );
		if ( '' !== $hata ) {
			set_transient( 'yp_ayar_hatasi', $hata, 60 );
			wp_safe_redirect( $donus );
			exit;
		}
		YP_Cekirdek::ayar_kaydet( array(
			'slug'           => $slug,
			'kaldirinca_sil' => empty( $_POST['kaldirinca_sil'] ) ? 0 : 1,
		) );
		set_transient( 'yp_ayar_kaydedildi', 1, 60 );
		wp_safe_redirect( $donus );
		exit;
	}

	public static function sayfa() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$ayar   = YP_Cekirdek::ayarlar();
		$hata   = get_transient( 'yp_ayar_hatasi' );
		$ok     = get_transient( 'yp_ayar_kaydedildi' );
		$sifir  = get_transient( 'yp_ayar_sifre_sifirlandi' );
		delete_transient( 'yp_ayar_sifre_sifirlandi' );
		$koruma = get_transient( 'yp_koruma_sonucu' );
		delete_transient( 'yp_ayar_hatasi' );
		delete_transient( 'yp_ayar_kaydedildi' );
		delete_transient( 'yp_koruma_sonucu' );
		$url = YP_Cekirdek::panel_url();
		?>
		<div class="wrap">
			<h1>Yüzyıl Panel</h1>
			<?php if ( $hata ) : ?><div class="notice notice-error"><p><?php echo esc_html( $hata ); ?></p></div><?php endif; ?>
			<?php if ( $ok ) : ?><div class="notice notice-success"><p>Ayarlar kaydedildi.</p></div><?php endif; ?>
			<?php if ( $sifir ) : ?><div class="notice notice-success"><p>Panel şifresi sıfırlandı. Panel adresini açtığınızda yeni şifre belirlemeniz istenecek.</p></div><?php endif; ?>
			<?php
			if ( $koruma ) {
				$m = array(
					'korunuyor'  => array( 'success', 'Fotoğraf klasörü korunuyor: dışarıdan doğrudan açılamıyor.' ),
					'disarida'   => array( 'success', 'Fotoğraflar web kökü dışındaki klasörde (YP_FOTO_DIZINI) tutuluyor.' ),
					'acik'       => array( 'error', 'DİKKAT: Fotoğraf klasörü dışarıdan açılabiliyor. Sunucunuz .htaccess dosyasını dikkate almıyor (Nginx olabilir). Kurulum talimatındaki "Fotoğraf klasörünü korumak" adımını uygulayın.' ),
					'bilinmiyor' => array( 'warning', 'Test yapılamadı (sunucu kendine bağlanamadı). Hosting firmanıza klasör korumasını sorun.' ),
				);
				if ( isset( $m[ $koruma ] ) ) {
					echo '<div class="notice notice-' . esc_attr( $m[ $koruma ][0] ) . '"><p>' . esc_html( $m[ $koruma ][1] ) . '</p></div>';
				}
			}
			?>
			<p>Panel adresi: <a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener"><strong><?php echo esc_html( $url ); ?></strong></a></p>
			<p>Bu adres yalnızca giriş yapmış <strong>Yönetici</strong> hesaplarına açılır; diğer herkes "sayfa bulunamadı" görür. Adresi kimseyle paylaşmayın.</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="yp_ayarlar">
				<?php wp_nonce_field( 'yp_ayarlar' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="yp-slug">Panel adresi</label></th>
						<td><code><?php echo esc_html( home_url( '/' ) ); ?></code><input id="yp-slug" name="slug" type="text" class="regular-text" value="<?php echo esc_attr( $ayar['slug'] ); ?>" required minlength="3" maxlength="60">
						<p class="description">Tahmin edilmesi zor bir adres seçmeniz önerilir (örnek: yonetim-x7k2). Yalnızca küçük harf, rakam ve tire kullanın.</p></td>
					</tr>
					<tr>
						<th scope="row">Eklenti silinince</th>
						<td><label><input type="checkbox" name="kaldirinca_sil" value="1" <?php checked( (int) $ayar['kaldirinca_sil'], 1 ); ?>> Eklenti <strong>silinirse</strong> tüm panel verilerini ve fotoğrafları da sil</label>
						<p class="description">İşaretli değilse (önerilen) eklentiyi silseniz bile verileriniz veritabanında kalır. Devre dışı bırakmak hiçbir zaman veri silmez.</p></td>
					</tr>
				</table>
				<?php submit_button( 'Kaydet' ); ?>
				<h2>Fotoğraf klasörü güvenliği</h2>
				<p>Aday fotoğraflarının dışarıdan doğrudan açılamadığını kontrol eder.</p>
				<button type="submit" name="koruma_testi" value="1" class="button">Korumayı test et</button>

				<h2>Panel şifresi</h2>
				<p>Panel şifresini unuttuysanız buradan sıfırlayabilirsiniz. Şifre silinir; panele bir sonraki girişte yeni şifre belirlemeniz istenir. Mevcut şifre burada gösterilmez.</p>
				<button type="submit" name="sifre_sifirla" value="1" class="button button-secondary" onclick="return confirm('Panel şifresi silinecek ve tüm cihazlarda yeniden giriş istenecek. Devam edilsin mi?');">Panel şifresini sıfırla</button>
			</form>
		</div>
		<?php
	}
}
