<?php
// ============================
// edit_employee.php (FINAL)
// ============================

// Koneksi database
$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "db_hrd2";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { die("Koneksi gagal: " . $conn->connect_error); }

// Ambil id dari URL (dukung ?id=... atau ?id_karyawan=...)
$id_karyawan = isset($_GET['id']) ? intval($_GET['id']) : (isset($_GET['id_karyawan']) ? intval($_GET['id_karyawan']) : 0);
if ($id_karyawan <= 0) { echo "Parameter ID tidak valid."; exit; }

// Ambil data karyawan
$stmt = $conn->prepare("SELECT * FROM karyawan WHERE id_karyawan = ?");
$stmt->bind_param("i", $id_karyawan);
$stmt->execute();
$res = $stmt->get_result();
$employee = $res->fetch_assoc();
$stmt->close();
$conn->close();

if (!$employee) { echo "Data karyawan tidak ditemukan."; exit; }

// Helper escape
function e($v){ return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }

// Notifikasi dari URL
$flash = '';
if (isset($_GET['status'])) {
    $map = [
        'updated'       => ['cls'=>'success', 'msg'=>'Data karyawan berhasil diperbarui!'],
        'nik_duplicate' => ['cls'=>'error',   'msg'=>'NIK sudah digunakan. Tidak bisa disimpan lagi.'],
        'nochange'      => ['cls'=>'warning', 'msg'=>'Tidak ada perubahan yang disimpan.'],
        'error'         => ['cls'=>'error',   'msg'=>'Terjadi kesalahan saat menyimpan perubahan.'],
    ];
    if (isset($map[$_GET['status']])) {
        $cls = $map[$_GET['status']]['cls'];
        $msg = $map[$_GET['status']]['msg'];
        $flash = '<div class="alert '.$cls.'">'.$msg.'</div>';
    }
}

// Kirim data employee ke JS
$employee_json = json_encode($employee, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Edit Data Karyawan - <?= e($employee['nama_karyawan'] ?? '') ?></title>
<link rel="stylesheet" href="../style.css" />
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
<style>
/* Alert warna */
.alert{padding:12px 16px;border-radius:6px;margin:12px 0;font-weight:600}
.alert.success{background:#2ecc71;color:#fff}
.alert.error{background:#e74c3c;color:#fff}
.alert.warning{background:#f39c12;color:#fff}

/* Kontainer form */
.form-container{background:#fff;border-radius:12px;padding:20px;box-shadow:0 4px 12px rgba(0,0,0,.05)}
.form-section{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.form-group label{font-weight:600;margin-bottom:6px;display:block}
.form-group input[type=text],
.form-group input[type=date],
.form-group input[type=email],
.form-group select,
.form-group textarea{padding:10px;border:1px solid #ddd;border-radius:8px;background:#f9f9f9;font:inherit;width:100%}
.form-group textarea{min-height:90px;resize:vertical}
.readonly{background:#eef2f7;cursor:not-allowed}
.submit-btn{background:#28a745;color:#fff;padding:12px 16px;border:none;border-radius:8px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:8px}
.submit-btn:hover{background:#218838}
.ghost{background:transparent;border:1px solid #e5e7eb;padding:12px 16px;border-radius:8px;cursor:pointer}
.muted{color:#777;font-size:.9rem}
@media(max-width:768px){.form-section{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="container">
    <aside class="sidebar">
        <!-- (opsional) sidebar kamu -->
    </aside>

    <main class="main-content">
        <header class="main-header">
            <h1>Ubah Data Karyawan</h1>
            <p class="current-date"><?= date('l, d F Y'); ?></p>
        </header>

        <?= $flash ?>

        <div class="form-container">
            <form action="process_edit_employee.php" method="POST" id="editForm" autocomplete="off" enctype="multipart/form-data">
                <input type="hidden" name="id_karyawan" value="<?= e($employee['id_karyawan']) ?>">
                <input type="hidden" name="__labels" id="__labels_map">

                <!-- Header proyek -->
                <div class="form-section" style="grid-template-columns:1fr 1fr">
                    <div class="form-group">
                        <label>Proyek</label>
                        <input type="text" id="proyek_text" class="readonly" readonly>
                        <input type="hidden" name="proyek" id="proyek_hidden">
                    </div>
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <small class="muted">Field di bawah akan menyesuaikan proyek.</small>
                    </div>
                </div>

                <!-- Field dinamis + field wajib -->
                <div id="dynamic_box" class="form-section"></div>

                <!-- Foto (opsional) -->
                <h3 style="margin-top:20px;">Foto</h3>
                <div class="form-section">
                    <div class="form-group">
                        <label for="foto">Ganti Foto (opsional)</label>
                        <input type="file" id="foto" name="foto" accept="image/*">
                    </div>
                    <?php if (!empty($employee['foto_path'])): ?>
                    <div class="form-group">
                        <label>Foto Saat Ini</label>
                        <img src="<?= e($employee['foto_path']) ?>" alt="Foto Karyawan" style="max-width:160px;border:1px solid #eee;border-radius:8px" />
                        <input type="hidden" name="foto_path_lama" value="<?= e($employee['foto_path']) ?>">
                    </div>
                    <?php endif; ?>
                </div>

                <div class="form-buttons" style="margin-top:16px">
                    <button type="submit" class="submit-btn"><i class="fas fa-save"></i> Simpan Perubahan</button>
                    <a href="all_employees.php" class="ghost" style="margin-left:10px;">Batal</a>
                </div>
            </form>
        </div>
    </main>
</div>

<script>
// ===== Data dari PHP =====
const EMP = <?= $employee_json ?: '{}' ?>;

// ===== Utility =====
function esc(v){return (v??'').toString().replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')}
function ucLabel(raw){
  const t=(raw||'').toString().replace(/_/g,' ').toLowerCase().replace(/\b\w/g,c=>c.toUpperCase());
  return t.replace(/\bNik\b/g,'NIK').replace(/\bBpjs\b/g,'BPJS').replace(/\bBpjamsostek\b/g,'BPJamsostek')
          .replace(/\bUmk\b/g,'UMK').replace(/\bUmp\b/g,'UMP').replace(/\bPks\b/g,'PKS')
          .replace(/\bId\b/g,'ID').replace(/\bNpwp\b/g,'NPWP').replace(/\bNip\b/g,'NIP');
}
function slugify(label){return label.toLowerCase().replace(/[^a-z0-9]+/g,'_').replace(/^_+|_+$/g,'').replace(/_{2,}/g,'_')}
function toDateInput(v){
  if(!v) return '';
  if(/^\d{4}-\d{2}-\d{2}$/.test(v)) return v;
  const d=new Date(v); if(isNaN(d)) return '';
  const iso=new Date(d.getTime()-d.getTimezoneOffset()*60000).toISOString();
  return iso.slice(0,10);
}

// ===== Input generator + validasi =====
function inputFor(label){
  const name = slugify(label);
  const slug = name;
  const plain = label.toLowerCase().replace(/[^a-z0-9]/g,'');

  if (slug.includes("email")) {
    return `<label>${ucLabel(label)}<input type="email" name="${name}" placeholder="${ucLabel(label)}"></label>`;
  }
  if (slug === "role") {
    return `<label>${ucLabel(label)}<select name="${name}"><option value="">— Pilih —</option><option value="KARYAWAN">KARYAWAN</option><option value="HRD">HRD</option></select></label>`;
  }
  if ( slug.includes("tanggal") || slug.includes("date") || slug.includes("tgl") ||
       slug.includes("end_of_contract") || slug.includes("pks") ) {
    return `<label>${ucLabel(label)}<input type="date" name="${name}" placeholder="${ucLabel(label)}"></label>`;
  }
  if ( slug === "jenis_kelamin" || plain.includes("gender") ) {
    return `<label>${ucLabel(label)}<select name="${name}"><option value="">— Pilih —</option><option value="Laki-laki">Laki-laki</option><option value="Perempuan">Perempuan</option></select></label>`;
  }
  if ( slug === "status_karyawan") {
    return `<label>${ucLabel(label)}<select name="${name}"><option value="">— Pilih —</option><option value="MITRA">MITRA</option><option value="PKWT">PKWT</option></select></label>`;
  }
  if ( slug === "status" || slug.includes("status_aktif") ) {
    return `<label>${ucLabel(label)}<select name="${name}"><option value="">— Pilih —</option><option value="AKTIF">AKTIF</option><option value="TIDAK AKTIF">TIDAK AKTIF</option></select></label>`;
  }
  if (slug === "rt_rw") {
    return `<label>${ucLabel(label)}<input type="text" name="${name}" placeholder="000/000" inputmode="numeric" pattern="\\d{3}/\\d{3}"><small class="muted">Format 3 angka, “/”, 3 angka. Mis: 005/012</small></label>`;
  }
  const isNumeric =
    slug.startsWith("no_") || slug.includes("nomor") || slug.includes("nik") ||
    slug.includes("kk") || slug.includes("npwp") || slug.includes("bpjs") || slug.includes("bpjamsostek") ||
    slug.includes("rekening") || slug.includes("nip") || slug.includes("sales_code") || slug.includes("reff");
  if (isNumeric){
    if (slug.includes('nik')) {
      return `<label>${ucLabel(label)}<input type="text" name="${name}" placeholder="${ucLabel(label)}" inputmode="numeric" pattern="\\d{16}" minlength="16" maxlength="16"><small class="muted">Masukkan tepat 16 digit angka.</small></label>`;
    }
    return `<label>${ucLabel(label)}<input type="text" inputmode="numeric" pattern="\\d*" name="${name}" placeholder="${ucLabel(label)}"></label>`;
  }
  if ( slug.includes("umk") || slug.includes("ump") || slug.includes("gapok") ){
    return `<label>${ucLabel(label)}<input type="number" name="${name}" min="0" step="1" placeholder="${ucLabel(label)}"></label>`;
  }
  if ( slug.includes("alamat") ){
    return `<label>${ucLabel(label)}<textarea rows="2" name="${name}" placeholder="${ucLabel(label)}"></textarea></label>`;
  }
  return `<label>${ucLabel(label)}<input type="text" name="${name}" placeholder="${ucLabel(label)}"></label>`;
}

// ====== PROJECT_FIELDS (sesuai requirement) ======
const PROJECT_FIELDS = {
  "CIMB": [
    "nama_karyawan","cabang","nomor_kontrak","tanggal_pembuatan_pks","tempat_lahir","tanggal_lahir","alamat","rt_rw","kelurahan","kecamatan","kota_kabupaten","nik_ktp","pendidikan_terakhir","no_hp","jabatan","alamat_email",
    "nama_sm","nama_sh","job","channel","tgl_rmu",
    "jenis_kelamin","no_kk","nama_ayah","nama_ibu","team_leader","recruiter","sales_code","join_date","status_karyawan","nomor_rekening","nama_bank","status","tgl_resign"
  ],
  "NOBU": [
    "nama_karyawan","cabang","nomor_kontrak","tanggal_pembuatan_pks","tempat_lahir","tanggal_lahir","alamat","rt_rw","kelurahan","kecamatan","kota_kabupaten","nik_ktp","pendidikan_terakhir","no_hp","jabatan","tgl_aktif_masuk","alamat_email","jenis_kelamin","no_kk","nama_ayah","nama_ibu","team_leader","recruiter","sales_code","join_date","status_karyawan","nomor_rekening","nama_bank","status","tgl_resign"
  ],
  "MOLADIN": [
    "nama_karyawan","jabatan","cabang","nomor_kontrak","tanggal_pembuatan_pks","tempat_lahir","tanggal_lahir","alamat","rt_rw","kelurahan","kecamatan","kota_kabupaten","nik_ktp","pendidikan_terakhir","no_hp","alamat_email","jenis_kelamin","no_kk","nama_ayah","nama_ibu","team_leader","recruiter","sales_code","join_date","status_karyawan","nomor_rekening","nama_bank","status","tgl_resign"
  ],
  "ALLO": [
    "nama_karyawan","jabatan","penempatan","kota","area","nomor_kontrak","tanggal_pembuatan_pks","tempat_lahir","tanggal_lahir","alamat","rt_rw","kelurahan","kecamatan","kota_kabupaten","nik_ktp","pendidikan_terakhir","no_hp","alamat_email","jenis_kelamin","no_kk","nama_ayah","nama_ibu","recruitment_officer","team_leader","join_date","status_karyawan","nomor_rekening","nama_bank","status","tgl_resign"
  ],
  "CNAF": [
    "nama_karyawan","cabang","nomor_kontrak","tanggal_pembuatan_pks","tempat_lahir","tanggal_lahir","alamat","rt_rw","kelurahan","kecamatan","kota_kabupaten","nik_ktp","pendidikan_terakhir","no_hp","jabatan","join_date","end_date","end_date_pks","umk_ump","jenis_kelamin","alamat_email","alamat_tinggal","npwp","status_pajak","no_kk","nama_ayah","nama_ibu","recruiter","team_leader","nik_karyawan","status","nomor_rekening","nama_bank","no_bpjamsostek","no_bpjs_kes","end_of_contract"
  ],
  "BNIF": [
    "nama_karyawan","cabang","nomor_kontrak","tanggal_pembuatan_pks","tempat_lahir","tanggal_lahir","alamat","rt_rw","kelurahan","kecamatan","kota_kabupaten","nik_ktp","pendidikan_terakhir","no_hp","jabatan","join_date","end_date","end_date_pks","umk_ump","jenis_kelamin","alamat_email","alamat_tinggal","nip","npwp","status_pajak","no_kk","nama_ayah","nama_ibu","recruiter","team_leader","nik_karyawan","status","nomor_rekening","nama_bank","no_bpjamsostek","no_bpjs_kes","end_of_contract"
  ],
  "SMBCI": [
    "nomor_kontrak","tanggal_pembuatan_pks","tanggal_lahir","nama_karyawan","tempat_lahir","jabatan","nik_ktp","alamat","rt_rw","kelurahan","kecamatan","kota","no_hp","pendidikan_terakhir","nama_user","penempatan","join_date","end_date","umk_ump","tanggal_pernyataan","nik_karyawan","nomor_surat_tugas","masa_penugasan","alamat_email","nomor_reff","jenis_kelamin","npwp","status_pajak","no_kk","nama_ayah","nama_ibu","recruitment_officer","team_leader","status","nomor_rekening","nama_bank","no_bpjamsostek","no_bpjs_kes","end_of_contract"
  ],
  "INTERNAL": [
    "nama_karyawan","cabang","nomor_kontrak","tanggal_pembuatan_pks","tempat_lahir","tanggal_lahir","alamat","rt_rw","kelurahan","kecamatan","kota_kabupaten","nik_ktp","pendidikan_terakhir","no_hp","jabatan","join_date","end_date","gapok","jenis_kelamin","alamat_email","alamat_tinggal","tl","manager","npwp","status_pajak","no_kk","nama_ayah","nama_ibu","status","nomor_rekening","nama_bank","end_of_contract","role"
  ]
};

// Alias proyek agar lebih toleran
const PROJECT_ALIASES = {
  "SMBCI": ["SMBCI","SMBC","SMB CI","SMBC1","SMBCL"],
  "ALLO": ["ALLO"],
  "MOLADIN": ["MOLADIN"],
  "NOBU": ["NOBU"],
  "CIMB": ["CIMB"],
  "CNAF": ["CNAF"],
  "BNIF": ["BNIF","BNI F","BNI-F"],
  "INTERNAL": ["INTERNAL"]
};
function resolveProjKey(raw){
  const s=(raw||'').toString().trim().toUpperCase();
  if(PROJECT_FIELDS[s]) return s;
  for(const key in PROJECT_ALIASES){
    const list=PROJECT_ALIASES[key];
    if(list.some(v => v.replace(/\s+/g,'')===s.replace(/\s+/g,''))) return key;
  }
  const fixed=s.replace(/1/g,'I').replace(/l/g,'I');
  if(PROJECT_FIELDS[fixed]) return fixed;
  return '';
}

// ===== Field wajib (selalu tampil) =====
const FIXED_FIELDS = ["nik_ktp", "nik_karyawan"];

// Render field edit sesuai proyek + field wajib
const editBox = document.getElementById('dynamic_box');
const labelsHidden = document.getElementById('__labels_map');
const proyekText = document.getElementById('proyek_text');
const proyekHidden = document.getElementById('proyek_hidden');

function renderForProject(key){
  editBox.innerHTML=''; labelsHidden.value='';
  const map = {};

  // 1) Field wajib dulu
  FIXED_FIELDS.forEach(raw=>{
    const div = document.createElement('div');
    div.className='form-group';
    div.innerHTML = inputFor(raw);
    editBox.appendChild(div);
    map[slugify(raw)] = ucLabel(raw);
  });

  // 2) Field spesifik proyek
  if(key && PROJECT_FIELDS[key]){
    PROJECT_FIELDS[key].forEach(raw=>{
      const slug = slugify(raw);
      if (map[slug]) return; // hindari duplikat
      const div = document.createElement('div');
      div.className='form-group';
      div.innerHTML = inputFor(raw);
      editBox.appendChild(div);
      map[slug] = ucLabel(raw);
    });
  } else {
    const p = document.createElement('p');
    p.textContent = 'Proyek tidak dikenali.';
    editBox.appendChild(p);
  }

  labelsHidden.value = JSON.stringify(map);
}

function fillValues(container, data){
  const els = container.querySelectorAll('input,select,textarea');
  els.forEach(el=>{
    const name = el.name; if(!name) return;
    let v = data[name];
    if(v===undefined){
      const target = name.replace(/_/g,'');
      for(const k in data){ if(k.replace(/_/g,'')===target){ v=data[k]; break; } }
    }
    if(v===undefined || v===null){ el.value=''; return; }
    if(el.type==='date') el.value = toDateInput(v);
    else el.value = v;
  });
}

// Init
(function(){
  const projKey = resolveProjKey(EMP.proyek || '');
  proyekText.value = projKey || '-';
  proyekHidden.value = projKey || '';
  renderForProject(projKey);
  fillValues(editBox, EMP);
})();
</script>
</body>
</html>
