<?php declare(strict_types=1);

namespace Translate\Form;

use Common\Form\Element as CommonElement;
use Laminas\Form\Element;
use Laminas\Form\Form;

class TranslateForm extends Form
{
    /**
     * @var \Translate\Api\Adapter\TranslateAdapter
     */
    protected $apiAdapterTranslate;

    public function init(): void
    {
        // TODO Validate unicity of string/lang source/lang target for translation (see module Table).

        $this
            ->setAttribute('id', 'translate-form')

            ->add([
                'name' => 'o:lang_source',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Language source', // @translate
                ],
                'attributes' => [
                    'id' => 'o-lang-source',
                    'required' => true,
                ],
            ])
            ->add([
                'name' => 'o:lang_target',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Language target', // @translate
                ],
                'attributes' => [
                    'id' => 'o-lang-target',
                    'required' => true,
                ],
            ])
            ->add([
                'name' => 'o:automatic',
                'type' => CommonElement\OptionalCheckbox::class,
                'options' => [
                    'label' => 'Is automatic', // @translate
                ],
                'attributes' => [
                    'id' => 'o-automatic',
                ],
            ])
            ->add([
                'name' => 'o:reviewed',
                'type' => CommonElement\OptionalCheckbox::class,
                'options' => [
                    'label' => 'Is reviewed', // @translate
                ],
                'attributes' => [
                    'id' => 'o-reviewed',
                ],
            ])
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
        ;
    }
}
