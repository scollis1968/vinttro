import React, { useState, useEffect, useRef, useMemo } from 'react';
import './visp-header-search.css';

const GROUPS = [
  { key: 'name', label: 'Contacts' },
  { key: 'opp', label: 'Opportunities & Reg' },
  { key: 'company', label: 'Companies' },
  { key: 'postcode', label: 'Postcodes' }
];

/**
 * Safely splits text and wraps matching query terms in <mark> without dangerouslySetInnerHTML
 */
function HighlightText({ text, query }) {
  const str = String(text || '');
  const terms = query
    .trim()
    .split(/\s+/)
    .filter((t) => t.length >= 2)
    .map((t) => t.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'));

  if (!str || terms.length === 0) return <>{str}</>;

  const regex = new RegExp(`(${terms.join('|')})`, 'gi');
  const parts = str.split(regex);

  return (
    <>
      {parts.map((part, i) =>
        regex.test(part) ? <mark key={i}>{part}</mark> : <React.Fragment key={i}>{part}</React.Fragment>
      )}
    </>
  );
}

export default function VispHeaderSearch({
  apiBaseUrl = 'http://127.0.0.1:3005',
  minChars = 3,
  debounceMs = 150,
  hasActiveCall = false,
  onOpenClient,
  onDialNumber,
  onLinkToCall
}) {
  const [query, setQuery] = useState('');
  const [results, setResults] = useState([]);
  const [isLoading, setIsLoading] = useState(false);
  const [isOpen, setIsOpen] = useState(false);
  const [activeIndex, setActiveIndex] = useState(-1);
  const [errorMsg, setErrorMsg] = useState('');

  const containerRef = useRef(null);
  const inputRef = useRef(null);
  const abortRef = useRef(null);

  // Build grouped sections + a flat list for keyboard arrow navigation
  const { groupedSections, flatItems } = useMemo(() => {
    const sections = [];
    const flat = [];

    for (const group of GROUPS) {
      const matches = results.filter((r) => r.match?.field === group.key).slice(0, 5);
      if (matches.length === 0) continue;

      const itemsWithIndex = matches.map((hit) => {
        const flatIndex = flat.length;
        flat.push(hit);
        return { hit, flatIndex };
      });

      sections.push({
        key: group.key,
        label: group.label,
        items: itemsWithIndex
      });
    }

    return { groupedSections: sections, flatItems: flat };
  }, [results]);

  // Debounced live search as you type (3+ characters)
  useEffect(() => {
    const trimmed = query.trim();

    if (trimmed.length < minChars) {
      if (abortRef.current) abortRef.current.abort();
      setResults([]);
      setErrorMsg('');
      setIsLoading(false);
      setIsOpen(false);
      setActiveIndex(-1);
      return;
    }

    const timer = setTimeout(async () => {
      if (abortRef.current) abortRef.current.abort();
      const controller = new AbortController();
      abortRef.current = controller;

      setIsLoading(true);
      setErrorMsg('');

      try {
        const url = `${apiBaseUrl}/api/clients/search?q=${encodeURIComponent(trimmed)}&limit=15`;
        const res = await fetch(url, { signal: controller.signal });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);

        const data = await res.json();
        setResults(data.results || []);
        setActiveIndex(-1);
        setIsOpen(true);
      } catch (err) {
        if (err.name !== 'AbortError') {
          console.error('[VIsP Search Error]:', err);
          setResults([]);
          setErrorMsg('Search unavailable. Check API connection.');
          setIsOpen(true);
        }
      } finally {
        if (!controller.signal.aborted) {
          setIsLoading(false);
        }
      }
    }, debounceMs);

    return () => clearTimeout(timer);
  }, [query, apiBaseUrl, minChars, debounceMs]);

  // Global '/' shortcut & click-outside handler
  useEffect(() => {
    const handleGlobalKeyDown = (e) => {
      const tag = document.activeElement?.tagName;
      if (e.key === '/' && !['INPUT', 'TEXTAREA', 'SELECT'].includes(tag)) {
        e.preventDefault();
        inputRef.current?.focus();
        inputRef.current?.select();
      }
    };

    const handleClickOutside = (e) => {
      if (containerRef.current && !containerRef.current.contains(e.target)) {
        setIsOpen(false);
      }
    };

    document.addEventListener('keydown', handleGlobalKeyDown);
    document.addEventListener('click', handleClickOutside);
    return () => {
      document.removeEventListener('keydown', handleGlobalKeyDown);
      document.removeEventListener('click', handleClickOutside);
    };
  }, []);

  const handleSelect = (hit) => {
    setIsOpen(false);
    setActiveIndex(-1);
    onOpenClient?.(hit);
  };

  const handleKeyDown = (e) => {
    if (!isOpen || flatItems.length === 0) {
      if (e.key === 'Escape') {
        setIsOpen(false);
        inputRef.current?.blur();
      }
      return;
    }

    if (e.key === 'ArrowDown') {
      e.preventDefault();
      setActiveIndex((prev) => (prev + 1) % flatItems.length);
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      setActiveIndex((prev) => (prev - 1 + flatItems.length) % flatItems.length);
    } else if (e.key === 'Enter' && activeIndex >= 0) {
      e.preventDefault();
      handleSelect(flatItems[activeIndex]);
    } else if (e.key === 'Escape') {
      e.preventDefault();
      setIsOpen(false);
    }
  };

  const handleClear = () => {
    setQuery('');
    setResults([]);
    setIsOpen(false);
    setActiveIndex(-1);
    inputRef.current?.focus();
  };

  const trimmedQuery = query.trim();

  return (
    <div ref={containerRef} className="visp-search">
      <div className="visp-search__input-wrap">
        <svg className="visp-search__icon" viewBox="0 0 20 20" fill="none" stroke="currentColor" strokeWidth="1.8">
          <circle cx="8.5" cy="8.5" r="5.75" />
          <path d="M13 13l4.25 4.25" strokeLinecap="round" />
        </svg>

        <input
          ref={inputRef}
          type="text"
          className="visp-search__input"
          placeholder="Search client, company, postcode or reg..."
          value={query}
          onChange={(e) => setQuery(e.target.value)}
          onFocus={() => {
            if (trimmedQuery.length >= minChars && (flatItems.length > 0 || errorMsg)) {
              setIsOpen(true);
            }
          }}
          onKeyDown={handleKeyDown}
          autoComplete="off"
          spellCheck={false}
          aria-label="Search clients"
          aria-expanded={isOpen}
        />

        {isLoading && <span className="visp-search__spinner" />}

        {query.length > 0 && (
          <button
            type="button"
            className="visp-search__clear"
            title="Clear search"
            onClick={handleClear}
          >
            &times;
          </button>
        )}

        <kbd className="visp-search__kbd">/</kbd>
      </div>

      {isOpen && (
        <div className="visp-search__popover" role="listbox">
          {errorMsg ? (
            <div className="visp-search__empty">{errorMsg}</div>
          ) : flatItems.length === 0 ? (
            <div className="visp-search__empty">
              No matching clients, postcodes, or reg numbers for &ldquo;{trimmedQuery}&rdquo;
            </div>
          ) : (
            <>
              {groupedSections.map((section) => (
                <React.Fragment key={section.key}>
                  <div className="visp-search__group-header">
                    {section.label} ({section.items.length})
                  </div>

                  {section.items.map(({ hit, flatIndex }) => (
                    <div
                      key={`${hit.client_id}-${flatIndex}`}
                      role="option"
                      aria-selected={flatIndex === activeIndex}
                      className={`visp-search__row ${flatIndex === activeIndex ? 'is-active' : ''}`}
                      onMouseEnter={() => setActiveIndex(flatIndex)}
                      onClick={() => handleSelect(hit)}
                    >
                      <div className="visp-search__row-main">
                        <div className="visp-search__row-title">
                          {hit.contact_name ? (
                            <>
                              <HighlightText text={hit.contact_name} query={trimmedQuery} />
                              <span className="visp-search__row-company">
                                {' '}&middot; <HighlightText text={hit.account_name} query={trimmedQuery} />
                              </span>
                            </>
                          ) : (
                            <HighlightText text={hit.account_name} query={trimmedQuery} />
                          )}
                        </div>

                        <div className="visp-search__row-meta">
                          {hit.match?.opportunity_label && (
                            <>
                              <span className="visp-search__badge visp-search__badge--opp">Opp</span>
                              <HighlightText text={hit.match.opportunity_label} query={trimmedQuery} />
                              {' '}&middot;{' '}
                            </>
                          )}
                          {hit.primary_address && (
                            <>
                              <HighlightText text={hit.primary_address} query={trimmedQuery} />
                              {hit.phone ? ' \u00B7 ' : ''}
                            </>
                          )}
                          {hit.phone && <span>{hit.phone}</span>}
                        </div>
                      </div>

                      <div className="visp-search__actions">
                        {hasActiveCall && (
                          <button
                            type="button"
                            className="visp-search__btn visp-search__btn--link"
                            onClick={(e) => {
                              e.stopPropagation();
                              setIsOpen(false);
                              onLinkToCall?.(hit);
                            }}
                          >
                            Link Call
                          </button>
                        )}

                        {hit.phone && (
                          <button
                            type="button"
                            className="visp-search__btn visp-search__btn--dial"
                            title={`Dial ${hit.phone}`}
                            onClick={(e) => {
                              e.stopPropagation();
                              setIsOpen(false);
                              onDialNumber?.(hit.phone, hit);
                            }}
                          >
                            Dial
                          </button>
                        )}
                      </div>
                    </div>
                  ))}
                </React.Fragment>
              ))}

              <div className="visp-search__footer">
                <span>&uarr;&darr; to navigate &middot; Enter to open</span>
                <span>
                  {flatItems.length} match{flatItems.length === 1 ? '' : 'es'}
                </span>
              </div>
            </>
          )}
        </div>
      )}
    </div>
  );
}