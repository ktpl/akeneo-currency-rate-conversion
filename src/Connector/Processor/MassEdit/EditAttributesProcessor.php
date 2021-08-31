<?php

namespace KTPL\CurrencyRateConversionBundle\Connector\Processor\MassEdit;

use Akeneo\Pim\Enrichment\Component\Product\Comparator\Filter\FilterInterface;
use Akeneo\Pim\Enrichment\Component\Product\EntityWithFamilyVariant\CheckAttributeEditable;
use Akeneo\Pim\Enrichment\Component\Product\Model\EntityWithFamilyInterface;
use Akeneo\Pim\Enrichment\Component\Product\Model\ProductInterface;
use Akeneo\Pim\Enrichment\Component\Product\Model\ProductModelInterface;
use Akeneo\Tool\Component\Batch\Item\DataInvalidItem;
use Akeneo\Tool\Component\Batch\Item\InitializableInterface;
use Akeneo\Tool\Component\StorageUtils\Repository\IdentifiableObjectRepositoryInterface;
use Akeneo\Tool\Component\StorageUtils\Updater\ObjectUpdaterInterface;
use Akeneo\Pim\Enrichment\Component\Product\Connector\Processor\MassEdit\AbstractProcessor;
use Doctrine\Common\Util\ClassUtils;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class EditAttributesProcessor extends AbstractProcessor implements InitializableInterface
{
    /** @var ValidatorInterface */
    protected $productValidator;

    /** @var ValidatorInterface */
    protected $productModelValidator;

    /** @var ObjectUpdaterInterface */
    protected $productUpdater;

    /** @var ObjectUpdaterInterface */
    protected $productModelUpdater;

    /** @var IdentifiableObjectRepositoryInterface */
    protected $attributeRepository;

    /** @var CheckAttributeEditable */
    protected $checkAttributeEditable;

    /** @var FilterInterface */
    protected $productEmptyValuesFilter;

    /** @var FilterInterface */
    protected $productModelEmptyValuesFilter;

    /** @var IdentifiableObjectRepositoryInterface */
    protected $currencyRepository;

    /** @var string */
    private $baseCurrency;

    /** @var array */
    private $currencyConversionRate = [];

    /** @var array */
    private $activatedCurrencyCodes = [];

    public function __construct(
        ValidatorInterface $productValidator,
        ValidatorInterface $productModelValidator,
        ObjectUpdaterInterface $productUpdater,
        ObjectUpdaterInterface $productModelUpdater,
        IdentifiableObjectRepositoryInterface $attributeRepository,
        CheckAttributeEditable $checkAttributeEditable,
        FilterInterface $productEmptyValuesFilter,
        FilterInterface $productModelEmptyValuesFilter,
        IdentifiableObjectRepositoryInterface $currencyRepository
    ) {
        $this->productValidator = $productValidator;
        $this->productModelValidator = $productModelValidator;
        $this->productUpdater = $productUpdater;
        $this->productModelUpdater = $productModelUpdater;
        $this->attributeRepository = $attributeRepository;
        $this->checkAttributeEditable = $checkAttributeEditable;
        $this->productEmptyValuesFilter = $productEmptyValuesFilter;
        $this->productModelEmptyValuesFilter = $productModelEmptyValuesFilter;
        $this->currencyRepository = $currencyRepository;
    }


    /**
     * {@inheritdoc}
     */
    public function initialize(): void
    {
        $jobParameters = $this->stepExecution->getJobParameters()->all();
        $this->baseCurrency = $jobParameters['base_currency'];
        $this->currencyConversionRate = $jobParameters['currencies_rate'];
        $this->activatedCurrencyCodes = $this->currencyRepository->getActivatedCurrencyCodes();
    }


    /**
     * {@inheritdoc}
     */
    public function process($entity)
    {
        if (!$this->isEntityEditable($entity)) {
            $this->stepExecution->incrementSummaryInfo('skipped_products');

            return null;
        }

        $filteredValues = $this->extractValuesToUpdate($entity);
        if ($entity instanceof ProductInterface) {
            $filteredValues = $this->productEmptyValuesFilter->filter($entity, ['values' => $filteredValues]);
        } else {
            $filteredValues = $this->productModelEmptyValuesFilter->filter($entity, ['values' => $filteredValues]);
        }

        if (empty($filteredValues['values'])) {
            $this->stepExecution->incrementSummaryInfo('skipped_products');

            return null;
        }

        $entity = $this->updateEntity($entity, $filteredValues['values']);
        if (!$this->isValid($entity)) {
            $this->stepExecution->incrementSummaryInfo('skipped_products');

            return null;
        }

        return $entity;
    }

    /**
     * @param EntityWithFamilyInterface $entity
     * @param array                     $filteredValues
     *
     * @return EntityWithFamilyInterface
     */
    protected function updateEntity(EntityWithFamilyInterface $entity, array $filteredValues): EntityWithFamilyInterface
    {
        if ($entity instanceof ProductInterface) {
            $this->productUpdater->update($entity, ['values' => $filteredValues]);
        } else {
            $this->productModelUpdater->update($entity, ['values' => $filteredValues]);
        }

        return $entity;
    }

    /**
     * @param EntityWithFamilyInterface $entity
     * @param string                    $attributeCode
     *
     * @return bool
     *
     * @throws \Exception
     */
    protected function isAttributeEditable(EntityWithFamilyInterface $entity, string $attributeCode): bool
    {
        $attribute = $this->attributeRepository->findOneByIdentifier($attributeCode);

        return $this->checkAttributeEditable->isEditable($entity, $attribute);
    }

    /**
     * Validate the entity
     *
     * @param EntityWithFamilyInterface $entity
     *
     * @return bool
     */
    protected function isValid(EntityWithFamilyInterface $entity): bool
    {
        if ($entity instanceof ProductInterface) {
            $violations = $this->productValidator->validate($entity);
        } else {
            $violations = $this->productModelValidator->validate($entity);
        }
        $this->addWarningMessage($violations, $entity);

        return 0 === $violations->count();
    }

    /**
     * Sadly, this is override in Enterprise Edition to check the permissions of the entity.
     *
     * @param EntityWithFamilyInterface $entity
     *
     * @return bool
     */
    protected function isEntityEditable(EntityWithFamilyInterface $entity): bool
    {
        return true;
    }

    /**
     * @param EntityWithFamilyInterface $entity
     */
    protected function addWarning(EntityWithFamilyInterface $entity): void
    {
        $this->stepExecution->addWarning(
            'pim_enrich.mass_edit_action.edit-common-attributes.message.no_valid_attribute',
            [],
            new DataInvalidItem(
                [
                    'class'  => ClassUtils::getClass($entity),
                    'id'     => $entity->getId(),
                    'string' => $entity->getIdentifier(),
                ]
            )
        );
    }

    /**
     * @param EntityWithFamilyInterface $entity
     *
     * @return array
     */
    private function extractValuesToUpdate(EntityWithFamilyInterface $entity): array
    {
        $normalizedValues = $this->convertCurrencyRate($entity);
        $filteredValues = [];
        foreach ($normalizedValues as $attributeCode => $values) {
            if ($this->isAttributeEditable($entity, $attributeCode)) {
                $filteredValues[$attributeCode] = $values;
            }
        }
        
        return $filteredValues;
    }

    /**
     * Convert currency rate
     *
     * @param ProductModelInterface|ProductInterface $entity
     *
     * @return array
     */
    private function convertCurrencyRate($entity)
    {
        $attributeValues = [];
        $entityValues = $entity->getValues();
        foreach ($entityValues as $value) {
            $attribute = $this->attributeRepository->findOneByIdentifier($value->getAttributeCode());
            if (null !== $attribute && $attribute->getType() === 'pim_catalog_price_collection') {
                $priceValues = $value->getData()->getValues();
                $priceValuesForCurrencies = array_filter(array_map(
                    function ($priceValue) {
                        return [
                                'currency' => $priceValue->getCurrency(),
                                'amount'   => $priceValue->getData()
                            ];
                    },
                    $priceValues
                ));
                $priceValuesForCurrencies = array_column($priceValuesForCurrencies, 'amount', 'currency');
                $localeCode = $value->getLocaleCode() ? $value->getLocaleCode() : null;
                $channelCode = $value->getScopeCode() ? $value->getScopeCode() : null;
                $attributeValue =  [
                    'locale' => $localeCode,
                    'scope'  => $channelCode,
                    'data' => []
                ];
                $basePriceValue = isset($priceValuesForCurrencies[$this->baseCurrency]) ? $priceValuesForCurrencies[$this->baseCurrency] : null;
                if ($basePriceValue !== null) {
                    foreach ($this->activatedCurrencyCodes as $activeCurrencyCode) {
                        $currencyConversionRate = isset($this->currencyConversionRate[$activeCurrencyCode]) ? $this->currencyConversionRate[$activeCurrencyCode] : null;
                        $amount = isset($priceValuesForCurrencies[$activeCurrencyCode]) ? $priceValuesForCurrencies[$activeCurrencyCode] : 0;
                        if ($currencyConversionRate) {
                            $amount =  ($basePriceValue * $currencyConversionRate);
                        }
                       
                        $attributeValue['data'][] = [
                            'amount' => $amount,
                            'currency' => $activeCurrencyCode
                        ];
                    }

                    if (!empty($attributeValue['data'])) {
                        $attributeValues[$value->getAttributeCode()][] = $attributeValue;
                    }
                }
            }
        }

        return $attributeValues;
    }
}
