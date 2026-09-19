import * as Turbo from '@hotwired/turbo';

// Entry for the "member_dashboard_my_event_registrations" content element.
// Turbo is only used for Turbo Streams (the "load more" button appends further past events).
// Turbo Drive is disabled so that regular page navigation keeps working as before;
// elements that should be handled by Turbo therefore need data-turbo="true".
Turbo.session.drive = false;
