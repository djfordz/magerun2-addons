<?php

namespace Magefoo\Magento\Command\System;

use N98\Magento\Command\AbstractMagentoCommand;
use N98\Util\Console\Helper\Table\Renderer\RendererFactory;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\App\State;
use Magento\Framework\DataObject;

class Sitemap extends AbstractMagentoCommand
{

    const NAME_ARGUMENT = "name";
    const PATH_ARGUMENT = "path";

    protected $_scopeConfig;

    protected $_storeManager;

    protected $_sitemapFactory;

    protected $_dateModel;

    protected $_datetime;

    protected $_state;

    protected $productMetadata;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName("sitemap:generate");
        $this->setDescription("Generate xml sitemap");
        $this->setDefinition([
            new InputArgument(self::NAME_ARGUMENT, InputArgument::REQUIRED, "Name"),
            new InputArgument(self::PATH_ARGUMENT, InputArgument::REQUIRED, "Path")
        ]);
        parent::configure();
    }

    

    public function inject(
        State $state,
        \Magento\Framework\App\ProductMetadataInterface $productMetadata,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Sitemap\Model\SitemapFactory $sitemapFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Stdlib\DateTime\DateTime $modelDate,
        \Magento\Framework\Stdlib\DateTime $dateTime

    ) {
        $this->_state = $state;
        $this->productMetadata = $productMetadata;
        $this->_scopeConfig = $scopeConfig;
        $this->_sitemapFactory = $sitemapFactory;
        $this->_storeManager = $storeManager;
        $this->_dateModel = $modelDate;
        $this->_dateTime = $dateTime;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ) {

        $errors = [];
        // Detect and Init Magento
        $this->detectMagento($output, true);
        if(!$this->initMagento()) {
            $errors[] = 'No Magento found';
            $output->writeln('No Magento found');
            return;
        }

        if ((version_compare($this->productMetadata->getVersion(), '2.1.5', '<') && version_compare($this->productMetadata->getVersion(), '2.2.0', '>')) || (version_compare($this->productMetadata->getVersion(), '2.2.0', '>') && version_compare($this->productMetadata->getVersion(), '2.2.3', '<'))) {
            $output->writeln('Not compatible with this version of Magento, please upgrade to latest version.');
            return;
        }


        $this->_state->setAreaCode(\Magento\Framework\App\Area::AREA_ADMINHTML);

        $stores = $this->_storeManager
            ->getStores();

        foreach ($stores as $store) {
            $storeId = $store->getId();
            break;
        }

            $sitemap = $this->_sitemapFactory
                ->create([
                    'area' => \Magento\Framework\App\Area::AREA_ADMINHTML,
                    'store' => \Magento\Store\Model\Store::DEFAULT_STORE_ID,
                ]);


            $sitemap
                ->setSitemapPath($input->getArgument('path'))
                ->setSitemapFilename($input->getArgument('name'))
                ->setStoreId($storeId)
                ->save();

            $sitemap->generateXml();

            $output->writeln('sitemap generated.');

    }
}

