<?php

return [
    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
    ],
    'xero_base_config' => [
        'xero' => [
        // API versions can be overridden if necessary for some reason.
        //'core_version'     => '2.0',
        //'payroll_version'  => '1.0',
        //'file_version'     => '1.0'
        ],
        'oauth' => [
            'callback' => 'http://befree.com.au',
            'consumer_key' => env('XERO_KEY'),
            'consumer_secret' => env('XERO_SECRET'),
            //I`f you have issues passing the Authorization header, you can set it to append to the query string
            //'signature_location'    => \XeroPHP\Remote\OAuth\Client::SIGN_LOCATION_QUERY
            //For certs on disk or a string - allows anything that is valid with openssl_pkey_get_(private|public)
            //'rsa_private_key' => '/var/www/html/befreeportal/public/certs/privatekey.pem',
            'rsa_private_key' => '-----BEGIN RSA PRIVATE KEY-----
MIICXAIBAAKBgQDS/pmt3ZgSrd1S43v6PZjBm/chDI4faDOslZxC4Wj0nXC42rcZ
G41eU6jEx4LglsnEJ2nGoGkBm1KrClL28q9vttJCQnaefABsN0RPu8NZ05z426ou
FeefC2gGavyjs6OSyK4+yRmo6NNl9r8rdPT5xtmmc4/pPwU1/tK3WVqLmwIDAQAB
AoGAMs516QT0CoNtSPlYMDDG6NAKmR2x12Q7FTLNdtlacZS7wPeBoX0d9HnGqOO1
4yjMGvy2nsqfnnBtXpxUz/wuPBNh/yFgYkVZK3QuqvymiJ3bLT1VV4Hb474qvu6V
Qrm6FgytNl2X5M7Do6vchr4aRKLhuRpaJpGKUqg2Uv0girECQQDs4XgqOe8CGni5
M//2quJsTbLw/g6XeL9dCQgGwdnr1AFKoPRFT21kwRGm4sWt5r6lEsTvDI1VigWu
1z636V5zAkEA5AZCU41+PyKHBrMxwQPw8u+kg+3Cjfel8ivYEdnbRKBRMSvimX2o
b6xZvPu+tnn7ZKNnUNeBCHeRaKwstERsOQJAN/K9BgQu7mlAMEYW47TSy8/CPudS
nPYZBKlYavgoN2oYb/76EtDCvrRXLfqLxBom1yhKuUdWrmhuFTCjkJ6e/wJBAKRL
LjbtV+09f3SAYHTl1hH0QOEdynRn3xViKciS473KlTWMnTRiqZ3s3Kuh54Oq2Etm
wOYqoDntjMOSapNoSWECQH6AKwreTuRzCJmAWn2bENGkAiBqI2EexPS+t9ctqkws
aYNDQtkjR8gi3hUQEMgN7sEHXl2fshsw0Ki6+fkrhI0=
-----END RSA PRIVATE KEY-----'],
        //These are raw curl options.  I didn't see the need to obfuscate these through methods
        'curl' => [
            CURLOPT_USERAGENT => 'XeroPHP App',
        //CURLOPT_SSL_VERIFYPEER => 1,
        //CURLOPT_SSL_VERIFYHOST => 1,
        //Only for partner apps - unfortunately need to be files on disk only.
        //CURLOPT_CAINFO          => '/var/www/html/befreeportal/public/certs/ca-bundle.crt',
        //CURLOPT_SSLCERT         => 'certs/entrust-cert-RQ3.pem',
        //CURLOPT_SSLKEYPASSWD    => '1234',
        //CURLOPT_SSLKEY          => 'certs/entrust-private-RQ3.pem'
        ]
    ]
];