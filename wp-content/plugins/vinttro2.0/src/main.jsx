import React from 'react';
import ReactDOM from 'react-dom/client';
import AgentDashboard from './AgentDashboard';
import './index.css';

function initDashboard() {
  const container = document.getElementById('vinttro-client-dashboard');
  if (container) {
    const clientId = container.getAttribute('data-client-id') || 'C-10492';
    const root = ReactDOM.createRoot(container);
    root.render(
      <React.StrictMode>
        <AgentDashboard initialClientId={clientId} />
      </React.StrictMode>
    );
  }
}

// Safely mount whether DOMContentLoaded has already passed or not
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initDashboard);
} else {
  initDashboard();
}