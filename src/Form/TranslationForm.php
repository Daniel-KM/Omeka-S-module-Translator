<?php declare(strict_types=1);

namespace Translate\Form;

use Laminas\Form\Element;
use Laminas\Form\Form;

class TranslationForm extends Form
{
    /**
     * @var \Translate\Api\Adapter\TranslationAdapter
     */
    protected $apiAdapterTranslate;

    public function init(): void
    {
        // TODO Validate unicity of string/lang/locale for translation (see module Table).

        $this
            ->setAttribute('id', 'translation-form')
            ->add([
                'name' => 'o:string',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'String', // @translate
                ],
                'attributes' => [
                    'id' => 'o-string',
                    'required' => true,
                ],
            ])
            ->add([
                'name' => 'o:lang',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Language', // @translate
                ],
                'attributes' => [
                    'id' => 'o-lang',
                    'required' => true,
                ],
            ])
            ->add([
                'name' => 'o:translation',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Translation', // @translate
                ],
                'attributes' => [
                    'id' => 'o-translation',
                    'required' => true,
                ],
            ])
            ->add([
                'name' => 'o:locale',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Locale', // @translate
                ],
                'attributes' => [
                    'id' => 'o-locale',
                    'required' => true,
                ],
            ])
            ->add([
                'name' => 'o:automatic',
                'type' => Element\Hidden::class,
                'attributes' => [
                    'id' => 'o-automatic',
                    'value' => '0',
                ],
            ])
            ->add([
                'name' => 'o:reviewed',
                'type' => Element\Hidden::class,
                'attributes' => [
                    'id' => 'o-reviewed',
                    'value' => '0',
                ],
            ])
        ;
    }
}
