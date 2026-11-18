<?php
// Copy to config.php (NOT committed / NOT public) and fill in.
return [
  'mail_to'     => 'requests@your-company.com',   // corporate inbox that receives requests
  'mail_from'   => 'no-reply@your-domain.com',    // sender on YOUR domain (needed for delivery)
  'sheet_url'   => '',                            // Google Apps Script web app URL (/exec), optional
  'sheet_secret'=> '',                            // same secret as in the Apps Script
  'min_seconds' => 3,                             // anti-bot: form must be open at least this long
  'max_per_hour'=> 5,                             // per IP
];
