import React from 'react';
import { Home, ShieldCheck, MapPin, Key } from 'lucide-react';

export default function HomeOpportunityView({ details }) {
  const d = details || {};

  return (
    <div className="vt-space-y-4">
      <div className="vt-grid vt-grid-cols-2 md:vt-grid-cols-4 vt-gap-3">
        <div className="vt-p-3 vt-bg-slate-50 vt-rounded-lg vt-border vt-border-slate-200">
          <div className="vt-text-xs vt-text-slate-500 vt-flex vt-items-center vt-gap-1">
            <Home className="vt-w-3.5 vt-h-3.5" /> Property Type
          </div>
          <div className="vt-text-sm vt-font-bold vt-text-slate-800 vt-mt-1">{d.property_type || 'Residential'}</div>
        </div>

        <div className="vt-p-3 vt-bg-slate-50 vt-rounded-lg vt-border vt-border-slate-200">
          <div className="vt-text-xs vt-text-slate-500 vt-flex vt-items-center vt-gap-1">
            <ShieldCheck className="vt-w-3.5 vt-h-3.5" /> Buildings Sum
          </div>
          <div className="vt-text-sm vt-font-bold vt-text-slate-800 vt-mt-1">
            {d.buildings_sum ? `£${Number(d.buildings_sum).toLocaleString()}` : '—'}
          </div>
        </div>

        <div className="vt-p-3 vt-bg-slate-50 vt-rounded-lg vt-border vt-border-slate-200">
          <div className="vt-text-xs vt-text-slate-500 vt-flex vt-items-center vt-gap-1">
            <Key className="vt-w-3.5 vt-h-3.5" /> Contents Sum
          </div>
          <div className="vt-text-sm vt-font-bold vt-text-slate-800 vt-mt-1">
            {d.contents_sum ? `£${Number(d.contents_sum).toLocaleString()}` : '—'}
          </div>
        </div>

        <div className="vt-p-3 vt-bg-slate-50 vt-rounded-lg vt-border vt-border-slate-200">
          <div className="vt-text-xs vt-text-slate-500 vt-flex vt-items-center vt-gap-1">
            <MapPin className="vt-w-3.5 vt-h-3.5" /> Postcode
          </div>
          <div className="vt-text-sm vt-font-bold vt-text-slate-800 vt-mt-1">{d.postcode || '—'}</div>
        </div>
      </div>
    </div>
  );
}