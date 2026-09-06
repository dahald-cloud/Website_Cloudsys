<?php
// Copy this file to /home/YOUR_CPANEL_USERNAME/cloudsys-config.php.
// Do not place the real configuration inside public_html.
return [
    'SITE_URL' => 'https://cloudsysllc.com',
    'TURNSTILE_SECRET_KEY' => 'your-production-turnstile-secret',
    'RESEND_API_KEY' => 're_your-resend-api-key',
    // Use onboarding@resend.dev only for testing to the Resend account email.
    // After verifying cloudsysllc.com, use website@cloudsysllc.com.
    'RESEND_FROM' => 'CloudSys Website <onboarding@resend.dev>',
    'CONTACT_TO' => 'support@cloudsysllc.com',
    // OpenRouter stays server-side. Never place these values in browser JavaScript.
    'OPENROUTER_API_KEY' => 'your-openrouter-api-key',
    'OPENROUTER_MODEL' => 'openai/gpt-4o-mini',
    'OPENROUTER_BASE_URL' => 'https://openrouter.ai/api/v1',
    // MySQL credentials created in cPanel. Keep these outside public_html.
    'DB_HOST' => 'localhost',
    'DB_PORT' => '3306',
    'DB_NAME' => 'your-cpanel-database-name',
    'DB_USER' => 'your-cpanel-database-user',
    'DB_PASSWORD' => 'your-database-password',
];