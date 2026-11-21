// wp-content/plugins/vinttro2.0/js/visp-header-search.js
(function () {
  class VispHeaderSearch {
    constructor(options = {}) {
      this.apiBaseUrl =
        options.apiBaseUrl ||
        (window.vinttroConfig && window.vinttroConfig.nodeApiUrl) ||
        'http://localhost:3005';
      this.minChars = options.minChars || 3;
      this.debounceMs = options.debounceMs || 150;
      this.onOpenClient = options.onOpenClient || ((hit) => console.log('[VIsP Search] Open client:', hit));
      this.onDialNumber = options.onDialNumber || ((phone, hit) => console.log('[VIsP Search] Dial:', phone, hit));
      this.onLinkToCall = options.onLinkToCall || ((hit) => console.log('[VIsP Search] Link to call:', hit));

      this.flatResults = [];
      this.lastResults = [];
      this.activeIndex = -1;
      this.debounceTimer = null;
      this.abortController = null;

      this.root = document.getElementById('visp-header-search');
      this.input = document.getElementById('visp-search-input');
      this.popover = document.getElementById('visp-search-popover');
      this.clearBtn = document.getElementById('visp-search-clear');
      this.spinner = document.getElementById('visp-search-spinner');
      this.callBar = document.getElementById('vinttro-header-call-bar');

      if (this.root && this.input && this.popover) {
        this.bindEvents();
      }
    }

    /** Automatically detects if #vinttro-header-call-bar is currently in an active call */
    isCallActive() {
      return Boolean(this.callBar && this.callBar.classList.contains('state-active'));
    }

    /** Checks if contact name and account name refer to the same person ("John Smith" vs "Smith John") */
    isSamePerson(accountName, contactName) {
      if (!accountName || !contactName) return false;
      const normalizeWords = (s) =>
        String(s)
          .toLowerCase()
          .replace(/\(private\)/g, '')
          .replace(/[^a-z0-9\s]/g, '')
          .trim()
          .split(/\s+/)
          .filter(Boolean)
          .sort()
          .join(' ');
      return normalizeWords(accountName) === normalizeWords(contactName);
    }

    bindEvents() {
      this.input.addEventListener('input', () => {
        const q = this.input.value.trim();
        if (this.clearBtn) this.clearBtn.hidden = q.length === 0;

        clearTimeout(this.debounceTimer);
        if (q.length < this.minChars) {
          if (this.abortController) this.abortController.abort();
          this.closePopover();
          return;
        }

        this.debounceTimer = setTimeout(() => this.performSearch(q), this.debounceMs);
      });

      this.input.addEventListener('focus', () => {
        if (this.input.value.trim().length >= this.minChars && this.flatResults.length > 0) {
          this.openPopover();
        }
      });

      this.input.addEventListener('keydown', (e) => this.handleKeyDown(e));

      if (this.clearBtn) {
        this.clearBtn.addEventListener('click', () => {
          this.input.value = '';
          this.clearBtn.hidden = true;
          this.closePopover();
          this.input.focus();
        });
      }

      // Global '/' shortcut to jump straight to search
      document.addEventListener('keydown', (e) => {
        const tag = document.activeElement?.tagName;
        if (e.key === '/' && !['INPUT', 'TEXTAREA', 'SELECT'].includes(tag)) {
          e.preventDefault();
          this.input.focus();
          this.input.select();
        }
      });

      // Close popover when clicking outside
      document.addEventListener('click', (e) => {
        if (!this.root.contains(e.target)) {
          this.closePopover();
        }
      });
    }

    async performSearch(query) {
      if (this.abortController) {
        this.abortController.abort();
      }
      this.abortController = new AbortController();
      if (this.spinner) this.spinner.hidden = false;

      try {
        const url = `${this.apiBaseUrl}/api/clients/search?q=${encodeURIComponent(query)}&limit=15`;
        const res = await fetch(url, { signal: this.abortController.signal });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);

        const data = await res.json();
        this.lastResults = data.results || [];
        this.renderResults(this.lastResults, query);
      } catch (err) {
        if (err.name !== 'AbortError') {
          console.error('[VIsP Search Error]:', err);
          this.popover.innerHTML = `<div class="visp-search__empty">Search unavailable. Check API connection.</div>`;
          this.openPopover();
        }
      } finally {
        if (this.spinner) this.spinner.hidden = true;
      }
    }

    renderResults(results, query) {
      this.flatResults = [];
      this.activeIndex = -1;

      if (!results || results.length === 0) {
        this.popover.innerHTML = `<div class="visp-search__empty">No matching clients, postcodes, or reg numbers for "${this.escapeHtml(query)}"</div>`;
        this.openPopover();
        return;
      }

      const groups = [
        { key: 'client', label: 'Clients' },
        { key: 'opp', label: 'Opportunities & Reg' },
        { key: 'postcode', label: 'Postcodes' }
      ];

      const activeCall = this.isCallActive();
      let html = '';

      for (const group of groups) {
        const items = results.filter((r) => r.match?.field === group.key).slice(0, 5);
        if (items.length === 0) continue;

        html += `<div class="visp-search__group-header">${group.label} (${items.length})</div>`;

        for (const item of items) {
          const idx = this.flatResults.length;
          this.flatResults.push(item);

          // Format Private accounts as "FirstName LastName (Private)" without duplicating "(Private)"
          const rawAccountName = item.account_name || 'Unnamed Client';
          const hasPrivateTag = /\(private\)/i.test(rawAccountName);
          const formattedAccount =
            item.is_private && !hasPrivateTag
              ? `${this.highlight(rawAccountName, query)} <span class="visp-search__row-company">(Private)</span>`
              : this.highlight(rawAccountName, query);

          // Show "via Contact" only if matched via a secondary/authorized contact (e.g. spouse or commercial contact)
          const showViaContact =
            item.contact_name && !this.isSamePerson(rawAccountName, item.contact_name);

          const primaryTitle = showViaContact
            ? `${formattedAccount} <span class="visp-search__row-company">&middot; via ${this.highlight(item.contact_name, query)}</span>`
            : formattedAccount;

          const metaParts = [];
          if (item.match?.opportunity_label) {
            metaParts.push(
              `<span class="visp-search__badge visp-search__badge--opp">Opp</span>${this.highlight(item.match.opportunity_label, query)}`
            );
          }
          if (item.primary_address) {
            metaParts.push(this.highlight(item.primary_address, query));
          }
          if (item.phone) {
            metaParts.push(this.escapeHtml(item.phone));
          }

          const actionButtons = [];
          if (activeCall) {
            actionButtons.push(
              `<button type="button" class="visp-search__btn visp-search__btn--link" data-action="link" data-idx="${idx}">Link Call</button>`
            );
          }
          if (item.phone) {
            actionButtons.push(
              `<button type="button" class="visp-search__btn visp-search__btn--dial" data-action="dial" data-idx="${idx}" title="Dial ${this.escapeHtml(item.phone)}">Dial</button>`
            );
          }

          html += `
            <div class="visp-search__row" role="option" data-idx="${idx}">
              <div class="visp-search__row-main">
                <div class="visp-search__row-title">${primaryTitle}</div>
                <div class="visp-search__row-meta">${metaParts.join(' &middot; ')}</div>
              </div>
              <div class="visp-search__actions">${actionButtons.join('')}</div>
            </div>
          `;
        }
      }

      html += `
        <div class="visp-search__footer">
          <span>&uarr;&darr; to navigate &middot; Enter to open</span>
          <span>${this.flatResults.length} match${this.flatResults.length === 1 ? '' : 'es'}</span>
        </div>
      `;

      this.popover.innerHTML = html;
      this.bindRowEvents();
      this.openPopover();
    }

    bindRowEvents() {
      this.popover.querySelectorAll('.visp-search__row').forEach((row) => {
        row.addEventListener('click', (e) => {
          const actionBtn = e.target.closest('[data-action]');
          const idx = Number(row.dataset.idx);
          const hit = this.flatResults[idx];
          if (!hit) return;

          if (actionBtn) {
            e.stopPropagation();
            const action = actionBtn.dataset.action;
            if (action === 'dial') this.onDialNumber(hit.phone, hit);
            if (action === 'link') this.onLinkToCall(hit);
            this.closePopover();
            return;
          }

          this.selectResult(hit);
        });
      });
    }

    handleKeyDown(e) {
      if (this.popover.hidden || this.flatResults.length === 0) {
        if (e.key === 'Escape') this.input.blur();
        return;
      }

      if (e.key === 'ArrowDown') {
        e.preventDefault();
        this.setActiveIndex((this.activeIndex + 1) % this.flatResults.length);
      } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        this.setActiveIndex((this.activeIndex - 1 + this.flatResults.length) % this.flatResults.length);
      } else if (e.key === 'Enter' && this.activeIndex >= 0) {
        e.preventDefault();
        this.selectResult(this.flatResults[this.activeIndex]);
      } else if (e.key === 'Escape') {
        e.preventDefault();
        this.closePopover();
      }
    }

    setActiveIndex(idx) {
      this.activeIndex = idx;
      const rows = this.popover.querySelectorAll('.visp-search__row');
      rows.forEach((r, i) => {
        const active = i === idx;
        r.classList.toggle('is-active', active);
        if (active) r.scrollIntoView({ block: 'nearest' });
      });
    }

    selectResult(hit) {
      this.closePopover();
      this.onOpenClient(hit);
    }

    openPopover() {
      this.popover.hidden = false;
      this.input.setAttribute('aria-expanded', 'true');
    }

    closePopover() {
      this.popover.hidden = true;
      this.input.setAttribute('aria-expanded', 'false');
      this.activeIndex = -1;
    }

    escapeHtml(str) {
      return String(str || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
    }

    highlight(text, query) {
      const safeText = this.escapeHtml(text);
      const terms = query
        .trim()
        .split(/\s+/)
        .filter((t) => t.length >= 2)
        .map((t) => t.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'));

      if (terms.length === 0) return safeText;
      const regex = new RegExp(`(${terms.join('|')})`, 'gi');
      return safeText.replace(regex, '<mark>$1</mark>');
    }
  }

  function initVispHeaderSearch() {
    if (window.vispSearch) return;
    window.vispSearch = new VispHeaderSearch({
      minChars: 3,

      onOpenClient(hit) {
        // Dispatch a cancelable event first so client-view can intercept without reloading if already open
        const event = new CustomEvent('visp:client-selected', {
          detail: hit,
          cancelable: true
        });
        document.dispatchEvent(event);

        if (!event.defaultPrevented) {
          const url = new URL('/visp/client-view/', window.location.origin);
          url.searchParams.set('client_id', hit.client_id);
          if (hit.match?.opportunity_id) {
            url.searchParams.set('opp_id', hit.match.opportunity_id);
          }
          window.location.href = url.toString();
        }
      },

      onDialNumber(phone, hit) {
        document.dispatchEvent(new CustomEvent('visp:dial-requested', { detail: { phone, hit } }));
      },

      onLinkToCall(hit) {
        document.dispatchEvent(new CustomEvent('visp:link-call-requested', { detail: hit }));
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initVispHeaderSearch);
  } else {
    initVispHeaderSearch();
  }
})();