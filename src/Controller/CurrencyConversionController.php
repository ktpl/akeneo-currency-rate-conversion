<?php

namespace KTPL\CurrencyRateConversionBundle\Controller;

use Akeneo\Tool\Bundle\BatchBundle\Launcher\JobLauncherInterface;
use Akeneo\Tool\Component\StorageUtils\Repository\IdentifiableObjectRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use KTPL\CurrencyRateConversionBundle\Components\ApiResources;
use KTPL\CurrencyRateConversionBundle\Components\CurrencyConverterApi;
use KTPL\CurrencyRateConversionBundle\Entity\CurrencyConversionConfiguration;
use KTPL\CurrencyRateConversionBundle\Repository\CurrencyConversionConfigurationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class CurrencyConversionController extends Controller
{
    const JOB_CODE = "compute_currency_conversion";

    const CURRENCY_CONFIGURATION_SECTION = 'currency_configuration';

    /** @var TokenStorageInterface */
    private $tokenStorage;

    /** @var JobLauncherInterface */
    private $jobLauncher;
 
    /** @var IdentifiableObjectRepositoryInterface */
    private $jobInstanceRepository;
 
    /** @var string */
    private $jobName;
    
    /** @var CurrencyConversionConfigurationRepository */
    private $currencyConfigurationRepository;

    /** @var EntityManagerInterface */
    protected $entityManager;
    
    /** @var IdentifiableObjectRepositoryInterface */
    protected $currencyRepository;

    /** @var CurrencyConverterApi */
    protected $currencyConverterApi;

    /**
     * @param TokenStorageInterface                     $tokenStorage
     * @param JobLauncherInterface                      $jobLauncher
     * @param IdentifiableObjectRepositoryInterface     $jobInstanceRepository
     * @param string                                    $jobName
     * @param CurrencyConversionConfigurationRepository $currencyConfigurationRepository
     * @param EntityManagerInterface                    $entityManager
     * @param IdentifiableObjectRepositoryInterface     $currencyRepository
     * @param CurrencyConverterApi                      $currencyConverterApi
     */
    public function __construct(
        TokenStorageInterface $tokenStorage,
        JobLauncherInterface $jobLauncher,
        IdentifiableObjectRepositoryInterface $jobInstanceRepository,
        string $jobName,
        CurrencyConversionConfigurationRepository $currencyConfigurationRepository,
        EntityManagerInterface $entityManager,
        IdentifiableObjectRepositoryInterface $currencyRepository,
        CurrencyConverterApi $currencyConverterApi
    ) {
        $this->tokenStorage = $tokenStorage;
        $this->jobLauncher = $jobLauncher;
        $this->jobInstanceRepository = $jobInstanceRepository;
        $this->jobName = $jobName;
        $this->currencyConfigurationRepository = $currencyConfigurationRepository;
        $this->entityManager = $entityManager;
        $this->currencyRepository = $currencyRepository;
        $this->currencyConverterApi = $currencyConverterApi;
    }

    /**
     * Compute currency conversion
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function computeCurrencyRateConversionAction(Request $request)
    {
        $user = $this->tokenStorage->getToken()->getUser();
        $jobInstance = $this->jobInstanceRepository->findOneByIdentifier($this->jobName);
        if ($jobInstance) {
            $currencyConversionConfiguration = [
                'base_currency' => '',
                'currencies_rate' => [],
                'realTimeVersioning' => true,
            ];
            $result = $this->currencyConfigurationRepository->findOneBySection(self::CURRENCY_CONFIGURATION_SECTION);
            if ($result) {
                $configuration = $result->getConfiguration();
                $currencyConversionConfiguration = array_merge($currencyConversionConfiguration, $configuration);
                $currencyConversionConfiguration['currencies_rate'] = array_column($currencyConversionConfiguration['currencies_rate'], 'rate', 'currencyCode');
            }
            $dataToRemove = [
                'supported_api_key',
                'supported_api',
            ];
            $currencyConversionConfiguration = $this->removeUnwantedData($currencyConversionConfiguration, $dataToRemove);
            $this->jobLauncher->launch($jobInstance, $user, $currencyConversionConfiguration);
        }

        return new JsonResponse(['successful' => true]);
    }

    /**
     * Update currency conversion configuration
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function updateCurrencyConversionConfigurationAction(Request $request)
    {
        $data = json_decode($request->getContent(), true);
        if (!empty($data[self::CURRENCY_CONFIGURATION_SECTION])) {
            $dataToRemove = [
                'supported_api',
            ];
            $data[self::CURRENCY_CONFIGURATION_SECTION] = $this->removeUnwantedData($data[self::CURRENCY_CONFIGURATION_SECTION], $dataToRemove);
            $activatedCurrencyCodes = $this->currencyRepository->getActivatedCurrencyCodes();
            if ($data[self::CURRENCY_CONFIGURATION_SECTION]['base_currency'] == ''
                && !in_array($data[self::CURRENCY_CONFIGURATION_SECTION]['base_currency'], $activatedCurrencyCodes)) {
                $data[self::CURRENCY_CONFIGURATION_SECTION]['base_currency'] = '';
            }
            foreach ($data[self::CURRENCY_CONFIGURATION_SECTION]['currencies_rate'] as $currency => $currencyConfig) {
                if (!in_array($currency, $activatedCurrencyCodes)) {
                    unset($data[self::CURRENCY_CONFIGURATION_SECTION]['currencies_rate'][$currency]);
                }
            }

            $configuration = $this->currencyConfigurationRepository->findOneBy(
                [
                    'section' => self::CURRENCY_CONFIGURATION_SECTION
                ]
            );

            if ($configuration) {
                $configuration->setConfiguration($data[self::CURRENCY_CONFIGURATION_SECTION]);
                $this->entityManager->persist($configuration);
                $this->entityManager->flush();
            } else {
                $configuration = new CurrencyConversionConfiguration();
                $configuration->setSection(self::CURRENCY_CONFIGURATION_SECTION);
                $configuration->setConfiguration($data[self::CURRENCY_CONFIGURATION_SECTION]);
                $this->entityManager->persist($configuration);
                $this->entityManager->flush();
            }
        }

        return new JsonResponse(['successful' => true]);
    }

    /**
     * Get currency conversion configuration
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function getCurrencyConversionConfigurationAction(Request $request)
    {
        $currencyConversionConfiguration[self::CURRENCY_CONFIGURATION_SECTION] = [
            'base_currency' => '',
            'currencies_rate' => [],
            'supported_api_key' => [],
            'supported_api' => ApiResources::SUPPORTED_API,
        ];

        $currencyConversionConfiguration['code'] = self::JOB_CODE;
        
        $result = $this->currencyConfigurationRepository->findOneBySection(self::CURRENCY_CONFIGURATION_SECTION);
        if ($result) {
            $configuration = $result->getConfiguration();
            $currencyConversionConfiguration[self::CURRENCY_CONFIGURATION_SECTION] = array_merge($currencyConversionConfiguration[self::CURRENCY_CONFIGURATION_SECTION], $configuration);
        }

        return new JsonResponse($currencyConversionConfiguration);
    }

    /**
     * Fetch currency rates
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function fetchCurrencyRatesAction(Request $request)
    {
        $apiType = $request->get('currency_api');
        $currencyConversionConfiguration = [
            'supported_api_key' => [],
            'base_currency' => '',
            'oldCurrenciesRate' => [],
            'all_active_currency' => $this->currencyRepository->getActivatedCurrencyCodes()
        ];

        $result = $this->currencyConfigurationRepository->findOneBySection(self::CURRENCY_CONFIGURATION_SECTION);
        if ($result) {
            $configuration = $result->getConfiguration();
            $currencyConversionConfiguration['supported_api_key'] = isset($configuration['supported_api_key']) ? $configuration['supported_api_key'] : [];
            $currencyConversionConfiguration['base_currency'] = $configuration['base_currency'];
            $currencyConversionConfiguration['oldCurrenciesRate'] = $configuration['currencies_rate'];
        }

        $currencyRate = $this->currencyConverterApi->getCurrenciesRates($apiType, $currencyConversionConfiguration);
       
        return new JsonResponse($currencyRate['response'], $currencyRate['response_code']);
    }

    /**
     * Remove unwanted data from the currency data
     *
     * @param array $currencyData
     * @param array $dataToRemove
     *
     * @return array
     */
    protected function removeUnwantedData(array $currencyData, $dataToRemove)
    {
        foreach ($dataToRemove as $dataKey) {
            if (isset($currencyData[$dataKey])) {
                unset($currencyData[$dataKey]);
            }
        }

        return $currencyData;
    }
}
