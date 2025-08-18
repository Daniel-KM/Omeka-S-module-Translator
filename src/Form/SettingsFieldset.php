<?php declare(strict_types=1);

namespace Translator\Form;

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
        'translator' => 'Translate', // @translate
    ];

    public function init(): void
    {
        $this
            ->setAttribute('id', 'translator')
            ->setOption('element_groups', $this->elementGroups)

            ->add([
                'name' => 'translator_lang_source_default',
                'type' => CommonElement\OptionalSelect::class,
                'options' => [
                    'element_group' => 'translator',
                    'label' => 'Default language for values without any one', // @translate
                    'info' => 'The language should be a 2-letter iso code (ISO 3166-1) supported by the translation service.', // @translate
                    'documentation' => 'https://developers.deepl.com/docs/getting-started/supported-languages',
                    'value_options' => [
                        '' => 'Automatic', // @translate
                        'skip' => 'Skip', // @translate
                    ] + \Translator\Module::$langsSupportedInput,
                ],
                'attributes' => [
                    'id' => 'translator_lang_source_default',
                    'required' => false,
                    'class' => 'chosen-select',
                    'data-placeholder' => 'Select language…', // @translate
                ],
            ])
            ->add([
                'name' => 'translator_lang_pairs',
                'type' => OmekaElement\ArrayTextarea::class,
                'options' => [
                    'element_group' => 'translator',
                    'label' => 'Target languages or pairs of languages to translate', // @translate
                    'info' => 'The source language will be automatically defined when not set. For pairs, separate source and target with a "=", one by line. The source language should be a 2-letter iso code (ISO 3166-1) supported by the translation service. The target language may have the localization code if supported.', // @translate
                    'documentation' => 'https://developers.deepl.com/docs/getting-started/supported-languages',
                    // Most of the time, the same source language is used for
                    // multiple targets so don't use an associative array.
                    'as_key_value' => false,
                ],
                'attributes' => [
                    'id' => 'translator_lang_pairs',
                    'required' => false,
                    'placeholder' => <<<'TXT'
                        de
                        en-gb
                        fr = pt-br
                        TXT,
                ],
            ])

            ->add([
                'name' => 'translator_properties_include',
                'type' => CommonElement\OptionalPropertySelect::class,
                'options' => [
                    'element_group' => 'translator',
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
                    'id' => 'translator_properties_include',
                    'class' => 'chosen-select',
                    'multiple' => true,
                    'data-placeholder' => 'Select properties…', // @translate
                ],
            ])
            ->add([
                'name' => 'translator_properties_exclude',
                'type' => CommonElement\OptionalPropertySelect::class,
                'options' => [
                    'element_group' => 'translator',
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
                    'id' => 'translator_properties_exclude',
                    'class' => 'chosen-select',
                    'multiple' => true,
                    'data-placeholder' => 'Select properties…', // @translate
                ],
            ])
        ;
    }
}
