<?php

declare(strict_types=1);

namespace KTPL\CurrencyRateConversionBundle\JobParameters\ConstraintCollectionProvider;

use Akeneo\Tool\Component\Batch\Job\JobInterface;
use Akeneo\Tool\Component\Batch\Job\JobParameters\ConstraintCollectionProviderInterface;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Optional;

class ComputeCurrencyRateConversion implements ConstraintCollectionProviderInterface
{
    /** @var array */
    private $supportedJobNames;

    /**
     * @param array $supportedJobNames
     */
    public function __construct(array $supportedJobNames)
    {
        $this->supportedJobNames = $supportedJobNames;
    }

    /**
     * {@inheritdoc}
     */
    public function getConstraintCollection(): Collection
    {
        return new Collection(
            [
                'fields' => [
                    'base_currency' => new Optional(null),
                    'currencies_rate' => new Optional(null),
                    'realTimeVersioning' => new Optional(null),
                ],
            ]
        );
    }

    /**
     * {@inheritdoc}
     */
    public function supports(JobInterface $job): bool
    {
        return in_array($job->getName(), $this->supportedJobNames);
    }
}
