<?php

declare(strict_types=1);

namespace Contenir\FormBuilder\Service;

use Laminas\Form\Form;

/**
 * Laminas\Form subclass exposing a public {@see $registry} property and acting
 * as the SplSubject passed to registrar observers.
 *
 * The {@see FormSubmissionService} piggybacks submission context onto the form
 * so registrars don't need to receive it as separate arguments. Implements
 * SplSubject so the form can be passed directly to {@see \SplObserver::update()}
 * — the {@see FormSubmissionService} owns observer dispatch, so the methods
 * here are sufficient for the type contract.
 */
class BuilderForm extends Form implements \SplSubject
{
    public ?\ArrayObject $registry = null;

    /** @var list<\SplObserver> */
    private array $observers = [];

    public function attach(\SplObserver $observer): void
    {
        $this->observers[] = $observer;
    }

    public function detach(\SplObserver $observer): void
    {
        foreach ($this->observers as $index => $existing) {
            if ($existing === $observer) {
                unset($this->observers[$index]);
                $this->observers = array_values($this->observers);
                return;
            }
        }
    }

    public function notify(): void
    {
        foreach ($this->observers as $observer) {
            $observer->update($this);
        }
    }
}
