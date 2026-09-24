const { chromium } = require('playwright-core');
let kotu = 0;
function k(ad, s, ek) { console.log((s ? '  TAMAM  ' : '  HATA   ') + ad + (ek ? ' → ' + ek : '')); if (!s) kotu++; }
(async () => {
  const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox'] });
  const c = await b.newContext({ viewport: { width: 1440, height: 900 }, locale: 'tr-TR' });
  const p = await c.newPage();
  p.on('dialog', d => d.accept());
  await p.goto('http://localhost:8765/?yerel_giris=yonetici', { waitUntil: 'load' });

  // Personel kartından kasadaki günü bul
  await p.goto('http://localhost:8765/panel/?ekran=personel&id=7', { waitUntil: 'load' });
  await p.waitForTimeout(250);
  const bag = await p.locator('a[title="Kasada bu günü aç"]').first().getAttribute('href');
  await p.goto(bag, { waitUntil: 'load' });
  await p.waitForTimeout(300);

  const satirVar = await p.locator('tr[data-kasa-satir]', { hasText: 'Personel:' }).count();
  k('kasada personel satırı görünüyor', satirVar > 0, satirVar + ' satır');
  if (!satirVar) { await b.close(); process.exit(1); }

  // Satırdaki "Sil" küçük formunu gönder
  const tr = p.locator('tr[data-kasa-satir]', { hasText: 'Personel:' }).first();
  const silDugme = tr.locator('button:has-text("Sil")');
  const silVar = await silDugme.count();
  console.log('    satır içi Sil düğmesi: ' + (silVar ? 'var' : 'yok'));
  if (silVar) {
    await silDugme.first().click();
    await p.waitForLoadState('load');
    await p.waitForTimeout(300);
    const govde = await p.content();
    k('silme engellendi ve sebebi yazıldı', govde.indexOf('personel ödemesinden geliyor') > -1,
      (govde.match(/Bu satır personel[^<]*/) || ['mesaj yok'])[0].slice(0, 120));
    k('kasa satırı hâlâ duruyor', (await p.locator('tr[data-kasa-satir]', { hasText: 'Personel:' }).count()) > 0);
  }
  await p.screenshot({ path: '/tmp/claude-0/-home-user-Panelyuzyil/d1741af1-6480-50f1-9a50-765d5e5db728/scratchpad/pt2-4-koruma.png' });
  console.log('SONUÇ: ' + (kotu ? kotu + ' BAŞARISIZ' : 'koruma çalışıyor'));
  await b.close();
  process.exit(kotu ? 1 : 0);
})().catch(e => { console.error('ÇÖKTÜ:', e.message); process.exit(1); });
