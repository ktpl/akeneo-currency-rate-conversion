<?php

namespace KTPL\CurrencyRateConversionBundle\Components;

class BaseCurrencyConverter
{
    /**
     * Fetch currency rates from fixerio
     *
     * @param string $accessKey
     * @param string $apiUrl
     * @param string $baseCurrency
     * @param array  $currencies
     *
     * @return array
     */
    public function fetchCurrencyRatesFromFixerIo(string $accessKey, string $apiUrl, string $baseCurrency, array $currencies)
    {
        $apiUrl = str_replace(
            ['{{ACCESS_KEY}}', '{{CURRENCY_FROM}}', '{{CURRENCIES_TO}}'],
            [$accessKey, $baseCurrency, implode(',', $currencies)],
            $apiUrl
        );

        return $this->fetchRates($apiUrl);
    }

    /**
     * Fetch currency rates from fixerio
     *
     * @param string $accessKey
     * @param string $apiUrl
     * @param string $baseCurrency
     * @param array  $currencies
     *
     * @return array
     */
    public function fetchCurrencyRatesFromCurrencyConverterApi(string $accessKey, string $apiUrl, string $baseCurrency, array $currencies)
    {
        $result = [];
        $currenciesForConversion = [];
        foreach ($currencies as $currencyCode) {
            $currenciesForConversion[] = $baseCurrency.'_'.$currencyCode;
        }
        $url = str_replace(
            ['{{ACCESS_KEY}}', '{{FROM_TO}}'],
            [$accessKey, implode(',', $currenciesForConversion)],
            $apiUrl
        );
        
        return $this->fetchRates($url);
    }

    /**
     * Fetch currency rates
     *
     * @param string $url
     *
     * @return array
     */
    protected function fetchRates(string $url)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        curl_close($ch);

        return json_decode($result, true);
    }
}
