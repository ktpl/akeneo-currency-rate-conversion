<?php

namespace KTPL\CurrencyRateConversionBundle\Components;

use Symfony\Component\HttpFoundation\Response;

class CurrencyConverterApi extends BaseCurrencyConverter
{

     /**
     * @param string $request
     * @param array  $currencyConversionConfiguration
     *
     * @return array
     */
    public function getCurrenciesRates($apiType, $currencyConversionConfiguration)
    {
        if (empty($currencyConversionConfiguration['supported_api_key'][$apiType]['api_key'])) {
            return [
                'response' => ['error' => sprintf('Api key not found for %s api', $apiType)],
                'response_code' => Response::HTTP_BAD_REQUEST
            ];
        }

        if (empty($currencyConversionConfiguration['base_currency'])) {
            return [
                'response' => ['error' => 'Base currency not found'],
                'response_code' => Response::HTTP_BAD_REQUEST
            ];
        }

        if (empty($currencyConversionConfiguration['all_active_currency'])) {
            return [
                'response' => ['error' => 'No active currencies found'],
                'response_code' => Response::HTTP_BAD_REQUEST
            ];
        }

        $apiKey = $currencyConversionConfiguration['supported_api_key'][$apiType]['api_key'];
        $apiSubscribedVersion = '';
        if (isset($currencyConversionConfiguration['supported_api_key'][$apiType]['api_version'])) {
            $apiSubscribedVersion = $currencyConversionConfiguration['supported_api_key'][$apiType]['api_version'];
        }

        $currencyConversionConfiguration['all_active_currency'] = array_diff(
            $currencyConversionConfiguration['all_active_currency'],
            [$currencyConversionConfiguration['base_currency']]
        );
        try {
            $results = $this->fetchCurrencyRates(
                $apiKey,
                $apiSubscribedVersion,
                $apiType,
                $currencyConversionConfiguration['base_currency'],
                $currencyConversionConfiguration['all_active_currency']
            );
        } catch (\Exception $e) {
            return [
                'response' => ['error' => $e->getMessage()],
                'response_code' => Response::HTTP_BAD_REQUEST
            ];
        }

        if ($results['success'] == false) {
            $message = 'Not able to fetch the currencies rate';
            switch ($results['error']['type']) {
                case 'no_error':
                    if (isset($results['error']['info'])) {
                        $message = $results['error']['info'];
                    }
                    break;
                case 'base_currency_access_restricted':
                    $message = sprintf('The %s is not allowed as base currency for your subscription plan', $currencyConversionConfiguration['base_currency']);
                    if (isset($results['error']['info'])) {
                        $message = $results['error']['info'];
                    }
                    break;
                case 'invalid_access_key':
                    $message = 'You have not supplied a valid API Access Key.';
                    if (isset($results['error']['info'])) {
                        $message = $results['error']['info'];
                    }
                    break;
                default:
                    $message = $results['error']['type'];

            }

            return [
                'response' => ['error' => $message],
                'response_code' => Response::HTTP_BAD_REQUEST
            ];
        }

        $notFoundCurrencyRates = array_diff($currencyConversionConfiguration['all_active_currency'], array_keys($results['rates']));
        if (!empty($notFoundCurrencyRates)) {
            $results['error']['type'] = sprintf('Currency rate of %s currencies not supported', implode(',', $notFoundCurrencyRates));
        }

        $results['oldCurrenciesRate'] = $currencyConversionConfiguration['oldCurrenciesRate'];


        return [
            'response' => $results,
            'response_code' => Response::HTTP_OK
        ];
    }

    /**
     * Fetch currency rates
     *
     * @param string $accessKey
     * @param string $apiType
     * @param string $apiSubscribedVersion
     * @param string $baseCurrency
     * @param array  $currencies
     *
     * @return array
     *
     * @throws \Exception
     */
    public function fetchCurrencyRates(string $accessKey, string $apiSubscribedVersion, string $apiType, string $baseCurrency, array $currencies)
    {
        $allSupportedApi= ApiResources::SUPPORTED_API_RESOURCES;
        if (!isset($allSupportedApi[$apiType])) {
            throw new \Exception(sprintf('%s api does not supported'));
        }

        $result = [
            'success' => false,
            "error" => [
                "type" => "no_error"
            ]
        ];
        $apiUrl = $allSupportedApi[$apiType]['currency_converter_url'];
        if ($apiType == 'fixerio') {
            $result = $this->fetchCurrencyRatesFromFixerIo($accessKey, $apiUrl, $baseCurrency, $currencies);
        } elseif ($apiType == 'currencyconverterapi') {
            if ($apiSubscribedVersion !== '') {
                $apiUrl = str_replace('free', $apiSubscribedVersion, $apiUrl);
            }
            $currencyRates = $this->fetchCurrencyRatesFromCurrencyConverterApi($accessKey, $apiUrl, $baseCurrency, $currencies);
            if (!isset($currencyRates['error'])) {
                unset($result['error']);
                $result['success'] = true;
                $result['rates'] = $currencyRates;
            } else {
                $result['error']['info'] = $currencyRates['error'];
            }
        }

        return $result;
    }
}
