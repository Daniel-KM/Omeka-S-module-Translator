<?php declare(strict_types=1);

namespace Translate\Form;

use Common\Form\Element as CommonElement;
use Laminas\Form\Fieldset;
use Omeka\Form\Element as OmekaElement;

class SettingsFieldset extends Fieldset
{
    /**
     * @var string
     */
    protected $label = 'Translate'; // @translate

    protected $elementGroups = [
        'translate' => 'Translate', // @translate
    ];

    public function init(): void
    {
        $this
            ->setAttribute('id', 'translate')
            ->setOption('element_groups', $this->elementGroups)

            ->add([
                'name' => 'translate_lang_source_default',
                'type' => Element\Text::class,
                'options' => [
                    'element_group' => 'translate',
                    'label' => 'Default language for source', // @translate
                    'info' => 'The language should be a 2-letter iso code (ISO 3166-1) supported by the translation service. If empty, it will be automatically detected, that is not recommended for short strings.', // @translate
                    'documentation' => 'https://developers.deepl.com/docs/getting-started/supported-languages',
                ],
                'attributes' => [
                    'id' => 'translate_lang_source_default',
                    'required' => false,
                    'minlength' => '1',
                    'maxlength' => '2',
                ],
            ])
            ->add([
                'name' => 'translate_lang_pairs',
                'type' => OmekaElement\ArrayTextarea::class,
                'options' => [
                    'element_group' => 'translate',
                    'label' => 'Pairs of languages to translate', // @translate
                    'info' => 'Separate each pair by "=", one by line. The source language should be a 2-letter iso code (ISO 3166-1) supported by the translation service. The target language may have the localization code if supported.', // @translate
                    'documentation' => 'https://developers.deepl.com/docs/getting-started/supported-languages',
                    // Most of the time, the same source is used for multiple targets.
                    'as_key_value' => false,
                ],
                'attributes' => [
                    'id' => 'translate_lang_pairs',
                    'required' => false,
                    'placeholder' => <<<'TXT'
                        fr = pt-br
                        TXT,
                ],
            ])

            ->add([
                'name' => 'translate_properties_include',
                'type' => CommonElement\OptionalPropertySelect::class,
                'options' => [
                    'element_group' => 'translate',
                    'label' => 'Properties to translate', // @translate
                    'info' => 'Only literal data are translated, not numeric values, resources, uri, or other data. It is recommended to remove big fields from the list of properties, in particular extracted text.', // @translate
                    'empty_option' => 'All', // @translate
                    'prepend_value_options' => [
                        'metadata' => [
                            'label' => 'Resource metadata', // @translate
                            'options' => [
                                'properties' => 'All properties', // @translate
                                'properties_max_500' => 'All properties less or equal to 500 characters', // @translate
                                'properties_max_1000' => 'All properties less or equal to 1000 characters', // @translate
                                'properties_max_5000' => 'All properties less or equal to 5000 characters', // @translate
                                'properties_min_500' => 'All properties more than 500 characters', // @translate
                                'properties_min_1000' => 'All properties more than 1000 characters', // @translate
                                'properties_min_5000' => 'All properties more than 5000 characters', // @translate
                            ],
                        ],
                    ],
                    'term_as_value' => true,
                ],
                'attributes' => [
                    'id' => 'translate_properties_include',
                    'class' => 'chosen-select',
                    'multiple' => true,
                    'data-placeholder' => 'Select properties…', // @translate
                ],
            ])
            ->add([
                'name' => 'translate_properties_exclude',
                'type' => CommonElement\OptionalPropertySelect::class,
                'options' => [
                    'element_group' => 'translate',
                    'label' => 'Properties not to translate', // @translate
                    'empty_option' => '',
                    'prepend_value_options' => [
                        'metadata' => [
                            'label' => 'Resource metadata', // @translate
                            'options' => [
                                'properties_min_500' => 'All properties more than 500 characters', // @translate
                                'properties_min_1000' => 'All properties more than 1000 characters', // @translate
                                'properties_min_5000' => 'All properties more than 5000 characters', // @translate
                                'properties_max_500' => 'All properties less or equal to 500 characters', // @translate
                                'properties_max_1000' => 'All properties less or equal to 1000 characters', // @translate
                                'properties_max_5000' => 'All properties less or equal to 5000 characters', // @translate
                            ],
                        ],
                    ],
                    'term_as_value' => true,
                ],
                'attributes' => [
                    'id' => 'translate_properties_exclude',
                    'class' => 'chosen-select',
                    'multiple' => true,
                    'data-placeholder' => 'Select properties…', // @translate
                ],
            ])
        ;
    }
}
