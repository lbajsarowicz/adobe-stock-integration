<?php

/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\AdobeStockImageAdminUi\Ui\Component\Listing\Filter;

use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Filters\FilterModifier;
use Magento\Ui\Component\Form\Element\ColorPicker;
use Magento\Ui\Component\Form\Element\Input as ElementInput;
use Magento\Ui\Component\Filters\Type\AbstractFilter;
use Magento\Ui\Model\ColorPicker\ColorModesProvider;

class Color extends AbstractFilter
{
    const COMPONENT = 'input';

    /**
     * Wrapped component
     *
     * @var ElementInput
     */
    private $wrappedComponent;

    /**
     * Provides color picker modes configuration
     *
     * @var ColorModesProvider
     */
    private $modesProvider;

    /**
     * Color constructor.
     * @param ContextInterface   $context
     * @param UiComponentFactory $uiComponentFactory
     * @param FilterBuilder      $filterBuilder
     * @param FilterModifier     $filterModifier
     * @param ColorModesProvider $modesProvider
     * @param array              $components
     * @param array              $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        FilterBuilder $filterBuilder,
        FilterModifier $filterModifier,
        ColorModesProvider $modesProvider,
        array $components = [],
        array $data = []
    ) {
        $this->modesProvider = $modesProvider;
        parent::__construct(
            $context,
            $uiComponentFactory,
            $filterBuilder,
            $filterModifier,
            $components,
            $data
        );
    }

    /**
     * Prepare component configuration
     *
     * @return void
     * @throws LocalizedException
     */
    public function prepare(): void
    {
        $this->wrappedComponent = $this->uiComponentFactory->create(
            $this->getName(),
            static::COMPONENT,
            ['context' => $this->getContext()]
        );
        $this->wrappedComponent->prepare();
        // Merge JS configuration with wrapped component configuration
        $jsConfig = array_replace_recursive(
            $this->getJsConfig($this->wrappedComponent),
            $this->getJsConfig($this)
        );
        $this->setData('js_config', $jsConfig);

        $this->setData(
            'config',
            array_replace_recursive(
                (array)$this->wrappedComponent->getData('config'),
                (array)$this->getData('config')
            )
        );

        $this->applyFilter();

        $this->initColorPickerConfig();

        parent::prepare();
    }

    /**
     * Initialize Color Picker Config
     */
    private function initColorPickerConfig()
    {
        $modes = $this->modesProvider->getModes();
        $colorPickerModeSetting = $this->getData('config/colorPickerMode');
        $colorFormatSetting = $this->getData('config/colorFormat');
        $colorPickerMode = $modes[$colorPickerModeSetting] ?? $modes[ColorPicker::DEFAULT_MODE];
        $colorPickerMode['preferredFormat'] = $colorFormatSetting;
        $this->_data['config']['colorPickerConfig'] = $colorPickerMode;
    }

    /**
     * Apply filter
     *
     * @return void
     */
    protected function applyFilter(): void
    {
        if (isset($this->filterData[$this->getName()])) {
            $value = str_replace(['%', '_'], ['\%', '\_'], $this->filterData[$this->getName()]);

            if ($value || $value === '0') {
                $filter = $this->filterBuilder->setConditionType('like')
                    ->setField($this->getName())
                    ->setValue(str_replace('#', '', urldecode($value)))
                    ->create();

                $this->getContext()->getDataProvider()->addFilter($filter);
            }
        }
    }
}
