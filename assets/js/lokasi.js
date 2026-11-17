/**
 * Lokasi.js — helper front-end untuk fitur "Teller Terdekat".
 *
 *  Lokasi.pencarian()  : kotak pencarian alamat ala ojek online (autocomplete)
 *  Lokasi.geoPosisi()  : ambil lokasi GPS perangkat
 *  Lokasi.peta()       : peta Leaflet (OpenStreetMap) + marker
 *  Lokasi.jarakKm()    : hitung jarak di sisi klien
 *  Lokasi.arahKe()     : tautan navigasi Google Maps
 */
(function (global) {
    'use strict';

    var Lokasi = {};

    Lokasi.baseUrl = (global.RECYCASH_BASE_URL || '').replace(/\/+$/, '');

    Lokasi.url = function (path) {
        return Lokasi.baseUrl + path;
    };

    Lokasi.escapeHtml = function (text) {
        return String(text == null ? '' : text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    };

    /* ------------------------------ GEOLOKASI ------------------------------ */

    Lokasi.geoPosisi = function () {
        return new Promise(function (resolve, reject) {
            if (!navigator.geolocation) {
                reject(new Error('Geolokasi tidak didukung oleh browser ini.'));
                return;
            }

            navigator.geolocation.getCurrentPosition(
                function (pos) {
                    resolve({
                        lat: pos.coords.latitude,
                        lng: pos.coords.longitude,
                        akurasi: Math.round(pos.coords.accuracy || 0)
                    });
                },
                function (err) {
                    var pesan = err && err.code === 1
                        ? 'Izin lokasi ditolak. Aktifkan izin lokasi pada browser Anda.'
                        : 'Gagal mendapatkan lokasi GPS saat ini.';
                    reject(new Error(pesan));
                },
                { enableHighAccuracy: true, timeout: 12000, maximumAge: 60000 }
            );
        });
    };

    /* -------------------------------- JARAK -------------------------------- */

    Lokasi.jarakKm = function (lat1, lng1, lat2, lng2) {
        var earth = 6371;
        var dLat = (lat2 - lat1) * Math.PI / 180;
        var dLng = (lng2 - lng1) * Math.PI / 180;
        var a = Math.sin(dLat / 2) * Math.sin(dLat / 2)
            + Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180)
            * Math.sin(dLng / 2) * Math.sin(dLng / 2);

        return earth * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    };

    Lokasi.fmtJarak = function (km) {
        if (km < 0.999) {
            return Math.round(km * 1000).toLocaleString('id-ID') + ' m';
        }
        return km.toFixed(1).replace('.', ',') + ' km';
    };

    Lokasi.arahKe = function (lat, lng) {
        return 'https://www.google.com/maps/dir/?api=1&destination='
            + encodeURIComponent(lat + ',' + lng) + '&travelmode=driving';
    };

    /* ------------------------------ AUTOCOMPLETE ------------------------------ */

    /**
     * Pasang kotak pencarian ala ojek online.
     * opts: { input, container, onPilih(place), onGagal(err), placeholder }
     */
    Lokasi.pencarian = function (opts) {
        var input = opts.input;
        var container = opts.container;
        var onPilih = opts.onPilih || function () {};
        var onGagal = opts.onGagal || function () {};
        var minChars = opts.minChars || 3;

        if (!input || !container) {
            return { hapus: function () {} };
        }

        var timer = null;
        var daftar = [];
        var aktif = -1;

        container.innerHTML = '';
        container.className = container.className.replace(/\s*lokasi-aktif/g, '');

        function tutup() {
            container.classList.remove('lokasi-aktif');
            container.innerHTML = '';
            daftar = [];
            aktif = -1;
        }

        function gambar() {
            if (!daftar.length) {
                tutup();
                return;
            }

            var html = '';

            daftar.forEach(function (item, i) {
                html += '<button type="button" class="lokasi-item' + (i === aktif ? ' aktif' : '') + '" data-index="' + i + '">'
                    + '<i class="bi bi-geo-alt-fill"></i>'
                    + '<span class="lokasi-item-teks">'
                    + '<strong>' + Lokasi.escapeHtml(item.label) + '</strong>'
                    + (item.detail ? '<small>' + Lokasi.escapeHtml(item.detail) + '</small>' : '')
                    + '</span>'
                    + '</button>';
            });

            container.innerHTML = html;
            container.classList.add('lokasi-aktif');
        }

        function pilih(index) {
            var item = daftar[index];
            if (!item) {
                return;
            }

            tutup();

            // Provider Google mengirim prediksi tanpa koordinat -> ambil detail dulu
            if (item.lat === null || item.lat === undefined) {
                fetch(Lokasi.url('/api/lokasi_detail.php?id=' + encodeURIComponent(item.id)), {
                    credentials: 'same-origin'
                })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        if (res && res.ok) {
                            onPilih(res);
                        } else {
                            onGagal(new Error((res && res.message) || 'Lokasi tidak ditemukan.'));
                        }
                    })
                    .catch(function () {
                        onGagal(new Error('Gagal memuat detail lokasi.'));
                    });

                return;
            }

            onPilih(item);
        }

        input.addEventListener('input', function () {
            var q = input.value.trim();

            if (timer) {
                clearTimeout(timer);
            }

            if (q.length < minChars) {
                tutup();
                return;
            }

            timer = setTimeout(function () {
                fetch(Lokasi.url('/api/lokasi_cari.php?q=' + encodeURIComponent(q)), {
                    credentials: 'same-origin'
                })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        if (!res || !res.ok) {
                            onGagal(new Error((res && res.message) || 'Pencarian lokasi gagal.'));
                            return;
                        }

                        daftar = res.results || [];
                        aktif = daftar.length ? 0 : -1;
                        gambar();
                    })
                    .catch(function () {
                        onGagal(new Error('Tidak dapat terhubung ke server lokasi.'));
                    });
            }, 450);
        });

        input.addEventListener('keydown', function (e) {
            if (!daftar.length) {
                return;
            }

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                aktif = (aktif + 1) % daftar.length;
                gambar();
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                aktif = (aktif - 1 + daftar.length) % daftar.length;
                gambar();
            } else if (e.key === 'Enter') {
                e.preventDefault();
                pilih(aktif < 0 ? 0 : aktif);
            } else if (e.key === 'Escape') {
                tutup();
            }
        });

        container.addEventListener('click', function (e) {
            var btn = e.target.closest('.lokasi-item');
            if (!btn) {
                return;
            }
            pilih(parseInt(btn.getAttribute('data-index'), 10) || 0);
        });

        document.addEventListener('click', function (e) {
            if (!container.contains(e.target) && e.target !== input) {
                tutup();
            }
        });

        return { tutup: tutup };
    };

    /* --------------------------------- PETA --------------------------------- */

    /**
     * Buat peta Leaflet.
     * opts: { el, lat, lng, zoom, titik: [{lat,lng,title,ikon}], onPeta }
     */
    Lokasi.peta = function (opts) {
        var el = opts.el;

        if (!el) {
            return null;
        }

        if (!global.L) {
            el.innerHTML = '<div class="peta-gagal"><i class="bi bi-map"></i>'
                + '<span>Peta belum termuat (perlu koneksi internet).</span></div>';
            return null;
        }

        var lat = opts.lat != null ? opts.lat : -6.9;
        var lng = opts.lng != null ? opts.lng : 107.6;
        var peta = global.L.map(el, { zoomControl: true, attributionControl: true })
            .setView([lat, lng], opts.zoom || 13);

        global.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap'
        }).addTo(peta);

        (opts.titik || []).forEach(function (t) {
            if (t.lat == null || t.lng == null) {
                return;
            }

            global.L.marker([t.lat, t.lng], {
                icon: global.L.divIcon({
                    className: 'peta-pin',
                    html: '<span class="peta-pin-ikon ' + (t.ikon || 'pin-nasabah') + '">'
                        + '<i class="bi ' + (t.ikonIkon || 'bi-geo-alt-fill') + '"></i></span>',
                    iconSize: [34, 34],
                    iconAnchor: [17, 34]
                })
            }).addTo(peta).bindPopup(t.title || '');

            if (t.zoomKe) {
                peta.setView([t.lat, t.lng], t.zoomKe);
            }
        });

        if (typeof opts.onPeta === 'function') {
            opts.onPeta(peta);
        }

        return peta;
    };

    global.Lokasi = Lokasi;
})(window);
