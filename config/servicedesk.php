<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Ticket email notifications
    |--------------------------------------------------------------------------
    |
    | it_lead is always cc'd on ticket emails regardless of role/department,
    | per management request — separate from the IT_MANAGER/IT_STAFF role
    | group, which is resolved dynamically from the users table.
    |
    | thread_domain is used to build the Message-ID / References headers that
    | keep every email about one ticket (creation, status changes, comments)
    | grouped as a single thread in the recipient's mail client.
    |
    */

    'it_lead' => [
        'name'  => env('IT_LEAD_NAME'),
        'email' => env('IT_LEAD_EMAIL'),
        'phone' => env('IT_LEAD_PHONE'),
    ],

    'mail_thread_domain' => env('MAIL_THREAD_DOMAIN', 'localhost'),

    /*
    | Accounts created by DmlEmployeeSeeder without a real mailbox get a
    | placeholder address on this domain (see that seeder's docblock). Never
    | send real mail to it — filtered out in TicketMailService.
    */
    'placeholder_mail_domain' => 'dml.local',

];
