<?php

namespace Magefoo\Magento\Command\Customer;

use Magento\Framework\App\State;
use Magento\Framework\App\Area;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SendEmailCommand extends \N98\Magento\Command\Customer\AbstractCustomerCommand
{
    protected $_state;
    protected $_scopeConfig;
    protected $_storeManager;
    protected $inlineTranslation;
    protected $_transportBuilder;
    protected $_config;
    protected $_template;
    protected $productMetadata;

    protected function configure()
    {
        $this
            ->setName('customer:sendemail')
            ->addArgument('email', InputArgument::REQUIRED, 'Email')
            ->setDescription('Send Random Order Email through Queue, to test email system');
    }

    public function inject(
        \Magento\Framework\App\State $state,
        \Magento\Framework\App\ProductMetadataInterface $productMetadata,
        \Magento\Framework\Mail\Template\TransportBuilder $transportBuilder,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Translate\Inline\StateInterface $inlineTranslation,
        \Magento\Framework\App\Config $config
    ) {
        $this->_state = $state;
        $this->productMetadata = $productMetadata;
        $this->_storeManager = $storeManager;
        $this->inlineTranslation = $inlineTranslation;
        $this->_transportBuilder = $transportBuilder;
        $this->_config = $config;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Detect and Init Magento
        $this->detectMagento($output, true);
        if(!$this->initMagento()) {
            return;
        }

        if (version_compare($this->productMetadata->getVersion(), '2.1.5', '<') || (version_compare($this->productMetadata->getVersion(), '2.2.0', '>') && version_compare($this->productMetadata->getVersion(), '2.2.3', '<'))) {
            $output->writeln('Not compatible with this version of Magento, please upgrade to latest version.');
            return;
        }
        $this->_state->setAreaCode(\Magento\Framework\App\Area::AREA_FRONTEND);

        $stores = $this->_storeManager->getStores();

        foreach ($stores as $store) {
            $storeCode = $store->getCode();
            break;
        }

        $transport = $this->_transportBuilder->setTemplateIdentifier(
            'sales_email_order_template'
        )->setTemplateOptions(
            array('area' => \Magento\Framework\App\Area::AREA_FRONTEND, 'store' => $storeCode)
        )->setTemplateVars(
            array('user' => $input->getArgument('email'), 'store' => $this->_storeManager->getStore($storeCode))
        )->setFrom(
            array('name' => 'test', 'email' => $this->_config->getValue('trans_email/ident_sales/email'))
        )->addTo(
            $input->getArgument('email'), 'test'
        )->getTransport();

        $this->inlineTranslation->suspend();

        try {
            $transport->sendMessage();
        } catch(\Exception $e) {
            echo "there was an error: " . $e->getMessage();
        }
        
        $this->inlineTranslation->resume();

        $output->writeln("email successfully sent to: " . $input->getArgument('email'));

    }
}
