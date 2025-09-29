<?php
// ===========================
// admin/invoice/invoice_pdf_template.php
// Dipanggil oleh download_invoice.php via include
// Variabel tersedia: $invoice, $items, $LOGO_SRC, $LOGO_FILE, $LOGO_URL
// ===========================
if (!function_exists('e')) {
    function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('rupiah')) {
    function rupiah($n){ return 'Rp ' . number_format((float)$n, 0, ',', '.'); }
}

// pilih sumber logo (base64 -> file:/// -> URL)
$__logo = $LOGO_SRC ?: ($LOGO_FILE ?: $LOGO_URL);
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
  /* Reset kecil agar konsisten di Dompdf */
  * { box-sizing: border-box; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; margin: 0; padding: 24px; }
  .brand{ display:flex; gap:14px; align-items:center; border-bottom:3px solid #e31837; padding-bottom:8px; margin-bottom:12px; }
  .brand img { height: 60px; }
  .brand h1 { margin:0; color:#e31837; font-size:18px; font-weight:700; }
  .brand p { margin:0; font-size:11px; }
  .muted { font-style: italic; }

  .tag { display:inline-block; background:#e31837; color:#fff; font-weight:700; padding:6px 10px; border-radius:4px; margin:10px 0; }

  .head-row { width:100%; }
  .col-left { width:58%; float:left; }
  .col-right{ width:38%; float:right; }

  table.info { width:100%; border-collapse:collapse; font-size:12px; }
  table.info td { padding:3px 0; }
  .label { width:70px; }

  table.items { width:100%; border-collapse:collapse; margin-top:8px; font-size:12px; }
  table.items th, table.items td { border:1px solid #000; padding:6px 8px; }
  table.items thead th { background:#f3f3f3; font-weight:700; }
  .tr { text-align:right; }

  table.summary { width:42%; margin-left:auto; border-collapse:collapse; font-size:12px; margin-top:10px; }
  table.summary td { border:1px solid #000; padding:6px 8px; }
  table.summary tr:last-child td { background:#f3f3f3; font-weight:700; }

  .transfer p { margin:4px 0; font-size:12px; }
  .sign { width:100%; margin-top:24px; }
  .sign .date { text-align:right; }
  .sign .line { border-bottom:1px solid #000; display:inline-block; padding:0 60px; font-weight:700; margin-bottom:6px; }
  .sign .title { font-size:12px; }
  .clearfix:after { content:""; display:block; clear:both; }
</style>
</head>
<body>

  <!-- Header / Brand -->
  <div class="brand">
    <img src="<?php echo e($__logo); ?>" alt="Logo">
    <div>
      <h1>PT. MANDIRI ANDALAN UTAMA</h1>
      <p class="muted">Committed to delivered the best result</p>
      <p>Jl Sultan Iskandar Muda No. 50 A-B</p>
      <p>Kebayoran Lama Selatan - Kebayoran Lama Jakarta Selatan 12240</p>
      <p>021-27518306 • www.manu.co.id</p>
    </div>
  </div>

  <div class="tag">BILL TO:</div>

  <!-- Bill to + Info -->
  <div class="head-row clearfix">
    <div class="col-left">
      <p><strong><?php echo e($invoice['bill_to_bank'] ?? ''); ?></strong></p>
      <?php if(!empty($invoice['bill_to_address1'])): ?><p><?php echo e($invoice['bill_to_address1']); ?></p><?php endif; ?>
      <?php if(!empty($invoice['bill_to_address2'])): ?><p><?php echo e($invoice['bill_to_address2']); ?></p><?php endif; ?>
      <?php if(!empty($invoice['bill_to_address3'])): ?><p><?php echo e($invoice['bill_to_address3']); ?></p><?php endif; ?>
    </div>
    <div class="col-right">
      <table class="info">
        <tr><td class="label">No</td><td>:</td><td><?php echo e($invoice['invoice_number'] ?? ''); ?></td></tr>
        <tr><td class="label">Tanggal</td><td>:</td><td><?php echo e(date('d/m/Y', strtotime($invoice['invoice_date'] ?? 'now'))); ?></td></tr>
        <tr><td class="label">Up</td><td>:</td><td><?php echo e($invoice['person_up_name'] ?? ''); ?></td></tr>
      </table>
    </div>
  </div>

  <!-- Items -->
  <table class="items">
    <thead>
      <tr>
        <th style="width:6%;">No</th>
        <th style="width:64%;">Description</th>
        <th style="width:30%;" class="tr">Amount</th>
      </tr>
    </thead>
    <tbody>
    <?php if(!empty($items)): $i=1; foreach($items as $it): ?>
      <tr>
        <td><?php echo $i++; ?></td>
        <td><?php echo e($it['description'] ?? ''); ?></td>
        <td class="tr"><?php echo rupiah($it['amount'] ?? 0); ?></td>
      </tr>
    <?php endforeach; else: ?>
      <tr><td colspan="3" style="text-align:center">Tidak ada item.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>

  <!-- Summary -->
  <table class="summary">
    <tr><td>SUB TOTAL</td><td class="tr"><?php echo rupiah($invoice['sub_total'] ?? 0); ?></td></tr>
    <tr><td>PPN</td><td class="tr"><?php echo rupiah($invoice['ppn_amount'] ?? 0); ?></td></tr>
    <tr><td>PPH</td><td class="tr"><?php echo rupiah($invoice['pph_amount'] ?? 0); ?></td></tr>
    <tr><td>GRAND TOTAL</td><td class="tr"><?php echo rupiah($invoice['grand_total'] ?? 0); ?></td></tr>
  </table>

  <!-- Transfer -->
  <div class="transfer">
    <div class="tag">Please Transfer to Account:</div>
    <p><strong>Bank :</strong> <?php echo e($invoice['transfer_bank'] ?? ''); ?></p>
    <p><strong>Rekening Number :</strong> <?php echo e($invoice['transfer_account_no'] ?? ''); ?></p>
    <p><strong>A/C :</strong> <?php echo e($invoice['transfer_account_name'] ?? ''); ?></p>
  </div>

  <!-- Signature -->
  <div class="sign">
    <p class="date">Jakarta, <?php echo e(date('d F Y', strtotime($invoice['footer_date'] ?? 'now'))); ?></p>
    <br><br><br>
    <p class="line"><?php echo e($invoice['manu_signatory_name'] ?? ''); ?></p>
    <p class="title"><?php echo e($invoice['manu_signatory_title'] ?? ''); ?></p>
  </div>

</body>
</html>
