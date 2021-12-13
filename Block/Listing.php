<?php
namespace MediaLounge\Storyblok\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

/**
 * Class Listing
 * @package MediaLounge\Storyblok\Block
 */
class Listing extends Template
{

    /**
     * Listing constructor.
     * @param Context $context
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        array $data = [])
    {
        parent::__construct($context, $data);
    }


    /**
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function _toHtml()
    {
        $stories = $this->getData('stories');

        foreach ($stories as $story) {
            $block = $this->getLayout()
                ->createBlock('\MediaLounge\Storyblok\Block\Container', $story['slug'])
                ->setData([
                    'is_list_item' => true,
                    'story' => $story
                ]);

            $this->append($block);
        }
        return parent::_toHtml();
    }

}
