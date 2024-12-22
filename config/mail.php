<?php
return [
    /*
    |--------------------------------------------------------------------------
    | Mail Driver
    |--------------------------------------------------------------------------
    |
    | Laravel supports both SMTP and PHP's "mail" function as drivers for the
    | sending of e-mail. You may specify which one you're using throughout
    | your application here. By default, Laravel is setup for SMTP mail.
    |
    | Supported: "smtp", "sendmail", "mailgun", "mandrill", "ses",
    |            "sparkpost", "log", "array"
    |
    */
    'driver' => env('MAIL_DRIVER', 'mailgun'),
    /*
    |--------------------------------------------------------------------------
    | SMTP Host Address
    |--------------------------------------------------------------------------
    |
    | Here you may provide the host address of the SMTP server used by your
    | applications. A default option is provided that is compatible with
    | the Mailgun mail service which will provide reliable deliveries.
    |
    */
    'host' => env('MAIL_HOST', 'smtp.mailgun.org'),
    /*
    |--------------------------------------------------------------------------
    | SMTP Host Port
    |--------------------------------------------------------------------------
    |
    | This is the SMTP port used by your application to deliver e-mails to
    | users of the application. Like the host we have set this value to
    | stay compatible with the Mailgun e-mail application by default.
    |
    */
    'port' => env('MAIL_PORT', 587),
    /*
    |--------------------------------------------------------------------------
    | Global "From" Address
    |--------------------------------------------------------------------------
    |
    | You may wish for all e-mails sent by your application to be sent from
    | the same address. Here, you may specify a name and address that is
    | used globally for all e-mails that are sent by your application.
    |
    */
    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name' => env('MAIL_FROM_NAME', 'Example'),
    ],
    /*
    |--------------------------------------------------------------------------
    | E-Mail Encryption Protocol
    |--------------------------------------------------------------------------
    |
    | Here you may specify the encryption protocol that should be used when
    | the application send e-mail messages. A sensible default using the
    | transport layer security protocol should provide great security.
    |
    */
    'encryption' => env('MAIL_ENCRYPTION', 'tls'),
    /*
    |--------------------------------------------------------------------------
    | SMTP Server Username
    |--------------------------------------------------------------------------
    |
    | If your SMTP server requires a username for authentication, you should
    | set it here. This will get used to authenticate with your server on
    | connection. You may also set the "password" value below this one.
    |
    */
    'username' => env('MAIL_USERNAME'),
    'password' => env('MAIL_PASSWORD'),
    /*
    |--------------------------------------------------------------------------
    | Sendmail System Path
    |--------------------------------------------------------------------------
    |
    | When using the "sendmail" driver to send e-mails, we will need to know
    | the path to where Sendmail lives on this server. A default path has
    | been provided here, which will work well on most of your systems.
    |
    */
    'sendmail' => '/usr/sbin/sendmail -bs',
    /*
    |--------------------------------------------------------------------------
    | Markdown Mail Settings
    |--------------------------------------------------------------------------
    |
    | If you are using Markdown based email rendering, you may configure your
    | theme and component paths here, allowing you to customize the design
    | of the emails. Or, you may simply stick with the Laravel defaults!
    |
    */
    'markdown' => [
        'theme' => 'default',
        'paths' => [
            resource_path('views/vendor/mail'),
        ],
    ],
    'common' => [
        'header' => '<html lang="en"><head><meta charset="UTF-8"><link rel="icon" type="image/x-icon" href="img/favicon.ico"/><style>.table_template table tr th { border-bottom: 1px solid rgba(2, 136, 209, 0.7); color: #005584; position: relative; font-weight: 600; font-size: 12px; background: rgba(4, 136, 208, 0.2);padding:10px;text-align: left}.table_template table tr td {padding: 7px 10px;font-size: 13px;color: #4f4f4f;border-bottom: 1px solid #f0f0f0;text-align: left}.table_template table tr td:first-child {font-weight:600;}</style></head><body><table width="100%" border="0" cellpadding="0" cellspacing="0" bgcolor="#FFFFFF"><tbody><tr><td style="border-collapse:collapse; padding: 0px;font-family:sans-serif;" width="100%" bgcolor="#f7f7f7" valign="top" align="center"><table width="600" border="0" cellpadding="0" cellspacing="0" style="background: #ffffff;margin-top: 20px;margin-bottom: 20px;"><tbody><tr><td style="border-collapse:collapse;padding-left:20px" width="250"height="100" bgcolor="#0288d1" align="left" valign="middle"><img src="http://befreecrm.com.au/images/emailtemplate/logo.png" width="120"/></td><td style="border-collapse:collapse;padding-right:20px" width="250"height="100" bgcolor="#0288d1" align="right" valign="middle"><img src="http://befreecrm.com.au/images/emailtemplate/mail.png" width="40"/></td></tr><tr><td style="border-collapse:collapse;padding-left:20px;padding-right:20px;font-size: 13px; padding-top: 20px" colspan="2" height="100" bgcolor="#ffffff" align="left" valign="middle">',
        
        'footer' => '</td></tr><tr><td height="20"  colspan="2"></td></tr><tr><td style="padding:0 20px;"  colspan="2"><table style="border-top: 2px solid #dae9ef;"><tr><td style="border-collapse:collapse;" width="600" height="60" bgcolor="#ffffff" align="left" valign="middle"><p>Copyright '.date('Y').' | Befree</td><td style="border-collapse:collapse;padding-right:20px" width="20" height="60" bgcolor="#ffffff" align="right" valign="middle"></td></tr></table></td></tr></tbody></table></td></tr></tbody></table></body></html>']
];