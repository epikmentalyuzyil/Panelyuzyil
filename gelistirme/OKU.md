# Geliştirme betikleri (pakete girmez)

Bu klasör, panelin kendisini **yerel bir WordPress kurulumunda** çalıştırıp denemek içindir.
Kurulum paketine (`.zip`) girmez.

| Dosya | İşi |
|---|---|
| `router.php` | `php -S` için yönlendirici — `/panel/` gibi adresleri WordPress'e devreder |
| `kur-wordpress.php` | Boş bir WordPress kurar (yönetici: `yonetici`) |
| `etkinlestir.php` | Eklentiyi etkinleştirir, 8 tablonun kurulduğunu doğrular |
| `kilit-kur.php` | Panel şifresini bir kez kurar |
| `yerel-giris.php` | `mu-plugins/` içine konur; `?yerel_giris=yonetici` ile oturumu ve panel kilidini açar (yalnızca localhost) |
| `ornek-veri.php` | **Uydurma** aday/işlem/ödeme/kasa verisi üretir — gerçek müşteri verisi kullanılmaz |
| `kimlikler.php` | Tarama betiklerinin kullanacağı örnek kayıt numaralarını verir |
| `ekran-tarama.js` | 32 ekranı 1440×900, 1280×720 ve 375×812'de açar; HTTP durumu, JS hatası ve **taşma** ölçer, ekran görüntüsü alır |
| `onizleme-aktar.js` | Panelin gerçek HTML çıktısını tıklanabilir, salt okunur bir önizlemeye dönüştürür |
| `onizleme-denetim.js` | Dışa aktarılan önizlemenin her sayfasını açar; boş ekran, eksik dosya ve JS hatası arar |
| `sms-testi.js` | SMS akışını uçtan uca dener: süzgeç, seçim, sayaç, onay, imza koruması, gönderim |
| `yedek-testi.php` | Yedek al, veriyi boz, geri yükle; her tablonun birebir aynı geldiğini doğrular |
| `yedek-ekran-testi.js` | Yedekleme ekranını tarayıcıda dener: indirme, dosya yükleme, inceleme, geri yükleme |

## Sıra

```
php gelistirme/kur-wordpress.php
php gelistirme/etkinlestir.php
php gelistirme/kilit-kur.php
php gelistirme/ornek-veri.php
php -S localhost:8765 -t <wordpress-klasoru> gelistirme/router.php
```

Tarama ve önizleme için `node` ve `playwright-core` gerekir; tarayıcı yolu betiklerin içinde yazar.
