# Yüzyıl Panel — Kurulum Talimatı

Bu talimat teknik bilgi gerektirmez. Adımları sırayla uygulayın; her adımda nereye tıklayacağınız yazıyor.
Tamamı yaklaşık 20 dakika sürer.

Elinizde olması gerekenler:
- WordPress sitenizin yönetici kullanıcı adı ve şifresi
- `yuzyil-panel-1.5.0.zip` dosyası (bilgisayarınızda, açmadan, zip hâlinde dursun)

---

## Adım 1 — Önce yedek alın (5 dakika)

1. Hosting firmanızın panelini açın (cPanel, Plesk veya firmanızın kendi paneli).
2. "Yedekleme" / "Backup" bölümünü bulun ve **tam yedek** alın. Yedeğin tamamlanmasını bekleyin.
3. Yedek alamıyorsanız hosting firmanıza "sitemin tam yedeğini alır mısınız" diye yazın.

Bu adım zorunlu değil ama atlamayın: bir şey ters giderse siteyi geri döndürmenin en kolay yolu budur.

---

## Adım 2 — Eklentiyi yükleyin (3 dakika)

1. Tarayıcıda sitenizi açın ve yönetim paneline girin: `siteadresim.com/wp-admin`
2. Sol menüden **Eklentiler** → **Yeni Ekle** seçin.
3. Sayfanın üstündeki **Eklenti Yükle** düğmesine tıklayın.
4. **Dosya Seç** deyip bilgisayarınızdaki `yuzyil-panel-1.5.0.zip` dosyasını seçin.
5. **Şimdi Yükle** düğmesine tıklayın ve bitmesini bekleyin.
6. Ekranda çıkan **Eklentiyi Etkinleştir** düğmesine tıklayın.

Sol menüde bir şey görünmez; bu normaldir. Panel ayrı bir adreste çalışır.

---

## Adım 3 — Panelin adresini belirleyin (2 dakika)

1. Yönetim panelinde sol menüden **Ayarlar** → **Yüzyıl Panel** seçin.
2. **Panel adresi** kutusuna tahmin edilmesi zor bir adres yazın.
   - İyi örnek: `yonetim-x7k2` veya `merkez-kayit-48`
   - Kötü örnek: `panel`, `admin`, `yonetim` (bunlar kolay tahmin edilir)
   - Yalnızca küçük harf, rakam ve tire kullanın. Türkçe harf ve boşluk kullanmayın.
3. **Kaydet** düğmesine tıklayın.
4. Sayfanın üstünde panelin tam adresi görünecek. Bu adresi bir yere not edin ve **kimseyle paylaşmayın**.

---

## Adım 4 — Panele ilk giriş ve şifre belirleme (3 dakika)

1. Not ettiğiniz panel adresini tarayıcıda açın (örnek: `siteadresim.com/yonetim-x7k2`).
2. Karşınıza "Panel şifresi oluşturun" ekranı gelecek.
3. **Kullanıcı adı** kutusunda `admin` yazıyor; isterseniz değiştirin.
4. **Yeni şifre** kutusuna en az 10 karakterlik, başka hiçbir yerde kullanmadığınız bir şifre yazın.
   - İyi örnek: `Merkez.2026!Kayit`
   - Şifreyi telefonunuzdaki not defterine değil, kâğıda yazıp kasada saklayın ya da bir şifre yöneticisi kullanın.
5. Alt kutuya aynı şifreyi tekrar yazın ve **Şifreyi kaydet** düğmesine tıklayın.
6. Ana menü açılacak. Kurulumun en önemli kısmı bitti.

**Şifreyi unutursanız:** Yönetim panelinde **Ayarlar → Yüzyıl Panel** sayfasındaki
**"Panel şifresini sıfırla"** düğmesine tıklayın. Panel bir sonraki açılışta yeni şifre isteyecek.

---

## Adım 5 — Fotoğraf klasörünün korumasını test edin (2 dakika)

Aday fotoğrafları, internetten doğrudan açılamayacak korumalı bir klasörde saklanır. Bunu kontrol edelim:

1. **Ayarlar → Yüzyıl Panel** sayfasını açın.
2. Sayfanın altındaki **Korumayı test et** düğmesine tıklayın.
3. Çıkan yazıya göre:
   - **"Fotoğraf klasörü korunuyor"** → her şey yolunda, sonraki adıma geçin.
   - **"DİKKAT: Fotoğraf klasörü dışarıdan açılabiliyor"** → aşağıdaki metni hosting firmanıza gönderin.
   - **"Test yapılamadı"** → aşağıdaki metni yine hosting firmanıza gönderin, kontrol etsinler.

Hosting firmasına göndereceğiniz metin:

> Merhaba, sitemde `wp-content/uploads/` klasörü altında adı `yp-korumali-` ile başlayan bir klasör var.
> Bu klasördeki dosyalara tarayıcıdan doğrudan erişimin kapatılmasını istiyorum (sunucu Nginx ise
> `.htaccess` çalışmıyor olabilir). Klasörün içeriği yalnızca site üzerinden, yetkili kullanıcıya
> gösterilecek. Gerekli engellemeyi yapabilir misiniz?

---

## Adım 6 — Kendi bilgilerinizi girin (10 dakika)

Panelde ana menüden **Tanımlar ve Ayarlar**'a girin. Solda üç başlıklı bir menü göreceksiniz:
**Tanımlar**, **Kurum ve Ayarlar**, **Referanslar**. Bölümleri sırayla doldurun:

1. **İşlem Türleri:** Yaptığınız psikoteknik işlemleri ve standart ücretlerini yazın.
   "Geçerlilik (ay)" alanına yazdığınız süre, rapor tarihine eklenerek geçerlilik bitişini otomatik hesaplar
   (örneğin SRC için 60 ay). Bu liste, aday kartındaki **İşlem Bilgileri** kutusunda çıkar.
2. **Gider Kalemleri:** Kira, elektrik, muhasebe, referans ödemesi gibi başlıklar hazır gelir; kendinize göre düzenleyin.
3. **Gelir Kalemleri:** Aday dışı gelirleriniz varsa ekleyin.
4. **Özel Kodlar:** Adayları gruplamak isterseniz (örneğin "Kurumsal", "İnternetten") buradan ekleyin.
5. **Evrak Listesi:** Adaydan istediğiniz evraklar (kimlik fotokopisi, ehliyet fotokopisi, fotoğraf).
   *Not: Aday kartı sadeleştirildiği için evrak işaretleme penceresinin düğmesi şu an kartta görünmüyor;
   liste burada tanımlı kalır, istenirse düğme geri eklenir.*
6. **Hesaplar (Kasa / Banka):** Paranın durduğu yerleri girin.
   - "Nakit Kasa" ve bankalarınızı yazın.
   - **Açılış bakiyesi** kutusuna, panele geçtiğiniz gün o hesapta bulunan parayı yazın.
   - **Açılış tarihi** olarak panele geçiş gününüzü seçin.
   - Bu iki bilgi doğru girilirse kasa bakiyeleri ilk günden itibaren doğru çalışır.
7. **Kurum Bilgileri:** Kurum adı, adres ve telefon (makbuzun üstünde çıkar), **web sitesi adresi**
   (ana menüdeki "Web Sitesi" düğmesi buraya gider), makbuz alt notu, "vadesi yaklaşan" kaç gün sayılsın,
   listelerde sayfa başına kaç kayıt gösterilsin.
8. **Referanslar:** Aday getiren sürücü kurslarını ve kişileri buradan ekleyin ("+ Yeni Referans").
   Listedeki bir satıra tıklayınca o referansın kartı açılır (ciro, getirdiği adaylar, referansa yapılan ödemeler).

---

## Adım 7 — Deneme kaydı yapın (5 dakika)

Sistemi tanımak için sahte bir kayıt açıp sonra silin:

1. Ana menüde **Yeni Aday Kaydı** karosuna tıklayın.
2. Adı ve soyadı yazın (örneğin DENEME KAYIT), telefon girin.
3. Ortadaki **İşlem Bilgileri** kutusundan bir işlem türü seçin (ör. "SRC Psikoteknik").
4. Sağ üstteki **Kaydet** düğmesine tıklayın. Aday numarası verilir ve seçtiğiniz türde bir kayıt açılır.
5. Üstteki **Ödeme Bilgileri** düğmesine tıklayın → **Borç Ekle** ile ücreti yazın, sonra **Ödeme Yap**
   ile tahsil edin (ödeme türünü ve hesabı seçin).
6. Aynı ekranda tahsil edilen satırın yanındaki **Yazdır** bağlantısına tıklayıp makbuzun görüntüsünü kontrol edin.
7. Aday kartına dönüp üstteki **Sil** düğmesiyle kaydı silin.
8. Ana menü → **Silinenler** → **Kalıcı sil** ile tamamen kaldırın.

Her şey beklediğiniz gibi çalışıyorsa panel kullanıma hazır demektir.

---

## Panelde gezinme — bilinmesi gerekenler

1. **Araç şeridi normalde gizlidir.** Aday listesi, takip, raporlar gibi ekranlarda **sağ üst köşedeki
   aşağı ok düğmesine** basarsanız simgeli araç şeridi aşağı doğru açılır; tekrar basarsanız kapanır.
   Tercihiniz o tarayıcıda hatırlanır. **Kasa ve aday kartında açılır şerit yoktur** — bütün düğmeler
   zaten en üstteki tek şeritte durur.
2. **Ana menüde yedi karo vardır:** Yeni Aday Kaydı, Kasa İşlemleri, Aday Listesi, Raporlar,
   İşlem Günlüğü, Silinenler, Tanımlar ve Ayarlar. Takip listeleri, borçlu/geciken süzgeçleri
   ve diğer ekranlar araç şeridinden (sağ üstteki ok) açılır.
3. **Karoların yerini kendiniz belirleyebilirsiniz:** bir karoya yarım saniye basılı tutun; karolar
   hafifçe sallanmaya başlar (düzen kipi). Karoyu sürükleyip boş bir yere bırakın ya da başka bir karonun
   üzerine bırakın — ikisi yer değiştirir. Bitince **Bitti** düğmesine basın; dizilim o tarayıcıda saklanır.
   **Varsayılan dizilim** düğmesi eski sıraya döndürür.
4. Bir karoya veya şerit düğmesine tıkladığınızda ekran **aynı sekmede** açılır. Her ekranın sol üstünde
   **Ana menü** düğmesi vardır; oradan geri dönersiniz.
5. Bir ekranı **ayrı sekmede** açmak isterseniz karoya **farenin orta tuşuyla** (tekerleğe basarak) tıklayın.
6. **Aday listesinde bir satıra bir kez tıklamak** satırı seçer ve solda adayın fotoğrafını gösterir.
   Aday kartını açmak için satıra **çift tıklayın**. Borcu kapanmış adaylarda bakiye hücresi yeşil
   **ÖDENDİ** olarak görünür.
7. Bilgisayarda ekranlar tarayıcı penceresine sığacak şekilde tasarlanmıştır; sayfa aşağı kaymaz.
   Yalnızca uzun listeler ve tablolar kendi içinde kaydırılır — tıpkı masaüstü programdaki gibi.
   Telefon ve tablette bu kilit kalkar: bölümler alt alta dizilir ve sayfa normal şekilde kaydırılır.

---

## Günlük kullanım — kısa özet

- **Yeni aday geldiğinde:** Ana menü → Yeni Aday Kaydı. Bilgileri girin, **İşlem Bilgileri** kutusundan
  işlem türünü seçin, **Kaydet** deyin. Sonra **Ödeme Bilgileri** ekranından ücreti yazıp tahsil edin
  ve makbuzu yazdırın.
- **Fotoğraf:** Aday kartında fotoğrafın altındaki **Fotoğraf değiştir** düğmesi. Bilgisayar kamerasıyla
  çekebilir veya dosya seçebilirsiniz. Telefondan girdiğinizde doğrudan telefon kamerası açılır.
- **Kaydın durumu:** Aday kartında sol alttaki **Durumu** kutusu — ARŞİV, TAMAMLANDI, İADE, YARIDA KALDI.
  "Arşiv" seçilen kayıt aday listesinde varsayılan olarak görünmez.
- **Fatura notu:** Aday kartında "KİMLİK BİLGİLERİ" başlığının yanındaki siyah alana fatura tarihini ve
  şirket unvanını yazabilirsiniz; yazılar sarı görünür ve Kaydet ile saklanır.
- **Aynı kişiye ikinci kayıt:** Aynı TC ile yeni kayıt açmak serbesttir (yıllar sonraki yeni psikoteknik).
  Kartın altındaki **Adaya Ait Başka Kayıtlar** listesinde kişinin diğer kayıtları/kartları görünür;
  satıra tıklayınca o kayda geçersiniz.
- **Taksitli satış:** Aday kartı → **Ödeme Bilgileri** → **Ödeme Planı**.
- **Para tahsil ettiğinizde:** Aday kartı → **Ödeme Bilgileri** → **Ödeme Yap**. Tutar en eski borçtan
  başlayarak kapanır; kısmen ödenen borç, ödenen ve kalan olarak iki satıra ayrılır.
- **Adaya borç eklemek:** Aday kartı → **Ödeme Bilgileri** → **Borç Ekle**.
- **Gelir/gider girdiğinizde:** Ana menü → Kasa → **Yeni Gelir / Gider** (pencerenin üstündeki "Tür"
  kutusundan gelir mi gider mi olduğunu seçersiniz).
- **Yanlış girilen kasa kaydını düzeltmek:** Kasa listesinde satıra bir kez tıklayıp seçin, üstteki
  **Düzenle** düğmesine basın. Tür, hesap, tutar, tarih, kalem ve açıklamayı düzeltebilirsiniz.
  Silmek için aynı şekilde seçip **Sil** deyin.
- **Bankaya para yatırdığınızda:** Kasa → **Para Transferi**.
- **Başka bir günü görmek:** Kasa → **Tarih Aralığı** (takvimden seçilir) ya da **Önceki Gün / Sonraki Gün**.
- **Gün sonunda:** Kasa ekranının sağındaki **KASA** kutusu her hesabın güncel bakiyesini,
  üstteki **Dünden Devir** ve **Bugün** kutuları da günün hareketini gösterir.
- **Sürücü kurslarına borç listesi göndermek:** Kasa → **Kasa Raporları** → borçlu kursları işaretleyin,
  isterseniz tarih aralığı verin → **Borç listesi hazırla**. Yeni sekmede yazdırılabilir liste açılır.
- **Alacak takibi:** Ana menü → Aday Listesi → araç şeridindeki **Borçlular** / **Gecikenler** süzgeçleri;
  ayrıntılı listeler için **Takip Listeleri**.
- **Referanslar (kimden geldi, ciro, referansa ödeme):** Ana menü → **Tanımlar ve Ayarlar** → Referanslar.
- **Aylık durum:** Ana menü → Raporlar → Aylık Gelir-Gider Özeti.

---

## Sık karşılaşılan durumlar

**Panel adresi "Sayfa bulunamadı" diyor.**
Bu normaldir: panel yalnızca WordPress'e yönetici olarak giriş yapmış kişiye açılır. Önce
`siteadresim.com/wp-admin` adresinden giriş yapın, sonra panel adresini açın.

**Panel şifresini unuttum.**
Yönetim paneli → Ayarlar → Yüzyıl Panel → "Panel şifresini sıfırla".

**"Çok fazla hatalı deneme" yazıyor.**
Güvenlik için 5 hatalı denemeden sonra 15 dakika beklemek gerekir. Süre dolunca tekrar deneyin.

**Fotoğraf yüklenmiyor.**
Dosyanın resim olduğundan emin olun (JPG veya PNG). Çok büyük fotoğraflar tarayıcıda otomatik küçültülür;
yine de hata alırsanız hosting firmanızdan yükleme boyutu sınırını (upload_max_filesize) 8 MB'a çıkarmasını isteyin.

**Excel dosyası açılmıyor.**
İndirilen dosyayı Excel veya LibreOffice ile açın. Sunucunuzda zip desteği yoksa panel otomatik olarak
CSV üretir; CSV'yi Excel'de açarken "noktalı virgülle ayrılmış" seçeneğini kullanın.

**Eklentiyi güncellemek istiyorum.**
Yeni zip dosyasını Adım 2'deki gibi yükleyin; WordPress "eklentiyi değiştir" seçeneği sunacaktır.
Verileriniz silinmez.

**Eklentiyi devre dışı bırakırsam verilerim silinir mi?**
Hayır. Devre dışı bırakmak hiçbir veriyi silmez. Eklentiyi tamamen silseniz bile veriler durur
(Ayarlar → Yüzyıl Panel sayfasındaki silme seçeneği işaretli değilse).

---

## Eklentinin dışında yapmanız gereken güvenlik ayarları

Bunlar panelin kendi güvenliğini değil, **sitenizin** güvenliğini ilgilendirir. Panel ne kadar korunaklı olursa
olsun, WordPress yöneticinizin şifresi ele geçerse veriler risk altına girer.

1. **HTTPS (kilit işareti) zorunlu olsun.**
   Sitenizin adresi `https://` ile başlamalı. Başlamıyorsa hosting firmanızdan ücretsiz SSL sertifikası
   (Let's Encrypt) isteyin ve "HTTP'den HTTPS'e yönlendirme" açtırın.

2. **WordPress yönetici şifrenizi güçlendirin.**
   En az 14 karakter, başka sitede kullanılmayan bir şifre. Aynı şifreyi panelde kullanmayın.

3. **İki adımlı doğrulama (2FA) kurun.**
   Eklentiler → Yeni Ekle → "Two Factor" veya "WP 2FA" arayın, kurun ve telefonunuzdaki
   Google Authenticator / Microsoft Authenticator uygulamasıyla eşleştirin. Böylece şifreniz çalınsa bile
   giriş yapılamaz.

4. **Giriş denemelerini sınırlayın.**
   "Limit Login Attempts Reload" gibi bir eklenti kurun. Şifre deneyerek giriş saldırılarını durdurur.

5. **Giriş adresini değiştirin.**
   "WPS Hide Login" eklentisiyle `wp-admin` / `wp-login.php` adresini değiştirin (örneğin `giris-4821`).
   Yeni adresi not edin; unutursanız siteye giremezsiniz.

6. **Otomatik yedekleme kurun.**
   "UpdraftPlus" gibi bir eklentiyle günlük veritabanı + haftalık dosya yedeği alın ve yedekleri
   Google Drive gibi site dışında bir yere gönderin. Yedeğin gerçekten oluştuğunu ayda bir kontrol edin.

7. **Gereksiz kullanıcıları kaldırın.**
   Kullanıcılar sayfasında yalnızca ihtiyaç duyulan hesaplar kalsın. Panele yalnızca **Yönetici**
   yetkisindeki hesaplar girebilir; başka birine yönetici yetkisi vermeyin.

8. **WordPress, tema ve eklentileri güncel tutun.**
   Ayda bir güncellemeleri kontrol edin. Güncellemeden önce yedek alın.

9. **Ortak bilgisayarda çalışıyorsanız.**
   Masadan kalkarken panelin üst çubuğundaki **Paneli kilitle** düğmesine basın.
   Kayıp veya çalıntı bir cihaz varsa Tanımlar → Panel Şifresi → **Tüm cihazlarda kilitle** deyin.

10. **Kişisel veri sorumluluğu (KVKK).**
    Panelde TC kimlik no, telefon, adres ve fotoğraf gibi kişisel veriler bulunur. Bu verileri yalnızca
    gerekli kişilerin görmesini sağlayın, yedekleri şifreli saklayın ve saklama sürenizi belirleyin.
    Artık gerekmeyen kayıtları Silinenler ekranından kalıcı olarak silin.
