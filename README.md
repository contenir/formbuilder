# contenir/formbuilder

Framework-agnostic form-builder engine for [Contenir CMS](https://github.com/contenir).

Provides the runtime-editable form-definition value objects, the registry of
field types, the curated validator vocabulary, the conditional-visibility
rule engine, and the build/submit services that turn a stored definition
into a working `Laminas\Form\Form` and a validated submission.

This package is the pure-PHP core. It has no opinion about how form
definitions are loaded (DB, JSON, hardcoded array — bring your own loader)
or how submissions are stored (registrar pattern). Adapter packages such
as `contenir/formbuilder-laminas-mvc` wire it into a host framework.

## Install

```bash
composer require contenir/formbuilder
```

Optional but commonly paired:

- [`contenir/storage`](https://github.com/contenir/storage) — required for
  the `file` field type, passed in to `FormSubmissionService`.

## Layout

```
src/
├── Definition/   value objects: FormDefinition, SectionDefinition,
│                 GroupDefinition, RowDefinition, FieldDefinition,
│                 ValidatorDefinition, NotificationDefinition,
│                 WebhookDefinition
├── FieldType/    FieldTypeRegistry + FieldTypeInterface + 17 built-in
│                 types (text, email, url, tel, number, date, time,
│                 datetime, select, multiselect, radio, checkbox,
│                 multicheckbox, textarea, file, hidden, content)
├── Validator/    ValidatorFactory — curated vocabulary mapping onto
│                 Laminas validators (StringLength, Between, Hostname,
│                 Regex, Identical, EmailAddress)
├── Conditional/  RuleEvaluator + ConditionalRulesParser
└── Service/      FormBuilderService, FormSubmissionService,
                  TokenReplacer, BuilderForm, SubmissionResult
```

## Usage

```php
use Contenir\FormBuilder\Definition\FormDefinition;
use Contenir\FormBuilder\FieldType\FieldTypeRegistry;
use Contenir\FormBuilder\Service\FormBuilderService;
use Contenir\FormBuilder\Service\FormSubmissionService;
use Contenir\FormBuilder\Validator\ValidatorFactory;

$registry = new FieldTypeRegistry();
$validators = new ValidatorFactory();

$builder = new FormBuilderService($registry, $validators);
$form    = $builder->build($definition);  // Laminas\Form\Form

$service = new FormSubmissionService($builder);
$service->attach($yourPersistenceObserver);
$result = $service->submit($definition, $_POST, $_FILES, [
    'ip'      => $_SERVER['REMOTE_ADDR'] ?? null,
    'user_id' => null,
    'meta'    => [],
]);
```

## License

MIT.