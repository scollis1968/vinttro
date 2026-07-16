/**
 * Vinttro Lead Pipeline & Task Manager Component
 */

// ==========================================================
// 🛡️ 1. CENTRALIZED SECURITY REST CLIENT
// ==========================================================
const VinttroAPI = {
    async fetch(endpoint) {
        const config = window.vinttroSettings || {};
        const url = `${config.root}vinttro/v1/${endpoint}`;
        
        const headers = {
            'Content-Type': 'application/json'
        };
        if (config.nonce) {
            headers['X-WP-Nonce'] = config.nonce;
        }

        const response = await fetch(url, { method: 'GET', headers });
        if (!response.ok) {
            throw new Error(`REST Error: ${response.status} ${response.statusText}`);
        }
        return response.json();
    }
};

// ==========================================================
// 💼 2. AGENT PIPELINE CONTROLLER
// ==========================================================
const VinttroAgentPipeline = {
    $container: null,
    $countEl: null,

    init() {
        this.$container = $('#vinttro-active-tasks');
        this.$countEl = $('#vinttro-task-count');
        
        // Prevent loading if the DOM element is not present on this WP page
        if (!this.$container.length) return;

        this.loadLeads();
    },

    async loadLeads() {
        this.$container.html('<p class="vinttro-loader">🔄 Fetching secure task matrix...</p>');
        const email = window.vinttroSettings?.currentUserEmail || '';

        try {
            const data = await VinttroAPI.fetch(`tasks?agent=${encodeURIComponent(email)}`);
            const leads = data.tasks || data.data?.tasks || [];

            if (!leads.length) {
                this.$container.html('<p class="vinttro-empty">🎉 Clean desk! No outstanding tasks found.</p>');
                this.$countEl.text('0 Tasks');
                return;
            }

            // 🎯 REQUIREMENT: Inbound Calls always rendered at the top
            const sortedLeads = this.sortLeads(leads);

            this.$countEl.text(`${sortedLeads.length} Active Task${sortedLeads.length !== 1 ? 's' : ''}`);
            this.render(sortedLeads);

        } catch (error) {
            console.error("❌ Agent pipeline error:", error);
            this.$container.html('<p class="vinttro-error">❌ Failed to query current task pipeline.</p>');
        }
    },

    sortLeads(leads) {
        return leads.sort((a, b) => {
            if (a.type === 'INBOUND_CALL' && b.type !== 'INBOUND_CALL') return -1;
            if (a.type !== 'INBOUND_CALL' && b.type === 'INBOUND_CALL') return 1;
            return 0; // Maintain original order for other leads
        });
    },

    getBadge(type) {
        switch (type) {
            case 'INBOUND_CALL':
                return '<span class="vinttro-badge badge-danger">📞 Inbound Call</span>';
            case 'OUTBOUND_CALL':
                return '<span class="vinttro-badge badge-primary">📞 Outbound Call</span>';
            case 'RESEARCH':
                return '<span class="vinttro-badge badge-teal">🔍 Data Research</span>';
            case 'CASE_REVIEW':
                return '<span class="vinttro-badge badge-orange">📂 Case Review</span>';
            default:
                return `<span class="vinttro-badge badge-secondary">${type}</span>`;
        }
    },

    render(leads) {
        this.$container.empty();
        leads.forEach(lead => {
            const badge = this.getBadge(lead.type);
            const cardHtml = `
                <div class="vinttro-task-card" 
                     data-task-id="${lead.id}" 
                     data-task-type="${lead.type}">
                    <div class="task-info">
                        <div style="margin-bottom:6px;">${badge} <span class="task-id">#${lead.id}</span></div>
                        <h4 class="task-customer-name" style="margin:0;">${lead.title}</h4>
                        <p class="task-meta">${lead.meta} | ${lead.target}</p>
                    </div>
                    <div class="task-action">
                        <button class="vinttro-btn vinttro-btn-primary claim-task-btn">Open Worksheet</button>
                    </div>
                </div>
            `;
            this.$container.append(cardHtml);
        });
    }
};

// ==========================================================
// 🤝 3. INTRODUCER PIPELINE CONTROLLER
// ==========================================================
const VinttroIntroducerPipeline = {
    $container: null,

    init() {
        this.$container = $('#vinttro-introduced-leads');
        
        // Only run if the element exists on the loaded Page/HTML block
        if (!this.$container.length) return;

        this.loadIntroducedLeads();
    },

    async loadIntroducedLeads() {
        this.$container.html('<p class="vinttro-loader">🔄 Fetching your introductions...</p>');
        const email = window.vinttroSettings?.currentUserEmail || '';

        try {
            // Target specific endpoint mapping the introduced leads and associated CRM states
            const data = await VinttroAPI.fetch(`introductions?introducer=${encodeURIComponent(email)}`);
            const introductions = data.leads || data.data?.leads || [];

            if (!introductions.length) {
                this.$container.html('<p class="vinttro-empty">💡 No introductions recorded yet. Start sharing leads to build income!</p>');
                return;
            }

            this.render(introductions);

        } catch (error) {
            console.error("❌ Introducer pipeline error:", error);
            this.$container.html('<p class="vinttro-error">❌ Failed to query introduction pipeline.</p>');
        }
    },

    render(leads) {
        this.$container.empty();
        leads.forEach(lead => {
            // Helper parsing values or fallback to default values
            const potentialIncome = lead.opportunity?.potential_income || 0;
            const realIncome = lead.opportunity?.real_income || 0;
            const oppStage = lead.opportunity?.stage || 'No Opportunity Created Yet';

            const cardHtml = `
                <div class="vinttro-lead-card" data-lead-id="${lead.id}">
                    <div class="lead-details">
                        <h4 class="lead-client-name">${lead.client_name}</h4>
                        <p class="lead-meta">Submitted: ${lead.date_created} | Status: <strong>${lead.status}</strong></p>
                    </div>
                    <div class="opportunity-details">
                        <div class="opportunity-stage-badge">CRM Stage: <strong>${oppStage}</strong></div>
                        <div class="income-matrix">
                            <div class="income-block text-muted">
                                <span class="income-label">Potential Share:</span>
                                <span class="income-value">£${potentialIncome.toLocaleString()}</span>
                            </div>
                            <div class="income-block text-success">
                                <span class="income-label">Realized Share:</span>
                                <span class="income-value"><strong>£${realIncome.toLocaleString()}</strong></span>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            this.$container.append(cardHtml);
        });
    }
};

// ==========================================================
// 🎬 4. INITIALIZE PIPELINES ON DOM READY
// ==========================================================
jQuery(document).ready(function($) {
    VinttroAgentPipeline.init();
    VinttroIntroducerPipeline.init();
});