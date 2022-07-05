<?php

namespace PowerComponents\LivewirePowerGrid\Helpers;

use Illuminate\Support\Arr;
use Illuminate\View\ComponentAttributeBag;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Themes\ThemeBase;

class Actions
{
    protected ComponentAttributeBag $componentBag;

    public array $parameters;

    protected Helpers $helperClass;

    public array $ruleRedirect = [];

    public bool $ruleDisabled;

    public bool $ruleHide;

    public array $ruleAttributes;

    public array $ruleEmit;

    public array $ruleEmitTo;

    public string $ruleCaption;

    public array $ruleBladeComponent;

    public bool $isButton = false;

    public bool $isRedirectable = false;

    public bool $isLinkeable = false;

    public ?string $bladeComponent = null;

    public ComponentAttributeBag $bladeComponentParams;

    protected array $attributes = [];

    public function __construct(
        public Button $action,
        public \Illuminate\Database\Eloquent\Model|\stdClass|null $row,
        public string|int $primaryKey,
        public ThemeBase $theme,
    ) {
        $this->componentBag = new ComponentAttributeBag();
        $this->helperClass  = new Helpers();

        if ($this->action->singleParam) {
            $this->parameters = $this->helperClass->makeActionParameter($this->action->param, $this->row);
        } else {
            $this->parameters = $this->helperClass->makeActionParameters($this->action->param, $this->row);
        }

        $this->actionRules();

        $this->emit();

        $this->emitTo();

        $this->openModal();

        $this->title();

        $this->caption();

        $this->bladeComponent();

        $this->disabled();

        $this->redirect();

        $this->toggleDetail();

        $this->attributes();

        if ($this->hasAttributesInComponentBag('wire:click')
            || $this->action->caption
            && blank($this->action->route)
            && blank($this->ruleRedirect)
        ) {
            $this->isButton = true;
        }
    }

    public function actionRules(): static
    {
        $rules = $this->helperClass->makeActionRules($this->action, $this->row);

        $this->ruleRedirect          = (array) data_get($rules, 'redirect', []);
        $this->ruleDisabled          = boolval(data_get($rules, 'disable', false));
        $this->ruleHide              = boolval(data_get($rules, 'hide', false));
        $this->ruleAttributes        = (array) (data_get($rules, 'setAttribute', []));
        $this->ruleBladeComponent    = (array) (data_get($rules, 'bladeComponent', []));
        $this->ruleEmit              = (array) data_get($rules, 'emit', []);
        $this->ruleEmitTo            = (array) data_get($rules, 'emitTo', []);
        $this->ruleCaption           = strval(data_get($rules, 'caption'));

        return $this;
    }

    public function attributes(): void
    {
        $class = filled($this->action->class) ? $this->action->class : $this->theme->actions->headerBtnClass;

        if (filled($this->ruleAttributes)) {
            $value = null;

            foreach ($this->ruleAttributes as $attribute) {
                if (is_string($attribute['value'])) {
                    $value = $attribute['value'];
                }

                if (is_array($attribute['value'])) {
                    if (is_array($attribute['value'][1])) {
                        $value = $attribute['value'][0] . '(' . json_encode($this->helperClass->makeActionParameters($attribute['value'][1], $this->row)) . ')';
                    } else {
                        $value = $attribute['value'][0] . '(' . $attribute['value'][1] . ')';
                    }
                }

                $this->componentBag = $this->componentBag->merge([$attribute['attribute'] => $value]);
            }
        }

        $this->componentBag = $this->componentBag->class($class);
    }

    private function hasAttributesInComponentBag(string $attribute): bool
    {
        $ruleExist = Arr::where(
            $this->ruleAttributes ?? [],
            function ($attributes) use ($attribute) {
                return $attributes['attribute'] === $attribute;
            }
        );

        return count($ruleExist) > 0 || $this->componentBag->has($attribute);
    }

    public function redirect(): void
    {
        if (blank($this->ruleRedirect)) {
            return;
        }

        $this->componentBag = $this->componentBag->merge([
            'href'   => $this->ruleRedirect['url'],
            'target' => $this->ruleRedirect['target'],
        ]);

        $this->isLinkeable = true;
        $this->isButton    = false;
    }

    public function emit(): void
    {
        if ((
            $this->hasAttributesInComponentBag('wire:click')
            || blank($this->action->event)
            || filled($this->action->to)
        ) && blank($this->ruleEmit)) {
            return;
        }

        $event = $this->ruleEmit['event'] ?? $this->action->event;

        $parameters = $this->parameters;

        if (isset($this->ruleEmit['params'])) {
            $parameters = $this->helperClass->makeActionParameters($this->ruleEmit['params'], $this->row);
        }

        $this->componentBag = $this->componentBag->merge([
            'wire:click' => '$emit("' . $event . '", ' . json_encode($parameters) . ')',
        ]);
    }

    public function emitTo(): void
    {
        if (($this->hasAttributesInComponentBag('wire:click')
            || blank($this->action->to)) && blank($this->ruleEmitTo)) {
            return;
        }

        $to  =  $this->ruleEmitTo['to'] ?? $this->action->to;

        $event = $this->ruleEmitTo['event'] ?? $this->action->event;

        $parameters = $this->parameters;

        if (isset($this->ruleEmitTo['params'])) {
            $parameters = $this->helperClass->makeActionParameters($this->ruleEmitTo['params'], $this->row);
        }

        $this->componentBag = $this->componentBag->merge([
            'wire:click' => '$emitTo("' . $to . '", "' . $event . '", ' . json_encode($parameters) . ')',
        ]);
    }

    public function openModal(): void
    {
        if ($this->hasAttributesInComponentBag('wire:click')
           || blank($this->action->view)
        || filled($this->ruleRedirect)) {
            return;
        }

        $this->componentBag = $this->componentBag->merge([
            'wire:click' => '$emit("openModal", "' . $this->action->view . '", ' . json_encode($this->parameters) . ')',
        ]);
    }

    public function toggleDetail(): void
    {
        if (!$this->action->toggleDetail) {
            return;
        }

        $this->componentBag = $this->componentBag->merge([
            'wire:click.prevent' => 'toggleDetail("' . $this->row->{$this->primaryKey} . '")',
        ]);
    }

    public function disabled(): void
    {
        if ($this->hasAttributesInComponentBag('disabled')) {
            return;
        }

        if (!$this->ruleDisabled) {
            return;
        }

        $this->componentBag = $this->componentBag->merge([
            'disabled' => 'disabled',
        ]);
    }

    public function title(): void
    {
        if ($this->hasAttributesInComponentBag('title')) {
            return;
        }

        $this->componentBag = $this->componentBag->merge([
            'title' => $this->action->tooltip,
        ]);
    }

    public function caption(): string
    {
        $this->isButton = true;

        return $this->ruleCaption ?: $this->action->caption;
    }

    public function bladeComponent(): void
    {
        $component = $this->action->bladeComponent;

        if (filled($this->ruleBladeComponent)) {
            $component     = $this->ruleBladeComponent['component'];
            $parameters    = $this->helperClass->makeActionParameters((array) data_get($this->ruleBladeComponent, 'params', []), $this->row);
        }

        $this->bladeComponentParams = new ComponentAttributeBag($parameters ?? $this->parameters);

        $this->bladeComponent = $component;
    }

    public function route(): void
    {
        if (!$this->action->route) {
            return;
        }

        $this->isRedirectable = true;
    }

    public function getAttributes(): ComponentAttributeBag
    {
        return $this->componentBag;
    }
}
