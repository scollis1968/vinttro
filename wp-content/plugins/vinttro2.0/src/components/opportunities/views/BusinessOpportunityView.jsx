import React from 'react';
import { Factory, Briefcase, Users, PoundSterling } from 'lucide-react';

export default function BusinessOpportunityView({ details }) {
  const d = details || {};

  return (
    <div className="vt-space-y-4">
      <div className="vt-grid vt-grid-cols-2 md:vt-grid-cols-4 vt-gap-3">
        <div className="vt-p-3 vt-bg-slate-50 vt-rounded-lg vt-border vt-border-slate-200">
          <div className="vt-text-xs vt-text-slate-500 vt-flex vt-items-center vt-gap-1">
            <Briefcase className="vt-w-3.5 vt-h-3.5" /> Trade / Sector
          </div>
          <div className="vt-text-sm vt-font-bold vt-text-slate-800 vt-mt-1">{d.trade || d.sector || 'Commercial'}</div>
        </div>

        <div className="vt-p-3 vt-bg-slate-50 vt-rounded-lg vt-border vt-border-slate-200">
          <div className="vt-text-xs vt-text-slate-500 vt-flex vt-items-center vt-gap-1">
            <PoundSterling className="vt-w-3.5 vt-h-3.5" /> Turnover
          </div>
          <div className="vt-text-sm vt-font-bold vt-text-slate-800 vt-mt-1">
            {d.turnover ? `£${Number(d.turnover).toLocaleString()}` : '—'}
          </div>
        </div>

        <div className="vt-p-3 vt-bg-slate-50 vt-rounded-lg vt-border vt-border-slate-200">
          <div className="vt-text-xs vt-text-slate-500 vt-flex vt-items-center vt-gap-1">
            <Factory className="vt-w-3.5 vt-h-3.5" /> Public Liability
          </div>
          <div className="vt-text-sm vt-font-bold vt-text-slate-800 vt-mt-1">{d.public_liability || '£2,000,000'}</div>
        </div>

        <div className="vt-p-3 vt-bg-slate-50 vt-rounded-lg vt-border vt-border-slate-200">
          <div className="vt-text-xs vt-text-slate-500 vt-flex vt-items-center vt-gap-1">
            <Users className="vt-w-3.5 vt-h-3.5" /> Employees
          </div>
          <div className="vt-text-sm vt-font-bold vt-text-slate-800 vt-mt-1">{d.employee_count || '—'}</div>
        </div>
      </div>
    </div>
  );
}