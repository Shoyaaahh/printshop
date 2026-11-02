<?php
require __DIR__ . '/../app/bootstrap.php';
public_header('Home', 'home');
$popular = active_services('AND s.is_popular = 1');
$estimateServices = [];
foreach (active_services() as $s) {
  $options = service_options($s);
  if (in_array('pages', $options['fields'], true) && in_array('copies', $options['fields'], true) && in_array('color', $options['fields'], true)) {
    $estimateServices[] = ['id' => (int)$s['id'], 'name' => $s['name'], 'fields' => $options['fields'], 'sizes' => $options['sizes'], 'papers' => $options['papers'], 'colors' => $options['colors'], 'sides' => $options['sides'], 'bindings' => $options['bindings']];
  }
}
$open = store_open_now();
?>
<section class="hero" data-reveal><div class="container hero-grid">
  <div>
    <span class="hero-kicker"><?= icon('map-pin') ?> Architectural Precision · Store Pickup Only</span>
    <h1>Print online. Pick up at the shop.</h1>
    <p><?= e(setting('business_name')) ?> transforms your digital files into precision-printed business cards, brochures, documents, and marketing collateral. Configure options online, preview accurate estimates, and pick up at the shop counter without waiting in line.</p>
    <div class="hero-actions">
      <a class="btn btn-primary btn-lg" href="<?= e(url('customer/order_new.php')) ?>"><?= icon('file-plus-2') ?> Start an order</a>
      <a class="btn btn-outline btn-lg" href="<?= e(url('services.php')) ?>"><?= icon('layers') ?> Explore services</a>
    </div>
    <ul class="hero-points">
      <li><?= icon('check') ?> Real-time estimates calculated directly from official rate cards</li>
      <li><?= icon('check') ?> Meticulous pre-flight file check by print specialists before printing</li>
      <li><?= icon('check') ?> Transparent milestone tracking from upload to pickup confirmation</li>
    </ul>
    <div class="hero-stats-ribbon">
      <div class="hero-stat-item">
        <span class="hero-stat-num">Same-Day</span>
        <span class="hero-stat-label">Fast turnaround ready</span>
      </div>
      <div class="hero-stat-item">
        <span class="hero-stat-num">100% In-Store</span>
        <span class="hero-stat-label">Direct counter pickup</span>
      </div>
      <div class="hero-stat-item">
        <span class="hero-stat-num">Real-Time</span>
        <span class="hero-stat-label">Verified price estimates</span>
      </div>
    </div>
  </div>

  <div class="hero-showcase">
    <div class="showcase-tabs" role="tablist" aria-label="Product 3D previews">
      <button type="button" class="showcase-tab active" data-showcase-tab="cards" role="tab" aria-selected="true"><?= icon('credit-card') ?> Business Cards</button>
      <button type="button" class="showcase-tab" data-showcase-tab="flyer" role="tab" aria-selected="false"><?= icon('file-text') ?> Flyers &amp; Posters</button>
      <button type="button" class="showcase-tab" data-showcase-tab="booklet" role="tab" aria-selected="false"><?= icon('book-open') ?> Spiral Binding</button>
      <button type="button" class="showcase-tab" data-showcase-tab="photo" role="tab" aria-selected="false"><?= icon('image') ?> Photo Prints</button>
    </div>

    <div class="showcase-stage" id="heroShowcaseStage" aria-label="Interactive 3D product preview">
      <div class="product-3d-scene" id="product3dScene">
        <!-- Business Cards Stack -->
        <div class="product-model scene-cards active" data-showcase-model="cards">
          <div class="card-layer card-layer-1"></div>
          <div class="card-layer card-layer-2"></div>
          <div class="card-layer card-layer-top">
            <div class="card-gold-logo"><?= icon('sparkles') ?> <?= e(setting('business_name')) ?></div>
            <div>
              <div class="card-name">Premium Heavyweight</div>
              <div class="card-sub">350gsm Cardstock · Matte Finish</div>
            </div>
            <div class="card-foil-edge"></div>
          </div>
        </div>

        <!-- Marketing Flyer -->
        <div class="product-model scene-flyer" data-showcase-model="flyer">
          <div class="flyer-sheet">
            <span class="flyer-badge">Full Color · High DPI</span>
            <div class="flyer-headline">Creative Agency Showcase</div>
            <div class="flyer-photo"><?= icon('printer') ?></div>
            <div class="small muted">Glossy 150gsm · Crisp vibrant reproduction</div>
          </div>
        </div>

        <!-- Spiral Booklet -->
        <div class="product-model scene-booklet" data-showcase-model="booklet">
          <div class="booklet-spine">
            <span class="spine-ring"></span><span class="spine-ring"></span><span class="spine-ring"></span>
            <span class="spine-ring"></span><span class="spine-ring"></span><span class="spine-ring"></span>
          </div>
          <div class="booklet-cover">
            <span class="badge badge-accent" style="width:fit-content">Document Services</span>
            <div class="booklet-title">Annual Report &amp; Portfolio</div>
            <div class="small muted">Durable spiral coil · Heavy clear cover</div>
          </div>
        </div>

        <!-- Photo Print -->
        <div class="product-model scene-photo" data-showcase-model="photo">
          <div class="photo-frame">
            <div class="photo-surface"><?= icon('image') ?></div>
            <div class="photo-caption">4R Premium Photo Paper · Ultra Gloss</div>
          </div>
        </div>
      </div>

      <div class="showcase-footer">
        <span class="showcase-badge"><?= icon('sparkles') ?> Interactive 3D Model (Move cursor to tilt)</span>
        <a id="showcaseCta" class="showcase-cta" href="<?= e(url('customer/order_new.php?service=business-cards')) ?>">Order this service <?= icon('arrow-right') ?></a>
      </div>
    </div>
  </div>
</div></section>

<section class="section" data-reveal><div class="container">
  <div class="section-head">
    <span class="eyebrow">High-Demand Solutions</span>
    <h2>Popular printing services</h2>
    <p>Explore the services customers order most frequently. Each listing reflects our current shop rate card and standard preparation times.</p>
  </div>
  <div class="grid g4">
    <?php foreach (array_slice($popular, 0, 8) as $s) {
      $card = service_card($s);
      $card = str_replace('alt=""', 'alt="' . e($s['name']) . ' sample"', $card);
      $card = str_replace('loading="lazy"', 'loading="lazy" width="320" height="140"', $card);
      echo $card;
    } ?>
  </div>
  <p style="margin-top:1.5rem"><a class="btn btn-outline" href="<?= e(url('services.php')) ?>"><?= icon('grid') ?> See all services</a></p>
</div></section>

<section class="section alt" id="quick-estimator" data-reveal><div class="container estimator-layout">
  <div class="section-head">
    <span class="eyebrow">Real-Time Pricing Engine</span>
    <h2>Get a quick estimate</h2>
    <p>Choose a print service, enter your page and copy counts, and our live rate card engine will compute an accurate estimate instantly.</p>
    <?php if (!current_user() || current_user()['role'] !== 'customer'): ?>
      <p class="small muted">Sign in to calculate a live estimate. This uses the same secure pricing service as your order.</p>
    <?php endif; ?>
  </div>
  <?php if (!current_user() || current_user()['role'] !== 'customer'): ?>
    <div class="card estimator-login">
      <p><?= icon('calculator') ?> A live estimate is available to registered customers.</p>
      <div style="display:flex;gap:0.75rem;margin-top:1rem">
        <a class="btn btn-primary" href="<?= e(url('login.php')) ?>">Log in to estimate</a>
        <a class="btn btn-outline" href="<?= e(url('register.php')) ?>">Create account</a>
      </div>
    </div>
  <?php elseif ($estimateServices): ?>
    <form class="card estimator" id="priceEstimator" data-api="<?= e(url('api/price.php')) ?>" data-csrf="<?= e(csrf_token()) ?>">
      <div class="form-grid">
        <div class="field"><label for="estimateService">Service</label><select id="estimateService" required></select></div>
        <div class="field"><label for="estimateColor">Print color</label><select id="estimateColor"><option value="bw">Black &amp; white</option><option value="color">Color</option></select></div>
        <div class="field"><label for="estimatePages">Pages</label><input id="estimatePages" type="number" min="1" max="5000" value="1" required></div>
        <div class="field"><label for="estimateCopies">Copies</label><input id="estimateCopies" type="number" min="1" max="10000" value="1" required></div>
      </div>
      <div class="estimate-result" aria-live="polite" id="estimateResult"><span class="muted">Enter your print details to see an estimate.</span></div>
      <noscript><p class="hint">Enable JavaScript to calculate a live estimate.</p></noscript>
    </form>
    <script>window.PRICE_ESTIMATOR = <?= json_encode(['services' => $estimateServices, 'colors' => COLORS, 'sides' => SIDES, 'api' => url('api/price.php'), 'csrf' => csrf_token()], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;</script>
  <?php else: ?>
    <div class="card"><p class="muted">Live estimates are available for services that use page, copy, and color rates.</p><a class="btn btn-primary" href="<?= e(url('customer/order_new.php')) ?>">Start an order</a></div>
  <?php endif; ?>
</div></section>

<section class="section alt" data-reveal><div class="container">
  <div class="section-head">
    <span class="eyebrow">Structured Workflow</span>
    <h2>How it works</h2>
    <p>From digital file upload to counter collection in five simple steps.</p>
  </div>
  <ol class="how">
    <li><span class="how-icon"><?= icon('list-checks') ?></span><strong>1. Choose a service</strong><span class="muted">Select your exact paper stock, dimensions, color mode, and binding requirements.</span></li>
    <li><span class="how-icon"><?= icon('cloud-upload') ?></span><strong>2. Upload files securely</strong><span class="muted">Upload PDF, Word, PowerPoint, or high-res images. Your files are stored privately.</span></li>
    <li><span class="how-icon"><?= icon('calculator') ?></span><strong>3. Verify the estimate</strong><span class="muted">Review the itemized price breakdown and receive your unique reference code.</span></li>
    <li><span class="how-icon"><?= icon('badge-check') ?></span><strong>4. Review &amp; payment</strong><span class="muted">Staff verify file specifications, confirm final pricing, and verify payment proof.</span></li>
    <li><span class="how-icon"><?= icon('package-check') ?></span><strong>5. Pick up in store</strong><span class="muted">Receive an automated readiness alert, then present your reference at our counter.</span></li>
  </ol>
</div></section>

<section class="section" data-reveal><div class="container">
  <div class="section-head">
    <span class="eyebrow">Quality &amp; Security Standards</span>
    <h2>Print with absolute confidence</h2>
    <p>Professional equipment, rigorous quality control, and respectful data privacy.</p>
  </div>
  <div class="grid g3">
    <div class="card benefit-card">
      <span class="benefit-icon"><?= icon('badge-check') ?></span>
      <h3>Pre-Flight File Inspection</h3>
      <p class="muted">Every file is inspected by trained print technicians before running production. Any margin or resolution issues are flagged for your approval first.</p>
    </div>
    <div class="card benefit-card">
      <span class="benefit-icon"><?= icon('shield-check') ?></span>
      <h3>Confidential File Handling</h3>
      <p class="muted">Your files are stored outside the public directory with cryptographic authorization. Only you and authorized shop staff can access your documents.</p>
    </div>
    <div class="card benefit-card">
      <span class="benefit-icon"><?= icon('clock') ?></span>
      <h3>Zero-Wait Counter Pickup</h3>
      <p class="muted">Skip the walk-in queue. Send your job ahead of time from your desk or phone, track progress live, and pick up your packaged job in seconds.</p>
    </div>
  </div>
</div></section>

<section class="section alt" data-reveal><div class="container location-grid">
  <div class="hours-card">
    <span class="eyebrow">Store Schedule</span>
    <h2>Opening hours</h2>
    <p><?= $open ? '<span class="badge badge-ok">● Open for pickup</span>' : '<span class="badge badge-muted">○ Currently closed</span>' ?></p>
    <?= hours_table() ?>
  </div>
  <div class="card location-card">
    <span class="location-icon"><?= icon('map-pin') ?></span>
    <span class="eyebrow">Walk-In Collection</span>
    <h2>Store pickup counter</h2>
    <p class="location-address"><?= nl2br(e(setting('address'))) ?></p>
    <p class="muted small">Pickup only. We do not offer delivery or shipping.</p>
    <div class="map-placeholder" aria-label="Store pickup location">
      <span><?= icon('map-pin') ?></span>
      <strong><?= e(setting('business_name')) ?> Counter</strong>
      <small>Show your reference code at pickup</small>
    </div>
  </div>
  <div>
    <h2>Need assistance?</h2>
    <p class="muted">Have questions regarding specialty paper or rush orders? Get in touch with our counter team.</p>
    <p><?= icon('phone') ?> <strong><?= e(setting('phone')) ?></strong><br><?= icon('mail') ?> <?= e(setting('email')) ?></p>
    <a class="btn btn-outline" href="<?= e(url('contact.php')) ?>"><?= icon('mail') ?> Send a message</a>
  </div>
</div></section>

<section class="section" data-reveal><div class="container faq" style="max-width:840px">
  <div class="section-head">
    <span class="eyebrow">Frequently Asked Questions</span>
    <h2>Clear answers before you order</h2>
    <p>Everything you need to know about turnaround times, payment options, and store pickup policies.</p>
  </div>
  <details><summary>Is the online price final?</summary><p>The online total is an accurate estimate based on standard specifications. If your file requires special adjustments, custom trimming, or high ink density, our staff will review the file and confirm any price change with you before printing.</p></details>
  <details><summary>Do you deliver to home or office?</summary><p>No. We operate as an in-store pickup printing workshop. All finished print jobs are collected at our physical store counter once you receive the "Ready for Pickup" status notification.</p></details>
  <details><summary>What payment methods do you accept?</summary><p>We accept GCash, Maya, Bank Transfer (with receipt upload for quick verification), as well as cash directly at the counter upon pickup.</p></details>
  <details><summary>Which file formats are supported?</summary><p>We accept PDF, DOC, DOCX, PPT, PPTX, JPG, JPEG, and PNG files up to <?= (int)(MAX_UPLOAD / 1048576) ?> MB per file. For best results, PDF is strongly recommended.</p></details>
  <details><summary>How do I track my active order?</summary><p>You can use the <a href="<?= e(url('track.php')) ?>">Track Order</a> page anytime with your order reference number (e.g. PS-250101-AB12CD) or log into your customer portal to view full details.</p></details>
</div></section>

<section class="home-cta" data-reveal><div class="container home-cta-inner">
  <div>
    <span class="eyebrow" style="color:#bfdbfe">Ready to print?</span>
    <h2>Start your next print order online now.</h2>
    <p>Choose your service, upload your document, and pick it up fresh off the press.</p>
  </div>
  <a class="btn btn-primary btn-lg" href="<?= e(url('customer/order_new.php')) ?>"><?= icon('file-plus-2') ?> Start an order</a>
</div></section>

<a class="floating-order btn btn-primary" href="<?= e(url('customer/order_new.php')) ?>"><?= icon('file-plus-2') ?> Order Now</a>

<?php public_footer();