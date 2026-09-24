/* Yüzyıl Panel — arayüz davranışları. KURAL: Harici kütüphane yok; veri değiştiren her istek sunucuda yetki+nonce ile doğrulanır. */
(function () {
	'use strict';

	var govde = document.body;

	function hepsi(secici, kok) {
		return Array.prototype.slice.call((kok || document).querySelectorAll(secici));
	}

	// ---- Sütun taşıma: başlık sürüklenerek sütunlar yer değiştirir ----
	// KURAL: Sıra yalnızca bu tarayıcıda saklanır (localStorage); veriye ve diğer kullanıcılara dokunmaz.
	(function () {
		var tablo = document.querySelector('[data-sutun-tasi]');
		if (!tablo || !tablo.tHead || !tablo.tBodies.length) { return; }
		var anahtar = 'yp-sutun-' + tablo.dataset.sutunTasi;
		var baslikSatiri = tablo.tHead.rows[0];

		function anahtarlar() {
			return Array.prototype.map.call(baslikSatiri.cells, function (th) { return th.dataset.sutun || ''; });
		}

		function satirlar() {
			var hepsiSatir = [baslikSatiri];
			Array.prototype.forEach.call(tablo.tBodies[0].rows, function (tr) { hepsiSatir.push(tr); });
			return hepsiSatir;
		}

		// Kaydedilmiş sıraya göre hücreleri yeniden dizer.
		// KURAL: Sonradan eklenen bir sütun listenin sonuna atılmaz; varsayılan komşusunun hemen arkasına girer.
		function sirayiBirlestir(kayitli, varsayilan) {
			var sonuc = kayitli.filter(function (k) { return varsayilan.indexOf(k) > -1; });
			varsayilan.forEach(function (k, i) {
				if (sonuc.indexOf(k) > -1) { return; }
				var yer = 0;
				for (var j = i - 1; j >= 0; j--) {
					var nerede = sonuc.indexOf(varsayilan[j]);
					if (nerede > -1) { yer = nerede + 1; break; }
				}
				sonuc.splice(yer, 0, k);
			});
			return sonuc;
		}

		function uygula(sira) {
			var simdiki = anahtarlar();
			var duzen = [];
			sirayiBirlestir(sira, simdiki).forEach(function (k) {
				var i = simdiki.indexOf(k);
				if (i > -1 && duzen.indexOf(i) === -1) { duzen.push(i); }
			});
			simdiki.forEach(function (k, i) { if (duzen.indexOf(i) === -1) { duzen.push(i); } });
			if (duzen.length !== simdiki.length) { return; }
			satirlar().forEach(function (satir) {
				if (satir.cells.length !== duzen.length) { return; } // "kayıt yok" satırı colspan'lıdır, atlanır
				var hucreler = Array.prototype.slice.call(satir.cells);
				duzen.forEach(function (i) { satir.appendChild(hucreler[i]); });
			});
		}

		function kaydet() {
			try { window.localStorage.setItem(anahtar, JSON.stringify(anahtarlar())); } catch (e) {}
		}

		try {
			var kayitli = JSON.parse(window.localStorage.getItem(anahtar) || 'null');
			if (Array.isArray(kayitli) && kayitli.length) { uygula(kayitli); }
		} catch (e) {}

		// KURAL: "Sütun Sırası" düğmesi kaydı siler ve sayfayı yeniler — varsayılan sıra geri gelir.
		var sifirla = document.querySelector('[data-sutun-sifirla]');
		if (sifirla) {
			sifirla.addEventListener('click', function () {
				try { window.localStorage.removeItem(anahtar); } catch (e) {}
				window.location.reload();
			});
		}

		var kaynak = null;
		Array.prototype.forEach.call(baslikSatiri.cells, function (th) {
			if (!th.dataset.sutun) { return; }
			th.draggable = true;
			th.addEventListener('dragstart', function (e) {
				kaynak = th;
				th.classList.add('tasiniyor');
				try { e.dataTransfer.setData('text/plain', th.dataset.sutun); e.dataTransfer.effectAllowed = 'move'; } catch (x) {}
			});
			th.addEventListener('dragend', function () {
				th.classList.remove('tasiniyor');
				hepsi('.hedef', tablo).forEach(function (x) { x.classList.remove('hedef'); });
				kaynak = null;
			});
			th.addEventListener('dragover', function (e) {
				if (!kaynak || kaynak === th) { return; }
				e.preventDefault();
				th.classList.add('hedef');
			});
			th.addEventListener('dragleave', function () { th.classList.remove('hedef'); });
			th.addEventListener('drop', function (e) {
				e.preventDefault();
				th.classList.remove('hedef');
				if (!kaynak || kaynak === th) { return; }
				var sira = anahtarlar();
				var eski = sira.indexOf(kaynak.dataset.sutun);
				var yeni = sira.indexOf(th.dataset.sutun);
				if (eski < 0 || yeni < 0) { return; }
				sira.splice(yeni, 0, sira.splice(eski, 1)[0]);
				uygula(sira);
				kaydet();
			});
		});
	}());

	// ---- Ana menü karoları: uzun basınca düzen kipi açılır, karolar sürüklenerek taşınır ----
	// KURAL: Karolar sabit yuvalara oturur; araya boş yuva bırakılabilir — dizilim kullanıcıya aittir.
	// KURAL: Yerleşim yalnızca bu tarayıcıda saklanır (localStorage); veriye ve diğer kullanıcılara dokunmaz.
	(function () {
		var izgara = document.querySelector('[data-karo-duzen]');
		if (!izgara) { return; }
		var karolar = hepsi('.karo[data-karo]', izgara);
		if (!karolar.length) { return; }

		var SUTUN = 3, SATIR = 5, YUVA = SUTUN * SATIR;
		// KURAL: Sütun sayısı değişince eski kayıt kullanılmaz — anahtarda sütun sayısı vardır.
		var anahtar = 'yp-karo-yerlesim-' + SUTUN;
		// KURAL: Basılı tutma süresi kısa, kayma payı geniştir — elin küçük titremesi taşımayı iptal etmez.
		var BASILI_SURE = 350, KAYMA_SINIRI = 16;
		var yerlesim = {}, duzenKipi = false, tasinan = null, bekleyen = null;

		// KURAL: Varsayılan dizilimde soldaki sütun boş kalır; karolar ikişerli olarak sağ iki sütuna dizilir.
		function varsayilanYuva(sira) {
			return Math.floor(sira / 2) * SUTUN + (sira % 2) + 1;
		}

		// Kayıtlı yerleşimi düzeltir: geçersiz/çakışan yuvalar önce varsayılan yerine, orası doluysa ilk boş yuvaya alınır.
		function duzelt(ham) {
			var alinan = {}, eksik = [], sonuc = {};
			karolar.forEach(function (k, i) {
				var ad = k.dataset.karo;
				var y = ham && typeof ham[ad] === 'number' ? Math.floor(ham[ad]) : -1;
				if (y >= 0 && y < YUVA && !alinan[y]) { alinan[y] = 1; sonuc[ad] = y; } else { eksik.push({ ad: ad, sira: i }); }
			});
			eksik.forEach(function (x) {
				var y = varsayilanYuva(x.sira);
				if (y >= YUVA || alinan[y]) {
					y = 0;
					while (alinan[y]) { y++; }
				}
				alinan[y] = 1;
				sonuc[x.ad] = y;
			});
			return sonuc;
		}

		function uygula() {
			karolar.forEach(function (k) {
				var y = yerlesim[k.dataset.karo];
				k.style.gridColumn = String((y % SUTUN) + 1);
				k.style.gridRow = String(Math.floor(y / SUTUN) + 1);
			});
			if (duzenKipi) { yuvalariCiz(); }
		}

		function kaydet() {
			try { window.localStorage.setItem(anahtar, JSON.stringify(yerlesim)); } catch (e) {}
		}

		// KURAL: Yuva ölçüsü ızgaranın gerçek satır/sütun boylarından okunur — ekran boyu değişse de hesap şaşmaz.
		function olcu() {
			var st = window.getComputedStyle(izgara);
			var sayi = function (m) { return (m || '').split(' ').map(parseFloat).filter(function (x) { return !isNaN(x); }); };
			return {
				kutu: izgara.getBoundingClientRect(),
				sutunlar: sayi(st.gridTemplateColumns).slice(0, SUTUN),
				satirlar: sayi(st.gridTemplateRows).slice(0, SATIR),
				gx: parseFloat(st.columnGap) || 0,
				gy: parseFloat(st.rowGap) || 0
			};
		}

		// KURAL: Bırakılan nokta bir yuvanın içinde değilse (iki karo arasındaki boşluksa) en yakın yuva seçilir
		// — karo, hedefe tam isabet ettirilmek zorunda kalmaz.
		function yuvaBul(x, y) {
			var o = olcu();
			var merkezler = [];
			var ust = o.kutu.top;
			for (var j = 0; j < o.satirlar.length; j++) {
				var sol = o.kutu.left;
				for (var i = 0; i < o.sutunlar.length; i++) {
					merkezler.push({ yuva: j * SUTUN + i, x: sol + o.sutunlar[i] / 2, y: ust + o.satirlar[j] / 2 });
					sol += o.sutunlar[i] + o.gx;
				}
				ust += o.satirlar[j] + o.gy;
			}
			var en_yakin = -1, en_kisa = Infinity;
			merkezler.forEach(function (m) {
				var uzaklik = Math.pow(m.x - x, 2) + Math.pow(m.y - y, 2);
				if (uzaklik < en_kisa) { en_kisa = uzaklik; en_yakin = m.yuva; }
			});
			// Izgaranın epey dışına bırakılırsa taşıma iptal olur.
			var pay = 80;
			if (x < o.kutu.left - pay || x > o.kutu.right + pay || y < o.kutu.top - pay || y > o.kutu.bottom + pay) {
				return -1;
			}
			return en_yakin;
		}

		// KURAL: Sürüklerken altındaki karo işaretlenir — bırakılınca hangi ikisinin yer değiştireceği önceden görünür.
		function hedefiIsaretle(yuva) {
			karolar.forEach(function (k) {
				k.classList.toggle('karo-hedef', yuva >= 0 && !k.classList.contains('tasiniyor') && yerlesim[k.dataset.karo] === yuva);
			});
		}

		// Boş yuvalar düzen kipinde kesik çizgiyle gösterilir — nereye bırakılacağı görünür.
		function yuvalariCiz() {
			hepsi('.karo-yuva', izgara).forEach(function (x) { x.parentNode.removeChild(x); });
			var dolu = {};
			karolar.forEach(function (k) { dolu[yerlesim[k.dataset.karo]] = 1; });
			for (var y = 0; y < YUVA; y++) {
				if (dolu[y]) { continue; }
				var kutu = document.createElement('span');
				kutu.className = 'karo-yuva';
				kutu.style.gridColumn = String((y % SUTUN) + 1);
				kutu.style.gridRow = String(Math.floor(y / SUTUN) + 1);
				izgara.appendChild(kutu);
			}
		}

		function kipiAc() {
			if (duzenKipi) { return; }
			duzenKipi = true;
			izgara.classList.add('duzen');
			yuvalariCiz();
		}

		function kipiKapat() {
			if (!duzenKipi) { return; }
			duzenKipi = false;
			izgara.classList.remove('duzen');
			hepsi('.karo-yuva', izgara).forEach(function (x) { x.parentNode.removeChild(x); });
		}

		function basilmayiBirak() {
			if (bekleyen) { window.clearTimeout(bekleyen); bekleyen = null; }
		}

		karolar.forEach(function (karo) {
			karo.addEventListener('pointerdown', function (e) {
				if (e.button !== undefined && e.button !== 0) { return; }
				var basX = e.clientX, basY = e.clientY;
				if (!duzenKipi) {
					// KURAL: Düzen kipi ancak parmak/fare yerinden oynamadan uzun süre basılı tutulursa açılır — tek tık ekranı açmaya devam eder.
					basilmayiBirak();
					bekleyen = window.setTimeout(function () { bekleyen = null; kipiAc(); baslat(); }, BASILI_SURE);
					var izle = function (h) {
						if (Math.abs(h.clientX - basX) > KAYMA_SINIRI || Math.abs(h.clientY - basY) > KAYMA_SINIRI) { basilmayiBirak(); }
					};
					var bitir = function () {
						basilmayiBirak();
						document.removeEventListener('pointermove', izle);
						document.removeEventListener('pointerup', bitir);
						document.removeEventListener('pointercancel', bitir);
					};
					document.addEventListener('pointermove', izle);
					document.addEventListener('pointerup', bitir);
					document.addEventListener('pointercancel', bitir);
					return;
				}
				e.preventDefault();
				baslat();

				function baslat() {
					var kutu = karo.getBoundingClientRect();
					tasinan = { karo: karo, x: basX, y: basY, kaydi: false };
					karo.classList.add('tasiniyor');
					try { karo.setPointerCapture(e.pointerId); } catch (x) {}
					karo.style.width = kutu.width + 'px';
					karo.style.height = kutu.height + 'px';
				}
			});

			karo.addEventListener('pointermove', function (e) {
				if (!tasinan || tasinan.karo !== karo) { return; }
				var dx = e.clientX - tasinan.x, dy = e.clientY - tasinan.y;
				if (!tasinan.kaydi && (Math.abs(dx) > 3 || Math.abs(dy) > 3)) { tasinan.kaydi = true; }
				karo.style.transform = 'translate(' + dx + 'px,' + dy + 'px)';
				hedefiIsaretle(yuvaBul(e.clientX, e.clientY));
			});

			var birak = function (e) {
				if (!tasinan || tasinan.karo !== karo) { return; }
				var hedef = yuvaBul(e.clientX, e.clientY);
				hedefiIsaretle(-1);
				karo.classList.remove('tasiniyor');
				karo.style.transform = '';
				karo.style.width = '';
				karo.style.height = '';
				tasinan = null;
				if (hedef < 0) { return; }
				var ad = karo.dataset.karo, eski = yerlesim[ad];
				if (hedef === eski) { return; }
				// KURAL: Hedef yuva doluysa iki karo yer değiştirir; boşsa karo oraya taşınır.
				karolar.forEach(function (k) {
					if (k !== karo && yerlesim[k.dataset.karo] === hedef) { yerlesim[k.dataset.karo] = eski; }
				});
				yerlesim[ad] = hedef;
				uygula();
				kaydet();
			};
			karo.addEventListener('pointerup', birak);
			karo.addEventListener('pointercancel', function () {
				if (!tasinan || tasinan.karo !== karo) { return; }
				hedefiIsaretle(-1);
				karo.classList.remove('tasiniyor');
				karo.style.transform = '';
				karo.style.width = '';
				karo.style.height = '';
				tasinan = null;
			});
			// KURAL: Düzen kipindeyken karoya tıklamak ekranı açmaz — yalnızca taşıma yapılır.
			karo.addEventListener('click', function (e) {
				if (duzenKipi) { e.preventDefault(); }
			});
			karo.addEventListener('contextmenu', function (e) {
				if (duzenKipi) { e.preventDefault(); }
			});
			karo.addEventListener('dragstart', function (e) { e.preventDefault(); });
		});

		var bittiDugmesi = izgara.querySelector('[data-karo-bitti]');
		if (bittiDugmesi) { bittiDugmesi.addEventListener('click', kipiKapat); }
		var varsayilanDugmesi = izgara.querySelector('[data-karo-varsayilan]');
		if (varsayilanDugmesi) {
			varsayilanDugmesi.addEventListener('click', function () {
				yerlesim = duzelt(null);
				uygula();
				kaydet();
			});
		}
		document.addEventListener('keydown', function (e) {
			if ('Escape' === e.key) { kipiKapat(); }
		});
		document.addEventListener('pointerdown', function (e) {
			if (duzenKipi && !izgara.contains(e.target)) { kipiKapat(); }
		});

		try {
			yerlesim = duzelt(JSON.parse(window.localStorage.getItem(anahtar) || 'null'));
		} catch (e) {
			yerlesim = duzelt(null);
		}
		uygula();
	}());

	// ---- Araç şeridi: kapalı başlar, sağ üstteki ok düğmesiyle açılır ----
	// KURAL: Seçim tarayıcıda hatırlanır; şerit kapalıyken ekranın tamamı listeye kalır.
	(function () {
		var anahtar = document.querySelector('[data-serit-anahtar]');
		if (!anahtar) { return; }
		if (!document.querySelector('.ekran-serit, .hub-serit')) {
			anahtar.hidden = true;
			return;
		}
		// KURAL: Şerit kapalıyken de en çok kullanılan düğmeler (Kaydet, Ödeme Yap gibi) araç çubuğunda durur.
		var yuva = document.querySelector('[data-birincil-yuva]');
		var adSeridi = yuva || document.querySelector('.ekran-ad');
		var birincil = hepsi('.serit-birincil');
		if (adSeridi && birincil.length) {
			var kutu = yuva || document.createElement('div');
			if (!yuva) { kutu.className = 'ad-islem'; }
			birincil.forEach(function (d) {
				if (d.closest('form')) { return; }
				var kopya = d.cloneNode(true);
				kopya.classList.remove('serit-birincil');
				kutu.appendChild(kopya);
			});
			if (!yuva && kutu.childNodes.length) { adSeridi.insertBefore(kutu, adSeridi.querySelector('.ad-bilgi')); }
		}
		var acik = false;
		try { acik = window.localStorage.getItem('yp-serit') === 'acik'; } catch (e) { acik = false; }
		function uygula() {
			govde.classList.toggle('serit-acik', acik);
			anahtar.setAttribute('aria-expanded', acik ? 'true' : 'false');
			anahtar.title = acik ? 'Araç şeridini kapat' : 'Araç şeridini aç';
		}
		uygula();
		anahtar.addEventListener('click', function () {
			acik = !acik;
			uygula();
			try { window.localStorage.setItem('yp-serit', acik ? 'acik' : 'kapali'); } catch (e) {}
		});
	}());

	// ---- Aday listesi: tek tık seçer, çift tık kartı açar ----
	// KURAL: Tek tıkta satır maviye döner ve fotoğrafı solda görünür; kart yalnızca çift tıkta açılır — yanlışlıkla sayfa değişmez.
	var fotoCerceve = document.querySelector('[data-foto-cerceve]');
	var fotoAd = document.querySelector('[data-foto-ad]');
	var fotoAyrinti = document.querySelector('[data-foto-ayrinti]');

	function satirSec(tr) {
		hepsi('tr.secili').forEach(function (x) { x.classList.remove('secili'); });
		tr.classList.add('secili');
		if (!fotoCerceve) { return; }
		// KURAL: Fotoğraf alanı DOM ile kurulur — innerHTML kullanılmaz.
		var url = tr.dataset.foto;
		while (fotoCerceve.firstChild) { fotoCerceve.removeChild(fotoCerceve.firstChild); }
		if (url) {
			var img = document.createElement('img');
			img.src = url;
			img.alt = '';
			fotoCerceve.appendChild(img);
		} else {
			var bos = document.createElement('span');
			bos.textContent = 'Bu adayın fotoğrafı yok';
			fotoCerceve.appendChild(bos);
		}
		if (fotoAd) { fotoAd.textContent = tr.dataset.ad || ''; }
		if (fotoAyrinti) { fotoAyrinti.textContent = tr.dataset.ayrinti || ''; }
	}

	var listeGovdesi = document.querySelector('.aday-listesi tbody');
	if (listeGovdesi) {
		listeGovdesi.addEventListener('click', function (e) {
			var tr = e.target.closest('tr[data-git]');
			if (!tr || e.target.closest('a, button, input, label')) { return; }
			satirSec(tr);
		});
		listeGovdesi.addEventListener('dblclick', function (e) {
			var tr = e.target.closest('tr[data-git]');
			if (!tr || e.target.closest('a, button, input, label')) { return; }
			window.location.href = tr.dataset.git;
		});
		// KURAL: Klavyeyle de gezilebilir: yukarı/aşağı seçer, Enter kartı açar.
		listeGovdesi.addEventListener('keydown', function (e) {
			var secili = listeGovdesi.querySelector('tr.secili');
			if (!secili) { return; }
			if (e.key === 'Enter') { window.location.href = secili.dataset.git; }
		});
		document.addEventListener('keydown', function (e) {
			if (e.target.matches('input, textarea, select')) { return; }
			if (e.key !== 'ArrowDown' && e.key !== 'ArrowUp') { return; }
			var satirlar = hepsi('tr[data-git]', listeGovdesi);
			if (!satirlar.length) { return; }
			var simdi = satirlar.indexOf(listeGovdesi.querySelector('tr.secili'));
			var yeni = e.key === 'ArrowDown' ? Math.min(satirlar.length - 1, simdi + 1) : Math.max(0, simdi - 1);
			if (simdi === -1) { yeni = 0; }
			e.preventDefault();
			satirSec(satirlar[yeni]);
			satirlar[yeni].scrollIntoView({ block: 'nearest' });
		});
		var ilk = listeGovdesi.querySelector('tr[data-git]');
		if (ilk) { satirSec(ilk); }
	}

	// ---- Hızlı arama (yazarken sonuç) ----
	// KURAL: En az 2 harf yazılınca ve yazmayı bıraktıktan 220 ms sonra arama yapılır — her tuşta sunucuya gidilmez.
	var araGirdi = document.querySelector('[data-hizli-ara]');
	var araKutu = document.querySelector('[data-hizli-sonuc]');
	if (araGirdi && araKutu && window.fetch) {
		var zaman = null;
		var sonIstek = 0;
		// KURAL: Arama sonuçları DOM ile kurulur; aday adı vb. yalnızca textContent ile yazılır — innerHTML ile kullanıcı verisi basılmaz (XSS).
		var bosalt = function (el) { while (el.firstChild) { el.removeChild(el.firstChild); } };
		// KURAL: Sonuçtaki adresler yalnızca bu sitenin http(s) adresi olabilir — javascript: gibi adresler kullanılmaz.
		var guvenliAdres = function (ham) {
			try {
				var u = new URL(String(ham || ''), window.location.href);
				return (u.protocol === 'http:' || u.protocol === 'https:') && u.origin === window.location.origin ? u.href : '';
			} catch (e) { return ''; }
		};
		var ogeYap = function (etiket, sinif, metin) {
			var el = document.createElement(etiket);
			if (sinif) { el.className = sinif; }
			if (metin) { el.textContent = metin; }
			return el;
		};
		var kapat = function () { araKutu.hidden = true; bosalt(araKutu); };
		var ciz = function (liste) {
			bosalt(araKutu);
			if (!liste.length) {
				araKutu.appendChild(ogeYap('div', 'hizli-bos', 'Sonuç yok'));
				araKutu.hidden = false;
				return;
			}
			liste.forEach(function (s, i) {
				var adres = guvenliAdres(s.adres);
				if (!adres) { return; }
				var a = document.createElement('a');
				a.href = adres;
				if (i === 0) { a.className = 'secili'; }
				var foto = guvenliAdres(s.foto);
				if (foto) {
					var img = document.createElement('img');
					img.src = foto;
					img.alt = '';
					img.width = 30;
					img.height = 39;
					img.loading = 'lazy';
					a.appendChild(img);
				} else {
					a.appendChild(ogeYap('span', 'foto-kucuk bos-foto'));
				}
				var ad = ogeYap('span', 'hizli-ad', String(s.ad || ''));
				ad.appendChild(ogeYap('small', 'soluk', String(s.alt || '')));
				a.appendChild(ad);
				if (s.kalan) { a.appendChild(ogeYap('b', 'kirmizi', String(s.kalan))); }
				araKutu.appendChild(a);
			});
			araKutu.hidden = false;
		};
		araGirdi.addEventListener('input', function () {
			var q = araGirdi.value.trim();
			window.clearTimeout(zaman);
			if (q.length < 2) { kapat(); return; }
			zaman = window.setTimeout(function () {
				var istek = ++sonIstek;
				var adres = govde.dataset.panel + '?ekran=ara&_yp_nonce=' + encodeURIComponent(govde.dataset.araNonce) + '&q=' + encodeURIComponent(q);
				fetch(adres, { credentials: 'same-origin' })
					.then(function (r) { return r.json(); })
					.then(function (j) { if (istek === sonIstek) { ciz((j && j.sonuc) || []); } })
					.catch(function () { kapat(); });
			}, 220);
		});
		araGirdi.addEventListener('keydown', function (e) {
			var secili = araKutu.querySelector('a.secili');
			if (e.key === 'Escape') { kapat(); return; }
			if (e.key === 'Enter' && secili && !araKutu.hidden) { e.preventDefault(); window.location.href = secili.href; return; }
			if (e.key !== 'ArrowDown' && e.key !== 'ArrowUp') { return; }
			var hepsiA = hepsi('a', araKutu);
			if (!hepsiA.length) { return; }
			e.preventDefault();
			var simdi = hepsiA.indexOf(secili);
			var yeni = e.key === 'ArrowDown' ? Math.min(hepsiA.length - 1, simdi + 1) : Math.max(0, simdi - 1);
			hepsiA.forEach(function (x) { x.classList.remove('secili'); });
			hepsiA[yeni].classList.add('secili');
			hepsiA[yeni].scrollIntoView({ block: 'nearest' });
		});
		document.addEventListener('click', function (e) {
			if (!e.target.closest('.hizli-ara')) { kapat(); }
		});
	}

	// ---- Kasa özet sekmeleri ----
	hepsi('[data-ozet]').forEach(function (d) {
		d.addEventListener('click', function () {
			hepsi('[data-ozet]').forEach(function (x) { x.classList.toggle('aktif', x === d); });
			hepsi('[data-ozet-icerik]').forEach(function (x) { x.hidden = x.dataset.ozetIcerik !== d.dataset.ozet; });
		});
	});

	// ---- Tarih alanı: gg.aa.yyyy ----
	// KURAL: Yalnızca rakam yazılır, noktalar otomatik eklenir; "b" yazılırsa bugünün tarihi gelir.
	function bugun() {
		var d = new Date();
		return ('0' + d.getDate()).slice(-2) + '.' + ('0' + (d.getMonth() + 1)).slice(-2) + '.' + d.getFullYear();
	}
	document.addEventListener('input', function (e) {
		var el = e.target;
		if (!el.matches || !el.matches('[data-tarih]')) { return; }
		if (/b$/i.test(el.value)) { el.value = bugun(); return; }
		var r = el.value.replace(/\D/g, '').slice(0, 8);
		var s = r;
		if (r.length > 4) { s = r.slice(0, 2) + '.' + r.slice(2, 4) + '.' + r.slice(4); }
		else if (r.length > 2) { s = r.slice(0, 2) + '.' + r.slice(2); }
		if (e.inputType && e.inputType.indexOf('delete') === 0) { return; }
		el.value = s;
	});

	// ---- Tutar alanı: 1.250,00 ----
	function tutarOku(m) {
		m = String(m || '').replace(/[^\d,.\-]/g, '');
		if (m.indexOf(',') > -1) { m = m.replace(/\./g, '').replace(',', '.'); }
		else if ((m.match(/\./g) || []).length > 1 || /\.\d{3}$/.test(m)) { m = m.replace(/\./g, ''); }
		var n = parseFloat(m);
		return isNaN(n) ? null : n;
	}
	function tutarYaz(n) {
		return n.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
	}
	document.addEventListener('blur', function (e) {
		var el = e.target;
		if (!el.matches) { return; }
		if (el.matches('input.tutar, .tutar input') && el.value.trim() !== '') {
			var n = tutarOku(el.value);
			if (n !== null) { el.value = tutarYaz(n); }
		}
		// KURAL: TC 11 hane ve algoritmaya uymuyorsa alan kırmızı çerçeve alır — kayıt yine yapılabilir.
		if (el.matches('[data-tc]')) {
			el.style.borderColor = (el.value === '' || tcGecerli(el.value)) ? '' : '#c62f2f';
			el.title = (el.value === '' || tcGecerli(el.value)) ? '' : 'TC kimlik no doğrulama kuralına uymuyor';
		}
	}, true);

	function tcGecerli(tc) {
		if (!/^[1-9]\d{10}$/.test(tc)) { return false; }
		var d = tc.split('').map(Number);
		var h10 = ((d[0] + d[2] + d[4] + d[6] + d[8]) * 7 - (d[1] + d[3] + d[5] + d[7])) % 10;
		if (h10 < 0) { h10 += 10; }
		var h11 = d.slice(0, 10).reduce(function (a, b) { return a + b; }, 0) % 10;
		return h10 === d[9] && h11 === d[10];
	}

	// ---- İşlem türü seçilince ücreti doldur ----
	document.addEventListener('change', function (e) {
		var el = e.target;
		if (el.matches('[data-ucret-doldur]')) {
			var form = el.form;
			var alan = form && form.querySelector('[data-ucret-alani] input, input[data-ucret-alani]');
			var sec = el.options[el.selectedIndex];
			if (alan && sec && sec.dataset.ucret && !alan.readOnly && (alan.value === '' || alan.dataset.otomatik === '1')) {
				alan.value = sec.dataset.ucret;
				alan.dataset.otomatik = '1';
			}
		}
		if (el.matches('[data-odeme-sekli]')) { odemeSekli(el); }
		// KURAL: Ödeme türü değişince hesap "Otomatik"e döner — sunucu türe uyan hesabı seçer.
		if (el.matches('[data-hesap-oner]') && el.form) {
			var h = el.form.querySelector('select[name=hesap_id]');
			if (h && h.querySelector('option[value=""]')) { h.value = ''; }
		}
	});
	document.addEventListener('input', function (e) {
		if (e.target.matches && e.target.matches('input[data-ucret-alani]')) { e.target.dataset.otomatik = '0'; }
	});

	function odemeSekli(sel) {
		var form = sel.form;
		if (!form) { return; }
		var v = sel.value;
		hepsi('.sekil-pesin', form).forEach(function (x) { x.hidden = v !== 'pesin'; });
		hepsi('.sekil-taksit', form).forEach(function (x) { x.hidden = v !== 'taksit'; });
		hepsi('.sekil-borc', form).forEach(function (x) { x.hidden = !(v === 'borc' || v === 'taksit'); });
	}
	hepsi('[data-odeme-sekli]').forEach(odemeSekli);

	// ---- Pencereler ----
	function doldur(dialog, veri) {
		var form = dialog.querySelector('form');
		if (form) { form.reset(); }
		hepsi('input[data-ucret-alani]', dialog).forEach(function (x) { x.readOnly = false; x.dataset.otomatik = ''; });
		Object.keys(veri || {}).forEach(function (ad) {
			var deger = veri[ad] === null ? '' : String(veri[ad]);
			hepsi('[data-alan="' + ad + '"]', dialog).forEach(function (x) { x.textContent = deger; });
			if (!form) { return; }
			var el = form.elements[ad];
			if (!el) { return; }
			// KURAL: Boş veya "0" değer seçim kutusunda "Yok/Seçiniz" demektir — listeye yeni seçenek eklenmez.
			if (el.tagName === 'SELECT' && (deger === '0' || deger === '')) { deger = ''; }
			if (el.tagName === 'SELECT' && deger !== '' && !el.querySelector('option[value="' + CSS.escape(deger) + '"]')) {
				var o = document.createElement('option');
				o.value = deger;
				o.textContent = deger;
				el.appendChild(o);
			}
			el.value = deger;
		});
		// KURAL: Kayıtlı işlem düzenlenirken ödeme bölümü gizlenir, ücret salt okunur olur.
		var duzen = veri && veri.islem_id && String(veri.islem_id) !== '0' && dialog.querySelector('[data-islem-formu]');
		dialog.classList.toggle('duzenleme', !!duzen);
		if (duzen) { hepsi('input[data-ucret-alani]', dialog).forEach(function (x) { x.readOnly = true; }); }
		hepsi('[data-odeme-sekli]', dialog).forEach(odemeSekli);
	}

	// KURAL: Şeritteki "Süzgeçler" düğmesi sayfadaki katlanır bölümü açıp kapatır — ayrı pencere açılmaz.
	document.addEventListener('click', function (e) {
		var t = e.target.closest('[data-ac]');
		if (!t) { return; }
		var bolum = document.getElementById(t.dataset.ac);
		if (!bolum) { return; }
		bolum.open = !bolum.open;
		if (bolum.open) {
			var ilk = bolum.querySelector('input:not([type=hidden]), select');
			if (ilk) { ilk.focus(); }
		}
	});

	document.addEventListener('click', function (e) {
		var ac = e.target.closest('[data-dialog]');
		if (ac) {
			var d = document.getElementById(ac.dataset.dialog);
			if (d) {
				var veri = {};
				if (ac.dataset.doldur) { try { veri = JSON.parse(ac.dataset.doldur); } catch (x) { veri = {}; } }
				doldur(d, veri);
				d.showModal();
				var ilk = d.querySelector('input:not([type=hidden]):not([readonly]), select, textarea');
				if (ilk && window.matchMedia('(min-width: 900px)').matches) { ilk.focus(); }
			}
			return;
		}
		var kapat = e.target.closest('[data-kapat]');
		if (kapat) {
			var dlg = kapat.closest('dialog');
			if (dlg) { dlg.close(); }
			return;
		}
		if (e.target.tagName === 'DIALOG') { e.target.close(); return; }

		var yazdir = e.target.closest('[data-yazdir]');
		if (yazdir) { window.print(); return; }

		// Diğer listelerde satıra tıklayınca kayıt açılır (aday listesi hariç; orada çift tık gerekir).
		// KURAL: Yalnızca satırın kendi düğmesine/alanına ya da satır içi forma tıklanınca gezinme yapılmaz;
		// satırın sayfa formunun (örn. aday kartı) içinde olması gezinmeyi engellemez.
		var tr = e.target.closest('tr[data-git]');
		if (tr && !tr.closest('.aday-listesi') && !e.target.closest('a, button, input, select, textarea, label, form.satir-ici')) {
			window.location.href = tr.dataset.git;
		}
	});

	document.addEventListener('close', function (e) {
		if (e.target.tagName === 'DIALOG') { kameraKapat(); }
	}, true);

	// ---- Kasa ekranı: tür seçimi, satır seçimi, üst şeritteki Kaydet ve Sil ----
	(function () {
		var serit = document.querySelector('.kasa-serit');
		if (!serit) { return; }

		// KURAL: "Yeni gelir / gider" penceresinde tür seçimi, o türe ait alanları açar; diğerleri gizlenir.
		function turUygula(sel) {
			var form = sel.form;
			if (!form) { return; }
			hepsi('.tur-gelir', form).forEach(function (x) { x.hidden = sel.value !== 'gelir'; });
			hepsi('.tur-gider', form).forEach(function (x) { x.hidden = sel.value !== 'gider'; });
		}
		hepsi('[data-kasa-turu]').forEach(turUygula);
		document.addEventListener('change', function (e) {
			if (e.target.matches && e.target.matches('[data-kasa-turu]')) { turUygula(e.target); }
		});

		// KURAL: Listeden tek tık satır seçer; seçim yalnızca silinebilir satırlarda olur.
		var secili = null;
		var silAlani = document.querySelector('[data-kasa-sil-id]');
		document.addEventListener('click', function (e) {
			var tr = e.target.closest('tr[data-kasa-satir]');
			if (!tr || e.target.closest('a, button, input, select, textarea')) { return; }
			hepsi('.kasa-liste tr.secili').forEach(function (x) { x.classList.remove('secili'); });
			tr.classList.add('secili');
			secili = tr.dataset.kasaSatir;
			if (silAlani) { silAlani.value = secili; }
		});

		// KURAL: "Düzenle" seçili gelir/gider satırının bilgileriyle düzeltme penceresini açar.
		var duzenleDugmesi = serit.querySelector('[data-kasa-duzenle]');
		if (duzenleDugmesi) {
			duzenleDugmesi.addEventListener('click', function () {
				var tr = document.querySelector('.kasa-liste tr.secili');
				if (!tr) { window.alert('Önce listeden düzeltilecek satırı seçin.'); return; }
				if (!tr.dataset.doldur) { window.alert('Bu satır buradan düzeltilemez. Aday tahsilatları adayın ödeme ekranından düzeltilir.'); return; }
				var pencere = document.getElementById('d-kasa-duzenle');
				if (!pencere) { return; }
				var veri = {};
				try { veri = JSON.parse(tr.dataset.doldur); } catch (x) { veri = {}; }
				doldur(pencere, veri);
				hepsi('[data-kasa-turu]', pencere).forEach(turUygula);
				pencere.showModal();
			});
		}

		var silDugmesi = serit.querySelector('[data-kasa-sil]');
		if (silDugmesi && silAlani) {
			silDugmesi.addEventListener('click', function () {
				if (!secili) {
					window.alert('Önce listeden silinecek satırı seçin.');
					return;
				}
				// KURAL: requestSubmit kullanılır — formun çift gönderim koruması devrede kalır.
				if (window.confirm('Seçili kayıt silinecek ve hesap bakiyesi değişecek. Emin misiniz?')) {
					if (silAlani.form.requestSubmit) { silAlani.form.requestSubmit(); } else { silAlani.form.submit(); }
				}
			});
		}

		// KURAL: Üst şeritteki "Kaydet" açık pencerenin formunu gönderir; pencere yokken pasiftir.
		var kaydetDugmesi = serit.querySelector('[data-kasa-kaydet]');
		function acikPencere() { return document.querySelector('dialog[open] form'); }
		function kaydetDurumu() {
			if (kaydetDugmesi) { kaydetDugmesi.disabled = !acikPencere(); }
		}
		if (kaydetDugmesi) {
			kaydetDugmesi.addEventListener('click', function () {
				var form = acikPencere();
				if (!form) { return; }
				if (form.requestSubmit) { form.requestSubmit(); } else { form.submit(); }
			});
			document.addEventListener('click', function () { window.setTimeout(kaydetDurumu, 0); });
			document.addEventListener('close', kaydetDurumu, true);
			kaydetDurumu();
		}
	}());

	// ---- Onay isteyen düğmeler ----
	document.addEventListener('submit', function (e) {
		var d = e.submitter || e.target.querySelector('[data-onay]');
		if (d && d.dataset && d.dataset.onay && !window.confirm(d.dataset.onay)) {
			e.preventDefault();
			return;
		}
		// KURAL: Form iki kez gönderilmez — çift tahsilat oluşmaz.
		if (e.target.dataset.gonderildi === '1') { e.preventDefault(); return; }
		if (e.target.method && e.target.method.toLowerCase() === 'post') {
			e.target.dataset.gonderildi = '1';
			setTimeout(function () { e.target.dataset.gonderildi = ''; }, 8000);
		}
	});

	// ---- Fotoğraf gönderimi ----
	// KURAL: Fotoğraf göndermeden önce tarayıcıda en fazla 1600 px'e küçültülür — büyük telefon fotoğrafı hosting sınırına takılmaz; asıl sıkıştırma sunucuda yapılır.
	function fotoGonder(form, blob, dugme) {
		var fd = new FormData(form);
		fd.delete('foto');
		fd.append('foto', blob, blob.name || 'foto.jpg');
		fd.append('ajax', '1');
		if (dugme) { dugme.disabled = true; }
		return fetch(form.action, { method: 'POST', body: fd, credentials: 'same-origin' })
			.then(function (r) { return r.json(); })
			.then(function (j) {
				if (j && j.ok) { kameraKapat(); window.location.reload(); }
				else { window.alert((j && j.mesaj) || 'Fotoğraf kaydedilemedi.'); if (dugme) { dugme.disabled = false; } }
			})
			.catch(function () { window.alert('Fotoğraf kaydedilemedi.'); if (dugme) { dugme.disabled = false; } });
	}
	function kucult(dosya) {
		return new Promise(function (coz, reddet) {
			var img = new Image();
			var url = URL.createObjectURL(dosya);
			img.onload = function () {
				var en = 1600, oran = Math.min(1, en / Math.max(img.naturalWidth, img.naturalHeight));
				// KURAL: Zaten küçük olan fotoğraf tarayıcıda yeniden sıkıştırılmaz — olduğu gibi gönderilir, sunucu doğrular.
				if (oran >= 1) { URL.revokeObjectURL(url); coz(dosya); return; }
				var c = document.createElement('canvas');
				c.width = Math.round(img.naturalWidth * oran);
				c.height = Math.round(img.naturalHeight * oran);
				c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
				URL.revokeObjectURL(url);
				c.toBlob(function (b) { if (b) { coz(b); } else { reddet(); } }, 'image/jpeg', 0.9);
			};
			img.onerror = function () { URL.revokeObjectURL(url); reddet(); };
			img.src = url;
		});
	}
	hepsi('form.foto-formu').forEach(function (form) {
		form.addEventListener('submit', function (e) {
			var girdi = form.querySelector('input[type=file]');
			if (!girdi || !girdi.files.length || !window.fetch) { return; }
			e.preventDefault();
			var dugme = form.querySelector('button[type=submit]');
			kucult(girdi.files[0]).then(function (b) { fotoGonder(form, b, dugme); }, function () {
				window.alert('Bu dosya resim olarak açılamadı. JPG veya PNG seçin.');
			});
		});
	});

	// ---- Kamera ile fotoğraf ----
	var akis = null;
	function kameraKapat() {
		if (akis) { akis.getTracks().forEach(function (t) { t.stop(); }); akis = null; }
		hepsi('[data-kamera]').forEach(function (k) { k.hidden = true; });
		hepsi('[data-kamera-cek]').forEach(function (k) { k.hidden = true; });
	}
	hepsi('[data-kamera-ac]').forEach(function (d) {
		d.addEventListener('click', function () {
			var form = d.closest('form');
			if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
				window.alert('Bu tarayıcı kamerayı desteklemiyor. Dosya seçerek yükleyin.');
				return;
			}
			navigator.mediaDevices.getUserMedia({ video: { width: { ideal: 1280 }, height: { ideal: 960 } }, audio: false }).then(function (s) {
				akis = s;
				var v = form.querySelector('[data-kamera-video]');
				v.srcObject = s;
				form.querySelector('[data-kamera]').hidden = false;
				form.querySelector('[data-kamera-cek]').hidden = false;
			}).catch(function () {
				window.alert('Kameraya erişilemedi. Tarayıcıya kamera izni verin veya dosya seçerek yükleyin.');
			});
		});
	});
	hepsi('[data-kamera-cek]').forEach(function (d) {
		d.addEventListener('click', function () {
			var form = d.closest('form');
			var v = form.querySelector('[data-kamera-video]');
			var c = form.querySelector('[data-kamera-tuval]');
			if (!v.videoWidth) { return; }
			// KURAL: Kamera görüntüsü vesikalık oranına (3:4) ortadan kırpılır.
			var h = v.videoHeight, w = Math.round(h * 3 / 4);
			if (w > v.videoWidth) { w = v.videoWidth; h = Math.round(w * 4 / 3); }
			c.width = w;
			c.height = h;
			c.getContext('2d').drawImage(v, (v.videoWidth - w) / 2, (v.videoHeight - h) / 2, w, h, 0, 0, w, h);
			c.toBlob(function (blob) { fotoGonder(form, blob, d); }, 'image/jpeg', 0.9);
		});
	});

	// ---- Makbuz otomatik yazdırma ----
	if (document.querySelector('[data-yazdir][data-otomatik]')) {
		window.addEventListener('load', function () { window.print(); });
	}
})();
