<?php
namespace Custom\DataStaging;

use DBManagerFactory;

abstract class AbstractStagingMapper implements StagingMapperInterface {
    
    /**
     * Shared utility to find a Contact ID by email address.
     * Accessible by any child mapper class using $this->findContactIdByEmail()
     * * @param string $email
     * @return string|null
     */
    protected function findContactIdByEmail(string $email): ?string {
        if (empty($email)) {
            return null;
        }

        $db = DBManagerFactory::getInstance();
        $cleanEmail = $db->quote(trim($email));

        $query = "SELECT eab.bean_id 
                  FROM email_addr_bean_rel eab
                  INNER JOIN email_addresses ea ON ea.id = eab.email_address_id
                  WHERE ea.email_address_caps = UPPER('{$cleanEmail}')
                    AND eab.bean_module = 'Contacts'
                    AND eab.deleted = 0
                    AND ea.deleted = 0
                  LIMIT 1";

        $result = $db->query($query);
        if ($row = $db->fetchByAssoc($result)) {
            return $row['bean_id'];
        }
        
        return null;
    }

    /**
     * Future Proofing: You can easily add more shared lookups here later!
     * For example, finding an Account by its Name or external ERP ID.
     */
    protected function findAccountIdByName(string $name): ?string {
        $db = DBManagerFactory::getInstance();
        $cleanName = $db->quote(trim($name));
        
        $query = "SELECT id FROM accounts WHERE name = '{$cleanName}' AND deleted = 0 LIMIT 1";
        $result = $db->query($query);
        if ($row = $db->fetchByAssoc($result)) {
            return $row['id'];
        }
        return null;
    }
}