<?php
require __DIR__ . '/../app/bootstrap.php';
public_header('Services', 'services');
$cats = fetch_all('SELECT * FROM service_categories ORDER BY sort_order, id');
$by = [];
foreach (active_services() as $s) $by[$s['category_id']][] = $s;
?>
<div class="container">
  <div class="page-title" data-reveal>
    <span class="eyebrow">Comprehensive Printing Catalog</span>
    <h1>Printing services &amp; solutions</h1>
    <p>Explore our complete commercial printing catalogue. Select any service to review technical options, turnaround estimates, and live pricing before starting your order.</p>
  </div>

  <!-- Interactive Search & Category Filter Drawer -->
  <div class="service-tools card" data-reveal>
    <div class="field service-search">
      <label for="serviceSearch"><?= icon('search') ?> Search catalogue</label>
      <input id="serviceSearch" type="search" placeholder="Search by name, paper type, binding, photos…" autocomplete="off">
    </div>
    <div class="service-filters" role="group" aria-label="Filter services by category">
      <button type="button" class="btn btn-sm btn-outline active" data-service-filter="all" aria-pressed="true">All services</button>
      <?php foreach ($cats as $c): if (empty($by[$c['id']])) continue; ?>
        <button type="button" class="btn btn-sm btn-outline" data-service-filter="<?= e($c['slug']) ?>" aria-pressed="false">
          <?= icon(category_icon($c['slug'])) ?> <?= e($c['name']) ?>
        </button>
      <?php endforeach; ?>
    </div>
    <p class="small muted" id="serviceCount" aria-live="polite"><?= count(active_services()) ?> services available</p>
  </div>

  <div class="alert alert-info service-empty" id="serviceEmpty" hidden>
    <?= icon('search-x') ?> No services match your query. Try a different search term or select "All services" above.
  </div>

  <!-- Interactive Category Catalog Sections -->
  <?php foreach ($cats as $c): if (empty($by[$c['id']])) continue; ?>
    <section class="section service-category" style="padding:1.5rem 0" id="<?= e($c['slug']) ?>" data-category="<?= e($c['slug']) ?>" data-reveal>
      <div class="section-head category-heading">
        <span class="category-icon"><?= icon(category_icon($c['slug'])) ?></span>
        <div>
          <h2><?= e($c['name']) ?></h2>
          <p><?= e($c['description']) ?></p>
        </div>
      </div>
      <div class="grid g3">
        <?php foreach ($by[$c['id']] as $s) {
          $card = service_card($s);
          $card = str_replace('alt=""', 'alt="' . e($s['name']) . ' sample"', $card);
          $card = str_replace('loading="lazy"', 'loading="lazy" width="320" height="140"', $card);
          echo $card;
        } ?>
      </div>
    </section>
  <?php endforeach; ?>

  <!-- Material & Paper Swatch Guide -->
  <section class="section alt" style="margin-top:2.5rem;border-radius:var(--radius-card);padding:2.5rem 2rem" data-reveal>
    <div class="section-head">
      <span class="eyebrow">Tactile Materials Guide</span>
      <h2>Supported paper stocks &amp; finishes</h2>
      <p>Understanding paper weights and finishes helps achieve the exact finish your job demands.</p>
    </div>
    <div class="grid g3">
      <div class="card" style="box-shadow:var(--shadow-xs)">
        <span class="badge badge-muted" style="width:fit-content;margin-bottom:0.5rem">80gsm · Uncoated</span>
        <h3>Regular Bond</h3>
        <p class="muted small">High-opacity white paper for everyday black &amp; white or color document printing, lecture notes, legal briefs, and photocopying.</p>
      </div>
      <div class="card" style="box-shadow:var(--shadow-xs)">
        <span class="badge badge-info" style="width:fit-content;margin-bottom:0.5rem">120gsm · Low Glare</span>
        <h3>Matte Coated</h3>
        <p class="muted small">Velvety, reflection-free surface ideal for formal presentations, thesis books, diagrams, and corporate proposals.</p>
      </div>
      <div class="card" style="box-shadow:var(--shadow-xs)">
        <span class="badge badge-accent" style="width:fit-content;margin-bottom:0.5rem">150gsm · High Sheen</span>
        <h3>Glossy Finish</h3>
        <p class="muted small">Vivid, high-contrast surface that accentuates bright color images, marketing flyers, event brochures, and folded menus.</p>
      </div>
      <div class="card" style="box-shadow:var(--shadow-xs)">
        <span class="badge badge-warn" style="width:fit-content;margin-bottom:0.5rem">350gsm · Heavy Stock</span>
        <h3>Premium Cardstock</h3>
        <p class="muted small">Rigid, heavyweight board offering substantial hand-feel. Perfect for business cards, postcards, table tents, and certificate cards.</p>
      </div>
      <div class="card" style="box-shadow:var(--shadow-xs)">
        <span class="badge badge-ok" style="width:fit-content;margin-bottom:0.5rem">260gsm · Archival</span>
        <h3>Photo Paper</h3>
        <p class="muted small">True continuous-tone photographic paper designed for rich dye/pigment ink absorption in passport photos, 2x2 ID sets, and 4R prints.</p>
      </div>
      <div class="card" style="box-shadow:var(--shadow-xs)">
        <span class="badge badge-accent" style="width:fit-content;margin-bottom:0.5rem">Post-Print Finishing</span>
        <h3>Specialty Binding</h3>
        <p class="muted small">Plastic coil spiral binding (lays completely flat), soft spine wrap binding, or hardbound foil-stamped bookbinding for university theses.</p>
      </div>
    </div>
  </section>
</div>
<?php public_footer();