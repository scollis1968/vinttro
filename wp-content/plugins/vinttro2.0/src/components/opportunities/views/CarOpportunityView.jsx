import React from 'react';
import { Car, Shield, Gauge, Calendar } from 'lucide-react';

export default function CarOpportunityView({ details }) {
  const d = details || {};

  return (
    <div className="vt-space-y-4">
      <div className="vt-grid vt-grid-cols-2 md:vt-grid-cols-4 vt-gap-3">
        <div className="vt-p-3 vt-bg-slate-50 vt-rounded-lg vt-border vt-border-slate-200">
          <div className="vt-text-xs vt-text-slate-500 vt-flex vt-items-center vt-gap-1">
            <Car className="vt-w-3.5 vt-h-3.5" /> Registration
          </div>
          <div className="vt-text-sm vt-font-bold vt-text-slate-800 vt-mt-1">{d.registration || '—'}</div>
        </div>

        <div className="vt-p-3 vt-bg-slate-50 vt-rounded-lg vt-border vt-border-slate-200">
          <div className="vt-text-xs vt-text-slate-500 vt-flex vt-items-center vt-gap-1">
            <Shield className="vt-w-3.5 vt-h-3.5" /> Agreed Value
          </div>
          <div className="vt-text-sm vt-font-bold vt-text-slate-800 vt-mt-1">
            {d.agreed_value ? `£${Number(d.agreed_value).toLocaleString()}` : '—'}
          </div>
        </div>

        <div className="vt-p-3 vt-bg-slate-50 vt-rounded-lg vt-border vt-border-slate-200">
          <div className="vt-text-xs vt-text-slate-500 vt-flex vt-items-center vt-gap-1">
            <Gauge className="vt-w-3.5 vt-h-3.5" /> Annual Mileage
          </div>
          <div className="vt-text-sm vt-font-bold vt-text-slate-800 vt-mt-1">{d.annual_mileage || '—'}</div>
        </div>

        <div className="vt-p-3 vt-bg-slate-50 vt-rounded-lg vt-border vt-border-slate-200">
          <div className="vt-text-xs vt-text-slate-500 vt-flex vt-items-center vt-gap-1">
            <Calendar className="vt-w-3.5 vt-h-3.5" /> Year of Make
          </div>
          <div className="vt-text-sm vt-font-bold vt-text-slate-800 vt-mt-1">{d.year || '—'}</div>
        </div>
      </div>

      {/* Additional specific sections like modifications or driver history */}
      {d.modifications && (
        <div className="vt-p-3 vt-bg-amber-50 vt-border vt-border-amber-200 vt-rounded-lg vt-text-xs vt-text-amber-900">
          <strong>Declared Modifications:</strong> {d.modifications}
        </div>
      )}
    </div>
  );
}