import React from 'react';
import OpportunityViewDispatcher from './OpportunityViewDispatcher';

export default function OpportunityShell({ opp, StatusPill }) {
  if (!opp) return null;

  return (
    <div className="vt-space-y-5">
      {/* 1. Universal Commercial Summary Bar */}
      <div className="vt-flex vt-justify-between vt-items-start vt-border-b vt-border-slate-200 vt-pb-4">
        <div>
          <div className="vt-flex vt-items-center vt-gap-2">
            <h2 className="vt-text-xl vt-font-bold vt-text-slate-800 vt-m-0">
              {opp.tab_tooltip || opp.title || opp.name}
            </h2>
            <StatusPill status={opp.status} />
          </div>
          <div className="vt-text-xs vt-text-slate-500 vt-mt-1 vt-flex vt-gap-3">
            <span>Ref: <strong className="vt-text-slate-700">{opp.id}</strong></span>
            {opp.insurer && <span>Insurer: <strong className="vt-text-slate-700">{opp.insurer}</strong></span>}
            {opp.renewal_date && <span>Renewal: <strong className="vt-text-slate-700">{opp.renewal_date}</strong></span>}
          </div>
        </div>

        <div className="vt-text-right">
          <div className="vt-text-xs vt-text-slate-500">Premium</div>
          <div className="vt-text-xl vt-font-extrabold vt-text-slate-900">
            £{typeof opp.premium === 'number' ? opp.premium.toFixed(2) : (opp.premium || '0.00')}
          </div>
        </div>
      </div>

      {/* 2. Type-Specific Body */}
      <OpportunityViewDispatcher type={opp.type} details={opp.details} />
    </div>
  );
}