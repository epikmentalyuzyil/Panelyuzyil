// KURAL: Ekranlar gerçek tarayıcıda açılır — taşma ve JS hataları ancak böyle görülür.
const { chromium } = require('playwright-core');
const fs = require('fs');
const path = require('path');

const KOK = 'http://localhost:8765';
const PANEL = KOK + '/panel/';
const CIKTI = process.env.CIKTI || '/home/user/wordpress/ekranlar';
const KIMLIK = JSON.parse(process.env.KIMLIK || '{}');

const A = KIMLIK.aday || 1;
const B = KIMLIK.borclu || 1;
const R = KIMLIK.referans || 1;
const M = KIMLIK.makbuz || '1';

const EKRANLAR = [
  ['01-ana-menu', '', 'Ana Menü'],
  ['02-aday-listesi', '?ekran=adaylar', 'Aday Listesi'],
  ['03-aday-karti', `?ekran=aday&id=${A}`, 'Aday Kartı'],
  ['04-aday-borclu', `?ekran=aday&id=${B}`, 'Aday Kartı (borçlu)'],
  ['05-odeme-ekrani', `?ekran=aday&id=${B}&sekme=odeme`, 'Ödeme Bilgileri'],
  ['06-kasa', '?ekran=kasa', 'Kasa'],
  ['07-kasa-gelir', '?ekran=kasa&sekme=gelir', 'Kasa · Gelir'],
  ['08-kasa-gider', '?ekran=kasa&sekme=gider', 'Kasa · Gider'],
  ['09-takip-geciken', '?ekran=takip&liste=geciken', 'Takip · Geciken Ödemeler'],
  ['10-takip-bugun', '?ekran=takip&liste=bugun', 'Takip · Bugün Vadesi Gelenler'],
  ['11-takip-yaklasan', '?ekran=takip&liste=yaklasan', 'Takip · Vadesi Yaklaşanlar'],
  ['12-takip-gecerlilik', '?ekran=takip&liste=gecerlilik', 'Takip · Geçerliliği Dolacak Raporlar'],
  ['13-takip-evrak', '?ekran=takip&liste=evrak', 'Takip · Eksik Evrak'],
  ['14-takip-dogumgunu', '?ekran=takip&liste=dogumgunu', 'Takip · Doğum Günleri'],
  ['15-takip-cift', '?ekran=takip&liste=cift', 'Takip · Çift Kayıt Şüphesi'],
  ['16-rapor-aylik', '?ekran=raporlar&rapor=aylik', 'Raporlar · Aylık Özet'],
  ['17-rapor-tahsilat', '?ekran=raporlar&rapor=tahsilat', 'Raporlar · Tahsilat'],
  ['18-rapor-borc', '?ekran=raporlar&rapor=borc', 'Raporlar · Borç / Bakiye'],
  ['19-referans-karti', `?ekran=referanslar&id=${R}`, 'Referans Kartı'],
  ['20-referans-yeni', '?ekran=referanslar&yeni=1', 'Yeni Referans'],
  ['21-tanim-islem', '?ekran=tanimlar&sekme=islem_turu', 'Tanımlar · İşlem Türleri'],
  ['22-tanim-gider', '?ekran=tanimlar&sekme=gider_kalemi', 'Tanımlar · Gider Kalemleri'],
  ['23-tanim-gelir', '?ekran=tanimlar&sekme=gelir_kalemi', 'Tanımlar · Gelir Kalemleri'],
  ['24-tanim-ozelkod', '?ekran=tanimlar&sekme=ozel_kod', 'Tanımlar · Özel Kodlar'],
  ['25-tanim-evrak', '?ekran=tanimlar&sekme=evrak', 'Tanımlar · Evrak Listesi'],
  ['26-tanim-hesaplar', '?ekran=tanimlar&sekme=hesaplar', 'Tanımlar · Hesaplar'],
  ['27-tanim-kurum', '?ekran=tanimlar&sekme=ayarlar', 'Tanımlar · Kurum Bilgileri'],
  ['28-tanim-sifre', '?ekran=tanimlar&sekme=guvenlik', 'Tanımlar · Panel Şifresi'],
  ['29-tanim-referanslar', '?ekran=tanimlar&sekme=referanslar', 'Tanımlar · Referans Listesi'],
  ['30-gunluk', '?ekran=gunluk', 'İşlem Günlüğü'],
  ['31-silinenler', '?ekran=silinenler', 'Silinenler'],
  ['32-makbuz', `?ekran=yazdir&tur=makbuz&id=${M}`, 'Tahsilat Makbuzu'],
];

const OLCULER = [
  { ad: 'masaustu', width: 1440, height: 900 },
  { ad: 'dizustu', width: 1280, height: 720 },
  { ad: 'telefon', width: 375, height: 812 },
];

(async () => {
  fs.mkdirSync(CIKTI, { recursive: true });
  const tarayici = await chromium.launch({
    executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome',
    args: ['--no-sandbox', '--disable-dev-shm-usage'],
  });

  const rapor = [];

  for (const olcu of OLCULER) {
    const baglam = await tarayici.newContext({
      viewport: { width: olcu.width, height: olcu.height },
      deviceScaleFactor: 1,
      locale: 'tr-TR',
      timezoneId: 'Europe/Istanbul',
    });
    const sayfa = await baglam.newPage();

    // Giriş: yerel yardımcı hem WordPress oturumunu hem panel kilidini açar.
    await sayfa.goto(KOK + '/?yerel_giris=yonetici', { waitUntil: 'domcontentloaded' });

    const klasor = path.join(CIKTI, olcu.ad);
    fs.mkdirSync(klasor, { recursive: true });

    for (const [dosya, sorgu, ad] of EKRANLAR) {
      const hatalar = [];
      const konsol = (m) => { if (m.type() === 'error') hatalar.push(m.text().slice(0, 300)); };
      const cokme = (e) => hatalar.push('sayfa hatası: ' + String(e).slice(0, 300));
      sayfa.on('console', konsol);
      sayfa.on('pageerror', cokme);

      let durum = 0;
      try {
        const yanit = await sayfa.goto(PANEL + sorgu, { waitUntil: 'load', timeout: 30000 });
        durum = yanit ? yanit.status() : 0;
        await sayfa.waitForTimeout(350); // JS'in yerleşimi kurmasını bekle
      } catch (e) {
        hatalar.push('açılmadı: ' + String(e).slice(0, 200));
      }

      // Taşma ölçümü: sayfa yatayda kayıyor mu, hangi öge taşıyor?
      const olcum = await sayfa.evaluate(() => {
        const g = document.documentElement;
        const tasma = Math.max(0, g.scrollWidth - g.clientWidth);
        let suclu = '';
        if (tasma > 0) {
          const sinir = g.clientWidth;
          for (const el of document.querySelectorAll('*')) {
            const k = el.getBoundingClientRect();
            if (k.right > sinir + 1 && k.width > 0) {
              suclu = (el.tagName.toLowerCase() + (el.className && typeof el.className === 'string' ? '.' + el.className.trim().split(/\s+/).slice(0, 2).join('.') : ''));
              break;
            }
          }
        }
        return { tasma, suclu, baslik: document.title, yuksek: g.scrollHeight };
      });

      await sayfa.screenshot({ path: path.join(klasor, dosya + '.png'), fullPage: false });

      sayfa.off('console', konsol);
      sayfa.off('pageerror', cokme);

      rapor.push({ olcu: olcu.ad, ekran: ad, adres: '/panel/' + sorgu, durum, tasma: olcum.tasma, suclu: olcum.suclu, baslik: olcum.baslik, hatalar });
    }

    await baglam.close();
  }

  await tarayici.close();
  fs.writeFileSync(path.join(CIKTI, 'rapor.json'), JSON.stringify(rapor, null, 1), 'utf8');

  // Özet
  const kotu = rapor.filter((r) => r.durum !== 200 || r.tasma > 0 || r.hatalar.length);
  console.log(`${rapor.length} ölçüm (${EKRANLAR.length} ekran × ${OLCULER.length} ölçü)`);
  console.log(`sorunsuz: ${rapor.length - kotu.length} · sorunlu: ${kotu.length}`);
  for (const r of kotu) {
    console.log(`  [${r.olcu}] ${r.ekran} — HTTP ${r.durum}${r.tasma ? ` · taşma ${r.tasma}px (${r.suclu})` : ''}${r.hatalar.length ? ' · ' + r.hatalar.join(' | ') : ''}`);
  }
})().catch((e) => { console.error('TARAMA HATASI:', e); process.exit(1); });
