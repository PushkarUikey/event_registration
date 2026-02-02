# Event Registration Module

## Overview
This is a custom Drupal 10 module that allows users to register for events via a custom form. It handles event creation, user registration with AJAX-dependent dropdowns, validation, email notifications, and administrative reporting with CSV export.

## Requirements
* Drupal 10.x
* PHP 8.1 or higher

## Installation
1.  Copy the `event_registration` folder to your project's `modules/custom/` directory.
2.  Go to **Extend** in the Drupal admin menu.
3.  Search for "Event Registration" and enable the module.
4.  The module will automatically create the necessary database tables (`event_details` and `event_registrations`) upon installation.

## URLs & Navigation
* **Event Configuration (Add Event):**
    `/admin/config/event-registration/add-event`
    * *Usage:* Administrators use this to create new events (Hackathons, Workshops, etc.).
    
* **User Registration Form:**
    `/events/register`
    * *Usage:* Public users use this page to register. The form uses AJAX to dynamically load Event Dates and Event Names based on the selected Category.

* **Admin Listing & Export:**
    `/admin/content/event-registrations`
    * *Usage:* Administrators can view all registrants, filter by date/event, and click "Export to CSV" to download the data.

* **Module Settings:**
    `/admin/config/event-registration/settings`
    * *Usage:* Configure the admin notification email address.

## Database Structure
The module uses two custom database tables defined in `.install`:

1.  **`event_details`**: Stores the event configuration.
    * `id`: Primary Key.
    * `event_name`: Name of the event.
    * `event_date`: Date of the event.
    * `category`: Event category (e.g., Hackathon, Workshop).
    * `reg_start_date` / `reg_end_date`: Registration window.

2.  **`event_registrations`**: Stores user submissions.
    * `id`: Primary Key.
    * `full_name`, `email`, `college`, `department`: User details.
    * `event_id`: Foreign key linking to `event_details`.
    * `created`: Timestamp of registration.

## Key Features & Logic
* **AJAX Dropdowns:** The registration form uses Drupal's Form API `#ajax` property. Selecting a "Category" triggers a callback to populate the "Date" dropdown. Selecting a "Date" triggers a callback to populate the "Event Name" dropdown.
* **Validation:**
    * **Special Characters:** Regex checks ensure names and college fields only contain letters, numbers, and spaces.
    * **Duplicate Prevention:** The system checks the database to ensure the same email cannot register for the same event ID twice.
* **Email Notifications:** Uses `hook_mail` and `MailManager` to send HTML-safe emails to both the user and the administrator upon successful registration.