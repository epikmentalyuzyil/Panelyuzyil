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

  console.log('\n--- 1. Yıl özeti kaldırıldı mı ---');
  await p.goto(P + '?ekran=personel&id=7', { waitUntil: 'load' });
  await p.waitForTimeout(300);
  let govde = await p.content();
  k('"yılı özeti" bölümü yok', govde.indexOf('yılı özeti') === -1);
  k('özet kutuları yok', await p.locator('.pt-ozet-kutu').count() === 0);
  k('maaş kontrol takvimi duruyor', govde.indexOf('Maaş kontrol takvimi') > -1);
  k('ödeme dökümü duruyor', govde.indexOf('ödeme dökümü') > -1);

  console.log('\n--- 2. Üst şerit sade ama eksiksiz ---');
  const alanlar = await p.locator('.pt-bilgi > span').allTextContents();
  const beklenen = ['Görevi','Yaş','Doğum gününe','İşe başlama','Kıdem','Aylık maaşı','Durumu','Telefon','TC No','SGK No','IBAN'];
  const eksik = beklenen.filter(x => !alanlar.includes(x));
  k('hiçbir bilgi kaybolmamış', eksik.length === 0, eksik.length ? 'eksik: ' + eksik.join(', ') : alanlar.length + ' alan');
  const yuksek = await p.locator('.pt-kimlik').evaluate(el => el.getBoundingClientRect().height);
  k('şerit sadeleşti (eskiden ~150px)', yuksek < 110, Math.round(yuksek) + 'px');
  await p.screenshot({ path: CIKTI + 'pt2-1-kart.png' });

  console.log('\n--- 3. Hesap seçenekleri banka adıyla ---');
  await p.locator('.arac-cubugu [data-dialog="d-odeme-ekle"]').click();
  await p.waitForTimeout(250);
  const secenekler = await p.locator('#d-odeme-ekle select[name=hesap_id] option').allTextContents();
  k('üç banka ayırt ediliyor', secenekler.filter(x => x.indexOf('Banka —') === 0).length === 3, secenekler.join(' | '));
  k('nakit ve PTT sade kaldı', secenekler.includes('Nakit Kasa') && secenekler.includes('PTT'));
  k('hesap seçimi zorunlu', await p.locator('#d-odeme-ekle select[name=hesap_id]').evaluate(el => el.required));
  await p.screenshot({ path: CIKTI + 'pt2-2-hesap.png' });

  console.log('\n--- 4. Ödeme ekle, sonra düzelt ---');
  await p.selectOption('#d-odeme-ekle select[name=hesap_id]', { label: 'Banka — İş Bankası' });
  await p.fill('#d-odeme-ekle input[name=tutar]', '12.000,00');
  await p.fill('#d-odeme-ekle input[name=aciklama]', 'Duzeltme denemesi');
  await p.click('#d-odeme-ekle button[type=submit]');
  await p.waitForLoadState('load');
  await p.waitForTimeout(300);
  govde = await p.content();
  k('ödeme eklendi', govde.indexOf('Ödeme kaydedildi') > -1);
  k('hesap adı banka adıyla yazıyor', govde.indexOf('Banka — İş Bankası') > -1);
  k('kasa rozeti bağlantılı', await p.locator('a[title="Kasada bu günü aç"]').count() > 0);

  const satir = p.locator('table.tablo tbody tr', { hasText: 'Duzeltme denemesi' }).first();
  await satir.locator('button[data-dialog="d-odeme-duzenle"]').click();
  await p.waitForTimeout(300);
  k('düzeltme penceresi doldu', await p.locator('#d-odeme-duzenle input[name=tutar]').inputValue() === '12.000,00',
     await p.locator('#d-odeme-duzenle input[name=tutar]').inputValue());
  k('kasa kutusu işaretli geldi', await p.locator('#d-odeme-duzenle input[name=kasaya]').isChecked());
  await p.fill('#d-odeme-duzenle input[name=tutar]', '15.750,00');
  await p.click('#d-odeme-duzenle button[type=submit]');
  await p.waitForLoadState('load');
  await p.waitForTimeout(300);
  govde = await p.content();
  k('ödeme güncellendi', govde.indexOf('Ödeme güncellendi') > -1);
  k('yeni tutar listede', govde.indexOf('15.750,00') > -1);

  console.log('\n--- 5. Kasadaki ikizi de güncellendi mi ---');
  const kasaBag = await p.locator('a[title="Kasada bu günü aç"]').first().getAttribute('href');
  await p.goto(kasaBag, { waitUntil: 'load' });
  await p.waitForTimeout(300);
  govde = await p.content();
  k('kasada aynı tutar görünüyor', govde.indexOf('15.750,00') > -1);
  k('kasada personel açıklaması var', govde.indexOf('Duzeltme denemesi') > -1);
  await p.screenshot({ path: CIKTI + 'pt2-3-kasa.png' });

  console.log('\n--- 6. Kasadan bu satır değiştirilemiyor mu ---');
  const korumaVar = await p.evaluate(async () => {
    const satirlar = [...document.querySelectorAll('tr')].filter(t => t.textContent.indexOf('Duzeltme denemesi') > -1);
    if (!satirlar.length) { return 'satır bulunamadı'; }
    const tr = satirlar[0];
    const id = tr.getAttribute('data-id') || (tr.querySelector('[data-id]') && tr.querySelector('[data-id]').getAttribute('data-id'));
    return id || 'id yok';
  });
  console.log('    kasa satır kimliği: ' + korumaVar);

  console.log('\nJS hatası: ' + (jsHata.length ? jsHata.join(' | ') : 'yok'));
  console.log('SONUÇ: ' + (kotu ? kotu + ' denetim BAŞARISIZ' : 'tüm denetimler geçti'));
  await b.close();
  process.exit(kotu ? 1 : 0);
})().catch(e => { console.error('ÇÖKTÜ:', e.message); process.exit(1); });
