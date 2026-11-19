// vinttro-api/mappers/suitecrmEventMapper.js
class SuitecrmEventMapper {
  mapRecord(moduleName, record, { includeDetails = false } = {}) {
    switch (moduleName) {
      case 'Calls':
        return this.mapCall(record, includeDetails);
      case 'Emails':
        return this.mapEmail(record, includeDetails);
      case 'Notes':
        return this.mapNote(record, includeDetails);
      default:
        throw new Error(`Unsupported SuiteCRM event module: ${moduleName}`);
    }
  }

  mapCall(record, includeDetails) {
    const attrs = record.attributes || {};
    const parsed = this.parseCallDescription(attrs.description || '');
    const subjectNumberMatch = (attrs.name || '').match(/(\+?\d[\d\s\-]{6,})/);

    let proxyAudioUrl = null;
    if (parsed.recordingUrl) {
      const sidMatch = parsed.recordingUrl.match(/\/Recordings\/(RE[a-f0-9]+)/i);
      if (sidMatch) {
        proxyAudioUrl = `/api/communicator/recordings/${sidMatch[1]}`;
      }
    }

    return {
      id: record.id,
      sourceModule: 'Calls',
      channel: 'call',
      direction: (parsed.direction || attrs.direction || 'inbound').toLowerCase(),
      timestamp: attrs.date_entered || attrs.date_start,
      contactEndpoint: parsed.number || (subjectNumberMatch ? subjectNumberMatch[1].trim() : ''),
      accountId: attrs.parent_type === 'Accounts' ? attrs.parent_id : null,
      accountName: attrs.parent_name || '',
      agent: parsed.lastAgent || parsed.firstAgent || attrs.assigned_user_name || '',
      status: attrs.status || '',
      summary: attrs.name || 'Phone Call',
      hasRecording: Boolean(proxyAudioUrl),
      payload: includeDetails
        ? {
            scheduledAt: attrs.date_start || null,
            duration: `${attrs.duration_hours || 0}h ${attrs.duration_minutes || 0}m`,
            callSid: parsed.callSid,
            firstAgent: parsed.firstAgent,
            lastAgent: parsed.lastAgent,
            audioUrl: proxyAudioUrl,
            legs: parsed.legs,
            notes: parsed.rawNotes,
          }
        : undefined,
    };
  }

  mapEmail(record, includeDetails) {
    const attrs = record.attributes || {};
    const isInbound = (attrs.type || '').toLowerCase() === 'inbound';
    const fromAddr = attrs.from_addr_name || attrs.from_addr || '';
    const toAddrs = attrs.to_addrs_names || attrs.to_addrs || '';

    return {
      id: record.id,
      sourceModule: 'Emails',
      channel: 'email',
      direction: isInbound ? 'inbound' : 'outbound',
      timestamp: attrs.date_sent_received || attrs.date_sent || attrs.date_entered,
      contactEndpoint: isInbound ? fromAddr : toAddrs,
      accountId: attrs.parent_type === 'Accounts' ? attrs.parent_id : null,
      accountName: attrs.parent_name || '',
      agent: attrs.assigned_user_name || '',
      status: attrs.status || '',
      summary: attrs.name || '(No Subject)',
      hasRecording: false,
      payload: includeDetails
        ? {
            from: fromAddr,
            to: toAddrs,
            cc: attrs.cc_addrs_names || attrs.cc_addrs || '',
            bodyHtml: attrs.description_html || null,
            bodyText: attrs.description || '',
          }
        : undefined,
    };
  }

  mapNote(record, includeDetails) {
    const attrs = record.attributes || {};
    // Supports custom channel_c ('whatsapp' | 'sms') or falls back to 'note'
    const channel = (attrs.channel_c || 'note').toLowerCase();

    return {
      id: record.id,
      sourceModule: 'Notes',
      channel,
      direction: (attrs.direction_c || 'inbound').toLowerCase(),
      timestamp: attrs.date_entered,
      contactEndpoint: attrs.contact_number_c || '',
      accountId: attrs.parent_type === 'Accounts' ? attrs.parent_id : null,
      accountName: attrs.parent_name || '',
      agent: attrs.assigned_user_name || '',
      status: attrs.status_c || 'Logged',
      summary: attrs.name || 'Message',
      hasRecording: false,
      payload: includeDetails
        ? {
            bodyText: attrs.description || '',
            externalId: attrs.external_id_c || null,
          }
        : undefined,
    };
  }

  parseCallDescription(description) {
    const [headerBlock = '', afterLegsHeader = ''] = description.split('--- Call Legs ---');
    const [legsBlock = '', selectedClientsBlock = ''] = afterLegsHeader.split('--- Selected Clients ---');
    const meta = {};

    headerBlock.split('\n').forEach((line) => {
      const idx = line.indexOf(':');
      if (idx > -1) {
        meta[line.slice(0, idx).trim()] = line.slice(idx + 1).trim();
      }
    });

    let legs = [];
    if (legsBlock.trim()) {
      try {
        legs = JSON.parse(legsBlock.trim());
      } catch (_) {
        legs = [];
      }
    }

    let selectedClients = [];
    if (selectedClientsBlock.trim()) {
      try {
        selectedClients = JSON.parse(selectedClientsBlock.trim());
      } catch (_) {
        selectedClients = [];
      }
    }

    return {
      callSid: meta['Call SID'] || null,
      direction: meta['Direction'] || null,
      number: meta['Number'] || null,
      firstAgent: meta['First Agent'] || null,
      lastAgent: meta['Last Agent'] || null,
      recordingUrl: meta['Recording URL'] || null,
      legs,
      selectedClients,
      rawNotes: !afterLegsHeader ? description : '',
    };
  }
}

module.exports = SuitecrmEventMapper;