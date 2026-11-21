<?php
declare(strict_types=1);

namespace TestApp\Form;

use Cake\Form\Form;
use Cake\Validation\Validator;

/**
 * Contact form sharing the `TestApp.Form` namespace with StorePostForm.
 *
 * Exists to prove forms merge into one namespace block instead of
 * repeating the namespace per form.
 */
class ContactForm extends Form
{
    /**
     * @param \Cake\Validation\Validator $validator Validator
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->requirePresence('name')
            ->notEmptyString('name');

        $validator
            ->requirePresence('message')
            ->notEmptyString('message');

        return $validator;
    }
}
