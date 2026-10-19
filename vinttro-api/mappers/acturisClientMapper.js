// vinttro-api/mappers/acturisClientMapper.js
const BaseMapper = require('./baseMapper');

class ActurisClientMapper extends BaseMapper {
    map(row) {
        const isIndividual = String(row['Is Individual?']).trim().toLowerCase() === 'yes';

        // 1. Resolve Contact Names based on entity type
        let firstName = null;
        let lastName = null;

        if (isIndividual) {
            firstName = this.cleanString(row['Client First Name']) || this.cleanString(row['Primary Contact First Name']);
            lastName = this.cleanString(row['Client Surname']) || this.cleanString(row['Primary Contact Surname']);
        } else {
            // For companies, primary contact holds the person's name
            firstName = this.cleanString(row['Primary Contact First Name']);
            lastName = this.cleanString(row['Primary Contact Surname']) || this.cleanString(row['Client Surname']);
        }

        // 2. Resolve Email
        const email = this.cleanEmail(row['Primary Contact Email']);

        // 3. Resolve Phones (Check mobile, work, and home columns)
        const rawMobile = row['Primary Contact Mobile'];
        const rawWork = row['Primary Contact Work Phone'];
        const rawHome = row['Primary Contact Home Phone'];

        const mobileParsed = this.parsePhone(rawMobile);
        const workParsed = this.parsePhone(rawWork);
        const homeParsed = this.parsePhone(rawHome);

        // Pick the best detected mobile & landline
        const finalMobile = mobileParsed.mobile || workParsed.mobile || homeParsed.mobile;
        const finalWork = workParsed.work || homeParsed.work || mobileParsed.work;

        // Validation: Contact must have a last name to save in SuiteCRM
        if (!lastName) {
            throw new Error(`Record (Ref: ${row['Contact Ref.'] || 'N/A'}) missing required Surname/Last Name.`);
        }

        const clientStatus = this.cleanString(row['Client Status']) || 'Live';

        // 4. Build standard SuiteCRM Contact attributes
        const attributes = {
            first_name: firstName,
            last_name: lastName,
            phone_mobile: finalMobile,
            phone_work: finalWork,
            lead_source: 'Acturis',
            description: [
                `Acturis Ref: ${row['Contact Ref.'] || 'N/A'}`,
                `Client Status: ${clientStatus}`,
                `Company: ${isIndividual ? 'Individual' : (row['Client Surname'] || row['Contact Name'] || 'N/A')}`,
                `Total Worth: £${row['Total Worth'] || '0.00'}`,
                `Live Policies: ${row['Count of Live Policies'] || '0'}`
            ].join('\n')
        };

        // Only include email1 if a valid, cleaned email exists
        if (email) {
            attributes.email1 = email;
        }

        // Strip null/undefined keys so SuiteCRM doesn't fail on missing optional fields
        Object.keys(attributes).forEach(key => {
            if (attributes[key] === null || attributes[key] === undefined) {
                delete attributes[key];
            }
        });

        return {
            email: email,
            acturisRef: this.cleanString(row['Contact Ref.']),
            isIndividual: isIndividual,
            attributes: attributes
        };
    }
}

module.exports = ActurisClientMapper;