const { chromium } = require('playwright-core');
const fs = require('fs');
const KOK = 'http://127.0.0.1:8899/';
const DIZIN = '/tmp/claude-0/-home-user-Panelyuzyil/d1741af1-6480-50f1-9a50-765d5e5db728/scratchpad/onizleme';

(async () => {
  const dosyalar = fs.readdirSync(DIZIN).filter(f => f.endsWith('.html')).sort();
  const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox'] });
  const c = await b.newContext({ viewport: { width: 1440, height: 900 }, locale: 'tr-TR' });
  const p = await c.newPage();

  const sorunlar = [];
  const eksikDosya = new Set();
  p.on('requestfailed', r => { if (!/fonts\.g/.test(r.url())) eksikDosya.add(r.url().replace(KOK, '')); });
  p.on('response', r => { if (r.status() === 404) eksikDosya.add(r.url().replace(KOK, '')); });

  for (const d of dosyalar) {
    const jsHata = [];
    const kons = m => { if (m.type() === 'error') jsHata.push(m.text().slice(0, 150)); };
    const cok = e => jsHata.push('sayfa hatası: ' + String(e).slice(0, 150));
    p.on('console', kons); p.on('pageerror', cok);

    await p.goto(KOK + encodeURIComponent(d), { waitUntil: 'load' });
    await p.waitForTimeout(90);
    const o = await p.evaluate(() => ({
      yazi: (document.body.innerText || '').trim().length,
      stil: document.styleSheets.length,
      kuralSayisi: [...document.styleSheets].reduce((t, s) => { try { return t + s.cssRules.length; } catch (e) { return t; } }, 0),
      baslik: document.title,
      ciftClass: document.querySelectorAll('[data-kapali][class]').length,
    }));

    p.off('console', kons); p.off('pageerror', cok);

    const kotu = [];
    if (o.yazi < 150) kotu.push('içerik çok az (' + o.yazi + ' karakter)');
    if (o.kuralSayisi < 100) kotu.push('stil yüklenmemiş (' + o.kuralSayisi + ' kural)');
    if (!o.baslik) kotu.push('başlık yok');
    if (jsHata.length) kotu.push('JS: ' + jsHata.join(' | '));
    if (kotu.length) sorunlar.push(d + ' → ' + kotu.join('; '));
  }

  await b.close();
  console.log('taranan sayfa: ' + dosyalar.length);
  console.log('sorunlu sayfa: ' + sorunlar.length);
  sorunlar.slice(0, 25).forEach(s => console.log('  ' + s));
  console.log('bulunamayan dosya: ' + (eksikDosya.size ? [...eksikDosya].slice(0, 10).join(', ') : 'yok'));
})();
