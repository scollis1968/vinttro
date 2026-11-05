import React, { useState, useEffect } from 'react';
import { 
  User, Car, Home, Factory, Warehouse, ShoppingBag, 
  Phone, Mail, MapPin, Search, UserPlus, AlertCircle, RefreshCw 
} from 'lucide-react';
import * as Tooltip from '@radix-ui/react-tooltip';

// Helper to guarantee clean URL construction without double-slashes
function getApiUrl(endpoint) {
  const config = window.vinttroDashboardConfig || window.vinttroConfig || {};
  const rawBase = config.apiBase || 'http://localhost:3005/api';
  const cleanBase = rawBase.replace(/\/+$/, '');
  const cleanEndpoint = endpoint.replace(/^\/+/, '');
  return `${cleanBase}/${cleanEndpoint}`;
}

const ICON_MAP = {
  car: Car,
  home: Home,
  business: Factory,
  storage: Warehouse,
};

export default function AgentDashboard({ clientId, initialClientId }) {
  const safeInitialId = clientId || initialClientId || 'C-10492';
  const [currentClientId, setCurrentClientId] = useState(safeInitialId);
  const [clientData, setClientData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [activeTab, setActiveTab] = useState('details');
  const [errorState, setErrorState] = useState(null); // null | 'NOT_FOUND' | 'SERVER_ERROR'

  async function loadClient(id) {
    if (!id || id === 'undefined') {
      setErrorState('NOT_FOUND');
      setLoading(false);
      return;
    }

    setLoading(true);
    setErrorState(null);

    try {
      const config = window.vinttroConfig || {};
      const url = getApiUrl(`communicator/clients/${encodeURIComponent(id)}/summary`);
      console.log('Fetching from:', url);

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
      setActiveTab('details');
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

  // Communicator CTI switch listener
  useEffect(() => {
    function handleClientSwitch(event) {
      if (event.detail?.clientId) {
        console.log('Switching client view to:', event.detail.clientId);
        setCurrentClientId(event.detail.clientId);
      }
    }
    window.addEventListener('vinttro:switch-client', handleClientSwitch);
    return () => window.removeEventListener('vinttro:switch-client', handleClientSwitch);
  }, []);

  useEffect(() => {
    function handlePopState(event) {
      const params = new URLSearchParams(window.location.search);
      const idFromUrl = params.get('client_id');
      if (idFromUrl && idFromUrl !== currentClientId) {
        setCurrentClientId(idFromUrl);
      }
    }

    window.addEventListener('popstate', handlePopState);
    return () => window.removeEventListener('popstate', handlePopState);
  }, [currentClientId]);

  // Action Dispatchers for Agent Workflow
  const handleOpenSearch = () => {
    // Dispatches event to communicator or opens search modal
    window.dispatchEvent(new CustomEvent('vinttro:open-client-search', {
      detail: { attemptedId: currentClientId }
    }));
  };

  const handleCreateClient = () => {
    // Triggers new account modal or redirects to SuiteCRM new record
    window.dispatchEvent(new CustomEvent('vinttro:create-client-prompt', {
      detail: { attemptedId: currentClientId }
    }));
  };

  // 1. Loading State
  if (loading) {
    return (
      <div className="vt-p-12 vt-text-center vt-bg-white vt-rounded-lg vt-border vt-border-slate-200 vt-shadow-sm vt-my-4">
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
      <div className="vt-bg-white vt-rounded-lg vt-border vt-border-rose-200 vt-p-6 vt-my-4 vt-text-center">
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

  // 4. Normal Populated Dashboard (Details, Tabs, Opportunities)
  return (
    <div className="vt-bg-white vt-rounded-lg vt-shadow-sm vt-border vt-border-slate-200 vt-overflow-hidden vt-my-4">
      {/* Sticky Header */}
      <div className="vt-bg-slate-800 vt-text-white vt-px-6 vt-py-4 vt-flex vt-justify-between vt-items-center">
        <div>
          <h1 className="vt-text-xl vt-font-bold vt-m-0">{clientData.client_name}</h1>
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
          onClick={() => setActiveTab('details')}
          className={`vt-flex vt-items-center vt-gap-1.5 vt-px-3 vt-py-1.5 vt-rounded-md vt-text-sm vt-font-medium vt-cursor-pointer vt-border-0 ${
            activeTab === 'details' ? 'vt-bg-white vt-shadow-sm vt-text-blue-600' : 'vt-bg-transparent vt-text-slate-600 hover:vt-text-slate-900'
          }`}
        >
          <User className="vt-w-4 vt-h-4" />
          <span>Details</span>
        </button>

        {clientData.opportunities?.map((opp) => {
          const Icon = ICON_MAP[opp.type] || Factory;
          const isSelected = activeTab === opp.id;

          return (
            <Tooltip.Provider key={opp.id} delayDuration={150}>
              <Tooltip.Root>
                <Tooltip.Trigger asChild>
                  <button
                    type="button"
                    onClick={() => setActiveTab(opp.id)}
                    className={`vt-flex vt-items-center vt-gap-1.5 vt-px-3 vt-py-1.5 vt-rounded-md vt-text-sm vt-font-medium vt-whitespace-nowrap vt-cursor-pointer vt-border-0 ${
                      isSelected ? 'vt-bg-white vt-shadow-sm vt-text-blue-600' : 'vt-bg-transparent vt-text-slate-600 hover:vt-text-slate-900'
                    }`}
                  >
                    <Icon className="vt-w-4 vt-h-4 vt-text-slate-500" />
                    <span>{opp.tab_label}</span>
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
          onClick={() => setActiveTab('retail')}
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
      </div>

      {/* Pane Content */}
      <div className="vt-p-6 vt-min-h-[260px]">
        {activeTab === 'details' && (
          <div className="vt-space-y-4">
            <h3 className="vt-text-lg vt-font-semibold vt-text-slate-800 vt-m-0">Customer Details</h3>
            <div className="vt-space-y-2 vt-text-slate-600 vt-text-sm">
              <div className="vt-flex vt-items-center vt-gap-2">
                <Phone className="vt-w-4 vt-h-4 vt-text-slate-400" />
                <span>{clientData.details?.phone}</span>
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

        {activeTab !== 'details' && activeTab !== 'retail' && (() => {
          const opp = clientData.opportunities?.find(o => o.id === activeTab);
          if (!opp) return null;

          return (
            <div>
              <div className="vt-flex vt-justify-between vt-items-start vt-mb-4">
                <div>
                  <h2 className="vt-text-xl vt-font-bold vt-text-slate-800 vt-m-0">{opp.tab_tooltip}</h2>
                  <span className="vt-inline-block vt-mt-1 vt-px-2 vt-py-0.5 vt-rounded-full vt-text-xs vt-font-semibold vt-bg-green-100 vt-text-green-800">
                    {opp.status}
                  </span>
                </div>
                <div className="vt-text-right">
                  <div className="vt-text-xs vt-text-slate-500">Premium</div>
                  <div className="vt-text-lg vt-font-bold vt-text-slate-900">
                    £{typeof opp.premium === 'number' ? opp.premium.toFixed(2) : opp.premium}
                  </div>
                </div>
              </div>
              <pre className="vt-bg-slate-50 vt-p-4 vt-rounded-md vt-text-xs vt-text-slate-700 vt-overflow-x-auto vt-border vt-border-slate-200">
                {JSON.stringify(opp.details, null, 2)}
              </pre>
            </div>
          );
        })()}
      </div>
    </div>
  );
}