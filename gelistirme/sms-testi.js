// SMS akışının uçtan uca denenmesi: süzgeç → seçim → mesaj → onay → gönderim.
const { chromium } = require('playwright-core');
const KOK = 'http://localhost:8765';
const P = KOK + '/panel/';
const CIKTI = '/tmp/claude-0/-home-user-Panelyuzyil/d1741af1-6480-50f1-9a50-765d5e5db728/scratchpad/';

let basarisiz = 0;
function kontrol(ad, sonuc, ek) {
  console.log((sonuc ? '  TAMAM  ' : '  HATA   ') + ad + (ek ? ' → ' + ek : ''));
  if (!sonuc) { basarisiz++; }
}

(async () => {
  const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox'] });
  const c = await b.newContext({ viewport: { width: 1440, height: 900 }, locale: 'tr-TR', timezoneId: 'Europe/Istanbul' });
  const p = await c.newPage();
  const jsHata = [];
  p.on('pageerror', e => jsHata.push(String(e).slice(0, 160)));
  p.on('console', m => { if (m.type() === 'error') jsHata.push(m.text().slice(0, 160)); });

  await p.goto(KOK + '/?yerel_giris=yonetici', { waitUntil: 'load' });

  console.log('\n--- 1. Ayarlar girilmeden gönderim engelleniyor mu ---');
  await p.goto(P + '?ekran=sms', { waitUntil: 'load' });
  kontrol('ayar eksik uyarısı görünüyor', await p.locator('.uyari-uyari').count() > 0);
  kontrol('Devam düğmesi kapalı', await p.locator('button[type=submit].ana.buyuk').isDisabled());
  await p.screenshot({ path: CIKTI + 'sms-1-ayarsiz.png' });

  console.log('\n--- 2. SMS ayarları kaydediliyor ---');
  await p.goto(P + '?ekran=tanimlar&sekme=sms', { waitUntil: 'load' });
  await p.fill('input[name=sms_kullanici]', '8503021234');
  await p.fill('input[name=sms_sifre]', 'deneme-sifre');
  await p.fill('input[name=sms_baslik]', 'YUZYIL');
  await p.check('input[name=sms_aktif]');
  await p.screenshot({ path: CIKTI + 'sms-2-ayarlar.png' });
  await p.click('button[type=submit].ana');
  await p.waitForLoadState('load');
  kontrol('ayarlar kaydedildi', (await p.content()).indexOf('kaydedildi') > -1);

  console.log('\n--- 3. Süzgeçle aday getiriliyor ---');
  await p.goto(P + '?ekran=sms&uygula=1&odeme=borclu', { waitUntil: 'load' });
  await p.waitForTimeout(300);
  const kutuSayisi = await p.locator('[data-sms-kutu]').count();
  kontrol('borçlu adaylar listelendi', kutuSayisi > 0, kutuSayisi + ' aday');
  kontrol('hepsi seçili başlıyor', await p.locator('[data-sms-kutu]:checked').count() === kutuSayisi);

  console.log('\n--- 4. Mesaj sayacı ---');
  await p.fill('[data-sms-metin]', 'Sayin ilgili, odenmemis borcunuz bulunmaktadir. Bilginize.');
  await p.waitForTimeout(150);
  let sayac = (await p.locator('[data-sms-sayac]').textContent()).trim();
  kontrol('sayaç düz metinde 1 SMS diyor', /kisi basina 1 SMS|kişi başına 1 SMS/.test(sayac), sayac);

  await p.check('[data-sms-turkce]');
  await p.fill('[data-sms-metin]', 'Sayın ilgili, ödenmemiş borcunuz bulunmaktadır. Ödeme için merkezimize başvurabilirsiniz. Bilginize sunarız.');
  await p.waitForTimeout(150);
  sayac = (await p.locator('[data-sms-sayac]').textContent()).trim();
  kontrol('Türkçe açıkken 70 hane sınırı uygulanıyor', /kişi başına 2 SMS/.test(sayac), sayac);

  console.log('\n--- 5. Birkaç alıcının işareti kaldırılıyor ---');
  await p.locator('[data-sms-kutu]').nth(0).uncheck();
  await p.locator('[data-sms-kutu]').nth(1).uncheck();
  await p.waitForTimeout(150);
  const kalan = await p.locator('[data-sms-kutu]:checked').count();
  kontrol('seçim azaldı', kalan === kutuSayisi - 2, kalan + ' kişi');
  const seciliYazi = (await p.locator('[data-sms-secili-yazi]').textContent()).trim();
  kontrol('seçili sayısı ekranda doğru', seciliYazi.indexOf(String(kalan)) === 0, seciliYazi);
  await p.screenshot({ path: CIKTI + 'sms-3-secim.png' });

  console.log('\n--- 6. Onay ekranı ---');
  await p.click('button[type=submit].ana.buyuk');
  await p.waitForLoadState('load');
  await p.waitForTimeout(200);
  kontrol('onay ekranı açıldı', (await p.title()).indexOf('SMS') > -1 && await p.locator('.sms-onay').count() > 0);
  const onayMetni = await p.locator('.sms-ozet').textContent();
  kontrol('onayda kişi sayısı yazıyor', onayMetni.indexOf(String(kalan)) > -1, onayMetni.replace(/\s+/g, ' ').trim());
  kontrol('alıcılar tek tek listeleniyor', await p.locator('.sms-alici-liste tbody tr').count() === kalan);
  kontrol('gönder düğmesi kapalı başlıyor', await p.locator('[data-sms-gonder]').isDisabled());
  await p.screenshot({ path: CIKTI + 'sms-4-onay.png', fullPage: false });

  await p.check('[data-sms-onay]');
  await p.waitForTimeout(100);
  kontrol('onay kutusu işaretlenince düğme açılıyor', !(await p.locator('[data-sms-gonder]').isDisabled()));

  console.log('\n--- 7. İmza koruması: liste kurcalanırsa gönderim reddedilmeli ---');
  await p.evaluate(() => {
    const f = document.querySelector('.sms-onay-form');
    const ek = document.createElement('input');
    ek.type = 'hidden'; ek.name = 'alici[]'; ek.value = '999999';
    f.appendChild(ek);
    const m = f.querySelector('input[name=mesaj]');
    if (m) { m.value = m.value + ' EKLENMIS METIN'; }
  });
  await p.click('[data-sms-gonder]');
  await p.waitForLoadState('load');
  await p.waitForTimeout(200);
  const govde = await p.content();
  kontrol('kurcalanan gönderim reddedildi', govde.indexOf('aynı değil') > -1 || govde.indexOf('iptal') > -1);
  await p.screenshot({ path: CIKTI + 'sms-5-imza-korumasi.png' });

  console.log('\n--- 8. Gerçek gönderim denemesi (sağlayıcıya ulaşılamaz, hata düzgün mü) ---');
  await p.goto(P + '?ekran=sms&uygula=1&odeme=geciken', { waitUntil: 'load' });
  await p.fill('[data-sms-metin]', 'Deneme mesaji. Gercek gonderim yapilmamaktadir.');
  const hepsi = await p.locator('[data-sms-kutu]').count();
  for (let i = 2; i < hepsi; i++) { await p.locator('[data-sms-kutu]').nth(i).uncheck(); }
  await p.waitForTimeout(150);
  await p.click('button[type=submit].ana.buyuk');
  await p.waitForLoadState('load');
  await p.check('[data-sms-onay]');
  await p.click('[data-sms-gonder]');
  await p.waitForLoadState('load');
  await p.waitForTimeout(400);
  const sonGovde = await p.content();
  kontrol('gönderim sonrası geçmiş ekranı açıldı', sonGovde.indexOf('Gönderim Geçmişi') > -1);
  kontrol('hata kullanıcıya anlaşılır yazıldı', sonGovde.indexOf('ulaşılamadı') > -1 || sonGovde.indexOf('Gönderim yapılamadı') > -1);
  await p.screenshot({ path: CIKTI + 'sms-6-gecmis.png' });

  console.log('\n--- 9. Ana menüde karo ---');
  await p.goto(P, { waitUntil: 'load' });
  await p.waitForTimeout(200);
  kontrol('SMS karosu ana menüde', await p.locator('[data-karo=sms]').count() === 1);
  await p.screenshot({ path: CIKTI + 'sms-7-anamenu.png' });

  console.log('\nJS hatası: ' + (jsHata.length ? jsHata.join(' | ') : 'yok'));
  console.log('SONUÇ: ' + (basarisiz ? basarisiz + ' denetim BAŞARISIZ' : 'tüm denetimler geçti'));
  await b.close();
  process.exit(basarisiz ? 1 : 0);
})().catch(e => { console.error('DENEME ÇÖKTÜ:', e); process.exit(1); });
