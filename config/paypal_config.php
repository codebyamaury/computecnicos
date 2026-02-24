<?php
return [
    'client_id' => getenv('PAYPAL_CLIENT_ID') ?: 'AVCJdMfxx8FdGz3wLHRaaewp7fI4Ue-Ry5ur1JAmp3hL398a5xh3r2GBJowzoenYBF96rw93g-N3oism',
    'client_secret' => getenv('PAYPAL_CLIENT_SECRET') ?: 'EPa4y-v9JLqVs9dLseGyZN8if0iQJIQZw8L8D5OuQC23HSO7WD-eLYjXgeuWPyYsr41AFgR25w6BtMna',
    'environment' => getenv('PAYPAL_ENVIRONMENT') ?: 'sandbox',
    'currency' => 'USD',
    // Tasa COP→USD si currency=USD (aprox. 1/4000)
    'exchange_rate_cop_to_usd' => 0.00025
];