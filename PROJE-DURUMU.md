# Yüzyıl Panel — proje durumu (24.09.2026, sürüm 1.5.0, DB sürüm 4)

Masaüstündeki "Wenntec ProX" aday/ödeme takip programının yerini alacak WordPress eklentisi.
Kapsam: **psikoteknik aday takibi + kasa**. MEB/MEBBİS arayüzü, şubeler arası aktarım, MEB sınav
ve ders programı, SRC aktarımı, AnyDesk gibi başlıklar **kapsam dışıdır**.

Çalışma kuralları: `CLAUDE.md`. Kullanıcıya verilen kurulum metni: `dagitim/KURULUM.md`.

---

## 1. Klasör yapısı

```
C:\Users\HP\Desktop\yuzyil-panel\
├─ CLAUDE.md               ← yeni oturum için kurallar
├─ PROJE-DURUMU.md         ← bu dosya
├─ eklenti\yuzyil-panel\   ← ESAS KAYNAK KOD (zip'e giren tek klasör)
├─ gelistirme\             ← yardımcı betikler (zip'e girmez)
├─ test-ortami\wordpress\  ← yerel WordPress + SQLite deneme sitesi (zip'e girmez, ~240 MB)
└─ dagitim\                ← yuzyil-panel-1.5.0.zip + KURULUM.md
```

### Eklenti dosyaları

| Dosya | İşi |
|---|---|
| `yuzyil-panel.php` | Başlık, sabitler (`YP_SURUM` 1.5.0, `YP_DB_SURUM` 4), etkinleştirme kancası, yönlendiricinin başlatılması |
| `includes/class-yp-cekirdek.php` | Tablo adları, ayarlar, `panel_url()`, `log()`, sürüm anahtarlı önbellek (`onbellek`, `veri_degisti`) |
| `includes/class-yp-kurulum.php` | 8 tablonun `dbDelta` şeması, eski yapı temizliği (`eski_yapiyi_temizle`), varsayılan tanım/hesap tohumlaması, korumalı fotoğraf klasörü |
| `includes/class-yp-yonlendirici.php` | Adres yakalama, yönetici kontrolü, başlıklar, ekran dosyalarının tembel yüklenmesi, `foto` / `logo` / `ara` uçları |
| `includes/class-yp-kilit.php` | Panel şifresi (ikinci kilit), cihaz anahtarı, 5 hatada 15 dk kilit, giriş ekranı + "Site ana sayfası" bağlantısı |
| `includes/class-yp-guvenlik.php` | Nonce, giriş doğrulama yardımcıları (`metin`, `tamsayi`, `secim`, `tarih`) |
| `includes/class-yp-bicim.php` | Türkçe tarih/tutar biçimleri, `katla` (arama), `yaziyla`, TC/telefon doğrulama |
| `includes/class-yp-veri.php` | Tanımlar, hesaplar, referanslar, aday CRUD, **transaction yardımcıları** (`tek_islemde`, `yazildi_mi`, `hareket_kilitli`, `kilit_eki`) ve `YP_Veri_Hatasi` / `YP_Islem_Engeli` sınıfları |
| `includes/class-yp-hesap.php` | **Para mantığının tamamı**: borç/ödenen/bakiye, `iade_edilebilir()`, kasa defteri, dönem özeti, taksite bölme |
| `includes/class-yp-foto.php` | Fotoğraf yükleme, orantılı küçültme (kırpma yok), yetkili sunum, silme |
| `includes/class-yp-excel.php` | Kendi XLSX yazıcısı (ZipArchive), desteklenmiyorsa CSV |
| `includes/class-yp-ekran.php` | Sayfa iskeleti, araç çubuğu (`arac_cubugu`), şerit (`uyg_basla`), `tek_islemde` sarmalayıcısı, `makbuz_url()`, form yardımcıları, SVG simgeler, karolar |
| `includes/class-yp-ayarlar-sayfasi.php` | wp-admin → Ayarlar → Yüzyıl Panel (slug, koruma testi, şifre sıfırlama) |
| `includes/ekranlar/*.php` | Ekranlar (aşağıdaki tablo) |
| `varliklar/panel.css` `panel.js` `minilogo.png` | Tasarım sistemi, arayüz davranışları, kurum logosu |
| `uninstall.php` | Ayar açık değilse **veri silinmez** |

### Ekranlar

| Dosya | Adres | Notu |
|---|---|---|
| `class-yp-ekran-ana-sayfa.php` | `/panel/` | Gizlenebilir araç şeridi + minimal sekme çubuğu (Web Sitesi · İşlem Günlüğü) + hızlı arama + 7 karo + ince durum çubuğu; solda logo ve "Otomasyon Sistemi" bloğu. "Y" rozeti paneli kilitler. Karolar **3×5 yuvalı** ızgaradadır (soldaki sütun varsayılan olarak boştur): karoya uzun basınca **düzen kipi** açılır, karolar sürüklenip boş yuvalara taşınır ya da başka bir karonun üzerine bırakılarak **yer değiştirir**; sürüklerken hedef karo sarı çerçeveyle işaretlenir, boşluğa bırakılırsa en yakın yuvaya oturur (`localStorage['yp-karo-yerlesim-3']`; anahtar sütun sayısını taşır) |
| `class-yp-ekran-adaylar.php` | `?ekran=adaylar` | Yoğun liste (No, Tarih, TC No, Adı, Soyadı, Bakiye, İşlem, Referans, GSM, XXY), sürüklenebilir sütunlar, solda dar fotoğraf paneli, canlı hızlı arama (JSON). Borcu kapanan satırda bakiye yerine yeşil **ÖDENDİ** kutusu |
| `class-yp-ekran-aday.php` | `?ekran=aday&id=` | Aday kartı **masaüstü kursiyer kartının yerleşimindedir** (`kopya\Adaykarti.png` referansı, kırmızı çizilen alanlar hariç): açılır şerit yoktur, koyu ad şeridinde Ödeme Bilgileri · Aday Ara · Kaydet · Sil durur. Izgara: sol sütunda fotoğraf, altında aday no kutusu ve fotoğraf düğmesi (devamı boş), yanında iki satır boyunca Kimlik Bilgileri (cinsiyet dâhil) ve **altında borç/ödenen/bakiye kutusu**, ortada dar şeritte üstten alta İşlem · Referans · İletişim (İl/Gsm 1/Gsm 2), sağ sütunun tamamında Aday Notları, altta, borç kutusunun hemen sağından başlayan çerçevesiz **"Adaya Ait Başka Kayıtlar"** tablosu (Tarih · İşlem Adı · Rapor · Ücret · Durum) — adayın *açık olmayan* eski kayıtlarını **ve aynı TC ile açılmış diğer aday kartlarını** (`KART #no` rozetiyle) listeler, satıra tıklanınca o kayıt/kart açılır (`?ekran=aday&id=..&islem=..`), açık kart kapanır. "İşlem Bilgileri" bir seçim kutusudur (referans gibi): kartın işlem türü listeden seçilir, Kaydet'e basınca açık kayda yazılır (`kart_islem_id`); açık kayıt yoksa seçilen türde yeni kayıt açılır. Altındaki ince satır kaydın tarihini/geçerliliğini gösterir, "ayrıntı" bağlantısı işlem penceresini açar. Genel Bilgiler ve E-Mail alanı yoktur. "Kimlik Bilgileri" başlık şeridinde siyah zeminli, sarı yazılı bir not alanı vardır (fatura tarihi / şirket unvanı); veritabanında **`sari_not`** sütununda tutulur, en çok 150 karakter. `&sekme=odeme` ödeme ekranı (bakiye matrisi, satır bazlı "Yazdır") eski şeridini korur |
| `class-yp-ekran-aday-islem.php` | POST | Kart/işlem/ödeme/borç/iade/evrak/fotoğraf/arşiv/sil işleyicileri — **para işlemleri transaction'lı** |
| `class-yp-ekran-kasa.php` | `?ekran=kasa` | Gün defteri, sağda özet; gelir/gider/transfer transaction'lı. **Tek ince üst şerit** (`kasa-serit`): Yeni Gelir/Gider · Para Transferi · Kaydet · **Düzenle** · Sil · Tarih Aralığı · Önceki Gün · Sonraki Gün · Kasa Raporları. Açılır beyaz şerit, araç çubuğu ve tarih paneli yoktur. Liste sekmeleri (Tüm İşlemler/Gelir/Gider) sağdaki Kasa/Gelir/Gider üçlüsüyle aynı hizada. "Kaydet" açık pencerenin formunu gönderir; "Düzenle" ve "Sil" listede seçili satırda çalışır. Düzenleme yalnızca gelir/gider satırlarında açılır (tür, hesap, tutar, tarih, kalem, referans, açıklama) ve `kasa_duzenle` işlemi transaction içinde, satırı kilitleyip yeniden doğrulayarak yazar. Gün toplamı tablonun altındaki ince şerittedir (net tutar en sağda); ayrı kayıt sayacı yoktur |
| `class-yp-ekran-takip.php` | `?ekran=takip` | 7 liste, ekranda 150 satır, Excel |
| `class-yp-ekran-raporlar.php` | `?ekran=raporlar` | Aylık gelir-gider, tahsilat, borç/bakiye + Excel çıktıları |
| `class-yp-ekran-referanslar.php` | `?ekran=referanslar&id=` | Referans **kartı** (ciro/ödenen, işlemler, referansa ödeme — transaction'lı) ve `&yeni=1` yeni kayıt formu. Listesi artık Tanımlar ekranındadır; `?ekran=referanslar` (idsiz) oraya yönlendirir |
| `class-yp-ekran-tanimlar.php` | `?ekran=tanimlar` | **Tanımlar, Kurum ve Ayarlar, Referanslar tek ekranda.** Solda üç başlıklı bölüm menüsü: *Tanımlar* (işlem türleri, gider/gelir kalemleri, özel kodlar, evrak, hesaplar), *Kurum ve Ayarlar* (kurum bilgileri, panel şifresi), *Referanslar* (arama + ciro/ödenen tablosu, satır referans kartını açar). Sağda yalnızca seçili bölüm görünür |
| `class-yp-ekran-gunluk.php` | `?ekran=gunluk` | Kim ne yaptı |
| `class-yp-ekran-silinenler.php` | `?ekran=silinenler` | Geri alma / kalıcı silme (transaction'lı) |
| `class-yp-ekran-yazdir.php` | `?ekran=yazdir&tur=makbuz&id=12,15` | **Numarasız** tahsilat makbuzu; satır kimlikleriyle açılır. `&tur=kurs_borc&ref[]=…` **sürücü kursu borç bakiye listesi** (Kasa Raporları penceresinden; kurs başına aday listesi, ara toplam, genel toplam ve yazıyla tutar) |

---

## 2. Veri modeli

Tablolar (`wp_yp_` ön eki, `utf8mb4_turkish_ci`):
`adaylar`, `islemler`, `hareketler`, `hesaplar`, `referanslar`, `gorusmeler`, `tanimlar`, `log`.
(`sayaclar` tablosu ve `hareketler.makbuz_no` sütunu 1.4.0'da kaldırıldı; DB sürüm 3 yükseltmesi
eski kurulumlarda bunları otomatik siler.)

- **Aynı TC ile birden çok aday kartı açılabilir** (yıllar sonraki yeni psikoteknik kaydı); kayıtta uyarı
  verilmez, kartlar birbirini "Adaya Ait Başka Kayıtlar" listesinde gösterir (`YP_Ekran_Aday::diger_kartlar()`).
- **hareketler** hem aday borç/ödemelerini hem kasa gelir/gider/transferlerini tutar.
  Ayrım `kayit_turu` (ADAY/GELIR/GIDER/TRANSFER) ve `durum` (ODENDI/ODENMEDI/IADE) ile yapılır.
  Sorgu koşulları `YP_Hesap::BORC_KOSULU` ve `YP_Hesap::KASA_KOSULU` sabitlerindedir.
- Silme **yumuşaktır** (`silindi = 1`); kalıcı silme yalnızca "Silinenler" ekranından.
- Fotoğraflar Medya Kütüphanesine **girmez**: `uploads/yp-korumali-<rastgele>/` altında,
  `.htaccess` + `web.config` + `index.php` ile korunur; yalnızca `?ekran=foto` ucundan, yetki
  kontrolünden geçerek sunulur.

### Masaüstünden bire bir taşınan para kuralları

1. Aday bakiyesi = ödenmiş + ödenmemiş borç toplamı − ödenen toplam.
2. "KENDİSİ YATIRDI" kaydı aday borcundan ve kasadan sayılmaz; makbuzu da yazdırılmaz.
3. Kasa devri: gün başı devir + gün içi giriş − çıkış = gün sonu devir.
4. **Ödeme Al** en eski vadeden başlayarak kapatır (FIFO); kısmen ödenen borç satırı ikiye
   bölünür: ödenen kısım `ODENDI`, kalan kısım `ODENMEDI` "Kısmi ödeme sonrası kalan".
5. Taksit planı `YP_Hesap::taksitlere_bol` ile kuruş farkı ilk taksite eklenerek bölünür.
6. **İade sınırı:** işlem seçiliyse o işlemin kendi tahsilatları (ödenen − o işlemin iadeleri);
   işlem seçilmezse adayın tümü — `YP_Hesap::iade_edilebilir()`.

### Para işlemlerinde transaction (1.4.0)

- Yazma yapan her para işlemi `YP_Veri::tek_islemde( callable )` içinde çalışır:
  `START TRANSACTION` → iş → `COMMIT`; hata/başarısız yazmada `ROLLBACK`.
- `YP_Veri::yazildi_mi()` her `$wpdb` yazmasının sonucunu denetler, `false` ise `YP_Veri_Hatasi` fırlatır.
- `YP_Islem_Engeli`, transaction içinde yeniden yapılan denetim işlemi durdurduğunda fırlatılır
  (örn. satır bu arada tahsil edilmiş) ve mesajı kullanıcıya gösterilir.
- Satırlar `YP_Veri::hareket_kilitli()` / `kilit_eki()` ile `FOR UPDATE` okunur (SQLite'ta eklenmez).
- Ekran tarafındaki sarmalayıcı: `YP_Ekran::tek_islemde( $is, $hata_hedefi )`.
- Transaction içinde **yönlendirme/exit yapılmaz**; mesajlar commit sonrası yazılır. Beklenmeyen
  exit olursa kapanışta `acik_islemi_geri_al()` çalışır.

---

## 3. Güvenlik modeli

- Panel `siteadresim.com/<slug>` adresindedir, slug eklenti ayarlarından değişir.
  Yönlendirme kuralı (rewrite) **eklenmez**; adres `parse_request` üzerinden yakalanır.
- Yönetici değilse hiçbir şey olmaz → WordPress'in **doğal 404**'ü görünür.
- Başlıklar: `noindex, nofollow`, `X-Frame-Options: DENY`, `Content-Security-Policy`,
  `Cache-Control: no-store`, `DONOTCACHEPAGE`.
- İkinci kilit: panel şifresi (`wp_hash_password`), cihaz anahtarı HMAC'lenmiş olarak
  kullanıcı metasında, çerez HttpOnly + SameSite=Lax, 5 hatalı denemede 15 dk bekleme.
  Şifre unutulursa wp-admin → Ayarlar → Yüzyıl Panel → "Panel şifresini sıfırla".
- Her POST: yetki + işleme özel nonce + temizleme; tüm sorgular `$wpdb->prepare`; tüm çıktılar `esc_*`.
- **Arayüzde `innerHTML` ile kullanıcı verisi basılmaz** (XSS): hızlı arama sonuçları ve liste
  fotoğraf paneli DOM API + `textContent` ile kurulur; adresler yalnızca aynı kaynaktan http(s) ise kabul edilir.
- Fotoğrafta gerçek resim doğrulaması (`wp_check_filetype_and_ext` + `getimagesize`) ve dosya boyutu
  sınırı vardır; **en/boy için alt sınır ve kırpma yoktur** (kullanıcı ~260 px hazır fotoğraf yükler).

---

## 4. Son doğrulama sonuçları

**Arayüz değişiklikleri (23.09.2026):** karo genişliği 260 px → `calc(260px - 2cm)`; ana menü sekme
çubuğunda yalnızca "Web Sitesi" (ayardaki `web_sitesi` adresi, yeni sekmede) ve "İşlem Günlüğü"
kaldı, düğmeler ve şerit inceltildi; aday kartından ana adı, baba adı, meslek, öğrenim, ehliyet
sınıfı/no, özel kod 2, e-posta, adres, sarı not alanları ile "Psikoteknik İşlemleri" bölümü
kaldırıldı, puntolar küçültüldü. Kaldırılan alanlar **veritabanında durur**: `aday_kaydet` artık
yalnızca formdan gerçekten gönderilen sütunları yazar (denendi — e-posta/adres/meslek/sarı not
kayıt sonrası korundu). Taşma ölçümü 1440×900, 1280×720 ve 375×812'de 0.

**İkinci tur (aynı gün):** karo ızgarası 2 → 3 sütun (sol sütun boş başlar), karo genişliği
`calc(260px - 2.5cm)`; aday listesinde borcu kapanan satırın bakiye **hücresi tamamen yeşil** ve
"ÖDENDİ" yazıyor; aday kartında kimlik+iletişim tek bölüm, aday no fotoğrafın altında, il/ilçe ve
"aktif kayıt"/"evrak tamam" kutuları kaldırıldı, kart şeridi (beyaz açılır şerit) kaldırılıp dört
düğme ad şeridine taşındı. **Bunun sonucu:** Evrak ve Görüşme pencerelerinin ve Önceki/Sonraki
gezinmesinin düğmesi kalmadı (`evrak_diyalogu`, `gorusme_diyalogu`, `komsular` metotları ileride geri
konmak üzere dosyada duruyor; POST işleyicileri de kayıtlı).

**Üçüncü tur — kart yerleşimi (referans görsel):** Kart, masaüstü kursiyer kartının ekran
görüntüsüne (`C:\Users\HP\Desktop\kopya\Adaykarti.png`, kullanıcı kırmızıyla istemediği alanları
çizmiş) göre yeniden dizildi. Karttaki alanlar: Durumu, Kayıt Tarihi, Aday No (kırmızı), TC, Adı,
Soyadı, Doğum Tarihi · İşlem Bilgileri (son işlem, sarı satır) · Referans (sarı) · Cinsiyet ·
İl, Gsm 1, E-Mail, Gsm 2 · Aday Notları · bakiye kutusu (borç kapanınca yeşil "ÖDENDİ") ·
Kayıtlı Olduğu İşlemler tablosu (satıra tıklayınca işlem penceresi açılır, başlıkta "+ Yeni İşlem").
Görsele uyarken önceki turlardan üç karar değişti: **Doğum Yeri kaldırıldı**, **İl ve E-Mail geri
geldi** (İlçe yok). Sayfa 1440×900 ve 1280×720'de hiç kaymıyor; bölüm içi kaydırma yalnızca işlem
listesinde. Kartta kimlik, işlem ve notlar tabloları aynı genişliktedir (315 px) ve aralarındaki
boşluklar eşittir.

**Dördüncü tur — kasa ekranı:** Açılır şerit, araç çubuğu ve "tarih/hesap aralığı" paneli kaldırıldı;
yerine tek ince üst şerit geldi (yukarıdaki ekran tablosuna bakın). "Aday Adına Ödemeler" sekmesi,
"Devreden (gün sonu)" kutusu ve transfer açıklama paragrafı silindi; "Hesap bazında" başlığı **KASA**
oldu (hesap süzgeci varken başında "Tüm hesaplar" bağlantısı çıkar). Gelir ve gider artık tek
pencerede girilir (`kasa_kayit` işlemi, tür seçimine göre `gelir_ekle`/`gider_ekle` çağırır).
Tarih aralığı penceresi tarayıcının kendi takvimini kullanır (`input type=date`). Kasa Raporları
penceresi borçlu sürücü kurslarını listeler (`YP_Ekran_Kasa::borclu_kurslar()`), seçilenler için
yazdırılabilir borç bakiye listesi açar; Excel ve ekran yazdırma da bu pencerededir.

**Beşinci tur — Tanımlar ve Ayarlar birleşimi:** Tanımlar, kurum ayarları ve referans listesi tek
ekranda toplandı (`?ekran=tanimlar`). Sol menü üç başlık altında bölümleri listeler, sağda seçili
bölüm çizilir. Referans listesi `YP_Ekran_Referanslar::istatistik_alt_sorgusu()` /
`odeme_alt_sorgusu()` (artık `public`) ile hesaplanır; referans **kartı** kendi ekranında kalır ve
`?ekran=referanslar` (idsiz) yeni birleşik ekrana yönlendirir. Yönlendirici `tanimlar` ekranında
referans dosyasını da yükler.

**1.4.0 (23.09.2026)** — `gelistirme/transaction-testi.php` ile: DB sürüm 3, `makbuz_no` sütunu ve
`sayaclar` tablosu kalktı; hata fırlatılan işte satırlar geri alındı; başarısız yazmada önceki satır da
geri alındı; başarılı iş kalıcı; iade sınırı işlem1 = 250, işlem2 = 900, aday geneli = 1150 (10/10 başarılı).
Ekrandan uçtan uca: borç ekleme → FIFO ödeme → numarasız makbuz yazdırma; bozuk/olmayan makbuz
kimlikleri reddedildi; işlem seçili iade aşımı doğru mesajla engellendi. `php -l` temiz, BOM yok.

**1.3.x ekran ölçümleri (1440×900):** aday listesi tablo alanı 811 px / 39 satır, aday kartı 823 px,
kasa 695 px, takip 721 px; taşma 1440×900 ve 1280×720'de 0, telefonda yatay taşma 0.
`olcum.php` (5.000 aday / 7.410 hareket): ana menü 1 sorgu 2 ms · aday listesi 9 sorgu 13 ms ·
aday kartı 15 sorgu 16 ms · kasa 9 sorgu 21 ms.

> Not: 1.4.0'da 22 ekranlık toplu tarayıcı taraması, yerel deneme sunucusu takıldığı için
> tekrarlanmadı; yeni oturumda sunucuyu başlatıp bu taramayı yapmak iyi olur.

---

## 4.1 Sürüm 1.5.0 — toparlama (24.09.2026)

**Veritabanı:** `adaylar.kayit_durumu` sütunu eklendi (DB sürüm 4); kart durumu ARŞİV / TAMAMLANDI /
İADE / YARIDA KALDI. "Arşiv" seçimi `arsiv = 1` yapar, diğerleri kaydı aktif bırakır.

**Silinen ölü kod:** `YP_Hesap::isaretli_tutar()`, `tahsilat_toplami()`, defterin kullanılmayan
yürüyen bakiyesi (hesap süzgecinde fazladan bir sorgu), `YP_Veri::ayni_tc()`, `YP_Bicim::tl_kisa()`,
`YP_Ekran::baslik()` / `onay()`, `YP_Ekran_Adaylar::kucuk_foto()`, `YP_Ekran_Aday::komsular()` ve
`yeni_islem_diyalogu()`, `YP_Ekran_Referanslar::liste()` (listesi Tanımlar ekranına taşındı),
CSS'te 44 kullanılmayan sınıf (eski aday kartı, kasa tarih paneli, karo ızgarası artıkları) —
panel.css 74,6 KB → 69 KB, 956 → 928 satır.

**Performans (5.001 aday / 7.416 hareket, aynı makine):**

| Ekran | Önce | Sonra |
|---|---|---|
| Ana menü | 8 sorgu · 9,4 ms | **5 sorgu · 4,1 ms** |
| Kasa (bugün) | 22 sorgu · 77,7 ms | **14 sorgu · 32,1 ms** |
| Kasa (30 gün) | 17 sorgu · 75,6 ms | **16 sorgu · 62,3 ms** |
| Kasa (tekrar açılış) | 10 sorgu · 36,3 ms | **9 sorgu · 20,2 ms** |

Kazanç: borçlu sürücü kursu toplamı (tüm adayları tarayan GROUP BY) artık sürüm anahtarlı
önbellekte (`YP_Ekran_Kasa::borclu_kurslar()`), yürüyen bakiye sorgusu kaldırıldı.

**Güvenlik/sağlamlık:** yazdırma ekranındaki `ref[]` parametresi artık skaler olmayan değerleri de
eler; kasa "Yeni gelir/gider" işleyicisi `$_POST` değiştirmek yerine kalem numarasını parametreyle
alıyor; üst şeritteki "Sil" formu `requestSubmit()` ile gönderiliyor (çift gönderim koruması devrede).

**Denetim:** 27 ekran adresi tek tek açıldı — hepsi HTTP 200, `wp-content/debug.log`'da eklentiden
tek bir uyarı/bildirim yok. `php -l` temiz, hiçbir dosyada BOM yok. `dogrulama.php` "tüm hesaplamalar
tutarlı", `transaction-testi.php` **11/11 başarılı** (yeni sütun denetimi eklendi).

## 5. Yapılacaklar

1. **Gerçek verinin aktarımı** (ayrı aşama, kullanıcı onayıyla): masaüstü `.bak` dosyasından
   adaylar, işlemler, hareketler, hesaplar, referanslar ve fotoğrafların panele taşınması.
   Aktarım betiği yazılırken açılış bakiyeleri de taşınmalı (makbuz sayacı artık yok).
2. 22 ekranlık toplu tarama + taşma ölçümünün 1.4.0 üzerinde tekrarlanması.
3. İsteğe bağlı temizlik (kullanıcı söylerse): `kopya` klasörü, LocalDB'deki `PROX_ANALIZ`
   kopyası, `dagitim` içindeki eski sürüm zip'leri ve yerel deneme sunucusu.
