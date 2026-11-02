(function () {
  var D = window.ORDER, root = document.getElementById('wizard');
  var ALLOWED = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'ppt', 'pptx'];
  var STEPS = ['Service', 'Options', 'Upload Files', 'Pricing', 'Review', 'Confirmed'];
  var S = { step: 1, service: null, v: {}, files: [], price: null, priceLoading: false, priceRequest: 0, priceTimer: null, submitting: false, method: '', error: '' };

  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }
  function peso(n) { return String.fromCharCode(8369) + Number(n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
  function fmtSize(b) { return b > 1048576 ? (b / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB'; }
  function has(f) { return S.service && S.service.options.fields.indexOf(f) > -1; }
  function icons() { if (window.lucide) lucide.createIcons(); }

  if (!D.accepting) {
    root.innerHTML = '<div class="alert alert-warn">' + esc(D.closedMsg) + '</div>';
    return;
  }

  function pickService(svc) {
    if (S.service && S.service.id === svc.id) return;
    S.service = svc; S.price = null;
    var o = svc.options;
    S.v = { size: o.sizes ? o.sizes[0] : 'A4', paper: o.papers ? o.papers[0] : 'Regular', color: o.colors ? o.colors[0] : 'bw', sides: o.sides ? o.sides[0] : 'single', orientation: 'portrait', pages: 1, copies: 1,
            binding: 'none', width: 3, height: 4, notes: S.v.notes || '' };
  }
  if (D.preselect) {
    D.services.forEach(function (s) { if (s.slug === D.preselect) { pickService(s); S.step = 2; } });
  }

  // ------------------------------------------------------------ rendering
  function header() {
    var pct = Math.round((S.step - 1) / 4 * 100);
    var h = '<div class="wizard-progress" role="progressbar" aria-label="Order progress" aria-valuemin="1" aria-valuemax="5" aria-valuenow="' + S.step + '"><span style="width:' + pct + '%"></span></div>';
    h += '<div class="wizard-steps">';
    STEPS.forEach(function (n, i) {
      if (i >= 5) return;
      var cls = i + 1 < S.step ? 'done' : (i + 1 === S.step ? 'current' : '');
      var iconMark = i + 1 < S.step ? '<i data-lucide="check" style="width:13px;height:13px;margin-right:3px"></i>' : (i + 1) + '. ';
      h += '<span class="' + cls + '">' + iconMark + n + '</span>';
    });
    h += '</div>' + (S.error ? '<div class="alert alert-error">' + esc(S.error) + '</div>' : '');
    return h;
  }

  function navButtons(next, nextLabel, disabled) {
    return '<div class="wizard-nav">' +
      (S.step > 1 ? '<button type="button" class="btn btn-outline" data-act="back"><i data-lucide="arrow-left"></i> Back</button>' : '<span></span>') +
      '<button type="button" class="btn btn-primary" data-act="' + next + '"' + (disabled ? ' disabled' : '') + '>' + nextLabel + ' <i data-lucide="arrow-right"></i></button></div>';
  }

  function renderSummary() {
    if (!S.service) {
      return '<div class="order-summary-card"><div class="card-head"><h3>Live Order Summary</h3></div><p class="muted small">Select a printing service on the left to begin configuring your specifications.</p></div>';
    }
    var s = S.service, v = S.v, L = D.labels;
    var det = detectedPages();
    var fileCount = S.files.filter(function (f) { return f.status === 'done'; }).length;

    var h = '<div class="order-summary-card">';
    h += '<div class="card-head"><h3>Live Order Summary</h3><span class="badge badge-accent">Step ' + S.step + ' of 5</span></div>';
    h += '<div class="summary-service-preview">';
    h += '  <div class="summary-service-title">' + esc(s.name) + '</div>';
    h += '  <div class="small muted">' + esc(s.category) + ' | Ready in ~' + s.days + ' day(s)</div>';
    h += '</div>';

    h += '<div class="summary-spec-list">';
    if (has('size')) h += '<div class="summary-spec-item"><span>Paper Size</span><strong>' + esc(v.size) + '</strong></div>';
    if (has('paper')) h += '<div class="summary-spec-item"><span>Paper Stock</span><strong>' + esc(v.paper) + '</strong></div>';
    if (has('color')) h += '<div class="summary-spec-item"><span>Color</span><strong>' + esc(L.colors[v.color] || v.color) + '</strong></div>';
    if (has('sides')) h += '<div class="summary-spec-item"><span>Sides</span><strong>' + esc(L.sides[v.sides] || v.sides) + '</strong></div>';
    if (has('pages')) h += '<div class="summary-spec-item"><span>Pages</span><strong>' + esc(v.pages) + (det > 0 && det !== Number(v.pages) ? ' <small class="muted">(' + det + ' in file)</small>' : '') + '</strong></div>';
    if (has('copies')) h += '<div class="summary-spec-item"><span>Copies</span><strong>' + esc(v.copies) + '</strong></div>';
    if (has('binding') && v.binding !== 'none') h += '<div class="summary-spec-item"><span>Finishing</span><strong>' + esc(L.bindings[v.binding] || v.binding) + '</strong></div>';
    if (has('dimensions')) h += '<div class="summary-spec-item"><span>Dimensions</span><strong>' + esc(v.width) + ' x ' + esc(v.height) + ' ft</strong></div>';
    h += '<div class="summary-spec-item"><span>Files</span><strong>' + (fileCount > 0 ? fileCount + ' file(s)' : '<span class="muted">None attached</span>') + '</strong></div>';
    h += '</div>';

    h += '<div class="summary-price-box">';
    if (S.price && S.price.lines && S.price.lines.length) {
      h += '<div class="summary-price-lines">' + S.price.lines.map(function (line) {
        return '<div class="summary-price-line"><span>' + esc(line.label) + '</span><strong>' + peso(line.amount) + '</strong></div>';
      }).join('') + '</div>';
    }
    var priceText = S.price ? (S.price.error ? 'Estimate unavailable' : peso(S.price.total)) : (S.priceLoading ? 'Updating...' : 'Calculating...');
    h += '  <div class="summary-price-row"><span>Estimated Total:</span><strong class="summary-total-amt" aria-live="polite" aria-atomic="true">' + priceText + '</strong></div>';
    h += '  <div class="small muted" style="margin-top:0.35rem"><i data-lucide="store" style="width:13px;height:13px"></i> Store pickup at: ' + esc(D.store.address.split('\n')[0]) + '</div>';
    h += '</div>';

    h += '</div>';
    return h;
  }

  function render() {
    if (S.step === 6) {
      root.classList.remove('step-enter');
      root.innerHTML = step6();
      void root.offsetWidth;
      root.classList.add('step-enter');
      icons();
      bind();
      return;
    }

    var body = '';
    if (S.step === 1) body = step1();
    else if (S.step === 2) body = step2();
    else if (S.step === 3) body = step3();
    else if (S.step === 4) body = step4();
    else if (S.step === 5) body = step5();

    var fullHtml = '<div class="wizard-layout">' +
      '<div class="wizard-main">' + header() + '<div class="card wizard-step-card">' + body + '</div></div>' +
      '<div class="wizard-summary-col">' + renderSummary() + '</div>' +
      '</div>';

    root.classList.remove('step-enter');
    root.innerHTML = fullHtml;
    void root.offsetWidth;
    root.classList.add('step-enter');
    icons();
    bind();
    if (S.service && !S.price && !S.priceLoading) schedulePrice(0);
  }

  function step1() {
    var cats = {}, order = [];
    D.services.forEach(function (s) { if (!cats[s.category]) { cats[s.category] = []; order.push(s.category); } cats[s.category].push(s); });
    var h = '<div class="step-head"><h2>Step 1: Select a printing service</h2><p class="muted">Choose what you would like to print. You can configure exact paper weights, color modes, and bindings in the next step.</p></div>';
    order.forEach(function (c) {
      h += '<h3 style="margin-top:1.5rem;font-size:1.1rem;color:var(--text)">' + esc(c) + '</h3><div class="svc-pick">';
      cats[c].forEach(function (s) {
        var isChecked = S.service && S.service.id === s.id;
        h += '<label class="' + (isChecked ? 'selected' : '') + '"><input type="radio" name="svc" value="' + s.id + '"' + (isChecked ? ' checked' : '') + '>' +
          '<div style="font-weight:700;font-size:0.98rem;margin-bottom:0.25rem">' + esc(s.name) + '</div>' +
          '<div class="small muted" style="margin-bottom:0.4rem">' + esc(s.description.length > 75 ? s.description.substring(0, 75) + '...' : s.description) + '</div>' +
          '<div class="price" style="font-size:0.92rem;color:var(--blue)">' + esc(s.price_label) + '</div>' +
          '</label>';
      });
      h += '</div>';
    });
    return h + navButtons('next', 'Continue to Options', !S.service);
  }

  function opt(list, sel, labels) {
    return list.map(function (x) { return '<option value="' + esc(x) + '"' + (x === sel ? ' selected' : '') + '>' + esc(labels ? labels[x] : x) + '</option>'; }).join('');
  }
  function field(label, html, hint) { return '<div class="field"><label>' + label + '<span class="field-control">' + html + '</span></label>' + (hint ? '<div class="hint">' + hint + '</div>' : '') + '</div>'; }

  function sizeGuide(sizes) {
    var dimensions = {
      A3: [297, 420, '297 x 420 mm'],
      A4: [210, 297, '210 x 297 mm'],
      Letter: [215.9, 279.4, '8.5 x 11 in'],
      Legal: [215.9, 355.6, '8.5 x 14 in']
    };
    var list = (sizes || []).filter(function (size, i, all) { return all.indexOf(size) === i; });
    if (!list.length) return '';
    var h = '<div class="size-guide"><div class="size-guide-head"><strong>Paper size proportions</strong><span class="muted small">Portrait orientation | proportional previews</span></div><div class="paper-size-grid">';
    list.forEach(function (size, i) {
      var dims = dimensions[size], ratio = dims ? dims[0] / dims[1] : 0;
      h += '<div class="paper-size-item">';
      if (dims) {
        var pw = 78 * ratio, x = (100 - pw) / 2;
        h += '<svg viewBox="0 0 100 100" role="img" aria-label="' + esc(size) + ' paper preview, ' + dims[2] + '"><rect class="paper-sheet" x="' + x.toFixed(1) + '" y="8" width="' + pw.toFixed(1) + '" height="78" rx="3" /></svg>';
      } else {
        h += '<div class="paper-custom-shape" aria-hidden="true">Set to order</div>';
      }
      h += '<strong class="paper-label">' + esc(size) + '</strong><span class="paper-dimensions">' + (dims ? dims[2] : 'Dimensions by request') + '</span></div>';
    });
    return h + '</div></div>';
  }

  function step2() {
    var o = S.service.options, v = S.v, L = D.labels;
    var h = '<div class="step-head"><h2>Step 2: Specifications for ' + esc(S.service.name) + '</h2>' +
      '<p class="muted">' + esc(S.service.description) + '</p></div><div class="form-grid">';
    if (has('size')) h += field('Paper size', '<select data-k="size">' + opt(o.sizes, v.size) + '</select>', v.size === 'Custom' ? 'Describe the size in the instructions below. The shop confirms final price.' : '');
    if (has('paper')) h += field('Paper stock <span class="help-tip" tabindex="0" title="Paper stock and weight influence finish and pricing." aria-label="Paper choices affect finish and price.">' + iconsHtml('info') + '</span>', '<select data-k="paper">' + opt(o.papers, v.paper) + '</select>', 'Regular paper is ideal for everyday text; cardstock and photo paper provide heavy premium finish.');
    if (has('color')) {
      h += '<div class="field"><span class="field-label">Printing color</span><div class="color-mode-options" role="radiogroup" aria-label="Printing color">';
      o.colors.forEach(function (color) {
        h += '<label class="color-mode-option"><input type="radio" name="color-mode" data-k="color" value="' + esc(color) + '"' + (v.color === color ? ' checked' : '') + '><span>' + esc(L.colors[color] || color) + '</span></label>';
      });
      h += '</div></div>';
    }
    if (has('sides')) h += field('Printing side', '<select data-k="sides">' + opt(o.sides, v.sides, L.sides) + '</select>', 'Double-sided printing receives a 10% volume discount.');
    if (has('orientation')) h += field('Orientation', '<select data-k="orientation">' + opt(['portrait', 'landscape'], v.orientation, { portrait: 'Portrait', landscape: 'Landscape' }) + '</select>');
    if (has('pages')) h += field('Number of pages', '<input type="number" min="1" max="5000" data-k="pages" value="' + esc(v.pages) + '">', 'Enter document page count. You can auto-sync with detected pages in Step 4.');
    if (has('dimensions')) h += field('Width (ft)', '<input type="number" min="0.5" max="100" step="0.1" data-k="width" value="' + esc(v.width) + '">') + field('Height (ft)', '<input type="number" min="0.5" max="100" step="0.1" data-k="height" value="' + esc(v.height) + '">');
    if (has('copies')) h += field('Number of copies', '<input type="number" min="1" max="10000" data-k="copies" value="' + esc(v.copies) + '">');
    if (has('binding')) h += field('Binding finish <span class="help-tip" tabindex="0" title="Binding is applied after printing." aria-label="Binding is applied after printing.">' + iconsHtml('info') + '</span>', '<select data-k="binding">' + opt(o.bindings, v.binding, L.bindings) + '</select>', 'Choose spiral coil, soft spine, or hardcover for reports and thesis books.');
    h += '</div>' + field('Special instructions / Custom dimensions', '<textarea data-k="notes" maxlength="1000" placeholder="Notes for print technicians: specific page ranges, bleed specifications, folding, cutting...">' + esc(v.notes) + '</textarea>');

    h += sizeGuide(o.sizes);
    return h + navButtons('next', 'Continue to Uploads');
  }
  function iconsHtml(name) { return '<i data-lucide="' + name + '" aria-hidden="true"></i>'; }

  function step3() {
    var req = S.service.needs_upload;
    var h = '<div class="step-head"><h2>Step 3: Upload your document files</h2>' +
      '<p class="muted">' + (req ? 'Upload at least one file to proceed with this service.' : 'Optional for this service. You can also bring your original hard copies to the counter.') +
      ' Supported: PDF, DOC, DOCX, PPT, PPTX, JPG, PNG. Max ' + D.maxMB + ' MB per file. All files stored confidentially.</p></div>' +
      '<div class="dropzone" id="drop" tabindex="0" role="button" aria-label="Upload document files"><i data-lucide="cloud-upload" style="width:36px;height:36px;color:var(--blue)"></i>' +
      '<p style="margin:0.6rem 0 0.2rem"><strong>Drag and drop your print files here</strong><br><span class="muted small">or click to browse from your device</span></p></div>' +
      '<input type="file" id="picker" multiple hidden accept=".pdf,.doc,.docx,.ppt,.pptx,.jpg,.jpeg,.png"><input type="file" id="replacer" hidden accept=".pdf,.doc,.docx,.ppt,.pptx,.jpg,.jpeg,.png"><div id="fileList">';
    S.files.forEach(function (f, i) { h += fileRow(f, i); });
    h += '</div>';
    var busy = S.files.some(function (f) { return f.status === 'uploading'; });
    var ok = S.files.some(function (f) { return f.status === 'done'; });
    return h + navButtons('next', 'Continue to Pricing', busy || (req && !ok));
  }
  function fileRow(f, i) {
    var thumb = f.preview ? '<img src="' + f.preview + '" alt="Preview of ' + esc(f.name) + '" width="46" height="46" loading="lazy" style="border-radius:6px;object-fit:cover">' : '<span class="svc-ph file-icon" aria-hidden="true" style="width:46px;height:46px;border-radius:6px"><i data-lucide="file-text"></i></span>';
    var st = f.status === 'uploading' ? '<div class="bar"><i style="width:' + f.progress + '%"></i></div><div class="small muted">Uploading ' + f.progress + '%</div>'
      : f.status === 'error' ? '<div class="small" style="color:var(--bad)">' + esc(f.error) + '</div>'
      : '<div class="small muted">' + fmtSize(f.size) + (f.pages ? ' <span class="badge badge-info">' + f.pages + ' page(s) detected</span>' : '') + '</div>';
    return '<div class="file-row">' + thumb + '<div class="meta"><div class="name">' + esc(f.name) + '</div>' + st + '</div>' +
      (f.status !== 'uploading' ? '<button type="button" class="btn btn-sm btn-outline" data-replace="' + i + '">Replace</button>' : '') +
      '<button type="button" class="btn btn-sm btn-ghost" data-remove="' + i + '" aria-label="Remove file">Remove</button></div>';
  }

  function detectedPages() {
    var n = 0; S.files.forEach(function (f) { if (f.status === 'done' && f.pages) n += f.pages; }); return n;
  }

  function step4() {
    var h = '<div class="step-head"><h2>Step 4: Price calculation breakdown</h2>' +
      '<p class="muted">Calculated using current shop rate cards and verified file options.</p></div>', p = S.price;
    var det = detectedPages();
    if (has('pages') && det > 0 && det !== Number(S.v.pages)) {
      h += '<div class="alert alert-info"><span>We detected <strong>' + det + '</strong> page(s) in your uploaded file(s), but you entered ' + esc(S.v.pages) + '.</span>' +
        '<button type="button" class="btn btn-sm btn-primary" data-act="usepages" style="margin-left:auto">Sync with ' + det + ' pages</button></div>';
    }
    if (!p) return h + '<div class="price-loading" role="status" aria-live="polite"><span class="estimate-spinner" aria-hidden="true"></span><span class="skeleton skeleton-line"></span><span class="skeleton skeleton-total"></span><span class="muted">Calculating estimate with server rate cards...</span></div>' + navButtons('next', 'Continue', true);
    if (p.error) return h + '<div class="alert alert-error">' + esc(p.error) + '</div>' + navButtons('next', 'Continue', true);
    h += '<div class="price-box" style="margin-top:1rem">';
    p.lines.forEach(function (l) { h += '<div class="line"><div><strong>' + esc(l.label) + '</strong>' + (l.detail ? '<div class="small muted">' + esc(l.detail) + '</div>' : '') + '</div><div>' + peso(l.amount) + '</div></div>'; });
    h += '<div class="total"><span>Estimated Total:</span><span>' + peso(p.total) + '</span></div></div>';
    h += '<div class="alert ' + (p.needs_review ? 'alert-warn' : 'alert-info') + '" style="margin-top:1.25rem">' +
      (p.needs_review ? '<strong>Pre-Flight Review Required:</strong> This service requires technicians to inspect layout density before final charging.' : '<strong>Transparent Estimate:</strong> ') +
      ' If specifications change after physical inspection, you will receive an acceptance alert before any job is printed.</div>';
    return h + navButtons('next', 'Continue to Review');
  }

  function step5() {
    var v = S.v, s = S.service, p = S.price, L = D.labels, rows = '';
    function r(k, val) { if (val !== null && val !== undefined && val !== '') rows += '<dt>' + k + '</dt><dd>' + esc(val) + '</dd>'; }
    r('Service', s.name);
    if (has('size')) r('Paper Size', v.size);
    if (has('paper')) r('Paper Stock', v.paper);
    if (has('color')) r('Color Mode', L.colors[v.color] || v.color);
    if (has('sides')) r('Printing Side', L.sides[v.sides] || v.sides);
    if (has('orientation')) r('Orientation', v.orientation);
    if (has('pages')) r('Pages', v.pages);
    if (has('dimensions')) r('Dimensions', v.width + ' x ' + v.height + ' ft');
    if (has('copies')) r('Copies', v.copies);
    if (has('binding') && v.binding !== 'none') r('Finishing', L.bindings[v.binding] || v.binding);
    var files = S.files.filter(function (f) { return f.status === 'done'; }).map(function (f) { return esc(f.name); }).join('<br>') || 'None (bringing originals to store)';
    var methods = Object.keys(D.methods);
    var h = '<div class="step-head"><h2>Step 5: Review &amp; place your order</h2>' +
      '<p class="muted">Check your printing specifications and customer details before submitting.</p></div>' +
      '<div class="grid g2">' +
      '<div class="card"><div class="card-head"><h3>Customer Information</h3></div><dl class="kv"><dt>Name</dt><dd>' + esc(D.user.name) + '</dd><dt>Email</dt><dd>' + esc(D.user.email) + '</dd><dt>Contact</dt><dd>' + esc(D.user.phone) + '</dd></dl></div>' +
      '<div class="card"><div class="card-head"><h3>Pickup Location</h3></div><p style="margin:0"><strong>' + esc(D.store.address).replace(/\n/g, '<br>') + '</strong></p><p class="small muted" style="margin:.5rem 0 0">Ready in about ' + s.days + ' day(s) after file review. Present reference at pickup.</p></div>' +
      '<div class="card"><div class="card-head"><h3>Print Specifications</h3><button type="button" class="btn btn-sm btn-outline" data-goto="2">Edit</button></div><dl class="kv">' + rows + '</dl></div>' +
      '<div class="card"><div class="card-head"><h3>Attached Files</h3><button type="button" class="btn btn-sm btn-outline" data-goto="3">Edit</button></div><p style="margin:0">' + files + '</p></div></div>';
    h += '<div class="card" style="margin-top:1.2rem"><div class="card-head"><h3>Special Instructions</h3></div><div class="field" style="margin:0"><textarea data-k="notes" maxlength="1000">' + esc(v.notes) + '</textarea></div></div>';
    if (methods.length) {
      h += '<div class="card" style="margin-top:1.2rem"><h3>Payment Preference</h3><p class="muted small">' + (D.paymentPolicy === 'before_processing' ? 'Payment is verified before production. You will be able to upload your payment proof directly after staff approves the order.' : 'You can pay at the counter upon pickup or pay online after approval.') + '</p>' +
        '<div class="field"><label>Preferred payment method</label><select id="method"><option value="">Decide later / at pickup</option>' + methods.map(function (m) { return '<option value="' + m + '"' + (S.method === m ? ' selected' : '') + '>' + esc(D.methods[m].label) + '</option>'; }).join('') + '</select></div><div id="methodInfo"></div></div>';
    }
    h += '<div class="price-box" style="margin-top:1.2rem"><div class="total" style="border:0;margin:0;padding:0"><span>Estimated Total:</span><span>' + (p ? peso(p.total) : '-') + '</span></div><div class="small muted">Final total confirmed upon technician review.</div></div>';
    return h + '<div class="wizard-nav"><button type="button" class="btn btn-outline" data-act="back"><i data-lucide="arrow-left"></i> Back</button><button type="button" class="btn btn-primary btn-lg" data-act="submit"' + (S.submitting ? ' disabled' : '') + '>' + (S.submitting ? '<span class="estimate-spinner"></span> Submitting...' : 'Submit Order Now <i data-lucide="check"></i>') + '</button></div>';
  }

  function step6() {
    var o = S.result;
    return '<div class="card" style="text-align:center;padding:3rem 1.5rem;max-width:680px;margin:2rem auto">' +
      '<div class="svc-ph" style="width:72px;height:72px;margin:0 auto 1.25rem;border-radius:50%;background:var(--ok-bg);color:var(--ok)"><i data-lucide="check" style="width:36px;height:36px"></i></div>' +
      '<h2 style="font-size:2rem;margin-bottom:0.4rem">Order successfully received!</h2>' +
      '<p class="muted">Please save your order reference code. You will need it to track your order and collect it at the counter.</p>' +
      '<div class="pickup-pass-box" style="margin:1.75rem auto;padding:1.5rem;background:var(--surface-2);border:2px dashed var(--border-strong);border-radius:var(--radius-card);max-width:440px">' +
      '  <span class="eyebrow" style="margin-bottom:0.25rem">Order Reference Code</span>' +
      '  <div style="font-size:1.85rem;font-weight:800;letter-spacing:0.06em;color:var(--blue);font-family:var(--font-display)">' + esc(o.reference) + '</div>' +
      '  <div style="margin-top:0.75rem;font-size:1.1rem">Estimated Total: <strong>' + peso(o.total) + '</strong>' + (o.needs_review ? ' <span class="badge badge-warn">To be confirmed</span>' : '') + '</div>' +
      '</div>' +
      '<p class="muted small" style="max-width:50ch;margin:0 auto 1.5rem">Next step: Our staff will review your files. You will receive an in-app notification when your job is approved or when payment can be uploaded.</p>' +
      '<div style="display:flex;justify-content:center;gap:0.85rem;flex-wrap:wrap">' +
      '  <a class="btn btn-primary" href="' + D.links.view + encodeURIComponent(o.reference) + '"><i data-lucide="clipboard-list"></i> View Order Details</a>' +
      '  <a class="btn btn-outline" href="' + D.links.orders + '">My Orders</a>' +
      '</div></div>';
  }

  // ------------------------------------------------------------ behaviour
  function bind() {
    root.querySelectorAll('[data-act]').forEach(function (b) { b.addEventListener('click', function () { act(b.getAttribute('data-act')); }); });
    root.querySelectorAll('[data-goto]').forEach(function (b) { b.addEventListener('click', function () { S.step = Number(b.getAttribute('data-goto')); S.error = ''; render(); }); });
    root.querySelectorAll('input[name=svc]').forEach(function (r) {
      r.addEventListener('change', function () {
        D.services.forEach(function (s) { if (s.id === Number(r.value)) pickService(s); });
        var nextBtn = root.querySelector('[data-act=next]');
        if (nextBtn) nextBtn.disabled = false;
        var summary = root.querySelector('.wizard-summary-col');
        if (summary) { summary.innerHTML = renderSummary(); icons(); }
        schedulePrice(0);
      });
    });
    root.querySelectorAll('[data-k]').forEach(function (el) {
      var updateOption = function () {
        var key = el.getAttribute('data-k');
        var affectsPrice = ['size', 'paper', 'color', 'sides', 'pages', 'copies', 'binding', 'width', 'height'].indexOf(key) !== -1;
        S.v[key] = el.value;
        if (affectsPrice) S.price = null;
        if (el.type === 'number') {
          var invalid = !el.checkValidity(), box = el.closest('.field'), feedback = box && box.querySelector('.field-error');
          el.setAttribute('aria-invalid', invalid ? 'true' : 'false');
          if (box) {
            box.classList.toggle('invalid', invalid);
            if (invalid && !feedback) {
              feedback = document.createElement('div');
              feedback.className = 'field-error';
              feedback.setAttribute('role', 'alert');
              feedback.textContent = key === 'pages' ? 'Enter between 1 and 5,000 pages.' : key === 'copies' ? 'Enter between 1 and 10,000 copies.' : 'Enter a valid number.';
              box.appendChild(feedback);
            } else if (!invalid && feedback) feedback.remove();
          }
        }
        var summary = root.querySelector('.wizard-summary-col');
        if (summary) { summary.innerHTML = renderSummary(); icons(); }
        if (affectsPrice) schedulePrice();
      };
      el.addEventListener('input', updateOption);
      el.addEventListener('change', updateOption);
    });
    var m = document.getElementById('method');
    if (m) { m.addEventListener('change', function () { S.method = m.value; showMethod(); }); showMethod(); }
    if (S.step === 3) bindUpload();
  }

  function showMethod() {
    var box = document.getElementById('methodInfo'); if (!box) return;
    var d = D.methods[S.method];
    box.innerHTML = d ? '<div class="alert alert-info" style="margin-top:0.75rem">' + (d.account ? '<strong>' + esc(d.account) + '</strong><br>' : '') + esc(d.instructions).replace(/\n/g, '<br>') + '</div>' : '';
  }

  function validateStep2() {
    var v = S.v;
    if (has('pages') && !(Number(v.pages) >= 1 && Number(v.pages) <= 5000)) return 'Pages must be between 1 and 5,000.';
    if (has('copies') && !(Number(v.copies) >= 1 && Number(v.copies) <= 10000)) return 'Copies must be between 1 and 10,000.';
    if (has('dimensions') && !(Number(v.width) >= 0.5 && Number(v.height) >= 0.5)) return 'Enter a width and height of at least 0.5 ft.';
    if (has('size') && v.size === 'Custom' && !String(v.notes).trim()) return 'Describe your custom size in the additional instructions.';
    return '';
  }

  function act(a) {
    S.error = '';
    if (a === 'back') { S.step = Math.max(1, S.step - 1); }
    else if (a === 'next') {
      if (S.step === 2) { var e = validateStep2(); if (e) { S.error = e; render(); return; } S.price = null; }
      S.step = Math.min(5, S.step + 1);
    }
    else if (a === 'usepages') { S.v.pages = detectedPages(); S.price = null; fetchPrice(); }
    else if (a === 'submit') { submit(); return; }
    render();
  }

  function payload() {
    var v = S.v, p = { service_id: S.service.id, notes: v.notes, payment_method: S.method,
      files: S.files.filter(function (f) { return f.status === 'done'; }).map(function (f) { return f.token; }) };
    ['size', 'paper', 'color', 'sides', 'orientation', 'pages', 'copies', 'binding', 'width', 'height'].forEach(function (k) { p[k] = v[k]; });
    return p;
  }
  function api(path, body) {
    return fetch(D.api + '/' + path, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': D.csrf }, body: JSON.stringify(body), credentials: 'same-origin' })
      .then(function (r) { return r.json().catch(function () { return { ok: false, error: 'Unexpected server response.' }; }); });
  }
  function animatePriceTotal(container) {
    var total = container && container.querySelector('.summary-total-amt');
    if (!total) return;
    total.classList.remove('price-updated');
    void total.offsetWidth;
    total.classList.add('price-updated');
    total.addEventListener('animationend', function () { total.classList.remove('price-updated'); }, { once: true });
  }
  function fetchPrice() {
    var request = ++S.priceRequest;
    S.priceLoading = true;
    var summary = root.querySelector('.wizard-summary-col');
    if (summary) summary.innerHTML = renderSummary();
    api('price.php', payload()).then(function (r) {
      if (request !== S.priceRequest) return;
      S.price = r.ok ? r : { error: (r.errors && r.errors.join(' ')) || r.error || 'Could not calculate the price.' };
      S.priceLoading = false;
      if (S.step === 4) {
        render();
        if (r.ok) animatePriceTotal(root);
      } else {
        var currentSummary = root.querySelector('.wizard-summary-col');
        if (currentSummary) {
          currentSummary.innerHTML = renderSummary();
          if (r.ok) animatePriceTotal(currentSummary);
        }
      }
    }).catch(function () {
      if (request !== S.priceRequest) return;
      S.price = { error: 'Network error. Check your connection.' };
      S.priceLoading = false;
      var currentSummary = root.querySelector('.wizard-summary-col');
      if (currentSummary) currentSummary.innerHTML = renderSummary();
      if (S.step === 4) render();
    });
  }
  function schedulePrice(delay) {
    if (!S.service) return;
    if (S.priceTimer) window.clearTimeout(S.priceTimer);
    S.priceRequest++;
    S.priceLoading = true;
    S.priceTimer = window.setTimeout(fetchPrice, delay === 0 ? 0 : 180);
  }
  function submit() {
    if (S.submitting) return;
    S.submitting = true; render();
    api('order_submit.php', payload()).then(function (r) {
      S.submitting = false;
      if (r.ok) { S.result = r; S.step = 6; S.files = []; if (window.showToast) window.showToast('success', 'Your order was submitted. Keep your reference number.'); }
      else { S.error = r.error || 'Could not submit the order.'; if (window.showToast) window.showToast('error', S.error); }
      render();
    }).catch(function () { S.submitting = false; S.error = 'Network error. Your order was not submitted.'; if (window.showToast) window.showToast('error', S.error); render(); });
  }

  // ------------------------------------------------------------ uploads
  function bindUpload() {
    var drop = document.getElementById('drop'), picker = document.getElementById('picker'), rep = document.getElementById('replacer'), replaceIdx = -1;
    if (!drop || !picker) return;
    drop.addEventListener('click', function () { picker.click(); });
    drop.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); picker.click(); } });
    ['dragover', 'dragenter'].forEach(function (ev) { drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.add('over'); }); });
    ['dragleave', 'drop'].forEach(function (ev) { drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.remove('over'); }); });
    drop.addEventListener('drop', function (e) { addFiles(e.dataTransfer.files); });
    picker.addEventListener('change', function () { addFiles(picker.files); picker.value = ''; });
    rep.addEventListener('change', function () { if (rep.files.length && replaceIdx > -1) { removeFile(replaceIdx, true); addFiles(rep.files); } rep.value = ''; });
    root.querySelectorAll('[data-remove]').forEach(function (b) { b.addEventListener('click', function () { removeFile(Number(b.getAttribute('data-remove'))); }); });
    root.querySelectorAll('[data-replace]').forEach(function (b) { b.addEventListener('click', function () { replaceIdx = Number(b.getAttribute('data-replace')); rep.click(); }); });
  }
  function addFiles(list) {
    Array.prototype.forEach.call(list, function (file) {
      var ext = file.name.split('.').pop().toLowerCase();
      var f = { name: file.name, size: file.size, status: 'uploading', progress: 0, preview: /^(jpe?g|png)$/.test(ext) ? URL.createObjectURL(file) : null };
      if (ALLOWED.indexOf(ext) < 0) { f.status = 'error'; f.error = 'Unsupported file type.'; }
      else if (file.size > D.maxMB * 1048576) { f.status = 'error'; f.error = 'File is larger than ' + D.maxMB + ' MB.'; }
      else if (file.size === 0) { f.status = 'error'; f.error = 'The file is empty.'; }
      else if (S.files.length >= 20) { f.status = 'error'; f.error = 'Maximum of 20 files.'; }
      S.files.push(f);
      if (f.status === 'uploading') upload(f, file);
    });
    S.price = null; render();
  }
  function upload(f, file) {
    var fd = new FormData(); fd.append('file', file); fd.append('_csrf', D.csrf);
    var x = new XMLHttpRequest(); x.open('POST', D.api + '/upload.php');
    x.upload.onprogress = function (e) { if (e.lengthComputable) { f.progress = Math.round(e.loaded / e.total * 100); updateRow(f); } };
    x.onload = function () {
      var r; try { r = JSON.parse(x.responseText); } catch (e) { r = { ok: false, error: 'The server rejected the file (it may be too large).' }; }
      if (r.ok) { f.status = 'done'; f.token = r.token; f.pages = r.pages; } else { f.status = 'error'; f.error = r.error || 'Upload failed.'; if (window.showToast) window.showToast('error', f.error); }
      if (S.step === 3) render();
    };
    x.onerror = function () { f.status = 'error'; f.error = 'Network error.'; if (window.showToast) window.showToast('error', f.error); if (S.step === 3) render(); };
    x.send(fd);
  }
  function updateRow(f) {
    var i = S.files.indexOf(f), rows = root.querySelectorAll('.file-row');
    if (rows[i]) { var b = rows[i].querySelector('.bar i'); if (b) b.style.width = f.progress + '%'; var t = rows[i].querySelector('.small'); if (t) t.textContent = 'Uploading ' + f.progress + '%'; }
  }
  function removeFile(i, silent) {
    var f = S.files[i]; if (!f) return;
    if (f.token) fetch(D.api + '/upload.php', { method: 'POST', headers: { 'X-CSRF-Token': D.csrf, 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'delete=' + f.token, credentials: 'same-origin' });
    if (f.preview) URL.revokeObjectURL(f.preview);
    S.files.splice(i, 1); S.price = null;
    if (!silent) render();
  }

  render();
})();
