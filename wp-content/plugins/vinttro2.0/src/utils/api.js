// wp-content/plugins/vinttro2.0/src/utils/api.js
export function getApiUrl(endpoint = '') {
  const config = window.vinttroConfig || window.vinttroDashboardConfig || {};
  const rawBase = config.apiBase || 'http://localhost:3005/api';
  const cleanBase = rawBase.replace(/\/+$/, '');
  const cleanEndpoint = endpoint.replace(/^\/+/, '');
  return cleanEndpoint ? `${cleanBase}/${cleanEndpoint}` : cleanBase;
}

