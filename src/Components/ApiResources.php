<?php

namespace KTPL\CurrencyRateConversionBundle\Components;

class ApiResources
{
    /** @var string[] */
    const SUPPORTED_API = [
        'fixerio' => [
            'label' => 'ktpl.currency_rate_conversion.currency_api.fixerio.label',
            'fields' => [
                'api_key' => [
                    'type' => 'password',
                    'name' => 'api_key',
                    'label' => 'ktpl.currency_rate_conversion.currency_api.fixerio.field.api_key.label'
                ],
            ]
        ],
        'currencyconverterapi' => [
            'label' => 'ktpl.currency_rate_conversion.currency_api.currencyconverterapi.label',
            'fields' => [
                'api_key' => [
                    'type' => 'password',
                    'name' => 'api_key',
                    'label' => 'ktpl.currency_rate_conversion.currency_api.currencyconverterapi.field.api_key.label'
                ],
                'api_version' => [
                    'type' => 'select',
                    'name' => 'api_version',
                    'options' => [
                        'free' => 'Free',
                        'api' => 'Premium',
                        'prepaid' => 'Prepaid',

                    ],
                    'label' => 'ktpl.currency_rate_conversion.currency_api.currencyconverterapi.field.api_version.label'
                ]
            ]
        ],
    ];

    /** @var string[] */
    const SUPPORTED_API_RESOURCES = [
        'fixerio' => [
            'currency_converter_url' => 'http://data.fixer.io/api/latest?access_key={{ACCESS_KEY}}&base={{CURRENCY_FROM}}&symbols={{CURRENCIES_TO}}',
        ],
        'currencyconverterapi' => [
            'currency_converter_url' => 'https://free.currconv.com/api/v7/convert?apiKey={{ACCESS_KEY}}&q={{FROM_TO}}&compact=ultra',
        ]
    ];
}
