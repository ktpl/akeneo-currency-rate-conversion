<?php

namespace KTPL\CurrencyRateConversionBundle\Commands;

use Akeneo\Tool\Bundle\BatchBundle\Job\JobInstanceRepository;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Command\Command;

class InstallationCommand extends Command
{
    /** @var string Default command name */
    protected static $defaultName = 'ktpl:install:currency-rate-conversion';

    /** @var array */
    protected $massActionJobs = [
        'compute_currency_conversion' => [
            'connector' => 'Akeneo Currency Rate Conversion Mass Edit Connector',
            'type' => 'mass_edit',
            'code' => 'compute_currency_conversion',
            'config' => '{}',
            'label' => 'Mass edit convert currency rate'
        ],
    ];
 
    /** @var JobInstanceRepository */
    private $jobInstanceRepository;

    public function __construct(
        JobInstanceRepository $jobInstanceRepository
    ) {
        parent::__construct();

        $this->jobInstanceRepository = $jobInstanceRepository;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('ktpl:install:currency-rate-conversion')
            ->setDescription('Install Currency Rate Conversion Bundle')
            ->setHelp('install currency rate conversion');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->runInstallationCommand($input, $output);
        $this->createMassActionJob($input, $output);
    }

    /**
     * Run installation command
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function runInstallationCommand(InputInterface $input, OutputInterface $output)
    {
        shell_exec('rm -rf ./var/cache/ && php bin/console cache:warmup;');
        shell_exec('rm -rf public/bundles public/js');
        $this->runCommand(
            'pim:installer:assets',
            [
                '--clean' => null,
                '--symlink'  => null,
            ],
            $output
        );

        $yarn_pkg = preg_replace("/\r|\n/", "", shell_exec('which yarnpkg || which yarn || echo "yarn"'));

        shell_exec('rm -rf public/css');
        shell_exec($yarn_pkg . ' run less');

        shell_exec('rm -rf public/dist-dev');
        shell_exec($yarn_pkg . ' run webpack-dev');

        $this->runCommand(
            'doctrine:schema:update',
            [
                '--force' => null,
            ],
            $output
        );
    }

    /**
     * Create mass action jobs
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function createMassActionJob(InputInterface $input, OutputInterface $output)
    {
        foreach ($this->massActionJobs as $massActionJobCode => $massActionJob) {
            $jobInstance = $this->jobInstanceRepository->findOneByIdentifier($massActionJob['code']);
            if ($jobInstance) {
                continue;
            }
            
            $this->runCommand(
                'akeneo:batch:create-job',
                [
                    'connector' => $massActionJob['connector'],
                    'job' => $massActionJobCode,
                    'type' => $massActionJob['type'],
                    'code' => $massActionJob['code'],
                    'config' => $massActionJob['config'],
                    'label' => $massActionJob['label'],
                ],
                $output
            );
        }
    }

    /**
     * Run commnd
     *
     * @param string          $name
     * @param array           $args
     * @param OutputInterface $output
     */
    protected function runCommand($name, array $args, $output)
    {
        $command = $this->getApplication()->find($name);
        $commandInput = new ArrayInput(
            $args
        );
        $command->run($commandInput, $output);
    }
}
