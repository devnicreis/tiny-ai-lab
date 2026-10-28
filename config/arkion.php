<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Feature flags
    |--------------------------------------------------------------------------
    |
    | Funcionalidades com interface pronta e decisão de produto pendente. Uma
    | flag desligada significa que nada da funcionalidade é renderizado — nem o
    | botão, nem o modal —, e não apenas que o botão fica escondido.
    |
    | `referral`: programa de indicação (Sessão 3.2). O mockup existe, mas
    | códigos, atribuição, anti-fraude, valor da recompensa e carteira de
    | destino são [A DEFINIR] (SAD 10.16). Sem backend.
    |
    */

    'features' => [
        'referral' => (bool) env('ARKION_FEATURE_REFERRAL', false),
    ],

];
