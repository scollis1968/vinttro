<?php
namespace Custom\DataStaging\Services;

use DBManagerFactory;
use BeanFactory;

class ContactService {

    public function findOrCreateContact(array $rawData): \Contact {
        if (empty($rawData['email'])) {
            throw new \InvalidArgumentException("Missing required source field: 'email'.");
        }
        if (empty($rawData['last-name'])) {
            throw new \InvalidArgumentException("CRM requires a 'last-name' to save a contact card.");
        }

        $email = trim($rawData['email']);
        $contactId = $this->findContactIdByEmail($email);

        /** @var \Contact|null $contact */
        $contact = $contactId ? BeanFactory::retrieveBean('Contacts', $contactId) : null;

        if (empty($contact) || empty($contact->id)) {
            $contact = BeanFactory::newBean('Contacts');
        }

        if (!empty($rawData['first-name'])) {
            $contact->first_name = $rawData['first-name'];
        }
        if (!empty($rawData['last-name'])) {
            $contact->last_name = $rawData['last-name'];
        }
        if (!empty($rawData['postcode'])) {
            $contact->primary_address_postalcode = $rawData['postcode'];
        }

        if (!empty($rawData['phone'])) {
            $rawPhone = trim($rawData['phone']);
            if (preg_match('/^(07|7|\+447)/', $rawPhone)) {
                $contact->phone_mobile = $rawPhone;
            } else {
                $contact->phone_work = $rawPhone;
            }
        }

        $contact->save();

        if (isset($contact->emailAddress)) {
            $contact->emailAddress->addAddress($email, true);
            $contact->emailAddress->save($contact->id, $contact->module_dir);
        }

        return $contact;
    }

    /**
     * Finds Contact ID using standard SuiteCRM database execution.
     */
    public function findContactIdByEmail(string $email): ?string {
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
}