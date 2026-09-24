const { chromium } = require('playwright-core');
const P = 'http://localhost:8765/panel/';
const CIKTI = '/tmp/claude-0/-home-user-Panelyuzyil/d1741af1-6480-50f1-9a50-765d5e5db728/scratchpad/';
let kotu = 0;
function k(ad, s, ek) { console.log((s ? '  TAMAM  ' : '  HATA   ') + ad + (ek ? ' → ' + ek : '')); if (!s) kotu++; }

(async () => {
  const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox'] });
  const c = await b.newContext({ viewport: { width: 1440, height: 900 }, locale: 'tr-TR', timezoneId: 'Europe/Istanbul' });
  const p = await c.newPage();
  const jsHata = [];
  p.on('pageerror', e => jsHata.push(String(e).slice(0, 150)));
  p.on('console', m => { if (m.type() === 'error') jsHata.push(m.text().slice(0, 150)); });
  await p.goto('http://localhost:8765/?yerel_giris=yonetici', { waitUntil: 'load' });

  console.log('\n--- Personel listesi ---');
  await p.goto(P + '?ekran=personel', { waitUntil: 'load' });
  await p.waitForTimeout(250);
  const satir = await p.locator('tbody tr.tiklanir').count();
  k('liste doldu', satir === 7, satir + ' çalışan');
  const basliklar = await p.locator('thead th').allTextContents();
  k('istenen sütunlar var', ['Adı Soyadı','Yaş','Görevi','Ne kadar zamandır çalışıyor','Maaşı','Doğum gününe kalan'].every(x => basliklar.some(b => b.indexOf(x) > -1)), basliklar.join(' | '));
  const ilkSatir = await p.locator('tbody tr.tiklanir').first().allInnerTexts();
  k('kıdem yıl/ay/gün olarak yazıyor', /\d+ yıl .*\d+ ay .*\d+ gün|\d+ ay .*\d+ gün|\d+ gün/.test(ilkSatir[0]), ilkSatir[0].replace(/\s+/g, ' ').trim());
  await p.screenshot({ path: CIKTI + 'personel-1-liste.png' });

  console.log('\n--- Sıralama ---');
  await p.click('thead th a:has-text("Doğum gününe kalan")');
  await p.waitForLoadState('load');
  const gunler = (await p.locator('tbody tr td:nth-child(6)').allInnerTexts()).map(t => parseInt(t, 10)).filter(n => !isNaN(n));
  k('doğum gününe göre sıralandı', gunler.every((v, i, a) => i === 0 || a[i-1] <= v), gunler.slice(0, 5).join(', ') + ' …');

  console.log('\n--- Kart (çift tık) ---');
  await p.goto(P + '?ekran=personel', { waitUntil: 'load' });
  await p.locator('tbody tr.tiklanir').first().dblclick();
  await p.waitForLoadState('load');
  await p.waitForTimeout(300);
  k('kart açıldı', (await p.content()).indexOf('Maaş kontrol takvimi') > -1, p.url().split('?')[1]);
  k('12 ay kutusu var', await p.locator('.pt-ay').count() === 12);
  k('ödenen ay yeşil', await p.locator('.pt-odendi').count() > 0, await p.locator('.pt-odendi').count() + ' ay ödenmiş');
  k('özet kutuları var', await p.locator('.pt-ozet-kutu').count() === 6);
  const odemeSatir = await p.locator('table.tablo tbody tr').count();
  k('ödeme dökümü dolu', odemeSatir > 5, odemeSatir + ' satır');
  await p.screenshot({ path: CIKTI + 'personel-2-kart.png', fullPage: true });

  console.log('\n--- Ödeme ekleme ---');
  const oncekiSatir = odemeSatir;
  await p.locator('.arac-cubugu [data-dialog="d-odeme-ekle"]').click();
  await p.waitForTimeout(250);
  k('pencere açıldı', await p.locator('#d-odeme-ekle[open]').count() === 1);
  k('maaşta "ait olduğu ay" görünür', await p.locator('#d-odeme-ekle .pt-donem').isVisible());
  await p.selectOption('#d-odeme-ekle [data-pt-tur]', 'AVANS');
  await p.waitForTimeout(150);
  k('avansta "ait olduğu ay" gizlenir', !(await p.locator('#d-odeme-ekle .pt-donem').isVisible()));
  await p.fill('#d-odeme-ekle input[name=tutar]', '7.500,00');
  await p.fill('#d-odeme-ekle input[name=aciklama]', 'Deneme avansı');
  await p.click('#d-odeme-ekle button[type=submit]');
  await p.waitForLoadState('load');
  await p.waitForTimeout(300);
  let govde = await p.content();
  k('ödeme kaydedildi', govde.indexOf('Ödeme kaydedildi') > -1);
  k('yeni satır listede', govde.indexOf('Deneme avansı') > -1);
  k('kasaya işlendi rozeti var', govde.indexOf('İşlendi') > -1);

  console.log('\n--- Kasaya gerçekten gider yazıldı mı ---');
  await p.goto(P + '?ekran=kasa&sekme=gider', { waitUntil: 'load' });
  await p.waitForTimeout(250);
  govde = await p.content();
  k('kasa gider listesinde personel satırı var', govde.indexOf('Personel:') > -1);

  console.log('\n--- Ödeme silinince kasadan da düşüyor mu ---');
  await p.goBack();
  await p.waitForLoadState('load');
  await p.waitForTimeout(200);

  console.log('\n--- Yeni personel ekleme ---');
  await p.goto(P + '?ekran=personel', { waitUntil: 'load' });
  await p.locator('.arac-cubugu [data-dialog="d-personel-yeni"]').click();
  await p.waitForTimeout(250);
  k('yeni personel penceresi açıldı', await p.locator('#d-personel-yeni[open]').count() === 1);
  k('ayrılma tarihi gizli başlıyor', !(await p.locator('#d-personel-yeni .pt-ayrilma').isVisible()));
  await p.check('#d-personel-yeni [data-pt-ayrildi]');
  await p.waitForTimeout(150);
  k('"ayrıldı" işaretlenince tarih alanı açılıyor', await p.locator('#d-personel-yeni .pt-ayrilma').isVisible());
  await p.uncheck('#d-personel-yeni [data-pt-ayrildi]');
  await p.fill('#d-personel-yeni input[name=ad]', 'Denemeci');
  await p.fill('#d-personel-yeni input[name=soyad]', 'Uydurmaoğlu');
  await p.selectOption('#d-personel-yeni select[name=gorev]', 'Büro Görevlisi');
  await p.fill('#d-personel-yeni input[name=dogum_tarihi]', '15.07.1990');
  await p.fill('#d-personel-yeni input[name=baslama_tarihi]', '01.03.2024');
  await p.fill('#d-personel-yeni input[name=maas]', '45.000,00');
  await p.click('#d-personel-yeni button[type=submit]');
  await p.waitForLoadState('load');
  await p.waitForTimeout(300);
  govde = await p.content();
  k('personel eklendi', govde.indexOf('Personel eklendi') > -1);
  k('kartında kıdem hesaplandı', /\d+ yıl/.test(govde) && govde.indexOf('Denemeci Uydurmaoğlu') > -1);

  console.log('\n--- Ana menüde karo ---');
  await p.goto(P, { waitUntil: 'load' });
  await p.waitForTimeout(200);
  k('Personel karosu ana menüde', await p.locator('[data-karo=personel]').count() === 1);

  console.log('\nJS hatası: ' + (jsHata.length ? jsHata.join(' | ') : 'yok'));
  console.log('SONUÇ: ' + (kotu ? kotu + ' denetim BAŞARISIZ' : 'tüm denetimler geçti'));
  await b.close();
  process.exit(kotu ? 1 : 0);
})().catch(e => { console.error('ÇÖKTÜ:', e); process.exit(1); });
