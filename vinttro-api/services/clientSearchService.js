// vinttro-api/services/clientSearchService.js
const redisClient = require('./redis');

const FIELD_WEIGHTS = {
    client: 100,
    postcode: 90,
    opp: 80
};

/**
 * Lowercase, strip punctuation (including delimiters), collapse spaces
 */
function normalizeText(input) {
    if (!input) return '';
    return String(input)
        .toLowerCase()
        .replace(/[^a-z0-9\s]/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
}

/**
 * Lowercase and strip all spaces/punctuation (ideal for UK postcodes & reg numbers)
 */
function compactText(input) {
    if (!input) return '';
    return String(input).toLowerCase().replace(/[^a-z0-9]/g, '');
}

/**
 * Generates word-boundary suffixes: "east coast ltd" -> ["east coast ltd", "coast ltd", "ltd"]
 */
function wordSuffixes(normalized, maxWords = 5) {
    if (!normalized) return [];
    const words = normalized.split(' ').slice(0, maxWords);
    const suffixes = [];
    for (let i = 0; i < words.length; i++) {
        suffixes.push(words.slice(i).join(' '));
    }
    return suffixes;
}

/**
 * Extracts unique 3-character sliding windows for mid-string opportunity/reg search
 */
function extractTrigrams(compact) {
    if (!compact || compact.length < 3) return [];
    const tris = new Set();
    for (let i = 0; i <= compact.length - 3; i++) {
        tris.add(compact.slice(i, i + 3));
    }
    return Array.from(tris);
}

/**
 * Builds in-memory ZSET members and trigram maps for a full SuiteCRM sync batch.
 * Called inside syncClientsFromSuiteCRM() before writing to Redis.
 */
function buildSearchIndexData({ accounts, contacts, contactToAccounts, oppsByClientId }) {
    const zsetMembers = new Set();
    const trigramMap = new Map(); // trigram -> Set<"clientId:oppIdx">

    const addLex = (token, clientId, field, oppIdx = -1, contactLabel = '') => {
        if (!token || token.length < 2 || !clientId) return;
        // Sanitize '|' from contactLabel so splitting on '|' is always safe
        const safeContact = String(contactLabel || '').replace(/\|/g, ' ').trim();
        zsetMembers.add(`${token}|${clientId}|${field}|${oppIdx}|${safeContact}`);
    };

    const addTrigram = (tri, member) => {
        if (!trigramMap.has(tri)) {
            trigramMap.set(tri, new Set());
        }
        trigramMap.get(tri).add(member);
    };

    // 1. Index Accounts (Client/Account Name, Billing Postcode, Opportunities)
    for (const acc of accounts) {
        const accAttr = acc.attributes || {};
        const clientId = acc.id;

        for (const suffix of wordSuffixes(normalizeText(accAttr.name))) {
            addLex(suffix, clientId, 'client');
        }

        const accPostcode = compactText(accAttr.billing_address_postalcode);
        if (accPostcode) {
            addLex(accPostcode, clientId, 'postcode');
        }

        const opps = oppsByClientId.get(clientId) || [];
        opps.forEach((opp, oppIdx) => {
            const oppTitle = opp.tab_label || opp.raw?.name || '';
            for (const suffix of wordSuffixes(normalizeText(oppTitle), 8)) {
                addLex(suffix, clientId, 'opp', oppIdx);
            }

            const oppCompact = compactText(oppTitle);
            if (oppCompact) {
                addLex(oppCompact, clientId, 'opp', oppIdx);
                const triMember = `${clientId}:${oppIdx}`;
                for (const tri of extractTrigrams(oppCompact)) {
                    addTrigram(tri, triMember);
                }
            }
        });
    }

    // 2. Index Contacts (First/Last Name + Contact Postcode) mapped to their linked Accounts
    for (const record of contacts) {
        const attr = record.attributes || {};
        const first = normalizeText(attr.first_name);
        const last = normalizeText(attr.last_name);
        const fullNameDisplay = `${attr.first_name || ''} ${attr.last_name || ''}`.trim();
        const contactPostcode = compactText(attr.primary_address_postalcode);

        if (!first && !last && !contactPostcode) continue;

        const linkedAccountIds = contactToAccounts.get(record.id);
        if (!linkedAccountIds || linkedAccountIds.size === 0) continue;

        for (const clientId of linkedAccountIds) {
            if (first || last) {
                addLex(`${first} ${last}`.trim(), clientId, 'client', -1, fullNameDisplay);
                if (first && last) {
                    addLex(`${last} ${first}`, clientId, 'client', -1, fullNameDisplay);
                }
            }
            if (contactPostcode) {
                addLex(contactPostcode, clientId, 'postcode', -1, fullNameDisplay);
            }
        }
    }

    return { zsetMembers: Array.from(zsetMembers), trigramMap };
}

/**
 * Writes the versioned search index in pipelined batches during full sync.
 */
async function writeVersionedSearchIndex(version, indexData) {
    const { zsetMembers, trigramMap } = indexData;
    const lexKey = `client_search:${version}:lex`;
    const CHUNK_SIZE = 500;

    // 1. Write ZSET members in chunks
    for (let i = 0; i < zsetMembers.length; i += CHUNK_SIZE) {
        const chunk = zsetMembers.slice(i, i + CHUNK_SIZE).map(value => ({
            score: 0,
            value
        }));
        await redisClient.zAdd(lexKey, chunk);
    }

    // 2. Write Trigram SETs in pipelined batches
    let multi = redisClient.multi();
    let opCount = 0;

    for (const [tri, membersSet] of trigramMap.entries()) {
        const members = Array.from(membersSet);
        if (members.length > 0) {
            multi.sAdd(`client_search:${version}:tri:${tri}`, members);
            opCount++;
        }
        if (opCount % CHUNK_SIZE === 0) {
            await multi.exec();
            multi = redisClient.multi();
        }
    }

    if (opCount % CHUNK_SIZE !== 0) {
        await multi.exec();
    }
}

/**
 * Incremental update for SuiteCRM after_save / import hooks
 */
async function indexClientInActiveSearch({ clientId, contactName, companyName, postcode, opportunities = [] }) {
    if (!clientId) return;
    const activeVersion = await redisClient.get('client_cli:active_version');
    if (!activeVersion) return;

    const lexKey = `client_search:${activeVersion}:lex`;
    const entries = [];
    const pushLex = (token, field, oppIdx = -1, contactLabel = '') => {
        if (token && token.length >= 2) {
            const safeContact = String(contactLabel || '').replace(/\|/g, ' ').trim();
            entries.push({ score: 0, value: `${token}|${clientId}|${field}|${oppIdx}|${safeContact}` });
        }
    };

    if (contactName) {
        const parts = normalizeText(contactName).split(' ').filter(Boolean);
        if (parts.length > 0) {
            pushLex(parts.join(' '), 'client', -1, contactName);
            if (parts.length > 1) {
                pushLex([...parts.slice(1), parts[0]].join(' '), 'client', -1, contactName);
            }
        }
    }

    for (const suffix of wordSuffixes(normalizeText(companyName))) {
        pushLex(suffix, 'client');
    }

    const compactPc = compactText(postcode);
    if (compactPc) pushLex(compactPc, 'postcode', -1, contactName || '');

    const multi = redisClient.multi();

    opportunities.forEach((opp, oppIdx) => {
        const title = typeof opp === 'string' ? opp : (opp.tab_label || opp.name || '');
        for (const suffix of wordSuffixes(normalizeText(title), 8)) {
            pushLex(suffix, 'opp', oppIdx);
        }
        const compactOpp = compactText(title);
        if (compactOpp) {
            pushLex(compactOpp, 'opp', oppIdx);
            for (const tri of extractTrigrams(compactOpp)) {
                multi.sAdd(`client_search:${activeVersion}:tri:${tri}`, `${clientId}:${oppIdx}`);
            }
        }
    });

    if (entries.length > 0) {
        multi.zAdd(lexKey, entries);
    }

    await multi.exec();
}

/**
 * Main Search Handler: Queries active ZSET + Trigrams and hydrates from client_summary:<id>
 */
async function searchClients(rawQuery, limit = 15) {
    const normQuery = normalizeText(rawQuery);
    const compactQuery = compactText(rawQuery);

    if (normQuery.length < 2 && compactQuery.length < 2) {
        return [];
    }

    const activeVersion = await redisClient.get('client_cli:active_version');
    if (!activeVersion) return [];

    const lexKey = `client_search:${activeVersion}:lex`;
    const runCompactLex = compactQuery.length >= 2 && compactQuery !== normQuery;
    const trigrams = extractTrigrams(compactQuery).slice(0, 6);

    // Execute prefix scans and trigram intersection in a single Redis round-trip
    const multi = redisClient.multi();
    multi.zRangeByLex(lexKey, `[${normQuery}`, `[${normQuery}\xff`, {
        LIMIT: { offset: 0, count: 60 }
    });

    if (runCompactLex) {
        multi.zRangeByLex(lexKey, `[${compactQuery}`, `[${compactQuery}\xff`, {
            LIMIT: { offset: 0, count: 60 }
        });
    }

    if (trigrams.length > 0) {
        const triKeys = trigrams.map(t => `client_search:${activeVersion}:tri:${t}`);
        multi.sInter(triKeys);
    }

    const execResults = await multi.exec();
    let idx = 0;
    const lexNormRows = execResults[idx++] || [];
    const lexCompactRows = runCompactLex ? (execResults[idx++] || []) : [];
    const trigramRows = trigrams.length > 0 ? (execResults[idx++] || []) : [];

    // Deduplicate by clientId, keeping the highest-scoring match per Account
    const bestByClient = new Map();
    const recordCandidate = (candidate) => {
        const existing = bestByClient.get(candidate.clientId);
        if (!existing || candidate.score > existing.score) {
            bestByClient.set(candidate.clientId, candidate);
        }
    };

    for (const row of [...lexNormRows, ...lexCompactRows]) {
        const [token, clientId, field, oppIdxStr, matchedContact] = row.split('|');
        const exactBonus = token === normQuery || token === compactQuery ? 15 : 0;
        recordCandidate({
            clientId,
            score: (FIELD_WEIGHTS[field] || 50) + exactBonus,
            matchedField: field,
            oppIndex: Number(oppIdxStr),
            matchedContact: matchedContact || null,
            isTrigramHit: false
        });
    }

    for (const row of trigramRows) {
        const [clientId, oppIdxStr] = row.split(':');
        recordCandidate({
            clientId,
            score: 65,
            matchedField: 'opp',
            oppIndex: Number(oppIdxStr),
            matchedContact: null,
            isTrigramHit: true
        });
    }

    if (bestByClient.size === 0) return [];

    // Sort top candidates (fetch limit * 2 to allow post-filtering of trigram hits)
    const rankedCandidates = Array.from(bestByClient.values())
        .sort((a, b) => b.score - a.score)
        .slice(0, limit * 2);

    // Hydrate from existing client_summary:<id> documents in one MGET call
    const summaryKeys = rankedCandidates.map(c => `client_summary:${c.clientId}`);
    const rawSummaries = await redisClient.mGet(summaryKeys);

    const hits = [];
    for (let i = 0; i < rankedCandidates.length; i++) {
        if (hits.length >= limit) break;

        const candidate = rankedCandidates[i];
        const rawJson = rawSummaries[i];
        if (!rawJson) continue;

        let summary;
        try {
            summary = JSON.parse(rawJson);
        } catch {
            continue;
        }

        const matchedOpp =
            candidate.matchedField === 'opp' && candidate.oppIndex >= 0
                ? summary.opportunities?.[candidate.oppIndex] || null
                : null;

        // Verify exact substring match for trigram candidates to prevent false positives
        if (candidate.isTrigramHit) {
            const oppText = compactText(matchedOpp?.tab_label || matchedOpp?.raw?.name || '');
            if (!oppText.includes(compactQuery)) {
                continue;
            }
        }

        hits.push({
            client_id: summary.client_id,
            account_name: summary.client_name,
            is_private: Boolean(summary.is_private),
            contact_name: candidate.matchedContact,
            phone: summary.details?.phone || '',
            email: summary.details?.email || '',
            primary_address: summary.details?.primary_address || '',
            opportunities: summary.opportunities || [],
            match: {
                field: candidate.matchedField,
                score: candidate.score,
                opportunity_id: matchedOpp?.id || null,
                opportunity_label: matchedOpp?.tab_label || null
            }
        });
    }

    return hits;
}

module.exports = {
    buildSearchIndexData,
    writeVersionedSearchIndex,
    indexClientInActiveSearch,
    searchClients
};