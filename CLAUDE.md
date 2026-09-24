# Yüzyıl Panel — çalışma kuralları

Bu dosya, projeye yeni bir oturumda devam eden asistan için yazılmıştır.
Ayrıntılı durum: `PROJE-DURUMU.md`.

## Dokunulmayacak yerler (kullanıcının koyduğu kurallar)

1. `C:\Wenntec` altındaki **masaüstü program canlıdır, kopya değildir**. Sadece okunur.
   Hiçbir dosyası değiştirilmez, silinmez, taşınmaz, yeniden adlandırılmaz; program çalıştırılmaz;
   klasörüne yeni dosya yazılmaz.
2. `C:\Users\HP\Desktop\kopya` klasörüne ve içindeki `.bak` dosyasına dokunulmaz.
3. **Canlı web sitesine hiçbir şekilde bağlanılmaz**, müdahale edilmez. Geliştirme yalnızca
   `test-ortami` içindeki yerel WordPress kurulumunda yapılır.
4. Gerçek müşteri bilgisi (isim, TC, telefon, adres, tutar, fotoğraf) ekrana yazdırılmaz.
   Örnek gerekirse uydurma veri kullanılır. Toplam kayıt adedi gibi özetler verilebilir.
5. Geliştirme sırasında **yalnızca uydurma test verisi** kullanılır. Gerçek verinin panele
   aktarımı ayrı bir aşamadır ve kullanıcı söylemeden yapılmaz.
6. Paket (pakete girmeyen dosyalar hariç) kullanıcıya kurulabilir `.zip` olarak teslim edilir.
7. Kurulum/kullanım metinleri teknik olmayan bir kişiye göre, adım adım ve Türkçe yazılır.

## Kod yazarken uyulan biçim

- Arayüz, değişken, sınıf ve dosya adları **Türkçe**dir (`YP_Ekran_Adaylar`, `aday_bakiyeleri`,
  `uyg_basla`). Sınıf ön eki `YP_`, tablo ön eki `wp_yp_`, sabit ön eki `YP_`.
- Açıklama satırları tek satırlık **"KURAL:"** notlarıdır ve *nedeni* yazar, ne yaptığını değil.
  Örnek: `// KURAL: Tutar 1.250,00 ₺ biçiminde gösterilir — binlik nokta, kuruş virgül.`
- Her veri işleminde: yetki (`manage_options`) + nonce + `sanitize` + `esc_*` + `$wpdb->prepare`.
- Tarih ekranda `gg.aa.yyyy`, veritabanında `yyyy-aa-gg`. Tutar `1.250,00 ₺`.
- Arama/sıralama Türkçe uyumludur (`YP_Bicim::katla`, `utf8mb4_turkish_ci`).
- **Para yazan her işlem** `YP_Ekran::tek_islemde( $is, $hata_hedefi )` (veya `YP_Veri::tek_islemde`)
  içinde yapılır; `$wpdb` yazmaları `YP_Veri::yazildi_mi()` ile denetlenir, satırlar
  `YP_Veri::hareket_kilitli()` ile kilitlenip yeniden doğrulanır. İşin içinde yönlendirme/exit olmaz;
  mesajlar commit sonrası yazılır.
- **JS'te `innerHTML` ile kullanıcı verisi basılmaz** — DOM API + `textContent` kullanılır.
- Makbuz numarası **yoktur**; makbuz tahsil edilen satır kimlikleriyle açılır (`YP_Ekran::makbuz_url()`).
- Fotoğrafta en/boy alt sınırı ve kırpma **eklenmez**; gerçek resim doğrulaması ve dosya boyutu sınırı kalır.

## Ekran tasarım formülü (hepsinde aynı)

```
<div class="uyg">
  <header class="ekran-serit">   ← simgeli düğme grupları (serit_grubu_ciz), varsayılan gizli
  <div class="arac-cubugu">      ← ince tek satır: ☰ Ana menü · ekran adı · özet · birincil düğmeler · ok · kilit
  <div class="uyg-govde sabit|kaydir">  ← içerik
```

- `YP_Ekran::uyg_basla( $baslik, $aktif, 'sabit'|'kaydir', $serit_callable, $ad_sagi_html, $govde_ek )`
- `$govde_ek` içinde **`ust-yok`** varsa lacivert üst çubuk çizilmez ve `YP_Ekran::arac_cubugu()`
  kullanılır (tüm ekranlarda böyledir). `$ad_sagi` **false** verilirse ekran kendi satırını kurar
  (aday listesi: `ust-yok liste-ekrani`). Aday kartı/ödeme ekranında bu görevi koyu `ekran-ad` şeridi üstlenir.
- **Araç şeridi kapalı başlar.** Sağ üstteki ok (`YP_Ekran::serit_anahtari()`) `body.serit-acik`
  sınıfını açıp kapatır; tercih `localStorage['yp-serit']` içinde tutulur. Şerit düğmelerinden
  `serit-birincil` sınıflı olanlar, şerit kapalıyken JS ile koyu ad şeridine kopyalanır
  (kendi `<form>`'u içinde olan düğmeler kopyalanmaz).
- Ana menüde yedi karo durur; nadir ekranlar (takip listeleri, referanslar, süzgeçler)
  şeritten veya Tanımlar ekranından açılır. Karolar 3×5 yuvalı ızgaradadır; karoya uzun basınca
  düzen kipi açılır, karolar sürüklenip taşınır/yer değiştirir (`localStorage['yp-karo-yerlesim-3']`).
- **Kasa ve aday kartı bu formülün dışındadır:** ikisinde de açılır şerit yoktur. Kasada tek ince
  `kasa-serit` (Yeni Gelir/Gider · Para Transferi · Kaydet · Düzenle · Sil · Tarih Aralığı · Önceki/
  Sonraki Gün · Kasa Raporları), aday kartında koyu `ekran-ad` şeridi (Ödeme Bilgileri · Aday Ara ·
  Kaydet · Sil) kullanılır.
- `sabit`: sayfa hiç kaymaz, tablolar kendi içinde kayar. `kaydir`: gövde tek parça kayar.
- Masaüstü ve 1280×720'de **sayfa taşması sıfır olmalı**; telefonda (≤900px) kilit kalkar,
  bölümler alt alta dizilir.
- Menüden açılan ekran **aynı sekmede** açılır; her ekranda "Ana Menü" düğmesi vardır;
  orta tuşla tıklama yeni sekmede açar (tarayıcının doğal davranışı, `<a href>` kullanıldığı için).
- Listede tek tık satırı seçer + fotoğrafı gösterir, çift tık kartı açar.

## Yerel test ortamı

```powershell
# Sunucu (zaten açıksa tekrar başlatma)
php -S localhost:8765 -t "C:\Users\HP\Desktop\yuzyil-panel\test-ortami\wordpress" "C:\Users\HP\Desktop\yuzyil-panel\gelistirme\router.php"
```

- Giriş: `http://localhost:8765/?yerel_giris=yonetici` (yalnızca localhost'ta çalışan mu-plugin;
  panel kilidini de açar). Panel adresi: `http://localhost:8765/panel/`.
- Paketleme: `powershell -File gelistirme\paketle.ps1` → `dagitim\yuzyil-panel-<sürüm>.zip`
  (sürüm `yuzyil-panel.php` başlığından okunur; yeni paket öncesi sürümü yükselt).
- Doğrulama: `php gelistirme\dogrulama.php` (bakiyeler), `php gelistirme\olcum.php` (sorgu/süre),
  `php gelistirme\transaction-testi.php` (DB yükseltmesi, transaction geri alma, iade sınırı).
  Bu betikler `test-ortami\wordpress` klasöründen çalıştırılır.
- Test ortamı yoksa: `php gelistirme\kur-test.php` (WordPress + SQLite kurar),
  `php gelistirme\etkinlestir.php`, `php gelistirme\ornek-veri.php` (uydurma veri).
- Kod değiştirince `php -l` ile tüm dosyaları kontrol et, sonra ekranları tara (bkz. PROJE-DURUMU.md).
- PowerShell ile dosya yazarken **BOM eklenmemeli** (`[System.IO.File]::WriteAllText` + `UTF8Encoding $false`);
  BOM, PHP çıktısının başına geçip sayfayı bozar.
