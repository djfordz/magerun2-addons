<?php

namespace Magefoo\Magento\Command\Customer;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use N98\Util\Console\Helper\InjectionHelper;
use N98\Magento\Command\Customer\AbstractCustomerCommand;


class SendEmailCommand extends AbstractCustomerCommand
{

    const XML_PATH_EMAIL_ORDER_TEMPLATE = 'sales_email_order_template';
    const XML_PATH_EMAIL_IDENTITY = 'trans_email/ident_sales/email';

    protected $_context;
    protected $_registry;
    protected $_appState;
    protected $_scopeConfig;
    protected $_storeManagerFactory;
    protected $inlineTranslation;
    protected $_transportBuilder;
    protected $_config;
    protected $_template;
    protected $productMetadata;
    protected $_website;

    protected function configure()
    {
        $this
            ->setName('customer:sendemail')
            ->addArgument('email', InputArgument::REQUIRED, 'Email')
            ->setDescription('Send Random Order Email through Queue, to test email system');
        parent::configure();
    }

    public function inject(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\View\LayoutFactory $layoutFactory,
        \Magento\Framework\App\State $appState,
        \Magento\Store\Model\App\Emulation $appEmulation,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Mail\Template\TransportBuilder $transportBuilder,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Translate\Inline\StateInterface $inlineTranslation,
        \Magento\Framework\App\Config $config
    ) {
        $this->_context = $context;
        $this->registry = $registry;
        $this->_layoutFactory = $layoutFactory;
        $this->_appState = $appState;
        $this->_appEmulation = $appEmulation;
        $this->_scopeConfig = $scopeConfig;
        $this->_storeManager = $storeManager;
        $this->inlineTranslation = $inlineTranslation;
        $this->_transportBuilder = $transportBuilder;
        $this->_config = $config;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        
        $this->_appState->setAreaCode(\Magento\Framework\App\Area::AREA_FRONTEND);

        // Detect and Init Magento
        $this->detectMagento($output, true);
        if (!$this->initMagento()) {
            return;
        }

        $this->_appState->emulateAreaCode(
            \Magento\Framework\App\Area::AREA_FRONTEND, function () use (
                $input
            ) {

                $websites = $this->_storeManager->getWebsites();
                foreach ($websites as $website) {
                    $this->_website = $website;
                    break;
                }

                $store = $this->_website->getDefaultStore();

                $storeId = $store->getId();

                $this->_appEmulation->startEnvironmentEmulation($storeId);

                print_r("sending email...\n");
                $layout = $this->_layoutFactory->create(['cacheable' => false]);
                $layout->getUpdate()->addHandle('test')->load();

                $block = $layout->createBlock(\Magento\Cms\Block\Block::class);

                $alertGrid = $this->_appState->emulateAreaCode(
                    \Magento\Framework\App\Area::AREA_FRONTEND,
                    [$block, 'toHtml']
                );

                $this->_appEmulation->stopEnvironmentEmulation();

                $transport = $this->_transportBuilder->setTemplateIdentifier(
                    self::XML_PATH_EMAIL_ORDER_TEMPLATE
                )->setTemplateOptions(
                    [
                        'area' => \Magento\Framework\App\Area::AREA_FRONTEND,
                        'store' => $storeId,
                    ]
                )->setTemplateVars(
                     [
                        'customerName' => 'test',
                        'alertGrid' => $alertGrid,
                    ] 
                )->setFrom(
                    $this->senderEmail(null, $storeId)
                )->addTo(
                    $input->getArgument('email'), 'test'
                )->getTransport();

                $this->inlineTranslation->suspend();

                $transport->sendMessage();
                
                $this->inlineTranslation->resume();
            }
        );
        
        $output->writeln("email successfully sent to: " . $input->getArgument('email'));
    }

    public function senderEmail($type = null, $storeId = null) {
        $sender['email'] = $this->_scopeConfig->getValue(
            self::XML_PATH_EMAIL_IDENTITY,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );
        $sender['name'] = 'admin';

        return $sender;
    }

    public function emailTemplate($storeId = null)
    {
        $templateId = $this->_scopeConfig->getValue(
            self::XML_PATH_EMAIL_ORDER_TEMPLATE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $templateId;
    }

    public function emulateAreaCallback()
    {
        $skipParams = ['handle', 'area'];

        /** @var $layout \Magento\Framework\View\LayoutInterface */
        $layout = $this->_layoutFactory->create(['cacheable' => false]);
        $layout->getUpdate()->addHandle('test')->load();

        $layout->generateXml();
        $layout->generateElements();

        $rootBlock = false;
        foreach ($layout->getAllBlocks() as $block) {
            /* @var $block \Magento\Framework\View\Element\AbstractBlock */
            if (!$block->getParentBlock() && !$rootBlock) {
                $rootBlock = $block;
            }
            foreach ($this->_directiveParams as $k => $v) {
                if (in_array($k, $skipParams)) {
                    continue;
                }
                $block->setDataUsingMethod($k, $v);
            }
        }

        /**
         * Add root block to output
         */
        if ($rootBlock) {
            $layout->addOutputElement($rootBlock->getNameInLayout());
        }

        $result = $layout->getOutput();
        $layout->__destruct();
        // To overcome bug with SimpleXML memory leak (https://bugs.php.net/bug.php?id=62468)
        return $result;
    }

}
