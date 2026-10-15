(function($){
  function esc(s){ return String(s||'').replace(/[&<>"']/g, function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];}); }
  function t(key, fallback){
    return (window.HET_SEO && HET_SEO.i18n && HET_SEO.i18n[key]) ? HET_SEO.i18n[key] : fallback;
  }

  // Accordion (Centrum) state persistence
  function accKey(){
    const u = new URL(window.location.href);
    const p = u.searchParams.get('page') || 'wp';
    return 'hetseo_acc_' + p;
  }

  function accId($item){
    const explicit = $item.attr('data-acc-id');
    if(explicit) return String(explicit);
    // Fallback: stable index within page
    const idx = $('[data-acc]').index($item);
    return 'idx_' + String(idx);
  }

  function readAcc(){
    try{
      const raw = window.localStorage.getItem(accKey());
      if(!raw) return {};
      const obj = JSON.parse(raw);
      return (obj && typeof obj === 'object') ? obj : {};
    }catch(e){ return {}; }
  }

  function writeAcc(map){
    try{ window.localStorage.setItem(accKey(), JSON.stringify(map||{})); }catch(e){}
  }

  function toggleAcc($item){
    const id = accId($item);
    const map = readAcc();
    const willOpen = !$item.hasClass('is-open');
    $item.toggleClass('is-open', willOpen);
    map[id] = willOpen ? 1 : 0;
    writeAcc(map);
  }

  $(document).on('click','[data-acc] .het-acc__hdr', function(){
    toggleAcc($(this).closest('[data-acc]'));
  });

  // Apply persisted state
  $(function(){
    const map = readAcc();
    $('[data-acc]').each(function(){
      const $it = $(this);
      const id = accId($it);
      if(map[id]) $it.addClass('is-open');
    });
  });

  async function api(path, method, body){
    const url = (window.HET_SEO && HET_SEO.rest ? HET_SEO.rest : '') + path;
    const opts = {
      method: method,
      headers: {
        'Content-Type':'application/json',
        'X-WP-Nonce': window.HET_SEO ? HET_SEO.nonce : ''
      }
    };
    if(body) opts.body = JSON.stringify(body);
    const r = await fetch(url, opts);
    const t = await r.text();
    try{ return JSON.parse(t); }catch(e){ return {ok:false,error:'Non-JSON response',raw:t}; }
  }

  function renderQueue(resp){
    if(!resp || !resp.ok){
      $('#hetQueueTable').html('<div class="notice notice-error"><p>'+esc(resp && resp.error ? resp.error : t('error','Błąd'))+'</p></div>');
      return;
    }
    const lic = resp.license||{};
    $('#hetAiUsage').html(
      '<div class="het-metric"><span>'+t('mode','Tryb')+'</span><strong>Open Source</strong></div>'+
      '<div class="het-metric"><span>'+t('access','Dostęp')+'</span><strong>'+(lic.active ? t('unlocked','Odblokowany ✅') : t('inactive','Nieaktywny ❌'))+'</strong></div>'+
      (lic.message ? '<div class="het-muted" style="margin-top:6px">'+esc(lic.message)+'</div>' : '')
    );

    const items = resp.items||[];
    let html = '<table class="widefat fixed striped">'+
      '<thead><tr><th>ID</th><th>'+t('post','Post')+'</th><th>'+t('type','Typ')+'</th><th>P</th><th>'+t('status','Status')+'</th><th>Dry</th><th>'+t('actions','Akcje')+'</th><th>'+t('error','Błąd')+'</th></tr></thead><tbody>';
    if(!items.length){
      html += '<tr><td colspan="8">'+t('no_items','Brak pozycji.')+'</td></tr>';
    } else {
      items.forEach(function(it){
        html += '<tr>'+
          '<td>'+esc(it.id)+'</td>'+
          '<td>'+(it.url ? '<a href="'+esc(it.url)+'" target="_blank" rel="noopener">'+esc(it.post_id)+'</a>' : esc(it.post_id))+'</td>'+
          '<td>'+esc(it.fix_type)+'</td>'+
          '<td>'+esc(it.priority)+'</td>'+
          '<td>'+esc(it.status)+'</td>'+
          '<td>'+ (parseInt(it.dry_run,10) ? '1' : '0') +'</td>'+
          '<td>'+
            (it.status==='done' && !parseInt(it.dry_run,10) ? '<button class="button" data-het-rollback="'+esc(it.id)+'">Rollback</button>' : '')+
          '</td>'+
          '<td class="het-small">'+esc(it.last_error||'')+'</td>'+
        '</tr>';
      });
    }
    html += '</tbody></table>';
    $('#hetQueueTable').html(html);
  }

  async function refreshQueue(){
    const resp = await api('/ai/queue','GET');
    renderQueue(resp);
  }

  $(document).on('click','[data-het-refresh]', function(){
    refreshQueue();
  });

  $(document).on('click','[data-het-scan]', async function(){
    const dry = $(this).data('dry') ? true : false;
    const includeCtr = $('#hetIncludeCTR').is(':checked');
    const resp = await api('/ai/scan','POST',{dry_run: dry, limit: 200, include_ctr: includeCtr});
    if(!resp.ok){
      alert(resp.error || t('error','Błąd'));
    }
    await refreshQueue();
  });

  $(document).on('click','[data-het-run]', async function(){
    const resp = await api('/ai/run','POST',{max: 10});
    if(!resp.ok){ alert(resp.error||t('error','Błąd')); }
    await refreshQueue();
  });

  $(document).on('click','[data-het-rollback]', async function(){
    const id = $(this).data('het-rollback');
    if(!id) return;
    if(!confirm(t('rollback_confirm_prefix','Cofnąć zmiany dla zadania #')+id+'?')) return;
    const resp = await api('/ai/rollback/'+id,'POST',{});
    if(!resp.ok){ alert(resp.error||t('error','Błąd')); }
    await refreshQueue();
  });

  $(function(){
    if($('#hetQueueTable').length){
      refreshQueue();
    }

    // Meta audit bulk: select all
    $(document).on('change', '#hetMetaSelectAll', function(){
      const on = $(this).is(':checked');
      $('input[name="post_ids[]"]').prop('checked', on);
    });
  });


  // PSI mini chart (no deps)
  function drawLineChart(canvas, points){
    if(!canvas) return;
    const ctx = canvas.getContext('2d');
    const w = canvas.width, h = canvas.height;
    ctx.clearRect(0,0,w,h);
    ctx.globalAlpha = 1;
    ctx.lineWidth = 2;

    const vals = points.map(p=>p.v).filter(v=>typeof v === 'number' && !isNaN(v));
    const min = vals.length ? Math.min.apply(null, vals) : 0;
    const max = vals.length ? Math.max.apply(null, vals) : 100;
    const pad = 18;

    // grid
    ctx.globalAlpha = 0.25;
    ctx.beginPath();
    for(let i=0;i<4;i++){
      const y = pad + (h - pad*2) * (i/3);
      ctx.moveTo(pad, y); ctx.lineTo(w-pad, y);
    }
    ctx.strokeStyle = 'currentColor';
    ctx.stroke();

    // line
    ctx.globalAlpha = 1;
    ctx.beginPath();
    const den = (max===min) ? 1 : (max-min);
    points.forEach((p, i)=>{
      const x = pad + (w - pad*2) * (points.length===1 ? 0 : (i/(points.length-1)));
      let y = h - pad;
      if(typeof p.v === 'number'){
        y = h - pad - (h - pad*2) * ((p.v - min)/den);
      }
      if(i===0) ctx.moveTo(x,y); else ctx.lineTo(x,y);
    });
    ctx.strokeStyle = '#b26bff';
    ctx.stroke();

    // last dot
    const last = points[points.length-1];
    if(last && typeof last.v === 'number'){
      const i = points.length-1;
      const x = pad + (w - pad*2) * (points.length===1 ? 0 : (i/(points.length-1)));
      const y = h - pad - (h - pad*2) * ((last.v - min)/den);
      ctx.fillStyle = '#7a00df';
      ctx.beginPath(); ctx.arc(x,y,3.2,0,Math.PI*2); ctx.fill();
    }
  }

  function initPsiChart(){
    if(!window.HET_SEO_PSI_SERIES) return;
    const sel = document.getElementById('hetPsiSeries');
    const canv = document.getElementById('hetPsiChart');
    if(!sel || !canv) return;

    function rerender(){
      const key = sel.value;
      const series = window.HET_SEO_PSI_SERIES[key] || [];
      const points = series.map(r=>({t:r.t, v:r.perf})).slice(-30);
      drawLineChart(canv, points);
      const last = points.length ? points[points.length-1].v : null;
      const el = document.getElementById('hetPsiLastValue');
      if(el) el.textContent = (typeof last === 'number') ? (last + '/100') : '—';
    }
    sel.addEventListener('change', rerender);
    rerender();
  }

  $(document).ready(function(){
    initPsiChart();
    initTabs();
    initInsights();
  });

  // Simple tabs
  function initTabs(){
    $(document).on('click', '[data-het-tabs] [data-het-tab]', function(){
      const id = $(this).data('het-tab');
      const $card = $(this).closest('.het-seo-card');
      $card.find('.het-tab').removeClass('is-active');
      $(this).addClass('is-active');
      $card.find('.het-pane').removeClass('is-active');
      $card.find('.het-pane[data-het-pane="'+id+'"]').addClass('is-active');
    });
  }

  // MEGA PACK C: insights UI
  async function apiAbs(url, method, body){
    const opts = {
      method: method,
      headers: {
        'Content-Type':'application/json',
        'X-WP-Nonce': window.HET_SEO ? HET_SEO.nonce : ''
      }
    };
    if(body) opts.body = JSON.stringify(body);
    const r = await fetch(url, opts);
    const t = await r.text();
    try{ return JSON.parse(t); }catch(e){ return {ok:false,error:'Non-JSON response',raw:t}; }
  }

  function renderConflicts(resp){
    try{
      if(resp && resp.ok && Array.isArray(resp.items)){
        const n = resp.items.length;
        const el = document.getElementById('hetKpiConflicts');
        if(el) el.textContent = String(n);
      }
    }catch(e){}

    const $wrap = $('#hetConflictsTable');
    if(!$wrap.length) return;
    if(!resp || !resp.ok){
      $wrap.html('<div class="notice notice-error"><p>'+esc(resp && resp.error ? resp.error : t('error','Błąd'))+'</p></div>');
      return;
    }
    const items = resp.items || [];
    let html = '<table class="widefat fixed striped">'+
      '<thead><tr><th>'+t('topic','Temat')+'</th><th>'+t('items','Elementy')+'</th><th>'+t('action','Akcja')+'</th></tr></thead><tbody>';
    if(!items.length){
      html += '<tr><td colspan="3">'+t('no_conflicts','Brak konfliktów (albo nie było skanu).')+'</td></tr>';
    } else {
      items.forEach(function(c){
        const rows = c.items || [];
        const list = rows.map(function(r){
          const type = r.type || (r.taxonomy ? 'term' : 'post');
          const id = r.id || r.post_id || r.term_id || '';
          const badge = (type==='term') ? ('<span class="het-badge het-badge--term">TERM</span>') : ('<span class="het-badge het-badge--post">POST</span>');
          const cid = (type==='term') ? (-1 * Math.abs(parseInt(id||'0',10))) : parseInt(id||'0',10);
          const wbadge = (cid && c.winner_id && (cid===parseInt(c.winner_id,10))) ? '<span class="het-badge het-badge--winner">WINNER</span>' : '<span class="het-badge het-badge--loser">LOSER</span>';
          const extra = (type==='term' && r.taxonomy) ? (' <span class="het-muted">('+esc(r.taxonomy)+')</span>') : '';
          const url = r.url || '';
          const title = r.title || url || ('#'+id);
          const act = (cid && c.winner_id && (cid!==parseInt(c.winner_id,10))) ? (' <button type="button" class="button button-small" data-het-merge-mark="1" data-het-topic="'+esc(c.topic_hash)+'" data-het-winner="'+esc(c.winner_id)+'" data-het-loser="'+esc(cid)+'">'+t('mark_merge','Oznacz do scalenia')+'</button>') : '';
          return '<div class="het-small">'+wbadge+' '+badge+' <a href="'+esc(url)+'" target="_blank" rel="noopener">'+esc(title)+'</a> <span class="het-muted">(#'+esc(id)+')</span>'+extra+act+'</div>';
        }).join('');
        html += '<tr>'+
          '<td><strong>'+esc(c.topic_hash)+'</strong><div class="het-muted">'+esc(c.kind)+' • score '+esc(c.score)+'</div></td>'+
          '<td>'+list+'</td>'+
          '<td><button class="button" data-het-insights-open="suggestions">'+t('view_suggestions','Zobacz sugestie')+'</button></td>'+
        '</tr>';
      });
    }
    html += '</tbody></table>';
    $wrap.html(html);
  }

  function renderSuggestions(resp){
    try{
      if(resp && resp.ok && Array.isArray(resp.items)){
        const pending = resp.items.filter(s=> (s.status==='pending' || s.status==='approval')).length;
        const el = document.getElementById('hetKpiSugPending');
        if(el) el.textContent = String(pending);
      }
    }catch(e){}

    const $wrap = $('#hetSuggestionsTable');
    if(!$wrap.length) return;
    if(!resp || !resp.ok){
      $wrap.html('<div class="notice notice-error"><p>'+esc(resp && resp.error ? resp.error : t('error','Błąd'))+'</p></div>');
      return;
    }
    const items = resp.items || [];
    let html = '<table class="widefat fixed striped">'+
      '<thead><tr><th>ID</th><th>'+t('post','Post')+'</th><th>'+t('type','Typ')+'</th><th>'+t('proposal','Propozycja')+'</th><th>Conf</th><th>'+t('status','Status')+'</th><th>'+t('actions','Akcje')+'</th></tr></thead><tbody>';
    if(!items.length){
      html += '<tr><td colspan="7">'+t('no_suggestions','Brak sugestii.')+'</td></tr>';
    } else {
      items.forEach(function(s){
        const p = s.payload || {};
        const desc = (s.kind==='canonical') ? ('canonical → ' + (p.canonical_to || '')) : (s.kind);
        html += '<tr>'+
          '<td>'+esc(s.id)+'</td>'+
          '<td>'+(s.url ? '<a href="'+esc(s.url)+'" target="_blank" rel="noopener">'+esc(s.title||('#'+s.post_id))+'</a>' : esc(s.post_id))+'</td>'+
          '<td>'+esc(s.kind)+'</td>'+
          '<td class="het-small">'+esc(desc)+'</td>'+
          '<td>'+esc(s.confidence)+'</td>'+
          '<td>'+esc(s.status)+'</td>'+
          '<td>'+(s.status!=='applied' ? '<button class="button button-primary" data-het-insights-apply="'+esc(s.id)+'">'+t('apply','Zastosuj')+'</button> ' : '')+((s.status!=='applied' && s.status!=='ignored') ? '<button class="button" data-het-insights-ignore="'+esc(s.id)+'">'+t('ignore','Ignoruj')+'</button>' : '')+'</td>'+
        '</tr>';
      });
    }
    html += '</tbody></table>';
    $wrap.html(html);
  }

  
  function renderExceptions(resp){
    const $wrap = $('#hetExceptionsTable');
    if(!$wrap.length) return;
    if(!resp || !resp.ok){
      $wrap.html('<div class="notice notice-error"><p>'+esc(resp && resp.error ? resp.error : t('error','Błąd'))+'</p></div>');
      return;
    }
    const items = resp.items || [];
    let html = '<table class="widefat fixed striped">'+
      '<thead><tr><th>ID</th><th>'+t('post','Post')+'</th><th>'+t('actions','Akcje')+'</th></tr></thead><tbody>';
    if(!items.length){
      html += '<tr><td colspan="3">'+t('no_exceptions','Brak wyjątków.')+'</td></tr>';
    } else {
      items.forEach(function(it){
        html += '<tr>'+
          '<td>'+esc(it.post_id)+'</td>'+
          '<td>'+(it.url ? '<a href="'+esc(it.url)+'" target="_blank" rel="noopener">'+esc(it.title||('#'+it.post_id))+'</a>' : esc(it.title||('#'+it.post_id)))+'</td>'+
          '<td><button class="button" data-het-ex-del="'+esc(it.post_id)+'">'+t('delete','Usuń')+'</button></td>'+
        '</tr>';
      });
    }
    html += '</tbody></table>';
    $wrap.html(html);
  }

async function refreshInsights(kind){
    if(!window.HET_SEO_INSIGHTS) return;
    const k = kind || (window._hetConfKind || 'cannibal');
    const conf = await apiAbs(window.HET_SEO_INSIGHTS.conflicts + '?kind='+encodeURIComponent(k)+'&limit=50', 'GET');
    renderConflicts(conf);

    const only = $('#hetSugOnlyApproval').is(':checked');
    const sugUrl = window.HET_SEO_INSIGHTS.suggestions + '?limit=150' + (only ? '&status=approval' : '');
    const sug = await apiAbs(sugUrl, 'GET');
    renderSuggestions(sug);

    if(window.HET_SEO_INSIGHTS.exclusions){
      const ex = await apiAbs(window.HET_SEO_INSIGHTS.exclusions, 'GET');
      renderExceptions(ex);
    }
  }

  function initInsights(){
    if(!window.HET_SEO_INSIGHTS) return;

    // conflicts filter
    window._hetConfKind = window._hetConfKind || 'cannibal';
    $(document).on('click', '[data-het-conf-kind]', function(){
      window._hetConfKind = $(this).data('het-conf-kind') || 'cannibal';
      $('[data-het-conf-kind]').removeClass('button-primary');
      $(this).addClass('button-primary');
      refreshInsights(window._hetConfKind);
    });


    // quick open tab
    $(document).on('click', '[data-het-insights-open]', function(){
      const tab = $(this).data('het-insights-open');
      const $btn = $('[data-het-tabs] [data-het-tab="'+tab+'"]');
      if($btn.length) $btn.trigger('click');
    });

    $(document).on('click', '[data-het-insights-scan]', async function(){
      const lim = parseInt($('#hetInsightsLimit').val() || '500', 10);
      const includeTerms = $('#hetInsightsIncludeTerms').length ? $('#hetInsightsIncludeTerms').is(':checked') : true;
      const termLim = parseInt($('#hetInsightsTermLimit').val() || '300', 10);
      $('#hetInsightsStatus').text(t('scanning','Skanuję…'));
      const resp = await apiAbs(window.HET_SEO_INSIGHTS.scan, 'POST', {limit: lim, include_terms: includeTerms, term_limit: termLim});
      if(!resp || !resp.ok){
        alert(resp && resp.error ? resp.error : t('error','Błąd'));
      } else {
        const d = resp.data || {};
        const sp = (d.scanned_posts!==undefined) ? d.scanned_posts : (d.scanned||0);
        const st = (d.scanned_terms!==undefined) ? d.scanned_terms : 0;
        $('#hetInsightsStatus').text('OK: posts '+sp+', terms '+st+', conflicts '+(d.conflicts_open||0)+', suggestions '+(d.suggestions_created||0));
      }
      await refreshInsights();
    });
    $(document).on('click', '[data-het-insights-refresh]', function(){
      refreshInsights();
    });

    $(document).on('change', '#hetSugOnlyApproval', function(){
      refreshInsights();
    });

    $(document).on('click', '[data-het-insights-apply-bulk]', async function(){
      if(!window.HET_SEO_INSIGHTS || !window.HET_SEO_INSIGHTS.apply_bulk) return;
      const min = parseInt($('#hetSugMinConf').val() || '88', 10);
      const lim = parseInt($('#hetSugApplyLimit').val() || '30', 10);
      if(!confirm(t('apply_bulk_confirm_prefix','Zastosować masowo sugestie z confidence ≥ ')+min+' (limit '+lim+')?')) return;
      const resp = await apiAbs(window.HET_SEO_INSIGHTS.apply_bulk, 'POST', {min_confidence:min, limit:lim, status:'approval'});
      if(!resp || !resp.ok){
        alert(resp && resp.error ? resp.error : t('error','Błąd'));
      } else {
        const d = resp.data || {};
        alert(t('apply_bulk_done','Zastosowano: ')+(d.applied||0)+' / '+(d.considered||0));
      }
      await refreshInsights();
    });

    $(document).on('click', '[data-het-insights-apply]', async function(){
      const id = $(this).data('het-insights-apply');
      if(!id) return;
      if(!confirm(t('apply_suggestion_prefix','Zastosować sugestię #')+id+'?')) return;
      const resp = await api('/insights/apply/'+id, 'POST', {});
      if(!resp || !resp.ok){ alert(resp && resp.error ? resp.error : t('error','Błąd')); }
      await refreshInsights();
    });

    $(document).on('click', '[data-het-insights-open]', function(){
      const tab = $(this).data('het-insights-open');
      const $btn = $('[data-het-tabs] [data-het-tab="'+tab+'"]');
      if($btn.length) $btn.trigger('click');
    });


    $(document).on('click', '[data-het-insights-ignore]', async function(){
      const id = $(this).data('het-insights-ignore');
      if(!id) return;
      if(!confirm(t('ignore_suggestion_prefix','Zignorować sugestię #')+id+'?')) return;
      const url = (window.HET_SEO_INSIGHTS.ignore || '').replace(/\/$/,'') + '/' + id;
      const resp = await apiAbs(url, 'POST', {});
      if(!resp || !resp.ok){ alert(resp && resp.error ? resp.error : t('error','Błąd')); }
      await refreshInsights();
    });

    
    // Mark merge candidate (winner/loser helper)
    $(document).on('click', '[data-het-merge-mark]', async function(){
      if(!window.HET_SEO_INSIGHTS || !window.HET_SEO_INSIGHTS.merge_mark) return;
      const topic = $(this).data('het-topic') || '';
      const winner = parseInt($(this).data('het-winner') || '0', 10);
      const loser = parseInt($(this).data('het-loser') || '0', 10);
      if(!topic || !winner || !loser) return;
      if(!confirm(t('mark_loser_prefix','Oznaczyć LOSER jako kandydat do scalenia (winner ')+winner+', loser '+loser+')?')) return;
      const resp = await apiAbs(window.HET_SEO_INSIGHTS.merge_mark, 'POST', {topic_hash: topic, winner_id: winner, loser_id: loser});
      if(!resp || !resp.ok){ alert(resp && resp.error ? resp.error : t('error','Błąd')); }
      await refreshInsights();
    });

// Exceptions (whitelist)
    $(document).on('click', '[data-het-ex-refresh]', async function(){
      await refreshInsights();
    });

    $(document).on('click', '[data-het-ex-add]', async function(){
      const pid = parseInt($('#hetExPostId').val() || '0', 10);
      if(!pid) return;
      $('#hetExStatus').text(t('adding','Dodaję…'));
      const resp = await apiAbs(window.HET_SEO_INSIGHTS.exclusions, 'POST', {action:'add', post_id: pid});
      if(!resp || !resp.ok){ alert(resp && resp.error ? resp.error : t('error','Błąd')); }
      $('#hetExStatus').text('');
      await refreshInsights();
    });

    $(document).on('click', '[data-het-ex-del]', async function(){
      const pid = $(this).data('het-ex-del');
      if(!pid) return;
      if(!confirm(t('remove_exception_prefix','Usunąć wyjątek dla post_id ')+pid+'?')) return;
      const resp = await apiAbs(window.HET_SEO_INSIGHTS.exclusions, 'POST', {action:'remove', post_id: parseInt(pid,10)});
      if(!resp || !resp.ok){ alert(resp && resp.error ? resp.error : t('error','Błąd')); }
      await refreshInsights();
    });

    // initial
    refreshInsights();
  }

})(jQuery);