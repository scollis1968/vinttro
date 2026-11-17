import React from 'react';
import { Warehouse, KeyRound, Clock, ShieldCheck } from 'lucide-react';

export default function StorageOpportunityView({ details }) {
  const d = details || {};

  return (
    <div className="vt-space-y-4">
      <div className="vt-grid vt-grid-cols-2 md:vt-grid-cols-4 vt-gap-3">
        <div className="vt-p-3 vt-bg-slate-50 vt-rounded-lg vt-border vt-border-slate-200">
          <div className="vt-text-xs vt-text-slate-500 vt-flex vt-items-center vt-gap-1">
            <Warehouse className="vt-w-3.5 vt-h-3.5" /> Facility
          </div>
          <div className="vt-text-sm vt-font-bold vt-text-slate-800 vt-mt-1">{d.facility_name || 'Main Unit'}</div>
        </div>

        <div className="vt-p-3 vt-bg-slate-50 vt-rounded-lg vt-border vt-border-slate-200">
          <div className="vt-text-xs vt-text-slate-500 vt-flex vt-items-center vt-gap-1">
            <KeyRound className="vt-w-3.5 vt-h-3.5" /> Bay Number
          </div>
          <div className="vt-text-sm vt-font-bold vt-text-slate-800 vt-mt-1">{d.bay_number || 'Unallocated'}</div>
        </div>

        <div className="vt-p-3 vt-bg-slate-50 vt-rounded-lg vt-border vt-border-slate-200">
          <div className="vt-text-xs vt-text-slate-500 vt-flex vt-items-center vt-gap-1">
            <ShieldCheck className="vt-w-3.5 vt-h-3.5" /> Climate Controlled
          </div>
          <div className="vt-text-sm vt-font-bold vt-text-slate-800 vt-mt-1">{d.climate_controlled ? 'Yes' : 'No'}</div>
        </div>

        <div className="vt-p-3 vt-bg-slate-50 vt-rounded-lg vt-border vt-border-slate-200">
          <div className="vt-text-xs vt-text-slate-500 vt-flex vt-items-center vt-gap-1">
            <Clock className="vt-w-3.5 vt-h-3.5" /> Term
          </div>
          <div className="vt-text-sm vt-font-bold vt-text-slate-800 vt-mt-1">{d.storage_term || 'Ongoing'}</div>
        </div>
      </div>
    </div>
  );
}