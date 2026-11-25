/**
 * VISP Header Bar — Unknown Caller Triage Panel
 * Mounts directly beneath the VISP header bar without blocking call controls.
 */
window.VispUnknownCallerBar = (() => {
  const NODE_BASE = (typeof vinttroConfig !== 'undefined' && vinttroConfig.nodeApiUrl)
    ? vinttroConfig.nodeApiUrl.replace(/\/+$/, '')
    : 'http://localhost:3005';

  const CONFIG = {
    headerHeightPx: 56,
    searchEndpoint: `${NODE_BASE}/api/communicator/crm-caller-search`,
    submitEndpoint: `${NODE_BASE}/api/communicator/crm-unknown-caller`,
    suiteCrmBaseUrl: 'http://localhost:8084'
  };

  const state = {
    isOpen: false,
    isMinimized: false,
    view: 'SEARCH', // 'SEARCH' | 'SCENARIO_2' | 'SCENARIO_1' | 'SCENARIO_3'
    callId: null,
    rawCli: '',
    displayCli: '',
    startedAt: null,
    callEnded: false,
    timerId: null,
    query: '',
    results: [],
    searching: false,
    selectedRecord: null,
    notes: '',
    showDismissMenu: false,
    submitting: false
  };

  let debounceTimer = null;

  // --- Formatting & Validation Helpers ---
  const esc = (v) =>
    String(v || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');

  function toE164(cli) {
    const digits = String(cli || '').replace(/[^\d+]/g, '');
    if (/^0[1237]\d{9}$/.test(digits)) return '+44' + digits.slice(1);
    return digits;
  }

  function formatUkCli(cli) {
    const e164 = toE164(cli);
    if (/^\+447\d{9}$/.test(e164)) return '0' + e164.slice(3, 7) + ' ' + e164.slice(7);
    if (/^\+44[123]\d{9}$/.test(e164)) return '0' + e164.slice(3, 7) + ' ' + e164.slice(7);
    return cli || 'Withheld / Unknown';
  }

  function inferPhoneSlot(cli) {
    return toE164(cli).startsWith('+447') ? 'phone_mobile' : 'phone_work';
  }

  function elapsedText() {
    const sec = state.startedAt ? Math.max(0, Math.floor((Date.now() - state.startedAt) / 1000)) : 0;
    return `${String(Math.floor(sec / 60)).padStart(2, '0')}:${String(sec % 60).padStart(2, '0')}`;
  }

  function elapsedSeconds() {
    return state.startedAt ? Math.max(1, Math.floor((Date.now() - state.startedAt) / 1000)) : 0;
  }

  // --- Scoped Styles ---
  function ensureStyles() {
    if (document.getElementById('visp-uc-styles')) return;
    const style = document.createElement('style');
    style.id = 'visp-uc-styles';
    style.textContent = `
      #visp-uc-panel {
        position: fixed;
        top: ${CONFIG.headerHeightPx + 8}px;
        right: 16px;
        width: 400px;
        max-height: calc(100vh - ${CONFIG.headerHeightPx + 24}px);
        background: #ffffff;
        border: 1px solid #cbd5e1;
        border-radius: 10px;
        box-shadow: 0 12px 32px rgba(15, 23, 42, 0.18);
        z-index: 9500;
        display: flex;
        flex-direction: column;
        font-family: inherit;
        font-size: 13px;
        color: #1e293b;
        overflow: hidden;
      }
      #visp-uc-panel.minimized {
        width: auto;
        max-height: none;
      }
      .vuc-head {
        background: #0f172a;
        color: #f8fafc;
        padding: 10px 14px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
      }
      .vuc-cli { font-size: 15px; font-weight: 700; }
      .vuc-status {
        display: inline-flex; align-items: center; gap: 6px;
        font-size: 11px; color: #cbd5e1; margin-top: 2px;
      }
      .vuc-dot { width: 7px; height: 7px; border-radius: 50%; background: #22c55e; }
      .vuc-dot.ended { background: #ef4444; }
      .vuc-head-btn {
        background: rgba(255,255,255,0.12); border: none; color: #fff;
        padding: 4px 8px; border-radius: 5px; cursor: pointer; font-size: 12px;
      }
      .vuc-body { padding: 14px; overflow-y: auto; flex: 1; }
      .vuc-foot {
        background: #f8fafc; border-top: 1px solid #e2e8f0;
        padding: 10px 14px; position: relative;
      }
      .vuc-notes {
        width: 100%; box-sizing: border-box; border: 1px solid #cbd5e1;
        border-radius: 6px; padding: 6px 8px; font-size: 12.5px;
        min-height: 44px; resize: vertical;
      }
      .vuc-foot-row {
        display: flex; justify-content: space-between; align-items: center; margin-top: 6px;
      }
      .vuc-dismiss-btn {
        background: none; border: none; color: #dc2626; font-size: 12px;
        font-weight: 600; cursor: pointer; padding: 2px 0;
      }
      .vuc-popover {
        position: absolute; bottom: 38px; left: 14px; background: #fff;
        border: 1px solid #cbd5e1; border-radius: 8px; padding: 6px;
        box-shadow: 0 8px 20px rgba(0,0,0,0.15); display: flex; gap: 6px;
      }
      .vuc-field { margin-bottom: 11px; }
      .vuc-label { display: block; font-size: 12px; font-weight: 600; color: #334155; margin-bottom: 4px; }
      .vuc-req { color: #dc2626; }
      .vuc-input, .vuc-select {
        width: 100%; box-sizing: border-box; padding: 7px 9px;
        border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; background: #fff;
      }
      .vuc-input:focus, .vuc-select:focus {
        outline: none; border-color: #2563eb; box-shadow: 0 0 0 2px rgba(37,99,235,0.15);
      }
      .vuc-card {
        border: 1px solid #e2e8f0; border-radius: 7px; padding: 9px 11px;
        margin-bottom: 7px; cursor: pointer; transition: 0.15s ease;
      }
      .vuc-card:hover { border-color: #2563eb; background: #eff6ff; }
      .vuc-card-top { display: flex; justify-content: space-between; font-weight: 600; }
      .vuc-card-sub { font-size: 11.5px; color: #64748b; margin-top: 2px; }
      .vuc-tag {
        font-size: 10px; font-weight: 700; text-transform: uppercase;
        background: #e2e8f0; color: #334155; padding: 2px 6px; border-radius: 4px;
      }
      .vuc-btn {
        width: 100%; padding: 8px 12px; border-radius: 6px; font-weight: 600;
        font-size: 13px; cursor: pointer; border: 1px solid transparent;
      }
      .vuc-btn-primary { background: #2563eb; color: #fff; }
      .vuc-btn-primary:disabled { background: #93c5fd; cursor: not-allowed; }
      .vuc-btn-alt { background: #f1f5f9; color: #0f172a; border-color: #cbd5e1; margin-top: 7px; }
      .vuc-btn-alt:hover { background: #e2e8f0; }
      .vuc-back {
        background: none; border: none; color: #2563eb; font-weight: 600;
        font-size: 12px; cursor: pointer; padding: 0; margin-bottom: 10px;
      }
      .vuc-alert {
        background: #fef3c7; border: 1px solid #f59e0b; color: #92400e;
        padding: 8px 10px; border-radius: 6px; font-size: 12px; margin-bottom: 11px;
      }
      .vuc-guard {
        background: #fef2f2; border: 1px solid #fecaca; padding: 8px 10px;
        border-radius: 6px; font-size: 12px; margin-bottom: 11px;
      }
      .vuc-seg { display: flex; border: 1px solid #cbd5e1; border-radius: 6px; overflow: hidden; }
      .vuc-seg button {
        flex: 1; padding: 6px; border: none; background: #f8fafc; font-weight: 600; cursor: pointer;
      }
      .vuc-seg button.active { background: #2563eb; color: #fff; }
      .vuc-toast {
        position: fixed; top: ${CONFIG.headerHeightPx + 12}px; right: 16px;
        background: #16a34a; color: #fff; padding: 10px 14px; border-radius: 8px;
        font-weight: 600; z-index: 9600; box-shadow: 0 6px 16px rgba(0,0,0,0.16);
      }
    `;
    document.head.appendChild(style);
  }

  function toast(msg) {
    const el = document.createElement('div');
    el.className = 'vuc-toast';
    el.textContent = msg;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 2600);
  }

  function getHost() {
    let el = document.getElementById('visp-uc-panel');
    if (!el) {
      el = document.createElement('div');
      el.id = 'visp-uc-panel';
      document.body.appendChild(el);
    }
    return el;
  }

  // --- Render Shell ---
  function render() {
    const host = getHost();
    if (!state.isOpen) {
      host.style.display = 'none';
      return;
    }
    host.style.display = 'flex';
    host.className = state.isMinimized ? 'minimized' : '';

    if (state.isMinimized) {
      host.innerHTML = `
        <div class="vuc-head">
          <div style="display:flex; align-items:center; gap:8px;">
            <span class="vuc-dot ${state.callEnded ? 'ended' : ''}"></span>
            <span style="font-weight:600;">${esc(state.displayCli)}</span>
            <span id="vuc-timer" style="font-size:11px; color:#cbd5e1;">${elapsedText()}</span>
          </div>
          <button type="button" class="vuc-head-btn" id="vuc-expand">Expand</button>
        </div>
      `;
      document.getElementById('vuc-expand').onclick = () => {
        state.isMinimized = false;
        render();
      };
      return;
    }

    host.innerHTML = `
      <div class="vuc-head">
        <div>
          <div class="vuc-cli">${esc(state.displayCli)}</div>
          <div class="vuc-status">
            <span class="vuc-dot ${state.callEnded ? 'ended' : ''}"></span>
            <span>${state.callEnded ? 'Call Ended' : 'Unknown Caller'}</span>
            <span>&bull;</span>
            <span id="vuc-timer">${elapsedText()}</span>
          </div>
        </div>
        <button type="button" class="vuc-head-btn" id="vuc-minimize" title="Minimize to pill">Minimize</button>
      </div>

      <div class="vuc-body" id="vuc-body"></div>

      <div class="vuc-foot">
        <textarea class="vuc-notes" id="vuc-notes" placeholder="Quick call notes (saved to SuiteCRM Call)...">${esc(state.notes)}</textarea>
        <div class="vuc-foot-row">
          <button type="button" class="vuc-dismiss-btn" id="vuc-dismiss">Dismiss / Not a Client</button>
          <span style="font-size:11px; color:#64748b;">Syncs to SuiteCRM</span>
        </div>
        ${state.showDismissMenu ? `
          <div class="vuc-popover">
            <button type="button" class="vuc-btn-alt" style="margin:0; padding:5px 9px; font-size:12px;" id="vuc-wrong">Wrong Number</button>
            <button type="button" class="vuc-btn-alt" style="margin:0; padding:5px 9px; font-size:12px; color:#dc2626;" id="vuc-spam">Spam / Cold Call</button>
          </div>
        ` : ''}
      </div>
    `;

    document.getElementById('vuc-minimize').onclick = () => { state.isMinimized = true; render(); };
    document.getElementById('vuc-notes').oninput = (e) => { state.notes = e.target.value; };
    document.getElementById('vuc-dismiss').onclick = () => {
      state.showDismissMenu = !state.showDismissMenu;
      render();
    };
    if (state.showDismissMenu) {
      document.getElementById('vuc-wrong').onclick = () => dismissCall('dismissed_wrong_number');
      document.getElementById('vuc-spam').onclick = () => dismissCall('dismissed_spam');
    }

    const body = document.getElementById('vuc-body');
    if (state.view === 'SEARCH') renderSearch(body);
    else if (state.view === 'SCENARIO_2') renderScenario2(body);
    else if (state.view === 'SCENARIO_1') renderScenario1(body);
    else if (state.view === 'SCENARIO_3') renderScenario3(body);
  }

  // --- View A: Search-First ---
  function renderSearch(body) {
    const listHtml = state.searching
      ? `<p style="color:#64748b; font-size:12px;">Searching SuiteCRM...</p>`
      : state.results.length
        ? state.results.map((r, i) => `
            <div class="vuc-card" data-i="${i}">
              <div class="vuc-card-top">
                <span>${esc(r.name)}</span>
                <span class="vuc-tag">${esc(r.module)}</span>
              </div>
              <div class="vuc-card-sub">${esc(r.account_name || 'Individual')} &bull; ${esc(r.postcode || 'No postcode')}</div>
              <div class="vuc-card-sub">Existing: ${esc(r.phone_mobile || r.phone_work || r.phone_other || 'None')}</div>
            </div>
          `).join('')
        : state.query.trim().length >= 2
          ? `<p style="color:#64748b; font-size:12px;">No matching Contacts or Leads in SuiteCRM.</p>`
          : `<p style="color:#64748b; font-size:12px;">Search by caller name, business, or postcode.</p>`;

    body.innerHTML = `
      <div class="vuc-field">
        <label class="vuc-label">Search SuiteCRM</label>
        <input type="text" class="vuc-input" id="vuc-search-box" placeholder="Name, company, email, or postcode..." value="${esc(state.query)}" />
      </div>
      <div>${listHtml}</div>
      <div style="border-top:1px solid #e2e8f0; margin-top:12px; padding-top:10px;">
        <button type="button" class="vuc-btn vuc-btn-alt" id="vuc-btn-s1">+ New Contact / Lead</button>
        <button type="button" class="vuc-btn vuc-btn-alt" id="vuc-btn-s3">+ Known Acturis Client (Not in CRM)</button>
      </div>
    `;

    const input = document.getElementById('vuc-search-box');
    input.focus();
    input.setSelectionRange(input.value.length, input.value.length);

    input.oninput = (e) => {
      state.query = e.target.value;
      clearTimeout(debounceTimer);
      if (state.query.trim().length < 2) {
        state.results = [];
        render();
        return;
      }
      debounceTimer = setTimeout(async () => {
        state.searching = true;
        render();
        try {
          const res = await fetch(`${CONFIG.searchEndpoint}?q=${encodeURIComponent(state.query.trim())}`);
          const json = await res.json();
          state.results = (json.records || []).slice(0, 5);
        } catch {
          state.results = [];
        } finally {
          state.searching = false;
          render();
        }
      }, 250);
    };

    body.querySelectorAll('.vuc-card').forEach((card) => {
      card.onclick = () => {
        state.selectedRecord = state.results[Number(card.dataset.i)];
        state.view = 'SCENARIO_2';
        render();
      };
    });

    document.getElementById('vuc-btn-s1').onclick = () => { state.view = 'SCENARIO_1'; render(); };
    document.getElementById('vuc-btn-s3').onclick = () => { state.view = 'SCENARIO_3'; render(); };
  }

  // --- View B: Scenario 2 (Existing Client, New Number) ---
  function renderScenario2(body) {
    const rec = state.selectedRecord;
    let slot = inferPhoneSlot(state.rawCli);
    let collisionChoice = 'other';
    let subject = '';

    function paint() {
      const occupiedVal = rec[slot];
      const baseCrm = CONFIG.suiteCrmBaseUrl.replace(/\/+$/, '');
      const crmUrl = `${baseCrm}/index.php?module=${encodeURIComponent(rec.module)}&action=DetailView&record=${encodeURIComponent(rec.id)}`;

      body.innerHTML = `
        <button type="button" class="vuc-back" id="vuc-back">&larr; Back to Search</button>
        <div class="vuc-card" style="cursor:default; background:#f8fafc;">
          <div class="vuc-card-top">
            <span>${esc(rec.name)}</span>
            <a href="${crmUrl}" target="_blank" rel="noopener" style="font-size:11px; color:#2563eb;">Open in CRM &nearr;</a>
          </div>
          <div class="vuc-card-sub">${esc(rec.account_name || '')} &bull; ${esc(rec.postcode || '')}</div>
        </div>

        <div class="vuc-field">
          <label class="vuc-label">Save ${esc(state.displayCli)} to <span class="vuc-req">*</span></label>
          <select class="vuc-select" id="s2-slot">
            <option value="phone_mobile" ${slot === 'phone_mobile' ? 'selected' : ''}>Mobile Phone</option>
            <option value="phone_work" ${slot === 'phone_work' ? 'selected' : ''}>Work / Direct Phone</option>
            <option value="phone_other" ${slot === 'phone_other' ? 'selected' : ''}>Other Phone</option>
          </select>
        </div>

        ${occupiedVal ? `
          <div class="vuc-guard">
            <strong>Existing number found (${esc(occupiedVal)}):</strong>
            <label style="display:block; margin-top:5px;">
              <input type="radio" name="s2-col" value="other" ${collisionChoice === 'other' ? 'checked' : ''} /> Save to <em>Other Phone</em> instead
            </label>
            <label style="display:block; margin-top:3px;">
              <input type="radio" name="s2-col" value="replace" ${collisionChoice === 'replace' ? 'checked' : ''} /> Replace existing number
            </label>
          </div>
        ` : ''}

        <div class="vuc-field">
          <label class="vuc-label">Call Reason / Subject</label>
          <input type="text" class="vuc-input" id="s2-subj" placeholder="e.g., Renewal query, MTA" value="${esc(subject)}" />
        </div>

        <button type="button" class="vuc-btn vuc-btn-primary" id="s2-save" ${state.submitting ? 'disabled' : ''}>
          ${state.submitting ? 'Saving...' : 'Add Number & Link Call'}
        </button>
      `;

      document.getElementById('vuc-back').onclick = () => { state.view = 'SEARCH'; render(); };
      document.getElementById('s2-slot').onchange = (e) => { slot = e.target.value; paint(); };
      document.getElementById('s2-subj').oninput = (e) => { subject = e.target.value; };
      body.querySelectorAll('input[name="s2-col"]').forEach((r) => {
        r.onchange = (e) => { collisionChoice = e.target.value; };
      });

      document.getElementById('s2-save').onclick = () =>
        submitToVisp({
          scenario: 'scenario_2',
          target_module: rec.module,
          target_id: rec.id,
          phone_slot: occupiedVal && collisionChoice === 'other' ? 'phone_other' : slot,
          formatted_cli: state.displayCli,
          raw_cli_e164: state.rawCli,
          call_subject: subject.trim() || 'Inbound call - New number added',
          notes: state.notes,
          duration_seconds: elapsedSeconds(),
          telephony_call_id: state.callId
        }, 'Number added to SuiteCRM');
    }

    paint();
  }

  // --- View C: Scenario 1 (New Contact / Lead) ---
  function renderScenario1(body) {
    let moduleType = 'Leads';
    let first = '';
    let last = '';
    let email = '';
    let lob = '';
    let company = '';
    const slot = inferPhoneSlot(state.rawCli);

    function paint() {
      const valid = first.trim() && last.trim() && lob && (lob !== 'Commercial' || company.trim());
      body.innerHTML = `
        <button type="button" class="vuc-back" id="vuc-back">&larr; Back to Search</button>

        <div class="vuc-field">
          <label class="vuc-label">Create As</label>
          <div class="vuc-seg">
            <button type="button" class="${moduleType === 'Leads' ? 'active' : ''}" id="s1-lead">Lead</button>
            <button type="button" class="${moduleType === 'Contacts' ? 'active' : ''}" id="s1-contact">Contact</button>
          </div>
        </div>

        <div class="vuc-field">
          <label class="vuc-label">First Name <span class="vuc-req">*</span></label>
          <input type="text" class="vuc-input" id="s1-first" value="${esc(first)}" />
        </div>

        <div class="vuc-field">
          <label class="vuc-label">Last Name <span class="vuc-req">*</span></label>
          <input type="text" class="vuc-input" id="s1-last" value="${esc(last)}" />
        </div>

        <div class="vuc-field">
          <label class="vuc-label">Email Address</label>
          <input type="email" class="vuc-input" id="s1-email" value="${esc(email)}" />
        </div>

        <div class="vuc-field">
          <label class="vuc-label">Line of Business <span class="vuc-req">*</span></label>
          <select class="vuc-select" id="s1-lob">
            <option value="">Select...</option>
            <option value="Personal_Lines" ${lob === 'Personal_Lines' ? 'selected' : ''}>Personal Lines</option>
            <option value="Commercial" ${lob === 'Commercial' ? 'selected' : ''}>Commercial</option>
            <option value="Motor" ${lob === 'Motor' ? 'selected' : ''}>Motor</option>
            <option value="Property" ${lob === 'Property' ? 'selected' : ''}>Property</option>
            <option value="Other" ${lob === 'Other' ? 'selected' : ''}>Other</option>
          </select>
        </div>

        <div class="vuc-field">
          <label class="vuc-label">Company / Trading Name ${lob === 'Commercial' ? '<span class="vuc-req">*</span>' : ''}</label>
          <input type="text" class="vuc-input" id="s1-comp" value="${esc(company)}" />
        </div>

        <button type="button" class="vuc-btn vuc-btn-primary" id="s1-save" ${!valid || state.submitting ? 'disabled' : ''}>
          ${state.submitting ? 'Creating...' : 'Create & Link Call'}
        </button>
      `;

      document.getElementById('vuc-back').onclick = () => { state.view = 'SEARCH'; render(); };
      document.getElementById('s1-lead').onclick = () => { moduleType = 'Leads'; paint(); };
      document.getElementById('s1-contact').onclick = () => { moduleType = 'Contacts'; paint(); };
      document.getElementById('s1-first').oninput = (e) => { first = e.target.value; toggleBtn(); };
      document.getElementById('s1-last').oninput = (e) => { last = e.target.value; toggleBtn(); };
      document.getElementById('s1-email').oninput = (e) => { email = e.target.value; };
      document.getElementById('s1-lob').onchange = (e) => { lob = e.target.value; paint(); };
      document.getElementById('s1-comp').oninput = (e) => { company = e.target.value; toggleBtn(); };

      function toggleBtn() {
        const ok = first.trim() && last.trim() && lob && (lob !== 'Commercial' || company.trim());
        document.getElementById('s1-save').disabled = !ok || state.submitting;
      }

      document.getElementById('s1-save').onclick = () =>
        submitToVisp({
          scenario: 'scenario_1',
          target_module: moduleType,
          first_name: first.trim(),
          last_name: last.trim(),
          email1: email.trim(),
          line_of_business_c: lob,
          account_name: company.trim(),
          phone_slot: slot,
          formatted_cli: state.displayCli,
          raw_cli_e164: state.rawCli,
          notes: state.notes,
          duration_seconds: elapsedSeconds(),
          telephony_call_id: state.callId
        }, `${moduleType === 'Leads' ? 'Lead' : 'Contact'} created in SuiteCRM`);
    }

    paint();
  }

  // --- View D: Scenario 3 (Existing Acturis Client Stub) ---
  function renderScenario3(body) {
    let fullName = '';
    let postcode = '';
    let acturisRef = '';
    let company = '';
    let verified = true;

    function paint() {
      const valid = fullName.trim() && (postcode.trim() || acturisRef.trim());
      body.innerHTML = `
        <button type="button" class="vuc-back" id="vuc-back">&larr; Back to Search</button>

        <div class="vuc-alert">
          <strong>Compliance Reminder:</strong> Full fact-find skipped. Verify caller identity before discussing policy details.
        </div>

        <div class="vuc-field">
          <label class="vuc-label">Client Full Name <span class="vuc-req">*</span></label>
          <input type="text" class="vuc-input" id="s3-name" placeholder="e.g., David Thornton" value="${esc(fullName)}" />
        </div>

        <div class="vuc-field">
          <label class="vuc-label">Matching Key (Postcode OR Acturis Ref) <span class="vuc-req">*</span></label>
          <div style="display:flex; gap:8px;">
            <input type="text" class="vuc-input" id="s3-pc" placeholder="Postcode (WR1 2AB)" value="${esc(postcode)}" />
            <input type="text" class="vuc-input" id="s3-ref" placeholder="Acturis Ref" value="${esc(acturisRef)}" />
          </div>
        </div>

        <div class="vuc-field">
          <label class="vuc-label">Company / Trading Name</label>
          <input type="text" class="vuc-input" id="s3-comp" value="${esc(company)}" />
        </div>

        <div class="vuc-field">
          <label style="display:flex; align-items:flex-start; gap:7px; font-size:12px; cursor:pointer;">
            <input type="checkbox" id="s3-ver" ${verified ? 'checked' : ''} style="margin-top:2px;" />
            <span>Known client &ndash; skip full fact-find &amp; queue Acturis cleanup task</span>
          </label>
        </div>

        <button type="button" class="vuc-btn vuc-btn-primary" id="s3-save" ${!valid || state.submitting ? 'disabled' : ''}>
          ${state.submitting ? 'Queuing...' : 'Save Stub & Queue Cleanup'}
        </button>
      `;

      document.getElementById('vuc-back').onclick = () => { state.view = 'SEARCH'; render(); };
      document.getElementById('s3-name').oninput = (e) => { fullName = e.target.value; toggleBtn(); };
      document.getElementById('s3-pc').oninput = (e) => { postcode = e.target.value.toUpperCase(); toggleBtn(); };
      document.getElementById('s3-ref').oninput = (e) => { acturisRef = e.target.value.toUpperCase(); toggleBtn(); };
      document.getElementById('s3-comp').oninput = (e) => { company = e.target.value; };
      document.getElementById('s3-ver').onchange = (e) => { verified = e.target.checked; };

      function toggleBtn() {
        const ok = fullName.trim() && (postcode.trim() || acturisRef.trim());
        document.getElementById('s3-save').disabled = !ok || state.submitting;
      }

      document.getElementById('s3-save').onclick = () => {
        const parts = fullName.trim().split(/\s+/);
        const last = parts.length > 1 ? parts.pop() : parts[0];
        const first = parts.length ? parts.join(' ') : '';

        submitToVisp({
          scenario: 'scenario_3',
          first_name: first,
          last_name: last,
          primary_address_postalcode: postcode.trim(),
          acturis_ref_c: acturisRef.trim(),
          account_name: company.trim(),
          known_client_verified_c: verified,
          phone_slot: inferPhoneSlot(state.rawCli),
          formatted_cli: state.displayCli,
          raw_cli_e164: state.rawCli,
          notes: state.notes,
          duration_seconds: elapsedSeconds(),
          telephony_call_id: state.callId
        }, 'Stub saved & Acturis cleanup queued');
      };
    }

    paint();
  }

  // --- API & Lifecycle Actions ---
  async function submitToVisp(payload, successMsg) {
    state.submitting = true;
    render();
    try {
      const res = await fetch(CONFIG.submitEndpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      if (!res.ok) throw new Error('VISP API error');
      toast(successMsg);
      if (state.callEnded) close();
      else {
        state.isMinimized = true;
        render();
      }
    } catch {
      alert('Failed to sync with SuiteCRM via VISP. Please try again.');
    } finally {
      state.submitting = false;
      if (state.isOpen) render();
    }
  }

  async function dismissCall(outcome) {
    if (outcome === 'dismissed_spam') {
      fetch(CONFIG.submitEndpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          scenario: 'scenario_4_spam',
          raw_cli_e164: state.rawCli,
          formatted_cli: state.displayCli,
          telephony_call_id: state.callId,
          duration_seconds: elapsedSeconds()
        })
      }).catch(() => {});
    }
    close();
  }

  function close() {
    clearInterval(state.timerId);
    state.isOpen = false;
    state.showDismissMenu = false;
    render();
  }

  // --- Public Hooks for VISP Header Bar ---
  return {
    onCallAnswered({ cli, callId, isKnownCrmMatch = false }) {
      if (isKnownCrmMatch) return;
      ensureStyles();
      clearInterval(state.timerId);
      Object.assign(state, {
        isOpen: true,
        isMinimized: false,
        view: 'SEARCH',
        callId: callId || `visp-${Date.now()}`,
        rawCli: toE164(cli),
        displayCli: formatUkCli(cli),
        startedAt: Date.now(),
        callEnded: false,
        query: '',
        results: [],
        selectedRecord: null,
        notes: '',
        showDismissMenu: false,
        submitting: false
      });

      state.timerId = setInterval(() => {
        const el = document.getElementById('vuc-timer');
        if (el && !state.callEnded) el.textContent = elapsedText();
      }, 1000);

      render();
    },

    onCallEnded() {
      state.callEnded = true;
      clearInterval(state.timerId);
      render();
      if (state.view === 'SEARCH' && !state.isMinimized) {
        setTimeout(() => {
          if (state.isOpen && state.callEnded && state.view === 'SEARCH') close();
        }, 30000);
      }
    }
  };
})();