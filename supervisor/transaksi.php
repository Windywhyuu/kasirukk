<?php
session_start();

if (!isset($_SESSION['id_user'])|| !in_array($_SESSION['level'], ['Supervisor'])) {
    header("Location: ../login.php");
    exit;
}

include "../koneksi.php";

/* ============================================
   PROSES TAMBAH MEMBER (AJAX / fetch)
============================================ */
if (isset($_POST['aksi']) &&$_POST['aksi'] === 'tambah_member') {
    header('Content-Type: application/json');

    $nama   = trim($_POST['nama_member'] ?? '');
    $no_hp  = trim($_POST['no_hp'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');

    if ($nama === '') {
        echo json_encode(["status" => "gagal", "pesan" => "Nama member wajib diisi."]);
        exit;
    }

    $nama_e   = mysqli_real_escape_string($koneksi,$nama);
    $no_hp_e  = mysqli_real_escape_string($koneksi, $no_hp);$alamat_e = mysqli_real_escape_string($koneksi,$alamat);

    $simpan = mysqli_query($koneksi, "
        INSERT INTO pelanggan (nama_pelanggan, alamat, no_hp)
        VALUES ('$nama_e', '$alamat_e', '$no_hp_e')
    ");

    if ($simpan) {
        echo json_encode([
            "status" => "sukses",
            "pesan"  => "Member berhasil ditambahkan.",
            "member" => [
                "id_pelanggan"   => mysqli_insert_id($koneksi),
                "nama_pelanggan" => $nama,
                "alamat"         => $alamat,
                "no_hp"          => $no_hp
            ]
        ]);
    } else {
        echo json_encode(["status" => "gagal", "pesan" => "Gagal menyimpan member ke database."]);
    }
    exit;
}

/* ============================================
   AMBIL DATA MEMBER
============================================ */
$query_member = mysqli_query($koneksi, "
    SELECT id_pelanggan, nama_pelanggan, alamat, no_hp
    FROM pelanggan
    ORDER BY nama_pelanggan ASC
");

$daftar_member = [];
while ($m = mysqli_fetch_assoc($query_member)) {
    $daftar_member[] =$m;
}

/* Format Tanggal Indonesia Formal */
function tanggal_indo($tanggal){
    $bulan = array (          1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',                  'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'          );$hari = array (
        'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa',
        'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'
    );
    $num_hari = date('l', strtotime($tanggal));
    $split = explode('-',$tanggal);
    return $hari[$num_hari] . ', ' . $split[2] . ' ' .$bulan[(int)$split[1]] . ' ' .$split[0];
}

$tgl_sekarang = tanggal_indo(date('Y-m-d'));
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kasir Penjualan — Gelora Wyne</title>

    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --bg-body: #F4F1EA;
            --surface-card: #FFFFFF;
            --brand-dark: #231F20;
            --brand-brown: #7A5C4A;
            --brand-brown-light: #F0EADF;
            --brand-accent: #C59B7D;
            --text-main: #2C2A29;
            --text-muted: #78716C;
            --border-soft: #E7E0D6;
        }

        body {
            background-color: var(--bg-body);
            color: var(--text-main);
            font-family: 'Plus Jakarta Sans', sans-serif;
            margin: 0;
            padding: 0;
            font-size: 13.5px;
        }

        .navbar-pos {
            background: var(--brand-dark);
            color: #FFFFFF;
            padding: 10px 20px;
            border-bottom: 2px solid var(--brand-accent);
        }

        .navbar-brand-title {
            font-size: 15px;
            font-weight: 700;
            letter-spacing: 0.3px;
            color: #F7F4F0;
        }

        .navbar-info {
            font-size: 12.5px;
            color: #D6CEC7;
        }

        .pos-wrapper {
            padding: 16px;
            max-width: 1440px;
            margin: auto;
        }

        .card-modern {
            background: var(--surface-card);
            border-radius: 12px;
            border: 1px solid var(--border-soft);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
            padding: 14px 16px;
        }

        .board-total {
            background: linear-gradient(135deg, #2B2623 0%, #1A1715 100%);
            border-radius: 12px;
            padding: 12px 18px;
            color: #FFF;
            box-shadow: inset 0 0 10px rgba(0,0,0,0.5);
            border: 1px solid #443C37;
        }

        .board-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2px;
        }

        .board-label {
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .board-val {
            font-family: 'Courier New', Courier, monospace;
            font-weight: 700;
            font-size: 24px;
        }

        .val-gold { color: #E5C378; }
        .val-cyan { color: #72D6EC; }
        .val-green { color: #72EC99; }

        .table-container {
            background: #FFFFFF;
            border: 1px solid var(--border-soft);
            border-radius: 10px;
            max-height: 330px;
            overflow-y: auto;
        }

        .table-pos {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .table-pos th {
            background: var(--brand-brown);
            color: #FFFFFF;
            font-weight: 600;
            font-size: 12.5px;
            padding: 10px 14px;
            position: sticky;
            top: 0;
            z-index: 5;
        }

        .table-pos td {
            padding: 9px 14px;
            border-bottom: 1px solid #F3EFEA;
            font-size: 13px;
        }

        .table-pos tbody tr:hover { background-color: var(--brand-brown-light); }

        .form-label-sm {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-main);
            margin-bottom: 4px;
        }

        .form-control-sm, .form-select-sm {
            font-size: 12.5px;
            padding: 6px 10px;
            border-radius: 6px;
            border: 1px solid #D6CEC5;
            background-color: #FAFAFA;
        }

        .form-control-sm:focus, .form-select-sm:focus {
            background-color: #FFFFFF;
            border-color: var(--brand-brown);
            box-shadow: 0 0 0 0.2rem rgba(122, 92, 74, 0.15);
        }

        .btn-modern {
            font-size: 12.5px;
            font-weight: 600;
            padding: 8px 14px;
            border-radius: 8px;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            border: none;
        }

        .btn-brown { background-color: var(--brand-brown); color: #FFFFFF; }
        .btn-brown:hover { background-color: #634839; color: #FFFFFF; }

        .btn-success-modern { background-color: #2D8A4E; color: #FFFFFF; }
        .btn-success-modern:hover { background-color: #236B3D; color: #FFFFFF; }

        .btn-light-modern {
            background-color: #FFFFFF;
            border: 1px solid #C8BFB5;
            color: var(--text-main);
        }
        .btn-light-modern:hover { background-color: #EFEBE4; }

        .badge-member {
            background: var(--brand-brown-light);
            color: var(--brand-brown);
            font-weight: 700;
            font-size: 11px;
            padding: 2px 8px;
            border-radius: 4px;
            border: 1px solid var(--brand-accent);
        }
    </style>
</head>
<body>

<div class="navbar-pos d-flex justify-content-between align-items-center">
    <div class="navbar-brand-title d-flex align-items-center gap-2">
        <i class="bi bi-bag-heart-fill text-warning"></i>
        <span>GELORA WYNE POS</span>
        <span class="badge bg-secondary text-light ms-2" style="font-size: 10px; font-weight: 500; letter-spacing: 0.5px;">ONLINE</span>
    </div>
    <div class="navbar-info d-flex align-items-center gap-3">
        <div>
            <i class="bi bi-calendar-event me-1 text-warning"></i> 
            <span><?= $tgl_sekarang; ?></span>
        </div>
        <div class="text-secondary">|</div>
        <div>
            <i class="bi bi-clock me-1 text-warning"></i> 
            <span id="liveClock" style="font-variant-numeric: tabular-nums;"><?= date('H:i:s'); ?></span> WIB
        </div>
        <div class="text-secondary">|</div>
        <div>
            <i class="bi bi-person-circle me-1 text-warning"></i> 
            <strong><?= htmlspecialchars($_SESSION['nama'] ?? 'Supervisor'); ?></strong> 
            <span class="text-muted" style="font-size: 11px;">(<?= htmlspecialchars($_SESSION['level'] ?? 'Supervisor'); ?>)</span>
        </div>
    </div>
</div>

<div class="pos-wrapper">
    
    <div class="row g-3 mb-3">
        <div class="col-lg-7">
            <div class="card-modern h-100">
                <div class="row g-2 align-items-center">
                    
                    <div class="col-md-5">
                        <label class="form-label-sm"><i class="bi bi-people me-1"></i> Tipe Pelanggan</label>
                        <div class="d-flex gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="jenisPelanggan" id="jUmum" value="Umum" checked onchange="pilihJenis('Umum')">
                                <label class="form-check-label fw-semibold" for="jUmum">Umum</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="jenisPelanggan" id="jMember" value="Member" onchange="pilihJenis('Member')">
                                <label class="form-check-label fw-semibold" for="jMember">Member</label>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-7" id="areaMember" style="display: none;">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label-sm m-0"><i class="bi bi-person-lines-fill me-1"></i> Data Member</label>
                            <button type="button" class="btn btn-sm btn-brown py-0 px-2" style="font-size: 11px;" onclick="bukaModalMember()">
                                <i class="bi bi-plus-lg"></i> Baru
                            </button>
                        </div>
                        <select id="idPelanggan" class="form-select form-select-sm" onchange="tampilkanPelanggan()">
                            <option value="">-- Pilih Member --</option>
                            <?php foreach ($daftar_member as$m) : ?>
                                <option value="<?= $m['id_pelanggan']; ?>" 
                                        data-nama="<?= htmlspecialchars($m['nama_pelanggan']); ?>"
                                        data-alamat="<?= htmlspecialchars($m['alamat']); ?>"
                                        data-hp="<?= htmlspecialchars($m['no_hp']); ?>">
                                    <?= htmlspecialchars($m['nama_pelanggan']); ?> (<?= htmlspecialchars($m['no_hp']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-7" id="areaUmum">
                        <label class="form-label-sm"><i class="bi bi-info-circle me-1"></i> Catatan</label>
                        <div class="text-muted" style="font-size: 12px;">Transaksi Non-Member / Pelanggan Umum.</div>
                    </div>

                </div>

                <div id="customerInfo" class="mt-2 p-2 rounded" style="background: var(--brand-brown-light); display:none; border: 1px solid var(--border-soft);">
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="board-total">
                <div class="board-row">
                    <span class="board-label text-warning">Total</span>
                    <span class="board-val val-gold" id="dispTotal">0</span>
                </div>
                <div class="board-row">
                    <span class="board-label text-info">Bayar</span>
                    <span class="board-val val-cyan" id="dispBayar">0</span>
                </div>
                <div class="board-row">
                    <span class="board-label text-success">Kembali</span>
                    <span class="board-val val-green" id="dispKembali">0</span>
                </div>
            </div>
        </div>
    </div>

    <!-- TABEL PENJUALAN -->
    <div class="table-container mb-3">
        <table class="table-pos">
            <thead>
                <tr>
                    <th style="width: 50px; text-align: center;">No</th>
                    <th>Nama Produk</th>
                    <th style="width: 110px; text-align: center;">Qty</th>
                    <th style="width: 140px; text-align: right;">Harga (Rp)</th>
                    <th style="width: 150px; text-align: right;">Subtotal (Rp)</th>
                </tr>
            </thead>
            <tbody id="daftarPesanan">
            </tbody>
        </table>
    </div>

    <!-- PANEL BOTTOM PEMBAYARAN -->
    <div class="card-modern">
        <div class="row g-3 align-items-center">
            
            <div class="col-md-3">
                <div class="p-2 rounded" style="background: var(--brand-brown-light);">
                    <div class="text-muted" style="font-size: 11px;">Total Barang</div>
                    <div class="fw-bold fs-6 text-dark" id="jumlahItem">0 Items</div>
                </div>
            </div>

            <div class="col-md-3">
                <label class="form-label-sm"><i class="bi bi-wallet-2 me-1"></i> Metode Pembayaran</label>
                <div class="d-flex gap-3 mt-1">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="metode" id="mTunai" value="Tunai" checked onchange="ubahMetode()">
                        <label class="form-check-label fw-bold" for="mTunai">Tunai</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="metode" id="mQris" value="QRIS" onchange="ubahMetode()">
                        <label class="form-check-label fw-bold" for="mQris">QRIS</label>
                    </div>
                </div>
            </div>

            <div class="col-md-3" id="bagianTunai">
                <label class="form-label-sm"><i class="bi bi-cash me-1"></i> Uang Bayar (Rp)</label>
                <input type="number" id="uangBayar" class="form-control form-control-sm fw-bold fs-6" placeholder="0" oninput="hitungKembalian()">
            </div>

            <div class="col-md-3 d-flex gap-2 justify-content-end">
                <button type="button" class="btn-modern btn-light-modern w-50" onclick="kembaliKeKasir()">
                    <i class="bi bi-arrow-left"></i> Batal
                </button>
                <button type="button" class="btn-modern btn-success-modern w-50" onclick="prosesPembayaran()">
                    <i class="bi bi-check2-circle"></i> Simpan
                </button>
            </div>

        </div>
    </div>

</div>

<!-- MODAL MEMBER -->
<div class="modal fade" id="modalMember" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content" style="border-radius: 12px; overflow: hidden;">
            <div class="modal-header py-2 px-3" style="background: var(--brand-brown); color:#FFF;">
                <h6 class="modal-title m-0 fw-bold"><i class="bi bi-person-plus me-1"></i> Member Baru</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3">
                <div class="mb-2">
                    <label class="form-label-sm">Nama Lengkap</label>
                    <input type="text" id="namaMemberBaru" class="form-control form-control-sm">
                </div>
                <div class="mb-2">
                    <label class="form-label-sm">No. HP</label>
                    <input type="text" id="hpMemberBaru" class="form-control form-control-sm">
                </div>
                <div class="mb-2">
                    <label class="form-label-sm">Alamat</label>
                    <textarea id="alamatMemberBaru" class="form-control form-control-sm" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer py-2 px-3">
                <button type="button" class="btn btn-sm btn-light-modern" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-sm btn-brown" id="btnSimpanMember" onclick="simpanMemberBaru()">Simpan</button>
            </div>
        </div>
    </div>
</div>

<script src="../assets/js/bootstrap.bundle.min.js"></script>
<script>
let keranjang = [];
let totalHarga = 0;
let totalItem = 0;
let jenisPelanggan = "Umum";

document.addEventListener("DOMContentLoaded", function () {
    // Jalankan live clock
    setInterval(updateLiveClock, 1000);

    const data = sessionStorage.getItem("keranjangGeloraWyne");
    if (!data) {
        tampilkanKosong();
        return;
    }

    try {
        keranjang = JSON.parse(data);
    } catch (error) {
        tampilkanKosong();
        return;
    }

    if (!Array.isArray(keranjang) || keranjang.length === 0) {
        tampilkanKosong();
        return;
    }

    tampilkanPesanan();
});

// Fungsi Jam Digital Real-Time
function updateLiveClock() {
    const now = new Date();
    let hours = String(now.getHours()).padStart(2, '0');
    let minutes = String(now.getMinutes()).padStart(2, '0');
    let seconds = String(now.getSeconds()).padStart(2, '0');
    
    const clockElement = document.getElementById('liveClock');
    if (clockElement) {
        clockElement.textContent = `${hours}:${minutes}:${seconds}`;
    }
}

function tampilkanPesanan() {
    const container = document.getElementById("daftarPesanan");
    container.innerHTML = "";
    totalHarga = 0;
    totalItem = 0;

    keranjang.forEach(function (item, index) {
        const jumlah = Number(item.jumlah) || 0;
        const harga = Number(item.harga) || 0;
        const subtotal = jumlah * harga;

        totalHarga += subtotal;
        totalItem += jumlah;

        const tr = document.createElement("tr");
        tr.innerHTML = `
            <td style="text-align: center;">${index + 1}</td>
            <td><strong>${escapeHTML(item.nama)}</strong></td>
            <td style="text-align: center;"><span class="badge bg-light text-dark border px-2 py-1">${jumlah} Pcs</span></td>
            <td style="text-align: right;">${harga.toLocaleString("id-ID")}</td>
            <td style="text-align: right; font-weight: 700;">${subtotal.toLocaleString("id-ID")}</td>
        `;
        container.appendChild(tr);
    });

    document.getElementById("dispTotal").innerText = totalHarga.toLocaleString("id-ID");
    document.getElementById("jumlahItem").innerText = totalItem + " Items";

    hitungKembalian();
}

function tampilkanKosong() {
    document.getElementById("daftarPesanan").innerHTML = `
        <tr>
            <td colspan="5" class="text-center py-5 text-muted">
                <i class="bi bi-cart-x fs-2 opacity-50 d-block mb-1"></i>
                Keranjang masih kosong. Silakan pilih produk terlebih dahulu.
            </td>
        </tr>
    `;
}

function pilihJenis(jenis) {
    jenisPelanggan = jenis;
    document.getElementById("areaUmum").style.display = (jenis === "Umum") ? "block" : "none";
    document.getElementById("areaMember").style.display = (jenis === "Member") ? "block" : "none";
    if(jenis === "Umum") {
        document.getElementById("customerInfo").style.display = "none";
    }
}

function tampilkanPelanggan() {
    const select = document.getElementById("idPelanggan");
    const option = select.options[select.selectedIndex];
    const info = document.getElementById("customerInfo");

    if (!select.value) {
        info.style.display = "none";
        return;
    }

    info.innerHTML = `
        <div class="d-flex align-items-center gap-2">
            <strong><i class="bi bi-person-badge me-1"></i> ${escapeHTML(option.dataset.nama)}</strong>
            <span class="badge-member">MEMBER</span>
        </div>
        <div class="text-muted mt-1" style="font-size:11px;">
            <i class="bi bi-telephone me-1"></i> ${escapeHTML(option.dataset.hp || '-')} &nbsp;|&nbsp; 
            <i class="bi bi-geo-alt me-1"></i> ${escapeHTML(option.dataset.alamat || '-')}
        </div>
    `;
    info.style.display = "block";
}

function bukaModalMember() {
    const modal = new bootstrap.Modal(document.getElementById("modalMember"));
    modal.show();
}

function simpanMemberBaru() {
    const nama = document.getElementById("namaMemberBaru").value.trim();
    const hp = document.getElementById("hpMemberBaru").value.trim();
    const alamat = document.getElementById("alamatMemberBaru").value.trim();

    if (nama === "") {
        alert("Nama member wajib diisi.");
        return;
    }

    const btn = document.getElementById("btnSimpanMember");
    btn.disabled = true;

    const body = new URLSearchParams();
    body.append("aksi", "tambah_member");
    body.append("nama_member", nama);
    body.append("no_hp", hp);
    body.append("alamat", alamat);

    fetch("transaksi_2.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: body.toString()
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === "sukses") {
            tambahOpsiMember(data.member);
            const modalEl = document.getElementById("modalMember");
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();

            document.getElementById("namaMemberBaru").value = "";
            document.getElementById("hpMemberBaru").value = "";
            document.getElementById("alamatMemberBaru").value = "";
        } else {
            alert(data.pesan || "Gagal menyimpan member.");
        }
    })
    .catch(() => alert("Terjadi kesalahan koneksi ke server."))
    .finally(() => { btn.disabled = false; });
}

function tambahOpsiMember(m) {
    const select = document.getElementById("idPelanggan");
    const opt = document.createElement("option");
    opt.value = m.id_pelanggan;
    opt.dataset.nama = m.nama_pelanggan;
    opt.dataset.alamat = m.alamat || "";
    opt.dataset.hp = m.no_hp || "";
    opt.textContent = m.nama_pelanggan + " (" + (m.no_hp || '-') + ")";
    select.appendChild(opt);

    pilihJenis("Member");
    document.getElementById("jMember").checked = true;
    select.value = m.id_pelanggan;
    tampilkanPelanggan();
}

function ubahMetode() {
    const metode = document.querySelector('input[name="metode"]:checked').value;
    const bagian = document.getElementById("bagianTunai");
    const input = document.getElementById("uangBayar");

    if (metode === "QRIS") {
        bagian.style.display = "none";
        input.value = totalHarga;
    } else {
        bagian.style.display = "block";
        input.value = "";
    }
    hitungKembalian();
}

function hitungKembalian() {
    const metode = document.querySelector('input[name="metode"]:checked').value;
    let bayar = Number(document.getElementById("uangBayar").value) || 0;

    if (metode === "QRIS") {
        bayar = totalHarga;
    }

    const kembalian = bayar - totalHarga;

    document.getElementById("dispBayar").innerText = bayar.toLocaleString("id-ID");
    document.getElementById("dispKembali").innerText = (kembalian > 0 ? kembalian : 0).toLocaleString("id-ID");
}

function prosesPembayaran() {
    if (keranjang.length === 0) {
        alert("Keranjang masih kosong.");
        return;
    }

    let idPelanggan = 0;
    let jenisKirim = "Umum";

    if (jenisPelanggan === "Member") {
        idPelanggan = document.getElementById("idPelanggan").value;
        if (!idPelanggan) {
            alert("Silakan pilih data member terlebih dahulu!");
            return;
        }
        jenisKirim = "Member";
    }

    const metode = document.querySelector('input[name="metode"]:checked').value;
    let bayar = 0;

    if (metode === "Tunai") {
        bayar = Number(document.getElementById("uangBayar").value) || 0;
        if (bayar <= 0) {
            alert("Masukkan uang pembayaran.");
            return;
        }
        if (bayar < totalHarga) {
            alert("Uang pembayaran kurang.");
            return;
        }
    } else {
        bayar = totalHarga;
    }

    const kembalian = bayar - totalHarga;

    const form = document.createElement("form");
    form.method = "POST";
    form.action = "simpan_transaksi.php";

    function addField(nama, nilai) {
        const input = document.createElement("input");
        input.type = "hidden";
        input.name = nama;
        input.value = nilai;
        form.appendChild(input);
    }

    addField("id_pelanggan", idPelanggan);
    addField("jenis_pelanggan", jenisKirim);
    addField("metode", metode);
    addField("total", totalHarga);
    addField("bayar", bayar);
    addField("kembalian", kembalian);

    keranjang.forEach(function (item) {
        addField("id_produk[]", item.id);
        addField("jumlah[]", item.jumlah);
        addField("harga[]", item.harga);
    });

    document.body.appendChild(form);
    form.submit();
}

function kembaliKeKasir() {
    window.location.href = "index.php";
}

function escapeHTML(text) {
    const div = document.createElement("div");
    div.textContent = text ?? "";
    return div.innerHTML;
}
</script>
</body>
</html>