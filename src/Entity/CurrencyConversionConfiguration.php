<?php

namespace KTPL\CurrencyRateConversionBundle\Entity;

class CurrencyConversionConfiguration
{
    /** @var int */
    protected $id;

    /** @var string*/
    protected $section;

    /** @var json */
    protected $configuration;
    
    public function getId()
    {
        return $this->id;
    }

    public function getSection()
    {
        return $this->section;
    }

    public function setSection($section)
    {
        $this->section = $section;

        return $this;
    }

    public function getConfiguration()
    {
        return $this->configuration;
    }

    public function setConfiguration($configuration)
    {
        $this->configuration = $configuration;

        return $this;
    }
}
