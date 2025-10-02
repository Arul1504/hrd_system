<?php
// Pastikan variabel $r sudah tersedia dari surat_tugas_view.php
// Contoh variabel yang digunakan:
// $r['no_surat'], $r['penempatan'], $r['alamat_penempatan'], 
// $r['tgl_pembuatan'], $r['nama_karyawan'], $r['jabatan'], 
// $r['sales_code']

// Format tanggal
function tgl_indo($tanggal) {
    return date('d F Y', strtotime($tanggal));
}
?>

<style>
    .allo-container {
        width: 100%;
        font-family: 'Arial', sans-serif;
        color: #000;
        font-size: 14px;
        line-height: 1.5;
    }

    .allo-header {
        text-align: center;
        font-weight: bold;
        margin-bottom: 10px;
    }

    .allo-info {
        margin-bottom: 15px;
    }

    .allo-table {
        width: 100%;
        border-collapse: collapse;
        margin: 10px 0;
    }

    .allo-table th,
    .allo-table td {
        border: 1px solid #000;
        padding: 6px;
        text-align: left;
    }

    .allo-ttd {
        margin-top: 30px;
        text-align: right;
    }

    .allo-ttd .nama {
        margin-top: 60px;
        font-weight: bold;
        border-bottom: 1px solid #000;
        display: inline-block;
        padding-bottom: 3px;
    }

    .allo-ttd .jabatan {
        font-size: 13px;
        margin-top: 3px;
    }

    .allo-ttd img {
        width: 150px;
        height: auto;
        margin-bottom: -10px;
        display: block;
        margin-left: auto;
        margin-right: 0;
    }
</style>

<div class="allo-container">
    <div class="allo-header">
        <div>PT MANDIRI ANDALAN UTAMA</div>
    </div>

    <div class="allo-info">
        <p><strong>Nomor:</strong> <?= htmlspecialchars($r['no_surat'] ?? '-') ?></p>
        <p><strong>Perihal:</strong> Surat Pengantar</p>
        <p><strong>Kepada Yth.</strong> Kepala Cabang <?= htmlspecialchars($r['penempatan'] ?? '-') ?></p>
        <p><?= nl2br(htmlspecialchars($r['alamat_penempatan'] ?? '-')) ?></p>
    </div>

    <p>Dengan hormat,</p>
    <p>Melalui surat ini, kami mengajukan pekerja dari PT Mandiri Andalan Utama untuk bergabung di cabang
        <?= htmlspecialchars($r['penempatan'] ?? '-') ?> sebagai berikut:</p>

    <table class="allo-table">
        <thead>
            <tr>
                <th>No</th>
                <th>Nama</th>
                <th>Posisi</th>
                <th>Sales Code</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>1</td>
                <td><?= htmlspecialchars($r['nama_karyawan'] ?? '-') ?></td>
                <td><?= htmlspecialchars($r['jabatan'] ?? '-') ?></td>
                <td><?= htmlspecialchars($r['sales_code'] ?? '-') ?></td>
            </tr>
        </tbody>
    </table>

    <p>Demikian surat ini kami sampaikan. Atas perhatian dan kerjasamanya kami ucapkan terima kasih.</p>

    <div class="allo-ttd">
        <p>Jakarta, <?= tgl_indo($r['tgl_pembuatan'] ?? date('Y-m-d')) ?></p>
        <p><strong>PT Mandiri Andalan Utama</strong></p>

        <?php if (file_exists('../image/ttd.png')): ?>
            <img src="../image/ttd.png" alt="Tanda Tangan">
        <?php endif; ?>

        <p class="nama">Kutobburizal</p>
        <p class="jabatan">HR & Support Manager</p>
    </div>
</div>
