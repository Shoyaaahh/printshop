(function () {
  document.body.classList.add('js-enabled');
  // Icons
  if (window.lucide) lucide.createIcons();

  // Theme toggle with smooth transition class
  var tt = document.getElementById('themeToggle');
  function paintTheme() {
    var dark = document.documentElement.dataset.theme === 'dark';
    if (tt) { tt.innerHTML = '<i data-lucide="' + (dark ? 'sun' : 'moon') + '"></i>'; tt.setAttribute('aria-pressed', dark ? 'true' : 'false'); if (window.lucide) lucide.createIcons(); }
  }
  if (tt) {
    paintTheme();
    tt.addEventListener('click', function () {
      document.documentElement.classList.add('theme-transitioning');
      var next = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
      document.documentElement.dataset.theme = next;
      try { localStorage.setItem('theme', next); } catch (e) {}
      paintTheme();
      document.dispatchEvent(new Event('themechange'));
      window.setTimeout(function () {
        document.documentElement.classList.remove('theme-transitioning');
      }, 300);
    });
  }

  // Mobile nav / sidebar
  var nt = document.getElementById('navToggle'), nl = document.getElementById('navLinks');
  function setMenu(button, panel, open) {
    if (!button || !panel) return;
    panel.classList.toggle('open', open);
    button.setAttribute('aria-expanded', open ? 'true' : 'false');
    button.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
  }
  if (nt && nl) {
    nt.addEventListener('click', function () { setMenu(nt, nl, !nl.classList.contains('open')); });
    nl.addEventListener('click', function (event) { if (event.target.closest('a')) setMenu(nt, nl, false); });
  }
  var st = document.getElementById('sidebarToggle'), sb = document.getElementById('sidebar');
  if (st && sb) st.addEventListener('click', function () { setMenu(st, sb, !sb.classList.contains('open')); });
  document.addEventListener('keydown', function (event) {
    if (event.key !== 'Escape') return;
    if (nt && nl && nl.classList.contains('open')) { setMenu(nt, nl, false); nt.focus(); }
    if (st && sb && sb.classList.contains('open')) { setMenu(st, sb, false); st.focus(); }
  });
  document.addEventListener('click', function (event) {
    if (nt && nl && nl.classList.contains('open') && !nl.contains(event.target) && !nt.contains(event.target)) setMenu(nt, nl, false);
    if (st && sb && sb.classList.contains('open') && !sb.contains(event.target) && !st.contains(event.target)) setMenu(st, sb, false);
  });

  // Confirmation dialogs for [data-confirm] buttons
  var dlg = document.createElement('dialog');
  dlg.innerHTML = '<form method="dialog"><h3 id="dlgTitle">Please confirm</h3><p id="dlgText"></p><div class="actions"><button value="cancel" class="btn btn-outline">Cancel</button><button value="ok" class="btn btn-primary" id="dlgOk">Confirm</button></div></form>';
  document.body.appendChild(dlg);
  document.addEventListener('click', function (ev) {
    var b = ev.target.closest('[data-confirm]');
    if (!b) return;
    ev.preventDefault();
    var form = b.form;
    if (form && !form.checkValidity()) { form.reportValidity(); return; }
    document.getElementById('dlgText').textContent = b.getAttribute('data-confirm');
    dlg.returnValue = '';
    dlg.onclose = function () {
      if (dlg.returnValue === 'ok' && form) {
        if (b.name) { var h = document.createElement('input'); h.type = 'hidden'; h.name = b.name; h.value = b.value; form.appendChild(h); }
        form.submit();
      }
    };
    dlg.showModal();
  });

  // Auto-submit selects marked data-autosubmit
  document.querySelectorAll('[data-autosubmit]').forEach(function (el) { el.addEventListener('change', function () { el.form.submit(); }); });

  // Reveal public sections once as they enter the viewport.
  var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var reveals = document.querySelectorAll('[data-reveal]');
  if (!reduced && 'IntersectionObserver' in window) {
    var revealObserver = new IntersectionObserver(function (entries, observer) {
      entries.forEach(function (entry) { if (entry.isIntersecting) { entry.target.classList.add('is-visible'); observer.unobserve(entry.target); } });
    }, { threshold: 0.12 });
    reveals.forEach(function (el) { revealObserver.observe(el); });
  } else reveals.forEach(function (el) { el.classList.add('is-visible'); });

  // Dashboard numbers count up when first seen.
  var counters = document.querySelectorAll('[data-count]');
  function countUp(el) {
    var target = Number(el.getAttribute('data-count')) || 0;
    if (reduced || target < 1) { el.textContent = String(target); return; }
    var start = performance.now(), duration = 650;
    function frame(now) { var p = Math.min(1, (now - start) / duration); el.textContent = String(Math.round(target * (1 - Math.pow(1 - p, 3)))); if (p < 1) requestAnimationFrame(frame); }
    requestAnimationFrame(frame);
  }
  if ('IntersectionObserver' in window) {
    var countObserver = new IntersectionObserver(function (entries, observer) { entries.forEach(function (entry) { if (entry.isIntersecting) { countUp(entry.target); observer.unobserve(entry.target); } }); }, { threshold: .5 });
    counters.forEach(function (el) { countObserver.observe(el); });
  } else counters.forEach(countUp);

  // Replace transient server flash banners with accessible toast messages.
  document.querySelectorAll('main > .alert[role="alert"]').forEach(function (alert) {
    var host = document.getElementById('toastHost');
    if (!host) { host = document.createElement('div'); host.id = 'toastHost'; host.className = 'toast-host'; host.setAttribute('aria-live', 'polite'); host.setAttribute('aria-atomic', 'false'); document.body.appendChild(host); }
    var toast = document.createElement('div'); toast.className = 'toast ' + alert.className.replace('alert', 'toast'); toast.setAttribute('role', 'status'); toast.textContent = alert.textContent;
    host.appendChild(toast); alert.remove();
    window.setTimeout(function () { toast.classList.add('toast-out'); window.setTimeout(function () { toast.remove(); }, 250); }, 5000);
  });
  window.showToast = function (type, message) {
    var host = document.getElementById('toastHost');
    if (!host) { host = document.createElement('div'); host.id = 'toastHost'; host.className = 'toast-host'; host.setAttribute('aria-live', 'polite'); host.setAttribute('aria-atomic', 'false'); document.body.appendChild(host); }
    var toast = document.createElement('div'); toast.className = 'toast toast-' + (type || 'info'); toast.setAttribute('role', type === 'error' ? 'alert' : 'status'); toast.textContent = message || ''; host.appendChild(toast);
    window.setTimeout(function () { toast.classList.add('toast-out'); window.setTimeout(function () { toast.remove(); }, 250); }, 5000);
  };

  // FAQ accordion with measured height for a smooth, accessible open/close.
  document.querySelectorAll('.faq details').forEach(function (details) {
    var summary = details.querySelector('summary'), content = Array.prototype.slice.call(details.children).filter(function (el) { return el.tagName !== 'SUMMARY'; });
    if (!summary || !content.length || reduced) return;
    var panel = document.createElement('div'); panel.className = 'faq-panel';
    content.forEach(function (el) { panel.appendChild(el); }); details.appendChild(panel);
    if (details.open) panel.style.maxHeight = panel.scrollHeight + 'px';
    summary.addEventListener('click', function (event) {
      event.preventDefault();
      if (details.open) { panel.style.maxHeight = panel.scrollHeight + 'px'; requestAnimationFrame(function () { panel.style.maxHeight = '0px'; }); panel.addEventListener('transitionend', function done() { details.open = false; panel.removeEventListener('transitionend', done); }, { once: true }); }
      else { details.open = true; panel.style.maxHeight = '0px'; requestAnimationFrame(function () { panel.style.maxHeight = panel.scrollHeight + 'px'; }); }
    });
  });
  document.querySelectorAll('.rate-table').forEach(function (details) {
    var summary = details.querySelector('summary'); if (!summary || reduced) return;
    var panel = document.createElement('div'); panel.className = 'rate-panel';
    Array.prototype.slice.call(details.children).filter(function (el) { return el !== summary; }).forEach(function (el) { panel.appendChild(el); });
    details.appendChild(panel); if (details.open) panel.style.maxHeight = panel.scrollHeight + 'px';
    summary.addEventListener('click', function (event) {
      event.preventDefault();
      if (details.open) { panel.style.maxHeight = panel.scrollHeight + 'px'; requestAnimationFrame(function () { panel.style.maxHeight = '0px'; }); panel.addEventListener('transitionend', function done() { details.open = false; panel.removeEventListener('transitionend', done); }, { once: true }); }
      else { details.open = true; panel.style.maxHeight = '0px'; requestAnimationFrame(function () { panel.style.maxHeight = panel.scrollHeight + 'px'; }); }
    });
  });

  // Small utility controls on long public pages.
  var topButton = document.createElement('button'); topButton.type = 'button'; topButton.className = 'back-to-top icon-btn'; topButton.setAttribute('aria-label', 'Back to top'); topButton.innerHTML = '<i data-lucide="arrow-up"></i>'; document.body.appendChild(topButton);
  document.querySelectorAll('.svc-card img').forEach(function (img) {
    var name = img.closest('.svc-card').querySelector('h3');
    if (!img.getAttribute('alt')) img.setAttribute('alt', (name ? name.textContent.trim() : 'Printing service') + ' sample');
    if (!img.hasAttribute('width')) img.setAttribute('width', '320');
    if (!img.hasAttribute('height')) img.setAttribute('height', '140');
  });
  if (document.body.classList.contains('public') && !document.querySelector('.floating-order')) {
    var orderFab = document.createElement('a'); orderFab.href = 'customer/order_new.php'; orderFab.className = 'floating-order btn btn-primary'; orderFab.innerHTML = '<i data-lucide="file-plus-2" aria-hidden="true"></i> Order Now'; document.body.appendChild(orderFab);
  }
  if (window.lucide) lucide.createIcons();
  var orderButton = document.querySelector('.floating-order');
  var visibleServiceCards = 0;
  if (orderButton && 'IntersectionObserver' in window) {
    var serviceCardObserver = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) { visibleServiceCards += entry.isIntersecting ? 1 : -1; });
      visibleServiceCards = Math.max(0, visibleServiceCards);
      scrollControls();
    }, { threshold: .1 });
    document.querySelectorAll('.svc-card').forEach(function (card) { serviceCardObserver.observe(card); });
  }
  function scrollControls() {
    topButton.classList.toggle('shown', window.scrollY > 400);
    var cardVisible = visibleServiceCards > 0 || Array.prototype.some.call(document.querySelectorAll('.svc-card'), function (card) {
      var rect = card.getBoundingClientRect(); return rect.bottom > 0 && rect.top < window.innerHeight;
    });
    if (orderButton) orderButton.classList.toggle('shown', window.scrollY > 500 && !cardVisible);
  }
  window.addEventListener('scroll', scrollControls, { passive: true }); scrollControls();
  topButton.addEventListener('click', function () { window.scrollTo({ top: 0, behavior: reduced ? 'auto' : 'smooth' }); });

  // Homepage estimator calls the same server-side price API as the order wizard.
  var estimator = document.getElementById('priceEstimator'), cfg = window.PRICE_ESTIMATOR;
  if (estimator && cfg) {
    var serviceSelect = document.getElementById('estimateService'), colorSelect = document.getElementById('estimateColor'), pagesInput = document.getElementById('estimatePages'), copiesInput = document.getElementById('estimateCopies'), result = document.getElementById('estimateResult'), timer;
    cfg.services.forEach(function (s) { var option = document.createElement('option'); option.value = s.id; option.textContent = s.name; serviceSelect.appendChild(option); });
    function estimate() {
      var svc = cfg.services.filter(function (s) { return String(s.id) === serviceSelect.value; })[0];
      if (!svc || !estimator.reportValidity()) return;
      result.innerHTML = '<span class="estimate-spinner" aria-hidden="true"></span><span class="muted">Calculating estimate…</span>'; result.setAttribute('aria-busy', 'true');
      var payload = { service_id: svc.id, pages: Number(pagesInput.value), copies: Number(copiesInput.value), color: svc.colors.indexOf(colorSelect.value) >= 0 ? colorSelect.value : svc.colors[0], sides: svc.sides[0], size: svc.sizes[0], paper: svc.papers[0], binding: svc.bindings[0] };
      fetch(cfg.api, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': cfg.csrf }, body: JSON.stringify(payload) })
        .then(function (response) { return response.json(); }).then(function (data) {
          result.removeAttribute('aria-busy');
          if (!data.ok) throw new Error((data.errors || []).join(' ') || data.error || 'Could not calculate this estimate.');
          var total = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(data.total);
          result.innerHTML = '<span class="muted">Estimated total</span><strong>' + total + '</strong>' + (data.needs_review ? '<small>Final price confirmed after file review.</small>' : '<small>Estimate only; the shop confirms your final price.</small>');
        }).catch(function (err) { result.removeAttribute('aria-busy'); result.innerHTML = '<span class="estimate-error">' + String(err.message || 'Network error. Please try again.') + '</span>'; });
    }
    estimator.addEventListener('input', function () { window.clearTimeout(timer); timer = window.setTimeout(estimate, 250); });
    estimator.addEventListener('change', function () { window.clearTimeout(timer); estimate(); });
    estimate();
  }

  // Service directory: category tabs, text search, and card details.
  var serviceCards = Array.prototype.slice.call(document.querySelectorAll('.service-category .svc-card'));
  if (serviceCards.length) {
    var filter = 'all', query = '', search = document.getElementById('serviceSearch'), count = document.getElementById('serviceCount'), empty = document.getElementById('serviceEmpty');
    var serviceDialog = document.createElement('dialog'); serviceDialog.className = 'service-dialog';
    serviceDialog.innerHTML = '<button type="button" class="icon-btn dialog-close" aria-label="Close service details"><i data-lucide="x"></i></button><div class="dialog-content"></div>';
    document.body.appendChild(serviceDialog); if (window.lucide) lucide.createIcons();
    function applyServiceFilters() {
      var visible = 0;
      document.querySelectorAll('.service-category').forEach(function (section) {
        var catMatch = filter === 'all' || section.getAttribute('data-category') === filter, shown = 0;
        section.querySelectorAll('.svc-card').forEach(function (card) {
          var textMatch = !query || card.textContent.toLowerCase().indexOf(query) !== -1;
          var show = catMatch && textMatch; card.hidden = !show; if (show) { shown++; visible++; }
        });
        section.hidden = shown === 0;
      });
      if (count) count.textContent = visible + (visible === 1 ? ' service' : ' services');
      if (empty) empty.hidden = visible !== 0;
    }
    document.querySelectorAll('[data-service-filter]').forEach(function (button) {
      button.addEventListener('click', function () {
        filter = button.getAttribute('data-service-filter');
        document.querySelectorAll('[data-service-filter]').forEach(function (other) { var active = other === button; other.classList.toggle('active', active); other.setAttribute('aria-pressed', active ? 'true' : 'false'); });
        applyServiceFilters();
      });
    });
    if (search) search.addEventListener('input', function () { query = search.value.trim().toLowerCase(); applyServiceFilters(); });
    serviceCards.forEach(function (card) {
      var orderLink = card.querySelector('a.btn'); if (!orderLink) return;
      orderLink.textContent = 'Add to order'; orderLink.classList.add('quick-order');
      var detailButton = document.createElement('button'); detailButton.type = 'button'; detailButton.className = 'btn btn-outline service-details'; detailButton.textContent = 'Details';
      var actions = document.createElement('div'); actions.className = 'service-actions';
      card.insertBefore(actions, orderLink); actions.appendChild(detailButton); actions.appendChild(orderLink);
      detailButton.addEventListener('click', function () {
        var box = serviceDialog.querySelector('.dialog-content'), title = card.querySelector('h3'), desc = card.querySelector('.grow'), price = card.querySelector('.price'), meta = card.querySelector('.small'), chips = card.querySelector('.chips'), image = card.querySelector('img, .svc-ph');
        box.replaceChildren();
        if (image) { var visual = image.cloneNode(true); visual.classList.add('dialog-visual'); box.appendChild(visual); }
        var h = document.createElement('h2'); h.textContent = title ? title.textContent : 'Service details'; box.appendChild(h);
        var p = document.createElement('p'); p.textContent = desc ? desc.textContent : ''; box.appendChild(p);
        if (chips) box.appendChild(chips.cloneNode(true));
        var info = document.createElement('p'); info.className = 'dialog-meta'; info.textContent = [price && price.textContent, meta && meta.textContent].filter(Boolean).join(' · '); box.appendChild(info);
        var link = document.createElement('a'); link.href = orderLink.href; link.className = 'btn btn-primary'; link.textContent = 'Add to order'; box.appendChild(link);
        serviceDialog.showModal();
      });
    });
    serviceDialog.querySelector('.dialog-close').addEventListener('click', function () { serviceDialog.close(); });
    serviceDialog.addEventListener('click', function (event) { if (event.target === serviceDialog) serviceDialog.close(); });
  }

  // 3D Interactive Product Showcase
  var showcaseStage = document.getElementById('heroShowcaseStage');
  var showcaseTabs = document.querySelectorAll('[data-showcase-tab]');
  var showcaseModels = document.querySelectorAll('[data-showcase-model]');
  var sceneEl = document.getElementById('product3dScene');
  var showcaseCta = document.getElementById('showcaseCta');

  var ctaLinks = {
    cards: 'customer/order_new.php?service=business-cards',
    flyer: 'customer/order_new.php?service=flyers',
    booklet: 'customer/order_new.php?service=spiral-binding',
    photo: 'customer/order_new.php?service=4r-photos'
  };

  if (showcaseTabs.length && showcaseModels.length) {
    showcaseTabs.forEach(function (tab) {
      tab.addEventListener('click', function () {
        var target = tab.getAttribute('data-showcase-tab');
        showcaseTabs.forEach(function (t) { t.classList.toggle('active', t === tab); });
        showcaseModels.forEach(function (m) {
          var isMatch = m.getAttribute('data-showcase-model') === target;
          m.classList.toggle('active', isMatch);
        });
        if (showcaseCta && ctaLinks[target]) {
          showcaseCta.href = ctaLinks[target];
        }
      });
    });

    if (showcaseStage && sceneEl && !reduced) {
      var isHovered = false;
      showcaseStage.addEventListener('mouseenter', function () { isHovered = true; });
      showcaseStage.addEventListener('mouseleave', function () {
        isHovered = false;
        sceneEl.style.transform = 'none';
      });
      showcaseStage.addEventListener('mousemove', function (e) {
        if (!isHovered) return;
        var rect = showcaseStage.getBoundingClientRect();
        var x = (e.clientX - rect.left) / rect.width - 0.5;
        var y = (e.clientY - rect.top) / rect.height - 0.5;
        var rotX = -y * 22;
        var rotY = x * 26;
        sceneEl.style.transform = 'rotateX(' + rotX.toFixed(1) + 'deg) rotateY(' + rotY.toFixed(1) + 'deg)';
      });
    }
  }
})();
