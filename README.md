# CiviCRM Rest API Example2

Using hook from the "Gravity Form" plugin - when a "Gravity Form" is submitted the information is sent to CiviCRM in another website using its Rest api. This creates/updates an individual contact in civicrm and/or perform other action(s) (adds a note, phone, company with the relationship for it, activity, adds the contact to a group(s), creates event registration, etc.) for the contact based on the type of the submitted "Gravity Form".


CiviCRM is based on the Symfony framework with the Smarty template engine and I have extensive experience modifying the plugin and overloading its functionality for a clients. The Controllers are defined in xml files (for each module) and each module xml file can be re-defined where only particular urls to be overloaded to point to the new child class extending their corresponding core parent class from civicrm. 

WIth an alias plugin and civicrm modifications - it is achieved a sync between civicrm contacts and WordPress users i.e. a field (e.g. first name) changed in civicrm contact is reflected in their WP user and vice versa (same for new contact or deleting contact). 

The current Mailchimp (a popular 3rd party for subscribing for mass emails) civicrm extension https://civicrm.org/extensions/mailchimp-civicrm-integration fails to sync a large number of users at once (i.e. if a large number of contacts have been bulk imported) due to a script time-out as it tries to push all users in 1 go. It has been re-written to push the users in batches in the following steps:

Identify the whole bunch to be pushed (i.e. those who have been recently modified).

Record the bunch in its own DB table to ensure proper order of existing and newly modified contacts.

By limiting the mysql query and specifying new offset each time the cronjob runs - send a number small enough not to cause script time-out.
