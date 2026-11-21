// wp-content/plugins/vinttro2.0/src/ClientView.jsx
import React, { useState, useEffect, useRef } from 'react';
import {
  User, Car, Home, Factory, Warehouse, ShoppingBag, Clock,
  Phone, Mail, MapPin, Search, UserPlus, AlertCircle, RefreshCw
} from 'lucide-react';
import * as Tooltip from '@radix-ui/react-tooltip';
import OpportunityShell from './components/opportunities/OpportunityShell';
import EventsTimeline from './components/EventsTimeline';
import { getApiUrl } from './utils/api';

const ICON_MAP = {
  car: Car,
  home: Home,
  business: Factory,
  storage: Warehouse,
};

const STATUS_CONFIG = {
  live: { label: 'Live', classes: 'vt-bg-emerald-100 vt-text-emerald-700 vt-border-emerald-200' },
  active: { label: 'Active', classes: 'vt-bg-emerald-100 vt-text-emerald-700 vt-border-emerald-200' },
  won: { label: 'Won', classes: 'vt-bg-emerald-100 vt-text-emerald-700 vt-border-emerald-200' },
  renewal: { label: 'Renewal', classes: 'vt-bg-amber-100 vt-text-amber-800 vt-border-amber-200' },
  renewal_due: { label: 'Renewal', classes: 'vt-bg-amber-100 vt-text-amber-800 vt-border-amber-200' },
  quoted: { label: 'Quoted', classes: 'vt-bg-amber-50 vt-text-amber-700 vt-border-amber-200' },
  in_progress: { label: 'Working', classes: 'vt-bg-blue-100 vt-text-blue-700 vt-border-blue-200' },
  pending: { label: 'Pending', classes: 'vt-bg-blue-100 vt-text-blue-700 vt-border-blue-200' },
  at_risk: { label: 'At Risk', classes: 'vt-bg-rose-100 vt-text-rose-700 vt-border-rose-200' },
  cancelled: { label: 'Cancelled', classes: 'vt-bg-rose-100 vt-text-rose-700 vt-border-rose-200' },
  lost: { label: 'Lost', classes: 'vt-bg-slate-100 vt-text-slate-500 vt-border-slate-200' },
  ntu: { label: 'NTU', classes: 'vt-bg-slate-100 vt-text-slate-400 vt-border-slate-200' },
  archived: { label: 'Archived', classes: 'vt-bg-slate-100 vt-text-slate-400 vt-border-slate-200' },
};

function StatusPill({ status }) {
  if (!status) return null;

  const key = String(status).toLowerCase().trim().replace(/[\s-]+/g, '_');
  const config = STATUS_CONFIG[key] || {
    label: status,
    classes: 'vt-bg-slate-100 vt-text-slate-600 vt-border-slate-200',
  };

  return (
    <span className={`vt-inline-flex vt-items-center vt-px-1.5 vt-py-0.2 vt-text-[10px] vt-font-semibold vt-rounded-full vt-border vt-ml-1 ${config.classes}`}>
      {config.label}
    </span>
  );
}

function getUrlParams() {
  const params = new URLSearchParams(window.location.search);
  return {
    clientId: params.get('client_id'),
    oppId: params.get('opp_id'),
  };
}

export default function ClientView({ clientId, initialClientId, initialOppId }) {
  const initialUrl = getUrlParams();
  const safeInitialId = clientId || initialClientId || initialUrl.clientId || 'C-10492';

  const [currentClientId, setCurrentClientId] = useState(safeInitialId);
  const [clientData, setClientData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [activeTab, setActiveTab] = useState('details');
  const [errorState, setErrorState] = useState(null); // null | 'NOT_FOUND' | 'SERVER_ERROR'

  // Holds the target opportunity ID to focus once clientData finishes loading
  const pendingOppIdRef = useRef(initialOppId || initialUrl.oppId || null);
  const callContextRef = useRef({ callId: null, agentId: null });

  async function loadClient(id) {
    if (!id || id === 'undefined') {
      setErrorState('NOT_FOUND');
      setLoading(false);
      return;
    }

    setLoading(true);
    setErrorState(null);

    try {
      const url = getApiUrl(`communicator/clients/${encodeURIComponent(id)}/summary`);
      const res = await fetch(url);

      if (res.status === 404) {
        setErrorState('NOT_FOUND');
        setClientData(null);
        return;
      }

      if (!res.ok) {
        throw new Error(`API error HTTP ${res.status}`);
      }

      const json = await res.json();
      setClientData(json);

      // Select the requested opportunity tab if opp_id was passed, otherwise default to 'details'
      const targetOppId = pendingOppIdRef.current;
      pendingOppIdRef.current = null;

      const matchedOpp = targetOppId
        ? json.opportunities?.find((o) => String(o.id) === String(targetOppId))
        : null;

      setActiveTab(matchedOpp ? matchedOpp.id : 'details');
    } catch (err) {
      console.error('Failed to retrieve client:', err.message);
      setErrorState('SERVER_ERROR');
      setClientData(null);
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    loadClient(currentClientId);
  }, [currentClientId]);

  // 1. Header Search Selection Listener (switches tabs/clients in-place without a full page reload)
  useEffect(() => {
    function handleHeaderSearchSelect(event) {
      const hit = event.detail;
      if (!hit?.client_id) return;

      // Cancel default full-page navigation in visp-header-search.js
      event.preventDefault();

      const nextOppId = hit.match?.opportunity_id || null;

      // Keep browser URL in sync so refresh/back button preserves state
      const nextUrl = new URL(window.location.href);
      nextUrl.searchParams.set('client_id', hit.client_id);
      if (nextOppId) {
        nextUrl.searchParams.set('opp_id', nextOppId);
      } else {
        nextUrl.searchParams.delete('opp_id');
      }
      window.history.pushState({}, '', nextUrl.toString());

      // Same client already loaded -> switch tab immediately
      if (String(hit.client_id) === String(currentClientId) && clientData) {
        const matchedOpp = nextOppId
          ? clientData.opportunities?.find((o) => String(o.id) === String(nextOppId))
          : null;
        setActiveTab(matchedOpp ? matchedOpp.id : 'details');
        return;
      }

      // Different client -> queue target opportunity tab and load new client
      pendingOppIdRef.current = nextOppId;
      setCurrentClientId(hit.client_id);
    }

    document.addEventListener('visp:client-selected', handleHeaderSearchSelect);
    return () => document.removeEventListener('visp:client-selected', handleHeaderSearchSelect);
  }, [currentClientId, clientData]);

  // 2. Communicator CTI Switch Listener
  useEffect(() => {
    function handleClientSwitch(event) {
      if (event.detail?.callId) {
        callContextRef.current.callId = event.detail.callId;
      }
      if (event.detail?.agentId) {
        callContextRef.current.agentId = event.detail.agentId;
      }
      if (event.detail?.clientId) {
        pendingOppIdRef.current = event.detail?.oppId || null;
        setCurrentClientId(event.detail.clientId);
      }
    }

    window.addEventListener('vinttro:switch-client', handleClientSwitch);
    return () => window.removeEventListener('vinttro:switch-client', handleClientSwitch);
  }, []);

  // 3. Browser Back / Forward Button Sync
  useEffect(() => {
    function handlePopState() {
      const { clientId: idFromUrl, oppId: oppFromUrl } = getUrlParams();
      if (idFromUrl && String(idFromUrl) !== String(currentClientId)) {
        pendingOppIdRef.current = oppFromUrl;
        setCurrentClientId(idFromUrl);
      } else if (oppFromUrl && clientData?.opportunities?.some((o) => String(o.id) === String(oppFromUrl))) {
        setActiveTab(oppFromUrl);
      } else if (!oppFromUrl) {
        setActiveTab('details');
      }
    }

    window.addEventListener('popstate', handlePopState);
    return () => window.removeEventListener('popstate', handlePopState);
  }, [currentClientId, clientData]);

  const handleTabSelect = (tabId, isOpportunity = false) => {
    setActiveTab(tabId);
    const nextUrl = new URL(window.location.href);
    if (isOpportunity) {
      nextUrl.searchParams.set('opp_id', tabId);
    } else {
      nextUrl.searchParams.delete('opp_id');
    }
    window.history.replaceState({}, '', nextUrl.toString());
  };

  const handleOpenSearch = () => {
    const headerInput = document.getElementById('visp-search-input');
    if (headerInput) {
      headerInput.focus();
      headerInput.select();
    }
    window.dispatchEvent(new CustomEvent('vinttro:open-client-search', {
      detail: { attemptedId: currentClientId }
    }));
  };

  const handleCreateClient = () => {
    window.dispatchEvent(new CustomEvent('vinttro:create-client-prompt', {
      detail: { attemptedId: currentClientId }
    }));
  };

  // 1. Loading State
  if (loading) {
    return (
      <div className="vt-p-12 vt-text-center vt-bg-white vt-rounded-b-lg vt-border vt-border-slate-200 vt-shadow-sm vt-my-4">
        <RefreshCw className="vt-w-6 vt-h-6 vt-animate-spin vt-mx-auto vt-text-blue-600 vt-mb-2" />
        <div className="vt-text-slate-600 vt-font-medium vt-text-sm">Fetching Client Details from Redis...</div>
      </div>
    );
  }

  // 2. Client Not Found State
  if (errorState === 'NOT_FOUND') {
    return (
      <div className="vt-bg-white vt-rounded-lg vt-border vt-border-slate-200 vt-shadow-sm vt-p-8 vt-my-4 vt-text-center">
        <div className="vt-w-12 vt-h-12 vt-bg-amber-100 vt-text-amber-600 vt-rounded-full vt-flex vt-items-center vt-justify-center vt-mx-auto vt-mb-4">
          <AlertCircle className="vt-w-6 vt-h-6" />
        </div>
        <h2 className="vt-text-xl vt-font-bold vt-text-slate-800 vt-m-0">Client Record Not Found</h2>
        <p className="vt-text-slate-500 vt-text-sm vt-mt-1 vt-mb-6 vt-max-w-md vt-mx-auto">
          No cached summary or SuiteCRM match found for ID: <strong className="vt-text-slate-700">"{currentClientId}"</strong>.
        </p>

        <div className="vt-flex vt-justify-center vt-items-center vt-gap-3">
          <button
            type="button"
            onClick={handleOpenSearch}
            className="vt-flex vt-items-center vt-gap-2 vt-bg-white hover:vt-bg-slate-50 vt-text-slate-700 vt-border vt-border-slate-300 vt-px-4 vt-py-2 vt-rounded-md vt-text-sm vt-font-medium vt-cursor-pointer vt-transition-colors"
          >
            <Search className="vt-w-4 vt-h-4 text-slate-500" />
            Search Clients
          </button>

          <button
            type="button"
            onClick={handleCreateClient}
            className="vt-flex vt-items-center vt-gap-2 vt-bg-blue-600 hover:vt-bg-blue-500 vt-text-white vt-border-0 vt-px-4 vt-py-2 vt-rounded-md vt-text-sm vt-font-medium vt-cursor-pointer vt-transition-colors"
          >
            <UserPlus className="vt-w-4 vt-h-4" />
            Create New Client
          </button>
        </div>
      </div>
    );
  }

  // 3. API Connection Error State
  if (errorState === 'SERVER_ERROR' || !clientData) {
    return (
      <div className="vt-bg-white vt-rounded-b-lg vt-border vt-border-rose-200 vt-p-6 vt-my-4 vt-text-center">
        <div className="vt-text-rose-600 vt-font-semibold vt-text-sm">Unable to connect to Vinttro API Service</div>
        <p className="vt-text-slate-500 vt-text-xs vt-mt-1 vt-mb-3">Ensure Node.js and Redis are running.</p>
        <button
          onClick={() => loadClient(currentClientId)}
          className="vt-px-3 vt-py-1.5 vt-bg-slate-100 hover:vt-bg-slate-200 vt-text-slate-700 vt-text-xs vt-font-medium vt-rounded vt-border vt-border-slate-300 vt-cursor-pointer"
        >
          Retry Connection
        </button>
      </div>
    );
  }

  const eventsAccountId =
    clientData.suitecrm_id || clientData.account_id || clientData.client_id;

  const displayClientName =
    clientData.is_private && !/\(private\)/i.test(clientData.client_name || '')
      ? `${clientData.client_name} (Private)`
      : clientData.client_name;

  // 4. Normal Populated Client View
  return (
    <div className="vt-bg-white vt-rounded-b-lg vt-shadow-sm vt-border vt-border-slate-200 vt-overflow-hidden vt-my-4">
      {/* Sticky Header */}
      <div className="vt-bg-slate-800 vt-text-white vt-px-6 vt-py-4 vt-flex vt-justify-between vt-items-center">
        <div>
          <h1 className="vt-text-xl vt-font-bold vt-m-0">{displayClientName}</h1>
          <span className="vt-text-slate-300 vt-text-sm">Client Ref: {clientData.client_id}</span>
        </div>
        <button
          type="button"
          className="vt-bg-blue-600 hover:vt-bg-blue-500 vt-text-white vt-px-4 vt-py-2 vt-rounded vt-text-sm vt-font-medium vt-cursor-pointer vt-border-0"
        >
          + New Quote
        </button>
      </div>

      {/* Tabs */}
      <div className="vt-flex vt-items-center vt-gap-1 vt-border-b vt-border-slate-200 vt-overflow-x-auto vt-px-4 vt-py-2 vt-bg-slate-50">
        <button
          type="button"
          onClick={() => handleTabSelect('details')}
          className={`vt-flex vt-items-center vt-gap-1.5 vt-px-3 vt-py-1.5 vt-rounded-md vt-text-sm vt-font-medium vt-cursor-pointer vt-border-0 ${
            activeTab === 'details' ? 'vt-bg-white vt-shadow-sm vt-text-blue-600' : 'vt-bg-transparent vt-text-slate-600 hover:vt-text-slate-900'
          }`}
        >
          <User className="vt-w-4 vt-h-4" />
          <span>Details</span>
        </button>

        {clientData.opportunities?.map((opp) => {
          const Icon = ICON_MAP[opp.type] || Factory;
          const isSelected = String(activeTab) === String(opp.id);

          return (
            <Tooltip.Provider key={opp.id} delayDuration={150}>
              <Tooltip.Root>
                <Tooltip.Trigger asChild>
                  <button
                    type="button"
                    onClick={() => handleTabSelect(opp.id, true)}
                    className={`vt-flex vt-items-center vt-gap-1.5 vt-px-3 vt-py-1.5 vt-rounded-md vt-text-sm vt-font-medium vt-whitespace-nowrap vt-cursor-pointer vt-border-0 ${
                      isSelected ? 'vt-bg-white vt-shadow-sm vt-text-blue-600' : 'vt-bg-transparent vt-text-slate-600 hover:vt-text-slate-900'
                    }`}
                  >
                    <Icon className="vt-w-4 vt-h-4 vt-text-slate-500" />
                    <span>{opp.tab_label}</span>
                    <StatusPill status={opp.status} />
                  </button>
                </Tooltip.Trigger>
                <Tooltip.Portal>
                  <Tooltip.Content
                    side="bottom"
                    sideOffset={6}
                    className="vt-bg-slate-900 vt-text-white vt-text-xs vt-px-2.5 vt-py-1.5 vt-rounded vt-shadow-lg vt-z-50 vt-max-w-xs"
                  >
                    {opp.tab_tooltip}
                    <Tooltip.Arrow className="vt-fill-slate-900" />
                  </Tooltip.Content>
                </Tooltip.Portal>
              </Tooltip.Root>
            </Tooltip.Provider>
          );
        })}

        <button
          type="button"
          onClick={() => handleTabSelect('retail')}
          className={`vt-flex vt-items-center vt-gap-1.5 vt-px-3 vt-py-1.5 vt-rounded-md vt-text-sm vt-font-medium vt-cursor-pointer vt-border-0 ${
            activeTab === 'retail' ? 'vt-bg-white vt-shadow-sm vt-text-blue-600' : 'vt-bg-transparent vt-text-slate-600 hover:vt-text-slate-900'
          }`}
        >
          <ShoppingBag className="vt-w-4 vt-h-4" />
          <span>Retail</span>
          {clientData.retail?.active_offers_count > 0 && (
            <span className="vt-ml-1 vt-px-1.5 vt-py-0.5 vt-bg-emerald-100 vt-text-emerald-700 vt-text-xs vt-font-semibold vt-rounded-full">
              {clientData.retail.active_offers_count}
            </span>
          )}
        </button>

        <button
          type="button"
          onClick={() => handleTabSelect('events')}
          className={`vt-flex vt-items-center vt-gap-1.5 vt-px-3 vt-py-1.5 vt-rounded-md vt-text-sm vt-font-medium vt-whitespace-nowrap vt-cursor-pointer vt-border-0 ${
            activeTab === 'events' ? 'vt-bg-white vt-shadow-sm vt-text-blue-600' : 'vt-bg-transparent vt-text-slate-600 hover:vt-text-slate-900'
          }`}
        >
          <Clock className="vt-w-4 vt-h-4" />
          <span>Events</span>
        </button>
      </div>

      {/* Pane Content */}
      <div className="vt-p-6 vt-min-h-[260px]">
        {activeTab === 'details' && (
          <div className="vt-space-y-4">
            <h3 className="vt-text-lg vt-font-semibold vt-text-slate-800 vt-m-0">Customer Details</h3>
            <div className="vt-space-y-2 vt-text-slate-600 vt-text-sm">
              <div className="vt-flex vt-items-center vt-gap-2">
                <Phone className="vt-w-4 vt-h-4 vt-text-slate-400" />
                {clientData.details?.phone ? (
                  <a
                    href={`tel:${clientData.details.phone}`}
                    data-client-id={clientData.client_id}
                    data-client-name={clientData.client_name}
                    title="Call client via Vinttro Softphone"
                    className="vt-text-blue-600 hover:vt-underline vt-font-medium vt-cursor-pointer"
                  >
                    {clientData.details.phone}
                  </a>
                ) : (
                  <span className="vt-text-slate-400">No phone number</span>
                )}
              </div>
              <div className="vt-flex vt-items-center vt-gap-2">
                <Mail className="vt-w-4 vt-h-4 vt-text-slate-400" />
                <span>{clientData.details?.email}</span>
              </div>
              <div className="vt-flex vt-items-center vt-gap-2">
                <MapPin className="vt-w-4 vt-h-4 vt-text-slate-400" />
                <span>{clientData.details?.primary_address}</span>
              </div>
            </div>
          </div>
        )}

        {activeTab === 'retail' && (
          <div className="vt-space-y-4">
            <h3 className="vt-text-lg vt-font-semibold vt-text-slate-800 vt-m-0">Retail Offers</h3>
            <div className="vt-grid vt-gap-3">
              {clientData.retail?.offers?.map(offer => (
                <div key={offer.id} className="vt-p-4 vt-bg-blue-50 vt-border vt-border-blue-100 vt-rounded-md">
                  <span className="vt-font-semibold vt-text-blue-900">{offer.title}:</span>{' '}
                  <span className="vt-text-blue-700 vt-text-sm">{offer.discount}</span>
                </div>
              ))}
            </div>
          </div>
        )}

        {activeTab === 'events' && (
          <EventsTimeline
            apiBase={getApiUrl('communicator')}
            accountId={eventsAccountId}
          />
        )}

        {activeTab !== 'details' && activeTab !== 'retail' && activeTab !== 'events' && (() => {
          const opp = clientData.opportunities?.find(o => String(o.id) === String(activeTab));
          if (!opp) return null;

          return <OpportunityShell opp={opp} StatusPill={StatusPill} />;
        })()}
      </div>
    </div>
  );
}