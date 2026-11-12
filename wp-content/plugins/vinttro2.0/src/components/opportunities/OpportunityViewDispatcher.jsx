import React from 'react';
import CarOpportunityView from './views/CarOpportunityView';
import HomeOpportunityView from './views/HomeOpportunityView';
import StorageOpportunityView from './views/StorageOpportunityView';
import BusinessOpportunityView from './views/BusinessOpportunityView';

const VIEW_REGISTRY = {
  car: CarOpportunityView,
  motor: CarOpportunityView,
  vehicle: CarOpportunityView,
  home: HomeOpportunityView,
  household: HomeOpportunityView,
  storage: StorageOpportunityView,
  warehouse: StorageOpportunityView,
  business: BusinessOpportunityView,
  factory: BusinessOpportunityView,
};

export default function OpportunityViewDispatcher({ type, details }) {
  const normalizedType = String(type || '').toLowerCase().trim();
  const Component = VIEW_REGISTRY[normalizedType];

  if (!Component) {
    return (
      <div className="vt-bg-slate-50 vt-p-4 vt-rounded-lg vt-border vt-border-slate-200">
        <div className="vt-text-xs vt-font-semibold vt-text-slate-500 vt-mb-2">Opportunity Data</div>
        <pre className="vt-text-xs vt-text-slate-700 vt-m-0 vt-overflow-x-auto">
          {JSON.stringify(details || {}, null, 2)}
        </pre>
      </div>
    );
  }

  return <Component details={details} />;
}