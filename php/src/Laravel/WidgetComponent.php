<?php

declare(strict_types=1);

namespace SimplyBoost\Identity\Laravel;

use Illuminate\Support\HtmlString;
use Illuminate\View\Component;

/**
 * <x-simplyboost-widget /> (or <x-simplyboost-widget :nonce="$nonce" />):
 * the widget, signed for the logged-in user, anonymous for guests.
 */
class WidgetComponent extends Component
{
    /** @var string|null */
    public $nonce;

    public function __construct(?string $nonce = null)
    {
        $this->nonce = $nonce;
    }

    public function render(): HtmlString
    {
        // HtmlString, not a string: a string returned here would be compiled
        // as a Blade template, and the user's name is part of the output.
        return new HtmlString(app(WidgetRenderer::class)->snippet($this->nonce));
    }
}
