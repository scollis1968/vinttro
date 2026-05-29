<?php
if(!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

class ContactsViewEdit extends ViewEdit 
{
    public function display() 
    {
        // Inject our script right into the HTML output before the form draws
        echo '<script type="text/javascript" src="custom/modules/Contacts/js/driver_popup.js"></script>';
        
        parent::display();
    }
}