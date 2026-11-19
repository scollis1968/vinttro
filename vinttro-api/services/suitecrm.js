// vinttro-api/services/suitecrm.js
const redisClient = require('./redis'); // Centralized Redis DB 1

const SUITECRM_URL = (process.env.SUITECRM_URL || '').replace(/\/$/, '');
const CLIENT_ID = process.env.SUITECRM_CLIENT_ID;
const CLIENT_SECRET = process.env.SUITECRM_CLIENT_SECRET;
const USERNAME = process.env.SUITECRM_USERNAME;
const PASSWORD = process.env.SUITECRM_PASSWORD;

/**
 * Fetch or retrieve cached SuiteCRM OAuth2 Access Token
 */
async function getAccessToken() {
    const redisKey = 'suitecrm:token';
    
    // 1. Check Redis DB 1 cache
    try {
        const cachedToken = await redisClient.get(redisKey);
        if (cachedToken) return cachedToken;
    } catch (err) {
        console.warn('[SuiteCRM Auth Warning] Failed to read token from Redis:', err.message);
    }

    // 2. Obtain new token from SuiteCRM
    console.log('[SuiteCRM Auth] Requesting new OAuth2 access token...');
    
    const tokenUrl = `${SUITECRM_URL}/access_token`;
    const response = await fetch(tokenUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            grant_type: 'password',
            client_id: CLIENT_ID,
            client_secret: CLIENT_SECRET,
            username: USERNAME,
            password: PASSWORD
        })
    });

    if (!response.ok) {
        const errorText = await response.text();
        throw new Error(`Auth failed (HTTP ${response.status}): ${errorText}`);
    }

    const data = await response.json();
    const token = data.access_token;
    const expiresIn = (data.expires_in || 3600) - 300; // Cache with 5 min buffer

    if (token) {
        // Cache token in Redis DB 1
        await redisClient.setEx(redisKey, expiresIn, token);
        return token;
    }

    throw new Error('No access_token returned by SuiteCRM');
}

/**
 * Generic JSON API Record Creator for SuiteCRM V8
 */
async function createRecord(moduleName, attributes) {
    const token = await getAccessToken();
    const apiUrl = `${SUITECRM_URL}/V8/module`;

    const payload = {
        data: {
            type: moduleName,
            attributes: attributes
        }
    };

    const response = await fetch(apiUrl, {
        method: 'POST',
        headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/vnd.api+json',
            'Accept': 'application/vnd.api+json'
        },
        body: JSON.stringify(payload)
    });

    if (!response.ok) {
        const errText = await response.text();
        throw new Error(`SuiteCRM V8 module write failed (${response.status}): ${errText}`);
    }

    return await response.json();
}

/**
 * Generic JSON API Record Updater for SuiteCRM V8
 */
async function updateRecord(moduleName, id, attributes) {
    const token = await getAccessToken();
    const apiUrl = `${SUITECRM_URL}/V8/module`;

    const payload = {
        data: {
            type: moduleName,
            id: id,
            attributes: attributes
        }
    };

    const response = await fetch(apiUrl, {
        method: 'PATCH',
        headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/vnd.api+json',
            'Accept': 'application/vnd.api+json'
        },
        body: JSON.stringify(payload)
    });

    if (!response.ok) {
        const errText = await response.text();
        throw new Error(`SuiteCRM V8 module update failed (${response.status}): ${errText}`);
    }

    return await response.json();
}

/**
 * Lookup Contact by primary email address using SuiteCRM V8 filters
 */
async function findContactByEmail(email) {
    if (!email) return null;

    const token = await getAccessToken();
    const cleanEmail = encodeURIComponent(email.trim().toLowerCase());
    const apiUrl = `${SUITECRM_URL}/V8/module/Contacts?filter[email1][eq]=${cleanEmail}&page[size]=1`;

    const response = await fetch(apiUrl, {
        method: 'GET',
        headers: {
            'Authorization': `Bearer ${token}`,
            'Accept': 'application/vnd.api+json'
        }
    });

    if (!response.ok) {
        const errText = await response.text();
        throw new Error(`SuiteCRM Contact lookup failed (${response.status}): ${errText}`);
    }

    const data = await response.json();
    return data.data?.[0]?.id || null;
}

/**
 * Formats and writes completed call records to SuiteCRM
 */
async function saveCallRecord(callData) {
    const identifier = callData.call_id || callData.conference_sid || 'UNKNOWN_CALL';
    const direction = callData.direction || 'Inbound';
    const partyNumber = direction === 'Outbound'
        ? (callData.to || 'Unknown')
        : (callData.from || callData.callerId || 'Unknown');

    const durationSeconds = parseInt(callData.duration_seconds || 0, 10);
    const durationHours = Math.floor(durationSeconds / 3600);
    const durationMinutes = Math.floor((durationSeconds % 3600) / 60);

    const startTime = callData.timestamp
        ? new Date(callData.timestamp).toISOString().replace('T', ' ').substring(0, 19)
        : new Date().toISOString().replace('T', ' ').substring(0, 19);

    const legsJson = JSON.stringify(callData.legs || [], null, 2);

    const attributes = {
        name: `${direction} Call - ${partyNumber}`,
        direction: direction,
        status: 'Held',
        date_start: startTime,
        duration_hours: durationHours,
        duration_minutes: durationMinutes,
        description: `Call SID: ${identifier}\nDirection: ${direction}\nNumber: ${partyNumber}\nFirst Agent: ${callData.first_agent || 'N/A'}\nLast Agent: ${callData.last_agent || 'N/A'}\nRecording URL: ${callData.recording_url || 'N/A'}\n\n--- Call Legs ---\n${legsJson}`
    };

    // Link the Call activity to the most specific CRM parent record
    if (callData.opportunity_id) {
        attributes.parent_type = 'Opportunities';
        attributes.parent_id = callData.opportunity_id;
    } else if (callData.task_id) {
        attributes.parent_type = 'Tasks';
        attributes.parent_id = callData.task_id;
    } else if (callData.client_id) {
        attributes.parent_type = 'Accounts';
        attributes.parent_id = callData.client_id;
    }

    console.log(`[SuiteCRM Sync] Pushing ${direction} Call ${identifier} to SuiteCRM...`);
    const result = await createRecord('Calls', attributes);
    console.log(`✅ [SuiteCRM Sync Success] Call record created with ID: ${result?.data?.id || 'OK'}`);
    return result;
}

/**
 * Fetch a paginated batch of Contacts from SuiteCRM V8 API
 */
async function fetchContactsBatch(pageNumber = 1, pageSize = 500) {
    const token = await getAccessToken();
    const apiUrl = `${SUITECRM_URL}/V8/module/Contacts?page[size]=${pageSize}&page[number]=${pageNumber}`;

    const response = await fetch(apiUrl, {
        method: 'GET',
        headers: {
            'Authorization': `Bearer ${token}`,
            'Accept': 'application/vnd.api+json'
        }
    });

    if (!response.ok) {
        const errText = await response.text();
        throw new Error(`SuiteCRM Contacts fetch failed (${response.status}): ${errText}`);
    }

    const data = await response.json();
    return {
        records: data.data || [],
        totalPages: data.meta?.['total-pages'] || 1
    };
}

/**
 * Iterates through all pages to retrieve all contacts from SuiteCRM
 */
async function getAllContacts() {
    let currentPage = 1;
    let totalPages = 1;
    let allContacts = [];

    console.log('[SuiteCRM Sync] Starting Contacts extraction...');

    do {
        const { records, totalPages: pages } = await fetchContactsBatch(currentPage, 500);
        totalPages = pages;
        allContacts = allContacts.concat(records);
        console.log(`[SuiteCRM Sync] Fetched page ${currentPage} of ${totalPages} (${records.length} contacts)`);
        currentPage++;
    } while (currentPage <= totalPages);

    console.log(`✅ [SuiteCRM Sync] Total Contacts retrieved: ${allContacts.length}`);
    return allContacts;
}


/**
 * Fetch a paginated batch of Opportunities from SuiteCRM V8 API
 */
async function fetchOpportunitiesBatch(pageNumber = 1, pageSize = 500) {
    const token = await getAccessToken();
    const apiUrl = `${SUITECRM_URL}/V8/module/Opportunities?page[size]=${pageSize}&page[number]=${pageNumber}`;

    const response = await fetch(apiUrl, {
        method: 'GET',
        headers: {
            'Authorization': `Bearer ${token}`,
            'Accept': 'application/vnd.api+json'
        }
    });

    if (!response.ok) {
        const errText = await response.text();
        throw new Error(`SuiteCRM Opportunities fetch failed (${response.status}): ${errText}`);
    }

    const data = await response.json();
    return {
        records: data.data || [],
        totalPages: data.meta?.['total-pages'] || 1
    };
}

/**
 * Iterates through all pages to retrieve all opportunities from SuiteCRM
 */
async function getAllOpportunities() {
    let currentPage = 1;
    let totalPages = 1;
    let allOpportunities = [];

    console.log('[SuiteCRM Sync] Starting Opportunities extraction...');

    do {
        const { records, totalPages: pages } = await fetchOpportunitiesBatch(currentPage, 500);
        totalPages = pages;
        allOpportunities = allOpportunities.concat(records);
        console.log(`[SuiteCRM Sync] Fetched opportunities page ${currentPage} of ${totalPages} (${records.length} records)`);
        currentPage++;
    } while (currentPage <= totalPages);

    console.log(`✅ [SuiteCRM Sync] Total Opportunities retrieved: ${allOpportunities.length}`);
    return allOpportunities;
}

async function createContact(attributes) {
    return await createRecord('Contacts', attributes);
}

async function updateContact(id, attributes) {
    return await updateRecord('Contacts', id, attributes);
}

/**
 * Fetch a paginated batch of Accounts from SuiteCRM V8 API
 */
async function fetchAccountsBatch(pageNumber = 1, pageSize = 500) {
    const token = await getAccessToken();
    const apiUrl = `${SUITECRM_URL}/V8/module/Accounts?page[size]=${pageSize}&page[number]=${pageNumber}`;

    const response = await fetch(apiUrl, {
        method: 'GET',
        headers: {
            'Authorization': `Bearer ${token}`,
            'Accept': 'application/vnd.api+json'
        }
    });

    if (!response.ok) {
        const errText = await response.text();
        throw new Error(`SuiteCRM Accounts fetch failed (${response.status}): ${errText}`);
    }

    const data = await response.json();
    return {
        records: data.data || [],
        totalPages: data.meta?.['total-pages'] || 1
    };
}

/**
 * Iterates through all pages to retrieve all accounts from SuiteCRM
 */
async function getAllAccounts() {
    let currentPage = 1;
    let totalPages = 1;
    let allAccounts = [];

    console.log('[SuiteCRM Sync] Starting Accounts extraction...');

    do {
        const { records, totalPages: pages } = await fetchAccountsBatch(currentPage, 500);
        totalPages = pages;
        allAccounts = allAccounts.concat(records);
        console.log(`[SuiteCRM Sync] Fetched accounts page ${currentPage} of ${totalPages} (${records.length} records)`);
        currentPage++;
    } while (currentPage <= totalPages);

    console.log(`✅ [SuiteCRM Sync] Total Accounts retrieved: ${allAccounts.length}`);
    return allAccounts;
}

/**
 * Fetch related record IDs for a given module record (e.g. Accounts -> contacts)
 */
async function getRelatedRecordIds(moduleName, recordId, linkFieldName) {
    const token = await getAccessToken();
    const apiUrl = `${SUITECRM_URL}/V8/module/${moduleName}/${recordId}/relationships/${linkFieldName}`;

    const response = await fetch(apiUrl, {
        method: 'GET',
        headers: {
            'Authorization': `Bearer ${token}`,
            'Accept': 'application/vnd.api+json'
        }
    });

    if (!response.ok) {
        // 404 simply means no related records exist on some SuiteCRM versions
        if (response.status === 404) return [];
        console.warn(`[SuiteCRM Sync Warning] Failed loading ${linkFieldName} for ${moduleName}/${recordId} (${response.status})`);
        return [];
    }

    const data = await response.json();
    return (data.data || []).map(item => item.id).filter(Boolean);
}

/**
 * Lightweight concurrency runner to avoid overwhelming SuiteCRM with simultaneous requests
 */
async function mapWithConcurrency(items, concurrency, workerFn) {
    const results = new Array(items.length);
    let index = 0;

    async function worker() {
        while (index < items.length) {
            const currentIndex = index++;
            results[currentIndex] = await workerFn(items[currentIndex], currentIndex);
        }
    }

    const workers = Array.from({ length: Math.min(concurrency, items.length) }, () => worker());
    await Promise.all(workers);
    return results;
}

/**
 * Builds a Map<contactId, Set<accountId>> covering all many-to-many Account <-> Contact links.
 * Automatically queries from whichever module has fewer records to minimize API calls.
 */
async function getContactAccountLinks(accounts, contacts) {
    const contactToAccounts = new Map();

    const addLink = (contactId, accountId) => {
        if (!contactId || !accountId) return;
        if (!contactToAccounts.has(contactId)) {
            contactToAccounts.set(contactId, new Set());
        }
        contactToAccounts.get(contactId).add(accountId);
    };

    // 1. Seed from Contact's primary account_id field first
    for (const contact of contacts) {
        const primaryAccId = contact.attributes?.account_id;
        if (primaryAccId) {
            addLink(contact.id, primaryAccId);
        }
    }

    // 2. Fetch many-to-many relationship links from the smaller collection (15 concurrent requests)
    if (accounts.length <= contacts.length) {
        await mapWithConcurrency(accounts, 15, async (account) => {
            const relatedContactIds = await getRelatedRecordIds('Accounts', account.id, 'contacts');
            for (const contactId of relatedContactIds) {
                addLink(contactId, account.id);
            }
        });
    } else {
        await mapWithConcurrency(contacts, 15, async (contact) => {
            const relatedAccountIds = await getRelatedRecordIds('Contacts', contact.id, 'accounts');
            for (const accountId of relatedAccountIds) {
                addLink(contact.id, accountId);
            }
        });
    }

    return contactToAccounts;
}

/**
- Generic JSON API Module List Query for SuiteCRM V8
 */
async function getModuleRecords(moduleName, params = {}) {
    const token = await getAccessToken();
    const query = new URLSearchParams(params).toString();
    const apiUrl = `${SUITECRM_URL}/V8/module/${moduleName}${query ? `?${query}` : ''}`;

    const response = await fetch(apiUrl, {
        method: 'GET',
        headers: {
            'Authorization': `Bearer ${token}`,
            'Accept': 'application/vnd.api+json'
        }
    });

    if (!response.ok) {
        const errText = await response.text();
        throw new Error(`SuiteCRM ${moduleName} list failed (${response.status}): ${errText}`);
    }

    return await response.json();
}

/**
- Generic JSON API Single Record Fetch for SuiteCRM V8
 */
async function getRecordById(moduleName, id) {
    const token = await getAccessToken();
    const apiUrl = `${SUITECRM_URL}/V8/module/${moduleName}/${id}`;

    const response = await fetch(apiUrl, {
        method: 'GET',
        headers: {
            'Authorization': `Bearer ${token}`,
            'Accept': 'application/vnd.api+json'
        }
    });

    if (!response.ok) {
        if (response.status === 404) return null;
        const errText = await response.text();
        throw new Error(`SuiteCRM ${moduleName}/${id} fetch failed (${response.status}): ${errText}`);
    }

    return await response.json();
}

module.exports = {
    getAccessToken,
    createRecord,
    updateRecord,
    findContactByEmail,
    saveCallRecord,
    fetchContactsBatch,
    getAllContacts,
    createContact,
    updateContact,
    fetchOpportunitiesBatch,
    getAllOpportunities,
    fetchAccountsBatch,
    getAllAccounts,
    getRelatedRecordIds,
    mapWithConcurrency,
    getContactAccountLinks,
    getModuleRecords,
    getRecordById
};      
