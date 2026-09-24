const { chromium } = require('playwright-core');
let kotu = 0;
function k(ad, s, ek) { console.log((s ? '  TAMAM  ' : '  HATA   ') + ad + (ek ? ' → ' + ek : '')); if (!s) kotu++; }
(async () => {
  const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox'] });
  const c = await b.newContext({ viewport: { width: 1440, height: 900 }, locale: 'tr-TR' });
  const p = await c.newPage();
  const jsHata = [];
  p.on('pageerror', e => jsHata.push(String(e).slice(0, 140)));
  p.on('console', m => { if (m.type() === 'error') jsHata.push(m.text().slice(0, 140)); });
  await p.goto('http://localhost:8765/?yerel_giris=yonetici', { waitUntil: 'load' });

  await p.goto('http://localhost:8765/panel/?ekran=personel&id=7', { waitUntil: 'load' });
  await p.waitForTimeout(300);

  const alanlar = await p.locator('.pt-bilgi > span').allTextContents();
  k('kartta "Doğum gününe" yok', !alanlar.includes('Doğum gününe'), alanlar.join(' · '));
  k('diğer bilgiler duruyor', ['Görevi','Yaş','İşe başlama','Kıdem','Aylık maaşı','Durumu'].every(x => alanlar.includes(x)), alanlar.length + ' alan');

  const satirSayisi = await p.evaluate(() => {
    const aylar = [...document.querySelectorAll('.pt-ay')];
    const ustler = new Set(aylar.map(a => Math.round(a.getBoundingClientRect().top)));
    return { ay: aylar.length, satir: ustler.size, yukseklik: Math.round(aylar[0].getBoundingClientRect().height) };
  });
  k('12 ay tek satırda', satirSayisi.ay === 12 && satirSayisi.satir === 1, satirSayisi.ay + ' ay / ' + satirSayisi.satir + ' satır');
  k('kutular sadeleşti', satirSayisi.yukseklik < 52, satirSayisi.yukseklik + 'px (eskiden ~64px)');
  k('sayfa yatayda kaymıyor', await p.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth));
  await p.screenshot({ path: '/tmp/claude-0/-home-user-Panelyuzyil/d1741af1-6480-50f1-9a50-765d5e5db728/scratchpad/pt3-kart.png' });

  // Listede doğum günü sütunu hâlâ dursun
  await p.goto('http://localhost:8765/panel/?ekran=personel', { waitUntil: 'load' });
  await p.waitForTimeout(200);
  const basliklar = await p.locator('thead th').allTextContents();
  k('listede "Doğum gününe kalan" duruyor', basliklar.some(x => x.indexOf('Doğum gününe kalan') > -1));

  // Dar ekranda sayfa taşmasın
  await c.newPage();
  const p2 = await c.newPage();
  await p2.setViewportSize({ width: 375, height: 812 });
  await p2.goto('http://localhost:8765/panel/?ekran=personel&id=7', { waitUntil: 'load' });
  await p2.waitForTimeout(300);
  const tasma = await p2.evaluate(() => Math.max(0, document.documentElement.scrollWidth - document.documentElement.clientWidth));
  k('telefonda sayfa taşması yok', tasma === 0, tasma + 'px');

  console.log('JS hatası: ' + (jsHata.length ? jsHata.join(' | ') : 'yok'));
  console.log('SONUÇ: ' + (kotu ? kotu + ' BAŞARISIZ' : 'tüm denetimler geçti'));
  await b.close();
  process.exit(kotu ? 1 : 0);
})().catch(e => { console.error('ÇÖKTÜ:', e.message); process.exit(1); });
