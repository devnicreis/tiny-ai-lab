<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | n8n — Motor Externo de Orquestração de IA
    |--------------------------------------------------------------------------
    |
    | Endereço privado (VPC) do webhook de entrada do n8n e o segredo
    | compartilhado usado nos headers `X-N8N-Secret` de ida e de callback
    | (SAD 7.5). Nenhum dos dois pode ser hardcoded no domínio nem registrado
    | em log.
    |
    */

    'n8n' => [
        'url' => env('N8N_WEBHOOK_URL'),
        'secret' => env('N8N_SHARED_SECRET'),
    ],

];
