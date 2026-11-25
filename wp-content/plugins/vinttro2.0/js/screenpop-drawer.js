(function () {
  'use strict';

  // =========================================================================
  // 1. API ADAPTER (Swap endpoint URL here to use custom EntryPoint or V8)
  // =========================================================================
  const API = {
    endpoint: 'index.php?entryPoint=unknown_caller_api',

    async search(query) {
      const res = await fetch(`${this.endpoint}&action=search&q=${encodeURIComponent(query)}`, {
        credentials: 'same-origin'
      });
      if (!res.ok) throw new Error('Search failed');
      return res.json(); // Expected: { records: [{ id, module, name, account_name, postcode, phone_mobile, phone_work, phone_other, acturis_ref_c }] }
    },

    async submit(payload) {
      const res = await fetch(`${this.endpoint}&action=submit`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      if (!res.ok) throw new Error('Save failed');
      return res.json(); // Expected: { success: true, record_id, task_id, message }
    }
  };

  // =========================================================================
  // 2. INTERNAL STATE
  // =========================================================================
  const state = {
    isOpen: false,
    isCollapsed: false,
    view: 'SEARCH', // 'SEARCH' | 'SCENARIO_2' | 'SCENARIO_1' | 'SCENARIO_3'
    callId: null,
    rawCli: '',
    formattedCli: '',
    startedAt: null,
    callEnded: false,
    timerInterval: null,
    searchQuery: '',
    searchResults: [],
    isSearching: false,
    selectedRecord: null,
    notes: '',
    dismissPopoverOpen: false
  };

  let searchDebounceTimer = null;

  // =========================================================================
  // 3. HELPERS
  // =========================================================================
  function escapeHtml(str) {
    return String(str || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function normalizeE164(cli) {
    const cleaned = String(cli || '').replace(/[^\d+]/g, '');
    if (cleaned.startsWith('07') && cleaned.length === 11) return '+44' + cleaned.slice(1);
    if (cleaned.startsWith('01') || cleaned.startsWith('02') || cleaned.startsWith('03')) {
      return '+44' + cleaned.slice(1);
    }
    return cleaned;
  }

  function formatDisplayCli(cli) {
    const raw = String(cli || '').trim();
    if (raw.startsWith('+447') && raw.length === 13) {
      return '0' + raw.slice(3, 7) + ' ' + raw.slice(7);
    }
    if (raw.startsWith('07') && raw.length === 11) {
      return raw.slice(0, 5) + ' ' + raw.slice(5);
    }
    return raw || 'Unknown Number';
  }

  function defaultPhoneSlot(cli) {
    const normalized = normalizeE164(cli);
    return normalized.startsWith('+447') || String(cli).startsWith('07')
      ? 'phone_mobile'
      : 'phone_work';
  }

  function formatElapsed(ms) {
    const totalSec = Math.max(0, Math.floor(ms / 1000));
    const mins = String(Math.floor(totalSec / 60)).padStart(2, '0');
    const secs = String(totalSec % 60).padStart(2, '0');
    return `${mins}:${secs}`;
  }

  function getElapsedSeconds() {
    return state.startedAt ? Math.max(1, Math.floor((Date.now() - state.startedAt) / 1000)) : 0;
  }

  // =========================================================================
  // 4. STYLES (Scoped to #scrm-screenpop-drawer)
  // =========================================================================
  function injectStyles() {
    if (document.getElementById('scrm-screenpop-styles')) return;
    const style = document.createElement('style');
    style.id = 'scrm-screenpop-styles';
    style.textContent = `
      #scrm-screenpop-drawer {
        position: fixed; top: 0; right: 0; width: 400px; height: 100vh;
        background: #ffffff; border-left: 1px solid #d1d5db;
        box-shadow: -4px 0 20px rgba(0,0,0,0.12);
        z-index: 99999; display: flex; flex-direction: column;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        font-size: 13px; color: #1f2937; transition: width 0.2s ease;
      }
      #scrm-screenpop-drawer.collapsed { width: 48px; overflow: hidden; }
      .sp-header {
        height: 64px; min-height: 64px; background: #1e293b; color: #fff;
        padding: 0 14px; display: flex; align-items: center; justify-content: space-between;
      }
      .sp-cli { font-size: 16px; font-weight: 700; letter-spacing: 0.3px; }
      .sp-badge {
        display: inline-flex; align-items: center; gap: 6px; font-size: 11px;
        background: #0f172a; padding: 3px 8px; border-radius: 999px; margin-top: 3px;
      }
      .sp-dot { width: 7px; height: 7px; border-radius: 50%; background: #22c55e; }
      .sp-dot.ended { background: #ef4444; }
      .sp-icon-btn {
        background: rgba(255,255,255,0.12); border: none; color: #fff;
        width: 28px; height: 28px; border-radius: 6px; cursor: pointer; font-weight: 700;
      }
      .sp-body { flex: 1; overflow-y: auto; padding: 16px; }
      .sp-footer {
        min-height: 110px; border-top: 1px solid #e5e7eb; background: #f9fafb;
        padding: 10px 14px; display: flex; flex-direction: column; gap: 8px; position: relative;
      }
      .sp-notes {
        width: 100%; box-sizing: border-box; border: 1px solid #d1d5db; border-radius: 6px;
        padding: 7px 9px; font-size: 12.5px; resize: vertical; min-height: 48px;
      }
      .sp-footer-actions { display: flex; justify-content: space-between; align-items: center; }
      .sp-ghost-danger {
        background: none; border: none; color: #dc2626; font-size: 12px;
        font-weight: 600; cursor: pointer; padding: 4px 0;
      }
      .sp-popover {
        position: absolute; bottom: 42px; left: 14px; background: #fff;
        border: 1px solid #d1d5db; border-radius: 8px; box-shadow: 0 6px 18px rgba(0,0,0,0.14);
        padding: 8px; display: flex; gap: 6px; z-index: 10;
      }
      .sp-input, .sp-select {
        width: 100%; box-sizing: border-box; padding: 8px 10px; border: 1px solid #cbd5e1;
        border-radius: 6px; font-size: 13px; margin-top: 4px; background: #fff;
      }
      .sp-input:focus, .sp-select:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 2px rgba(37,99,235,0.15); }
      .sp-field { margin-bottom: 12px; }
      .sp-label { font-weight: 600; font-size: 12px; color: #374151; display: block; }
      .sp-req { color: #dc2626; }
      .sp-card {
        border: 1px solid #e5e7eb; border-radius: 8px; padding: 10px 12px;
        margin-bottom: 8px; cursor: pointer; transition: border-color 0.15s, background 0.15s;
      }
      .sp-card:hover { border-color: #2563eb; background: #eff6ff; }
      .sp-card-title { font-weight: 600; color: #111827; display: flex; justify-content: space-between; }
      .sp-card-meta { font-size: 11.5px; color: #6b7280; margin-top: 3px; }
      .sp-pill {
        font-size: 10px; text-transform: uppercase; padding: 2px 6px;
        border-radius: 4px; background: #e2e8f0; color: #334155; font-weight: 700;
      }
      .sp-btn {
        width: 100%; padding: 9px 12px; border-radius: 6px; font-weight: 600;
        font-size: 13px; cursor: pointer; border: 1px solid transparent;
      }
      .sp-btn-primary { background: #2563eb; color: #fff; }
      .sp-btn-primary:disabled { background: #93c5fd; cursor: not-allowed; }
      .sp-btn-secondary { background: #f1f5f9; color: #1e293b; border-color: #cbd5e1; margin-top: 8px; }
      .sp-btn-secondary:hover { background: #e2e8f0; }
      .sp-back {
        background: none; border: none; color: #2563eb; font-weight: 600;
        cursor: pointer; padding: 0; margin-bottom: 12px; font-size: 12.5px;
      }
      .sp-amber-alert {
        background: #fef3c7; border: 1px solid #f59e0b; color: #92400e;
        padding: 9px 11px; border-radius: 6px; font-size: 12px; margin-bottom: 12px;
      }
      .sp-guard-box {
        background: #fef2f2; border: 1px solid #fecaca; padding: 9px 11px;
        border-radius: 6px; margin-bottom: 12px; font-size: 12px;
      }
      .sp-segmented { display: flex; border: 1px solid #cbd5e1; border-radius: 6px; overflow: hidden; margin-top: 4px; }
      .sp-segmented button {
        flex: 1; padding: 7px; border: none; background: #f8fafc; cursor: pointer; font-weight: 600;
      }
      .sp-segmented button.active { background: #2563eb; color: #fff; }
      .sp-toast {
        position: fixed; bottom: 24px; right: 420px; background: #16a34a; color: #fff;
        padding: 10px 16px; border-radius: 8px; font-weight: 600; z-index: 100000;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
      }
    `;
    document.head.appendChild(style);
  }

  // =========================================================================
  // 5. RENDERING
  // =========================================================================
  function getContainer() {
    let el = document.getElementById('scrm-screenpop-drawer');
    if (!el) {
      el = document.createElement('aside');
      el.id = 'scrm-screenpop-drawer';
      document.body.appendChild(el);
    }
    return el;
  }

  function showToast(msg) {
    const toast = document.createElement('div');
    toast.className = 'sp-toast';
    toast.textContent = msg;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 2500);
  }

  function render() {
    const container = getContainer();
    if (!state.isOpen) {
      container.style.display = 'none';
      return;
    }
    container.style.display = 'flex';
    container.className = state.isCollapsed ? 'collapsed' : '';

    if (state.isCollapsed) {
      container.innerHTML = `
        <div class="sp-header" style="flex-direction:column; height:100%; padding:14px 0; justify-content:flex-start; gap:16px;">
          <button class="sp-icon-btn" id="sp-expand-btn" title="Expand Caller Drawer">&laquo;</button>
          <span class="sp-dot ${state.callEnded ? 'ended' : ''}"></span>
        </div>
      `;
      document.getElementById('sp-expand-btn').onclick = () => {
        state.isCollapsed = false;
        render();
      };
      return;
    }

    container.innerHTML = `
      <div class="sp-header">
        <div>
          <div class="sp-cli">${escapeHtml(state.formattedCli)}</div>
          <div class="sp-badge">
            <span class="sp-dot ${state.callEnded ? 'ended' : ''}"></span>
            <span>${state.callEnded ? 'Ended' : 'Connected'}</span>
            <span>&bull;</span>
            <span id="sp-timer">${formatElapsed(Date.now() - state.startedAt)}</span>
          </div>
        </div>
        <button class="sp-icon-btn" id="sp-collapse-btn" title="Dock to edge">&raquo;</button>
      </div>

      <div class="sp-body" id="sp-body"></div>

      <div class="sp-footer">
        <textarea class="sp-notes" id="sp-notes-input" placeholder="Quick call notes...">${escapeHtml(state.notes)}</textarea>
        <div class="sp-footer-actions">
          <button type="button" class="sp-ghost-danger" id="sp-dismiss-trigger">Dismiss / Not a Client</button>
          <span style="font-size:11px; color:#6b7280;">Auto-links CLI</span>
        </div>
        ${state.dismissPopoverOpen ? `
          <div class="sp-popover">
            <button type="button" class="sp-btn-secondary" style="margin:0; padding:5px 9px; font-size:12px;" id="sp-dismiss-wrong">Wrong Number</button>
            <button type="button" class="sp-btn-secondary" style="margin:0; padding:5px 9px; font-size:12px; color:#dc2626;" id="sp-dismiss-spam">Spam / Cold Call</button>
          </div>
        ` : ''}
      </div>
    `;

    // Persistent bindings
    document.getElementById('sp-collapse-btn').onclick = () => { state.isCollapsed = true; render(); };
    document.getElementById('sp-notes-input').oninput = (e) => { state.notes = e.target.value; };
    document.getElementById('sp-dismiss-trigger').onclick = () => {
      state.dismissPopoverOpen = !state.dismissPopoverOpen;
      render();
    };
    if (state.dismissPopoverOpen) {
      document.getElementById('sp-dismiss-wrong').onclick = () => handleDismiss('dismissed_wrong_number');
      document.getElementById('sp-dismiss-spam').onclick = () => handleDismiss('dismissed_spam');
    }

    const bodyEl = document.getElementById('sp-body');
    if (state.view === 'SEARCH') renderSearchView(bodyEl);
    else if (state.view === 'SCENARIO_2') renderScenario2View(bodyEl);
    else if (state.view === 'SCENARIO_1') renderScenario1View(bodyEl);
    else if (state.view === 'SCENARIO_3') renderScenario3View(bodyEl);
  }

  // --- VIEW A: Search-First Landing ---
  function renderSearchView(bodyEl) {
    const resultsHtml = state.isSearching
      ? `<p style="color:#6b7280; font-size:12px;">Searching SuiteCRM...</p>`
      : state.searchResults.length > 0
        ? state.searchResults.map((r, idx) => `
            <div class="sp-card" data-idx="${idx}">
              <div class="sp-card-title">
                <span>${escapeHtml(r.name)}</span>
                <span class="sp-pill">${escapeHtml(r.module)}</span>
              </div>
              <div class="sp-card-meta">
                ${escapeHtml(r.account_name || 'Individual')} &bull; ${escapeHtml(r.postcode || 'No postcode')}
              </div>
              <div class="sp-card-meta">
                Existing: ${escapeHtml(r.phone_mobile || r.phone_work || r.phone_other || 'None')}
              </div>
            </div>
          `).join('')
        : state.searchQuery.length >= 2
          ? `<p style="color:#6b7280; font-size:12px;">No matching Contacts or Leads found.</p>`
          : `<p style="color:#6b7280; font-size:12px;">Type a caller's name, company, or postcode as you greet them.</p>`;

    bodyEl.innerHTML = `
      <div class="sp-field">
        <label class="sp-label">Find Existing Client or Lead</label>
        <input type="text" class="sp-input" id="sp-search-input"
               placeholder="Name, company, email, or postcode..."
               value="${escapeHtml(state.searchQuery)}" />
      </div>

      <div id="sp-results-list">${resultsHtml}</div>

      <div style="margin-top:16px; border-top:1px solid #e5e7eb; padding-top:12px;">
        <button type="button" class="sp-btn sp-btn-secondary" id="sp-go-s1">+ New Contact / Lead</button>
        <button type="button" class="sp-btn sp-btn-secondary" id="sp-go-s3">+ Known Acturis Client (Not in CRM)</button>
      </div>
    `;

    const searchInput = document.getElementById('sp-search-input');
    searchInput.focus();
    searchInput.setSelectionRange(searchInput.value.length, searchInput.value.length);

    searchInput.oninput = (e) => {
      state.searchQuery = e.target.value;
      clearTimeout(searchDebounceTimer);
      if (state.searchQuery.trim().length < 2) {
        state.searchResults = [];
        render();
        return;
      }
      searchDebounceTimer = setTimeout(async () => {
        state.isSearching = true;
        render();
        try {
          const data = await API.search(state.searchQuery.trim());
          state.searchResults = (data.records || []).slice(0, 5);
        } catch (err) {
          state.searchResults = [];
        } finally {
          state.isSearching = false;
          render();
        }
      }, 250);
    };

    bodyEl.querySelectorAll('.sp-card').forEach((card) => {
      card.onclick = () => {
        state.selectedRecord = state.searchResults[Number(card.dataset.idx)];
        state.view = 'SCENARIO_2';
        render();
      };
    });

    document.getElementById('sp-go-s1').onclick = () => { state.view = 'SCENARIO_1'; render(); };
    document.getElementById('sp-go-s3').onclick = () => { state.view = 'SCENARIO_3'; render(); };
  }

  // --- VIEW B: Scenario 2 (Existing Client - Add Number) ---
  function renderScenario2View(bodyEl) {
    const rec = state.selectedRecord;
    let chosenSlot = defaultPhoneSlot(state.rawCli);
    let overwriteMode = 'other'; // 'replace' | 'other'
    let callReason = '';

    function draw() {
      const existingVal = rec[chosenSlot];
      bodyEl.innerHTML = `
        <button type="button" class="sp-back" id="sp-back-btn">&larr; Back to Search</button>
        <div class="sp-card" style="cursor:default; background:#f8fafc;">
          <div class="sp-card-title">
            <span>${escapeHtml(rec.name)}</span>
            <a href="index.php?module=${encodeURIComponent(rec.module)}&action=DetailView&record=${encodeURIComponent(rec.id)}"
               target="_blank" style="font-size:11px; color:#2563eb;">Open &nearr;</a>
          </div>
          <div class="sp-card-meta">${escapeHtml(rec.account_name || '')} &bull; ${escapeHtml(rec.postcode || '')}</div>
        </div>

        <div class="sp-field">
          <label class="sp-label">Save ${escapeHtml(state.formattedCli)} to Slot <span class="sp-req">*</span></label>
          <select class="sp-select" id="s2-slot">
            <option value="phone_mobile" ${chosenSlot === 'phone_mobile' ? 'selected' : ''}>Mobile Phone</option>
            <option value="phone_work" ${chosenSlot === 'phone_work' ? 'selected' : ''}>Work / Direct Phone</option>
            <option value="phone_other" ${chosenSlot === 'phone_other' ? 'selected' : ''}>Other Phone</option>
          </select>
        </div>

        ${existingVal ? `
          <div class="sp-guard-box">
            <strong>Slot already occupied (${escapeHtml(existingVal)}):</strong>
            <div style="margin-top:6px;">
              <label style="display:block; margin-bottom:4px;">
                <input type="radio" name="s2-guard" value="other" ${overwriteMode === 'other' ? 'checked' : ''} />
                Save to <em>Other Phone</em> instead
              </label>
              <label style="display:block;">
                <input type="radio" name="s2-guard" value="replace" ${overwriteMode === 'replace' ? 'checked' : ''} />
                Replace existing number
              </label>
            </div>
          </div>
        ` : ''}

        <div class="sp-field">
          <label class="sp-label">Call Subject / Reason</label>
          <input type="text" class="sp-input" id="s2-reason" placeholder="e.g., Renewal query, MTA" value="${escapeHtml(callReason)}" />
        </div>

        <button type="button" class="sp-btn sp-btn-primary" id="s2-submit">Add Number &amp; Link Call</button>
      `;

      document.getElementById('sp-back-btn').onclick = () => { state.view = 'SEARCH'; render(); };
      document.getElementById('s2-slot').onchange = (e) => { chosenSlot = e.target.value; draw(); };
      document.getElementById('s2-reason').oninput = (e) => { callReason = e.target.value; };
      bodyEl.querySelectorAll('input[name="s2-guard"]').forEach((r) => {
        r.onchange = (e) => { overwriteMode = e.target.value; };
      });

      document.getElementById('s2-submit').onclick = async () => {
        const finalSlot = existingVal && overwriteMode === 'other' ? 'phone_other' : chosenSlot;
        await executeSubmit({
          scenario: 'scenario_2',
          target_module: rec.module,
          target_id: rec.id,
          phone_slot: finalSlot,
          formatted_cli: state.formattedCli,
          raw_cli_e164: state.rawCli,
          call_subject: callReason || 'Inbound call - New number added',
          notes: state.notes,
          duration_seconds: getElapsedSeconds(),
          telephony_call_id: state.callId
        }, 'Number saved & call linked');
      };
    }

    draw();
  }

  // --- VIEW C: Scenario 1 (New Contact / Lead) ---
  function renderScenario1View(bodyEl) {
    let recordType = 'Leads';
    let firstName = '';
    let lastName = '';
    let email = '';
    let lob = '';
    let company = '';
    let phoneSlot = defaultPhoneSlot(state.rawCli);

    function draw() {
      const isCommercial = lob === 'Commercial';
      const isValid = firstName.trim() && lastName.trim() && lob && (!isCommercial || company.trim());

      bodyEl.innerHTML = `
        <button type="button" class="sp-back" id="sp-back-btn">&larr; Back to Search</button>

        <div class="sp-field">
          <label class="sp-label">Record Type</label>
          <div class="sp-segmented">
            <button type="button" class="${recordType === 'Leads' ? 'active' : ''}" id="s1-type-lead">Lead</button>
            <button type="button" class="${recordType === 'Contacts' ? 'active' : ''}" id="s1-type-contact">Contact</button>
          </div>
        </div>

        <div class="sp-field">
          <label class="sp-label">First Name <span class="sp-req">*</span></label>
          <input type="text" class="sp-input" id="s1-first" value="${escapeHtml(firstName)}" />
        </div>

        <div class="sp-field">
          <label class="sp-label">Last Name <span class="sp-req">*</span></label>
          <input type="text" class="sp-input" id="s1-last" value="${escapeHtml(lastName)}" />
        </div>

        <div class="sp-field">
          <label class="sp-label">Incoming Number (${phoneSlot === 'phone_mobile' ? 'Mobile' : 'Work'})</label>
          <input type="text" class="sp-input" value="${escapeHtml(state.formattedCli)}" readonly style="background:#f1f5f9;" />
        </div>

        <div class="sp-field">
          <label class="sp-label">Email Address</label>
          <input type="email" class="sp-input" id="s1-email" value="${escapeHtml(email)}" />
        </div>

        <div class="sp-field">
          <label class="sp-label">Line of Business <span class="sp-req">*</span></label>
          <select class="sp-select" id="s1-lob">
            <option value="">Select...</option>
            <option value="Personal_Lines" ${lob === 'Personal_Lines' ? 'selected' : ''}>Personal Lines</option>
            <option value="Commercial" ${lob === 'Commercial' ? 'selected' : ''}>Commercial</option>
            <option value="Motor" ${lob === 'Motor' ? 'selected' : ''}>Motor</option>
            <option value="Property" ${lob === 'Property' ? 'selected' : ''}>Property</option>
            <option value="Other" ${lob === 'Other' ? 'selected' : ''}>Other</option>
          </select>
        </div>

        <div class="sp-field">
          <label class="sp-label">Company / Trading Name ${isCommercial ? '<span class="sp-req">*</span>' : ''}</label>
          <input type="text" class="sp-input" id="s1-company" value="${escapeHtml(company)}" />
        </div>

        <button type="button" class="sp-btn sp-btn-primary" id="s1-submit" ${isValid ? '' : 'disabled'}>Create &amp; Link Call</button>
      `;

      document.getElementById('sp-back-btn').onclick = () => { state.view = 'SEARCH'; render(); };
      document.getElementById('s1-type-lead').onclick = () => { recordType = 'Leads'; draw(); };
      document.getElementById('s1-type-contact').onclick = () => { recordType = 'Contacts'; draw(); };
      document.getElementById('s1-first').oninput = (e) => { firstName = e.target.value; updateBtn(); };
      document.getElementById('s1-last').oninput = (e) => { lastName = e.target.value; updateBtn(); };
      document.getElementById('s1-email').oninput = (e) => { email = e.target.value; };
      document.getElementById('s1-lob').onchange = (e) => { lob = e.target.value; draw(); };
      document.getElementById('s1-company').oninput = (e) => { company = e.target.value; updateBtn(); };

      function updateBtn() {
        const ok = firstName.trim() && lastName.trim() && lob && (lob !== 'Commercial' || company.trim());
        document.getElementById('s1-submit').disabled = !ok;
      }

      document.getElementById('s1-submit').onclick = async () => {
        await executeSubmit({
          scenario: 'scenario_1',
          target_module: recordType,
          first_name: firstName.trim(),
          last_name: lastName.trim(),
          email1: email.trim(),
          line_of_business_c: lob,
          account_name: company.trim(),
          phone_slot: phoneSlot,
          formatted_cli: state.formattedCli,
          raw_cli_e164: state.rawCli,
          notes: state.notes,
          duration_seconds: getElapsedSeconds(),
          telephony_call_id: state.callId
        }, `${recordType === 'Leads' ? 'Lead' : 'Contact'} created & call linked`);
      };
    }

    draw();
  }

  // --- VIEW D: Scenario 3 (Existing Acturis Client Stub) ---
  function renderScenario3View(bodyEl) {
    let fullName = '';
    let postcode = '';
    let acturisRef = '';
    let company = '';
    let skipFactFind = true;

    function draw() {
      const hasMatchingKey = postcode.trim().length > 0 || acturisRef.trim().length > 0;
      const isValid = fullName.trim().length > 0 && hasMatchingKey;

      bodyEl.innerHTML = `
        <button type="button" class="sp-back" id="sp-back-btn">&larr; Back to Search</button>

        <div class="sp-amber-alert">
          <strong>Compliance Reminder:</strong> Admin entry skipped. Still verify caller identity before disclosing policy details.
        </div>

        <div class="sp-field">
          <label class="sp-label">Client Full Name <span class="sp-req">*</span></label>
          <input type="text" class="sp-input" id="s3-name" placeholder="e.g., David Thornton" value="${escapeHtml(fullName)}" />
        </div>

        <div class="sp-field">
          <label class="sp-label">Matching Key (Enter Postcode OR Acturis Ref) <span class="sp-req">*</span></label>
          <div style="display:flex; gap:8px;">
            <input type="text" class="sp-input" id="s3-postcode" placeholder="Postcode (WR1 2AB)" value="${escapeHtml(postcode)}" />
            <input type="text" class="sp-input" id="s3-ref" placeholder="Acturis Ref" value="${escapeHtml(acturisRef)}" />
          </div>
        </div>

        <div class="sp-field">
          <label class="sp-label">Company / Trading Name</label>
          <input type="text" class="sp-input" id="s3-company" value="${escapeHtml(company)}" />
        </div>

        <div class="sp-field">
          <label style="display:flex; align-items:flex-start; gap:8px; font-size:12px; cursor:pointer;">
            <input type="checkbox" id="s3-skip" ${skipFactFind ? 'checked' : ''} style="margin-top:2px;" />
            <span>Known client &ndash; skip full fact-find &amp; queue post-call Acturis cleanup task</span>
          </label>
        </div>

        <button type="button" class="sp-btn sp-btn-primary" id="s3-submit" ${isValid ? '' : 'disabled'}>Save Stub &amp; Queue Cleanup</button>
      `;

      document.getElementById('sp-back-btn').onclick = () => { state.view = 'SEARCH'; render(); };
      document.getElementById('s3-name').oninput = (e) => { fullName = e.target.value; updateBtn(); };
      document.getElementById('s3-postcode').oninput = (e) => { postcode = e.target.value.toUpperCase(); updateBtn(); };
      document.getElementById('s3-ref').oninput = (e) => { acturisRef = e.target.value.toUpperCase(); updateBtn(); };
      document.getElementById('s3-company').oninput = (e) => { company = e.target.value; };
      document.getElementById('s3-skip').onchange = (e) => { skipFactFind = e.target.checked; };

      function updateBtn() {
        const ok = fullName.trim().length > 0 && (postcode.trim().length > 0 || acturisRef.trim().length > 0);
        document.getElementById('s3-submit').disabled = !ok;
      }

      document.getElementById('s3-submit').onclick = async () => {
        const parts = fullName.trim().split(/\s+/);
        const last = parts.length > 1 ? parts.pop() : parts[0];
        const first = parts.length > 0 ? parts.join(' ') : '';

        await executeSubmit({
          scenario: 'scenario_3',
          first_name: first,
          last_name: last,
          primary_address_postalcode: postcode.trim(),
          acturis_ref_c: acturisRef.trim(),
          account_name: company.trim(),
          known_client_verified_c: skipFactFind,
          phone_slot: defaultPhoneSlot(state.rawCli),
          formatted_cli: state.formattedCli,
          raw_cli_e164: state.rawCli,
          notes: state.notes,
          duration_seconds: getElapsedSeconds(),
          telephony_call_id: state.callId
        }, 'Acturis stub saved & cleanup task queued');
      };
    }

    draw();
  }

  // =========================================================================
  // 6. SUBMISSION & PUBLIC TELEPHONY HOOKS
  // =========================================================================
  async function executeSubmit(payload, successMessage) {
    try {
      await API.submit(payload);
      showToast(successMessage);
      if (state.callEnded) {
        closeDrawer();
      } else {
        state.isCollapsed = true;
        render();
      }
    } catch (err) {
      alert('Could not save to SuiteCRM. Please check your connection and try again.');
    }
  }

  async function handleDismiss(outcome) {
    if (outcome === 'dismissed_spam') {
      try {
        await API.submit({
          scenario: 'scenario_4_spam',
          raw_cli_e164: state.rawCli,
          formatted_cli: state.formattedCli,
          telephony_call_id: state.callId,
          duration_seconds: getElapsedSeconds()
        });
      } catch (e) { /* Fail silently on spam log */ }
    }
    closeDrawer();
  }

  function closeDrawer() {
    clearInterval(state.timerInterval);
    state.isOpen = false;
    state.dismissPopoverOpen = false;
    render();
  }

  window.SuiteCRMScreenPop = {
    open({ cli, callId }) {
      injectStyles();
      clearInterval(state.timerInterval);
      Object.assign(state, {
        isOpen: true,
        isCollapsed: false,
        view: 'SEARCH',
        callId: callId || `call-${Date.now()}`,
        rawCli: normalizeE164(cli),
        formattedCli: formatDisplayCli(cli),
        startedAt: Date.now(),
        callEnded: false,
        searchQuery: '',
        searchResults: [],
        selectedRecord: null,
        notes: '',
        dismissPopoverOpen: false
      });

      state.timerInterval = setInterval(() => {
        const timerEl = document.getElementById('sp-timer');
        if (timerEl && !state.callEnded) {
          timerEl.textContent = formatElapsed(Date.now() - state.startedAt);
        }
      }, 1000);

      render();
    },

    endCall() {
      state.callEnded = true;
      clearInterval(state.timerInterval);
      render();
      // Auto-archive after 30s if still untouched on Search view
      if (state.view === 'SEARCH' && !state.isCollapsed) {
        setTimeout(() => {
          if (state.isOpen && state.callEnded && state.view === 'SEARCH') closeDrawer();
        }, 30000);
      }
    }
  };
})();