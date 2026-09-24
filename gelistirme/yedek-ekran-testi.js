const { chromium } = require('playwright-core');
const fs = require('fs');
const P = 'http://localhost:8765/panel/';
const CIKTI = '/tmp/claude-0/-home-user-Panelyuzyil/d1741af1-6480-50f1-9a50-765d5e5db728/scratchpad/';
let kotu = 0;
function kontrol(ad, s, ek) { console.log((s ? '  TAMAM  ' : '  HATA   ') + ad + (ek ? ' → ' + ek : '')); if (!s) kotu++; }

(async () => {
  const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox'] });
  const c = await b.newContext({ viewport: { width: 1440, height: 900 }, locale: 'tr-TR', acceptDownloads: true });
  const p = await c.newPage();
  const jsHata = [];
  p.on('pageerror', e => jsHata.push(String(e).slice(0, 150)));
  p.on('console', m => { if (m.type() === 'error') jsHata.push(m.text().slice(0, 150)); });

  await p.goto('http://localhost:8765/?yerel_giris=yonetici', { waitUntil: 'load' });

  console.log('\n--- Yedekleme ekranı ---');
  await p.goto(P + '?ekran=yedek', { waitUntil: 'load' });
  kontrol('ekran açıldı', (await p.title()).indexOf('Yedekleme') > -1);
  kontrol('kayıt sayıları görünüyor', await p.locator('.yedek-sayi').count() >= 8);
  await p.screenshot({ path: CIKTI + 'yedek-1-ekran.png' });

  console.log('\n--- Yedek indiriliyor ---');
  const indirmeSozu = p.waitForEvent('download', { timeout: 60000 });
  await p.click('button:has-text("Yedek Al ve İndir")');
  const indirme = await indirmeSozu;
  const yol = CIKTI + 'indirilen-yedek.zip';
  await indirme.saveAs(yol);
  const boyut = fs.statSync(yol).size;
  kontrol('yedek indi', boyut > 10000, indirme.suggestedFilename() + ' · ' + Math.round(boyut / 1024) + ' KB');
  kontrol('dosya adı doğru biçimde', /^yuzyil-panel-yedek-\d{4}-\d{2}-\d{2}-\d{4}\.zip$/.test(indirme.suggestedFilename()));

  console.log('\n--- Yanlış dosya reddediliyor mu ---');
  const sahteYol = CIKTI + 'sahte.zip';
  fs.writeFileSync(sahteYol, 'bu bir zip bile degil');
  await p.goto(P + '?ekran=yedek', { waitUntil: 'load' });
  await p.setInputFiles('input[type=file]', sahteYol);
  await p.click('button:has-text("Dosyayı incele")');
  await p.waitForLoadState('load');
  let govde = await p.content();
  kontrol('bozuk dosya reddedildi', govde.indexOf('açılamadı') > -1 || govde.indexOf('yedeği değil') > -1);

  console.log('\n--- Gerçek yedek inceleniyor ---');
  await p.goto(P + '?ekran=yedek', { waitUntil: 'load' });
  await p.setInputFiles('input[type=file]', yol);
  await p.click('button:has-text("Dosyayı incele")');
  await p.waitForLoadState('load');
  await p.waitForTimeout(200);
  kontrol('inceleme ekranı açıldı', await p.locator('.sms-ozet').count() > 0);
  kontrol('parmak izi doğrulandı yazıyor', (await p.content()).indexOf('Doğrulandı') > -1);
  kontrol('karşılaştırma tablosu var', await p.locator('table.tablo tbody tr').count() >= 8);
  kontrol('geri yükle düğmesi KAPALI başlıyor', await p.locator('[data-onay-dugme]').isDisabled());
  await p.screenshot({ path: CIKTI + 'yedek-2-inceleme.png' });

  await p.check('[data-onay-kutu]');
  await p.waitForTimeout(100);
  kontrol('onay kutusu işaretlenince düğme açılıyor', !(await p.locator('[data-onay-dugme]').isDisabled()));

  console.log('\n--- Geri yükleniyor ---');
  await p.click('[data-onay-dugme]');
  await p.waitForLoadState('load');
  await p.waitForTimeout(400);
  govde = await p.content();
  kontrol('geri yükleme başarı mesajı verdi', govde.indexOf('geri yüklendi') > -1, (govde.match(/Yedek geri yüklendi[^<]*/) || [''])[0]);
  await p.screenshot({ path: CIKTI + 'yedek-3-sonuc.png' });

  console.log('\n--- Ana menüde karo ---');
  await p.goto(P, { waitUntil: 'load' });
  await p.waitForTimeout(200);
  kontrol('Yedek Al karosu ana menüde', await p.locator('[data-karo=yedek]').count() === 1);

  console.log('\nJS hatası: ' + (jsHata.length ? jsHata.join(' | ') : 'yok'));
  console.log('SONUÇ: ' + (kotu ? kotu + ' denetim BAŞARISIZ' : 'tüm denetimler geçti'));
  await b.close();
  process.exit(kotu ? 1 : 0);
})().catch(e => { console.error('ÇÖKTÜ:', e); process.exit(1); });
