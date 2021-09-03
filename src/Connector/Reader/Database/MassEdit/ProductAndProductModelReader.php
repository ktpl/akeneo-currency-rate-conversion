<?php

declare(strict_types=1);

namespace KTPL\CurrencyRateConversionBundle\Connector\Reader\Database\MassEdit;

use Akeneo\Channel\Component\Model\ChannelInterface;
use Akeneo\Channel\Component\Repository\ChannelRepositoryInterface;
use Akeneo\Pim\Enrichment\Component\Product\Exception\ObjectNotFoundException;
use Akeneo\Pim\Enrichment\Component\Product\Model\EntityWithFamilyInterface;
use Akeneo\Pim\Enrichment\Component\Product\Query\Filter\Operators;
use Akeneo\Pim\Enrichment\Component\Product\Query\ProductQueryBuilderFactoryInterface;
use Akeneo\Tool\Component\Batch\Item\DataInvalidItem;
use Akeneo\Tool\Component\Batch\Item\InitializableInterface;
use Akeneo\Tool\Component\Batch\Item\ItemReaderInterface;
use Akeneo\Tool\Component\Batch\Item\TrackableItemReaderInterface;
use Akeneo\Tool\Component\Batch\Model\StepExecution;
use Akeneo\Tool\Component\Batch\Step\StepExecutionAwareInterface;
use Akeneo\Tool\Component\StorageUtils\Cursor\CursorInterface;
use Akeneo\Tool\Component\StorageUtils\Repository\IdentifiableObjectRepositoryInterface;

class ProductAndProductModelReader implements
    ItemReaderInterface,
    InitializableInterface,
    StepExecutionAwareInterface,
    TrackableItemReaderInterface
{
    /** @var ProductQueryBuilderFactoryInterface */
    private $pqbFactory;

    /** @var ChannelRepositoryInterface */
    private $channelRepository;

    /** @var StepExecution */
    private $stepExecution;

    /** @var CursorInterface */
    private $productsAndProductModels;

    /** @var IdentifiableObjectRepositoryInterface */
    protected $currencyRepository;

    /** @var bool */
    private $readChildren;

    /** @var bool */
    private $firstRead = true;

    /** @var bool */
    private $readProduct = true;

    /** @var string */
    private $baseCurrency;

    /** @var array */
    private $currencyConversionRate = [];

    /**
     * @param ProductQueryBuilderFactoryInterface   $pqbFactory
     * @param ChannelRepositoryInterface            $channelRepository
     * @param bool                                  $readChildren
     * @param IdentifiableObjectRepositoryInterface $currencyRepository
     */
    public function __construct(
        ProductQueryBuilderFactoryInterface $pqbFactory,
        ChannelRepositoryInterface $channelRepository,
        IdentifiableObjectRepositoryInterface $currencyRepository,
        bool $readChildren
    ) {
        $this->pqbFactory         = $pqbFactory;
        $this->channelRepository  = $channelRepository;
        $this->currencyRepository = $currencyRepository;
        $this->readChildren       = $readChildren;
    }

    /**
     * {@inheritdoc}
     */
    public function initialize(): void
    {
        $jobParameters = $this->stepExecution->getJobParameters()->all();
        $this->baseCurrency = $jobParameters['base_currency'];
        $this->currencyConversionRate = $jobParameters['currencies_rate'];
        if (empty($this->currencyConversionRate)) {
            $this->stepExecution->addWarning(
                'Currencies rate required for currency conversion are missing from the currency configuration',
                [],
                new DataInvalidItem([])
            );

            $this->readProduct = false;
        }

        if (!$this->baseCurrency) {
            $this->stepExecution->addWarning(
                'Base currency is missing from the currency configuration',
                [],
                new DataInvalidItem([])
            );
            $this->readProduct = false;
        } else {
            $currency = $this->currencyRepository->findOneByIdentifier($this->baseCurrency);
            if (!$currency) {
                $this->stepExecution->addWarning(
                    sprintf('Base currency %s is not active in akeneo', $this->baseCurrency),
                    [],
                    new DataInvalidItem([])
                );
                $this->readProduct = false;
            }

            if ($this->readProduct) {
                $channel = $this->getConfiguredChannel();
                $filters = $this->getConfiguredFilters();
                $this->productsAndProductModels = $this->getCursor($filters, $channel);
                $this->firstRead = true;
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function read(): ?EntityWithFamilyInterface
    {
        if (!$this->readProduct) {
            return null;
        }

        $entity = null;

        if ($this->productsAndProductModels->valid()) {
            if (!$this->firstRead) {
                $this->productsAndProductModels->next();
            }

            $entity = $this->productsAndProductModels->current();
            if (false === $entity) {
                return null;
            }
            $this->stepExecution->incrementSummaryInfo('read');
        }
        $this->firstRead = false;

        return $entity;
    }

    /**
     * {@inheritdoc}
     */
    public function setStepExecution(StepExecution $stepExecution): void
    {
        $this->stepExecution = $stepExecution;
    }

    /**
     * Returns the configured channel from the parameters.
     * If no channel is specified, returns null.
     *
     * @throws ObjectNotFoundException
     *
     * @return ChannelInterface|null
     */
    private function getConfiguredChannel(): ?ChannelInterface
    {
        return null;
    }

    /**
     * Returns the filters from the configuration.
     *
     * @return array
     */
    private function getConfiguredFilters(): array
    {
        return [];
    }

    /**
     * @param array            $filters
     * @param ChannelInterface $channel
     *
     * @return CursorInterface
     */
    private function getCursor(array $filters, ChannelInterface $channel = null): CursorInterface
    {
        $options = ['filters' => $filters];

        if (null !== $channel) {
            $options['default_scope'] = $channel->getCode();
        }

        $queryBuilder = $this->pqbFactory->create($options);

        return $queryBuilder->execute();
    }

    public function totalItems(): int
    {
        if (null === $this->productsAndProductModels) {
            throw new \RuntimeException('Unable to compute the total items the reader will process until the reader is not initialized');
        }

        return $this->productsAndProductModels->count();
    }
}
