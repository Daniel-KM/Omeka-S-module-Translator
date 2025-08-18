<?php declare(strict_types=1);

namespace Translator\Form;

use Laminas\Form\Element;
use Laminas\Form\Form;

class ConfigForm extends Form
{
    public function init(): void
    {
        $this
            ->add([
                'name' => 'translator_deepl_api_key',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'DeepL api key for automatic translation', // @translate
                ],
            ])
        ;
    }
}
