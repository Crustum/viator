<?php
declare(strict_types=1);

namespace TestApp\Form;

use Cake\Form\Form;
use Cake\Validation\Validator;
use TestApp\Enum\QuotedEnum;

/**
 * Store-post form mirroring workbench StorePostRequest rules.
 */
class StorePostForm extends Form
{
    /**
     * @param \Cake\Validation\Validator $validator Validator
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->requirePresence('title')
            ->notEmptyString('title')
            ->add('title', 'maxLength', ['rule' => ['maxLength', 255]]);

        $validator
            ->requirePresence('status')
            ->add('status', 'inList', ['rule' => ['inList', array_column(QuotedEnum::cases(), 'value')]]);

        $validator
            ->requirePresence('format')
            ->add('format', 'enum', ['rule' => ['enum', QuotedEnum::class]]);

        $validator
            ->requirePresence('body')
            ->notEmptyString('body');

        $validator
            ->allowEmptyString('excerpt');

        $validator
            ->allowEmptyString('author_email')
            ->add('author_email', 'email', ['rule' => 'email']);

        $validator
            ->requirePresence('meta.description')
            ->add('meta.description', 'maxLength', ['rule' => ['maxLength', 500]]);

        $validator
            ->requirePresence('age')
            ->add('age', 'integer', ['rule' => 'integer']);

        $validator
            ->allowEmptyFile('avatar');

        $validator
            ->allowEmptyFile('cover')
            ->add('cover', 'image', ['rule' => 'image']);

        return $validator;
    }
}
