<?php

namespace Room204\UpdateNameMeta\Console\Command;

use Magento\Framework\App\Filesystem\DirectoryList;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\Registry;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Goodwill\AdminTheme\Model\PricingAlgorithmFunctions;
use Magento\Framework\Filesystem;


/**
 * Created by PhpStorm.
 * User: markcyrulik
 * Date: 11/1/16
 * Time: 6:58 PM
 */
class UpdateInfo extends Command
{

    protected $productCollection;

    protected $_registry;

    protected $_state;

    protected $_categoryRepository;

    protected $_productRepository;

    protected $_priceFunctions;

    /**
     * @var Filesystem
     */
    protected $_filesystem;

    /**
     * Constructor
     */
    public function __construct(
        \Magento\Framework\App\State $state,
        CollectionFactory $productCollection,
        CategoryRepositoryInterface $categoryRepository,
        Registry $registry,
        ProductRepositoryInterface $productRepository,
        \Magento\Eav\Model\Config $eavConfig,
        Filesystem $filesystem

    ) {
        $this->_priceFunctions = new PricingAlgorithmFunctions($eavConfig, $categoryRepository);
        $this->productCollection = $productCollection;
        $this->_registry = $registry;
        $this->_state = $state;
        $this->_filesystem = $filesystem;
        $this->_categoryRepository = $categoryRepository;
        $this->_productRepository = $productRepository;
        parent::__construct();
    }

    /**
     *
     */
    protected function configure()
    {
        $this->setName('room204:updateinfo')
            ->setDescription('Update the Info');
        parent::configure();;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $path = $this->_filesystem->getDirectoryWrite(DirectoryList::VAR_DIR)->getAbsolutePath("reports");
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }

        $filePath = $path."/".'MetaUpdates.txt';

        $this->_registry->register('isSecureArea', true);
        $this->_state->setAreaCode('adminhtml');

        $allProducts = $this->productCollection
            ->create()
            ->addAttributeToSelect('*');

        $output->writeln('Updating Name, meta, etc..');
        $counter = 1;

        /** @var \Magento\Catalog\Model\Product\Interceptor $product */
        foreach ($allProducts as $product) {
            $brand = $product->getData('manufacturer');
            $condition = $product->getData('gw_condition');
            /** @var \Magento\Catalog\Model\Category\Interceptor $category */
            $category = $this->_categoryRepository->get($product->getCategoryIds()[0]);
            $parent = $this->getCategoryParent($product->getCategoryIds()[0]);
            $specSize = (empty($product->getData('gw_specialty_length')) ? null : $product->getData('gw_specialty_length'));


            $output->write(str_pad($counter, 6, "0", STR_PAD_LEFT)."\t");
            $output->write($product->getSku()."\t");

            $categoryText = "Women/".$parent."/".$category->getName();

            if ($parent == "Plus" && $category->getId() == 62) {
                $size = $product->getAttributeText('gw_bra_size');
            } else if ($parent == "Plus") {
                $size = $product->getAttributeText('gw_size_plus_us');
            } else {
                $size = $product->getAttributeText('gw_size_womens_us');
            }

            $productName = $categoryText." ".$product->getAttributeText('manufacturer')." Size ".$size;
            $urlKey = $categoryText." ".$product->getAttributeText('manufacturer')." Size ".$size." ".$product->getSku();

            $metaTitle = $categoryText." ".$product->getAttributeText('manufacturer')." Size ".$size;
            $metaDescription = "Get This ".$product->getAttributeText('manufacturer')." ".$categoryText;

            $currentPrice = number_format($product->getPrice(), 2);
            $newPrice = number_format($this->_priceFunctions->getAlgorithmPrice($brand, $category->getId(), $condition, $parent, $specSize), 2);

            $product->setName($productName);
            $product->setData('meta_description', $metaDescription);
            $product->setData('meta_title', $metaTitle);
            $product->setUrlKey($this->urlFilter($urlKey));
            $product->setPrice($newPrice);

            if ($currentPrice != $newPrice) {
                $changed = "CHANGED\t";
            } else {
                $changed = "UNCHANGED\t";
            }


            $output->write($currentPrice."\t".$newPrice."\t".$changed);

//            $product->save();
            $this->_productRepository->save($product);

            $output->writeln($productName);
            $counter++;
        }

    }

    /**
     * @param string $text
     * @return string
     */
    private function urlFilter($text)
    {
        $text = str_replace(' ', '-', $text);
        $text = preg_replace("[^A-Za-z0-9-]", "", $text);
        return strtolower($text);
    }

    /**
     * @param $category
     *
     * @return string
     */
    private function getCategoryParent($category)
    {
        $category = $this->_categoryRepository->get((int) $category);

        return $this->_categoryRepository->get($category->getParentId())->getName();
    }
}