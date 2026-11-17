// vinttro-api/mappers/baseMapper.js
class BaseMapper {
    /**
     * Cleans general text, stripping Acturis placeholder chars like '.', '-', 'N/A'
     */
    cleanString(value) {
        if (!value) return null;
        let trimmed = String(value).trim();

        // Treat Acturis placeholders as null
        if (['.', '-', 'N/A', 'n/a', 'null', 'None'].includes(trimmed)) {
            return null;
        }

        return trimmed.length > 0 ? trimmed : null;
    }

    /**
     * Extracts and validates real email addresses out of strings like:
     * "Catherine Dance &lt;catherine@danceanddean.co.uk&gt;" or "."
     */
    cleanEmail(email) {
        if (!email) return null;

        // Decode basic HTML entities
        let decoded = String(email)
            .replace(/&lt;/g, '<')
            .replace(/&gt;/g, '>')
            .trim();

        // Extract email inside brackets <email@domain.com> if present
        const bracketMatch = decoded.match(/<([^>]+)>/);
        if (bracketMatch) {
            decoded = bracketMatch[1].trim();
        }

        // Validate actual email pattern
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (emailRegex.test(decoded)) {
            return decoded.toLowerCase();
        }

        return null; // Discard invalid values like '.' or garbage
    }

    /**
     * Handles extensions like "01473744801 Opt 3", ignores '.', and sorts mobile vs landline
     */
    parsePhone(phone) {
        if (!phone) return { mobile: null, work: null };

        let clean = String(phone).trim();
        if (['.', '-', 'N/A'].includes(clean)) {
            return { mobile: null, work: null };
        }

        // Remove notes/extensions like "Opt 3" or "x102"
        clean = clean.split(/opt|ext|x/i)[0].replace(/[^\d+]/g, '').trim();

        if (!clean) return { mobile: null, work: null };

        // UK Mobile matching: 07xxx, +447xxx, 7xxx
        if (/^(07|7|\+447)\d{8,9}$/.test(clean)) {
            return { mobile: clean, work: null };
        }

        return { mobile: null, work: clean };
    }

    /**
     * Converts Acturis currency strings like "7,875.60" to float numbers
     */
    parseCurrency(value) {
        if (!value) return 0.00;
        const cleaned = String(value).replace(/,/g, '').trim();
        const num = parseFloat(cleaned);
        return isNaN(num) ? 0.00 : num;
    }
}

module.exports = BaseMapper;


