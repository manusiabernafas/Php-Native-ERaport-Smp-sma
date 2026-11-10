// Konfirmasi aksi penting (hapus, kunci, terbitkan)
document.querySelectorAll('form[data-yakin]').forEach(function (f) {
    f.addEventListener('submit', function (e) { if (!confirm(f.dataset.yakin)) e.preventDefault(); });
});
// Kirim otomatis saat filter diubah
document.querySelectorAll('form[data-auto] select').forEach(function (el) {
    el.addEventListener('change', function () { el.form.submit(); });
});

// ---- Lembar input nilai: hitung nilai akhir langsung, tandai di bawah KKTP, navigasi panah
var lembar = document.querySelector('table.lembar-nilai');
if (lembar) {
    var bobot = +lembar.dataset.bobot, kktp = +lembar.dataset.kktp;
    var ubah = false;
    function hitung(tr) {
        var tp = [].slice.call(tr.querySelectorAll('input.n-tp')).map(function (i) { return i.value.trim(); });
        var sas = tr.querySelector('input.n-sas').value.trim();
        var isi = tp.filter(function (v) { return v !== ''; }).map(Number);
        var na = tr.querySelector('.n-akhir');
        if (isi.length && sas !== '') {
            var rata = isi.reduce(function (a, b) { return a + b; }, 0) / isi.length;
            var v = Math.round((bobot * rata + (100 - bobot) * +sas) / 100);
            na.textContent = v;
            na.classList.toggle('rendah', v < kktp);
        } else { na.textContent = '–'; na.classList.remove('rendah'); }
    }
    function tandai(i) {
        var v = i.value.trim();
        var salah = v !== '' && (!/^\d{1,3}$/.test(v) || +v > 100);
        i.classList.toggle('salah', salah);
        i.classList.toggle('rendah', !salah && v !== '' && +v < kktp);
    }
    lembar.querySelectorAll('tbody tr').forEach(function (tr) {
        tr.querySelectorAll('input.n').forEach(function (i) {
            tandai(i);
            i.addEventListener('input', function () { tandai(i); hitung(tr); ubah = true; });
        });
        hitung(tr);
    });
    // Panah atas/bawah/Enter pindah baris seperti spreadsheet
    lembar.addEventListener('keydown', function (e) {
        var i = e.target;
        if (!i.classList || !i.classList.contains('n')) return;
        var td = i.closest('td'), tr = td.parentElement, kol = [].indexOf.call(tr.children, td), tuju = null;
        if (e.key === 'ArrowDown' || e.key === 'Enter') tuju = tr.nextElementSibling;
        else if (e.key === 'ArrowUp') tuju = tr.previousElementSibling;
        if (tuju) { e.preventDefault(); var x = tuju.children[kol].querySelector('input'); if (x) { x.focus(); x.select(); } }
    });
    window.addEventListener('beforeunload', function (e) { if (ubah) { e.preventDefault(); e.returnValue = ''; } });
    var f = lembar.closest('form');
    if (f) f.addEventListener('submit', function () { ubah = false; });
}

// Isi satu kolom sekaligus (mis. semua hadir / predikat sama)
document.querySelectorAll('[data-isi-kolom]').forEach(function (b) {
    b.addEventListener('click', function () {
        var sel = b.dataset.isiKolom, nilai = b.dataset.nilai;
        document.querySelectorAll(sel).forEach(function (el) { if (el.value === '' || b.dataset.timpa) el.value = nilai; });
    });
});
