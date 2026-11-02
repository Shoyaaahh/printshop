<?php
require __DIR__ . '/../../app/bootstrap.php';
$u = require_login('customer'); $uid = (int)$u['id'];
$ref = strtoupper(get('ref'));
$o = load_order('o.reference = ?', [$ref], $uid);          // ownership enforced in the query
if (!$o) { http_response_code(404); flash('error', 'Order not found.'); redirect('customer/orders.php'); }
$self = 'customer/order_view.php?ref=' . $o['reference'];

if (is_post()) {
    csrf_check();
    $act = post('action'); $err = null; $ok = 'Done.';
    if ($act === 'accept_price') $err = customer_answer_price($o, $uid, true);
    elseif ($act === 'decline_price') $err = customer_answer_price($o, $uid, false);
    elseif ($act === 'cancel') $err = customer_cancel($o, $uid);
    elseif ($act === 'pay') {
        if (!rate_limit('pay:' . $uid, 20, 3600)) $err = 'Too many attempts. Try again later.';
        else { $err = submit_payment($o, $uid, post('method'), (float)post('amount'), post('reference_no'), $_FILES['receipt'] ?? null); $ok = 'Payment submitted. It is pending staff verification.'; }
    }
    flash($err ? 'error' : 'success', $err ?: $ok);
    redirect($self);
}

$it = $o['item']; $total = order_total($o);
$methods = payment_methods(true);
$canPay = !in_array($o['status'], ['cancelled', 'completed', 'order_received', 'awaiting_review', 'awaiting_customer_confirmation'], true) && $o['payment_status'] !== 'paid'
    && !array_filter($o['payments'], fn($p) => $p['status'] === 'pending_verification');
dash_header('Order ' . $o['reference'], 'customer', 'orders'); ?>
<div style="margin-bottom:1rem">
  <a class="btn btn-sm btn-ghost" href="<?= e(url('customer/orders.php')) ?>"><?= icon('arrow-left') ?> Back to my orders</a>
</div>

<div class="card" style="margin-bottom:1.5rem">
  <div class="card-head" style="flex-wrap:wrap;gap:1rem;border-bottom:1px solid var(--border);padding-bottom:1rem;margin-bottom:1.25rem">
    <div>
      <span class="eyebrow" style="margin-bottom:0.25rem">Customer Order</span>
      <h2 style="font-family:var(--font-mono);font-size:1.65rem;color:var(--blue);margin:0"><?= e($o['reference']) ?></h2>
      <span class="muted small"><?= icon('clock') ?> Submitted <?= fmt_dt($o['created_at']) ?></span>
    </div>
    <div style="display:flex;gap:0.5rem;align-items:center">
      <?= status_badge($o['status']) ?>
      <?= status_badge($o['payment_status']) ?>
    </div>
  </div>
  <?= order_progress($o['status']) ?>
</div>

<?php if ($o['status'] === 'awaiting_customer_confirmation'): ?>
  <div class="alert alert-warn" style="margin-bottom:1.5rem">
    <div>
      <strong>Price changed.</strong> After reviewing your files, the shop updated your total from <?= peso($o['estimated_total']) ?> to <strong><?= peso($total) ?></strong>. Please confirm to continue.
      <div style="margin-top:0.85rem;display:flex;gap:0.6rem">
        <?= confirm_form(url($self), ['action' => 'accept_price'], 'Accept new price', 'Accept the new total of ' . peso($total) . '?', 'btn btn-sm btn-primary') ?>
        <?= confirm_form(url($self), ['action' => 'decline_price'], 'Decline and cancel', 'Decline the new price? The order will be cancelled.', 'btn btn-sm btn-danger') ?>
      </div>
    </div>
  </div>
<?php endif; ?>

<?php if ($o['status'] === 'ready_for_pickup'): ?>
  <div class="pickup-pass-box card" style="background:linear-gradient(135deg,var(--ok-bg),var(--surface));border:2px dashed var(--ok);margin-bottom:1.5rem">
    <div style="display:flex;gap:1rem;align-items:center">
      <span class="svc-ph" style="width:52px;height:52px;background:var(--ok);color:#fff;border-radius:50%"><?= icon('package-check') ?></span>
      <div>
        <h3 style="color:var(--ok);margin:0 0 0.25rem">Ready for pickup!</h3>
        <p style="margin:0">Bring your reference number <strong><?= e($o['reference']) ?></strong> to the store counter.<br><?= nl2br(e(setting('address'))) ?></p>
      </div>
    </div>
  </div>
<?php endif; ?>

<div class="split">
  <div class="grid">
    <div class="card">
      <div class="card-head">
        <h3>Printing specifications</h3>
        <span class="badge badge-muted"><?= e($it['service_name'] ?? '') ?></span>
      </div>
      <dl class="kv">
        <dt>Service</dt><dd><strong><?= e($it['service_name'] ?? '') ?></strong></dd>
        <?php foreach (['paper_size' => 'Paper size', 'paper_type' => 'Paper type', 'orientation' => 'Orientation', 'pages' => 'Pages', 'copies' => 'Copies'] as $k => $l) if (!empty($it[$k])) echo '<dt>' . $l . '</dt><dd>' . e(ucfirst((string)$it[$k])) . '</dd>'; ?>
        <?php if (!empty($it['color'])) echo '<dt>Color</dt><dd>' . e(COLORS[$it['color']] ?? $it['color']) . '</dd>'; ?>
        <?php if (!empty($it['sides'])) echo '<dt>Sides</dt><dd>' . e(SIDES[$it['sides']] ?? $it['sides']) . '</dd>'; ?>
        <?php if (!empty($it['binding']) && $it['binding'] !== 'none') echo '<dt>Binding</dt><dd>' . e(BINDINGS[$it['binding']]) . '</dd>'; ?>
        <?php if (!empty($it['width_ft'])) echo '<dt>Dimensions</dt><dd>' . e($it['width_ft']) . ' × ' . e($it['height_ft']) . ' ft</dd>'; ?>
        <dt>Instructions</dt><dd><?= $o['special_instructions'] ? nl2br(e($o['special_instructions'])) : '—' ?></dd>
      </dl>
    </div>

    <div class="card">
      <div class="card-head">
        <h3>Uploaded files</h3>
        <span class="badge badge-muted"><?= count($o['files']) ?> file(s)</span>
      </div>
      <?php foreach ($o['files'] as $f): ?>
        <div class="file-row">
          <span class="svc-ph" style="width:42px;height:42px"><?= icon('file-text') ?></span>
          <div class="meta">
            <div class="name"><?= e($f['original_name']) ?></div>
            <div class="small muted"><?= round($f['size_bytes'] / 1024) ?> KB<?= $f['page_count'] ? ' · ' . (int)$f['page_count'] . ' page(s)' : '' ?></div>
          </div>
          <a class="btn btn-sm btn-outline" href="<?= e(url('download.php?id=' . $f['id'])) ?>"><?= icon('download') ?> Download</a>
        </div>
      <?php endforeach; ?>
      <?php if (!$o['files']) echo '<p class="muted">No files attached. Bring your physical originals to the store counter.</p>'; ?>
    </div>

    <div class="card">
      <div class="card-head">
        <h3>Payment verification</h3>
      </div>
      <?php foreach ($o['payments'] as $p): ?>
        <div class="file-row">
          <div class="meta">
            <strong><?= e(payment_methods()[$p['method']]['label'] ?? $p['method']) ?></strong> · <?= peso($p['amount']) ?>
            <div class="small muted">
              <?= $p['reference_no'] ? 'Ref ' . e($p['reference_no']) . ' · ' : '' ?>
              <?= fmt_dt($p['submitted_at']) ?>
              <?php if (!empty($p['rejection_reason'])): ?><br><span style="color:var(--bad)">Reason: <?= e($p['rejection_reason']) ?></span><?php endif; ?>
              <?php if (!empty($p['receipt_id'])): ?><br><a href="<?= e(url('download.php?type=receipt&inline=1&id=' . $p['receipt_id'])) ?>" target="_blank" rel="noopener">View submitted proof</a><?php endif; ?>
            </div>
          </div>
          <?= status_badge($p['status']) ?>
          <?php if ($p['status'] === 'paid'): ?>
            <a class="btn btn-sm btn-outline" href="<?= e(url('receipt.php?id=' . $p['id'])) ?>"><?= icon('receipt') ?> Receipt</a>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
      <?php if (!$o['payments']) echo '<p class="muted">No payments recorded yet.</p>'; ?>

      <?php if ($canPay && $methods): ?>
        <hr style="border:0;border-top:1px solid var(--border);margin:1.5rem 0 1rem">
        <h3>Submit payment proof</h3>
        <p class="muted small"><?= setting('payment_policy') === 'before_processing' ? 'Payment must be verified before our workshop starts printing.' : 'You may also settle in cash at pickup.' ?> Amount due: <strong><?= peso($total) ?></strong>. Payments are marked paid upon staff verification.</p>
        <?php if ($o['payment_status'] === 'rejected'): ?><div class="alert alert-warn" role="status"><strong>Payment proof was rejected.</strong> Review the reason above, correct it, then submit a replacement payment on this same order.</div><?php endif; ?>
        <form method="post" enctype="multipart/form-data">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="pay">
          <div class="field">
            <label for="method">Payment channel</label>
            <select id="method" name="method" required>
              <?php foreach ($methods as $k => $m) echo '<option value="' . $k . '">' . e($m['label']) . '</option>'; ?>
            </select>
          </div>
          <div class="payment-method-summary" id="paymentMethodSummary" aria-live="polite"><strong id="paymentMethodName"><?= e(reset($methods)['label'] ?? 'Payment') ?></strong><span>Amount to pay: <strong><?= peso($total) ?></strong></span></div>
          <div id="instr">
            <?php foreach ($methods as $k => $m): ?>
              <div class="alert alert-info" data-m="<?= $k ?>" hidden>
                <?= $m['account'] ? '<strong>' . e($m['account']) . '</strong><br>' : '' ?>
                <?= nl2br(e($m['instructions'])) ?>
              </div>
            <?php endforeach; ?>
          </div>
          <div id="onlineFields">
            <div class="form-grid">
              <div class="field"><label for="amount">Amount sent (PHP)</label><input id="amount" type="number" step="0.01" min="0" name="amount" value="<?= e(number_format($total, 2, '.', '')) ?>" required></div>
              <div class="field"><label for="reference_no">Transaction reference</label><input id="reference_no" name="reference_no" maxlength="60" placeholder="GCash / Maya / Bank Ref No." required></div>
            </div>
            <div class="field" id="proofField"><label id="receiptLabel" for="receipt">Payment screenshot <span class="muted">(required)</span></label>
              <input id="receipt" type="file" name="receipt" accept=".jpg,.jpeg,.png,.webp" aria-describedby="receiptHint proofFeedback">
              <div class="hint" id="receiptHint">JPG, JPEG, PNG, or WebP image. Maximum <?= (int)(MAX_UPLOAD / 1048576) ?> MB.</div>
              <div class="field-error" id="proofFeedback" role="alert" hidden></div>
              <div class="payment-proof-preview" id="proofPreview" hidden><img id="receiptPreview" alt="Selected payment screenshot preview"><div class="payment-proof-file"><strong id="receiptName"></strong><span class="small muted" id="receiptSize"></span></div><button type="button" class="btn btn-sm btn-outline" id="replaceReceipt">Replace image</button><button type="button" class="btn btn-sm btn-ghost" id="clearReceipt">Remove</button></div>
            </div>
          </div>
          <button class="btn btn-primary" id="submitPayment" type="submit"><?= icon('wallet') ?> Submit payment</button>
        </form>
        <script>
          (function(){
            var form=document.querySelector('#method').form, method=document.getElementById('method'), fields=document.getElementById('onlineFields');
            var amount=document.getElementById('amount'), reference=document.getElementById('reference_no'), proofField=document.getElementById('proofField');
            var receipt=document.getElementById('receipt'), label=document.getElementById('receiptLabel'), hint=document.getElementById('receiptHint');
            var feedback=document.getElementById('proofFeedback'), preview=document.getElementById('proofPreview'), image=document.getElementById('receiptPreview');
            var submit=document.getElementById('submitPayment'), methodName=document.getElementById('paymentMethodName');
            var maxBytes=<?= MAX_UPLOAD ?>, imageUrl='';
            function digital(){return method.value==='gcash'||method.value==='maya'}
            function validateFile(file){
              if(!file)return '';
              var ext=(file.name.split('.').pop()||'').toLowerCase();
              var allowed=digital()?['jpg','jpeg','png','webp']:['pdf','jpg','jpeg','png'];
              if(allowed.indexOf(ext)<0)return digital()?'Choose a JPG, JPEG, PNG, or WebP image.':'Choose a PDF, JPG, JPEG, or PNG receipt.';
              if(digital()&&file.type&&!['image/jpeg','image/png','image/webp'].includes(file.type))return 'The selected file is not a supported image.';
              if(file.size<=0)return 'The selected file is empty.';
              if(file.size>maxBytes)return 'The file exceeds the '+Math.round(maxBytes/1048576)+' MB upload limit.';
              return '';
            }
            function clearPreview(){if(imageUrl)URL.revokeObjectURL(imageUrl);imageUrl='';preview.hidden=true;image.removeAttribute('src');document.getElementById('receiptName').textContent='';document.getElementById('receiptSize').textContent=''}
            function showFile(){
              var file=receipt.files[0], error=validateFile(file); feedback.textContent=error; feedback.hidden=!error;
              receipt.setCustomValidity(error); clearPreview();
              if(file&&!error&&digital()){imageUrl=URL.createObjectURL(file);image.src=imageUrl;document.getElementById('receiptName').textContent=file.name;document.getElementById('receiptSize').textContent=(file.size/1048576).toFixed(2)+' MB';preview.hidden=false}
              update();
            }
            function update(){
              var cash=method.value==='cash', isDigital=digital(), file=receipt.files[0];
              fields.hidden=cash;amount.disabled=cash;amount.required=!cash;reference.disabled=cash;reference.required=!cash;
              proofField.hidden=cash; receipt.required=isDigital; receipt.accept=isDigital?'.jpg,.jpeg,.png,.webp':'.pdf,.jpg,.jpeg,.png';
              label.innerHTML=isDigital?'Payment screenshot <span class="muted">(required)</span>':'Bank transfer receipt <span class="muted">(optional)</span>';
              hint.textContent=isDigital?'JPG, JPEG, PNG, or WebP image. Maximum '+Math.round(maxBytes/1048576)+' MB.':'PDF, JPG, JPEG, or PNG. Maximum '+Math.round(maxBytes/1048576)+' MB.';
              methodName.textContent=method.options[method.selectedIndex].text;
              document.querySelectorAll('[data-m]').forEach(function(d){d.hidden=d.dataset.m!==method.value});
              if(isDigital&&file){var error=validateFile(file);feedback.textContent=error;feedback.hidden=!error;receipt.setCustomValidity(error)}else if(isDigital){feedback.textContent='Upload your payment screenshot before submitting.';feedback.hidden=false;receipt.setCustomValidity('')}else{feedback.textContent='';feedback.hidden=true;receipt.setCustomValidity('')}
              submit.disabled=isDigital&&(!file||!!validateFile(file));
            }
            method.addEventListener('change',function(){clearPreview();receipt.value='';update()});
            receipt.addEventListener('change',showFile);
            document.getElementById('replaceReceipt').addEventListener('click',function(){receipt.click()});
            document.getElementById('clearReceipt').addEventListener('click',function(){receipt.value='';showFile()});
            form.addEventListener('submit',function(ev){if(digital()&&(!receipt.files[0]||validateFile(receipt.files[0]))){ev.preventDefault();if(!receipt.files[0]){feedback.textContent='Upload your payment screenshot before submitting.';feedback.hidden=false}receipt.reportValidity();return}submit.disabled=true;submit.textContent='Submitting payment…'});
            update();
          })();
        </script>
      <?php endif; ?>
    </div>
  </div>

  <div class="grid">
    <div class="card">
      <div class="card-head">
        <h3>Price breakdown</h3>
      </div>
      <?php foreach (json_decode($it['breakdown'] ?? '[]', true) ?: [] as $l): ?>
        <div class="price-box" style="padding:0;border:0;background:none">
          <div class="line"><span><?= e($l['label']) ?></span><strong><?= peso($l['amount']) ?></strong></div>
        </div>
      <?php endforeach; ?>
      <div class="price-box" style="margin-top:0.75rem">
        <div class="total" style="border:0;margin:0;padding:0">
          <span><?= $o['final_total'] !== null ? 'Final total' : 'Estimated total' ?></span>
          <span style="color:var(--blue)"><?= peso($total) ?></span>
        </div>
        <?php if ($o['price_adjusted']): ?><div class="small muted" style="margin-top:0.25rem">Originally estimated at <?= peso($o['estimated_total']) ?></div><?php endif; ?>
        <?php if ($o['final_total'] === null): ?><div class="small muted" style="margin-top:0.25rem">Estimate only. Confirmed after physical file inspection.</div><?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-head">
        <h3>Pickup details</h3>
      </div>
      <dl class="kv" style="grid-template-columns:1fr">
        <dt>Estimated Completion</dt><dd><strong><?= fmt_dt($o['estimated_completion'], false) ?></strong></dd>
        <dt>Pickup Counter</dt><dd><?= nl2br(e(setting('address'))) ?></dd>
      </dl>
      <p class="small muted" style="margin-top:1rem;border-top:1px dashed var(--border);padding-top:0.75rem"><?= nl2br(e(setting('pickup_policy'))) ?></p>
    </div>

    <div class="card">
      <div class="card-head">
        <h3>Milestone history</h3>
      </div>
      <ul class="timeline">
        <?php foreach (array_reverse($o['history']) as $h): ?>
          <li class="<?= $h['status'] === $o['status'] ? 'timeline-current' : '' ?>">
            <strong><?= e(ORDER_STATUSES[$h['status']] ?? $h['status']) ?></strong>
            <div class="small muted"><?= fmt_dt($h['created_at']) ?></div>
            <?php if (!empty($h['note'])): ?><div class="small" style="margin-top:0.2rem"><?= e($h['note']) ?></div><?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>

    <?php if (in_array($o['status'], CUSTOMER_CANCELLABLE, true) && $o['payment_status'] !== 'paid'): ?>
      <div><?= confirm_form(url($self), ['action' => 'cancel'], icon('x') . ' Cancel order', 'Cancel this order? This cannot be undone.', 'btn btn-outline btn-danger') ?></div>
    <?php endif; ?>
  </div>
</div>
<?php dash_footer();
