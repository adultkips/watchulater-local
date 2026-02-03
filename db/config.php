<?php
// db/config.php
return [
    'db_path' => __DIR__ . '/watchulater.db',
    'ca_cert' => __DIR__ . '/../cacert.pem',
    // TEMP: set true to bypass TLS verification if CA issues occur.
    'tls_insecure' => false,
];

