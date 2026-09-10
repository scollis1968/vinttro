<?php
namespace Custom\DataStaging\Services;

if (!class_exists('SugarQuery')) {
    require_once __DIR__ . '/../../../../include/SugarQuery/SugarQuery.php';
}
use BeanFactory;

class ContactService {

    /**
     * Finds an existing contact by email or creates a new one,
     * then applies updated fields, phone routing, and email address.
     */
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

        // Map basic fields
        if (!empty($rawData['first-name'])) {
            $contact->first_name = $rawData['first-name'];
        }
        if (!empty($rawData['last-name'])) {
            $contact->last_name = $rawData['last-name'];
        }
        if (!empty($rawData['postcode'])) {
            $contact->primary_address_postalcode = $rawData['postcode'];
        }

        // Custom Phone Routing
        if (!empty($rawData['phone'])) {
            $rawPhone = trim($rawData['phone']);
            if (preg_match('/^(07|7|\+447)/', $rawPhone)) {
                $contact->phone_mobile = $rawPhone;
            } else {
                $contact->phone_work = $rawPhone;
            }
        }

        $contact->save();

        // Assign primary email safely
        if (isset($contact->emailAddress)) {
            $contact->emailAddress->addAddress($email, true);
            $contact->emailAddress->save($contact->id, $contact->module_dir);
        }

        return $contact;
    }

    /**
     * Reusable contact lookup via standard SuiteCRM SugarQuery API.
     */
    public function findContactIdByEmail(string $email): ?string {
        if (empty($email)) {
            return null;
        }

        $seed = BeanFactory::newBean('Contacts');
        $q = new \SugarQuery();
        $q->select(['id']);
        $q->from($seed);
        $q->join('email_addresses', ['alias' => 'ea']);
        $q->where()
            ->equals('ea.email_address_caps', strtoupper(trim($email)))
            ->equals('ea.deleted', 0);
        $q->limit(1);

        $results = $q->execute();
        return !empty($results[0]['id']) ? $results[0]['id'] : null;
    }
}