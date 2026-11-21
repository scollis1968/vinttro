import React, { useState, useEffect, useCallback } from 'react';
import { getApiUrl } from '../utils/api';

const CHANNEL_META = {
  call: { label: 'Call', icon: '📞', color: '#2563eb', bg: '#eff6ff' },
  email: { label: 'Email', icon: '✉️', color: '#7c3aed', bg: '#f5f3ff' },
  whatsapp: { label: 'WhatsApp', icon: '💬', color: '#16a34a', bg: '#f0fdf4' },
  sms: { label: 'SMS', icon: '📱', color: '#0891b2', bg: '#ecfeff' },
  note: { label: 'Note', icon: '📝', color: '#4b5563', bg: '#f3f4f6' },
};

function formatDateTime(isoString) {
  if (!isoString) return '—';
  const d = new Date(isoString);
  if (Number.isNaN(d.getTime())) return isoString;
  return d.toLocaleString('en-GB', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

function formatTimeOnly(isoString) {
  if (!isoString) return '';
  const d = new Date(isoString);
  if (Number.isNaN(d.getTime())) return isoString;
  return d.toLocaleTimeString('en-GB', {
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
  });
}

export default function EventsTimeline({ apiBase = getApiUrl('communicator'), accountId = null }) {
  const [events, setEvents] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  const [channelFilter, setChannelFilter] = useState('all');
  const [searchTerm, setSearchTerm] = useState('');

  // Selected event & full detail payload
  const [selectedSummary, setSelectedSummary] = useState(null);
  const [eventDetail, setEventDetail] = useState(null);
  const [detailLoading, setDetailLoading] = useState(false);

  const fetchEvents = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const params = new URLSearchParams({ limit: '40' });
      if (accountId) params.set('accountId', accountId);
      if (channelFilter !== 'all') params.set('channel', channelFilter);

      const res = await fetch(`${apiBase}/events?${params.toString()}`);
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();
      setEvents(data.events || []);
    } catch (err) {
      setError(err.message || 'Failed to load events');
    } finally {
      setLoading(false);
    }
  }, [apiBase, accountId, channelFilter]);

  useEffect(() => {
    fetchEvents();
  }, [fetchEvents]);

  const handleSelectEvent = async (ev) => {
    setSelectedSummary(ev);
    setEventDetail(null);
    setDetailLoading(true);
    try {
      const res = await fetch(`${apiBase}/events/${ev.sourceModule}/${ev.id}`);
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();
      setEventDetail(data.event);
    } catch (err) {
      setEventDetail({ error: err.message });
    } finally {
      setDetailLoading(false);
    }
  };

  const filteredEvents = events.filter((ev) => {
    if (!searchTerm.trim()) return true;
    const q = searchTerm.toLowerCase();
    return (
      (ev.summary || '').toLowerCase().includes(q) ||
      (ev.contactEndpoint || '').toLowerCase().includes(q) ||
      (ev.agent || '').toLowerCase().includes(q) ||
      (ev.accountName || '').toLowerCase().includes(q)
    );
  });

  return (
    <div style={styles.container}>
      {/* Header & Controls */}
      <div style={styles.toolbar}>
        <div style={styles.tabs}>
          {[
            { key: 'all', label: 'All Events' },
            { key: 'call', label: 'Calls' },
            { key: 'email', label: 'Emails' },
            { key: 'whatsapp,sms', label: 'Messages' },
          ].map((tab) => (
            <button
              key={tab.key}
              onClick={() => setChannelFilter(tab.key)}
              style={{
                ...styles.tabBtn,
                ...(channelFilter === tab.key ? styles.tabBtnActive : {}),
              }}
            >
              {tab.label}
            </button>
          ))}
        </div>

        <div style={styles.rightControls}>
          <input
            type="text"
            placeholder="Search number, subject, agent..."
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            style={styles.searchInput}
          />
          <button onClick={fetchEvents} style={styles.refreshBtn} title="Refresh">
            ↻ Refresh
          </button>
        </div>
      </div>

      {/* Main List */}
      {loading ? (
        <div style={styles.emptyState}>Loading communication events...</div>
      ) : error ? (
        <div style={{ ...styles.emptyState, color: '#dc2626' }}>Error: {error}</div>
      ) : filteredEvents.length === 0 ? (
        <div style={styles.emptyState}>No communication events found.</div>
      ) : (
        <div style={styles.listWrapper}>
          {filteredEvents.map((ev) => {
            const meta = CHANNEL_META[ev.channel] || CHANNEL_META.note;
            const isSelected = selectedSummary?.id === ev.id;

            return (
              <div
                key={`${ev.sourceModule}-${ev.id}`}
                onClick={() => handleSelectEvent(ev)}
                style={{
                  ...styles.row,
                  ...(isSelected ? styles.rowSelected : {}),
                }}
              >
                {/* Channel Icon */}
                <div style={{ ...styles.channelBadge, backgroundColor: meta.bg, color: meta.color }}>
                  <span>{meta.icon}</span>
                </div>

                {/* Primary Info */}
                <div style={styles.rowMain}>
                  <div style={styles.rowTopLine}>
                    <span style={styles.summaryText}>{ev.summary}</span>
                    <span style={styles.directionTag}>
                      {ev.direction === 'outbound' ? '↗ Outbound' : '↙ Inbound'}
                    </span>
                    {ev.hasRecording && (
                      <span style={styles.recordingTag}>🎙️ Recording</span>
                    )}
                  </div>

                  <div style={styles.rowSubLine}>
                    {ev.contactEndpoint && <span>{ev.contactEndpoint} • </span>}
                    {ev.accountName && <strong>{ev.accountName} • </strong>}
                    <span>Agent: {ev.agent || 'Unassigned'}</span>
                  </div>
                </div>

                {/* Status & Timestamp */}
                <div style={styles.rowMeta}>
                  <span style={styles.statusPill}>{ev.status || 'Logged'}</span>
                  <span style={styles.timeText}>{formatDateTime(ev.timestamp)}</span>
                </div>
              </div>
            );
          })}
        </div>
      )}

      {/* Slide-Over Detail Drawer */}
      {selectedSummary && (
        <div style={styles.drawerBackdrop} onClick={() => setSelectedSummary(null)}>
          <div style={styles.drawer} onClick={(e) => e.stopPropagation()}>
            <div style={styles.drawerHeader}>
              <div>
                <div style={styles.drawerEyebrow}>
                  {(CHANNEL_META[selectedSummary.channel]?.label || 'Event').toUpperCase()} •{' '}
                  {selectedSummary.direction.toUpperCase()}
                </div>
                <h3 style={styles.drawerTitle}>{selectedSummary.summary}</h3>
              </div>
              <button onClick={() => setSelectedSummary(null)} style={styles.closeBtn}>
                ✕
              </button>
            </div>

            <div style={styles.drawerBody}>
              {detailLoading && <p style={{ color: '#6b7280' }}>Loading event details...</p>}

              {eventDetail?.error && (
                <p style={{ color: '#dc2626' }}>Could not load details: {eventDetail.error}</p>
              )}

              {eventDetail && !eventDetail.error && (
                <>
                  {/* Shared Metadata Grid */}
                  <div style={styles.metaGrid}>
                    <div>
                      <div style={styles.metaLabel}>Date & Time</div>
                      <div style={styles.metaValue}>{formatDateTime(eventDetail.timestamp)}</div>
                    </div>
                    <div>
                      <div style={styles.metaLabel}>Status</div>
                      <div style={styles.metaValue}>{eventDetail.status || '—'}</div>
                    </div>
                    <div>
                      <div style={styles.metaLabel}>Contact / Endpoint</div>
                      <div style={styles.metaValue}>{eventDetail.contactEndpoint || '—'}</div>
                    </div>
                    <div>
                      <div style={styles.metaLabel}>Agent</div>
                      <div style={styles.metaValue}>{eventDetail.agent || '—'}</div>
                    </div>
                  </div>

                  {/* CALL-SPECIFIC DETAILS */}
                  {eventDetail.channel === 'call' && eventDetail.payload && (
                    <div style={styles.section}>
                      <h4 style={styles.sectionTitle}>Call Recording</h4>
                      {eventDetail.payload.audioUrl ? (
                        <audio
                          controls
                          preload="none"
                          src={`${apiBase.replace(/\/api\/communicator$/, '')}${eventDetail.payload.audioUrl}`}
                          style={{ width: '100%', marginTop: 6 }}
                        />
                      ) : (
                        <p style={styles.mutedText}>No recording available for this call.</p>
                      )}

                      <div style={{ ...styles.metaGrid, marginTop: 16 }}>
                        <div>
                          <div style={styles.metaLabel}>Duration</div>
                          <div style={styles.metaValue}>{eventDetail.payload.duration}</div>
                        </div>
                        <div>
                          <div style={styles.metaLabel}>Call SID</div>
                          <div style={{ ...styles.metaValue, fontFamily: 'monospace', fontSize: 12 }}>
                            {eventDetail.payload.callSid || '—'}
                          </div>
                        </div>
                        <div>
                          <div style={styles.metaLabel}>First Agent</div>
                          <div style={styles.metaValue}>{eventDetail.payload.firstAgent || '—'}</div>
                        </div>
                        <div>
                          <div style={styles.metaLabel}>Last Agent</div>
                          <div style={styles.metaValue}>{eventDetail.payload.lastAgent || '—'}</div>
                        </div>
                      </div>

                      <h4 style={{ ...styles.sectionTitle, marginTop: 20 }}>Call Legs</h4>
                      {Array.isArray(eventDetail.payload.legs) && eventDetail.payload.legs.length > 0 ? (
                        <div style={styles.legsList}>
                          {eventDetail.payload.legs.map((leg, i) => (
                            <div key={i} style={styles.legItem}>
                              <div style={styles.legDot} />
                              <div style={{ flex: 1 }}>
                                <div style={styles.legTitle}>
                                  <strong>{leg.leg_type || leg.status || 'Event'}</strong>
                                  {leg.status && leg.leg_type ? ` (${leg.status})` : ''}
                                </div>
                                <div style={styles.legSub}>
                                  {leg.agent_id && <span>Agent: {leg.agent_id} • </span>}
                                  {leg.caller_id && <span>From: {leg.caller_id} • </span>}
                                  {leg.ddi && <span>DDI: {leg.ddi} • </span>}
                                  {leg.source && <span>Source: {leg.source}</span>}
                                </div>
                              </div>
                              <div style={styles.legTime}>{formatTimeOnly(leg.timestamp)}</div>
                            </div>
                          ))}
                        </div>
                      ) : (
                        <p style={styles.mutedText}>
                          {eventDetail.payload.notes || 'No call leg telemetry recorded.'}
                        </p>
                      )}
                    </div>
                  )}

                  {/* EMAIL-SPECIFIC DETAILS */}
                  {eventDetail.channel === 'email' && eventDetail.payload && (
                    <div style={styles.section}>
                      <div style={styles.emailHeaders}>
                        <div><strong>From:</strong> {eventDetail.payload.from || '—'}</div>
                        <div><strong>To:</strong> {eventDetail.payload.to || '—'}</div>
                        {eventDetail.payload.cc && (
                          <div><strong>CC:</strong> {eventDetail.payload.cc}</div>
                        )}
                      </div>
                      <h4 style={{ ...styles.sectionTitle, marginTop: 16 }}>Message</h4>
                      <pre style={styles.messageBody}>
                        {eventDetail.payload.bodyText || '(Empty email body)'}
                      </pre>
                    </div>
                  )}

                  {/* WHATSAPP / SMS / NOTE DETAILS */}
                  {['whatsapp', 'sms', 'note'].includes(eventDetail.channel) && eventDetail.payload && (
                    <div style={styles.section}>
                      <h4 style={styles.sectionTitle}>Message Content</h4>
                      <pre style={styles.messageBody}>
                        {eventDetail.payload.bodyText || '(No content)'}
                      </pre>
                    </div>
                  )}
                </>
              )}
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

const styles = {
  container: { fontFamily: 'system-ui, -apple-system, sans-serif', color: '#111827' },
  toolbar: {
    display: 'flex',
    flexWrap: 'wrap',
    justifyContent: 'space-between',
    alignItems: 'center',
    gap: 12,
    marginBottom: 16,
  },
  tabs: { display: 'flex', gap: 6 },
  tabBtn: {
    padding: '6px 12px',
    borderRadius: 6,
    border: '1px solid #d1d5db',
    background: '#fff',
    cursor: 'pointer',
    fontSize: 13,
    fontWeight: 500,
    color: '#374151',
  },
  tabBtnActive: {
    background: '#111827',
    color: '#fff',
    borderColor: '#111827',
  },
  rightControls: { display: 'flex', gap: 8, alignItems: 'center' },
  searchInput: {
    padding: '6px 10px',
    borderRadius: 6,
    border: '1px solid #d1d5db',
    fontSize: 13,
    width: 220,
  },
  refreshBtn: {
    padding: '6px 12px',
    borderRadius: 6,
    border: '1px solid #d1d5db',
    background: '#f9fafb',
    cursor: 'pointer',
    fontSize: 13,
  },
  listWrapper: {
    border: '1px solid #e5e7eb',
    borderRadius: 8,
    overflow: 'hidden',
    background: '#fff',
  },
  row: {
    display: 'flex',
    alignItems: 'center',
    gap: 12,
    padding: '12px 16px',
    borderBottom: '1px solid #f3f4f6',
    cursor: 'pointer',
    transition: 'background 0.15s',
  },
  rowSelected: { backgroundColor: '#eff6ff' },
  channelBadge: {
    width: 36,
    height: 36,
    borderRadius: 8,
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    fontSize: 16,
    flexShrink: 0,
  },
  rowMain: { flex: 1, minWidth: 0 },
  rowTopLine: { display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap' },
  summaryText: { fontWeight: 600, fontSize: 14, color: '#111827' },
  directionTag: {
    fontSize: 11,
    padding: '2px 6px',
    borderRadius: 4,
    background: '#f3f4f6',
    color: '#4b5563',
  },
  recordingTag: {
    fontSize: 11,
    padding: '2px 6px',
    borderRadius: 4,
    background: '#fef3c7',
    color: '#92400e',
    fontWeight: 500,
  },
  rowSubLine: { fontSize: 12, color: '#6b7280', marginTop: 3 },
  rowMeta: {
    display: 'flex',
    flexDirection: 'column',
    alignItems: 'flex-end',
    gap: 4,
    flexShrink: 0,
  },
  statusPill: {
    fontSize: 11,
    fontWeight: 600,
    padding: '2px 8px',
    borderRadius: 999,
    background: '#e5e7eb',
    color: '#374151',
  },
  timeText: { fontSize: 12, color: '#6b7280' },
  emptyState: {
    padding: 32,
    textAlign: 'center',
    color: '#6b7280',
    border: '1px solid #e5e7eb',
    borderRadius: 8,
    background: '#fff',
  },
  drawerBackdrop: {
    position: 'fixed',
    inset: 0,
    background: 'rgba(17, 24, 39, 0.45)',
    display: 'flex',
    justifyContent: 'flex-end',
    zIndex: 9999,
  },
  drawer: {
    width: '100%',
    maxWidth: 520,
    background: '#fff',
    height: '100%',
    display: 'flex',
    flexDirection: 'column',
    boxShadow: '-4px 0 20px rgba(0,0,0,0.15)',
  },
  drawerHeader: {
    padding: '16px 20px',
    borderBottom: '1px solid #e5e7eb',
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    gap: 12,
  },
  drawerEyebrow: { fontSize: 11, fontWeight: 700, color: '#6b7280', letterSpacing: '0.04em' },
  drawerTitle: { margin: '4px 0 0', fontSize: 17, fontWeight: 600 },
  closeBtn: {
    border: 'none',
    background: '#f3f4f6',
    borderRadius: 6,
    width: 30,
    height: 30,
    cursor: 'pointer',
    fontSize: 14,
  },
  drawerBody: { padding: 20, overflowY: 'auto', flex: 1 },
  metaGrid: {
    display: 'grid',
    gridTemplateColumns: '1fr 1fr',
    gap: 12,
    background: '#f9fafb',
    padding: 12,
    borderRadius: 8,
    border: '1px solid #e5e7eb',
  },
  metaLabel: { fontSize: 11, color: '#6b7280', textTransform: 'uppercase', fontWeight: 600 },
  metaValue: { fontSize: 13, color: '#111827', marginTop: 2, wordBreak: 'break-all' },
  section: { marginTop: 20 },
  sectionTitle: { margin: '0 0 8px', fontSize: 14, fontWeight: 600, color: '#111827' },
  mutedText: { fontSize: 13, color: '#6b7280', margin: 0 },
  legsList: { display: 'flex', flexDirection: 'column', gap: 10 },
  legItem: {
    display: 'flex',
    alignItems: 'flex-start',
    gap: 10,
    padding: '8px 12px',
    borderRadius: 6,
    border: '1px solid #e5e7eb',
    background: '#fff',
  },
  legDot: {
    width: 8,
    height: 8,
    borderRadius: '50%',
    background: '#2563eb',
    marginTop: 5,
    flexShrink: 0,
  },
  legTitle: { fontSize: 13, color: '#111827' },
  legSub: { fontSize: 12, color: '#6b7280', marginTop: 2 },
  legTime: { fontSize: 12, fontFamily: 'monospace', color: '#6b7280' },
  emailHeaders: {
    fontSize: 13,
    lineHeight: 1.6,
    background: '#f9fafb',
    padding: 12,
    borderRadius: 8,
    border: '1px solid #e5e7eb',
  },
  messageBody: {
    whiteSpace: 'pre-wrap',
    fontFamily: 'inherit',
    fontSize: 13,
    lineHeight: 1.5,
    background: '#f9fafb',
    padding: 12,
    borderRadius: 8,
    border: '1px solid #e5e7eb',
    margin: 0,
  },
};