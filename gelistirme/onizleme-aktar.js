// KURAL: Panelin gerçek HTML'i dışa aktarılır — önizleme, kodun kendi çıktısıdır, taklit değildir.
const { chromium } = require('playwright-core');
const fs = require('fs');
const path = require('path');

const KOK = 'http://localhost:8765';
const PANEL_YOL = '/panel/';
const CIKTI = process.env.CIKTI || '/home/user/wordpress/onizleme';
const SINIR = parseInt(process.env.SINIR || '235', 10); // artifact dosya sınırı 255

// Dışa aktarılmayacak adresler: dosya indirme, fotoğraf/logo uçları, çıkış işlemleri.
const ATLA = /(?:varlik=|excel=|cikti=excel|ekran=foto|ekran=logo|ekran=ara|kilitle|cikis|yp_kilit)/;

function anahtar(url) {
  const u = new URL(url, KOK);
  const p = new URLSearchParams(u.search);
  p.delete('_wpnonce');
  const parcalar = [...p.entries()].sort((a, b) => (a[0] < b[0] ? -1 : 1));
  return parcalar.map(([k, v]) => `${k}=${v}`).join('&');
}

function dosyaAdi(url) {
  const a = anahtar(url);
  if (a === '') return 'ana-menu.html';
  const slug = a
    .replace(/[^a-zA-Z0-9=&_,.-]/g, '')
    .replace(/[=&]/g, '-')
    .replace(/-+/g, '-')
    .slice(0, 90);
  return slug + '.html';
}

const cozUrl = (ham) => {
  const temiz = ham.replace(/&#0?38;/g, '&').replace(/&amp;/g, '&');
  try {
    return new URL(temiz, KOK).href;
  } catch (e) {
    return null;
  }
};

function panelMi(url) {
  try {
    const u = new URL(url);
    return u.origin === KOK && u.pathname === PANEL_YOL;
  } catch (e) {
    return false;
  }
}

(async () => {
  fs.mkdirSync(CIKTI, { recursive: true });
  fs.mkdirSync(path.join(CIKTI, 'varliklar'), { recursive: true });

  const tarayici = await chromium.launch({
    executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome',
    args: ['--no-sandbox', '--disable-dev-shm-usage'],
  });
  const baglam = await tarayici.newContext({ locale: 'tr-TR', timezoneId: 'Europe/Istanbul' });
  const sayfa = await baglam.newPage();
  await sayfa.goto(KOK + '/?yerel_giris=yonetici', { waitUntil: 'domcontentloaded' });
  const istek = baglam.request;

  // Varlıklar
  for (const [ad, sorgu] of [['panel.css', 'varlik=css'], ['panel.js', 'varlik=js']]) {
    const y = await istek.get(`${KOK}${PANEL_YOL}?${sorgu}`);
    fs.writeFileSync(path.join(CIKTI, 'varliklar', ad), await y.text(), 'utf8');
  }
  fs.copyFileSync(
    '/home/user/Panelyuzyil/eklenti/yuzyil-panel/varliklar/minilogo.png',
    path.join(CIKTI, 'varliklar', 'minilogo.png')
  );

  // Gezinme: önce ana ekranlar, sonra aday kartları, kalan bütçe sıralama/sayfa çeşitlemelerine.
  const K = process.env.KIMLIK ? JSON.parse(process.env.KIMLIK) : {};
  const TOHUM = [
    '', '?ekran=adaylar', '?ekran=kasa', '?ekran=takip', '?ekran=raporlar',
    '?ekran=tanimlar', '?ekran=gunluk', '?ekran=silinenler',
    `?ekran=aday&id=${K.aday || 1}`, `?ekran=aday&id=${K.borclu || 1}`,
    `?ekran=aday&id=${K.borclu || 1}&sekme=odeme`, '?ekran=aday&yeni=1',
    `?ekran=referanslar&id=${K.referans || 1}`, '?ekran=referanslar&yeni=1',
    `?ekran=yazdir&tur=makbuz&id=${K.makbuz || 1}`,
    ...['geciken','bugun','yaklasan','gecerlilik','evrak','dogumgunu','cift'].map((l) => `?ekran=takip&liste=${l}`),
    ...['aylik','tahsilat','borc'].map((r) => `?ekran=raporlar&rapor=${r}`),
    ...['islem_turu','gider_kalemi','gelir_kalemi','ozel_kod','evrak','hesaplar','ayarlar','guvenlik','referanslar']
      .map((b) => `?ekran=tanimlar&sekme=${b}`),
    ...['tumu','gelir','gider'].map((sk) => `?ekran=kasa&sekme=${sk}`),
  ].map((q) => KOK + PANEL_YOL + q);

  // Öncelik: 0 = ana ekran, 1 = aday/referans kartı, 2 = süzgeç, 3 = sıralama/sayfalama
  function oncelik(url) {
    const a = anahtar(url);
    if (/sirala=|yon=|sayfa=/.test(a)) return 3;
    if (/ekran=aday&|ekran=aday$|ekran=referanslar/.test(a + '&')) return 1;
    if (/arsiv=|odeme=|liste=|rapor=|sekme=|bas=|bit=/.test(a)) return 2;
    return 0;
  }
  const PAY = { 0: 999, 1: 60, 2: 90, 3: 60 }; // her öncelikten en çok kaç sayfa

  const gorulen = new Set();
  const bekleyen = [[], [], [], []];
  const ekle = (url) => {
    const a = anahtar(url);
    if (gorulen.has(a) || ATLA.test(url)) return;
    gorulen.add(a);
    bekleyen[oncelik(url)].push(url);
  };
  TOHUM.forEach(ekle);

  const sayfalar = new Map();
  const alinan = { 0: 0, 1: 0, 2: 0, 3: 0 };

  while (sayfalar.size < SINIR) {
    let url = null, o = 0;
    for (o = 0; o < 4; o++) {
      while (bekleyen[o].length && alinan[o] >= PAY[o]) bekleyen[o].shift();
      if (bekleyen[o].length) { url = bekleyen[o].shift(); break; }
    }
    if (!url) break;

    const y = await istek.get(url);
    if (y.status() !== 200) continue;
    const html = await y.text();
    if (!/varlik=css/.test(html)) continue;

    alinan[o]++;
    sayfalar.set(anahtar(url), { url, dosya: dosyaAdi(url), html });

    for (const m of html.matchAll(/href="([^"]+)"/g)) {
      const hedef = cozUrl(m[1]);
      if (hedef && panelMi(hedef)) ekle(hedef);
    }
  }
  console.log('öncelik dağılımı — ana ekran: %d, kart: %d, süzgeç: %d, sıralama: %d', alinan[0], alinan[1], alinan[2], alinan[3]);

  // Bağlantıları yerel dosyalara çevir
  const harita = new Map([...sayfalar.values()].map((s) => [anahtar(s.url), s.dosya]));

  const ekBetik = `
<script>
// KURAL: Bu bir önizlemedir — veri yazan hiçbir işlem çalışmaz, form gönderimi engellenir.
(function(){
  document.addEventListener('submit', function(e){ e.preventDefault();
    var u = document.getElementById('onizleme-uyari'); if (u) { u.hidden = false; clearTimeout(u._z); u._z = setTimeout(function(){ u.hidden = true; }, 2600); }
  }, true);
  document.addEventListener('click', function(e){
    var a = e.target.closest ? e.target.closest('a.onizleme-kapali') : null;
    if (a) { e.preventDefault();
      var u = document.getElementById('onizleme-uyari'); if (u) { u.hidden = false; clearTimeout(u._z); u._z = setTimeout(function(){ u.hidden = true; }, 2600); }
    }
  }, true);
  if (window.fetch) { var eski = window.fetch; window.fetch = function(){ return Promise.reject(new Error('onizleme')); }; }
})();
</script>
<style>
#onizleme-cubuk{position:fixed;left:10px;bottom:10px;z-index:99999;display:flex;gap:6px;align-items:center;font:500 11px/1 system-ui,sans-serif}
#onizleme-cubuk a{background:rgba(20,26,40,.82);color:#fff;text-decoration:none;padding:6px 10px;border-radius:999px;backdrop-filter:blur(4px);border:1px solid rgba(255,255,255,.18)}
#onizleme-cubuk a:hover{background:rgba(20,26,40,.95)}
#onizleme-uyari{position:fixed;left:50%;transform:translateX(-50%);bottom:14px;z-index:99999;background:#a3271b;color:#fff;padding:8px 14px;border-radius:4px;font:500 12px/1.3 system-ui,sans-serif;box-shadow:0 6px 20px -8px rgba(0,0,0,.6)}
#onizleme-uyari[hidden]{display:none}
</style>
<div id="onizleme-cubuk"><a href="index.html">&#8592; Önizleme kapağı</a></div>
<div id="onizleme-uyari" hidden>Önizlemede kayıt yapılmaz — bu düğme yalnızca gerçek kurulumda çalışır.</div>
`;

  let kapaliSayisi = 0;
  for (const s of sayfalar.values()) {
    let html = s.html;

    // Varlık adresleri
    html = html.replace(/https?:\/\/localhost:8765\/panel\/\?varlik=css[^"']*/g, 'varliklar/panel.css');
    html = html.replace(/https?:\/\/localhost:8765\/panel\/\?varlik=js[^"']*/g, 'varliklar/panel.js');
    html = html.replace(/https?:\/\/localhost:8765\/panel\/\?ekran=logo[^"']*/g, 'varliklar/minilogo.png');

    // İç bağlantılar
    html = html.replace(/href="([^"]+)"/g, (tam, ham) => {
      const hedef = cozUrl(ham);
      if (!hedef || !panelMi(hedef)) {
        if (hedef && hedef.startsWith(KOK)) {
          kapaliSayisi++;
          return 'href="#" class="onizleme-kapali"';
        }
        return tam; // dış bağlantı (web sitesi vb.) olduğu gibi kalsın
      }
      const dosya = harita.get(anahtar(hedef));
      if (dosya) return `href="${dosya}"`;
      kapaliSayisi++;
      return 'href="#" class="onizleme-kapali"';
    });

    // Form gönderimleri etkisiz
    html = html.replace(/action="[^"]*"/g, 'action="#"');

    html = html.replace(/<\/body>/i, ekBetik + '</body>');
    fs.writeFileSync(path.join(CIKTI, s.dosya), html, 'utf8');
  }

  await tarayici.close();

  const dokum = [...sayfalar.values()].map((s) => ({ adres: new URL(s.url).search || '(ana menü)', dosya: s.dosya }));
  fs.writeFileSync(path.join(CIKTI, 'dokum.json'), JSON.stringify(dokum, null, 1), 'utf8');
  console.log(`dışa aktarılan sayfa: ${sayfalar.size}`);
  console.log(`kapatılan bağlantı: ${kapaliSayisi}`);
  console.log(`toplam boyut: ${(fs.readdirSync(CIKTI).reduce((t, f) => { const p = path.join(CIKTI, f); return t + (fs.statSync(p).isFile() ? fs.statSync(p).size : 0); }, 0) / 1048576).toFixed(2)} MB`);
})().catch((e) => { console.error('AKTARIM HATASI:', e); process.exit(1); });
