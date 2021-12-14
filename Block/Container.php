<?php
namespace MediaLounge\Storyblok\Block;

use Storyblok\ApiException;
use Magento\Framework\View\FileSystem;
use Magento\Store\Model\ScopeInterface;
use Storyblok\Client as StoryblokClient;
use Magento\Framework\View\Element\AbstractBlock;
use MediaLounge\Storyblok\Block\Container\Element;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Storyblok\ClientFactory as StoryblokClientFactory;

class Container extends \Magento\Framework\View\Element\Template implements IdentityInterface
{
    /**
     * @var StoryblokClient
     */
    private $storyblokClient;

    /**
     * @var FileSystem
     */
    private $viewFileSystem;

    public function __construct(
        FileSystem $viewFileSystem,
        StoryblokClientFactory $storyblokClient,
        ScopeConfigInterface $scopeConfig,
        Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->viewFileSystem = $viewFileSystem;
        $this->storyblokClient = $storyblokClient->create([
            'apiKey' => $scopeConfig->getValue(
                'storyblok/general/api_key',
                ScopeInterface::SCOPE_STORE,
                $this->_storeManager->getStore()->getId()
            )
        ]);
    }

    public function getCacheLifetime()
    {
        return parent::getCacheLifetime() ?: 3600;
    }

    public function getIdentities(): array
    {
        if (!empty($this->getSlug())) {
            return ["storyblok_slug_{$this->getSlug()}"];
        } elseif (!empty($this->getData('story')['id'])) {
            return ["storyblok_{$this->getData('story')['id']}"];
        }

        return [];
    }

    public function getCacheKeyInfo(): array
    {
        $info = parent::getCacheKeyInfo();

        if (!empty($this->getData('story')['id'])) {
            $info[] = "storyblok_{$this->getData('story')['id']}";
        } elseif (!empty($this->getSlug())) {
            $info[] = "storyblok_slug_{$this->getSlug()}";
        }

        return $info;
    }

    private function getStory(): array
    {
        if (!$this->getData('story')) {
            try {
                $storyblokClient = $this->storyblokClient->getStoryBySlug($this->getSlug());
                $data = $storyblokClient->getBody();

                $this->setData('story', $data['story']);
            } catch (ApiException $e) {
                return [];
            }
        }

        $story = $this->getData('story');
        $slug = $story['full_slug'];
        if ($story['is_startpage'] === true) {
            $childStories = $this->getChildStories($slug);
            if (!empty($childStories) && !empty($childStories['stories'])) {
                $story['child_stories'] = $childStories['stories'];
                $this->setData('story', $story);
            }
        }

        return $this->getData('story');
    }

    private function isArrayOfBlocks(array $data): bool
    {
        return count($data) !== count($data, COUNT_RECURSIVE);
    }

    private function createBlockFromData(array $blockData): Element
    {
        $block = $this->getLayout()
            ->createBlock(
                Element::class,
                $this->getNameInLayout()
                    ? $this->getNameInLayout() . '_' . $blockData['_uid']
                    : $blockData['_uid']
            )
            ->setData($blockData);

        $templateFile = $this->getIsListItem()
            ? "MediaLounge_Storyblok::story/{$blockData['component']}-list-item.phtml"
            : "MediaLounge_Storyblok::story/{$blockData['component']}.phtml";

        $templatePath = $this->viewFileSystem->getTemplateFileName($templateFile);

        if ($templatePath) {
            $block->setTemplate($templateFile);
        } else {
            $block->setTemplate('MediaLounge_Storyblok::story/debug.phtml')->addData([
                'original_template' => $templateFile
            ]);
        }

        $this->appendChildBlocks($block, $blockData);

        return $block;
    }

    private function appendChildBlocks(AbstractBlock $parentBlock, array $blockData): void
    {
        foreach ($blockData as $data) {
            if (is_array($data) && $this->isArrayOfBlocks($data)) {
                foreach ($data as $childData) {
                    // Ignore if rich text editor block
                    if (empty($childData['_uid'])) {
                        continue;
                    }

                    $childBlock = $this->createBlockFromData($childData);

                    $parentBlock->append($childBlock);
                }
            }
        }
    }

    protected function _toHtml(): string
    {
        $storyData = $this->getStory();

        if ($storyData) {
            $blockData = $storyData['content'] ?? [];
            $parentBlock = $this->createBlockFromData($blockData);

            if (!empty($storyData['child_stories'])) {
                $block = $this->getLayout()
                    ->createBlock('\MediaLounge\Storyblok\Block\Listing', $storyData['content']['component'])
                    ->setTemplate("MediaLounge_Storyblok::story/{$storyData['content']['component']}.phtml")
                    ->setData('stories', $storyData['child_stories']);
                $parentBlock->append($block);
            }
            return $parentBlock->toHtml();
        }
        return '';
    }

    /**
     * @param string $slug
     * @return array
     */
    private function getChildStories($slug): array
    {
        $storyblokClient = $this->storyblokClient->getStories([
            'starts_with' => $slug,
            'is_startpage' => 0
        ]);
        $childStories = $storyblokClient->getBody();
        return $childStories;
    }
}
