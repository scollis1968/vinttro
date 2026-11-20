// wp-content/plugins/vinttro2.0/src/main.jsx
import React from 'react';
import { createRoot } from 'react-dom/client';
import './index.css';
import AgentDashboard from './AgentDashboard';
import EventsTimeline from './components/EventsTimeline';
import { getApiUrl } from './utils/api';

// 1. Client Dashboard Page ([vinttro_client_screen])
const dashboardEl = document.getElementById('vinttro-client-dashboard');
if (dashboardEl) {
  const clientId = dashboardEl.dataset.clientId || 'C-10492';
  createRoot(dashboardEl).render(<AgentDashboard initialClientId={clientId} />);
}

// 2. Standalone Event List Page ([vinttro_event_list])
const eventListEl = document.getElementById('vinttro-event-list');
if (eventListEl) {
  const accountId = eventListEl.dataset.accountId || null;
  createRoot(eventListEl).render(
    <EventsTimeline
      apiBase={getApiUrl('communicator')}
      accountId={accountId}
    />
  );
}